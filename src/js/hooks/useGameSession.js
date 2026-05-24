/**
 * useGameSession — manages the current game session in IndexedDB.
 *
 * Session is persisted on every word result so the app can resume
 * from the last checkpoint after a crash or browser close.
 */

import { useState, useEffect, useCallback } from 'react';
import { getRecord, putRecord, deleteRecord } from './idbUtils.js';

const SESSION_KEY = 'game-session:current';
const LEARNED_KEY = 'learned-words:production';

const safeGetRecord = async (storeName, key) => {
    if (typeof getRecord === 'function') {
        return getRecord(storeName, key);
    }
    return null;
};

/**
 * Games that require the player to produce (write/type/arrange) the word.
 * Only these games contribute to the "words you can write" count.
 *
 * Recognition-only games (domain_flash, meaning_match) measure recall but
 * not orthographic production, so they do not increment learnedCount.
 */
const PRODUCTION_GAMES = new Set([
    'listen_write',
    'arrange_word',
    'complete_sentence',
    'letter_reveal',
]);

/**
 * @returns {{
 *   session: object|null,
 *   learnedCount: number,
 *   initSession: Function,
 *   recordResult: Function,
 *   completeSession: Function,
 *   clearSession: Function,
 * }}
 */
export function useGameSession() {
    const [session, setSession] = useState(null);
    const [learnedCount, setLearnedCount] = useState(0);

    /* Load any in-progress session and learned-word count on mount. */
    useEffect(() => {
        let cancelled = false;

        async function load() {
            const [saved, learnedRecord] = await Promise.all([
                safeGetRecord('game-sessions', SESSION_KEY),
                safeGetRecord('learned-words', LEARNED_KEY),
            ]);

            if (cancelled) return;

            if (saved && saved.completedAt === null) {
                setSession(saved);
            }
            if (learnedRecord && Array.isArray(learnedRecord.uuids)) {
                setLearnedCount(learnedRecord.uuids.length);
            }
        }

        load();
        return () => {
            cancelled = true;
        };
    }, []);

    /**
     * Start a new session, replacing any existing one.
     *
     * @param {object} opts
     * @param {string} opts.gameType
     * @param {string} opts.langSource
     * @param {string} opts.domain
     * @param {Array}  opts.words  Shuffled game-set slice
     */
    const initSession = useCallback(async ({ gameType, langSource, domain, words }) => {
        const newSession = {
            key: SESSION_KEY,
            gameType,
            langSource,
            domain,
            words,
            currentIndex: 0,
            results: [],
            xpEarned: 0,
            startedAt: Date.now(),
            completedAt: null,
        };
        await putRecord('game-sessions', newSession);
        setSession(newSession);
    }, []);

    /**
     * Record the outcome for one word.
     *
     * learnedCount ("words you can write") is only incremented for
     * production games (listen_write, arrange_word, complete_sentence,
     * letter_reveal) where the player demonstrated orthographic output.
     * Recognition-only games (domain_flash, meaning_match) do not
     * contribute to this count.
     *
     * @param {string} wordUuid
     * @param {'correct'|'learning'} outcome
     * @param {number} attempts  Number of attempts (1, 2, or 3)
     * @param {number} xp        XP earned for this word
     */
    const safePutRecord = useCallback(async (...args) => {
        if (typeof putRecord !== 'function') {
            throw new TypeError('putRecord is not a function');
        }
        return putRecord(...args);
    }, []);

    const safeDeleteRecord = useCallback(async (...args) => {
        if (typeof deleteRecord !== 'function') {
            throw new TypeError('deleteRecord is not a function');
        }
        return deleteRecord(...args);
    }, []);

    const recordResult = useCallback(
        async (wordUuid, outcome, attempts, xp) => {
            if (!session) return;

            const result = { wordUuid, outcome, attempts, xp, ts: Date.now() };
            const updated = {
                ...session,
                currentIndex: session.currentIndex + 1,
                results: [...session.results, result],
                xpEarned: session.xpEarned + xp,
            };

            /* Only count toward "words you can write" for production games. */
            const isProductionGame = PRODUCTION_GAMES.has(session.gameType);
            if (outcome === 'correct' && isProductionGame) {
                const learnedRecord =
                    typeof getRecord === 'function'
                        ? await getRecord('learned-words', LEARNED_KEY)
                        : null;
                const existing = learnedRecord?.uuids ?? [];
                if (!existing.includes(wordUuid)) {
                    const next = [...existing, wordUuid];
                    await safePutRecord('learned-words', { key: LEARNED_KEY, uuids: next });
                    setLearnedCount(next.length);
                }
            }

            await safePutRecord('game-sessions', updated);
            setSession(updated);
        },
        [session, safePutRecord]
    );

    /**
     * Mark the session as complete and persist the timestamp.
     */
    const completeSession = useCallback(async () => {
        if (!session) return;

        const completed = { ...session, completedAt: Date.now() };
        await safePutRecord('game-sessions', completed);
        setSession(completed);
    }, [session, safePutRecord]);

    /**
     * Remove the current session from storage (e.g. after sync).
     */
    const clearSession = useCallback(async () => {
        await safeDeleteRecord('game-sessions', SESSION_KEY);
        setSession(null);
    }, [safeDeleteRecord]);

    return { session, learnedCount, initSession, recordResult, completeSession, clearSession };
}
