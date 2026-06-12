# 3iAtlas — Data Zones and Trust Boundary Spec

**Version:** 1.0
**Status:** Approved
**Scope:** All 3iAtlas platform decisions involving data storage, API design, and service boundaries
**ADR Reference:** ADR-002, ADR-003, ADR-004, INV-003, INV-004, INV-007, INV-008, INV-010
**Authority:** Max Barrett / Starisian Technologies
**Date:** June 2026

---

## 1. The Three Trust Zones

Per ADR-002, all structured data in the 3iAtlas platform lives in exactly one of three trust zones. Zone membership is a structural property of a record, not a deployment decision. A record cannot span zones.

---

### Zone 1: Governed Evidence Zone

**What it contains:** Contributor evidence. Community submissions. DVE-approved lexical records. Speaker confirmations. All data whose legitimacy traces to a human actor with a verifiable identity (contributor ref via Helios, INV-010).

**Who may write:** Governed write paths only — DVE approval pipeline, approved import packages, contributor submissions via authenticated intake surfaces.

**Key invariants:**
- **INV-003:** No graph store is ever the system of record for contributor evidence.
- **INV-010:** One identity authority (Helios); opaque refs everywhere else in Zone 1.

**Contains in the dictionary context:**
- All `aiwa-entry` records (post DVE import)
- All `aiwa-root` records (post DVE validation)
- All `aiwa-compound` records (post DVE validation)
- `aiwa_example_sentences` content
- `aiwa_audio_file` references
- Speaker community tags at `speaker_confirmed` / `editorial_approved` status
- Contributor submissions in the intake pipeline (pre-DVE approval)
- Cross-language sibling editorial links (`aiwa_cross_language_siblings`)

**What Zone 1 is not:** The `aiwa_cross_language_siblings` field is an editorial relationship between Zone 1 entries. It is not a cognate judgment (that lives in Zone 2). The distinction matters: an editorial sibling link is a human curatorial act at the entry level; a cognate judgment is an academic comparative claim with methodology. They are independent.

---

### Zone 2: Reference / Comparative Zone

**What it contains:** Reconstructed, inferred, or academically comparative data. No contributor identity lives here. Records in this zone are governed by linguistic methodology and academic review, not community submission.

**Who may write:** Qualified linguists with appropriate credentials. Automated processes producing clearly marked preliminary data (evidence_status: `proposed`). No contributor submissions.

**Key invariants:**
- **INV-004:** No `contributor_ref` in reference zone tables.
- **INV-006:** Recurrence promotion to "cognate" or "ancient" requires evidence linkage, not recurrence alone.
- **INV-007:** Connection types are distinct and never collapse into one edge type.
- **INV-008:** Cognate judgment target exclusivity (XOR: entry_id or variant_form_id — never both on one judgment).

**Contains:**

Comparative reference tables (ADR-003):

```
cognate_sets            — groupings of historically related entries across languages
cognate_judgments       — specific cognacy assessments with methodology notes
sound_correspondences   — attested phonological correspondence patterns
borrowing_events        — documented borrowing episodes with direction and dating
```

Morpheme tier — reconstructed roots (ADR-004, Phase 3.5):

```
roots                   — reconstructed proto-roots, not to be confused with aiwa-root (Zone 1)
morpheme_root_links     — links between dictionary morphemes and reconstructed roots
```

**Table schemas:**

```sql
-- Comparative Reference Tables (ADR-003)
cognate_sets (
  id, proto_form, proto_language,
  gloss_en, confidence,       -- proposed | probable | established
  methodology_notes, source_refs, created_by_linguist_ref
)

cognate_judgments (
  id, cognate_set_id,
  target_type,               -- ENUM: entry_id | variant_form_id  (INV-008: XOR, never both)
  entry_id,
  variant_form_id,
  confidence, methodology, reviewer_ref, reviewed_at
)

sound_correspondences (
  id, correspondence_set_id,
  language_a_id, phoneme_a,
  language_b_id, phoneme_b,
  example_entry_id_a, example_entry_id_b,
  attested_count, source_refs
)

borrowing_events (
  id,
  donor_language_id, recipient_language_id,
  donor_entry_id, recipient_entry_id,
  period_label,
  direction,                 -- ENUM: unidirectional | bidirectional | unclear
  evidence_type,             -- direct_attestation | phonological_analysis | historical_record | inferred
  notes, source_refs, linguist_ref
)

-- Morpheme Tier — Reconstructed (ADR-004, Phase 3.5)
roots (
  id, proto_form, proto_language_label,
  gloss_en, reconstruction_confidence,
  source_refs, linguist_ref
)

morpheme_root_links (
  id, morpheme_id, root_id,
  link_type,                 -- reflex | cognate | borrowed | parallel_development
  confidence, notes, linguist_ref
)
```

---

### Zone 3: Derived Graph Projection

**What it contains:** Computed views and graph projections derived from Zones 1 and 2. The graph is a projection, never a source of truth.

**Invariant:** INV-003 — No graph store is ever the system of record for contributor evidence.

**Contains:**
- Semantic relationship graphs (derived from Zone 1 entry relationships)
- Root family graphs (derived from Zone 1 root/application/compound links)
- Cross-language concept graphs (derived from Concepticon ID links)
- Colexification projections (derived from CLICS references)

**Write rule:** Zone 3 records are never directly edited. They are regenerated from Zone 1 and Zone 2 sources. Any inconsistency between Zone 3 and Zone 1/2 is resolved by regenerating Zone 3, never by editing it directly.

---

## 2. Zone Boundaries

### What May Cross From Zone 1 to Zone 2

Zone 2 reference tables may cite Zone 1 entry IDs as targets of cognate judgments or borrowing events. This is a read reference only — no Zone 2 record may write to Zone 1.

### What May Cross From Zone 2 to Zone 1

Zone 2 data may be promoted into Zone 1 when a linguist-proposed relationship receives speaker confirmation. The promoted record moves to Zone 1 with a speaker identity attached. The original Zone 2 record is retained as research provenance. Zone 2 is not deleted — it is the record of how the claim was established.

### What May Never Cross

- `contributor_ref` from Zone 1 never appears in Zone 2 tables (INV-004).
- Zone 2 records never carry community submission status — only academic/methodological status values.
- Zone 3 projections never serve as the basis for governance decisions. All governance acts on Zone 1 records.

---

## 3. The Dictionary's Zone Placement

The AIWA Dictionary plugin is a **Zone 1 service**. It:
- Stores Zone 1 records (approved entries, linguistic fields, speaker community tags)
- Does not store Zone 2 comparative tables (those live in the DVE / comparative research layer)
- Does not own Zone 3 projections (graph views are derived from and owned by the graph projection layer)

The dictionary imports Zone 1 records from DVE as Approved Entry Packages. It does not receive or store Zone 2 comparative tables. The dictionary's `aiwa_cross_language_siblings` editorial links are Zone 1 records — they are not cognate judgments.

---

## 4. Morpheme Tier Zone Placement (Phase 3.5 — ADR-004)

Per ADR-004, the morpheme tier is reserved for Phase 3.5. The zone placement is already decided and must not be changed when implementation begins.

**Dictionary Zone (Zone 1 — canonical segmentation):**

```sql
morphemes (
  id, language_id, form, gloss_en, gloss_native,
  morpheme_type,    -- root | prefix | suffix | infix | tone_morpheme | zero
  evidence_status,  -- ai_suggested | linguist_proposed | speaker_confirmed | community_confirmed
  created_by_linguist_ref
)

morpheme_allomorphs (
  id, morpheme_id, allomorph_form,
  conditioning_environment, notes, evidence_status
)

segmentations (
  id, entry_id, segmentation_type,
  analysis_notes, evidence_status,
  created_by_linguist_ref
)

segmentation_morphemes (
  id, segmentation_id, morpheme_id,
  position, surface_form, gloss, role
)
```

**Reference Zone (Zone 2 — reconstructed roots, see schemas above):**
```
roots                   — reconstructed proto-roots
morpheme_root_links     — links between dictionary morphemes and reconstructed roots
```

**Authority boundary (ADR-004):**
- ESU carries morpheme evidence (evidence_status, confidence scores, transcription provenance)
- The Dictionary owns canonical segmentation (segmentations + segmentation_morphemes)
- The Reference Zone owns reconstructed roots (roots + morpheme_root_links)

These are three distinct authorities for three distinct claims. A morpheme in Zone 1 is a claim about what speakers use. A root in Zone 2 is a claim about historical reconstruction. They are related but never the same record.

---

## 5. Signal vs Record (INV-011)

INV-011: A signal routes the concern; a record carries the measurement.

The dictionary emits signals (lookup events, game outcomes, contribution submissions). The reward ledger carries the measurements (XP, mastery state). Signals are not stored in the dictionary. Records are.

This distinction governs how the dictionary's data is structured: what the dictionary stores (Zone 1 lexical records) versus what it emits (events to the reward ledger and game service).

---

## 6. What This Spec Does Not Cover

- DVE internal data structures (Zone 2 tables in the research layer are governed by DVE's own spec)
- Graph database selection or configuration (Zone 3 implementation is a platform infrastructure decision)
- Contributor identity internals (governed by ADR-012, Helios spec, and the Identity Service spec)
- Invariant arbitration process (governed by the ADR repository)
- The intake topology and two-door model (see `3IATLAS-SUITE-ARCHITECTURE-v2.0`, ADR-008)
