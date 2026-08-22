# Dictionary de-branding — parked proposal

**Status: reference-only. Parked. Not active. Do not delete.**

This directory holds a proposal to de-brand the dictionary's storage layer —
renaming client-branded `aiwa_*` identifiers to platform `sparx_dict_*` (per
ADR-017). It is **captured here deliberately, not merged into the live code**,
because the change is **not the dictionary's to make alone**: the field names
are canonical **upstream**, shared across systems, and renaming them here would
break that contract. The proposal activates only **when the upstream schema
de-brands** and the rename becomes coordinated platform-wide.

## What's in here

| File | What it is | Why it's parked |
|------|------------|-----------------|
| `aiwa-to-sparx-dict-migration-plan.md` | The full migration plan (inventory, tiers, WP-CLI mechanics, open questions). | Planning artifact only — it specifies the migration, it does not perform it. |
| `adr-conformance.yml` | The ADR-registry `repository_dispatch` receiver workflow. | **Deliberately parked out of `.github/workflows/`.** GitHub only executes workflows located in `.github/workflows/`, so sitting here it is **inert by location** — preserved as a reference, not an active gate. |

## How to activate (later, when upstream de-brands)

1. Move `adr-conformance.yml` back to `.github/workflows/` — that, by location,
   makes it live again. No edits to the file are needed.
2. Re-open the migration plan, resolve its open questions in coordination with
   the upstream schema owners, and execute it as its own change.

## Why this exists rather than being deleted or merged

Without this note, a future reader finds two files and guesses wrong — either
mistaking them for **dead code** and deleting them, or for **live code** and
merging/activating them. Both are wrong. This is **neither**: it is a real,
considered plan that is intentionally dormant until an upstream dependency
lands. Parking it (instead of leaving the PR open forever) means everything is
preserved in `main`, nothing executes, and the reasoning travels with the
files.
