# Changelog

All notable changes to `ahegyes/wp-framework-bootstrap` are documented in this file. Format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/), versioning follows [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

Pending entries live in [`changelog/`](./changelog) — add via `composer changelog:add:bootstrap` from the monorepo root. Aggregate into a release with `composer changelog:write:bootstrap`.

## 2.0.0 - unreleased

### Added

- **Complete rewrite of v1.** Lean pre-autoload requirements check. PHP 5.6+ compatible — runs before the autoloader so it must execute on legacy runtimes too. See README for architecture details.
- **Requirements check** — validates a consumer plugin's declared PHP/WP minimums against the runtime with the framework's own floor applied; returns a `WP_Error` for the consumer to render via the admin-notice renderer.
- **Admin notice renderer** — renders an unmet-requirements `WP_Error` as a localized admin notice; supports custom error codes added by the consumer.
- **Plugin metadata reader** — cached `get_plugin_data()` wrapper used by the requirements check and notice rendering.
