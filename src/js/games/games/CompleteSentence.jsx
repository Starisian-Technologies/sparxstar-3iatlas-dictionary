/**
 * CompleteSentence — Game 4.4: Example sentence with headword blanked.
 *
 * Requires AccessoryBar (rendered in GameShell when this game is active).
 * Up to 3 attempts; each wrong attempt reveals the next letter.
 */
import React, { useState, useCallback, useRef, useEffect } from 'react';
import { CheckCircle } from 'lucide-react';

const XP_CORRECT = 8;

/** Build a hint showing revealed prefix and blanks. */
function buildHint( target, revealed ) {
    return target
        .split( '' )
        .map( ( char, idx ) => ( idx < revealed ? char : '_' ) )
        .join( ' ' );
}

export default function CompleteSentence( { word, wordIndex, totalWords, onResult } ) {
    const target   = word.headword;
    const example  = word.example;

    const [ input,   setInput   ] = useState( '' );
    const [ attempt, setAttempt ] = useState( 0 ); // 0, 1, 2
    const [ status,  setStatus  ] = useState( 'idle' ); // idle | wrong | correct | revealed
    const inputRef = useRef( null );

    useEffect( () => {
        setInput( '' );
        setAttempt( 0 );
        setStatus( 'idle' );
    }, [ word.headword ] );

    useEffect( () => {
        // Focus input on mount and word change
        inputRef.current?.focus();
    }, [ word.headword, status ] );

    const handleSubmit = useCallback( () => {
        if ( status === 'correct' || status === 'revealed' ) return;

        const trimmed = input.trim();
        if ( trimmed.toLowerCase() === target.toLowerCase() ) {
            setStatus( 'correct' );
            const xp = attempt === 0 ? XP_CORRECT : Math.max( 1, XP_CORRECT - attempt * 2 );
            setTimeout( () => onResult( 'correct', attempt + 1, xp ), 1000 );
        } else {
            const nextAttempt = attempt + 1;
            if ( nextAttempt >= 3 ) {
                // Third wrong: show full word
                setStatus( 'revealed' );
                setTimeout( () => onResult( 'learning', 3, 0 ), 1500 );
            } else {
                setAttempt( nextAttempt );
                setStatus( 'wrong' );
                setInput( '' );
                setTimeout( () => setStatus( 'idle' ), 600 );
            }
        }
    }, [ input, target, attempt, status, onResult ] );

    const handleKeyDown = useCallback( ( e ) => {
        if ( e.key === 'Enter' ) {
            e.preventDefault();
            handleSubmit();
        }
    }, [ handleSubmit ] );

    // Build displayed sentence
    const sentence = example?.sentence || '';
    const displaySentence = sentence.replace(
        new RegExp( target.replace( /[.*+?^${}()|[\]\\]/g, '\\$&' ), 'i' ),
        '______',
    );

    const hint = buildHint( target, attempt );

    return (
        <div className="flex flex-col h-full p-6 max-w-sm mx-auto pb-20">
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

            {/* Domain + translation hint */}
            { word.domain && (
                <span
                    className="inline-block self-start mb-2 px-3 py-1 rounded-full text-xs font-semibold"
                    style={ { background: '#EDE7F6', color: '#7B3FA0' } }
                >
                    { word.domain }
                </span>
            ) }
            <p className="text-base font-medium text-gray-600 dark:text-gray-400 mb-4">
                { word.translation_en }
            </p>

            {/* Sentence with blank */}
            { sentence ? (
                <div
                    className="rounded-2xl p-4 mb-5 border-l-4"
                    style={ { background: '#F9F5FF', borderColor: '#7B3FA0' } }
                >
                    <p className="text-base text-gray-800 font-medium leading-relaxed">
                        { displaySentence }
                    </p>
                    { example?.translation_en && (
                        <p className="text-sm text-gray-500 italic mt-2">
                            { example.translation_en }
                        </p>
                    ) }
                </div>
            ) : (
                <div
                    className="rounded-2xl p-4 mb-5 text-center"
                    style={ { background: '#F9F5FF' } }
                >
                    <p className="text-gray-500 text-sm italic">No example sentence.</p>
                    <p className="text-base font-semibold text-gray-700 mt-1">
                        Type: { buildHint( target, attempt ) }
                    </p>
                </div>
            ) }

            {/* Letter hint after first wrong */}
            { attempt > 0 && status !== 'correct' && status !== 'revealed' && (
                <p className="text-center font-mono text-lg tracking-widest text-gray-600 dark:text-gray-400 mb-3">
                    { hint }
                </p>
            ) }

            {/* Revealed / correct state */}
            { status === 'revealed' && (
                <p className="text-center mb-3">
                    <span className="text-gray-500 text-sm">The word was </span>
                    <span className="font-bold text-lg text-gray-900 dark:text-gray-100">
                        { target }
                    </span>
                </p>
            ) }
            { status === 'correct' && (
                <div className="flex items-center justify-center gap-2 text-green-600 font-bold mb-3">
                    <CheckCircle size={ 20 } aria-hidden="true" />
                    <span>Correct! +{ attempt === 0 ? XP_CORRECT : Math.max( 1, XP_CORRECT - attempt * 2 ) } XP</span>
                </div>
            ) }

            {/* Input */}
            { status !== 'correct' && status !== 'revealed' && (
                <div className="flex gap-2 mt-auto">
                    <input
                        ref={ inputRef }
                        type="text"
                        value={ input }
                        onChange={ ( e ) => setInput( e.target.value ) }
                        onKeyDown={ handleKeyDown }
                        placeholder="Type the missing word…"
                        className="flex-1 px-4 py-3 rounded-xl border-2 text-base text-gray-900 dark:text-gray-100 dark:bg-gray-800 focus:outline-none transition-colors"
                        style={ {
                            borderColor: status === 'wrong' ? '#ef4444' : '#e5e7eb',
                        } }
                        aria-label="Type the missing word"
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
            ) }

            { status === 'wrong' && (
                <p className="text-center text-sm text-red-500 mt-2">
                    { attempt === 2 ? 'One last hint!' : 'Not quite — try again!' }
                </p>
            ) }
        </div>
    );
}
