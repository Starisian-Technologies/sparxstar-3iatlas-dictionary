# 3iAtlas Suite — Integration Architecture

## Version 1.0 · Starisian Technologies · AIWA · May 2026 · Confidential

---

## Why This Exists

Mandinka is an oral language. It has been spoken for centuries — by farmers, griots, elders, mothers, traders, children — and almost never written. There is no alphabet song. No primer. No dictionary on a shelf. When a Mandinka speaker goes to school, they go as an ESL student in a system built for a colonial language. If they love writing, they write in English or French — in someone else's house. The intelligence is there. The language is there. What has never been there are tools built for their language, by people who understand their language.

The 3iAtlas suite exists to change that. Not as a language learning product — these users are not learners. They are fluent, lifelong speakers of their mother tongue. The gap is orthographic: they have never been shown how to write what they already know. The tools must honor that. They must feel like a studio, not a classroom. They must never make a native speaker feel like a child in their own language.

This is what AIWA and Starisian Technologies are building. Everything else follows from this.

---

## The Suite

Four tools. One shared data foundation. Independent frontends.

| Tool | Repo | What It Is |
| :---- | :---- | :---- |
| **Dictionary** | `sparxstar-3iatlas-dictionary` | The hub. Authoritative lexical data store. Public-facing dictionary experience \+ word games. Every other tool consumes from here. |
| **WordPad** | `sparxstar-3iatlas-wordpad` | Writing tool. First place many users will ever write in their mother tongue. Draws spelling, thesaurus, and rhyme support from the Dictionary API. |
| **RLC** | `sparxstar-3iatlas-rlc` | Classroom collection game. Collects new words from the community. Feeds the pipeline that enriches the Dictionary over time. |
| **S2S** | `sparxstar-3iatlas-s2s` | Sound to Symbol. Speaks → sees it written. The bridge between oral mastery and written form. Reads from Brain, writes through Esu. |

---

## The Data Flow

```
COMMUNITY (oral knowledge)
    │
    ▼
RLC (collect in classroom)
    │  aiwa_token → QC → teacher approval → aiwa_word
    ▼
AIWA REVIEW (human governance)
    │
    ▼
DICTIONARY (authoritative lexical store)
    │
    ├──► WordPad (spell check, thesaurus, rhyme)
    ├──► Games (word pools, domain sets, audio)
    ├──► RLC (domain lists, spelling signal)
    └──► S2S (canonical word forms, IPA)
```

**The rule:** Data flows down from Dictionary to consumers. Consumers do not write back to the Dictionary at runtime. New words enter through the RLC → AIWA governance pipeline — never through a direct write from a consumer app.

---

## The API — Stack-Agnostic Contract

The Dictionary exposes a REST API. Consumers do not care whether the backend is WordPress, Node, or any other implementation. The contract is the API. This section defines that contract.

**Base path:** `/sparxstar/v1/dictionary`

**Auth model:**

- Read endpoints (`/lookup`, `/search`, `/wordlist`, `/languages`, `/domains`, `/game-set`, `/word-of-day`): Public, rate-limited (100 requests / 15 min / IP). No auth required.  
- Progress endpoints (`/progress/sync`): Require Helios Bearer token. WordPress session is for admins only — regular users authenticate through Helios.  
- No community voting endpoints — removed. Games replace that mechanism.

### Endpoints

#### GET /lookup

Parameters: `slug` OR `uuid` (one required), `lang` (default `en`), `lang_source` (optional — taxonomy slug e.g. `mandinka`)

Response: Full entry — headword, IPA, phonetic, part of speech, definition, translations (EN \+ FR), example sentences, audio URL, synonyms, antonyms, domain, origin.

#### GET /search

Parameters: `q` (min 2 chars), `lang`, `lang_source`, `pos` (optional), `per_page` (default 20, max 100), `page`

Response: Array of summary entries (no example sentences — performance).

#### GET /wordlist

Parameters: `lang`, `lang_source`, `alpha` (optional), `per_page` (default 1000, max 2000), `page`

Purpose: Lightweight word list for consumer tools — offline caching, game word pools, RLC spelling signal.

#### GET /languages

No parameters. Returns all language terms with word counts.

```json
{
  "success": true,
  "data": {
    "languages": [
      { "slug": "mandinka", "name": "Mandinka", "count": 4231 },
      { "slug": "wolof",    "name": "Wolof",    "count": 1820 }
    ]
  }
}
```

This is the authoritative source for what languages exist in the corpus. All consumer tools fetch from here — never hardcode language lists.

#### GET /domains

Parameters: `lang_source` (optional)

Returns semantic domain taxonomy for a language. Used by RLC to populate session setup and by games to build domain-filtered word sets.

```json
{
  "success": true,
  "data": {
    "domains": [
      { "slug": "agriculture-6.2", "name": "Agriculture", "code": "6.2", "count": 312 },
      { "slug": "family-2.1",      "name": "Family",      "code": "2.1", "count": 189 }
    ]
  }
}
```

#### GET /game-set

Parameters: `lang_source` (required), `domain` (optional), `limit` (default 20, max 50), `include_audio` (bool, default false)

Purpose: Returns a curated word set for game use. Words must have: headword, at least one translation, IPA. Words without these fields are excluded automatically.

```json
{
  "success": true,
  "data": {
    "words": [
      {
        "uuid": "...",
        "headword": "alibalaa",
        "ipa": "/alibalaː/",
        "phonetic": "ahl-ehhb-ahl-ah-ah",
        "translation_en": "calamity, disaster",
        "translation_fr": "calamité, désastre",
        "part_of_speech": "n",
        "domain": "General",
        "example_sentence": "Alamaa n tanka la alibalaa la",
        "example_translation_en": "May God save us from calamity",
        "audio_url": null
      }
    ]
  }
}
```

**This endpoint is the games' primary data source.** It is not the same as `/wordlist` — it returns richer data selected for game quality.

#### GET /word-of-day

No parameters. Returns one entry per calendar day (deterministic — same word for all users on the same day).

```json
{
  "success": true,
  "data": {
    "word": { /* full entry object */ },
    "date": "2026-05-14"
  }
}
```

#### POST /progress/sync

**Requires Helios Bearer token.**

Accepts a batch of game events from the client's offline outbox. Fires myCred hooks server-side. Returns awarded points.

```json
// Request body
{
  "events": [
    { "type": "aiwa_game_word_correct",     "word_uuid": "...", "game": "listen_write", "ts": 1747000000 },
    { "type": "aiwa_game_session_complete", "domain": "agriculture-6.2",               "ts": 1747000060 }
  ]
}

// Response
{
  "success": true,
  "data": {
    "xp_awarded": 30,
    "gold_awarded": 0,
    "events_processed": 2
  }
}
```

Idempotent — duplicate events (same word\_uuid \+ type \+ ts) are detected and skipped. Safe to retry.

---

## The Dictionary Experience (Frontend)

The Dictionary is not just a data store. It is the user-facing destination. The React frontend has two modes: **Browse** and **Play**. They are equal. Neither is a secondary feature.

### Browse Mode

Standard dictionary experience. Search, filter by language and domain, view word detail (IPA, definition, example sentences, audio). Word of the Day card is the entry point — it shows a word you already know, written down, possibly for the first time.

The principle: **the Dictionary is a mirror, not a teacher.** It shows you your language written. It does not introduce vocabulary you don't know.

### Play Mode (Games)

Games live inside the Dictionary. You log in once. The same session, the same app. Moving from Browse to Play is a tab switch, not a new destination.

**Word of the Day → Learn → Play** is the core flow:

1. Word of the Day card is visible on the Dictionary home  
2. "Learn more" opens the full entry  
3. "Practice this word" launches a game seeded with that word and its domain neighbors

**Browse → Practice** is the secondary flow:

1. User browses words in a domain (e.g., Agriculture)  
2. "Practice this domain" button → launches a game with that domain's word set

### Games Design Principles

These are not vocabulary games. The user already has the vocabulary. They are **orthographic practice** — learning to write words you already speak.

The design must honor this:

- **Never start from nothing.** Every game gives a clue — an audio clip, a definition, partial letters, a domain hint. Cold recall of spelling in an unfamiliar orthography is humiliating. Scaffolding is respect.  
- **The AccessoryBar is always present.** Mandinka has characters (ŋ ɓ ɗ ñ ɲ ʔ) that are not on a standard keyboard. Any game requiring typed input must surface the AccessoryBar. This is non-negotiable.  
- **Wrong answers teach, they don't shame.** A wrong answer reveals more information, not just "incorrect."  
- **Progress is visible.** "You can now write 23 words" is meaningful. Letter grades are not.

### Games — The Five Types

#### 1\. Listen & Write

*Audio → Typed word. The most important game.*

The audio file for a dictionary word plays. The player types the word. Length is shown as blank tiles. AccessoryBar present. On correct answer: IPA and definition appear, confirming what they already knew. On wrong answer: first letter is revealed, they try again.

**Why this matters:** Hearing a word you've said ten thousand times and then writing it correctly for the first time is the core experience this suite exists to create.

**Data requirement:** `/game-set` with `include_audio=true`. Words without audio are excluded from this game.

#### 2\. Arrange the Word

*Scrambled letter tiles → correct order.*

Letters of the word are presented as movable tiles, scrambled. Player drags or taps to arrange them. Domain and English meaning are shown as hints throughout. AccessoryBar is not needed — letters are pre-provided.

**Why this matters:** Lower barrier than typing from scratch. Good for early learners and younger students in the 7-week course.

**Data requirement:** `/game-set` — any word with headword.

#### 3\. Meaning Match

*Written Mandinka word → correct English meaning.*

The written Mandinka form is shown. Player picks from three English meanings. This tests whether they can connect the written form to the meaning they already know.

**Why this matters:** Reinforces the bridge between written form and oral knowledge.

**Data requirement:** `/game-set` — needs translation\_en. Distractors drawn from same domain.

#### 4\. Complete the Sentence

*Example sentence with headword blanked → player fills it in.*

A real example sentence from the dictionary is shown with the key word removed. Player types it using AccessoryBar.

**Why this matters:** Context-based. The sentence is a real utterance — the kind of thing they have actually said. They are not guessing; they are writing something they know.

**Data requirement:** `/game-set` — words with at least one example sentence. Sentence must have translation for post-answer reveal.

#### 5\. Domain Flash

*Flashcard through a semantic domain.*

Cards cycle through all words in a domain. Each card shows the English meaning — player tries to recall the Mandinka word (written). Flip reveals the word \+ IPA \+ audio if available. Self-reported: "I knew it" or "Still learning."

**Why this matters:** Domain-organized learning mirrors how people actually think about vocabulary — by context (family words, farming words, food words).

**Data requirement:** `/game-set?domain=agriculture-6.2`.

### Games and the 7-Week Course

Games are not separate from the course — they are part of it. The 7-week course curriculum should map directly to game types and domain sets:

- Early weeks: Arrange the Word (low barrier, letter recognition)  
- Mid weeks: Listen & Write for core vocabulary domains  
- Later weeks: Complete the Sentence (grammar and context)  
- Throughout: Domain Flash as homework / self-directed review

When the course ends, students already know where to go. They have been playing on the same Dictionary site they will keep using. The transition from structured to self-directed is seamless because there is no transition — it is the same tool.

**Note:** The course curriculum document should be the driver for which domains and word sets are prioritized in the game word pools. When AIWA produces the curriculum outline, game domain sequencing should be updated to match.

---

## How WordPad Connects

WordPad consumes the Dictionary API via a server-side proxy. The dictionary never goes to the device directly.

| WordPad Need | Dictionary Endpoint |
| :---- | :---- |
| Spell check | `/search?q={word}&lang_source={lang}` — checks if word exists, returns variants |
| Synonym lookup | `/lookup?slug={word}` — returns synonyms from entry |
| Antonym lookup | `/lookup?slug={word}` — returns antonyms from entry |
| Rhyme lookup | `/lookup?slug={word}` — returns phonetic variants as rhyme approximation |
| Language list for selector | `/languages` |
| Domain list | `/domains?lang_source={lang}` |

**What WordPad does not do:**

- Write to the Dictionary  
- Store dictionary data locally in any form  
- Make direct calls to the Dictionary REST API from the browser (all calls go through WordPad's server-side layer)

### WordPad → Games Bridge (Future)

When spell check suggests a correction, a "Practice this word" micro-link can deep-link into the Dictionary's Play mode for that word. This is a URL-based link — no shared session state required. Format: `https://dictionary.aiwa.gm/play?word={slug}&lang={lang}`.

---

## How RLC Connects

RLC consumes the Dictionary API at session setup and during gameplay. It does not call the Dictionary at any other time.

| RLC Need | Dictionary Endpoint |
| :---- | :---- |
| Populate language selector | `/languages` |
| Populate domain selector | `/domains?lang_source={lang}` |
| Offline word list for spelling signal | `/wordlist?lang_source={lang}&per_page=2000` (cached at session start) |

**The spelling signal:** RLC checks submitted words against the cached wordlist. If the submitted word exactly matches a wordlist entry → `confirmed`. If it fuzzy-matches (trigram score 50–89) → `variant`. If no match → `discovery`. This logic runs in the RLC backend against the cached wordlist — not via live Dictionary calls during gameplay.

**Discovery → Dictionary pipeline:**

```
RLC discovery signal
    → aiwa_token (unmatched word)
    → QC phase (class votes: is this a real word in our language?)
    → Teacher approval
    → aiwa_word (promoted)
    → AIWA human review
    → If approved: new dictionary entry created
    → Next /wordlist export includes this word
```

This is the community-sourced enrichment pipeline. The dictionary grows because students play. No reverse flow at the API level — the pipeline runs through AIWA governance, not a REST endpoint.

---

## What Changes in Existing Specs

### Dictionary Direction → v3

**Remove entirely:**

- Section 2 (Community Corrections & Voting) — the aiwa-cpt-correction CPT, all AJAX voting endpoints, correction routing, admin queue, and all frontend voting UI  
- The `user_vote`, `vote_counts`, and `corrections` fields from the `/lookup` endpoint response  
- The `isLoggedIn`/`userId` fields from `wp_localize_script` (no longer needed for community features)

**Add:**

- `/domains` endpoint (Section 3\)  
- `/game-set` endpoint (Section 3\)  
- `/word-of-day` endpoint (Section 3\)  
- Games as a first-class feature of the frontend (new Section 4 alongside Browse)  
- Play mode UI spec (Browse ↔ Play tab navigation)  
- Five game types with data requirements (from this document)

**Modify:**

- Section 6 Work Plan — remove Phase 1D (community class) and Phase 2E/2F (voting UI). Add Games Phase.

### WordPad Spec — No structural changes

WordPad already correctly specifies server-side dictionary lookups through the Dictionary API. The endpoint URLs align with this spec. No changes required.

### RLC Spec v2.1 — One addition

Add `/domains` endpoint as the source for the session domain selector. Currently the spec implies domains come from the dictionary export — they should come from the live `/domains` endpoint at session setup, with a fallback to a hardcoded list if the API is unreachable.

---

## Resolved Decisions

| ID | Question | Answer |
| :---- | :---- | :---- |
| OQ-S1 | Auth layer for regular users | **Helios** — not WordPress. WP auth is for admins only. Regular users authenticate through Helios Bearer tokens. The Dictionary REST API validates Helios tokens on any endpoint that writes progress or awards points. |
| OQ-S2 | Game progress persistence | **Yes — myCred**, same pattern as RLC. Points awarded for game activity. Real-world rewards drive return visits. Progress stored server-side against the user's myCred account. See MyCred section below. |
| OQ-S3 | URL structure | **Subdomain** — `dictionary.aiwa.gm` (or equivalent). Deep-link format from WordPad: `https://dictionary.aiwa.gm/play?word={slug}&lang={lang}` |
| OQ-S4 | Curriculum document | Exists or will exist. Domain sequencing in games follows it. Not a blocker for games spec. |
| OQ-S5 | Offline support | **Yes — offline-first, cache aggressively.** Gambia connectivity is improving but not consistent everywhere. Download more than needed. See Offline Strategy section below. |

---

## MyCred Gamification

Games award points through the same myCred hook pattern as RLC. The Dictionary plugin fires WordPress action hooks; myCred (when active) listens and awards points. When myCred is absent, hooks fire as no-ops — the game still works, nothing breaks.

**Auth bridge:** The user's Helios identity must be mapped to a WordPress user ID for myCred to award points. This mapping is resolved at login — Helios token → WP user lookup. The Dictionary plugin reads `get_current_user_id()` after Helios auth resolves.

### Hook Map

| Hook | Trigger | Award |
| :---- | :---- | :---- |
| `aiwa_game_word_correct` | Player answers a word correctly in any game | \+5 XP |
| `aiwa_game_listen_write_correct` | Correct answer specifically in Listen & Write | \+10 XP (harder — extra reward) |
| `aiwa_game_session_complete` | Player completes a full game session (min 10 words) | \+25 XP |
| `aiwa_game_domain_mastered` | Player scores 100% on a full domain set | \+50 Gold |
| `aiwa_game_streak_3` | 3 correct answers in a row | \+15 XP bonus |
| `aiwa_game_new_word_practiced` | First time practicing a word not previously seen | \+8 XP |
| `aiwa_game_return_visit` | Player opens games on a new calendar day | \+10 XP |

**Point types:** XP \= session leaderboard and lifetime total. Gold \= redeemable currency for real-world rewards. Same split as RLC.

**Return visit design:** The `aiwa_game_return_visit` hook and the Word of the Day card together create a daily draw. The word changes every day. The points reset daily opportunity. These are the two mechanisms that bring people back.

### What "Real-World Rewards" Means

MyCred Gold is the currency that connects to tangible value — airtime top-up, market discounts, event tickets, or other rewards AIWA establishes with community partners. The specific reward catalog is AIWA's decision. The platform delivers the currency; AIWA defines what it buys. This must be documented in AIWA policy (AIWA-DOC series), not in this spec.

---

## Offline Strategy

Gambia's connectivity is improving but uneven. The Dictionary and games must work on a 2G connection on a low-end Android during a good day — and they must degrade gracefully when the connection drops mid-session.

### Principle: Download More Than Needed

When a user is connected, the app pre-fetches beyond what they've explicitly requested. The goal is that most game sessions run entirely from cache, with the network used only for syncing progress and refreshing word sets.

### Caching Architecture

**Storage:** IndexedDB (not localStorage — more capacity, works with larger word sets).

**What gets cached:**

| Data | When | TTL |
| :---- | :---- | :---- |
| Word of the Day | On app load | 24 hours |
| `/languages` response | On app load | 7 days |
| `/domains` for selected language | On language selection | 7 days |
| `/game-set` for selected domain | On domain selection | 3 days |
| **Adjacent domain sets** | Automatically, after selected domain loads | 3 days |
| `/wordlist` for RLC spelling signal | On language selection | 3 days |

**Adjacent domain pre-fetch:** When a user selects Agriculture (domain 6.2), the app also pre-fetches Family (2.1) and Food (5.2) in the background without prompting. The exact adjacency map is defined by the curriculum sequence — domains taught near each other in the 7-week course are "adjacent" for pre-fetch purposes.

**Game sessions:** Once the `/game-set` is cached, a game session runs entirely offline. No network calls during gameplay.

### Progress Sync

When a user earns points offline, progress is written to IndexedDB immediately. When connectivity restores, a sync queue fires:

```
IndexedDB outbox → POST /sparxstar/v1/dictionary/progress/sync
    → Helios token validation
    → myCred points awarded
    → outbox cleared
```

If sync fails (still offline or server error), the outbox retains the events and retries on next connection. Points are never lost — they are queued, not discarded.

**Conflict rule:** Server wins on total score. Client wins on "words practiced" inventory (a word practiced offline is always recorded, even if the sync is delayed).

### Service Worker

A service worker handles:

- Cache-first for all `/sparxstar/v1/dictionary/*` GET responses (with network fallback for stale)  
- Offline fallback page if the app shell itself fails to load  
- Background sync for the progress outbox

The service worker is versioned. On update, old caches are cleared and fresh word sets are downloaded when connection is available.

---

## Version History

| Version | Date | Changes |
| :---- | :---- | :---- |
| 1.0 | May 2026 | Initial document. Suite architecture, API contract, games design, consumer relationships, resolved decisions, MyCred gamification, offline strategy. |

| Version | Date | Changes |
| :---- | :---- | :---- |
| 1.0 | May 2026 | Initial document. Suite architecture, API contract, games design, consumer relationships. |

---

*Starisian Technologies · AIWA · Confidential — Internal Use Only*  
