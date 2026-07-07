# wp-framework-shared

Substrate primitives for plugins built on the DWS framework: result and value-object patterns (including a version-compare value object), plus error and exception scaffolding. Runtime-WP-aware but pulls in no WP-specific abstractions like Options or Hooks — the shared kernel that `core`, `infrastructure`, and `woocommerce` build on.

Part of the [DWS WordPress framework](https://github.com/ahegyes/wordpress-framework) — see the monorepo for architecture, contributing, and the rest of the package set.

## Installation

```bash
composer require ahegyes/wp-framework-shared
```

## Lineage

New in v2 — no archived v1 counterpart. Distills the cross-cutting primitives that v1 spread across [`deep-web-solutions/wp-framework-helpers`](https://github.com/deep-web-solutions/wordpress-framework-helpers) and [`deep-web-solutions/wp-framework-utilities`](https://github.com/deep-web-solutions/wordpress-framework-utilities) (both archived) into a substrate kernel.
