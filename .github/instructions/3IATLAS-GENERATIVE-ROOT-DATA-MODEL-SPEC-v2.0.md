# 3iAtlas Generative Root Data Model

## Specification v2.0

**Status:** Approved
**Scope:** Dictionary data model for all generative-root languages under the 3iAtlas platform
**ADR References:** ADR-002, ADR-003, ADR-004, ADR-006, INV-003, INV-004, INV-006, INV-008
**Supersedes:** `3IATLAS-GENERATIVE-ROOT-DATA-MODEL-SPEC-v1.0.md`
**Related specs:** `3IATLAS-DATA-ZONES-AND-TRUST-BOUNDARY-SPEC-v1.0`, `3IATLAS-DICTIONARY-ROLE-AND-PIPELINE-SPEC-v1.0`, `3IATLAS-DICTIONARY-MULTILANGUAGE-MODEL-SPEC-v2.0`

**What changed from v1.0:**
- Zone placement formalized for all entities (ADR-002 / Data Zones spec)
- Soup model (borrowing_status) added to entry schema
- Morpheme tier at Phase 3.5 added with authority boundary (ADR-004)
- Comparative reference tables added (ADR-003)
- Recurrence promotion rule added (ADR-006 / INV-006)
- Cognate judgment target exclusivity rule added (INV-008)

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

| Model Type | Description | Examples |
|---|---|---|
| `word_first` | Words are the primary unit. Polysemy is handled as multiple senses of one word. | English, French |
| `root_first` | Roots are the primary unit. Words are contextual applications of roots. | Mandinka, Bambara, Chinese |
| `morpheme_first` | Morphemes are the primary unit, with rich inflectional systems. | Fula (20+ noun classes), Swahili, Arabic |

This configuration is stored on the language record and controls which data entry interface, API response shape, and visualization layer the platform uses.

The English CSV scaffold (`AiWA_Semantic_Scaffold_1.csv`) operates as a `word_first` interoperability layer and is not affected by this spec. It remains the Concepticon/WordNet/CLICS/proficiency bridge for all languages.

---

## 3. Zone Placement

Per ADR-002 and `3IATLAS-DATA-ZONES-AND-TRUST-BOUNDARY-SPEC-v1.0`, every entity in this model lives in exactly one trust zone:

| Entity | Zone | Authority |
|---|---|---|
| `aiwa-root` (approved) | Zone 1: Governed Evidence | DVE |
| `aiwa-entry` (approved) | Zone 1: Governed Evidence | DVE |
| `aiwa-compound` (approved) | Zone 1: Governed Evidence | DVE |
| Background resonances | Zone 1: Governed Evidence | DVE (speaker confirmation required) |
| `morphemes` | Zone 1: Governed Evidence | Linguist / DVE |
| `segmentations` | Zone 1: Governed Evidence | Linguist / DVE |
| `cognate_sets` | Zone 2: Reference / Comparative | Qualified linguists |
| `cognate_judgments` | Zone 2: Reference / Comparative | Qualified linguists |
| `borrowing_events` | Zone 2: Reference / Comparative | Qualified linguists |
| `sound_correspondences` | Zone 2: Reference / Comparative | Qualified linguists |
| `roots` (reconstructed) | Zone 2: Reference / Comparative | Qualified linguists |
| Root family graph | Zone 3: Derived Projection | Regenerated from Zone 1 |

**INV-003:** No graph store is ever the system of record for contributor evidence. The Zone 3 root family graph is a derived view, never a source of truth.

---

## 4. The Pipeline Loop

The dictionary is both an upstream scaffold and a downstream product:

```
3iAtlas
(games / WordPad / learner activity)
            ↓ Communication Door (Sky — ADR-008)
         Sky
(intake, questions, readiness)
            ↓
         ESU
(Machine Door — transcription, alignment, annotation)
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
(Zone 1 — validated language product)
            ↓
        3iAtlas
(feeds games, WordPad, drills, SRS, publishing)
```

**Before DVE validation:** the dictionary entries are a scaffold — structured holding spaces for proposed data. Nothing is authoritative until DVE confirms it.

**After DVE validation:** the dictionary is the authoritative public language product. Validated records feed 3iAtlas learning surfaces, SRS scheduling, WordPad target words, and publishing workflows.

---

## 5. Entity Model Overview

| Entity | Post Type | Zone | Purpose |
|---|---|---|---|
| Root | `aiwa-root` | Zone 1 | The generative seed. Primary entry for root-first languages. |
| Application | `aiwa-entry` | Zone 1 | A contextual projection of a root. One application per semantic context. |
| Compound | `aiwa-compound` | Zone 1 | A word derived from one or more roots through productive compounding. |
| Concept Link | (relationship) | Zone 1 | Bridge from an Application to the Concepticon / CSV scaffold. |

Existing `aiwa-entry` posts are **not deleted**. They are redesigned as Application records. Posts with no root relationship are treated as `word_first` entries and remain unchanged.

---

## 6. The Root Record (`aiwa-root`)

The Root Record is the primary entity for all root-first language entries.

### 6.1 Definition of Conceptual Seed

> A native-speaker-authored explanation of the root's core semantic force across all its applications.

The conceptual seed is an empirical claim, not a poetic interpretation. It must be authored or confirmed by a native speaker. It must be falsifiable — it should be possible to challenge a proposed seed by showing an application it cannot explain.

### 6.2 SCF Fields

| Field | Type | Required | Description |
|---|---|---|---|
| `aiwa_root_form` | text | yes | The root as spoken, with diacritics and tone marks |
| `aiwa_root_language` | taxonomy (Languages) | yes | Language this root belongs to |
| `aiwa_root_script` | select | no | `latin`, `arabic`, `tifinagh`, `nko`, `latin_tonal`, `other` |
| `aiwa_root_pronunciation` | text | no | IPA transcription of the root form |
| `aiwa_root_audio` | file | no | Audio recording by a native speaker |
| `aiwa_root_tone_class` | select | no | `high`, `low`, `mid`, `rising`, `falling`, `non_tonal`, `complex` |
| `aiwa_root_compounding_rules` | textarea | no | Tonal and phonetic shifts in compounding. Free text until Grammar Spec formalizes this. |
| `aiwa_conceptual_seed_native` | textarea | yes (root_first) | Seed definition authored in the root's own language, by a native speaker |
| `aiwa_conceptual_seed_english` | textarea | yes (root_first) | English translation of the seed definition |
| `aiwa_seed_explanation_audio` | file | no | Audio of a native speaker explaining the seed |
| `aiwa_seed_author_type` | select | yes | `native_speaker`, `fluent_speaker`, `linguist`, `community_panel`, `ai_proposed` |
| `aiwa_seed_confidence` | select | yes | Evidence status (see § 9) |
| `aiwa_dialect_regions` | textarea | no | Comma-separated regions where this root is attested |
| `aiwa_applications` | relationship | — | Links to Application records |
| `aiwa_compounds` | relationship | — | Links to Compound records |
| `aiwa_evidence_status` | select | yes | Overall evidence status. See § 9. |
| `aiwa_review_status` | select | yes | `draft`, `pending_speaker_review`, `pending_dvr_review`, `validated`, `rejected`, `deprecated` |
| `aiwa_created_by` | text | yes | Contributor identifier (opaque Helios ref — INV-010) |
| `aiwa_created_at` | date | yes | Creation date |

---

## 7. The Application Record (`aiwa-entry`)

An Application is one contextual projection of a Root. It answers: in what context does this root foreground this particular meaning?

Applications are the SRS learning unit. A learner learns an application, not a root. The root is the organizing structure.

### 7.1 New SCF Fields

| Field | Type | Required | Description |
|---|---|---|---|
| `aiwa_root_id` | relationship | no | Link to parent `aiwa-root`. Null for word_first entries. |
| `aiwa_application_context` | select | root_first | `anatomical`, `spatial`, `social`, `commercial`, `verbal_creative`, `temporal`, `epistemic`, `ecological`, `ceremonial`, `other` |
| `aiwa_native_gloss` | textarea | root_first | Brief gloss in the root's language |
| `aiwa_english_gloss` | textarea | yes | Brief English gloss |
| `aiwa_concepticon_id` | number | yes | Concepticon concept ID. Bridge to CSV scaffold and cross-language siblings. |
| `aiwa_semantic_domain` | taxonomy | no | SemDom domain code |
| `aiwa_background_resonances` | repeater | no | See § 8 |
| `aiwa_foreground_strength` | select | root_first | `primary`, `extended_metaphor`, `compound_base`, `idiomatic`, `archaic` |
| `aiwa_dialect_scope` | textarea | no | Which dialect regions use this application |
| `aiwa_borrowing_status` | select | yes | See Soup Model below |
| `aiwa_example_sentence_native` | textarea | no | Example sentence in the native language |
| `aiwa_example_sentence_english` | textarea | no | English translation of example sentence |
| `aiwa_example_audio` | file | no | Audio recording of example sentence |
| `aiwa_application_confidence` | select | yes | Evidence status (see § 9) |
| `aiwa_evidence_status` | select | yes | See § 9 |

### 7.2 Soup Model: `aiwa_borrowing_status`

Every application record carries a borrowing status describing how the word sits in the living language:

| Value | Meaning |
|---|---|
| `native` | Originates in this language's ancestral stock |
| `borrowed_integrated` | Fully integrated borrowing — speakers use it without awareness of foreign origin |
| `borrowed_active` | Recognized as borrowed; in active daily use |
| `code_mixed` | Appears primarily in code-mixed speech; not yet stable in formal register |
| `archaic` | Historical form; in corpus but not in living daily use |
| `neologism` | Newly coined; active but not yet established |
| `contested` | Disputed — some community members accept it, others reject it |

This field is set by DVE and is linguistically locked after import. It reflects the living linguistic ecosystem — the dictionary records reality, not an idealized archive.

### 7.3 Existing fields that remain

All existing `aiwa-entry` fields (audio, IPA, phonetic, synonyms, part of speech, example sentences, SCF relationship fields) remain. New fields are additive.

---

## 8. Background Resonance

Background resonance is the empirical claim that when a native speaker hears or uses one application of a root, other applications remain semantically active in the background.

This is not mystical. It is a testable claim about cognitive activation patterns in native speaker processing. It requires evidence.

### 8.1 Resonance Record Structure

| Field | Type | Description |
|---|---|---|
| `resonance_application_id` | relationship | The sibling Application whose meaning remains active |
| `resonance_evidence_type` | select | How this resonance is established (see below) |
| `resonance_confidence` | number | 0.0 to 1.0 |
| `resonance_notes` | textarea | Evidence notes, citations, speaker quotes |

### 8.2 Resonance Evidence Types

| Value | Meaning |
|---|---|
| `speaker_confirmed` | Native speakers explicitly confirmed this resonance is felt |
| `linguist_proposed` | Proposed based on etymological or semantic analysis; not yet speaker-confirmed |
| `community_disputed` | Proposed but community members dispute whether it is felt by contemporary speakers |
| `historical_note` | Was active in historical usage but may no longer be felt by contemporary speakers |

### 8.3 What resonance is not

Resonance does not mean all meanings are equally active at once. It means the root's semantic force is present as background context even when one application is foregrounded.

When `dáa` is used to mean price, the concept of threshold is present. The negotiation is a verbal crossing of a boundary. This is the resonance.

---

## 9. Evidence and Confidence Layer

All root, application, and resonance records carry evidence status. The platform must not treat all semantic interpretations as equal.

### 9.1 Evidence Status Values

| Value | Meaning | Who sets it |
|---|---|---|
| `speaker_confirmed` | A native speaker confirmed this record is accurate | DVE speaker review |
| `community_confirmed` | Multiple speakers from the dialect region confirmed | DVE community panel |
| `linguist_proposed` | Proposed based on analysis; awaiting speaker confirmation | Contributor |
| `ai_suggested` | AI generated; requires human review | AI pipeline |
| `disputed` | Actively contested by speakers or linguists | DVE dispute process |
| `deprecated` | Was once active; no longer considered accurate | DVE governance |

### 9.2 Graduation Path

```
ai_suggested → linguist_proposed → speaker_confirmed → community_confirmed
                                                     ↘ disputed → deprecated (if sustained)
```

Records below `speaker_confirmed` are visible internally. Only `speaker_confirmed` and `community_confirmed` reach public-facing 3iAtlas surfaces, games, and SRS.

---

## 10. The Compound Record (`aiwa-compound`)

Compounds are words formed by combining two or more roots. They are distinct from Applications because they have their own phonetic form and often undergo tonal compactness changes.

### 10.1 SCF Fields

| Field | Type | Description |
|---|---|---|
| `aiwa_compound_form` | text | The compound word as spoken, with tone marks |
| `aiwa_compound_language` | taxonomy | Language |
| `aiwa_compound_audio` | file | Audio of the compound |
| `aiwa_morphology` | repeater | Ordered list of roots/morphemes: `{root_id, form, role}` |
| `aiwa_tonal_change` | textarea | Description of tonal shifts in compounding |
| `aiwa_compound_gloss_native` | textarea | Gloss in the native language |
| `aiwa_compound_gloss_english` | text | English translation |
| `aiwa_compound_gloss_literal` | text | Literal morpheme-by-morpheme translation |
| `aiwa_concepticon_id` | number | Concepticon link if the compound maps to a universal concept |
| `aiwa_semantic_domain` | taxonomy | Domain |
| `aiwa_borrowing_status` | select | Soup model (same values as Application) |
| `aiwa_evidence_status` | select | Evidence status |
| `aiwa_parent_roots` | relationship | All `aiwa-root` records contributing to this compound |

### 10.2 Canonical Mandinka Compound Examples

| Compound | Literal gloss | Morphology | Meaning |
|---|---|---|---|
| `daajikoo` | mouth-character | dáa + jikoo | behavior, conduct |
| `daaturoo` | mouth-stopper | dáa + turu | lip |
| `daakuloo` | mouth-bone | dáa + kuloo | edge, sideline |
| `Daamansoo` | creation-king | dáa (verb) + mansoo | The Creator |
| `kuntiyo` | head-owner | kŭn + tiyo | leader, chief |
| `kunino` | head-awareness | kŭn + in | wisdom, awareness |
| `kuntano` | headless | kŭn + -tan | foolish person |
| `kunfin` | dark head | kŭn + fin | illiterate person |
| `bondi` | cause-to-exit | bó + -ndi | to remove, extract |

---

## 11. Comparative Reference Tables (ADR-003, Zone 2)

Comparative relationships between entries in different languages are stored in Zone 2, not Zone 1. Full table schemas in `3IATLAS-DATA-ZONES-AND-TRUST-BOUNDARY-SPEC-v1.0`.

**What Zone 2 contains for comparative analysis:**
- `cognate_sets` — groupings of historically related entries
- `cognate_judgments` — specific cognacy assessments with methodology
- `sound_correspondences` — phonological patterns
- `borrowing_events` — documented borrowing episodes

**Recurrence Promotion Rule (ADR-006, INV-006):** A word appearing repeatedly in a language's corpus does not become "cognate" or "ancient" by recurrence alone. Promotion to a comparative relationship requires an evidence-linked judgment in Zone 2 — a qualified linguist's assessment with methodology. Recurrence is a signal, not a judgment.

**Cognate Judgment Target Exclusivity (INV-008):** A cognate judgment targets either an `entry_id` (an approved dictionary entry) or a `variant_form_id` (a specific variant form), never both on the same judgment record. This is a data integrity invariant.

---

## 12. Morpheme Tier (ADR-004, Phase 3.5 — Deferred)

The morpheme tier is reserved for Phase 3.5. No morpheme tables are to be built before Phase 3.5 is formally opened.

**Authority boundary (decided in ADR-004):**
- **ESU** carries morpheme evidence (confidence scores, transcription provenance, analysis outputs)
- **Dictionary** owns canonical segmentation (Zone 1: `morphemes`, `segmentations`, `segmentation_morphemes`)
- **Reference Zone** owns reconstructed roots (Zone 2: `roots`, `morpheme_root_links`)

These are three distinct authorities. A morpheme in Zone 1 is a claim about what speakers produce. A root in Zone 2 is a claim about historical reconstruction. They are related but never the same record.

Full table schemas for the morpheme tier are in `3IATLAS-DATA-ZONES-AND-TRUST-BOUNDARY-SPEC-v1.0` §4.

---

## 13. Concept Link (Application → Concepticon)

Every Application record connects to the English scaffold via `aiwa_concepticon_id`. This link is the bridge between the native-language root graph and the universal concept layer.

The Concept Link enables:
- Cross-language sibling discovery (which Wolof, Fula, Arabic, Chinese words express the same concept)
- Colexification analysis (which concepts the root bundles across its applications — this is what CLICS measures)
- CEFR and AIWA level assignment (via the English scaffold row for that concept)
- WordNet semantic relationships inherited from the English scaffold

When a Mandinka Application links to Concepticon ID `1290` (MOUTH), it immediately inherits all cross-language siblings in the CSV for MOUTH — Chinese `zuǐ`, Wolof `bët`, French `bouche`, Arabic `fam` — reachable through the same Concepticon node.

---

## 14. API Shape: Root Family Retrieval

The primary API endpoint for root-first language entries returns the full root family.

### `GET /sparxstar/v1/dictionary/root/{root_id}`

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
      "english_gloss": "mouth",
      "concepticon_id": 1290,
      "foreground_strength": "primary",
      "borrowing_status": "native",
      "evidence_status": "speaker_confirmed",
      "background_resonances": [
        {
          "application_id": "aiwa-entry-002",
          "english_gloss": "door, entrance",
          "evidence_type": "speaker_confirmed",
          "confidence": 0.95
        }
      ]
    }
  ],
  "compounds": [
    {
      "form": "daajikoo",
      "literal_gloss": "mouth-character",
      "english_gloss": "behavior, conduct",
      "borrowing_status": "native",
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

## 15. Migration Path

Existing `aiwa-entry` posts are not deleted. Migration proceeds in phases.

**Phase 1** — Tag all entries with their language. English entries are `word_first`; no migration needed.
**Phase 2** — Group generative-language entries by phonological root form. Each distinct root form becomes a candidate `aiwa-root` in `draft` status.
**Phase 3** — A native speaker authors `aiwa_conceptual_seed_native` for each candidate root.
**Phase 4** — Existing entries linked to parent root via `aiwa_root_id`. Application context and evidence status populated.
**Phase 5** — Background resonances proposed and submitted for speaker confirmation.

Migration is an ongoing editorial process governed by DVE. The platform must support both migrated (root-first) and unmigrated (word-first) entries simultaneously.

---

## 16. Downstream Impact

**Games:** Must not be redesigned against `linguist_proposed` or `ai_suggested` data. Only validated root families produce reliable game content. Root-family game types (which application is foregrounded, compound recognition, radiating semantic field) are deferred until root data reaches `speaker_confirmed`.

**SRS Scheduler:** The SRS unit remains the Application, not the Root. However, after the first application is mastered, the scheduler should surface sibling applications: "You know `dáa` as mouth — here is how the same root means threshold in commerce."

**WordPad:** Target words can be Applications or Compounds. Correct production events should tag the parent root so the learner's root mastery state advances.

**DVE:** DVE reviewers work primarily at the Application level and at the Resonance level. Root-level seed authoring requires a senior speaker or linguist.

---

## 17. What This Spec Does Not Cover

- Grammar Spec: noun class systems, verb extensions, tonal grammar rules, sentence frame modeling
- Visualization Spec: radial semantic field display, cross-language concept travel maps, compound derivation trees
- DVE workflow internals: how speakers are recruited, disputes resolved, resonance sessions conducted
- AI suggestion pipeline: how `ai_suggested` records are generated and graduated
- `GAME-SERVICE-INTAKE-SPEC-v1.0`: games must wait for this. Do not implement `syncNow()` until that spec is committed.

---

## Version History

| Version | Date | Changes |
|---|---|---|
| 1.0 | June 2026 | Initial specification |
| 2.0 | June 2026 | Zone placement formalized (ADR-002). Borrowing status / soup model added. Morpheme tier Phase 3.5 added (ADR-004). Comparative reference tables added (ADR-003). Recurrence promotion rule (ADR-006/INV-006). Cognate judgment exclusivity (INV-008). |

---

*This spec represents the founding architectural correction for the 3iAtlas language platform. English-shaped word boxes cannot hold Mandinka meaning. Build the root graph first.*
