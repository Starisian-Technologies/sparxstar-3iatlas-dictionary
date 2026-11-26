/**
 * Contract tests for the browser-side display source.
 *
 * The browser half of the seam: it must call the same-origin WordPress adapter
 * only, read the `{ success, data, meta }` envelope, refuse anything that is not
 * JSON, and turn every failure into a controlled error rather than a blank list.
 *
 * These consume the same fixture payloads as the PHP tests
 * (`tests/fixtures/display/`). See the provenance note in
 * `tests/phpunit/DisplayAdapterTest.php`.
 */

const fs = require('fs');
const path = require('path');

const {
    createDisplaySource,
    toDisplayWord,
    toDisplayHit,
    MIN_QUERY_LENGTH,
} = require('../../src/js/displaySource');

const FIXTURES = path.join(__dirname, '..', 'fixtures', 'display');

/**
 * @param {string} name Fixture filename.
 * @returns {string}
 */
function fixture(name) {
    return fs.readFileSync(path.join(FIXTURES, name), 'utf8');
}

/**
 * Turn a Node display-tier entry into what the WordPress adapter hands the
 * browser.
 *
 * The fixtures are the Node's payloads; the browser never sees them directly —
 * `LiveJsonDisplaySource::entry()` renames the fields on the server. This helper
 * mirrors that rename so both test suites assert against one fixture set. If it
 * ever disagrees with the PHP normaliser, the PHP one is right.
 *
 * @param {object} entry Node entry.
 * @returns {object} Adapter entry.
 */
function adapterEntry(entry) {
    return {
        slug: entry.slug,
        headword: entry.header_word,
        language: entry.language,
        locale: entry.locale || '',
        part_of_speech: entry.part_of_speech || '',
        definition: entry.definition || '',
        ipa: entry.ipa_pronunciation || '',
        phonetic: entry.phonetic_pronunciation || '',
        translation_en: entry.translation_en || '',
        translation_fr: entry.translation_fr || '',
        domain: entry.domain || '',
        accepted_spellings: entry.accepted_spellings || [],
        examples: entry.examples || [],
        audio_url: entry.audio_url || null,
        image_url: entry.image_url || null,
    };
}

/**
 * @param {object} hit Node search hit.
 * @returns {object} Adapter search hit.
 */
function adapterHit(hit) {
    return {
        slug: hit.slug,
        headword: hit.header_word,
        language: hit.language || '',
        part_of_speech: hit.part_of_speech || '',
        matched_on: hit.matched_on || '',
    };
}

/**
 * A fetch stand-in that replays queued responses and records the URLs asked for.
 *
 * @param {Array<{status?: number, contentType?: string, body: string}>} queue Responses.
 * @returns {Function}
 */
function fakeFetch(queue) {
    const calls = [];
    const fn = async (url) => {
        calls.push(url);
        const next = queue.shift();
        if (next instanceof Error) throw next;
        return {
            ok: (next.status || 200) < 400,
            status: next.status || 200,
            headers: {
                get: (name) =>
                    name.toLowerCase() === 'content-type'
                        ? next.contentType || 'application/json; charset=utf-8'
                        : null,
            },
            json: async () => JSON.parse(next.body),
        };
    };
    fn.calls = calls;
    return fn;
}

const BASE = '/wp-json/sparxstar/v1/dictionary';

describe('display source — envelope handling', () => {
    test('reads the success envelope and normalises languages', async () => {
        const fetcher = fakeFetch([
            {
                body: JSON.stringify({
                    success: true,
                    data: {
                        languages: [
                            { code: 'zxx', name: 'zxx' },
                            { code: 'mis', name: 'Example Reported Name' },
                        ],
                        default: 'zxx',
                        selector: true,
                    },
                    meta: {},
                }),
            },
        ]);

        const result = await createDisplaySource(BASE, fetcher).getLanguages();

        expect(result.languages).toEqual([
            { slug: 'zxx', name: 'zxx', count: 0 },
            { slug: 'mis', name: 'Example Reported Name', count: 0 },
        ]);
        expect(result.defaultLanguage).toBe('zxx');
        expect(fetcher.calls[0]).toBe(`${BASE}/display/languages`);
    });

    test('an HTML body is a controlled error and is never parsed', async () => {
        const fetcher = fakeFetch([
            { contentType: 'text/html; charset=utf-8', body: fixture('upstream-html-body.html') },
        ]);

        await expect(createDisplaySource(BASE, fetcher).getLanguages()).rejects.toThrow(
            /could not be reached/i
        );
    });

    test('an error envelope becomes a thrown error carrying its message', async () => {
        const fetcher = fakeFetch([{ status: 404, body: fixture('error-not-found.json') }]);

        await expect(createDisplaySource(BASE, fetcher).getEntry('missing', 'zxx')).rejects.toThrow(
            'Not found.'
        );
    });

    test('a success:false body is refused even with a 200', async () => {
        const fetcher = fakeFetch([{ body: JSON.stringify({ ok: true, data: {} }) }]);

        await expect(createDisplaySource(BASE, fetcher).getLanguages()).rejects.toThrow();
    });

    test('malformed JSON is a controlled error', async () => {
        const fetcher = fakeFetch([{ body: '{"success":true,"data":' }]);

        await expect(createDisplaySource(BASE, fetcher).getLanguages()).rejects.toThrow(
            /could not be reached/i
        );
    });
});

describe('display source — requests', () => {
    test('an entry request names exactly one language', async () => {
        const entry = adapterEntry(JSON.parse(fixture('entry-success.json')).data.entry);
        const fetcher = fakeFetch([
            { body: JSON.stringify({ success: true, data: { entry }, meta: {} }) },
        ]);

        await createDisplaySource(BASE, fetcher).getEntry('kaŋo', 'zxx');

        const url = fetcher.calls[0];
        expect(url).toContain('lang=zxx');
        expect(url).toContain(encodeURIComponent('kaŋo'));
    });

    test('the same slug in two languages is disambiguated by the language', async () => {
        const first = adapterEntry(JSON.parse(fixture('entry-success.json')).data.entry);
        const second = adapterEntry(JSON.parse(fixture('entry-other-language.json')).data.entry);
        const fetcher = fakeFetch([
            { body: JSON.stringify({ success: true, data: { entry: first }, meta: {} }) },
            { body: JSON.stringify({ success: true, data: { entry: second }, meta: {} }) },
        ]);

        const source = createDisplaySource(BASE, fetcher);
        const a = await source.getEntry('kaŋo', 'zxx');
        const b = await source.getEntry('kaŋo', 'mis');

        expect(fetcher.calls[0]).toContain('lang=zxx');
        expect(fetcher.calls[1]).toContain('lang=mis');
        expect(a.title).not.toBe(b.title);
    });

    test('search results carry no fabricated counts', async () => {
        const results = JSON.parse(fixture('search-success.json')).data.results.map(adapterHit);
        const fetcher = fakeFetch([
            {
                body: JSON.stringify({
                    success: true,
                    data: { results },
                    meta: { is_suggestion: false, truncated: false },
                }),
            },
        ]);

        const result = await createDisplaySource(BASE, fetcher).search('ka', 'zxx');

        expect(result.words).toHaveLength(2);
        expect(result.words[0].title).toBe('kaŋo');
        expect(result.isSuggestion).toBe(false);
    });
});

describe('display source — entry mapping', () => {
    test('an entry maps onto the shape the Browse components read', () => {
        const word = toDisplayWord(
            adapterEntry(JSON.parse(fixture('entry-success.json')).data.entry)
        );

        expect(word.title).toBe('kaŋo');
        expect(word.slug).toBe('kaŋo');
        expect(word.languages.nodes[0].slug).toBe('zxx');
        expect(word.dictionaryEntryDetails.aiwaIpaPronunciation).toBe('kaŋo');
        expect(word.dictionaryEntryDetails.aiwaExampleSentences).toHaveLength(1);
        expect(word.dictionaryEntryDetails.aiwaAudioFile.node.mediaItemUrl).toContain('audio.mp3');
    });

    test('west African orthography survives the mapping unchanged', () => {
        const word = toDisplayWord(
            adapterEntry(JSON.parse(fixture('entry-success.json')).data.entry)
        );

        expect(word.dictionaryEntryDetails.aiwaExtract).toContain('ŋ ɓ ɗ ñ ɲ ʔ');
        expect(word.dictionaryEntryDetails.aiwaExampleSentences[0].sentenceIpaPronounciation).toBe(
            'ɓa'
        );
    });

    test('a withheld field is absent and the entry still renders', () => {
        const word = toDisplayWord(
            adapterEntry(JSON.parse(fixture('entry-rights-filtered.json')).data.entry)
        );

        expect(word.title).toBe('ɗaa');
        expect(word.dictionaryEntryDetails.aiwaAudioFile).toBeNull();
        expect(word.dictionaryEntryDetails.aiwaWordPhoto).toBeNull();
        expect(word.dictionaryEntryDetails.aiwaExtract).toBe('');
    });

    test('a search hit maps without inventing detail it was not given', () => {
        const word = toDisplayHit(
            adapterHit(JSON.parse(fixture('search-success.json')).data.results[0])
        );

        expect(word.title).toBe('kaŋo');
        expect(word.dictionaryEntryDetails.aiwaExampleSentences).toEqual([]);
        expect(word.dictionaryEntryDetails.aiwaExtract).toBe('');
    });

    test('an entry without a slug is dropped rather than half-rendered', () => {
        expect(toDisplayWord({ headword: 'x' })).toBeNull();
        expect(toDisplayHit(null)).toBeNull();
    });
});

describe('display source — secrecy boundary', () => {
    test('the module contains no upstream host, credential or token handling', () => {
        // Comments are stripped first: this asserts on what the module DOES,
        // not on the prose explaining what it deliberately does not do.
        const code = fs
            .readFileSync(path.join(__dirname, '..', '..', 'src', 'js', 'displaySource.js'), 'utf8')
            .replace(/\/\*[\s\S]*?\*\//g, '')
            .replace(/^\s*\/\/.*$/gm, '');

        expect(code).not.toMatch(/Authorization/i);
        expect(code).not.toMatch(/Bearer/i);
        expect(code).not.toMatch(/client_assertion/i);
        expect(code).not.toMatch(/X-Reader-Ref/i);
        expect(code).not.toMatch(/localStorage/);
        expect(code).not.toMatch(/indexedDB/i);
    });

    test('every request goes to the same-origin adapter path', async () => {
        const fetcher = fakeFetch([
            { body: JSON.stringify({ success: true, data: { languages: [] }, meta: {} }) },
            { body: JSON.stringify({ success: true, data: { domains: [] }, meta: {} }) },
        ]);

        const source = createDisplaySource(BASE, fetcher);
        await source.getLanguages();
        await source.getDomains('zxx');

        fetcher.calls.forEach((url) => {
            expect(url.startsWith(`${BASE}/display/`)).toBe(true);
        });
    });

    test('a minimum query length is declared so single letters are not sent upstream', () => {
        expect(MIN_QUERY_LENGTH).toBeGreaterThanOrEqual(2);
    });
});
