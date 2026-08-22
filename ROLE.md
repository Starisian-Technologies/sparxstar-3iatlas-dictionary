# 3iAtlas Dictionary — Role and Boundary

## Owns

- Authoritative storage of approved dictionary entries (WordPress CPT `aiwa-cpt-dictionary` + ACF/SCF fields)
- The public REST API at `sparxstar/v1/dictionary` (lookup, search, wordlist, languages, domains, game-set, word-of-day, pronounce, page-token, spell, and the deprecated progress/sync)
- Ephemeral page-token issuance and consumer API key validation for that API (Webster Model auth)
- The public-facing Browse experience (dictionary lookup UI) as a React PWA
- The `aiwa_`/`sparxstar_` naming prohibition for game mechanics data, as it binds anything **this** repo persists. The absolute rule from `.github/copilot-instructions.md` — never use `aiwa_`/`sparxstar_` for game mechanics data — governs any future server-side persistence here; the `game_` (persistent) / `_spx_` (session-scoped) token format is the WordPress-user-meta convention that applies to it. The client-side game state that rule also covers is no longer ours — see Does not own.
- myCred gamification hook firing (`aiwa_game_*` actions) for downstream XP/Gold processing

## Does not own

- **The game UI and all client-side game state.** The six games, the game shell,
  session/progress hooks and the IndexedDB layer (`aiwa-games-db`, stores
  `game-sets`/`game-sessions`/`progress-outbox`/`learned-words`) live in
  `Starisian-Technologies/sparxstar-3iatlas-dictionary-games` (npm `sparxstar-rlc-games`, UMD global `RlcGames`), which is canonical.
  This repo carried a second, older copy under `src/js/games/` and
  `src/js/hooks/`; it was deleted rather than kept in sync, along with the Play
  tab in `src/js/app.jsx`. Do not re-add game source here. What this repo does
  own is the server side that app consumes — the `/game-set`, `/domains`,
  `/page-token` and `/pronounce` routes, and the `aiwa_game_*` hooks below.

- **New word intake, review, or normalization** — owned by DVE (Dictionary Validation Engine); see `.github/instructions/3IATLAS-DICTIONARY-ROLE-AND-PIPELINE-SPEC-v1.0.md`. By design there is no community submission or correction-proposal pathway here — the one exception is `Sparxstar3IAtlasDictionaryForm`, a legacy WP-authenticated frontend form that can still add/edit `aiwa-cpt-dictionary` entries for logged-in users (see Open items for the pending decision to deprecate or formally scope it). New work must not extend it or build new intake paths against it.
- **Linguistic editing of locked fields** (headword, language, definitions, pronunciation, siblings, speaker community tags) — intended to be locked post-import so corrections originate upstream in DVE as a new Approved Entry Package, arriving here only via WP-CLI import. This lock is not currently enforced by any ACF/WordPress admin-UI restriction (see Open items) — it's a process/policy boundary today, not a technical one.
- **Identity and authentication for suite users** — owned by `sparxstar-identity` (RS256 JWT, Cloudflare Workers). New user-facing endpoints must not use `wp_nonce` or `is_user_logged_in()` (the legacy frontend form predates this rule — see Open items).
- **Cross-tool progress persistence / Game Service intake** — owned by the 3iAtlas Game Service (RLC Node engine). `useProgressSync.syncNow()` is an intentional no-op pending `GAME-SERVICE-INTAKE-SPEC-v1.0`.
- **Consumer tools' own caching or offline behavior** — WordPad, RLC, Sound to Symbol, and Games consume this repo's REST API; how they cache or present that data is their concern, not this repo's.
- **DVE runtime dependencies** (Sirus, Helios, Mḗh₁n̥s, Dheghom) — this repo operates in standalone mode and does not connect to any of these at runtime.

## Contracts produced

- `schemas/dictionary-openapi.yaml` — OpenAPI 3.0 contract for the full public REST API at `sparxstar/v1/dictionary`, including both auth schemes (`X-Page-Token`, `X-Api-Key`). This is the canonical machine-readable contract for every downstream 3iAtlas tool.
- Synced to the platform contracts repo (`Starisian-Technologies/sparxstar-contracts-registry`, path `Contracts/dictionary/`) via `.github/workflows/sync-contracts.yml` on every push to `main` that touches `schemas/`.

## Consumed by

- WordPad
- RLC (Reading/Listening/Comprehension tool)
- Sound to Symbol
- Any future 3iAtlas tool that needs dictionary lookups, search, or game word sets

## Open items

- `Sparxstar3IAtlasDictionaryForm` (`src/frontend/Sparxstar3IAtlasDictionaryForm.php`, instantiated for logged-in users in `src/core/Sparxstar3IAtlasDictionary.php`) is a WP-authenticated frontend form that adds/edits `aiwa-cpt-dictionary` entries directly — this predates and contradicts the "linguistically read-only, DVE-only intake" boundary above. It needs an explicit platform decision: deprecate it, or carve out and document its exception.
