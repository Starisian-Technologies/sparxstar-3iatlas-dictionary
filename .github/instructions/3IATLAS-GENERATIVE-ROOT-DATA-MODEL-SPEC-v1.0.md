# 3iAtlas Generative Root Data Model

## Specification v1.0

**Status:** Approved  
**Scope:** Dictionary data model for all generative-root languages under the 3iAtlas platform  
**Related specs:** `3IATLAS-DICTIONARY-ROLE-AND-PIPELINE-SPEC-v1.0.md`, `3IATLAS-DICTIONARY-ENRICHMENT-FIELDS-SPEC-v1.0.md`, `3IATLAS-LITERACY-REWARD-MODEL-SPEC-v1.0.md`

---

## 1. Platform Ruling

**For generative languages, the root is the primary dictionary entity. Words are contextual applications of roots.**

This ruling is not a preference or an optimization. It is a structural correction. If a generative language like Mandinka is stored in a word-first model — one row per English translation — the platform will:

- Invent accidental homonyms where the language has a unified generative seed
- Silence background semantic resonance that native speakers actively experience
- Force the language into English grammatical categories it does not use
- Destroy the cognitive architecture the language is actually built on

The dáa example is the canonical proof. In a flat database:

```
Row: dáa → "mouth"
Row: dáa → "door"
Row: dáa → "price"
Row: dáa → "to create"
```

This model suggests that Mandinka speakers coincidentally reused the same sound for four unrelated things. That is wrong. In the Mandinka mind, these are not four words. They are one root — the threshold of transition and creation — experienced in four contexts. The platform must preserve this or it has already failed.

---

## 2. Language Model Types

Not all languages are generative in the same way. This spec applies a language-level configuration:

| Model Type       | Description                                                                     | Examples                                 |
| ---------------- | ------------------------------------------------------------------------------- | ---------------------------------------- |
| `word_first`     | Words are the primary unit. Polysemy is handled as multiple senses of one word. | English, French                          |
| `root_first`     | Roots are the primary unit. Words are contextual applications of roots.         | Mandinka, Bambara, Chinese               |
| `morpheme_first` | Morphemes are the primary unit, with rich inflectional systems.                 | Fula (20+ noun classes), Swahili, Arabic |

The `root_first` model defined in this spec applies to all Mande languages and other identified generative languages. The `word_first` model applies to European languages. The `morpheme_first` model is partially covered here and will be extended in the Grammar Spec.

This configuration is stored on the language record and controls which data entry interface, API response shape, and visualization layer the platform uses.

The English CSV scaffold (`AiWA_Semantic_Scaffold_1.csv`) operates as a `word_first` interoperability layer and is not affected by this spec. It remains the Concepticon/WordNet/CLICS/proficiency bridge for all languages, reached via Concept Links defined in § 5.

---

## 3. The Two Roles of the Dictionary

The dictionary is both an upstream scaffold and a downstream product.

```
3iAtlas
(games / WordPad / learner activity)
            ↓
         Sky
(intake, questions, readiness)
            ↓
         ESU
(transcription, translation, alignment)
            ↓
        Mēh₁n̥s
(epistemic sieve: rights, authority, confidence, export rules)
            ↓
        Dheghom
(structured storage and artifact handling)
            ↓
         DVE
(human validation and governance)
            ↓
   Dictionary / Webster
(validated language product)
            ↓
        3iAtlas
(feeds games, WordPad, drills, SRS, publishing)
```

**Before DVE validation:** the dictionary is a scaffold — a structured holding space for proposed root/application data, sourced from contributors, researchers, community members, and AI-assisted suggestions. Nothing in the scaffold is authoritative until DVE confirms it.

**After DVE validation:** the dictionary is the authoritative public language product. Validated roots and applications immediately feed 3iAtlas learning surfaces, SRS scheduling, WordPad target words, and publishing workflows.

The loop is intentional. 3iAtlas learner activity generates new language evidence. That evidence returns to DVE. DVE validates it. The dictionary grows. The circle must be governed: Mēh₁n̥s controls what becomes authoritative so the loop improves rather than hallucinates.

---

## 4. Entity Model Overview

The Generative Root Data Model introduces three entities and redesigns one.

| Entity       | Post Type                     | Purpose                                                                  |
| ------------ | ----------------------------- | ------------------------------------------------------------------------ |
| Root         | `aiwa-root` (new)             | The generative seed. Primary entry for root-first languages.             |
| Application  | `aiwa-entry` (redesigned)     | A contextual projection of a root. One application per semantic context. |
| Compound     | `aiwa-compound` (new)         | A word derived from one or more roots through productive compounding.    |
| Concept Link | (relationship, not post type) | The bridge from an Application to the Concepticon / CSV scaffold.        |

The existing `aiwa-entry` posts are **not deleted**. They are redesigned as Application records. Posts that currently have no root relationship are treated as `word_first` entries (English entries) and remain unchanged.

---

## 5. The Root Record (`aiwa-root`)

The Root Record is the primary entity for all root-first language entries. It holds the generative seed and acts as the parent of all its Applications and Compounds.

### 5.1 Definition of Conceptual Seed

> A native-speaker-authored explanation of the root's core semantic force across all its applications.

The conceptual seed is an empirical claim, not a poetic interpretation. It must be authored or confirmed by a native speaker. It must be falsifiable — it should be possible to challenge a proposed seed by showing an application it cannot explain. Seeds that cannot be validated remain in `linguist_proposed` evidence status until confirmed.

### 5.2 SCF Fields

| Field                          | Type                 | Required         | Description                                                                                                               |
| ------------------------------ | -------------------- | ---------------- | ------------------------------------------------------------------------------------------------------------------------- |
| `aiwa_root_form`               | text                 | yes              | The root as it appears in the spoken language, including diacritics and tone marks                                        |
| `aiwa_root_language`           | taxonomy (Languages) | yes              | Language this root belongs to                                                                                             |
| `aiwa_root_script`             | select               | no               | Writing system used: `latin`, `arabic`, `tifinagh`, `nko`, `latin_tonal`, `other`                                         |
| `aiwa_root_pronunciation`      | text                 | no               | IPA transcription of the root form                                                                                        |
| `aiwa_root_audio`              | file                 | no               | Audio recording of the root form by a native speaker                                                                      |
| `aiwa_root_tone_class`         | select               | no               | Tonal signature: `high`, `low`, `mid`, `rising`, `falling`, `non_tonal`, `complex`                                        |
| `aiwa_root_compounding_rules`  | textarea             | no               | Description of tonal and phonetic shifts the root undergoes in compounding. Free text until Grammar Spec formalizes this. |
| `aiwa_conceptual_seed_native`  | textarea             | yes (root_first) | The seed definition authored in the root's own language, by a native speaker                                              |
| `aiwa_conceptual_seed_english` | textarea             | yes (root_first) | English translation of the seed definition, for interoperability                                                          |
| `aiwa_seed_explanation_audio`  | file                 | no               | Audio of a native speaker explaining the seed in their own words                                                          |
| `aiwa_seed_author_type`        | select               | yes              | Who authored the seed: `native_speaker`, `fluent_speaker`, `linguist`, `community_panel`, `ai_proposed`                   |
| `aiwa_seed_confidence`         | select               | yes              | See Evidence Status values in § 8                                                                                         |
| `aiwa_dialect_regions`         | textarea             | no               | Comma-separated list of regions where this root is attested                                                               |
| `aiwa_applications`            | relationship         | —                | Links to Application (`aiwa-entry`) records that belong to this root                                                      |
| `aiwa_compounds`               | relationship         | —                | Links to Compound (`aiwa-compound`) records derived from this root                                                        |
| `aiwa_evidence_status`         | select               | yes              | Overall evidence status of the root record. See § 8.                                                                      |
| `aiwa_review_status`           | select               | yes              | Pipeline status: `draft`, `pending_speaker_review`, `pending_dvr_review`, `validated`, `rejected`, `deprecated`           |
| `aiwa_created_by`              | text                 | yes              | Contributor identifier (not stored as plaintext PII — use contributor ID)                                                 |
| `aiwa_created_at`              | date                 | yes              | Creation date                                                                                                             |

---

## 6. The Application Record (redesigned `aiwa-entry`)

An Application is one contextual projection of a Root. It answers: in what context does this root foreground this particular meaning?

Applications are the unit that connects roots to the English scaffold (Concepticon, WordNet, CLICS). They are also the unit the SRS scheduler works with — a learner learns an application, not a root. The root is the organizing structure; the application is the learning unit.

### 6.1 New SCF Fields (additions to existing `aiwa-entry`)

| Field                           | Type         | Required   | Description                                                                                                                                             |
| ------------------------------- | ------------ | ---------- | ------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `aiwa_root_id`                  | relationship | no         | Link to parent `aiwa-root` post. Null for word_first entries.                                                                                           |
| `aiwa_application_context`      | select       | root_first | Semantic context type: `anatomical`, `spatial`, `social`, `commercial`, `verbal_creative`, `temporal`, `epistemic`, `ecological`, `ceremonial`, `other` |
| `aiwa_native_gloss`             | textarea     | root_first | Brief gloss of this application in the root's language                                                                                                  |
| `aiwa_english_gloss`            | textarea     | yes        | Brief English gloss                                                                                                                                     |
| `aiwa_concepticon_id`           | number       | yes        | Concepticon concept ID. This is the bridge to the CSV scaffold and cross-language siblings.                                                             |
| `aiwa_semantic_domain`          | taxonomy     | no         | SemDom domain code (e.g. `4.3.3.3 Abandon`)                                                                                                             |
| `aiwa_background_resonances`    | repeater     | no         | See § 7                                                                                                                                                 |
| `aiwa_foreground_strength`      | select       | root_first | How central this application is to the root: `primary`, `extended_metaphor`, `compound_base`, `idiomatic`, `archaic`                                    |
| `aiwa_dialect_scope`            | textarea     | no         | Which dialect regions use this application                                                                                                              |
| `aiwa_example_sentence_native`  | textarea     | no         | Example sentence in the native language                                                                                                                 |
| `aiwa_example_sentence_english` | textarea     | no         | English translation of example sentence                                                                                                                 |
| `aiwa_example_audio`            | file         | no         | Audio recording of example sentence                                                                                                                     |
| `aiwa_application_confidence`   | select       | yes        | See Evidence Status values in § 8                                                                                                                       |
| `aiwa_evidence_status`          | select       | yes        | See § 8                                                                                                                                                 |

### 6.2 Existing fields that remain

All existing `aiwa-entry` fields (audio, IPA, phonetic, synonyms, part of speech, example sentences, SCF relationship fields) remain. The new fields are additive.

---

## 7. Background Resonance

Background resonance is the empirical claim that when a native speaker hears or uses one application of a root, other applications of the same root remain semantically active in the background. They are not turned off. The context foregrounds one application; the others remain as background resonance, enriching the communication.

This is not mystical. It is a testable claim about cognitive activation patterns in native speaker processing. It requires evidence.

### 7.1 Resonance Record Structure (repeater subfields within `aiwa_background_resonances`)

| Field                      | Type         | Description                                          |
| -------------------------- | ------------ | ---------------------------------------------------- |
| `resonance_application_id` | relationship | The sibling Application whose meaning remains active |
| `resonance_evidence_type`  | select       | How this resonance is established (see below)        |
| `resonance_confidence`     | number       | 0.0 to 1.0                                           |
| `resonance_notes`          | textarea     | Evidence notes, citations, speaker quotes            |

### 7.2 Resonance Evidence Types

| Value                | Meaning                                                                                                           |
| -------------------- | ----------------------------------------------------------------------------------------------------------------- |
| `speaker_confirmed`  | One or more native speakers explicitly confirmed this resonance is felt when the foregrounded application is used |
| `linguist_proposed`  | A linguist has proposed this resonance based on etymological or semantic analysis; not yet speaker-confirmed      |
| `community_disputed` | Resonance has been proposed but community members dispute whether it is felt by contemporary speakers             |
| `historical_note`    | Resonance was active in historical usage but may no longer be felt by contemporary speakers                       |

### 7.3 What resonance is not

Resonance does not mean all meanings are equally active at once. It does not mean a speaker cannot distinguish between applications. It means the underlying root's semantic force is present as background context even when one application is foregrounded.

When `dáa` is used to mean price, the concept of threshold is present. The negotiation is not a dry number transaction; it is a verbal crossing of a boundary. This is the resonance. It does not mean the speaker is confused about whether they are buying bread or describing a mouth.

---

## 8. Evidence and Confidence Layer

All root, application, and resonance records carry evidence status. The platform must not treat all semantic interpretations as equal.

### 8.1 Evidence Status Values

| Value                 | Meaning                                                                       | Who sets it         |
| --------------------- | ----------------------------------------------------------------------------- | ------------------- |
| `speaker_confirmed`   | A native speaker has confirmed this record is accurate                        | DVE speaker review  |
| `community_confirmed` | Multiple speakers from the dialect region have confirmed                      | DVE community panel |
| `linguist_proposed`   | A linguist has proposed this based on analysis; awaiting speaker confirmation | Contributor         |
| `ai_suggested`        | An AI system generated this record; requires human review before use          | AI pipeline         |
| `disputed`            | Record exists but is actively contested by speakers or linguists              | DVE dispute process |
| `deprecated`          | Record was once active but is no longer considered accurate                   | DVE governance      |

### 8.2 Graduation path

```
ai_suggested → linguist_proposed → speaker_confirmed → community_confirmed
                                                     ↘ disputed → deprecated (if dispute sustained)
```

Records below `speaker_confirmed` are visible internally and in research views. Only `speaker_confirmed` and `community_confirmed` records feed public-facing 3iAtlas surfaces, games, and SRS.

---

## 9. The Compound Record (`aiwa-compound`)

Compounds are words formed by combining two or more roots (or a root with a grammatical element). They are distinct from Applications because they have their own phonetic form and often undergo tonal compactness changes.

### 9.1 SCF Fields

| Field                         | Type         | Description                                                                    |
| ----------------------------- | ------------ | ------------------------------------------------------------------------------ |
| `aiwa_compound_form`          | text         | The compound word as spoken, with tone marks                                   |
| `aiwa_compound_language`      | taxonomy     | Language                                                                       |
| `aiwa_compound_audio`         | file         | Audio of the compound                                                          |
| `aiwa_morphology`             | repeater     | Ordered list of roots/morphemes: `{root_id, form, role}`                       |
| `aiwa_tonal_change`           | textarea     | Description of tonal shifts that occurred in compounding                       |
| `aiwa_compound_gloss_native`  | textarea     | Gloss in the native language                                                   |
| `aiwa_compound_gloss_english` | text         | English translation                                                            |
| `aiwa_compound_gloss_literal` | text         | Literal morpheme-by-morpheme translation (e.g. "mouth-character" for daajikoo) |
| `aiwa_concepticon_id`         | number       | Concepticon link if the compound maps to a universal concept                   |
| `aiwa_semantic_domain`        | taxonomy     | Domain                                                                         |
| `aiwa_evidence_status`        | select       | Evidence status                                                                |
| `aiwa_parent_roots`           | relationship | All `aiwa-root` records that contribute to this compound                       |

### 9.2 Canonical Mandinka compound examples

| Compound    | Literal gloss   | Morphology          | Meaning            |
| ----------- | --------------- | ------------------- | ------------------ |
| `daajikoo`  | mouth-character | dáa + jikoo         | behavior, conduct  |
| `daaturoo`  | mouth-stopper   | dáa + turu          | lip                |
| `daakuloo`  | mouth-bone      | dáa + kuloo         | edge, sideline     |
| `Daamansoo` | creation-king   | dáa (verb) + mansoo | The Creator        |
| `kuntiyo`   | head-owner      | kŭn + tiyo          | leader, chief      |
| `kunino`    | head-wokeness   | kŭn + in            | wisdom, awareness  |
| `kuntano`   | headless        | kŭn + -tan          | foolish person     |
| `kunfin`    | dark head       | kŭn + fin           | illiterate person  |
| `bondi`     | cause-to-exit   | bó + -ndi           | to remove, extract |

---

## 10. Concept Link (Application → Concepticon)

Every Application record connects to the English scaffold via its `aiwa_concepticon_id`. This link is the bridge between the native-language root graph and the universal concept layer.

The Concept Link enables:

- Cross-language sibling discovery (which Wolof, Fula, Arabic, Chinese words express the same concept)
- Colexification analysis (which concepts the root bundles together across its applications — this is what the CLICS data measures)
- CEFR and AIWA level assignment (via the English scaffold row for that concept)
- WordNet semantic relationships (hypernyms, hyponyms, meronyms) inherited from the English scaffold

When a Mandinka Application links to Concepticon ID `1290` (MOUTH), it immediately inherits all cross-language siblings in the CSV for MOUTH — including every other language that has contributed that concept. Chinese `zuǐ`, Wolof `bët`, French `bouche`, Arabic `fam` are all reachable through the same Concepticon node.

The path from Mandinka `dáa` (anatomical) to Chinese `zuǐ` (mouth) already exists the moment the Concepticon link is made.

---

## 11. API Shape: Root Family Retrieval

The primary API endpoint for root-first language entries returns the full root family, not a single word definition.

### `GET /sparxstar/v1/dictionary/root/{root_id}`

Response:

```json
{
  "root": {
    "id": "aiwa-root-001",
    "form": "dáa",
    "pronunciation": "/dáː/",
    "tone_class": "high",
    "language": "Mandinka",
    "conceptual_seed": {
      "native": "...",
      "english": "the threshold of transition and creation",
      "confidence": "speaker_confirmed",
      "author_type": "native_speaker"
    },
    "evidence_status": "speaker_confirmed",
    "dialect_regions": ["Gambia", "Casamance", "Guinea-Bissau"]
  },
  "applications": [
    {
      "id": "aiwa-entry-001",
      "context": "anatomical",
      "native_gloss": "...",
      "english_gloss": "mouth",
      "concepticon_id": 1290,
      "foreground_strength": "primary",
      "evidence_status": "speaker_confirmed",
      "background_resonances": [
        {
          "application_id": "aiwa-entry-002",
          "english_gloss": "door, entrance",
          "evidence_type": "speaker_confirmed",
          "confidence": 0.95
        },
        {
          "application_id": "aiwa-entry-003",
          "english_gloss": "price",
          "evidence_type": "speaker_confirmed",
          "confidence": 0.88
        }
      ]
    },
    {
      "id": "aiwa-entry-002",
      "context": "spatial",
      "native_gloss": "...",
      "english_gloss": "door, entrance",
      "concepticon_id": 623,
      "foreground_strength": "extended_metaphor",
      "evidence_status": "speaker_confirmed",
      "background_resonances": [...]
    },
    {
      "id": "aiwa-entry-003",
      "context": "commercial",
      "native_gloss": "...",
      "english_gloss": "price, threshold of negotiation",
      "concepticon_id": 1099,
      "foreground_strength": "extended_metaphor",
      "evidence_status": "speaker_confirmed",
      "background_resonances": [...]
    },
    {
      "id": "aiwa-entry-004",
      "context": "verbal_creative",
      "native_gloss": "...",
      "english_gloss": "to create, make, weave",
      "concepticon_id": 778,
      "foreground_strength": "extended_metaphor",
      "evidence_status": "speaker_confirmed",
      "background_resonances": [...]
    }
  ],
  "compounds": [
    {
      "form": "daajikoo",
      "literal_gloss": "mouth-character",
      "english_gloss": "behavior, conduct",
      "morphology": [
        {"form": "dáa", "role": "root", "root_id": "aiwa-root-001"},
        {"form": "jikoo", "role": "root", "root_id": "aiwa-root-045"}
      ],
      "evidence_status": "speaker_confirmed"
    }
  ],
  "cross_language_siblings": [
    {
      "concepticon_id": 1290,
      "application_context": "anatomical",
      "siblings": [
        {"language": "Wolof", "form": "bët", "evidence_status": "speaker_confirmed"},
        {"language": "Mandarin", "form": "zuǐ", "evidence_status": "community_confirmed"},
        {"language": "Arabic", "form": "fam", "evidence_status": "community_confirmed"}
      ]
    }
  ]
}
```

---

## 12. Migration Path

Existing `aiwa-entry` posts are not deleted. Migration proceeds in phases.

### Phase 1 — Categorization

Tag all existing entries with their language. English entries are `word_first` and require no migration. Entries for generative languages are flagged for migration.

### Phase 2 — Root identification

Group generative-language entries by phonological root form. Each distinct root form becomes a candidate `aiwa-root` record in `draft` status. This can be partially automated but must be reviewed by a linguist or speaker.

### Phase 3 — Seed authoring

For each candidate root, a native speaker authors the `aiwa_conceptual_seed_native` field. Until this is done, the root remains in `linguist_proposed` status.

### Phase 4 — Application linking

Existing `aiwa-entry` posts are linked to their parent root via `aiwa_root_id`. Their `aiwa_application_context` and `aiwa_evidence_status` fields are populated.

### Phase 5 — Resonance mapping

Background resonances between sibling applications are proposed and submitted for speaker confirmation.

Migration is not a one-time event. It is an ongoing editorial process governed by DVE. The platform must support both migrated (root-first) and unmigrated (word-first) entries simultaneously.

---

## 13. Downstream Impact

### Games (deferred — do not redesign until root model is validated)

Once root data exists and carries evidence status `speaker_confirmed`, games can:

- Ask which application of a root is foregrounded in a sentence
- Ask which words share the same root family
- Show the radiating semantic field and ask the learner to place a word on it
- Test compound recognition ("what two roots make this compound, and what is the literal gloss?")
- Ask cross-language comparison questions using Concepticon siblings

Games must not be redesigned against `linguist_proposed` or `ai_suggested` data. Only validated root families produce reliable game content.

### SRS Scheduler

The SRS unit remains the **Application**, not the Root. Learners learn one contextual use of a root at a time. However, the scheduler should surface sibling applications after the first application is mastered, so the learner builds the full root family over time. "You know `dáa` as mouth — here is how the same root means threshold in commerce."

### WordPad

Target words in WordPad can be Applications or Compounds. When a learner correctly uses a word, the reward event should tag the parent root so the learner's root mastery state advances.

### DVE

DVE governs the evidence graduation path. DVE reviewers work primarily at the Application level (validating specific contextual uses) and at the Resonance level (confirming or disputing background activation claims). Root-level seed authoring is a higher-authority task requiring a senior speaker or linguist.

### Sky, ESU, Mēh₁n̥s, Dheghom

These platform services interact with the root model as follows:

- **Sky**: when a learner query is ambiguous, Sky can ask "do you mean dáa as mouth, door, price, or creation?" — this requires the root family to exist in the API
- **ESU**: transcription and alignment must track which application of a root is being used in each utterance
- **Mēh₁n̥s**: evidence status determines export eligibility; `ai_suggested` and `disputed` records cannot be exported to public products
- **Dheghom**: stores the root graph structure as a first-class artifact, not a flat table

---

## 14. What This Spec Does Not Cover

The following are out of scope for v1.0 and must not be assumed:

- Grammar Spec: noun class systems, verb extensions, tonal grammar rules, sentence frame modeling. These build on roots but require their own spec.
- Visualization Spec: the radial semantic field display, cross-language concept travel maps, compound derivation trees. These are the presentation layer over this data model.
- DVE workflow internals: how speakers are recruited, how disputes are resolved, how resonance confirmation sessions are conducted.
- Morpheme-first language model: languages like Fula with complex noun class systems require extensions beyond what is defined here.
- AI suggestion pipeline: how `ai_suggested` records are generated, reviewed, and graduated. This belongs in the DVE/pipeline spec.
- `GAME-SERVICE-INTAKE-SPEC-v1.0`: games must wait for this. Do not implement `syncNow()` until that spec is committed.

---

_This spec represents the founding architectural correction for the 3iAtlas language platform. English-shaped word boxes cannot hold Mandinka meaning. Build the root graph first._
