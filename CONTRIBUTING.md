# Contributing

Each package keeps to the dependency edges that [AGENTS.md](AGENTS.md) maps, because a plugin ships only the closure of the packages it requires. A pull request that adds an edge explains why the package needs it.

## Before opening a pull request

Install the dependencies on the PHP, Node and npm versions the README lists, then run the local equivalents of the CI checks:

```sh
composer packages-install
npm install
composer validate --strict
composer quality-check
composer audit --locked
npm audit --omit=dev --audit-level=high
actionlint
zizmor .github/
```

## Filing changes

- Keep one concern per pull request.
- Fill in the template's breaking-changes section.
- Follow the code, documentation and commit conventions in [AGENTS.md](AGENTS.md).
