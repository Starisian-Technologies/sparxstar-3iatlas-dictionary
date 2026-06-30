# 3iAtlas Dictionary — Role and Boundary

## Owns

- Authoritative storage of approved dictionary entries (WordPress CPT `aiwa-cpt-dictionary` + ACF/SCF fields)
- The public REST API at `sparxstar/v1/dictionary` (lookup, search, wordlist, languages, domains, game-set, word-of-day, page-token, spell)
- Ephemeral page-token issuance and consumer API key validation for that API (Webster Model auth)
- The public-facing Browse experience (dictionary lookup UI) and Play experience (six language games) as a React PWA
- Game session state, learned-word tracking, and progress-event capture (IndexedDB outbox), scoped under `game_` / `_spx_` meta prefixes
- myCred gamification hook firing (`aiwa_game_*` actions) for downstream XP/Gold processing

## Does not own

- **Word intake, review, or normalization** — owned by DVE (Dictionary Validation Engine). This repo never accepts community submissions or correction proposals; see `.github/instructions/3IATLAS-DICTIONARY-ROLE-AND-PIPELINE-SPEC-v1.0.md`.
- **Linguistic editing of locked fields** (headword, language, definitions, pronunciation, siblings, speaker community tags) — corrections originate upstream in DVE as a new Approved Entry Package and arrive here only via WP-CLI import.
- **Identity and authentication for suite users** — owned by `sparxstar-identity` (RS256 JWT, Cloudflare Workers). This repo never uses `wp_nonce` or `is_user_logged_in()` for user-facing features.
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
