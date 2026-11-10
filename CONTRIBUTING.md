# Contributing

Each package keeps to the dependency edges that [AGENTS.md](AGENTS.md) maps, because a plugin ships only the closure of the packages it requires. A pull request that adds an edge explains why the package needs it.

## Before opening a pull request

Install the dependencies on the PHP, Node and npm versions the README lists, then run the local equivalents of the CI checks:

```sh
composer packages-install
npm install
composer quality-check
composer test:integration:multisite
composer test:integration:php74
npm run lint:scripts
npm run test:e2e
composer test:integration:lowest
git checkout tests/Fixtures/consumer-a/composer.json tests/Fixtures/consumer-a/composer.lock
npm run audit
actionlint
zizmor .github/
```

## Changelog entries

Each user-visible change adds one fragment, so pull requests never conflict on `CHANGELOG.md`:

```sh
composer changelog:add
```

The command asks for the change's significance, its type and its entry, and writes the fragment to `changelog/<branch-name>`, which the pull request commits. CI validates the fragments with `composer changelog:validate`.

## Filing changes

- Keep one concern per pull request.
- Fill in the template's breaking-changes section.
- Follow the code, documentation and commit conventions in [AGENTS.md](AGENTS.md).

## Releases

The five packages release together under one version. A release writes the fragments into a new version block of `CHANGELOG.md`, whose version the fragments' significance sets:

```sh
composer changelog:write
```

Pushing the tag `v<version>` on that commit splits it to every mirror, where consumers' `vcs` repositories find the release.

## WooCommerce floor

The woocommerce package tracks the newest stable WooCommerce release until a tag. Raising the floor moves, in one commit, the manifest's `extra.requires-wc`, the root's and the fixtures' `wp-plugin/woocommerce` and `php-stubs/woocommerce-stubs` constraints, the fixtures' `WC requires at least` and `WC tested up to` headers, and the locks. The same commit re-checks that WooCommerce still uses each cache group `MetaTypes::DEFINITIONS` names, because the uninstall engine flushes them.
