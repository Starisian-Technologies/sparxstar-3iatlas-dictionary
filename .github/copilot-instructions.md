# Copilot Instructions — sparxstar-3iatlas-dictionary

## Reference repositories (read via MCP)

Before reviewing any PR, read these repos:
- ADR Registry: Starisian-Technologies/sparxstar-architecture-governance-registry
- Product Specs: Starisian-Technologies/sparxstar-product-specification-registry
- Coding Standards: Starisian-Technologies/starisian-technologies-coding-standards
- Enforcement Workflows: Starisian-Technologies/sparxstar-code-conformance
- Contracts: Starisian-Technologies/sparxstar-platform-contracts
- Claude PR Review: Starisian-Technologies/sparxstar-claude-pr-review

## Review checklist

Flag any PR that:
- Contradicts an ADR or invariant
- Assumes an answer to an open question (OQ in OPEN state)
- Violates a coding standard
- Changes a contract interface (`schemas/`) without updating `schemas/README.md`
- Changes behavior that contradicts the product spec (`docs/dictionary-tech-spec.md`)
- Adds code with no spec backing it
- Violates any rule in the "Absolute Rules" or "What Copilot Must Not Do" sections below

You are a reviewer, not the authority. Flag and explain. The owner decides.

---

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

## Current State — As of June 2026

| Phase | Description | Status |
|---|---|---|
| Phase 0 | Bug fixes — CPT, taxonomies, form | ✅ Done |
| Phase 1 | REST API — 9 endpoints | ✅ Done |
| Phase 2 | React frontend rebuild — Browse mode | ✅ Done |
| Phase 2 UI Fix | Mockup-aligned UI pass (v3 §3.1–3.7) + backend hardening | ✅ Done (PR #64) |
| Phase 3 | Cross-tool integration tests (WordPad, S2S, RLC) | ⏸ Pending |
| Phase 4 | Games / Play tab — six game types, session management | ✅ Done (PR #59) |

**What is in `main` right now:**
- Full React app with Browse and Play tabs; desktop three-column layout, mobile bottom-nav + bottom sheet
- Phase 2 UI Fix: categories nav, example counts on rows, two-column desktop detail, Favorites CTA, Share icon (Web Share API), sidebar footer placeholder (OQ-V1), `/word-of-day` server endpoint (24h localStorage cache)
- Six games: MeaningMatch, LetterReveal, ArrangeWord, DomainFlash, CompleteSentence, ListenWrite
- IndexedDB-backed game set cache (`useGameSet`), session tracking (`useGameSession`), progress outbox (`useProgressSync`)
- Shared IndexedDB helper `idbUtils.js` (openDB, getRecord, putRecord, getAllRecords, deleteRecord)
- Spell API hardened with `$wpdb` guard and normalized response envelope (PR #61)
- Boot blockers fixed (PR #62): Autoloader constants corrected, form CSS enqueue corrected
- Backend hardening (PR #64): `/game-set` ETag + 304 support, `/progress/sync` idempotent via per-user transient ledger, `Cache-Control: public` on all GET endpoints (most use `max-age=3600`; taxonomy endpoints `/languages` use `max-age=604800`)
- WPCS + VIPWPCS toolchain with transitional PHPCS baseline and PHPStan level 5 (PR #65)

---

## Open Questions — Do Not Implement Without a Spec Decision

| ID | Status | Question | Blocking |
|---|---|---|---|
| OQ-V1 | ⏸ Open | AIWA logo asset path and tagline copy for the desktop sidebar footer | Sidebar footer final content |
| OQ-G1 | ✅ Closed | WP nonce auth for `/progress/sync` — endpoint retired per identity decision §6.2 | — |
| OQ-G3 | ⏸ Open | Letter Reveal animation — pottery vessel emoji (🏺) is a placeholder; replace with AIWA-approved cultural visual | Letter Reveal polish |
| OQ-G4 | ⏸ Open | DomainFlash "I knew it" — fires `aiwa_game_word_correct`; confirm if a separate hook is needed | myCred hook map |
| OQ-G5 | ✅ Closed | Sync destination = 3iAtlas Game Service, suite-JWT authenticated | — |
| OQ-I3 | ⏸ Open | Account-claim flow: merging guest device progress into a new suite account | Game Service intake spec |
| OQ-I4 | ⏸ Open | Tier verification: who approves teacher accounts for Lower Basic sessions | Identity Service spec |

---

## Known Intentional Gaps — Do Not Fix These Without a Spec

**`useProgressSync.syncNow()` is a no-op — do not implement until `GAME-SERVICE-INTAKE-SPEC-v1.0` lands**
OQ-G1 is closed: sync destination is the 3iAtlas Game Service (RLC Node engine), authenticated by suite JWT from `sparxstar-identity`. The IndexedDB outbox and event schema are frozen. `syncNow()` must not POST to the deprecated WordPress `/progress/sync` endpoint. Implement only when `GAME-SERVICE-INTAKE-SPEC-v1.0` is committed to `.github/instructions/`.

**`POST /progress/sync` is deprecated**
The WordPress route is frozen and scheduled for removal after the Game Service intake is live. Its `@deprecated` docblock is in the source. Never extend it. Never build a client against it.

**`// TODO: Replace with Helios token introspection`**
These appear across `src/api/` (RestApi, SpellChecker, RateLimitTrait). They are **integration stubs, not technical debt**. They correctly mark where Helios authentication attaches when the full SPARXSTAR stack is present. In standalone mode, WordPress user checks are correct degraded behaviour. Do not remove them or replace them with a different auth model without a platform decision.

---

## Absolute Rules — Never Violate

- **Never modify the `aiwa-cpt-dictionary` CPT slug.** Live data depends on it.
- **Never add community voting, correction CPTs, or AJAX voting endpoints.** Removed by design. Games replace them.
- **Never store the dictionary corpus on the client device inside this React app.** All lookups are server-side. The app sends a query; the server returns only the result. (Game set caching in IndexedDB is permitted — that is gameplay data, not the corpus. Consumer tools such as RLC may cache `/wordlist` snapshots for offline use — that is a consumer concern, not this repo's.)
- **Never hardcode language names in the React app.** Language terms come from `GET /languages`.
- **Never add a custom database table.** Use WordPress CPTs and post meta only.
- **Never use `aiwa_` or `sparxstar_` prefixes for game mechanics data.** Those prefixes are governed cultural data markers. Game scores, session state, and learned-word records must use `game_` prefix or `_spx_` for session-scoped data. This keeps the governed data perimeter clean for future DVE integration.
- **Never add DVE, Sky, Mḗh₁n̥s, Dheghom, or Sirus dependencies.** This is a standalone component.
- **Never use `wordpress/mcp-adapter`.** It does not exist on Packagist. Use the Node gateway pattern for any MCP integration.
- **License header on all PHP files: `Proprietary`, not `MIT`.**
- **Text domain: `sparxstar-3iatlas-dictionary`.**
- **All PHP files must declare `declare(strict_types=1)`.**
- **Namespace root: `Starisian\Sparxstar\IAtlas`** with subpackages `\api`, `\core`, `\frontend`, `\includes`. Example: `Starisian\Sparxstar\IAtlas\api\Sparxstar3IAtlasDictionaryRestApi`.
- **`exit(1)` not `wp_die()` in loader/bootstrap contexts.**
- **No `SELECT *` — always name columns.**
- **No `error_log()` in production paths.** Wrap in `WP_DEBUG` check.
- **No `empty()` for validation.** Use `=== null || === ''`. `empty()` treats `"0"` as empty.

---

## REST API

**Base namespace:** `sparxstar/v1/dictionary`

**Auth model — Webster Model (June 2026):**
The website is free to use; the API requires credentials. Two credential types:
- **Ephemeral page token** (`X-Page-Token` header) — minted server-side on page render, TTL 60 min, scope `browse`. Frontend auto-refreshes via `GET /page-token` on 401.
- **Consumer API key** (`X-Api-Key` header) — long-lived, SHA-256 hashed at rest, 10k req/day. Issued via `wp sparxstar-dict key generate --label=<name>`.

Per-IP rate limit (100/15 min) applies to all endpoints as an outer layer.

**Rule: every new endpoint must declare its auth row in the table before implementation.**

| Endpoint | Page token | API key | None |
|---|---|---|---|
| GET `/lookup`, `/search`, `/languages`, `/domains`, `/word-of-day`, `/game-set` | ✅ | ✅ | ❌ 401 |
| POST `/spell` | Public (rate-limited, no credentials required) | | |
| GET `/wordlist` | ❌ 403 | ✅ only | ❌ 401 |
| GET `/page-token` | Public (referer check + rate limit) | | |
| POST `/progress/sync` | **DEPRECATED** — do not build against | | |

**Auth doorway:** `src/api/auth/DictionaryAuthInterface.php`. When `sparxstar-identity` ships, its RS256 JWT verifier becomes a third implementation — no endpoint changes.

**Response envelope (all endpoints):**
```json
{ "success": true, "data": {}, "meta": { "total": 0, "page": 1, "per_page": 20 } }
```
Error responses use the WordPress REST API standard: `{ "code": "...", "message": "...", "data": { "status": 4xx } }`. 429 responses additionally include a `Retry-After: 86400` header.

**Endpoints:**

| Method | Path | Purpose |
|---|---|---|
| GET | `/lookup` | Full entry by slug or UUID |
| GET | `/search` | Search by query string |
| GET | `/wordlist` | Lightweight list for offline caching — consumer API key only |
| GET | `/languages` | Language taxonomy terms with counts |
| GET | `/domains` | Domain taxonomy terms with counts |
| GET | `/game-set` | Curated word set for game use |
| GET | `/word-of-day` | Single deterministic daily entry |
| GET | `/page-token` | Mint fresh ephemeral token for the React app |
| POST | `/spell` | Spell-check against dictionary entries |
| POST | `/progress/sync` | **DEPRECATED** — frozen, removal pending Game Service |

**`/game-set` rules:**
- Parameters: `lang_source` (required), `domain` (optional), `limit` (default 20, max 50), `include_audio` (bool)
- Exclude entries missing headword, translation_en, or IPA — all three required for games
- `ORDER BY RAND()` is acceptable temporarily; replace before large production datasets

**Caching headers:**
- All GET responses: `Cache-Control: public` with a TTL — most endpoints use `max-age=3600`; `/languages` uses `max-age=604800` (taxonomy terms change rarely)
- `/wordlist` and `/game-set`: ETag support for conditional requests (304 responses)
- `/word-of-day`: include `date` field (ISO 8601) so clients detect staleness

---

## Games Architecture (Phase 4 — Merged)

**Files added in Phase 4:**
```
src/js/hooks/
  idbUtils.js            — shared IndexedDB helper (openDB, getRecord, putRecord, getAllRecords, deleteRecord)
  useGameSet.js          — /game-set fetch + 3-day IndexedDB TTL cache; cache key includes includeAudio flag
  useGameSession.js      — session state, learned-word tracking, sessionRef pattern
  useProgressSync.js     — IndexedDB outbox for progress events (syncNow is no-op — OQ-G1)
src/js/games/
  GameShell.jsx          — top-level game orchestrator, phase state machine
  AccessoryBar.jsx       — special character input bar (ŋ ɓ ɗ ñ ɲ ʔ á é í ó ú) — always present for typed input
  SessionComplete.jsx    — end-of-session summary
  games/
    MeaningMatch.jsx
    LetterReveal.jsx
    ArrangeWord.jsx
    DomainFlash.jsx
    CompleteSentence.jsx
    ListenWrite.jsx
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
do_action('aiwa_game_listen_write_correct', $user_id, $word_uuid);            // +10 XP
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
- `aiwa_translation_english` / `aiwa_translation_french`
- `aiwa_ipa_pronunciation` — IPA pronunciation
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

**Suite identity (June 2026):**
All 3iAtlas products share one identity system — the `sparxstar-identity` service (RS256 JWT, Cloudflare Workers). WordPress authentication is prohibited for all user-facing features. The Dictionary React app uses an ephemeral page token (HMAC-SHA256, server-minted) for browse access and will use suite JWTs for authenticated play when the Identity Service is live. Do not add `wp_nonce` or `is_user_logged_in()` to any new user-facing endpoint.


**Eshu migration awareness:**
The platform direction moves PHP processing pipelines toward Eshu MCP and ACF/CPT toward Dheghom vault storage. New capabilities that need persistent storage should be designed lightly. Do not over-invest in WordPress/ACF for new storage layers if they will migrate.

**Export contract:**
The dependency direction is: DVE → export → 3iAtlas dictionary → RLC. The dictionary is a DVE export consumer, not a DVE runtime component. No live DVE connection at runtime.

---

## Coding Standards

- **WordPress Coding Standards + VIPWPCS** — not PSR-12. The code uses WP style: `array()`, spaced parens, WP-prefixed function calls.
- `declare(strict_types=1)` at the top of every PHP file
- Typed parameters and return types on all methods
- No raw SQL — use `$wpdb->prepare()` if ever needed
- No `die()` — use `exit(1)` with a message in loaders, `wp_send_json_error()` in AJAX
- All user input sanitized with `sanitize_text_field()` or equivalent
- All output escaped with `esc_html()`, `esc_attr()`, `esc_url()` as appropriate
- Rate limiting via WordPress transients — never external infrastructure
- PHP 8.2 minimum, WordPress 6.8 minimum (plugin header `Requires at least: 6.8`)

### PHP Toolchain

- **PHPCS** (`composer lint:php`): WPCS + VIPWPCS standard. Pre-existing legacy violations are demoted to warnings in `phpcs.xml.dist` (transitional baseline — warnings do not fail CI). New code must produce zero errors. Auto-fix with `composer fix:php` (PHPCBF).
- **Strict gate** (`composer lint:php:strict` via `.phpcs-strict.xml.dist`): full WPCS + VIPWPCS with no demotions, run on **newly added** `.php` files only. New files must pass fully.
- **PHPStan**: level 5 (`phpstan.neon.dist`). Pre-existing findings captured in `phpstan-baseline.neon`. New code must pass clean; shrink the baseline as files are fixed.
- Toolchain pinned to **PHPCS 3.x** (do not jump to 4.x until WPCS declares 4.x support) and **PHPStan 1.x**. Do not upgrade these without a deliberate toolchain decision.

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
