# AIWA Dictionary — Role, Pipeline, and Governance Spec

**Version:** 1.0
**Status:** Active
**Scope:** sparxstar-3iatlas-dictionary
**Authority:** Max Barrett / Starisian Technologies
**Date:** June 2026

---

## 1. Foundation Statement

The AIWA Dictionary is the governed publication and distribution layer for approved lexical records. It does not collect, adjudicate, or approve words. DVE is the upstream authority for lexical intake, review, normalization, and approval. The dictionary preserves DVE-approved records, exposes them through controlled search and API services, and prevents direct edits to linguistic content after import.

---

## 2. What the Dictionary Is

- A downstream, governed publication service
- A read-only lexical API for the 3iAtlas suite and external consumers
- The authoritative public record of approved AIWA entries
- A search, browse, and game export service
- A governed storefront for approved linguistic truth

The dictionary is **linguistically read-only** after import. WordPress operational controls remain editable. Linguistic corrections must originate upstream in DVE.

---

## 3. What the Dictionary Is Not

- An intake system for community word submissions
- A review or adjudication pipeline
- A normalization or spelling correction tool
- A staging area for unverified entries
- A place where headwords, definitions, pronunciations, or cross-language relationships are decided

The dictionary should never contain:
- Provisional spellings under review
- Entries flagged "Muhammed needs to check"
- Disputed entries pending elder review
- Raw contributor versions
- Unapproved audio
- Duplicate candidates

That work belongs in DVE. The dictionary receives only finished, approved lexical records.

---

## 4. The Pipeline

```
Community / Speakers / Linguists / Elders
        ↓
DVE (Digital Village Elder)
Intake → spelling review → normalization → approval → package
        ↓
AIWA Dictionary
Import → validate → stage → publish
Store approved records, lock linguistic fields
        ↓
3iAtlas Applications
Games (RLC) / Workbooks (WordPad) / Browse App / S2S / REST API consumers
```

DVE governs truth. The dictionary distributes truth.

---

## 5. Linguistic Fields vs Operational Fields

**Linguistic fields — locked after import. Edit prohibited in WordPress UI. Corrections require a new DVE-approved package.**

| Field | Type | Minted By |
|---|---|---|
| `aiwa_entry_uuid` | UUID | DVE — immutable after import |
| Normalized headword | Post title | DVE |
| Slug | Post name | Derived from normalized headword at import |
| Primary language | Taxonomy: `starmus_tax_language` | DVE |
| Part of speech | Taxonomy: `starmus_part_of_speech` | DVE |
| English gloss | ACF: `aiwa_translation_english` | DVE |
| French gloss | ACF: `aiwa_translation_french` | DVE |
| Definition / extract | ACF: `aiwa_extract` | DVE |
| IPA pronunciation | ACF: `aiwa_ipa_pronunciation` | DVE or derived |
| Phonetic pronunciation | ACF: `aiwa_phonetic` | DVE or derived |
| Audio URL | ACF: `aiwa_audio_file` | DVE |
| Origin notes | ACF: `aiwa_origin` | DVE |
| Synonyms | ACF relationship: `aiwa_synonyms` | DVE |
| Antonyms | ACF relationship: `aiwa_antonyms` | DVE |
| Rhyme entries | ACF relationship: `aiwa_rhyme_entries` | DVE |
| Cross-language siblings | ACF relationship: `aiwa_cross_language_siblings` | DVE |
| Cross-language relation type | ACF: `aiwa_cross_language_relation_type` | DVE |
| Speaker community tags | Taxonomy: `aiwa_speaker_community` | DVE |
| Community usage status | ACF: `aiwa_community_usage_status` (per tag) | DVE / AIWA review |
| Semantic domain | Taxonomy: `aiwa_domain` | DVE |
| AIWA Level | ACF select: `aiwa_level` | DVE |
| CEFR approximation | ACF select: `aiwa_cefr_approx` | DVE (optional mapping) |
| Oxford tier | ACF checkbox: `aiwa_oxford_tier` | DVE (optional) |
| Concepticon ID | ACF number: `aiwa_concepticon_id` | DVE (academic anchor) |
| CLICS ID | ACF text: `aiwa_clics_id` | DVE (academic anchor) |
| Example sentences | ACF repeater: `aiwa_example_sentences` | DVE |

**Operational fields — editable in WordPress by authorized dictionary administrators.**

| Field | Purpose |
|---|---|
| Post status (publish/draft) | Visibility control |
| Entry lifecycle status | active / deprecated / merged / hidden / withdrawn |
| Merge target UUID | If deprecated or merged, points to canonical entry |
| Featured flag | Highlights entry in browse/word-of-day pool |
| API eligibility flag | Enables or disables entry in REST API responses |
| Workbook inclusion flag | Marks entries approved for WordPad workbook export |
| Cache invalidation controls | Force-refresh cached API responses |
| Public display notes | Editorial notes visible to end users |
| Internal admin notes | Notes not exposed via API |

---

## 6. UUID Architecture

- `aiwa_entry_uuid` is minted by DVE at the point of approval.
- It is the canonical cross-suite lexical identifier — used by games, workbooks, audio tools, and any external system referencing a dictionary entry.
- The WordPress post ID is local storage identity only. It is not a lexical identifier.
- The URL slug is the human-readable routing identity.
- The dictionary **never** generates or regenerates a UUID for an approved entry.
- If a UUID is missing from an import batch, the entry must be rejected and returned to DVE.

```
DVE UUID    = canonical lexical identity (immutable, cross-suite)
WP post ID  = local storage identity (not portable)
Slug        = human-readable routing identity (derived from headword at import)
```

---

## 7. Intake Mechanism — Version 1

Publication is deliberate and rare, and it is **manual by design**. There is no automated
import path into the dictionary in v1, and building one is out of scope — the governance
model requires a human to be accountable for every entry that becomes public.

The intake pipeline is:

1. DVE completes intake, review, normalization, and approval upstream
2. An approved entry is reviewed a second time by a dictionary operator
3. The operator enters or updates the record in WordPress, preserving the DVE-minted
   `aiwa_entry_uuid` exactly
4. The operator publishes the entry

The Approved Entry Package format in
`3IATLAS-DICTIONARY-APPROVED-ENTRY-SPEC-v1.0.md` defines **what a complete approved
entry contains** — required fields, relation types, speaker-community terms, lifecycle
states. It is the review checklist and the field contract. It is not a file format
consumed by any automated importer, because no such importer exists.

**No WP-CLI importer.** Earlier revisions of this spec documented
`wp aiwa-dictionary import --file=… --dry-run|--publish`. That command was never
implemented and must not be cited as though it were. `src/cli/` registers API-key
management commands only.

**Version 2 (deferred):** A service-to-service push endpoint
(`POST /sparxstar/v1/dictionary/import`, service auth) delivering into a staging queue,
with a human publish step before entries go live, remains a possible future. Do not build
it in v1. The governance model is not yet mature enough for automatic publishing, and the
manual boundary is the control that substitutes for it.

---

## 8. Entry Lifecycle States

Once imported, an entry has a lifecycle managed through operational fields, not linguistic fields.

| State | Meaning |
|---|---|
| `active` | Published and served via API. Default state after import. |
| `deprecated` | Superseded by a newer approved entry. UUID preserved. Not served in primary results. |
| `merged` | Identified as duplicate. Merged into a canonical entry (referenced by merge target UUID). UUID preserved for historical continuity. |
| `hidden` | Temporarily removed from API without correction. Operational decision. |
| `withdrawn` | Approved entry retracted after publication. Requires DVE authority. UUID preserved. |

**Entries are never silently deleted.** Games, workbooks, audio assets, citations, and printed materials may reference entry UUIDs. Historical continuity is required. When a game session recorded a score against a UUID, that UUID must remain resolvable forever, even if the entry is deprecated or merged.

---

## 9. Correction Types

**Replacement Package**
DVE has corrected one or more linguistic fields on a previously approved entry. The replacement package contains the same UUID and the corrected field values. The dictionary importer overwrites only the linguistic fields specified in the replacement. The UUID, lifecycle state, and operational fields are preserved.

**Deprecation / Merge Package**
DVE has determined that an entry is a duplicate, superseded, or should be consolidated. The package specifies the affected UUID, the new lifecycle state (`deprecated` or `merged`), and the canonical target UUID if merging. The entry's linguistic fields are frozen in their last approved state and remain resolvable for historical reference.

---

## 10. Governance and Edit Lock Rules

1. WordPress administrators may not edit linguistic fields after import via the standard ACF edit UI.
2. All linguistic fields on the dictionary CPT must be rendered read-only in the WordPress admin after the entry is imported.
3. The only paths to change a linguistic field are:
   - A DVE replacement package imported via WP-CLI
   - An emergency admin override with documented DVE authorization and an audit log entry
4. Operational fields (visibility, lifecycle status, featured flag, API eligibility) may be edited directly by authorized dictionary administrators.
5. The `aiwa_entry_uuid` field is never editable by any WordPress role under any circumstances.
6. No plugin, WordPress admin action, or REST API endpoint may overwrite `aiwa_entry_uuid` after initial import.

---

## 11. What the Dictionary Serves

The dictionary receives data. It does not generate linguistic data. It serves:

- REST API (9 active endpoints: lookup, search, wordlist, languages, domains, game-set, word-of-day, spell, page-token)
- GraphQL (browse app index)
- WP-CLI key management
- 3iAtlas suite: RLC game service, WordPad, S2S
- External API consumers via consumer API key

The dictionary does not:
- Route content for quality control
- Generate signals to Esu or any other downstream system (game service handles that)
- Flag entries for review
- Classify incoming submissions
- Mint governance tokens

---

## 12. Relationship to DVE

DVE is the upstream lexical validation and onboarding pipeline. The dictionary does not accept raw community submissions directly as authoritative entries. The dictionary imports approved lexical records from DVE, preserves canonical identifiers, stores approved metadata, locks linguistic fields after import, and exposes the records through search, browse, game, workbook, and API services.

The dictionary crew builds the approved dictionary service. The linguistic justice system lives upstream.
