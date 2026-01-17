import React, { useState, useMemo, useRef } from 'react';
import { createRoot } from 'react-dom/client';
import { ApolloClient, InMemoryCache, gql } from '@apollo/client';
import { useQuery } from '@apollo/client/react';
import { Virtuoso } from 'react-virtuoso';
import { Search, Volume2, X, Globe, BookOpen, Image as ImageIcon } from 'lucide-react';
import '../css/sparxstar-3iatlas-dictionary-form.css';

// --- CONFIGURATION ---
const GRAPHQL_ENDPOINT = window.sparxStarDictionarySettings?.graphqlUrl || '/graphql';

// --- UPDATED QUERY (Matches 2026-01-17 JSON) ---
const GET_ENTRIES = gql`
    query GetAllEntries {
        dictionaries(first: 10000, where: { orderby: { field: TITLE, order: ASC } }) {
            edges {
                node {
                    id
                    title
                    slug
                    dictionaryEntryDetails {
                        entryUuid
                        aiwaTranslationEnglish
                        aiwaTranslationFrench
                        aiwaPartOfSpeech
                        aiwaIpaPronunciation
                        phoneticProunciation
                        aiwaOrigin
                        aiwaExtract
                        aiwaSearchStringEnglish
                        aiwaSearchStringFrench
                        
                        aiwaAudioFile {
                            node { mediaItemUrl }
                        }
                        aiwaWordPhoto {
                            node { sourceUrl }
                        }
                        
                        aiwaExampleSentences {
                            sentenceExample
                            sentencePhoneticPronunciation
                            sentenceEnglishTranslation
                            sentenceFrenchTranslation
                        }

                        # Relationships
                        aiwaSynonyms {
                            ... on Dictionary { title }
                        }
                        aiwaAntonyms {
                            ... on Dictionary { title }
                        }
                        aiwaPhoneticVariants {
                            ... on Dictionary { title }
                        }
                    }
                }
            }
        }
    }
`;

const client = new ApolloClient({
    uri: GRAPHQL_ENDPOINT,
    cache: new InMemoryCache(),
    defaultOptions: {
        watchQuery: { fetchPolicy: 'cache-first' },
        query: { fetchPolicy: 'cache-first' },
    },
});

// --- COMPONENTS ---

const AudioButton = ({ url }) => {
    const playAudio = (e) => {
        e.stopPropagation();
        const audio = new Audio(url);
        audio.play();
    };
    if (!url) return null;
    return (
        <button onClick={playAudio} className="p-2 rounded-full bg-blue-100 text-blue-600 hover:bg-blue-200 transition-colors">
            <Volume2 size={20} />
        </button>
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

// Helper component for related word lists
const RelatedList = ({ title, items }) => {
    if (!items || items.length === 0) return null;
    return (
        <div className="mt-3">
            <h4 className="text-xs font-bold uppercase text-gray-400 mb-1">{title}</h4>
            <div className="flex flex-wrap gap-2">
                {items.map((item, i) => (
                    <span key={i} className="bg-gray-100 text-gray-700 text-sm px-2 py-1 rounded-md border border-gray-200">
                        {item.title}
                    </span>
                ))}
            </div>
        </div>
    );
};

const WordDetail = ({ word, language, onClose }) => {
    if (!word) return null;
    const d = word.dictionaryEntryDetails;
    const translation = language === 'en' ? d.aiwaTranslationEnglish : d.aiwaTranslationFrench;

    return (
        <div className="fixed inset-0 z-50 flex justify-end md:justify-center items-end md:items-center pointer-events-none">
            <div className="absolute inset-0 bg-black/50 pointer-events-auto transition-opacity" onClick={onClose} />
            
            <div className="bg-white w-full md:w-[600px] h-[85vh] md:h-[80vh] rounded-t-2xl md:rounded-2xl shadow-2xl pointer-events-auto flex flex-col overflow-hidden animate-slide-up">
                
                {/* Image */}
                {d.aiwaWordPhoto?.node?.sourceUrl && (
                    <div className="h-48 w-full relative bg-gray-100 shrink-0">
                        <img src={d.aiwaWordPhoto.node.sourceUrl} alt={word.title} className="w-full h-full object-cover" />
                        <div className="absolute inset-0 bg-gradient-to-t from-black/60 to-transparent" />
                    </div>
                )}

                {/* Header */}
                <div className="p-6 border-b border-gray-100 flex justify-between items-start bg-white z-10">
                    <div>
                        <div className="flex items-center gap-3">
                            <h2 className="text-3xl font-bold text-gray-900">{word.title}</h2>
                            {d.aiwaAudioFile?.node?.mediaItemUrl && <AudioButton url={d.aiwaAudioFile.node.mediaItemUrl} />}
                        </div>
                        <div className="flex flex-wrap items-center gap-2 mt-2 text-gray-600">
                            <span className="italic font-serif text-lg text-gray-500">{d.aiwaPartOfSpeech}</span>
                            {d.aiwaIpaPronunciation && (
                                <span className="bg-gray-100 px-2 py-0.5 rounded text-sm font-mono text-gray-700">/{d.aiwaIpaPronunciation}/</span>
                            )}
                            {d.phoneticProunciation && (
                                <span className="bg-gray-50 border border-gray-200 px-2 py-0.5 rounded text-sm text-gray-600">[{d.phoneticProunciation}]</span>
                            )}
                        </div>
                    </div>
                    <button onClick={onClose} className="p-2 hover:bg-gray-100 rounded-full"><X size={24} /></button>
                </div>

                {/* Content */}
                <div className="overflow-y-auto p-6 space-y-6">
                    {/* Translation Box */}
                    <div className="bg-blue-50 p-4 rounded-xl border border-blue-100">
                        <h3 className="text-sm uppercase tracking-wide text-blue-500 font-bold mb-1">
                            {language === 'en' ? 'English' : 'Français'}
                        </h3>
                        <p className="text-2xl text-blue-900 font-medium">{translation || 'No translation available'}</p>
                    </div>

                    {/* Definition */}
                    {d.aiwaExtract && (
                        <div>
                            <h3 className="flex items-center gap-2 font-bold text-gray-900 mb-2"><BookOpen size={18} /> Definition</h3>
                            <p className="text-gray-700 leading-relaxed">{d.aiwaExtract}</p>
                        </div>
                    )}

                    {/* Relationships (Synonyms/Antonyms) */}
                    {(d.aiwaSynonyms?.length > 0 || d.aiwaAntonyms?.length > 0 || d.aiwaPhoneticVariants?.length > 0) && (
                        <div className="border-t border-b border-gray-100 py-4">
                            <h3 className="flex items-center gap-2 font-bold text-gray-900 mb-2"><LinkIcon size={18} /> Related</h3>
                            <RelatedList title="Synonyms" items={d.aiwaSynonyms} />
                            <RelatedList title="Antonyms" items={d.aiwaAntonyms} />
                            <RelatedList title="Phonetic Variants" items={d.aiwaPhoneticVariants} />
                        </div>
                    )}

                    {/* Example Sentences */}
                    {d.aiwaExampleSentences && d.aiwaExampleSentences.length > 0 && (
                        <div>
                            <h3 className="font-bold text-gray-900 mb-3">Examples</h3>
                            <div className="space-y-4">
                                {d.aiwaExampleSentences.map((ex, idx) => (
                                    <div key={idx} className="pl-4 border-l-4 border-gray-200">
                                        <p className="text-lg text-gray-900 mb-1">{ex.sentenceExample}</p>
                                        {ex.sentencePhoneticPronunciation && (
                                            <p className="text-xs text-gray-400 font-mono mb-1">{ex.sentencePhoneticPronunciation}</p>
                                        )}
                                        <p className="text-gray-500 italic">
                                            {language === 'en' ? ex.sentenceEnglishTranslation : ex.sentenceFrenchTranslation}
                                        </p>
                                    </div>
                                ))}
                            </div>
                        </div>
                    )}

                    {/* Origin */}
                    {d.aiwaOrigin && (
                        <div className="text-sm text-gray-500 border-t pt-4 mt-4">
                            <span className="font-bold text-gray-700">Origin:</span> {d.aiwaOrigin}
                        </div>
                    )}
                </div>
            </div>
        </div>
    );
};

// --- APP ROOT ---

export default function DictionaryApp() {
    const [searchTerm, setSearchTerm] = useState('');
    const [language, setLanguage] = useState('en'); 
    const [selectedWord, setSelectedWord] = useState(null);
    const virtuosoRef = useRef(null);

    const { loading, error, data } = useQuery(GET_ENTRIES, { client });

    const filteredData = useMemo(() => {
        if (!data) return [];
        let entries = data.dictionaries.edges.map((edge) => edge.node);

        if (searchTerm) {
            const lowerSearch = searchTerm.toLowerCase();
            entries = entries.filter((item) => {
                const d = item.dictionaryEntryDetails;
                // Search in Title OR English Search String OR French Search String
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

    if (loading) return <div className="flex h-screen items-center justify-center text-gray-500">Loading Dictionary...</div>;
    if (error) return <div className="p-4 text-red-500">Error: {error.message}</div>;

    return (
        <div className="flex flex-col h-screen bg-gray-50 text-gray-900 font-sans overflow-hidden">
            <header className="bg-white border-b border-gray-200 z-20 shrink-0">
                <div className="max-w-3xl mx-auto px-4 py-3">
                    <div className="flex justify-between items-center mb-3">
                        <h1 className="text-xl font-bold tracking-tight text-gray-800">
                            AI West Africa <span className="text-blue-600">Dictionary</span>
                        </h1>
                        <button
                            onClick={() => setLanguage((l) => (l === 'en' ? 'fr' : 'en'))}
                            className="flex items-center gap-2 bg-gray-100 hover:bg-gray-200 px-3 py-1.5 rounded-full text-sm font-medium transition-colors"
                        >
                            <Globe size={16} /> {language === 'en' ? 'EN' : 'FR'}
                        </button>
                    </div>
                    <div className="relative">
                        <Search className="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400" size={18} />
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
                            onClick={() => setSelectedWord(word)}
                            className="px-4 py-4 border-b border-gray-100 bg-white hover:bg-blue-50 cursor-pointer active:bg-blue-100 transition-colors"
                        >
                            <div className="flex justify-between items-start">
                                <div>
                                    <h3 className="text-lg font-bold text-gray-900">{word.title}</h3>
                                    <p className="text-gray-500 text-sm mt-0.5 line-clamp-1">
                                        {language === 'en'
                                            ? word.dictionaryEntryDetails.aiwaTranslationEnglish
                                            : word.dictionaryEntryDetails.aiwaTranslationFrench}
                                    </p>
                                </div>
                                <div className="flex gap-2">
                                    {word.dictionaryEntryDetails.aiwaWordPhoto?.node && <ImageIcon size={16} className="text-gray-300" />}
                                    <span className="text-xs font-semibold text-gray-400 px-2 py-1 bg-gray-100 rounded">
                                        {word.dictionaryEntryDetails.aiwaPartOfSpeech?.substring(0, 3)}
                                    </span>
                                </div>
                            </div>
                        </div>
                    )}
                />
                <AlphaIndex onSelectLetter={handleScrollToLetter} />
            </div>

            {selectedWord && (
                <WordDetail word={selectedWord} language={language} onClose={() => setSelectedWord(null)} />
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
