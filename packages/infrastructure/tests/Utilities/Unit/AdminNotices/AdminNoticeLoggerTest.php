<?php declare( strict_types=1 );

namespace DeepWebSolutions\Framework\Utilities\Tests\Unit\AdminNotices;

use DeepWebSolutions\Framework\Utilities\AdminNotices\AdminNoticeLogger;
use DeepWebSolutions\Framework\Utilities\AdminNotices\AdminNoticesService;
use DeepWebSolutions\Framework\Utilities\AdminNotices\Exceptions\InvalidNoticeIdentifierException;
use DeepWebSolutions\Framework\Utilities\AdminNotices\Exceptions\UnknownNoticeStoreException;
use DeepWebSolutions\Framework\Utilities\AdminNotices\NoticeStore;
use DeepWebSolutions\Framework\Utilities\AdminNotices\NoticeType;
use DeepWebSolutions\Framework\Utilities\AdminNotices\ValueObjects\AdminNotice;
use DeepWebSolutions\Framework\Storage\MemoryStore;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\Attributes\UsesFunction;
use PHPUnit\Framework\TestCase;
use Psr\Log\InvalidArgumentException;
use Psr\Log\LogLevel;

#[CoversClass( AdminNoticeLogger::class )]
#[UsesClass( AdminNoticesService::class )]
#[UsesClass( NoticeStore::class )]
#[UsesClass( AdminNotice::class )]
#[UsesClass( NoticeType::class )]
#[UsesClass( MemoryStore::class )]
#[UsesFunction( 'DeepWebSolutions\Framework\Utilities\AdminNotices\is_valid_notice_id' )]
final class AdminNoticeLoggerTest extends TestCase {
	public function test_error_record_queues_a_persistent_notice(): void {
		$store  = new NoticeStore( new MemoryStore() );
		$logger = new AdminNoticeLogger( $this->service_with( $store ), 'install-failure', 'failures' );

		$logger->error( 'Installation failed' );

		$notice = $store->get( 'install-failure' );
		self::assertNotNull( $notice );
		self::assertSame( 'Installation failed', $notice->message );
		self::assertSame( NoticeType::Error, $notice->type );
		self::assertTrue( $notice->persistent );
		self::assertFalse( $notice->dismissible );
	}

	public function test_records_below_the_threshold_are_dropped(): void {
		$store  = new NoticeStore( new MemoryStore() );
		$logger = new AdminNoticeLogger( $this->service_with( $store ), 'install-failure', 'failures' );

		$logger->warning( 'just a warning' );
		$logger->debug( 'noise' );

		self::assertSame( array(), $store->get_all() );
	}

	public function test_a_lower_threshold_admits_lower_levels(): void {
		$store  = new NoticeStore( new MemoryStore() );
		$logger = new AdminNoticeLogger( $this->service_with( $store ), 'heads-up', 'failures', LogLevel::WARNING );

		$logger->warning( 'heads up' );

		$notice = $store->get( 'heads-up' );
		self::assertNotNull( $notice );
		self::assertSame( NoticeType::Warning, $notice->type );
	}

	public function test_context_placeholders_are_interpolated(): void {
		$store  = new NoticeStore( new MemoryStore() );
		$logger = new AdminNoticeLogger( $this->service_with( $store ), 'migration', 'failures' );

		$logger->error( 'Migration {step} failed', array( 'step' => 'capabilities' ) );

		self::assertSame( 'Migration capabilities failed', $store->get( 'migration' )?->message );
	}

	public function test_severe_levels_map_to_the_error_type(): void {
		$store  = new NoticeStore( new MemoryStore() );
		$logger = new AdminNoticeLogger( $this->service_with( $store ), 'critical', 'failures' );

		$logger->critical( 'boom' );

		self::assertSame( NoticeType::Error, $store->get( 'critical' )?->type );
	}

	public function test_the_configured_capability_is_carried_onto_the_notice(): void {
		$store  = new NoticeStore( new MemoryStore() );
		$logger = new AdminNoticeLogger( $this->service_with( $store ), 'failure', 'failures', LogLevel::ERROR, 'manage_woocommerce' );

		$logger->error( 'failed' );

		self::assertSame( 'manage_woocommerce', $store->get( 'failure' )?->capability );
	}

	public function test_repeated_records_collapse_onto_one_notice(): void {
		$store  = new NoticeStore( new MemoryStore() );
		$logger = new AdminNoticeLogger( $this->service_with( $store ), 'install-failure', 'failures' );

		$logger->error( 'first failure' );
		$logger->error( 'second failure' );

		self::assertCount( 1, $store->get_all() );
		self::assertSame( 'second failure', $store->get( 'install-failure' )?->message );
	}

	public function test_a_throwing_stringable_context_value_does_not_break_logging(): void {
		$store  = new NoticeStore( new MemoryStore() );
		$logger = new AdminNoticeLogger( $this->service_with( $store ), 'install-failure', 'failures' );

		$throwing = new class() implements \Stringable {
			public function __toString(): string {
				throw new \RuntimeException( 'boom' );
			}
		};

		$logger->error( 'Migration {step} failed', array( 'step' => $throwing ) );

		// Logging stays lenient about context per PSR-3: the unresolvable placeholder is left in place
		// rather than letting the throw escape the log call.
		self::assertSame( 'Migration {step} failed', $store->get( 'install-failure' )?->message );
	}

	public function test_a_context_value_with_no_matching_placeholder_is_not_stringified(): void {
		$store  = new NoticeStore( new MemoryStore() );
		$logger = new AdminNoticeLogger( $this->service_with( $store ), 'install-failure', 'failures' );

		$probe = new class() implements \Stringable {
			public bool $stringified = false;

			public function __toString(): string {
				$this->stringified = true;
				return 'never used';
			}
		};

		// The kernel passes a Throwable context on every broken boot; a value whose token is absent
		// from the message must not be rendered, so an unused (costly) __toString() is never invoked.
		$logger->error( 'Installation failed', array( 'context' => $probe ) );

		self::assertFalse( $probe->stringified );
		self::assertSame( 'Installation failed', $store->get( 'install-failure' )?->message );
	}

	public function test_a_non_stringable_context_value_leaves_its_placeholder(): void {
		$store  = new NoticeStore( new MemoryStore() );
		$logger = new AdminNoticeLogger( $this->service_with( $store ), 'install-failure', 'failures' );

		$logger->error( 'Migration {step} failed', array( 'step' => array( 'not', 'stringable' ) ) );

		self::assertSame( 'Migration {step} failed', $store->get( 'install-failure' )?->message );
	}

	public function test_informational_levels_map_to_the_info_type(): void {
		$store  = new NoticeStore( new MemoryStore() );
		$logger = new AdminNoticeLogger( $this->service_with( $store ), 'note', 'failures', LogLevel::DEBUG );

		$logger->notice( 'just so you know' );

		self::assertSame( NoticeType::Info, $store->get( 'note' )?->type );
	}

	public function test_an_unknown_level_is_rejected(): void {
		$logger = new AdminNoticeLogger( $this->service_with( new NoticeStore( new MemoryStore() ) ), 'x', 'failures' );

		$this->expectException( InvalidArgumentException::class );

		$logger->log( 'verbose', 'nope' );
	}

	public function test_an_unknown_minimum_level_is_rejected_at_construction(): void {
		$this->expectException( InvalidArgumentException::class );

		new AdminNoticeLogger( $this->service_with( new NoticeStore( new MemoryStore() ) ), 'x', 'failures', 'verbose' );
	}

	public function test_an_unknown_store_is_rejected_at_construction(): void {
		$service = new AdminNoticesService( array( 'failures' => new NoticeStore( new MemoryStore() ) ) );

		$this->expectException( UnknownNoticeStoreException::class );

		new AdminNoticeLogger( $service, 'x', 'typo-store' );
	}

	public function test_an_unstable_notice_id_is_rejected_at_construction(): void {
		$this->expectException( InvalidNoticeIdentifierException::class );

		new AdminNoticeLogger( $this->service_with( new NoticeStore( new MemoryStore() ) ), 'Bad.Id', 'failures' );
	}

	private function service_with( NoticeStore $store ): AdminNoticesService {
		return new AdminNoticesService( array( 'failures' => $store ) );
	}
}
