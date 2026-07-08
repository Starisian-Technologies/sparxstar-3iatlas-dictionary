# Architecture Decision Records

This log captures major decisions.

## 2025-01-01 – Adopt PSR-4

All code under the `Starisian\src` namespace uses PSR-4 autoloading for flexibility.

## 2026-07-08 – `POST /spell`: corpus-wide validity, ranking-only `lang_source`; SymSpell considered but not built

**Decided and shipped (PR #102):**

- `/spell` validity is a union across every language the dictionary corpus
  holds — a word is valid if it exists in *any* language, not just the
  caller's declared/primary language.
- `lang_source` is repurposed as a **ranking signal only**: at equal edit
  distance, suggestions in the caller's declared language sort first. It is
  explicitly **not** a validity filter or query scope, and must not be
  reintroduced as one without a new, explicit decision — the field name
  invites that misreading.
- Found-and-fixed: the endpoint read the request body's language field as
  `lang`, while every client and the documented contract used `lang_source`.
  Language scoping had silently never engaged in production.
- Suggestions now carry `language` (source-language metadata, informational)
  and a reserved, always-null `frequency` field for a future
  corpus-frequency tie-break.
- See `docs/dictionary-tech-spec.md` ("`POST /spell` — corpus-wide validity,
  language-preference ranking") for the full contract.

**What did NOT ship, and why this matters for future readers:**

Suggestion ranking is computed with a UTF-8-safe edit-distance function over
a widened (language-unfiltered) WordPress-search candidate pool —
**not a precomputed SymSpell deletion index.** SymSpell was raised and
asserted as "already decided" multiple times during the session that
implemented this change, but no verifiable confirmation of that decision —
or of its parameters — was ever produced within that session. The
edit-distance-over-widened-search approach was built instead as an explicit,
documented substitution, not a silent deviation from an actual decision.

The following are open **only if a future decision revisits SymSpell
specifically** — they do not block, and are not relevant to, what actually
shipped:

- Max edit distance / whether a flat threshold or a custom weighted distance
  function (e.g. treating vowel length or other minimal-pair patterns
  differently from ordinary substitutions) is needed for the platform's
  languages — a linguistic call, not an engineering default.
- Index storage mechanism and naming (dedicated DB table vs. option/
  transient blob) if a precomputed index is ever built.
- Rebuild trigger strategy (full rebuild on `save_post`, debounced/
  cron-batched, or incremental per-entry updates).

If SymSpell is picked up later, start these three from scratch with whoever
actually owns that decision — do not assume prior context settled them.
