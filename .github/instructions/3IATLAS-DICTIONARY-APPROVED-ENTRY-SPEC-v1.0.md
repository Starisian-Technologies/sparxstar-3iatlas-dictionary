# AIWA Dictionary — Approved Entry Package Spec

**Version:** 1.0
**Status:** Active
**Scope:** sparxstar-3iatlas-dictionary
**Authority:** Max Barrett / Starisian Technologies
**Date:** June 2026

---

## 1. Purpose

This document defines the Approved Entry Package format — the structured data unit that DVE delivers to the dictionary for import. The dictionary accepts no other format as authoritative input.

An Approved Entry Package represents a lexical record that has passed all DVE review, normalization, and approval steps. The dictionary imports it as-is. It does not re-evaluate, re-normalize, or re-adjudicate the content.

---

## 2. Package Format

An Approved Entry Package is a JSON object. A batch import file is an array of these objects.

```json
{
    "aiwa_entry_uuid": "string — DVE-minted UUID, immutable",
    "headword": "string — normalized spelling, approved by DVE",
    "slug": "string — URL slug derived from headword (optional: dictionary derives if absent)",
    "primary_language": "string — language taxonomy slug (e.g. mandinka, wolof, fula)",
    "part_of_speech": "string — POS taxonomy slug (e.g. noun, verb, adjective, phrase)",
    "definition": "string — primary definition / extract",
    "translation_en": "string — English gloss",
    "translation_fr": "string — French gloss",
    "ipa": "string — IPA pronunciation (optional: derived from headword if absent)",
    "phonetic": "string — phonetic pronunciation in plain text",
    "origin": "string — origin and cultural notes (optional)",
    "audio_url": "string — URL of approved audio asset (optional)",
    "audio_asset_id": "string — DVE asset identifier for the audio file (optional)",
    "example_sentences": [
        {
            "sentence": "string",
            "ipa": "string",
            "phonetic": "string",
            "translation_en": "string",
            "translation_fr": "string"
        }
    ],
    "synonyms": ["string — aiwa_entry_uuid of related entries"],
    "antonyms": ["string — aiwa_entry_uuid of related entries"],
    "rhyme_entries": ["string — aiwa_entry_uuid of rhyming entries"],
    "cross_language_siblings": [
        {
            "uuid": "string — aiwa_entry_uuid of the sibling entry",
            "relation_type": "string — see §4 for valid values"
        }
    ],
    "speaker_communities": [
        {
            "community_slug": "string — controlled taxonomy term, see §5",
            "usage_status": "string — observed | speaker_confirmed | editorial_approved"
        }
    ],
    "domain": "string — semantic domain taxonomy slug",
    "aiwa_level": "string — AIWA-0 through AIWA-5 (see §6)",
    "cefr_approx": "string — optional CEFR approximation (A1, A2, B1, B2, C1, C2)",
    "oxford_tier": "string — optional Oxford reference tier (oxford_3000, oxford_5000)",
    "concepticon_id": "integer — optional Concepticon database ID",
    "clics_id": "string — optional CLICS database reference",
    "approval_status": "string — approved | provisional",
    "approved_by": "string — name or identifier of approving authority",
    "approval_date": "string — ISO 8601 date (YYYY-MM-DD)",
    "source_batch": "string — DVE batch identifier for traceability",
    "public_notes": "string — notes intended for public display (optional)",
    "internal_notes": "string — notes for dictionary admins only, not exposed via API (optional)"
}
```

---

## 3. Required vs Optional Fields

**Required — batch is rejected if any entry is missing these:**

| Field              | Reason                                                                                         |
| ------------------ | ---------------------------------------------------------------------------------------------- |
| `aiwa_entry_uuid`  | Canonical identifier. Without it the entry cannot be safely imported.                          |
| `headword`         | The word itself.                                                                               |
| `primary_language` | Which language the word belongs to. Required for every downstream filter.                      |
| `part_of_speech`   | Required for game filtering and search.                                                        |
| `definition`       | The word must have a definition.                                                               |
| `translation_en`   | English gloss. Required for game display and cross-language context.                           |
| `approval_status`  | Must be `approved`. `provisional` entries are staged but not published via API until promoted. |
| `approved_by`      | Audit trail.                                                                                   |
| `approval_date`    | Audit trail.                                                                                   |

**Optional — missing is acceptable; API returns null for these fields:**

`translation_fr`, `ipa`, `phonetic`, `origin`, `audio_url`, `audio_asset_id`, `example_sentences`, `synonyms`, `antonyms`, `rhyme_entries`, `cross_language_siblings`, `speaker_communities`, `domain`, `aiwa_level`, `cefr_approx`, `oxford_tier`, `concepticon_id`, `clics_id`, `slug`, `public_notes`, `internal_notes`

---

## 4. Cross-Language Relation Types

The `relation_type` field on a cross-language sibling is required when a sibling is specified. Valid values:

| Value                   | Meaning                                                                                                               |
| ----------------------- | --------------------------------------------------------------------------------------------------------------------- |
| `same_concept`          | Entries represent the same underlying concept in different languages. Not identical words — equivalent concepts.      |
| `loanword`              | One entry is a direct borrowing of the other. Direction is recorded in internal notes.                                |
| `cognate`               | Entries share a common etymological root. Related but distinct forms.                                                 |
| `shared_regional_usage` | Speakers of one language community use this word from another language in a specific regional context.                |
| `religious_term`        | The connection is primarily through religious practice (Islamic vocabulary shared across communities, etc.).          |
| `market_usage`          | The word circulates in trade and market contexts across language communities.                                         |
| `school_usage`          | The word appears in formal educational materials across language communities.                                         |
| `false_friend`          | Entries look or sound similar but mean different things across languages. Used as a teaching and disambiguation flag. |
| `uncertain`             | The relationship exists but its precise nature has not been confirmed by a qualified reviewer.                        |

A sibling link with `uncertain` relation type is valid and serves as a curation queue signal.

---

## 5. Speaker Community Taxonomy

Controlled vocabulary. Terms are defined and governed by AIWA. New terms require AIWA board approval before use in import packages. Freeform values in `community_slug` will cause import validation to fail.

**Current approved terms:**

| Slug                        | Definition                                                                                                                                                        |
| --------------------------- | ----------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `mandinka-speakers`         | People who identify Mandinka as a primary, heritage, household, or fluent community language.                                                                     |
| `wolof-speakers`            | People who identify Wolof as a primary, heritage, household, or fluent community language.                                                                        |
| `fula-speakers`             | People who identify Fula/Pulaar/Fulani as a primary, heritage, household, or fluent community language.                                                           |
| `jola-speakers`             | People who identify Jola as a primary, heritage, household, or fluent community language.                                                                         |
| `serer-speakers`            | People who identify Serer as a primary, heritage, household, or fluent community language.                                                                        |
| `soninke-speakers`          | People who identify Soninke as a primary, heritage, household, or fluent community language.                                                                      |
| `mixed-urban-gambia`        | Common multilingual speech environment in urban Gambian settings where words circulate across named language groups regardless of the speaker's primary language. |
| `banjul-market`             | Words that circulate specifically in Banjul market and commercial contexts, often across multiple language communities.                                           |
| `serekunda-urban`           | Words associated with urban Serekunda speech, which reflects a high degree of multilingual mixing.                                                                |
| `senegambia-region`         | Words used across the broader Senegambia region, spanning Gambian and Senegalese speaker communities.                                                             |
| `school-gambia`             | Words that appear in formal Gambian school curricula or educational materials across language communities.                                                        |
| `islamic-religious-context` | Words that circulate primarily in Islamic religious practice across language communities in the region.                                                           |

**Community usage status values:**

| Value                | Meaning                                                                                                                       |
| -------------------- | ----------------------------------------------------------------------------------------------------------------------------- |
| `observed`           | A contributor or editor noted this word is used by this community. Not yet confirmed by a community speaker or AIWA reviewer. |
| `speaker_confirmed`  | At least one fluent speaker of this community has confirmed the usage. Reviewer recorded.                                     |
| `editorial_approved` | AIWA language board has reviewed and approved the speaker community tag. Authoritative.                                       |

Games and strict-mode API consumers should only trust `editorial_approved` tags. Browse and ecology-mode consumers may include all three statuses with appropriate labeling.

---

## 6. AIWA Level Scale

AIWA Level is the primary public-facing educational grading field. It reflects the pedagogical difficulty and curriculum placement of a word as assessed by AIWA against the Gambian educational context. It is a sovereign educational scale — not a CEFR or Oxford label, though approximate mappings are provided as reference.

**Proposed scale (pending AIWA curriculum board final confirmation):**

| Level    | Label                           | Description                                                                                                                | CEFR Approx | Oxford Approx       |
| -------- | ------------------------------- | -------------------------------------------------------------------------------------------------------------------------- | ----------- | ------------------- |
| `AIWA-0` | Picture / First Exposure        | Pre-literate vocabulary. Taught through image and audio. Used in early childhood and first-contact literacy.               | Pre-A1      | —                   |
| `AIWA-1` | Beginner Survival               | Core survival vocabulary. Greetings, numbers, body, family, immediate environment. First reading and writing.              | A1          | Oxford 3000 A1      |
| `AIWA-2` | Everyday Sentence               | Vocabulary for everyday conversation and simple sentences. Markets, home, community.                                       | A2          | Oxford 3000 A2      |
| `AIWA-3` | Storytelling and Explanation    | Vocabulary for oral narrative, explanation, and basic description. School subjects, community life, simple stories.        | B1          | Oxford 3000 B1–B2   |
| `AIWA-4` | School, Civic, Formal           | Abstract and formal vocabulary. School curriculum, civic life, governance, structured writing and argument.                | B2          | Oxford 5000         |
| `AIWA-5` | Literary, Technical, Specialist | Literary vocabulary, specialist terminology, historical and cultural depth. Academic, archival, and cultural heritage use. | C1–C2       | Beyond Oxford lists |

**Note:** The CEFR and Oxford columns are approximate mappings provided for reference only. AIWA Level is the authoritative field. CEFR approximation is an optional secondary field (`aiwa_cefr_approx`) stored separately. Oxford tier is an English-reference field (`aiwa_oxford_tier`) that records whether the English translation of the entry appears in the Oxford 3000 or 5000 list. Neither CEFR nor Oxford overrides AIWA Level.

---

## 7. Import Validation Rules

The WP-CLI importer must enforce these rules before writing any record:

1. `aiwa_entry_uuid` must be present and must be a valid UUID format.
2. `aiwa_entry_uuid` must not already exist in the dictionary unless the import is an explicit replacement (flag: `--replace`).
3. `primary_language` must be a registered taxonomy term in `starmus_tax_language`.
4. `part_of_speech` must be a registered taxonomy term in `starmus_part_of_speech`.
5. `approval_status` must be `approved` or `provisional`. Any other value rejects the entry.
6. If `cross_language_siblings` is present, each sibling's `relation_type` must be one of the values in §4.
7. If `speaker_communities` is present, each `community_slug` must be a registered term in §5.
8. If `speaker_communities` is present, each `usage_status` must be `observed`, `speaker_confirmed`, or `editorial_approved`.
9. `aiwa_level` if present must be one of: `AIWA-0`, `AIWA-1`, `AIWA-2`, `AIWA-3`, `AIWA-4`, `AIWA-5`.
10. `cefr_approx` if present must be one of: `A1`, `A2`, `B1`, `B2`, `C1`, `C2`.
11. `oxford_tier` if present must be one of: `oxford_3000`, `oxford_5000`.
12. Cross-language sibling UUIDs must exist in the dictionary. Unknown UUIDs are flagged in the validation report and the sibling link is skipped (not a blocking error — the entry is still imported).

---

## 8. Replacement Package

A replacement package corrects one or more linguistic fields on an existing approved entry. It uses the same format as a standard package with these additions:

```json
{
  "aiwa_entry_uuid": "the existing UUID — must match exactly",
  "replacement_reason": "string — brief explanation for audit log",
  "replacement_authorized_by": "string — DVE authority",
  ...corrected fields only or all fields...
}
```

Import command for replacements:

```bash
wp aiwa-dictionary import --file=replacement-batch.json --publish --replace
```

The importer updates only the linguistic fields provided in the replacement package. UUID, operational fields, and lifecycle status are preserved.

---

## 9. Deprecation / Merge Package

```json
{
    "aiwa_entry_uuid": "the entry being deprecated or merged",
    "lifecycle_action": "deprecated | merged",
    "merge_target_uuid": "string — UUID of canonical entry (required if merged)",
    "reason": "string — audit note",
    "authorized_by": "string"
}
```

The entry's linguistic fields are frozen in their last approved state. The entry remains resolvable via UUID for historical continuity. It is excluded from primary API results unless explicitly requested.
