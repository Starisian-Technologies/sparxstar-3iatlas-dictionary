/**
 * useProgressSync — queues game progress events in IndexedDB and syncs them
 * to POST /progress/sync when the device is online.
 *
 * Sync fires automatically:
 *  - When syncNow() is called (e.g. on session complete)
 *  - When the window fires the 'online' event
 *
 * // TODO: Replace with Helios token introspection when available.
 */

import { useState, useEffect, useCallback, useRef } from 'react';
import { getRecord, putRecord } from './idbUtils.js';

const OUTBOX_KEY = 'progress-outbox:pending';

/**
 * @param {object} opts
 * @param {string} opts.restUrl   Base REST URL
 * @returns {{ addEvent: Function, syncNow: Function, syncing: boolean }}
 */
export function useProgressSync({ restUrl }) {
    const [syncing, setSyncing] = useState(false);
    const isSyncing = useRef(false);

    /**
     * Add a progress event to the outbox.
     *
     * @param {object} event  e.g. { type: 'aiwa_game_word_correct', word_uuid: '...', game: 'listen_write' }
     */
    const addEvent = useCallback(async (event) => {
        const outbox = await getRecord('progress-outbox', OUTBOX_KEY);
        const events = outbox?.events ?? [];
        await putRecord('progress-outbox', {
            key: OUTBOX_KEY,
            events: [...events, { ...event, ts: Date.now() }],
        });
    }, []);

    /**
     * Flush all queued events to the server.
     * Requires a Helios Bearer token — falls back gracefully if none is present.
     *
     * // TODO: Replace with Helios token introspection when available.
     */
    const syncNow = useCallback(async () => {
        if (isSyncing.current || !navigator.onLine) return;

        const outbox = await getRecord('progress-outbox', OUTBOX_KEY);
        if (!outbox?.events?.length) return;

        isSyncing.current = true;
        setSyncing(true);

        try {
            /* Retrieve Helios Bearer token from localStorage (temporary guard). */
            const token = localStorage.getItem('aiwa-helios-token') ?? '';

            const res = await fetch(`${restUrl}/progress/sync`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    ...(token ? { Authorization: `Bearer ${token}` } : {}),
                },
                body: JSON.stringify({ events: outbox.events }),
            });

            if (res.ok) {
                /* Clear the outbox on success. */
                await putRecord('progress-outbox', { key: OUTBOX_KEY, events: [] });
            }
        } catch {
            /* Network error — events remain in outbox for next online event. */
        } finally {
            isSyncing.current = false;
            setSyncing(false);
        }
    }, [restUrl]);

    /* Re-attempt sync whenever the device comes back online. */
    useEffect(() => {
        const handler = () => syncNow();
        window.addEventListener('online', handler);
        return () => window.removeEventListener('online', handler);
    }, [syncNow]);

    return { addEvent, syncNow, syncing };
}
