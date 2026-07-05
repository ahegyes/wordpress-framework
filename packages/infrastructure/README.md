# wp-framework-infrastructure

Persistence, declarative settings, and runtime services for full DWS WordPress framework plugins.

This package provides the standard framework tier:
- `DeepWebSolutions\Framework\Storage\` key-value stores and object-meta repositories.
- `DeepWebSolutions\Framework\Settings\` descriptors, WordPress options backend, object-field forms, and REST-aware schema surfaces.
- `DeepWebSolutions\Framework\Utilities\` hooks, admin notices, caching, conditionals, scheduling, permissions, logging, and helpers.

The PHP namespaces are unchanged from the retired storage, settings, and utilities packages. Composer `replace` entries bridge existing requirements during the next consumer re-lock.

## Installation

```bash
composer require ahegyes/wp-framework-infrastructure
```

## Package Merge

This package consolidates the standard framework tier. Existing Storage, Settings, and Utilities PHP namespaces are unchanged.
