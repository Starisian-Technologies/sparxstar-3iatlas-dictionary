# Migration Plan: `aiwa_*` → `sparx_dict_*` (de-brand the storage layer)

**Status:** Proposed (planning artifact — no code or schema changed by this PR)
**Implements:** ADR-014 (SPX Prefix Standard) · Owner directive 2026-06-13
**Owner approval:** Field-naming decision approved 2026-06-13 — *"new fields are `sparx_dict_*`; existing `aiwa_*` fields get a migration plan to `sparx_dict_*` as a separate PR. Don't build new work on top of client-branded columns."*
**Repo-local ADR number:** pending — see Open Question 1 (ADR-015 repo prefix not yet assigned).

---

## 1. Why

`aiwa_*` storage identifiers are **client-branded columns in a platform tool**. AIWA's Mandinka entries and (e.g.) Agua Caliente's Cahuilla entries must store in the *same* schema with the *same* field names. Every other layer was de-branded this week; the storage layer is the last and most expensive, so it is done early — before more data accumulates against the wrong names.

**Key scoping fact (lowers blast radius):** the *external contract is already clean.* REST output keys (`definition`, `translation_en`, …) and the DVE import-package keys (`headword`, `definition`, …) are unbranded. `aiwa_*` exists **only at the WordPress storage layer**. For the linguistic fields, API consumers and the React browse/games clients are therefore **unaffected** — this is an internal rename, not a wire-format break.

The one exception is the game-signal namespace (§4), which *is* on the wire and *is* cross-system.

---

## 2. Targets are tiered (ADR-014) — not a flat `sparx_dict_*`

| Artifact type | Tier | Target pattern | Example |
|---|---|---|---|
| Post meta / ACF field names | medium | `sparx_dict_*` | `aiwa_extract` → `sparx_dict_extract` |
| ACF field group + field keys | medium | `group_sparx_dict_*` / `field_*` | `group_aiwa_dictionary_main` → `group_sparx_dict_main` |
| CPT slug | medium | `sparx_dict_*` | `aiwa_cpt_dictionary` → `sparx_dict_word` |
| Taxonomy slug | medium | `sparx_dict_*` | `aiwa_domain` → `sparx_dict_domain` |
| `wp_options` names | medium | `sparx_dict_*` | `aiwa_dict_api_keys` → `sparx_dict_api_keys` |
| Action/filter hooks | full | `sparxstar_dict_*` | `aiwa_game_word_correct` → `sparxstar_dict_game_word_correct` |
| CSS classes | css | `spx-dict-*` | `spx-dictionary-root` → `spx-dict-root` |
| PHP classes | short | `SPX*` | (new code only; existing grandfathered until touched) |

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
| `aiwa_phonetic_variants` (+ `_var_root`) | `sparx_dict_phonetic_variants` |
| `aiwa_edited_from` | `sparx_dict_edited_from` |

> `_en`/`_fr` over `_english`/`_french` aligns with the wire keys (`translation_en`) and is a naming decision to confirm. ACF field **keys** (`aiwa_field_1`, `aiwa_field_qc_1`, …) are opaque internal identifiers; renaming them is optional cleanup (Open Question 5).

**Source-of-truth files:** `src/includes/Sparxstar3IAtlasPostTypes.php` (ACF registration) **and** `acf-json/sparxstar-dictionary-scf.json` (ACF local-JSON sync). Both must change together or ACF will resync the old names.

**Read/write call sites:** `src/api/Sparxstar3IAtlasDictionaryRestApi.php` (all `get_field`/`get_post_meta`/`meta_query`), `src/frontend/Sparxstar3IAtlasDictionaryForm.php`, `src/core/Sparxstar3IAtlasDictionaryCore.php`.

### Bucket B — CPT slug
`aiwa_cpt_dictionary` → `sparx_dict_word`. Confirmed call sites: `register_post_type` (`Sparxstar3IAtlasPostTypes.php:940`), `save_post_aiwa_cpt_dictionary` hook and `get_post_type() !== 'aiwa_cpt_dictionary'` guard (`Sparxstar3IAtlasDictionaryCore.php`). Data migration: `wp_posts.post_type`. **Rewrite/permalink impact — see Open Question 2.**

### Bucket C — Taxonomy slug
`aiwa_domain` → `sparx_dict_domain`. Data migration: `wp_term_taxonomy.taxonomy`. Re-register under new slug. *(Related: `starmus_tax_language`, `starmus_part_of_speech` are also non-platform prefixes — Open Question 3.)*

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

A dedicated, **idempotent** WP-CLI command (new class, ADR-014 compliant, e.g. `SPXDictionaryMigrateCommand` → `wp sparx-dict migrate-fields`):

- `--dry-run` (default): report row counts per mapping, write nothing.
- `--run`: execute inside a transaction where the storage engine allows.
- For each `old → new` meta key: `UPDATE {$wpdb->postmeta} SET meta_key=<new> WHERE meta_key=<old>` **and** the `_<old>` → `_<new>` reference row, scoped to CPT posts.
- CPT: `UPDATE {$wpdb->posts} SET post_type='sparx_dict_word' WHERE post_type='aiwa_cpt_dictionary'`, then `flush_rewrite_rules()`.
- Taxonomy: `UPDATE {$wpdb->term_taxonomy} SET taxonomy='sparx_dict_domain' WHERE taxonomy='aiwa_domain'`.
- Options: copy → new `option_name`, delete old.
- Idempotent: re-running detects already-migrated keys and no-ops.
- Reversible: ship the inverse mapping for rollback.

**Atomicity rule:** the **code rename (Bucket A–D) and the data migration command ship in the same PR**. A code rename without the data migration orphans every existing row; a data migration without the code rename breaks all reads. They are one change.

---

## 5. PR breakdown & sequencing

| PR | Contents | Risk | Depends on |
|---|---|---|---|
| **PR-1 (this)** | This plan. | none | — |
| **PR-2** | Buckets A–D: ACF reg + `acf-json` + all PHP read/write sites + CPT + taxonomy + options, **plus** the idempotent WP-CLI data-migration command. Atomic. | medium (internal) | PR-1 review/sign-off + Open Q 1–3 |
| **PR-3** | Bucket E: game hooks + event-`type` strings, coordinated with Rewards listener + both frontend copies. | high (cross-system, wire) | Rewards/Open Q 4 |
| **PR-4** | Bucket F: CSS `spx-dict-*`, rebuild assets, doc/spec reconciliation. | low | PR-2 |

**Importer work** (held) is built **directly on `sparx_dict_*`** from creation and lands *after* PR-2 (or against the new names concurrently). It never touches `aiwa_*`.

---

## 6. Rollout & safety

- **Full DB backup** before `--run`. This rewrites `wp_postmeta`, `wp_posts`, `wp_term_taxonomy`, `wp_options`.
- Run `--dry-run` first; review counts against expected entry volume.
- Maintenance window: brief, since reads break the instant code lands without data, and vice-versa — deploy code + run migration together.
- After CPT slug change: `flush_rewrite_rules()` + verify entry permalinks + ACF field-group sync (`acf-json`) loads cleanly.
- Re-run any search index / cached REST responses (cache invalidation) post-migration.
- Because the REST/import **field** contract is unchanged, no consumer coordination is needed for Buckets A–D. Bucket E is the only consumer-facing change and is gated behind PR-3.

---

## 7. Open questions (need a ruling before PR-2)

1. **Repo-local ADR prefix** (ADR-015): this repo has no assigned prefix yet (e.g. `DICT-ADR-` / `3IA-ADR-`). Needed to file this plan as a formal repo-local ADR rather than a `docs/migrations/` artifact.
2. **CPT slug — rename vs grandfather?** `aiwa_cpt_dictionary` → `sparx_dict_word` rewrites `wp_posts.post_type` and changes admin/rewrite bases. ADR-014 left "rename existing production CPTs" explicitly undecided. Owner call. (Owner example named `sparx_dict_word`, implying rename.)
3. **`starmus_*` taxonomies** (`starmus_tax_language`, `starmus_part_of_speech`): also non-`sparx` prefixes (Starmus = audio-acquisition product). De-brand to `sparx_dict_language` / `sparx_dict_pos`, or are these **shared cross-suite taxonomies** intentionally owned by Starmus? Not assumed — needs a ruling.
4. **Game-signal rename ownership** (Bucket E): coordination with the Rewards/MyCred listener and the `sparxstar-3iatlas-dictionary-games` frontend. Who owns that cross-repo change, and is `sparxstar_dict_game_*` the agreed hook name?
5. **ACF field-key renames** (`aiwa_field_N` → `field_*`): include in PR-2 or leave as opaque internal identifiers?
6. **`_en`/`_fr` vs `_english`/`_french`** suffix convention for the translation/search/sentence fields — confirm.

---

## 8. Rollback

The migration command ships its inverse mapping; `wp sparx-dict migrate-fields --run --reverse` restores prior keys. Combined with the pre-run DB backup, rollback is a code revert (PR-2) + reverse migration.
