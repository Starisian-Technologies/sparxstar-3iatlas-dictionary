# Authority Root — sparxstar-identity-node Supersedes Sirus
Reference 07 — What transfers, what does not, and what an agent must not reintroduce
Authority: owner decision, 2026-08-31. Supersedes the Sirus authority mandate in ref-01, ref-02,
ref-04, ref-06 and `.github/instructions/sparxstar-coding-standards-v1 (2).md` §1 where they conflict.

## The ruling

`sparxstar-identity-node` is the platform's authority root. **Sirus is retired.** No new work
integrates Sirus, stubs a `SirusClientInterface`, or marks a `// PROVISIONAL` Sirus seam.

This is a substitution of the trust root, not of the whole control plane. Read the table before
assuming the identity node inherits everything Sirus was described as doing — most of the
cross-repo mandate does not transfer, and one part of it was never one service's to answer.

## What transfers

| Sirus role | Successor |
|---|---|
| The single named authority root — no repo decides for itself who a caller is | `sparxstar-identity-node` |
| Caller identity resolution before any governed action | RS256 token verified against the identity node's JWKS, with a per-service audience (`aud: dictionary` for the dictionary service) |
| Token validity and revocation | The identity node's validate/revocation surface |
| Fail-closed on an unreachable root | Unchanged, verbatim: refuse the action, no fallback, no guess |
| "Authoritative, must not be modified downstream" | Applies to identity-node claims |

## What does NOT transfer

| Sirus role | Where it goes |
|---|---|
| *May this caller take this action?* | The service that owns the action, locally and fail-closed. Its route table and tiers are its own structure; delegating the decision would require the identity node to know another service's routes — which its own `AGENTS.md` §2 boundary rule forbids |
| Consent and agreement evaluation | Helios / Mḗh₁n̥s where deployed. Never the identity node |
| WordPress device and user context discovery | **No successor in the Node series.** The Node services have no users, no devices and no WordPress. Do not stub this path — a greenfield service inheriting a retired trust root is the failure this ruling exists to stop |
| ContextPulse issuance and `SIRUS_PULSE_SIGNING_KEY` | WordPress-era artifacts of the two-zone model (ref-01, ref-02). They describe the DVE stack as built, not the authority root for new services |
| The `000-sirus.php` mu-plugin boot slot and its place in the execution order | WordPress-era. Retained in ref-01 as a description of the deployed stack, not as a requirement on new services |

## The distinction that keeps being lost

Sirus was described as answering four questions: who is the caller, what rules apply, is this
action permitted, and what consent exists. The identity node answers **only the first**, by design
and by its own locked boundary rule: it answers *who are you*, never *what are you allowed to do*.

An agent that substitutes the name without the split will try to move route authorization into the
identity node, which is a spec violation in that repository. An agent that reads "identity node
does less than Sirus did" as a gap will invent a local identity path, which is a fail condition
here. Both are the same mistake from opposite directions.

Tier and audience claims state a **fact** about the caller. They never by themselves grant a
permission.

## Fail conditions

| FAIL | Governed action without a caller identity verified against the identity node |
| FAIL | Caller identity determined locally instead of delegated |
| FAIL | Identity-node claims modified, merged, or overridden downstream |
| FAIL | Authorization decision that is not fail-closed |
| FAIL | New integration against Sirus, or a `// PROVISIONAL` Sirus stub |
| FAIL | Authorization logic added to the identity node |

## Status of this repository

This plugin operates in standalone mode and holds no runtime dependency on the authority root
either way. The record matters here because this repo carries the platform reference set that
agents read first, and because the dictionary corpus it serves is moving to
`sparxstar-3iatlas-dictionary-node`, which does authenticate against the identity node.
