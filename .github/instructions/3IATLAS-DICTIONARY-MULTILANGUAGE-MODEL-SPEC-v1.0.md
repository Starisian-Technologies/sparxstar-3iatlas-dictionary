# AIWA Dictionary — Multilanguage Model Spec

**Version:** 1.0
**Status:** Active
**Scope:** sparxstar-3iatlas-dictionary
**Authority:** Max Barrett / Starisian Technologies
**Date:** June 2026

---

## 1. The Core Principle

Language of origin and language of use are not the same field. For West Africa, that distinction is not optional. It is the difference between a dictionary that preserves language correctly and a dictionary that reflects how people actually speak.

AIWA will maintain a two-layer language model.

**The Primary Language Layer** identifies the source language of an entry for archival, educational, linguistic, and strict learning purposes.

**The Speaker Community Layer** identifies which communities use, recognize, borrow, adapt, or encounter a word in lived speech.

These layers are independent. A word may have Wolof as its primary language while also being tagged as used by Mandinka speakers in specific Gambian contexts. Strict learning products must use the Primary Language Layer only. Browse, literacy, and real-speech products may opt into the Speaker Community Layer with clear labeling.

---

## 2. Primary Language Layer

Each entry has exactly one primary language — the language to which the entry belongs linguistically. This is the authoritative archival and educational classification.

- Stored as: taxonomy `starmus_tax_language`, single term per entry
- Set by: DVE, preserved immutably in the dictionary once entered
- Used by: game service (strict mode), curriculum alignment, wordlist filtering, formal language learning

The primary language is the answer to: *where does this word belong linguistically?*

It is not the answer to: *who uses this word?* That is the Speaker Community Layer.

---

## 3. Speaker Community Layer

An entry may be tagged with one or more speaker communities — the communities that use, recognize, or encounter this word in lived speech, regardless of its primary language.

- Stored as: taxonomy `aiwa_speaker_community` (controlled vocabulary)
- Each tag carries a `community_usage_status`: `observed`, `speaker_confirmed`, or `editorial_approved`
- Set by: DVE, with AIWA review authority for `editorial_approved` status
- Used by: browse app (ecology mode), adult literacy, real-speech exploration, community dictionary contexts

The speaker community layer answers: *who actually uses this word, and in what context?*

The full taxonomy of valid speaker community terms and their definitions is in the Approved Entry Package Spec §5. Terms are controlled and governed by AIWA. Freeform tags are not permitted.

---

## 4. Separating the Concepts

Each of these questions is answered by a different field. No single field answers more than one question.

| Question | Field |
|---|---|
| What is the normalized form of this word? | Normalized headword (post title) |
| What language does this word belong to? | Primary language taxonomy |
| Who uses or recognizes this word? | Speaker community taxonomy |
| How is this word related to words in other languages? | Cross-language siblings |
| What is the meaning or concept? | Definition + glosses + Concepticon ID |
| How is this word pronounced? | IPA + phonetic + audio |

These are independent. A word may be:
- Primary language: Wolof
- Speaker community: Wolof speakers, Mandinka speakers, mixed-urban-gambia
- Used in: market speech, youth speech, Banjul/Serekunda
- Teaching status: not strict Mandinka, but valid in Gambian ecology mode

That is the truth. The model expresses it without flattening it.

---

## 5. Cross-Language Siblings

Cross-language sibling links connect entries in different languages that are related. The relationship must be typed — not just "these are linked" but how they are linked.

Field: `aiwa_cross_language_siblings` — ACF relationship field returning entries + relation type.

Valid relation types and their meanings are defined in the Approved Entry Package Spec §4:
`same_concept`, `loanword`, `cognate`, `shared_regional_usage`, `religious_term`, `market_usage`, `school_usage`, `false_friend`, `uncertain`

A false_friend relationship is as important to record as a same_concept relationship. Knowing that two words look similar but mean different things is a teaching asset and a data integrity safeguard.

**No concept-node model at this stage.** Sibling links are flat and editorial. If enough sibling links cluster around a concept, a formal concept node may be introduced in a future spec. Not now.

---

## 6. Search Modes

Search and wordlist endpoints expose three explicit modes. Mode is never implicit. Consumers must declare what they want.

### mode=strict

Returns only entries where the primary language taxonomy matches `lang_source`.

- Use for: games, quizzes, formal language lessons, school workbooks, certified learning sets
- Behavior: no speaker community filtering, no cross-language expansion
- The game service must always use strict mode
- Unset mode defaults to strict

```
GET /wordlist?lang_source=mandinka&mode=strict
GET /game-set?lang_source=mandinka&mode=strict
GET /search?q=market&lang_source=mandinka&mode=strict
```

### mode=ecology

Returns primary language matches first, then entries where the speaker community taxonomy includes the specified community (regardless of primary language).

- Use for: browse app, community dictionary, adult literacy, real-speech exploration, urban Gambian communication
- Results must be labeled in the response to distinguish primary language matches from speaker community matches
- Speaker community tags with `editorial_approved` status are returned; `observed` and `speaker_confirmed` are included but flagged
- `lang_source` parameter in ecology mode is optional. `speaker_community` parameter specifies the community.

```
GET /search?q=market&speaker_community=mandinka-speakers&mode=ecology
GET /wordlist?speaker_community=mixed-urban-gambia&mode=ecology
```

Ecology mode response shape includes a `match_type` field on each result:

```json
{
  "match_type": "primary_language | speaker_community",
  "community_usage_status": "observed | speaker_confirmed | editorial_approved"
}
```

### mode=cross_language

Returns entries linked via cross-language sibling relationships to the requested entry or set.

- Use for: word detail views (show related words in other languages), S2S sentence building, WordPad context generation
- Typically used on a specific entry, not a list query
- Results include the sibling's relation type

```
GET /lookup?slug=some-mandinka-word&mode=cross_language
```

---

## 7. The No-Silent-Mixing Rule

Results from different layers must never be combined without labeling. A search result that does not tell the user whether a word is primary Mandinka or "Mandinka speakers also use this Wolof word" is misleading and culturally inaccurate.

Required display labels (example values — exact rendering is consumer's responsibility):

| Match type | Label |
|---|---|
| `primary_language` | "Mandinka Word" |
| `speaker_community` with `editorial_approved` | "Also Used by Mandinka Speakers" |
| `speaker_community` with `speaker_confirmed` | "Reported Used by Mandinka Speakers" |
| `speaker_community` with `observed` | "Observed in Mandinka Speaker Contexts" |
| Cross-language sibling, `same_concept` | "Related Wolof Word — Same Concept" |
| Cross-language sibling, `loanword` | "Borrowed Word" |
| Cross-language sibling, `false_friend` | "Looks Similar — Different Meaning" |

Consumer applications are responsible for implementing the labeling. The API provides the data to make it possible. Not labeling is a product violation of this spec.

---

## 8. JWT Language Claims

The 3iAtlas identity service (sparxstar-identity) must encode language identity as four distinct claims, not one. This aligns the authentication layer with the two-layer language model.

```
primary_language:     mandinka        — mother tongue / linguistic identity
speaker_communities:  [mandinka-speakers, mixed-urban-gambia]  — lived usage
learning_languages:   [wolof, english]  — languages under active study
interface_language:   english          — UI display language preference
```

A JWT carrying only `language: mandinka` is the Oxford model encoded in auth. That is not what AIWA is building. This is a requirement for `3IATLAS-IDENTITY-SERVICE-SPEC-v1.0` (currently in the spec writing queue, not yet committed).

---

## 9. Game Service Constraints

The RLC game service is a strict-mode consumer. It may not use ecology mode or cross-language mode to populate game word sets.

A game teaching Mandinka vocabulary must contain only primary-language-Mandinka words. Cross-language contamination in a learning set is a curriculum integrity failure.

The game service parameter: `lang_source` + `mode=strict` (or omit mode, which defaults to strict).

The game service may use cross-language sibling data for cultural context display *after* a game interaction (e.g., "This Mandinka word has a Wolof cognate — see also [link]"). This is enrichment, not the game set itself.
