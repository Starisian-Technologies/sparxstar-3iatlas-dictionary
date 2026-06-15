# Migration Plan: `aiwa_*` → `sparx_dict_*` (de-brand the storage layer)

**Status:** Proposed (planning artifact — this document specifies the migration; it performs no rename itself)
**Implements:** ADR-017 (SPARXSTAR Naming Convention — supersedes the ADR-014 "SPX Prefix Standard" draft; ADR-017 owner-ratification pending) · Owner directive 2026-06-13
**Owner approval:** Field-naming decision approved 2026-06-13 — *"new fields are `sparx_dict_*`; existing `aiwa_*` fields get a migration plan to `sparx_dict_*` as a separate PR. Don't build new work on top of client-branded columns."*
**Repo-local ADR number:** pending — see Open Question 1 (ADR-015 repo prefix not yet assigned).

---

## 1. Why

`aiwa_*` storage identifiers are **client-branded columns in a platform tool**. AIWA's Mandinka entries and (e.g.) Agua Caliente's Cahuilla entries must store in the *same* schema with the *same* field names. Every other layer was de-branded this week; the storage layer is the last and most expensive, so it is done early — before more data accumulates against the wrong names.

**Key scoping fact (lowers blast radius):** the *external contract is already clean.* REST output keys (`definition`, `translation_en`, …) and the DVE import-package keys (`headword`, `definition`, …) are unbranded. `aiwa_*` exists **only at the WordPress storage layer**. For the linguistic fields, API consumers and the React browse/games clients are therefore **unaffected** — this is an internal rename, not a wire-format break.

The one exception is the game-signal namespace (§4), which *is* on the wire and *is* cross-system.

---

## 2. Targets are tiered (ADR-017) — not a flat `sparx_dict_*`

| Artifact type | Tier | Target pattern | Example |
|---|---|---|---|
| Post meta / ACF field names | medium | `sparx_dict_*` | `aiwa_extract` → `sparx_dict_extract` |
| ACF field group + field keys | medium | `group_sparx_dict_*` / `field_*` | `group_aiwa_dictionary_main` → `group_sparx_dict_main` |
| CPT slug | **LOCKED — do not rename** | `aiwa-cpt-dictionary` stays | grandfathered by repo rule; see Bucket B |
| Taxonomy slug | medium | `sparx_dict_*` | `aiwa_domain` → `sparx_dict_domain` |
| `wp_options` names | medium | `sparx_dict_*` | `aiwa_dict_api_keys` → `sparx_dict_api_keys` |
| Action/filter hooks | full | `sparxstar_dict_*` | `aiwa_game_word_correct` → `sparxstar_dict_game_word_correct` |
| CSS classes | css | `spx-dict-*` | `spx-dictionary-root` → `spx-dict-root` |
| PHP service layer (classes/interfaces/fns) | — | **governed by AI Manifest Protocol v5.0** | closed-vocab composition `SPX\{Auth}\{Sys}\{Prod}\{Domain}\{Entity}\{Action}{Suffix}`; `allowed_class_suffixes` = `Service`/`Interface` only |

> PHP class/interface/function names are **not** decided by this plan — they're set deterministically by `spx-vocab.json` + the Protocol validator. New code conforms from creation; existing grandfathered until touched. This affects the migration/importer command class names — see Open Question 7.

---

## 3. Inventory by bucket

Grounded in a full `aiwa_` sweep of the repo (2026-06-13).

### Bucket A — Linguistic ACF fields / post meta (the core "disease")
Storage keys in `wp_postmeta`. Each ACF value is **two rows**: the value row (`meta_key = aiwa_extract`) and the reference row (`meta_key = _aiwa_extract`, value = field key). **Both rows migrate.**

Representative mapping (full list maintained in the migration command):

| Current | Target |
|---|---|
| `aiwa_entry_uuid` | `sparx_dict_uuid` |
| `aiwa_extract` | `sparx_dict_extract` |
| `aiwa_translation` (parent group) | `sparx_dict_translation` |
| `aiwa_translation_english` | `sparx_dict_translation_en` |
| `aiwa_translation_french` | `sparx_dict_translation_fr` |
| `aiwa_ipa_pronunciation` | `sparx_dict_ipa` |
| `aiwa_phonetic` | `sparx_dict_phonetic` |
| `aiwa_audio_file` | `sparx_dict_audio` |
| `aiwa_origin` | `sparx_dict_origin` |
| `aiwa_word_photo` | `sparx_dict_photo` |
| `aiwa_example_sentences` (repeater) | `sparx_dict_examples` |
| `aiwa_sentence_{example,ipa,phonetic,english,french}` | `sparx_dict_sentence_{example,ipa,phonetic,en,fr}` |
| `aiwa_synonyms` / `aiwa_antonyms` | `sparx_dict_synonyms` / `sparx_dict_antonyms` |
| `aiwa_part_of_speech` (ACF field) | `sparx_dict_pos` |
| `aiwa_search_string_english` / `_french` | `sparx_dict_search_en` / `_fr` |
| `aiwa_rating_average` / `aiwa_rating` | `sparx_dict_rating_avg` / `sparx_dict_rating` |
| `aiwa_qc_status` / `aiwa_qc_notes` | `sparx_dict_qc_status` / `sparx_dict_qc_notes` |
| `aiwa_phonetic_variants` (relationship) | `sparx_dict_phonetic_variants` |
| `aiwa_phonetic_var_root` (text) | `sparx_dict_phonetic_var_root` |
| `aiwa_edited_from` | `sparx_dict_edited_from` |

> `_en`/`_fr` over `_english`/`_french` aligns with the wire keys (`translation_en`) and is a naming decision to confirm. ACF field **keys** (`aiwa_field_1`, `aiwa_field_qc_1`, …) are opaque internal identifiers; renaming them is optional cleanup (Open Question 5).

**Source-of-truth files:** `src/includes/Sparxstar3IAtlasPostTypes.php` (ACF registration) **and** `acf-json/sparxstar-dictionary-scf.json` (ACF local-JSON sync). Both must change together or ACF will resync the old names. **Known exception:** `aiwa_sentence_ipa` is registered in `PostTypes.php` but **intentionally absent** from `scf.json` (the other four `aiwa_sentence_*` subfields are present). PR-2 must preserve that asymmetry — rename it in `PostTypes.php` only and **not** "correct" the JSON by adding it.

**Read/write call sites:** `src/api/Sparxstar3IAtlasDictionaryRestApi.php` (all `get_field`/`get_post_meta`/`meta_query`), `src/frontend/Sparxstar3IAtlasDictionaryForm.php`, `src/core/Sparxstar3IAtlasDictionaryCore.php`.

### Bucket B — CPT slug: **LOCKED — do NOT rename**
The CPT slug stays `aiwa-cpt-dictionary`. This is a deliberate **exception to the ADR-017 `sparx_dict_*` default**, because the repo has an explicit overriding rule: `AGENTS.md` and `AIWA-Dictionary-Direction-v3.md` state *"Never modify the `aiwa-cpt-dictionary` CPT slug. Live data depends on it. Changing it destroys existing entries."* So **no `wp_posts.post_type` rewrite.** A rename would require a *future* ADR that explicitly overrides that rule and ships a full permalink + data-migration plan (Open Question 2).

**What PR-2 *does* fix here (a bug, not a rename):** the registered slug is hyphenated (`register_post_type('aiwa-cpt-dictionary')`, `Sparxstar3IAtlasPostTypes.php:941`), but `Sparxstar3IAtlasDictionaryCore.php` hooks `save_post_aiwa_cpt_dictionary` and guards `get_post_type() !== 'aiwa_cpt_dictionary'` with *underscores* — these never match the hyphenated post type and are latent no-ops. PR-2 corrects them to target the **existing** slug (`save_post_aiwa-cpt-dictionary`, `=== 'aiwa-cpt-dictionary'`). No data is rewritten.

### Bucket C — Taxonomy slug
Client-branded taxonomies registered in `Sparxstar3IAtlasPostTypes.php`:

| Current | Target |
|---|---|
| `aiwa_domain` | `sparx_dict_domain` |
| `aiwa-alpha-letter` (hyphenated slug) | `sparx_dict_alpha_letter` |

Data migration: `wp_term_taxonomy.taxonomy` for each. Re-register under the new slug. *(Related: `starmus_tax_language`, `starmus_part_of_speech`, `starmus_tax_dialect` are also non-platform prefixes — Open Question 3.)*

### Bucket D — `wp_options`
`aiwa_dict_api_keys`, `aiwa_dict_cors_origins`, `aiwa_dict_ptquota_*`, `aiwa_dict_keyquota_*` → `sparx_dict_*`. Low risk (operational). Copy value to new option, delete old.

### Bucket E — Game signals (CROSS-SYSTEM — separate, coordinated PR)
`aiwa_game_{word_correct,listen_write_correct,session_complete,domain_mastered,streak_3,new_word_practiced,return_visit}` → `sparxstar_dict_game_*` (hook tier).
- **Wire contract:** the React games send these as `addEvent({ type: 'aiwa_game_*' })` (`src/js/games/GameShell.jsx`, `src/js/hooks/useProgressSync.js`, **and** the parallel package `sparxstar-3iatlas-dictionary-games/`). The REST endpoint switches on the `type` string and re-emits via `do_action()` (`…RestApi.php:1083+`).
- **Consumers:** whatever `add_action()`s these — the Rewards/MyCred listener (separate system).
- Renaming requires a coordinated change across: this REST endpoint, both frontend copies, and the Rewards listener. **Do not rename unilaterally.** Owner/Rewards coordination required (Open Question 4).

### Bucket F — Derived / regenerated
- `assets/**/*.min.{js,css}` + `*.map` — build output; regenerated, not hand-edited. Rebuild after source changes.
- CSS source `src/css/…style.css`: `aiwa`/`sparxstar-dictionary-*` classes → `spx-dict-*` on next touch.
- Specs/docs under `.github/instructions/`, `README.md`, `AGENTS.md` reference `aiwa_*` field names — update for consistency (non-breaking). The APPROVED-ENTRY / ROLE-AND-PIPELINE specs name fields as `aiwa_*`; reconcile to `sparx_dict_*` so the importer is built against a correct contract.

---

## 4. Migration mechanics

A dedicated, **idempotent** WP-CLI command (new PHP class — its name set by the AI Manifest Protocol's closed vocabulary, not asserted here; see Open Question 7) exposing `wp sparx-dict migrate-fields`:

- `--dry-run` (default): report row counts per mapping, write nothing.
- `--run`: execute inside a transaction where the storage engine allows.
- For each `old → new` meta key: `UPDATE {$wpdb->postmeta} SET meta_key=<new> WHERE meta_key=<old>` **and** the `_<old>` → `_<new>` reference row, scoped to CPT posts.
- CPT: **no `wp_posts.post_type` rewrite** — the `aiwa-cpt-dictionary` slug is locked (Bucket B). PR-2 only fixes the underscore/hyphen `Core.php` hook + guard to target the existing slug.
- Taxonomy (per client-branded slug): `UPDATE {$wpdb->term_taxonomy} SET taxonomy='sparx_dict_domain' WHERE taxonomy='aiwa_domain'` and `… SET taxonomy='sparx_dict_alpha_letter' WHERE taxonomy='aiwa-alpha-letter'`.
- Options: copy → new `option_name`, delete old.
- Idempotent: re-running detects already-migrated keys and no-ops.
- Reversible: ship the inverse mapping for rollback.

**Atomicity rule:** the **code rename (Bucket A–D) and the data migration command ship in the same PR**. A code rename without the data migration orphans every existing row; a data migration without the code rename breaks all reads. They are one change.

---

## 5. PR breakdown & sequencing

| PR | Contents | Risk | Depends on |
|---|---|---|---|
| **PR-1 (this)** | This plan. | none | — |
| **PR-2** | Buckets A, C, D: ACF reg + `acf-json` + all PHP read/write sites + taxonomy + options, **plus** the idempotent WP-CLI data-migration command. Includes Bucket B's `Core.php` underscore/hyphen hook bug-fix (no slug rename). Atomic. | medium (internal) | PR-1 review/sign-off + Open Q 1–3 |
| **PR-3** | Bucket E: game hooks + event-`type` strings, coordinated with Rewards listener + both frontend copies. | high (cross-system, wire) | Rewards/Open Q 4 |
| **PR-4** | Bucket F: CSS `spx-dict-*`, rebuild assets, doc/spec reconciliation. | low | PR-2 |

**Importer work** (held) is built **directly on `sparx_dict_*`** from creation and lands *after* PR-2 (or against the new names concurrently). It never touches `aiwa_*`.

---

## 6. Rollout & safety

- **Full DB backup** before `--run`. This rewrites `wp_postmeta`, `wp_term_taxonomy`, `wp_options` (the CPT slug in `wp_posts` is **not** changed — Bucket B).
- Run `--dry-run` first; review counts against expected entry volume.
- Maintenance window: brief, since reads break the instant code lands without data, and vice-versa — deploy code + run migration together.
- After taxonomy slug changes: re-register taxonomies + `flush_rewrite_rules()` only if a taxonomy's rewrite config changed; verify ACF field-group sync (`acf-json`) loads cleanly. Entry permalinks are unaffected (CPT slug unchanged).
- Re-run any search index / cached REST responses (cache invalidation) post-migration.
- Because the REST/import **field** contract is unchanged, no consumer coordination is needed for Buckets A–D. Bucket E is the only consumer-facing change and is gated behind PR-3.

---

## 7. Open questions (need a ruling before PR-2)

1. **Repo-local ADR prefix** (ADR-015): this repo has no assigned prefix yet (e.g. `DICT-ADR-` / `3IA-ADR-`). Needed to file this plan as a formal repo-local ADR rather than a `docs/migrations/` artifact.
2. **CPT slug — already locked; can/should ADR-017 ever override?** The repo rule (`AGENTS.md`, `AIWA-Dictionary-Direction-v3.md`) is explicit: never change `aiwa-cpt-dictionary` (live data + permalinks depend on it). This plan therefore treats it as **grandfathered/locked** — that is the default, not an open choice. The only real question is whether a *future ADR* should deliberately override that rule with a full permalink + `wp_posts.post_type` data-migration plan. Absent such an ADR, the slug stays as-is.
3. **`starmus_*` taxonomies** (`starmus_tax_language`, `starmus_part_of_speech`, `starmus_tax_dialect`): also non-`sparx` prefixes (Starmus = audio-acquisition product). De-brand to `sparx_dict_language` / `sparx_dict_pos` / `sparx_dict_dialect`, or are these **shared cross-suite taxonomies** intentionally owned by Starmus? Not assumed — needs a ruling.
4. **Game-signal rename ownership** (Bucket E): coordination with the Rewards/MyCred listener and the `sparxstar-3iatlas-dictionary-games` frontend. Who owns that cross-repo change, and is `sparxstar_dict_game_*` the agreed hook name?
5. **ACF field-key renames** (`aiwa_field_N` → `field_*`): include in PR-2 or leave as opaque internal identifiers?
6. **`_en`/`_fr` vs `_english`/`_french`** suffix convention for the translation/search/sentence fields — confirm.
7. **WP-CLI command class naming under the Manifest Protocol** (ADR-017). `allowed_class_suffixes` is `Service`/`Interface` only — there is no `Command` suffix. The migration command and the (held) importer command classes need conformant coordinates from `spx-vocab.json`; their PHP class names can't be finalized without it. Resolve whether WP-CLI commands fall under the Protocol's service-layer governance and, if so, their `{Auth}/{Sys}/{Prod}/{Domain}/{Entity}/{Action}` coordinates + suffix.

---

## 8. Rollback

The migration command ships its inverse mapping; `wp sparx-dict migrate-fields --run --reverse` restores prior keys. Combined with the pre-run DB backup, rollback is a code revert (PR-2) + reverse migration.
