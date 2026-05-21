/**
 * useProgressSync — maintains an offline outbox in IndexedDB and syncs to
 * POST /sparxstar/v1/dictionary/progress/sync when the device is online.
 *
 * Sync fires immediately on session complete (if online) or deferred to
 * the next `window.online` event.
 */
import { useEffect, useRef, useCallback } from 'react';

const DB_NAME = 'aiwa-games-db';
const DB_VERSION = 1;
const STORE = 'progress-outbox';

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
                db.createObjectStore(STORE, { keyPath: 'id', autoIncrement: true });
            }
            if (!db.objectStoreNames.contains('game-sets')) {
                db.createObjectStore('game-sets', { keyPath: 'key' });
            }
            if (!db.objectStoreNames.contains('game-sessions')) {
                db.createObjectStore('game-sessions', { keyPath: 'key' });
            }
        };
        req.onsuccess = (e) => resolve(e.target.result);
        req.onerror = (e) => reject(e.target.error);
    });
}

async function idbAddBatch(db, events) {
    const tx = db.transaction(STORE, 'readwrite');
    const st = tx.objectStore(STORE);
    events.forEach((ev) => st.add({ events: ev, ts: Date.now() }));
    return new Promise((resolve, reject) => {
        tx.oncomplete = resolve;
        tx.onerror = (e) => reject(e.target.error);
    });
}

async function idbGetAll(db) {
    return new Promise((resolve, reject) => {
        const tx = db.transaction(STORE, 'readonly');
        const req = tx.objectStore(STORE).getAll();
        req.onsuccess = (e) => resolve(e.target.result || []);
        req.onerror = (e) => reject(e.target.error);
    });
}

async function idbClearIds(db, ids) {
    const tx = db.transaction(STORE, 'readwrite');
    const st = tx.objectStore(STORE);
    ids.forEach((id) => st.delete(id));
    return new Promise((resolve, reject) => {
        tx.oncomplete = resolve;
        tx.onerror = (e) => reject(e.target.error);
    });
}

// ---------------------------------------------------------------------------
// Sync logic
// ---------------------------------------------------------------------------

async function flushOutbox(db) {
    const rows = await idbGetAll(db);
    if (!rows.length) return;

    // Flatten all event batches
    const allEvents = rows.flatMap((r) => (Array.isArray(r.events) ? r.events : [r.events]));

    const token = window.sparxstarDictionarySettings?.heliosToken || '';
    const headers = { 'Content-Type': 'application/json' };
    if (token) headers['Authorization'] = `Bearer ${token}`;

    try {
        const res = await fetch(`${REST_URL}/progress/sync`, {
            method: 'POST',
            headers,
            body: JSON.stringify({ events: allEvents }),
        });
        if (res.ok) {
            // Remove successfully synced rows
            await idbClearIds(
                db,
                rows.map((r) => r.id)
            );
        }
    } catch {
        // Network failure — leave in outbox for next attempt
    }
}

// ---------------------------------------------------------------------------
// Hook
// ---------------------------------------------------------------------------

export function useProgressSync() {
    const dbRef = useRef(null);

    const ensureDb = useCallback(async () => {
        if (!dbRef.current) {
            dbRef.current = await openDb();
        }
        return dbRef.current;
    }, []);

    // Listen for online events and flush immediately
    useEffect(() => {
        let destroyed = false;
        const handleOnline = async () => {
            if (destroyed) return;
            try {
                const db = await ensureDb();
                await flushOutbox(db);
            } catch {
                /* degrade silently */
            }
        };
        window.addEventListener('online', handleOnline);
        return () => {
            destroyed = true;
            window.removeEventListener('online', handleOnline);
        };
    }, [ensureDb]);

    /**
     * Enqueue a batch of progress events.  Flushes immediately if online.
     *
     * @param {Array<object>} events
     */
    const enqueue = useCallback(
        async (events) => {
            if (!Array.isArray(events) || !events.length) return;
            try {
                const db = await ensureDb();
                await idbAddBatch(db, events);
                if (navigator.onLine) {
                    await flushOutbox(db);
                }
            } catch {
                /* degrade silently */
            }
        },
        [ensureDb]
    );

    return { enqueue };
}
