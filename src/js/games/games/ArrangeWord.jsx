import React, { useState, useMemo, useCallback } from 'react';
import { Volume2 } from 'lucide-react';

/**
 * ArrangeWord — Game 4.2
 *
 * Scrambled letter tiles → player taps tiles to build the correct word.
 * Translation and domain hint are shown throughout.
 *
 * Props:
 *   words      {Array}    Game-set words
 *   language   {string}   'en' | 'fr'
 *   onResult   {Function} (uuid, outcome, attempts, xp) => void
 *   onComplete {Function} () => void
 */
export default function ArrangeWord({ words, language, onResult, onComplete }) {
    const deck = useMemo(() => shuffle(words), [words]);
    const [index, setIndex] = useState(0);
    const [answer, setAnswer] = useState([]);
    const [pool, setPool] = useState(() => buildPool(deck[0]?.headword ?? ''));
    const [shake, setShake] = useState(false);
    const [correct, setCorrect] = useState(false);

    const word = deck[index];
    const target = word ? word.headword.toLowerCase() : '';

    /* Advance to next word or complete session. */
    const advance = useCallback(() => {
        if (index + 1 >= deck.length) {
            onComplete();
        } else {
            const nextIndex = index + 1;
            setIndex(nextIndex);
            setPool(buildPool(deck[nextIndex].headword));
            setAnswer([]);
            setCorrect(false);
        }
    }, [index, deck, onComplete]);

    /* Tap a tile in the pool → append to answer. */
    const pickFromPool = useCallback(
        (poolIdx) => {
            if (correct || !word) return;
            const tile = pool[poolIdx];
            const newPool = pool.filter((_, i) => i !== poolIdx);
            const newAnswer = [...answer, tile];
            setPool(newPool);
            setAnswer(newAnswer);

            if (newAnswer.length === target.length) {
                const attempt = newAnswer
                    .map((t) => t.char)
                    .join('')
                    .toLowerCase();
                if (attempt === target) {
                    setCorrect(true);
                    if (word.audio_url) new Audio(word.audio_url).play().catch(() => {});
                    onResult(word.uuid, 'correct', 1, 5);
                    setTimeout(advance, 1200);
                } else {
                    setShake(true);
                    setTimeout(() => {
                        setShake(false);
                        setPool(buildPool(word.headword));
                        setAnswer([]);
                    }, 600);
                }
            }
        },
        [answer, correct, pool, target, word, onResult, advance]
    );

    /* Return a placed tile back to the pool. */
    const returnToPool = useCallback(
        (answerIdx) => {
            if (correct) return;
            const tile = answer[answerIdx];
            setAnswer(answer.filter((_, i) => i !== answerIdx));
            setPool([...pool, tile]);
        },
        [answer, correct, pool]
    );

    if (!word) return null;

    const meaning =
        language === 'fr' && word.translation_fr ? word.translation_fr : word.translation_en;

    return (
        <div className="flex flex-col h-full p-4 gap-4">
            {/* Header */}
            <div className="flex items-center justify-between text-sm text-gray-500 dark:text-gray-400 shrink-0">
                <span className="font-semibold" style={{ color: '#E91E8C' }}>
                    Arrange the Word
                </span>
                <span>
                    {index + 1} / {deck.length}
                </span>
            </div>

            <ProgressBar current={index} total={deck.length} />

            {/* Hints */}
            <div className="shrink-0 text-center py-2">
                <p className="text-lg font-semibold text-gray-800 dark:text-gray-200">{meaning}</p>
                {word.domain && (
                    <span
                        className="inline-block mt-1 text-xs font-bold uppercase tracking-wider px-3 py-0.5 rounded-full text-white"
                        style={{ background: '#7B3FA0' }}
                    >
                        {word.domain}
                    </span>
                )}
            </div>

            {/* Answer row */}
            <div className="flex flex-wrap gap-2 justify-center min-h-[52px] px-2 shrink-0">
                {answer.map((tile, i) => (
                    <Tile
                        key={tile.id}
                        char={tile.char}
                        onClick={() => returnToPool(i)}
                        variant="answer"
                        shake={shake}
                        correct={correct}
                    />
                ))}
                {Array.from({ length: target.length - answer.length }).map((_, i) => (
                    <div
                        key={`empty-${i}`}
                        className="w-10 h-10 rounded-lg border-2 border-dashed border-gray-200 dark:border-gray-700"
                    />
                ))}
            </div>

            {correct && <p className="text-center text-green-600 font-bold shrink-0">+5 XP ✓</p>}

            {/* Tile pool */}
            <div className="flex-1 flex flex-wrap gap-2 content-center justify-center px-2">
                {pool.map((tile, i) => (
                    <Tile
                        key={tile.id}
                        char={tile.char}
                        onClick={() => pickFromPool(i)}
                        variant="pool"
                        shake={false}
                        correct={false}
                    />
                ))}
            </div>

            {correct && word.audio_url && (
                <div className="shrink-0 flex justify-center pb-2">
                    <button
                        type="button"
                        onClick={() => new Audio(word.audio_url).play().catch(() => {})}
                        className="p-3 rounded-full"
                        style={{ background: '#FCE4F3', color: '#E91E8C' }}
                        aria-label="Play pronunciation"
                    >
                        <Volume2 size={20} aria-hidden="true" />
                    </button>
                </div>
            )}
        </div>
    );
}

function Tile({ char, onClick, variant, shake, correct }) {
    let bg = variant === 'answer' ? '#E91E8C' : '#7B3FA0';
    if (correct && variant === 'answer') bg = '#4CAF50';

    return (
        <button
            type="button"
            onClick={onClick}
            className={`w-10 h-10 rounded-lg font-bold text-lg text-white uppercase transition-all select-none${shake ? ' animate-shake' : ''}`}
            style={{ background: bg }}
            aria-label={char}
        >
            {char}
        </button>
    );
}

function ProgressBar({ current, total }) {
    const pct = total > 0 ? (current / total) * 100 : 0;
    return (
        <div className="h-1.5 w-full bg-gray-100 dark:bg-gray-800 rounded-full overflow-hidden shrink-0">
            <div
                className="h-full rounded-full transition-all"
                style={{ width: `${pct}%`, background: '#E91E8C' }}
            />
        </div>
    );
}

function buildPool(headword) {
    return shuffle(headword.split('').map((char, i) => ({ char, id: `${char}-${i}` })));
}

function shuffle(arr) {
    const a = [...arr];
    for (let i = a.length - 1; i > 0; i--) {
        const j = Math.floor(Math.random() * (i + 1));
        [a[i], a[j]] = [a[j], a[i]];
    }
    return a;
}
