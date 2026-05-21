/**
 * DomainFlash — Game 4.6: Flashcard through a semantic domain.
 *
 * Card shows EN/FR meaning → player recalls the Mandinka word → flips to
 * reveal word + IPA + audio.  Self-reported "I knew it" / "Still learning".
 */
import React, { useState, useCallback } from 'react';
import { Volume2, RotateCcw } from 'lucide-react';

const XP_CORRECT = 5;

export default function DomainFlash( { word, wordIndex, totalWords, onResult } ) {
    const [ flipped,  setFlipped  ] = useState( false );
    const [ revealed, setRevealed ] = useState( false );

    const handleFlip = useCallback( () => {
        setFlipped( true );
        setRevealed( true );
    }, [] );

    const handleKnew = useCallback( () => {
        onResult( 'correct', 1, XP_CORRECT );
    }, [ onResult ] );

    const handleLearning = useCallback( () => {
        onResult( 'learning', 1, 0 );
    }, [ onResult ] );

    const playAudio = useCallback( () => {
        if ( word.audio_url ) {
            new Audio( word.audio_url ).play().catch( () => {} );
        }
    }, [ word.audio_url ] );

    return (
        <div className="flex flex-col items-center justify-between h-full p-6 max-w-sm mx-auto">
            {/* Progress */}
            <div className="w-full flex items-center gap-3 mb-4">
                <div className="flex-1 bg-gray-200 dark:bg-gray-700 rounded-full h-2">
                    <div
                        className="h-2 rounded-full transition-all"
                        style={ {
                            background: 'linear-gradient(90deg,#E91E8C,#7B3FA0)',
                            width:      `${totalWords > 0 ? ( ( wordIndex ) / totalWords ) * 100 : 0}%`,
                        } }
                    />
                </div>
                <span className="text-xs text-gray-400 shrink-0">
                    { wordIndex } / { totalWords }
                </span>
            </div>

            {/* Domain badge */}
            { word.domain && (
                <span
                    className="mb-4 px-3 py-1 rounded-full text-xs font-semibold"
                    style={ { background: '#EDE7F6', color: '#7B3FA0' } }
                >
                    { word.domain }
                </span>
            ) }

            {/* Card */}
            <div
                className="w-full flex-1 flex flex-col items-center justify-center rounded-3xl shadow-xl p-8 mb-6"
                style={ {
                    background: flipped
                        ? 'linear-gradient(135deg,#E91E8C,#7B3FA0)'
                        : 'white',
                    minHeight: 220,
                    transition: 'background 0.3s',
                } }
            >
                { ! flipped ? (
                    <>
                        {/* Front: meaning */}
                        <p className="text-2xl font-bold text-gray-800 text-center mb-2">
                            { word.translation_en }
                        </p>
                        { word.translation_fr && (
                            <p className="text-base text-gray-500 text-center italic">
                                { word.translation_fr }
                            </p>
                        ) }
                    </>
                ) : (
                    <>
                        {/* Back: headword */}
                        <p className="text-3xl font-bold text-white text-center mb-2">
                            { word.headword }
                        </p>
                        { word.ipa && (
                            <p className="text-base text-white/70 font-mono text-center mb-3">
                                /{ word.ipa }/
                            </p>
                        ) }
                        { word.audio_url && (
                            <button
                                type="button"
                                onClick={ playAudio }
                                className="mt-2 p-3 rounded-full transition-opacity active:opacity-70"
                                style={ { background: 'rgba(255,255,255,0.2)' } }
                                aria-label="Play pronunciation"
                            >
                                <Volume2 size={ 22 } color="white" aria-hidden="true" />
                            </button>
                        ) }
                    </>
                ) }
            </div>

            {/* Actions */}
            { ! revealed ? (
                <button
                    type="button"
                    onClick={ handleFlip }
                    className="w-full py-4 rounded-2xl font-semibold text-white text-lg transition-opacity active:opacity-80"
                    style={ { background: 'linear-gradient(135deg,#E91E8C,#7B3FA0)' } }
                >
                    Reveal
                </button>
            ) : (
                <div className="w-full flex gap-3">
                    <button
                        type="button"
                        onClick={ handleLearning }
                        className="flex-1 py-3 rounded-2xl font-semibold border-2 text-gray-600 dark:text-gray-300 transition-colors"
                        style={ { borderColor: '#e5e7eb' } }
                    >
                        <RotateCcw size={ 14 } className="inline mr-1" aria-hidden="true" />
                        Still learning
                    </button>
                    <button
                        type="button"
                        onClick={ handleKnew }
                        className="flex-1 py-3 rounded-2xl font-semibold text-white transition-opacity active:opacity-80"
                        style={ { background: '#22c55e' } }
                    >
                        I knew it ✓
                    </button>
                </div>
            ) }
        </div>
    );
}
