<?php declare( strict_types=1 );

namespace DeepWebSolutions\Framework\Settings;

use DeepWebSolutions\Framework\Settings\ValueObjects\SettingsPage;

/**
 * Registers and persists one option-scoped settings page.
 *
 * A per-page instance: it holds the page descriptor and routes field access to
 * the section that declares each field. Field ids are page-unique, so
 * get/set/has/delete address a field by its id alone — no namespaced keys.
 *
 * @since   2.0.0
 * @version 2.0.0
 */
interface SettingsBackendInterface {
	/**
	 * Registers the page's admin menu and settings with WordPress.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   SettingsPage $page Page to register.
	 */
	public function register_page( SettingsPage $page ): void;

	/**
	 * Retrieves a field's stored value, or the default if none is stored.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   string $field_id      Field whose value to read.
	 * @param   mixed  $default_value Value to return when nothing is stored.
	 *
	 * @return  mixed
	 */
	public function get( string $field_id, mixed $default_value = null ): mixed;

	/**
	 * Persists a field's value.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   string $field_id Field whose value to write.
	 * @param   mixed  $value    Value to persist.
	 */
	public function set( string $field_id, mixed $value ): void;

	/**
	 * Whether a value is stored for a field.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   string $field_id Field to check.
	 *
	 * @return  bool
	 */
	public function has( string $field_id ): bool;

	/**
	 * Deletes a field's stored value.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   string $field_id Field to clear.
	 *
	 * @return  bool True if a value was deleted, false if none existed.
	 */
	public function delete( string $field_id ): bool;
}
