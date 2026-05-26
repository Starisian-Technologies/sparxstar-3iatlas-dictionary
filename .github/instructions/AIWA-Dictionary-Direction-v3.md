# AIWA Dictionary — Direction v3

**Version:** 3.1  
**Status:** Active  
**Repo:** sparxstar-3iatlas-dictionary  
**Authors:** Max Barrett / Starisian Technologies  
**Date:** May 2026 (Sprint continuation update)  
**Supersedes:** DICTIONARY-DIRECTION-v2.md (partially — see Section 1)

---

## 0. Sprint Boot Rules (Apply Before New Feature Work)

### 0.1 Repairs Needed First (Boot Blockers)

Fix these two defects before beginning any net-new feature scope:

1. **Autoloader constants mismatch** (`src/includes/Autoloader.php`)  
   Fallback autoloader must use plugin boot constants `SPARX_3IATLAS_NAMESPACE` + `SPARX_3IATLAS_PATH`.
2. **Frontend form CSS mismatch** (`src/frontend/Sparxstar3IAtlasDictionaryForm.php`)  
   Form style enqueue must target an actually built CSS asset.

### 0.2 Bugs vs Intentional Gaps

- **Fix now (bugs):** broken autoloader constants, broken form CSS path, and any regression directly caused by those repairs.
- **Do not touch without approved spec (intentional gaps):**
  - `useProgressSync.syncNow()` no-op behavior
  - Helios auth stubs / temporary non-Helios guard paths

### 0.3 Game Event Payload Field Rule

`/progress/sync` events currently use `word_uuid`, `game`, and `domain` as payload keys. If adding new game-specific payload fields, prefix them with `game_`. Use `aiwa_*` only where the identifier is a WordPress hook/event name (e.g. `aiwa_game_word_correct`).

### 0.4 Full Absolute Rules + Platform Context

- Never change `aiwa-cpt-dictionary` CPT slug.
- Never reintroduce voting/corrections/community AJAX endpoints.
- Never add custom DB tables; use CPT + post meta.
- Never hardcode language names in React; always use `/languages`.
- Never store dictionary files on client devices.
- This plugin is a **standalone SPARXSTAR dictionary/API service** in the 3iAtlas suite.
- Do **not** add DVE, Sky, Mḗh₁n̥s, Dheghom, or Brain coupling/dependencies here.

---

## 1. What Changed from v2

This document supersedes v2 in the following areas only:

| Area | v2 | v3 |
|---|---|---|
| Community corrections and voting | Specified | **Removed entirely** — per `3IATLAS-SUITE-ARCHITECTURE-v1.0.md` |
| Games / Play mode | Not specified | **Added** — deferred to Phase 4, spec in `3IATLAS-SUITE-ARCHITECTURE-v1.0.md` |
| UI layout — word list row | Basic row | **Updated** — counts on audio/image icons |
| UI layout — detail panel | Four flat tabs | **Updated** — two-column layout per mockup |
| UI layout — sidebar | Minimal | **Updated** — Categories nav item, tagline, logo footer |
| UI layout — mobile detail | Bottom sheet | **Updated** — share icon, Add to Favorites CTA |
| `isLoggedIn` / `userId` in `wp_localize_script` | Passed | **Remove** — no longer needed without community features |

Everything in v2 not listed above remains correct and in force.

**Authoritative mockup references (committed to repo):**
- Mobile: `.github/instructions/Dictionary.png`
- Web/Desktop: `.github/instructions/Dictionary-web.png`

---

## 2. What Is Removed — Final and Permanent

The following must never be built. Any future session that proposes these is in violation of spec:

- `aiwa-cpt-correction` CPT — do not create
- Community correction submission UI — do not build
- Community voting UI (thumbs up/down) — do not build
- `user_vote`, `vote_counts`, `corrections` fields in any API response — do not add
- AJAX voting endpoints — do not add
- `isLoggedIn` and `userId` from `wp_localize_script` — remove in Phase 3 cleanup

---

## 3. Phase 2 UI Fix — What Copilot Must Change

Phase 2 was built from the written spec without the visual mockup reference. The structure is architecturally correct but diverges from the mockup in seven areas. This section specifies each fix precisely.

**Do not rebuild `app.jsx` from scratch.** Apply targeted changes to the existing Phase 2 build.

---

### 3.1 Sidebar — Categories Nav Item

**File:** `src/js/app.jsx` — `DesktopSidebar` component  
**Mockup reference:** `.github/instructions/Dictionary-web.png` — left sidebar

Add `Categories` as the fifth nav item in the desktop sidebar, below History.

```
NAV_ITEMS (desktop sidebar only):
- Home       (House icon)
- Explore    (Compass icon)
- Favorites  (Heart icon)
- History    (Clock icon)
- Categories (Grid/LayoutGrid icon — lucide-react)
```

The `Categories` view renders the same content as the Explore tab — language and domain cards. It is a nav alias, not a new data layer. When selected, it sets `activeNav` to `'explore'` and renders `ExploreView`.

**Mobile bottom nav is unchanged** — it has four items (Home, Explore, Favorites, History). Categories is desktop sidebar only.

---

### 3.2 Word List Row — Audio and Image Counts

**File:** `src/js/app.jsx` — `WordListRow` component  
**Mockup reference:** Both mockups — rows show numeric counts next to icons

The current row shows audio and image icons only (Volume2, ImageIcon) when present. The mockup shows a count number next to each icon — e.g. `🔊 3`, `🖼 2`.

The count is the **number of example sentences** for that word, shown next to the image icon. The audio icon count is always `1` if audio is present (one audio file per entry) — render as icon only, no count.

**Correction:** The number shown in the mockup next to the image icon is the example sentence count, not an image count. The GraphQL list query already fetches `aiwaExampleSentences` — use `.length` for this count.

```jsx
// In WordListRow, replace the hasImage icon with:
{hasImage && (
  <span className="flex items-center gap-0.5 text-purple-400">
    <ImageIcon size={14} aria-label="Has image" />
  </span>
)}
{exampleCount > 0 && (
  <span className="text-xs font-semibold text-gray-400">{exampleCount}</span>
)}
```

**GraphQL list query update required:** Add `aiwaExampleSentences { sentenceExample }` to `GET_ALL_WORDS_INDEX` so the count is available in the list without a detail fetch. The full sentence text is not needed — only the array length. Fetch the array and use `.length` client-side.

---

### 3.3 Detail Panel — Two-Column Desktop Layout

**File:** `src/js/app.jsx` — `DetailView` component  
**Mockup reference:** `.github/instructions/Dictionary-web.png` — right panel

The current `DetailView` uses a single-column tabbed layout (Overview / Examples / Related / Origin). The mockup shows a **two-column layout** within the detail panel on desktop:

- **Left column (scrollable content):** Meaning, Definition, How people use it, Pronunciation, Image — rendered as stacked sections, no tabs
- **Right column (fixed feature cards):** Six cards that are scroll anchors into the left column

**Layout spec:**

```
DetailView (desktop, no isSheet):
  ┌─────────────────────────────────────────────────┐
  │ Header row (word, IPA, POS pill, audio, heart)  │
  ├──────────────────────────┬──────────────────────┤
  │  Left: scrollable        │  Right: fixed cards  │
  │  - Meaning section       │  - Audio Pronunciation│
  │  - Definition section    │  - Cultural Images    │
  │  - How people use it     │  - Example Sentences  │
  │  - Pronunciation section │  - Related Words      │
  │  - Image section         │  - Origin & Cultural  │
  │  - Examples full list    │    Notes              │
  │  - Related words         │  - (Add to Favorites  │
  │  - Origin notes          │    button — pink CTA) │
  └──────────────────────────┴──────────────────────┘
```

**Right column cards — scroll anchor behaviour:**  
Each card is a `<button>` that calls `scrollIntoView({ behavior: 'smooth' })` on the corresponding section ref in the left column. Cards are not tabs — they do not hide/show content. All left-column sections are always rendered. The cards simply scroll the left column to the relevant section.

**Section IDs for scroll anchors:**

| Card label | Scrolls to section |
|---|---|
| Audio Pronunciation | `#detail-pronunciation` |
| Cultural Images | `#detail-image` |
| Example Sentences | `#detail-examples` |
| Related Words | `#detail-related` |
| Origin & Cultural Notes | `#detail-origin` |

**Right column card component:**

```jsx
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
      <p className="text-xs text-gray-400 dark:text-gray-500 mt-0.5 leading-snug">{description}</p>
    </div>
  </button>
);
```

**Feature card definitions:**

```js
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
    icon: Users,   // lucide-react Users icon
    anchor: 'detail-related',
  },
  {
    label: 'Origin & Cultural Notes',
    description: 'Discover the roots and cultural background of words.',
    iconBg: '#F57F17',
    icon: Leaf,    // lucide-react Leaf icon
    anchor: 'detail-origin',
  },
];
```

Add `Users` and `Leaf` to the lucide-react import.

**Desktop layout implementation:**

```jsx
// DetailView — desktop (isSheet === false)
<div className="flex flex-col h-full bg-white dark:bg-gray-900 overflow-hidden">
  {/* Header */}
  <div className="p-5 border-b border-gray-100 dark:border-gray-800 shrink-0">
    {/* ... existing header row unchanged ... */}
  </div>
  {/* Two-column body */}
  <div className="flex flex-1 overflow-hidden">
    {/* Left: scrollable sections */}
    <div className="flex-1 overflow-y-auto p-5 space-y-6">
      {/* Meaning */}
      <section id="detail-meaning">...</section>
      {/* Definition */}
      <section id="detail-definition">...</section>
      {/* How people use it (first example sentence) */}
      <section id="detail-pronunciation">...</section>
      {/* Pronunciation */}
      <section id="detail-image">...</section>
      {/* Image */}
      <section id="detail-examples">...</section>
      {/* All example sentences */}
      <section id="detail-related">...</section>
      {/* Related words */}
      <section id="detail-origin">...</section>
      {/* Origin */}
    </div>
    {/* Right: feature cards */}
    <div className="w-56 shrink-0 border-l border-gray-100 dark:border-gray-800 p-3 overflow-y-auto flex flex-col gap-2">
      {FEATURE_CARDS.map((card) => (
        <FeatureCard
          key={card.anchor}
          {...card}
          onClick={() => {
            document.getElementById(card.anchor)
              ?.scrollIntoView({ behavior: 'smooth', block: 'start' });
          }}
        />
      ))}
      {/* Add to Favorites CTA */}
      <button
        type="button"
        onClick={() => onFavoriteToggle(slug)}
        className="mt-2 flex items-center justify-center gap-2 w-full py-3 rounded-xl font-semibold text-sm text-white transition-colors"
        style={{ background: '#E91E8C' }}
      >
        <Heart size={16} fill={isFav ? 'currentColor' : 'none'} />
        {isFav ? 'Saved' : 'Add to Favorites'}
      </button>
    </div>
  </div>
</div>
```

**Mobile bottom sheet (`isSheet === true`) is unchanged** — it keeps the existing four-tab layout. The two-column layout is desktop only.

---

### 3.4 Add to Favorites — Full CTA Button

**File:** `src/js/app.jsx` — `DetailView` component  
**Mockup reference:** Both mockups — pink full-width button at bottom of detail

On **mobile** (`isSheet === true`), add a full-width pink "Add to Favorites" button pinned to the bottom of the bottom sheet, outside the scrollable area.

```jsx
{/* Pin to bottom of bottom sheet */}
<div className="shrink-0 p-4 border-t border-gray-100 dark:border-gray-800">
  <button
    type="button"
    onClick={() => onFavoriteToggle(slug)}
    className="flex items-center justify-center gap-2 w-full py-3 rounded-xl font-semibold text-sm text-white transition-colors"
    style={{ background: '#E91E8C' }}
  >
    <Heart size={16} fill={isFav ? 'currentColor' : 'none'} />
    {isFav ? 'Saved' : 'Add to Favorites'}
  </button>
</div>
```

The heart icon in the detail header remains — it is a secondary control. The pink CTA is the primary action.

---

### 3.5 Share Icon — Mobile Detail Header

**File:** `src/js/app.jsx` — `DetailView` component  
**Mockup reference:** `.github/instructions/Dictionary.png` — mobile detail top bar

Add a share button to the detail header on mobile (`isSheet === true`), alongside the existing close (back arrow) button.

```jsx
import { Share2 } from 'lucide-react'; // add to import

// In detail header, when isSheet:
<button
  type="button"
  onClick={() => {
    if (navigator.share) {
      navigator.share({
        title: word.title,
        text: `${word.title} — ${translation}`,
        url: window.location.href,
      }).catch(() => {});
    }
  }}
  className="p-2 hover:bg-gray-100 dark:hover:bg-gray-800 rounded-full shrink-0"
  aria-label="Share this word"
  type="button"
>
  <Share2 size={20} aria-hidden="true" />
</button>
```

Use the Web Share API (`navigator.share`). If unavailable (desktop, unsupported browser), the button is not rendered — wrap in `{navigator.share && ...}`.

---

### 3.6 AIWA Logo and Tagline Footer — Desktop Sidebar

**File:** `src/js/app.jsx` — `DesktopSidebar` component  
**Mockup reference:** `.github/instructions/Dictionary-web.png` — sidebar bottom

Add a footer to the bottom of the desktop sidebar with:
- The AIWA pottery/vessel image (if available as a static asset in the repo)
- Tagline text (three lines — content to be confirmed by AIWA; use placeholder structure)
- AIWA wordmark

**[OPEN — OQ-V1]** The AIWA logo asset path and tagline text must be confirmed before this section renders real content. Use a structural placeholder that matches the mockup layout until confirmed:

```jsx
{/* Sidebar footer */}
<div className="mt-auto pt-4 border-t border-gray-100 dark:border-gray-800">
  {/* Pottery image placeholder */}
  <div className="w-16 h-16 rounded-lg bg-gray-100 dark:bg-gray-800 mb-3" aria-hidden="true" />
  {/* Tagline placeholder — replace with AIWA-confirmed copy */}
  <p className="text-xs text-gray-400 dark:text-gray-500 leading-relaxed">
    Preserving our language.<br />
    Connecting our heritage.<br />
    Building our future.
  </p>
  {/* AIWA wordmark */}
  <p className="mt-2 text-lg font-bold" style={{ color: '#E91E8C' }}>
    AIWA
  </p>
</div>
```

**Do not use the placeholder tagline as final copy.** Mark as `[OPEN — OQ-V1]` in AGENTS.md. AIWA must approve final wording.

---

### 3.7 Word of the Day — Switch to Server Endpoint

**File:** `src/js/app.jsx` — `DictionaryApp` component  
**Spec reference:** `3IATLAS-SUITE-ARCHITECTURE-v1.0.md` — `/word-of-day` endpoint

The current Word of the Day uses `Math.floor(Date.now() / 86400000) % count` — client-side time. The suite architecture specifies a server-side `/word-of-day` endpoint that returns the same word for all users on the same calendar day.

**This fix is conditional on the endpoint existing.** Check whether `GET /sparxstar/v1/dictionary/word-of-day` is implemented in `Sparxstar3IAtlasDictionary.php` before switching.

- If the endpoint exists: fetch from it on app load. Cache result in `localStorage('aiwa-dict-word-of-day')` with a TTL of 24 hours (store `{ slug, date }` — if stored date matches today's date, use cache; else fetch fresh).
- If the endpoint does not exist yet: leave the client-side calculation in place and add a `// TODO: switch to /word-of-day endpoint when available` comment. Do not build a fake endpoint.

---

## 4. AGENTS.md Updates Required

Add the following to AGENTS.md after merging this phase:

```markdown
### Phase 2 UI Fix ✅ Done
Specification: `DICTIONARY-DIRECTION-v3.md` Section 3.
Fixed: Categories nav, example counts on rows, two-column detail desktop layout,
Add to Favorites CTA, Share icon, sidebar footer structure, Word of Day server switch (conditional).

### Open Questions
| ID | Question | Blocking |
|---|---|---|
| OQ-V1 | AIWA logo asset path and tagline copy for sidebar footer | Sidebar footer final content |
| OQ-V2 | Is /word-of-day endpoint implemented? | Section 3.7 Word of Day server switch |
```

---

## 5. What Is NOT in This Phase

Do not build any of the following — they are future phases:

- Games / Play mode — requires v3 spec games section (not yet written)
- `/game-set`, `/domains`, `/word-of-day` endpoints — backend phase
- IndexedDB caching — games phase
- Progress sync / MyCred hooks — games phase
- Phase 3 integration tests — separate phase

---

## 6. Version History

| Version | Date | Changes |
|---|---|---|
| 1.0 | May 2026 | Original direction document |
| 2.0 | May 2026 | Implementation spec — REST API, React rebuild |
| 3.0 | May 2026 | UI fixes against mockup; voting/corrections removed; games deferred |
