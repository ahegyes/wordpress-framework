# Changelog

All notable changes to `ahegyes/wp-framework-utilities` are documented in this file. Format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/), versioning follows [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

Pending entries live in [`changelog/`](./changelog) — add via `composer changelog:add:utilities` from the monorepo root. Aggregate into a release with `composer changelog:write:utilities`.

## 2.0.0 - unreleased

### Added

- **Complete rewrite of v1.** Lean library architecture: interfaces + final classes, PSR-11/PSR-3 throughout. PHP 8.5+, WordPress 7.0+. See README for architecture details.
- **Hooks system** — multi-handler facade with pluggable routing strategies. Deprecated-hook dispatcher for preserving v1 hook surfaces.
- **AdminNotices system** — in-memory notice queue rendered via WP's native admin notice API.
- **Storage system** — key-value store interface with in-memory, wp_options, and user-meta backends.
- **Conditionals system** — standard pre-resolution gates: dependency checks (plugin active, PHP/WP version, extension, function, ini size) and request-context checks (admin, AJAX, CLI, current-user capability).
