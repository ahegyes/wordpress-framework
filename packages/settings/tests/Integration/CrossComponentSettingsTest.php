<?php declare( strict_types=1 );

namespace DeepWebSolutions\Framework\Settings\Tests\Integration;

use DeepWebSolutions\Framework\Settings\Backend\WordPressSettingsBackend;
use DeepWebSolutions\Framework\Settings\Schema\Exceptions\DuplicateSettingsFieldException;
use DeepWebSolutions\Framework\Settings\Schema\Aggregation\SettingsFieldAggregator;
use DeepWebSolutions\Framework\Settings\Schema\Aggregation\SettingsFieldProviderInterface;
use DeepWebSolutions\Framework\Settings\Schema\ValueObjects\SettingsField;
use DeepWebSolutions\Framework\Settings\Schema\ValueObjects\SettingsPage;
use DeepWebSolutions\Framework\Settings\Schema\ValueObjects\SettingsSection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass( SettingsFieldAggregator::class )]
#[UsesClass( SettingsField::class )]
#[UsesClass( SettingsSection::class )]
#[UsesClass( SettingsPage::class )]
#[UsesClass( WordPressSettingsBackend::class )]
final class CrossComponentSettingsTest extends TestCase {
	private const SLUG   = 'dws-cross-test';
	private const OPTION = 'dws-cross-test-main';

	protected function setUp(): void {
		parent::setUp();

		\wp_set_current_user( 1 );
		\remove_all_actions( 'admin_init' );
		\remove_all_filters( 'sanitize_option_' . self::OPTION );
		\delete_option( self::OPTION );
	}

	protected function tearDown(): void {
		\delete_option( self::OPTION );
		parent::tearDown();
	}

	public function test_fields_from_two_providers_each_apply_their_own_validation(): void {
		$volume = new SettingsField(
			id: 'volume',
			type: 'text',
			label: 'Volume',
			validate: static fn ( mixed $value ): bool => \in_array( $value, array( 'low', 'high' ), true ),
		);
		$mode = new SettingsField(
			id: 'mode',
			type: 'text',
			label: 'Mode',
			validate: static fn ( mixed $value ): bool => 'auto' === $value,
		);

		$backend = $this->register( $this->provider( $volume ), $this->provider( $mode ) );
		\do_action( 'admin_init' );

		// Both fields receive 'auto', which only mode's validator accepts and volume's rejects. Volume
		// coercing to false while mode survives proves each field ran its OWN validator, not the other's
		// — a swap would let 'auto' through on volume or reject it on mode.
		$this->save( array( 'volume' => 'auto', 'mode' => 'auto' ) );

		self::assertFalse( $backend->get( 'volume' ) );
		self::assertSame( 'auto', $backend->get( 'mode' ) );
	}

	public function test_both_providers_fields_are_persisted_when_valid(): void {
		$backend = $this->register(
			$this->provider( new SettingsField( id: 'first', type: 'text', label: 'First' ) ),
			$this->provider( new SettingsField( id: 'second', type: 'text', label: 'Second' ) ),
		);
		\do_action( 'admin_init' );

		$this->save( array( 'first' => 'A', 'second' => 'B' ) );

		self::assertSame( 'A', $backend->get( 'first' ) );
		self::assertSame( 'B', $backend->get( 'second' ) );
	}

	public function test_a_duplicate_field_id_across_providers_is_rejected(): void {
		$this->expectException( DuplicateSettingsFieldException::class );

		( new SettingsFieldAggregator() )->aggregate(
			array(
				$this->provider( new SettingsField( id: 'dup', type: 'text', label: 'A' ) ),
				$this->provider( new SettingsField( id: 'dup', type: 'text', label: 'B' ) ),
			),
		);
	}

	private function register( SettingsFieldProviderInterface ...$providers ): WordPressSettingsBackend {
		$fields = ( new SettingsFieldAggregator() )->aggregate( \array_values( $providers ) );
		$page   = new SettingsPage(
			slug: self::SLUG,
			page_title: 'Cross',
			menu_title: 'Cross',
			capability: 'manage_options',
			sections: array( new SettingsSection( 'main', 'Main', $fields ) ),
		);

		$backend = new WordPressSettingsBackend();
		$backend->register_page( $page );

		return $backend;
	}

	/**
	 * @param array<string, mixed> $values
	 */
	private function save( array $values ): void {
		// A direct update_option models the options.php form save: a write the backend did not
		// initiate, so the registered sanitize_callback runs (the write-guard is clear).
		\update_option( self::OPTION, $values );
	}

	private function provider( SettingsField ...$fields ): SettingsFieldProviderInterface {
		return new class( \array_values( $fields ) ) implements SettingsFieldProviderInterface {
			/**
			 * @param list<SettingsField> $fields
			 */
			public function __construct( private array $fields ) {}

			/**
			 * @return list<SettingsField>
			 */
			public function get_fields(): array {
				return $this->fields;
			}
		};
	}
}
