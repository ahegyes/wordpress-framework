# Changelog

All notable changes to `ahegyes/wp-framework-bootstrap` are documented in this file. Format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/), versioning follows [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

Pending entries live in [`changelog/`](./changelog) — add via `composer changelog:add:bootstrap` from the monorepo root. Aggregate into a release with `composer changelog:write:bootstrap`.

## 2.0.0 - unreleased

### Added
- Initial release.
- Pre-autoload PHP/WordPress version check via Composer `files` autoload.
- Graceful admin notice when the runtime can't host the framework's modern PHP code.
