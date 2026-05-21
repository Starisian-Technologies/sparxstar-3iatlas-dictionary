/**
 * useGameSession — manages the current game session in IndexedDB.
 *
 * Session is persisted on every word result so the app can resume
 * from the last checkpoint after a crash or browser close.
 */

import { useState, useEffect, useCallback } from 'react';
import { getRecord, putRecord, deleteRecord } from './idbUtils.js';

const SESSION_KEY = 'game-session:current';
const LEARNED_KEY = 'learned-words:set';

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
                getRecord('game-sessions', SESSION_KEY),
                getRecord('learned-words', LEARNED_KEY),
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
     * @param {string} wordUuid
     * @param {'correct'|'learning'} outcome
     * @param {number} attempts  Number of attempts (1, 2, or 3)
     * @param {number} xp        XP earned for this word
     */
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

            /* Track cumulative learned words. */
            if (outcome === 'correct') {
                const learnedRecord = await getRecord('learned-words', LEARNED_KEY);
                const existing = learnedRecord?.uuids ?? [];
                if (!existing.includes(wordUuid)) {
                    const next = [...existing, wordUuid];
                    await putRecord('learned-words', { key: LEARNED_KEY, uuids: next });
                    setLearnedCount(next.length);
                }
            }

            await putRecord('game-sessions', updated);
            setSession(updated);
        },
        [session]
    );

    /**
     * Mark the session as complete and persist the timestamp.
     */
    const completeSession = useCallback(async () => {
        if (!session) return;

        const completed = { ...session, completedAt: Date.now() };
        await putRecord('game-sessions', completed);
        setSession(completed);
    }, [session]);

    /**
     * Remove the current session from storage (e.g. after sync).
     */
    const clearSession = useCallback(async () => {
        await deleteRecord('game-sessions', SESSION_KEY);
        setSession(null);
    }, []);

    return { session, learnedCount, initSession, recordResult, completeSession, clearSession };
}
