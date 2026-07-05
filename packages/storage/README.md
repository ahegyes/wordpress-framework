# wp-framework-storage

Storage primitives for WordPress plugins built on the DWS framework: a small `KeyValueStoreInterface` with in-memory, `wp_options`, and user-meta implementations, plus `ObjectMeta/` object-meta repositories (`ObjectMetaRepositoryInterface` and a WordPress-metadata-backed `MetadataRepository`) for per-object persistence. Zero internal framework dependencies — a leaf package that higher framework packages and consumer plugins build on.

Part of the [DWS WordPress framework](https://github.com/ahegyes/wordpress-framework) — see the monorepo for architecture, contributing, and the rest of the package set.

## Installation

```bash
composer require ahegyes/wp-framework-storage
```
