# Two Authority Roots, Split by Platform
Reference 07 — Sirus governs the WordPress repos; sparxstar-identity-node governs the Node services
Authority: owner decision, 2026-08-31. Scopes ref-01/02/04/06 and
`.github/instructions/sparxstar-coding-standards-v1 (2).md` §1 to the platform each governs.
It does NOT retire Sirus.

## The ruling

**Sirus is not retired.** It remains the authority layer for the WordPress/PHP repositories — the
DVE stack, where it resolves device and user context alongside Helios. Every rule in ref-06 and
coding-standards §1.1–1.5 stands unchanged for the repos it already governed.

**The Node services are a separate platform with a separate root.**
`sparxstar-3iatlas-identity-node`, `sparxstar-3iatlas-dictionary-node` and the RLC engine have no
WordPress — no WordPress session, user record or device context for Sirus to resolve. They do not
integrate Sirus.

That is not the same as having no users. The identity node holds account records and authenticates
the people who log in; its account holders are its own records, not WordPress identities. The
distinction is whose user record it is, not whether users exist. (The dictionary node is the
stronger case: machine-to-machine only, no human callers at all.) Their authority root for *who is
calling* is `sparxstar-3iatlas-identity-node`: an RS256 token carrying a per-service audience
(`aud: dictionary` for the dictionary service), verified against its JWKS.

The intent of the Sirus mandate — *no repo invents its own answer to who is calling* — is
satisfied on both platforms. Only the root differs.

## Which root applies

| | WordPress / PHP repos | Node services |
|---|---|---|
| Authority root | **Sirus** (`sparxstar-sirus-context`) | **`sparxstar-3iatlas-identity-node`** |
| Who is calling? | `Sirus::resolveContext()` / `resolveAuthority()` | RS256 token verified against the identity node's JWKS |
| Is this action permitted? | Sirus — governed action check | The service that owns the action, locally and fail-closed |
| What consent applies? | Sirus — consent resolution | Helios / Mḗh₁n̥s where deployed. Never the identity node |
| Device / user context | Sirus ContextPulse, `SIRUS_PULSE_SIGNING_KEY` | No WordPress session, user record or device context exists to resolve (the identity node has its own account holders; that is a different thing) |
| Fail-closed on unreachable root | Required | Required, identically |

## Two mistakes this document exists to prevent

**Do not carry Sirus into a Node service.** It is WordPress device/user discovery; a Node service
has nothing for it to discover. A `// PROVISIONAL` `SirusClientInterface` stub in a Node repo is a
PHP contract stubbed in a service with no PHP, and it is a fail condition — not because Sirus is
wrong, but because it is not that platform's root.

**Do not carry the Sirus job description into the identity node.** Sirus answers four questions:
who is calling, what rules apply, is this action permitted, and what consent exists. On the Node
side only the first belongs to the identity node — its own locked `AGENTS.md` §2 forbids the rest,
because answering *what are you allowed to do* would require it to know another service's route
table. A `scope`, `permissions`, or `trust_level` claim in a suite token is a spec violation there
however Sirus behaves in WordPress.

Both are the same error from opposite directions: treating one platform's root as the other's.

## Status of this repository

This plugin is a WordPress repo, so Sirus governs it — and it operates in **standalone mode**, so
it holds no runtime dependency on Sirus, Helios, Mḗh₁n̥s or Dheghom, exactly as ROLE.md and
`.github/copilot-instructions.md` already state. Nothing about that changes.

The record is kept here because the dictionary corpus this plugin serves is moving to
`sparxstar-3iatlas-dictionary-node` (a Node service, identity-node root), so both roots appear in
work that touches this repo's reference set. Reach for the one that matches the platform in front
of you.
