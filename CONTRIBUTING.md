# Contributing

## Changelog entries

Each package maintains its own `CHANGELOG.md` in Keep-a-Changelog format. To avoid merge conflicts when multiple PRs touch the same package, entries are added as fragment files in `packages/<name>/changelog/` and aggregated into a release block by [`automattic/jetpack-changelogger`](https://packagist.org/packages/automattic/jetpack-changelogger).

For a PR touching a specific package, use the matching script:

```bash
composer changelog:add:bootstrap     # wp-framework-bootstrap
composer changelog:add:core          # wp-framework-core
composer changelog:add:infrastructure # wp-framework-infrastructure
composer changelog:add:shared        # wp-framework-shared
composer changelog:add:woocommerce   # wp-framework-woocommerce
```

The interactive prompt asks for `Significance` (patch/minor/major) and `Type` (added/changed/deprecated/removed/fixed/security). Commit the fragment file with the rest of the PR. CI validates every fragment via `composer changelog:validate`.

## Releases

Per package:

```bash
composer changelog:write:bootstrap   # or :core / :infrastructure / :shared / :woocommerce
```

Aggregates `packages/<name>/changelog/*` → new version block in `packages/<name>/CHANGELOG.md`, computes the next semver from fragment significance levels, deletes the fragments. Commit the diff. The split-packages workflow propagates the package (with its updated CHANGELOG.md) into the per-package consumer-facing repo on `push` to `trunk`.

The seed `## 2.0.0 - unreleased` block in each package's CHANGELOG.md is jetpack-changelogger's native unreleased-date placeholder. New release blocks get inserted above it.
