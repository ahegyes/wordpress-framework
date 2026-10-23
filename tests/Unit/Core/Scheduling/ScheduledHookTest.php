<?php declare( strict_types=1 );

namespace DeepWebSolutions\Framework\Tests\Unit\Core\Scheduling;

use DeepWebSolutions\Framework\Core\Scheduling\ScheduledHook;
use DeepWebSolutions\Framework\Shared\Exception\InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass( ScheduledHook::class )]
final class ScheduledHookTest extends TestCase {
	public static function valid_identities(): array {
		return array(
			'no-arguments'       => array( 'acme_sweep', array() ),
			'191-byte-hook'      => array( \str_repeat( 'a', 191 ), array() ),
			'every-scalar-kind'  => array( 'acme_sweep', array( 1, 'one', true, null ) ),
			'8000-bytes-of-json' => array( 'acme_sweep', array( \str_repeat( 'a', 7996 ) ) ),
		);
	}

	#[DataProvider( 'valid_identities' )]
	public function test_a_valid_identity_keeps_its_hook_and_arguments( string $hook, array $args ): void {
		$scheduled_hook = new ScheduledHook( $hook, $args );

		self::assertSame( $hook, $scheduled_hook->hook );
		self::assertSame( $args, $scheduled_hook->args );
	}

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
			'invalid-utf-8'       => array( 'acme_sweep', array( "a\xB1b" ) ),
			'8001-bytes-of-json'  => array( 'acme_sweep', array( \str_repeat( 'a', 7997 ) ) ),
		);
	}

	#[DataProvider( 'invalid_identities' )]
	public function test_an_invalid_identity_is_rejected( string $hook, array $args ): void {
		$this->expectException( InvalidArgumentException::class );

		new ScheduledHook( $hook, $args );
	}
}
