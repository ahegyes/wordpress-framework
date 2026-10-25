<?php declare( strict_types=1 );

namespace DeepWebSolutions\Framework\Tests\Integration\Core\Storage;

use DeepWebSolutions\Framework\Core\Storage\Lock;
use DeepWebSolutions\Framework\Core\Storage\LockClaim;
use DeepWebSolutions\Framework\Core\Storage\OptionRows;
use DeepWebSolutions\Framework\Shared\Exception\InvalidArgumentException;
use DeepWebSolutions\Framework\Shared\Exception\RuntimeException;
use DeepWebSolutions\Framework\Tests\Integration\Support\InterceptsQueriesTrait;
use DeepWebSolutions\Framework\Tests\Integration\Support\IsolatesHooksTrait;
use DeepWebSolutions\Framework\Tests\Integration\Support\IsolatesStorageTrait;
use DeepWebSolutions\Framework\Tests\Integration\Support\SettableClock;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass( Lock::class )]
final class LockTest extends TestCase {
	use InterceptsQueriesTrait;
	use IsolatesHooksTrait;
	use IsolatesStorageTrait;

	protected SettableClock $clock;

	#[\Override]
	protected function setUp(): void {
		$this->clock = new SettableClock( new \DateTimeImmutable( '@1800000000' ) );
	}

	public function test_a_free_lock_is_acquired_as_a_row_naming_the_owner_the_time_and_the_ttl(): void {
		self::assertSame( LockClaim::Acquired, $this->make_lock()->acquire( 'first' ) );
		self::assertSame( self::hold( 'first', 1800000000 ), $this->stored() );
	}

	public function test_a_live_hold_is_busy_for_another_owner_and_for_its_own(): void {
		$lock = $this->make_lock();
		$lock->acquire( 'first' );
		$this->clock->advance( 60 );

		self::assertSame( LockClaim::Busy, $lock->acquire( 'second' ) );
		self::assertSame( LockClaim::Busy, $lock->acquire( 'first' ) );
		self::assertSame( self::hold( 'first', 1800000000 ), $this->stored() );
	}

	public function test_a_hold_past_its_ttl_is_taken_over(): void {
		$lock = $this->make_lock();
		$lock->acquire( 'first' );
		$this->clock->advance( 61 );

		self::assertSame( LockClaim::TakenOver, $lock->acquire( 'second' ) );
		self::assertSame( self::hold( 'second', 1800000061 ), $this->stored() );
	}

	public function test_expiry_is_judged_by_the_ttl_the_row_holds(): void {
		( new Lock( "{$this->prefix}_lock", 600, $this->clock ) )->acquire( 'first' );
		$this->clock->advance( 61 );

		self::assertSame( LockClaim::Busy, $this->make_lock()->acquire( 'second' ) );
	}

	public static function rows_that_hold_no_lock(): array {
		$hold = self::hold( 'first', 1800000000 );

		return array(
			'timestamp'     => array( 1800000000 ),
			'missing-owner' => array( \array_diff_key( $hold, array( 'owner' => true ) ) ),
			'empty-owner'   => array( array( 'owner' => '' ) + $hold ),
			'numeric-owner' => array( array( 'owner' => 7 ) + $hold ),
			'string-time'   => array( array( 'time' => '1800000000' ) + $hold ),
			'zero-time'     => array( array( 'time' => 0 ) + $hold ),
			'missing-ttl'   => array( \array_diff_key( $hold, array( 'ttl' => true ) ) ),
			'zero-ttl'      => array( array( 'ttl' => 0 ) + $hold ),
		);
	}

	#[DataProvider( 'rows_that_hold_no_lock' )]
	public function test_a_row_that_holds_no_lock_is_taken_over( mixed $value ): void {
		OptionRows::upsert( "{$this->prefix}_lock", $value );

		self::assertSame( LockClaim::TakenOver, $this->make_lock()->acquire( 'second' ) );
		self::assertSame( self::hold( 'second', 1800000000 ), $this->stored() );
	}

	public function test_a_hold_with_keys_it_does_not_know_still_holds_the_lock(): void {
		OptionRows::upsert( "{$this->prefix}_lock", self::hold( 'first', 1800000000 ) + array( 'host' => 'web-2' ) );

		self::assertSame( LockClaim::Busy, $this->make_lock()->acquire( 'second' ) );
		self::assertTrue( $this->make_lock()->refresh( 'first' ) );
	}

	public function test_of_two_takeovers_of_one_expired_hold_only_one_wins(): void {
		$lock = $this->make_lock();
		$lock->acquire( 'first' );
		$this->clock->advance( 61 );
		$claims = array();
		$this->before_query(
			'UPDATE',
			static function () use ( $lock, &$claims ): void {
				$claims['third'] = $lock->acquire( 'third' );
			}
		);

		$claims['second'] = $lock->acquire( 'second' );

		self::assertSame(
			array(
				'third'  => LockClaim::TakenOver,
				'second' => LockClaim::Busy,
			),
			$claims
		);
		self::assertSame( self::hold( 'third', 1800000061 ), $this->stored() );
	}

	public function test_refreshing_extends_the_hold_to_a_full_ttl_from_now(): void {
		$lock = $this->make_lock();
		$lock->acquire( 'first' );
		$this->clock->advance( 50 );

		self::assertTrue( $lock->refresh( 'first' ) );
		$this->clock->advance( 50 );

		self::assertSame( LockClaim::Busy, $lock->acquire( 'second' ) );
		self::assertSame( self::hold( 'first', 1800000050 ), $this->stored() );
	}

	public function test_a_refresh_within_the_second_of_the_last_write_holds_without_writing(): void {
		$lock = $this->make_lock();
		$lock->acquire( 'first' );
		$writes = array();
		\add_filter(
			'query',
			static function ( string $query ) use ( &$writes ): string {
				if ( 1 === \preg_match( '/^\s*(INSERT|UPDATE|DELETE)\b/i', $query ) ) {
					$writes[] = $query;
				}

				return $query;
			}
		);

		self::assertTrue( $lock->refresh( 'first' ) );
		self::assertSame( array(), $writes );
	}

	public function test_an_expired_hold_that_nobody_took_over_is_refreshed_by_its_owner(): void {
		$lock = $this->make_lock();
		$lock->acquire( 'first' );
		$this->clock->advance( 61 );

		self::assertTrue( $lock->refresh( 'first' ) );
		self::assertSame( self::hold( 'first', 1800000061 ), $this->stored() );
	}

	public function test_only_the_holder_refreshes_a_hold(): void {
		$lock = $this->make_lock();

		self::assertFalse( $lock->refresh( 'first' ) );
		$lock->acquire( 'first' );
		self::assertFalse( $lock->refresh( 'second' ) );
		self::assertFalse( $lock->refresh( '' ) );
		self::assertSame( self::hold( 'first', 1800000000 ), $this->stored() );
	}

	public function test_a_holder_learns_of_a_takeover_at_its_next_refresh(): void {
		$lock = $this->make_lock();
		$lock->acquire( 'first' );
		$this->clock->advance( 61 );
		$lock->acquire( 'second' );

		self::assertFalse( $lock->refresh( 'first' ) );
		self::assertSame( self::hold( 'second', 1800000061 ), $this->stored() );
	}

	public function test_a_refresh_that_races_a_takeover_loses(): void {
		$lock = $this->make_lock();
		$lock->acquire( 'first' );
		$this->clock->advance( 61 );
		$this->before_query( 'UPDATE', static fn (): LockClaim => $lock->acquire( 'second' ) );

		self::assertFalse( $lock->refresh( 'first' ) );
		self::assertSame( self::hold( 'second', 1800000061 ), $this->stored() );
	}

	public function test_releasing_deletes_only_the_holders_row(): void {
		$lock = $this->make_lock();
		$lock->acquire( 'first' );

		$lock->release( 'second' );
		self::assertSame( self::hold( 'first', 1800000000 ), $this->stored() );

		$lock->release( 'first' );
		$lock->release( 'first' );
		self::assertNull( $this->stored() );
		self::assertSame( LockClaim::Acquired, $lock->acquire( 'second' ) );
	}

	public function test_a_release_that_races_a_takeover_leaves_the_new_hold(): void {
		$lock = $this->make_lock();
		$lock->acquire( 'first' );
		$this->clock->advance( 61 );
		$this->before_query( 'DELETE', static fn (): LockClaim => $lock->acquire( 'second' ) );

		$lock->release( 'first' );

		self::assertSame( self::hold( 'second', 1800000061 ), $this->stored() );
	}

	public function test_without_a_clock_the_lock_reads_the_system_time(): void {
		$before = \time();
		( new Lock( "{$this->prefix}_lock", 60 ) )->acquire( 'first' );

		$time = $this->stored()['time'] ?? 0;
		self::assertGreaterThanOrEqual( $before, $time );
		self::assertLessThanOrEqual( \time(), $time );
	}

	public static function invalid_calls(): array {
		return array(
			'zero-ttl'     => array( static fn (): mixed => new Lock( 'acme_lock', 0 ) ),
			'negative-ttl' => array( static fn (): mixed => new Lock( 'acme_lock', -60 ) ),
			'empty-name'   => array( static fn (): mixed => new Lock( '', 60 ) ),
			'empty-owner'  => array( static fn (): mixed => ( new Lock( 'acme_lock', 60 ) )->acquire( '' ) ),
		);
	}

	#[DataProvider( 'invalid_calls' )]
	public function test_a_ttl_below_1_an_invalid_name_or_an_empty_owner_is_rejected( \Closure $call ): void {
		$this->expectException( InvalidArgumentException::class );

		$call();
	}

	public static function failing_operations(): array {
		return array(
			'acquire' => array( 'INSERT IGNORE', static fn ( Lock $lock ): mixed => $lock->acquire( 'second' ) ),
			'refresh' => array( 'SELECT option_value', static fn ( Lock $lock ): mixed => $lock->refresh( 'first' ) ),
			'release' => array( 'DELETE FROM', static fn ( Lock $lock ): mixed => $lock->release( 'first' ) ),
		);
	}

	#[DataProvider( 'failing_operations' )]
	public function test_a_storage_failure_throws( string $needle, \Closure $operation ): void {
		$lock = $this->make_lock();
		$lock->acquire( 'first' );
		$this->fail_queries( $needle );

		$this->expectException( RuntimeException::class );

		$operation( $lock );
	}

	protected function make_lock(): Lock {
		return new Lock( "{$this->prefix}_lock", 60, $this->clock );
	}

	protected function stored(): mixed {
		return OptionRows::get( "{$this->prefix}_lock" )?->value;
	}

	protected static function hold( string $owner, int $time ): array {
		return array(
			'owner' => $owner,
			'time'  => $time,
			'ttl'   => 60,
		);
	}
}
