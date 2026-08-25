# Changelog

All notable changes to this project will be documented here.

## [2.10.1](https://github.com/Starisian-Technologies/sparxstar-3iatlas-dictionary/compare/v2.10.0...v2.10.1) (2026-08-25)


### Bug Fixes

* **ci:** propose spec to the canonical registry path, not a flat orphan path ([#147](https://github.com/Starisian-Technologies/sparxstar-3iatlas-dictionary/issues/147)) ([d934a45](https://github.com/Starisian-Technologies/sparxstar-3iatlas-dictionary/commit/d934a453fd23e7e9e92866ba31451538769f2bbf))

## [2.10.0](https://github.com/Starisian-Technologies/sparxstar-3iatlas-dictionary/compare/v2.9.3...v2.10.0) (2026-08-24)


### Features

* support manual releases as a first-class path ([#145](https://github.com/Starisian-Technologies/sparxstar-3iatlas-dictionary/issues/145)) ([b080cc3](https://github.com/Starisian-Technologies/sparxstar-3iatlas-dictionary/commit/b080cc3c648e9c079198330135d7e58d6e7841d5))

## [2.9.3](https://github.com/Starisian-Technologies/sparxstar-3iatlas-dictionary/compare/v2.9.2...v2.9.3) (2026-08-22)


### Bug Fixes

* repair TypeError on WAV serve and redundant WP_Term isset ([#121](https://github.com/Starisian-Technologies/sparxstar-3iatlas-dictionary/issues/121)) ([3e5bdf2](https://github.com/Starisian-Technologies/sparxstar-3iatlas-dictionary/commit/3e5bdf26180ee3984ff36cacf2071f4a090c7348))

## [2.9.2](https://github.com/Starisian-Technologies/sparxstar-3iatlas-dictionary/compare/v2.9.1...v2.9.2) (2026-08-22)


### Bug Fixes

* correct ScopeIndent errors blocking the release gate ([#120](https://github.com/Starisian-Technologies/sparxstar-3iatlas-dictionary/issues/120)) ([9ae1145](https://github.com/Starisian-Technologies/sparxstar-3iatlas-dictionary/commit/9ae11450ad048f2d4b6d88da9abe0b15a5457072))
* gate the release on PHPCS errors; let release-please be re-run without a commit ([#118](https://github.com/Starisian-Technologies/sparxstar-3iatlas-dictionary/issues/118)) ([54521cc](https://github.com/Starisian-Technologies/sparxstar-3iatlas-dictionary/commit/54521ccdbfb4f89d970b319f549776bec61d9bbf))

## [2.9.1](https://github.com/Starisian-Technologies/sparxstar-3iatlas-dictionary/compare/v2.9.0...v2.9.1) (2026-08-22)


### Bug Fixes

* stop the release gate failing on PHPCS warnings ([#116](https://github.com/Starisian-Technologies/sparxstar-3iatlas-dictionary/issues/116)) ([82874ae](https://github.com/Starisian-Technologies/sparxstar-3iatlas-dictionary/commit/82874aeb547b7f7699dc011ae09f06f4cde44835))

## [2.9.0](https://github.com/Starisian-Technologies/sparxstar-3iatlas-dictionary/compare/v2.8.12...v2.9.0) (2026-08-21)


### Features

* dictionary game API, Webster auth model, CORS middleware, wave 1–4 fixes ([#68](https://github.com/Starisian-Technologies/sparxstar-3iatlas-dictionary/issues/68)) ([6579dbe](https://github.com/Starisian-Technologies/sparxstar-3iatlas-dictionary/commit/6579dbe54629d535d50366b57419fd66586fd9a2))
* extract game layer + API client, fix lang_source bug, add arch specs ([#67](https://github.com/Starisian-Technologies/sparxstar-3iatlas-dictionary/issues/67)) ([2d8a4af](https://github.com/Starisian-Technologies/sparxstar-3iatlas-dictionary/commit/2d8a4af51bf092e0d3af29915e27fc4a2bcb79fb))
* Phase 2 — React frontend rebuild with AIWA brand design ([#57](https://github.com/Starisian-Technologies/sparxstar-3iatlas-dictionary/issues/57)) ([36e8571](https://github.com/Starisian-Technologies/sparxstar-3iatlas-dictionary/commit/36e8571d4e53128d16334bceca37aa0c0f6327f4))
* Phase 4 — Games / Play tab (all six games, session management, progress sync) ([#59](https://github.com/Starisian-Technologies/sparxstar-3iatlas-dictionary/issues/59)) ([a2dd6b5](https://github.com/Starisian-Technologies/sparxstar-3iatlas-dictionary/commit/a2dd6b52b61cc944678643df5eabebbfc62ad3b6))
* **ui:** switch dictionary UI font to Inter (Google Fonts) ([#83](https://github.com/Starisian-Technologies/sparxstar-3iatlas-dictionary/issues/83)) ([a54ab51](https://github.com/Starisian-Technologies/sparxstar-3iatlas-dictionary/commit/a54ab51bc7852a4a27fa8efa2133ce15644a06c6))


### Bug Fixes

* **dictionary:** UI fixes (truncation, crash, theme, mobile, game exit, affordance) + standalone full-page/iframe delivery ([#85](https://github.com/Starisian-Technologies/sparxstar-3iatlas-dictionary/issues/85)) ([31353f8](https://github.com/Starisian-Technologies/sparxstar-3iatlas-dictionary/commit/31353f8639606da07d6ebd190df8b8baf6cd56bb))
* guard form script localization and normalize direct-access exit ([#60](https://github.com/Starisian-Technologies/sparxstar-3iatlas-dictionary/issues/60)) ([50fcfb7](https://github.com/Starisian-Technologies/sparxstar-3iatlas-dictionary/commit/50fcfb7edf0743b5efdf835705cc08fcfa66c408))
* PCRE "regular expression is too large" in auto-linker with large dictionary ([#45](https://github.com/Starisian-Technologies/sparxstar-3iatlas-dictionary/issues/45)) ([39534a0](https://github.com/Starisian-Technologies/sparxstar-3iatlas-dictionary/commit/39534a095119fe7f3d04726d584522dc6fefa8aa))
* **phase-0:** register aiwa-cpt-dictionary on language/dialect taxonomies, add language selector to submission form ([#53](https://github.com/Starisian-Technologies/sparxstar-3iatlas-dictionary/issues/53)) ([f8e723e](https://github.com/Starisian-Technologies/sparxstar-3iatlas-dictionary/commit/f8e723ef35c50d541bb94dd2f7883c501fc021a3))
* re-sync package-lock.json so `npm ci` works ([#71](https://github.com/Starisian-Technologies/sparxstar-3iatlas-dictionary/issues/71)) ([0914728](https://github.com/Starisian-Technologies/sparxstar-3iatlas-dictionary/commit/091472826c487a1f7e01e31b6fea8f78e57b0e56))
* **release:** add workflow_dispatch escape hatch to release.yml ([#99](https://github.com/Starisian-Technologies/sparxstar-3iatlas-dictionary/issues/99)) ([d16b6ae](https://github.com/Starisian-Technologies/sparxstar-3iatlas-dictionary/commit/d16b6aea49e5c6893374d5d2f43e1e964b930687))
* **release:** automate version bumps/changelog with release-please; fix ZIP and version-drift bugs ([#89](https://github.com/Starisian-Technologies/sparxstar-3iatlas-dictionary/issues/89)) ([7e51a1a](https://github.com/Starisian-Technologies/sparxstar-3iatlas-dictionary/commit/7e51a1ad4d403bf74b8da5ad197097e80532c4f6))
* **release:** clean plugin header markers, switch to expressions updater, use jq for version extraction ([#91](https://github.com/Starisian-Technologies/sparxstar-3iatlas-dictionary/issues/91)) ([594e752](https://github.com/Starisian-Technologies/sparxstar-3iatlas-dictionary/commit/594e7528f415a2466ceb298dc7cc5f2df9601555))
* **release:** let workflow_dispatch rebuild an existing tag by name ([#101](https://github.com/Starisian-Technologies/sparxstar-3iatlas-dictionary/issues/101)) ([4518251](https://github.com/Starisian-Technologies/sparxstar-3iatlas-dictionary/commit/4518251c8a6d1aaa826ec4f87d3ac2005c724470))
* **release:** restore x-release-please-version annotations release-please actually needs ([#98](https://github.com/Starisian-Technologies/sparxstar-3iatlas-dictionary/issues/98)) ([2e9a6d6](https://github.com/Starisian-Technologies/sparxstar-3iatlas-dictionary/commit/2e9a6d6c1291895c761770374b9c3e4890a8a327))
* remove stray backslash from GraphQL endpoint slug ([#94](https://github.com/Starisian-Technologies/sparxstar-3iatlas-dictionary/issues/94)) ([94392ab](https://github.com/Starisian-Technologies/sparxstar-3iatlas-dictionary/commit/94392abe685b7e5b01a1ddeb5ae9a0d7c14b837b))
* root width, #N/A rendering, stale word-count placeholder ([#70](https://github.com/Starisian-Technologies/sparxstar-3iatlas-dictionary/issues/70)) ([01a29e9](https://github.com/Starisian-Technologies/sparxstar-3iatlas-dictionary/commit/01a29e98b5f79614a2eb716e2f1fbd16316a2755))
* **spell:** corpus-wide validity, ranking-only lang_source, source-language metadata ([#102](https://github.com/Starisian-Technologies/sparxstar-3iatlas-dictionary/issues/102)) ([b6e7d77](https://github.com/Starisian-Technologies/sparxstar-3iatlas-dictionary/commit/b6e7d77706bd5d86154b09b7245e48e139a1763d))
* unbreak release pipeline — drop stray gitlink, retire Node 20 actions ([#112](https://github.com/Starisian-Technologies/sparxstar-3iatlas-dictionary/issues/112)) ([ab29a2f](https://github.com/Starisian-Technologies/sparxstar-3iatlas-dictionary/commit/ab29a2f01ccccb15ff1c0c088382c5dee4f29928))

## [2.8.12](https://github.com/Starisian-Technologies/sparxstar-3iatlas-dictionary/compare/v2.8.11...v2.8.12) (2026-07-24)


### Bug Fixes

* **spell:** corpus-wide validity, ranking-only lang_source, source-language metadata ([#102](https://github.com/Starisian-Technologies/sparxstar-3iatlas-dictionary/issues/102)) ([b6e7d77](https://github.com/Starisian-Technologies/sparxstar-3iatlas-dictionary/commit/b6e7d77706bd5d86154b09b7245e48e139a1763d))

## [2.8.11](https://github.com/Starisian-Technologies/sparxstar-3iatlas-dictionary/compare/v2.8.10...v2.8.11) (2026-07-03)


### Bug Fixes

* **release:** add workflow_dispatch escape hatch to release.yml ([#99](https://github.com/Starisian-Technologies/sparxstar-3iatlas-dictionary/issues/99)) ([d16b6ae](https://github.com/Starisian-Technologies/sparxstar-3iatlas-dictionary/commit/d16b6aea49e5c6893374d5d2f43e1e964b930687))
* **release:** let workflow_dispatch rebuild an existing tag by name ([#101](https://github.com/Starisian-Technologies/sparxstar-3iatlas-dictionary/issues/101)) ([4518251](https://github.com/Starisian-Technologies/sparxstar-3iatlas-dictionary/commit/4518251c8a6d1aaa826ec4f87d3ac2005c724470))

## [2.8.10](https://github.com/Starisian-Technologies/sparxstar-3iatlas-dictionary/compare/v2.8.9...v2.8.10) (2026-07-03)


### Bug Fixes

* **release:** automate version bumps/changelog with release-please; fix ZIP and version-drift bugs ([#89](https://github.com/Starisian-Technologies/sparxstar-3iatlas-dictionary/issues/89)) ([7e51a1a](https://github.com/Starisian-Technologies/sparxstar-3iatlas-dictionary/commit/7e51a1ad4d403bf74b8da5ad197097e80532c4f6))
* **release:** clean plugin header markers, switch to expressions updater, use jq for version extraction ([#91](https://github.com/Starisian-Technologies/sparxstar-3iatlas-dictionary/issues/91)) ([594e752](https://github.com/Starisian-Technologies/sparxstar-3iatlas-dictionary/commit/594e7528f415a2466ceb298dc7cc5f2df9601555))
* **release:** restore x-release-please-version annotations release-please actually needs ([#98](https://github.com/Starisian-Technologies/sparxstar-3iatlas-dictionary/issues/98)) ([2e9a6d6](https://github.com/Starisian-Technologies/sparxstar-3iatlas-dictionary/commit/2e9a6d6c1291895c761770374b9c3e4890a8a327))
* remove stray backslash from GraphQL endpoint slug ([#94](https://github.com/Starisian-Technologies/sparxstar-3iatlas-dictionary/issues/94)) ([94392ab](https://github.com/Starisian-Technologies/sparxstar-3iatlas-dictionary/commit/94392abe685b7e5b01a1ddeb5ae9a0d7c14b837b))

## [0.7.1](https://github.com/Starisian-Technologies/sparxstar-3iatlas-dictionary/compare/v0.7.0...v0.7.1) (2026-07-02)


### Bug Fixes

* **release:** clean plugin header markers, switch to expressions updater, use jq for version extraction ([#91](https://github.com/Starisian-Technologies/sparxstar-3iatlas-dictionary/issues/91)) ([594e752](https://github.com/Starisian-Technologies/sparxstar-3iatlas-dictionary/commit/594e7528f415a2466ceb298dc7cc5f2df9601555))

## [0.7.0](https://github.com/Starisian-Technologies/sparxstar-3iatlas-dictionary/compare/v0.6.7...v0.7.0) (2026-07-01)


### Features

* dictionary game API, Webster auth model, CORS middleware, wave 1–4 fixes ([#68](https://github.com/Starisian-Technologies/sparxstar-3iatlas-dictionary/issues/68)) ([6579dbe](https://github.com/Starisian-Technologies/sparxstar-3iatlas-dictionary/commit/6579dbe54629d535d50366b57419fd66586fd9a2))
* extract game layer + API client, fix lang_source bug, add arch specs ([#67](https://github.com/Starisian-Technologies/sparxstar-3iatlas-dictionary/issues/67)) ([2d8a4af](https://github.com/Starisian-Technologies/sparxstar-3iatlas-dictionary/commit/2d8a4af51bf092e0d3af29915e27fc4a2bcb79fb))
* Phase 2 — React frontend rebuild with AIWA brand design ([#57](https://github.com/Starisian-Technologies/sparxstar-3iatlas-dictionary/issues/57)) ([36e8571](https://github.com/Starisian-Technologies/sparxstar-3iatlas-dictionary/commit/36e8571d4e53128d16334bceca37aa0c0f6327f4))
* Phase 4 — Games / Play tab (all six games, session management, progress sync) ([#59](https://github.com/Starisian-Technologies/sparxstar-3iatlas-dictionary/issues/59)) ([a2dd6b5](https://github.com/Starisian-Technologies/sparxstar-3iatlas-dictionary/commit/a2dd6b52b61cc944678643df5eabebbfc62ad3b6))
* **ui:** switch dictionary UI font to Inter (Google Fonts) ([#83](https://github.com/Starisian-Technologies/sparxstar-3iatlas-dictionary/issues/83)) ([a54ab51](https://github.com/Starisian-Technologies/sparxstar-3iatlas-dictionary/commit/a54ab51bc7852a4a27fa8efa2133ce15644a06c6))


### Bug Fixes

* **dictionary:** UI fixes (truncation, crash, theme, mobile, game exit, affordance) + standalone full-page/iframe delivery ([#85](https://github.com/Starisian-Technologies/sparxstar-3iatlas-dictionary/issues/85)) ([31353f8](https://github.com/Starisian-Technologies/sparxstar-3iatlas-dictionary/commit/31353f8639606da07d6ebd190df8b8baf6cd56bb))
* guard form script localization and normalize direct-access exit ([#60](https://github.com/Starisian-Technologies/sparxstar-3iatlas-dictionary/issues/60)) ([50fcfb7](https://github.com/Starisian-Technologies/sparxstar-3iatlas-dictionary/commit/50fcfb7edf0743b5efdf835705cc08fcfa66c408))
* PCRE "regular expression is too large" in auto-linker with large dictionary ([#45](https://github.com/Starisian-Technologies/sparxstar-3iatlas-dictionary/issues/45)) ([39534a0](https://github.com/Starisian-Technologies/sparxstar-3iatlas-dictionary/commit/39534a095119fe7f3d04726d584522dc6fefa8aa))
* **phase-0:** register aiwa-cpt-dictionary on language/dialect taxonomies, add language selector to submission form ([#53](https://github.com/Starisian-Technologies/sparxstar-3iatlas-dictionary/issues/53)) ([f8e723e](https://github.com/Starisian-Technologies/sparxstar-3iatlas-dictionary/commit/f8e723ef35c50d541bb94dd2f7883c501fc021a3))
* re-sync package-lock.json so `npm ci` works ([#71](https://github.com/Starisian-Technologies/sparxstar-3iatlas-dictionary/issues/71)) ([0914728](https://github.com/Starisian-Technologies/sparxstar-3iatlas-dictionary/commit/091472826c487a1f7e01e31b6fea8f78e57b0e56))
* **release:** automate version bumps/changelog with release-please; fix ZIP and version-drift bugs ([#89](https://github.com/Starisian-Technologies/sparxstar-3iatlas-dictionary/issues/89)) ([7e51a1a](https://github.com/Starisian-Technologies/sparxstar-3iatlas-dictionary/commit/7e51a1ad4d403bf74b8da5ad197097e80532c4f6))
* root width, #N/A rendering, stale word-count placeholder ([#70](https://github.com/Starisian-Technologies/sparxstar-3iatlas-dictionary/issues/70)) ([01a29e9](https://github.com/Starisian-Technologies/sparxstar-3iatlas-dictionary/commit/01a29e98b5f79614a2eb716e2f1fbd16316a2755))

## [0.1.0] - 2025-01-01

- Initial scaffold.
