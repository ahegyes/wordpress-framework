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

## Filing changes

- Keep one concern per pull request.
- Fill in the template's breaking-changes section.
- Follow the code, documentation and commit conventions in [AGENTS.md](AGENTS.md).
