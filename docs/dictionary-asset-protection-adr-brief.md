# ADR Commissioning Brief — Dictionary Asset Protection

**Commissions:** the governing ADR required by
[`dictionary-asset-protection-spec.md`](./dictionary-asset-protection-spec.md) §9 step 1.
**Status:** brief. Not the ADR. This document states what the ADR must decide, what
engineering recommends for each open value, and what the deployed reality is — so the
signing owners (games, web, legal) decide from facts rather than from guesswork.
**Rule this brief obeys:** the spec is the single source of truth. Where this brief and
the spec disagree, the spec wins and this brief is wrong.

---

## 1. Why this brief exists before the code

Spec §9 step 1 says: *commission the governing ADR before any implementation.* Spec §1.4
says every enforcement flip is gated on a cutover milestone that does not yet exist.

Those two together mean code may land, but only if it cannot change production behaviour
before the ADR ratifies and the cutover verifies. That is the discipline the accompanying
implementation follows: **every enforcement path ships behind a cutover flag that defaults
to the §1 migration exception** (current behaviour), and the flag is the single thing the
ADR's cutover milestone flips. Nothing in the plugin changes what a player experiences
until an owner sets that flag.

That includes the **response contract**, which an earlier revision of this brief wrongly
treated as exempt. The §2 caps, the over-cap `400`, and count suppression are all changes a
deployed client can observe, so they are tiered on the same flag: before cutover the legacy
caps, the legacy `absint()` coercion, and the exact `meta.total` are preserved byte for
byte; at cutover the spec regime applies. Requests that the target regime *would* refuse
are logged in the meantime, so the ADR gets observed data on who is over-cap instead of a
guess — the same discipline as the §1.1 tripwire.

This matters because it cannot be closed by evidence. The in-repo app provably never sends
`per_page` and never reads `meta.total` (it calls only `/page-token`, `/pronounce`,
`/languages`, `/word-of-day`). But **the games app's source is not in this repository**
(`AGENTS.md`: "source has since moved out of this repo"), and `/game-set` is its endpoint,
so its request shapes cannot be evidenced here. Gating is the only honest answer.

Surfaces that no shipped client consumes are a different case. Closing those is not a
behaviour change and is not gated — see §4 below for exactly which, and the evidence that
nothing consumes them.

---

## 2. Decisions the ADR must make (spec §9 step 1, itemised)

Each row is a decision the spec deliberately leaves open. "Recommendation" is engineering's
input, not a decision.

| # | Decision required | Spec ref | Engineering recommendation |
|---|---|---|---|
| D-1 | Exact routes and the **field allowlist per system** | §1, §9.1 | Allowlist per credential, default-deny. Games broker needs `headword`, `ipa`, `phonetic`, `translation_en`, `translation_fr`, one example, `domain`, `language`. It does not need `origin`, `synonyms`, `antonyms`, `uuid`, or full `definition`. |
| D-2 | **Pagination mechanics and caps** | §2, §9.1 | Search ≤ 20 (spec-stated). List page size ≤ 100. Over-cap `per_page` → `400`, never a silent clamp — a silent clamp teaches a scraper the cap for free. |
| D-3 | **Cutover milestone and its verification criteria** | §1.4, §9.1 | Criterion: 7 consecutive days of monitor-only tripwire data showing zero browser-origin requests on the dictionary route, ESU serving 100% of games traffic. This is the gate every other enforcement step waits on. |
| D-4 | **Approved-systems list**, each system's rate ceiling and onward-exposure budget | §1.2, §1.6, §9.1 | ESU (Sky) at cutover. Payload pipeline if adopted. WordPad backend when it exists. No others without an ADR amendment. |
| D-5 | **Rolling unique-entry budget** per credential — window length and ceiling | §1.2 | 24h rolling window. ESU ceiling = its real daily word need × 3 headroom. Express the ceiling as a **fraction of the live corpus per language**, not a fixed number: any ceiling above roughly a third of a language's entry count makes a three-day full walk of that language feasible. See §7 — the repo's own corpus figures disagree and the ADR must settle the real one before this can be numeric. |
| D-6 | **Interim vs target credential form** and the migration trigger | §1.2, §9.1 | Ship interim (static per-system secrets, `Authorization: Bearer`, hashed at rest). Trigger for target form: the identity node's client-credentials endpoint going live. |
| D-7 | Target-form token verification: `aud: dictionary`, clock skew, JWKS cache TTL | §1.2, §9.1 | Deferred with D-6. Belongs to the identity node's half of the contract. |
| D-8 | **Licensing posture** (with counsel) | §7, §9.1 | Engineering has no recommendation. §7.1 constrains it: no measure may reduce community access below its pre-measure level. |
| D-9 | **Numeric rate limits and their measurement points** | §2, §9.1 | Plugin is authoritative (§2). Cloudflare/nginx numbers belong to `system-core`, not this repo. |
| D-10 | Governance conflict: **custom database tables** | §1.2, §5 | See §5 of this brief. Needs an explicit ruling. |
| D-11 | **Single permalinks vs. the autolinker** — a §7.1 access conflict | §1.3, §7.1 | See §4a of this brief. Escalated, not decided. |
| D-12 | **Block editor for dictionary entries** — `show_in_rest => false` means editors get the classic editor | §1.3 | **Owner decision taken:** gated on cutover rather than applied on merge, so the editorial change lands with the deploy-reviewed flip. See §4b. |

---

## 3. Deployed reality the ADR is deciding against

Verified by reading this repository at the commit this brief lands on. These are facts,
not estimates.

1. **The browser pulls the entire corpus index in one query.** `src/js/app.jsx` issues
   `GetWordIndex($first: Int = 20000)` against WPGraphQL, and
   `Sparxstar3IAtlasDictionaryCore::sparxIAtlas_increase_query_limit()` deliberately raises
   WPGraphQL's ceiling to 5,000 so that query succeeds — the code comment says so plainly:
   *"Allow the dictionary index query to fetch the full corpus in one pass."*
   This is the §1.5 full-dump path, and it is the live production data path. Closing it is
   strictly cutover-gated: closing it today breaks the shipped app.
2. **The dictionary CPT is fully public.** `aiwa-cpt-dictionary` registers with
   `public => true`, `show_in_rest => true`, `show_in_graphql => true`. That means default
   `wp/v2` REST, permalinks, archives, feeds, global search, and sitemaps are all live
   doors onto entry content, independent of the `sparxstar/v1/dictionary` route. §1.3 exists
   precisely for this.
3. **`X-Api-Key` is live**, is the only consumer-grade credential, and is advertised to
   browsers in the CORS `Access-Control-Allow-Headers` list. §1.1 condemns it
   architecturally; §1.4 keeps it served until cutover.
4. **`/wordlist` is an unbounded-list endpoint in practice** — `per_page` up to 2,000,
   paginated, returning exact `found_posts`. That is §1.5's prohibition and §2's
   count-suppression rule, both violated by one route.
5. **No `Authorization: Bearer` path exists.** There is no per-system credential, so there
   is nothing to attribute a leak to and nothing to revoke surgically (§1.2).
6. **Audio sits in the WordPress media library** — `aiwa_audio_file` yields a plain
   `/wp-content/uploads/` URL, walkable by date structure. §1.3a.

---

## 4. Surfaces closed without a cutover gate, and why that is safe

§1.3 requires the non-route WordPress doors shut. These are closed immediately because
**no shipped client reads them** — the frontend uses WPGraphQL for the index and the
`sparxstar/v1/dictionary` REST namespace for everything else, both of which are untouched:

| Surface | Evidence nothing consumes it |
|---|---|
| Global search (`/?s=`) | No shipped template surfaces dictionary results in WP search |
| Feeds, sitemaps, oEmbed, author archives | No client reference; these are WordPress defaults, never a product feature here |
| Post-type archives | The app renders at its own `/dictionary/` route via `template_redirect`, never at a CPT archive |
| Attachment pages | Never linked; audio is delivered by URL from the API payload |

Four things are **gated rather than closed now**, each because something live depends on
them: the WPGraphQL full-index path (§3.1), single entry permalinks (§4a), `show_in_rest`
and with it the block editor (§4b), and the §2 response-contract changes (§1). All are wired
to the same cutover flag as the credential enforcement, so one deploy-reviewed switch moves
the whole regime at once.

Deferring `show_in_rest` costs nothing in exposure terms: before cutover the entire corpus is
already served to browsers through the WPGraphQL index, so the default `wp/v2` routes add no
reach that is not already open. It is the §1.5 full-dump path, not `wp/v2`, that carries the
real pre-cutover exposure — and closing that is what the cutover is for.

---

### 4a. Escalated access conflict, not resolved here (D-11)

Spec §1.3 requires "no public single/archive permalinks or templates". Closing single
permalinks today would break a real community-facing feature: `Sparxstar3IAtlasAutoLinker`
turns dictionary terms in ordinary posts and pages into links built from
`get_permalink()`, and the React app has **no URL deep-linking** to replace them — its
word selection is in-app state only, so there is no `/dictionary/?word=…` to redirect to.

Spec §7.1 is explicit that this is not engineering's call:

> No security or licensing measure in this spec may be implemented in a way that reduces
> the community's legitimate access below what existed before the measure. Where
> protection and access conflict, the conflict is escalated to an ADR, not resolved
> silently by engineering.

So it is escalated. The implementation gates single permalinks on the cutover flag along
with the GraphQL index, which keeps today's access intact and puts the decision in front
of the ADR rather than shipping it as a side effect. **Whatever the ADR decides, the
autolinker needs a destination before the cutover flips** — either app deep-linking, or a
deliberate ruling that autolinks become non-links. Treat that as a cutover prerequisite,
not a follow-up.

### 4b. An editorial trade-off that needs confirming, not deciding (D-12)

§1.3 permits either "no public `show_in_rest`, or capability-gated". The implementation
switches it off, which is the complete option: it also removes the CPT from
`/wp/v2/search`, which a per-route capability gate would leave open.

The cost is that **dictionary entries fall back to the classic editor**, since the block
editor requires `show_in_rest`. An earlier revision of this brief argued that cost was
negligible because `post_content` is a generated search index rather than authored prose.
**That argument was wrong and is withdrawn.** The sync that would generate it,
`Sparxstar3IAtlasDictionaryCore::sparxIAtlas_sync_dictionary_search_index()`, guards on the
post type `aiwa_cpt_dictionary` (underscores) while the registered type is
`aiwa-cpt-dictionary` (hyphens), so it never runs — `post_content` is whatever an editor
typed. (The same mismatch disables the alphabet-taxonomy hook on line 78. Both are
pre-existing defects, unrelated to this PR and deliberately not fixed in it; they are
recorded here so they are not lost.)

So this is a real change for editors, and whether it is acceptable is a question for the
people who enter entries rather than for engineering.

**Owner decision (2026-08-26): gate it on cutover.** `show_in_rest` stays as it is today and
switches off with the rest of the regime at the deploy-reviewed flip, so the editorial change
cannot land silently on merge and the team has the cutover window to confirm or object. If
they turn out to need the block editor permanently, the fallback remains keeping
`show_in_rest => true` and capability-gating the `wp/v2` routes, accepting that
`/wp/v2/search` then needs its own handling.

## 5. Governance conflict requiring an explicit ruling (D-10)

**Three separate documents assert the prohibition**, which is worth knowing before the
ADR rules on one of them and assumes it is done:

| Source | What it says |
|---|---|
| `AGENTS.md`, **Absolute Rules — Never Violate** | "Never add a custom database table. Use WordPress CPTs and post meta only." |
| `docs/dictionary-tech-spec.md` §48–53 | Dictionary data uses CPT/ACF storage, with "no custom database tables" |
| Qodo platform rules 2921756 and 2924175 | Forbid non-core custom tables, and require rate-limiting state in transients exclusively |

A ruling that amends only `AGENTS.md` leaves the tech spec and the automated rules still
contradicting the code, so every future PR touching this store re-opens the argument. The
ADR needs to name all three.

Spec §1.2 requires an **atomic, durable** `(credential_id, entry_id, window)` store with
race-safe insert-if-absent counting, naming *"a WordPress custom table with a unique index,
or Redis with SETNX"*. Spec §5 requires a `dictionary_ledger` table by name.

These cannot both hold. Engineering's read:

* Post meta cannot satisfy the requirement. It has no unique index, so insert-if-absent is
  a read-then-write race — exactly the race §1.2 says must not exist. Under concurrency a
  compromised consumer would undercount its own budget, which defeats the control.
* Redis `SETNX` is atomic but **not durable across a cache flush**, and §1.2 says the store
  must survive restarts. It is a correct fast path, not a correct system of record. The same
  objection defeats rule 2924175's "transients exclusively": a transient-backed budget fails
  **open** on every cache flush, which is the one direction a security control must not fail.
* Therefore the spec's requirement is only satisfiable by a table with a unique index.

**Recommendation:** the ADR grants a narrowly scoped exception to the `AGENTS.md` rule,
limited to protection-infrastructure tables (budget accounting, ledger) and explicitly not
extending to linguistic content, which stays in CPTs and post meta as the rule intends. The
accompanying implementation uses the table alone as the system of record. An object-cache
fast path was considered and deliberately not built: it can only ever say "already counted",
and a stale entry near a window boundary would silently under-count — a wrong answer in the
one direction that matters for a security control. The table will need reverting if the ADR
rules the other way. **The rule is not amended by this brief; only
the ADR can amend it.**

Because of that, **merging this PR does not create the table.** It is no longer provisioned
at plugin activation; it is created only when an operator deliberately runs
`wp sparxstar-dict system generate` to onboard a consuming system — an action that belongs
after the ruling, not before it. Until then the accounting reports itself unavailable and,
before cutover, tolerates that rather than failing requests.

---

## 6. What the accompanying implementation does and does not deliver

Per §9's "one focused PR per area — no omnibus security PR", the code landing alongside
this brief is **one area**: the plugin's exposure-reduction and credential-readiness half
of §9 step 2, plus the measurement §1.4 step 3 depends on.

**Delivered:** §1.3 non-route surface lockdown; §2 response caps, over-cap refusal and
count suppression; §1.2 interim per-system `Authorization: Bearer` credentials with the
atomic durable unique-entry budget store; §1.1 tripwire in monitor-only mode; §3 credential
redaction in logs.

**Deliberately not delivered here** — each is its own PR per §9, and several are outside
this repository entirely:

| Deferred | Why |
|---|---|
| §5 hash-chained `dictionary_ledger` | Own PR (§9 step 6). Blocked on D-10. |
| §4 provenance tooling, shingle index, serving-layer watermarks | Own PR (§9 step 6). Watermark mapping is T2-held, not repo content. |
| §3 honey endpoints | T2-held inventory by spec. Publishing the honey surface in a public repo would defeat it. |
| §6 snapshots and offline masters | Own PR (§9 step 6), and largely an ops operation. |
| §2 Cloudflare and nginx rules | `system-core`, not this repository. |
| §1.3a media relocation and `auth_request` gate | Own PR; needs the broker (§1.4 step 1) to exist first. |
| §5 WP hardening checklist | Own PR (§9 step 7). |
| §7 legal package | Counsel, not engineering (§9 step 8). |

---

## 6a. Corpus size is unsettled, and D-5 depends on it

The repository states three different figures, none of them sourced:

| Claim | Where |
|---|---|
| "~4,175" live entries | `Sparxstar3IAtlasDictionaryCore` line 148 |
| "all 12,000+ words" | `Sparxstar3IAtlasDictionaryCore` line 134, same file |
| "11,550 entries" | `docs/dictionary-data-coverage-report.md` |

A budget ceiling sized against the wrong figure is either useless (too high to bound a walk)
or an outage (too low for legitimate use), and a ceiling sized for today's corpus silently
becomes wrong as the corpus grows. Hence D-5's recommendation is now expressed as a fraction
of the live per-language count rather than an absolute. **The ADR needs the real figure, and
whether it is per-language or across all languages**, before that fraction can be made
numeric.

## 7. Sign-off required

Spec §9 step 1: *"The ADR is the Contract, not just the record: the games, web, and legal
owners sign it."* The clause needing signature is the one that breaks a roadmap —
**no browser calls the dictionary, ever** — together with the §1.4 sequencing that makes it
survivable: the consuming-system path is live and verified *before* any lockdown flips.
