# wp-framework-bootstrap

Pre-autoload PHP and WordPress version check. Call this from a consumer plugin's main file **before** `require_once 'vendor/autoload.php'`, so a runtime mismatch renders a graceful admin notice instead of fataling out on a legacy PHP version.

Part of the [DWS WordPress framework](https://github.com/ahegyes/wordpress-framework) — see the monorepo for architecture, contributing, and the rest of the package set.

## Installation

```bash
composer require ahegyes/wp-framework-bootstrap
```

## Why a separate package

The framework targets PHP 8.5+, but if a consumer plugin ships to a site running PHP 7.4, requiring `vendor/autoload.php` would fatal-error before any version check could run. This package is the one piece of the framework that must parse and execute on PHP 5.6+, so it can do the check first, queue an admin notice on failure, and let the consumer plugin bail cleanly.

## Usage

In your plugin's main file, after `defined( 'ABSPATH' ) || exit;` and your `define( 'YOUR_PLUGIN_FILE', __FILE__ );` constants:

```php
require_once __DIR__ . '/vendor/autoload.php';

$requirements = \DeepWebSolutions\Framework\Bootstrap\Requirements\check_requirements( plugin_basename( __FILE__ ) );
if ( $requirements instanceof \WP_Error ) {
    \DeepWebSolutions\Framework\Bootstrap\Notice\output_requirements_error( plugin_basename( __FILE__ ), $requirements );
    return;
}

// Continue plugin bootstrap from here.
```

`check_requirements()` reads `Requires PHP` and `Requires at least` from the plugin's header, applies the framework's own PHP 8.5 / WordPress 7.0 floor, and compares against the runtime. On success it returns `true`; on failure it returns a `WP_Error` carrying `plugin_php_incompatible` / `plugin_wp_incompatible` codes with `min` and `current` data. `output_requirements_error()` queues an admin notice describing what's missing.

For dependency checks beyond PHP/WP versions — e.g. requiring WooCommerce, a PHP extension, or a custom predicate — use the framework's Conditionals system in `wp-framework-core` / `wp-framework-utilities`, which runs after the autoloader and integrates with Feature gating and `AdminNoticesService`. Bootstrap stays narrowly scoped to "make the autoloader safe to require."

## API

- `DeepWebSolutions\Framework\Bootstrap\Requirements\check_requirements( $plugin_basename ): true|WP_Error` — runs the PHP/WP version check. Returns `true` when the runtime is acceptable, or a `WP_Error` carrying `plugin_php_incompatible` / `plugin_wp_incompatible` codes (each with `min` and `current` data).
- `DeepWebSolutions\Framework\Bootstrap\Notice\output_requirements_error( $plugin_basename, $error ): void` — queues an admin notice rendering the two known codes as localized "Requires PHP X" / "Requires WordPress Y" sentences. No-op when the error bag is empty or carries only unknown codes.

## Requirements

PHP 5.6 or higher. WordPress is detected at runtime; the wrappers degrade gracefully when WP isn't loaded.

## License

GPL-2.0-or-later

## Predecessor

Successor to [`deep-web-solutions/wp-framework-bootstrapper`](https://github.com/deep-web-solutions/wordpress-framework-bootstrapper) (archived).
