import React, { useState, useMemo, useRef, useEffect } from 'react';
import { createRoot } from 'react-dom/client';
// Consolidated Import to satisfy Webpack
import { ApolloClient, InMemoryCache, gql, ApolloProvider, useQuery } from '@apollo/client';
import { Virtuoso } from 'react-virtuoso';
import {
    Search,
    Volume2,
    X,
    Globe,
    BookOpen,
    Image as ImageIcon,
    Link as LinkIcon,
    Loader2,
    ChevronDown,
} from 'lucide-react';
import '../css/sparxstar-3iatlas-dictionary-style.css';

// --- HELPERS ---
const renderTitle = (title) => {
    if (!title) return <span>Dictionary</span>;
    const parts = title.trim().split(' ');
    if (parts.length === 1) return <span className="text-blue-600">{parts[0]}</span>;
    const lastWord = parts.pop();
    return (
        <>
            {parts.join(' ')} <span className="text-blue-600">{lastWord}</span>
        </>
    );
};

// --- CONFIGURATION ---
const settings = window.sparxstarDictionarySettings || {};
const GRAPHQL_ENDPOINT = settings.graphqlUrl || '/graphql';

const client = new ApolloClient({
    uri: GRAPHQL_ENDPOINT,
    cache: new InMemoryCache(),
    defaultOptions: {
        query: { fetchPolicy: 'cache-first' },
        watchQuery: { fetchPolicy: 'cache-first' },
    },
});

// --- QUERY 1: LIST VIEW ---
const GET_ALL_WORDS_INDEX = gql`
    query GetWordIndex($first: Int = 10000) {
        dictionaries(first: $first, where: { orderby: { field: TITLE, order: ASC } }) {
            edges {
                node {
                    id
                    title
                    slug
                    dictionaryEntryDetails {
                        aiwaTranslationEnglish
                        aiwaTranslationFrench
                        aiwaPartOfSpeech
                        aiwaSearchStringEnglish
                        aiwaSearchStringFrench
                        aiwaWordPhoto {
                            node {
                                id
                            }
                        }
                    }
                }
            }
        }
    }
`;

// --- QUERY 2: DETAIL VIEW ---
const GET_SINGLE_WORD_DETAILS = gql`
    query GetWordDetails($slug: String!) {
        dictionaryBy(slug: $slug) {
            id
            title
            dictionaryEntryDetails {
                aiwaTranslationEnglish
                aiwaTranslationFrench
                aiwaPartOfSpeech
                aiwaIpaPronunciation
                phoneticProunciation
                aiwaOrigin
                aiwaExtract
                aiwaAudioFile {
                    node {
                        mediaItemUrl
                    }
                }
                aiwaWordPhoto {
                    node {
                        sourceUrl
                    }
                }
                aiwaExampleSentences {
                    sentenceExample
                    sentencePhoneticPronunciation
                    sentenceEnglishTranslation
                    sentenceFrenchTranslation
                }
                aiwaSynonyms {
                    nodes {
                        ... on Dictionary {
                            title
                            slug
                        }
                    }
                }
                aiwaAntonyms {
                    nodes {
                        ... on Dictionary {
                            title
                            slug
                        }
                    }
                }
                aiwaPhoneticVariants {
                    nodes {
                        ... on Dictionary {
                            title
                            slug
                        }
                    }
                }
            }
        }
    }
`;

// --- COMPONENTS ---

const AudioButton = ({ url }) => {
    const playAudio = (e) => {
        e.stopPropagation();
        const audio = new Audio(url);
        audio.play();
    };
    if (!url) return null;
    return (
        <button
            onClick={playAudio}
            className="p-2 rounded-full bg-blue-100 text-blue-600 hover:bg-blue-200 transition-colors"
            aria-label="Play pronunciation audio"
            type="button"
        >
            <Volume2 size={20} aria-hidden="true" />
        </button>
    );
};

const RelatedList = ({ title, items }) => {
    const list = items?.nodes || items || [];
    if (list.length === 0) return null;
    return (
        <div className="mt-3">
            <h4 className="text-xs font-bold uppercase text-gray-400 mb-1">{title}</h4>
            <div className="flex flex-wrap gap-2">
                {list.map((item, i) => (
                    <span
                        key={i}
                        className="bg-gray-100 text-gray-700 text-sm px-2 py-1 rounded-md border border-gray-200"
                    >
                        {item.title}
                    </span>
                ))}
            </div>
        </div>
    );
};

// --- MODAL COMPONENT (Fixed Z-Index) ---
const WordDetailModal = ({ slug, initialTitle, language, onClose }) => {
    const { loading, error, data } = useQuery(GET_SINGLE_WORD_DETAILS, {
        variables: { slug },
    });

    return (
        <div
            className="fixed inset-0 z-[9999] flex justify-end md:justify-center items-end md:items-center pointer-events-none"
            role="dialog"
            aria-modal="true"
            aria-labelledby="modal-title"
        >
            {/* Backdrop: Explicit Z-Index 40 */}
            <div
                className="absolute inset-0 bg-black/50 pointer-events-auto transition-opacity z-40 backdrop-blur-sm"
                onClick={onClose}
                aria-hidden="true"
            />

            {/* Card: Explicit Z-Index 50 (Above Backdrop) */}
            <div className="relative z-50 bg-white w-full md:w-[600px] h-[85vh] md:h-[80vh] rounded-t-2xl md:rounded-2xl shadow-2xl pointer-events-auto flex flex-col overflow-hidden animate-slide-up">
                {loading && (
                    <div className="h-full flex flex-col items-center justify-center space-y-4">
                        <Loader2 className="animate-spin text-blue-600" size={40} />
                        <p className="text-gray-500">Loading {initialTitle}...</p>
                    </div>
                )}

                {error && (
                    <div className="p-6 text-red-500 text-center">Error loading details.</div>
                )}

                {!loading && !error && data?.dictionaryBy && (
                    <>
                        {(() => {
                            const word = data.dictionaryBy;
                            const d = word.dictionaryEntryDetails;
                            const translation =
                                language === 'en'
                                    ? d.aiwaTranslationEnglish
                                    : d.aiwaTranslationFrench;

                            return (
                                <>
                                    {/* Header Image */}
                                    {d.aiwaWordPhoto?.node?.sourceUrl && (
                                        <div className="h-48 w-full relative bg-gray-100 shrink-0">
                                            <img
                                                src={d.aiwaWordPhoto.node.sourceUrl}
                                                alt={word.title}
                                                className="w-full h-full object-cover"
                                            />
                                            <div className="absolute inset-0 bg-gradient-to-t from-black/60 to-transparent" />
                                        </div>
                                    )}

                                    {/* Modal Header */}
                                    <div className="p-6 border-b border-gray-100 flex justify-between items-start bg-white z-10 shrink-0">
                                        <div>
                                            <div className="flex items-center gap-3">
                                                <h2 id="modal-title" className="text-3xl font-bold text-gray-900">
                                                    {word.title}
                                                </h2>
                                                {d.aiwaAudioFile?.node?.mediaItemUrl && (
                                                    <AudioButton
                                                        url={d.aiwaAudioFile.node.mediaItemUrl}
                                                    />
                                                )}
                                            </div>
                                            <div className="flex flex-wrap items-center gap-2 mt-2 text-gray-600">
                                                <span className="italic font-serif text-lg text-gray-500">
                                                    {d.aiwaPartOfSpeech}
                                                </span>
                                                {d.aiwaIpaPronunciation && (
                                                    <span className="bg-gray-100 px-2 py-0.5 rounded text-sm font-mono text-gray-700">
                                                        /{d.aiwaIpaPronunciation}/
                                                    </span>
                                                )}
                                                {d.phoneticProunciation && (
                                                    <span className="bg-gray-50 border border-gray-200 px-2 py-0.5 rounded text-sm text-gray-600">
                                                        [{d.phoneticProunciation}]
                                                    </span>
                                                )}
                                            </div>
                                        </div>
                                        <button
                                            onClick={onClose}
                                            className="p-2 hover:bg-gray-100 rounded-full"
                                            aria-label="Close word details"
                                            type="button"
                                        >
                                            <X size={24} aria-hidden="true" />
                                        </button>
                                    </div>

                                    {/* Scrollable Content */}
                                    <div className="overflow-y-auto p-6 space-y-6 flex-1">
                                        <div className="bg-blue-50 p-4 rounded-xl border border-blue-100">
                                            <h3 className="text-sm uppercase tracking-wide text-blue-500 font-bold mb-1">
                                                {language === 'en' ? 'English' : 'Français'}
                                            </h3>
                                            <p className="text-2xl text-blue-900 font-medium" lang={language === 'en' ? 'en' : 'fr'}>
                                                {translation || 'No translation available'}
                                            </p>
                                        </div>

                                        {d.aiwaExtract && (
                                            <div>
                                                <h3 className="flex items-center gap-2 font-bold text-gray-900 mb-2">
                                                    <BookOpen size={18} aria-hidden="true" /> Definition
                                                </h3>
                                                <p className="text-gray-700 leading-relaxed">
                                                    {d.aiwaExtract}
                                                </p>
                                            </div>
                                        )}

                                        <div className="border-t border-b border-gray-100 py-4">
                                            <RelatedList title="Synonyms" items={d.aiwaSynonyms} />
                                            <RelatedList title="Antonyms" items={d.aiwaAntonyms} />
                                            <RelatedList
                                                title="Phonetic Variants"
                                                items={d.aiwaPhoneticVariants}
                                            />
                                        </div>

                                        {d.aiwaExampleSentences &&
                                            d.aiwaExampleSentences.length > 0 && (
                                                <div>
                                                    <h3 className="font-bold text-gray-900 mb-3">
                                                        Examples
                                                    </h3>
                                                    <div className="space-y-4">
                                                        {d.aiwaExampleSentences.map((ex, idx) => (
                                                            <div
                                                                key={idx}
                                                                className="pl-4 border-l-4 border-gray-200"
                                                            >
                                                                <p className="text-lg text-gray-900 mb-1">
                                                                    {ex.sentenceExample}
                                                                </p>
                                                                {ex.sentencePhoneticPronunciation && (
                                                                    <p className="text-xs text-gray-400 font-mono mb-1">
                                                                        {
                                                                            ex.sentencePhoneticPronunciation
                                                                        }
                                                                    </p>
                                                                )}
                                                                <p className="text-gray-500 italic">
                                                                    {language === 'en'
                                                                        ? ex.sentenceEnglishTranslation
                                                                        : ex.sentenceFrenchTranslation}
                                                                </p>
                                                            </div>
                                                        ))}
                                                    </div>
                                                </div>
                                            )}
                                        {d.aiwaOrigin && (
                                            <div className="text-sm text-gray-500 border-t pt-4 mt-4">
                                                <span className="font-bold text-gray-700">
                                                    Origin:
                                                </span>{' '}
                                                {d.aiwaOrigin}
                                            </div>
                                        )}
                                    </div>
                                </>
                            );
                        })()}
                    </>
                )}
            </div>
        </div>
    );
};

const AlphaIndex = ({ onSelectLetter }) => {
    const alphabet = '#ABCDEFGHIJKLMNOPQRSTUVWXYZ'.split('');
    return (
        <nav
            className="hidden md:flex flex-col fixed right-2 top-24 bottom-4 w-6 items-center justify-center z-[50] text-xs text-gray-500 font-bold overflow-y-auto"
            aria-label="Alphabetical navigation"
        >
            {alphabet.map((char) => (
                <button
                    key={char}
                    onClick={() => onSelectLetter(char)}
                    className="hover:text-blue-600 hover:scale-150 transition-transform py-1 px-2 cursor-pointer"
                    aria-label={`Jump to words starting with ${char === '#' ? 'number' : char}`}
                    type="button"
                >
                    {char}
                </button>
            ))}
        </nav>
    );
};

// --- MAIN APP ---
export default function DictionaryApp({ appTitle }) {
    const [searchTerm, setSearchTerm] = useState('');
    const [language, setLanguage] = useState('en');
    const [selectedWordSlug, setSelectedWordSlug] = useState(null);
    const [selectedWordTitle, setSelectedWordTitle] = useState('');
    const [showScrollHint, setShowScrollHint] = useState(true);
    const [scrollState, setScrollState] = useState({ atTop: true, atBottom: false });
    const virtuosoRef = useRef(null);

    const { loading, error, data } = useQuery(GET_ALL_WORDS_INDEX, { client });

    // Auto-hide scroll hint after 3 seconds
    useEffect(() => {
        const timer = setTimeout(() => setShowScrollHint(false), 3000);
        return () => clearTimeout(timer);
    }, []);

    // FIXED: Search Logic Crash
    const filteredData = useMemo(() => {
        if (!data) return [];
        let entries = data.dictionaries.edges.map((edge) => edge.node);

        if (searchTerm) {
            const lowerSearch = searchTerm.toLowerCase();
            entries = entries.filter((item) => {
                const d = item.dictionaryEntryDetails;
                // FIX: Use || '' to prevent crashing if fields are null
                return (
                    (item.title || '').toLowerCase().includes(lowerSearch) ||
                    (d.aiwaSearchStringEnglish || '').toLowerCase().includes(lowerSearch) ||
                    (d.aiwaSearchStringFrench || '').toLowerCase().includes(lowerSearch)
                );
            });
        }
        return entries;
    }, [data, searchTerm]);

    // FIXED: Letter Jump Logic (Trimming whitespace)
    const handleScrollToLetter = (char) => {
        if (!virtuosoRef.current) return;

        // Find index of first word starting with char (Case insensitive, trimmed)
        const index = filteredData.findIndex((item) => {
            const cleanTitle = item.title.trim().toUpperCase();
            return cleanTitle.startsWith(char);
        });

        if (index !== -1) {
            virtuosoRef.current.scrollToIndex({ index, align: 'start', behavior: 'auto' });
        }
    };

    if (loading)
        return (
            <div className="flex h-screen items-center justify-center flex-col gap-4">
                <Loader2 className="animate-spin text-blue-600" size={48} />
                <p className="text-gray-500 font-medium">Loading Dictionary...</p>
            </div>
        );

    if (error) return <div className="p-4 text-red-500">Error: {error.message}</div>;

    // NEW: Prefetch logic
    const prefetchWord = (slug) => {
        // We use client.query directly.
        // This fetches data into the Apollo Cache without triggering a UI loading state.
        client.query({
            query: GET_SINGLE_WORD_DETAILS,
            variables: { slug },
            fetchPolicy: 'cache-first', // Only fetch if not already in memory
        });
    };

    return (
        <div className="flex flex-col h-screen bg-gray-50 text-gray-900 font-sans overflow-hidden">
            <header className="bg-white border-b border-gray-200 z-20 shrink-0">
                <div className="max-w-3xl mx-auto px-4 py-3">
                    <div className="flex justify-between items-center mb-3">
                        <h1 className="text-xl font-bold tracking-tight text-gray-800">
                            {renderTitle(appTitle)}
                        </h1>
                        <button
                            onClick={() => setLanguage((l) => (l === 'en' ? 'fr' : 'en'))}
                            className="flex items-center gap-2 bg-gray-100 hover:bg-gray-200 px-3 py-1.5 rounded-full text-sm font-medium transition-colors"
                            aria-label={`Switch to ${language === 'en' ? 'French' : 'English'} translation`}
                            type="button"
                        >
                            <Globe size={16} aria-hidden="true" /> {language === 'en' ? 'EN' : 'FR'}
                        </button>
                    </div>
                    <div className="relative">
                        <label htmlFor="dictionary-search" className="sr-only">
                            Search dictionary words
                        </label>
                        <Search
                            className="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400"
                            size={18}
                            aria-hidden="true"
                        />
                        <input
                            id="dictionary-search"
                            type="search"
                            placeholder={`Search ${filteredData.length} words...`}
                            value={searchTerm}
                            onChange={(e) => setSearchTerm(e.target.value)}
                            className="w-full bg-gray-100 text-gray-900 pl-10 pr-4 py-3 rounded-xl focus:outline-none focus:ring-2 focus:ring-blue-500 transition-all"
                            aria-label="Search dictionary words"
                        />
                    </div>
                </div>
            </header>

            <main className="flex-1 max-w-3xl mx-auto w-full relative">
                <div className="sr-only" role="status" aria-live="polite" aria-atomic="true">
                    {filteredData.length} words found
                </div>

                {/* Top scroll fade indicator */}
                <div
                    className={`absolute top-0 left-0 right-0 h-8 bg-gradient-to-b from-gray-50 to-transparent pointer-events-none z-10 transition-opacity duration-300 ${scrollState.atTop ? 'opacity-0' : 'opacity-100'
                        }`}
                    aria-hidden="true"
                />

                {/* Bottom scroll fade indicator */}
                <div
                    className={`absolute bottom-0 left-0 right-0 h-12 bg-gradient-to-t from-gray-50 via-gray-50/80 to-transparent pointer-events-none z-10 transition-opacity duration-300 ${scrollState.atBottom ? 'opacity-0' : 'opacity-100'
                        }`}
                    aria-hidden="true"
                />

                {/* Initial scroll hint - mobile friendly */}
                {showScrollHint && !scrollState.atBottom && filteredData.length > 5 && (
                    <div
                        className="absolute bottom-4 left-1/2 -translate-x-1/2 z-20 pointer-events-none animate-bounce"
                        aria-hidden="true"
                    >
                        <div className="bg-blue-600 text-white px-4 py-2 rounded-full shadow-lg flex items-center gap-2 text-sm font-medium">
                            <span>Scroll for more</span>
                            <ChevronDown size={16} />
                        </div>
                    </div>
                )}

                <Virtuoso
                    ref={virtuosoRef}
                    data={filteredData}
                    totalCount={filteredData.length}
                    className="h-full w-full scrollbar-hide"
                    atTopStateChange={(atTop) => {
                        setScrollState(prev => ({ ...prev, atTop }));
                        if (!atTop) setShowScrollHint(false);
                    }}
                    atBottomStateChange={(atBottom) => {
                        setScrollState(prev => ({ ...prev, atBottom }));
                        if (atBottom) setShowScrollHint(false);
                    }}
                    itemContent={(index, word) => (
                        <div
                            role="button"
                            tabIndex={0}
                            onClick={() => {
                                setSelectedWordTitle(word.title);
                                setSelectedWordSlug(word.slug);
                            }}
                            onKeyDown={(e) => {
                                if (e.key === 'Enter' || e.key === ' ') {
                                    e.preventDefault();
                                    setSelectedWordTitle(word.title);
                                    setSelectedWordSlug(word.slug);
                                }
                                prefetchWord(word.slug);
                            }}
                            // NEW: Prefetch on hover (Desktop)
                            onMouseEnter={() => prefetchWord(word.slug)}
                            // NEW: Prefetch on touch start (Mobile) - triggers before the 'click' registers
                            onTouchStart={() => prefetchWord(word.slug)}
                            className="px-4 py-4 border-b border-gray-100 bg-white hover:bg-blue-50 cursor-pointer active:bg-blue-100 transition-colors"
                            aria-label={`View details for ${word.title}`}
                        >
                            <div className="flex justify-between items-start">
                                <div>
                                    <h3 className="text-lg font-bold text-gray-900">
                                        {word.title}
                                    </h3>
                                    <p className="text-gray-500 text-sm mt-0.5 line-clamp-1">
                                        {language === 'en'
                                            ? word.dictionaryEntryDetails.aiwaTranslationEnglish
                                            : word.dictionaryEntryDetails.aiwaTranslationFrench}
                                    </p>
                                </div>
                                <div className="flex gap-2 items-center">
                                    {word.dictionaryEntryDetails?.aiwaWordPhoto?.node && (
                                        <ImageIcon className="text-blue-500" size={16} aria-hidden="true" />
                                    )}
                                    <span className="text-xs font-semibold text-gray-400 px-2 py-1 bg-gray-100 rounded" aria-label={`Part of speech: ${word.dictionaryEntryDetails.aiwaPartOfSpeech}`}>
                                        {word.dictionaryEntryDetails.aiwaPartOfSpeech?.substring(
                                            0,
                                            3
                                        )}
                                    </span>
                                </div>
                            </div>
                        </div>
                    )}
                />
                <AlphaIndex onSelectLetter={handleScrollToLetter} />
            </main>

            {selectedWordSlug && (
                <WordDetailModal
                    slug={selectedWordSlug}
                    initialTitle={selectedWordTitle}
                    language={language}
                    onClose={() => setSelectedWordSlug(null)}
                />
            )}
        </div>
    );
}

document.addEventListener('DOMContentLoaded', () => {
    const rootId = window.sparxstarDictionarySettings?.root_id || 'sparxstar-dictionary-root';
    const container = document.getElementById(rootId);
    if (container) {
        const appTitle = container.dataset.title || 'Dictionary';
        const root = createRoot(container);
        root.render(
            <ApolloProvider client={client}>
                <DictionaryApp appTitle={appTitle} />
            </ApolloProvider>
        );
    }
});
