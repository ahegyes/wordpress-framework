<?php declare( strict_types=1 );

namespace DeepWebSolutions\Framework\Tests\Integration\Core\Scheduling;

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
			'invalid-utf-8'      => array( 'acme_sweep', array( "a\xB1b" ) ),
			'8000-bytes-of-json' => array( 'acme_sweep', array( \str_repeat( 'a', 7996 ) ) ),
		);
	}

	#[DataProvider( 'valid_identities' )]
	public function test_a_valid_identity_keeps_its_hook_and_arguments( string $hook, array $args ): void {
		$scheduled_hook = new ScheduledHook( $hook, $args );

		self::assertSame( $hook, $scheduled_hook->hook );
		self::assertSame( $args, $scheduled_hook->args );
	}

	public function test_arguments_over_8000_bytes_of_json_are_rejected(): void {
		$this->expectException( InvalidArgumentException::class );

		new ScheduledHook( 'acme_sweep', array( \str_repeat( 'a', 7997 ) ) );
	}

	public function test_hooks_are_equal_when_their_names_and_arguments_are(): void {
		$hook = new ScheduledHook( 'acme_sweep', array( 1, 'one' ) );

		self::assertTrue( $hook->equals( new ScheduledHook( 'acme_sweep', array( 1, 'one' ) ) ) );
		self::assertFalse( $hook->equals( new ScheduledHook( 'acme_sweep', array( '1', 'one' ) ) ) );
		self::assertFalse( $hook->equals( new ScheduledHook( 'acme_other', array( 1, 'one' ) ) ) );
	}
}
