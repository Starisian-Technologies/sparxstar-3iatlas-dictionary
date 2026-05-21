# AGENTS.md — sparxstar-3iatlas-dictionary

## What This Repo Is

This is the authoritative lexical data store and REST API service for the entire 3iAtlas platform. It is a WordPress plugin with a React frontend. Every other 3iAtlas tool (WordPad, RLC, Sound to Symbol, Games) is a consumer of this plugin's REST API. This repo does not consume from them.

**Three responsibilities:**
1. Store and serve dictionary entries via WordPress CPTs and ACF fields
2. Expose a public REST API consumed by all 3iAtlas tools
3. Render a public-facing dictionary experience (Browse mode) and word games (Play mode) via a React PWA

---

## Absolute Rules — Never Violate

- **Never modify the `aiwa-cpt-dictionary` CPT slug.** Live data depends on it. Changing it destroys existing entries.
- **Never add community voting, correction CPTs, or AJAX voting endpoints.** This feature was removed by design. Do not re-introduce it.
- **Never store dictionary files on the client device in any form.** All dictionary lookups are server-side. The device sends a query; the server returns only the result.
- **Never hardcode language names in the React app.** Language terms come from the `/languages` REST endpoint.
- **Never use `WidthType.PERCENTAGE` in any generated DOCX.** Not relevant here but noted for completeness.
- **Never add a custom database table.** Use WordPress CPTs and post meta only.
- **License header on all PHP files must read `Proprietary`, not `MIT`.**
- **Text domain on all PHP files: `sparxstar-3iatlas-dictionary`.**
- **All PHP files must declare `strict_types=1`.**
- **Namespace: `Starisian\Sparxstar\Atlas\Dictionary`**

---

## What Exists (Do Not Rebuild)

- `src/includes/Sparxstar3IAtlasPostTypes.php` — CPT and taxonomy registrations
- `src/frontend/Sparxstar3IAtlasDictionaryForm.php` — community word submission form
- `src/js/app.jsx` — React frontend (needs full rebuild in Phase 2 — do not patch, wait for spec)
- `src/core/Sparxstar3IAtlasDictionary.php` — main plugin class
- `tailwind.config.js` — Tailwind config (needs AIWA brand colors in Phase 2)
- GraphQL queries via WPGraphQL — existing, working

## Data Model — Key CPT and Fields

**CPT:** `aiwa-cpt-dictionary`
**Taxonomies:** `starmus_tax_language` (source language — Mandinka, Wolof, etc.), `starmus_tax_dialect`, `starmus_tax_alpha` (alphabetical grouping)

Key ACF fields on `aiwa-cpt-dictionary`:
- `aiwa_extract` — definition/extract text
- `aiwa_translation_en` — English translation
- `aiwa_translation_fr` — French translation
- `aiwa_ipa` — IPA pronunciation
- `aiwa_phonetic` — phonetic pronunciation
- `aiwa_audio_file` — audio recording URL
- `aiwa_word_photo` — image URL
- `aiwa_origin` — word origin notes
- `aiwa_synonyms` / `aiwa_antonyms` — related words
- `aiwa_example_sentences` — repeater field with sub-fields: sentence, IPA, phonetic, EN translation, FR translation
- `aiwa_sentence_ipa` (key: `field_696e6b18c17f4`) — registered in PostTypes.php but absent from SCF JSON. **PostTypes.php is authoritative. Do not add this field to the SCF JSON.**

---

## SCF DISCREPANCY — DO NOT SYNC

`aiwa_sentence_ipa` (key: `field_696e6b18c17f4`) is registered programmatically
in `PostTypes.php` as a sub-field of the example sentences repeater.
It is intentionally absent from the SCF JSON import file.
PostTypes.php is authoritative for ACF field registration.
Do not add this field to the SCF JSON. Do not remove it from PostTypes.php.

---

## REST API — Base Namespace

`sparxstar/v1/dictionary`

**Auth model:**
- All GET endpoints: public, no auth required, rate-limited (100 requests / 15 min / IP via WordPress transients)
- POST `/progress/sync`: temporary non-Helios guard (Bearer token presence + logged-in user + capability check) until Helios token introspection is implemented
- Add `// TODO: Replace with Helios token introspection` comment on every rate-limit check

**Response envelope (all endpoints):**
```json
{ "success": true, "data": {}, "meta": { "total": 0, "page": 1, "per_page": 20 } }
```

**Core Phase 1 endpoints (implemented):**

| Method | Path | Auth | Purpose |
|---|---|---|---|
| GET | `/lookup` | Public | Full entry by slug or UUID |
| GET | `/search` | Public | Search entries by query string |
| GET | `/wordlist` | Public | Lightweight word list for offline caching |
| GET | `/languages` | Public | All language taxonomy terms with word counts |
| GET | `/domains` | Public | Semantic domain taxonomy terms with counts |
| GET | `/game-set` | Public | Curated word set for game use (richer than wordlist) |
| GET | `/word-of-day` | Public | Single deterministic daily entry |
| POST | `/progress/sync` | Temporary non-Helios guard | Batch game event sync → myCred points |
| POST | `/spell` | Public (rate-limited) | Spell-checking service for dictionary entries |

**`/game-set` parameters:** `lang_source` (required), `domain` (optional), `limit` (default 20, max 50), `include_audio` (bool)
**`/game-set` exclusion rule:** Exclude entries missing headword, translation_en, or IPA. Games require all three.
**Scale note:** `ORDER BY RAND()` is acceptable only as a temporary implementation pattern; replace it with a scalable selection approach before large production datasets.

---

## MyCred Gamification Hooks

Fire these WordPress action hooks when processing `/progress/sync` events. myCred listens; when absent, hooks are no-ops.

```php
do_action('aiwa_game_word_correct',      $user_id, $word_uuid, $game_type);   // +5 XP
do_action('aiwa_game_listen_write',      $user_id, $word_uuid);                // +10 XP
do_action('aiwa_game_session_complete',  $user_id, $domain_slug);              // +25 XP
do_action('aiwa_game_domain_mastered',   $user_id, $domain_slug);              // +50 Gold
do_action('aiwa_game_streak_3',          $user_id);                            // +15 XP
do_action('aiwa_game_new_word_practiced',$user_id, $word_uuid);                // +8 XP
do_action('aiwa_game_return_visit',      $user_id);                            // +10 XP
```

---

## Offline / Caching Requirements

- All GET endpoint responses must include `Cache-Control: public, max-age=3600` headers
- `/wordlist` and `/game-set` must support `ETag` headers for conditional requests
- `/word-of-day` response must include `date` field (ISO 8601) so clients can detect staleness

---

## Coding Standards

- PSR-12 for all PHP
- `declare(strict_types=1)` at the top of every PHP file
- No raw SQL — use `$wpdb->prepare()` if ever needed
- No `die()` — use `exit(1)` with a message
- All user input sanitized with `sanitize_text_field()` or equivalent before use
- All output escaped with `esc_html()`, `esc_attr()`, `esc_url()` as appropriate
- Rate limiting via WordPress transients — never external infrastructure
- PHP 8.2 minimum

---

## File Structure

```
src/
  api/
    Sparxstar3IAtlasDictionaryRestApi.php   ← Phase 1: implemented
  gamification/
    Sparxstar3IAtlasDictionaryProgress.php  ← Phase 1: implemented
  includes/
    Sparxstar3IAtlasPostTypes.php           ← Phase 0 bug fixes completed
  frontend/
    Sparxstar3IAtlasDictionaryForm.php      ← Phase 0 bug fix completed
  core/
    Sparxstar3IAtlasDictionary.php          ← register new classes here
  js/
    app.jsx                                 ← Phase 2: full rebuild
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
Endpoints live under `sparxstar/v1/dictionary`:
- GET /lookup
- GET /search
- GET /wordlist (with ETag)
- GET /languages
- GET /domains
- GET /game-set
- GET /word-of-day
- POST /progress/sync (temporary non-Helios guard: Bearer token presence + logged-in user + WordPress capability check; full Helios token introspection still TODO)
- POST /spell (public, rate-limited spell-check endpoint)

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

### Phase 4 — Games / Play Tab ✅ Done

Specification: `.github/instructions/dictionary-game-spec-v1.md`

**New files:**
```
src/js/hooks/idbUtils.js           — shared IndexedDB helper (openDB, getRecord, putRecord, getAllRecords, deleteRecord)
src/js/hooks/useGameSet.js         — /game-set fetch + 3-day IndexedDB TTL cache
src/js/hooks/useGameSession.js     — session state (currentIndex, results, xpEarned, checkpoint resume)
src/js/hooks/useProgressSync.js    — event outbox → POST /progress/sync on connect
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

### What v3 of Dictionary Direction Will Add

The architecture doc specifies a v3 of the direction document that will:
- Remove Section 2 entirely
- Add games as a first-class feature: Browse ↔ Play tab navigation, five game types
- Specify IndexedDB caching (not localStorage) for game-set and wordlist data
- Add a Play mode UI spec

**Until that v3 spec is committed to this repo, do not build the games UI.** Wait for the spec document, same as Phase 2 waited for the UI spec.

### Phase 3 — Integration Tests ⏸ Pending

Phase 3 covers cross-tool REST integration verification. The authoritative scope is in `3IATLAS-SUITE-ARCHITECTURE-v1.0.md`:
- WordPad → `/lookup` and `/spell` endpoints
- S2S → `/wordlist` with `lang_source`
- RLC → offline `/wordlist` with `lang_source` filter and fallback

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
