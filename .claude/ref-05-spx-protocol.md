# SPARXSTAR SPX Protocol
## Reference 05 — Naming Protocol, Drift Classification, ai_manifest()

**Authority:** AI Manifest Protocol v0.5.0-draft (supersedes v0.4.0-draft)
**Status:** PATENT PENDING — Invention Date: April 10, 2026 — Inventor: Max Barrett / Starisian Technologies

---

## PATENT SENSITIVITY — Review Instructions

**This spec covers patent-pending material.** When reviewing code against this spec:
- Flag conformance/violations by rule reference only
- Do NOT reproduce novel algorithm implementation details in review output
- Do NOT suggest public documentation of the drift classification algorithm or brittleness scoring formula
- Do NOT commit further public changes to `sparxstar-ai-manifest-protocol` or `spx-protocol` repos without patent attorney clearance

Patent Family: A (Brain-Sieve Architecture) and C (Multi-Tiered Executable Governance)
Repos: `Starisian-Technologies/sparxstar-ai-manifest-protocol` and `Starisian-Technologies/spx-protocol`

---

## Purpose

The SPX Protocol solves a coordination failure: when AI agents generate code across multiple repositories without a shared naming contract, they produce inconsistent names for the same concepts. The result is integration failure — correct names for nonexistent endpoints, mismatched routes, broken contracts that no linter catches because the syntax is valid while semantics diverge.

**Core claim:**
- The manifest tells you what exists.
- The protocol tells you what it is called.
- The drift analyzer tells you where the system is under stress before it breaks.

---

## The Four-Layer Architecture

Each layer has exactly one job. No layer performs the job of another. This separation is non-negotiable.

| Layer | Component | Nature | Job |
|---|---|---|---|
| 1 — Law | spx-vocab.json | Deterministic | Defines the valid coordinate space. The closed vocabulary. |
| 2 — Police | spx_validator.py | Binary | Pass or fail. Never rewrites. Never infers. |
| 3 — Harvest | ai_manifest() | Declarative | Declares what exists and is callable. Runtime contract. |
| 4 — Telemetry | spx_drift_analyzer.py | Probabilistic | Observes, scores, reports drift. Never blocks. Never enforces. |

**Invariant:** A validator that mutates state is not a validator. A telemetry tool that blocks is not telemetry. These boundaries are absolute.

---

## The Naming Equation

```
Name = f(authority, system, product, [subsystem], domain, entity, action, [execution])

Group 1 — Structure Path (WHERE it lives):
  authority  ∈ structure.authorities  [required]
  system     ∈ structure.systems      [required]
  product    ∈ structure.products     [required]
  subsystem  ∈ structure.subsystems   [optional]

Group 2 — Function Signature (WHAT it does and HOW):
  domain     ∈ domains    [required]
  entity     ∈ entities   [required]
  action     ∈ actions    [required]
  execution  ∈ executions [optional]
```

All terms from closed sets in spx-vocab.json. No term invented, inferred, abbreviated, or guessed.

Invalid term → ERR_COORDINATE_UNDEF. Full stop.

**Composition output:**

```
function:  spx_{authority}_{system}_{product}_{domain}_{entity}_{action}
route:     /{authority}/{system}/{product}/{domain}/{entity}/{action}
class:     SPX\{Auth}\{Sys}\{Prod}\{Dom}\{Ent}\{Act}Service
namespace: SPX\{Auth}\{Sys}\{Prod}\{Dom}\{Ent}
file:      /src/{Auth}/{Sys}/{Prod}/{Dom}/{Ent}/{Act}Service.php
```

With execution coordinate:

```
function:  spx_{authority}_{system}_{product}_{domain}_{entity}_{action}_{execution}
```

Examples:

```
spx_brain_sparxstar_player_artifact_audio_read
spx_brain_sparxstar_player_artifact_audio_read_stream
spx_brain_sparxstar_player_artifact_audio_read_batch
spx_group_aiwa_archive_lexicon_word_create
```

---

## Structure Path Rules

- All coordinates MUST be lowercase
- Word separation MUST use underscores. Hyphens are forbidden.
- All terms MUST be registered in system/spx-vocab.json under 'structure'
- Order is fixed: authority → system → product → [subsystem]

---

## Machine State Taxonomy

The validator returns exactly one state. Seven states defined. No other output permitted.

| State | Condition | Response |
|---|---|---|
| VALID | All coordinates valid. Constraints satisfied. | Execution continues. |
| ERR_SCHEMA_MISMATCH | ai_manifest() output missing required fields or wrong types. | CI FAILS. Repository classified NOT_AI_GOVERNED. |
| ERR_COORDINATE_UNDEF | Token not in vocabulary. No synonym mapping. | CI FAILS. Token and position reported. No fallback. |
| ERR_INTENT_AMBIGUOUS | Intent maps to more than one valid coordinate set. | CI FAILS. All matches reported. Amendment required. |
| ERR_NAMESPACE_COLLISION | Two names in same Structure Path resolve to identical coordinates. | CI FAILS. Both names reported. No auto-resolution. |
| ERR_ILLEGAL_COMBINATION | Coordinate pairing violates constraint rules. | CI FAILS. Violated constraint and offending pair reported. |
| ERR_COORDINATE_RESERVED | Coordinate set already registered to different logic. | CI FAILS. Existing registration reported. Amendment required. |

---

## ai_manifest() — The Universal Beacon

Every AI-governed repository MUST implement ai_manifest().

Rules:
- The name is fixed. Cannot be changed, namespaced, or renamed.
- Any repository without ai_manifest() is NOT AI-governed.
- CI MUST check for its existence and fail if absent.
- MUST be callable with zero arguments.
- All language implementations MUST return byte-for-byte compatible JSON structure.
- All calls to governed coordinates MUST be made over TLS.

**Two modes:**
- `public` — vocabulary and grammar only. Any AI reads the grammar.
- `governed` — vocabulary plus scoped live callable coordinates. Authorized callers only, governed by Sirus agreements and Helios trust.

**Return schema — 14 fields, all required:**

```json
{
  "protocol":    "SPX",
  "version":     "2.2.0",
  "governed":    true,
  "vocab":       "system/spx-vocab.json",
  "contract":    "system/CONTRACT.md",
  "validator":   "tools/spx_validator.py",
  "domains":     {},
  "entities":    {},
  "actions":     {},
  "executions":  {},
  "synonyms":    {},
  "scope":       "public",
  "mode":        "new",
  "legacy_map":  null
}
```

`legacy_map` MUST be a string file path or null. NEVER an inline object.

---

## Drift Classification — Three Tiers (Patent Core)

The three-tier drift classification is the novel contribution of v0.5.0. It enables the system to distinguish between AI hallucination, syntactic misexpression, and architectural stress signals — three fundamentally different conditions that prior systems treat identically as validation failure.

| Tier | Definition | Signal | Response |
|---|---|---|---|
| Explicit Drift | Fails validation. Fails after normalization. | AI hallucinated a coordinate. Term does not exist in vocabulary. | Vocab amendment required or AI needs retraining signal. |
| Recoverable Drift | Fails validation. Passes after normalization. | AI knew correct coordinates. Used wrong syntax (dot instead of underscore). | Linter alignment needed. Architecture is sound. |
| Structural Drift | Repeated dot-notation at consistent positions. | AI is sensing hierarchy the flat coordinate system cannot express. | Architectural signal. Vocabulary amendment may be needed. Structural review required. |

**Key insight:** Structural drift is not noise. It is the AI voting on your architecture. When multiple AI agents independently express the same structural drift pattern, the protocol should surface it as an amendment candidate, not suppress it as a validation failure.

---

## Linter Observer Effect

A linter configured to automatically convert dot-notation to underscore-notation creates artificial conformance. The code appears compliant. The drift score appears low. But the latent tension is hidden.

**Two linter behaviors:**
- **Constructive linting:** Enforces spx_ prefix and snake_case. Reduces noise without hiding signal. **Approved.**
- **Destructive linting:** Automatically converts dot-notation to underscores. Hides latent tension. **NOT approved for protocol-governed codebases.**

**Rule:** A linter must warn on structural expression mismatches — never silently mutate them.

---

## Vocabulary Semantic Constraints

Vocab entries carry descriptions (semantic anchors for AI coordinate selection) and constraint rules (mechanically enforced by validator).

**Constraint types:**
- `allowed_actions` on entity entry — action must be in entity's allowed list
- `disallowed_with` on entity entry — action must not be in entity's disallow list
- `allowed_domains` on action entry — domain must be in action's allowed list
- `allowed_entities` on action entry — entity must be in action's allowed list
- `allowed_entities` on domain entry — entity must be in domain's allowed list

All constraint checks run before composition. Violation → ERR_ILLEGAL_COMBINATION, halt.

---

## Constraint on illegal combinations

Examples that CORRECTLY FAIL:
- `transcribe` on `session` entity → ERR_ILLEGAL_COMBINATION
- `transcribe` on `word` entity → ERR_ILLEGAL_COMBINATION
- `BRAIN` (uppercase authority) → ERR_COORDINATE_UNDEF (casing violation)
- `sparx-star` (hyphen in system) → ERR_COORDINATE_UNDEF (hyphen violation)
- `corporate` (invented authority) → ERR_COORDINATE_UNDEF (not in vocab)
- `real-time` (hyphen in execution) → ERR_COORDINATE_UNDEF

---

## The Conformance Loop

```
spx-vocab.json → seeds semantic index at build time
spx_validator.py → enforces conformance on every PR
semantic index → learns governed patterns from conformant code
Copilot / AI → generates new code using learned patterns
spx_validator.py → validates new code against vocabulary
                 → loop closes
```

Mathematical property: as conformance approaches 100%, `semantic_index ≈ f(spx-vocab.json)`

---

## Final Rules

- Same input + same vocab = SAME output. If not, the protocol is incomplete.
- You cannot name what does not exist. You cannot call what is not registered.
- A validator that mutates state is not a validator. It is an unreliable executor.
- Structural drift is not noise. It is the AI voting on your architecture.
