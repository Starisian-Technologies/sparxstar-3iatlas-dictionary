/**
 * GameShell — Play tab container.
 *
 * Handles:
 *   - Session setup (language, domain, game type, word count selectors)
 *   - Fetching + caching the game-set via useGameSet
 *   - Orchestrating game progression via useGameSession
 *   - Progress sync via useProgressSync
 *   - Rendering the active game component + AccessoryBar for typed games
 *   - Session complete summary screen
 *
 * Games are driven by the spec's §4–6.
 */
import React, { useState, useCallback, useEffect, useMemo } from 'react';
import { Loader2, ChevronLeft, Zap } from 'lucide-react';

import { useGameSet }       from '../hooks/useGameSet.js';
import { useGameSession }   from '../hooks/useGameSession.js';
import { useProgressSync }  from '../hooks/useProgressSync.js';
import AccessoryBar         from './AccessoryBar.jsx';
import SessionComplete      from './SessionComplete.jsx';

import ListenWrite       from './games/ListenWrite.jsx';
import ArrangeWord       from './games/ArrangeWord.jsx';
import MeaningMatch      from './games/MeaningMatch.jsx';
import CompleteSentence  from './games/CompleteSentence.jsx';
import LetterReveal      from './games/LetterReveal.jsx';
import DomainFlash       from './games/DomainFlash.jsx';

// ---------------------------------------------------------------------------
// Constants
// ---------------------------------------------------------------------------

const GAME_TYPES = [
    { id: 'domain_flash',       label: 'Domain Flash',          emoji: '🃏', desc: 'Flashcard recall — flip to reveal' },
    { id: 'meaning_match',      label: 'Meaning Match',         emoji: '🔍', desc: 'Choose the correct meaning' },
    { id: 'arrange_word',       label: 'Arrange the Word',      emoji: '🧩', desc: 'Tap scrambled tiles into order' },
    { id: 'letter_reveal',      label: 'Letter Reveal',         emoji: '🔡', desc: 'Guess letters to reveal the word' },
    { id: 'complete_sentence',  label: 'Complete the Sentence', emoji: '✍️', desc: 'Fill in the missing word' },
    { id: 'listen_write',       label: 'Listen & Write',        emoji: '🎧', desc: 'Hear the word, write it down' },
];

const WORD_COUNTS = [ 10, 20, 30 ];

/** Games requiring the AccessoryBar. */
const ACCESSORY_BAR_GAMES = new Set( [ 'listen_write', 'complete_sentence' ] );

/** Games requiring audio URLs. */
const AUDIO_REQUIRED_GAMES = new Set( [ 'listen_write' ] );

/** Games requiring example sentences. */
const SENTENCE_REQUIRED_GAMES = new Set( [ 'complete_sentence' ] );

// ---------------------------------------------------------------------------
// MyCred event builders
// ---------------------------------------------------------------------------

function buildProgressEvents( session ) {
    const { results, gameType, domain, xpEarned: _ } = session;
    const events = [];

    results.forEach( ( r ) => {
        if ( r.outcome === 'correct' ) {
            events.push( { type: 'aiwa_game_word_correct',  word_uuid: r.wordUuid, game: gameType, ts: Date.now() } );
            if ( gameType === 'listen_write' ) {
                events.push( { type: 'aiwa_game_listen_write',  word_uuid: r.wordUuid, ts: Date.now() } );
            }
            if ( gameType === 'complete_sentence' ) {
                events.push( { type: 'aiwa_game_sentence_correct', word_uuid: r.wordUuid, ts: Date.now() } );
            }
        }
    } );

    // Session complete
    events.push( { type: 'aiwa_game_session_complete', domain: domain || '', ts: Date.now() } );

    // Streak check: 3+ in a row
    let streak = 0;
    for ( const r of results ) {
        if ( r.outcome === 'correct' ) {
            streak++;
            if ( streak >= 3 ) {
                events.push( { type: 'aiwa_game_streak_3', ts: Date.now() } );
                streak = 0;
            }
        } else {
            streak = 0;
        }
    }

    return events;
}

// ---------------------------------------------------------------------------
// Setup screen
// ---------------------------------------------------------------------------

function SetupScreen( {
    languages,
    langSource,
    onSetLang,
    domains,
    domainsLoading,
    selectedDomain,
    onSetDomain,
    gameType,
    onSetGameType,
    wordCount,
    onSetWordCount,
    onStart,
    seedWord,
    seedDomain,
} ) {
    return (
        <div className="flex flex-col gap-5 p-5 overflow-y-auto flex-1">
            <h2 className="text-xl font-bold text-gray-900 dark:text-gray-100">
                Play ▸ Set up your session
            </h2>

            {/* Language selector */}
            <section>
                <h3 className="text-xs font-bold uppercase tracking-wider text-gray-400 mb-2">
                    Language
                </h3>
                <div className="flex flex-wrap gap-2">
                    { languages.map( ( lang ) => (
                        <button
                            key={ lang.slug }
                            type="button"
                            onClick={ () => onSetLang( lang.slug ) }
                            className="px-4 py-2 rounded-full text-sm font-medium border-2 transition-colors"
                            style={
                                langSource === lang.slug
                                    ? { background: '#E91E8C', color: 'white', borderColor: 'transparent' }
                                    : { borderColor: '#e5e7eb', color: '#374151' }
                            }
                        >
                            { lang.name }
                        </button>
                    ) ) }
                    { ! languages.length && (
                        <p className="text-gray-400 text-sm italic">Loading languages…</p>
                    ) }
                </div>
            </section>

            {/* Domain selector */}
            { langSource && (
                <section>
                    <h3 className="text-xs font-bold uppercase tracking-wider text-gray-400 mb-2">
                        Domain <span className="text-gray-300">(optional)</span>
                    </h3>
                    { domainsLoading ? (
                        <Loader2 className="animate-spin" style={ { color: '#E91E8C' } } size={ 18 } />
                    ) : (
                        <div className="flex flex-wrap gap-2">
                            <button
                                type="button"
                                onClick={ () => onSetDomain( '' ) }
                                className="px-4 py-2 rounded-full text-sm font-medium border-2 transition-colors"
                                style={
                                    selectedDomain === ''
                                        ? { background: '#7B3FA0', color: 'white', borderColor: 'transparent' }
                                        : { borderColor: '#e5e7eb', color: '#374151' }
                                }
                            >
                                All domains
                            </button>
                            { domains.map( ( d ) => (
                                <button
                                    key={ d.slug }
                                    type="button"
                                    onClick={ () => onSetDomain( d.slug ) }
                                    className="px-4 py-2 rounded-full text-sm font-medium border-2 transition-colors"
                                    style={
                                        selectedDomain === d.slug
                                            ? { background: '#7B3FA0', color: 'white', borderColor: 'transparent' }
                                            : { borderColor: '#e5e7eb', color: '#374151' }
                                    }
                                >
                                    { d.name }
                                    { d.count > 0 && (
                                        <span style={ { marginLeft: 4, fontSize: '0.7rem', opacity: 0.7 } }>
                                            ({ d.count })
                                        </span>
                                    ) }
                                </button>
                            ) ) }
                        </div>
                    ) }
                </section>
            ) }

            {/* Game type selector */}
            <section>
                <h3 className="text-xs font-bold uppercase tracking-wider text-gray-400 mb-2">
                    Game
                </h3>
                <div className="flex flex-col gap-2">
                    { GAME_TYPES.map( ( g ) => (
                        <button
                            key={ g.id }
                            type="button"
                            onClick={ () => onSetGameType( g.id ) }
                            className="flex items-center gap-3 p-3 rounded-2xl border-2 text-left transition-all"
                            style={
                                gameType === g.id
                                    ? { borderColor: '#E91E8C', background: '#FCE4F3' }
                                    : { borderColor: '#f3f4f6', background: 'white' }
                            }
                        >
                            <span className="text-2xl" aria-hidden="true">{ g.emoji }</span>
                            <div>
                                <p className="font-semibold text-sm text-gray-900">{ g.label }</p>
                                <p className="text-xs text-gray-400">{ g.desc }</p>
                            </div>
                        </button>
                    ) ) }
                </div>
            </section>

            {/* Word count */}
            <section>
                <h3 className="text-xs font-bold uppercase tracking-wider text-gray-400 mb-2">
                    Words per session
                </h3>
                <div className="flex gap-2">
                    { WORD_COUNTS.map( ( n ) => (
                        <button
                            key={ n }
                            type="button"
                            onClick={ () => onSetWordCount( n ) }
                            className="flex-1 py-2 rounded-full font-bold border-2 text-sm transition-colors"
                            style={
                                wordCount === n
                                    ? { background: '#E91E8C', color: 'white', borderColor: 'transparent' }
                                    : { borderColor: '#e5e7eb', color: '#374151' }
                            }
                        >
                            { n }
                        </button>
                    ) ) }
                </div>
            </section>

            {/* Start button */}
            <button
                type="button"
                onClick={ onStart }
                disabled={ ! langSource }
                className="w-full py-4 rounded-2xl font-bold text-lg text-white transition-opacity disabled:opacity-40"
                style={ { background: 'linear-gradient(135deg,#E91E8C,#7B3FA0)' } }
            >
                Start session
            </button>
        </div>
    );
}

// ---------------------------------------------------------------------------
// GameShell
// ---------------------------------------------------------------------------

export default function GameShell( {
    languages,
    initialLangSource,
    initialDomain,
    seedWord,
    onBrowseDomain,
} ) {
    const [ langSource,     setLangSource     ] = useState( initialLangSource || '' );
    const [ selectedDomain, setSelectedDomain ] = useState( initialDomain    || '' );
    const [ gameType,       setGameType       ] = useState( 'domain_flash' );
    const [ wordCount,      setWordCount      ] = useState( 20 );
    const [ phase,          setPhase          ] = useState( 'setup' ); // setup | loading | playing | complete
    const [ domains,        setDomains        ] = useState( [] );
    const [ domainsLoading, setDomainsLoading ] = useState( false );

    const REST_URL =
        ( window.sparxstarDictionarySettings && window.sparxstarDictionarySettings.restUrl ) ||
        '/wp-json/sparxstar/v1/dictionary';

    // Fetch domains when language changes
    useEffect( () => {
        if ( ! langSource ) return;
        setDomainsLoading( true );
        fetch( `${REST_URL}/domains?lang_source=${encodeURIComponent( langSource )}` )
            .then( ( r ) => r.ok ? r.json() : null )
            .then( ( json ) => {
                if ( json?.success && Array.isArray( json.data?.domains ) ) {
                    setDomains( json.data.domains );
                }
            } )
            .catch( () => {} )
            .finally( () => setDomainsLoading( false ) );
    }, [ langSource, REST_URL ] );

    const includeAudio = AUDIO_REQUIRED_GAMES.has( gameType );

    const { words: gameWords, loading: wordsLoading, error: wordsError } = useGameSet( {
        langSource,
        domain:        selectedDomain,
        limit:         50, // Fetch max; we slice to wordCount on start
        includeAudio,
        enabled:       phase === 'loading',
    } );

    const {
        session,
        loading: sessionLoading,
        currentWord,
        isDone,
        startSession,
        recordResult,
        completeSession,
        clearSession,
    } = useGameSession();

    const { enqueue } = useProgressSync();

    // When words load, kick off the session
    useEffect( () => {
        if ( phase !== 'loading' || wordsLoading ) return;
        if ( wordsError || ! gameWords.length ) {
            setPhase( 'setup' );
            return;
        }

        // Filter words based on game requirements
        let eligible = gameWords;
        if ( AUDIO_REQUIRED_GAMES.has( gameType ) ) {
            eligible = eligible.filter( ( w ) => w.audio_url );
        }
        if ( SENTENCE_REQUIRED_GAMES.has( gameType ) ) {
            eligible = eligible.filter( ( w ) => w.example?.sentence );
        }

        const slice = eligible.slice( 0, wordCount );
        if ( ! slice.length ) {
            setPhase( 'setup' );
            return;
        }

        startSession( { gameType, langSource, domain: selectedDomain, words: slice } );
        setPhase( 'playing' );
    }, [ phase, wordsLoading, wordsError, gameWords, gameType, wordCount, langSource, selectedDomain, startSession ] );

    // Detect session completion
    useEffect( () => {
        if ( phase !== 'playing' || ! isDone ) return;
        completeSession();
        // Enqueue progress events
        if ( session ) {
            const events = buildProgressEvents( session );
            enqueue( events );
        }
        setPhase( 'complete' );
    }, [ isDone, phase, session, completeSession, enqueue ] );

    const handleStart = useCallback( () => {
        setPhase( 'loading' );
    }, [] );

    const handlePlayAgain = useCallback( () => {
        clearSession();
        setPhase( 'setup' );
    }, [ clearSession ] );

    const handlePracticeMissed = useCallback( () => {
        if ( ! session ) return;
        const missedUuids = new Set(
            session.results.filter( ( r ) => r.outcome === 'learning' ).map( ( r ) => r.wordUuid ),
        );
        const missedWords = session.words.filter( ( w ) => missedUuids.has( w.uuid ) );
        if ( ! missedWords.length ) {
            setPhase( 'setup' );
            return;
        }
        startSession( { gameType, langSource, domain: selectedDomain, words: missedWords } );
        setPhase( 'playing' );
    }, [ session, gameType, langSource, selectedDomain, startSession ] );

    const handleBrowseDomain = useCallback( () => {
        onBrowseDomain && onBrowseDomain( selectedDomain );
    }, [ onBrowseDomain, selectedDomain ] );

    // Memoize all game words for MeaningMatch distractors
    const allGameWords = session?.words || [];

    const needsAccessoryBar = ACCESSORY_BAR_GAMES.has( gameType ) && phase === 'playing';

    // -------------------------------------------------------------------------
    // Render
    // -------------------------------------------------------------------------

    if ( phase === 'setup' ) {
        return (
            <div className="flex flex-col h-full overflow-hidden">
                <SetupScreen
                    languages={ languages }
                    langSource={ langSource }
                    onSetLang={ setLangSource }
                    domains={ domains }
                    domainsLoading={ domainsLoading }
                    selectedDomain={ selectedDomain }
                    onSetDomain={ setSelectedDomain }
                    gameType={ gameType }
                    onSetGameType={ setGameType }
                    wordCount={ wordCount }
                    onSetWordCount={ setWordCount }
                    onStart={ handleStart }
                    seedWord={ seedWord }
                    seedDomain={ initialDomain }
                />
            </div>
        );
    }

    if ( phase === 'loading' || wordsLoading || sessionLoading ) {
        return (
            <div className="flex flex-col h-full items-center justify-center gap-4">
                <Loader2 className="animate-spin" style={ { color: '#E91E8C' } } size={ 44 } />
                <p className="text-gray-500 text-sm">Loading words&hellip;</p>
            </div>
        );
    }

    if ( phase === 'complete' && session ) {
        return (
            <div className="flex flex-col h-full overflow-y-auto">
                <SessionComplete
                    session={ session }
                    onPlayAgain={ handlePlayAgain }
                    onPracticeMissed={ handlePracticeMissed }
                    onBrowseDomain={ handleBrowseDomain }
                />
            </div>
        );
    }

    if ( phase !== 'playing' || ! currentWord ) {
        return null;
    }

    const wordIndex  = ( session?.currentIndex ?? 0 );
    const totalWords = session?.words.length ?? 0;

    const gameProps = {
        word:        currentWord,
        wordIndex,
        totalWords,
        onResult:    recordResult,
        allWords:    allGameWords,
    };

    const GameComponent = {
        listen_write:       ListenWrite,
        arrange_word:       ArrangeWord,
        meaning_match:      MeaningMatch,
        complete_sentence:  CompleteSentence,
        letter_reveal:      LetterReveal,
        domain_flash:       DomainFlash,
    }[ gameType ] || DomainFlash;

    return (
        <div className="flex flex-col h-full overflow-hidden relative">
            {/* In-game header */}
            <div
                className="shrink-0 flex items-center gap-3 px-4 py-3 border-b border-gray-100 dark:border-gray-800 bg-white dark:bg-gray-900"
            >
                <button
                    type="button"
                    onClick={ handlePlayAgain }
                    className="p-1.5 rounded-full hover:bg-gray-100 dark:hover:bg-gray-800 transition-colors"
                    aria-label="Exit game"
                >
                    <ChevronLeft size={ 20 } className="text-gray-500" aria-hidden="true" />
                </button>
                <span className="font-semibold text-sm text-gray-700 dark:text-gray-300 flex-1">
                    { GAME_TYPES.find( ( g ) => g.id === gameType )?.label }
                </span>
                <span className="flex items-center gap-1 text-amber-500 font-bold text-sm">
                    <Zap size={ 14 } aria-hidden="true" />
                    { session?.xpEarned ?? 0 } XP
                </span>
            </div>

            {/* Game body */}
            <div className="flex-1 overflow-y-auto">
                <GameComponent { ...gameProps } />
            </div>

            {/* AccessoryBar for typed games */}
            { needsAccessoryBar && <AccessoryBar /> }
        </div>
    );
}
