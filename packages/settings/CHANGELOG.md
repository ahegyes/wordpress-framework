# Changelog

All notable changes to `ahegyes/wp-framework-settings` are documented in this file. Format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/), versioning follows [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

Pending entries live in [`changelog/`](./changelog) — add via `composer changelog:add:settings` from the monorepo root. Aggregate into a release with `composer changelog:write:settings`.

## 2.0.0 - unreleased

### Added

- **Initial release.** A settings stack as its own release granule — declare an admin settings screen and persist values, with storage decoupled from UI. PHP 8.5+.
- **Declarative descriptors** — storage- and UI-agnostic page, section, and field value objects carrying a per-field sanitize/validate seam, capability, and dynamic-options resolution.
- **Cross-component contribution** — field providers concatenated by a pure aggregator, with stable ordering and duplicate-id rejection.
- **WordPress options backend** — per-section grouped-array option storage (reusing `wp-framework-storage`), submenu and Settings API registration, and a save-context-guarded group sanitizer.
- **Object fields** — per-entity meta storage with HPOS-aware order/post meta-box registration.
- **WP-free field-type and render layer** — a field-processing dispatcher (sanitize → validate → coerce) and renderer over a fixed field-type taxonomy.
