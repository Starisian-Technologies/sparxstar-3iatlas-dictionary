# Copilot Instructions — sparxstar-3iatlas-dictionary

## Reference repositories (read via MCP)

When accessible and relevant to the PR under review, read these repos for
governance context. Don't block a review on an unreachable repo or fetch
one that has no bearing on the change (e.g. a docs-only typo fix doesn't
need an ADR registry read):

- ADR Registry: Starisian-Technologies/sparxstar-architecture-governance-registry
- Product Specs: Starisian-Technologies/sparxstar-product-specification-registry
- Coding Standards: Starisian-Technologies/starisian-technologies-coding-standards
- Enforcement Workflows: Starisian-Technologies/sparxstar-code-conformance
- Contracts: Starisian-Technologies/sparxstar-contracts-registry
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

| Phase          | Description                                              | Status           |
| -------------- | -------------------------------------------------------- | ---------------- |
| Phase 0        | Bug fixes — CPT, taxonomies, form                        | ✅ Done          |
| Phase 1        | REST API — 9 endpoints                                   | ✅ Done          |
| Phase 2        | React frontend rebuild — Browse mode                     | ✅ Done          |
| Phase 2 UI Fix | Mockup-aligned UI pass (v3 §3.1–3.7) + backend hardening | ✅ Done (PR #64) |
| Phase 3        | Cross-tool integration tests (WordPad, S2S, RLC)         | ⏸ Pending        |
| Phase 4        | Games / Play tab — six game types, session management    | ✅ Done (PR #59) |

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

| ID        | Status                               | Question                                                                                                                                                                                                                                                                                                                                                                     | Blocking                     |
| --------- | ------------------------------------ | ---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- | ---------------------------- |
| OQ-V1     | ⏸ Open                               | AIWA logo asset path and tagline copy for the desktop sidebar footer                                                                                                                                                                                                                                                                                                         | Sidebar footer final content |
| ~~OQ-G1~~ | Retired label — corrected 2026-07-08 | This ID was redefined and reused for two different questions across this repo's own document history (original: Helios-token-source for `/progress/sync`; later: WP nonce auth, closed on a fabricated citation). Do not cite "OQ-G1" going forward — see `docs/dictionary-tech-spec.md` § "OQ-G1 — retired as a citation" for the disambiguated facts, tracked as `OQ-013`. | —                            |
| OQ-G3     | ⏸ Open                               | Letter Reveal animation — pottery vessel emoji (🏺) is a placeholder; replace with AIWA-approved cultural visual                                                                                                                                                                                                                                                             | Letter Reveal polish         |
| OQ-G4     | ⏸ Open                               | DomainFlash "I knew it" — fires `aiwa_game_word_correct`; confirm if a separate hook is needed                                                                                                                                                                                                                                                                               | myCred hook map              |
| OQ-G5     | ✅ Closed                            | Sync destination = 3iAtlas Game Service, suite-JWT authenticated                                                                                                                                                                                                                                                                                                             | —                            |
| OQ-I3     | ⏸ Open                               | Account-claim flow: merging guest device progress into a new suite account                                                                                                                                                                                                                                                                                                   | Game Service intake spec     |
| OQ-I4     | ⏸ Open                               | Tier verification: who approves teacher accounts for Lower Basic sessions                                                                                                                                                                                                                                                                                                    | Identity Service spec        |

---

## Known Intentional Gaps — Do Not Fix These Without a Spec

**`useProgressSync.syncNow()` is a no-op — do not implement until `GAME-SERVICE-INTAKE-SPEC-v1.0` lands**
Sync destination is decided: the 3iAtlas Game Service (RLC Node engine), authenticated by suite JWT from `sparxstar-identity`. The IndexedDB outbox behavior is stable and carries over unchanged. **Correction (2026-07-08):** this section previously said "OQ-G1 is closed" and "the event schema [is] frozen" — both were inaccurate. "OQ-G1" is a retired/ambiguous citation (see Open Questions above); the anonymous/guest client token-source question it originally named is still open (`OQ-013`). The event schema is not frozen as a richer contract — the verified, currently-shipped wire shape is `{ type, word_uuid?, game?, domain?, ts }` (see `docs/dictionary-tech-spec.md` § "Game integration"). `syncNow()` must not POST to the deprecated WordPress `/progress/sync` endpoint. Implement only when `GAME-SERVICE-INTAKE-SPEC-v1.0` is committed to `.github/instructions/`.

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

**Full endpoint table, auth-row table, and caching rules (canonical): `docs/dictionary-tech-spec.md` § "API surface".**
This section previously duplicated that content in full; trimmed 2026-07-08 to avoid drift
between two copies. Rules to remember without opening the other file:

- **Auth model (Webster Model, June 2026):** the website is free to use; the API requires
  credentials — ephemeral page token (`X-Page-Token`, 60 min TTL, `browse` scope) or consumer
  API key (`X-Api-Key`, long-lived, SHA-256 hashed at rest, 10k req/day). Per-IP rate limit
  (100/15 min) applies to all endpoints as an outer layer, **except `GET /pronounce`
  (`Sparxstar3IAtlasDictionaryTts`)** — it does not use `Sparxstar3IAtlasRateLimitTrait` and has
  no rate limit of its own today (see `docs/dictionary-tech-spec.md` OQ-010 — a real
  resource-exhaustion gap, not just a documentation inconsistency). **Every new endpoint must
  declare its auth row in the tech spec's auth table before implementation.**
- `POST /progress/sync` is **DEPRECATED and frozen** — do not build against it.
- `GET /wordlist` is consumer-API-key only.
- **Auth doorway:** `src/api/auth/DictionaryAuthInterface.php`. When `sparxstar-identity`
  ships, its RS256 JWT verifier becomes a third implementation — no endpoint changes.
- **`/game-set` rules:** exclude entries missing headword, translation_en, or IPA — all three
  required for games. `ORDER BY RAND()` is acceptable temporarily; replace before large
  production datasets.

---

## Games Architecture (Phase 4 — merged here, then extracted)

**The game source is not in this repo.** Phase 4 landed it under
`src/js/games/` and `src/js/hooks/`, but the game layer was extracted to
`Starisian-Technologies/sparxstar-3iatlas-dictionary-games` (npm `sparxstar-rlc-games`, UMD global `RlcGames`) and the copy here was
deleted rather than maintained in parallel. The Play tab came out of
`src/js/app.jsx` with it.

Do not re-add game source, hooks, or the IndexedDB layer here. This repo's
side of the boundary is the REST API the games app calls — `/game-set`,
`/domains`, `/page-token`, `/pronounce` — plus the `aiwa_game_*`
`do_action()` re-emitters. The game design mandate below still governs the
games; it is now enforced in the games repo.

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

**Full hook map (canonical): `docs/dictionary-tech-spec.md` § "MyCred hook map".**
Fired server-side from `handle_progress_sync()` on `/progress/sync` events. myCred listens;
when absent, hooks are no-ops. Seven hooks exist: `aiwa_game_word_correct`,
`aiwa_game_listen_write_correct`, `aiwa_game_session_complete`, `aiwa_game_domain_mastered`,
`aiwa_game_streak_3`, `aiwa_game_new_word_practiced`, `aiwa_game_return_visit`.

---

## Data Model

**CPT:** `aiwa-cpt-dictionary` — never change this slug.

**Full ACF field/taxonomy reference (canonical): `docs/dictionary-tech-spec.md` § "Data model".**

**SCF discrepancy — do not sync:**
`aiwa_sentence_ipa` (key: `field_696e6b18c17f4`) is registered programmatically in `src/includes/Sparxstar3IAtlasPostTypes.php` as a sub-field of the example sentences repeater. It is intentionally absent from the SCF JSON. `Sparxstar3IAtlasPostTypes.php` is authoritative. Do not add it to the SCF JSON. Do not remove it from `Sparxstar3IAtlasPostTypes.php`.

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
- Implement `syncNow()` without a spec decision (see OQ-002 in `docs/dictionary-tech-spec.md` § Open Questions, blocked on `GAME-SERVICE-INTAKE-SPEC-v1.0`; OQ-013 is the related guest token-source sub-question)
- Remove or replace Helios TODO stubs with a different auth model without a platform decision
