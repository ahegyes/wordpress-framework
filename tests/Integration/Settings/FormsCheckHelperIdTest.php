<?php declare( strict_types=1 );

namespace DeepWebSolutions\Framework\Tests\Integration\Settings;

use DeepWebSolutions\Framework\Settings\Forms;
use DeepWebSolutions\Framework\Shared\Exception\InvalidArgumentException;
use DeepWebSolutions\Framework\Tests\Integration\Support\ProvidesHelperIdsTrait;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass( Forms::class )]
final class FormsCheckHelperIdTest extends TestCase {
	use ProvidesHelperIdsTrait;

	#[DataProvider( 'invalid_helper_ids' )]
	public function test_an_invalid_id_is_rejected_by_name( string $id ): void {
		$this->expectException( InvalidArgumentException::class );
		$this->expectExceptionMessage( "Helper id '$id' is not a lowercase letter, after an optional underscore, followed by lowercase letters, digits, underscores and hyphens." );

		Forms::check_helper_id( $id );
	}

	#[DataProvider( 'v1_helper_ids' )]
	public function test_every_v1_key_is_a_valid_id( string $id ): void {
		Forms::check_helper_id( $id );

		$this->addToAssertionCount( 1 );
	}
}
