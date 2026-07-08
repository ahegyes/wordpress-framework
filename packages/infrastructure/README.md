# wp-framework-infrastructure

Persistence, declarative settings, and runtime services for full DWS WordPress framework plugins. The PHP namespaces are unchanged from the retired storage, settings, and utilities packages, and Composer `replace` entries bridge existing requirements.

Part of the [DWS WordPress framework](https://github.com/ahegyes/wordpress-framework) — see the monorepo for architecture, contributing, and the rest of the package set.

This package provides the standard framework tier:
- `DeepWebSolutions\Framework\Storage\` key-value stores and object-meta repositories.
- `DeepWebSolutions\Framework\Settings\` descriptors, WordPress options backend, object-field forms, and REST-aware schema surfaces.
- `DeepWebSolutions\Framework\Utilities\` hooks, admin notices, caching, conditionals, scheduling, permissions, logging, and helpers.

## Installation

```bash
composer require ahegyes/wp-framework-infrastructure
```

## Usage

### Register a settings page

Declare the page as descriptors and hand it to a `WordPressSettingsBackend` from a Hookable component:

```php
use DeepWebSolutions\Framework\Core\Lifecycle\Hookable\HookableInterface;
use DeepWebSolutions\Framework\Settings\Backend\WordPressSettingsBackend;
use DeepWebSolutions\Framework\Settings\Schema\Field\FieldType;
use DeepWebSolutions\Framework\Settings\Schema\ValueObjects\SettingsField;
use DeepWebSolutions\Framework\Settings\Schema\ValueObjects\SettingsPage;
use DeepWebSolutions\Framework\Settings\Schema\ValueObjects\SettingsSection;

final class Settings implements HookableInterface {
	public function __construct( protected WordPressSettingsBackend $backend ) {}

	public function register_hooks(): void {
		$this->backend->register_page(
			new SettingsPage(
				slug: 'my_plugin',
				page_title: 'My Plugin',
				menu_title: 'My Plugin',
				capability: 'manage_options',
				sections: array(
					new SettingsSection(
						id: 'general',
						title: 'General',
						fields: array(
							new SettingsField( id: 'api_key', type: FieldType::Text->value, label: 'API key', default_value: '' ),
						),
					),
				),
			),
		);
	}
}
```

A field's `default_value` fills the control until the first save — it is render-time only. Reads fall back to the default you pass at read time: `$backend->get( 'api_key', 'fallback' )`.

### Schedule a recurring job

```php
use DeepWebSolutions\Framework\Utilities\Scheduling\Backends\ActionSchedulerBackend;
use DeepWebSolutions\Framework\Utilities\Scheduling\Backends\WPCronBackend;
use DeepWebSolutions\Framework\Utilities\Scheduling\Scheduler;

// An omitted backend list defaults to WP-Cron alone; list Action Scheduler first to prefer it.
$scheduler = new Scheduler( array( new ActionSchedulerBackend(), new WPCronBackend() ) );

// Every request — e.g. from a component's register_hooks() — so each backend is wired.
$scheduler->register_hooks();

// schedule_recurring() is #[\NoDiscard]: a dropped Failure is a job that silently never runs.
if ( ! $scheduler->is_scheduled( 'my_plugin_sync' ) ) {
	$result = $scheduler->schedule_recurring( 'my_plugin_sync', HOUR_IN_SECONDS );
	if ( $result->is_failure() ) {
		// Branch on the SchedulingError payload — the job was NOT scheduled.
	}
}

// On deactivation, clear the job; unschedule() also returns a Result.
if ( $scheduler->unschedule( 'my_plugin_sync' )->is_failure() ) {
	// Log it — the job may still fire.
}
```

### Show a persistent dismissible admin notice

```php
use DeepWebSolutions\Framework\Storage\OptionsStore;
use DeepWebSolutions\Framework\Storage\UserMetaStore;
use DeepWebSolutions\Framework\Utilities\AdminNotices\AdminNoticesService;
use DeepWebSolutions\Framework\Utilities\AdminNotices\DismissedNoticesTracker;
use DeepWebSolutions\Framework\Utilities\AdminNotices\NoticeStore;
use DeepWebSolutions\Framework\Utilities\AdminNotices\NoticeType;
use DeepWebSolutions\Framework\Utilities\AdminNotices\ValueObjects\AdminNotice;

// Sticky dismissal needs all three: a persistent store, a dismissal tracker, and a dismiss action.
$notices = new AdminNoticesService(
	stores: array( 'options' => new NoticeStore( new OptionsStore( 'my_plugin_notices' ) ) ),
	dismissals: new DismissedNoticesTracker( new UserMetaStore( 'my_plugin_dismissed_notices' ) ),
	dismiss_action: 'my_plugin_dismiss_notice',
);
$notices->register_hooks(); // Once, during boot.

$notices->add_notice(
	new AdminNotice(
		id: 'migration_failed',
		message: 'My Plugin could not complete its data migration.',
		type: NoticeType::Error,
		persistent: true, // Recurs on every admin request until dismissed.
	),
	store: 'options',
);
```

An explicit `stores` map replaces the default in-memory store — `add_notice()` to an unregistered store name throws.

## Lineage

Successor to:
- [`deep-web-solutions/wp-framework-settings`](https://github.com/deep-web-solutions/wordpress-framework-settings) (archived)
- [`deep-web-solutions/wp-framework-utilities`](https://github.com/deep-web-solutions/wordpress-framework-utilities) (archived)

The Storage namespace is new in v2.
