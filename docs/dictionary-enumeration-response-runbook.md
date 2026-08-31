# Dictionary Enumeration — Response Runbook

**Implements:** Asset Protection Spec §3, *"A documented response runbook: throttle →
revoke the system credential → block source → rotate. Who may pull each lever, and
evidence retention for legal follow-up."*
**Applies to:** the SPARXSTAR Dictionary API in this repository.
**Read with:** [`dictionary-asset-protection-spec.md`](./dictionary-asset-protection-spec.md)
and [`dictionary-asset-protection-adr-brief.md`](./dictionary-asset-protection-adr-brief.md).

---

## 0. What wakes you

Every alert below arrives through the `sparxstar_dict_security_event` action, at severity
`anomaly` or `critical`. Route that action to wherever your team actually reads alerts —
the plugin deliberately does not choose a destination for you.

| Event | Severity | What it means |
|---|---|---|
| `enumeration_signature_detected` | anomaly | A walk appears to be **in progress**. Signature named in `context.signature`. |
| `unique_entry_budget_exhausted` | anomaly | A credential took its entire window budget. The walk already happened. |
| `system_credential_presented_with_browser_origin` | **critical** | `[BREACH_DETECTED]`. A valid system credential arrived with a browser `Origin`. |
| `system_credential_rejected` | anomaly | An unrecognised credential was presented. |
| `retired_credential_presented_after_cutover` | anomaly | Someone is still using `X-Api-Key` or a page token post-cutover. |
| `browser_origin_on_dictionary_route` | anomaly | Browser-origin traffic after the tripwire is armed. |

### The three enumeration signatures

| `context.signature` | Fires when | Why it is suspicious |
|---|---|---|
| `sustained_near_cap` | A credential passes 80% of its distinct-entry ceiling | Real product use plateaus on a working set; it does not creep toward a corpus-sized number. |
| `breadth_first_coverage` | A credential takes ≥25% of its whole window budget within 10 minutes | A consumer serving users re-reads entries, so its *distinct* count grows slowly. A walker's distinct count tracks its request count. |
| `distinct_entries_per_ip` | One source IP touches ≥500 distinct entries in an hour | Catches a walk spread across several credentials from one host, which per-credential accounting alone would miss. |

**Not yet detected:** sequential/near-sequential *page-token* walks. That signature needs
cursor lineage, which arrives with the opaque signed page tokens of §9 step 4. It is a
known gap, not an oversight — see the detector's class docblock.

---

## 1. Triage — the first five minutes

Answer these in order. Most alerts stop at question 2.

**1. Is it a known consumer doing something new?**
Read `context.credential_id`. Cross-check the approved-systems list (ADR D-4). A new
integration going live, a backfill, or a payload rebuild all look like a walk and all
have an owner who can confirm within minutes. **Ask before you revoke.**

**2. Is the ceiling wrong rather than the caller?**
`sustained_near_cap` on a system doing legitimate work means D-5 sized its budget too
tightly. The fix is an ADR amendment raising that credential's ceiling, not an incident.
A ceiling that fires on normal use trains everyone to ignore it.

**3. Did it arrive with a browser `Origin`?**
If the same window also holds `system_credential_presented_with_browser_origin`, stop
triaging and go to §2 — that credential is burned, and enumeration is now the second
problem.

**4. Is it one credential or one source?**
`distinct_entries_per_ip` without a matching per-credential signature means several
credentials from one host. That is either shared infrastructure (plausible: two of our
own systems in one cluster) or one actor holding more than one credential. Establish
which before acting.

---

## 2. The ladder

Climb only as far as the evidence justifies, and record each rung.

### Rung 1 — Throttle
**Effect:** the caller slows down; nothing breaks.
**How:** lower that credential's ceiling via the `sparxstar_dict_budget_ceiling` filter,
or its stored `entry_budget`.
**Who:** any on-call engineer, no approval needed.
**Use when:** the signature is real but the caller is probably legitimate.

### Rung 2 — Revoke the credential
**Effect:** that system stops working. Nothing else does — which is the whole reason
§1.2 refuses a single shared token.
```
wp sparxstar-dict system revoke --id=<credential-id>
```
**Who:** on-call engineer may revoke unilaterally when §1 is satisfied (browser Origin
with a valid credential); otherwise the consuming system's owner is told first.
**Verify:** the command fails loudly if the registry did not persist. If it errors, the
credential is **still active** — do not report it revoked.

### Rung 3 — Block the source
**Effect:** blunt, and it hits anything sharing that address.
**How:** Cloudflare/nginx in `system-core`. Not available from this plugin.
**Who:** whoever holds edge access, on request from the on-call engineer.
**Use when:** the source keeps coming after revocation, i.e. it holds another credential.

### Rung 4 — Rotate
**Effect:** issues a replacement secret; the old one dies immediately.
```
wp sparxstar-dict system rotate --id=<credential-id>
```
**Who:** on-call engineer, coordinated with the consuming system's owner — they must
deploy the new secret or their system stays down.
**Note:** rotation is per-system by design (§1.2), so one system's rotation never forces
an outage on another.

> **On the `[BREACH_DETECTED]` critical indicator specifically**, §1.1 sets the posture:
> treat the credential as burned and **rotate fast, with human confirmation on the first
> occurrence** — automatic rotation on a single event lets one misconfigured consumer
> take the platform down. Automatic on repetition.

---

## 3. What the alert is not

- **An `Origin` header alone is not proof of a leak.** §1.1 is explicit: a preflight
  proves an *attempt*. Any webpage or scanner can cause one while holding no credential.
- **A signature is not a verdict.** All three fire on rates and breadth, both of which a
  legitimate new integration can produce on its first day.
- **Robots.txt and user-agent rules are not controls** (§2). Never cite them when
  justifying that something was or was not blocked.

---

## 4. Evidence retention

§3 requires evidence retention for legal follow-up, and §4 explains why: extraction-time
detection is what the priority-of-authorship package is built around.

**Preserve, before touching anything:**
- The `sparxstar_dict_security_event` records for the whole episode, with timestamps.
- Request-log lines for the credential ID and source across the window — §3 requires the
  log to carry credential **ID**, source IP, route, and page-token lineage.
- The credential's registry record, including `created` and `rotated` timestamps.
- Which entries were served: the budget table rows for that credential, **captured before
  the scheduled purge removes rows older than the rolling window**. This is the only
  record of *which* corpus entries left, and it ages out on the purge schedule. Snapshot
  it first; everything else is reconstructible, this is not.

**Never place in an incident record:** the credential value itself. §3 permits credential
IDs and the truncated non-reversible fingerprint, nothing more. That holds in tickets and
chat exactly as it holds in logs.

**Hand-off:** episodes involving a confirmed corpus walk go to counsel with the §6
archival snapshot manifest for the same period, per §7.

---

## 5. After

1. Record the episode in `INCIDENTS.md`.
2. If a ceiling was wrong, raise the ADR amendment (D-5) rather than leaving a filtered
   override in place — a filter nobody remembers is a control nobody has.
3. If a signature fired on a legitimate pattern more than once, tune the threshold in
   `Sparxstar3IAtlasDictionaryEnumerationDetector` and say so here. An alarm people learn
   to dismiss is worse than no alarm.
4. If the walk succeeded, §4 applies: the shingle index and the archival snapshots are
   what make a recovered copy provable.
