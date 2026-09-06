# DICT-ADR-001: Serve the WordPress dictionary from the Node over a bounded display JSON tier

**Date:** 2026-09-06
**Status:** accepted
**Applies to:** `sparxstar-3iatlas-dictionary-node`, `sparxstar-3iatlas-dictionary`,
`sparxstar-3iatlas-identity-node`
**Canonical:** `sparxstar-3iatlas-dictionary-node`, `docs/adr/`. This file is a read-only snapshot — do not edit it here.

---

## Context

The WordPress plugin has been the authoritative lexical store, reading SCF/ACF
fields off an `aiwa-cpt-dictionary` CPT and exposing its own
`sparxstar/v1/dictionary/*` REST namespace. `sparxstar-3iatlas-dictionary-node`
was then built as a **port** of that system and now holds the canonical corpus,
the governed compilers, the immutable ledger, and field-level provenance rules
the plugin never had.

That left two live implementations of the same read path, with the authoritative
one being the newer. `docs/PORT-AND-MIGRATION.md` §Cutover assumed the plugin
would simply be **removed** and WordPress reduced to a frame around the Node's
server-rendered pages.

The platform owner has ruled otherwise: WordPress remains the **public display
application** — its React UI, routing, theme and SEO are the product surface —
while the Node remains the **private authoritative data service**. The Node's
existing display tier returns HTML (`/w/:slug`, `/search`), which cannot power
that UI, and the JSON routes that could (`/wordlist`, `/languages`, `/domains`)
were retired on purpose as enumeration bait.

## Decision

1. The Node gains **bounded, credentialed JSON display endpoints** under
   `/v1/display/` — languages, entry lookup by slug + ISO 639-3 language,
   search, domains, word-of-day — in the **`display` authorization tier**,
   separate from `m2m`, `import`, artifacts, and the public-domain route.
   `/v1/display/` is the surface the suite architecture document already
   named for this service; this implements it rather than inventing it. That
   document (`3IATLAS-SUITE-ARCHITECTURE-v1.0.md`) is not in this repository —
   it is shared byte-identically by `sparxstar-3iatlas-dictionary` and
   `sparxstar-3iatlas-wordpad` at `.github/instructions/`.
2. **WordPress becomes a display adapter and UI only.** Browse mode reads
   through a same-origin WordPress REST adapter that calls the Node
   server-side. No Dictionary record is copied, synced, mirrored, or re-imported
   into WordPress in any store.
3. **WordPress authenticates as its own machine identity** — its own Identity
   service client and its own Dictionary caller row with `scope=display`. The
   WordPad and Games credentials are not reused.
4. **The anti-enumeration invariants are carried into the new tier unchanged**:
   no all-entries route, no pagination, no sequential-id walking, no database
   counts, no bulk export. Language discovery may list every available language;
   every entry-bearing request names exactly one ISO 639-3 language.
5. **One versioned OpenAPI contract and one fixture set**, owned by the Node and
   consumed by both repos, is the enforcement. The prose contract is the
   agreement; the fixtures are what make it true.
6. **Display names for languages and domains are the Node's to supply.** The
   Node schema currently holds codes only. WordPress never hardcodes a name and
   never maps a code to a name locally; until a name exists upstream the Node
   returns the code as the name.

## Affects

- **Contract:** `3IATLAS-DICTIONARY-DISPLAY-JSON-CONTRACT-v1.0.md`, shared
  byte-identically by the Node and plugin repos.
- **Machine-readable contract:** `docs/dictionary-openapi.yaml` (Node).
- **Supersedes, in part:** `docs/PORT-AND-MIGRATION.md` §Cutover step 2 — the
  plugin is **not** removed and is not reduced to a frame. Its data layer, CPT
  and SCF dependency still retire; its UI does not. The rest of that document,
  including every retired-route ruling, stands.
- **Identity:** one new registered service client, `allowed_audiences:
  ['dictionary']`, per `SERVICE-CLIENT-AUTH-SPEC-v1.0.md` §6 in
  `sparxstar-3iatlas-identity-node`.
- **Inherited, not restated:** platform invariants **INV-015** (a credential is
  valid for exactly one resource, in exactly one class) and **INV-010** (one
  identity authority; opaque refs everywhere) already govern the dedicated
  machine identity and the opaque `X-Reader-Ref`. They are cited by the
  contract, not re-derived in it. Both are distributed to this repo by the
  `sparxstar-contract-sync` App at
  `.github/instructions/governance/invariants.compiled.md`.

## Consequence

**Now true:**

- The Node is the single source of lexical truth for every surface, WordPress
  included. WordPress holds no corpus.
- The plugin's `aiwa-cpt-dictionary` CPT, its SCF/ACF field dependency, and its
  WPGraphQL lexical schema stop being a data authority.
- A reader's page render depends on the Node being reachable, so every upstream
  failure — timeout, `401`, `429`, malformed JSON, an HTML error page, an
  oversized body — must produce a controlled WordPress error. A blank page or a
  fatal is a defect, not a degradation.
- The Node's per-reader sub-budget only works if the adapter sends an opaque
  `X-Reader-Ref`; without it the whole site meters as one bucket.

**Now forbidden:**

- Any route, in any tier, that returns all entries or permits a pagination walk.
- Copying Dictionary records into WordPress in any form.
- Exposing the Node URL, credential, private key, service token, or signed
  upstream headers to the browser.
- Reusing the WordPad or Games credential for WordPress.
- Hardcoding a language or domain name, in either repo.
- Maintaining two live implementations of the read path, or two independently
  edited schemas, past the cutover.

**Sequencing:** the Node's JSON support must be deployed before the WordPress
plugin switches its UI. The old WordPress dictionary API is retired as a data
authority only after a tested cutover.

## Scope boundary — what this tier is, and what it is not

Ruled by the platform owner 2026-09-06, after a proposal to publish the corpus
as versioned static JSON artifacts was considered and bounded. Recorded here
rather than as a superseding ADR, because it confirms this decision rather than
replacing it.

| Consumer need | Mechanism |
| :-- | :-- |
| WordPress browse / search / display | `/v1/display/*` |
| WordPress language selection | Display-tier language filters |
| Keyboard / WordPad spell support | The existing versioned SpellLexicon artifact |
| Handwriting recognition backend | The same SpellLexicon artifact |
| Offline full-dictionary mirror | A future requirement, and its own ADR |

Consequences of that boundary:

- **Artifacts do not replace the display tier.** They serve bulk and offline
  consumers. No full-dictionary artifact is built to power ordinary WordPress
  Browse mode.
- **The existing SpellLexicon is the compact lexicon projection.** It is
  extended only where a required field is genuinely missing. A second lexicon
  implementation is forbidden — one pipeline, no independently maintained
  copies.
- **No public tier.** Every route stays credentialed and
  `PUBLIC_ENTRY_GATE_OPEN` is not touched. WordPress calls the Node
  server-to-server; no credential reaches browser code.
- **An offline full-dictionary mirror is out of scope here.** It is a real
  future requirement and gets its own ADR, not an extension of this one.

## Contract distribution

A shared contract may exist as byte-identical copies **only while exactly one
of them is canonical and the others are generated, read-only consumer
snapshots.** Two independently edited copies are two authorities, which
defeats the purpose of having a contract at all.

- **Canonical:** `sparxstar-3iatlas-dictionary-node`,
  `.github/instructions/3IATLAS-DICTIONARY-DISPLAY-JSON-CONTRACT-v1.0.md`.
- **Snapshot:** the copy in `sparxstar-3iatlas-dictionary`, re-taken from the
  canonical file and never edited in place.
- **Ultimate home:** `sparxstar-contracts-registry`. When that registry takes
  the contract, the canonical designation moves with it and both current files
  become snapshots.

That move is not hypothetical, and the mechanism for it already runs. The
`sparxstar-contract-sync` App compiles the registry into
`.github/instructions/governance/` in this repo — `contracts.compiled.md`,
`invariants.compiled.md`, `adr-reference.compiled.md`,
`open-questions.compiled.md` — each stamped `DO NOT EDIT`. Checked at
registry@78a426f, **no existing registry contract covers this seam**, so this
document fills a gap rather than duplicating an authority. It is hand-placed at
`.github/instructions/` precisely because the registry does not yet carry it;
authoring it in the registry and letting the sync distribute it is the end
state, and is the one change that would retire the snapshot problem entirely.

## Provenance

Ruled by the platform owner, 2026-09-06, in the working
session that opened branch `claude/dictionary-plugin-api-cyvzs4` across
`sparxstar-3iatlas-dictionary-node`, `sparxstar-3iatlas-dictionary`, and
`sparxstar-3iatlas-identity-node`.

Repo facts this ADR rests on were read from the code, not from specs:
`src/http/envelope.ts` (envelope shape), `src/http/middleware/authenticate.ts`
(tier gating, `x-reader-ref`, no-store), `src/http/routes/display.ts` (existing
HTML display tier, budget charging, provenance filtering),
`src/domain/projections.ts` and `src/domain/types.ts` (projection safety and
`DisplayEntry`), `migrations/001`–`006` (codes, not names).
