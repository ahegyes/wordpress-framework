<?php declare( strict_types=1 );

namespace DeepWebSolutions\Framework\Tests\Integration\Settings\EditScreen\Stores;

use DeepWebSolutions\Framework\Settings\EditScreen\Stores\MetadataStore;
use DeepWebSolutions\Framework\Shared\Exception\InvalidArgumentException;
use DeepWebSolutions\Framework\Tests\Integration\Support\CreatesUsersTrait;
use DeepWebSolutions\Framework\Tests\Integration\Support\IsolatesStorageTrait;
use PHPUnit\Framework\Attributes\After;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass( MetadataStore::class )]
final class MetadataStoreTest extends TestCase {
	use CreatesUsersTrait;
	use IsolatesStorageTrait;

	protected array $post_ids = array();
	protected array $term_ids = array();

	public static function meta_types(): array {
		return array(
			'post' => array( 'post' ),
			'user' => array( 'user' ),
			'term' => array( 'term' ),
		);
	}

	#[DataProvider( 'meta_types' )]
	public function test_only_the_keys_the_object_has_are_returned_with_their_stored_values( string $meta_type ): void {
		$object_id = $this->make_object( $meta_type );
		add_metadata( $meta_type, $object_id, "_{$this->prefix}_text", 'stored' );
		add_metadata( $meta_type, $object_id, "_{$this->prefix}_empty", '' );
		add_metadata( $meta_type, $object_id, "_{$this->prefix}_list", array() );

		$values = new MetadataStore( $meta_type )->get_values( $object_id, array( "_{$this->prefix}_text", "_{$this->prefix}_empty", "_{$this->prefix}_list", "_{$this->prefix}_absent" ) );

		self::assertSame(
			array(
				"_{$this->prefix}_text"  => 'stored',
				"_{$this->prefix}_empty" => '',
				"_{$this->prefix}_list"  => array(),
			),
			$values
		);
	}

	#[DataProvider( 'meta_types' )]
	public function test_a_null_row_reads_as_absent_with_or_without_a_registered_default( string $meta_type ): void {
		$object_id = $this->make_object( $meta_type );
		register_meta( $meta_type, "_{$this->prefix}_registered", array( 'default' => 'Registered default.' ) );

		try {
			update_metadata( $meta_type, $object_id, "_{$this->prefix}_registered", null );
			update_metadata( $meta_type, $object_id, "_{$this->prefix}_plain", null );

			self::assertSame( array(), new MetadataStore( $meta_type )->get_values( $object_id, array( "_{$this->prefix}_registered", "_{$this->prefix}_plain" ) ) );
		} finally {
			unregister_meta_key( $meta_type, "_{$this->prefix}_registered" );
		}
	}

	#[DataProvider( 'meta_types' )]
	public function test_values_are_stored_as_given_backslashes_included( string $meta_type ): void {
		$object_id = $this->make_object( $meta_type );
		add_metadata( $meta_type, $object_id, "_{$this->prefix}_text", 'old' );

		new MetadataStore( $meta_type )->update_values(
			$object_id,
			array(
				"_{$this->prefix}_text" => 'O\'Brien C:\\dir',
				"_{$this->prefix}_list" => array( 'C:\\dir', 'red' ),
			)
		);

		self::assertSame( array( 'O\'Brien C:\\dir' ), get_metadata( $meta_type, $object_id, "_{$this->prefix}_text", false ) );
		self::assertSame( array( array( 'C:\\dir', 'red' ) ), get_metadata( $meta_type, $object_id, "_{$this->prefix}_list", false ) );
	}

	public static function unstorable_values(): array {
		return array(
			'object'        => array( (object) array( 'path' => 'C:\\reports' ) ),
			'nested-object' => array( array( 'red', array( (object) array( 'path' => 'C:\\reports' ) ) ) ),
		);
	}

	#[DataProvider( 'unstorable_values' )]
	public function test_a_value_holding_anything_but_scalars_null_and_arrays_throws_before_any_key_is_written( mixed $value ): void {
		$object_id = $this->make_object( 'post' );

		try {
			new MetadataStore( 'post' )->update_values(
				$object_id,
				array(
					"_{$this->prefix}_first" => 'Written first.',
					"_{$this->prefix}_value" => $value,
				)
			);
			self::fail( 'The store wrote a value it cannot keep.' );
		} catch ( InvalidArgumentException $exception ) {
			self::assertStringContainsString( "_{$this->prefix}_value", $exception->getMessage() );
		}

		self::assertSame( array(), get_metadata( 'post', $object_id, "_{$this->prefix}_first", false ) );
		self::assertSame( array(), get_metadata( 'post', $object_id, "_{$this->prefix}_value", false ) );
	}

	#[DataProvider( 'meta_types' )]
	public function test_a_stored_empty_value_survives_a_round_trip( string $meta_type ): void {
		$object_id = $this->make_object( $meta_type );
		$store     = new MetadataStore( $meta_type );

		$store->update_values(
			$object_id,
			array(
				"_{$this->prefix}_empty" => '',
				"_{$this->prefix}_list"  => array(),
			)
		);

		self::assertSame(
			array(
				"_{$this->prefix}_empty" => '',
				"_{$this->prefix}_list"  => array(),
			),
			$store->get_values( $object_id, array( "_{$this->prefix}_empty", "_{$this->prefix}_list" ) )
		);
	}

	protected function make_object( string $meta_type ): int {
		return match ( $meta_type ) {
			'post' => $this->post_ids[] = wp_insert_post(
				array(
					'post_title'  => 'Acme post',
					'post_status' => 'draft',
				)
			),
			'user' => $this->make_user( "{$this->prefix}_user", 'subscriber' ),
			'term' => $this->term_ids[] = (int) wp_insert_term( "$this->prefix term", 'category' )['term_id'],
		};
	}

	#[After]
	protected function delete_objects(): void {
		foreach ( $this->post_ids as $post_id ) {
			wp_delete_post( $post_id, true );
		}
		foreach ( $this->term_ids as $term_id ) {
			wp_delete_term( $term_id, 'category' );
		}
		$user = get_user_by( 'login', "{$this->prefix}_user" );
		if ( $user instanceof \WP_User ) {
			require_once ABSPATH . 'wp-admin/includes/user.php';
			wp_delete_user( $user->ID );
		}
	}
}
