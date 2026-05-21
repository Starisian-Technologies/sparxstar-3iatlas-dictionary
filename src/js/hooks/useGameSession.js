/**
 * useGameSession — persists current game session state in IndexedDB.
 *
 * Session is written on every word result so that closing and reopening
 * the app mid-game resumes from the last checkpoint.
 */
import { useState, useEffect, useRef, useCallback } from 'react';

const DB_NAME = 'aiwa-games-db';
const DB_VERSION = 1;
const STORE = 'game-sessions';
const SESSION_KEY = 'game-session:current';

// ---------------------------------------------------------------------------
// IndexedDB helpers (scoped to sessions store)
// ---------------------------------------------------------------------------

function openDb() {
    return new Promise((resolve, reject) => {
        const req = indexedDB.open(DB_NAME, DB_VERSION);
        req.onupgradeneeded = (e) => {
            const db = e.target.result;
            if (!db.objectStoreNames.contains(STORE)) {
                db.createObjectStore(STORE, { keyPath: 'key' });
            }
            // Also ensure game-sets store exists (shared DB).
            if (!db.objectStoreNames.contains('game-sets')) {
                db.createObjectStore('game-sets', { keyPath: 'key' });
            }
        };
        req.onsuccess = (e) => resolve(e.target.result);
        req.onerror = (e) => reject(e.target.error);
    });
}

async function idbGet(db, key) {
    return new Promise((resolve, reject) => {
        const tx = db.transaction(STORE, 'readonly');
        const req = tx.objectStore(STORE).get(key);
        req.onsuccess = (e) => resolve(e.target.result || null);
        req.onerror = (e) => reject(e.target.error);
    });
}

async function idbPut(db, record) {
    return new Promise((resolve, reject) => {
        const tx = db.transaction(STORE, 'readwrite');
        const req = tx.objectStore(STORE).put(record);
        req.onsuccess = () => resolve();
        req.onerror = (e) => reject(e.target.error);
    });
}

async function idbDelete(db, key) {
    return new Promise((resolve, reject) => {
        const tx = db.transaction(STORE, 'readwrite');
        const req = tx.objectStore(STORE).delete(key);
        req.onsuccess = () => resolve();
        req.onerror = (e) => reject(e.target.error);
    });
}

// ---------------------------------------------------------------------------
// Shuffle helper
// ---------------------------------------------------------------------------

function shuffle(arr) {
    const a = [...arr];
    for (let i = a.length - 1; i > 0; i--) {
        const j = Math.floor(Math.random() * (i + 1));
        [a[i], a[j]] = [a[j], a[i]];
    }
    return a;
}

// ---------------------------------------------------------------------------
// Hook
// ---------------------------------------------------------------------------

/**
 * @returns {object} session and controls
 */
export function useGameSession() {
    const [session, setSession] = useState(null);
    const [loading, setLoading] = useState(true);
    const dbRef = useRef(null);

    // Load any persisted session on mount
    useEffect(() => {
        let cancelled = false;
        async function load() {
            try {
                if (!dbRef.current) {
                    dbRef.current = await openDb();
                }
                const saved = await idbGet(dbRef.current, SESSION_KEY);
                if (!cancelled && saved) {
                    setSession(saved);
                }
            } catch {
                /* degrade silently */
            }
            if (!cancelled) setLoading(false);
        }
        load();
        return () => {
            cancelled = true;
        };
    }, []);

    const persist = useCallback(async (next) => {
        setSession(next);
        try {
            if (!dbRef.current) dbRef.current = await openDb();
            if (next) {
                await idbPut(dbRef.current, next);
            } else {
                await idbDelete(dbRef.current, SESSION_KEY);
            }
        } catch {
            /* degrade silently */
        }
    }, []);

    /**
     * Start a new session.
     *
     * @param {object} opts
     * @param {string} opts.gameType
     * @param {string} opts.langSource
     * @param {string} opts.domain
     * @param {Array}  opts.words
     */
    const startSession = useCallback(
        ({ gameType, langSource, domain, words }) => {
            const next = {
                key: SESSION_KEY,
                gameType,
                langSource,
                domain,
                words: shuffle(words),
                currentIndex: 0,
                results: [],
                xpEarned: 0,
                startedAt: Date.now(),
                completedAt: null,
            };
            persist(next);
        },
        [persist]
    );

    /**
     * Record the result for the current word and advance.
     *
     * @param {'correct'|'learning'} outcome
     * @param {number} attempts  1, 2, or 3
     * @param {number} xp  XP earned for this word
     */
    const recordResult = useCallback(
        (outcome, attempts, xp) => {
            setSession((prev) => {
                if (!prev) return prev;
                const word = prev.words[prev.currentIndex];
                const results = [
                    ...prev.results,
                    { wordUuid: word?.uuid || '', outcome, attempts },
                ];
                const next = {
                    ...prev,
                    results,
                    xpEarned: prev.xpEarned + (xp || 0),
                    currentIndex: prev.currentIndex + 1,
                };
                persist(next);
                return next;
            });
        },
        [persist]
    );

    /**
     * Mark session complete.
     */
    const completeSession = useCallback(() => {
        setSession((prev) => {
            if (!prev) return prev;
            const next = { ...prev, completedAt: Date.now() };
            persist(next);
            return next;
        });
    }, [persist]);

    /**
     * Clear session (after summary is dismissed or new game starts).
     */
    const clearSession = useCallback(() => {
        persist(null);
    }, [persist]);

    const currentWord = session ? session.words[session.currentIndex] || null : null;
    const isDone = session ? session.currentIndex >= session.words.length : false;

    return {
        session,
        loading,
        currentWord,
        isDone,
        startSession,
        recordResult,
        completeSession,
        clearSession,
    };
}
