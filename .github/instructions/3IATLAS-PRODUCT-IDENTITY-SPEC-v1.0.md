# 3iAtlas Dictionary — Product Identity Spec

**Version:** 1.0
**Status:** Approved
**Scope:** All 3iAtlas products and suite-wide product decisions
**Authority:** Max Barrett / Starisian Technologies
**Date:** June 2026

---

## 1. What This Platform Is

The 3iAtlas suite is a **Linguistic Atlas** — not a word list, not a vocabulary app, not a translation service.

A Linguistic Atlas maps the living structure of a language as its speakers experience it: roots and their semantic reach, borrowing histories, colexification patterns across language families, tonal structure, orthographic conventions, community usage patterns, and the evidence that backs every claim.

The dictionary is the public face of this atlas. Everything in the dictionary is evidence-grounded, speaker-confirmed, and historically accountable. Nothing is estimated, approximated, or borrowed from English grammatical categories.

---

## 2. Who the Users Are

The users of the AIWA Dictionary and 3iAtlas suite are **native speakers of their mother tongue**. They are not learners. They are not ESL students. They have a language. What they have not had is tools built for that language.

The gap is **orthographic**: they have spoken Mandinka, Wolof, or Fula their entire lives. The barrier is writing — a writing system established within living memory, spelling conventions not yet standardized, and no digital tools that make writing in their language as natural as speaking it.

These tools exist to close that gap. They must honor what users already know. They must never make a native speaker feel like a child in their own language.

---

## 3. The Dual Standard

The platform produces lexical records of:

1. **Oxford quality** — rigorous, evidence-grounded, citable, academically defensible
2. **Descriptive accuracy** — faithful to how language is actually spoken, including code-mixing, borrowing, and regional variation

These are not in tension. Oxford quality means evidentiary rigor, not prescriptivism. The platform records what speakers actually say and confirms it with the same methodological rigor a linguistics journal would require.

A word that has been integrated into daily Mandinka speech from Arabic deserves the same evidentiary depth as a reconstructed proto-Mande root. Both exist. Both are documented. Neither is treated as contamination.

---

## 4. The Three-Layer Identity

Every entry in the atlas carries three layers of identity:

### Layer 1: Language Model Type

Each language is configured with a model type that governs how entries are organized and served:

| Model Type | Primary Unit | Examples |
|---|---|---|
| `root_first` | Root families | Mandinka, Bambara, Chinese |
| `word_first` | Individual words | English, French |
| `morpheme_first` | Morpheme sets | Fula, Swahili, Arabic |

This is not a performance optimization. It is a linguistic reality. Mandinka `dáa` is not four coincidentally similar words. It is one root — the threshold of transition and creation — with four contextual applications. Storing it as four rows destroys the language's cognitive architecture.

**Platform ruling:** For generative languages, the root is the primary dictionary entity. Words are contextual applications of roots.

### Layer 2: Borrowing Status (Soup Model)

Every entry carries a `borrowing_status` field that describes how the word sits in the living language:

| Status | Meaning |
|---|---|
| `native` | Word form and meaning originate in this language's ancestral stock |
| `borrowed_integrated` | Borrowed from another language; fully integrated — speakers use it without awareness of foreign origin |
| `borrowed_active` | Borrowed and still recognized as borrowed, but in active daily use |
| `code_mixed` | Appears primarily in code-mixed speech contexts; not yet a stable entry in the formal register |
| `archaic` | Historical form; still in the corpus but not in living daily use |
| `neologism` | Newly coined, active in contemporary usage but not yet established |
| `contested` | Disputed by community members — some accept it, some reject it |

This is the **soup model**: the dictionary reflects the living linguistic ecosystem, not an idealized archive. Languages borrow. Communities mix. The atlas records the reality.

### Layer 3: Evidence Status

Every claim in the atlas is evidence-graded:

```
ai_suggested → linguist_proposed → speaker_confirmed → community_confirmed
```

Records below `speaker_confirmed` do not reach public-facing surfaces. The graduation path is governed by DVE.

---

## 5. The Platform Loop

The atlas is not a static archive. It is a living system with a directed evidence loop:

```
Native speakers use the language
        ↓
RLC / Sky / S2S capture usage evidence
        ↓
ESU transcribes, aligns, and annotates
        ↓
DVE validates and governs
        ↓
Dictionary publishes the approved record
        ↓
3iAtlas surfaces (WordPad, games, RLC) use the dictionary
        ↓
Usage generates new language evidence
        ↓ (loop continues)
```

The loop is intentional. Learner activity is not separate from the language record — it generates new evidence that feeds back into the atlas. DVE governs what becomes authoritative. Mēh₁n̥s controls export eligibility. The loop must be governed or it hallucinates.

---

## 6. Per-Language Navigation

The dictionary surface adapts to the language model type of the selected language:

**root_first languages:** Primary navigation is by root family. A search for `dáa` surfaces the root family — its conceptual seed, all applications, all compounds, background resonances. The word-by-word view remains available but is not the primary entry point.

**word_first languages:** Primary navigation is by word. The English scaffold (`AiWA_Semantic_Scaffold_1.csv`) operates in this mode. It is the interoperability layer to Concepticon, WordNet, and CEFR — not the model for any African language in the corpus.

**morpheme_first languages:** Primary navigation is by morpheme set, with inflectional paradigms. This mode is reserved for Phase 3.5 of the platform's development (see `3IATLAS-DATA-ZONES-AND-TRUST-BOUNDARY-SPEC-v1.0`, ADR-004).

---

## 7. The ADR System

All architectural decisions for the 3iAtlas platform are governed by the Architecture Decision Record (ADR) system, maintained in the sparxstar-architecture-decision-record repository. Specs in this directory cite ADRs by number. ADR content is authoritative and immutable. Specs do not restate ADR content — they reference it.

All invariants (INV-001 through INV-011) are maintained in `standards/invariants.md` of the ADR repository. When a spec cites an invariant, the ADR repository is the authority. The spec is the implementation contract.

---

## 8. Commercial Identity

The 3iAtlas suite is Starisian-hosted SaaS. Three sales motions (decided June 2026, recorded in `3IATLAS-IDENTITY-AND-GAME-SERVICES-DECISION-v1.0`, §10):

1. **Institutional / government** — donor-funded projects, tender procurement, multi-year contracts. Funder-facing usage and impact reporting is a product requirement in this motion.
2. **School subscriptions** — per-classroom or per-student annual SaaS; teacher self-serve; class-code login (Lower Basic tier) is the standard flow.
3. **Consumer / diaspora** — app-store freemium subscription.

The atlas is sovereign language infrastructure. Indigenous-market deployments carry a contractual data-export/repatriation guarantee. Community data ownership with a guaranteed exit path is a sales differentiator and an ethical requirement.

Self-hosted enterprise edition: not built now. Reserved as a premium, negotiated exception requiring a separate corpus license — self-hosting otherwise places AIWA's sovereign linguistic work product on customer infrastructure.

---

## 9. What the Dictionary Is Not

The dictionary is not:
- A crowdsourced word list
- A translation service (it records lexical relationships; it does not translate documents)
- A governance engine (DVE governs; the dictionary publishes)
- A data collection surface (ESU collects; the dictionary distributes)
- A community voting or correction mechanism (removed — see Suite Architecture)

---

*The 3iAtlas suite exists to give native speakers the tools their language deserves. Everything else follows from this.*
