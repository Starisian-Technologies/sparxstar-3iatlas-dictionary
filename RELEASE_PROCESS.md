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
