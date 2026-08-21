# Dictionary Data Coverage Report

_Audit date: 2026-08-21. Scope: `Ai-West-Africa/aiwa-mandinka-dictionary` (data) against
`Starisian-Technologies/sparxstar-3iatlas-dictionary` (product plugin)._

## Headline

**The data is ahead of the plugin, and none of the new enrichment reaches production today.**

The Mandinka corpus now carries 56 columns of lexical and semantic enrichment. The
WordPress plugin exposes 24 ACF fields, of which the public REST API serialises 15.
There is **no importer in the plugin at all** — the `wp aiwa-dictionary import` command
referenced in `AGENTS.md`, `docs/dictionary-tech-spec.md` §Seams, and both
`.github/instructions/3IATLAS-DICTIONARY-*-SPEC-v1.0.md` files does not exist in code.
`src/cli/Sparxstar3IAtlasDictionaryCliCommands.php` registers only API-key
`generate` / `create` / `list` / `revoke`.

So the honest status is: **0 of 9,134 Mandinka rows are currently loadable**, and even once
an importer exists, **30 of the 56 columns have nowhere to land** — 20 of those 30 carry
real data today.

---

## 1. What the data layer now holds

| File | Rows | Cols | Role |
|---|---:|---:|---|
| `dictionary/AiWA Mandinka Dictionary - CLEAN.csv` | 9,134 | 56 | Source of truth, Mandinka (`mnk`, `mnk_GM`) |
| `dictionary/AiWA Semantic Scaffold.csv` | 4,905 | 47 | English-prompt concept scaffold; target-language slots intentionally blank |
| `dictionary/AiWA Mandinka Dictionary - Phonetic Variants.csv` | 1,000 | 679 | Phonetic-root → variant-UUID map |
| `reference/sil_semantic_domains.csv` | 1,792 | — | SemDom hierarchy used for the `Domain` codes |

### Fill rates that matter (CLEAN.csv, n=9,134)

Strong:

- UUID / `aiwa_entry_uuid` / ID / Source / Language / ISO 639-3 / Locale / Country — **100%**
- Header Word, Normalized Headword, Source Spelling — **99.9%**
- IPA + Phonetic Pronunciation — **98.3%**
- English Translation — **97.7%**

Partial:

- Sentence IPA / Sentence Phonetic — 74.1%; Sentence English Translation — 72.7%
- AIWA Level / CEFR Approximation / Oxford Tier — **74.6%** each
- English Definition — 67.9%; Phonetic Variant Root — 64.5%
- French Translation — 60.4%; Domain (SemDom code) — **58.9%**; Example Sentence — 56.9%
- French Definition — 54.8%
- WordNet: Synonyms 43.9%, Sense ID / Definition 41.6%, Hypernyms 35.2%, Hyponyms 23.3%,
  Antonyms 12.9%, Has Parts 6.3%, Part Of 6.5%

Empty (0%) — 10 columns: `Definition`, `Change Location`, `Date QC Status Changed`,
`QC Status Changed By`, `Alternative Spelling`, `Ajami Form`, `Audio Verified`,
`Pronunciation Source`, `Approved By`, `Approval Date`, `Alternative Form Of`.

Good news worth recording: the `=ai(...)` Google-Sheets formula junk called out in
`dictionary/README.md` and `DATA_QUALITY_REPORT.md` is **gone** — zero formula cells
remain in CLEAN.csv. Those two docs are stale on that point (see §5).

---

## 2. Column-by-column: does it have a home in the plugin?

### Lands today (importer permitting) — 23 columns

| CSV column | Destination |
|---|---|
| UUID / `aiwa_entry_uuid` | `aiwa_entry_uuid` (ACF text, required) |
| Header Word | `post_title` |
| Normalized Headword | `post_name` (slug) |
| English Definition | `aiwa_extract` |
| English Translation | `aiwa_translation_english` |
| French Translation | `aiwa_translation_french` |
| IPA Pronunciation | `aiwa_ipa_pronunciation` |
| Phonetic Pronunciation | `aiwa_phonetic` |
| Example Sentence | `aiwa_example_sentences[].aiwa_sentence_example` |
| Sentence IPA Pronunciation | `aiwa_example_sentences[].aiwa_sentence_ipa` (PHP-only sub-field) |
| Sentence Phonetic Pronunciation | `aiwa_example_sentences[].aiwa_sentence_phonetic` |
| Sentence English Translation | `aiwa_example_sentences[].aiwa_sentence_english` |
| Origin / Geographic Origin | `aiwa_origin` |
| Language / ISO 639-3 | `starmus_tax_language` term |
| Letter | `aiwa-alpha-letter` term |
| Part of Speech | `starmus_part_of_speech` term **and** `aiwa_part_of_speech` select (duplicated) |
| Domain | `aiwa_domain` term |
| QC Status | `aiwa_qc_status` select |
| Phonetic Variant Root | `aiwa_phonetic_variants` relationship (needs UUID→post_ID resolution) |

(Rows above cover 23 columns: `UUID` and `aiwa_entry_uuid` are the same destination,
`Language`/`ISO 639-3` resolve to one term, `Origin`/`Geographic Origin` to one field, and
the empty `Definition` column would map to `aiwa_extract` alongside `English Definition`.)

### Has a field defined in spec but **not** in the exported SCF — 3 CLEAN columns (+5 scaffold concepts)

`docs/dictionary-tech-spec.md` already flags these as "spec-defined enrichment fields
(not yet in the exported SCF JSON)":

| CSV column | Spec field |
|---|---|
| AIWA Level | `aiwa_level` |
| CEFR Approximation | `aiwa_cefr_approx` |
| Oxford Tier | `aiwa_oxford_tier` |
| (scaffold) Concepticon_ID | `aiwa_concepticon_id` |
| (scaffold) Colexification (CLICS) | `aiwa_clics_id` |
| (scaffold) English Rhyming Words | `aiwa_rhyme_entries` |
| (scaffold) Cross-Language Siblings | `aiwa_cross_language_siblings` + `_relation_type` |
| Speaker community | `aiwa_community_usage_status` |

Two supporting taxonomies named in the spec are also **not registered in code**:
`aiwa_speaker_community` (absent entirely) and `starmus_tax_alpha` (code registers
`aiwa-alpha-letter` instead — the spec name is wrong, or the code is).

### Has no home anywhere — 30 columns (20 of them non-empty)

**Carrying real data (20):** `ID`, `Header Word Root`, `French Definition`, `Source`,
`Source Batch`, `Country`, `ISO 2 Country Code`, `Locale`, `Swadesh`, `Source Spelling`,
`Approval Status`, `AIWA Level Source`, and the eight WordNet relation columns
(`WordNet Sense ID`, `WordNet Definition (EN)`, `WordNet Synonyms (EN)`,
`WordNet Antonyms (EN)`, `Hypernyms (EN)`, `Hyponyms (EN)`, `Has Parts (EN)`, `Part Of (EN)`).

**Currently empty, so no loss yet but no destination either (10):** `Change Location`,
`Date QC Status Changed`, `QC Status Changed By`, `Alternative Spelling`, `Ajami Form`,
`Audio Verified`, `Pronunciation Source`, `Approved By`, `Approval Date`,
`Alternative Form Of`. `Ajami Form` in particular is a field the data model anticipates
and the product has no plan for.

The WordNet block is the biggest single loss: 4,013 rows carry English synonym sets and
3,798 carry sense IDs, and there is no field, no taxonomy, and no API surface for any of it.
`aiwa_synonyms` / `aiwa_antonyms` are ACF **relationship** fields pointing at other
`aiwa-cpt-dictionary` posts — they cannot hold English WordNet strings. `French Definition`
(5,004 rows, 54.8%) is a straightforward miss: `aiwa_translation_french` exists but there is
no French counterpart to `aiwa_extract`.

---

## 3. Value-level mismatches that will break an import

These are not missing fields — they are fields where the data's encoding does not match
what the plugin or the Approved Entry Spec accepts.

1. **`AIWA Level` in CLEAN.csv holds CEFR values, not AIWA values.** The column contains
   `A1` (3,356), `A2` (1,390), `B2` (903), `B1` (757), `C1` (408) — byte-identical to the
   `CEFR Approximation` column. The spec defines AIWA Level as `AIWA-0`…`AIWA-5`. The
   Semantic Scaffold in the *same repo* gets this right (`AIWA-4`, `AIWA-5`, `AIWA-1`…).
   The master needs a CEFR→AIWA remap, or the column is redundant.

2. **`Oxford Tier` has three encodings across three artefacts.** CLEAN.csv says
   `Oxford 3000`; the Scaffold says `Oxford 3000 (A1)`; the spec expects `oxford_3000`.

3. **Part of Speech is abbreviated and partly corrupted.** 32 distinct values. The legitimate
   ones (`n` 5,171, `v` 1,880, `adj` 1,036, `vn` 461, `adv` 176, `sv` 112, plus `conj`,
   `dem`, `postp`, `pron`-family) need mapping to the ACF select's full words
   (`noun`, `verb`, …) — and `vn`, `sv`, `spn`, `pospn` have **no ACF choice at all**.
   Separately, roughly a dozen values are OCR bleed from adjacent cells: `at sunrise`,
   `belly is`, `liquid`, `morning (n`, `people`, `person`. Those rows need repair.

4. **`QC Status` is `unreviewed` on all 9,134 rows.** The `aiwa_qc_status` ACF select only
   accepts `pending` / `verified` / `needs_fix`. Add `unreviewed` or map it to `pending`.

5. **`Domain` holds bare SemDom codes** (`4.3.1.4`, `1.2.1.7`, …) — 967 distinct codes across
   5,379 rows. `aiwa_domain` is a hierarchical WP taxonomy expecting names. Nothing in the
   plugin seeds it, so the 1,792-node `reference/sil_semantic_domains.csv` hierarchy has to be
   imported as terms before any domain-filtered browse or `/game-set?domain=` call works.

6. **Approval fields are empty but required.** `3IATLAS-DICTIONARY-APPROVED-ENTRY-SPEC-v1.0`
   lists `approval_status` (must be `approved`), `approved_by`, and `approval_date` as
   required. CLEAN.csv has `Approval Status = unreviewed` on every row and `Approved By` /
   `Approval Date` **0% filled**. Under the spec as written, **zero rows are importable**
   until DVE stamps approval — this is the hard gate, ahead of every technical gap above.

### How much would actually survive

Applying the Approved Entry Spec's required set except approval
(uuid + headword + POS + definition + translation_en): **5,981 of 9,134 rows (65.5%)**.
Adding `approved_by` + `approval_date`: **0**.

For `/game-set`, which excludes entries missing headword, translation_en, or IPA:
**8,768 rows (96.0%)** qualify — dropping to **5,260 (57.6%)** once you also require a
Domain for domain-filtered sets.

---

## 4. Other languages — schema-ready, not product-ready

Commit `b750e0d` added 22 per-language CSVs under
`reference/comparison/internal-only/`. **All 22 match the Mandinka master's 56-column
header exactly** — that part is genuinely done, and it is the reason this question has a
clean answer.

**Word-level lexicons (5):**

| Language | ISO | Rows | POS | Source / licence |
|---|---|---:|---:|---|
| Bambara | `bam` | 11,488 | 11,488 | Bamadaba/Bailleul — **CC-BY-NC** |
| Wolof | `wol` | 8,848 | 0 | OCR auto-split, **UNVERIFIED**, licence unknown |
| Maninka | `emk` | 7,432 | 7,430 | **CC-BY-SA (verify)** — share-alike risk |
| Yoruba | `yor` | 2,772 | 0 | 313 rows CMS 1913 (public domain); 2,459 rows **AI-generated** |
| Twi | `twi` | 77 | 60 | Open Twi, community-contributed, licence unspecified |

**Sentence-pair sets (17)** — Hausa 8,531 · Igbo 9,750 · Swahili 33,939 · Nigerian Pidgin
7,834 · Luo 7,262 · Luganda 6,983 · Zulu 5,712 · Mossi 5,558 · Fon 5,443 · Ewe 4,995 ·
Setswana 4,941 · Ghomala 4,697 · Amharic 1,931 · Shona 1,561 · Chichewa 1,487 ·
Kinyarwanda 1,466 · Xhosa 1,425. All LAFAND-MT, **CC-BY-NC-4.0**.

Four things make these **not** ready to ship:

1. **They are structurally barred from shipping.** `.gitattributes` marks
   `reference/comparison/internal-only/**` as `export-ignore`, and
   `scripts/check_ship_boundary.py` fails the build if a shippable artifact references those
   paths. That is deliberate and correct — the licences (CC-BY-NC, CC-BY-SA, unknown) do not
   permit product use. Graduation requires a rights clearance recorded in
   `reference/comparison/SCREENING_AND_RIGHTS.md`. These files exist to screen paid
   submissions against, not to populate the dictionary.

2. **The sentence-pair files are not lexicons.** Header Word holds a full sentence. Loading
   them as dictionary entries would produce ~113,000 junk "headwords". The naming
   (`*_lafand_sentence_pairs.csv` vs `*_reference_lexicon.csv`) already signals this; it just
   needs to be respected by whatever consumes them.

3. **Nothing is verified.** All 22 files are `QC Status = unreviewed` and
   `Approval Status = unreviewed` on every row, 0 IPA anywhere, and no `Approved By` /
   `Approval Date` — the same hard gate as Mandinka, times 22.

4. **The plugin has no multi-language content path anyway.** `starmus_tax_language` exists
   and `/languages` is in the OpenAPI contract, so the *browse* dimension is ready. But
   with no importer, adding a second language is the same blocked task as adding the first.

**So: the schema alignment work is real and valuable — it means a second language is an
import job, not a modelling job. But no language other than Mandinka is currently cleared
to become product data, and Mandinka itself isn't loadable yet.**

---

## 5. Stale documentation found along the way

- `dictionary/README.md` states CLEAN.csv is **14,539 rows × 57 columns**. It is
  **9,134 × 56**. It also lists `=ai(...)` formula text as a known issue; that is fixed.
- `MASTER_BUILD_REPORT.md` describes building `MASTER.csv` (12,532 × 31) from
  `NEW DICTIONARY.csv` and `Mandinka Dictionary file 2.csv`. None of those three files exist
  in the repo any more, and `dictionary/README.md` says explicitly "do not restore MASTER.csv."
- `DATA_QUALITY_REPORT.md` (dated 2026-06-06) audits `NEW DICTIONARY.csv` at 29 columns /
  11,550 entries — a file that no longer exists, with a column list that no longer matches.
- Plugin side: `AGENTS.md` and `docs/dictionary-tech-spec.md` document
  `wp aiwa-dictionary import` as the DVE→Dictionary seam. It is unimplemented.
  `OQ-015` correctly tracks this but frames it as "importer production qualification is
  incomplete", which reads as *hardening an existing importer* rather than *writing one*.

---

## 6. What to do, in order

**P0 — unblocks everything**

1. Get DVE to stamp `Approval Status` / `Approved By` / `Approval Date`. Nothing imports
   until this exists, no matter what code gets written. This is an upstream/process item,
   not an engineering one.
2. Build `wp aiwa-dictionary import` against
   `3IATLAS-DICTIONARY-APPROVED-ENTRY-SPEC-v1.0` (dry-run, publish, idempotent re-import,
   UUID preservation, rollback). Re-word `OQ-015` to say the importer does not exist.
3. Seed `aiwa_domain` from `reference/sil_semantic_domains.csv` (1,792 terms), so the
   967 SemDom codes in the data resolve to terms.

**P1 — stops data loss at import time**

4. Add the eight spec-defined enrichment fields to `acf-json/sparxstar-dictionary-scf.json`
   (`aiwa_level`, `aiwa_cefr_approx`, `aiwa_oxford_tier`, `aiwa_concepticon_id`,
   `aiwa_clics_id`, `aiwa_rhyme_entries`, `aiwa_cross_language_siblings` +
   `_relation_type`, `aiwa_community_usage_status`) and register the
   `aiwa_speaker_community` taxonomy.
5. Add fields for the WordNet block and `French Definition` — or make an explicit,
   recorded decision that WordNet enrichment stays in the data layer and never ships.
   Right now it is being lost by omission rather than by decision.
6. Write the value mappings: POS abbreviation→ACF choice, CEFR→AIWA Level,
   Oxford tier normalisation, `unreviewed`→`pending`.

**P2 — data-layer hygiene**

7. Fix `AIWA Level` in CLEAN.csv to carry AIWA-0…5, matching the Scaffold.
8. Repair the ~12 OCR-bleed Part of Speech values.
9. Refresh `dictionary/README.md`, `MASTER_BUILD_REPORT.md`, and `DATA_QUALITY_REPORT.md`
   against the files that actually exist, or archive the two obsolete reports.

**P2 — languages**

10. Run the rights ledger to conclusion for Bambara and Maninka (the two largest
    word-level sets). Until a source graduates out of `internal-only/`, treat all 22
    files as screening indexes only.
