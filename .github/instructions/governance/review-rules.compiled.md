# Coding agent review rules — Auto-synced from registry@97d10c7
# DO NOT EDIT
# Coding agent review and merge rules

Owner instructions, updated 2026-11-25. These rules supersede older instructions asking
for human approval and native Codex review requests. The ordered fallback below
supersedes the earlier blanket prohibition on Copilot.

Reviewers are Amazon Q (automatic) plus the central review service. Automatic
central review tries Anthropic first, then OpenAI when Anthropic is unavailable,
errors or times out, then requests native Copilot only if neither API works.
Do not independently request Copilot or use native `@codex review`.

A repository is migrated only if `AGENTS.md` has the central-review pointer and
the request-review relay workflow is on its default branch. Otherwise rely on
Amazon Q until migration. A legacy Claude missing-key failure is known and is
not a merge blocker under this owner ruling; unrelated CI failures are not
waived. Never add AI provider keys to consumer repositories.

## Dependabot updates (ADR-058)

- Dependabot PRs merge without waiting for CI; CI then runs once on the
  default branch. Resolve any open review thread first.
- If the default branch goes red, fix forward or revert the offending update
  immediately. Nothing else merges ahead of that fix.
- Never cut a release (including merging a release PR) or deploy from a red
  default branch.
- Exception: a repository that deploys automatically on merge (WordPad, and
  any other repository whose merge triggers a deploy) merges Dependabot PRs
  only on green CI.
- Every repository uses the shared `.github/dependabot.yml` template from the
  reusable-CI repository (`caller-templates/dependabot.yml`).

## Other pull requests

Open PRs as drafts. Complete the work and get CI green before marking ready for
review. Draft-to-ready triggers the ordered central fallback; do not request reviews on
drafts or unfinished work.

- Routine changes use one successful automatic fallback-chain review.
- Additional central Codex review is available for runtime behavior changes.
- Reserve explicit `/review all` for high-risk auth/2FA, payments/billing,
  credentials, migrations, licence changes or data deletion; it is not automatic.

Use standalone `/review codex` or `/review all` only for the applicable tier if
needed. Post once, never on every push. One successful review per provider/PR
is enough. Allow one re-review only when behavior changes after review; typo and
test fixes do not require another paid review. The service caps paid attempts at
two per provider/PR and enforces configured daily per-provider budgets.

An API review counts only when an App-authored Central review comment names the
provider, model and current head commit. Copilot delegation counts only when an
actual native review is submitted at the current head; a pending request is not
a review. GitHub does not expose a selectable model or accept the central full
prompt in its review-request API; do not claim identical pinned-spec coverage
or invent model attribution for native Copilot. A dispatch, skipped job, daily cap or billing error is not a
review. Never claim one happened or describe an older-head review as current.

If no result appears within two hours, inspect the run, fix the request error
and re-request once. If it still fails, or a provider is out of budget, state in
the PR which review did not happen and why. Then merge on green once real
findings are fixed and review threads resolved. No human approval is required.
Do not use the unavailability rule to ignore real findings or other CI failures.

The central service reads declared specs, referenced ADRs/invariants, contracts
and base-commit agent rules at pinned registry tags; do not train a separate
model or substitute stale guidance for current governing documents.

Canonical implementation/setup guide:
[Central review guide](https://github.com/Starisian-Technologies/sparxstar-pr-review/blob/main/docs/central-review.md)

Pilot: `sparxstar-woocommerce-stripe-billing`. Migrate other private repositories
one at a time, disabling each legacy reviewer only after its first successful
central review. Until that evidence exists, call a rollout a pilot, not complete.

Execution secrets are repository-level `ANTHROPIC_API_KEY` and
`CHATGPT_API_SECRET_KEY_GITHUB_ACTIONS` in the private execution repo. Do not
guess aliases or report missing secrets when listing is forbidden. Both API
providers use the same resolved SPARXSTAR context and three-pass review prompt.

Source enrollment follows the existing contract-sync App installation's private
Starisian repository access. No manual repository-name variable is required.
The service verifies authenticated installation membership and scopes tokens to
the requested source. Owner/requester permission, private execution, head checks
and budget limits still apply. Installation membership does not supply missing
App credentials: consumers need the existing contract-sync client ID/private key
for a relay; provider API keys remain only in private central execution.
