# AGENTS.md — sparxstar-3iatlas-dictionary

## What This Repo Is

This is the authoritative lexical data store and REST API service for the entire 3iAtlas platform. It is a WordPress plugin with a React frontend. Every other 3iAtlas tool (WordPad, RLC, Sound to Symbol, Games) is a consumer of this plugin's REST API. This repo does not consume from them.

**Three responsibilities:**
1. Store and serve dictionary entries via WordPress CPTs and ACF fields
2. Expose a public REST API consumed by all 3iAtlas tools
3. Render a public-facing dictionary experience (Browse mode) and word games (Play mode) via a React PWA

---

## Absolute Rules — Never Violate

- **Never modify the `aiwa-cpt-dictionary` CPT slug.** Live data depends on it. Changing it destroys existing entries.
- **Never add community voting, correction CPTs, or AJAX voting endpoints.** This feature was removed by design. Do not re-introduce it.
- **Never store dictionary files on the client device in any form.** All dictionary lookups are server-side. The device sends a query; the server returns only the result.
- **Never hardcode language names in the React app.** Language terms come from the `/languages` REST endpoint.
- **Never use `WidthType.PERCENTAGE` in any generated DOCX.** Not relevant here but noted for completeness.
- **Never add a custom database table.** Use WordPress CPTs and post meta only.
- **License header on all PHP files must read `Proprietary`, not `MIT`.**
- **Text domain on all PHP files: `sparxstar-3iatlas-dictionary`.**
- **All PHP files must declare `strict_types=1`.**
- **Namespace: `Starisian\Sparxstar\Atlas\Dictionary`**

---

## What Exists (Do Not Rebuild)

- `src/includes/Sparxstar3IAtlasPostTypes.php` — CPT and taxonomy registrations (has known bugs — see Phase 0)
- `src/frontend/Sparxstar3IAtlasDictionaryForm.php` — community word submission form (has known bugs — see Phase 0)
- `src/js/app.jsx` — React frontend (needs full rebuild in Phase 2 — do not patch, wait for spec)
- `src/core/Sparxstar3IAtlasDictionary.php` — main plugin class
- `tailwind.config.js` — Tailwind config (needs AIWA brand colors in Phase 2)
- GraphQL queries via WPGraphQL — existing, working

## Data Model — Key CPT and Fields

**CPT:** `aiwa-cpt-dictionary`
**Taxonomies:** `starmus_tax_language` (source language — Mandinka, Wolof, etc.), `starmus_tax_dialect`, `starmus_tax_alpha` (alphabetical grouping)

Key ACF fields on `aiwa-cpt-dictionary`:
- `aiwa_extract` — definition/extract text
- `aiwa_translation_en` — English translation
- `aiwa_translation_fr` — French translation
- `aiwa_ipa` — IPA pronunciation
- `aiwa_phonetic` — phonetic pronunciation
- `aiwa_audio_file` — audio recording URL
- `aiwa_word_photo` — image URL
- `aiwa_origin` — word origin notes
- `aiwa_synonyms` / `aiwa_antonyms` — related words
- `aiwa_example_sentences` — repeater field with sub-fields: sentence, IPA, phonetic, EN translation, FR translation
- `aiwa_sentence_ipa` (key: `field_696e6b18c17f4`) — registered in PostTypes.php but absent from SCF JSON. **PostTypes.php is authoritative. Do not add this field to the SCF JSON.**

---

## SCF DISCREPANCY — DO NOT SYNC

`aiwa_sentence_ipa` (key: `field_696e6b18c17f4`) is registered programmatically
in `PostTypes.php` as a sub-field of the example sentences repeater.
It is intentionally absent from the SCF JSON import file.
PostTypes.php is authoritative for ACF field registration.
Do not add this field to the SCF JSON. Do not remove it from PostTypes.php.

---

## REST API — Base Namespace

`sparxstar/v1/dictionary`

**Auth model:**
- All GET endpoints: public, no auth required, rate-limited (100 requests / 15 min / IP via WordPress transients)
- POST `/progress/sync`: requires Helios Bearer token (not WordPress session)
- Add `// TODO: Replace with Helios token introspection` comment on every rate-limit check

**Response envelope (all endpoints):**
```json
{ "success": true, "data": {}, "meta": { "total": 0, "page": 1, "per_page": 20 } }
```

**Endpoints to implement (Phase 1):**

| Method | Path | Auth | Purpose |
|---|---|---|---|
| GET | `/lookup` | Public | Full entry by slug or UUID |
| GET | `/search` | Public | Search entries by query string |
| GET | `/wordlist` | Public | Lightweight word list for offline caching |
| GET | `/languages` | Public | All language taxonomy terms with word counts |
| GET | `/domains` | Public | Semantic domain taxonomy terms with counts |
| GET | `/game-set` | Public | Curated word set for game use (richer than wordlist) |
| GET | `/word-of-day` | Public | Single deterministic daily entry |
| POST | `/progress/sync` | Helios token | Batch game event sync → myCred points |

**`/game-set` parameters:** `lang_source` (required), `domain` (optional), `limit` (default 20, max 50), `include_audio` (bool)
**`/game-set` exclusion rule:** Exclude entries missing headword, translation_en, or IPA. Games require all three.
**Scale note:** `ORDER BY RAND()` is acceptable only as a temporary implementation pattern; replace it with a scalable selection approach before large production datasets.

---

## MyCred Gamification Hooks

Fire these WordPress action hooks when processing `/progress/sync` events. myCred listens; when absent, hooks are no-ops.

```php
do_action('aiwa_game_word_correct',      $user_id, $word_uuid, $game_type);   // +5 XP
do_action('aiwa_game_listen_write',      $user_id, $word_uuid);                // +10 XP
do_action('aiwa_game_session_complete',  $user_id, $domain_slug);              // +25 XP
do_action('aiwa_game_domain_mastered',   $user_id, $domain_slug);              // +50 Gold
do_action('aiwa_game_streak_3',          $user_id);                            // +15 XP
do_action('aiwa_game_new_word_practiced',$user_id, $word_uuid);                // +8 XP
do_action('aiwa_game_return_visit',      $user_id);                            // +10 XP
```

---

## Offline / Caching Requirements

- All GET endpoint responses must include `Cache-Control: public, max-age=3600` headers
- `/wordlist` and `/game-set` must support `ETag` headers for conditional requests
- `/word-of-day` response must include `date` field (ISO 8601) so clients can detect staleness

---

## Coding Standards

- PSR-12 for all PHP
- `declare(strict_types=1)` at the top of every PHP file
- No raw SQL — use `$wpdb->prepare()` if ever needed
- No `die()` — use `exit(1)` with a message
- All user input sanitized with `sanitize_text_field()` or equivalent before use
- All output escaped with `esc_html()`, `esc_attr()`, `esc_url()` as appropriate
- Rate limiting via WordPress transients — never external infrastructure
- PHP 8.2 minimum

---

## File Structure

```
src/
  api/
    Sparxstar3IAtlasDictionaryRestApi.php   ← Phase 1: create this
  gamification/
    Sparxstar3IAtlasDictionaryProgress.php  ← Phase 1: create this
  includes/
    Sparxstar3IAtlasPostTypes.php           ← Phase 0: bug fixes here
  frontend/
    Sparxstar3IAtlasDictionaryForm.php      ← Phase 0: bug fix here
  core/
    Sparxstar3IAtlasDictionary.php          ← register new classes here
  js/
    app.jsx                                 ← Phase 2: full rebuild
tailwind.config.js                          ← Phase 2: AIWA brand colors
AGENTS.md                                   ← this file
```

---

## Current Task — Phase 0: Bug Fixes

**This is a prerequisite for all other work. Deliver as a single PR.**

### Bug 1 — `starmus_tax_language` not registered on dictionary CPT

**File:** `src/includes/Sparxstar3IAtlasPostTypes.php`

**Problem:** `register_taxonomy('starmus_tax_language')` lists `object_type` as audio CPTs only. `aiwa-cpt-dictionary` is absent. WordPress resolves this at registration time — the CPT's own taxonomy declaration is not sufficient. The taxonomy's `object_type` array is authoritative.

**Fix:** ✅ Added `aiwa-cpt-dictionary` to `starmus_tax_language` and `starmus_tax_dialect` object_type arrays.

**Verification step:** After deployment, run `wp term list starmus_tax_language --orderby=count` to confirm terms exist with correct counts.
**API dependency note:** Language-filtered API behavior depends on this taxonomy registration fix being present in the deployed environment; without it, language filters can return incomplete or empty results.

### Bug 2 — `aiwa_sentence_ipa` absent from SCF JSON

**No code change needed.** See SCF DISCREPANCY section above.

### Bug 3 — `DictionaryForm.php` creates entries with no language taxonomy

**File:** `src/frontend/Sparxstar3IAtlasDictionaryForm.php`

**Blocked by:** Bug 1 must be fixed and deployed first.

**Fix:** ✅ Added language `<select>` field populated from `starmus_tax_language` terms. Validates that the submitted value is a real term slug. Calls `wp_set_object_terms()` after `wp_insert_post()`.

---

## Phase 1 — After Phase 0 Merges

1. Write `src/api/Sparxstar3IAtlasDictionaryRestApi.php` — all eight endpoints listed above
2. Write `src/gamification/Sparxstar3IAtlasDictionaryProgress.php` — `/progress/sync` handler + myCred hook firing
3. Register both classes in `Sparxstar3IAtlasDictionary::sparxIAtlas_load_dependencies()`
4. Add rate-limit transient logic to every public GET endpoint
5. Add `Cache-Control` and `ETag` headers to `/wordlist` and `/game-set`

Do not begin Phase 1 until Phase 0 PR is merged.

---

## Phase 2 — React Frontend Rebuild

**Do not begin until Phase 1 is complete and a separate UI spec is provided.**

The React frontend (`src/js/app.jsx`) requires a full rebuild. Do not patch the existing file. Wait for the UI specification before touching this file.

---

## What Copilot Must Not Do

- Add voting, correction, or community review features — removed by design
- Add a custom WordPress admin page — use standard WP CPT list for any admin needs
- Call Brain (PostgreSQL) directly — this plugin does not connect to Brain
- Add DVE, Sky, Mḗh₁n̥s, or Dheghom dependencies
- Store dictionary data in localStorage or IndexedDB on the client
- Add the `aiwa_sentence_ipa` field to the SCF JSON
- Create a custom database table
- Hardcode language names anywhere in the React app
