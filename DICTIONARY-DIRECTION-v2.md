# AIWA Dictionary — Direction Document v2

**Repository:** `sparxstar-3iatlas-dictionary-main`  
**Plugin version:** 0.6.7 → target 0.8.0  
**Date:** May 2026  
**Supersedes:** DICTIONARY-DIRECTION-v1.md

---

## What This Repo Is

The dictionary plugin is the **authoritative lexical data store and service provider** for the entire 3iAtlas platform. Every other tool — WordPad, RLC, Sound to Symbol, Teach, Encyclopedia, AI Chat — is a consumer. This repo does not consume from them.

It has three responsibilities:

1. Store and serve dictionary entries (existing — working)
2. Provide a REST API for cross-tool consumption (new)
3. Accept and queue community corrections and votes for human review → Esu pipeline (new)

The React frontend (`app.jsx`) is the public-facing dictionary experience. It must be rebuilt to match the AIWA design mockups and support source-language browsing by registered users.

---

## Decisions Locked

| Decision                | Answer                                                                                                        |
| :---------------------- | :------------------------------------------------------------------------------------------------------------ |
| Multilingual type       | **Source language** — browse Mandinka vs Twi vs Wolof separately, filtered by `starmus_tax_language` taxonomy |
| REST API auth           | **Public with rate limiting** — no nonce required for read endpoints                                          |
| Community participation | **Any registered WordPress user**                                                                             |
| Correction/vote routing | **Human reviewer first** — WP admin queue, then Esu pipeline                                                  |
| Dark/light theme        | **localStorage only** — no WP user meta                                                                       |
| Word of the Day         | **Client-side deterministic** — `date % count`, no backend mechanism                                          |
| Favorites / History     | **localStorage only** — never WP user meta or post meta                                                       |

---

## Known Bugs — Fix Before Any New Work

These are existing defects in the current code. They must be fixed in Phase 0 (a single PR before Phase 1 begins).

### Bug 1: `starmus_tax_language` not actually registered on the dictionary CPT

**Location:** `src/includes/Sparxstar3IAtlasPostTypes.php`

**Problem:** The `register_post_type('aiwa-cpt-dictionary')` call lists `starmus_tax_language` in its `taxonomies` array. However, the `register_taxonomy('starmus_tax_language')` call registers it only against audio CPTs:

```php
register_taxonomy( 'starmus_tax_language', array(
    'audio-script',
    'audio-recording',
    'starmus_transcript',
    'starmus_translate',
), ...
```

`aiwa-cpt-dictionary` is absent from that array. WordPress resolves this at registration time — the CPT declaring the taxonomy in its own array is not sufficient. The taxonomy's `object_type` array is authoritative.

**Fix:** Add `'aiwa-cpt-dictionary'` to the `register_taxonomy('starmus_tax_language')` object_type array. This is a one-line change with a potentially large downstream effect — existing dictionary entries that have language terms already associated via the CPT admin panel may or may not have the term relationship stored correctly. After the fix, run `wp term list starmus_tax_language --orderby=count` to verify terms exist and have counts.

**Same fix applies to `starmus_tax_dialect`** — it has the same problem. Add `'aiwa-cpt-dictionary'` to both taxonomy registrations.

### Bug 2: `aiwa_sentence_ipa` field missing from SCF but present in PostTypes.php

**Location:** `src/includes/Sparxstar3IAtlasPostTypes.php` registers `aiwa_sentence_ipa` (key: `field_696e6b18c17f4`) as a sub-field of the example sentences repeater with GraphQL name `sentenceIpaPronounciation`. The SCF JSON does not include this field under the repeater's sub_fields array.

**Fix:** Add the missing sub-field to the SCF JSON, or accept that PostTypes.php is the authoritative source (it is — ACF programmatic registration takes precedence over the JSON). No code change needed; document this explicitly in AGENTS.md so Copilot does not try to sync the SCF.

### Bug 3: `DictionaryForm.php` creates an uncategorized draft — no language taxonomy

**Location:** `src/frontend/Sparxstar3IAtlasDictionaryForm.php`, `sparxIAtlas_dict_submit_form()`

**Problem:** When a user submits a new entry via the form, `wp_insert_post()` creates the post but no taxonomy terms are set — no language, no alpha-letter. The alpha-letter is set by the `save_post_aiwa-cpt-dictionary` hook in Core, but language is never set.

**Fix:** The form must include a language selector field. On submission, call `wp_set_object_terms($new_post_id, $language_term_id, 'starmus_tax_language')`. This is blocked by Bug 1 being fixed first.

---

## Section 1: Source Language Architecture

### 1.1 The Data Model

Each dictionary entry (`aiwa-cpt-dictionary`) will carry exactly one `starmus_tax_language` term. This is the language the **headword is written in** — Mandinka, Twi, Wolof, Fula, Hausa, etc.

This is distinct from the interface language (EN/FR toggle), which controls which translation fields are displayed.

The taxonomy already exists and is already on the CPT's taxonomy list. Bug 1 fix makes it actually work.

### 1.2 Language Terms

Language terms are standard WordPress taxonomy terms. They must be seeded during plugin activation or documented as a setup step:

| Term slug  | Term name     | Script direction |
| :--------- | :------------ | :--------------- |
| `mandinka` | Mandinka      | LTR              |
| `twi`      | Twi           | LTR              |
| `wolof`    | Wolof         | LTR              |
| `fula`     | Fula (Pulaar) | LTR              |
| `hausa`    | Hausa         | LTR              |
| `bambara`  | Bambara       | LTR              |

These are the initial set. The taxonomy is hierarchical, so dialects can be children of language terms.

Copilot must not hardcode this list in the React app. Language terms are fetched from the GraphQL endpoint and cached. If no language is selected, all entries are shown (no language filter applied — the existing behavior).

### 1.3 GraphQL Query Changes

The `GET_ALL_WORDS_INDEX` query must add a language filter variable:

```
query GetWordIndex($first: Int = 20000, $language: [String]) {
    dictionaries(
        first: $first,
        where: {
            orderby: { field: TITLE, order: ASC },
            taxQuery: {
                taxArray: [{
                    taxonomy: STARMUS_TAX_LANGUAGE,
                    terms: $language,
                    field: SLUG,
                    operator: IN
                }]
            }
        }
    ) {
        edges { node { ... } }
    }
}
```

When `$language` is null or empty, the `taxQuery` clause is omitted entirely — returns all entries regardless of language.

A second query fetches available language terms for the language selector:

```
query GetLanguages {
    starmusTaxLanguages(first: 50) {
        nodes {
            slug
            name
            count
        }
    }
}
```

### 1.4 Language Selector UI

The language selector appears in the left sidebar (desktop) and as a bottom-sheet or secondary filter panel (mobile). It is **not** the EN/FR toggle — that remains separate and controls interface language.

Behavior:

- Default state: "All Languages" — no filter
- Selecting a language: filters the word list to that language only
- Language selector shows term name \+ word count (e.g. "Mandinka (4,231)")
- Selection persisted to `localStorage` key `aiwa-dict-source-lang`
- On return visit, the last selected language is restored

The selected source language is also passed to the REST API endpoints as a `lang_source` parameter so consumer tools can filter by source language.

### 1.5 REST API Language Parameter

All four REST endpoints gain an optional `lang_source` parameter (string, taxonomy term slug). When present, results are filtered to entries with that language term. When absent, no filter is applied.

---

## Section 2: Community Corrections & Voting

### 2.1 What Users Can Do

Any registered WordPress user, when logged in, can:

1. **Vote on an entry** — thumbs up or thumbs down. Signals overall quality/accuracy.
2. **Suggest a correction** — propose a changed value for a specific field (translation, IPA, phonetic, definition/extract). One correction per field per user per entry.
3. **Vote on an existing suggestion** — upvote or downvote another user's correction proposal.

What users **cannot** do:

- Publish changes directly — all go to the WP admin review queue
- Delete entries
- Change the headword (title) — that requires editor role
- Access other users' votes or personal data

### 2.2 Data Model — New CPT: `aiwa-cpt-correction`

Community corrections are stored as a separate CPT, not as meta on the dictionary entry. This keeps the canonical entry clean and gives editors a clear queue to work from.

**CPT:** `aiwa-cpt-correction`

- `post_title`: Auto-generated — `[Correction] {word} — {field_name}`
- `post_status`: Always `draft` until editor reviews
- `post_author`: The suggesting user
- `post_parent`: The ID of the dictionary entry being corrected

**ACF Fields on `aiwa-cpt-correction`:**

| Field key                        | Field name            | Type             | Description                                                                                                 |
| :------------------------------- | :-------------------- | :--------------- | :---------------------------------------------------------------------------------------------------------- |
| `aiwa_correction_entry_uuid`     | Correction Entry UUID | text             | UUID of the parent dictionary entry                                                                         |
| `aiwa_correction_field`          | Field Being Corrected | select           | Which field: `translation_en`, `translation_fr`, `ipa`, `phonetic`, `extract`, `origin`, `example_sentence` |
| `aiwa_correction_original_value` | Original Value        | textarea         | The current value being challenged                                                                          |
| `aiwa_correction_proposed_value` | Proposed Value        | textarea         | What the user believes it should be                                                                         |
| `aiwa_correction_reason`         | Reason                | textarea         | Why this is more accurate (optional, 500 char max)                                                          |
| `aiwa_correction_upvotes`        | Upvotes               | number           | Count of upvotes on this correction                                                                         |
| `aiwa_correction_downvotes`      | Downvotes             | number           | Count of downvotes                                                                                          |
| `aiwa_correction_esu_status`     | Esu Status            | select           | `pending` / `sent_to_esu` / `applied` / `rejected`                                                          |
| `aiwa_correction_esu_notes`      | Esu Notes             | textarea         | Set by editor when sending to Esu                                                                           |
| `aiwa_correction_reviewer`       | Reviewer              | user             | WP user who reviewed this correction                                                                        |
| `aiwa_correction_reviewed_at`    | Reviewed At           | date_time_picker | When it was reviewed                                                                                        |

**Note on `aiwa_correction_field` choices:** The select choices map to human-readable labels but the stored values are slugs that correspond directly to ACF field names on the parent entry. This is intentional — when an editor applies a correction, the code uses the slug to know exactly which field to update.

### 2.3 Data Model — Vote Tracking

Votes (both entry votes and correction votes) are stored as WordPress post meta on the relevant post, keyed by user ID. This prevents double-voting without requiring a separate database table.

**On `aiwa-cpt-dictionary` (entry vote):**

- Meta key: `aiwa_vote_{user_id}` → value: `up` or `down`
- Meta key: `aiwa_vote_up_count` → integer total (updated on each vote, cached)
- Meta key: `aiwa_vote_down_count` → integer total

**On `aiwa-cpt-correction` (correction vote):**

- Meta key: `aiwa_correction_vote_{user_id}` → value: `up` or `down`
- Update `aiwa_correction_upvotes` / `aiwa_correction_downvotes` ACF fields accordingly

This approach uses standard WP meta — no custom tables, no raw SQL. The vote count meta is updated atomically using `update_post_meta` with the previous value as a check (standard WP optimistic locking pattern).

### 2.4 AJAX Endpoints for Voting & Corrections

A new class `Sparxstar3IAtlasDictionaryCommunity` in `src/community/` handles the logged-in user actions.

**AJAX action: `sparxIAtlas_dict_vote_entry`**

- Auth: `is_user_logged_in()` required, nonce verified
- POST params: `entry_id` (int), `vote` (`up`|`down`), `nonce`
- Behavior: Check if user already voted; if same vote, remove it (toggle); if different, replace it. Update counts.
- Response: `{ success: true, data: { up: N, down: N, user_vote: 'up'|'down'|null } }`

**AJAX action: `sparxIAtlas_dict_suggest_correction`**

- Auth: `is_user_logged_in()` required, nonce verified
- POST params: `entry_id` (int), `field` (slug), `proposed_value` (string), `reason` (string), `nonce`
- Behavior: Check rate limit (max 5 corrections per user per day — stored as user meta `aiwa_correction_count_{date}`). Create `aiwa-cpt-correction` post. Set post meta. Return success with correction ID.
- Response: `{ success: true, data: { correction_id: N, message: '...' } }`
- Validation: `$field` must be one of the allowed values. `$proposed_value` max 2000 chars. Sanitize all inputs.

**AJAX action: `sparxIAtlas_dict_vote_correction`**

- Auth: `is_user_logged_in()` required, nonce verified
- POST params: `correction_id` (int), `vote` (`up`|`down`), `nonce`
- Same toggle logic as entry vote.
- Response: `{ success: true, data: { up: N, down: N, user_vote: '...' } }`

### 2.5 Admin Review Queue

No custom admin page is needed for Phase 1\. Editors use the standard WP post list for `aiwa-cpt-correction`. The following admin columns are added via `manage_aiwa-cpt-correction_posts_custom_column`:

- **Word** — link to parent entry's edit page
- **Field** — which field is being corrected
- **Votes** — `↑N ↓N`
- **Status** — `aiwa_correction_esu_status` value
- **Submitted** — post date

A bulk action "Send to Esu" is added. When an editor selects corrections and runs this bulk action:

1. `aiwa_correction_esu_status` is set to `sent_to_esu`
2. The correction data is added to a transient queue: `aiwa_esu_correction_queue`
3. A WordPress cron event `aiwa_process_esu_correction_queue` fires and processes the queue
4. The cron handler POSTs to `SPARXSTAR_ESU_INGEST_URL` (a `wp-config.php` constant, not hardcoded)
5. On success, status is updated to `applied` and the parent entry's relevant field is updated via `update_field()`

**Important:** Step 5 (actually updating the parent entry's field) only happens after Esu responds with a `200` status. If Esu is unavailable, status remains `sent_to_esu` and the cron retries up to 3 times with exponential backoff. The editor can also manually apply a correction via a custom post row action "Apply Manually" that updates the parent field immediately without going through Esu.

### 2.6 Frontend — Logged-In Experience

When a user is logged in, the dictionary frontend must:

1. Display vote buttons on each word detail view (thumbs up / thumbs down) with current counts
2. Display the user's current vote state (highlighted if they've voted)
3. Show a "Suggest a Correction" button on each field in the detail view
4. Show existing correction proposals with their vote counts (read-only for viewing, interactive for voting)

Authentication state is passed to the React app via `wp_localize_script` on the existing `sparxstar-dictionary-app` handle:

```php
wp_localize_script('sparxstar-dictionary-app', 'sparxstarDictionarySettings', [
    'root_id'    => 'sparxstar-dictionary-root',
    'graphqlUrl' => $graphql_url,
    'ajaxUrl'    => admin_url('admin-ajax.php'),
    'nonce'      => wp_create_nonce('sparxstar_dict_community_nonce'),
    'isLoggedIn' => is_user_logged_in(),
    'userId'     => get_current_user_id(),
]);
```

The React app reads `window.sparxstarDictionarySettings.isLoggedIn` to conditionally render the community participation UI. When not logged in, a subtle "Log in to contribute" prompt appears in the detail view — not a blocker, just an invitation.

Votes and corrections are fetched as part of the `/lookup` REST endpoint response when the user is authenticated (the endpoint detects auth from the request and conditionally adds `user_vote` and `corrections` fields to the response).

---

## Section 3: REST API

### 3.1 Auth Model

- **Read endpoints** (`/lookup`, `/search`, `/wordlist`): Public, no authentication required. Rate limited: 100 requests per IP per 15 minutes via WordPress transients. Header `X-RateLimit-Remaining` on each response.
- **Write endpoints** (votes, corrections): Require WordPress session (nonce \+ `is_user_logged_in()`). These remain AJAX, not REST, because they require WordPress session context.
- **Future:** A `TODO: Replace IP rate limiting with Helios Bearer token introspection` comment on every rate-limit check. When Helios integrates, tier-aware rate limits replace IP-based limits.

### 3.2 Rate Limiting Implementation

```php
private function check_rate_limit(string $ip): bool {
    $key   = 'aiwa_dict_rate_' . md5($ip);
    $count = (int) get_transient($key);
    if ($count >= 100) {
        return false; // Rate limited
    }
    set_transient($key, $count + 1, 900); // 15 min window
    return true;
}
```

This uses WordPress transients, which automatically use Redis/Memcached when available (Profile C baseline). No external rate-limiting infrastructure required.

### 3.3 Endpoint Specification

**Base namespace:** `sparxstar/v1/dictionary`

All responses use the envelope:

```json
{ "success": bool, "data": T, "meta": { "total": N, "page": N, "per_page": N } }
```

**`GET /sparxstar/v1/dictionary/lookup`**

Parameters: `slug` OR `uuid` (one required), `lang` (default `en`), `lang_source` (optional)

Response: Full entry object (see v1 spec). When authenticated, adds:

```json
{
  "user_vote": "up" | "down" | null,
  "vote_counts": { "up": N, "down": N },
  "corrections": [
    {
      "id": N,
      "field": "translation_en",
      "proposed_value": "...",
      "reason": "...",
      "votes": { "up": N, "down": N },
      "user_correction_vote": "up" | "down" | null
    }
  ]
}
```

**`GET /sparxstar/v1/dictionary/search`**

Parameters: `q` (min 2 chars), `lang` (default `en`), `lang_source` (optional), `pos` (optional), `per_page` (default 20, max 100), `page` (default 1\)

Response: Array of summary entry objects (no example sentences for performance).

**`GET /sparxstar/v1/dictionary/wordlist`**

Parameters: `lang` (default `en`), `lang_source` (optional), `alpha` (optional), `per_page` (default 1000, max 2000), `page` (default 1\)

Response: Lightweight word list for offline caching by consumer tools.

**`GET /sparxstar/v1/dictionary/languages`**

New endpoint. No parameters. Public.

Response:

```json
{
    "success": true,
    "data": {
        "languages": [
            { "slug": "mandinka", "name": "Mandinka", "count": 4231 },
            { "slug": "twi", "name": "Twi", "count": 12345 }
        ]
    }
}
```

This is consumed by consumer tools (WordPad, S2S, RLC) to populate their own language selectors. The dictionary is the authoritative source for what languages are in the corpus.

---

## Section 4: UI — Design Target

### 4.1 Color System

Replace current blue Tailwind palette with AIWA brand colors in `tailwind.config.js`:

```javascript
colors: {
  brand: {
    pink:   '#E91E8C',
    purple: '#7B3FA0',
  },
  surface: {
    light: '#F8F8F8',
    dark:  '#1A1A1A',
  },
  pos: {
    noun:        { bg: '#FCE4F3', text: '#C2185B' },
    verb:        { bg: '#E8F5E9', text: '#2E7D32' },
    adjective:   { bg: '#E3F2FD', text: '#1565C0' },
    phrase:      { bg: '#E0F7FA', text: '#00796B' },
    adverb:      { bg: '#FFF8E1', text: '#F57F17' },
    other:       { bg: '#F3E5F5', text: '#6A1B9A' },
  }
}
```

`darkMode: 'class'` — toggled by adding `dark` class to `<html>`. Preference stored in `localStorage('aiwa-dict-theme')`.

### 4.2 Typography

No changes to font stack. Noto Sans is correct (platform standard, covers Mandinka special characters including ŋ, ny, gb, kp). Remove the Crimson Pro \+ Work Sans import from `sparxstar-3iatlas-dictionary-style.css` app bundle — those belong only to the form bundle.

### 4.3 Layout — Three States

**Mobile (\< 768px):**

- Top bar: AIWA logo \+ source language selector \+ EN/FR toggle
- Search bar \+ filter pills (All | Noun | Verb | Phrase | Audio | Image)
- Word of the Day card
- Virtualized word list
- Bottom nav: Home | Explore | Favorites | History
- Alpha bar across bottom above nav

**Tablet (768px – 1024px):**

- Top bar with search
- Full-width word list
- Word detail slides up as bottom sheet (current modal behavior, retained)

**Desktop (\> 1024px):**

- Left sidebar: Logo, nav, language selector, tagline, EN/FR \+ theme toggles
- Center: Search \+ filters \+ virtualized word list
- Right panel: Persistent word detail (NOT a modal). Opens on first word selection, stays open. Empty state shows AIWA brand message.

### 4.4 Language Selector Component

A `LanguageSelector` component renders as:

- Mobile: A horizontal scrollable row of language pills above the filter pills
- Desktop: A list in the left sidebar with word counts

Behavior:

- "All" option always first
- Shows language name \+ count in brackets
- Selected language highlighted with brand pink
- Selection triggers a new GraphQL query with the language filter variable

### 4.5 Word List Row

Anatomy (left to right):

1. Avatar circle — 40px, background color deterministic from first letter (A=pink, B=purple, C=teal, etc. — fixed map of 26 colors), white letter
2. Word title — bold, 16px
3. POS pill — colored by part of speech (see color map above)
4. IPA — muted, 13px
5. Translation — muted, 14px, truncated to 1 line
6. Right side icons: audio (if `aiwa_audio_file`), image (if `aiwa_word_photo`), example count badge (if sentences \> 0\)
7. Chevron right

### 4.6 Detail View — Tab Structure

Four tabs: **Overview | Examples N | Related | Origin**

**Overview:**

- Meaning (short translation)
- Definition (`aiwa_extract`)
- "How people use it" — first example sentence only, with leaf decoration
- Pronunciation — phonetic \+ IPA \+ audio play button
- Vote buttons (logged-in only) — thumbs up/down with counts
- "Suggest a Correction" per-field button (logged-in only, appears on hover/tap of each field)
- Existing corrections listed below relevant field (all users can see, logged-in users can vote on them)

**Examples:**

- All example sentences with original \+ IPA \+ phonetic \+ EN \+ FR

**Related:**

- Synonyms, Antonyms, Phonetic Variants as clickable pills (clicking opens that word's detail)

**Origin:**

- `aiwa_origin` text
- Cultural notes if present

**Desktop right panel feature cards:**

- Audio Pronunciation, Cultural Images, Example Sentences, Related Words, Origin & Cultural Notes
- These are navigation shortcuts that switch to the relevant tab section

### 4.7 Word of the Day

Client-side deterministic selection:

```javascript
const todayIndex = Math.floor(Date.now() / 86400000) % totalWordCount;
const wordOfTheDay = allWords[todayIndex];
```

Rendered as a full-width card with word, IPA, POS pill, first example sentence, and word photo if available. "Learn more →" opens the word detail.

---

## Section 5: AGENTS.md

Must be written and merged as the first PR. Key additions beyond v1:

**New CPT rules:**

- Do not modify `aiwa-cpt-dictionary` CPT slug — live data depends on it
- `aiwa-cpt-correction` is the only new CPT allowed in Phase 1-2
- Never store vote data in a custom database table — use WP post meta

**Taxonomy rules:**

- `starmus_tax_language` must be registered against BOTH audio CPTs AND `aiwa-cpt-dictionary`
- Language terms are taxonomy terms, not ACF fields — do not add an ACF language field to the dictionary entry
- The authoritative language list comes from WordPress taxonomy — never hardcode language names in React

**Community feedback rules:**

- All correction submissions create a `aiwa-cpt-correction` post with `post_status = 'draft'`
- Votes are stored as post meta on the relevant post — never in a custom table
- Rate limit: 5 corrections per user per day (meta key `aiwa_correction_count_{Y-m-d}`)
- The "Send to Esu" action POSTs to `SPARXSTAR_ESU_INGEST_URL` (wp-config.php constant) — never hardcode URLs
- Never auto-apply corrections — always human review first

**REST API rules:**

- Base namespace: `sparxstar/v1/dictionary`
- All read endpoints: public, rate-limited (100 req / 15 min / IP)
- Rate limit uses transients — never external infrastructure
- Response envelope: `{ success: bool, data: T, meta?: {...} }`
- Add `// TODO: Replace with Helios token introspection` comment on every rate-limit check
- `permission_callback` on write endpoints: `is_user_logged_in()` \+ nonce

---

## Section 6: Work Plan

### Phase 0 — Bug Fixes (single PR, prerequisite for everything)

- Fix `register_taxonomy('starmus_tax_language')` — add `'aiwa-cpt-dictionary'` to object_type array
- Fix `register_taxonomy('starmus_tax_dialect')` — same fix
- Fix `DictionaryForm.php` — add language selector field, set taxonomy term on submission
- Document the `aiwa_sentence_ipa` SCF/PostTypes discrepancy in AGENTS.md (no code change)

### Phase 1 — Foundation

**1A.** Write `AGENTS.md`  
**1B.** Rebuild Tailwind config (colors, dark mode strategy)  
**1C.** Create `src/api/Sparxstar3IAtlasDictionaryRestApi.php` — four read endpoints \+ rate limiting  
**1D.** Create `src/community/Sparxstar3IAtlasDictionaryCommunity.php` — CPT registration, AJAX handlers, admin columns, bulk action, cron handler  
**1E.** Register both new classes in `Sparxstar3IAtlasDictionary::sparxIAtlas_load_dependencies()`  
**1F.** Add community-aware fields to `wp_localize_script` in `sparxIAtlas_render_app()`

### Phase 2 — UI Rebuild

**2A.** Layout restructure — mobile / tablet / desktop three-state  
**2B.** Language selector component \+ GraphQL query update  
**2C.** Word list row — full anatomy per Section 4.5  
**2D.** Detail view tabs \+ right panel  
**2E.** Vote UI \+ correction suggestion UI (logged-in only)  
**2F.** Correction display in detail view (all users)  
**2G.** Word of the Day card  
**2H.** Favorites \+ History (localStorage)

### Phase 3 — Integration

**3A.** Consumer tool REST integration test (WordPad `/lookup` and `/spell`)  
**3B.** S2S `/wordlist` integration test  
**3C.** RLC offline `/wordlist` with `lang_source` filter test

---

## Section 7: Files That Change

| File                                                    | Change                                                                                                             |
| :------------------------------------------------------ | :----------------------------------------------------------------------------------------------------------------- |
| `AGENTS.md`                                             | Create                                                                                                             |
| `src/includes/Sparxstar3IAtlasPostTypes.php`            | Add `aiwa-cpt-dictionary` to both taxonomy object_type arrays (Bug 1); add `aiwa-cpt-correction` CPT \+ ACF fields |
| `src/frontend/Sparxstar3IAtlasDictionaryForm.php`       | Add language selector, set taxonomy on submit (Bug 3\)                                                             |
| `src/api/Sparxstar3IAtlasDictionaryRestApi.php`         | Create — four read endpoints                                                                                       |
| `src/community/Sparxstar3IAtlasDictionaryCommunity.php` | Create — CPT, AJAX, admin, cron                                                                                    |
| `src/core/Sparxstar3IAtlasDictionary.php`               | Register new classes; update `wp_localize_script`                                                                  |
| `tailwind.config.js`                                    | AIWA brand colors, dark mode strategy                                                                              |
| `src/css/sparxstar-3iatlas-dictionary-style.css`        | Remove Crimson Pro/Work Sans import from app bundle                                                                |
| `src/js/app.jsx`                                        | Full rebuild — layout, language selector, AIWA design, community UI                                                |
| `src/includes/Sparxstar3IAtlasAutoLinker.php`           | No changes                                                                                                         |

---

## Section 8: Remaining Open Questions

| ID    | Question                                                                                                        | Blocks   |
| :---- | :-------------------------------------------------------------------------------------------------------------- | :------- |
| OQ-D3 | What does "Explore" in the bottom nav show? Categories browse? Random word? Featured words?                     | Phase 2A |
| OQ-D5 | Should anonymous (not logged in) users see vote counts on entries, or are counts hidden until logged in?        | Phase 2E |
| OQ-D6 | Should existing corrections on an entry be visible to all users in the detail view, or only to logged-in users? | Phase 2F |
| OQ-D7 | Which languages are in the initial corpus? This determines what taxonomy terms to seed.                         | Phase 0  |

---

## Version Targets

| Milestone           | Version |
| :------------------ | :------ |
| Phase 0 (bug fixes) | 0.6.8   |
| Phase 1 complete    | 0.7.0   |
| Phase 2 complete    | 0.8.0   |
| Phase 3 verified    | 0.9.0   |
