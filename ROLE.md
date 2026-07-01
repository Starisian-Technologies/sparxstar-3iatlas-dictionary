# 3iAtlas Dictionary — Role and Boundary

## Owns

- Authoritative storage of approved dictionary entries (WordPress CPT `aiwa-cpt-dictionary` + ACF/SCF fields)
- The public REST API at `sparxstar/v1/dictionary` (lookup, search, wordlist, languages, domains, game-set, word-of-day, pronounce, page-token, spell, and the deprecated progress/sync)
- Ephemeral page-token issuance and consumer API key validation for that API (Webster Model auth)
- The public-facing Browse experience (dictionary lookup UI) and Play experience (six language games) as a React PWA
- Game session state, learned-word tracking, and progress-event capture. Today this lives entirely client-side in IndexedDB (`aiwa-games-db`, stores `game-sets`/`game-sessions`/`progress-outbox`/`learned-words` — see `src/js/hooks/idbUtils.js`), with no prefix convention applied since it isn't WordPress user meta. If/when this data is persisted server-side as WP user meta, it must use the `game_` (persistent) / `_spx_` (session-scoped) prefix rule from `.github/copilot-instructions.md`, never `aiwa_`/`sparxstar_`.
- myCred gamification hook firing (`aiwa_game_*` actions) for downstream XP/Gold processing

## Does not own

- **New word intake, review, or normalization** — owned by DVE (Dictionary Validation Engine); see `.github/instructions/3IATLAS-DICTIONARY-ROLE-AND-PIPELINE-SPEC-v1.0.md`. This repo has no community submission or correction-proposal pathway. (A legacy WP-authenticated frontend form, `Sparxstar3IAtlasDictionaryForm`, can still add/edit `aiwa-cpt-dictionary` entries for logged-in users — see Open items. New work must not extend it or build new intake paths against it.)
- **Linguistic editing of locked fields** (headword, language, definitions, pronunciation, siblings, speaker community tags) — corrections originate upstream in DVE as a new Approved Entry Package and arrive here only via WP-CLI import.
- **Identity and authentication for suite users** — owned by `sparxstar-identity` (RS256 JWT, Cloudflare Workers). New user-facing endpoints must not use `wp_nonce` or `is_user_logged_in()` (the legacy frontend form predates this rule — see Open items).
- **Cross-tool progress persistence / Game Service intake** — owned by the 3iAtlas Game Service (RLC Node engine). `useProgressSync.syncNow()` is an intentional no-op pending `GAME-SERVICE-INTAKE-SPEC-v1.0`.
- **Consumer tools' own caching or offline behavior** — WordPad, RLC, Sound to Symbol, and Games consume this repo's REST API; how they cache or present that data is their concern, not this repo's.
- **DVE runtime dependencies** (Sirus, Helios, Mḗh₁n̥s, Dheghom) — this repo operates in standalone mode and does not connect to any of these at runtime.

## Contracts produced

- JSON/YAML schemas under `schemas/`, synced to the platform contracts repo (`Starisian-Technologies/sparxstar-platform-contracts`, path `Contracts/IAMC/Dictionary/`) via `.github/workflows/sync-contracts.yml` on every push to `main` that touches `schemas/`.
- The REST API surface itself (see `docs/dictionary-tech-spec.md` § API surface) is the de facto contract consumed by every downstream 3iAtlas tool.

## Consumed by

- WordPad
- RLC (Reading/Listening/Comprehension tool)
- Sound to Symbol
- Any future 3iAtlas tool that needs dictionary lookups, search, or game word sets

## Open items

- `Sparxstar3IAtlasDictionaryForm` (`src/frontend/Sparxstar3IAtlasDictionaryForm.php`, instantiated for logged-in users in `src/core/Sparxstar3IAtlasDictionary.php`) is a WP-authenticated frontend form that adds/edits `aiwa-cpt-dictionary` entries directly — this predates and contradicts the "linguistically read-only, DVE-only intake" boundary above. It needs an explicit platform decision: deprecate it, or carve out and document its exception.
