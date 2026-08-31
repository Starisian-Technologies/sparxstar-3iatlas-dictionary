# SPARXSTAR Platform Architecture
Reference 01 — Stack, Execution Order, Five Invariants, Sovereignty Model
Authority: Platform Integrity Map v1.0 (normative superspec), Platform Overview v1.0, Platform Vision v3.0

## The Five-Layer Stack

Every data payload ascends through all five layers in order. No layer may be skipped, reordered, or collapsed. The Platform Integrity Map Rule 2.4 enforces this mechanically via the loader.

| Layer | Component | Repository | Technical Role |
|---|---|---|---|
| ∞ | Ouroboros Integrity | sparxstar-ouroboros-integrity | Execution substrate. Loads first, enforces last. Shared exceptions, DTOs, interfaces, constants, loader. |
| ☀ | Helios Trust | sparxstar-helios-trust | Edge Agreement Engine. Stateless fail-closed evaluator. Decides whether a request may proceed. |
| ✦ | Sirus Context | sparxstar-sirus-context | Sovereign context engine. Establishes who is present, on what device, under what authority, before anything else. |
| ☁ | Sky DVE Core | sparxstar-sky-dve-core | AI intake layer. Converts conversation to structured draft. Waits for human commit. |
| 🌙 | Mḗh₁n̥s DVE Core | sparxstar-mehns-dve-core | The Epistemic Sieve. Governance enforcement, policy evaluation, cultural law. Mints GovernanceToken. |
| 🌍 | Dheghom DVE Core | sparxstar-dheghom-dve-core | Schema-driven vault. Persistent sovereign storage. Final resting place of aligned, governed truth. |

Execution order is always: Ouroboros → Helios → Sirus → Sky → Mḗh₁n̥s → Dheghom
Enforced by: mu-plugins/00-sparxstar-loader.php

This is the WordPress stack. The Node services (identity node, dictionary node, RLC engine) have no mu-plugin boot sequence and do not integrate Sirus — their authority root is `sparxstar-3iatlas-identity-node`. See ref-07; Sirus is unchanged for every repo listed above.

## Boot Sequence

```
000-sirus.php     muplugins_loaded priority 0
001-helios.php    muplugins_loaded priority 1
002-dheghom.php   plugins_loaded priority 0
003-sky.php       plugins_loaded priority 10
```

Loader uses `exit(1)` on any component failure. NOT `wp_die()`. Hard stop — WordPress must not continue if any component fails to load.

`BootIntegrityException` fires if any component loads outside the loader sequence.

## The Two-Zone Execution Model

**Zone A — Edge (Cloudflare):**
- Helios Worker (TypeScript)
- Cloudflare KV — revocation list, device flags, session revocations
- Pulse verification, step-up triggers, Level 1 content cache

**Zone B — Origin (WordPress / PHP):**
- Sirus, Helios PHP mirror, Sky, Mḗh₁n̥s, Dheghom

**Zone Contract — Hard Rules:**
- Edge MUST be able to DENY any request without origin involvement
- Origin MUST NEVER override an Edge DENY — if edge said no, origin does not evaluate
- Edge and Origin communicate ONLY through signed artifacts: ContextPulse (Sirus-signed) and GovernanceToken (Mḗh₁n̥s-signed)
- Edge does NOT access WordPress database, Sirus internals, or any PHP layer directly
- Origin does NOT access Cloudflare KV directly — all KV interaction routes through KVRevocationClient interface
- No shared mutable state between zones

## The Five Invariants

These do not change. Any proposal that violates any of these does not ship.

**Invariant 1 — Sovereignty precedes execution.**
Authentication and authority resolution happen before any application logic runs.

**Invariant 2 — The Regional Brains are licensed consumers, not owners.**
Regional Brains hold display edges, not origin truth. A community may revoke a display license at any time.

**Invariant 3 — Code is law; AI is an assistant.**
All governance, cultural protocol, and business rules are enforced by deterministic code. AI assists with interpretation and intake. Neither makes governance decisions.

**Invariant 4 — Data is never silently destroyed.**
Failed governance checks result in protective routing (QUARANTINE), not deletion. Intentional destruction requires explicit authorization and produces an auditable Tombstone Record.

**Invariant 5 — Epistemic pluralism is non-negotiable.**
The platform does not impose a single cognitive model on any community's knowledge.

## Foundational Assumption — Commercial Plugin Hostility

**Rule 0.1:** No SPARXSTAR security, governance, sovereignty, or archival guarantee may depend on the behavior, restraint, or compatibility of third-party WordPress plugins. All such guarantees must be enforceable even when other plugins are present and operating normally.

**Rule 0.2:** WordPress is treated as a host runtime for UI, routing, REST API registration, and third-party integration. Dheghom is the sovereign runtime for governed data. When these conflict, the sovereign runtime governs.

**Rule 0.3:** Sovereign guarantees are enforced by architecture — hook interception, cryptographic token requirements, envelope encryption, triple binding, database-level permission restrictions. Not by plugin etiquette or convention.

**Practical consequences:**
- Governed data cannot rely on ordinary wp_postmeta protection alone
- Field prefixes (aiwa_, sparxstar_) are classification markers, not access controls
- DatabaseWriteInterceptor and MetadataReadInterceptor are necessary but not final layers
- The final layer is database-level: MySQL/MariaDB user must have INSERT-only permissions on governed meta keys — UPDATE and DELETE denied at the database engine level

## SPARXSTAR vs DVE — Commercial Distinction

SPARXSTAR is the platform. It is the commercial content engine. The same architecture that protects an elder's oral history protects an artist's unreleased recording, a tribe's governance records, a musician's campaign brief.

Digital Village Elder (DVE) is one governed application on the platform. It is not synonymous with SPARXSTAR.

The Press Room is the commercial surface — same engine, different purpose.

"Build the commercial product. The Elder is already inside it."

When reviewing code: flag any code that hardcodes DVE-specific assumptions into platform-level components. Platform components must work for any SPARXSTAR application, not only DVE.

## Repository Topology

| Repository | Purpose | Load Order |
|---|---|---|
| sparxstar-ouroboros-integrity | Execution substrate. Shared type definitions for all repos. | 0 — loads first |
| sparxstar-helios-trust | Edge Agreement Engine | 1 |
| sparxstar-sirus-context | Context kernel | 2 |
| sparxstar-sky-dve-core | AI intake layer | 3 |
| sparxstar-mehns-dve-core | Governance sieve | 4 |
| sparxstar-dheghom-dve-core | The vault | 5 |
| sparxstar-event-horizon | Nginx perimeter layer | Infrastructure |
| sparxstar-shine | Social publishing engine | Standalone |
| sparxstar-3iatlas-rlc | Classroom language game | Standalone — no DVE runtime |

## Standalone Operational Rule

Every component must work in two modes:
1. **Full-system mode** — correct, governed, safe, with all other components present
2. **Standalone mode** — functional to the highest capability possible, but reduced guarantees

Standalone Sirus: can resolve environment and device context. Without Helios, cannot enforce agreement. Without Mḗh₁n̥s, cannot enforce governance.

## What Each Component Answers

| System | Question It Answers |
|---|---|
| Sirus | What is the situation? Who is present, on what device, in what environment, under what authority? |
| Helios | Given the situation, does this specific request proceed? |
| Mḗh₁n̥s | Given the context and proof, does this action comply with governance? |

These three questions must be answered in this order, by these components. No component answers another's question.

## Failure Mode Hierarchy

Applied in order — first matching condition governs:

| Failure Condition | System Response |
|---|---|
| Missing ContextPulse | DENY |
| Invalid pulse signature | DENY |
| KV unavailable (edge) | DENY for Level 2 and 3. Level 1 cached content may continue for defined grace period only |
| Redis / persistent cache unavailable (origin) | Degrade — disable trust caching, fall back to per-request pulse verification. Do NOT use transients as fallback |
| Origin unavailable | Edge handles Level 1 only. Level 2 and 3 denied. No partial Level 2 service |
| Sirus fails to load | exit(1) — hard stop |
| Helios fails to load | exit(1) — hard stop |
| ContextEngine::current() returns partial context | Throw ContextBootException — terminate request |
| ContextBootException caught and swallowed | PROHIBITED — PHPStan Level 5 must catch this |
| Clock skew > 2 seconds between zones | Pulse verification fails |

## Clock Synchronization

- All systems must maintain clock skew under 2 seconds relative to UTC
- NTP synchronization is a deployment requirement, not a recommendation
- PulseVerifier allows 30-second issued_at skew tolerance for future timestamps
- Clock sync must be verified as part of deployment health checks before loader runs

## WordPress Minimum Version

WordPress 6.8+ minimum across all repos that use WordPress; 6.9 is the platform-wide target standard as repos are updated. Individual repo plugin headers are authoritative for their own requirement — check `Requires at least:` in each plugin header before documenting a specific version. Single-site and multisite compatible. Network activation supported but not required.

Note: Sky v2.0 (draft) moves Sky out of WordPress entirely — it becomes a standalone React package. Sky v1.0 (implemented) is a WordPress plugin. Flag code accordingly based on which version the repo targets.
