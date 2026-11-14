# wp-framework-settings

Settings screens and edit-screen fields declared as data, so WordPress's own APIs render, validate and save them and a plugin never hand-writes form handling.

## Requirements

It builds on wp-framework-core, which brings wp-framework-bootstrap and wp-framework-shared. Its `composer.json` holds its PHP floor under `require.php` and its WordPress floor under `extra.requires-wp`, which a plugin's `Requires PHP` and `Requires at least` headers meet.

## Installation

The packages are not on Packagist, and Composer reads repositories only from the plugin's own `composer.json`, so a plugin lists one `vcs` repository for each package of the stack. It requires the package for development only, because it ships a prefixed copy instead:

```json
{
  "require-dev": {
    "ahegyes/wp-framework-settings": "^2.0"
  },
  "repositories": [
    { "type": "vcs", "url": "https://github.com/ahegyes/wp-framework-bootstrap" },
    { "type": "vcs", "url": "https://github.com/ahegyes/wp-framework-shared" },
    { "type": "vcs", "url": "https://github.com/ahegyes/wp-framework-core" },
    { "type": "vcs", "url": "https://github.com/ahegyes/wp-framework-settings" }
  ],
  "minimum-stability": "dev",
  "prefer-stable": true
}
```

`prefer-stable` installs the newest release that satisfies `^2.0`, and `minimum-stability` lets Composer take the development branch where no release does.

## Prefixing

WordPress loads every plugin into one PHP process, so each plugin ships the framework prefixed under its own namespace. Two plugins that ship the packages unprefixed fatal with "Cannot redeclare class …" once both load bootstrap's files, and their autoloaded classes resolve to one plugin's copy, chosen by the order the plugins load in, so the other plugin silently runs it. WordPress, WooCommerce (`WC_*`, `wc_*`, `WC()`, `Automattic\WooCommerce\*`) and Action Scheduler (`as_*`, `ActionScheduler*`) symbols stay global, and `Psr\*` is prefixed with the framework, so the plugin's container and logger implement the same prefixed interfaces.

[wordpress-configs](https://github.com/ahegyes/wordpress-configs) prefixes the packages with php-scoper into `vendor-prefixed/` after every development install, and a later `composer install --no-dev` keeps that output, so a build runs both, in that order. It also replaces the framework's text domain with the plugin's `extra.text-domain`, failing the build if any of the framework's survives. The plugin adds to its `composer.json`:

```json
{
  "require-dev": {
    "ahegyes/wordpress-configs": "dev-trunk",
    "humbug/php-scoper": "^0.18"
  },
  "repositories": [
    { "type": "vcs", "url": "https://github.com/ahegyes/wordpress-configs" }
  ],
  "autoload": {
    "psr-4": { "Acme\\PersonaSettings\\": "src/" },
    "files": [ "vendor-prefixed/scoper-autoload.php" ]
  },
  "scripts": {
    "post-autoload-dump": [ "DeepWebSolutions\\Config\\Composer\\ScopePhpDependencies::postAutoloadDump" ]
  },
  "extra": {
    "scoping-prefix": "Acme\\PersonaSettings\\Scoped",
    "text-domain": "acme-persona-settings"
  }
}
```

Its `scoper.inc.php` names the packages to prefix:

```php
return ( require __DIR__ . '/vendor/ahegyes/wordpress-configs/php/php-scoper/scoper-base.inc.php' )( __DIR__, 'ahegyes/wp-framework-*' );
```

## Usage

An `OptionsPage` stores each field in its own option through the Settings API, and an `ObjectForm` stores each field on the edited object through a store. The plugin's main file requires `load.php` from bootstrap's prefixed copy, then its autoloader once the floor check passes:

```php
use Acme\PersonaSettings\Scoped\DeepWebSolutions\Framework\Bootstrap\Requirements;

defined( 'ABSPATH' ) || exit;

define( 'ACME_PS_FILE', __FILE__ );

$acme_ps_bootstrap = __DIR__ . '/vendor-prefixed/ahegyes/wp-framework-bootstrap/load.php';
if ( ! is_readable( $acme_ps_bootstrap ) ) { // The floor gate lives in the bootstrap package, so nothing built may load without it.
	return;
}

require_once $acme_ps_bootstrap;
if ( ! Requirements::check_floors( __FILE__ ) ) {
	return;
}

require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/functions.php';
```

The helpers need no kernel. On `plugins_loaded` at priority 15 the plugin builds its `PluginIdentity` from the entry file, its slug and its prefix, and registers its settings page and a form on the post edit screen whose rejections core's `AdminNotices` reports, each taking the identity, whose slug roots their filters:

```php
use Acme\PersonaSettings\Details;
use Acme\PersonaSettings\Settings;
use Acme\PersonaSettings\Scoped\DeepWebSolutions\Framework\Core\AdminNotice\AdminNotices;
use Acme\PersonaSettings\Scoped\DeepWebSolutions\Framework\Core\Logging\ErrorLogLogger;
use Acme\PersonaSettings\Scoped\DeepWebSolutions\Framework\Core\PluginIdentity;
use Acme\PersonaSettings\Scoped\DeepWebSolutions\Framework\Settings\AdminPage\OptionsPage;
use Acme\PersonaSettings\Scoped\DeepWebSolutions\Framework\Settings\EditScreen\Hosts\PostMetaBox;
use Acme\PersonaSettings\Scoped\DeepWebSolutions\Framework\Settings\EditScreen\ObjectForm;

defined( 'ABSPATH' ) || exit;

add_action(
	'plugins_loaded',
	static function (): void {
		$identity = new PluginIdentity( ACME_PS_FILE, Settings::SLUG, Settings::PREFIX );
		$notices  = new AdminNotices( $identity, new ErrorLogLogger( $identity ) );
		$notices->register_hooks();

		new OptionsPage( $identity, Settings::PREFIX, new Settings() )->register_hooks();
		new ObjectForm( $identity, Settings::PREFIX . '_details', new Details(), new PostMetaBox( array( 'post' ) ), $notices )->register_hooks();
	},
	15
);
```

A definition object declares the page and, apart, each tab's fields as data, so the page asks only for the fields of the tab it shows or saves. Each field is stored in the option its id names, which must start with the plugin's prefix and an underscore, since the page's capability writes it; the page leaves out any other field and reports it through `_doing_it_wrong()`. A number field is held to the `min`, `max` and `step` in its `custom_attributes`, and one with a `step` states its `min` too, from which both the browser and the save count the steps. A field's `validate` callback returns the value to store, or a `WP_Error` whose message the page shows while the stored value stays. Another plugin adds tabs through the `{root}/options_page/tabs` filter and their fields, or fields of an existing tab, through `{root}/options_page/fields`:

```php
final class Settings implements OptionsPageDefinitionInterface {
	// ...

	#[\Override]
	public function get_options_page( string $page_id ): array {
		return array(
			'parent'     => 'options-general.php',
			'capability' => 'manage_options',
			'page_title' => __( 'Acme Persona Settings', 'acme-persona-settings' ),
			'menu_title' => __( 'Acme Persona Settings', 'acme-persona-settings' ),
			'tabs'       => array(
				array(
					'slug'  => 'general',
					'title' => __( 'General', 'acme-persona-settings' ),
				),
			),
		);
	}

	// ...

	#[\Override]
	public function get_tab_fields( string $page_id, string $tab_slug ): array {
		return match ( $tab_slug ) {
			'general' => array(
				array(
					'type'              => 'number',
					'id'                => 'acme_ps_limit',
					'title'             => __( 'Limit', 'acme-persona-settings' ),
					'default'           => '10',
					'custom_attributes' => array(
						'min'  => '1',
						'step' => '1',
					),
				),
			),
			default   => array(),
		};
	}

	// ...
}
```

A form's definition object declares its fields the same way. `PostMetaBox` shows the form on the edit screens of the post types it names, `UserProfile` on the profile screens and `TermForm` on the term screens of the taxonomies it names. Each host stores a field under its id as the object's meta through `MetadataStore`, unless the form takes another store:

```php
final class Details implements ObjectFormDefinitionInterface {
	// ...

	#[\Override]
	public function get_object_form( string $form_id, int $object_id ): array {
		return array(
			'title'      => __( 'Details', 'acme-persona-settings' ),
			'capability' => 'edit_posts',
			'fields'     => array(
				array(
					'type'  => 'text',
					'id'    => '_' . Settings::PREFIX . '_subtitle',
					'title' => __( 'Subtitle', 'acme-persona-settings' ),
				),
			),
		);
	}

	// ...
}
```

## Contract and versioning

The five packages release together under one version, which the [changelog](https://github.com/ahegyes/wordpress-framework/blob/trunk/CHANGELOG.md) records.

The 2.x contract is every public class, interface, enum, method, property and constant, the protected members of non-final classes, and the hook and priority each public callback method is registered on, unless tagged `@internal`. A `protected(set)` property is contract for reading, by its name and declared type. `@internal`, `private` and protected-on-final members are not contract, and an `@internal` symbol serves only its own package, because a caret edge installs a package beside an older or newer release of another. Hook names and their arguments, option and meta keys, REST routes and the path of bootstrap's `load.php` are contract too.

Releases follow SemVer on this contract from the first tag: a break of it ships as the next major, and internals change in any release. Plugins on different majors run side by side, because each ships its own prefixed copy.

This package is developed in the [wordpress-framework](https://github.com/ahegyes/wordpress-framework) monorepo, which holds its issues and pull requests.
