# AIWA Dictionary — Enrichment Fields Spec

**Version:** 1.0
**Status:** Active
**Scope:** sparxstar-3iatlas-dictionary
**Authority:** Max Barrett / Starisian Technologies
**Date:** June 2026

---

## 1. Purpose

This spec defines the enrichment fields that give the dictionary its pedagogical and research depth. These fields transform the dictionary from a word store into a curriculum-aware, academically grounded lexical engine.

All enrichment fields are assigned upstream in DVE and arrive in the Approved Entry Package. The dictionary stores and serves them. It does not generate, infer, or assign enrichment values.

---

## 2. AIWA Level

**Field:** `aiwa_level`
**ACF type:** Select
**Authority:** DVE, subject to AIWA curriculum board confirmation

AIWA Level is the primary public-facing educational grading field. It is a sovereign educational scale designed for the Gambian and West African context. It is not CEFR. It is not Oxford. It draws on those frameworks as reference points only.

The public curriculum field is AIWA's. Not Europe's.

**Scale:**

| Value | Label | Description |
|---|---|---|
| `AIWA-0` | Picture / First Exposure | Pre-literate vocabulary. Taught through image and audio. Early childhood and first-contact literacy. |
| `AIWA-1` | Beginner Survival | Core survival vocabulary: greetings, numbers, body, family, immediate environment. First reading and writing. |
| `AIWA-2` | Everyday Sentence | Vocabulary for everyday conversation and simple sentences: markets, home, community. |
| `AIWA-3` | Storytelling and Explanation | Vocabulary for oral narrative, explanation, and basic description. School subjects, community life. |
| `AIWA-4` | School, Civic, Formal | Abstract and formal vocabulary: school curriculum, civic life, governance, structured writing. |
| `AIWA-5` | Literary, Technical, Specialist | Literary vocabulary, specialist and technical terminology, historical and cultural depth. |

**Note:** This scale is the proposed working version pending AIWA curriculum board final confirmation. The definition of each level against actual Gambian school grades and curriculum standards is a content/curriculum call for AIWA and its language board, not an engineering decision. The field is present in the schema; the definitions are AIWA's authority.

**Null behavior:** An entry without `aiwa_level` is ungraded. Game service queries filtering by AIWA level must not return ungraded entries as fallback. Ungraded entries are valid for open-level play only.

---

## 3. CEFR Approximation

**Field:** `aiwa_cefr_approx`
**ACF type:** Select (optional)
**Authority:** DVE — reference mapping only

An optional secondary field recording the approximate CEFR equivalent of the entry's AIWA Level. Provided for interoperability with educational systems that require CEFR grading.

Valid values: `A1`, `A2`, `B1`, `B2`, `C1`, `C2`

CEFR approximation is informational. It does not override AIWA Level. It is not the published grading field. If `aiwa_cefr_approx` conflicts with `aiwa_level`, the AIWA Level governs.

Approximate mapping (reference only, not normative):
- AIWA-0 → Pre-A1
- AIWA-1 → A1
- AIWA-2 → A2
- AIWA-3 → B1
- AIWA-4 → B2
- AIWA-5 → C1 / C2

---

## 4. Oxford Tier

**Field:** `aiwa_oxford_tier`
**ACF type:** Checkbox (optional)
**Authority:** DVE — English reference field

An optional field recording whether the English translation of this entry appears in the Oxford 3000 or Oxford 5000 word lists. This is an indirect, English-reference enrichment field — not a direct linguistic assessment of the entry's own language.

Valid values: `oxford_3000`, `oxford_5000` (not mutually exclusive — 3000 is a subset of 5000)

This field is useful for:
- Learners with English as a background language who want to know if they already know the English equivalent
- Curriculum alignment with English-medium educational materials
- Cross-suite interoperability with English-focused learning tools

**Future intent:** Once AIWA publishes a native Gambian core vocabulary list, a field `aiwa_core_vocabulary_tier` will represent the equivalent concept without the Oxford dependency. The Oxford tier field will remain for backward compatibility and English-reference value.

---

## 5. Semantic Domain

**Field:** `aiwa_domain` (existing taxonomy, expanded)
**Authority:** DVE

The semantic domain taxonomy categorizes entries by subject area and cultural domain. Used by the game service for domain-filtered word sets and by curriculum tools for subject-matter lesson building.

The taxonomy is hierarchical. Top-level domains cover broad subject areas. Child terms allow specific filtering.

**Proposed domain structure (expand as corpus grows):**

```
body
  ├── body-parts
  ├── health
  └── illness

family
  ├── immediate-family
  ├── extended-family
  └── community-relations

nature
  ├── animals
  │   ├── domestic-animals
  │   └── wild-animals
  ├── plants
  ├── land-and-water
  └── weather

food
  ├── ingredients
  ├── prepared-food
  └── cooking

time
  ├── days-and-months
  ├── seasons
  └── daily-routine

numbers-and-quantity

colors

home
  ├── household-objects
  └── building-and-construction

community
  ├── civic-life
  ├── governance
  └── market-and-trade

education

religion-and-ceremony

emotion-and-cognition

movement-and-direction

speech-and-communication

tools-and-craft

cultural-heritage
  ├── oral-tradition
  ├── music
  └── ceremony
```

Domain taxonomy terms are assigned by DVE. Game service queries using `domain=animals` receive entries tagged with the `animals` term or any child term (`domestic-animals`, `wild-animals`). Hierarchical inheritance in queries is supported.

---

## 6. Synonyms and Antonyms

**Fields:** `aiwa_synonyms`, `aiwa_antonyms` (existing ACF relationship fields)
**Authority:** DVE

These fields are existing ACF relationship fields linking to other entries in the same CPT. Both fields already exist in the schema.

- `aiwa_synonyms`: entries with similar or equivalent meaning in the same language
- `aiwa_antonyms`: entries with opposite or contrasting meaning in the same language

These fields carry intra-language relationships. Cross-language related entries belong in `aiwa_cross_language_siblings`, not here.

---

## 7. Rhyme Entries

**Field:** `aiwa_rhyme_entries`
**ACF type:** Relationship (new field)
**Authority:** DVE — editorial curation

An ACF relationship field linking entries that rhyme with this entry in the source language. Rhyme is phonologically defined (based on IPA endings) but the relationship is editorially confirmed — no automatic generation.

Used by:
- Rhyme-based word games
- Oral tradition teaching — Mandinka, Wolof, and Fula oral poetry and story traditions use rhyme and rhythm
- BaobabBoom literary content
- Audio-first learning modes

This field connects the dictionary to the living oral tradition, not just the written word. It is not a game mechanic footnote — it is the linguistic bridge between the lexical archive and the cultural practice.

---

## 8. Concepticon ID

**Field:** `aiwa_concepticon_id`
**ACF type:** Number (optional)
**Authority:** DVE — academic anchor, assigned by qualified linguists

An integer reference to the Concepticon database (concepticon.clld.org). Concepticon is an open cross-linguistic concept list resource that provides stable identifiers for linguistic concepts across language families.

Use:
- Links this entry to a globally recognized concept identifier
- Enables cross-language concept queries (find all Mandinka entries that map to the same concept as a given Wolof entry)
- Connects the dictionary to international linguistics research

This is sparse academic metadata. Many entries will not have a Concepticon ID at launch. The field accepts null and API consumers must handle null gracefully. Concepticon IDs are assigned by linguists with academic training in cross-linguistic concept mapping — not by community contributors or automated processes.

---

## 9. CLICS ID

**Field:** `aiwa_clics_id`
**ACF type:** Text (optional)
**Authority:** DVE — academic anchor, assigned by qualified linguists

A reference to the CLICS database (clics.clld.org — Cross-Linguistic Colexifications). CLICS records when multiple concepts share a single word form across language families.

Use:
- Documents cross-linguistic colexification patterns for African languages
- Provides the academic backing for the Speaker Community Layer — explains *why* certain concepts travel across language communities
- Connects to open linguistic research (CLICS is CC-BY 4.0 licensed)

Like Concepticon ID, this is sparse academic metadata. Many entries will not have a CLICS ID. API consumers must handle null.

---

## 10. Example Sentences

**Field:** `aiwa_example_sentences` (existing ACF repeater)
**Authority:** DVE

Existing repeater field. Each row contains:

| Sub-field | Description |
|---|---|
| `aiwa_sentence_example` | The sentence in the source language |
| `aiwa_sentence_ipa` | IPA pronunciation of the full sentence |
| `aiwa_sentence_phonetic` | Phonetic pronunciation in plain text |
| `aiwa_sentence_english` | English translation |
| `aiwa_sentence_french` | French translation |

---

## 11. API Exposure of Enrichment Fields

### DictionaryEntry shape — full field set

The `DictionaryEntry` shape returned by `/lookup` includes all enrichment fields when present. New fields added to the existing shape:

```
aiwa_level: string | null          — e.g. "AIWA-2"
cefr_approx: string | null         — e.g. "A2"
oxford_tier: string[] | null       — e.g. ["oxford_3000"]
concepticon_id: number | null
clics_id: string | null
rhymes: Array<{ uuid, headword, slug }>
```

### Game set and wordlist filter params

New filter parameters added to `/game-set` and `/wordlist`:

| Param | Values | Behavior |
|---|---|---|
| `aiwa_level` | Comma-separated AIWA level values | Returns entries at these levels only. Ungraded entries excluded. |
| `cefr` | Comma-separated CEFR values | Returns entries matching CEFR approximation. Entries without `aiwa_cefr_approx` excluded. |
| `oxford_tier` | `oxford_3000` or `oxford_5000` | Returns entries tagged with this Oxford tier. |
| `domain` | Domain taxonomy slug | Returns entries in this domain (including child terms). |

Example — Grade 2 game set request:
```
GET /game-set?lang_source=mandinka&aiwa_level=AIWA-1,AIWA-2&domain=animals&include_audio=true&limit=10&mode=strict
```

### Game set meta — updated shape

```json
"meta": {
  "total": 10,
  "lang_source": "mandinka",
  "domain": "animals",
  "aiwa_level": ["AIWA-1", "AIWA-2"],
  "oxford_tier": null,
  "include_audio": true,
  "mode": "strict"
}
```

### Search filter params

New optional filter params on `/search`:

| Param | Values | Behavior |
|---|---|---|
| `aiwa_level` | Comma-separated AIWA level values | Filters results to specified levels |
| `domain` | Domain taxonomy slug | Filters results to specified domain |
| `speaker_community` | Community taxonomy slug | Used with `mode=ecology` |

---

## 12. Field Assignment Summary

| Field | Assigned By | Editable in Dictionary? |
|---|---|---|
| AIWA Level | DVE | No — linguistically locked |
| CEFR Approximation | DVE | No |
| Oxford Tier | DVE | No |
| Concepticon ID | DVE (qualified linguist) | No |
| CLICS ID | DVE (qualified linguist) | No |
| Domain | DVE | No |
| Synonyms / Antonyms | DVE | No |
| Rhyme Entries | DVE (editorial) | No |
| Cross-language Siblings | DVE | No |
| Speaker Community Tags | DVE, AIWA review | No — corrections via DVE package |
| Community Usage Status | DVE, AIWA board | No — promoted via DVE/AIWA |
| Example Sentences | DVE | No |
