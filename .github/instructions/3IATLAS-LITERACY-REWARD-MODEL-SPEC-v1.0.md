# 3iAtlas Literacy Reward Model

## Specification v1.0

**Status:** Approved  
**Scope:** Dictionary, WordPad, and all non-game literacy clients under the 3iAtlas tenant  
**Related specs:** `3IATLAS-IDENTITY-AND-GAME-SERVICES-DECISION-v1.0.md`, `3IATLAS-DICTIONARY-ROLE-AND-PIPELINE-SPEC-v1.0.md`

---

## 1. Governing Principle

**Pay for verbs, not visits.**

A lookup is a signal, not an achievement. The reward comes when the signal resolves into learning, production, contribution, or shared value.

The Dictionary is the entry point of the literacy loop. It helps the learner discover a word, then sends that word into SRS, games, WordPad, and contribution pathways. It is not a game. Neither is WordPad. Both are **non-game literacy clients** — first-class participants in the reward engine that plug into the same manifest, SRS, ledger, and identity architecture as the game surfaces.

The three surfaces have distinct roles:

| Surface    | Primary reward category         |
| ---------- | ------------------------------- |
| Games      | Recall and recognition          |
| Dictionary | Contribution and delayed recall |
| WordPad    | Production and revision         |

---

## 2. The Core Literacy Loop

```
Dictionary lookup
      ↓
SRS enqueue  (signal — no XP)
      ↓
Recognition task in Dictionary  (★ Recognized — 5 XP)
      ↓
Game recall  (★★ Recalled — 15 XP)
      ↓
WordPad correct production  (★★★ Used — 25 XP)
      ↓
Accepted contribution  (★★★★ Contributed — 40–50 XP net)
      ↓
Contribution helps another learner complete a meaningful learning event
      (★★★★★ Shared — up to 50 additional XP, 5 per unique learner)
```

The learner is rewarded for demonstrated learning at each step, not for passive presence at any step.

---

## 3. XP Rules

### 3.1 Dictionary-origin events

| Event                                                                            | XP                       | Notes                                       |
| -------------------------------------------------------------------------------- | ------------------------ | ------------------------------------------- |
| Lookup                                                                           | **0**                    | Enqueues word into SRS; no XP               |
| Correct meaning selected (recognition task)                                      | **5**                    | Task served during SRS window               |
| Looked-up word recalled in a game                                                | **15**                   | Cross-surface; see § 3.3 for credit split   |
| Looked-up word used correctly in WordPad                                         | **25**                   | Production proof                            |
| Example sentence submitted                                                       | **5–10 pending**         | Held until reviewed                         |
| Example sentence accepted                                                        | **+40**                  | Net 45–50 total                             |
| Pronunciation submitted                                                          | **5–10 pending**         | Held until reviewed                         |
| Pronunciation confirmed                                                          | **+40**                  | Net 45–50 total                             |
| Error flag confirmed as materially useful                                        | **25**                   | Max 3/day; see anti-farming rules           |
| Accepted contribution helps another learner complete a meaningful learning event | **5 per unique learner** | Capped at 50 XP per contribution; see § 3.4 |

### 3.2 WordPad production tiers

Production events are tiered to keep struggling learners engaged without letting unvalidated output flood the ledger.

| Production level                                   | XP  |
| -------------------------------------------------- | --- |
| Attempted (submitted but incorrect)                | 2   |
| Improved (measurably better revision)              | 10  |
| Correct (target word used correctly)               | 25  |
| Validated contribution (DVE or community accepted) | 100 |

"Attempted" earns 2 XP as encouragement only. It does not advance the per-word mastery state.

### 3.3 Cross-surface credit split

When a learner recalls a word in a game that was previously enqueued from a Dictionary lookup, the XP is credited to the learner but the event is attributed to **both surfaces** for analytics.

```json
{
    "event": "word_recalled",
    "learner_id": "...",
    "aiwa_entry_uuid": "...",
    "xp_awarded": 15,
    "earning_surface": "game",
    "origin_surface": "dictionary",
    "origin_event": "lookup",
    "credit": {
        "dictionary": "origin",
        "game": "performance"
    }
}
```

This preserves the game surface's performance signal in analytics. Dictionary receives **origin credit** — it initiated the learning path. The game receives **performance credit** — it produced the recall event. Neither surface is undervalued in reporting.

### 3.4 "Helps another learner" definition

The 5-XP-per-learner contribution reward requires a **meaningful learning event**, not a view. Qualifying events include:

- Another learner selected the contributed example sentence in a meaning task
- Another learner recalled a word after being exposed to the contributed example
- Another learner's pronunciation submission was accepted after reviewing a contributed audio

The following do **not** qualify:

- Another learner viewed the word entry
- Another learner opened the dictionary
- Another learner scrolled past the contribution

This rule is enforced server-side by the manifest. The contribution reward fires only when the downstream event is logged against the contribution's `contribution_id`.

---

## 4. Anti-Farming Rules

These rules are declared in the Dictionary manifest and enforced server-side.

| Rule                             | Detail                                                                                                     |
| -------------------------------- | ---------------------------------------------------------------------------------------------------------- |
| Same-word lookup deduplication   | A word looked up more than once per day by the same learner creates no additional SRS signal and no XP     |
| Daily XP cap — Dictionary origin | 150 XP/day from Dictionary-origin events                                                                   |
| Daily XP cap — Presence bonus    | 10 XP/day; structurally separate from learning XP; cannot be farmed into significance                      |
| Recognition task cap             | 10 tasks/day from Dictionary source                                                                        |
| Contribution submission cooldown | One pending submission per word per learner per 24-hour window; cooldown resets on acceptance or rejection |
| Error flag cap                   | 3 confirmed-useful flags/day; unconfirmed flags earn no XP                                                 |
| Contribution sharing reward gate | Requires a qualifying downstream learning event (§ 3.4); raw view or open does not trigger                 |

---

## 5. Per-Word Mastery States

**Seen** is a status, not a star. The five-star ladder begins at recognition — the first point where the learner has demonstrated active engagement with the word, not merely passive exposure.

| Level    | State           | How earned                                                                       |
| -------- | --------------- | -------------------------------------------------------------------------------- |
| (status) | **Seen**        | Word looked up                                                                   |
| ★        | **Recognized**  | Correct meaning selected in a recognition task                                   |
| ★★       | **Recalled**    | Word recalled correctly in a game                                                |
| ★★★      | **Used**        | Word used correctly in WordPad production                                        |
| ★★★★     | **Contributed** | Example sentence or pronunciation accepted                                       |
| ★★★★★    | **Shared**      | Accepted contribution helps another learner complete a meaningful learning event |

A five-star word is not merely known. It has been recognized, recalled, written, contributed back, and shared with the community.

### Mastery state on the learner profile

The learner profile displays:

- **Star count** — sum of all per-word star levels across all words; reflects depth of learning, not raw activity
- **Word counts per level** — how many words are at each mastery state
- **Five-star words** — shown prominently as the highest achievement

Star count is a better engagement signal than raw XP because 100 five-star words represents genuine acquisition, not accumulated passive time.

Per-word state is stored on the learner record indexed by `aiwa_entry_uuid`. The UUID is minted by DVE and is immutable — it is the stable cross-surface identifier for a word across the Dictionary, SRS, games, and WordPad.

---

## 6. Badge Families

### 6.1 Learning badges

| Badge                                   | Trigger                                                  |
| --------------------------------------- | -------------------------------------------------------- |
| First Word                              | First lookup                                             |
| Word Collector (Bronze / Silver / Gold) | 10 / 50 / 100 unique words looked up                     |
| Memory                                  | First recall of a looked-up word in a game               |
| Recall Champion (Silver / Gold)         | 25 / 100 cross-surface recalls                           |
| First Writer                            | First correct use of a Dictionary word in WordPad        |
| Production (Silver / Gold)              | 10 / 50 correct WordPad productions                      |
| Full Loop                               | Lookup → recall → written use completed on a single word |

**Full Loop** is the signature badge for this model. It represents the complete literacy journey on a single word and should be surfaced prominently.

### 6.2 Contribution badges

| Badge            | Trigger                                                                                         |
| ---------------- | ----------------------------------------------------------------------------------------------- |
| Voice            | First accepted example sentence or pronunciation                                                |
| Chronicler       | 10 accepted contributions                                                                       |
| Griot            | 5 accepted audio or pronunciation contributions — oral tradition recognized explicitly          |
| Error Hunter     | 5 confirmed, materially useful error flags                                                      |
| Trusted Voice    | 50 accepted contributions                                                                       |
| Knowledge Keeper | 200 validated contributions that have helped other learners complete meaningful learning events |

**Note on "Knowledge Keeper":** In many African contexts, "Elder" carries cultural authority grounded in age and lived experience. Awarding an "Elder" badge from point accumulation may feel disrespectful to learners and communities. "Knowledge Keeper" honors the same idea — deep, trustworthy contribution — without appropriating cultural meaning.

**Griot** is appropriate as a contribution badge because it names the role without claiming the person holds the full spiritual and social authority of a traditional griot.

### 6.3 Cultural and language badges

| Badge          | Trigger                                                                                       |
| -------------- | --------------------------------------------------------------------------------------------- |
| Pioneer        | Contribution to a language with fewer than 500 dictionary entries at the time of contribution |
| Domain badge   | Accepted contribution in one of the platform's recognized domains (see § 6.4)                 |
| Language badge | Active study or accepted contribution in a specific named language                            |

Domain and language badges appear on the learner's profile and visually represent the breadth of their engagement across the platform's linguistic scope.

### 6.4 Domain badges

Accepted contributions in these domains earn a domain-specific badge:

- Traditional Knowledge
- Nature and Environment
- Family and Community
- Ceremony and Ritual
- Music and Performance
- Food and Agriculture
- Work and Trade
- Governance and Law
- Healing and Medicine
- Storytelling

Domains are defined by the `aiwa_domain` taxonomy in the dictionary entry. A learner can hold badges in multiple domains.

### 6.5 Milestone badges

| Badge                                             | Trigger                                                                                   |
| ------------------------------------------------- | ----------------------------------------------------------------------------------------- |
| Daily Learner (Bronze / Silver / Gold / Platinum) | Meaningful engagement streak: 3 / 7 / 30 / 100 days                                       |
| Centurion                                         | 100 words at ★★ (Recalled) or higher                                                      |
| Scholar                                           | 250 words at ★★★ (Used) or higher                                                         |
| Bridge Builder                                    | A contribution you made was used in the meaningful learning of 10 or more unique learners |

**Streak badges** are capped-presence glue only. They must remain structurally separate from learning XP. A learner should not be able to achieve Scholar or Knowledge Keeper through streak farming. The 10 XP/day presence cap enforces this.

---

## 7. Surfaces and Responsibilities

### 7.1 Dictionary

- Emits `lookup` events → SRS enqueue, no XP
- Serves recognition tasks at SRS window → `recognition_correct` event → 5 XP
- Hosts contribution endpoints: example sentences, pronunciation, error flags
- Holds pending XP until review resolves
- Reports `contribution_accepted` and `contribution_helped` events to the reward ledger

Dictionary registers as a **non-game literacy client** under the 3iAtlas tenant.

### 7.2 WordPad

- Emits production events tagged with `aiwa_entry_uuid` when target words are used
- Reports production tier (attempted / improved / correct / validated)
- Receives cross-surface credit when a word was previously enqueued from a Dictionary lookup

WordPad registers as a **non-game literacy client** under the 3iAtlas tenant.

### 7.3 Games

- Emit `word_recalled` events tagged with `aiwa_entry_uuid`
- Receive **performance credit** for recall events
- Dictionary receives **origin credit** when the recalled word was previously enqueued from a lookup

Games are unchanged by this spec. They do not need to know whether a word entered SRS from a Dictionary lookup or another pathway.

---

## 8. Manifest Primitives

Dictionary and WordPad compose the following existing reward engine primitives:

| Primitive         | Used by                                                                   |
| ----------------- | ------------------------------------------------------------------------- |
| `recall_srs`      | Games (recall), Dictionary (recognition task)                             |
| `validate_single` | Dictionary (meaning recognition), WordPad (correct production)            |
| `collect_text`    | Dictionary (example sentence submission), WordPad (production submission) |
| `collect_audio`   | Dictionary (pronunciation submission)                                     |
| `correct_text`    | WordPad (revision improvement)                                            |
| `presence_daily`  | All surfaces (capped at 10 XP/day, separate from learning XP)             |

### New primitives required

| Primitive              | Description                                                                                                                                    |
| ---------------------- | ---------------------------------------------------------------------------------------------------------------------------------------------- |
| `contribution_pending` | Holds XP on submission; fires `contribution_accepted` or `contribution_rejected` on resolution                                                 |
| `contribution_helped`  | Fires when a downstream meaningful learning event is attributed to an accepted contribution; carries `contribution_id` and `helped_learner_id` |
| `origin_credit`        | Annotates a ledger event with the surface and event that initiated the SRS enqueue path                                                        |

---

## 9. Technical Implementation Requirements

The following additions are required to implement this spec. None of them require changes to the reward engine core or the game surfaces.

### 9.1 Dictionary API additions

- `GET /recognition` — serve a meaning-selection task for a word in the learner's SRS window; record result via `validate_single`
- `POST /contributions/sentence` — submit example sentence; fires `contribution_pending`
- `POST /contributions/pronunciation` — submit audio; fires `contribution_pending`
- `POST /contributions/error` — flag a potential error; reviewed separately; fires pending XP on confirmation

### 9.2 Cross-surface XP credit

Game and WordPad services emit events tagged with `aiwa_entry_uuid`. The reward ledger resolves origin credit as Dictionary when:

1. The word's `aiwa_entry_uuid` has an open `lookup` event in the learner's SRS state, and
2. The SRS enqueue that opened that state has `origin_surface: dictionary`

### 9.3 Per-word mastery state

- Stored on the learner record
- Indexed by `aiwa_entry_uuid`
- Updated by the reward ledger when a qualifying event is processed
- Exposed via the identity/progress API as `word_mastery_state[]`
- Never downgraded — mastery states are append-only

### 9.4 Manifest registration

Dictionary and WordPad each register a manifest under the 3iAtlas tenant declaring:

- Surface identifier
- Surface type: `literacy_client`
- Composed primitives
- XP caps per primitive
- Anti-farming rules
- Contribution review pathway reference

Scoring is server-side. XP caps and anti-farming rules are declared in the manifest and enforced by the reward engine. No client-side XP calculation.

### 9.5 Contribution review pathway

Pending contributions (example sentences, pronunciations) feed into the DVE review pipeline for acceptance or rejection. The review pathway is external to this spec but must emit `contribution_accepted` or `contribution_rejected` events to resolve pending XP.

---

## 10. What This Model Does Not Cover

The following are out of scope for v1.0 and must not be assumed:

- Leaderboards or comparative ranking
- Monetary or voucher redemption from XP
- Social sharing of badges
- WordPad rubric definitions (handled by a separate WordPad spec)
- DVE review pipeline internals
- Game-specific scoring and difficulty balancing
- `GAME-SERVICE-INTAKE-SPEC-v1.0` (must be committed before `syncNow()` is implemented)

---

_Approved for implementation. Dictionary and WordPad are first-class non-game literacy clients. They do not need to look like games to participate in the reward engine — they need only produce meaningful literacy events._
