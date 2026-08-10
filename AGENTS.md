# AGENTS.md — sparxstar-3iatlas-dictionary

## Platform Governance

Read `.github/instructions/governance/` for compiled ADRs, invariants, and
open questions before building anything. Those files are synced from the
ADR registry and are read-only — never edit them, never commit local
changes to them. If the folder is empty or missing, the org governance-sync
workflow has not run against this repo yet; ask the repo owner to trigger
it from the ADR registry's Actions tab rather than inventing rules.

See `ROLE.md` for this repo's boundary (what it owns / does not own).

Platform repos (read these for full context when accessible):
- Decisions: https://github.com/Starisian-Technologies/sparxstar-architecture-governance-registry
- Specs: https://github.com/Starisian-Technologies/sparxstar-product-specification-registry
- Standards: https://github.com/Starisian-Technologies/starisian-technologies-coding-standards
- Enforcement: https://github.com/Starisian-Technologies/sparxstar-code-conformance
- Contracts: https://github.com/Starisian-Technologies/sparxstar-contracts-registry
- PR Review: https://github.com/Starisian-Technologies/sparxstar-claude-pr-review

If no spec exists for what you're asked to build, stop implementation and
draft or request the missing spec first — do not invent product behavior
in code. This repo's tech spec lives at `docs/dictionary-tech-spec.md`.

---

## What This Repo Is

This is the authoritative lexical data store and REST API service for the entire 3iAtlas platform. It is a WordPress plugin with a React frontend. Every other 3iAtlas tool (WordPad, RLC, Sound to Symbol, Games) is a consumer of this plugin's REST API. This repo does not consume from them.

**Three responsibilities:**
1. Store and serve dictionary entries via WordPress CPTs and ACF fields
2. Expose a public REST API consumed by all 3iAtlas tools
3. Render a public-facing dictionary experience (Browse mode) and word games (Play mode) via a React PWA

---

## Dictionary Role — Read Before Writing Any Code

**Authoritative spec:** `.github/instructions/3IATLAS-DICTIONARY-ROLE-AND-PIPELINE-SPEC-v1.0.md`

The dictionary is a **downstream governed publication service**. It is **linguistically read-only** after import.

```
Community / Speakers / Linguists / Elders
        ↓
DVE (intake, review, normalization, approval)
        ↓
AIWA Dictionary  ← you are here
(import, store, lock, serve)
        ↓
3iAtlas Apps (games, workbooks, browse, API consumers)
```

**What this means for code:**
- The dictionary does NOT intake words. No community submission pathways for new entries.
- The dictionary does NOT adjudicate or flag entries. No review queues, no quality routing.
- Linguistic fields (headword, language, definitions, pronunciation, siblings, speaker community tags) are **locked after import**. WordPress admins cannot edit them directly.
- Operational fields (publish status, visibility, API eligibility, featured flag) are editable.
- All corrections originate upstream in DVE as a new Approved Entry Package, imported via WP-CLI.
- `aiwa_entry_uuid` is minted by DVE. The dictionary preserves it. Never regenerate it. Never let WordPress mint a UUID for an approved entry.

**Import v1 — WP-CLI batch, deliberate and rare:**
```bash
wp aiwa-dictionary import --file=approved-entry-batch.json --dry-run   # validates, no write
wp aiwa-dictionary import --file=approved-entry-batch.json --publish    # validates and writes
```

**Entry lifecycle states:** `active`, `deprecated`, `merged`, `hidden`, `withdrawn` — never delete a UUID once published.

---

## Language Model — Two Layers, Never Mixed

**Authoritative spec:** `.github/instructions/3IATLAS-DICTIONARY-MULTILANGUAGE-MODEL-SPEC-v1.0.md`

The dictionary maintains two independent language layers. They answer different questions.

| Layer | Field | Question answered | Who uses it |
|---|---|---|---|
| **Primary Language Layer** | `starmus_tax_language` | Where does this word belong linguistically? | Games (strict mode), curriculum, formal learning |
| **Speaker Community Layer** | `aiwa_speaker_community` taxonomy | Who uses or recognizes this word in lived speech? | Browse app (ecology mode), literacy, real-speech |

**The rule: never silently mix.** A result set that combines primary-language matches with speaker-community matches without labeling them separately is a spec violation.

**Search modes — always explicit:**

| Mode | Param | Returns | Use for |
|---|---|---|---|
| `mode=strict` | `lang_source=mandinka` | Primary language matches only | Games, quizzes, formal lessons |
| `mode=ecology` | `speaker_community=mandinka-speakers` | Primary language first, then speaker-community matches labeled | Browse, literacy, urban speech |
| `mode=cross_language` | on `/lookup` | Cross-language sibling entries with relation type | Detail views, S2S, WordPad |

Default mode when omitted: `strict`. The game service must always use `strict`. It must never use `ecology`.

**Speaker community taxonomy is controlled.** Valid terms: `mandinka-speakers`, `wolof-speakers`, `fula-speakers`, `jola-speakers`, `serer-speakers`, `soninke-speakers`, `mixed-urban-gambia`, `banjul-market`, `serekunda-urban`, `senegambia-region`, `school-gambia`, `islamic-religious-context`. Freeform tags are not permitted.

**Community usage status per tag:** `observed` → `speaker_confirmed` → `editorial_approved`. Games must only trust `editorial_approved` tags in strict mode.

---

## Absolute Rules — Never Violate

- **Never modify the `aiwa-cpt-dictionary` CPT slug.** Live data depends on it. Changing it destroys existing entries.
- **Never add community voting, correction CPTs, or AJAX voting endpoints.** This feature was removed by design. Do not re-introduce it.
- **Never store dictionary files on the client device in any form.** All dictionary lookups are server-side. The device sends a query; the server returns only the result.
- **Never hardcode language names in the React app.** Language terms come from the `/languages` REST endpoint.
- **Never use `WidthType.PERCENTAGE` in any generated DOCX.** Not relevant here but noted for completeness.
- **Never add a custom database table.** Use WordPress CPTs and post meta only.
- **Treat intentional gaps as intentional gaps.** `useProgressSync.syncNow()` no-op behavior and Helios auth stubs are not bug-fix targets until a replacement spec lands.
- **Game payload field naming:** `/progress/sync` events currently use `word_uuid`, `game`, and `domain`; if adding new game-specific payload fields, prefix them with `game_`. Reserve `aiwa_*` for WordPress hook/event names (e.g. `aiwa_game_word_correct`).
- **Platform context rule:** this plugin is a standalone dictionary/API service in the 3iAtlas suite; do not add DVE/Sky/Mḗh₁n̥s/Dheghom dependencies or cross-service runtime coupling.
- **License header on all PHP files must read `Proprietary`, not `MIT`.**
- **Text domain on all PHP files: `sparxstar-3iatlas-dictionary`.**
- **All PHP files must declare `strict_types=1`.**
- **Namespace root: `Starisian\Sparxstar\IAtlas`** with subpackages `\api`, `\core`, `\frontend`, `\includes`

---

## What Exists (Do Not Rebuild)

- `src/includes/Sparxstar3IAtlasPostTypes.php` — CPT and taxonomy registrations
- `src/frontend/Sparxstar3IAtlasDictionaryForm.php` — community word submission form
- `src/js/app.jsx` — React frontend (Phase 2 complete — full rebuild done; Phase 2 UI Fix also done in PR #64)
- `src/core/Sparxstar3IAtlasDictionary.php` — main plugin class
- `tailwind.config.js` — Tailwind config (needs AIWA brand colors in Phase 2)
- GraphQL queries via WPGraphQL — existing, working

## Data Model — Key CPT and Fields

**Authoritative specs:** `.github/instructions/3IATLAS-DICTIONARY-APPROVED-ENTRY-SPEC-v1.0.md` and `.github/instructions/3IATLAS-DICTIONARY-ENRICHMENT-FIELDS-SPEC-v1.0.md`

**Full field/taxonomy reference (canonical): `docs/dictionary-tech-spec.md` § "Data model".**
This section previously duplicated that table in full; trimmed 2026-07-08 to avoid drift
between two copies. What to remember without opening the other file:

**CPT:** `aiwa-cpt-dictionary` — slug is frozen, never change (see Absolute Rules).
**Canonical identifier:** `aiwa_entry_uuid`, DVE-minted, immutable, never regenerated here.
**Taxonomies:** `starmus_tax_language` (Primary Language Layer), `starmus_tax_dialect`,
`starmus_tax_alpha`, `aiwa_domain` (hierarchical), `aiwa_speaker_community` (Speaker Community
Layer — see Language Model section above), `starmus_part_of_speech`.

- `aiwa_sentence_ipa` (key: `field_696e6b18c17f4`) — registered in `Sparxstar3IAtlasPostTypes.php` but absent from SCF JSON. **Sparxstar3IAtlasPostTypes.php is authoritative. Do not add this field to the SCF JSON.**

---

## SCF DISCREPANCY — DO NOT SYNC

`aiwa_sentence_ipa` (key: `field_696e6b18c17f4`) is registered programmatically
in `src/includes/Sparxstar3IAtlasPostTypes.php` as a sub-field of the example sentences repeater.
It is intentionally absent from the SCF JSON import file.
Sparxstar3IAtlasPostTypes.php is authoritative for ACF field registration.
Do not add this field to the SCF JSON. Do not remove it from Sparxstar3IAtlasPostTypes.php.

---

## REST API — Base Namespace

`sparxstar/v1/dictionary`

**Full endpoint table, auth-row table, and caching rules (canonical): `docs/dictionary-tech-spec.md` § "API surface".**
This section previously duplicated that content in full; trimmed 2026-07-08 to avoid drift
between two copies. Rules to remember without opening the other file:

- **Auth model (Webster Model, June 2026)** — the website is free to use; the API requires
  credentials: an ephemeral page token (`X-Page-Token`, HMAC-SHA256, 60 min TTL, `browse`
  scope) or a consumer API key (`X-Api-Key`, long-lived, SHA-256 hashed at rest). **Rule: all
  new endpoints must declare their auth row in the tech spec's auth table before
  implementation.**
- `POST /progress/sync` is **DEPRECATED and frozen** — do not touch, do not build clients
  against it (see Progress Sync — Current State, below).
- `GET /wordlist` is consumer-API-key only — never page-token, never public.
- **Consumer key onboarding (one step):** issue a key AND add its origin to
  `aiwa_dict_cors_origins` option — these always happen together.
- **Auth implementation:** `src/api/auth/DictionaryAuthInterface.php` — single doorway. When
  `sparxstar-identity` ships, its RS256 JWT verifier becomes a third implementation behind this
  doorway with no endpoint code changes.
- **`/game-set` exclusion rule:** exclude entries missing headword, translation_en, or IPA —
  games require all three.
- **Scale note:** `ORDER BY RAND()` on `/game-set` is acceptable only as a temporary
  implementation pattern; replace before large production datasets.

---

## MyCred Gamification Hooks

**Full hook map (canonical): `docs/dictionary-tech-spec.md` § "MyCred hook map".**
Fired server-side from `handle_progress_sync()` when processing `/progress/sync` events; myCred
listens, and hooks are no-ops when it is absent. Seven hooks exist:
`aiwa_game_word_correct`, `aiwa_game_listen_write_correct`, `aiwa_game_session_complete`,
`aiwa_game_domain_mastered`, `aiwa_game_streak_3`, `aiwa_game_new_word_practiced`,
`aiwa_game_return_visit`.

---

## Offline / Caching Requirements

- All GET endpoint responses must include `Cache-Control: public` headers with a TTL (most
  `max-age=3600`; `/languages` uses `max-age=604800`)
- `/wordlist` and `/game-set` must support `ETag` headers for conditional requests
- `/word-of-day` response must include `date` field (ISO 8601) so clients can detect staleness

---

## Coding Standards

- **WordPress Coding Standards + VIPWPCS** is the canonical PHP standard (NOT PSR-12 — an
  earlier version of this doc said PSR-12, which was incorrect; the code uses WP style:
  `array()`, spaced parens, `aiwa_*`/`sparx*` prefixes, and composer requires `wpcs`+`vipwpcs`).
- `declare(strict_types=1)` at the top of every PHP file
- No raw SQL — use `$wpdb->prepare()` if ever needed
- No `die()` — use `exit(1)` with a message
- All user input sanitized with `sanitize_text_field()` or equivalent before use
- All output escaped with `esc_html()`, `esc_attr()`, `esc_url()` as appropriate
- Rate limiting via WordPress transients — never external infrastructure
- PHP 8.2 minimum, WordPress 6.8 minimum (plugin header `Requires at least: 6.8`; 6.9 is the platform-wide target standard)
- Text domain: `sparxstar-3iatlas-dictionary`. Plugin global prefixes: `sparxstar`/`sparx`/
  `aiwa`/`starisian` and the namespace `Starisian\Sparxstar\IAtlas`.

### PHP tooling and the lint gate

- **PHPCS**: `composer lint:php` runs the repo-wide gate (`phpcs.xml.dist`) — the canonical
  WPCS+VIPWPCS standard, but pre-existing legacy violations are **demoted to warnings** and
  warnings do not fail the build (transitional baseline). New code must not add **errors**.
- **Strict standard** (`.phpcs-strict.xml.dist`) is the full WPCS+VIPWPCS with no demotions.
  CI runs it on **newly-added** `.php` files only (`lint:php:strict`), so new files must pass
  fully while legacy files are cleaned up incrementally. To tighten: delete a demotion line
  in `phpcs.xml.dist` once that category is clean. **Re-promote the Security/DB group first.**
- **PHPStan**: level 5 (`phpstan.neon.dist`); pre-existing findings are captured in
  `phpstan-baseline.neon`. New code must pass clean; shrink the baseline as files are fixed.
- Toolchain pinned to **PHPCS 3.x** (do not jump to PHPCS 4.x until WPCS declares 4.x support)
  and **PHPStan 1.x**. PHPCBF (`composer fix:php`) auto-fixes mechanical violations.

---

## File Structure

```
src/
  api/
    Sparxstar3IAtlasDictionaryRestApi.php   ← Phase 1: 8 endpoints (lookup, search, wordlist, languages, domains, game-set, word-of-day, progress/sync)
    Sparxstar3IAtlasDictionarySpellChecker.php ← Phase 1: POST /spell endpoint
  gamification/
    Sparxstar3IAtlasDictionaryProgress.php  ← Phase 1: myCred hooks for /progress/sync
  includes/
    Sparxstar3IAtlasPostTypes.php           ← Phase 0 bug fixes completed
    Autoloader.php                          ← boot blocker fixed PR #62
  frontend/
    Sparxstar3IAtlasDictionaryForm.php      ← Phase 0 bug fix + PR #62 CSS fix
  core/
    Sparxstar3IAtlasDictionary.php          ← register new classes here
  js/
    app.jsx                                 ← Phase 2 full rebuild done; Phase 2 UI Fix done (PR #64)
    hooks/
      idbUtils.js                           ← Phase 4: shared IndexedDB helper
      useGameSet.js                         ← Phase 4: /game-set fetch + IndexedDB TTL cache
      useGameSession.js                     ← Phase 4: session state + sessionRef pattern
      useProgressSync.js                    ← Phase 4: IndexedDB outbox (syncNow is a no-op — see OQ-002 in docs/dictionary-tech-spec.md § Open Questions. NOT blocked on GAME-SERVICE-INTAKE-SPEC-v1.0, which now exists — blocked on the `sparxstar-identity` issuer, unbuilt. OQ-013, the guest-token question, is closed — guests never sync, by design, not an open blocker)
    games/
      GameShell.jsx                         ← Phase 4: game orchestrator + phase state machine
      AccessoryBar.jsx                      ← Phase 4: Mandinka character input bar
      SessionComplete.jsx                   ← Phase 4: post-session summary
      games/
        MeaningMatch.jsx
        LetterReveal.jsx
        ArrangeWord.jsx
        DomainFlash.jsx
        CompleteSentence.jsx
        ListenWrite.jsx
tailwind.config.js                          ← Phase 2: AIWA brand colors
AGENTS.md                                   ← this file
```

---

## Current State — Phases 0 and 1 Complete

### Phase 0 — Bug Fixes ✅ Done
- `starmus_tax_language` and `starmus_tax_dialect` both registered on `aiwa-cpt-dictionary`
- `src/frontend/Sparxstar3IAtlasDictionaryForm.php` language taxonomy set on submission
- `aiwa_sentence_ipa` SCF discrepancy documented — `src/includes/Sparxstar3IAtlasPostTypes.php` is authoritative, do not add to SCF JSON

### Phase 1 — REST API ✅ Done
Endpoints live under `sparxstar/v1/dictionary`. 8 endpoints are registered in `Sparxstar3IAtlasDictionaryRestApi.php`; POST /spell is registered in `Sparxstar3IAtlasDictionarySpellChecker.php`:
- GET /lookup
- GET /search
- GET /wordlist (with ETag)
- GET /languages
- GET /domains
- GET /game-set
- GET /word-of-day
- POST /progress/sync (temporary non-Helios guard: Bearer token presence + logged-in user + WordPress capability check; full Helios token introspection still TODO)
- POST /spell — in `Sparxstar3IAtlasDictionarySpellChecker.php` (public, rate-limited)

Note: Do not describe `/progress/sync` as complete Helios auth until `permission_helios()` validates Helios tokens via the real introspection/verification path instead of the current stub guard.

Rate limiting extracted to `Sparxstar3IAtlasRateLimitTrait` — used by both RestApi and SpellChecker.

### Phase 2 — React Frontend Rebuild ✅ Done

Specification: `DICTIONARY-DIRECTION-v2.md` Sections 4 and 6 — with voting/corrections removed per `3IATLAS-SUITE-ARCHITECTURE-v1.0.md`. See **Spec Version History** section below for full context.

**Completed:**
- `tailwind.config.js`: AIWA brand colours (brand.pink `#E91E8C`, brand.purple `#7B3FA0`), POS colour map, surface colours, `darkMode: 'class'`. Old `primary` blue palette removed.
- `src/css/sparxstar-3iatlas-dictionary-style.css`: Crimson Pro / Work Sans Google Font `@import` removed from the app bundle. This entry does not imply those fonts are still loaded elsewhere for the form bundle.
- `src/core/Sparxstar3IAtlasDictionary.php`: `wp_localize_script` now passes `ajaxUrl`, `restUrl`, `isLoggedIn`, `userId` in addition to existing keys.
- `src/js/app.jsx`: **Full rebuild** — the old patched file has been replaced. Key features delivered:
  - Three-state responsive layout: mobile (< 1024 px) and desktop (≥ 1024 px). Mobile uses bottom-nav (Home / Explore / Saved / Recent) + bottom-sheet detail. Desktop uses a three-column layout (240 px sidebar, flexible word list, 420 px persistent detail panel).
  - Source-language selector: fetched from REST `GET /languages`; persisted in `localStorage('aiwa-dict-source-lang')`. Renders as horizontal pills on mobile, vertical list in desktop sidebar.
  - Filter pills: All / Noun / Verb / Phrase / Audio / Image — applied client-side.
  - Word list row: deterministic avatar circle (26-colour map), title, POS pill (AIWA brand colours), IPA, translation, audio/image icons, heart (save) button, chevron.
  - Detail view with four tabs: Overview / Examples N / Related / Origin. Desktop: persistent right panel. Mobile/tablet: animated bottom sheet.
  - Word of the Day card: client-side deterministic (`Math.floor(Date.now() / 86400000) % count`). Displayed above word list on mobile home tab and in desktop empty-state panel.
  - Favorites: `localStorage('aiwa-dict-favorites')`. Heart toggle on every row and detail header.
  - History: `localStorage('aiwa-dict-history')` — last 50 viewed words. Shown in "Recent" nav tab.
  - Dark mode: `localStorage('aiwa-dict-theme')`. `dark` class applied to root container; all components carry `dark:` Tailwind variants.
  - Language filter: `localStorage('aiwa-dict-source-lang')`. Words fetched via GraphQL; filtered client-side using `languages { nodes { slug } }` included in the list query.
  - Explore tab: language-card grid; selecting a language sets the source-language filter and switches to Home tab.

**Not implemented (per AGENTS.md absolute rules):**
- Vote UI (removed by design — see Absolute Rules)
- Correction submission UI (removed by design)
- Correction display in detail view (removed by design)

---

### Phase 2 UI Fix ✅ Done

Specification: `AIWA-Dictionary-Direction-v3.md` Section 3 (mockup-aligned). Targeted
changes applied to the existing `app.jsx` (not a rebuild):

- **§3.1 Categories nav** — `DesktopSidebar` now renders a primary nav (Home, Explore,
  Favorites, History, Categories). The desktop center column switches on `activeNav`.
  Categories is a nav alias that renders `ExploreView`. Mobile bottom nav unchanged
  (Home, Explore, Saved, Recent, Play — Play added in Phase 4, superseding v3's
  "four items" note).
- **§3.2 Example counts on rows** — `GET_ALL_WORDS_INDEX` now fetches
  `aiwaExampleSentences { sentenceExample }`; `WordListRow` shows the example-sentence
  count next to the image icon.
- **§3.3 Two-column desktop detail** — `DetailView` (desktop, `isSheet === false`) renders
  always-on left sections (`#detail-meaning`, `#detail-definition`, `#detail-pronunciation`,
  `#detail-image`, `#detail-examples`, `#detail-related`, `#detail-origin`) plus a right
  column of `FeatureCard` scroll anchors. Mobile bottom sheet keeps the four-tab layout.
- **§3.4 Add to Favorites CTA** — pink full-width CTA pinned to the mobile sheet bottom and
  in the desktop right column. Header heart remains as secondary control.
- **§3.5 Share icon** — mobile detail header uses the Web Share API (`navigator.share`);
  not rendered when unsupported.
- **§3.6 Sidebar footer** — pottery placeholder + tagline + AIWA wordmark. **[OPEN — OQ-V1]**
  logo asset path and tagline copy pending AIWA approval (placeholder copy in use).
- **§3.7 Word of the Day** — switched to the server `/word-of-day` endpoint (which exists),
  cached in `localStorage('aiwa-dict-word-of-day')` keyed by date (24h). Falls back to the
  deterministic client-side pick if the endpoint is unavailable or its slug is absent from
  the loaded index.

### Backend hardening (this sprint)

- `/game-set` now sends `Cache-Control: public, max-age=3600` + `ETag` with `304` support
  (was `no-store`). The set is deterministic per calendar day per lang/domain, so it is
  safely cacheable — satisfies the AGENTS.md offline/caching rules.
- `/progress/sync` now returns the standard `{ success, data, meta }` envelope and is
  idempotent: duplicate events (`word_uuid|type|ts`) are detected and skipped via a capped
  per-user transient ledger (`sparx_3iatlas_dict_sync_seen_{user_id}`, 1-day TTL). Helios
  stub and `syncNow()` no-op left untouched (intentional gaps).
- Listen & Write hook canonicalized to **`aiwa_game_listen_write_correct`** (was
  `aiwa_game_listen_write`) across the hook map, the `/progress/sync` handler, and the
  client emitter (`GameShell.jsx`) — matches the games spec / suite architecture. myCred is
  not yet configured for the dictionary, so nothing was listening; this locks the canonical
  name before integration.
- `CompleteSentence` deck now excludes words whose headword does not appear verbatim in the
  example sentence (otherwise the blank substitution silently failed and revealed the answer).
- `package-lock.json` re-synced with `package.json` (missing stylelint deps added) so
  `npm ci` works again.

### Open Questions

| ID | Status | Question | Blocking |
|---|---|---|---|
| OQ-V1 | ⏸ Open | AIWA logo asset path and tagline copy for the desktop sidebar footer | Sidebar footer final content |
| ~~OQ-G1~~ | Retired label — corrected 2026-07-08 | This ID was redefined and reused for two different questions across this repo's own document history (original: Helios-token-source for `/progress/sync`; later: WP nonce auth, closed on a fabricated citation). Do not cite "OQ-G1" going forward. See `docs/dictionary-tech-spec.md` § "OQ-G1 — retired as a citation" for the two disambiguated facts: (1) WP nonce auth for the deprecated `/progress/sync` endpoint — resolved/stable; (2) anonymous/guest game-client token source — **closed 2026-08, was never actually open** (guest play is device-local by design, permanently, per `3IATLAS-IDENTITY-AND-GAME-SERVICES-DECISION-v1.0.md` §4 — no token is ever issued to guests). | — |
| OQ-G3 | ⏸ Open | Animation asset for Letter Reveal — pottery vessel emoji (🏺) is placeholder; replace with AIWA-approved cultural visual | Letter Reveal polish |
| OQ-G4 | ⏸ Open | DomainFlash "I knew it" — currently fires `aiwa_game_word_correct`; confirm if a separate hook is needed | myCred hook map |
| OQ-G5 | ✅ Closed | Sync destination — 3iAtlas Game Service (RLC Node engine), authenticated by suite JWT from `sparxstar-identity` (RS256; apps verify with public key only) | — |
| OQ-I3 | ⏸ Open | Account-claim flow: merging guest device progress into a new suite account | Identity Service spec (not the intake spec — `GAME-SERVICE-INTAKE-SPEC-v1.0` already exists and is implemented, Phase 2/3) |
| OQ-I4 | ⏸ Open | Tier verification: who approves teacher (Lower Basic session-opening) accounts | Identity Service spec |

---

### Progress Sync — Current State (June 2026)

- **Server route `/progress/sync`:** live but **DEPRECATED**. `@deprecated` docblock added. Never extend. Removal scheduled after the Game Service intake is live.
- **Client `syncNow()`:** intentional no-op. **DO NOT IMPLEMENT** until `GAME-SERVICE-INTAKE-SPEC-v1.0` is committed to `.github/instructions/` in this repo.
- **Event schema — corrected 2026-07-08:** the prior claim here ("FROZEN as contract: `word_uuid`, `game_type`, `outcome`, `attempts`, `xp`, `timestamp`, production-vs-recognition distinction, per PR #59 Fix 2") was checked directly against PR #59 and found to be fabricated — that PR contains no schema definition or "Fix 2" content; its actual content is the `sessionRef` stale-closure fix. The verified, currently-shipped wire shape is `{ type, word_uuid?, game?, domain?, ts }`. `outcome`/`attempts`/`xp` exist only in the separate, local-only `useGameSession.recordResult()` object and never leave the client. See `docs/dictionary-tech-spec.md` § "Game integration" for the full verification and for what remains an open decision (a richer frozen wire contract, if ever needed, is not yet settled).
- **IndexedDB outbox behavior:** unchanged, correct, keep.
- **Sync target:** 3iAtlas Game Service (§3 of decision record). Suite JWT from `sparxstar-identity`. Guest play stays device-local.

### Authentication Rule (suite-wide, permanent)

WordPress authentication is prohibited for all user-facing features. WordPress sessions are admin-only. User identity comes from the suite Identity Service (`sparxstar-identity`) when its spec lands. Do not add `wp_nonce` / `is_user_logged_in()` gates to any new user-facing endpoint.

---

### Phase 4 — Games / Play Tab ✅ Done

Specification: `.github/instructions/dictionary-game-spec-v1.md`

**New files:**
```
src/js/hooks/idbUtils.js           — shared IndexedDB helper (openDB, getRecord, putRecord, getAllRecords, deleteRecord)
src/js/hooks/useGameSet.js         — /game-set fetch + 3-day IndexedDB TTL cache
src/js/hooks/useGameSession.js     — session state (currentIndex, results, xpEarned, checkpoint resume) + `sessionRef` mirror pattern to prevent stale-session writes during rapid actions
src/js/hooks/useProgressSync.js    — event outbox (IndexedDB); network sync intentionally a no-op — see OQ-002 (docs/dictionary-tech-spec.md § Open Questions). NOT blocked on GAME-SERVICE-INTAKE-SPEC-v1.0, which now exists and is implemented — blocked on the `sparxstar-identity` issuer, unbuilt. OQ-013 (guest-token question) is closed, not open — guests never sync, by design
src/js/games/AccessoryBar.jsx      — Mandinka character bar (ŋ ɓ ɗ ñ ɲ ʔ á é í ó ú), visualViewport positioning
src/js/games/SessionComplete.jsx   — post-session summary (stats, cumulative word count, action buttons)
src/js/games/GameShell.jsx         — session setup (domain/game/word-count selectors), game router, phase management
src/js/games/games/DomainFlash.jsx — Game 4.6: flashcard reveal, "I knew it" / "Still learning"
src/js/games/games/MeaningMatch.jsx — Game 4.3: 3-option meaning selection, same-domain distractors
src/js/games/games/ArrangeWord.jsx — Game 4.2: scrambled letter tiles, tap-to-place, auto-check
src/js/games/games/LetterReveal.jsx — Game 4.5: alphabet pool, 5 wrong = word skipped, pottery vessel tilt
src/js/games/games/CompleteSentence.jsx — Game 4.4: sentence with word blanked, typed input, AccessoryBar
src/js/games/games/ListenWrite.jsx — Game 4.1: auto-play audio, typed response, AccessoryBar, +10 XP
```

**Changes to existing files:**
- `src/js/app.jsx`: Play tab added to mobile bottom nav (5th item, Gamepad2 icon). Desktop gets Browse/Play tab bar above the content area. GameShell rendered when Play is active.
- `webpack.config.js`: Added `javascript/auto` rule for `src/**/*.js` to allow ESM import/export with `"type":"commonjs"` in package.json.

**Open questions carried forward:**
| ID | Question | Blocking |
|---|---|---|
| OQ-G3 | Animation asset for Letter Reveal — pottery vessel emoji (🏺) used as placeholder; replace with AIWA-approved cultural visual | Letter Reveal polish |
| OQ-G4 | Domain Flash "I knew it" — currently fires `aiwa_game_word_correct`; confirm if a separate hook is needed | MyCred hook map |

---

### Boot Blockers — FIXED (PR #62)

Both boot blockers below were resolved in PR #62. Recorded for historical context only.

1. **Autoloader constants mismatch** (`src/includes/Autoloader.php`) — **FIXED**
   Now correctly uses `SPARX_3IATLAS_NAMESPACE` and `SPARX_3IATLAS_PATH`.

2. **Frontend form CSS enqueue mismatch** (`src/frontend/Sparxstar3IAtlasDictionaryForm.php`) — **FIXED**
   The enqueue was corrected to reference the existing built stylesheet (`sparxstar-3iatlas-dictionary-style.min.css`). The `wp_enqueue_style()` call remains — it was pointing at the wrong filename, not absent.

`syncNow()` no-op and Helios auth stubs remain as **intentional gaps** — do not alter without an approved spec.

---

## Spec Version History and Decision Record

> **READ THIS BEFORE STARTING ANY NEW SPRINT.** This section documents the decision trail so future sessions do not re-introduce removed features.

### What Copilot Built Against in Each Phase

| Phase | Spec used | Status |
|---|---|---|
| Phase 0 (bug fixes) | `DICTIONARY-DIRECTION-v2.md` | Correct |
| Phase 1 (REST API) | `DICTIONARY-DIRECTION-v2.md` | Mostly correct — endpoints align |
| Phase 2 (React rebuild) | `DICTIONARY-DIRECTION-v2.md` Sections 4 & 6 | UI correct; voting/corrections correctly omitted |

### The v2 / Architecture Doc Conflict

`DICTIONARY-DIRECTION-v2.md` still exists in this repo. **It should be treated as partially superseded.** Specifically:

- **Section 2 (Community Corrections & Voting)** is **fully removed** from the product. The decision to remove it was made in the May 14, 2026 session and captured in `3IATLAS-SUITE-ARCHITECTURE-v1.0.md` (stored at `.github/instructions/`). Games replace community voting as the quality/engagement signal.
- **Sections 4 and 6** (UI and work plan) remain broadly correct for Phase 2, with the voting UI removed.

The AGENTS.md Absolute Rule "Never add community voting, correction CPTs, or AJAX voting endpoints" is **correct** and takes precedence over anything written in v2.

### Authoritative Spec for Future Work

For anything beyond Phase 2, the authoritative reference is **`3IATLAS-SUITE-ARCHITECTURE-v1.0.md`** at `.github/instructions/`. Key decisions recorded there:

- Community voting and corrections: **removed**. Games replace them.
- The `aiwa-cpt-correction` CPT was designed in v2 but **must not be created**.
- The `user_vote`, `vote_counts`, and `corrections` fields were designed for `/lookup` in v2 but **must not be added**.
- No community AJAX endpoints — these were v2 designs that did not ship.

### v3 Status

`AIWA-Dictionary-Direction-v3.md` is now committed in `.github/instructions/` and should be treated as active guidance for sprint sequencing and guardrails.

For continuation work:
- Treat documented boot blockers as bug fixes first (autoloader constants and form CSS enqueue path).
- Keep intentional gaps (`syncNow()` no-op and Helios stubs) unchanged until a dedicated spec lands.

### Dictionary Architecture — June 2026 (active)

Four specs committed to `.github/instructions/` that define the dictionary's final architecture. Any code that contradicts these specs is a violation.

| Spec | Status | Summary |
|---|---|---|
| `3IATLAS-DICTIONARY-ROLE-AND-PIPELINE-SPEC-v1.0.md` | Active | DVE upstream / dictionary downstream. Linguistically read-only after import. WP-CLI batch import. Entry lifecycle states. Edit lock rules. |
| `3IATLAS-DICTIONARY-APPROVED-ENTRY-SPEC-v1.0.md` | Active | Approved Entry Package format. Required fields. UUID ownership (DVE mints, dictionary preserves). AIWA Level scale. Replacement and deprecation packages. |
| `3IATLAS-DICTIONARY-MULTILANGUAGE-MODEL-SPEC-v1.0.md` | Active | Primary Language Layer + Speaker Community Layer. Strict / ecology / cross-language search modes. No silent mixing. Controlled speaker community taxonomy. Cross-language relation types. Community usage status. JWT language claims (for identity service). |
| `3IATLAS-DICTIONARY-ENRICHMENT-FIELDS-SPEC-v1.0.md` | Active | AIWA Level sovereign scale. CEFR/Oxford as reference mappings only. Concepticon and CLICS academic anchors. Rhyme entries field. Domain taxonomy expansion. Updated `/game-set` and `/wordlist` filter params. |

### Phase 3 — Integration Tests ⏸ Pending

Phase 3 covers cross-tool REST integration verification. The authoritative scope is in `3IATLAS-SUITE-ARCHITECTURE-v1.0.md`:
- WordPad → `/lookup` and `/spell` endpoints
- S2S → `/wordlist` with `lang_source`
- RLC → offline `/wordlist` with `lang_source` filter and fallback

### Phase 4 — Games / Play Tab ✅ Done (PR #59, merged May 2026)

Six game types implemented: MeaningMatch, LetterReveal, ArrangeWord, DomainFlash, CompleteSentence, ListenWrite.

Key files added:
- `src/js/games/GameShell.jsx` — phase state machine, game orchestrator
- `src/js/games/AccessoryBar.jsx` — special character bar (ŋ ɓ ɗ ñ ɲ ʔ), always present for typed input
- `src/js/games/SessionComplete.jsx` — end-of-session summary
- `src/js/games/games/*.jsx` — individual game components
- `src/js/hooks/useGameSet.js` — IndexedDB-backed game set cache
- `src/js/hooks/useGameSession.js` — session tracking with sessionRef pattern
- `src/js/hooks/useProgressSync.js` — IndexedDB outbox (syncNow is intentional no-op — see OQ-002 in docs/dictionary-tech-spec.md § Open Questions. NOT blocked on GAME-SERVICE-INTAKE-SPEC-v1.0, which now exists and is implemented — blocked on the `sparxstar-identity` issuer, unbuilt. OQ-013, the guest-token question, is closed, not open — guests never sync, by design)

**sessionRef pattern:** `recordResult` and `completeSession` in `useGameSession.js` use `sessionRef.current` to avoid stale React closure bugs. Do not remove this pattern.

---

## Repairs Fixed (PR #62)

Both boot blockers below were resolved in PR #62 (merged May 2026). They are recorded here for historical context only — do not re-introduce either issue.

**1. Autoloader constant mismatch — FIXED**
`src/includes/Autoloader.php` previously used legacy `STARISIAN_NAMESPACE` / `STARISIAN_PATH`.
Now correctly uses `SPARX_3IATLAS_NAMESPACE` and `SPARX_3IATLAS_PATH`.

**2. Missing form CSS asset — FIXED**
`src/frontend/Sparxstar3IAtlasDictionaryForm.php` previously enqueued a non-existent stylesheet filename.
The enqueue was corrected to reference `sparxstar-3iatlas-dictionary-style.min.css` (the existing built asset). The `wp_enqueue_style()` call is still present and correct — do not remove it.

---

## What Copilot Must Not Do

- Add voting, correction, or community review features — removed by design
- Add a custom WordPress admin page — use standard WP CPT list for any admin needs
- Call Brain (PostgreSQL) directly — this plugin does not connect to Brain
- Add DVE, Sky, Mḗh₁n̥s, or Dheghom dependencies
- Store dictionary data in localStorage or IndexedDB on the client
- Add the `aiwa_sentence_ipa` field to the SCF JSON
- Create a custom database table
- Hardcode language names anywhere in the React app
