# SPARXSTAR Component Boundaries

## Reference 04 — What Each Component IS and IS NOT

**Authority:** Platform Integrity Map v1.0, all component specs, Copilot Instructions

---

## The One Question That Cuts Every Boundary Dispute

| System | Answers                                  |
| ------ | ---------------------------------------- |
| Sirus  | What is the situation?                   |
| Helios | Does this specific request proceed?      |
| Mḗh₁n̥s | Does this action comply with governance? |

These are three different questions. They must be answered in this order, by these components. No component answers another's question.

---

## Ouroboros Integrity

**IS:**

- Platform loader and boot orchestrator (`00-sparxstar-loader.php`)
- Shared exception classes (BootIntegrityException, ContextBootException, TripleBindingException, TokenValidationException, DraftEncryptionException, ValidationException)
- Shared DTOs (ContextPulse, AgreementResult, GovernanceToken, ResourceSensitivity, ZonePrimitive)
- Shared interfaces (ModuleEntity, ModuleMapper, HeliosClientInterface, AuditLedgerInterface)
- ValidationHelper (all static validation methods including normalize_unicode_nfc)
- Platform constants (Platform.php)
- shared-test-vectors.json (agreement evaluator consistency vectors)
- GovernanceTokenSigningMaterial (canonical implementation in src/Utils/)

**IS NOT:**

- Business logic of any kind
- A governance or policy engine
- A schema definition layer
- An AI or content processing layer
- A WordPress plugin with features
- Identity resolution (Sirus)
- Agreement evaluation (Helios)
- Governance policy (Mḗh₁n̥s)
- Structured field persistence (Dheghom)

**Hard rules:**

- `declare(strict_types=1)` in every PHP file
- All classes are `final` unless explicitly abstract
- No WordPress hooks except in the loader
- PHPStan Level 5 must pass before any merge
- Every exception class extends RuntimeException except ValidationException which extends Exception
- No business logic of any kind enters this repository
- If the thing you are adding is needed by only one component, it belongs in that component, not here

---

## Helios Trust

**IS:**

- AgreementEvaluator — canonical logic, identical in TypeScript (edge) and PHP (origin)
- PulseVerifier — six-check verification
- KVRevocationClient — reads from and writes to Cloudflare KV
- StepUpPolicy
- enforce_sky_entry() — Sky must call this before chat loop execution
- enforce_release_entry() — Release Gate must call this before Sieve
- Session triple binding verification
- Audit log (append-only)
- Cloudflare Worker (TypeScript) + WordPress mu-plugin bridge (PHP)

**IS NOT:**

- An identity store
- A session manager
- A user database
- The source of device context — that is Sirus
- The source of policy authorship — that is Mḗh₁n̥s
- Connected to WordPress authentication in any way
- A replacement for or extension of wp-login.php
- The source of identity claims in the ContextPulse (Sirus produces it, Helios verifies it)

**Hard rules:**

- MUST NOT interact with WordPress authentication in any way
- MUST NOT create WordPress sessions for frontend users
- MUST NEVER grant Level 3 access at the edge
- MUST fail closed — missing or invalid input = DENY, always
- MUST NOT use email 2FA for Level 3 resources
- Audit log MUST NOT be deletable by any actor
- ContextPulse MUST NOT be stored in localStorage or any JavaScript-accessible location
- Signing keys MUST NOT be stored in WordPress options or the database
- AgreementResult must resolve to exactly one of: ALLOW_EDGE | ALLOW_ORIGIN | STEP_UP | DENY | PROVISIONAL. No null. No fallback. No default pass.

**Dual Execution Consistency:**
AgreementEvaluator exists in TypeScript (edge) AND PHP (origin). Both must pass shared-test-vectors.json (owned by Ouroboros). If you change evaluation logic, change BOTH implementations simultaneously. A change to one without the other breaks the platform.

**Selective enforcement:**
Helios enforcement is opt-in via possession of a Helios-issued credential. Normal WordPress traffic not carrying a Helios auth cookie is not intercepted. Only requests directed at /sparxstar/ or /aiwa/ endpoints, or requests carrying a Helios-issued auth cookie, enter the gated path.

---

## Sirus Context

**IS:**

- ContextEngine — context creation and current() accessor
- SirusContext DTO — primary output of context engine
- ContextPulse generation and signing (PulseGenerator)
- PulseVerifier — generates and signs (Helios verifies)
- TrustEngine — trust state and trust score computation
- DeviceContinuity — server-issued device_id, fingerprint, session recovery
- EnvironmentResolver, IdentityResolver, AuthorityResolver, ConsentManager
- StepUpPolicy, NetworkContextBroker

**IS NOT:**

- The authentication system — that is Helios
- The governance engine — that is Mḗh₁n̥s
- The storage layer — that is Dheghom
- The source of policy decisions — Sirus describes; Helios and Mḗh₁n̥s decide
- A WordPress session manager for frontend users
- The source of identity claims in the ContextPulse (Pulse carries device state and trust signal only)

**Hard rules:**

- Must be deployed as WordPress Must-Use (mu-plugin)
- MUST NEVER call wp_set_auth_cookie() or issue JWTs
- MUST NEVER query Dheghom or any external plugin directly
- ContextEngine::current() must return a valid SirusContext or throw ContextBootException — never return null, never return partial context
- device_id is ALWAYS server-issued — never derived from JS fingerprint alone
- IP addresses stored with last octet zeroed: 192.168.1.0
- ContextPulse NEVER contains identity claims

**Trust Score Algorithm (frozen):**

```
base = 1.0
device drifting:   -0.3
geo mismatch:      -0.2
new session:       -0.1
recent failures:   -0.3
clamped to [0.0, 1.0]
```

**CLI Context (when PHP_SAPI === "cli"):**

```
identity_id  = "SYSTEM"
trust_score  = 1.0
trust_level  = "NORMAL"
authority_id = "GLOBAL"
device_id    = "CLI"
```

Helios grants ALLOW_ORIGIN to SYSTEM identity for all maintenance tasks. CryptographicAuditLedger still records all writes under SYSTEM identity.

---

## Sky DVE Core

**IS:**

- spx-sky-engine WordPress plugin (v1.0) / standalone React package (v2.0 draft)
- wp_spx_sessions custom table — conversation accumulation store (NOT a WordPress auth session)
- Chat loop (POST /spx/v1/chat)
- Commit Gate / Release Gate (POST /spx/v1/commit)
- Draft accumulation and merge logic
- Confirmed fields tracking
- Ability schema loading (SCF schema + Block schema)
- SpxExtractionService, SpxDraftService, SpxSessionRepository

**IS NOT:**

- Identity resolution — use HeliosClientInterface
- Device context — comes from Sirus ContextPulse
- Governance evaluation — that is Mḗh₁n̥s (runs at Release, not at Save)
- Structured field persistence — that is Dheghom
- WordPress authentication — Sky MUST NOT create WordPress sessions

**Hard rules:**

- Session = wp_spx_sessions table ONLY. NEVER WordPress $\_SESSION or wp auth cookies.
- The AI NEVER saves, validates, or publishes. It extracts only.
- Nothing irreversible executes without the Commit Gate.
- Sky MUST NOT perform identity checks through any path other than HeliosClientInterface.
- Asset fields are IMMUTABLE after first write. Any modification produces a new \_derived field.
- Merge logic uses array_key_exists() NOT isset(). isset() is PROHIBITED — produces false positives on null.
- Confirmed fields can only be overwritten by explicit user action, never by AI extraction alone.
- The AI prompt is fixed across all abilities. Never change it per user.
- Loads SCF schema + Block schema for Abilities. Does NOT load Policy schema (that is Mḗh₁n̥s).

**"Session" definition (Platform Integrity Map Rule 1.3):**
Any reference to "session" within Sky or Dheghom refers exclusively to the custom wp_spx_sessions database table. This is a conversation accumulation store, NOT a PHP session or WordPress authentication session.

**Sky v1.0 vs v2.0:**

- v1.0 (implemented): WordPress plugin, sparxstar-sky-dve-core
- v2.0 (draft, May 2026): Standalone React package (@sparxstar/sky) + SkyOrchestrator backend service + WordPress abilities layer
- v2.0 introduces boundary: React package never calls AI model directly — all model interaction passes through SkyOrchestrator via single POST endpoint
- Flag code accordingly based on which version the repo targets

---

## Mḗh₁n̥s DVE Core

**IS:**

- PolicyRegistry — 4-layer resolver (global → authority → ability → individual)
- PolicyDispatcher — orchestrator, runs policies, propagates mutations
- PolicyCompiler — JSON policy grammar evaluator (AND/OR groups)
- GovernanceTokenMinter — HMAC-SHA256 token generator
- CryptographicAuditLedger — SHA-256 chained append-only ledger
- SieveKernel — boots at muplugins_loaded, registers all interceptors
- DatabaseWriteInterceptor — blocks rogue writes to aiwa*/sparxstar* fields
- MetadataReadInterceptor — blocks rogue reads of draft/quarantined fields
- RestApiInterceptor, GraphQlInterceptor, QueryEnforcementFilter
- PolicyResolver (new — DVE-TRUST-001)
- Policy schema loading (SPARXSTAR JSON policy grammar)

**IS NOT:**

- Context production — that is Sirus
- Agreement evaluation — that is Helios
- Draft accumulation — that is Sky
- Structured field PERSISTENCE — that is Dheghom. Mḗh₁n̥s evaluates and routes. Dheghom stores.
- SCF schema loading — that is Dheghom and Sky
- Block schema loading — that is Sky

**Hard rules:**

- QUARANTINE is a SUCCESS STATE. Never treat as a bug to fix by adjusting code.
- DENY has been deliberately removed. Use QUARANTINE. Data is never dropped.
- Mḗh₁n̥s MUST NEVER run Policy Pack evaluation on draft records.
- Mḗh₁n̥s MUST NEVER persist data directly — it routes to Dheghom.
- CryptographicAuditLedger is append-only. No UPDATE. No DELETE. Ever. By anyone.
- AI pipeline query constraint is fixed and inviolable (see ref-03).

---

## Dheghom DVE Core

**IS:**

- ModuleRegistry — single source of truth for all schema field definitions
- 57 module field groups (Entity + Mapper + Repository per module)
- AbstractModuleMapper — hydration, validation, persistence. Never caches.
- AbstractRepository — caching, partial hydration, CRUD coordination
- TokenValidator — three-gate GovernanceToken verification
- ValidationEngine — strict field validation using ValidationHelper
- EncryptionEngine — AES-256 envelope encryption for draft payloads
- saveQuarantined() — preserves payload to sealed_archive, forced draft
- commitFromRelease() — token-validated save with asset field fidelity
- Triple Binding enforcement (session_id + identity_id + device_id)
- Schema Engine — generates APIs, UI, docs from registry (NEVER DAL logic)
- SCF schema loading only

**IS NOT:**

- Identity resolution — use HeliosClientInterface
- Governance policy evaluation — that is Mḗh₁n̥s
- Draft accumulation — that is Sky (wp_spx_sessions)
- Context production — that is Sirus
- Agreement evaluation — that is Helios

**Hard rules:**

- EVERY write to a governed field (aiwa*, sparxstar*) requires valid GovernanceToken verified by TokenValidator at all three gates
- TRIPLE BINDING before every governed write — session_id + identity_id + device_id must all align. If any signal missing or mismatched: throw TripleBindingException
- ASSET IMMUTABILITY is absolute. Asset fields never overwritten after first write. Never normalized, cleaned, or improved by automation. Modifications produce new fields with \_derived, \_cleaned, \_translated suffix.
- ALL draft payloads MUST be AES-256 encrypted before any DB write. Plaintext draft write = throw DraftEncryptionException, block save.
- NO caching in mappers. Caching belongs in repositories only.
- NO field mapping or validation logic in repositories.
- NO direct $wpdb->query() or $wpdb->update() on governed fields. All writes go through WordPress meta API via Repository layer.
- required() uses `$value === null || $value === ""` — NEVER empty(). empty() treats "0" as empty which is wrong.
- validate_base64_signature() uses `base64_decode($value, true) === false`. Never use base64_encode(base64_decode()) comparison.
- UUID validation checks BOTH structure AND version (1-5).
- Schema Engine NEVER generates entity or mapper classes. Entities and mappers are always explicit, hand-authored classes.

**The only valid write paths to governed fields:**

```
REST API → Helios → Repository → DatabaseWriteInterceptor → WP meta API
Release Gate → TokenValidator → commitFromRelease() → WP meta API
CLI (SYSTEM) → Repository under SYSTEM context → WP meta API
```

**Modular composition pattern:**

- Entity — typed, immutable data container (DTO). No logic; data only.
- Mapper — translation between storage and entity. Handles hydration, validation, persistence. Never caches.
- Repository — orchestration, caching, and CRUD. Never contains field mapping or validation logic.
- Registry — single authoritative source of schema field definitions. Not used for runtime hydration.
  No class crosses these boundaries. No module is ever merged with another.

---

## Event Horizon

**IS:**

- Nginx http-context map library
- Threat intelligence computation — maps, zones, aggregation, worker trust, risk scoring
- Outputs: $spx*final_decision (binary 0|1) and X-SPX-* / X-SPARXSTAR-\_ headers

**IS NOT:**

- Security response headers (HSTS, CSP, X-Frame-Options, Permissions-Policy) — those are system-core
- Location blocks — operator decisions
- The firewall gate check itself (`if ($spx_final_decision) { return 444; }`) — placement instruction, not a shipped snippet
- Static asset bypass configuration — operator decides
- An nginx.conf or sites-available configuration

**Boundary rule:**
If a file contains proxy_pass, return, add_header, a location block, or a limit_req enforcement directive, it is operator runtime code and does NOT belong in Event Horizon. Event Horizon ships maps and proxy headers. The operator ships everything else.

**Geo rule:**
Geo codes act as risk amplifiers only — they never trigger a block without another concurrent threat signal.

---

## Shine (Social Publishing Engine)

**IS:**

- WordPress plugin (spe_social custom post type) + Node.js engine
- Token Vault (Node.js + PostgreSQL) — encrypted OAuth token storage
- Dispatcher Engine (Node.js + BullMQ + Redis) — queue management, SDK execution, retry logic
- Stats Layer (Node.js + PostgreSQL) — metrics ingestion, engagement data, reply queue

**IS NOT:**

- Connected to DVE pipeline at runtime
- A governance system
- A cultural data handler

**Hard rules:**

- WordPress database NEVER holds OAuth tokens — only post content, schedule times, platform targets
- Token Vault never exposes plaintext tokens via any external API
- Vault and Dispatcher communicate over private network with mutual TLS — Vault API not exposed to public internet
- Envelope encryption for all OAuth tokens (DEK + KEK pattern)
- HMAC-SHA256 webhook signature verification — reject with 401 on failure, no body, no hint
- No PII beyond wp_user_id (integer) — no email, no display names, no IP addresses stored in Vault
- Revoked records retained for audit — never deleted, token ciphertext zeroed
- Redis holds job state only — NO token material ever in Redis
- MCP boundary: Shine MUST NOT call Mḗh₁n̥s, MUST NOT write to Dheghom, MUST NOT mint governance tokens

---

## 3iAtlas RLC

**IS:**

- Standalone classroom game — React + Node
- Consumes DVE exports (dictionary export — read-only snapshot)
- Mobile-first: 360px viewport, low-end Android, variable connectivity

**IS NOT:**

- A DVE component
- A WordPress plugin
- Connected to DVE runtime pipeline
- A dictionary (it consumes the dictionary export, it does not write to it)

**Dependency rule:** DVE → export → 3iAtlas dictionary → RLC. No reverse flow. No live DVE connection at runtime.

Do NOT apply WordPress coding standards to 3iAtlas RLC code. It is intentionally outside that stack. The 3iAtlas RLC v2.0 explicitly supersedes the AIWA RWC/RSC v1.0 WordPress plugin architecture.

---

## Schema Loading Map

| Component                              | Loads                        |
| -------------------------------------- | ---------------------------- |
| Dheghom DVE Core                       | SCF schema only              |
| Sky DVE Core (Abilities)               | SCF schema + Block schema    |
| Mḗh₁n̥s DVE Core                        | Policy schema only           |
| sparxstar-digital-village-elder-schema | Reference only — not runtime |
