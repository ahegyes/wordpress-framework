<?php declare( strict_types=1 );
/**
 * Adds the request spec's object form on plugins_loaded, as a plugin without the kernel does.
 *
 * The spec names the form's host (post, user or term) with its arguments, and the form's title, capability and fields; the definition records each evaluation. A field's validate may name a behavior as acme:append, acme:reject, acme:reject-silently or acme:record, and its render may be acme:render; each records what it receives in the request's outcome. Fields listed per object come from a closure that records the object id it receives. The spec may also contribute fields through the form's fields filter, keep posts in the classic editor, give the form a recording store and update the saved post again inside save_post.
 *
 * @package Acme
 */

use DeepWebSolutions\Framework\Settings\ObjectForm;
use DeepWebSolutions\Framework\Settings\ObjectStoreInterface;
use DeepWebSolutions\Framework\Settings\PostMetaBox;
use DeepWebSolutions\Framework\Settings\TermForm;
use DeepWebSolutions\Framework\Settings\UserProfile;

require_once dirname( __DIR__, 3 ) . '/vendor/autoload.php';

$acme_with_behaviors = static function ( array $fields ): array {
	foreach ( $fields as &$field ) {
		$field_id = $field['id'] ?? '';
		if ( 'acme:render' === ( $field['render'] ?? null ) ) {
			$field['render'] = static function ( array $field, mixed $value, string $name, string $id ) use ( $field_id ): string {
				$GLOBALS['acme_outcome']['records'][] = array( 'render', $field_id, $value, $name, $id );

				return '<input type="hidden" name="' . esc_attr( $name ) . '" value="" /><input type="text" class="acme-widget" id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '" value="' . esc_attr( is_string( $value ) ? $value : '' ) . '" />';
			};
		}

		$behavior = $field['validate'] ?? null;
		if ( is_string( $behavior ) && str_starts_with( $behavior, 'acme:' ) ) {
			$field['validate'] = static function ( mixed $raw ) use ( $behavior, $field_id ): mixed {
				$GLOBALS['acme_outcome']['records'][] = array( 'validate', $field_id, $raw );

				return match ( $behavior ) {
					'acme:append'          => $raw . '!',
					'acme:reject'          => new WP_Error( 'acme_rejected', 'Use at most four characters.' ),
					'acme:reject-silently' => new WP_Error( 'acme_rejected' ),
					'acme:record'          => $raw,
				};
			};
		}
	}

	return $fields;
};

add_action(
	'plugins_loaded',
	static function () use ( $spec, $acme_with_behaviors ): void {
		$fields = $acme_with_behaviors( $spec['form']['fields'] );
		if ( $spec['per_object'] ?? false ) {
			$fields = static function ( int $object_id ) use ( $fields ): array {
				$GLOBALS['acme_outcome']['records'][] = array( 'fields', $object_id );

				return $fields;
			};
		}
		$definition = static function () use ( $spec, $fields ): array {
			$GLOBALS['acme_outcome']['records'][] = array( 'definition' );

			return array( 'fields' => $fields ) + $spec['form'];
		};

		$host = match ( $spec['host'] ) {
			'post' => new PostMetaBox( ...$spec['host_args'] ),
			'user' => new UserProfile(),
			'term' => new TermForm( ...$spec['host_args'] ),
		};

		$store = null;
		if ( 'acme:recording' === ( $spec['store'] ?? null ) ) {
			$store = new class( "{$spec['id']}_store" ) implements ObjectStoreInterface {
				public function __construct(
					protected string $option
				) {}

				public function get_values( int $object_id, array $keys ): array {
					$GLOBALS['acme_outcome']['records'][] = array( 'get_values', $object_id, $keys );

					return array_intersect_key( get_option( $this->option, array() )[ $object_id ] ?? array(), array_flip( $keys ) );
				}

				public function update_values( int $object_id, array $values ): void {
					$GLOBALS['acme_outcome']['records'][] = array( 'update_values', $object_id, $values );

					$stored               = get_option( $this->option, array() );
					$stored[ $object_id ] = array_replace( $stored[ $object_id ] ?? array(), $values );
					update_option( $this->option, $stored );
				}
			};
		}

		( new ObjectForm( $spec['id'], $definition, $host, $store ) )->register_hooks();
	}
);

if ( isset( $spec['contributed'] ) ) {
	add_filter(
		"{$spec['id']}_fields",
		static function ( array $fields, int $object_id ) use ( $spec, $acme_with_behaviors ): array {
			$GLOBALS['acme_outcome']['records'][] = array( 'contributed', $object_id );

			return array_merge( $fields, $acme_with_behaviors( $spec['contributed'] ) );
		},
		10,
		2
	);
}

if ( 'classic' === ( $spec['editor'] ?? null ) ) {
	add_filter( 'use_block_editor_for_post', '__return_false' );
}

if ( $spec['nested_update'] ?? false ) {
	add_action(
		'save_post',
		static function ( int $post_id ): void {
			static $updating = false;
			if ( ! $updating ) {
				$updating = true;
				wp_update_post( array( 'ID' => $post_id ) );
				$updating = false;
			}
		},
		20
	);
}
