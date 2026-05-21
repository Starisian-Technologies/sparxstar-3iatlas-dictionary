/**
 * ListenWrite — Game 4.1: Audio → typed word.
 *
 * The most important game.  Player hears the word they already know and
 * writes it using keyboard + AccessoryBar.  Up to 3 attempts with
 * progressive letter reveals on wrong answers.
 *
 * Requires AccessoryBar (rendered by GameShell when this game is active).
 * Data requirement: /game-set?include_audio=true — words without audio are
 * excluded upstream in GameShell word filtering.
 */
import React, { useState, useCallback, useRef, useEffect } from 'react';
import { Volume2, CheckCircle } from 'lucide-react';

const XP_CORRECT = 10;

/** Build a display hint with revealed prefix and blanks for the rest. */
function buildHint( target, revealed ) {
    return target
        .split( '' )
        .map( ( char, idx ) => ( idx < revealed ? char : '_' ) )
        .join( ' ' );
}

export default function ListenWrite( { word, wordIndex, totalWords, onResult } ) {
    const [ input,   setInput   ] = useState( '' );
    const [ attempt, setAttempt ] = useState( 0 );
    const [ status,  setStatus  ] = useState( 'idle' ); // idle | wrong | correct | revealed
    const inputRef = useRef( null );
    const audioRef = useRef( null );

    // Reset and auto-play audio when word changes
    useEffect( () => {
        setInput( '' );
        setAttempt( 0 );
        setStatus( 'idle' );

        if ( word.audio_url ) {
            // Small delay so component has rendered
            const timer = setTimeout( () => {
                const audio = new Audio( word.audio_url );
                audioRef.current = audio;
                audio.play().catch( () => {} );
            }, 300 );
            return () => clearTimeout( timer );
        }
        return undefined;
    }, [ word.headword, word.audio_url ] );

    useEffect( () => {
        if ( status === 'idle' || status === 'wrong' ) {
            inputRef.current?.focus();
        }
    }, [ status, word.headword ] );

    const playAudio = useCallback( () => {
        if ( word.audio_url ) {
            new Audio( word.audio_url ).play().catch( () => {} );
        }
    }, [ word.audio_url ] );

    const handleSubmit = useCallback( () => {
        if ( status === 'correct' || status === 'revealed' ) return;

        const trimmed = input.trim();
        if ( trimmed.toLowerCase() === word.headword.toLowerCase() ) {
            setStatus( 'correct' );
            const xp = attempt === 0 ? XP_CORRECT : Math.max( 2, XP_CORRECT - attempt * 3 );
            setTimeout( () => onResult( 'correct', attempt + 1, xp ), 1500 );
        } else {
            const nextAttempt = attempt + 1;
            if ( nextAttempt >= 3 ) {
                setStatus( 'revealed' );
                setTimeout( () => onResult( 'learning', 3, 0 ), 1500 );
            } else {
                setAttempt( nextAttempt );
                setStatus( 'wrong' );
                setInput( '' );
                setTimeout( () => setStatus( 'idle' ), 700 );
            }
        }
    }, [ input, word.headword, attempt, status, onResult ] );

    const handleKeyDown = useCallback( ( e ) => {
        if ( e.key === 'Enter' ) {
            e.preventDefault();
            handleSubmit();
        }
    }, [ handleSubmit ] );

    const target = word.headword;
    const hint   = buildHint( target, attempt );

    // Build blank tiles for word length indicator
    const blankTiles = target.split( '' );

    return (
        <div className="flex flex-col h-full p-6 max-w-sm mx-auto pb-24">
            {/* Progress */}
            <div className="w-full flex items-center gap-3 mb-4">
                <div className="flex-1 bg-gray-200 dark:bg-gray-700 rounded-full h-2">
                    <div
                        className="h-2 rounded-full transition-all"
                        style={ {
                            background: 'linear-gradient(90deg,#E91E8C,#7B3FA0)',
                            width: `${totalWords > 0 ? ( wordIndex / totalWords ) * 100 : 0}%`,
                        } }
                    />
                </div>
                <span className="text-xs text-gray-400 shrink-0">{ wordIndex } / { totalWords }</span>
            </div>

            {/* Audio play button — central focus */}
            <div className="flex flex-col items-center my-8">
                <button
                    type="button"
                    onClick={ playAudio }
                    className="w-24 h-24 rounded-full flex items-center justify-center shadow-xl transition-all active:scale-95"
                    style={ { background: 'linear-gradient(135deg,#E91E8C,#7B3FA0)' } }
                    aria-label="Play word audio"
                >
                    <Volume2 size={ 40 } color="white" aria-hidden="true" />
                </button>
                <p className="text-gray-500 text-sm mt-3">Tap to hear the word</p>
            </div>

            {/* IPA */}
            { word.ipa && (
                <p className="text-center text-base font-mono text-gray-400 mb-4">
                    /{ word.ipa }/
                </p>
            ) }

            {/* Blank tiles — word length indicator */}
            <div className="flex flex-wrap gap-1.5 justify-center mb-6">
                { blankTiles.map( ( char, idx ) => {
                    const isLetter   = /\p{L}/u.test( char );
                    const isRevealed = status === 'correct' || status === 'revealed' || idx < attempt;
                    return (
                        <div
                            key={ idx }
                            className="w-8 h-10 rounded-lg border-2 flex items-center justify-center font-bold text-base"
                            style={ {
                                borderColor: status === 'correct' ? '#22c55e'
                                    : isRevealed ? '#E91E8C' : '#d1d5db',
                                background: status === 'correct' ? '#dcfce7'
                                    : isRevealed ? '#FCE4F3' : 'white',
                            } }
                            aria-label={ isRevealed ? char : 'Hidden' }
                        >
                            { isLetter && isRevealed ? char : isLetter ? '' : char }
                        </div>
                    );
                } ) }
            </div>

            {/* Letter hint for wrong attempts */}
            { attempt > 0 && status !== 'correct' && status !== 'revealed' && (
                <p className="text-center font-mono text-lg tracking-widest text-gray-600 dark:text-gray-400 mb-3">
                    { hint }
                </p>
            ) }

            {/* Correct / revealed states */}
            { status === 'correct' && (
                <div className="flex flex-col items-center gap-1 mb-3">
                    <div className="flex items-center gap-2 text-green-600 font-bold text-lg">
                        <CheckCircle size={ 22 } aria-hidden="true" />
                        <span>{ target }</span>
                    </div>
                    <p className="text-sm text-gray-500">
                        { word.translation_en }
                    </p>
                    <p className="text-sm font-semibold text-green-600">
                        +{ attempt === 0 ? XP_CORRECT : Math.max( 2, XP_CORRECT - attempt * 3 ) } XP
                    </p>
                </div>
            ) }
            { status === 'revealed' && (
                <div className="text-center mb-3">
                    <p className="text-gray-500 text-sm mb-1">Still learning</p>
                    <p className="text-xl font-bold text-gray-900 dark:text-gray-100">{ target }</p>
                    <p className="text-sm text-gray-500 mt-1">{ word.translation_en }</p>
                </div>
            ) }

            {/* Input */}
            { status !== 'correct' && status !== 'revealed' && (
                <>
                    <div className="flex gap-2 mt-auto">
                        <input
                            ref={ inputRef }
                            type="text"
                            value={ input }
                            onChange={ ( e ) => setInput( e.target.value ) }
                            onKeyDown={ handleKeyDown }
                            placeholder="Type what you hear…"
                            className="flex-1 px-4 py-3 rounded-xl border-2 text-base text-gray-900 dark:text-gray-100 dark:bg-gray-800 focus:outline-none transition-colors"
                            style={ {
                                borderColor: status === 'wrong' ? '#ef4444' : '#e5e7eb',
                            } }
                            aria-label="Type the word you hear"
                            autoCapitalize="none"
                            autoCorrect="off"
                            spellCheck="false"
                        />
                        <button
                            type="button"
                            onClick={ handleSubmit }
                            className="px-5 py-3 rounded-xl font-bold text-white transition-opacity active:opacity-80"
                            style={ { background: '#E91E8C' } }
                            aria-label="Submit answer"
                        >
                            Go
                        </button>
                    </div>
                    { status === 'wrong' && (
                        <p className="text-center text-sm text-red-500 mt-2">
                            { attempt >= 2 ? 'Last try!' : 'Try again — first letter revealed above' }
                        </p>
                    ) }
                </>
            ) }
        </div>
    );
}
