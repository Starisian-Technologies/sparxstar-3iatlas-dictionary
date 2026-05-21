/**
 * LetterReveal — Game 4.5: Click letters from a pool to reveal the hidden word.
 *
 * Word length shown as blank tiles.  Full alphabet pool (including Mandinka
 * special characters) shown.  Player taps letters; correct letters reveal
 * all their instances; wrong letters decrement a counter (5 wrong = over).
 */
import React, { useState, useMemo, useCallback, useEffect } from 'react';

const XP_CORRECT   = 5;
const MAX_WRONG    = 5;

/** Mandinka alphabet pool — standard + special chars. */
const STANDARD_ALPHA = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ'.split( '' );
const SPECIAL_ALPHA  = [ 'Ŋ', 'Ɓ', 'Ɗ', 'Ñ', 'Ɲ', 'ʔ', 'Á', 'É', 'Í', 'Ó', 'Ú' ];

function buildAlphabet( word ) {
    const upper = word.toUpperCase();
    const needed = [ ...new Set( upper.split( '' ).filter( ( c ) => /\p{L}/u.test( c ) ) ) ];
    // Ensure all letters in the word appear in the pool.
    const pool = [ ...STANDARD_ALPHA ];
    needed.forEach( ( c ) => {
        if ( ! pool.includes( c ) ) pool.push( c );
    } );
    return pool;
}

export default function LetterReveal( { word, wordIndex, totalWords, onResult } ) {
    const upperWord = useMemo( () => word.headword.toUpperCase(), [ word.headword ] );
    const alphabet  = useMemo( () => buildAlphabet( word.headword ), [ word.headword ] );

    const [ guessed,    setGuessed    ] = useState( new Set() );
    const [ wrongCount, setWrongCount ] = useState( 0 );
    const [ done,       setDone       ] = useState( false );

    // Reset on new word
    useEffect( () => {
        setGuessed( new Set() );
        setWrongCount( 0 );
        setDone( false );
    }, [ word.headword ] );

    const revealedTiles = useMemo(
        () =>
            word.headword.split( '' ).map( ( char ) => {
                const upper = char.toUpperCase();
                const isLetter = /\p{L}/u.test( char );
                return isLetter ? ( guessed.has( upper ) ? char : null ) : char;
            } ),
        [ word.headword, guessed ],
    );

    const isComplete = useMemo(
        () => word.headword.split( '' ).every( ( char ) => {
            if ( ! /\p{L}/u.test( char ) ) return true;
            return guessed.has( char.toUpperCase() );
        } ),
        [ word.headword, guessed ],
    );

    const isFailed = wrongCount >= MAX_WRONG;

    // Detect win or fail
    useEffect( () => {
        if ( done ) return;
        if ( isComplete ) {
            setDone( true );
            setTimeout( () => onResult( 'correct', 1, XP_CORRECT ), 900 );
        } else if ( isFailed ) {
            setDone( true );
            setTimeout( () => onResult( 'learning', 1, 0 ), 1200 );
        }
    }, [ isComplete, isFailed, done, onResult ] );

    const handleGuess = useCallback( ( letter ) => {
        if ( done || guessed.has( letter ) ) return;
        const isCorrect = upperWord.includes( letter );
        setGuessed( ( prev ) => new Set( [ ...prev, letter ] ) );
        if ( ! isCorrect ) {
            setWrongCount( ( c ) => c + 1 );
        }
    }, [ done, guessed, upperWord ] );

    // Wrong tries indicator
    const tries = Array.from( { length: MAX_WRONG }, ( _, i ) => i < wrongCount );

    return (
        <div className="flex flex-col h-full p-6 max-w-sm mx-auto">
            {/* Progress bar */}
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

            {/* Hint: translation */}
            <p className="text-center text-lg font-semibold text-gray-800 dark:text-gray-200 mb-1">
                { word.translation_en }
            </p>

            {/* Wrong tries */}
            <div className="flex gap-1 justify-center mb-4">
                { tries.map( ( used, i ) => (
                    <div
                        key={ i }
                        className="w-4 h-4 rounded-full border-2"
                        style={ {
                            background:  used ? '#ef4444' : 'transparent',
                            borderColor: used ? '#ef4444' : '#d1d5db',
                        } }
                        aria-hidden="true"
                    />
                ) ) }
            </div>

            {/* Word tiles */}
            <div className="flex flex-wrap gap-2 justify-center mb-6">
                { word.headword.split( '' ).map( ( char, idx ) => {
                    const isLetter  = /\p{L}/u.test( char );
                    const isRevealed = revealedTiles[ idx ] !== null;
                    return (
                        <div
                            key={ idx }
                            className="min-w-[2.2rem] h-10 rounded-lg border-2 flex items-center justify-center font-bold text-lg"
                            style={ {
                                borderColor: isRevealed || ! isLetter ? '#E91E8C' : '#d1d5db',
                                background:  isRevealed || ! isLetter ? '#FCE4F3' : 'white',
                                color:       '#1f2937',
                            } }
                            aria-label={ isRevealed || ! isLetter ? char : 'Hidden' }
                        >
                            { isRevealed || ! isLetter ? char : '' }
                        </div>
                    );
                } ) }
            </div>

            { /* Completion or failure state */ }
            { done && isComplete && (
                <p className="text-center font-bold text-green-600 mb-3">
                    You got it! +{ XP_CORRECT } XP
                </p>
            ) }
            { done && isFailed && ! isComplete && (
                <div className="text-center mb-3">
                    <p className="font-bold text-red-500">The word was:</p>
                    <p className="text-xl font-bold text-gray-800 dark:text-gray-200">
                        { word.headword }
                    </p>
                </div>
            ) }

            {/* Alphabet pool */}
            <div className="mt-auto">
                <div className="flex flex-wrap gap-1 justify-center mb-2">
                    { alphabet.map( ( letter ) => {
                        const isGuessed  = guessed.has( letter );
                        const isCorrect  = upperWord.includes( letter );
                        const isWrong    = isGuessed && ! isCorrect;

                        return (
                            <button
                                key={ letter }
                                type="button"
                                onClick={ () => handleGuess( letter ) }
                                disabled={ isGuessed || done }
                                className="min-w-[2rem] h-9 rounded-lg font-bold text-sm transition-all active:scale-95"
                                style={ {
                                    background:  isWrong ? '#fee2e2' : isGuessed ? '#dcfce7' : 'white',
                                    color:       isWrong ? '#dc2626' : isGuessed ? '#16a34a' : '#1f2937',
                                    border:      `1px solid ${isWrong ? '#fca5a5' : isGuessed ? '#86efac' : '#e5e7eb'}`,
                                    opacity:     isGuessed ? 0.5 : 1,
                                    textDecoration: isWrong ? 'line-through' : 'none',
                                } }
                                aria-label={ `Guess letter ${letter}` }
                                aria-pressed={ isGuessed }
                            >
                                { letter }
                            </button>
                        );
                    } ) }
                </div>
                {/* Special characters row */}
                <div className="flex flex-wrap gap-1 justify-center">
                    { SPECIAL_ALPHA.map( ( letter ) => {
                        const isGuessed = guessed.has( letter );
                        const isCorrect = upperWord.includes( letter );
                        const isWrong   = isGuessed && ! isCorrect;
                        return (
                            <button
                                key={ letter }
                                type="button"
                                onClick={ () => handleGuess( letter ) }
                                disabled={ isGuessed || done }
                                className="min-w-[2rem] h-9 rounded-lg font-bold text-sm transition-all active:scale-95"
                                style={ {
                                    background:  isWrong ? '#fee2e2' : isGuessed ? '#dcfce7' : '#FCE4F3',
                                    color:       isWrong ? '#dc2626' : isGuessed ? '#16a34a' : '#E91E8C',
                                    border:      `1px solid ${isWrong ? '#fca5a5' : isGuessed ? '#86efac' : '#f9a8d4'}`,
                                    opacity:     isGuessed ? 0.5 : 1,
                                    textDecoration: isWrong ? 'line-through' : 'none',
                                } }
                                aria-label={ `Guess letter ${letter}` }
                                aria-pressed={ isGuessed }
                            >
                                { letter }
                            </button>
                        );
                    } ) }
                </div>
            </div>
        </div>
    );
}
