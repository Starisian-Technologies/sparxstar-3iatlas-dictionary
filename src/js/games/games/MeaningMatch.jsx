/**
 * MeaningMatch — Game 4.3: Written Mandinka headword → correct meaning choice.
 *
 * Shows the headword + IPA.  Player picks from 3 meaning cards (1 correct,
 * 2 distractors drawn from the same game set).
 */
import React, { useState, useMemo, useCallback } from 'react';

const XP_CORRECT = 5;

/** Pick n unique random items from arr, excluding the item at excludeIdx. */
function pickDistractors( arr, correctIdx, n ) {
    const pool    = arr.map( ( w, i ) => i ).filter( ( i ) => i !== correctIdx );
    const shuffled = [ ...pool ].sort( () => Math.random() - 0.5 );
    return shuffled.slice( 0, n ).map( ( i ) => arr[ i ] );
}

export default function MeaningMatch( { word, allWords, wordIndex, totalWords, onResult } ) {
    const options = useMemo( () => {
        const distractors = pickDistractors( allWords, allWords.indexOf( word ), 2 );
        const opts = [
            { word, correct: true },
            ...distractors.map( ( w ) => ( { word: w, correct: false } ) ),
        ].sort( () => Math.random() - 0.5 );
        return opts;
    }, [ word, allWords ] );

    const [ selected,  setSelected  ] = useState( null );
    const [ answered,  setAnswered  ] = useState( false );

    const handleSelect = useCallback( ( opt ) => {
        if ( answered ) return;
        setSelected( opt );
        setAnswered( true );
        setTimeout( () => {
            onResult( opt.correct ? 'correct' : 'learning', 1, opt.correct ? XP_CORRECT : 0 );
        }, 900 );
    }, [ answered, onResult ] );

    return (
        <div className="flex flex-col h-full p-6 max-w-sm mx-auto">
            {/* Progress */}
            <div className="w-full flex items-center gap-3 mb-6">
                <div className="flex-1 bg-gray-200 dark:bg-gray-700 rounded-full h-2">
                    <div
                        className="h-2 rounded-full transition-all"
                        style={ {
                            background: 'linear-gradient(90deg,#E91E8C,#7B3FA0)',
                            width:      `${totalWords > 0 ? ( wordIndex / totalWords ) * 100 : 0}%`,
                        } }
                    />
                </div>
                <span className="text-xs text-gray-400 shrink-0">{ wordIndex } / { totalWords }</span>
            </div>

            {/* Headword prompt */}
            <div className="flex flex-col items-center mb-8">
                <p className="text-4xl font-bold text-gray-900 dark:text-gray-100 text-center">
                    { word.headword }
                </p>
                { word.ipa && (
                    <p className="text-base text-gray-400 font-mono mt-2">
                        /{ word.ipa }/
                    </p>
                ) }
                <p className="text-sm text-gray-500 dark:text-gray-400 mt-3">
                    Choose the correct meaning
                </p>
            </div>

            {/* Options */}
            <div className="flex flex-col gap-3">
                { options.map( ( opt, idx ) => {
                    let cardStyle = {};
                    if ( answered ) {
                        if ( opt.correct ) {
                            cardStyle = { background: '#dcfce7', borderColor: '#22c55e' };
                        } else if ( selected === opt ) {
                            cardStyle = { background: '#fee2e2', borderColor: '#ef4444', opacity: 0.7 };
                        } else {
                            cardStyle = { opacity: 0.5 };
                        }
                    }

                    return (
                        <button
                            key={ idx }
                            type="button"
                            onClick={ () => handleSelect( opt ) }
                            disabled={ answered }
                            className="w-full p-4 rounded-2xl border-2 text-left font-medium text-gray-800 dark:text-gray-200 transition-all"
                            style={ {
                                borderColor: '#e5e7eb',
                                background:  'white',
                                ...cardStyle,
                            } }
                        >
                            { opt.word.translation_en }
                        </button>
                    );
                } ) }
            </div>

            {/* Feedback message */}
            { answered && (
                <p className="mt-4 text-center text-sm font-medium"
                    style={ { color: selected?.correct ? '#16a34a' : '#dc2626' } }
                >
                    { selected?.correct
                        ? `Correct! +${XP_CORRECT} XP`
                        : `The answer was: ${word.translation_en}` }
                </p>
            ) }
        </div>
    );
}
