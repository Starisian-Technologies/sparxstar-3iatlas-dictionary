# SPARXSTAR Standards and CI Enforcement
## Reference 06 — Coding Standards, CI Rules, Data Modeling Policy, PostgreSQL Install Flow, Field Prefixes

**Authority:** Coding Standards Handbook v1.0, Data Modeling Policy v1.0, PostgreSQL Install Flow v1.0, Platform Integrity Map v1.0

---

## Global System Rules — Non-Negotiable

Apply to every layer of the stack. PHP, JavaScript, GraphQL, TUS, Edge. No exceptions.

### System Mode Declaration

Every system MUST declare its operating mode:

| Mode | Enforcement |
|---|---|
| draft | Limits enforced. Failures logged. CI warns, does not block. |
| development | Limits enforced. CI blocks on hard violations. |
| production | All limits enforced at maximum sensitivity. CI blocks on all violations. |

### Determinism Rule

Same input produces the same output with no hidden side effects. Applies to PHP functions, REST handlers, GraphQL resolvers, and all Sirus-governed actions.

### No Silent Failure

Every failure must:
- Return a defined error
- Log internally with full context
- Never fallback silently

The client receives a generic error. The server logs the full context. Stack traces never reach the client.

### Bounded Execution — Hard Caps

| Limit | Value | Applies To |
|---|---|---|
| Max request CPU time | 2 seconds | All PHP requests, GraphQL resolvers |
| Max request size | 5 MB | All inbound requests |
| Max API response | 100 KB | All REST and GraphQL responses |
| Max concurrent ops | 1 per user | Mutations, uploads, governed actions |
| Max JS bundle | 150 KB gzipped | All JavaScript bundles |
| Max CSS size | 50 KB | All stylesheet bundles |

### Idempotency — Mandatory

All endpoints must be safe to retry without duplication. All writes require an idempotency key. Duplicate requests return same result and produce no additional DB write. Especially: TUS uploads, REST writes, GraphQL mutations.

### Infrastructure Provider Selection

No code written against a provider-specific proprietary API without an abstraction layer that can be swapped. S3-compatible storage interfaces, not named-provider SDKs directly. FAIL if direct provider-specific API call appears in application code without abstraction layer.

---

## PHP and WordPress Standards

### Version Policy

- WordPress: Latest stable release. No deprecated WP APIs.
- PHP: Latest stable with active support. Not security-only. Not end-of-life. Strict types required. No dynamic properties.
- MariaDB: Latest stable — provider-agnostic. No direct SQL interpolation. All queries parameterized.

### PHP Hard Rules

```
FAIL: PHP file missing declare(strict_types=1)
FAIL: function missing typed parameters or return type
FAIL: raw superglobal access without sanitization
FAIL: direct SQL string interpolation
FAIL: SELECT * in any query
FAIL: governed action without Sirus call
FAIL: governed action without ability check
FAIL: governed action without consent verification
FAIL: ContextBootException caught and swallowed
FAIL: local redefinition of any Ouroboros-owned type
```

### Sirus Integration — Mandatory Pattern

No repository may independently determine authority, context, or applicable rules.

```
Before any governed action:
  context   = Sirus::resolveContext(request)
  authority = Sirus::resolveAuthority(caller)
  if context is null OR authority is null:
    FAIL CLOSED
    return error
    do NOT execute action
    do NOT guess
    do NOT fallback
```

**Performance constraint:**

| Metric | Limit |
|---|---|
| Sirus calls per request | 1 preferred / 2 hard cap |
| Sirus response cache TTL | 30 seconds maximum |
| Cross-user context reuse | Forbidden |
| Long-lived authority caching | Forbidden |

**FAIL conditions:**
- Governed action exists without preceding Sirus call
- Sirus output modified or overridden downstream
- Local permission check without Sirus delegation

### Trust Levels

| Layer | Trust Level | Rule |
|---|---|---|
| Client | Untrusted | Validate everything. Assume nothing. |
| API layer | Validated | Validates all upstream input before acting. |
| Sirus output | Authoritative | Must not be modified, merged, or overridden downstream. |
| Cache (Redis) | Disposable | Never treat as source of truth. Always verify. |
| DB (MariaDB) | Authoritative | Single source of truth. Never trusts upstream. |
| Edge cache | Disposable | TTL-bounded. Invalidated on write. |

---

## JavaScript Standards

```
FAIL: event listener without throttle or debounce
FAIL: JS bundle exceeds 150 KB gzipped
FAIL: API call without timeout
FAIL: sensor active beyond 5 seconds without auto-disable
FAIL: blob in memory exceeds 5 MB
```

---

## Media Standards

```
FAIL: audio sampleRate > 16000
FAIL: audio channels > 1
FAIL: audio bitrate > 32000
FAIL: video width > 640 or height > 480
FAIL: video fps > 15
FAIL: video codec is not H.264 Baseline
```

---

## TUS Upload Standards

```
FAIL: upload chunk > 512 KB
FAIL: upload without chunk checksum
FAIL: upload without UUID
FAIL: full-file upload endpoint present
```

TUS minimum session timeout: 3600 seconds.

---

## GraphQL Standards

```
FAIL: query depth > 5
FAIL: N+1 query pattern in resolver
FAIL: governed resolver without Sirus call
```

---

## CSS Standards

```
FAIL: CSS bundle exceeds 50 KB
FAIL: blur filter or heavy shadow in production CSS
```

---

## Distributed System Rules

```
FAIL: cache invalidation or event emission before DB commit confirmed
FAIL: client-supplied timestamp used for ordering or conflict resolution
FAIL: job silently discarded after max retries without dead-letter entry
FAIL: breaking schema change deployed without feature flag
FAIL: DB migration that is not rollback-safe
FAIL: IndexedDB usage without defined eviction policy
FAIL: direct provider-specific API call without abstraction layer
```

### Time Authority

Server time is authoritative. Client timestamps are advisory only and must never be used for ordering decisions, session authority, or conflict resolution.

### Dead-Letter Queue

Jobs that exhaust their retry budget must NOT be silently dropped. They move to a dead-letter queue where they remain queryable and manually retryable. Dead-letter queue depth > 0 triggers alert in production.

### Deployment Safety

- Schema changes must be backward compatible
- Breaking changes must be behind a feature flag
- Rollback must be possible within one deploy cycle
- DB migrations must be additive first (add column) before destructive (drop column) — two-phase migration

---

## Client-Side Storage Limits

| Storage Type | Limit | Eviction Policy |
|---|---|---|
| IndexedDB | 20 MB maximum | LRU |
| localStorage | 5 MB maximum | Explicit TTL |
| sessionStorage | Session only | Cleared on session end |
| Service Worker cache | Defined per route | TTL or version-based invalidation |

---

## Abuse Escalation

| Violation Pattern | Automated Response |
|---|---|
| Burst traffic > 10 req/sec | Throttle — 1 req/sec for 60 seconds |
| Rate limit exceeded 1-3 times | Throttle — 5 minutes |
| Rate limit exceeded 4-10 times | Temporary block — 5 to 30 minutes |
| Rate limit exceeded > 10 times | Extended block — 24 hours minimum |
| Header spoofing detected | Immediate block |

---

## Field Prefix Protocol

All schema meta keys carry a canonical domain prefix:

| Prefix | Domain |
|---|---|
| `aiwa_` | AIWA content fields (governed) |
| `sparxstar_` | Platform-level fields (governed) |
| `film_`, `bio_` | Domain-specific content fields (governed) |
| `_spx_` | Internal session/UUID fields (Sky layer, not Sieve-governed) |
| `dheghom_` | Cache keys and runtime IDs |

Prefixes are classification markers, not access controls. Any plugin can write to a prefixed key. Architecture enforces protection, not naming convention.

**Example:** `aiwa_master_hash` is the storage key; `dheghom_1.01_cache_key` is its corresponding cache identifier.

---

## Data Modeling Policy — Decision Matrix

Apply these rules in order. Stop at the first match.

**1. Governance / Legal / Money → RELATIONAL (Required)**

Use PostgreSQL relational tables when ANY of these apply:
- Consent or permission records
- Royalties, payments, or financial obligations
- Ownership, attribution, or rights assignment
- Governance rules or authority definitions
- Audit requirements
- Override authority records

**2. Structured + Queryable (Not Enforced) → JSONB + GIN Index**

Use JSONB when ALL THREE are true:
- Data is structured and occasionally filtered
- NOT used in joins
- NOT governance-critical

Requirements: add GIN index, document expected query patterns in schema comments.

**3. Display / Informational → JSONB (No Index)**

Use when ALL are true:
- Never filtered in WHERE clause
- Only read and rendered — never joined
- No constraints needed

**4. Repeated Entities → RELATIONAL (Normalization Trigger)**

If data: repeats across multiple rows, requires own identity (UUID), or is referenced from other tables → must become relational table.

**5. Spatial Data → HYBRID (Relational + PostGIS)**

- PostGIS must be enabled at system level across all deployments
- Coordinates are optional per row
- Fictional or mythological places MUST NOT have a geom value
- When lat/lon provided, geom must be populated by trigger or application logic

**6. Cross-System Identity → RELATIONAL (Always)**

`uuid`, `record_id`, `version_chain_id` must always be relational columns. Never JSONB. These drive Neo4j synchronization and lineage tracking.

**7. Graph Relationships → NEO4J ONLY**

Model exclusively in Neo4j — DERIVED_FROM, GOVERNED_BY, EQUIVALENT_TO, PART_OF, AUTHORIZED_BY, SUPERSEDED_BY. Do not replicate in PostgreSQL.

**Decision Flow (stop at first YES):**
1. Does it affect governance, money, or rights? → RELATIONAL
2. Does it need joins or stable identity? → RELATIONAL
3. Is it queried but not governance-critical? → JSONB + GIN INDEX
4. Is it only displayed? → JSONB (no index)
5. Is it a repeated entity referenced elsewhere? → RELATIONAL
6. Does it require spatial queries? → RELATIONAL + PostGIS
7. Is it a relationship requiring traversal? → NEO4J

### Anti-Patterns (flag at code review, must fix before merge)

**ANTI-PATTERN 1 — JSONB for Governance:**
consent_rules, permission_flags, or royalty_terms stored in JSONB = CRITICAL VIOLATION.

**ANTI-PATTERN 2 — Relational Tables for Display Blobs:**
Join tables for UI display data or informational arrays that are never queried by their foreign keys.

**ANTI-PATTERN 3 — JSONB Used in Joins:**

```sql
-- WRONG
JOIN records ON records.metadata->>'record_id' = events.record_id
-- CORRECT
JOIN records ON records.record_id = events.record_id
```

**ANTI-PATTERN 4 — Duplicate Modeling:**
Same data in both a relational column and a JSONB field creates drift. Pick one layer.

---

## PostgreSQL Install Flow — Hard Rules

Mandatory sequence: **Roles → Schema → Privileges → Services**

Never break that order.

| Step | What | Who runs it |
|---|---|---|
| STEP 0 | Environment setup | DBA only |
| STEP 1 | RBAC Roles (role identities, no grants) | postgres / superuser |
| STEP 2 | Schema Load (tables, constraints, triggers, PostGIS) | migration_user via Flyway |
| STEP 3 | RBAC Privileges (grants, revokes, default privileges) | postgres / superuser |
| STEP 4 | Application Binding | DBA |
| STEP 5 | Projection Layer (Debezium / Neo4j sync) | After Step 3 complete |

**Never do:**
- Run privileges before schema — tables do not exist yet, grants silently fail
- Mix role definitions and privilege grants in one file
- Run RBAC files through Flyway — Flyway runs as migration_user, role creation requires superuser
- Use CREATE OR REPLACE TRIGGER — not valid PostgreSQL syntax. Use DROP TRIGGER IF EXISTS then CREATE TRIGGER.
- Use `\i` (backslash-i includes) inside Flyway migrations — Flyway does not support psql meta-commands
- Enable projection before Step 3 is complete

**Neo4j must only receive data from a fully governed PostgreSQL instance.** Enabling projection before Step 3 creates shadow states that are expensive to reconcile.

---

## Repository Documentation Standard

Every SPARXSTAR repository must maintain:
- `.github/copilot-instructions.md` — architectural law for AI working in that codebase
- README covering: what the repo is, what it owns, what it does not own, hard rules, dependencies
- WordPress minimum version declared in README, composer.json, bootstrap files, and AGENTS.md

---

## Engineering Statement

"Engineering for underserved environments is not about removing features. It is about designing systems that survive reality."

"If it cannot fail a build, it is not a standard. It is a suggestion."

"Bandwidth is a financial cost. Battery is a finite resource. Connectivity is not guaranteed. Build accordingly."
