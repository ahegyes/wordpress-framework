<?php declare( strict_types=1 );

namespace DeepWebSolutions\Framework\Tests\Unit\Core\Notices;

use DeepWebSolutions\Framework\Notices\Internal\NoticeRecords;
use DeepWebSolutions\Framework\Notices\Notice;
use DeepWebSolutions\Framework\Notices\NoticeContext;
use DeepWebSolutions\Framework\Notices\NoticeScope;
use DeepWebSolutions\Framework\Notices\StoredNotice;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass( NoticeRecords::class )]
final class NoticeRecordsTest extends TestCase {
	public function test_a_record_is_its_notice_row_with_its_generation_and_creation_time(): void {
		$record = $this->record( 'saved', 1_800_000_000_123_456 );

		$row = NoticeRecords::encode( $record );

		self::assertSame(
			$record->notice->to_array() + array(
				'gen'        => 'gen-saved',
				'created_at' => 1_800_000_000_123_456,
			),
			$row
		);
		self::assertEquals( $record, NoticeRecords::decode( $row, $record->context ) );
	}

	public static function rejected_rows(): array {
		$row = NoticeRecords::encode( new StoredNotice( new Notice( 'a', 'Hi' ), new NoticeContext( NoticeScope::User, 1, 1 ), 5, 'gen' ) );

		return array(
			'not-an-array'           => array( 'a:1:{s:1:"v";i:1;}' ),
			'array-object'           => array( new \ArrayObject( $row ) ),
			'no-generation'          => array( \array_diff_key( $row, array( 'gen' => true ) ) ),
			'generation-not-string'  => array( array( 'gen' => 7 ) + $row ),
			'no-creation-time'       => array( \array_diff_key( $row, array( 'created_at' => true ) ) ),
			'creation-time-a-string' => array( array( 'created_at' => '5' ) + $row ),
			'invalid-notice'         => array( array( 'revision' => 0 ) + $row ),
			'notice-of-other-scope'  => array( array( 'scope' => 'request' ) + $row ),
		);
	}

	#[DataProvider( 'rejected_rows' )]
	public function test_a_row_that_is_not_a_valid_record_of_the_scope_is_rejected( mixed $row ): void {
		self::assertNull( NoticeRecords::decode( $row, new NoticeContext( NoticeScope::User, 1, 1 ) ) );
	}

	public function test_records_sort_oldest_first_and_by_id_among_equals(): void {
		$records = array( $this->record( 'c', 2 ), $this->record( 'b', 1 ), $this->record( 'a', 2 ) );

		$sorted = NoticeRecords::sort(
			array(
				'c' => $records[0],
				'b' => $records[1],
				'a' => $records[2],
			)
		);

		self::assertSame( array( $records[1], $records[2], $records[0] ), $sorted );
	}

	public static function expiries(): array {
		return array(
			'never'       => array( null, false ),
			'an-hour-ago' => array( -3600, true ),
			'this-second' => array( 0, true ),
			'in-an-hour'  => array( 3600, false ),
		);
	}

	#[DataProvider( 'expiries' )]
	public function test_a_notice_expires_at_its_expiry_time( ?int $offset, bool $expired ): void {
		$notice = new Notice( 'a', 'Hi', expires_at: \is_null( $offset ) ? null : \time() + $offset );

		self::assertSame( $expired, NoticeRecords::is_expired( new StoredNotice( $notice, new NoticeContext( NoticeScope::User, 1, 1 ), 1, 'gen' ) ) );
	}

	public function test_now_is_the_unix_time_in_microseconds(): void {
		$before = \time();
		$now    = NoticeRecords::now();

		self::assertGreaterThanOrEqual( $before * 1_000_000, $now );
		self::assertLessThan( ( \time() + 1 ) * 1_000_000, $now );
	}

	protected function record( string $id, int $created_at ): StoredNotice {
		return new StoredNotice( new Notice( $id, 'Hi' ), new NoticeContext( NoticeScope::User, 1, 1 ), $created_at, "gen-$id" );
	}
}
