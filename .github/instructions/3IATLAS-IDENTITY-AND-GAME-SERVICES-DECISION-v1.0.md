# 3iAtlas Suite — Identity & Game Services Decision Record

**Version:** 1.0
**Status:** Active
**Scope:** All 3iAtlas repos — sparxstar-3iatlas-dictionary, sparxstar-3iatlas-wordpad-universe, sparxstar-3iatlas-rlc, sparxstar-3iatlas-s2s
**Authors:** Max Barrett / Starisian Technologies
**Date:** June 2026
**Supersedes:** Scoped amendments only, listed in §6. Everything else in prior documents remains in force.
**Replaces:** DICTIONARY-DELTA-OQ-G5.md draft (June 2026, never committed) — its content is incorporated here.

> **Record-of-decision document — retained in place; two factual errors corrected in situ
> below (§3, §5, §6), 2026-07-08.** For the current, verified technical detail (actual
> `/progress/sync` wire schema, REST API surface) in the dictionary repo, see
> `docs/dictionary-tech-spec.md` in `sparxstar-3iatlas-dictionary`.

---

## 1. Decision — One Login for the Entire 3iAtlas Suite

All 3iAtlas products share a single identity system. One account works in WordPad, the Dictionary and its games, RLC, and S2S.

The model is the WordPad v4.0 auth architecture, promoted from product-level to suite-level:

| Tier | Level | Login |
|---|---|---|
| Lower Basic | Grades 1–6 | Class code + screen-name tap (no password) |
| Upper Basic | Grades 7–9 | Screen name + 4-digit PIN |
| Senior Secondary | Grades 10–12 | Screen name + password |
| Adult | Post-secondary | Full credentials |

Properties carried over unchanged from WordPad v4.0: JWT-based, no WordPress login anywhere in the suite, no email or personal data collected from minors, teacher visibility rules per tier, Helios as the platform-mode issuer behind `HeliosClientInterface` (boot-detected, no degradation when absent).

**WordPress authentication is prohibited for all 3iAtlas user-facing products.** WordPress sessions remain admin-only, where a WordPress backend exists at all.

## 2. The Shared Identity Service

"Same login" requires one token issuer. A small **Identity Service** (working name `sparxstar-identity`; Node, deployable on Cloudflare Workers alongside Helios) is the single issuer of suite JWTs in standalone mode.

**Scope: platform-level, not 3iAtlas-only.** Following the established pattern (RLC engine: "AIWA is the first deployment, not the definition"), the Identity Service is a SPARXSTAR platform service for which the 3iAtlas suite is the first client. Future SPARXSTAR consumer products use the same issuer.

**Boundary rule (permanent):** the Identity Service answers only *who are you* — login, tier claims, token issuance. It never answers *what are you allowed to do* — no trust levels, no agreement evaluation, no governance tokens. Authorization remains Helios/Mḗh₁n̥s exclusively. Any PR adding authorization logic to the Identity Service is a spec violation.

- All apps validate tokens against this issuer. Same token, same tiers, everywhere.
- Tokens are **Helios-shaped**: claim names and structure match Helios's token format, so future convergence is an issuer-URL change, not a migration.
- **Hosting:** Starisian-hosted (SaaS model, §10) at a single address (e.g. `id.sparxstar.com`). Products point at it; nothing is bundled per sale. A self-hosted enterprise edition (§10) would bundle it.
- **Platform mode:** when `SPARXSTAR_PLATFORM_PRESENT` and `HeliosClientInterface` are available, Helios is the issuer. Apps are unchanged; the doorway swaps. Identical to the WordPad v4.0 pattern.

## 3. Decision — The RLC Node Engine Is the Suite's Shared Game Service

The Node + Express engine built for RLC is promoted from "RLC's backend" to the **3iAtlas Game Service**: sessions, progress, XP, rewards signals, and gameplay-accuracy signal aggregation for every game in the suite.

- RLC is its first client. The Dictionary's six games are its second.
- The Dictionary games are not rebuilt. Only the client `syncNow()` changes: it will POST to the Game Service instead of WordPress. **Correction (2026-07-08):** this section previously described the payload as an "existing frozen event schema — `word_uuid`, `game_type`, `outcome`, `attempts`, `xp`, `timestamp`, production-vs-recognition flag (per dictionary PR #59 Fix 2)". That citation was checked directly against dictionary PR #59 and found to be fabricated — the PR contains no schema definition or "Fix 2" content; its actual content is the `sessionRef` stale-closure fix in `useGameSession.js`. The verified, currently-shipped wire shape is `{ type, word_uuid?, game?, domain?, ts }` (see `docs/dictionary-tech-spec.md` in the dictionary repo, § "Game integration"). `outcome`/`attempts`/`xp` are local-only fields in a different object and never leave the client today. Whether the Game Service intake needs a richer wire-visible schema is undecided and belongs to `GAME-SERVICE-INTAKE-SPEC-v1.0` (§8). The IndexedDB outbox and idempotency behavior carry over unchanged regardless of that open decision.
- The Game Service is the single emitter of myCred reward signals (fire-and-forget) and the single aggregator forwarding gameplay-accuracy signal onward to Esu. One rewarder, one quality pipe.
- Game Service auth: suite JWT from the Identity Service (§2). Classroom sessions use the Lower Basic flow — teacher's account opens the session, students tap their names.

## 4. Guest Play

Unauthenticated visitors can play Dictionary games. Guest progress is device-local only (anonymous device ID, IndexedDB). One non-blocking prompt: creating an account keeps progress across devices. The account created is the suite account (§1). No progress syncs to the Game Service without a suite token.

## 5. What This Closes

| Item | Resolution |
|---|---|
| OQ-G5 (sync destination, opened informally June 2026) | Closed — destination is the 3iAtlas Game Service, suite-JWT authenticated |
| Game-player identity question | Closed — suite tiers + guest mode (§1, §4) |
| ~~OQ-G1~~ (recorded closed May 2026: WP nonce auth for /progress/sync) | **Correction (2026-07-08):** "OQ-G1" was redefined and reused for two different questions across the dictionary repo's own document history — the original (`dictionary-game-spec-v1.md`, May 2026) asked about **Helios-token-source** for `/progress/sync`; this row instead closes a *different* question ("WP nonce auth for /progress/sync") that was substituted later, on a citation (§6 item 2, below) that is itself fabricated. Disambiguated: (a) the WP nonce/session auth approach for the deprecated `/progress/sync` endpoint is resolved/stable — that endpoint is retired (§6); (b) how an anonymous/guest game client obtains a token to sync to the Game Service with no WordPress session and no Helios identity remains genuinely open and is not closed by this document. See `docs/dictionary-tech-spec.md` in the dictionary repo for the full disambiguation. Do not cite "OQ-G1" for either question going forward. |

## 6. Scoped Supersessions

Per the suite convention, each supersession is explicit and limited:

1. **WordPad v4.0 §3.1** ("Own JWT auth — signed with WORDPAD_JWT_SECRET, no external issuer"): amended. Standalone-mode issuer is the bundled 3iAtlas Identity Service. All other WordPad v4.0 content, including every non-negotiable in §23, remains in force.
2. **WP nonce auth for `/progress/sync`** (previously cited here as "GH-ISSUE-dictionary-PR59-fixes.md Fix 1" — **correction, 2026-07-08: that file does not exist anywhere in the dictionary repo; confirmed by a repo-wide search. There is no GitHub Issue backing this decision — it was pure markdown-table bookkeeping, and the citation should never have been treated as an authoritative source.** The substantive decision itself is not reversed by this correction): the WordPress `/progress/sync` route is **retired**. Mark deprecated in the Dictionary plugin; remove after the Game Service intake is live. No client may be built against it.
3. **3IATLAS-SUITE-ARCHITECTURE-v1.0 auth model** ("Progress endpoints require Helios Bearer token"): amended. Progress goes to the Game Service under suite JWT (standalone) or Helios (platform). The Suite Architecture's read-endpoint model is unchanged.

## 7. Open Questions and Closed Sub-Decisions

Closed (June 2026, options reviewed with Max):

| ID | Decision |
|---|---|
| OQ-I1 | **Own repo, platform-scoped.** `sparxstar-identity` (working name) is its own repository — not 3iAtlas-only; 3iAtlas is the first client. Rationale: security isolation — the suite's token-minting code must not share a repo with actively agent-developed feature code. Its AGENTS.md is locked down; PRs are reviewed with heightened scrutiny. |
| OQ-I2 | **Keypair signing (RS256).** Only the Identity Service holds the private key and can mint tokens. Apps hold the public key and can only verify. No shared secret anywhere in the suite. |

Still open:

| ID | Question | Blocking |
|---|---|---|
| OQ-I3 | Account-claim flow: merging guest device progress into a new suite account | Game Service intake spec |
| OQ-I4 | Tier verification: who approves teacher (Lower Basic session-opening) accounts — AIWA approval flow per RLC's existing rule | Identity Service spec |

## 8. Next Specs to Write (in order)

1. 3IATLAS-IDENTITY-SERVICE-SPEC-v1.0 — issuer, token format, tier claims, Helios swap
2. GAME-SERVICE-INTAKE-SPEC-v1.0 — progress event contract, idempotency, guest-claim, myCred + Esu signal emission
3. Dictionary delta — syncNow() implementation against the Game Service; WP /progress/sync deprecation

## 9. AGENTS.md Updates Required

- **Dictionary repo:** ~~OQ-G1 closed (historical)~~ — retired as a citation, 2026-07-08 (see §5, §6 corrections above); /progress/sync deprecated, sync target = Game Service, OQ-G5 closed per this document.
- **WordPad repo:** note §3.1 amendment per this document.
- **RLC repo:** engine is the suite Game Service; Dictionary games are a client.

## 10. Commercial Model — SaaS-First (decided June 2026)

All 3iAtlas products are delivered as Starisian-hosted SaaS. Offline-first clients + hosted backend. Customers never deploy servers. This formalizes what existing specs already imply (WordPad subscription storage tiers, server-only dictionary, Starisian-hosted ESU/R2/key vault) and matches how every buyer segment actually purchases.

Three sales motions, per market analysis (June 2026):
1. **Institutional / government** — donor-co-funded projects, RFP/tender procurement, pilot → evidence → multi-year contract. The Gambia school pilot is the reference deployment. Funder-facing usage/impact reporting is a product requirement in this motion.
2. **School subscriptions** — per-classroom or per-student annual SaaS; teacher self-serve; class-code login (Lower Basic tier) is the expected pattern.
3. **Consumer / diaspora** — app-store freemium subscription.

**Self-hosted enterprise edition:** not built now. Reserved as a premium, negotiated exception (ministry national rollout, sovereignty-maximalist indigenous program) requiring a separate corpus license — self-hosting otherwise places AIWA's sovereign linguistic work product on customer infrastructure. Architecture remains 12-factor/configurable so this edition is possible without rebuild.

**Indigenous-market requirement:** contractual data-export/repatriation guarantee — community data ownership with a guaranteed exit path. This is a sales differentiator, not only an ethical position.

## 11. Session Capture — Not Decided, Parked

- **Sirus JS extraction:** Sirus's large client-side JavaScript (context, error monitoring, reporting) lives inside a PHP repo. Candidate for extraction into its own JS package, following the `@sparxstar/starmus-audio` precedent. Needs its own session; do not act on this note alone.
