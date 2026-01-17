import React, { useState, useMemo, useRef } from 'react';
import { createRoot } from 'react-dom/client';
import { ApolloClient, InMemoryCache, ApolloProvider, gql, useQuery } from '@apollo/client';
import { Virtuoso } from 'react-virtuoso';
import { Search, Volume2, X, BookOpen, Link as LinkIcon, Loader2 } from 'lucide-react';
import '../css/sparxstar-3iatlas-dictionary-form.css';

// --- CONFIGURATION ---
const GRAPHQL_ENDPOINT = window.sparxStarDictionarySettings?.graphqlUrl || '/graphql';

const client = new ApolloClient({
    uri: GRAPHQL_ENDPOINT,
    cache: new InMemoryCache(),
});

// --- QUERY 1: LIGHTWEIGHT INDEX (For the List) ---
const GET_ALL_WORDS_INDEX = gql`
    query GetWordIndex {
        dictionaries(first: 100000, where: { orderby: { field: TITLE, order: ASC } }) {
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
        >
            <Volume2 size={20} />
        </button>
    );
};

// Updated to handle the 'nodes' structure or flat arrays safely
const RelatedList = ({ title, items }) => {
    // Safety check: items might be a connection object (with nodes) or null
    const list = items?.nodes ? items.nodes : items;

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

    // Determine content to display
    const renderContent = () => {
        if (loading) {
            return (
                <div className="h-full flex flex-col items-center justify-center space-y-4">
                    <Loader2 className="animate-spin text-blue-600" size={40} />
                    <p className="text-gray-500">Loading details for {initialTitle}...</p>
                </div>
            );
        }

        if (error) {
            return (
                <div className="p-6 text-red-500 text-center">
                    <p className="font-bold">Error loading details</p>
                    <p className="text-sm mt-2">
                        Unable to load word details. Please try again or contact support if the
                        problem persists.
                    </p>
                </div>
            );
        }

        if (!data?.dictionaryBy) {
            return (
                <div className="h-full flex flex-col items-center justify-center space-y-4">
                    <p className="text-gray-500">No data available</p>
                </div>
            );
        }

        const word = data.dictionaryBy;
        const d = word.dictionaryEntryDetails;
        const translation = language === 'en' ? d.aiwaTranslationEnglish : d.aiwaTranslationFrench;

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

                {/* Sticky Header */}
                <div className="p-6 border-b border-gray-100 flex justify-between items-start bg-white z-10">
                    <div>
                        <div className="flex items-center gap-3">
                            <h2 className="text-3xl font-bold text-gray-900">{word.title}</h2>
                            {d.aiwaAudioFile?.node?.mediaItemUrl && (
                                <AudioButton url={d.aiwaAudioFile.node.mediaItemUrl} />
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
                            {translation || 'No translation available'}
                        </p>
                    </div>

                    {d.aiwaExtract && (
                        <div>
                            <h3 className="flex items-center gap-2 font-bold text-gray-900 mb-2">
                                <BookOpen size={18} /> Definition
                            </h3>
                            <p className="text-gray-700 leading-relaxed">{d.aiwaExtract}</p>
                        </div>
                    )}

                    {/* Relationships */}
                    <div className="border-t border-b border-gray-100 py-4">
                        {((d.aiwaSynonyms?.nodes?.length ?? 0) > 0 ||
                            (d.aiwaAntonyms?.nodes?.length ?? 0) > 0 ||
                            (d.aiwaPhoneticVariants?.nodes?.length ?? 0) > 0) && (
                            <h3 className="flex items-center gap-2 font-bold text-gray-900 mb-2">
                                <LinkIcon size={18} /> Related
                            </h3>
                        )}
                        <RelatedList title="Synonyms" items={d.aiwaSynonyms} />
                        <RelatedList title="Antonyms" items={d.aiwaAntonyms} />
                        <RelatedList title="Phonetic Variants" items={d.aiwaPhoneticVariants} />
                    </div>

                    {d.aiwaExampleSentences && d.aiwaExampleSentences.length > 0 && (
                        <div>
                            <h3 className="font-bold text-gray-900 mb-3">Examples</h3>
                            <div className="space-y-4">
                                {d.aiwaExampleSentences.map((ex, idx) => (
                                    <div key={idx} className="pl-4 border-l-4 border-gray-200">
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

                    {d.aiwaOrigin && (
                        <div className="text-sm text-gray-500 border-t pt-4 mt-4">
                            <span className="font-bold text-gray-700">Origin:</span> {d.aiwaOrigin}
                        </div>
                    )}
                </div>
            </>
        );
    };

    return (
        <div className="fixed inset-0 z-50 flex justify-end md:justify-center items-end md:items-center pointer-events-none">
            <div
                className="absolute inset-0 bg-black/50 pointer-events-auto transition-opacity"
                onClick={onClose}
            />

            <div className="bg-white w-full md:w-[600px] h-[85vh] md:h-[80vh] rounded-t-2xl md:rounded-2xl shadow-2xl pointer-events-auto flex flex-col overflow-hidden animate-slide-up">
                {renderContent()}
            </div>
        </div>
    );
};

// --- MAIN APP ---

const DictionaryApp = () => {
    const [searchTerm, setSearchTerm] = useState('');
    const [selectedModal, setSelectedModal] = useState(null);
    const [language, setLanguage] = useState('en');
    const searchRef = useRef(null);

    const { loading, error, data } = useQuery(GET_ALL_WORDS_INDEX);

    // Filtered list of words
    const filteredWords = useMemo(() => {
        if (!data?.dictionaries?.edges) return [];

        const words = data.dictionaries.edges.map(({ node }) => ({
            id: node.id,
            title: node.title,
            slug: node.slug,
            details: node.dictionaryEntryDetails,
        }));

        if (!searchTerm.trim()) return words;

        const lowerSearch = searchTerm.toLowerCase();
        return words.filter((w) => {
            const englishSearch = w.details.aiwaSearchStringEnglish || '';
            const frenchSearch = w.details.aiwaSearchStringFrench || '';
            return (
                englishSearch.toLowerCase().includes(lowerSearch) ||
                frenchSearch.toLowerCase().includes(lowerSearch) ||
                w.title.toLowerCase().includes(lowerSearch)
            );
        });
    }, [data, searchTerm]);

    if (loading)
        return (
            <div className="min-h-screen flex items-center justify-center">
                <div className="text-center space-y-4">
                    <Loader2 className="animate-spin mx-auto text-blue-600" size={48} />
                    <p className="text-gray-600">Loading dictionary...</p>
                </div>
            </div>
        );

    if (error)
        return (
            <div className="min-h-screen flex items-center justify-center">
                <div className="text-center text-red-600 space-y-2">
                    <p className="font-bold text-xl">Error Loading Dictionary</p>
                    <p className="text-sm">
                        Unable to load the dictionary. Please refresh or contact support.
                    </p>
                </div>
            </div>
        );

    return (
        <div className="min-h-screen bg-gradient-to-br from-blue-50 via-white to-purple-50">
            {/* Sticky Header */}
            <div className="sticky top-0 z-40 bg-white/90 backdrop-blur-lg border-b border-gray-200 shadow-sm">
                <div className="max-w-5xl mx-auto px-4 py-4 space-y-3">
                    {/* Search */}
                    <div className="relative">
                        <Search
                            className="absolute left-4 top-1/2 -translate-y-1/2 text-gray-400"
                            size={20}
                        />
                        <input
                            ref={searchRef}
                            type="text"
                            value={searchTerm}
                            onChange={(e) => setSearchTerm(e.target.value)}
                            placeholder="Search by word or translation..."
                            className="w-full pl-12 pr-4 py-3 rounded-xl border-2 border-gray-200 focus:border-blue-400 focus:ring-4 focus:ring-blue-100 transition-all shadow-sm"
                        />
                    </div>

                    {/* Toggle */}
                    <div className="flex items-center gap-3 justify-center">
                        <span
                            className={`text-sm font-medium transition-colors ${language === 'en' ? 'text-blue-600' : 'text-gray-400'}`}
                        >
                            English
                        </span>
                        <button
                            onClick={() => setLanguage(language === 'en' ? 'fr' : 'en')}
                            className={`relative w-14 h-7 rounded-full transition-colors ${language === 'en' ? 'bg-blue-500' : 'bg-purple-500'}`}
                            aria-label="Toggle language"
                        >
                            <span
                                className={`absolute top-1 left-1 w-5 h-5 bg-white rounded-full shadow-md transition-transform ${language === 'fr' ? 'translate-x-7' : ''}`}
                            />
                        </button>
                        <span
                            className={`text-sm font-medium transition-colors ${language === 'fr' ? 'text-purple-600' : 'text-gray-400'}`}
                        >
                            Français
                        </span>
                    </div>
                </div>
            </div>

            {/* Results */}
            <div className="max-w-5xl mx-auto px-4 py-6">
                <div className="mb-4 text-sm text-gray-500">
                    {filteredWords.length} {filteredWords.length === 1 ? 'word' : 'words'} found
                </div>

                <Virtuoso
                    style={{ height: 'calc(100vh - 220px)' }}
                    data={filteredWords}
                    itemContent={(index, word) => (
                        <div
                            key={word.id}
                            onClick={() => setSelectedModal({ slug: word.slug, title: word.title })}
                            className="px-4 py-4 border-b border-gray-100 bg-white hover:bg-blue-50 cursor-pointer active:bg-blue-100 transition-colors"
                        >
                            <div className="flex justify-between items-start">
                                <div>
                                    <h3 className="text-lg font-bold text-gray-900">
                                        {word.title}
                                    </h3>
                                    <p className="text-gray-500 text-sm mt-0.5 line-clamp-1">
                                        {language === 'en'
                                            ? word.details.aiwaTranslationEnglish
                                            : word.details.aiwaTranslationFrench}
                                    </p>
                                </div>
                                <div className="flex gap-2">
                                    <span className="text-xs font-semibold text-gray-400 px-2 py-1 bg-gray-100 rounded">
                                        {word.details.aiwaPartOfSpeech?.substring(0, 3)}
                                    </span>
                                </div>
                            </div>
                        </div>
                    )}
                />
            </div>

            {/* Modal */}
            {selectedModal && (
                <WordDetailModal
                    slug={selectedModal.slug}
                    initialTitle={selectedModal.title}
                    language={language}
                    onClose={() => setSelectedModal(null)}
                />
            )}
        </div>
    );
};

// --- MOUNT ---

document.addEventListener('DOMContentLoaded', () => {
    const rootId = window.sparxStarDictionarySettings?.root_id || 'sparxstar-dictionary-app';
    const container = document.getElementById(rootId);
    if (container) {
        const root = createRoot(container);
        root.render(
            <ApolloProvider client={client}>
                <DictionaryApp />
            </ApolloProvider>
        );
    }
});
