# AIWA Dictionary — Games Specification v1.0

**Version:** 1.0  
**Status:** Active — do not build until this document is committed to `.github/instructions/`  
**Repo:** sparxstar-3iatlas-dictionary  
**Authors:** Max Barrett / Starisian Technologies  
**Date:** May 2026  
**Depends on:** `DICTIONARY-DIRECTION-v3.md`, `3IATLAS-SUITE-ARCHITECTURE-v1.0.md`

---

## 1. Design Mandate

These are not vocabulary games. The users are **lifelong fluent Mandinka speakers** who have never been shown how to write their language. The gap is orthographic — they know the words, they cannot produce the written form cold.

Every game must:

- **Never start from nothing.** Always provide a scaffold — audio, partial letters, meaning, domain hint, or image. Cold spelling recall in an unfamiliar orthography is humiliating. Scaffolding is respect.
- **The AccessoryBar is always present** in any game requiring typed input. Mandinka uses characters (ŋ ɓ ɗ ñ ɲ ʔ) not on a standard keyboard. Non-negotiable.
- **Wrong answers teach, they don't shame.** A wrong answer reveals more — the next letter, the IPA, or the definition — not just "incorrect."
- **Progress is specific.** "You can now write 23 words" is meaningful. Letter grades are not.
- **Offline first.** Once a game set is loaded, no network calls during gameplay. Progress syncs when connection restores.

---

## 2. Where Games Live

Games live inside the Dictionary React app. Browse and Play are **two equal tabs** — neither is secondary.

**Tab structure (top level, mobile and desktop):**
```
[ Browse ]  [ Play ]
```

Switching between Browse and Play is a tab switch, not a page navigation. Same session, same login, same Word of the Day visible in both.

**Word of the Day → Practice flow:**
1. Word of the Day card is visible on Browse home
2. "Learn more" opens the full word detail
3. "Practice this word" button launches a game seeded with that word and its domain neighbors

**Browse → Practice flow:**
1. User filters words by domain (e.g., Agriculture)
2. "Practice this domain" button appears — launches a game with that domain's word set

---

## 3. Data Source

All games consume from `GET /sparxstar/v1/dictionary/game-set`.

```
Parameters:
  lang_source   required   taxonomy slug e.g. "mandinka"
  domain        optional   domain slug e.g. "agriculture-6.2"
  limit         default 20, max 50
  include_audio bool, default false (set true for Listen & Write)

Response:
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

Words without headword + at least one translation are excluded automatically by the endpoint.

**Caching:** On domain selection, cache the `/game-set` response in IndexedDB with a 3-day TTL. Games run entirely from cache — no network calls during gameplay. Adjacent domain sets are pre-fetched silently.

**IndexedDB schema (games store):**
```js
{
  key: `game-set:${lang_source}:${domain || 'all'}`,
  data: [ ...words ],
  fetchedAt: timestamp,
  ttlMs: 259200000  // 3 days
}
```

---

## 4. The Six Games

### 4.1 Listen & Write
*The most important game. Audio → typed word.*

**Source:** CodePen reference — word guess game (Screenshots 2 & 3)  
**Data requirement:** `/game-set?include_audio=true` — words without audio are excluded  
**Scaffold:** Audio plays automatically. Word length shown as blank tiles (e.g. `_ _ _ _ _ _`). IPA shown below tiles.

**Flow:**
1. Audio clip plays — player hears the word they already know spoken aloud
2. Blank tiles show word length
3. Player types using keyboard + AccessoryBar
4. On correct: tiles fill green, IPA and translation appear confirming what they knew. "+10 XP" shown.
5. On wrong: first letter revealed, player tries again. Second wrong: second letter revealed. Third wrong: full word shown, marked "Still learning."
6. Next word loads automatically after 1.5 seconds

**Why this matters:** Hearing a word you've said ten thousand times and writing it correctly for the first time is the core experience this suite exists to create.

---

### 4.2 Arrange the Word
*Scrambled letter tiles → correct order.*

**Source:** CodePen reference — word scramble with arrow navigation (Screenshot 1)  
**Data requirement:** `/game-set` — any word with headword  
**Scaffold:** All letters provided as tappable tiles, scrambled. Domain and English translation shown throughout as hints. No typing required.

**Flow:**
1. Scrambled letter tiles displayed (e.g. `A L A B A L I A` shuffled)
2. Translation shown: "calamity, disaster"
3. Domain shown: "General"
4. Player taps tiles in correct order — selected tiles move to answer row
5. On correct: tiles animate into place, audio plays if available, "+5 XP"
6. On wrong: tile shakes, returns to pool — player tries again

**Mobile:** Tap to select, tap answer position to place. Desktop: drag and drop OR click-to-select then click position.

**Why this matters:** Lower barrier than typing from scratch. Good for early orthographic learners and younger students in the 7-week course.

---

### 4.3 Meaning Match
*Written Mandinka word → correct English/French meaning.*

**Source:** CodePen reference — word association cards (Screenshot 6)  
**Data requirement:** `/game-set` — needs `translation_en`. Distractors drawn from same domain.  
**Scaffold:** The written Mandinka headword is shown. Player selects from three meanings — one correct, two distractors from the same domain.

**Flow:**
1. Headword displayed large (e.g. "alibalaa")
2. IPA shown below: /alibalaː/
3. Three meaning options displayed as cards
4. Player taps the correct meaning
5. On correct: card highlights, "+5 XP", brief pause, next word
6. On wrong: wrong card dims, correct card highlighted, brief explanation

**Distractor selection:** Distractors must be from the same domain and same part of speech where possible. Never show antonyms as distractors — too confusing. Draw from words in the cached game-set.

**Why this matters:** Tests whether the player can connect the written form to the meaning they already know. Bridges written form → oral knowledge.

---

### 4.4 Complete the Sentence
*Example sentence with headword blanked → player fills it in.*

**Source:** CodePen reference — hangman/letter reveal (Screenshot 4)  
**Data requirement:** `/game-set` — words with at least one `example_sentence`  
**Scaffold:** A real example sentence from the dictionary is shown with the key word removed. Word length shown as blank tiles. Domain and translation of the target word shown.

**Flow:**
1. Sentence displayed with target word blanked: "Alamaa n tanka la ______ la"
2. English translation shown: "May God save us from calamity"
3. Blank tiles show target word length
4. Player types using keyboard + AccessoryBar
5. On correct: word fills in, full sentence shown with translation, "+8 XP"
6. On wrong: first letter revealed, try again. Second wrong: second letter. Third wrong: word shown.

**Why this matters:** Context-based. The sentence is a real utterance — the kind of thing they have actually said. They are not guessing; they are writing something they know.

---

### 4.5 Letter Reveal (Word Shape)
*Click letters from a pool to reveal the hidden word.*

**Source:** CodePen reference — letter pool / hangman with animation (Screenshot 4 — the cat/shark mechanic is the hook, not the model)  
**Data requirement:** `/game-set` — any word with headword  
**Scaffold:** Word length shown as blank tiles. Full alphabet pool shown. Translation given as hint throughout.

**Flow:**
1. Blank tiles show word length (e.g. 8 blanks for "alibalaa")
2. Translation shown: "calamity, disaster"
3. Alphabet pool displayed — player taps letters
4. Correct letter: reveals all instances in the word, letter greys out in pool
5. Wrong letter: counter decrements (5 wrong = session over for this word). Wrong letters shown crossed out.
6. On complete: "+5 XP", next word

**AIWA character handling:** The alphabet pool must include Mandinka-specific characters (ŋ ɓ ɗ ñ ɲ ʔ) displayed as a second row in the pool, styled distinctly (AIWA pink background). These are never "wrong" — they appear in the pool and reveal if present in the word.

**Animation hook:** On wrong answer, a visual element animates (e.g., the AIWA pottery vessel tilts further). On word complete, it rights itself. This is the engagement hook — keep it culturally appropriate, nothing threatening.

---

### 4.6 Domain Flash
*Flashcard through a semantic domain.*

**Source:** Suite architecture doc — Domain Flash  
**Data requirement:** `/game-set?domain=agriculture-6.2`  
**Scaffold:** Each card shows the English/French meaning — player tries to recall the Mandinka word (written). Flip reveals the word + IPA + audio if available. Self-reported result only.

**Flow:**
1. Card shows: "calamity, disaster" + domain badge "General"
2. Player thinks of the Mandinka word
3. Player taps "Reveal"
4. Card flips: shows "alibalaa" + IPA + plays audio if available
5. Two buttons: "I knew it ✓" (+5 XP) / "Still learning" (no penalty, word added back to end of deck)
6. Progress: "12 / 20 words"

**Deck completion:** When all cards are marked "I knew it," session completes. "+25 XP" bonus. Option to replay with "Still learning" words only.

**Why this matters:** Domain-organized learning mirrors how people think about vocabulary — by context (family words, farming words, food words). No wrong answers, no shame.

---

## 5. AccessoryBar

Required in games 4.1 (Listen & Write) and 4.4 (Complete the Sentence). Must appear above the keyboard on mobile — pinned to the bottom of the game container, above the soft keyboard.

```
Mandinka special characters:
[ ŋ ] [ ɓ ] [ ɗ ] [ ñ ] [ ɲ ] [ ʔ ] [ á ] [ é ] [ í ] [ ó ] [ ú ]
```

Each button inserts the character at the current cursor position in the input field. Style: AIWA pink background (`#E91E8C`), white text, same height as a standard keyboard row.

**Implementation:** The AccessoryBar is a React component that attaches a `focusin` listener to game input fields and positions itself above the virtual keyboard using `window.visualViewport` resize events.

---

## 6. Game Session Flow

### 6.1 Session Setup (Play Tab Entry)

```
Play tab opens →
  If no language selected: prompt language selection (same as Browse)
  If language selected:
    Show domain selector (from /domains endpoint)
    Show game type selector
    Show word count selector (10 / 20 / 30 words)
    [Start] button
```

### 6.2 Session State (IndexedDB)

```js
{
  key: 'game-session:current',
  gameType: 'listen_write',
  langSource: 'mandinka',
  domain: 'agriculture-6.2',
  words: [...],           // shuffled game-set slice
  currentIndex: 0,
  results: [],            // { wordUuid, outcome: 'correct'|'learning', attempts: 1|2|3 }
  xpEarned: 0,
  startedAt: timestamp,
  completedAt: null,
}
```

Session is written to IndexedDB on every word result. If the app closes mid-session, it resumes from the last checkpoint on next open.

### 6.3 Session Complete

```
All words done →
  Summary screen:
    "You practiced 20 words"
    "You can now write N words" (cumulative from progress store)
    XP earned this session
    [Practice missed words] (if any "Still learning")
    [Browse this domain] → switches to Browse tab filtered by domain
    [Play again] → new session, same settings
```

### 6.4 Progress Sync

On session complete, write results to IndexedDB outbox:

```js
{
  events: [
    { type: 'aiwa_game_word_correct', word_uuid: '...', game: 'listen_write', ts: ... },
    { type: 'aiwa_game_session_complete', domain: 'agriculture-6.2', ts: ... }
  ]
}
```

Sync fires on session complete if online, or on next connection via `window.online` event. POST to `/sparxstar/v1/dictionary/progress/sync` with Helios Bearer token.

---

## 7. MyCred Hook Map

| Hook | Trigger | Award |
|---|---|---|
| `aiwa_game_word_correct` | Any correct answer in any game | +5 XP |
| `aiwa_game_listen_write_correct` | Correct in Listen & Write specifically | +10 XP (harder — extra reward) |
| `aiwa_game_sentence_correct` | Correct in Complete the Sentence | +8 XP |
| `aiwa_game_session_complete` | Full session completed (min 10 words) | +25 XP |
| `aiwa_game_domain_mastered` | 100% correct on full domain set | +50 Gold |
| `aiwa_game_streak_3` | 3 correct in a row | +15 XP bonus |
| `aiwa_game_new_word_practiced` | First time practicing a word | +8 XP |
| `aiwa_game_return_visit` | Opens Play tab on a new calendar day | +10 XP |

MyCred hooks fire server-side on `/progress/sync`. When myCred is absent, hooks fire as no-ops — games still work.

---

## 8. File Structure

All game code lives inside `src/js/` in the existing dictionary React app. No new repo.

```
src/js/
  app.jsx                          # Existing — add Play tab
  games/
    GameShell.jsx                  # Tab container, session setup, domain/game selectors
    AccessoryBar.jsx               # Mandinka character bar for typed games
    SessionComplete.jsx            # Summary screen
    games/
      ListenWrite.jsx              # Game 4.1
      ArrangeWord.jsx              # Game 4.2
      MeaningMatch.jsx             # Game 4.3
      CompleteSentence.jsx         # Game 4.4
      LetterReveal.jsx             # Game 4.5
      DomainFlash.jsx              # Game 4.6
  hooks/
    useGameSession.js              # Session state, IndexedDB read/write
    useProgressSync.js             # Outbox → /progress/sync
    useGameSet.js                  # /game-set fetch + IndexedDB cache
```

---

## 9. What Is NOT in This Spec

Do not build until separately specced:

- Real-time multiplayer / head-to-head — future phase
- Teacher dashboard showing class game results — RLC owns that
- In-game leaderboard — future phase (MyCred leaderboard exists but game integration unspecced)
- Audio recording within games — Starmus owns audio capture

---

## 10. Backend Endpoints Required Before Games Can Launch

The following endpoints must exist in `Sparxstar3IAtlasDictionary.php` before any game UI is built:

| Endpoint | Status | Blocking |
|---|---|---|
| `GET /sparxstar/v1/dictionary/game-set` | **Not yet built** | All games |
| `GET /sparxstar/v1/dictionary/domains` | **Not yet built** | Session setup |
| `GET /sparxstar/v1/dictionary/word-of-day` | **Not yet built** | WotD → Practice flow |
| `POST /sparxstar/v1/dictionary/progress/sync` | **Not yet built** | XP / MyCred |

**These four endpoints are Phase 3 backend work.** Games UI (Phase 4) cannot begin until Phase 3 is complete and tested.

---

## 11. Open Questions

| ID | Question | Blocking |
|---|---|---|
| OQ-G1 | Helios auth — how does the Dictionary app obtain a Helios Bearer token for `/progress/sync`? Is there a login flow in the Dictionary frontend, or does it piggyback WordPress session? | Progress sync / MyCred |
| OQ-G2 | Adjacent domain pre-fetch map — which domains are "adjacent" to each other for background caching? Depends on 7-week curriculum document. | Offline pre-fetch |
| OQ-G3 | Animation asset for Letter Reveal game — what culturally appropriate visual replaces the cat/shark mechanic from the CodePen reference? | Letter Reveal polish |
| OQ-G4 | Domain Flash self-report — does "I knew it" result sync to server as `aiwa_game_word_correct` or as a separate hook? | MyCred hook map |

---

## 12. Version History

| Version | Date | Changes |
|---|---|---|
| 1.0 | May 2026 | Initial games specification. Six game types, session flow, MyCred hooks, backend dependencies, file structure. |
