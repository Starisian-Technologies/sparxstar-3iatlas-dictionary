import React, { useState, useMemo, useRef } from 'react';
import { createRoot } from 'react-dom/client';
import { ApolloClient, InMemoryCache, gql, useQuery } from '@apollo/client';
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
} from 'lucide-react';
import '../css/sparxstar-3iatlas-dictionary-form.css';

// --- CONFIGURATION ---
const GRAPHQL_ENDPOINT = window.sparxStarDictionarySettings?.graphqlUrl || '/graphql';

const client = new ApolloClient({
    uri: GRAPHQL_ENDPOINT,
    cache: new InMemoryCache(),
    defaultOptions: {
        query: {
            fetchPolicy: 'cache-first',
            nextFetchPolicy: 'cache-first',
        },
        watchQuery: {
            fetchPolicy: 'cache-first',
            nextFetchPolicy: 'cache-first',
        },
    },
});

// --- QUERY 1: LIGHTWEIGHT INDEX (For the List) ---
const GET_ALL_WORDS_INDEX = gql`
    query GetWordIndex {
        dictionaries(first: 10000, where: { orderby: { field: TITLE, order: ASC } }) {
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
                    }
                }
            }
        }
    }
`;

// --- QUERY 2: HEAVY DETAILS (For the Popup) ---
// FIXED: Relationships now query 'nodes' to handle AcfContentNodeConnection
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

                # --- FIX: Querying inside 'nodes' ---
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
                # If this field still errors, ensure you have clicked "Save" on your ACF Field Group
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

// --- HELPER COMPONENTS ---

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
            aria-label="Play pronunciation"
        >
            <Volume2 size={20} />
        </button>
    );
};

// Updated to handle the 'nodes' structure or flat arrays safely
const RelatedList = ({ title, items }) => {
    // Safety check: items might be a connection object (with nodes) or null
    let list;
    if (Array.isArray(items?.nodes)) {
        list = items.nodes;
    } else if (Array.isArray(items)) {
        list = items;
    } else {
        list = [];
    }

    if (!list || list.length === 0) return null;

    return (
        <div className="mt-3">
            <h4 className="text-xs font-bold uppercase text-gray-400 mb-1">{title}</h4>
            <div className="flex flex-wrap gap-2">
                {list.map((item, i) => (
                    <span
                        key={item.slug || item.title || i}
                        className="bg-gray-100 text-gray-700 text-sm px-2 py-1 rounded-md border border-gray-200"
                    >
                        {item.title}
                    </span>
                ))}
            </div>
        </div>
    );
};

// --- MODAL COMPONENT ---

const WordDetailModal = ({ slug, initialTitle, language, onClose }) => {
    const { loading, error, data } = useQuery(GET_SINGLE_WORD_DETAILS, {
        variables: { slug },
    });

    // Memoize computed values to avoid recalculating on every render
    const wordData = useMemo(() => {
        if (!data?.dictionaryBy) return null;

        const word = data.dictionaryBy;
        const d = word.dictionaryEntryDetails;
        const translation = language === 'en' ? d.aiwaTranslationEnglish : d.aiwaTranslationFrench;

        return { word, d, translation };
    }, [data, language]);

    return (
        <div className="fixed inset-0 z-50 flex justify-end md:justify-center items-end md:items-center pointer-events-none">
            <div
                className="absolute inset-0 bg-black/50 pointer-events-auto transition-opacity"
                onClick={onClose}
            />

            <div className="bg-white w-full md:w-[600px] h-[85vh] md:h-[80vh] rounded-t-2xl md:rounded-2xl shadow-2xl pointer-events-auto flex flex-col overflow-hidden animate-slide-up">
                {loading && (
                    <div className="h-full flex flex-col items-center justify-center space-y-4">
                        <Loader2 className="animate-spin text-blue-600" size={40} />
                        <p className="text-gray-500">Loading details for {initialTitle}...</p>
                    </div>
                )}

                {error && (
                    <div className="p-6 text-red-500 text-center">
                        <p className="font-bold">Error loading details</p>
                        <p className="text-sm mt-2">
                            Unable to load word details. Please try again or contact support if the
                            problem persists.
                        </p>
                    </div>
                )}

                {!loading && !error && data && !data.dictionaryBy && (
                    <div className="p-6 text-center text-gray-600">
                        <p className="font-bold text-gray-800">Word not found</p>
                        <p className="text-sm mt-2">
                            No details were found for{' '}
                            <span className="font-semibold">{initialTitle}</span>. It may have been
                            removed or is not yet in the dictionary.
                        </p>
                    </div>
                )}

                {!loading && !error && data && data.dictionaryBy && wordData && (
                    <>
                        {/* Header Image */}
                        {wordData.d.aiwaWordPhoto?.node?.sourceUrl && (
                            <div className="h-48 w-full relative bg-gray-100 shrink-0">
                                <img
                                    src={wordData.d.aiwaWordPhoto.node.sourceUrl}
                                    alt={wordData.word.title}
                                    className="w-full h-full object-cover"
                                />
                                <div className="absolute inset-0 bg-gradient-to-t from-black/60 to-transparent" />
                            </div>
                        )}

                        {/* Sticky Header */}
                        <div className="p-6 border-b border-gray-100 flex justify-between items-start bg-white z-10">
                            <div>
                                <div className="flex items-center gap-3">
                                    <h2 className="text-3xl font-bold text-gray-900">
                                        {wordData.word.title}
                                    </h2>
                                    {wordData.d.aiwaAudioFile?.node?.mediaItemUrl && (
                                        <AudioButton
                                            url={wordData.d.aiwaAudioFile.node.mediaItemUrl}
                                        />
                                    )}
                                </div>
                                <div className="flex flex-wrap items-center gap-2 mt-2 text-gray-600">
                                    <span className="italic font-serif text-lg text-gray-500">
                                        {wordData.d.aiwaPartOfSpeech}
                                    </span>
                                    {wordData.d.aiwaIpaPronunciation && (
                                        <span className="bg-gray-100 px-2 py-0.5 rounded text-sm font-mono text-gray-700">
                                            /{wordData.d.aiwaIpaPronunciation}/
                                        </span>
                                    )}
                                    {wordData.d.phoneticProunciation && (
                                        <span className="bg-gray-50 border border-gray-200 px-2 py-0.5 rounded text-sm text-gray-600">
                                            [{wordData.d.phoneticProunciation}]
                                        </span>
                                    )}
                                </div>
                            </div>
                            <button
                                onClick={onClose}
                                className="p-2 hover:bg-gray-100 rounded-full"
                                aria-label="Close word details"
                            >
                                <X size={24} />
                            </button>
                        </div>

                        {/* Content Scroll */}
                        <div className="overflow-y-auto p-6 space-y-6">
                            <div className="bg-blue-50 p-4 rounded-xl border border-blue-100">
                                <h3 className="text-sm uppercase tracking-wide text-blue-500 font-bold mb-1">
                                    {language === 'en' ? 'English' : 'Français'}
                                </h3>
                                <p className="text-2xl text-blue-900 font-medium">
                                    {wordData.translation || 'No translation available'}
                                </p>
                            </div>

                            {wordData.d.aiwaExtract && (
                                <div>
                                    <h3 className="flex items-center gap-2 font-bold text-gray-900 mb-2">
                                        <BookOpen size={18} /> Definition
                                    </h3>
                                    <p className="text-gray-700 leading-relaxed">
                                        {wordData.d.aiwaExtract}
                                    </p>
                                </div>
                            )}

                            {/* Relationships */}
                            <div className="border-t border-b border-gray-100 py-4">
                                {(wordData.d.aiwaSynonyms?.nodes?.length ||
                                    wordData.d.aiwaAntonyms?.nodes?.length ||
                                    wordData.d.aiwaPhoneticVariants?.nodes?.length) && (
                                    <h3 className="flex items-center gap-2 font-bold text-gray-900 mb-2">
                                        <LinkIcon size={18} /> Related
                                    </h3>
                                )}
                                <RelatedList title="Synonyms" items={wordData.d.aiwaSynonyms} />
                                <RelatedList title="Antonyms" items={wordData.d.aiwaAntonyms} />
                                <RelatedList
                                    title="Phonetic Variants"
                                    items={wordData.d.aiwaPhoneticVariants}
                                />
                            </div>

                            {wordData.d.aiwaExampleSentences &&
                                wordData.d.aiwaExampleSentences.length > 0 && (
                                    <div>
                                        <h3 className="font-bold text-gray-900 mb-3">Examples</h3>
                                        <div className="space-y-4">
                                            {wordData.d.aiwaExampleSentences.map((ex, idx) => (
                                                <div
                                                    key={idx}
                                                    className="pl-4 border-l-4 border-gray-200"
                                                >
                                                    <p className="text-lg text-gray-900 mb-1">
                                                        {ex.sentenceExample}
                                                    </p>
                                                    {ex.sentencePhoneticPronunciation && (
                                                        <p className="text-xs text-gray-400 font-mono mb-1">
                                                            {ex.sentencePhoneticPronunciation}
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

                            {wordData.d.aiwaOrigin && (
                                <div className="text-sm text-gray-500 border-t pt-4 mt-4">
                                    <span className="font-bold text-gray-700">Origin:</span>{' '}
                                    {wordData.d.aiwaOrigin}
                                </div>
                            )}
                        </div>
                    </>
                )}
            </div>
        </div>
    );
};

const AlphaIndex = ({ onSelectLetter }) => {
    const alphabet = '#ABCDEFGHIJKLMNOPQRSTUVWXYZ'.split('');
    return (
        <div className="hidden md:flex flex-col fixed right-2 top-24 bottom-4 w-6 items-center justify-center z-10 text-xs text-gray-500 font-bold overflow-y-auto">
            {alphabet.map((char) => (
                <button
                    key={char}
                    onClick={() => onSelectLetter(char)}
                    className="hover:text-blue-600 hover:scale-125 transition-transform py-0.5"
                >
                    {char}
                </button>
            ))}
        </div>
    );
};

// --- MAIN LIST COMPONENT ---

export default function DictionaryApp() {
    const [searchTerm, setSearchTerm] = useState('');
    const [language, setLanguage] = useState('en');
    const [selectedWordSlug, setSelectedWordSlug] = useState(null);
    const [selectedWordTitle, setSelectedWordTitle] = useState('');
    const virtuosoRef = useRef(null);

    const { loading, error, data } = useQuery(GET_ALL_WORDS_INDEX, { client });

    const filteredData = useMemo(() => {
        if (!data) return [];
        let entries = data.dictionaries.edges.map((edge) => edge.node);

        if (searchTerm) {
            const lowerSearch = searchTerm.toLowerCase();
            entries = entries.filter((item) => {
                const d = item.dictionaryEntryDetails;
                return (
                    item.title.toLowerCase().includes(lowerSearch) ||
                    d.aiwaSearchStringEnglish?.toLowerCase().includes(lowerSearch) ||
                    d.aiwaSearchStringFrench?.toLowerCase().includes(lowerSearch)
                );
            });
        }
        return entries;
    }, [data, searchTerm]);

    const handleScrollToLetter = (char) => {
        const index = filteredData.findIndex((item) => item.title.toUpperCase().startsWith(char));
        if (index !== -1 && virtuosoRef.current) {
            virtuosoRef.current.scrollToIndex({ index, align: 'start' });
        }
    };

    const handleWordClick = (word) => {
        setSelectedWordTitle(word.title);
        setSelectedWordSlug(word.slug);
    };

    if (loading)
        return (
            <div className="flex h-screen items-center justify-center flex-col gap-4">
                <Loader2 className="animate-spin text-blue-600" size={48} />
                <p className="text-gray-500 font-medium">Loading Words...</p>
            </div>
        );

    if (error) return <div className="p-4 text-red-500">Error: {error.message}</div>;

    return (
        <div className="flex flex-col h-screen bg-gray-50 text-gray-900 font-sans overflow-hidden">
            <header className="bg-white border-b border-gray-200 z-20 shrink-0">
                <div className="max-w-3xl mx-auto px-4 py-3">
                    <div className="flex justify-between items-center mb-3">
                        <h1 className="text-xl font-bold tracking-tight text-gray-800">
                            AIWA <span className="text-blue-600">Dictionary</span>
                        </h1>
                        <button
                            onClick={() => setLanguage((l) => (l === 'en' ? 'fr' : 'en'))}
                            className="flex items-center gap-2 bg-gray-100 hover:bg-gray-200 px-3 py-1.5 rounded-full text-sm font-medium transition-colors"
                        >
                            <Globe size={16} /> {language === 'en' ? 'EN' : 'FR'}
                        </button>
                    </div>
                    <div className="relative">
                        <Search
                            className="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400"
                            size={18}
                        />
                        <input
                            type="text"
                            placeholder={`Search ${filteredData.length} words...`}
                            value={searchTerm}
                            onChange={(e) => setSearchTerm(e.target.value)}
                            className="w-full bg-gray-100 text-gray-900 pl-10 pr-4 py-3 rounded-xl focus:outline-none focus:ring-2 focus:ring-blue-500 transition-all"
                        />
                    </div>
                </div>
            </header>

            <div className="flex-1 max-w-3xl mx-auto w-full relative">
                <Virtuoso
                    ref={virtuosoRef}
                    data={filteredData}
                    totalCount={filteredData.length}
                    className="h-full w-full scrollbar-hide"
                    itemContent={(index, word) => (
                        <div
                            onClick={() => handleWordClick(word)}
                            className="px-4 py-4 border-b border-gray-100 bg-white hover:bg-blue-50 cursor-pointer active:bg-blue-100 transition-colors"
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
                                    {word.dictionaryEntryDetails &&
                                        (word.dictionaryEntryDetails.imageUrl ||
                                            word.dictionaryEntryDetails.photoUrl) && (
                                            <ImageIcon
                                                className="text-blue-500"
                                                size={16}
                                                aria-label="Has image"
                                            />
                                        )}
                                    <span className="text-xs font-semibold text-gray-400 px-2 py-1 bg-gray-100 rounded">
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
            </div>
            {selectedWordSlug && (
                <WordDetailModal
                    slug={selectedWordSlug}
                    initialTitle={selectedWordTitle}
                    language={language}
                    onClose={() => {
                        setSelectedWordSlug(null);
                        setSelectedWordTitle('');
                    }}
                />
            )}
        </div>
    );
}

document.addEventListener('DOMContentLoaded', () => {
    const rootId = window.sparxStarDictionarySettings?.root_id || 'sparxstar-dictionary-root';
    const container = document.getElementById(rootId);
    if (container) {
        const root = createRoot(container);
        root.render(<DictionaryApp />);
    }
});
