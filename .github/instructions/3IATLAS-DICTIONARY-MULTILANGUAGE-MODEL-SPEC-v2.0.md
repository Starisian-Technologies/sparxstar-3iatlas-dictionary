# AIWA Dictionary — Multilanguage Model Spec

**Version:** 2.0
**Status:** Active
**Scope:** sparxstar-3iatlas-dictionary
**ADR References:** ADR-002, ADR-003, ADR-006, INV-006, INV-007
**Supersedes:** `3IATLAS-DICTIONARY-MULTILANGUAGE-MODEL-SPEC-v1.0.md`
**Authority:** Max Barrett / Starisian Technologies
**Date:** June 2026

**What changed from v1.0:**
- Soup model (borrowing_status) added as a first-class field distinct from speaker community layer
- Language model type (root_first / word_first / morpheme_first) added as a per-language configuration
- Comparative relationships (Zone 2) distinguished explicitly from editorial sibling links (Zone 1)
- Recurrence promotion rule referenced (ADR-006, INV-006)
- Connection types clarified (INV-007)

---

## 1. The Core Principle

Language of origin and language of use are not the same field. For West Africa, that distinction is not optional. It is the difference between a dictionary that preserves language correctly and a dictionary that reflects how people actually speak.

AIWA maintains a **three-layer language model**:

1. **Language Model Type** — how a language organizes its primary lexical units (root_first, word_first, morpheme_first)
2. **Primary Language Layer** — the source language of an entry for archival, educational, and strict learning purposes
3. **Speaker Community Layer** — which communities use, recognize, borrow, adapt, or encounter a word in lived speech

These layers are independent. A word may have Wolof as its primary language while also being tagged as used by Mandinka speakers in specific Gambian contexts. And a word that is borrowed-integrated may be native to neither community in the strict linguistic sense but be in full active use by both.

---

## 2. Language Model Type (Per-Language Configuration)

Each language in the corpus is configured with a model type that governs primary navigation, API response shape, data entry interface, and visualization layer.

| Model Type | Primary Unit | Examples |
|---|---|---|
| `root_first` | Root families | Mandinka, Bambara, Chinese |
| `word_first` | Individual words | English, French |
| `morpheme_first` | Morpheme sets with inflectional paradigms | Fula, Swahili, Arabic |

This configuration is stored on the language record. It is set by DVE at language onboarding and is not editable in the dictionary.

**root_first languages:** Primary navigation is by root family — root record, all applications, all compounds, background resonances. The single-word view remains available but is secondary. See `3IATLAS-GENERATIVE-ROOT-DATA-MODEL-SPEC-v2.0`.

**word_first languages:** Primary navigation is by word. The English scaffold operates in this mode. It is the interoperability layer, not the model for any African language in the corpus.

**morpheme_first languages:** Reserved for Phase 3.5. See ADR-004 and `3IATLAS-DATA-ZONES-AND-TRUST-BOUNDARY-SPEC-v1.0`.

---

## 3. Primary Language Layer

Each entry has exactly one primary language — the language to which the entry belongs linguistically. This is the authoritative archival and educational classification.

- Stored as: taxonomy `starmus_tax_language`, single term per entry
- Set by: DVE, preserved immutably in the dictionary after import
- Used by: game service (strict mode), curriculum alignment, wordlist filtering, formal language learning

The primary language is the answer to: *where does this word belong linguistically?*

It is not the answer to: *who uses this word?* (Speaker Community Layer) or *how did this word arrive?* (Soup Model).

---

## 4. Soup Model: Borrowing Status

The `borrowing_status` field describes how a word sits in the living language ecosystem. This is the "soup model" — the dictionary records the living linguistic reality, not an idealized archive.

**Field:** `aiwa_borrowing_status`
**Authority:** DVE — set at import, linguistically locked
**Applies to:** all `aiwa-entry` records, all `aiwa-compound` records

| Value | Meaning |
|---|---|
| `native` | Word form and meaning originate in this language's ancestral stock |
| `borrowed_integrated` | Borrowed from another language; fully integrated — speakers use it without awareness of foreign origin |
| `borrowed_active` | Recognized as borrowed but in active daily use |
| `code_mixed` | Appears primarily in code-mixed speech; not yet stable in formal register |
| `archaic` | Historical form; in corpus but not in living daily use |
| `neologism` | Newly coined; active in contemporary usage but not yet established |
| `contested` | Disputed — some community members accept it, some reject it |

**Distinction from speaker community layer:** The speaker community layer records *who uses a word*. The borrowing status records *how that word came to be used there*. A `borrowed_integrated` word may be tagged for multiple speaker communities — the borrowing status explains the history; the speaker community tags explain the current distribution.

**Distinction from Zone 2 borrowing events:** Zone 2 `borrowing_events` are academic comparative records with evidence type, dating, and source references — created by qualified linguists. The `borrowing_status` field on a Zone 1 entry is a simpler editorial classification for the dictionary's display and filtering purposes. They are related but distinct records.

---

## 5. Speaker Community Layer

An entry may be tagged with one or more speaker communities — the communities that use, recognize, or encounter this word in lived speech, regardless of its primary language.

- Stored as: taxonomy `aiwa_speaker_community` (controlled vocabulary)
- Each tag carries a `community_usage_status`: `observed`, `speaker_confirmed`, or `editorial_approved`
- Set by: DVE, with AIWA review authority for `editorial_approved` status
- Used by: browse app (ecology mode), adult literacy, real-speech exploration, community dictionary contexts

The full taxonomy of valid speaker community terms is in `3IATLAS-DICTIONARY-APPROVED-ENTRY-SPEC-v1.0` §5. Terms are controlled and governed by AIWA. Freeform tags are not permitted.

---

## 6. Separating the Concepts

Each question is answered by a different field. No single field answers more than one question.

| Question | Field |
|---|---|
| What is the normalized form? | Normalized headword (post title) |
| How does this language organize its primary units? | Language model type (language record) |
| What language does this entry belong to? | Primary language taxonomy |
| How did this word arrive in the language? | `aiwa_borrowing_status` (soup model) |
| Who uses or recognizes this word? | Speaker community taxonomy |
| How is this word related to words in other languages (editorially)? | Cross-language siblings (Zone 1) |
| How is this word related to words in other languages (academically)? | Cognate sets / borrowing events (Zone 2) |
| What is the meaning? | Definition + glosses + Concepticon ID |
| How is this word pronounced? | IPA + phonetic + audio |

---

## 7. Cross-Language Siblings (Zone 1) vs Comparative Records (Zone 2)

Two distinct mechanisms connect entries across languages. They are not interchangeable.

### Zone 1: Editorial Sibling Links (`aiwa_cross_language_siblings`)

Editorial relationships set by DVE at the entry level. These are curatorial acts — a DVE editor has determined that these entries are related and specified the relationship type.

Valid relation types: `same_concept`, `loanword`, `cognate`, `shared_regional_usage`, `religious_term`, `market_usage`, `school_usage`, `false_friend`, `uncertain`

**INV-007:** Connection types are distinct and never collapse into one edge type. A loanword link and a cognate link express different historical claims. They must not be merged into a generic "related" edge.

A `false_friend` relationship is as important to record as a `same_concept` relationship. Knowing that two words look similar but mean different things is a teaching asset and a data integrity safeguard.

**No concept-node model at this stage.** Sibling links are flat and editorial. If enough sibling links cluster around a concept, a formal concept node may be introduced in a future spec. Not now.

### Zone 2: Academic Comparative Records

Cognate judgments, borrowing events, and sound correspondences live in Zone 2 with methodology notes, evidence types, and linguist attribution. These are academic claims, not editorial tags. Full schemas in `3IATLAS-DATA-ZONES-AND-TRUST-BOUNDARY-SPEC-v1.0`.

**ADR-006 / INV-006:** A word appearing repeatedly across languages does not become "cognate" or "ancient" by recurrence alone. Promotion to a cognate judgment requires a qualified linguist's assessment with methodology. Recurrence is a signal, not a judgment.

---

## 8. Search Modes

Search and wordlist endpoints expose three explicit modes. Mode is never implicit. Consumers must declare what they want.

### mode=strict (default)

Returns only entries where the primary language taxonomy matches `lang_source`.

- Use for: games, quizzes, formal language lessons, school workbooks, certified learning sets
- The game service **must always use strict mode**
- Unset mode defaults to strict

```
GET /wordlist?lang_source=mandinka&mode=strict
GET /game-set?lang_source=mandinka&mode=strict
GET /search?q=market&lang_source=mandinka&mode=strict
```

### mode=ecology

Returns primary language matches first, then entries where the speaker community taxonomy includes the specified community.

- Use for: browse app, community dictionary, adult literacy, real-speech exploration
- Results labeled to distinguish primary language matches from speaker community matches
- `observed` and `speaker_confirmed` speaker community tags are included but flagged; only `editorial_approved` is shown as confirmed

```
GET /search?q=market&speaker_community=mandinka-speakers&mode=ecology
GET /wordlist?speaker_community=mixed-urban-gambia&mode=ecology
```

Ecology mode response shape:

```json
{
  "match_type": "primary_language | speaker_community",
  "community_usage_status": "observed | speaker_confirmed | editorial_approved",
  "borrowing_status": "native | borrowed_integrated | ..."
}
```

### mode=cross_language

Returns entries linked via cross-language sibling relationships to the requested entry.

- Use for: word detail views, S2S sentence building, WordPad context generation
- Results include the sibling's relation type

```
GET /lookup?slug=some-mandinka-word&mode=cross_language
```

---

## 9. The No-Silent-Mixing Rule

Results from different layers must never be combined without labeling. A search result that does not tell the user whether a word is primary Mandinka or "Mandinka speakers also use this Wolof word" is misleading and culturally inaccurate.

Required display labels (exact rendering is consumer's responsibility):

| Match type | Label |
|---|---|
| `primary_language` | "Mandinka Word" |
| `speaker_community` + `editorial_approved` | "Also Used by Mandinka Speakers" |
| `speaker_community` + `speaker_confirmed` | "Reported Used by Mandinka Speakers" |
| `speaker_community` + `observed` | "Observed in Mandinka Speaker Contexts" |
| Cross-language sibling, `same_concept` | "Related Wolof Word — Same Concept" |
| Cross-language sibling, `loanword` | "Borrowed Word" |
| Cross-language sibling, `false_friend` | "Looks Similar — Different Meaning" |
| `borrowing_status: borrowed_integrated` | "Integrated Borrowing" |
| `borrowing_status: code_mixed` | "Code-Mixed Usage" |

Not labeling is a product violation of this spec.

---

## 10. JWT Language Claims

The 3iAtlas Identity Service must encode language identity as four distinct claims, not one. This aligns the authentication layer with the three-layer language model.

```
primary_language:     mandinka                               — mother tongue / linguistic identity
speaker_communities:  [mandinka-speakers, mixed-urban-gambia] — lived usage communities
learning_languages:   [wolof, english]                       — languages under active study
interface_language:   english                                — UI display language preference
```

A JWT carrying only `language: mandinka` is the Oxford model encoded in auth. That is not what AIWA is building. This is a requirement for the Identity Service spec.

---

## 11. Game Service Constraints

The game service is a strict-mode consumer. It may not use ecology mode or cross_language mode to populate game word sets.

A game teaching Mandinka vocabulary must contain only primary-language-Mandinka entries. Cross-language contamination in a learning set is a curriculum integrity failure.

The game service may use cross-language sibling data for cultural context display *after* a game interaction (e.g., "This Mandinka word has a Wolof cognate — see also [link]"). This is enrichment display, not game set selection.

---

## Version History

| Version | Date | Changes |
|---|---|---|
| 1.0 | June 2026 | Initial specification |
| 2.0 | June 2026 | Language model type per-language config added. Soup model (borrowing_status) added as first-class field. Zone 1 editorial siblings vs Zone 2 academic comparative records clarified. Recurrence promotion rule (ADR-006/INV-006). Connection type distinctness (INV-007). |
