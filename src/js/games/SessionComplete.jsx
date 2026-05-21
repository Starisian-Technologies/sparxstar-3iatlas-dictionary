/**
 * SessionComplete — end-of-session summary screen.
 *
 * Shows XP earned, words practiced, "Still learning" list, and navigation
 * options (practice missed, browse domain, play again).
 */
import React, { useMemo } from 'react';
import { Star, RotateCcw, BookOpen, Zap } from 'lucide-react';

/** XP awards per game type (mirrors MyCred hook map in spec §7). */
const XP_LABELS = {
    listen_write:       '+10 XP',
    arrange_word:       '+5 XP',
    meaning_match:      '+5 XP',
    complete_sentence:  '+8 XP',
    letter_reveal:      '+5 XP',
    domain_flash:       '+5 XP',
};

export default function SessionComplete( {
    session,
    onPlayAgain,
    onPracticeMissed,
    onBrowseDomain,
} ) {
    const { results = [], xpEarned = 0, domain, gameType } = session || {};

    const correctCount  = useMemo( () => results.filter( ( r ) => r.outcome === 'correct' ).length,  [ results ] );
    const learningCount = useMemo( () => results.filter( ( r ) => r.outcome === 'learning' ).length, [ results ] );
    const totalCount    = results.length;

    const xpLabel  = XP_LABELS[ gameType ] || '+5 XP';
    const pct      = totalCount > 0 ? Math.round( ( correctCount / totalCount ) * 100 ) : 0;

    return (
        <div className="flex flex-col items-center justify-center min-h-full p-6 text-center max-w-sm mx-auto">
            {/* Trophy / celebration icon */}
            <div
                className="w-20 h-20 rounded-full flex items-center justify-center mb-4 text-4xl shadow-lg"
                style={ { background: 'linear-gradient(135deg,#E91E8C,#7B3FA0)' } }
                aria-hidden="true"
            >
                &#127881;
            </div>

            <h2 className="text-2xl font-bold text-gray-900 dark:text-gray-100 mb-1">
                Session complete!
            </h2>

            <p className="text-gray-500 dark:text-gray-400 text-sm mb-6">
                You practiced { totalCount } word{ totalCount !== 1 ? 's' : '' }
            </p>

            {/* Stats row */}
            <div className="w-full flex gap-3 mb-6">
                <div className="flex-1 rounded-2xl p-4" style={ { background: '#FCE4F3' } }>
                    <div className="text-3xl font-bold" style={ { color: '#E91E8C' } }>
                        { correctCount }
                    </div>
                    <div className="text-xs text-gray-500 mt-0.5">Correct</div>
                </div>
                <div className="flex-1 rounded-2xl p-4" style={ { background: '#EDE7F6' } }>
                    <div className="text-3xl font-bold" style={ { color: '#7B3FA0' } }>
                        { pct }%
                    </div>
                    <div className="text-xs text-gray-500 mt-0.5">Accuracy</div>
                </div>
                <div className="flex-1 rounded-2xl p-4 bg-amber-50">
                    <div className="flex items-center justify-center gap-1 text-3xl font-bold text-amber-500">
                        <Zap size={ 22 } aria-hidden="true" />
                        { xpEarned }
                    </div>
                    <div className="text-xs text-gray-500 mt-0.5">XP earned</div>
                </div>
            </div>

            {/* Still learning note */}
            { learningCount > 0 && (
                <p className="text-sm text-gray-500 dark:text-gray-400 mb-6">
                    <span className="font-semibold text-gray-700 dark:text-gray-300">
                        { learningCount } word{ learningCount !== 1 ? 's' : '' }
                    </span>{ ' ' }
                    still need practice.
                </p>
            ) }

            {/* Action buttons */}
            <div className="w-full flex flex-col gap-3">
                { learningCount > 0 && (
                    <button
                        type="button"
                        onClick={ onPracticeMissed }
                        className="w-full py-3 rounded-2xl font-semibold text-white transition-opacity active:opacity-80"
                        style={ { background: 'linear-gradient(135deg,#E91E8C,#7B3FA0)' } }
                    >
                        <RotateCcw size={ 16 } className="inline mr-2" aria-hidden="true" />
                        Practice missed words
                    </button>
                ) }

                <button
                    type="button"
                    onClick={ onPlayAgain }
                    className="w-full py-3 rounded-2xl font-semibold border-2 transition-colors"
                    style={ { borderColor: '#E91E8C', color: '#E91E8C' } }
                >
                    <Star size={ 16 } className="inline mr-2" aria-hidden="true" />
                    Play again
                </button>

                { domain && (
                    <button
                        type="button"
                        onClick={ onBrowseDomain }
                        className="w-full py-3 rounded-2xl font-semibold text-gray-600 dark:text-gray-300 bg-gray-100 dark:bg-gray-800 transition-colors"
                    >
                        <BookOpen size={ 16 } className="inline mr-2" aria-hidden="true" />
                        Browse this domain
                    </button>
                ) }
            </div>
        </div>
    );
}
