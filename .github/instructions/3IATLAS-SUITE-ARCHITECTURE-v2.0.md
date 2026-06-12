# 3iAtlas Suite — Integration Architecture

## Version 2.0 · Starisian Technologies · AIWA · June 2026 · Confidential

**Supersedes:** `3IATLAS-SUITE-ARCHITECTURE-v1.0.md`
**ADR References:** ADR-002, ADR-008, ADR-011, ADR-012
**Related specs:** `3IATLAS-DATA-ZONES-AND-TRUST-BOUNDARY-SPEC-v1.0`, `3IATLAS-IDENTITY-AND-GAME-SERVICES-DECISION-v1.0`, `3IATLAS-PRODUCT-IDENTITY-SPEC-v1.0`

**What changed from v1.0:**
- Two-door intake topology added (ADR-008)
- Three trust zones added, with reference to Data Zones spec (ADR-002)
- Auth model updated: suite JWT via Identity Service, not Helios directly for user-facing
- Progress sync target moved from WP endpoint to Game Service (Identity & Game Services Decision §3, §6)
- RLC Node Engine promoted to Suite Game Service (Identity & Game Services Decision §3)
- SaaS-first commercial model formalized (Identity & Game Services Decision §10)
- `/progress/sync` WP endpoint marked deprecated

---

## Why This Exists

Mandinka is an oral language. It has been spoken for centuries — by farmers, griots, elders, mothers, traders, children — and almost never written. There is no alphabet song. No primer. No dictionary on a shelf. When a Mandinka speaker goes to school, they go as an ESL student in a system built for a colonial language. If they love writing, they write in English or French — in someone else's house. The intelligence is there. The language is there. What has never been there are tools built for their language, by people who understand their language.

The 3iAtlas suite exists to change that. Not as a language learning product — these users are not learners. They are fluent, lifelong speakers of their mother tongue. The gap is orthographic: they have never been shown how to write what they already know. The tools must honor that. They must feel like a studio, not a classroom. They must never make a native speaker feel like a child in their own language.

---

## The Suite

Four tools. One shared data foundation. Independent frontends.

| Tool | Repo | What It Is |
|---|---|---|
| **Dictionary** | `sparxstar-3iatlas-dictionary` | The hub. Authoritative lexical data store. Public-facing dictionary experience + word games. Every other tool consumes from here. |
| **WordPad** | `sparxstar-3iatlas-wordpad` | Writing tool. First place many users will ever write in their mother tongue. Draws spelling, thesaurus, and rhyme support from the Dictionary API. |
| **RLC** | `sparxstar-3iatlas-rlc` | Classroom collection game. Collects new words from the community. Feeds the pipeline that enriches the Dictionary over time. |
| **S2S** | `sparxstar-3iatlas-s2s` | Sound to Symbol. Speaks → sees it written. The bridge between oral mastery and written form. Reads from Brain, writes through Esu. |

---

## The Two-Door Intake Topology (ADR-008)

All data entering the platform uses one of two intake doors. The door determines authority, governance path, and zone placement. Nothing enters without a door.

### Communication Door (Sky)

Sky is the Communication Door. Human acts enter here: speaker contributions, classroom sessions, learner queries, oral recordings, community corrections. Sky understands communication as a sign — not defined by carrier type (text, audio, gesture) but by the communicative act behind it (INV-001).

Anything that enters through Sky is contributor-linked. It carries a Helios identity reference (INV-010). It enters the governed evidence pipeline: quarantine → DVE review → Zone 1 publication. No raw submission from Sky becomes authoritative without DVE sign-off (ADR-011: unconditional capture, asynchronous governance — deny nothing, quarantine instead).

### Machine Door (ESU)

ESU is the Machine Door. Archives, automated transcription, linguistic analysis, import packages, and AI-generated suggestions enter here. ESU produces evidence. It does not produce approved records.

Records entering through ESU carry `evidence_status: ai_suggested` or `linguist_proposed`. They move through the evidence graduation path before reaching Zone 1:

```
ai_suggested → linguist_proposed → speaker_confirmed → community_confirmed
```

Only `speaker_confirmed` and above reach public-facing 3iAtlas surfaces.

### Why Two Doors Matter

One door collapses the distinction between what speakers say and what machines compute. That distinction is the difference between a linguistically honest record and a hallucinated one. The platform keeps two doors so the governance system always knows which kind of claim it is evaluating.

---

## The Three Trust Zones

Data in the platform lives in exactly one of three zones. Full zone definitions are in `3IATLAS-DATA-ZONES-AND-TRUST-BOUNDARY-SPEC-v1.0`.

| Zone | Contents | Governs |
|---|---|---|
| Zone 1: Governed Evidence | Approved entries, speaker confirmations, contributor-linked records | DVE |
| Zone 2: Reference / Comparative | Cognate sets, borrowing events, reconstructed roots | Qualified linguists |
| Zone 3: Derived Graph | Graph projections, semantic maps (never source of truth) | Regenerated from Z1+Z2 |

The dictionary is a Zone 1 service. It imports Zone 1 records. It does not store Zone 2 or Zone 3 data.

---

## The Data Flow

```
COMMUNITY / ORAL KNOWLEDGE
          │
          ▼ Sky (Communication Door)
    RLC (collect in classroom)
    aiwa_token → QC → teacher approval → aiwa_word
          │
          ▼
    AIWA REVIEW / DVE (human governance)
          │
          ▼ ESU (Machine Door, for archive imports)
    DICTIONARY (Zone 1 — authoritative lexical store)
          │
    ┌─────┼─────────────────────────────┐
    ▼     ▼                             ▼
WordPad  Game Service              External consumers
(spell,  (sessions, progress,      (API key auth,
 rhyme,   XP, rewards signals)      wordlist, lookup)
 thesaurus)
```

**The rule:** Data flows down from Dictionary to consumers. Consumers do not write lexical data back to the Dictionary at runtime. New words enter through the RLC → AIWA governance pipeline — never through a direct write from a consumer app.

---

## The API — Stack-Agnostic Contract

The Dictionary exposes a REST API. Consumers do not care whether the backend is WordPress, Node, or any other implementation. The contract is the API.

**Base path:** `/sparxstar/v1/dictionary`

**Auth model:**

- Read endpoints (`/lookup`, `/search`, `/wordlist`, `/languages`, `/domains`, `/game-set`, `/word-of-day`, `/spell`): public, page-token authenticated (ephemeral HMAC-SHA256 token, 1hr TTL, 600 req/token) or API key.
- `/wordlist`: API key only. No ephemeral page token.
- `/page-token`: returns a fresh page token. No auth.
- Game progress: routed to the **3iAtlas Game Service** (suite JWT authenticated). The WP `/progress/sync` endpoint is deprecated — see §Game Service below.

**WordPress authentication is prohibited for all user-facing endpoints.** WordPress sessions are for admins only.

### Endpoint Reference

#### GET /lookup
Parameters: `slug` OR `uuid` (one required), `lang` (default `en`), `lang_source` (optional), `mode` (strict | ecology | cross_language)

Response: Full entry — headword, IPA, phonetic, POS, definition, translations (EN + FR), example sentences, audio URL, synonyms, antonyms, domain, origin, AIWA level, Concepticon ID, cross-language siblings, borrowing status.

#### GET /search
Parameters: `q` (min 2 chars), `lang`, `lang_source`, `pos`, `aiwa_level`, `domain`, `speaker_community`, `mode`, `per_page` (default 20, max 100), `page`

Response: Array of summary entries (no example sentences — performance).

#### GET /wordlist
Parameters: `lang`, `lang_source`, `alpha`, `aiwa_level`, `domain`, `per_page` (default 1000, max 2000), `page`

**Auth: API key only.** Consumer tools, game service, RLC spelling signal. No ephemeral page token.

#### GET /languages
No parameters. Returns all language terms with word counts. Authoritative source for what languages exist in the corpus — never hardcode language lists in any consumer tool.

#### GET /domains
Parameters: `lang_source` (optional). Returns semantic domain taxonomy. Used by RLC session setup and game domain filtering.

#### GET /game-set
Parameters: `lang_source` (required), `domain` (optional), `aiwa_level` (optional), `limit` (default 20, max 50), `include_audio` (bool), `mode` (default: strict)

Returns a curated word set for game use. Minimum word requirements: headword + at least one translation + IPA. Words without these fields are automatically excluded.

**mode=strict is required for all game service calls.** The game service must never use ecology or cross_language mode to populate game word sets.

#### GET /word-of-day
No parameters. Returns one entry per calendar day (deterministic — same word for all users).

#### GET /spell
Parameters: `q` (required), `lang_source` (required). Spell-check lookup. Returns match status and suggestions.

#### GET /page-token
No auth. Returns a short-lived page token for browser-based read access.

#### POST /progress/sync
**DEPRECATED.** The WP `/progress/sync` endpoint is retired. Mark deprecated in the plugin. Remove after the Game Service intake is live. No new clients may be built against this endpoint. Progress routes to the 3iAtlas Game Service (see below).

---

## The Game Service (Promoted from RLC)

The Node + Express engine built for RLC is promoted from "RLC's backend" to the **3iAtlas Game Service** — the single handler for game sessions, progress, XP, reward signals, and gameplay-accuracy signal aggregation across the entire suite.

**Auth:** Suite JWT from the Identity Service (see `3IATLAS-IDENTITY-AND-GAME-SERVICES-DECISION-v1.0`).

**Dictionary games integration:**
- Dictionary game client `syncNow()` POSTs the frozen event schema to the Game Service instead of the WP endpoint.
- Event schema (frozen, do not change): `word_uuid`, `game_type`, `outcome`, `attempts`, `xp`, `timestamp`, production-vs-recognition flag.
- The IndexedDB offline outbox and idempotency behavior carry over unchanged.

**Guest play:** Unauthenticated users can play. Guest progress is device-local only (anonymous device ID, IndexedDB). One non-blocking prompt: creating an account keeps progress across devices. No progress syncs to the Game Service without a suite JWT.

**`syncNow()` implementation is blocked** until `GAME-SERVICE-INTAKE-SPEC-v1.0` is committed to `.github/instructions/`.

---

## The Identity Service

A single identity system serves all 3iAtlas products. Full decision record in `3IATLAS-IDENTITY-AND-GAME-SERVICES-DECISION-v1.0`.

**Tier model (unchanged from WordPad v4.0, promoted to suite-level):**

| Tier | Level | Login |
|---|---|---|
| Lower Basic | Grades 1–6 | Class code + screen-name tap (no password) |
| Upper Basic | Grades 7–9 | Screen name + 4-digit PIN |
| Senior Secondary | Grades 10–12 | Screen name + password |
| Adult | Post-secondary | Full credentials |

**Permanent rules:**
- WordPress authentication is prohibited for all 3iAtlas user-facing products.
- No email or personal data collected from minors.
- Teacher visibility rules per tier, per WordPad v4.0 specification.
- When `SPARXSTAR_PLATFORM_PRESENT` is set, Helios is the issuer. Apps are unchanged; the doorway swaps.

---

## The Dictionary Experience (Frontend)

The Dictionary React frontend has two modes: **Browse** and **Play**. They are equal.

### Browse Mode

Standard dictionary experience. Search, filter by language and domain, view word detail. For root_first languages, the primary view is the root family — conceptual seed, all applications, compounds, background resonances — not a single-word definition.

**The principle:** The Dictionary is a mirror, not a teacher. It shows speakers their language written down. It does not introduce vocabulary they don't know.

### Play Mode (Games)

Games live inside the Dictionary. One login. The same session. Browse ↔ Play is a tab switch.

**Core user flow:** Word of the Day → Learn → Practice this word (seeds a game)

**Secondary flow:** Browse domain → Practice this domain (seeds a game with that domain's word set)

### Games Design Principles

These are orthographic practice games — not vocabulary games. The user has the vocabulary. The games teach writing.

- Never start from nothing. Every game provides a clue. Cold recall in an unfamiliar orthography is humiliating. Scaffolding is respect.
- The AccessoryBar is always present for any game requiring typed input. Mandinka characters (ŋ ɓ ɗ ñ ɲ ʔ) are not on a standard keyboard. This is non-negotiable.
- Wrong answers teach, they don't shame.
- Progress is visible. "You can now write 23 words" is meaningful.

### Game Types

| Game | Mechanic | Primary data requirement |
|---|---|---|
| Listen & Write | Audio → typed word | `/game-set` with `include_audio=true` |
| Arrange the Word | Scrambled tiles → correct order | `/game-set` — any word with headword |
| Meaning Match | Written word → correct English meaning | `/game-set` — needs translation_en |
| Complete the Sentence | Sentence with headword blanked → player fills in | `/game-set` — words with example sentences |
| Domain Flash | Flashcard through semantic domain | `/game-set?domain={slug}` |

---

## How WordPad Connects

WordPad consumes the Dictionary API via a server-side proxy. The dictionary never goes to the device directly.

| WordPad Need | Dictionary Endpoint |
|---|---|
| Spell check | `/spell?q={word}&lang_source={lang}` |
| Synonym lookup | `/lookup?slug={word}` — returns synonyms |
| Antonym lookup | `/lookup?slug={word}` — returns antonyms |
| Rhyme lookup | `/lookup?slug={word}` — returns rhyme entries |
| Language list | `/languages` |
| Domain list | `/domains?lang_source={lang}` |

WordPad does not write to the Dictionary. All calls go through WordPad's server-side layer — never direct from browser.

---

## How RLC Connects

RLC consumes the Dictionary API at session setup only.

| RLC Need | Dictionary Endpoint |
|---|---|
| Language selector | `/languages` |
| Domain selector | `/domains?lang_source={lang}` |
| Spelling signal word list | `/wordlist?lang_source={lang}&per_page=2000` (API key auth) |

Spelling signal: RLC checks submitted words against the cached wordlist. Exact match → `confirmed`. Fuzzy match (trigram 50–89) → `variant`. No match → `discovery`. Logic runs in RLC backend — not via live Dictionary calls during gameplay.

---

## Offline Strategy

Gambia's connectivity is improving but uneven. The Dictionary and games must work on a 2G connection and degrade gracefully when the connection drops mid-session.

**Principle: Download more than needed.** When connected, pre-fetch beyond what the user has explicitly requested. Most game sessions should run entirely from cache.

**What gets cached:**

| Data | When cached | TTL |
|---|---|---|
| Word of the Day | On app load | 24 hours |
| `/languages` | On app load | 7 days |
| `/domains` for selected language | On language selection | 7 days |
| `/game-set` for selected domain | On domain selection | 3 days |
| Adjacent domain sets | Automatically after domain loads | 3 days |
| `/wordlist` for RLC spelling signal | On language selection | 3 days |

**Progress sync:** Game events are written to IndexedDB immediately. When connectivity restores, the outbox POSTs to the Game Service. Events are never lost — queued, not discarded.

**Service worker:** Cache-first for all `/sparxstar/v1/dictionary/*` GET responses. Background sync for the game event outbox.

---

## Commercial Model (SaaS-First)

All 3iAtlas products are Starisian-hosted SaaS. Offline-first clients + hosted backend. Customers never deploy servers.

Three sales motions (June 2026):
1. **Institutional / government** — donor-funded, RFP/tender procurement, pilot → evidence → multi-year contract. Funder-facing impact reporting is a product requirement.
2. **School subscriptions** — per-classroom or per-student annual; teacher self-serve; class-code login (Lower Basic) is the standard flow.
3. **Consumer / diaspora** — app-store freemium subscription.

**Indigenous-market requirement:** Contractual data-export/repatriation guarantee. Community data ownership with a guaranteed exit path. Sales differentiator and ethical requirement.

---

## Version History

| Version | Date | Changes |
|---|---|---|
| 1.0 | May 2026 | Initial document. Suite architecture, API contract, games design, consumer relationships. |
| 2.0 | June 2026 | Two-door intake topology (ADR-008). Three trust zones cross-reference. Auth model updated to Identity Service / suite JWT. Progress routing to Game Service. RLC Node Engine promoted to Suite Game Service. SaaS-first commercial model formalized. WP /progress/sync deprecated. |

---

*Starisian Technologies · AIWA · Confidential — Internal Use Only*
