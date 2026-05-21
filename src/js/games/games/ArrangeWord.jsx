/**
 * ArrangeWord — Game 4.2: Scrambled letter tiles → correct order.
 *
 * All letters provided as tappable tiles in a shuffled order.  Player taps
 * tiles to move them into the answer row.
 */
import React, { useState, useMemo, useCallback, useEffect } from 'react';
import { Volume2 } from 'lucide-react';

const XP_CORRECT = 5;

function shuffleLetters( word ) {
    const letters = word.split( '' );
    // Keep shuffling until not identical to original (for short words that may be palindromes)
    let shuffled;
    let attempts = 0;
    do {
        shuffled = [ ...letters ].sort( () => Math.random() - 0.5 );
        attempts++;
    } while ( shuffled.join( '' ) === word && attempts < 20 );
    return shuffled.map( ( char, idx ) => ( { char, id: idx } ) );
}

export default function ArrangeWord( { word, wordIndex, totalWords, onResult } ) {
    const pool = useMemo( () => shuffleLetters( word.headword ), [ word.headword ] );

    const [ available, setAvailable ] = useState( pool );
    const [ chosen,    setChosen    ] = useState( [] );
    const [ status,    setStatus    ] = useState( 'idle' ); // idle | wrong | correct

    // Reset state when word changes
    useEffect( () => {
        setAvailable( shuffleLetters( word.headword ) );
        setChosen( [] );
        setStatus( 'idle' );
    }, [ word.headword ] );

    const handlePick = useCallback( ( tile ) => {
        if ( status === 'correct' ) return;
        setAvailable( ( prev ) => prev.filter( ( t ) => t.id !== tile.id ) );
        setChosen( ( prev ) => [ ...prev, tile ] );
    }, [ status ] );

    const handleRemove = useCallback( ( tile ) => {
        if ( status === 'correct' ) return;
        setChosen( ( prev ) => prev.filter( ( t ) => t.id !== tile.id ) );
        setAvailable( ( prev ) => [ ...prev, tile ] );
    }, [ status ] );

    // Auto-check when all letters are placed
    useEffect( () => {
        if ( chosen.length !== word.headword.length ) return;

        const attempt = chosen.map( ( t ) => t.char ).join( '' );
        if ( attempt === word.headword ) {
            setStatus( 'correct' );
            if ( word.audio_url ) {
                new Audio( word.audio_url ).play().catch( () => {} );
            }
            setTimeout( () => {
                onResult( 'correct', 1, XP_CORRECT );
            }, 1200 );
        } else {
            setStatus( 'wrong' );
            // Brief shake, then return all chosen tiles back to pool
            setTimeout( () => {
                setAvailable( pool );
                setChosen( [] );
                setStatus( 'idle' );
            }, 700 );
        }
    }, [ chosen, word.headword, word.audio_url, pool, onResult ] );

    const tileBase = 'min-w-[2.5rem] h-11 px-3 rounded-xl font-bold text-lg flex items-center justify-center border-2 transition-all select-none cursor-pointer';

    return (
        <div className="flex flex-col h-full p-6 max-w-sm mx-auto">
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

            {/* Hint */}
            <div className="mb-4 text-center">
                { word.domain && (
                    <span
                        className="inline-block mb-2 px-3 py-1 rounded-full text-xs font-semibold"
                        style={ { background: '#EDE7F6', color: '#7B3FA0' } }
                    >
                        { word.domain }
                    </span>
                ) }
                <p className="text-lg font-semibold text-gray-800 dark:text-gray-200">
                    { word.translation_en }
                </p>
            </div>

            {/* Answer row */}
            <div className="flex flex-wrap gap-2 justify-center mb-4 min-h-[3rem] p-3 rounded-2xl bg-gray-50 dark:bg-gray-800 border-2 border-dashed border-gray-300 dark:border-gray-600">
                { chosen.length === 0 && (
                    <p className="text-gray-400 text-sm self-center">Tap letters below&hellip;</p>
                ) }
                { chosen.map( ( tile ) => (
                    <button
                        key={ tile.id }
                        type="button"
                        onClick={ () => handleRemove( tile ) }
                        className={ `${tileBase} ${
                            status === 'correct'
                                ? 'border-green-400 bg-green-100 text-green-800'
                                : status === 'wrong'
                                    ? 'border-red-400 bg-red-100 text-red-800 animate-shake'
                                    : 'border-purple-400 bg-purple-50 text-purple-800'
                        }` }
                        aria-label={ `Remove ${tile.char}` }
                    >
                        { tile.char }
                    </button>
                ) ) }
            </div>

            {/* Available tile pool */}
            <div className="flex flex-wrap gap-2 justify-center mb-6">
                { available.map( ( tile ) => (
                    <button
                        key={ tile.id }
                        type="button"
                        onClick={ () => handlePick( tile ) }
                        className={ `${tileBase} border-gray-300 bg-white dark:bg-gray-700 dark:border-gray-600 text-gray-800 dark:text-gray-200 hover:border-pink-400 active:scale-95` }
                        aria-label={ `Select letter ${tile.char}` }
                    >
                        { tile.char }
                    </button>
                ) ) }
            </div>

            {/* Status message */}
            { status === 'correct' && (
                <div className="text-center">
                    <p className="font-bold text-green-600">
                        Correct! +{ XP_CORRECT } XP
                    </p>
                    { word.audio_url && (
                        <p className="text-xs text-gray-400 mt-1">Playing pronunciation&hellip;</p>
                    ) }
                </div>
            ) }
            { status === 'wrong' && (
                <p className="text-center text-sm font-medium text-red-500">
                    Not quite — try again!
                </p>
            ) }
        </div>
    );
}
