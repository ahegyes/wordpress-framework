# Changelog

All notable changes to `ahegyes/wp-framework-shared` are documented in this file. Format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/), versioning follows [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

Pending entries live in [`changelog/`](./changelog) — add via `composer packages:shared:changelog:add` from the monorepo root. Aggregate into a release with `composer packages:shared:changelog:write`.

## 2.0.0 - unreleased

### Added

- **Initial release.** Substrate kernel for the DWS framework family — primitives that other framework packages and consumer plugins build on. PHP 8.5+; runtime-WP-aware but no WP-specific abstractions.
- **Result and ValueObject patterns** — sealed-type result simulation and reflection-driven value-object base with structural equality and JSON serialization.
- **Version value object** — a `version_compare()`-semantics value object with comparison and parsing helpers, over the value-object base.
- **Error and Exception scaffolding** — marker interfaces and abstract bases for framework-side exception hierarchies.
- **Reflection helpers** — pure-PHP utilities underpinning the value-object base.
