# Copilot Instructions — sparxstar-3iatlas-dictionary

## What This Repo Is

A standalone WordPress plugin that is the **authoritative lexical data store and REST API** for the 3iAtlas platform. It is part of the SPARXSTAR family but is **not a DVE component** — it does not use Sirus, Helios, Mḗh₁n̥s, or Dheghom at runtime. It operates in standalone mode by design.

Every other 3iAtlas tool (WordPad, RLC, Sound to Symbol) is a consumer of this plugin's REST API. Data flow is one-way: this repo serves, others consume. No reverse flow.

**Three responsibilities:**
1. Store and serve dictionary entries (WordPress CPT + ACF fields)
2. Expose a public REST API at `sparxstar/v1/dictionary`
3. Render a React PWA with Browse mode (dictionary) and Play mode (six language games)

---

## Reference Documents — Read Before Any Sprint

- `.github/instructions/` — all authoritative specs
- `3IATLAS-SUITE-ARCHITECTURE-v1.0.md` — supersedes all earlier architecture decisions
- `.github/instructions/dictionary-game-spec-v1.md` — games specification
- `AGENTS.md` — phase-by-phase implementation record and absolute rules
- `DICTIONARY-DIRECTION-v2.md` — **partially superseded**. Section 2 (community voting/corrections) is fully removed. Sections 4 and 6 remain broadly correct for Browse UI.

---

## Current State — As of May 2026

| Phase | Description | Status |
|---|---|---|
| Phase 0 | Bug fixes — CPT, taxonomies, form | ✅ Done |
| Phase 1 | REST API — 9 endpoints | ✅ Done |
| Phase 2 | React frontend rebuild — Browse mode | ✅ Done |
| Phase 3 | Cross-tool integration tests (WordPad, S2S, RLC) | ⏸ Pending |
| Phase 4 | Games / Play tab — six game types, session management | ✅ Done (merged PR #59) |

**What is in `main` right now:**
- Full React app with Browse and Play tabs
- Six games: MeaningMatch, LetterReveal, ArrangeWord, DomainFlash, CompleteSentence, ListenWrite
- IndexedDB-backed game set cache (`useGameSet`), session tracking (`useGameSession`), progress outbox (`useProgressSync`)
- Spell API hardened with `$wpdb` guard and normalized response envelope (merged PR #61)

---

## Known Repairs Needed — Do These Before New Features

**1. Autoloader constant mismatch (boot blocker)**
`src/includes/Autoloader.php` references `STARISIAN_NAMESPACE` and `STARISIAN_PATH`.
The plugin header defines `SPARX_3IATLAS_NAMESPACE` and `SPARX_3IATLAS_PATH`.
If Composer vendor is absent, the fallback autoloader silently fails and no classes load.
Fix: update `src/includes/Autoloader.php` to use `SPARX_3IATLAS_NAMESPACE` and `SPARX_3IATLAS_PATH`.

**2. Missing form CSS reference**
`src/frontend/Sparxstar3IAtlasDictionaryForm.php` enqueues `sparxstar-3iatlas-dictionary-form-style.min.css` which does not exist in `/assets/css/`.
Fix: remove that `wp_enqueue_style()` call (and its matching `wp_register_style()` if present). The form falls back to the shared stylesheet.

---

## Known Intentional Gaps — Do Not Fix These Without a Spec

**`useProgressSync.syncNow()` is a no-op (OQ-G1)**
Progress sync to the server is intentionally deferred. The outbox is written to IndexedDB.
Do not implement `syncNow()` as a simple REST POST. Before implementing OQ-G1, a spec decision is needed on two-mode behaviour: standalone (write to WordPress user meta) vs. full-system (route through governed pipeline). Implementing blind will require a refactor.

**`// TODO: Replace with Helios token introspection`**
There are 9 of these across `src/api/`. They are **integration stubs, not technical debt**. They correctly mark where Helios authentication attaches when the full SPARXSTAR stack is present. In standalone mode, WordPress user checks are correct degraded behaviour. Do not remove them or replace them with a different auth model without a platform decision.

---

## Absolute Rules — Never Violate

- **Never modify the `aiwa-cpt-dictionary` CPT slug.** Live data depends on it.
- **Never add community voting, correction CPTs, or AJAX voting endpoints.** Removed by design. Games replace them.
- **Never store dictionary word data on the client device.** All lookups are server-side. Devices send a query; server returns only the result. (Game set caching in IndexedDB is permitted — that is gameplay data, not the dictionary corpus.)
- **Never hardcode language names in the React app.** Language terms come from `GET /languages`.
- **Never add a custom database table.** Use WordPress CPTs and post meta only.
- **Never use `aiwa_` or `sparxstar_` prefixes for game mechanics data.** Those prefixes are governed cultural data markers. Game scores, session state, and learned-word records must use `game_` prefix or `_spx_` for session-scoped data. This keeps the governed data perimeter clean for future DVE integration.
- **Never add DVE, Sky, Mḗh₁n̥s, Dheghom, or Sirus dependencies.** This is a standalone component.
- **Never use `wordpress/mcp-adapter`.** It does not exist on Packagist. Use the Node gateway pattern for any MCP integration.
- **License header on all PHP files: `Proprietary`, not `MIT`.**
- **Text domain: `sparxstar-3iatlas-dictionary`.**
- **All PHP files must declare `declare(strict_types=1)`.**
- **Namespace: `Starisian\Sparxstar\Atlas\Dictionary`.**
- **`exit(1)` not `wp_die()` in loader/bootstrap contexts.**
- **No `SELECT *` — always name columns.**
- **No `error_log()` in production paths.** Wrap in `WP_DEBUG` check.
- **No `empty()` for validation.** Use `=== null || === ''`. `empty()` treats `"0"` as empty.

---

## REST API

**Base namespace:** `sparxstar/v1/dictionary`

**Auth model:**
- All GET endpoints: public, rate-limited (100 req / 15 min / IP via WordPress transients)
- `POST /progress/sync`: temporary non-Helios guard (Bearer token presence + `is_user_logged_in()` + capability check). Mark with `// TODO: Replace with Helios token introspection`.
- `POST /spell`: public, rate-limited

**Response envelope (all endpoints):**
```json
{ "success": true, "data": {}, "meta": { "total": 0, "page": 1, "per_page": 20 } }
```

**Endpoints:**

| Method | Path | Purpose |
|---|---|---|
| GET | `/lookup` | Full entry by slug or UUID |
| GET | `/search` | Search by query string |
| GET | `/wordlist` | Lightweight list for offline caching (ETag required) |
| GET | `/languages` | Language taxonomy terms with counts |
| GET | `/domains` | Domain taxonomy terms with counts |
| GET | `/game-set` | Curated word set for game use |
| GET | `/word-of-day` | Single deterministic daily entry |
| POST | `/progress/sync` | Batch game event sync → myCred hooks |
| POST | `/spell` | Spell-check against dictionary entries |

**`/game-set` rules:**
- Parameters: `lang_source` (required), `domain` (optional), `limit` (default 20, max 50), `include_audio` (bool)
- Exclude entries missing headword, translation_en, or IPA — all three required for games
- `ORDER BY RAND()` is acceptable temporarily; replace before large production datasets

**Caching headers:**
- All GET responses: `Cache-Control: public, max-age=3600`
- `/wordlist` and `/game-set`: ETag support for conditional requests
- `/word-of-day`: include `date` field (ISO 8601) so clients detect staleness

---

## Games Architecture (Phase 4 — Merged)

**Files added in Phase 4:**
```
src/js/games/
  GameShell.jsx          — top-level game orchestrator, phase state machine
  AccessoryBar.jsx       — special character input bar (ŋ ɓ ɗ ñ ɲ ʔ) — always present for typed input
  SessionComplete.jsx    — end-of-session summary
  games/
    MeaningMatch.jsx
    LetterReveal.jsx
    ArrangeWord.jsx
    DomainFlash.jsx
    CompleteSentence.jsx
    ListenWrite.jsx
src/js/hooks/
  useGameSet.js          — fetches and caches game word set in IndexedDB
  useGameSession.js      — session state, learned-word tracking, sessionRef pattern
  useProgressSync.js     — IndexedDB outbox for progress events (syncNow is no-op — OQ-G1)
```

**Game design mandate (from game spec):**
- Users are fluent Mandinka speakers learning to write their own language. The gap is orthographic, not vocabulary.
- Never start from nothing — always scaffold with audio, partial letters, meaning, or domain hint.
- Wrong answers reveal more (next letter, IPA, definition) — never just "incorrect."
- AccessoryBar is non-negotiable for any game requiring typed input.
- Offline first — no network calls during gameplay once game set is loaded.

**`useGameSession` — sessionRef pattern:**
`recordResult` and `completeSession` read from `sessionRef.current` (not React `session` state) to avoid stale closure bugs. `sessionRef` is updated synchronously before any `await`. Do not remove this pattern.

---

## MyCred Gamification Hooks

Fire these on `/progress/sync` events. myCred listens; when absent, hooks are no-ops.

```php
do_action('aiwa_game_word_correct',       $user_id, $word_uuid, $game_type); // +5 XP
do_action('aiwa_game_listen_write',       $user_id, $word_uuid);              // +10 XP
do_action('aiwa_game_session_complete',   $user_id, $domain_slug);            // +25 XP
do_action('aiwa_game_domain_mastered',    $user_id, $domain_slug);            // +50 Gold
do_action('aiwa_game_streak_3',           $user_id);                          // +15 XP
do_action('aiwa_game_new_word_practiced', $user_id, $word_uuid);              // +8 XP
do_action('aiwa_game_return_visit',       $user_id);                          // +10 XP
```

---

## Data Model

**CPT:** `aiwa-cpt-dictionary` — never change this slug.

**Key ACF fields:**
- `aiwa_extract` — definition text
- `aiwa_translation_en` / `aiwa_translation_fr`
- `aiwa_ipa` — IPA pronunciation
- `aiwa_phonetic` — phonetic pronunciation
- `aiwa_audio_file` — audio URL
- `aiwa_word_photo` — image URL
- `aiwa_origin` — etymology notes
- `aiwa_synonyms` / `aiwa_antonyms` — related word relationships
- `aiwa_example_sentences` — repeater: sentence, IPA, phonetic, EN/FR translation

**SCF discrepancy — do not sync:**
`aiwa_sentence_ipa` (key: `field_696e6b18c17f4`) is registered programmatically in `PostTypes.php` as a sub-field of the example sentences repeater. It is intentionally absent from the SCF JSON. `PostTypes.php` is authoritative. Do not add it to the SCF JSON. Do not remove it from `PostTypes.php`.

**Field prefix rule for game data:**
Game mechanics data (scores, session state, learned-word records) must never use `aiwa_` or `sparxstar_` prefixes. Use `game_` for persistent game user meta, `_spx_` for session-scoped data.

---

## SPARXSTAR Platform Context

This repo is SPARXSTAR-family but operates in standalone mode. Standalone means:
- Functional to the highest capability possible without the full DVE stack
- The Helios TODO stubs are correct — they mark future integration points
- `syncNow()` no-op is correct — it marks a future governed pipeline integration point
- Do not attempt to replicate Sirus, Helios, Mḗh₁n̥s, or Dheghom behaviour locally

**Eshu migration awareness:**
The platform direction moves PHP processing pipelines toward Eshu MCP and ACF/CPT toward Dheghom vault storage. New capabilities that need persistent storage should be designed lightly. Do not over-invest in WordPress/ACF for new storage layers if they will migrate.

**Export contract:**
The dependency direction is: DVE → export → 3iAtlas dictionary → RLC. The dictionary is a DVE export consumer, not a DVE runtime component. No live DVE connection at runtime.

---

## Coding Standards

- PSR-12 for all PHP
- `declare(strict_types=1)` at the top of every PHP file
- Typed parameters and return types on all methods
- No raw SQL — use `$wpdb->prepare()` if ever needed
- No `die()` — use `exit(1)` with a message in loaders, `wp_send_json_error()` in AJAX
- All user input sanitized with `sanitize_text_field()` or equivalent
- All output escaped with `esc_html()`, `esc_attr()`, `esc_url()` as appropriate
- Rate limiting via WordPress transients — never external infrastructure
- PHP 8.2 minimum, WordPress 6.9 minimum

---

## What Copilot Must Not Do

- Add voting, correction, or community review features — removed by design
- Re-introduce `aiwa-cpt-correction` CPT or `user_vote` / `vote_counts` fields
- Add a custom WordPress admin page — use standard WP CPT list for admin needs
- Connect to Brain (PostgreSQL) directly — this plugin does not use Brain
- Add Sirus, Helios, Sky, Mḗh₁n̥s, or Dheghom dependencies
- Use `wordpress/mcp-adapter` — it does not exist
- Add `aiwa_sentence_ipa` to the SCF JSON
- Create a custom database table
- Hardcode language names anywhere in the React app
- Use `aiwa_` or `sparxstar_` prefixes for game session or score data
- Implement `syncNow()` without a two-mode spec decision (OQ-G1)
- Remove or replace Helios TODO stubs with a different auth model without a platform decision
