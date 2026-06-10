# 3iAtlas Product Identity Spec v1.0

**Status:** Approved  
**Authority:** This document is normative for all 3iAtlas product decisions. It supersedes any description that calls the product a "dictionary." Every downstream spec — Generative Root Data Model, Literacy Reward Model, Grammar Spec, Visualization Spec, Game Service Intake Spec — is subordinate to this document. When any spec conflicts with this one, this one wins.

---

## Platform Ruling

**3iAtlas is a Linguistic Atlas.**

It is not a dictionary. A dictionary lists words and their definitions. An atlas maps territory. 3iAtlas maps the territory of human language — how concepts are born inside a language, how they generate meaning across contexts, and how they travel between languages through borrowing, contact, and coexistence.

The word "dictionary" is not the product. It is one view inside the atlas, available when a user needs it.

This ruling applies everywhere: product naming, API design, data modeling, UI, marketing copy, and all downstream specs. If a decision would make sense for a dictionary but not for an atlas, build for the atlas.

---

## What the Atlas Maps

The atlas maps three things simultaneously:

### 1. Generative Root Families

For root-first languages (Mandinka, Arabic, Hebrew, and others), meaning does not originate in words — it originates in roots. A root is a conceptual seed that generates a family of meanings through tonal, morphological, and contextual application.

The canonical example: Mandinka **dáa** is not "mouth." It is a generative seed whose foregrounded applications include anatomical mouth, spatial opening (door, gate), economic entry point (price, market access), and verbal creation (to open, to found, to utter). These are not metaphors of "mouth." They are all equal applications of the same conceptual seed. Calling it "mouth" is the colonial etymology mistake.

The atlas makes root families navigable, searchable, and visible. The root is the primary entity. Words are applications.

### 2. Words as Contextual Applications

For word-first languages (English, French, Swahili in practice) and within root-first languages, individual word entries are applications of generative seeds. An entry belongs to a root and carries:

- Its foregrounded meaning in a specific context
- Its background resonances — the non-foregrounded meanings that remain active for speakers
- Its conceptual seed (native formulation and English approximation)
- Its evidence status — how confidently the linguistics community has validated this

### 3. Concept Travel Across Languages

The same concept can live in every human language. The atlas shows how it does. When Mandinka speakers borrowed Arabic terms for religious practice and French terms for market economy, that is not corruption — it is documented concept travel with an origin, a route, and an arrival form.

The atlas tracks:
- Concepticon IDs — universal concept identifiers that exist independently of any language
- CLICS colexification — which concepts tend to be expressed by the same word, the scientific map of which meanings travel together
- Borrowing routes — where a word came from, why it came, and what it displaced or joined

This is what makes 3iAtlas differentiated from every dictionary on the planet. No other product shows you that the concept you're looking at in Mandinka is also present in Wolof, French, and Arabic — and how each language carries it differently.

---

## Language Model Types

Every language in the atlas is assigned a model type. This type governs the primary navigation mode, data structure, and entry shape for that language.

| Type | Description | Primary Navigation | Examples |
|---|---|---|---|
| `root_first` | Meaning originates in generative roots. Words are applications. | Root family browser | Mandinka, Arabic, Hebrew, Amharic |
| `word_first` | Meaning originates in discrete words. Roots exist but are etymological context, not primary navigation. | Word search | English, French, Swahili (practical) |
| `morpheme_first` | Meaning is constructed from morpheme combinations. Neither root nor word is the primary unit. | Morpheme browser | Classical Chinese, agglutinative languages |

**Navigation rule (platform decision):** The default navigation lens matches the language model type. When a user opens Mandinka, they open to root families. When they open English, they open to word search. The language model type drives the UI.

There is no override. A `root_first` language is never forced into word-first navigation. A `word_first` language is never forced into root-first navigation. Users can switch lenses — but the default is honest to the language.

---

## The Soup Model: Describing Real Spoken Language

**Platform ruling:** 3iAtlas is simultaneously Oxford-quality reference AND descriptive of real spoken language. These are not in tension. Oxford is a standard of rigor, not a standard of prescription.

West African language communities — Mandinka speakers in particular — exist in sustained contact with Wolof, Fulani, Serer, Arabic, French, and Portuguese. This contact has been happening for centuries. The resulting vocabulary is not corrupted Mandinka. It is what Mandinka speakers actually speak.

**The atlas includes all of it, tagged with full provenance.**

### Borrowing Status

Every entry carries a `borrowing_status` field:

| Value | Meaning |
|---|---|
| `native` | Derived from a root internal to the language |
| `borrowed_integrated` | Borrowed word fully integrated — native phonology, native morphology, native speakers no longer perceive as foreign |
| `borrowed_active` | Borrowed word in active use, still perceived by speakers as from another language |
| `code_mixed` | Word used in code-mixed speech; origin language clear to speakers |
| `archaic` | Present in historical record but no longer in common use |
| `neologism` | New formation — native-derived or borrowed — not yet fully integrated |
| `contested` | Status disputed among linguists or communities |

### Origin Language

Every non-native entry carries `origin_language` (ISO 639-3 code) and, where known, `borrowing_era` (approximate century or historical period).

### Evidence Graduation

Soup entries follow the same evidence graduation as root entries:

`ai_suggested → linguist_proposed → speaker_confirmed → community_confirmed`

An AI-suggested borrowing entry is not published the same way as a community-confirmed one. The evidence level is always visible. This is the atlas's mechanism for being both cutting-edge (ingesting AI-proposed data) and rigorous (requiring human validation before full publication).

### What this makes visible

When a Mandinka speaker looks up the word they use for "market," they may find:
- The native Mandinka root-derived term with full etymology
- The Arabic-origin term that arrived with Islamic trade networks
- The French-origin term that arrived with colonial administration
- All three, with their usage contexts, their evidence status, and the historical route each one traveled

That is what an atlas does. That is not what a dictionary does.

---

## The Oxford Standard

Being Oxford-quality means three things:

**1. Provenance for every claim.** Every entry, every field value, every borrowing status has a source. AI suggestions are labeled. Linguist proposals are labeled. Speaker confirmations are labeled. Nothing is published as fact that is not evidenced.

**2. Tonal and grammatical precision.** For tonal languages, tone is part of meaning — `dáa` (mouth, door, price) and `dàa` (to sleep) are not the same root. The atlas records tone class, distinguishing tone patterns, and tonal alternations. A transcription without tone is not a valid entry for a tonal language.

**3. Native speaker primacy.** The highest evidence level is `community_confirmed`. AI and linguists propose. Speakers confirm. The atlas does not override native speaker knowledge with academic reconstruction.

---

## What the Atlas Displays

The atlas is built to make the following visible:

**Root family view:** All applications of a generative root — anatomical, spatial, commercial, verbal — displayed as a family. Background resonances visible. Cross-language sibling roots (roots in other languages that share a Concepticon concept) linked.

**Concept travel map:** For any concept (via Concepticon ID), a map showing which languages have a native root for it, which have borrowed a term, and what form it takes in each. This is the visualization that no other product in the world has built for West African languages.

**Borrowing timeline:** For a given language, a view of when and from where its vocabulary has arrived — Arabic in the 9th–14th centuries, French in the colonial period, English now through digital culture.

**Language soup navigator:** A view scoped to a specific language that shows the full lexical picture — native roots, integrated borrowings, active borrowings, code-mixed vocabulary — not hidden, not apologized for, documented.

The Visualization Spec (not yet written) will define the UI implementation of these views. This spec defines what must be visualizable — the Visualization Spec defines how.

---

## What the Atlas Is Not

**Not a flashcard app.** Game modes are literacy surfaces that use atlas data. They are not the product. The games package is a separate package that calls the atlas API. The atlas does not change its data model to serve game mechanics.

**Not a word list service.** `/wordlist` is an API endpoint for external consumers. It is not the product's identity.

**Not prescriptive.** The atlas does not tell speakers what their language should be. It documents what it is, was, and is becoming — with full evidence, full provenance, and full visibility into who said what.

**Not English-first.** The atlas does not define Mandinka words in terms of English equivalents. It defines them in terms of their own conceptual seeds, with English translations as one output among many. `dáa` is not "mouth + door + price." It is `dáa`. The English glosses are navigation aids for non-speakers, not definitions.

**Not a single-language product.** The atlas is built to hold every language, with each language navigated according to its own model type. Mandinka is the first fully built language. It is not the only language.

---

## Relationship to the Platform Loop

The atlas sits in the following loop:

```
3iAtlas (atlas, game surfaces, literacy events)
    ↓ XP events, mastery signals, game results
Sky (social publishing, community layer)
    ↓ community validation events
ESU (Mēh₁n̥s governance layer)
    ↓ governance approval
DVE (Dheghom vault + verification engine)
    ↓ validated, immutable data
Dictionary (the atlas's data service layer)
    ↑ enriched entries, confirmed roots
3iAtlas
```

The atlas is both the input and the output of this loop. Community interaction generates linguistic evidence. That evidence graduates through DVE into permanent atlas records. The atlas then shows the upgraded record to the community.

`aiwa_entry_uuid` is minted by DVE at import. The atlas never regenerates it. Linguistic fields are locked after import. Only the evidence graduation field advances — it never regresses.

---

## Downstream Implications

### Data Model
- Root (`aiwa-root` CPT) is the primary entity for `root_first` languages.
- Entry (`aiwa-entry` CPT) is an application of a root or a standalone word.
- Every entry has `borrowing_status`, `origin_language`, `evidence_status`.
- Concepticon ID (`aiwa_concepticon_id`) is the cross-language bridge.

### API
- `GET /root/{root_id}` returns the root + all its applications + cross-language siblings. This is a first-class endpoint.
- `GET /lookup` returns an entry by slug. Works as before for word-first languages.
- `GET /concept/{concepticon_id}` returns the concept across all languages the atlas covers. This endpoint does not yet exist — it is a target.
- No endpoint hides borrowing status. Soup entries are fully accessible.

### UI
- Root-first languages open to root family navigation by default.
- Language selector shows `model_type` as a visible attribute.
- Evidence status is displayed on every entry — users see whether they are reading a community-confirmed record or an AI suggestion.
- Borrowing status is displayed. The atlas never hides where a word came from.

### Game Service
- Games use the atlas API with `mode=strict`.
- Games never use `mode=ecology`.
- Games are a literacy surface on atlas data. The atlas data model does not change to serve game mechanics.
- `GAME-SERVICE-INTAKE-SPEC-v1.0` must be written and committed before `syncNow()` is implemented.

### WordPress Authentication
- WordPress authentication is prohibited for all 3iAtlas user-facing products. Permanent suite-wide rule.
- `is_user_logged_in()` and `wp_nonce` are not used on new user-facing endpoints.
- The Webster model (ephemeral page tokens + consumer API keys) governs all access.

---

## Summary

3iAtlas is a Linguistic Atlas. It maps the conceptual territory of human language — how meaning is generated inside a language through roots, how it applies across contexts through entries, and how it travels between languages through documented contact.

It holds borrowed vocabulary alongside native vocabulary, tagged and evidenced, because the language soup is not a problem to be solved — it is the living record of how communities have met, traded, prayed, and governed together across centuries.

It navigates each language according to that language's own structure — root-first for root-first languages, word-first for word-first languages.

It holds itself to Oxford standards of provenance and precision not to be prescriptive, but because communities deserve accurate records of their own languages.

It is the only product of its kind for West African languages. It is being built to be the only product of its kind for any language.
