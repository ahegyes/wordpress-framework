<?php declare( strict_types=1 );

namespace DeepWebSolutions\Framework\Settings\Tests\Unit;

use DeepWebSolutions\Framework\Settings\Schema\Exceptions\DuplicateSettingsFieldException;
use DeepWebSolutions\Framework\Settings\Schema\SettingsFieldAggregator;
use DeepWebSolutions\Framework\Settings\Schema\SettingsFieldProviderInterface;
use DeepWebSolutions\Framework\Settings\Schema\ValueObjects\SettingsField;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\Attributes\UsesFunction;
use PHPUnit\Framework\TestCase;

#[CoversClass( SettingsFieldAggregator::class )]
#[UsesClass( SettingsField::class )]
#[UsesFunction( 'DeepWebSolutions\Framework\Settings\Schema\is_valid_identifier' )]
final class SettingsFieldAggregatorTest extends TestCase {
	public function test_no_providers_yields_an_empty_list(): void {
		self::assertSame( array(), ( new SettingsFieldAggregator() )->aggregate( array() ) );
	}

	public function test_a_provider_contributing_no_fields_yields_an_empty_list(): void {
		$result = ( new SettingsFieldAggregator() )->aggregate( array( $this->provider() ) );

		self::assertSame( array(), $result );
	}

	public function test_concatenates_provider_fields_in_provider_then_field_order(): void {
		// Reverse-lexical ids: the asserted order can only come from insertion order, not an id sort.
		$result = ( new SettingsFieldAggregator() )->aggregate(
			array(
				$this->provider( $this->field( 'b' ), $this->field( 'a' ) ),
				$this->provider( $this->field( 'd' ), $this->field( 'c' ) ),
			),
		);

		self::assertSame( array( 'b', 'a', 'd', 'c' ), $this->ids( $result ) );
	}

	public function test_a_duplicate_field_id_across_providers_throws(): void {
		$this->expectException( DuplicateSettingsFieldException::class );

		( new SettingsFieldAggregator() )->aggregate(
			array(
				$this->provider( $this->field( 'dup' ) ),
				$this->provider( $this->field( 'dup' ) ),
			),
		);
	}

	public function test_a_duplicate_field_id_within_one_provider_throws(): void {
		$this->expectException( DuplicateSettingsFieldException::class );

		( new SettingsFieldAggregator() )->aggregate(
			array( $this->provider( $this->field( 'dup' ), $this->field( 'dup' ) ) ),
		);
	}

	public function test_positioned_fields_sort_ascending_and_unpositioned_follow_in_insertion_order(): void {
		$result = ( new SettingsFieldAggregator() )->aggregate(
			array(
				$this->provider( $this->field( 'a', 2 ), $this->field( 'd' ) ),
				$this->provider( $this->field( 'c', 1 ), $this->field( 'b' ) ),
			),
		);

		// c(1), a(2), then the unpositioned d, b in insertion order (not id order).
		self::assertSame( array( 'c', 'a', 'd', 'b' ), $this->ids( $result ) );
	}

	public function test_equal_positions_keep_insertion_order(): void {
		// Inserted y before x at the same position; a stray id tie-break would flip them.
		$result = ( new SettingsFieldAggregator() )->aggregate(
			array( $this->provider( $this->field( 'y', 1 ), $this->field( 'x', 1 ) ) ),
		);

		self::assertSame( array( 'y', 'x' ), $this->ids( $result ) );
	}

	public function test_all_unpositioned_fields_keep_insertion_order(): void {
		$result = ( new SettingsFieldAggregator() )->aggregate(
			array( $this->provider( $this->field( 'one' ), $this->field( 'two' ), $this->field( 'three' ) ) ),
		);

		self::assertSame( array( 'one', 'two', 'three' ), $this->ids( $result ) );
	}

	/**
	 * @param list<SettingsField> $fields
	 *
	 * @return list<string>
	 */
	private function ids( array $fields ): array {
		return \array_map( static fn ( SettingsField $field ): string => $field->id, $fields );
	}

	private function field( string $id, ?int $position = null ): SettingsField {
		return new SettingsField( id: $id, type: 'text', label: $id, position: $position );
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
