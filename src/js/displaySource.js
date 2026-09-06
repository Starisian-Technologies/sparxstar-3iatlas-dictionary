/**
 * Browse mode's data source when the display adapter is switched on.
 *
 * The browser talks to the same-origin WordPress adapter under
 * `/wp-json/sparxstar/v1/dictionary/display/*` and to nothing else. It never
 * learns the Dictionary Node's URL, never holds a dictionary credential, and
 * never receives a signed upstream header (contract §1, §9).
 *
 * Nothing here is written to localStorage, IndexedDB, or any other client
 * store: dictionary lookups are server-side, and the device keeps no corpus.
 *
 * @package sparxstar-3iatlas-dictionary
 */

/**
 * The word shape the Browse components consume.
 *
 * These key names are the React components' current prop contract, inherited
 * from the WPGraphQL schema they were written against. They are kept verbatim
 * on purpose: the legacy GraphQL read path is scheduled for deletion after the
 * tested cutover (contract §7 step 5), and keeping one shape means that
 * deletion is one commit rather than a rewrite of every component.
 *
 * @typedef {object} DisplayWord
 */

/** Minimum query length before a search is issued. */
export const MIN_QUERY_LENGTH = 2;

/**
 * Read the `{ success, data, meta }` envelope the WordPress adapter returns.
 *
 * Every failure — an error envelope, a non-JSON body, a transport failure —
 * becomes a thrown Error with a reader-safe message. There is no silent empty
 * result: a blank page is a defect, not a degradation (DICT-ADR-001).
 *
 * @param {Response} res Fetch response.
 * @returns {Promise<{data: object, meta: object}>}
 */
async function readEnvelope(res) {
    const contentType = res.headers.get('content-type') || '';
    if (!contentType.toLowerCase().includes('application/json')) {
        throw new Error('The dictionary could not be reached.');
    }

    let json = null;
    try {
        json = await res.json();
    } catch {
        throw new Error('The dictionary could not be reached.');
    }

    if (!res.ok || json?.success !== true) {
        throw new Error(
            typeof json?.message === 'string' && json.message
                ? json.message
                : 'The dictionary could not answer that request.'
        );
    }

    return { data: json.data ?? {}, meta: json.meta ?? {} };
}

/**
 * Map one adapter entry onto the component word shape.
 *
 * Language NAMES are never invented here. An entry carries its ISO 639-3 code;
 * the readable name comes from the adapter's language list, which is the Node's
 * to supply (contract §5).
 *
 * @param {object} entry Adapter entry.
 * @returns {DisplayWord|null}
 */
export function toDisplayWord(entry) {
    if (!entry || typeof entry.slug !== 'string' || !entry.slug) return null;

    const examples = Array.isArray(entry.examples) ? entry.examples : [];
    const audioUrl = entry.audio_url || null;
    const imageUrl = entry.image_url || null;

    return {
        id: `display:${entry.language || ''}:${entry.slug}`,
        title: entry.headword || entry.slug,
        slug: entry.slug,
        languages: {
            nodes: entry.language ? [{ slug: entry.language, name: entry.language }] : [],
        },
        dictionaryEntryDetails: {
            aiwaTranslationEnglish: entry.translation_en || '',
            aiwaTranslationFrench: entry.translation_fr || '',
            aiwaPartOfSpeech: entry.part_of_speech || '',
            aiwaSearchStringEnglish: '',
            aiwaSearchStringFrench: '',
            aiwaIpaPronunciation: entry.ipa || '',
            phoneticProunciation: entry.phonetic || '',
            aiwaOrigin: '',
            aiwaExtract: entry.definition || '',
            // A field the service withholds on rights grounds arrives absent or
            // empty; the entry still renders without it.
            aiwaAudioFile: audioUrl ? { node: { mediaItemUrl: audioUrl } } : null,
            aiwaWordPhoto: imageUrl ? { node: { id: entry.slug, sourceUrl: imageUrl } } : null,
            aiwaExampleSentences: examples.map((example) => ({
                sentenceExample: example.sentence || '',
                sentenceIpaPronounciation: example.ipa || '',
                sentencePhoneticPronunciation: example.phonetic || '',
                sentenceEnglishTranslation: example.translation_en || '',
                sentenceFrenchTranslation: example.translation_fr || '',
            })),
            // Relations are not part of the display projection.
            aiwaSynonyms: { nodes: [] },
            aiwaAntonyms: { nodes: [] },
            aiwaPhoneticVariants: { nodes: [] },
        },
    };
}

/**
 * Map one search hit onto the component word shape. A hit reveals a headword,
 * not an entry, so the detail fields are empty until the entry is opened.
 *
 * @param {object} hit Adapter search hit.
 * @returns {DisplayWord|null}
 */
export function toDisplayHit(hit) {
    if (!hit || typeof hit.slug !== 'string' || !hit.slug) return null;

    return toDisplayWord({
        slug: hit.slug,
        headword: hit.headword,
        language: hit.language,
        part_of_speech: hit.part_of_speech,
        examples: [],
    });
}

/**
 * Build a display source bound to one same-origin base URL and one fetcher.
 *
 * @param {string}   baseUrl   The site's dictionary REST base, e.g. `/wp-json/sparxstar/v1/dictionary`.
 * @param {Function} apiFetch  Authenticated same-origin fetch helper.
 * @returns {object} The five read operations Browse mode needs.
 */
export function createDisplaySource(baseUrl, apiFetch) {
    const root = `${String(baseUrl).replace(/\/$/, '')}/display`;

    /**
     * @param {string} path  Route path under `/display`.
     * @param {object} query Query parameters; empty values are dropped.
     * @returns {Promise<{data: object, meta: object}>}
     */
    async function get(path, query = {}) {
        const params = new URLSearchParams();
        Object.entries(query).forEach(([key, value]) => {
            if (value !== undefined && value !== null && value !== '') {
                params.set(key, String(value));
            }
        });
        const qs = params.toString();
        return readEnvelope(await apiFetch(qs ? `${root}${path}?${qs}` : `${root}${path}`));
    }

    return {
        /**
         * Languages this site shows, with the service's own display names.
         *
         * @returns {Promise<{languages: Array<{slug: string, name: string, count: number}>, defaultLanguage: string, showSelector: boolean, notice: string}>}
         */
        async getLanguages() {
            const { data, meta } = await get('/languages');
            const languages = Array.isArray(data.languages) ? data.languages : [];
            return {
                // `count` stays 0: exact corpus counts are suppressed upstream
                // and the UI hides the badge when there is no count.
                languages: languages
                    .filter((lang) => lang && typeof lang.code === 'string' && lang.code)
                    .map((lang) => ({ slug: lang.code, name: lang.name || lang.code, count: 0 })),
                defaultLanguage: typeof data.default === 'string' ? data.default : '',
                showSelector: data.selector !== false,
                notice: typeof meta.notice === 'string' ? meta.notice : '',
            };
        },

        /**
         * Bounded search inside exactly one language.
         *
         * @param {string} query    The reader's query.
         * @param {string} language ISO 639-3 code.
         * @param {object} options  Optional `limit`.
         * @returns {Promise<{words: Array<DisplayWord>, isSuggestion: boolean, truncated: boolean}>}
         */
        async search(query, language, options = {}) {
            const { data, meta } = await get('/search', {
                q: query,
                lang: language,
                limit: options.limit,
            });
            const results = Array.isArray(data.results) ? data.results : [];
            return {
                words: results.map(toDisplayHit).filter(Boolean),
                isSuggestion: meta.is_suggestion === true,
                truncated: meta.truncated === true,
            };
        },

        /**
         * One entry, by slug, in exactly one language.
         *
         * @param {string} slug     Entry slug.
         * @param {string} language ISO 639-3 code.
         * @returns {Promise<DisplayWord|null>}
         */
        async getEntry(slug, language) {
            const { data } = await get('/entry', { slug, lang: language });
            return toDisplayWord(data.entry);
        },

        /**
         * The one entry for today, in one language.
         *
         * @param {string} language ISO 639-3 code.
         * @returns {Promise<{word: DisplayWord|null, date: string}>}
         */
        async getWordOfDay(language) {
            const { data } = await get('/word-of-day', { lang: language });
            return { word: toDisplayWord(data.entry), date: data.date || '' };
        },

        /**
         * Domains available in one language.
         *
         * @param {string} language ISO 639-3 code.
         * @returns {Promise<Array<{code: string, name: string}>>}
         */
        async getDomains(language) {
            const { data } = await get('/domains', { lang: language });
            return Array.isArray(data.domains) ? data.domains : [];
        },
    };
}
