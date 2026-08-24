# Release Process

There are two supported ways to cut a release. Both end in the same place: a
commit whose version markers match the tag, a `vX.Y.Z` tag on that commit, and
`release.yml` building it into a ZIP with checksums attached to the release.

Never edit a version by hand, and never `git tag` `main` yourself. Both paths
below exist so you don't have to.

## Path A — Manual Release (pick the version yourself)

**Actions → "Manual Release" → Run workflow → `version: 2.9.9`**

Use this whenever you want to ship what is on `main` right now, at a version
you choose. It is the right path when release-please has nothing to offer —
which is most of the time, because it only opens a Release PR for `feat:` or
`fix:` commits. A `main` full of dependency bumps, docs and CI work is not
releasable by its rules and will produce no Release PR at all.

The workflow does the steps in the order that keeps them consistent:

1. Rejects the input unless it is clean semver, is higher than the committed
   version, and has no existing tag — released numbers are immutable.
2. Bumps all four version markers together: `package.json`, the plugin header
   `Version:`, `SPARX_3IATLAS_VERSION`, and `.release-please-manifest.json`.
   It re-reads all four afterwards and aborts before committing if any
   disagree.
3. Adds a `CHANGELOG.md` entry linking the compare range.
4. Commits that to `main`, **then** tags the commit it just made.
5. Calls `release.yml` directly to build and publish.

Because the manifest is bumped too, release-please stays correct afterwards —
its next Release PR starts from the version you just shipped.

## Path B — release-please (version computed from commits)

Use this when the work is genuinely `feat:`/`fix:` and you want the version and
changelog derived from the commits.

1. Merge PRs to `main` using [Conventional Commits](https://www.conventionalcommits.org/)
   commit/PR-title format (`fix:`, `feat:`, `feat!:` / `BREAKING CHANGE:`, etc.) —
   `commitlint.config.js` defines the accepted types.
2. `release-please.yml` watches `main` and keeps a standing "Release PR" open,
   auto-generated from those commits. It bumps `package.json`, the plugin
   header (`Version:` and `SPARX_3IATLAS_VERSION`) in
   `sparxstar-3iatlas-dictionary.php`, and writes `CHANGELOG.md` — configured
   in `release-please-config.json` / `.release-please-manifest.json`.
3. When a maintainer merges that Release PR, release-please tags the merge
   commit `vX.Y.Z` and creates a draft GitHub Release.
4. The tag push triggers `release.yml`, which verifies the tagged commit's
   version numbers actually match the tag (they always will if you didn't
   tag manually), builds Composer + pnpm assets, packages the plugin ZIP
   (excluding dev tooling and the vendored `sparxstar-3iatlas-dictionary-games`
   package per `.distignore`), generates checksums, and attaches everything
   to the release.

### Why tagging `main` by hand does not work

`release.yml` builds the **tag's** tree. If you tag `main` before the version
bump is committed, the tagged tree still carries the old version, the
consistency check in step 4 fails, and the release publishes with no ZIP.

This has happened twice: `v2.8.13` and `v2.9.8` were both tagged by hand and
both published empty. Neither could be repaired afterwards — the fix has to be
in the tag's tree, and moving a published tag is not an option. Use Path A
instead; it exists precisely so this stops happening.

## Do not delete a release tag

`vX.Y.Z` tags are release-please's history anchor: it finds the most recent
one to decide what is unreleased and what the next version should be. Deleting
one silently corrupts that. When `v2.8.12` was deleted, release-please fell
back to `v2.8.11`, swept up `feat:` commits that had already shipped, and
proposed a **minor** bump (2.9.0) with a changelog replaying released features
— for a branch containing nothing but a CI fix.

Released version numbers are immutable. If a release is wrong, supersede it
with a new version; do not delete the tag and reuse the number. Someone may
already have installed the plugin at that version, and reusing it means two
different artifacts answer to the same version string.

If a tag is deleted by accident, re-point it at that version's
`chore(main): release X.Y.Z` commit before letting release-please run again.

## `Release-As:` must be the final trailer

Path A is usually the easier way to pin a version. But if you want
release-please itself to cut a specific version — so it writes the changelog —
put a `Release-As:` footer in the commit that lands on `main`:

```
Release-As: 2.8.14
```

Because this repo squash-merges, the commit release-please parses is the
*squashed* message, and GitHub builds that by concatenating every commit on the
branch. A branch with two or more commits therefore buries the footer in the
middle of the body, between prose from the other commits — where release-please
does not read it, so the override is silently ignored and you get the computed
version instead.

Keep the branch to a single commit, with `Release-As:` as its last line.
