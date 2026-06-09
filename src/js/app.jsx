import React, { useState, useMemo, useRef, useEffect, useCallback } from 'react';
import { createRoot } from 'react-dom/client';
import {
    ApolloClient,
    InMemoryCache,
    gql,
    ApolloProvider,
    useQuery,
    HttpLink,
} from '@apollo/client';
import { Virtuoso } from 'react-virtuoso';
import {
    Search,
    Volume2,
    X,
    Globe,
    BookOpen,
    Image as ImageIcon,
    Loader2,
    ChevronRight,
    Heart,
    Clock,
    Compass,
    Home,
    Sun,
    Moon,
    Gamepad2,
    Share2,
    LayoutGrid,
    Users,
    Leaf,
} from 'lucide-react';
import '../css/sparxstar-3iatlas-dictionary-style.css';
import GameShell from './games/GameShell.jsx';

// ---------------------------------------------------------------------------
// Settings
// ---------------------------------------------------------------------------
const settings = window.sparxstarDictionarySettings || {};
const GRAPHQL_ENDPOINT = settings.graphqlUrl || '/graphql';
const REST_URL = settings.restUrl || '/wp-json/sparxstar/v1/dictionary';

// ---------------------------------------------------------------------------
// apiFetch — authenticated fetch helper for dictionary REST endpoints.
// GraphQL calls go to graphqlUrl (different path) — do NOT use apiFetch there.
// ---------------------------------------------------------------------------

/**
 * Refresh the ephemeral page token by calling GET /page-token.
 * Stores the new token in window.sparxstarDictionarySettings.pageToken.
 *
 * @returns {Promise<string>} The new token, or empty string on failure.
 */
async function refreshPageToken() {
    try {
        const res = await fetch(`${REST_URL}/page-token`);
        if (!res.ok) return '';
        const json = await res.json();
        const token = json?.data?.token ?? '';
        if (token && window.sparxstarDictionarySettings) {
            window.sparxstarDictionarySettings.pageToken = token;
        }
        return token;
    } catch {
        return '';
    }
}

/**
 * Authenticated fetch for dictionary REST API endpoints.
 *
 * 1. Reads window.sparxstarDictionarySettings?.pageToken.
 * 2. Adds X-Page-Token header.
 * 3. Makes the fetch.
 * 4. On 401: refreshes the token, retries once.
 * 5. Returns the Response.
 *
 * Do NOT use this for GraphQL calls — those go to graphqlUrl.
 *
 * @param {string} url     Full URL (should start with REST_URL).
 * @param {object} options fetch() options (optional).
 * @returns {Promise<Response>}
 */
async function apiFetch(url, options = {}) {
    const token = window.sparxstarDictionarySettings?.pageToken ?? '';
    const headers = { ...(options.headers || {}), 'X-Page-Token': token };

    let res = await fetch(url, { ...options, headers });

    if (res.status === 401) {
        const newToken = await refreshPageToken();
        const retryHeaders = { ...(options.headers || {}), 'X-Page-Token': newToken };
        res = await fetch(url, { ...options, headers: retryHeaders });
    }

    return res;
}

// ---------------------------------------------------------------------------
// Apollo client
// ---------------------------------------------------------------------------
const httpLink = new HttpLink({ uri: GRAPHQL_ENDPOINT });
const client = new ApolloClient({
    link: httpLink,
    cache: new InMemoryCache({
        typePolicies: {
            Query: {
                fields: {
                    dictionaries: {
                        merge(existing = {}, incoming) {
                            return { ...existing, ...incoming };
                        },
                    },
                },
            },
            Dictionary: {
                fields: {
                    dictionaryEntryDetails: {
                        merge(existing = {}, incoming) {
                            return { ...existing, ...incoming };
                        },
                    },
                },
            },
        },
    }),
    defaultOptions: {
        query: { fetchPolicy: 'cache-first' },
        watchQuery: { fetchPolicy: 'cache-first' },
    },
});

// ---------------------------------------------------------------------------
// Constants
// ---------------------------------------------------------------------------

/** POS colour map per AIWA brand spec. */
const POS_STYLE = {
    noun: { background: '#FCE4F3', color: '#C2185B' },
    verb: { background: '#E8F5E9', color: '#2E7D32' },
    adjective: { background: '#E3F2FD', color: '#1565C0' },
    phrase: { background: '#E0F7FA', color: '#00796B' },
    adverb: { background: '#FFF8E1', color: '#F57F17' },
};

/** 26 deterministic avatar background colours — A to Z. */
const AVATAR_COLORS = [
    '#E91E8C',
    '#7B3FA0',
    '#009688',
    '#F44336',
    '#FF9800',
    '#4CAF50',
    '#2196F3',
    '#9C27B0',
    '#00BCD4',
    '#FF5722',
    '#607D8B',
    '#795548',
    '#3F51B5',
    '#8BC34A',
    '#FFC107',
    '#E91E63',
    '#03A9F4',
    '#CDDC39',
    '#FF4081',
    '#00E676',
    '#FF6D00',
    '#651FFF',
    '#00B0FF',
    '#1DE9B6',
    '#FFD600',
    '#D500F9',
];

const FILTER_LABELS = ['All', 'Noun', 'Verb', 'Phrase', 'Audio', 'Image'];

const NAV_ITEMS = [
    { id: 'home', label: 'Home', Icon: Home },
    { id: 'explore', label: 'Explore', Icon: Compass },
    { id: 'favorites', label: 'Saved', Icon: Heart },
    { id: 'history', label: 'Recent', Icon: Clock },
    { id: 'play', label: 'Play', Icon: Gamepad2 },
];

/**
 * Desktop sidebar nav (v3 §3.1). Categories is a nav alias that renders ExploreView.
 * Mobile bottom nav is separate (NAV_ITEMS) and additionally carries Play.
 */
const DESKTOP_NAV_ITEMS = [
    { id: 'home', label: 'Home', Icon: Home },
    { id: 'explore', label: 'Explore', Icon: Compass },
    { id: 'favorites', label: 'Favorites', Icon: Heart },
    { id: 'history', label: 'History', Icon: Clock },
    { id: 'categories', label: 'Categories', Icon: LayoutGrid },
];

/** Detail panel feature cards (v3 §3.3) — scroll anchors into the left column. */
const FEATURE_CARDS = [
    {
        label: 'Audio Pronunciation',
        description: 'Listen to the correct pronunciation of each word.',
        iconBg: '#E91E8C',
        icon: Volume2,
        anchor: 'detail-pronunciation',
    },
    {
        label: 'Cultural Images',
        description: 'Visual context helps you connect deeper with the meaning.',
        iconBg: '#7B3FA0',
        icon: ImageIcon,
        anchor: 'detail-image',
    },
    {
        label: 'Example Sentences',
        description: 'See how words are used in real life situations.',
        iconBg: '#1565C0',
        icon: BookOpen,
        anchor: 'detail-examples',
    },
    {
        label: 'Related Words',
        description: 'Explore synonyms, antonyms and word variants.',
        iconBg: '#00796B',
        icon: Users,
        anchor: 'detail-related',
    },
    {
        label: 'Origin & Cultural Notes',
        description: 'Discover the roots and cultural background of words.',
        iconBg: '#F57F17',
        icon: Leaf,
        anchor: 'detail-origin',
    },
];

// ---------------------------------------------------------------------------
// GraphQL queries
// ---------------------------------------------------------------------------
const GET_ALL_WORDS_INDEX = gql`
    query GetWordIndex($first: Int = 20000) {
        dictionaries(first: $first, where: { orderby: { field: TITLE, order: ASC } }) {
            edges {
                node {
                    id
                    title
                    slug
                    languages {
                        nodes {
                            slug
                            name
                        }
                    }
                    dictionaryEntryDetails {
                        aiwaTranslationEnglish
                        aiwaTranslationFrench
                        aiwaPartOfSpeech
                        aiwaSearchStringEnglish
                        aiwaSearchStringFrench
                        aiwaIpaPronunciation
                        aiwaAudioFile {
                            node {
                                mediaItemUrl
                            }
                        }
                        aiwaWordPhoto {
                            node {
                                id
                            }
                        }
                        aiwaExampleSentences {
                            __typename
                        }
                    }
                }
            }
        }
    }
`;

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
                    sentenceIpaPronounciation
                    sentencePhoneticPronunciation
                    sentenceEnglishTranslation
                    sentenceFrenchTranslation
                }
                aiwaSynonyms {
                    nodes {
                        ... on Dictionary {
                            id
                            title
                            slug
                        }
                    }
                }
                aiwaAntonyms {
                    nodes {
                        ... on Dictionary {
                            id
                            title
                            slug
                        }
                    }
                }
                aiwaPhoneticVariants {
                    nodes {
                        ... on Dictionary {
                            id
                            title
                            slug
                        }
                    }
                }
            }
        }
    }
`;

// ---------------------------------------------------------------------------
// Custom hooks
// ---------------------------------------------------------------------------

function useLocalStorage(key, defaultValue) {
    const [value, setValue] = useState(() => {
        try {
            const raw = localStorage.getItem(key);
            return raw !== null ? JSON.parse(raw) : defaultValue;
        } catch {
            return defaultValue;
        }
    });

    const set = useCallback(
        (next) => {
            const resolved = typeof next === 'function' ? next(value) : next;
            setValue(resolved);
            try {
                localStorage.setItem(key, JSON.stringify(resolved));
            } catch {
                /* quota exceeded — degrade silently */
            }
        },
        [key, value]
    );

    return [value, set];
}

function useIsDesktop() {
    const [isDesktop, setIsDesktop] = useState(() => window.innerWidth >= 1024);
    useEffect(() => {
        const mq = window.matchMedia('(min-width: 1024px)');
        const handler = (e) => setIsDesktop(e.matches);
        mq.addEventListener('change', handler);
        return () => mq.removeEventListener('change', handler);
    }, []);
    return isDesktop;
}

// ---------------------------------------------------------------------------
// Helper utilities
// ---------------------------------------------------------------------------

function avatarColor(title) {
    const char = (title || 'A').trim().toUpperCase().charCodeAt(0);
    const idx = Math.max(0, char - 65) % AVATAR_COLORS.length;
    return AVATAR_COLORS[idx];
}

function posStyle(pos) {
    const key = (pos || '').toLowerCase();
    return POS_STYLE[key] || { background: '#F3E5F5', color: '#6A1B9A' };
}

function posKey(pos) {
    const k = (pos || '').toLowerCase();
    return POS_STYLE[k] ? k : 'other';
}

function wordOfDayIndex(total) {
    return total > 0 ? Math.floor(Date.now() / 86400000) % total : 0;
}

// ---------------------------------------------------------------------------
// Components — atoms
// ---------------------------------------------------------------------------

const AvatarCircle = ({ title }) => {
    const bg = avatarColor(title);
    const letter = (title || '?').trim()[0].toUpperCase();
    return (
        <div
            className="flex items-center justify-center rounded-full text-white font-bold text-base shrink-0"
            style={{ width: 40, height: 40, background: bg }}
            aria-hidden="true"
        >
            {letter}
        </div>
    );
};

const POSPill = ({ pos }) => {
    if (!pos) return null;
    return (
        <span className="text-xs font-semibold px-2 py-0.5 rounded-full" style={posStyle(pos)}>
            {pos}
        </span>
    );
};

const AudioButton = ({ url, size = 20 }) => {
    const play = (e) => {
        e.stopPropagation();
        new Audio(url).play().catch(() => {});
    };
    if (!url) return null;
    return (
        <button
            onClick={play}
            className="p-2 rounded-full transition-colors"
            style={{ background: '#FCE4F3', color: '#E91E8C' }}
            aria-label="Play pronunciation audio"
            type="button"
        >
            <Volume2 size={size} aria-hidden="true" />
        </button>
    );
};

const RelatedWordList = ({ title, items, onSelectWord }) => {
    const list = items?.nodes || [];
    if (!list.length) return null;
    return (
        <div className="mt-4">
            <h4 className="text-xs font-bold uppercase text-gray-400 dark:text-gray-500 mb-2">
                {title}
            </h4>
            <div className="flex flex-wrap gap-2">
                {list.map((item) => (
                    <button
                        key={item.id}
                        onClick={() => onSelectWord && onSelectWord(item.slug, item.title)}
                        className="bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-200 text-sm px-3 py-1 rounded-full border border-gray-200 dark:border-gray-600 hover:border-pink-400 transition-colors"
                        type="button"
                    >
                        {item.title}
                    </button>
                ))}
            </div>
        </div>
    );
};

// ---------------------------------------------------------------------------
// Components — Word list row
// ---------------------------------------------------------------------------

const WordListRow = ({ word, language, onSelect, onPrefetch, isFavorite, onFavoriteToggle }) => {
    const d = word.dictionaryEntryDetails;
    const translation = language === 'fr' ? d.aiwaTranslationFrench : d.aiwaTranslationEnglish;
    const hasAudio = !!d.aiwaAudioFile?.node?.mediaItemUrl;
    const hasImage = !!d.aiwaWordPhoto?.node?.id;
    const exampleCount = d.aiwaExampleSentences?.length || 0;

    return (
        <div
            role="button"
            tabIndex={0}
            onClick={() => onSelect(word.slug, word.title)}
            onKeyDown={(e) => {
                if (e.key === 'Enter' || e.key === ' ') {
                    e.preventDefault();
                    onSelect(word.slug, word.title);
                }
            }}
            onMouseEnter={() => onPrefetch(word.slug)}
            onTouchStart={() => onPrefetch(word.slug)}
            className="flex items-center gap-3 px-4 py-3 border-b border-gray-100 dark:border-gray-800 bg-white dark:bg-gray-900 hover:bg-pink-50 dark:hover:bg-gray-800 cursor-pointer active:bg-pink-100 transition-colors"
            aria-label={`View details for ${word.title}`}
        >
            <AvatarCircle title={word.title} />

            <div className="flex-1 min-w-0">
                <div className="flex items-center gap-2 flex-wrap">
                    <span className="font-bold text-base text-gray-900 dark:text-gray-100">
                        {word.title}
                    </span>
                    <POSPill pos={d.aiwaPartOfSpeech} />
                </div>
                {d.aiwaIpaPronunciation && (
                    <span className="text-xs text-gray-400 dark:text-gray-500 font-mono block mt-0.5">
                        /{d.aiwaIpaPronunciation}/
                    </span>
                )}
                {translation && (
                    <p className="text-sm text-gray-500 dark:text-gray-400 mt-0.5 truncate">
                        {translation}
                    </p>
                )}
            </div>

            <div className="flex items-center gap-1 shrink-0">
                {hasAudio && <Volume2 size={14} className="text-pink-400" aria-label="Has audio" />}
                {hasImage && (
                    <ImageIcon size={14} className="text-purple-400" aria-label="Has image" />
                )}
                {exampleCount > 0 && (
                    <span
                        className="text-xs font-semibold text-gray-400"
                        aria-label={`${exampleCount} example sentences`}
                    >
                        {exampleCount}
                    </span>
                )}
                <button
                    onClick={(e) => {
                        e.stopPropagation();
                        onFavoriteToggle(word.slug);
                    }}
                    className={`p-1 rounded-full transition-colors ${isFavorite ? 'text-pink-500' : 'text-gray-300 hover:text-pink-400'}`}
                    aria-label={isFavorite ? 'Remove from saved' : 'Save word'}
                    type="button"
                >
                    <Heart size={14} fill={isFavorite ? 'currentColor' : 'none'} />
                </button>
                <ChevronRight size={14} className="text-gray-300" aria-hidden="true" />
            </div>
        </div>
    );
};

// ---------------------------------------------------------------------------
// Components — Word of the Day card
// ---------------------------------------------------------------------------

const WordOfDayCard = ({ word, language, onSelect }) => {
    if (!word) return null;
    const d = word.dictionaryEntryDetails;
    const translation = language === 'fr' ? d.aiwaTranslationFrench : d.aiwaTranslationEnglish;

    return (
        <div
            className="mx-4 my-3 rounded-2xl overflow-hidden cursor-pointer shadow-md"
            style={{ background: 'linear-gradient(135deg, #E91E8C 0%, #7B3FA0 100%)' }}
            role="button"
            tabIndex={0}
            onClick={() => onSelect(word.slug, word.title)}
            onKeyDown={(e) => {
                if (e.key === 'Enter') onSelect(word.slug, word.title);
            }}
            aria-label={`Word of the day: ${word.title}`}
        >
            {d.aiwaWordPhoto?.node?.sourceUrl && (
                <div className="relative h-28">
                    <img
                        src={d.aiwaWordPhoto.node.sourceUrl}
                        alt={word.title}
                        className="w-full h-full object-cover opacity-40"
                    />
                    <div className="absolute inset-0 bg-gradient-to-t from-purple-900/80 to-transparent" />
                </div>
            )}
            <div className="p-4">
                <span className="text-white/70 text-xs font-semibold uppercase tracking-wider">
                    Word of the day
                </span>
                <h2 className="text-white text-2xl font-bold mt-1">{word.title}</h2>
                {d.aiwaIpaPronunciation && (
                    <p className="text-white/60 text-xs font-mono mt-0.5">
                        /{d.aiwaIpaPronunciation}/
                    </p>
                )}
                {translation && (
                    <p className="text-white/90 text-sm mt-2 line-clamp-2">{translation}</p>
                )}
                <span className="inline-block mt-3 text-white/70 text-xs">Learn more &rarr;</span>
            </div>
        </div>
    );
};

// ---------------------------------------------------------------------------
// Components — Language selector (pills for mobile, list for desktop)
// ---------------------------------------------------------------------------

const LanguageSelectorPills = ({ languages, selected, onSelect }) => (
    <div className="flex gap-2 overflow-x-auto px-4 py-2 scrollbar-hide shrink-0">
        <button
            onClick={() => onSelect(null)}
            className="shrink-0 px-3 py-1 rounded-full text-sm font-medium border transition-colors"
            style={
                !selected
                    ? { background: '#E91E8C', color: 'white', borderColor: 'transparent' }
                    : { background: 'white', color: '#6b7280', borderColor: '#e5e7eb' }
            }
            type="button"
        >
            All
        </button>
        {languages.map((lang) => (
            <button
                key={lang.slug}
                onClick={() => onSelect(lang.slug)}
                className="shrink-0 px-3 py-1 rounded-full text-sm font-medium border transition-colors"
                style={
                    selected === lang.slug
                        ? { background: '#E91E8C', color: 'white', borderColor: 'transparent' }
                        : { background: 'white', color: '#6b7280', borderColor: '#e5e7eb' }
                }
                type="button"
            >
                {lang.name}
                {lang.count > 0 && (
                    <span style={{ marginLeft: 4, fontSize: '0.7rem', opacity: 0.7 }}>
                        ({lang.count})
                    </span>
                )}
            </button>
        ))}
    </div>
);

const LanguageSelectorList = ({ languages, selected, onSelect }) => (
    <div style={{ marginTop: 8 }}>
        <button
            onClick={() => onSelect(null)}
            className="w-full text-left px-3 py-2 rounded-lg text-sm font-medium transition-colors"
            style={!selected ? { background: '#E91E8C', color: 'white' } : { color: '#374151' }}
            type="button"
        >
            All Languages
        </button>
        {languages.map((lang) => (
            <button
                key={lang.slug}
                onClick={() => onSelect(lang.slug)}
                className="w-full text-left px-3 py-2 rounded-lg text-sm font-medium transition-colors flex justify-between items-center"
                style={
                    selected === lang.slug
                        ? { background: '#E91E8C', color: 'white' }
                        : { color: '#374151' }
                }
                type="button"
            >
                <span>{lang.name}</span>
                {lang.count > 0 && (
                    <span style={{ fontSize: '0.75rem', opacity: 0.7 }}>{lang.count}</span>
                )}
            </button>
        ))}
    </div>
);

// ---------------------------------------------------------------------------
// Components — Filter pills
// ---------------------------------------------------------------------------

const FilterPills = ({ active, onChange }) => (
    <div className="flex gap-2 overflow-x-auto px-4 py-2 scrollbar-hide">
        {FILTER_LABELS.map((label) => {
            const key = label.toLowerCase();
            const isActive = active === key;
            return (
                <button
                    key={label}
                    onClick={() => onChange(key)}
                    className="shrink-0 px-3 py-1 rounded-full text-sm font-medium border transition-colors"
                    style={
                        isActive
                            ? { background: '#7B3FA0', color: 'white', borderColor: 'transparent' }
                            : { background: 'white', color: '#6b7280', borderColor: '#e5e7eb' }
                    }
                    type="button"
                >
                    {label}
                </button>
            );
        })}
    </div>
);

// ---------------------------------------------------------------------------
// Components — Alpha bar
// ---------------------------------------------------------------------------

const AlphaBar = ({ onSelect }) => {
    const chars = '#ABCDEFGHIJKLMNOPQRSTUVWXYZ'.split('');
    return (
        <nav
            className="flex overflow-x-auto scrollbar-hide bg-white dark:bg-gray-900 border-t border-gray-100 dark:border-gray-800 px-2 py-1 shrink-0"
            aria-label="Alphabetical navigation"
        >
            {chars.map((c) => (
                <button
                    key={c}
                    onClick={() => onSelect(c)}
                    className="shrink-0 px-2 py-1 text-xs font-bold text-gray-400 hover:text-pink-500 transition-colors"
                    aria-label={`Jump to ${c === '#' ? 'numbers' : c}`}
                    type="button"
                >
                    {c}
                </button>
            ))}
        </nav>
    );
};

// ---------------------------------------------------------------------------
// Components — Detail view (tabbed)
// ---------------------------------------------------------------------------

/** Desktop detail right-column card — a scroll anchor into the left column (v3 §3.3). */
const FeatureCard = ({ icon: Icon, iconBg, label, description, onClick }) => (
    <button
        type="button"
        onClick={onClick}
        className="flex items-start gap-3 p-3 rounded-xl border border-gray-100 dark:border-gray-800 bg-white dark:bg-gray-800 hover:border-pink-200 dark:hover:border-pink-900 transition-colors text-left w-full"
    >
        <div
            className="w-9 h-9 rounded-full flex items-center justify-center shrink-0"
            style={{ background: iconBg }}
        >
            <Icon size={16} className="text-white" />
        </div>
        <div>
            <p className="text-sm font-semibold text-gray-800 dark:text-gray-200">{label}</p>
            <p className="text-xs text-gray-400 dark:text-gray-500 mt-0.5 leading-snug">
                {description}
            </p>
        </div>
    </button>
);

const DETAIL_TABS = ['overview', 'examples', 'related', 'origin'];

const DetailView = ({
    slug,
    initialTitle,
    language,
    onClose,
    onSelectWord,
    favorites,
    onFavoriteToggle,
    isSheet = false,
}) => {
    const [activeTab, setActiveTab] = useState('overview');
    const { loading, error, data } = useQuery(GET_SINGLE_WORD_DETAILS, { variables: { slug } });

    const word = data?.dictionaryBy;
    const d = word?.dictionaryEntryDetails;
    const translation = d
        ? language === 'fr'
            ? d.aiwaTranslationFrench
            : d.aiwaTranslationEnglish
        : null;
    const exampleCount = d?.aiwaExampleSentences?.length || 0;
    const isFav = favorites.includes(slug);

    const tabLabel = (tab) => {
        if (tab === 'examples') return exampleCount > 0 ? `Examples ${exampleCount}` : 'Examples';
        return tab.charAt(0).toUpperCase() + tab.slice(1);
    };

    const outerClass = isSheet
        ? 'relative z-50 bg-white dark:bg-gray-900 w-full md:w-[600px] h-[85vh] md:h-[80vh] rounded-t-2xl md:rounded-2xl shadow-2xl pointer-events-auto flex flex-col overflow-hidden animate-slide-up'
        : 'flex flex-col h-full bg-white dark:bg-gray-900 overflow-hidden';

    const scrollToAnchor = (anchor) => {
        document.getElementById(anchor)?.scrollIntoView({ behavior: 'smooth', block: 'start' });
    };

    const canShare = isSheet && typeof navigator !== 'undefined' && !!navigator.share;

    const favoriteCta = (
        <button
            type="button"
            onClick={() => onFavoriteToggle(slug)}
            className="flex items-center justify-center gap-2 w-full py-3 rounded-xl font-semibold text-sm text-white transition-colors"
            style={{ background: '#E91E8C' }}
        >
            <Heart size={16} fill={isFav ? 'currentColor' : 'none'} />
            {isFav ? 'Saved' : 'Add to Favorites'}
        </button>
    );

    return (
        <div className={outerClass}>
            {loading && (
                <div className="h-full flex flex-col items-center justify-center space-y-4">
                    <Loader2 className="animate-spin" style={{ color: '#E91E8C' }} size={40} />
                    <p className="text-gray-500 text-sm">Loading {initialTitle}&hellip;</p>
                </div>
            )}

            {error && (
                <div className="p-6 text-red-500 text-center text-sm">
                    Could not load word details.
                </div>
            )}

            {!loading && !error && word && (
                <>
                    {/* Optional header image */}
                    {d.aiwaWordPhoto?.node?.sourceUrl && (
                        <div className="h-40 w-full relative bg-gray-100 shrink-0">
                            <img
                                src={d.aiwaWordPhoto.node.sourceUrl}
                                alt={word.title}
                                className="w-full h-full object-cover"
                            />
                            <div className="absolute inset-0 bg-gradient-to-t from-black/60 to-transparent" />
                        </div>
                    )}

                    {/* Header row */}
                    <div className="p-5 border-b border-gray-100 dark:border-gray-800 flex justify-between items-start shrink-0">
                        <div className="flex-1 min-w-0 pr-3">
                            <div className="flex items-center gap-2 flex-wrap">
                                <h2
                                    id="detail-title"
                                    className="text-2xl font-bold text-gray-900 dark:text-gray-100"
                                >
                                    {word.title}
                                </h2>
                                {d.aiwaAudioFile?.node?.mediaItemUrl && (
                                    <AudioButton
                                        url={d.aiwaAudioFile.node.mediaItemUrl}
                                        size={18}
                                    />
                                )}
                                <button
                                    onClick={() => onFavoriteToggle(slug)}
                                    className={`p-1 rounded-full transition-colors ${isFav ? 'text-pink-500' : 'text-gray-300 hover:text-pink-400'}`}
                                    aria-label={isFav ? 'Remove from saved' : 'Save word'}
                                    type="button"
                                >
                                    <Heart size={18} fill={isFav ? 'currentColor' : 'none'} />
                                </button>
                            </div>
                            <div className="flex flex-wrap items-center gap-2 mt-1">
                                <POSPill pos={d.aiwaPartOfSpeech} />
                                {d.aiwaIpaPronunciation && (
                                    <span className="bg-gray-100 dark:bg-gray-800 px-2 py-0.5 rounded text-xs font-mono text-gray-600 dark:text-gray-300">
                                        /{d.aiwaIpaPronunciation}/
                                    </span>
                                )}
                                {d.phoneticProunciation && (
                                    <span className="bg-gray-50 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 px-2 py-0.5 rounded text-xs text-gray-500">
                                        [{d.phoneticProunciation}]
                                    </span>
                                )}
                            </div>
                        </div>
                        <div className="flex items-center gap-1 shrink-0">
                            {canShare && (
                                <button
                                    type="button"
                                    onClick={() => {
                                        navigator
                                            .share({
                                                title: word.title,
                                                text: `${word.title}${translation ? ` — ${translation}` : ''}`,
                                                url: window.location.href,
                                            })
                                            .catch(() => {});
                                    }}
                                    className="p-2 hover:bg-gray-100 dark:hover:bg-gray-800 rounded-full"
                                    aria-label="Share this word"
                                >
                                    <Share2 size={20} aria-hidden="true" />
                                </button>
                            )}
                            {onClose && (
                                <button
                                    onClick={onClose}
                                    className="p-2 hover:bg-gray-100 dark:hover:bg-gray-800 rounded-full"
                                    aria-label="Close word details"
                                    type="button"
                                >
                                    <X size={22} aria-hidden="true" />
                                </button>
                            )}
                        </div>
                    </div>

                    {/* Mobile bottom sheet keeps the four-tab layout (v3 §3.3). */}
                    {isSheet ? (
                        <>
                            {/* Tab bar */}
                            <div
                                className="flex border-b border-gray-100 dark:border-gray-800 shrink-0 overflow-x-auto scrollbar-hide"
                                role="tablist"
                            >
                                {DETAIL_TABS.map((tab) => (
                                    <button
                                        key={tab}
                                        role="tab"
                                        aria-selected={activeTab === tab}
                                        onClick={() => setActiveTab(tab)}
                                        className="px-4 py-3 text-sm font-medium whitespace-nowrap border-b-2 transition-colors"
                                        style={
                                            activeTab === tab
                                                ? { borderColor: '#E91E8C', color: '#E91E8C' }
                                                : { borderColor: 'transparent', color: '#9ca3af' }
                                        }
                                        type="button"
                                    >
                                        {tabLabel(tab)}
                                    </button>
                                ))}
                            </div>

                            {/* Tab body */}
                            <div className="overflow-y-auto flex-1 p-5 space-y-5">
                                {activeTab === 'overview' && (
                                    <>
                                        {translation && (
                                            <div
                                                className="p-4 rounded-xl"
                                                style={{ background: '#FCE4F3' }}
                                            >
                                                <h3
                                                    className="text-xs font-bold uppercase tracking-wider mb-1"
                                                    style={{ color: '#E91E8C' }}
                                                >
                                                    {language === 'fr'
                                                        ? 'Fran\u00e7ais'
                                                        : 'English'}
                                                </h3>
                                                <p className="text-xl font-medium text-gray-900">
                                                    {translation}
                                                </p>
                                            </div>
                                        )}
                                        {d.aiwaExtract && (
                                            <div>
                                                <h3 className="flex items-center gap-2 font-bold text-gray-800 dark:text-gray-200 mb-2">
                                                    <BookOpen size={16} aria-hidden="true" />{' '}
                                                    Definition
                                                </h3>
                                                <p className="text-gray-700 dark:text-gray-300 leading-relaxed">
                                                    {d.aiwaExtract}
                                                </p>
                                            </div>
                                        )}
                                        {exampleCount > 0 && (
                                            <div>
                                                <h3 className="font-bold text-gray-800 dark:text-gray-200 mb-2">
                                                    How people use it
                                                </h3>
                                                <div
                                                    className="pl-4 border-l-4"
                                                    style={{ borderColor: '#E91E8C' }}
                                                >
                                                    <p className="text-base text-gray-900 dark:text-gray-100">
                                                        {d.aiwaExampleSentences[0].sentenceExample}
                                                    </p>
                                                    <p className="text-sm text-gray-500 dark:text-gray-400 italic mt-1">
                                                        {language === 'fr'
                                                            ? d.aiwaExampleSentences[0]
                                                                  .sentenceFrenchTranslation
                                                            : d.aiwaExampleSentences[0]
                                                                  .sentenceEnglishTranslation}
                                                    </p>
                                                </div>
                                            </div>
                                        )}
                                    </>
                                )}

                                {activeTab === 'examples' && (
                                    <div>
                                        {exampleCount === 0 && (
                                            <p className="text-gray-400 text-sm italic">
                                                No examples recorded yet.
                                            </p>
                                        )}
                                        <div className="space-y-5">
                                            {d.aiwaExampleSentences &&
                                                d.aiwaExampleSentences.map((ex, idx) => (
                                                    <div
                                                        key={idx}
                                                        className="pl-4 border-l-4 border-gray-200 dark:border-gray-700"
                                                    >
                                                        <p className="text-base text-gray-900 dark:text-gray-100">
                                                            {ex.sentenceExample}
                                                        </p>
                                                        {ex.sentenceIpaPronounciation && (
                                                            <p className="text-xs text-gray-400 font-mono mt-0.5">
                                                                /{ex.sentenceIpaPronounciation}/
                                                            </p>
                                                        )}
                                                        {ex.sentencePhoneticPronunciation && (
                                                            <p className="text-xs text-gray-400 mt-0.5">
                                                                [{ex.sentencePhoneticPronunciation}]
                                                            </p>
                                                        )}
                                                        <p className="text-sm text-gray-500 dark:text-gray-400 italic mt-1">
                                                            {language === 'fr'
                                                                ? ex.sentenceFrenchTranslation
                                                                : ex.sentenceEnglishTranslation}
                                                        </p>
                                                    </div>
                                                ))}
                                        </div>
                                    </div>
                                )}

                                {activeTab === 'related' && (
                                    <div>
                                        <RelatedWordList
                                            title="Synonyms"
                                            items={d.aiwaSynonyms}
                                            onSelectWord={onSelectWord}
                                        />
                                        <RelatedWordList
                                            title="Antonyms"
                                            items={d.aiwaAntonyms}
                                            onSelectWord={onSelectWord}
                                        />
                                        <RelatedWordList
                                            title="Phonetic Variants"
                                            items={d.aiwaPhoneticVariants}
                                            onSelectWord={onSelectWord}
                                        />
                                        {!d.aiwaSynonyms?.nodes?.length &&
                                            !d.aiwaAntonyms?.nodes?.length &&
                                            !d.aiwaPhoneticVariants?.nodes?.length && (
                                                <p className="text-gray-400 text-sm italic">
                                                    No related words recorded.
                                                </p>
                                            )}
                                    </div>
                                )}

                                {activeTab === 'origin' && (
                                    <div>
                                        {d.aiwaOrigin ? (
                                            <p className="text-gray-700 dark:text-gray-300 leading-relaxed">
                                                {d.aiwaOrigin}
                                            </p>
                                        ) : (
                                            <p className="text-gray-400 text-sm italic">
                                                No origin notes available.
                                            </p>
                                        )}
                                    </div>
                                )}
                            </div>

                            {/* Pinned Add to Favorites CTA (v3 §3.4) */}
                            <div className="shrink-0 p-4 border-t border-gray-100 dark:border-gray-800">
                                {favoriteCta}
                            </div>
                        </>
                    ) : (
                        /* Desktop two-column layout (v3 §3.3) */
                        <div className="flex flex-1 overflow-hidden">
                            {/* Left: always-rendered scrollable sections */}
                            <div className="flex-1 overflow-y-auto p-5 space-y-6">
                                {translation && (
                                    <section id="detail-meaning">
                                        <div
                                            className="p-4 rounded-xl"
                                            style={{ background: '#FCE4F3' }}
                                        >
                                            <h3
                                                className="text-xs font-bold uppercase tracking-wider mb-1"
                                                style={{ color: '#E91E8C' }}
                                            >
                                                {language === 'fr' ? 'Français' : 'English'}
                                            </h3>
                                            <p className="text-xl font-medium text-gray-900">
                                                {translation}
                                            </p>
                                        </div>
                                    </section>
                                )}

                                {d.aiwaExtract && (
                                    <section id="detail-definition">
                                        <h3 className="flex items-center gap-2 font-bold text-gray-800 dark:text-gray-200 mb-2">
                                            <BookOpen size={16} aria-hidden="true" /> Definition
                                        </h3>
                                        <p className="text-gray-700 dark:text-gray-300 leading-relaxed">
                                            {d.aiwaExtract}
                                        </p>
                                    </section>
                                )}

                                {exampleCount > 0 && (
                                    <section>
                                        <h3 className="font-bold text-gray-800 dark:text-gray-200 mb-2">
                                            How people use it
                                        </h3>
                                        <div
                                            className="pl-4 border-l-4"
                                            style={{ borderColor: '#E91E8C' }}
                                        >
                                            <p className="text-base text-gray-900 dark:text-gray-100">
                                                {d.aiwaExampleSentences[0].sentenceExample}
                                            </p>
                                            <p className="text-sm text-gray-500 dark:text-gray-400 italic mt-1">
                                                {language === 'fr'
                                                    ? d.aiwaExampleSentences[0]
                                                          .sentenceFrenchTranslation
                                                    : d.aiwaExampleSentences[0]
                                                          .sentenceEnglishTranslation}
                                            </p>
                                        </div>
                                    </section>
                                )}

                                <section id="detail-pronunciation">
                                    <h3 className="font-bold text-gray-800 dark:text-gray-200 mb-2">
                                        Pronunciation
                                    </h3>
                                    <div className="flex flex-wrap items-center gap-2">
                                        {d.aiwaAudioFile?.node?.mediaItemUrl && (
                                            <AudioButton
                                                url={d.aiwaAudioFile.node.mediaItemUrl}
                                                size={18}
                                            />
                                        )}
                                        {d.aiwaIpaPronunciation && (
                                            <span className="bg-gray-100 dark:bg-gray-800 px-2 py-0.5 rounded text-xs font-mono text-gray-600 dark:text-gray-300">
                                                /{d.aiwaIpaPronunciation}/
                                            </span>
                                        )}
                                        {d.phoneticProunciation && (
                                            <span className="bg-gray-50 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 px-2 py-0.5 rounded text-xs text-gray-500">
                                                [{d.phoneticProunciation}]
                                            </span>
                                        )}
                                        {!d.aiwaAudioFile?.node?.mediaItemUrl &&
                                            !d.aiwaIpaPronunciation &&
                                            !d.phoneticProunciation && (
                                                <p className="text-gray-400 text-sm italic">
                                                    No pronunciation recorded.
                                                </p>
                                            )}
                                    </div>
                                </section>

                                <section id="detail-image">
                                    <h3 className="font-bold text-gray-800 dark:text-gray-200 mb-2">
                                        Image
                                    </h3>
                                    {d.aiwaWordPhoto?.node?.sourceUrl ? (
                                        <img
                                            src={d.aiwaWordPhoto.node.sourceUrl}
                                            alt={word.title}
                                            className="w-full rounded-xl object-cover"
                                            style={{ maxHeight: 220 }}
                                        />
                                    ) : (
                                        <p className="text-gray-400 text-sm italic">
                                            No image available.
                                        </p>
                                    )}
                                </section>

                                <section id="detail-examples">
                                    <h3 className="font-bold text-gray-800 dark:text-gray-200 mb-2">
                                        Example Sentences
                                    </h3>
                                    {exampleCount === 0 && (
                                        <p className="text-gray-400 text-sm italic">
                                            No examples recorded yet.
                                        </p>
                                    )}
                                    <div className="space-y-5">
                                        {d.aiwaExampleSentences &&
                                            d.aiwaExampleSentences.map((ex, idx) => (
                                                <div
                                                    key={idx}
                                                    className="pl-4 border-l-4 border-gray-200 dark:border-gray-700"
                                                >
                                                    <p className="text-base text-gray-900 dark:text-gray-100">
                                                        {ex.sentenceExample}
                                                    </p>
                                                    {ex.sentenceIpaPronounciation && (
                                                        <p className="text-xs text-gray-400 font-mono mt-0.5">
                                                            /{ex.sentenceIpaPronounciation}/
                                                        </p>
                                                    )}
                                                    {ex.sentencePhoneticPronunciation && (
                                                        <p className="text-xs text-gray-400 mt-0.5">
                                                            [{ex.sentencePhoneticPronunciation}]
                                                        </p>
                                                    )}
                                                    <p className="text-sm text-gray-500 dark:text-gray-400 italic mt-1">
                                                        {language === 'fr'
                                                            ? ex.sentenceFrenchTranslation
                                                            : ex.sentenceEnglishTranslation}
                                                    </p>
                                                </div>
                                            ))}
                                    </div>
                                </section>

                                <section id="detail-related">
                                    <h3 className="font-bold text-gray-800 dark:text-gray-200 mb-2">
                                        Related Words
                                    </h3>
                                    <RelatedWordList
                                        title="Synonyms"
                                        items={d.aiwaSynonyms}
                                        onSelectWord={onSelectWord}
                                    />
                                    <RelatedWordList
                                        title="Antonyms"
                                        items={d.aiwaAntonyms}
                                        onSelectWord={onSelectWord}
                                    />
                                    <RelatedWordList
                                        title="Phonetic Variants"
                                        items={d.aiwaPhoneticVariants}
                                        onSelectWord={onSelectWord}
                                    />
                                    {!d.aiwaSynonyms?.nodes?.length &&
                                        !d.aiwaAntonyms?.nodes?.length &&
                                        !d.aiwaPhoneticVariants?.nodes?.length && (
                                            <p className="text-gray-400 text-sm italic">
                                                No related words recorded.
                                            </p>
                                        )}
                                </section>

                                <section id="detail-origin">
                                    <h3 className="font-bold text-gray-800 dark:text-gray-200 mb-2">
                                        Origin &amp; Cultural Notes
                                    </h3>
                                    {d.aiwaOrigin ? (
                                        <p className="text-gray-700 dark:text-gray-300 leading-relaxed">
                                            {d.aiwaOrigin}
                                        </p>
                                    ) : (
                                        <p className="text-gray-400 text-sm italic">
                                            No origin notes available.
                                        </p>
                                    )}
                                </section>
                            </div>

                            {/* Right: feature cards + favorites CTA */}
                            <div className="w-56 shrink-0 border-l border-gray-100 dark:border-gray-800 p-3 overflow-y-auto flex flex-col gap-2">
                                {FEATURE_CARDS.map((card) => (
                                    <FeatureCard
                                        key={card.anchor}
                                        icon={card.icon}
                                        iconBg={card.iconBg}
                                        label={card.label}
                                        description={card.description}
                                        onClick={() => scrollToAnchor(card.anchor)}
                                    />
                                ))}
                                <div className="mt-2">{favoriteCta}</div>
                            </div>
                        </div>
                    )}
                </>
            )}
        </div>
    );
};

// ---------------------------------------------------------------------------
// Components — Detail bottom sheet (mobile / tablet)
// ---------------------------------------------------------------------------

const DetailBottomSheet = (props) => (
    <div
        className="fixed inset-0 z-[9999] flex justify-end md:justify-center items-end md:items-center pointer-events-none"
        role="dialog"
        aria-modal="true"
        aria-labelledby="detail-title"
    >
        <div
            className="absolute inset-0 bg-black/50 pointer-events-auto backdrop-blur-sm"
            onClick={props.onClose}
            aria-hidden="true"
        />
        <div className="relative z-50 pointer-events-auto w-full md:w-[600px]">
            <DetailView {...props} isSheet />
        </div>
    </div>
);

// ---------------------------------------------------------------------------
// Components — Favorites & History views
// ---------------------------------------------------------------------------

const FavoritesView = ({ words, favorites, language, onSelect, onFavoriteToggle, onPrefetch }) => {
    const favWords = useMemo(
        () => words.filter((w) => favorites.includes(w.slug)),
        [words, favorites]
    );
    if (!favWords.length) {
        return (
            <div className="flex flex-col items-center justify-center h-full text-center p-8">
                <Heart size={48} className="text-gray-200 mb-4" />
                <p className="text-gray-500 font-medium">No saved words yet.</p>
                <p className="text-gray-400 text-sm mt-1">
                    Tap the heart icon on any word to save it here.
                </p>
            </div>
        );
    }
    return (
        <div className="flex-1 overflow-y-auto">
            {favWords.map((word) => (
                <WordListRow
                    key={word.id}
                    word={word}
                    language={language}
                    onSelect={onSelect}
                    onPrefetch={onPrefetch}
                    isFavorite
                    onFavoriteToggle={onFavoriteToggle}
                />
            ))}
        </div>
    );
};

const HistoryView = ({
    words,
    history,
    language,
    onSelect,
    favorites,
    onFavoriteToggle,
    onPrefetch,
}) => {
    const histWords = useMemo(
        () => history.map((slug) => words.find((w) => w.slug === slug)).filter(Boolean),
        [words, history]
    );
    if (!histWords.length) {
        return (
            <div className="flex flex-col items-center justify-center h-full text-center p-8">
                <Clock size={48} className="text-gray-200 mb-4" />
                <p className="text-gray-500 font-medium">No recently viewed words.</p>
                <p className="text-gray-400 text-sm mt-1">Words you open will appear here.</p>
            </div>
        );
    }
    return (
        <div className="flex-1 overflow-y-auto">
            {histWords.map((word) => (
                <WordListRow
                    key={word.id}
                    word={word}
                    language={language}
                    onSelect={onSelect}
                    onPrefetch={onPrefetch}
                    isFavorite={favorites.includes(word.slug)}
                    onFavoriteToggle={onFavoriteToggle}
                />
            ))}
        </div>
    );
};

// ---------------------------------------------------------------------------
// Components — Explore view
// ---------------------------------------------------------------------------

const ExploreView = ({ languages, sourceLanguage, onSelectLanguage }) => (
    <div className="flex-1 overflow-y-auto p-4">
        <h2 className="text-lg font-bold text-gray-800 dark:text-gray-200 mb-4">
            Browse by Language
        </h2>
        {!languages.length && (
            <p className="text-gray-400 text-sm italic">Loading languages&hellip;</p>
        )}
        <div className="grid grid-cols-2 gap-3">
            {languages.map((lang) => (
                <button
                    key={lang.slug}
                    onClick={() => onSelectLanguage(lang.slug)}
                    className="p-4 rounded-xl text-left border-2 transition-all"
                    style={
                        sourceLanguage === lang.slug
                            ? {
                                  background: 'linear-gradient(135deg,#E91E8C,#7B3FA0)',
                                  color: 'white',
                                  borderColor: 'transparent',
                              }
                            : { borderColor: '#f3f4f6' }
                    }
                    type="button"
                >
                    <span
                        className="block font-bold text-base"
                        style={{ color: sourceLanguage === lang.slug ? 'white' : '#1f2937' }}
                    >
                        {lang.name}
                    </span>
                    <span
                        style={{
                            fontSize: '0.85rem',
                            color:
                                sourceLanguage === lang.slug ? 'rgba(255,255,255,0.7)' : '#9ca3af',
                        }}
                    >
                        {lang.count} words
                    </span>
                </button>
            ))}
        </div>
    </div>
);

// ---------------------------------------------------------------------------
// Components — Bottom navigation (mobile)
// ---------------------------------------------------------------------------

const BottomNav = ({ active, onChange }) => (
    <nav
        className="flex border-t border-gray-100 dark:border-gray-800 bg-white dark:bg-gray-900 shrink-0"
        aria-label="Main navigation"
    >
        {NAV_ITEMS.map(({ id, label, Icon }) => {
            const isActive = active === id;
            return (
                <button
                    key={id}
                    onClick={() => onChange(id)}
                    className="flex-1 flex flex-col items-center py-2 px-1 text-xs font-medium transition-colors"
                    style={{ color: isActive ? '#E91E8C' : '#9ca3af' }}
                    aria-label={label}
                    aria-current={isActive ? 'page' : undefined}
                    type="button"
                >
                    <Icon size={20} aria-hidden="true" />
                    <span className="mt-0.5">{label}</span>
                </button>
            );
        })}
    </nav>
);

// ---------------------------------------------------------------------------
// Components — Desktop sidebar
// ---------------------------------------------------------------------------

const DesktopSidebar = ({
    language,
    onLanguageToggle,
    isDark,
    onThemeToggle,
    languages,
    sourceLanguage,
    onSourceLanguage,
    activeNav,
    onNavChange,
}) => (
    <aside className="flex flex-col h-full bg-white dark:bg-gray-900 border-r border-gray-100 dark:border-gray-800 p-5 overflow-y-auto">
        <div className="mb-6">
            <h1 className="text-xl font-bold" style={{ color: '#E91E8C' }}>
                AIWA
            </h1>
            <p className="text-xs text-gray-400 mt-0.5">African Indigenous Words Archive</p>
        </div>

        <div className="flex items-center gap-2 mb-4">
            <Globe size={16} className="text-gray-400" aria-hidden="true" />
            <button
                onClick={onLanguageToggle}
                className="bg-gray-100 dark:bg-gray-800 hover:bg-gray-200 dark:hover:bg-gray-700 px-3 py-1.5 rounded-full text-sm font-medium transition-colors text-gray-700 dark:text-gray-200"
                type="button"
            >
                {language === 'fr' ? 'FR' : 'EN'}
            </button>
            <button
                onClick={onThemeToggle}
                className="ml-auto p-1.5 rounded-full bg-gray-100 dark:bg-gray-800 hover:bg-gray-200 dark:hover:bg-gray-700 text-gray-600 dark:text-gray-300 transition-colors"
                aria-label={isDark ? 'Switch to light mode' : 'Switch to dark mode'}
                type="button"
            >
                {isDark ? (
                    <Sun size={16} aria-hidden="true" />
                ) : (
                    <Moon size={16} aria-hidden="true" />
                )}
            </button>
        </div>

        <hr className="border-gray-100 dark:border-gray-800 mb-4" />

        {/* Primary navigation (v3 §3.1) */}
        <nav className="flex flex-col gap-1 mb-4" aria-label="Dictionary navigation">
            {DESKTOP_NAV_ITEMS.map(({ id, label, Icon }) => {
                // Categories keeps its own active state (v3 §3.1); the center column renders
                // ExploreView for both 'explore' and 'categories'.
                const isActive = activeNav === id;
                return (
                    <button
                        key={id}
                        type="button"
                        onClick={() => onNavChange(id)}
                        className={`flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium transition-colors ${
                            isActive ? '' : 'text-gray-700 dark:text-gray-300'
                        }`}
                        style={isActive ? { background: '#FCE4F3', color: '#E91E8C' } : undefined}
                        aria-current={isActive ? 'page' : undefined}
                    >
                        <Icon size={18} aria-hidden="true" />
                        {label}
                    </button>
                );
            })}
        </nav>

        <hr className="border-gray-100 dark:border-gray-800 mb-4" />

        <h3 className="text-xs font-bold uppercase tracking-wider text-gray-400 mb-1">
            Source Language
        </h3>
        <LanguageSelectorList
            languages={languages}
            selected={sourceLanguage}
            onSelect={onSourceLanguage}
        />

        {/* Sidebar footer (v3 §3.6) — [OPEN — OQ-V1] logo asset + tagline copy pending AIWA approval */}
        <div className="mt-auto pt-4 border-t border-gray-100 dark:border-gray-800">
            <div
                className="w-16 h-16 rounded-lg bg-gray-100 dark:bg-gray-800 mb-3"
                aria-hidden="true"
            />
            <p className="text-xs text-gray-400 dark:text-gray-500 leading-relaxed">
                Preserving our language.
                <br />
                Connecting our heritage.
                <br />
                Building our future.
            </p>
            <p className="mt-2 text-lg font-bold" style={{ color: '#E91E8C' }}>
                AIWA
            </p>
        </div>
    </aside>
);

// ---------------------------------------------------------------------------
// Components — Desktop right panel empty state
// ---------------------------------------------------------------------------

const DesktopEmptyPanel = ({ wordOfDay, language, onSelect }) => (
    <div className="flex flex-col h-full overflow-y-auto">
        {wordOfDay && (
            <div className="p-4">
                <WordOfDayCard word={wordOfDay} language={language} onSelect={onSelect} />
            </div>
        )}
        <div className="flex-1 flex flex-col items-center justify-center text-center p-8">
            <div
                className="w-16 h-16 rounded-2xl flex items-center justify-center mb-4 text-3xl"
                style={{ background: 'linear-gradient(135deg,#E91E8C,#7B3FA0)' }}
                aria-hidden="true"
            >
                &#128218;
            </div>
            <h2 className="font-bold text-gray-700 dark:text-gray-300 text-lg">Select a word</h2>
            <p className="text-sm text-gray-400 mt-2 leading-relaxed max-w-xs">
                Explore its meaning, pronunciation, and cultural context from our growing archive of
                African indigenous words.
            </p>
        </div>
    </div>
);

// ---------------------------------------------------------------------------
// Main DictionaryApp
// ---------------------------------------------------------------------------

export default function DictionaryApp() {
    const [language, setLanguage] = useLocalStorage('aiwa-dict-lang', 'en');
    const [sourceLanguage, setSourceLanguage] = useLocalStorage('aiwa-dict-source-lang', null);
    const [isDark, setIsDark] = useLocalStorage('aiwa-dict-theme', false);
    const [favorites, setFavorites] = useLocalStorage('aiwa-dict-favorites', []);
    const [history, setHistory] = useLocalStorage('aiwa-dict-history', []);

    const [searchTerm, setSearchTerm] = useState('');
    const [activeFilter, setActiveFilter] = useState('all');
    const [selectedSlug, setSelectedSlug] = useState(null);
    const [selectedTitle, setSelectedTitle] = useState('');
    const [activeNav, setActiveNav] = useState('home');
    const [scrollState, setScrollState] = useState({ atTop: true, atBottom: false });
    const [languages, setLanguages] = useState([]);
    const [topTab, setTopTab] = useState('browse');
    const [wordOfDaySlug, setWordOfDaySlug] = useState(null);

    const virtuosoRef = useRef(null);
    const isDesktop = useIsDesktop();

    // Fetch source languages from REST API
    useEffect(() => {
        apiFetch(`${REST_URL}/languages`)
            .then((r) => (r.ok ? r.json() : null))
            .then((json) => {
                if (json && json.success && Array.isArray(json.data?.languages)) {
                    setLanguages(json.data.languages);
                }
            })
            .catch(() => {});
    }, []);

    // Word of the Day — server endpoint with a 24h localStorage cache keyed by date (v3 §3.7).
    // The server returns the same word for all users on a given calendar day.
    useEffect(() => {
        const today = new Date().toISOString().slice(0, 10);
        try {
            const cached = JSON.parse(localStorage.getItem('aiwa-dict-word-of-day') || 'null');
            if (cached && cached.date === today && cached.slug) {
                setWordOfDaySlug(cached.slug);
                return;
            }
        } catch {
            /* ignore malformed cache */
        }
        apiFetch(`${REST_URL}/word-of-day`)
            .then((r) => (r.ok ? r.json() : null))
            .then((json) => {
                const slug = json?.success ? json.data?.word?.slug : null;
                const date = json?.data?.date || today;
                if (slug) {
                    setWordOfDaySlug(slug);
                    try {
                        localStorage.setItem(
                            'aiwa-dict-word-of-day',
                            JSON.stringify({ slug, date })
                        );
                    } catch {
                        /* quota — degrade silently */
                    }
                }
            })
            .catch(() => {});
    }, []);

    const { loading, error, data } = useQuery(GET_ALL_WORDS_INDEX, { client });

    const allWords = useMemo(() => (data?.dictionaries?.edges || []).map((e) => e.node), [data]);

    const wordOfDay = useMemo(() => {
        if (!allWords.length) return null;
        // Prefer the server-selected word; fall back to a deterministic client-side pick
        // if the endpoint is unavailable or its slug is not in the loaded index.
        if (wordOfDaySlug) {
            const match = allWords.find((w) => w.slug === wordOfDaySlug);
            if (match) return match;
        }
        return allWords[wordOfDayIndex(allWords.length)];
    }, [allWords, wordOfDaySlug]);

    const filteredWords = useMemo(() => {
        let entries = allWords;

        if (sourceLanguage) {
            entries = entries.filter((w) =>
                (w.languages?.nodes || []).some((l) => l.slug === sourceLanguage)
            );
        }

        if (searchTerm.trim().length > 0) {
            const q = searchTerm.toLowerCase();
            entries = entries.filter((w) => {
                const d = w.dictionaryEntryDetails;
                return (
                    (w.title || '').toLowerCase().includes(q) ||
                    (d.aiwaSearchStringEnglish || '').toLowerCase().includes(q) ||
                    (d.aiwaSearchStringFrench || '').toLowerCase().includes(q)
                );
            });
        }

        if (activeFilter === 'noun') {
            entries = entries.filter(
                (w) => posKey(w.dictionaryEntryDetails.aiwaPartOfSpeech) === 'noun'
            );
        } else if (activeFilter === 'verb') {
            entries = entries.filter(
                (w) => posKey(w.dictionaryEntryDetails.aiwaPartOfSpeech) === 'verb'
            );
        } else if (activeFilter === 'phrase') {
            entries = entries.filter(
                (w) => posKey(w.dictionaryEntryDetails.aiwaPartOfSpeech) === 'phrase'
            );
        } else if (activeFilter === 'audio') {
            entries = entries.filter(
                (w) => !!w.dictionaryEntryDetails.aiwaAudioFile?.node?.mediaItemUrl
            );
        } else if (activeFilter === 'image') {
            entries = entries.filter((w) => !!w.dictionaryEntryDetails.aiwaWordPhoto?.node?.id);
        }

        return entries;
    }, [allWords, sourceLanguage, searchTerm, activeFilter]);

    const prefetchWord = useCallback((slug) => {
        client.query({
            query: GET_SINGLE_WORD_DETAILS,
            variables: { slug },
            fetchPolicy: 'cache-first',
        });
    }, []);

    const handleSelectWord = useCallback(
        (slug, title) => {
            setSelectedSlug(slug);
            setSelectedTitle(title);
            setHistory((prev) => [slug, ...prev.filter((s) => s !== slug)].slice(0, 50));
        },
        [setHistory]
    );

    const handleFavoriteToggle = useCallback(
        (slug) => {
            setFavorites((prev) =>
                prev.includes(slug) ? prev.filter((s) => s !== slug) : [...prev, slug]
            );
        },
        [setFavorites]
    );

    const handleScrollToLetter = useCallback(
        (char) => {
            if (!virtuosoRef.current) return;
            const idx = filteredWords.findIndex((w) =>
                (w.title || '').trim().toUpperCase().startsWith(char)
            );
            if (idx !== -1) {
                virtuosoRef.current.scrollToIndex({ index: idx, align: 'start', behavior: 'auto' });
            }
        },
        [filteredWords]
    );

    // Shared word-list area (used by both desktop center and mobile home tab)
    const wordListArea = (
        <div className="flex-1 flex flex-col overflow-hidden">
            <div className="px-4 py-2 bg-white dark:bg-gray-900 border-b border-gray-100 dark:border-gray-800 shrink-0">
                <div className="relative">
                    <label htmlFor="aiwa-dict-search" className="sr-only">
                        Search dictionary words
                    </label>
                    <Search
                        className="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400"
                        size={18}
                        aria-hidden="true"
                    />
                    <input
                        id="aiwa-dict-search"
                        type="search"
                        placeholder={`Search ${filteredWords.length.toLocaleString()} words\u2026`}
                        value={searchTerm}
                        onChange={(e) => setSearchTerm(e.target.value)}
                        className="w-full bg-gray-100 dark:bg-gray-800 text-gray-900 dark:text-gray-100 pl-10 pr-4 py-2.5 rounded-xl focus:outline-none transition-all text-sm"
                        style={{ WebkitAppearance: 'none' }}
                    />
                </div>
            </div>

            <div className="bg-white dark:bg-gray-900 border-b border-gray-100 dark:border-gray-800 shrink-0">
                <FilterPills active={activeFilter} onChange={setActiveFilter} />
            </div>

            {loading && (
                <div className="flex-1 flex flex-col items-center justify-center gap-4">
                    <Loader2 className="animate-spin" style={{ color: '#E91E8C' }} size={40} />
                    <p className="text-gray-400 text-sm">Loading dictionary&hellip;</p>
                </div>
            )}

            {error && (
                <div className="flex-1 flex items-center justify-center p-8 text-center">
                    <p className="text-red-500 text-sm">Could not load dictionary data.</p>
                </div>
            )}

            {!loading && !error && (
                <div className="relative flex-1 overflow-hidden">
                    <div className="sr-only" role="status" aria-live="polite" aria-atomic="true">
                        {filteredWords.length} words found
                    </div>
                    <div
                        className="absolute top-0 left-0 right-0 h-8 pointer-events-none z-10 transition-opacity"
                        style={{
                            background: 'linear-gradient(to bottom, #F8F8F8, transparent)',
                            opacity: scrollState.atTop ? 0 : 1,
                        }}
                        aria-hidden="true"
                    />
                    <Virtuoso
                        ref={virtuosoRef}
                        data={filteredWords}
                        className="scrollbar-hide"
                        style={{ height: '100%' }}
                        atTopStateChange={(atTop) => setScrollState((s) => ({ ...s, atTop }))}
                        atBottomStateChange={(atBottom) =>
                            setScrollState((s) => ({ ...s, atBottom }))
                        }
                        itemContent={(_, word) => (
                            <WordListRow
                                word={word}
                                language={language}
                                onSelect={handleSelectWord}
                                onPrefetch={prefetchWord}
                                isFavorite={favorites.includes(word.slug)}
                                onFavoriteToggle={handleFavoriteToggle}
                            />
                        )}
                    />
                    <div
                        className="absolute bottom-0 left-0 right-0 h-10 pointer-events-none z-10 transition-opacity"
                        style={{
                            background: 'linear-gradient(to top, #F8F8F8, transparent)',
                            opacity: scrollState.atBottom ? 0 : 1,
                        }}
                        aria-hidden="true"
                    />
                </div>
            )}
        </div>
    );

    // -------------------------------------------------------------------------
    // Desktop layout
    // -------------------------------------------------------------------------
    if (isDesktop) {
        return (
            <div
                className={`${isDark ? 'dark' : ''}`}
                style={{
                    display: 'flex',
                    flexDirection: 'column',
                    height: '100vh',
                    overflow: 'hidden',
                    fontFamily: '"Noto Sans", system-ui, sans-serif',
                    background: isDark ? '#1A1A1A' : '#F8F8F8',
                }}
            >
                {/* Top-level Browse / Play tab bar */}
                <div
                    role="tablist"
                    aria-orientation="horizontal"
                    aria-label="Dictionary modes"
                    style={{
                        display: 'flex',
                        alignItems: 'center',
                        height: 48,
                        flexShrink: 0,
                        borderBottom: '1px solid #f3f4f6',
                        background: isDark ? '#111827' : 'white',
                    }}
                >
                    {['browse', 'play'].map((tab) => {
                        const isActive = topTab === tab;
                        const Icon = tab === 'play' ? Gamepad2 : BookOpen;
                        return (
                            <button
                                key={tab}
                                type="button"
                                role="tab"
                                id={`desktop-tab-${tab}`}
                                aria-controls={`desktop-panel-${tab}`}
                                aria-selected={isActive}
                                onClick={() => setTopTab(tab)}
                                style={{
                                    display: 'flex',
                                    alignItems: 'center',
                                    gap: 6,
                                    padding: '0 20px',
                                    height: '100%',
                                    fontWeight: isActive ? 700 : 500,
                                    fontSize: '0.9rem',
                                    color: isActive ? '#E91E8C' : '#9ca3af',
                                    borderBottom: isActive
                                        ? '2px solid #E91E8C'
                                        : '2px solid transparent',
                                    background: 'none',
                                    cursor: 'pointer',
                                    transition: 'color 0.15s',
                                }}
                                tabIndex={isActive ? 0 : -1}
                            >
                                <Icon size={16} aria-hidden="true" />
                                {tab === 'play' ? 'Play' : 'Browse'}
                            </button>
                        );
                    })}
                </div>

                {/* Content area */}
                {topTab === 'browse' ? (
                    <div
                        role="tabpanel"
                        id="desktop-panel-browse"
                        aria-labelledby="desktop-tab-browse"
                        style={{ flex: 1, display: 'flex', overflow: 'hidden' }}
                    >
                        <div style={{ width: 240, flexShrink: 0 }}>
                            <DesktopSidebar
                                language={language}
                                onLanguageToggle={() =>
                                    setLanguage((l) => (l === 'en' ? 'fr' : 'en'))
                                }
                                isDark={isDark}
                                onThemeToggle={() => setIsDark((d) => !d)}
                                languages={languages}
                                sourceLanguage={sourceLanguage}
                                onSourceLanguage={setSourceLanguage}
                                activeNav={activeNav}
                                onNavChange={setActiveNav}
                            />
                        </div>

                        <div
                            style={{
                                flex: 1,
                                display: 'flex',
                                flexDirection: 'column',
                                overflow: 'hidden',
                                borderRight: '1px solid #f3f4f6',
                            }}
                        >
                            {activeNav === 'home' && (
                                <>
                                    {wordListArea}
                                    <AlphaBar onSelect={handleScrollToLetter} />
                                </>
                            )}
                            {(activeNav === 'explore' || activeNav === 'categories') && (
                                <ExploreView
                                    languages={languages}
                                    sourceLanguage={sourceLanguage}
                                    onSelectLanguage={(slug) => {
                                        setSourceLanguage(slug);
                                        setActiveNav('home');
                                    }}
                                />
                            )}
                            {activeNav === 'favorites' && (
                                <FavoritesView
                                    words={allWords}
                                    favorites={favorites}
                                    language={language}
                                    onSelect={handleSelectWord}
                                    onFavoriteToggle={handleFavoriteToggle}
                                    onPrefetch={prefetchWord}
                                />
                            )}
                            {activeNav === 'history' && (
                                <HistoryView
                                    words={allWords}
                                    history={history}
                                    language={language}
                                    onSelect={handleSelectWord}
                                    favorites={favorites}
                                    onFavoriteToggle={handleFavoriteToggle}
                                    onPrefetch={prefetchWord}
                                />
                            )}
                            {activeNav === 'play' && (
                                <>
                                    {wordListArea}
                                    <AlphaBar onSelect={handleScrollToLetter} />
                                </>
                            )}
                        </div>

                        <div style={{ width: 420, flexShrink: 0, overflow: 'hidden' }}>
                            {selectedSlug ? (
                                <DetailView
                                    key={selectedSlug}
                                    slug={selectedSlug}
                                    initialTitle={selectedTitle}
                                    language={language}
                                    onClose={null}
                                    onSelectWord={handleSelectWord}
                                    favorites={favorites}
                                    onFavoriteToggle={handleFavoriteToggle}
                                />
                            ) : (
                                <DesktopEmptyPanel
                                    wordOfDay={wordOfDay}
                                    language={language}
                                    onSelect={handleSelectWord}
                                />
                            )}
                        </div>
                    </div>
                ) : (
                    <div
                        role="tabpanel"
                        id="desktop-panel-play"
                        aria-labelledby="desktop-tab-play"
                        style={{ flex: 1, display: 'flex', overflow: 'hidden' }}
                    >
                        <GameShell
                            restUrl={REST_URL}
                            language={language}
                            sourceLanguage={sourceLanguage}
                            languages={languages}
                            onSourceLanguage={setSourceLanguage}
                            onBrowse={() => setTopTab('browse')}
                        />
                    </div>
                )}
            </div>
        );
    }

    // -------------------------------------------------------------------------
    // Mobile / tablet layout
    // -------------------------------------------------------------------------
    const showWordOfDay = activeNav === 'home' && !searchTerm;

    return (
        <div
            className={`${isDark ? 'dark' : ''}`}
            style={{
                display: 'flex',
                flexDirection: 'column',
                height: '100vh',
                overflow: 'hidden',
                fontFamily: '"Noto Sans", system-ui, sans-serif',
                background: isDark ? '#1A1A1A' : '#F8F8F8',
            }}
        >
            {/* Top bar */}
            <header
                className="shrink-0 z-20"
                style={{
                    background: isDark ? '#111827' : 'white',
                    borderBottom: '1px solid #f3f4f6',
                }}
            >
                <div
                    style={{
                        display: 'flex',
                        alignItems: 'center',
                        justifyContent: 'space-between',
                        padding: '12px 16px',
                    }}
                >
                    <h1
                        style={{
                            fontSize: '1.15rem',
                            fontWeight: 700,
                            color: '#E91E8C',
                            margin: 0,
                        }}
                    >
                        AIWA
                    </h1>
                    <div style={{ display: 'flex', alignItems: 'center', gap: 8 }}>
                        <button
                            onClick={() => setLanguage((l) => (l === 'en' ? 'fr' : 'en'))}
                            style={{
                                display: 'flex',
                                alignItems: 'center',
                                gap: 4,
                                background: '#f3f4f6',
                                borderRadius: '999px',
                                padding: '4px 10px',
                                fontSize: '0.75rem',
                                fontWeight: 600,
                                color: '#374151',
                                border: 'none',
                                cursor: 'pointer',
                            }}
                            type="button"
                        >
                            <Globe size={12} aria-hidden="true" />
                            {language === 'fr' ? 'FR' : 'EN'}
                        </button>
                        <button
                            onClick={() => setIsDark((d) => !d)}
                            style={{
                                background: '#f3f4f6',
                                border: 'none',
                                borderRadius: '999px',
                                padding: 6,
                                cursor: 'pointer',
                                color: '#6b7280',
                            }}
                            aria-label={isDark ? 'Light mode' : 'Dark mode'}
                            type="button"
                        >
                            {isDark ? (
                                <Sun size={14} aria-hidden="true" />
                            ) : (
                                <Moon size={14} aria-hidden="true" />
                            )}
                        </button>
                    </div>
                </div>
                {languages.length > 0 && (
                    <LanguageSelectorPills
                        languages={languages}
                        selected={sourceLanguage}
                        onSelect={setSourceLanguage}
                    />
                )}
            </header>

            {/* Main content area */}
            <main style={{ flex: 1, display: 'flex', flexDirection: 'column', overflow: 'hidden' }}>
                {activeNav === 'home' && (
                    <>
                        {showWordOfDay && wordOfDay && (
                            <WordOfDayCard
                                word={wordOfDay}
                                language={language}
                                onSelect={handleSelectWord}
                            />
                        )}
                        {wordListArea}
                    </>
                )}
                {activeNav === 'explore' && (
                    <ExploreView
                        languages={languages}
                        sourceLanguage={sourceLanguage}
                        onSelectLanguage={(slug) => {
                            setSourceLanguage(slug);
                            setActiveNav('home');
                        }}
                    />
                )}
                {activeNav === 'favorites' && (
                    <FavoritesView
                        words={allWords}
                        favorites={favorites}
                        language={language}
                        onSelect={handleSelectWord}
                        onFavoriteToggle={handleFavoriteToggle}
                        onPrefetch={prefetchWord}
                    />
                )}
                {activeNav === 'history' && (
                    <HistoryView
                        words={allWords}
                        history={history}
                        language={language}
                        onSelect={handleSelectWord}
                        favorites={favorites}
                        onFavoriteToggle={handleFavoriteToggle}
                        onPrefetch={prefetchWord}
                    />
                )}
                {activeNav === 'play' && (
                    <GameShell
                        restUrl={REST_URL}
                        language={language}
                        sourceLanguage={sourceLanguage}
                        languages={languages}
                        onSourceLanguage={setSourceLanguage}
                        onBrowse={() => setActiveNav('home')}
                    />
                )}
            </main>

            {/* Alpha bar (home tab only) */}
            {activeNav === 'home' && <AlphaBar onSelect={handleScrollToLetter} />}

            {/* Bottom navigation */}
            <BottomNav active={activeNav} onChange={setActiveNav} />

            {/* Detail bottom sheet */}
            {selectedSlug && (
                <DetailBottomSheet
                    key={selectedSlug}
                    slug={selectedSlug}
                    initialTitle={selectedTitle}
                    language={language}
                    onClose={() => setSelectedSlug(null)}
                    onSelectWord={handleSelectWord}
                    favorites={favorites}
                    onFavoriteToggle={handleFavoriteToggle}
                />
            )}
        </div>
    );
}

// ---------------------------------------------------------------------------
// Mount
// ---------------------------------------------------------------------------

document.addEventListener('DOMContentLoaded', () => {
    const rootId = settings.root_id || 'sparxstar-dictionary-root';
    const container = document.getElementById(rootId);
    if (!container) return;

    createRoot(container).render(
        <ApolloProvider client={client}>
            <DictionaryApp />
        </ApolloProvider>
    );
});
