# Changelog

All notable changes to `ahegyes/wp-framework-infrastructure` are documented in this file. Format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/), versioning follows [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

Pending entries live in [`changelog/`](./changelog) — add via `composer packages:infrastructure:changelog:add` from the monorepo root. Aggregate into a release with `composer packages:infrastructure:changelog:write`.

## 2.0.0 - unreleased

### Added

- **Key-value stores** — `KeyValueStoreInterface` with memory, options, and user-meta backends; null-correct reads and grouped persistent rows.
- **Object-meta repositories** — settings-free per-object persistence contract with a WordPress-metadata implementation.
- **Declarative settings schema** — page/section/field descriptors with a typed field taxonomy, options resolution, rendering, and sanitize/validate processing.
- **WordPress settings backend** — registers descriptor-backed options pages with grouped per-section storage and section-level REST exposure.
- **Meta-field surfaces** — a shared object-field form engine with post-meta, term, and user-profile surfaces plus descriptor-addressed CRUD.
- **Hooks service** — callable-native hook registration through direct, buffered, and scoped handlers, plus a deprecated-hook dispatcher.
- **Admin notices** — a queue-and-render service with persistent stores, per-user dismissal tracking, and dependency-requirement notices.
- **Caching** — transient and object-cache wrappers with per-plugin key prefixes and versioned-group invalidation.
- **Conditionals** — dependency and context predicates (plugin/PHP/WP versions, extensions, admin/AJAX/CLI/capability) for kernel feature gating.
- **Scheduling** — an ordered-backend scheduler facade over WP-Cron and Action Scheduler with `Result`-carried failures.
- **Permissions** — a capability registrar that grants on install, reconciles on update, and revokes on uninstall.
- **Logging** — composite and redacting PSR-3 decorators plus an admin-notice logger.
- **Helpers** — cross-plugin stateless array and asset utilities.

### Changed

- Consolidates `ahegyes/wp-framework-storage`, `ahegyes/wp-framework-settings`, and `ahegyes/wp-framework-utilities`; PHP namespaces are unchanged.
