# SPARXSTAR Canonical Types

## Reference 02 — PAM-002 Canonical DTOs, Enums, Interfaces, Signing Material, TTL Tiers

**Authority:** PAM-002 (normative, supersedes PAM-001 entirely)
**Status:** PAM-001 is WITHDRAWN. Any code implementing PAM-001 decisions that PAM-002 reversed is a violation.

---

## Document Hierarchy for Types

PAM-002 was written backward from the most architecturally complete specs (Sky Eshu v1.1, Mḗh₁n̥s v3.1). It is the single authoritative record for all cross-repository structural decisions.

**Primary implementation repo:** `sparxstar-ouroboros-integrity`

All shared types live in Ouroboros. No other repo may redefine them locally. Violation = FAIL.

---

## Canonical ContextPulse — 15 Fields

```php
final class ContextPulse
{
    public readonly string $pulse_id;              // UUID v4
    public readonly string $context_id;            // Sirus context this pulse was issued for
    public readonly string $device_id;             // Server-issued device identifier
    public readonly string $session_id;            // Active session identifier
    public readonly string $site_id;               // WordPress site ID
    public readonly string $network_id;            // WordPress network ID (multisite)
    public readonly float  $trust_score;           // [0.0, 1.0] — never stored, always fresh
    public readonly string $trust_level;           // 'NORMAL' | 'STEP_UP_REQUIRED' | 'LOCKED'
    public readonly array  $behavior_flags;        // string[] — threat posture signals from Sirus
    public readonly string $geo_zone;              // Geographic trust zone identifier
    public readonly string $network_effective_type;// '4g'|'wifi'|'3g'|'2g'|'slow-2g'|'cli'
    public readonly int    $session_duration;      // Seconds since session established
    public readonly int    $issued_at;             // Unix timestamp
    public readonly int    $expires;               // Absolute Unix timestamp (NOT duration)
    public readonly string $sig;                   // HMAC-SHA256 hex signature
}
```

**PAM-001 reversals — these fields were EXCLUDED in PAM-001 and are INCLUDED in PAM-002:**

- `behavior_flags` — INCLUDED (required for Helios Group trust evaluation)
- `geo_zone` — INCLUDED (required for hyper-localized Group authority enforcement)
- `network_effective_type` — INCLUDED (required for GovernanceToken TTL resolution)
- `session_duration` — INCLUDED (session stability signal for trust computation)

**Always excluded:**

- `user_id` — identity never travels in a pulse (Rule 9.3 — replay attack surface)
- `ttl` — use `expires` (absolute timestamp). Duration creates clock-skew ambiguity.

**Field naming:**

- `sig` (not `signature`) — canonical name
- `expires` (not `expires_at`) — canonical name

**ContextPulse transport:**

- HttpOnly, SameSite=Strict cookie — never header, never localStorage, never URL parameter
- Size limit: under 1 KB including signature. PROHIBITED: adding fields without removing others.
- JavaScript access: strictly forbidden

**Canonical HMAC Signing Material (pipe-delimited, exact field order):**

```
pulse_id|context_id|device_id|session_id|site_id|network_id
|trust_score_4dp|trust_level|behavior_flags_json|geo_zone
|network_effective_type|session_duration|issued_at|expires
```

- `trust_score` serialized with `number_format(x, 4, '.', '')` for cross-language reproducibility
- `behavior_flags` serialized as JSON array with sorted keys
- Algorithm: HMAC-SHA256
- Key: `SIRUS_PULSE_SIGNING_KEY` (minimum 32 bytes)

---

## Canonical AgreementResult — LOWERCASE

```php
enum AgreementResult: string
{
    case ALLOW_EDGE  = 'allow_edge';   // NOT 'ALLOW_EDGE'
    case ALLOW_ORIGIN = 'allow_origin';
    case STEP_UP     = 'step_up';
    case DENY        = 'deny';
    case PROVISIONAL = 'provisional';
}
```

**Wire values are lowercase.** This was incorrect in PAM-001 Decision 4 (uppercase), correctly reversed in PAM-001 Part V C1.1, and confirmed in PAM-002. Any uppercase values are a violation.

---

## Canonical ResourceSensitivity — INTEGER backing

```php
enum ResourceSensitivity: int
{
    case LEVEL_1 = 1; // Public / General — edge caching permitted
    case LEVEL_2 = 2; // Creator / Financial — origin-only, proof required
    case LEVEL_3 = 3; // Heritage / Sovereign — hardware key proof required
}
```

**Cross-language wire format:** lowercase string labels (`'level_1'`, `'level_2'`, `'level_3'`) via `ResourceSensitivity::label()` and `ResourceSensitivity::fromLabel()`.

**NEVER serialize `->value` directly to the wire.** PHP-internal and database representation is integer. Wire format is lowercase string label.

---

## Canonical ZonePrimitive

```php
enum ZonePrimitive: string
{
    case EDGE   = 'edge';   // Cloudflare edge. ALLOW_EDGE for Level 1 only.
    case ORIGIN = 'origin'; // WordPress origin. Issues the final authoritative YES.
}
```

Wire values are lowercase. `EDGE->value === 'edge'`. `ORIGIN->value === 'origin'`.

Downstream consumers resolve raw string from request to `ZonePrimitive::from($zoneRaw)` before passing to `evaluate()`. Invalid string returns DENY (via 400 response in Agreement_Controller).

**Namespace:** `Starisian\Sparxstar\Infrastructure\DTOs\ZonePrimitive`

This replaced `string $zone` from PAM-001 Decision 6. The PAM-001 spec specified `string $zone // 'edge' | 'origin'` because ZonePrimitive did not exist at that time. ZonePrimitive now owns this at the Ouroboros contract boundary.

---

## Canonical HeliosClientInterface

```php
interface HeliosClientInterface
{
    public function evaluate(
        mixed $proof,                    // Governance proof. Pass null if not applicable.
        ?ContextPulse $pulse,            // Current device context and trust signal.
        ResourceSensitivity $sensitivity,// Sensitivity tier of the resource.
        int $now,                        // Current Unix timestamp (caller-supplied).
        ZonePrimitive $zone,             // Originating zone: EDGE or ORIGIN.
    ): AgreementResult;

    public function verifyDevice(string $deviceId): bool;
    public function getTrustState(string $deviceId): string;
    public function validateSession(string $sessionId, string $deviceId): bool;
}
```

**Key notes:**

- `$proof` is `mixed` — pass `null` unless implementing hardware key proof for Level 3
- `$now` is caller-supplied for testability
- `$zone` is `ZonePrimitive`, NOT string — callers must resolve raw string before calling
- `$proof` was removed in PAM-001, then added back as `mixed` in CO-001

All integration points (Sky, Mḗh₁n̥s, Dheghom) must depend ONLY on this interface. No direct database access, no bypasses.

---

## Canonical GovernanceToken

```php
final class GovernanceToken
{
    public readonly string $token_id;        // UUID v4
    public readonly string $transaction_id;  // UUID v4 — matches ExecutionContext
    public readonly int    $issued_at;       // Unix timestamp
    public readonly int    $expires_at;      // Unix timestamp
    public readonly string $authority_id;    // Issuing authority
    public readonly string $identity_id;     // Subject identity
    public readonly string $ability_id;      // Permission being granted
    public readonly string $status;          // 'active' | 'revoked'
    public readonly array  $packs_enforced;  // string[] — ordered pack IDs
    public readonly array  $routing_flags;   // Opaque routing metadata
                                             // routing_flags['session_id']
                                             // routing_flags['device_id']
    public readonly string $payload_hash;    // SHA-256 hex of governed payload
    public readonly string $signature;       // HMAC-SHA256 over canonical material
}
```

**Critical:** `session_id` and `device_id` are in `routing_flags`, NOT as top-level fields.
This was an architectural decision made during CO-001. PAM-001 incorrectly specified them as top-level fields. PAM-002 confirms routing_flags placement.

**packs_enforced** is `string[]` (ordered list of pack IDs), NOT bool. PAM-001 Ouroboros provisional used `bool packs_enforced = false` — this was incorrect.

**Canonical Signing Material** (produced by `GovernanceTokenSigningMaterial::build()`):

```
v=v1
token_id={token_id}
identity_id={identity_id}
session_id={session_id}        ← from routing_flags['session_id']
device_id={device_id}          ← from routing_flags['device_id']
authority_id={authority_id}
transaction_id={transaction_id}
ability_id={ability_id}
status={status}
packs_enforced={json_sorted_array}
payload_hash={payload_hash}
expires_at={unix_integer}
issued_at={unix_integer}
```

**Three immutable signing rules — never change without PAM-003 amendment:**

1. Timestamps are Unix integers serialized as plain decimal strings. NOT ISO 8601.
2. session_id and device_id are read from routing_flags, not from token fields.
3. content_id is intentionally excluded from the signing payload.

**GovernanceTokenSigningMaterial ownership:** Ouroboros `src/Utils/`. Both Mḗh₁n̥s and Dheghom import from Ouroboros. Neither may maintain a local copy.

Method name: `GovernanceTokenSigningMaterial::build()` — NOT `canonicalize()` (that was PAM-001).

---

## GovernanceToken TTL Tiers

```php
// In Platform.php (Ouroboros)
GOVERNANCE_TOKEN_TTL_MIN_SECONDS      = 60;   // Absolute floor — replay attack boundary
GOVERNANCE_TOKEN_TTL_HIGH_CONNECTIVITY = 120;  // Broadband / stable WiFi
GOVERNANCE_TOKEN_TTL_STANDARD          = 300;  // Standard mobile / urban 3G (default)
GOVERNANCE_TOKEN_TTL_LOW_CONNECTIVITY  = 600;  // 2G / rural / degraded link
GOVERNANCE_TOKEN_TTL_CLI               = 3600; // WP-CLI / SYSTEM context
```

**TTL Resolution** (in `ReleaseGateService::resolveTtlSeconds(ContextPulse $pulse)`):

```php
return match ($pulse->network_effective_type) {
    '4g', 'wifi'       => Platform::GOVERNANCE_TOKEN_TTL_HIGH_CONNECTIVITY,
    '3g'               => Platform::GOVERNANCE_TOKEN_TTL_STANDARD,
    '2g', 'slow-2g'    => Platform::GOVERNANCE_TOKEN_TTL_LOW_CONNECTIVITY,
    'cli', 'system'    => Platform::GOVERNANCE_TOKEN_TTL_CLI,
    default            => Platform::GOVERNANCE_TOKEN_TTL_STANDARD,
};
```

**Hard floor:** GovernanceTokenMinter MUST throw `InvalidArgumentException` if `$ttlSeconds < 60`. No exception. No administrative override.

---

## Three Trust Primitives

| Primitive | Scope                                                | Geo-Zone Model                                                                    |
| --------- | ---------------------------------------------------- | --------------------------------------------------------------------------------- |
| Personal  | Device-to-device capability grants                   | Not geography-based. Travels with device.                                         |
| Group     | Institutional perimeter with geographic trust radius | The Agua Caliente model — hyper-localized. IP geo-zone + behavior profile.        |
| Brain     | Content access control via keys and entitlements     | Global by design. Geography does not restrict Brain access — entitlement keys do. |

**Group trust enforcement:**

- Recognized geo_zone + known device + clean behavior_flags → ALLOW_ORIGIN
- Unrecognized geo_zone + known device → STEP_UP
- Any geo_zone + attack behavioral pattern in behavior_flags → DENY (posture overrides zone)
- Recognized geo_zone + LOCKED trust_level → DENY (state overrides zone)

**Geo-zone rule:** Country code alone is NEVER sufficient to trigger a geo-block. Composite signals (country + behavior) are required.

---

## behavior_flags Vocabulary

Produced by Sirus TrustEngine and BehaviorAnalyzer. Values are lowercase snake_case.

| Flag                   | Meaning                                                    |
| ---------------------- | ---------------------------------------------------------- |
| credential_stuffing    | Request pattern matches credential stuffing attack profile |
| rapid_device_shift     | Multiple new device fingerprints in short duration         |
| geo_impossibility      | Geographic jump that cannot be explained by travel time    |
| bot_signature          | User-agent and request pattern match known bot profiles    |
| asm_jump               | ASN/country jump within a window that exceeds tolerance    |
| repeated_failures      | Multiple consecutive agreement failures from this device   |
| session_hijack_pattern | Behavior consistent with stolen session exploitation       |

Empty `behavior_flags` = no threat signals. Helios and Mḗh₁n̥s must NEVER assume behavior_flags is empty — always check.

---

## Namespaces

| Component     | Namespace                                               |
| ------------- | ------------------------------------------------------- |
| Ouroboros     | `Starisian\Sparxstar\Infrastructure\`                   |
| Sirus         | `Starisian\Sparxstar\Sirus\`                            |
| Mḗh₁n̥s        | `Starisian\Sparxstar\Mehns\`                            |
| Dheghom       | `Starisian\Sparxstar\Dheghom\`                          |
| ZonePrimitive | `Starisian\Sparxstar\Infrastructure\DTOs\ZonePrimitive` |

---

## Migration Status (as of PAM-002, May 2026)

| Repo                  | Status            |
| --------------------- | ----------------- |
| Ouroboros CO-001      | ✅ Merged         |
| Helios Phase 3 PR #18 | ✅ Ready to merge |
| Sirus PR #53          | ⏳ Pending merge  |
| Mḗh₁n̥s                | 🔜 Next           |
| Dheghom               | 🔜 After Mḗh₁n̥s   |
| Sky Eshu              | 🔜 Planned        |

**PAM-002 migration phases still pending:**

- PAM-002-P1: Update Ouroboros ContextPulse with four restored fields. Recompute all HMAC signatures.
- PAM-002-P2: Update Sirus PulseGenerator. Update Helios PulseVerifier.
- PAM-002-P3: Update Helios AgreementEvaluator with Group trust geo-zone + behavior_flags logic.
- PAM-002-P4: Mḗh₁n̥s primary build.
- PAM-002-P5: ArtifactGovernanceDeclaration — Mḗh₁n̥s mints it, Dheghom stores it permanently.
- PAM-002-P9: Personal Policy Token, Group Policy resolution, PolicyResolver (DVE-TRUST-001).

When reviewing code: note which migration phase it relates to and whether it's consistent with the phase's requirements.
