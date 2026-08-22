# Release Process

Releases are prepared automatically and built automatically. There is no
manual version bump and no manual changelog edit.

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

**Never tag `main` directly.** Releases must go through the release-please
PR so the version bump actually lands in the repo — tagging manually will
fail step 4's version-consistency check.

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

To release a specific version regardless of what the Conventional Commit types
compute, put a `Release-As:` footer in the commit that lands on `main`:

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
