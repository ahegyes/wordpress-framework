<?php declare( strict_types=1 );

namespace DeepWebSolutions\Framework\Tests\Unit\Core\Scheduling;

use DeepWebSolutions\Framework\Core\Scheduling\ScheduledHook;
use DeepWebSolutions\Framework\Shared\Exception\InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass( ScheduledHook::class )]
final class ScheduledHookTest extends TestCase {
	public static function invalid_identities(): array {
		return array(
			'empty-hook'          => array( '', array() ),
			'zero-hook'           => array( '0', array() ),
			'192-byte-hook'       => array( \str_repeat( 'a', 192 ), array() ),
			'hook-with-a-space'   => array( 'acme sweep', array() ),
			'hook-with-a-newline' => array( "acme_sweep\n", array() ),
			'non-list'            => array( 'acme_sweep', array( 'order' => 1 ) ),
			'nested-array'        => array( 'acme_sweep', array( array( 1 ) ) ),
			'object'              => array( 'acme_sweep', array( new \stdClass() ) ),
			'float'               => array( 'acme_sweep', array( 1.5 ) ),
		);
	}

	#[DataProvider( 'invalid_identities' )]
	public function test_an_invalid_identity_is_rejected( string $hook, array $args ): void {
		$this->expectException( InvalidArgumentException::class );

		new ScheduledHook( $hook, $args );
	}

	public function test_arguments_of_the_wrong_type_are_reported_before_an_invalid_hook(): void {
		$this->expectExceptionMessage( 'are not a list of integers, strings, booleans and nulls' );

		new ScheduledHook( '', array( 1.5 ) );
	}
}
