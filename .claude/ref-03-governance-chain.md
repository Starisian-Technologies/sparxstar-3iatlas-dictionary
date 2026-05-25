# SPARXSTAR Governance Chain
## Reference 03 — Three-Token Model, Release Gate, QUARANTINE, DecisionStatus, PolicyResolver

**Authority:** DVE Trust Architecture (DVE-TRUST-001), Mḗh₁n̥s v3.1, PAM-002

---

## The Three-Token Model

The platform requires three distinct cryptographic instruments. These are NOT variants of the same object. Conflating them is a design error.

| Token | Minted By | Consumed By | Lifetime |
|---|---|---|---|
| Personal Policy Token | Sky Eshu at intake | Mḗh₁n̥s PolicyResolver | Short — 300s default |
| Group Policy | Community authority (declarative) | Mḗh₁n̥s PolicyResolver + PolicyDispatcher | Standing — until amended |
| Release Receipt (GovernanceToken) | Mḗh₁n̥s GovernanceTokenMinter | Dheghom TokenValidator | 60–3600s, one-time |

**Plus the permanent record:**
- ArtifactGovernanceDeclaration — minted by Mḗh₁n̥s at Release Gate alongside Release Receipt. Stored permanently in Dheghom. Never consumed and discarded. The deed that says who owns what is inside.

---

## Token 1 — Personal Policy Token

**Minted by:** Sky Eshu immediately before handing ExecutionContext to Mḗh₁n̥s.
**Minted when:** After contributor clicks Release — NOT at session start, NOT at draft save.

```php
// Field set
ppt_id              UUID v4
transaction_id      UUID v4 — matches ExecutionContext.transaction_id
identity_id         string — contributor UUID from Helios/Sirus. Never name.
authority_id        string|null — linked community authority. Null for independent creators.
ai_consent          enum: denied | internal_only | commercial
sharing_scope       enum: private | community | public
royalty_preference  string|null
voice_reconstruction_consent  enum: prohibited | community_only | authorized_use
tk_labels_asserted  string[]
issued_at           int — Unix timestamp
expires_at          int — Unix timestamp. Default: issued_at + 300
signature           string — HMAC-SHA256 using SKY_ESHU_SIGNING_KEY
```

**Canonical signing material (newline-delimited key=value, exact order):**

```
v=v1
ppt_id={ppt_id}
transaction_id={transaction_id}
identity_id={identity_id}
authority_id={authority_id}    ← empty string if null
ai_consent={ai_consent}
sharing_scope={sharing_scope}
voice_reconstruction_consent={voice_reconstruction_consent}
tk_labels_asserted={json_sorted_array}
expires_at={unix_integer}
issued_at={unix_integer}
```

**Architectural rule:** Sky Eshu mints. Mḗh₁n̥s evaluates. Dheghom validates the Release Receipt. No component mints a token that it also evaluates or consumes.

**Must NOT carry:** session credentials, device identity, payload content, community authority rules, final governance decision.

**Fallback if absent:** Release Gate refuses. Token is required.

---

## Token 2 — Group Policy

**What it is:** Standing governance declaration of a community authority. NOT a token in the runtime sense — it is structured JSON policy files.

**Persistent and standing.** Does not expire. Amended through constitutional governance process.

**Location:** `/policies/authority/{authority_id}/` directory. Loaded by PolicyRegistry.

**Key rule — Group Policy supersedes Personal Policy Token in the direction of restriction:**
- Community AI opt-out overrides member's AI opt-in
- Individual AI opt-out always stands even if community permits AI
- Resolution: most restrictive of Group and Personal on every dimension

This is sovereignty, not punishment. A contributor submitting sacred knowledge under tribal authority submits under the community's law.

**Why Group Policy is NOT a token:** Making it a token would mean community governance could be bypassed by waiting for expiry. Community law does not expire.

**Fallback if absent (no Group Policy for this authority):** Personal Policy Token governs. Independent creator path.

---

## Token 3 — Release Receipt (GovernanceToken)

See `ref-02-canonical-types.md` for complete field set and signing material.

**Purpose:** Proof that a specific payload cleared the Mḗh₁n̥s Epistemic Sieve at a specific moment and is cleared for vault ingestion.

**Consumed by:** Dheghom TokenValidator at write time. Consumed once. Not reused.

**Expires:** Yes. Deliberately. Replay attack prevention.

**Fallback if absent:** DAL refuses all writes. No bypass.

---

## Token 4 — ArtifactGovernanceDeclaration (Permanent)

**Purpose:** Permanent, artifact-bound declaration of who owns this artifact, under what law it exists, and what rules govern its use — from moment of release until authorized authority explicitly amends them.

**Lifetime:** PERMANENT. Does not expire. Survives the artifact. Travels with it.

**Minted by:** Mḗh₁n̥s at Release Gate alongside Release Receipt.
**Stored by:** Dheghom permanently.
**Never consumed and discarded** — it is a permanent record, not a session credential.

Key fields: artifact_id, authority_id, identity_id, jurisdiction, tk_labels, care_principles, royalty_terms, geographic_scope, permitted_uses, prohibited_uses, ai_training_rights, voice_reconstruction_rights, amendment_authority, declared_at, signature.

"A song recorded by a Cahuilla elder belongs to the Cahuilla people. This document is how that guarantee is enforced in code."

---

## Token Resolution Chain

Complete flow at Release Gate:

1. Contributor clicks Release
2. Sky Eshu mints Personal Policy Token (PPT)
3. Sky Eshu calls Mḗh₁n̥s::dispatch() with ExecutionContext + PPT
4. Mḗh₁n̥s PolicyResolver validates PPT signature
5. Mḗh₁n̥s PolicyResolver validates PPT not expired
6. Mḗh₁n̥s PolicyResolver loads Group Policy for authority_id from SirusContext
7. PolicyResolver produces ResolvedGovernancePosture (most-restrictive-wins on every dimension)
8. ResolvedGovernancePosture appended to ExecutionContext
9. PolicyDispatcher runs all Policy Packs in order
10. If any rule returns QUARANTINE → halt, save to sealed_archive, return decision
11. If all rules PASS or REROUTE → mint GovernanceToken + ArtifactGovernanceDeclaration
12. Hand token-bearing payload to Dheghom DAL
13. Dheghom TokenValidator verifies token at all three gates before writing

**PAM-002-P9** covers implementation of Personal Policy Token, Group Policy resolution, and PolicyResolver. This follows PAM-002-P4 (Mḗh₁n̥s primary build). Sky Eshu cannot call PolicyResolver until Mḗh₁n̥s is built.

---

## The Release Gate — 7-Step Sequence

```
1. Decrypt draft payload from encrypted staging vault
2. Run strict DAL validation — all required: true fields must now be populated
3. Load all applicable Policy Packs via PolicyRegistry (Global → Authority → Ability → Individual)
4. Run PolicyDispatcher across every loaded pack
5. If any rule returns QUARANTINE: lock record, flag for review, return message to user
6. If all rules PASS or REROUTE: mint GovernanceToken, set status = 'publish'
7. Hand clean, token-bearing, fully-authorised payload to Dheghom DAL
```

**Draft vs Release — fundamental separation:**
- Save = store draft (encrypted, invisible to AI pipeline, no validation of required fields)
- Release = governance runs (all required fields checked, Policy Packs evaluated, token minted)

The Sieve does NOT block users from saving. It blocks unverified data from reaching the AI pipeline. A user can always save a Draft.

**AI training pipeline hard rule:**

```sql
SELECT * FROM posts
WHERE post_status = 'publish'
AND meta_key = 'governance_token'
AND meta_value IS NOT NULL
AND token_expiry > NOW()
```

This query is fixed and inviolable. Cannot be bypassed for any reason including development or testing.

---

## DecisionStatus Enum

```php
enum DecisionStatus: string {
    case PASS       = 'pass';       // All rules satisfied — proceed to TokenMinter
    case REROUTE    = 'reroute';    // Satisfied but route to restricted vault
    case MUTATE     = 'mutate';     // Payload field(s) altered, continue pipeline
    case QUARANTINE = 'quarantine'; // Save + lock. NEVER delete. Flag for review.
    // DENY has been deliberately removed from this system.
}
```

**DENY has been deliberately removed.** Data is never dropped. It is always preserved.

**QUARANTINE is a SUCCESS STATE.** It means the system correctly identified data that cannot yet be released and preserved it safely. NEVER treat QUARANTINE as a bug to fix by adjusting code. QUARANTINE is resolved by a human reviewer, not by code changes.

---

## QUARANTINE — Complete Contract

When any rule in any Policy Pack returns QUARANTINE:

**At the Sieve Layer:**
- PolicyDispatcher immediately halts — no further policy rules execute
- Decision logged to CryptographicAuditLedger with full rule-by-rule trace
- No GovernanceToken minted
- Triggering rule's message surfaced to user interface verbatim

**At the Storage Layer:**
- Payload saved unconditionally — QUARANTINE does not prevent storage
- post_status forced to 'draft' regardless of what user requested
- aiwa_qc_status set to value declared in rule's qc_status field
- Record written to target_vault declared in rule (default: sealed_archive)
- Record flagged as locked — no further user edits until authorized reviewer acts

**At the AI Pipeline Layer:**
- QUARANTINEd record permanently invisible to AI training pipeline
- Produces no governance_token, cannot be queried by the training SELECT

**At the Review Layer:**
- Appears in Elder / Admin review queue
- Reviewer can: approve (re-run Release Gate), reject (permanent sealed status), or request correction (unlock for creator amendment)
- Every reviewer action appended to AuditLedger as new chained entry

---

## PolicyDispatcher Implementation Contract

```php
// Inside PolicyDispatcher::dispatch()
foreach ($policies as $policy) {
    $decision = $this->compiler->executePolicy($policy, $context);
    $this->ledger->append($context->transaction_id, $decision);

    if ($decision->status === DecisionStatus::QUARANTINE) {
        $this->dal->saveQuarantined(
            $context->payload,
            $decision->targetVault,
            $decision->qcStatus,
            $decision->message
        );
        return $decision; // Return immediately — no token minted, no further rules run
    }

    if ($decision->status === DecisionStatus::MUTATE) {
        $context = $context->withUpdatedPayload($decision->mutatedPayload);
        // Propagate mutation — Policy B evaluates the MUTATED version
    }
}

// Only reached if all policies PASS or REROUTE
$token = $this->minter->mint($context, $finalDecision);
return $finalDecision->withToken($token);
```

**Mutation propagation rule:** When Policy A mutates the payload, Policy B evaluates the MUTATED version. Re-create ExecutionContext with updated payload between each policy run.

---

## Dheghom TokenValidator — Three Gates

Every write to a governed field MUST pass all three gates:

1. **Signature** (HMAC-SHA256) — was this token minted by Mḗh₁n̥s?
2. **Payload hash** (SHA-256) — does the payload match what was authorized? Prevents swap attacks.
3. **Expiry** — is the token still valid? Prevents replay attacks.

**Triple Binding:** Before every governed write, session_id + identity_id + device_id must all align. If any signal is missing or mismatched: throw `TripleBindingException`.

---

## Draft-State Encryption

When a user saves a Draft, the payload MUST be AES-256 encrypted using a key tied exclusively to their identity_id.

**Envelope encryption model:**
1. Unique Data Encryption Key (DEK) generated per draft record (AES-256)
2. DEK encrypted using creator's identity key (Key Encryption Key / KEK)
3. Encrypted DEK stored alongside ciphertext payload in database
4. Delegating access = encrypting DEK with delegate's identity key

**Plaintext draft write = throw `DraftEncryptionException`, block save. This is not optional.**

---

## CryptographicAuditLedger

- Append-only. No UPDATE. No DELETE. Ever. By anyone.
- SHA-256 chained entries
- Records every governance decision with full rule-by-rule trace
- Every reviewer action appended as new chained entry

---

## Read Interceptor Rule

`MetadataReadInterceptor` returns `NULL` (not `false`) for unauthorized reads of governed fields.

Returning `false` tells WordPress the meta does not exist and triggers a DB lookup.
Returning `null` denies the value without information leakage.

Namespacing (aiwa_, sparxstar_) is classification, not protection. Any plugin can read governed fields via `get_post_meta()`. The interceptor is the defense layer.

---

## PolicyRegistry — 4-Layer Resolution Order

```
Global → Authority → Ability → Individual
```

Policy files live in:
- `/policies/global/`
- `/policies/authority/{authority_id}/`
- `/policies/ability/{ability_id}/`
- `/policies/user/{user_id}/`

Mḗh₁n̥s loads Policy schema only — the SPARXSTAR JSON policy grammar. Mḗh₁n̥s does NOT load SCF schema or Block schema.

---

## ResolvedGovernancePosture

Internal Mḗh₁n̥s working object. NOT a token. Appended to ExecutionContext before PolicyDispatcher runs.

New condition types in JSON policy grammar:
- `resolved_ai_consent_is` — evaluates resolved_posture.ai_consent
- `resolved_sharing_scope_is` — evaluates resolved_posture.sharing_scope
- `resolved_voice_reconstruction_is` — evaluates resolved_posture.voice_reconstruction_consent
- `personal_token_valid` — PersonalPolicyToken present, not expired, signature valid
- `personal_token_expired` — PersonalPolicyToken is absent or expired
