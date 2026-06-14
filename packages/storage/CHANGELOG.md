# Changelog

All notable changes to `ahegyes/wp-framework-storage` are documented in this file. Format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/), versioning follows [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

Pending entries live in [`changelog/`](./changelog) — add via `composer changelog:add:storage` from the monorepo root. Aggregate into a release with `composer changelog:write:storage`.

## 2.0.0 - unreleased

### Added

- **Initial release.** Key-value storage backends for the DWS framework family — extracted as a zero-dependency leaf package so storage primitives release independently and other packages can depend on storage alone. PHP 8.5+.
- **`KeyValueStoreInterface`** — common get/set/has/delete/get_all/clear contract.
- **Storage backends** — in-memory (per-request), `wp_options`-backed (one option row per store, with an autoload policy flag), and user-meta-backed (per-user, with optional explicit user targeting).
