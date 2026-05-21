/**
 * useGameSet — fetch /game-set from REST and cache in IndexedDB (3-day TTL).
 *
 * Offline first: once cached, games run entirely from cache; network is only
 * contacted when the TTL has expired or the cache is cold.
 */
import { useState, useEffect, useRef } from 'react';

const DB_NAME = 'aiwa-games-db';
const DB_VERSION = 1;
const STORE = 'game-sets';
const TTL_MS = 259_200_000; // 3 days

const REST_URL =
    (window.sparxstarDictionarySettings && window.sparxstarDictionarySettings.restUrl) ||
    '/wp-json/sparxstar/v1/dictionary';

// ---------------------------------------------------------------------------
// IndexedDB helpers
// ---------------------------------------------------------------------------

function openDb() {
    return new Promise((resolve, reject) => {
        const req = indexedDB.open(DB_NAME, DB_VERSION);
        req.onupgradeneeded = (e) => {
            const db = e.target.result;
            if (!db.objectStoreNames.contains(STORE)) {
                db.createObjectStore(STORE, { keyPath: 'key' });
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

// ---------------------------------------------------------------------------
// Hook
// ---------------------------------------------------------------------------

/**
 * @param {object} params
 * @param {string} params.langSource  Required — taxonomy slug (e.g. "mandinka")
 * @param {string} [params.domain]    Optional domain slug
 * @param {number} [params.limit]     Number of words (default 20, max 50)
 * @param {boolean} [params.includeAudio] Include audio URLs (default false)
 * @param {boolean} [params.enabled]  Set false to skip fetching
 */
export function useGameSet({
    langSource,
    domain = '',
    limit = 20,
    includeAudio = false,
    enabled = true,
} = {}) {
    const [words, setWords] = useState([]);
    const [loading, setLoading] = useState(false);
    const [error, setError] = useState(null);
    const dbRef = useRef(null);

    useEffect(() => {
        if (!enabled || !langSource) return;

        let cancelled = false;

        async function load() {
            setLoading(true);
            setError(null);

            try {
                if (!dbRef.current) {
                    dbRef.current = await openDb();
                }
                const db = dbRef.current;
                const key = `game-set:${langSource}:${domain || 'all'}`;

                // Check cache first
                const cached = await idbGet(db, key);
                const now = Date.now();

                if (cached && now - cached.fetchedAt < TTL_MS) {
                    if (!cancelled) {
                        setWords(cached.data);
                        setLoading(false);
                    }
                    return;
                }

                // Fetch from network
                const params = new URLSearchParams({
                    lang_source: langSource,
                    limit: String(limit),
                });
                if (domain) params.set('domain', domain);
                if (includeAudio) params.set('include_audio', 'true');

                const res = await fetch(`${REST_URL}/game-set?${params}`);
                if (!res.ok) throw new Error(`HTTP ${res.status}`);
                const json = await res.json();

                if (!json.success || !Array.isArray(json.data?.words)) {
                    throw new Error('Unexpected response shape');
                }

                const data = json.data.words;
                await idbPut(db, { key, data, fetchedAt: now, ttlMs: TTL_MS });

                if (!cancelled) {
                    setWords(data);
                    setLoading(false);
                }
            } catch (err) {
                if (!cancelled) {
                    setError(err);
                    setLoading(false);
                }
            }
        }

        load();
        return () => {
            cancelled = true;
        };
    }, [langSource, domain, limit, includeAudio, enabled]);

    return { words, loading, error };
}
