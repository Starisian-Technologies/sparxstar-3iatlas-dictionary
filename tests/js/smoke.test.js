'use strict';
/**
 * Dictionary API smoke test — validates live response shapes against the contract.
 *
 * Requires Node 18+ (built-in fetch).
 *
 * Run against a local wp-env instance:
 *
 *   DICT_BASE_URL=http://localhost:8888/wp-json/sparxstar/v1/dictionary \
 *   DICT_API_KEY=<plaintext key from: wp sparxstar-dict key generate --label=smoke> \
 *   npx jest tests/js/smoke.test.js --testTimeout=30000
 *
 * Without DICT_BASE_URL the suite is skipped with a descriptive message.
 * Without DICT_API_KEY the /wordlist tests are skipped.
 */

const BASE_URL = (process.env.DICT_BASE_URL || '').replace(/\/$/, '');
const API_KEY = process.env.DICT_API_KEY || '';

if (!BASE_URL) {
    test('smoke tests skipped — DICT_BASE_URL not set', () => {
        console.warn(
            '\nSmoke tests skipped.\n' +
                'Set DICT_BASE_URL (and optionally DICT_API_KEY) to run against a live instance.\n' +
                'Example:\n' +
                '  DICT_BASE_URL=http://localhost:8888/wp-json/sparxstar/v1/dictionary \\\n' +
                '  DICT_API_KEY=<key> npx jest tests/js/smoke.test.js\n'
        );
    });
}

if (!BASE_URL) return; // Jest module-level guard; remaining code unreachable when skipped.

// ─── HTTP helpers ─────────────────────────────────────────────────────────────

/** Shared page token state, populated in beforeAll. */
const state = { pageToken: '' };

/**
 * @param {boolean} [consumerOnly] Suppresses X-Page-Token — use for /wordlist.
 * @returns {Record<string, string>}
 */
function authHeaders(consumerOnly = false) {
    const h = {};
    if (API_KEY) h['X-Api-Key'] = API_KEY;
    if (!consumerOnly && state.pageToken) h['X-Page-Token'] = state.pageToken;
    return h;
}

/**
 * @param {string} path
 * @param {Record<string, string>} [params]
 * @param {boolean} [consumerOnly]
 * @returns {Promise<{ status: number, headers: Headers, json: unknown }>}
 */
async function get(path, params = {}, consumerOnly = false) {
    const qs = new URLSearchParams(params).toString();
    const url = qs ? `${BASE_URL}${path}?${qs}` : `${BASE_URL}${path}`;
    const res = await fetch(url, { headers: authHeaders(consumerOnly) });
    const json = await res.json().catch(() => null);
    return { status: res.status, headers: res.headers, json };
}

/**
 * @param {string} path
 * @param {unknown} body
 * @returns {Promise<{ status: number, headers: Headers, json: unknown }>}
 */
async function post(path, body) {
    const url = `${BASE_URL}${path}`;
    const res = await fetch(url, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', ...authHeaders() },
        body: JSON.stringify(body),
    });
    const json = await res.json().catch(() => null);
    return { status: res.status, headers: res.headers, json };
}

// ─── Contract shape assertions ────────────────────────────────────────────────

function assertEnvelope(json) {
    expect(json).not.toBeNull();
    expect(json.success).toBe(true);
    expect(json.data).toBeDefined();
    expect(json.meta).toBeDefined();
}

function assertDictionaryEntry(entry) {
    const required = [
        'uuid',
        'headword',
        'slug',
        'definition',
        'translation_en',
        'translation_fr',
        'ipa',
        'phonetic',
        'part_of_speech',
        'language',
        'domain',
        'origin',
        'synonyms',
        'antonyms',
        'example_sentences',
    ];
    for (const field of required) {
        expect(entry).toHaveProperty(field);
    }
    expect(Array.isArray(entry.synonyms)).toBe(true);
    expect(Array.isArray(entry.antonyms)).toBe(true);
    expect(Array.isArray(entry.example_sentences)).toBe(true);
}

function assertSearchItem(item) {
    for (const field of [
        'uuid',
        'headword',
        'slug',
        'definition',
        'translation_en',
        'ipa',
        'language',
    ]) {
        expect(item).toHaveProperty(field);
    }
}

function assertWordlistEntry(entry) {
    for (const field of ['headword', 'slug', 'uuid', 'language']) {
        expect(entry).toHaveProperty(field);
    }
}

function assertLanguageTerm(term) {
    expect(typeof term.slug).toBe('string');
    expect(typeof term.name).toBe('string');
    expect(typeof term.count).toBe('number');
}

function assertDomainTerm(term) {
    expect(typeof term.slug).toBe('string');
    expect(typeof term.name).toBe('string');
    expect(typeof term.code).toBe('string');
    expect(typeof term.count).toBe('number');
}

function assertSpellResult(result) {
    expect(typeof result.word).toBe('string');
    expect(typeof result.valid).toBe('boolean');
    expect(Array.isArray(result.suggestions)).toBe(true);
}

// ─── Suite ───────────────────────────────────────────────────────────────────

describe('Dictionary API smoke tests', () => {
    /** Language slug resolved from /languages — used by game-set, wordlist, search */
    let firstLangSlug = null;
    /** Word slug resolved from /search — used by /lookup */
    let firstWordSlug = null;

    beforeAll(async () => {
        // Fetch a page token for the whole suite.
        const res = await fetch(`${BASE_URL}/page-token`);
        if (res.ok) {
            const json = await res.json().catch(() => null);
            state.pageToken = json?.data?.token ?? '';
        }

        // Resolve a language slug for param-dependent tests.
        const langRes = await get('/languages');
        const langs = langRes.json?.data?.languages;
        if (Array.isArray(langs) && langs.length > 0) {
            firstLangSlug = langs[0].slug;
        }

        // Resolve a word slug via /search for /lookup tests.
        if (firstLangSlug || state.pageToken || API_KEY) {
            const searchRes = await get('/search', { q: 'a', per_page: '1' });
            const results = searchRes.json?.data?.results;
            if (Array.isArray(results) && results.length > 0) {
                firstWordSlug = results[0].slug;
            }
        }
    });

    // ─── Auth contract ─────────────────────────────────────────────────────

    describe('auth', () => {
        test('returns 401 without any credentials', async () => {
            const url = `${BASE_URL}/languages`;
            const res = await fetch(url);
            expect(res.status).toBe(401);
        });

        test('GET /languages succeeds with page token', async () => {
            const { status } = await get('/languages');
            expect(status).toBe(200);
        });

        test('GET /wordlist rejects ephemeral page token with 403', async () => {
            if (!state.pageToken) return;
            // Send only the page token, no API key — even if API_KEY is set, omit it here.
            const url = `${BASE_URL}/wordlist?lang_source=${firstLangSlug || 'test'}`;
            const res = await fetch(url, { headers: { 'X-Page-Token': state.pageToken } });
            expect(res.status).toBe(403);
        });
    });

    // ─── GET /page-token ───────────────────────────────────────────────────

    describe('GET /page-token', () => {
        test('returns token and expires_at without any credentials', async () => {
            const res = await fetch(`${BASE_URL}/page-token`);
            const json = await res.json();
            expect(res.status).toBe(200);
            assertEnvelope(json);
            expect(typeof json.data.token).toBe('string');
            expect(json.data.token.length).toBeGreaterThan(0);
            expect(typeof json.data.expires_at).toBe('number');
        });
    });

    // ─── GET /languages ────────────────────────────────────────────────────

    describe('GET /languages', () => {
        test('envelope and LanguageTerm shape', async () => {
            const { status, json } = await get('/languages');
            expect(status).toBe(200);
            assertEnvelope(json);
            expect(Array.isArray(json.data.languages)).toBe(true);
            if (json.data.languages.length > 0) {
                assertLanguageTerm(json.data.languages[0]);
            }
        });

        test('returns ETag header', async () => {
            const res = await fetch(`${BASE_URL}/languages`, { headers: authHeaders() });
            expect(res.headers.get('etag')).toBeTruthy();
        });

        test('returns 304 on conditional GET with matching ETag', async () => {
            const res1 = await fetch(`${BASE_URL}/languages`, { headers: authHeaders() });
            const etag = res1.headers.get('etag');
            if (!etag) return; // Cached endpoint may not yet have content.
            const res2 = await fetch(`${BASE_URL}/languages`, {
                headers: { ...authHeaders(), 'If-None-Match': etag },
            });
            expect(res2.status).toBe(304);
        });

        test('exposes ETag and X-RateLimit-Remaining in response headers', async () => {
            const res = await fetch(`${BASE_URL}/languages`, { headers: authHeaders() });
            // These are in the Access-Control-Expose-Headers allowlist.
            expect(res.headers.get('x-ratelimit-remaining')).not.toBeNull();
        });
    });

    // ─── GET /domains ──────────────────────────────────────────────────────

    describe('GET /domains', () => {
        test('envelope and DomainTerm shape', async () => {
            const { status, json } = await get('/domains');
            expect(status).toBe(200);
            assertEnvelope(json);
            expect(Array.isArray(json.data.domains)).toBe(true);
            if (json.data.domains.length > 0) {
                assertDomainTerm(json.data.domains[0]);
            }
        });
    });

    // ─── GET /lookup ───────────────────────────────────────────────────────

    describe('GET /lookup', () => {
        test('returns DictionaryEntry shape', async () => {
            if (!firstWordSlug) return;
            const { status, json } = await get('/lookup', { slug: firstWordSlug });
            expect(status).toBe(200);
            assertEnvelope(json);
            assertDictionaryEntry(json.data.word);
        });

        test('audio_url absent when include_audio not set', async () => {
            if (!firstWordSlug) return;
            const { json } = await get('/lookup', { slug: firstWordSlug });
            // Key must not be serialised at all (not present, not null) when omitted.
            expect(json.data.word).not.toHaveProperty('audio_url');
        });

        test('audio_url present when include_audio=true', async () => {
            if (!firstWordSlug) return;
            const { json } = await get('/lookup', { slug: firstWordSlug, include_audio: 'true' });
            expect(json.data.word).toHaveProperty('audio_url');
        });

        test('returns 404 for unknown slug', async () => {
            const { status } = await get('/lookup', { slug: '__nonexistent_slug_smoke_xyz__' });
            expect(status).toBe(404);
        });
    });

    // ─── GET /search ───────────────────────────────────────────────────────

    describe('GET /search', () => {
        test('returns SearchItem array with correct shape', async () => {
            const { status, json } = await get('/search', { q: 'a' });
            expect(status).toBe(200);
            assertEnvelope(json);
            expect(Array.isArray(json.data.results)).toBe(true);
            if (json.data.results.length > 0) {
                assertSearchItem(json.data.results[0]);
            }
        });

        test('per_page limits results', async () => {
            const { json } = await get('/search', { q: 'a', per_page: '3' });
            expect(json.data.results.length).toBeLessThanOrEqual(3);
        });
    });

    // ─── GET /wordlist ─────────────────────────────────────────────────────

    describe('GET /wordlist', () => {
        test('returns WordlistEntry array with API key', async () => {
            if (!API_KEY) {
                console.warn('  Skipping /wordlist test — DICT_API_KEY not set.');
                return;
            }
            if (!firstLangSlug) return;
            const { status, json } = await get('/wordlist', { lang_source: firstLangSlug }, true);
            expect(status).toBe(200);
            assertEnvelope(json);
            expect(Array.isArray(json.data.words)).toBe(true);
            if (json.data.words.length > 0) {
                assertWordlistEntry(json.data.words[0]);
            }
        });
    });

    // ─── GET /game-set ─────────────────────────────────────────────────────

    describe('GET /game-set', () => {
        test('non-standard meta shape (no page/per_page)', async () => {
            if (!firstLangSlug) return;
            const { status, json } = await get('/game-set', {
                lang_source: firstLangSlug,
                limit: '5',
            });
            expect(status).toBe(200);
            expect(json.success).toBe(true);
            expect(Array.isArray(json.data.words)).toBe(true);
            // Non-standard meta: has lang_source, domain, include_audio — no page/per_page.
            expect(json.meta).toHaveProperty('total');
            expect(json.meta).toHaveProperty('lang_source');
            expect(json.meta).toHaveProperty('domain');
            expect(json.meta).toHaveProperty('include_audio');
            expect(json.meta).not.toHaveProperty('page');
            expect(json.meta).not.toHaveProperty('per_page');
        });

        test('respects limit param', async () => {
            if (!firstLangSlug) return;
            const { json } = await get('/game-set', { lang_source: firstLangSlug, limit: '5' });
            expect(json.data.words.length).toBeLessThanOrEqual(5);
        });

        test('game words match DictionaryEntry shape', async () => {
            if (!firstLangSlug) return;
            const { json } = await get('/game-set', { lang_source: firstLangSlug, limit: '2' });
            if (json.data.words.length > 0) {
                assertDictionaryEntry(json.data.words[0]);
            }
        });
    });

    // ─── GET /word-of-day ──────────────────────────────────────────────────

    describe('GET /word-of-day', () => {
        test('returns DictionaryEntry with YYYY-MM-DD date', async () => {
            const { status, json } = await get('/word-of-day');
            expect(status).toBe(200);
            assertEnvelope(json);
            assertDictionaryEntry(json.data.word);
            expect(typeof json.data.date).toBe('string');
            expect(json.data.date).toMatch(/^\d{4}-\d{2}-\d{2}$/);
        });
    });

    // ─── POST /spell ───────────────────────────────────────────────────────

    describe('POST /spell', () => {
        test('SpellResult shape and canonical envelope', async () => {
            const { status, json } = await post('/spell', { words: ['hello', 'wrold'] });
            expect(status).toBe(200);
            assertEnvelope(json);
            expect(Array.isArray(json.data.results)).toBe(true);
            if (json.data.results.length > 0) {
                assertSpellResult(json.data.results[0]);
            }
        });

        test('QUIRK: results duplicated at top-level response.results', async () => {
            const { json } = await post('/spell', { words: ['test'] });
            // Legacy field — duplicates data.results. Consumers must read data.results.
            expect(Array.isArray(json.results)).toBe(true);
        });
    });
});
