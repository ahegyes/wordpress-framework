<?php declare( strict_types=1 );

namespace DeepWebSolutions\Framework\Tests\Integration\Core;

use DeepWebSolutions\Framework\BatchedMigrationStepInterface;
use DeepWebSolutions\Framework\MigrationRunner;
use DeepWebSolutions\Framework\Scheduling\Backends\ActionSchedulerBackend;
use DeepWebSolutions\Framework\Scheduling\Backends\WpCronBackend;
use DeepWebSolutions\Framework\Scheduling\Scheduler;
use DeepWebSolutions\Framework\Scheduling\SchedulerInterface;
use DeepWebSolutions\Framework\Shared\Exception\LogicException;
use DeepWebSolutions\Framework\Tests\Integration\Support\CreatesUsersTrait;
use DeepWebSolutions\Framework\Tests\Integration\Support\IsolatesHooksTrait;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Clock\ClockInterface as PsrClockInterface;
use Psr\Log\AbstractLogger as PsrAbstractLogger;
use Psr\Log\LoggerInterface as PsrLoggerInterface;

final class MigrationLogger extends PsrAbstractLogger {
	public array $records = array();

	#[\Override]
	public function log( mixed $level, string|\Stringable $message, array $context = array() ): void {
		$this->records[] = array( $level, (string) $message, $context );
	}
}

final class FailingMigrationLogger extends PsrAbstractLogger {
	#[\Override]
	public function log( mixed $level, string|\Stringable $message, array $context = array() ): void {
		throw new \UnexpectedValueException( 'The log is not writable.' );
	}
}

final class SettableClock implements PsrClockInterface {
	public function __construct(
		public \DateTimeImmutable $now
	) {}

	#[\Override]
	public function now(): \DateTimeImmutable {
		return $this->now;
	}

	public function advance( int $seconds ): void {
		$this->now = $this->now->modify( "+$seconds seconds" );
	}
}

final class RecordedBatches implements BatchedMigrationStepInterface {
	public int $processed = 0;

	public function __construct(
		protected int $batches,
		protected ?\Closure $after_each = null
	) {}

	#[\Override]
	public function process_batch(): bool {
		++$this->processed;
		if ( ! \is_null( $this->after_each ) ) {
			( $this->after_each )( $this->processed );
		}

		return $this->processed < $this->batches;
	}
}

#[CoversClass( MigrationRunner::class )]
final class MigrationRunnerTest extends TestCase {
	use CreatesUsersTrait;
	use IsolatesHooksTrait;

	protected const string PLUGIN_FILE = __DIR__ . '/../../Fixtures/Core/acme-boot.php';

	protected const string ERROR_NOTICE = 'Acme Boot could not update its data, so features that need the update stay off. Error: ';

	protected const string PROGRESS_NOTICE = 'Acme Boot is updating its data in the background.';

	protected string $option;

	protected MigrationLogger $logger;

	protected array $ran = array();

	protected array $writes = array();

	#[\Override]
	protected function setUp(): void {
		$this->option = 'acme_db_' . \bin2hex( \random_bytes( 4 ) );
		$this->logger = new MigrationLogger();
	}

	#[\Override]
	protected function tearDown(): void {
		global $wpdb;

		foreach ( array( '', '_error', '_high_water', '_first_run', '_lock' ) as $suffix ) {
			\delete_option( $this->option . $suffix );
		}
		\wp_unschedule_hook( "{$this->option}_run" );
		\wp_set_current_user( 0 );
		unset( $_REQUEST['_wpnonce'], $_SERVER['HTTP_REFERER'] );
		$wpdb->delete( $wpdb->actionscheduler_actions, array( 'hook' => "{$this->option}_run" ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Removes the actions the test created.
	}

	public function test_pending_steps_run_once_each_in_order_with_a_checkpoint_after_each(): void {
		$this->load_wordpress( $this->make_runner( array( $this->step( 1 ), $this->step( 2 ), $this->step( 3 ) ) ) );

		self::assertSame( array( array( 1, 0 ), array( 2, 1 ), array( 3, 2 ) ), $this->ran );
		self::assertSame( 3, MigrationRunner::get_revision( $this->option ) );
	}

	public function test_steps_below_the_stored_revision_never_run(): void {
		\update_option( $this->option, 1 );

		$this->load_wordpress( $this->make_runner( array( $this->step( 1 ), $this->step( 2 ), $this->step( 3 ) ) ) );

		self::assertSame( array( array( 2, 1 ), array( 3, 2 ) ), $this->ran );
		self::assertSame( 3, MigrationRunner::get_revision( $this->option ) );
	}

	public static function current_revisions(): array {
		return array(
			'database-string'  => array( true ),
			'object-cache-int' => array( false ),
		);
	}

	#[DataProvider( 'current_revisions' )]
	public function test_a_current_revision_runs_nothing_and_writes_nothing( bool $from_database ): void {
		\update_option( $this->option, 3 );
		if ( $from_database ) {
			\wp_cache_delete( 'alloptions', 'options' );
			\wp_cache_delete( $this->option, 'options' );
		}
		$this->record_option_writes();

		$this->load_wordpress( $this->make_runner( array( $this->step( 1 ), $this->step( 2 ), $this->step( 3 ) ) ) );

		self::assertSame( array(), $this->ran );
		self::assertSame( array(), $this->writes );
	}

	public function test_a_step_can_insert_a_post_of_a_type_registered_on_init(): void {
		\register_post_type( 'acme_note' );
		$post_id = 0;
		$runner  = $this->make_runner(
			array(
				static function () use ( &$post_id ): void {
					$post_id = \wp_insert_post(
						array(
							'post_type'   => 'acme_note',
							'post_title'  => 'Acme note',
							'post_status' => 'publish',
						),
						true
					);
				},
			)
		);

		try {
			$this->load_wordpress( $runner );

			self::assertIsInt( $post_id );
			self::assertSame( 'acme_note', \get_post_type( $post_id ) );
		} finally {
			\is_int( $post_id ) && \wp_delete_post( $post_id, true );
			\unregister_post_type( 'acme_note' );
		}
	}

	public function test_two_connections_contending_run_the_steps_once(): void {
		$second = new \wpdb( DB_USER, DB_PASSWORD, DB_NAME, DB_HOST );
		$second->set_prefix( $GLOBALS['wpdb']->prefix );
		$contender = $this->make_runner( array( $this->step( 'contender' ) ), $second );
		$runner    = $this->make_runner(
			array(
				function () use ( $contender ): void {
					$this->ran[] = 'holder';
					$contender->run();
				},
			)
		);

		$runner->run();

		self::assertSame( array( 'holder' ), $this->ran );
		self::assertSame( 1, MigrationRunner::get_revision( $this->option ) );
	}

	public function test_a_lock_older_than_ten_minutes_is_taken_over(): void {
		$clock = new SettableClock( new \DateTimeImmutable( '@1800000000' ) );
		$this->insert_lock( 1800000000 - 601 );

		$this->make_runner( array( $this->step( 1 ) ), null, $clock )->run();

		self::assertSame( array( array( 1, 0 ) ), $this->ran );
		self::assertNull( $this->get_lock() );
	}

	public function test_a_lock_ten_minutes_old_or_younger_skips_the_run(): void {
		$clock = new SettableClock( new \DateTimeImmutable( '@1800000000' ) );
		$this->insert_lock( 1800000000 - 600 );

		$this->make_runner( array( $this->step( 1 ) ), null, $clock )->run();

		self::assertSame( array(), $this->ran );
		self::assertSame( (string) ( 1800000000 - 600 ), $this->get_lock() );
	}

	public function test_a_failed_revision_read_stops_the_run_with_an_error(): void {
		global $wpdb;

		\add_filter(
			'query',
			function ( string $query ): string {
				return \str_starts_with( \trim( $query ), 'SELECT option_value' ) && \str_contains( $query, "'{$this->option}'" ) ? 'SELECT option_value FROM acme_missing_table' : $query;
			}
		);
		$suppressed = $wpdb->suppress_errors();

		try {
			$this->make_runner( array( $this->step( 1 ) ) )->run();
		} finally {
			$wpdb->suppress_errors( $suppressed );
		}

		self::assertSame( array(), $this->ran );
		$error = \get_option( "{$this->option}_error" );
		self::assertIsArray( $error );
		self::assertStringStartsWith( 'Could not read the stored data revision: ', $error['message'] );
	}

	public function test_taking_over_a_lock_with_no_error_row_records_the_step_that_did_not_finish(): void {
		\update_option( $this->option, 1 );
		$this->insert_lock( \time() - 601 );
		$seen   = null;
		$runner = $this->make_runner(
			array(
				$this->step( 1 ),
				function () use ( &$seen ): void {
					$seen = \get_option( "{$this->option}_error" );
				},
			)
		);

		$runner->run();

		self::assertIsArray( $seen );
		self::assertSame( 'Step 2 did not finish within 10 minutes.', $seen['message'] );
		self::assertSame( 1, $seen['revision'] );
		self::assertSame( array( 'warning' ), \array_column( $this->logger->records, 0 ) );
	}

	public function test_a_lock_insert_that_fails_skips_the_run_without_a_takeover(): void {
		global $wpdb;

		$statements = array();
		\add_filter(
			'query',
			function ( string $query ) use ( &$statements ): string {
				if ( \str_contains( $query, "{$this->option}_lock" ) ) {
					$statements[] = \strtok( \trim( $query ), ' ' );
					if ( \str_starts_with( \trim( $query ), 'INSERT IGNORE' ) ) {
						return 'INSERT IGNORE INTO acme_missing_table (option_name) VALUES (1)';
					}
				}

				return $query;
			}
		);
		$suppressed = $wpdb->suppress_errors();

		try {
			$this->make_runner( array( $this->step( 1 ) ) )->run();
		} finally {
			$wpdb->suppress_errors( $suppressed );
		}

		self::assertSame( array( 'INSERT' ), $statements );
		self::assertSame( array(), $this->ran );
		self::assertSame( array( 'warning' ), \array_column( $this->logger->records, 0 ) );
	}

	public static function malformed_revisions(): array {
		return array(
			'letters'  => array( 'abc' ),
			'negative' => array( '-1' ),
			'empty'    => array( '' ),
		);
	}

	#[DataProvider( 'malformed_revisions' )]
	public function test_a_malformed_stored_revision_stops_the_run_with_an_error( string $stored ): void {
		\update_option( $this->option, $stored );

		self::assertSame( 0, MigrationRunner::get_revision( $this->option ) );
		$this->make_runner( array( $this->step( 1 ) ) )->run();

		self::assertSame( array(), $this->ran );
		$error = \get_option( "{$this->option}_error" );
		self::assertIsArray( $error );
		self::assertStringContainsString( "'$stored'", $error['message'] );
		self::assertNull( $error['revision'] );
	}

	public function test_an_unconfirmed_checkpoint_stops_the_run_with_an_error(): void {
		\add_filter( "pre_update_option_{$this->option}", static fn ( mixed $value, mixed $old_value ): mixed => $old_value, 10, 2 );

		$this->make_runner( array( $this->step( 1 ), $this->step( 2 ) ) )->run();

		self::assertSame( array( array( 1, 0 ) ), $this->ran );
		$error = \get_option( "{$this->option}_error" );
		self::assertIsArray( $error );
		self::assertSame( "Could not record schema revision '1'.", $error['message'] );
	}

	public function test_a_resumable_step_rerun_after_an_interruption_creates_no_duplicate(): void {
		$interrupt = true;
		$step      = function () use ( &$interrupt ): void {
			$existing = \get_posts(
				array(
					'post_type'   => 'post',
					'post_status' => 'any',
					'meta_key'    => '_acme_marker', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- The step's lookup marker.
					'meta_value'  => $this->option, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value -- The step's lookup marker.
					'fields'      => 'ids',
				)
			);
			$post_id  = $existing[0] ?? \wp_insert_post(
				array(
					'post_title'  => 'Acme resource',
					'post_author' => 1,
					'meta_input'  => array( '_acme_marker' => $this->option ),
				)
			);
			if ( $interrupt ) {
				$interrupt = false;
				throw new \RuntimeException( 'Interrupted before recording the id.' );
			}
			\update_option( "{$this->option}_resource", $post_id );
		};

		try {
			$this->make_runner( array( $step ) )->run();
			$this->age_lock();
			$this->make_runner( array( $step ) )->run();

			$posts = \get_posts(
				array(
					'post_type'   => 'post',
					'post_status' => 'any',
					'meta_key'    => '_acme_marker', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- The step's lookup marker.
					'meta_value'  => $this->option, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value -- The step's lookup marker.
					'fields'      => 'ids',
				)
			);
			self::assertCount( 1, $posts );
			self::assertSame( $posts[0], \get_option( "{$this->option}_resource" ) );
			self::assertSame( 1, MigrationRunner::get_revision( $this->option ) );
		} finally {
			foreach ( $posts ?? array() as $post_id ) {
				\wp_delete_post( $post_id, true );
			}
			\delete_option( "{$this->option}_resource" );
		}
	}

	public function test_a_failing_step_records_and_logs_its_error_and_keeps_the_lock(): void {
		$clock   = new SettableClock( new \DateTimeImmutable( '@1800000000' ) );
		$failure = new \RuntimeException( 'Step two failed.' );
		$steps   = array(
			$this->step( 1 ),
			static function () use ( $failure ): void {
				throw $failure;
			},
		);

		$this->make_runner( $steps, null, $clock )->run();
		$this->make_runner( $steps, null, $clock )->run();

		self::assertSame( array( array( 1, 0 ) ), $this->ran, 'The second request skipped while the lock held.' );
		self::assertSame( 1, MigrationRunner::get_revision( $this->option ) );
		self::assertSame(
			array(
				'v'        => 1,
				'revision' => 1,
				'message'  => 'Step two failed.',
				'since'    => 1800000000,
				'at'       => 1800000000,
			),
			\get_option( "{$this->option}_error" )
		);
		self::assertCount( 1, $this->logger->records );
		self::assertSame( 'error', $this->logger->records[0][0] );
		self::assertSame( $failure, $this->logger->records[0][2]['exception'] );
		self::assertSame( '1800000000', $this->get_lock() );
	}

	public function test_a_repeated_failure_keeps_the_time_of_the_first(): void {
		$clock = new SettableClock( new \DateTimeImmutable( '@1800000000' ) );
		$steps = array(
			static function (): void {
				throw new \RuntimeException( 'Still failing.' );
			},
		);

		$this->make_runner( $steps, null, $clock )->run();
		$clock->advance( 700 );
		$this->make_runner( $steps, null, $clock )->run();

		$error = \get_option( "{$this->option}_error" );
		self::assertIsArray( $error );
		self::assertSame( 'Still failing.', $error['message'] );
		self::assertSame( 1800000000, $error['since'] );
		self::assertSame( 1800000700, $error['at'] );
		self::assertSame( array( 'error', 'error' ), \array_column( $this->logger->records, 0 ) );
	}

	public function test_a_run_whose_lock_is_taken_over_stops_before_its_checkpoint_and_leaves_the_new_lock(): void {
		$later    = new SettableClock( new \DateTimeImmutable( '@' . ( \time() + 700 ) ) );
		$takeover = $this->make_runner( array( $this->step( 'takeover' ), $this->step( 'takeover' ) ), null, $later );
		$runner   = $this->make_runner(
			array(
				function () use ( $takeover ): void {
					$this->ran[] = 'first';
					$takeover->run();
				},
				$this->step( 'never' ),
			)
		);

		$runner->run();

		self::assertSame( array( 'first', array( 'takeover', 0 ), array( 'takeover', 1 ) ), $this->ran );
		self::assertSame( 2, MigrationRunner::get_revision( $this->option ) );
		self::assertNull( $this->get_lock(), 'The takeover released its own lock.' );
		self::assertContains( 'Another run took over the data migration lock, so this run stopped at revision {revision}.', \array_column( $this->logger->records, 1 ) );
	}

	public function test_a_run_whose_lock_is_taken_over_leaves_the_new_lock_in_place(): void {
		$later    = new SettableClock( new \DateTimeImmutable( '@' . ( \time() + 700 ) ) );
		$takeover = $this->make_runner(
			array(
				static function (): void {
					throw new \RuntimeException( 'The takeover failed and kept its lock.' );
				},
			),
			null,
			$later
		);
		$runner   = $this->make_runner(
			array(
				static function () use ( $takeover ): void {
					$takeover->run();
				},
			)
		);

		$runner->run();

		self::assertSame( (string) $later->now->getTimestamp(), $this->get_lock() );
		self::assertSame( 0, MigrationRunner::get_revision( $this->option ) );
	}

	public function test_a_revision_above_the_code_is_written_back_without_running_a_step(): void {
		\update_option( $this->option, 3 );
		\update_option( "{$this->option}_high_water", 3 );

		$this->make_runner( array( $this->step( 1 ), $this->step( 2 ) ) )->run();

		self::assertSame( array(), $this->ran );
		self::assertSame( 2, MigrationRunner::get_revision( $this->option ) );
		self::assertSame( 3, \get_option( "{$this->option}_high_water" ) );
		self::assertSame( array( 'warning' ), \array_column( $this->logger->records, 0 ) );
	}

	public function test_a_revision_above_the_code_is_left_alone_while_another_run_holds_the_lock(): void {
		\update_option( $this->option, 3 );
		$this->insert_lock( \time() - 5 );

		$this->make_runner( array( $this->step( 1 ), $this->step( 2 ) ) )->run();

		self::assertSame( 3, MigrationRunner::get_revision( $this->option ) );
		self::assertSame( array(), $this->logger->records );
	}

	public function test_the_high_water_mark_records_the_highest_revision_reached(): void {
		$this->make_runner( array( $this->step( 1 ), $this->step( 2 ) ) )->run();
		self::assertSame( 2, \get_option( "{$this->option}_high_water" ) );

		\update_option( $this->option, 0 );
		$this->make_runner( array( $this->step( 1 ) ) )->run();

		self::assertSame( 2, \get_option( "{$this->option}_high_water" ) );
	}

	public function test_a_success_after_a_failure_clears_the_error_and_the_lock(): void {
		$fail  = true;
		$steps = array(
			static function () use ( &$fail ): void {
				if ( $fail ) {
					throw new \RuntimeException( 'Not yet.' );
				}
			},
		);
		$this->make_runner( $steps )->run();
		$fail = false;
		$this->age_lock();

		$this->make_runner( $steps )->run();

		self::assertFalse( \get_option( "{$this->option}_error" ) );
		self::assertNull( $this->get_lock() );
		self::assertSame( 1, MigrationRunner::get_revision( $this->option ) );
	}

	public function test_the_first_run_is_recorded_once_when_no_revision_is_stored(): void {
		$clock = new SettableClock( new \DateTimeImmutable( '@1800000000' ) );

		$this->make_runner( array( $this->step( 1 ) ), null, $clock )->run();
		$clock->advance( 60 );
		$this->make_runner( array( $this->step( 1 ), $this->step( 2 ) ), null, $clock )->run();

		self::assertSame(
			array(
				'v'  => 1,
				'at' => 1800000000,
			),
			\get_option( "{$this->option}_first_run" )
		);
	}

	public function test_no_first_run_is_recorded_when_a_revision_is_stored(): void {
		\update_option( $this->option, 0 );

		$this->make_runner( array( $this->step( 1 ) ) )->run();

		self::assertFalse( \get_option( "{$this->option}_first_run" ) );
	}

	public function test_a_failure_whose_logger_throws_still_reaches_the_error_log(): void {
		$log_file = (string) \tempnam( \sys_get_temp_dir(), 'acme-log-' );
		$previous = \ini_set( 'error_log', $log_file );
		$runner   = $this->make_runner(
			array(
				static function (): void {
					throw new \RuntimeException( 'Step failed with a broken logger.' );
				},
			),
			null,
			null,
			new FailingMigrationLogger()
		);

		try {
			$runner->run();
			$log = (string) \file_get_contents( $log_file );
		} finally {
			\ini_set( 'error_log', (string) $previous );
			\wp_delete_file( $log_file );
		}

		self::assertStringContainsString( 'Data migration failed: Step failed with a broken logger.', $log );
		self::assertStringContainsString( 'The log is not writable.', $log );
		self::assertIsArray( \get_option( "{$this->option}_error" ) );
	}

	public function test_a_warning_whose_logger_throws_reaches_the_error_log_without_breaking_the_request(): void {
		global $wpdb;

		\add_filter(
			'query',
			function ( string $query ): string {
				return \str_starts_with( \trim( $query ), 'INSERT IGNORE' ) && \str_contains( $query, "{$this->option}_lock" ) ? 'INSERT IGNORE INTO acme_missing_table (option_name) VALUES (1)' : $query;
			}
		);
		$suppressed = $wpdb->suppress_errors();
		$log_file   = (string) \tempnam( \sys_get_temp_dir(), 'acme-log-' );
		$previous   = \ini_set( 'error_log', $log_file );

		try {
			$this->make_runner( array( $this->step( 1 ) ), null, null, new FailingMigrationLogger() )->run();
			$log = (string) \file_get_contents( $log_file );
		} finally {
			\ini_set( 'error_log', (string) $previous );
			\wp_delete_file( $log_file );
			$wpdb->suppress_errors( $suppressed );
		}

		self::assertStringContainsString( "[{$this->option}.warning] Could not take the data migration lock: ", $log );
		self::assertStringContainsString( 'The log is not writable.', $log );
		self::assertSame( array(), $this->ran );
	}

	public function test_a_missing_database_handle_fails_at_construction(): void {
		$wpdb            = $GLOBALS['wpdb'];
		$GLOBALS['wpdb'] = null;

		try {
			$this->expectException( LogicException::class );
			$this->make_runner( array() );
		} finally {
			$GLOBALS['wpdb'] = $wpdb;
		}
	}


	public function test_a_request_outside_the_runner_hook_runs_plain_steps_but_no_batch_and_leaves_one_pending_run(): void {
		$batches = new RecordedBatches( 3 );
		$steps   = array( $this->step( 1 ), $batches );

		$this->load_wordpress( $this->make_runner( $steps ) );
		$this->load_wordpress( $this->make_runner( $steps ) );

		self::assertSame( array( array( 1, 0 ) ), $this->ran );
		self::assertSame( 0, $batches->processed );
		self::assertSame( 1, MigrationRunner::get_revision( $this->option ) );
		self::assertSame( 1, $this->count_pending_runs() );
		self::assertNull( $this->get_lock() );
	}

	public function test_the_runner_hook_runs_batches_until_the_step_finishes(): void {
		$batches = new RecordedBatches( 3 );
		$this->make_runner( array( $batches, $this->step( 2 ) ) )->register_hooks();

		\do_action( "{$this->option}_run" );

		self::assertSame( 3, $batches->processed );
		self::assertSame( array( array( 2, 1 ) ), $this->ran );
		self::assertSame( 2, MigrationRunner::get_revision( $this->option ) );
		self::assertSame( 0, $this->count_pending_runs() );
		self::assertNull( $this->get_lock() );
	}

	public function test_a_spent_budget_releases_the_lock_before_it_enqueues_one_continuation(): void {
		$clock   = new SettableClock( new \DateTimeImmutable( '@' . \time() ) );
		$batches = new RecordedBatches( 5, static fn () => $clock->advance( 10 ) );
		$this->make_runner( array( $batches ), null, $clock )->register_hooks();
		$locks = array();
		\add_filter(
			'schedule_event',
			function ( mixed $event ) use ( &$locks ): mixed {
				$locks[] = $this->get_lock();

				return $event;
			}
		);

		\do_action( "{$this->option}_run" );

		self::assertSame( 2, $batches->processed );
		self::assertSame( array( null ), $locks );
		self::assertSame( 1, $this->count_pending_runs() );
		self::assertSame( 0, MigrationRunner::get_revision( $this->option ) );
		self::assertFalse( \get_option( "{$this->option}_error" ) );
	}

	public function test_a_batched_step_spans_requests_while_its_revision_stays_behind(): void {
		$clock     = new SettableClock( new \DateTimeImmutable( '@' . \time() ) );
		$batches   = new RecordedBatches( 5, static fn () => $clock->advance( 10 ) );
		$revisions = array();

		for ( $request = 1; $request <= 3; ++$request ) {
			\remove_all_actions( "{$this->option}_run" );
			$this->make_runner( array( $batches ), null, $clock )->register_hooks();
			\do_action( "{$this->option}_run" );
			$revisions[] = array( $batches->processed, MigrationRunner::get_revision( $this->option ) );
		}

		self::assertSame( array( array( 2, 0 ), array( 4, 0 ), array( 5, 1 ) ), $revisions );
	}

	public function test_one_deadline_covers_the_wp_loaded_pass_and_the_runner_hook_in_one_request(): void {
		$clock   = new SettableClock( new \DateTimeImmutable( '@' . \time() ) );
		$batches = new RecordedBatches( 5, static fn () => $clock->advance( 10 ) );
		$runner  = $this->make_runner(
			array(
				static function () use ( $clock ): void {
					$clock->advance( 15 );
				},
				$batches,
			),
			null,
			$clock
		);

		$this->load_wordpress( $runner );
		\do_action( "{$this->option}_run" );

		self::assertSame( 1, $batches->processed );
		self::assertSame( 1, MigrationRunner::get_revision( $this->option ) );
	}

	public function test_a_batched_run_whose_lock_is_taken_over_stops_before_its_next_batch(): void {
		$later    = new SettableClock( new \DateTimeImmutable( '@' . ( \time() + 700 ) ) );
		$takeover = $this->make_runner(
			array(
				static function (): void {
					throw new \RuntimeException( 'The takeover keeps its lock.' );
				},
			),
			null,
			$later
		);
		$batches  = new RecordedBatches( 3, static fn ( int $processed ) => 1 === $processed && $takeover->run() );
		$this->make_runner( array( $batches ) )->register_hooks();

		\do_action( "{$this->option}_run" );

		self::assertSame( 1, $batches->processed );
		self::assertSame( (string) $later->now->getTimestamp(), $this->get_lock() );
		self::assertSame( 'The takeover keeps its lock.', \get_option( "{$this->option}_error" )['message'] );
	}

	public function test_a_run_that_fails_after_its_lock_was_taken_over_leaves_the_new_error(): void {
		$later    = new SettableClock( new \DateTimeImmutable( '@' . ( \time() + 700 ) ) );
		$takeover = $this->make_runner(
			array(
				static function (): void {
					throw new \RuntimeException( 'The takeover failed.' );
				},
			),
			null,
			$later
		);
		$runner   = $this->make_runner(
			array(
				static function () use ( $takeover ): void {
					$takeover->run();
					throw new \RuntimeException( 'The first run failed late.' );
				},
			)
		);

		$runner->run();

		self::assertSame( 'The takeover failed.', \get_option( "{$this->option}_error" )['message'] );
		self::assertContains( 'The first run failed late.', \array_column( \array_column( $this->logger->records, 2 ), 'message' ) );
	}

	public function test_the_runtime_cache_is_flushed_after_every_batch(): void {
		$cached = array();
		$batch  = static function ( int $processed ) use ( &$cached ): void {
			$cached[] = \wp_cache_get( 'acme_batch', 'acme' );
			\wp_cache_set( 'acme_batch', $processed, 'acme' );
		};
		$this->make_runner( array( new RecordedBatches( 2, $batch ), new RecordedBatches( 1, $batch ) ) )->register_hooks();

		\do_action( "{$this->option}_run" );

		self::assertSame( array( false, false, false ), $cached );
	}

	public function test_a_failed_continuation_is_logged_as_a_warning(): void {
		$scheduler = new Scheduler( 'acme', array( new ActionSchedulerBackend( 'acme', '99.0.0' ) ) );

		$this->load_wordpress( $this->make_runner( array( new RecordedBatches( 3 ) ), null, null, null, $scheduler ) );

		self::assertSame( array( 'warning' ), \array_column( $this->logger->records, 0 ) );
		self::assertSame( 'No scheduler takes writes.', $this->logger->records[0][2]['error'] );
		self::assertNull( $this->get_lock() );
	}

	public function test_a_spent_budget_stops_between_plain_steps_without_an_error_row(): void {
		$clock = new SettableClock( new \DateTimeImmutable( '@' . \time() ) );
		$step  = function ( int $name ) use ( $clock ): \Closure {
			return function () use ( $clock, $name ): void {
				$this->ran[] = $name;
				$clock->advance( 15 );
			};
		};

		$this->load_wordpress( $this->make_runner( array( $step( 1 ), $step( 2 ), $step( 3 ) ), null, $clock ) );

		self::assertSame( array( 1, 2 ), $this->ran );
		self::assertSame( 2, MigrationRunner::get_revision( $this->option ) );
		self::assertFalse( \get_option( "{$this->option}_error" ) );
		self::assertSame( 1, $this->count_pending_runs() );
	}

	public static function memory_limits(): array {
		return array(
			'past-90-percent' => array( true, 1 ),
			'unlimited'       => array( false, 3 ),
		);
	}

	#[DataProvider( 'memory_limits' )]
	public function test_memory_past_90_percent_of_the_limit_stops_the_loop( bool $tight, int $processed ): void {
		$batches = new RecordedBatches( 3 );
		$this->make_runner( array( $batches ) )->register_hooks();
		$previous = (string) \ini_get( 'memory_limit' );
		\ini_set( 'memory_limit', $tight ? (string) (int) ( \memory_get_usage( true ) / 0.93 ) : '-1' );

		try {
			\do_action( "{$this->option}_run" );
		} finally {
			\ini_set( 'memory_limit', $previous );
		}

		self::assertSame( $processed, $batches->processed );
	}

	public function test_the_runner_hook_returns_without_enqueueing_while_another_run_holds_the_lock(): void {
		$batches = new RecordedBatches( 3 );
		$this->insert_lock( \time() - 5 );
		$this->make_runner( array( $batches ) )->register_hooks();

		\do_action( "{$this->option}_run" );

		self::assertSame( 0, $batches->processed );
		self::assertSame( 0, $this->count_pending_runs() );
	}

	public function test_a_batch_that_throws_records_the_error_and_keeps_the_revision(): void {
		$batches = new RecordedBatches(
			5,
			static function ( int $processed ): void {
				if ( 2 === $processed ) {
					throw new \RuntimeException( 'The second batch failed.' );
				}
			}
		);
		$this->make_runner( array( $this->step( 1 ), $batches ) )->register_hooks();
		\update_option( $this->option, 1 );

		\do_action( "{$this->option}_run" );

		$error = \get_option( "{$this->option}_error" );
		self::assertIsArray( $error );
		self::assertSame( 1, $error['revision'] );
		self::assertSame( 'The second batch failed.', $error['message'] );
		self::assertSame( 1, MigrationRunner::get_revision( $this->option ) );
		self::assertNotNull( $this->get_lock() );
	}

	public function test_a_successful_batch_clears_the_error_of_an_earlier_failure(): void {
		$clock   = new SettableClock( new \DateTimeImmutable( '@' . \time() ) );
		$batches = new RecordedBatches( 5, static fn () => $clock->advance( 30 ) );
		$this->make_runner( array( $batches ), null, $clock )->register_hooks();
		\update_option(
			"{$this->option}_error",
			array(
				'v'        => 1,
				'revision' => 0,
				'message'  => 'An earlier batch failed.',
				'since'    => 1800000000,
				'at'       => 1800000000,
			),
			false
		);

		\do_action( "{$this->option}_run" );

		self::assertSame( 1, $batches->processed );
		self::assertFalse( \get_option( "{$this->option}_error" ) );
	}

	public static function dispatchers(): array {
		return array(
			'action-scheduler' => array( 'production' ),
			'wp-cron'          => array( 'wp-cron' ),
		);
	}

	#[DataProvider( 'dispatchers' )]
	public function test_a_batched_step_finishes_through_real_scheduler_dispatch( string $kind ): void {
		$clock     = new SettableClock( new \DateTimeImmutable( '@' . \time() ) );
		$batches   = new RecordedBatches( 5, static fn () => $clock->advance( 10 ) );
		$scheduler = 'production' === $kind ? new Scheduler( 'acme' ) : new Scheduler( 'acme', array( new WpCronBackend( 'acme' ) ) );
		$this->load_wordpress( $this->make_runner( array( $batches ), null, $clock, null, $scheduler ) );

		$dispatches = 0;
		while ( 0 < $this->count_pending_runs() && $dispatches < 5 ) {
			\remove_all_actions( "{$this->option}_run" );
			$this->make_runner( array( $batches ), null, $clock, null, $scheduler )->register_hooks();
			'production' === $kind ? $this->run_due_actions() : $this->run_due_cron_events();
			++$dispatches;
		}

		self::assertSame( 3, $dispatches );
		self::assertSame( 5, $batches->processed );
		self::assertSame( 1, MigrationRunner::get_revision( $this->option ) );
		self::assertSame( 0, $this->count_pending_runs() );
	}

	public function test_a_failure_shows_its_error_instead_of_the_progress_notice_and_only_to_plugin_activators(): void {
		$batches = new RecordedBatches(
			3,
			static function (): void {
				throw new \RuntimeException( 'The <batch> failed.' );
			}
		);
		$runner  = $this->make_runner( array( $batches ) );
		$runner->register_hooks();
		\do_action( "{$this->option}_run" );

		\wp_set_current_user( $this->make_user( 'acme-migrations-admin', 'administrator' ) );
		$notices = $this->render_admin_notices();
		self::assertStringContainsString( self::ERROR_NOTICE . 'The &lt;batch&gt; failed.', $notices );
		self::assertStringNotContainsString( self::PROGRESS_NOTICE, $notices );

		\wp_set_current_user( $this->make_user( 'acme-migrations-editor', 'editor' ) );
		self::assertStringNotContainsString( self::ERROR_NOTICE, $this->render_admin_notices() );
	}

	public function test_a_pending_batched_step_shows_plugin_activators_a_run_now_link(): void {
		$this->make_runner( array( $this->step( 1 ), new RecordedBatches( 3 ) ) )->register_hooks();
		\update_option( $this->option, 1 );

		\wp_set_current_user( $this->make_user( 'acme-migrations-admin', 'administrator' ) );
		$notices = $this->render_admin_notices();
		self::assertStringContainsString( self::PROGRESS_NOTICE, $notices );
		self::assertSame( 1, \preg_match( '/<a href="([^"]+)">Run the update now<\/a>/', $notices, $link ) );
		\parse_str( (string) \wp_parse_url( \html_entity_decode( $link[1] ), \PHP_URL_QUERY ), $query );
		self::assertSame( "{$this->option}_run_now", $query['action'] );
		self::assertSame( 1, \wp_verify_nonce( $query['_wpnonce'], "{$this->option}_run_now" ) );

		\wp_set_current_user( $this->make_user( 'acme-migrations-editor', 'editor' ) );
		self::assertStringNotContainsString( self::PROGRESS_NOTICE, $this->render_admin_notices() );
	}

	public static function invalid_error_rows(): array {
		$valid = array(
			'v'        => 1,
			'revision' => 0,
			'message'  => 'Broken.',
			'since'    => 1800000000,
			'at'       => 1800000000,
		);

		return array(
			'later-version'     => array( array( 'v' => 2 ) + $valid ),
			'missing-version'   => array( \array_diff_key( $valid, array( 'v' => true ) ) ),
			'missing-revision'  => array( \array_diff_key( $valid, array( 'revision' => true ) ) ),
			'negative-revision' => array( array( 'revision' => -1 ) + $valid ),
			'string-revision'   => array( array( 'revision' => '0' ) + $valid ),
			'missing-message'   => array( \array_diff_key( $valid, array( 'message' => true ) ) ),
			'numeric-message'   => array( array( 'message' => 5 ) + $valid ),
			'string-since'      => array( array( 'since' => '1800000000' ) + $valid ),
			'missing-at'        => array( \array_diff_key( $valid, array( 'at' => true ) ) ),
			'not-an-array'      => array( 'Broken.' ),
		);
	}

	#[DataProvider( 'invalid_error_rows' )]
	public function test_an_invalid_error_row_shows_no_error_notice( mixed $row ): void {
		$this->make_runner( array( new RecordedBatches( 3 ) ) )->register_hooks();
		\update_option( "{$this->option}_error", $row, false );

		\wp_set_current_user( $this->make_user( 'acme-migrations-admin', 'administrator' ) );
		$notices = $this->render_admin_notices();

		self::assertStringNotContainsString( self::ERROR_NOTICE, $notices );
		self::assertStringContainsString( self::PROGRESS_NOTICE, $notices );
	}

	public function test_a_pending_plain_step_shows_no_progress_notice(): void {
		$this->make_runner( array( $this->step( 1 ) ) )->register_hooks();

		\wp_set_current_user( $this->make_user( 'acme-migrations-admin', 'administrator' ) );

		self::assertStringNotContainsString( self::PROGRESS_NOTICE, $this->render_admin_notices() );
	}

	public function test_no_notice_shows_once_every_step_has_run(): void {
		$this->make_runner( array( $this->step( 1 ), new RecordedBatches( 3 ) ) )->register_hooks();
		\update_option( $this->option, 2 );
		\update_option(
			"{$this->option}_error",
			array(
				'v'        => 1,
				'revision' => 1,
				'message'  => 'A stale failure.',
				'since'    => 1800000000,
				'at'       => 1800000000,
			),
			false
		);

		\wp_set_current_user( $this->make_user( 'acme-migrations-admin', 'administrator' ) );
		$notices = $this->render_admin_notices();

		self::assertStringNotContainsString( self::ERROR_NOTICE, $notices );
		self::assertStringNotContainsString( self::PROGRESS_NOTICE, $notices );
	}

	public static function referers(): array {
		return array(
			'admin-page' => array( 'plugins.php', 'plugins.php' ),
			'other-host' => array( 'https://example.org/wp-admin/', '' ),
		);
	}

	#[DataProvider( 'referers' )]
	public function test_run_now_runs_the_runner_hook_and_returns_to_a_local_referring_page( string $referer, string $destination ): void {
		$batches = new RecordedBatches( 3 );
		$this->make_runner( array( $batches ) )->register_hooks();
		\wp_set_current_user( $this->make_user( 'acme-migrations-admin', 'administrator' ) );
		$_REQUEST['_wpnonce']    = \wp_create_nonce( "{$this->option}_run_now" );
		$_SERVER['HTTP_REFERER'] = \str_starts_with( $referer, 'https://' ) ? $referer : \admin_url( $referer );

		self::assertSame( \admin_url( $destination ), $this->post_run_now() );
		self::assertSame( 3, $batches->processed );
		self::assertSame( 1, MigrationRunner::get_revision( $this->option ) );
	}

	public static function refused_run_now_requests(): array {
		return array(
			'invalid-nonce'                    => array( 'administrator', false ),
			'user-who-cannot-activate-plugins' => array( 'editor', true ),
		);
	}

	#[DataProvider( 'refused_run_now_requests' )]
	public function test_run_now_refuses_an_invalid_nonce_and_users_who_cannot_activate_plugins( string $role, bool $valid_nonce ): void {
		$batches = new RecordedBatches( 3 );
		$this->make_runner( array( $batches ) )->register_hooks();
		\wp_set_current_user( $this->make_user( "acme-migrations-$role", $role ) );
		$_REQUEST['_wpnonce'] = $valid_nonce ? \wp_create_nonce( "{$this->option}_run_now" ) : 'invalid';

		self::assertSame( 'died', $this->post_run_now() );
		self::assertSame( 0, $batches->processed );
	}

	protected function make_runner( array $steps, ?\wpdb $wpdb = null, ?PsrClockInterface $clock = null, ?PsrLoggerInterface $logger = null, ?SchedulerInterface $scheduler = null ): MigrationRunner {
		return new MigrationRunner( self::PLUGIN_FILE, $this->option, $steps, $logger ?? $this->logger, $scheduler ?? new Scheduler( 'acme', array( new WpCronBackend( 'acme' ) ) ), 20, $wpdb, $clock );
	}

	protected function step( int|string $name ): \Closure {
		return function () use ( $name ): void {
			$this->ran[] = array( $name, MigrationRunner::get_revision( $this->option ) );
		};
	}

	protected function load_wordpress( MigrationRunner $runner ): void {
		\remove_all_actions( 'wp_loaded' );
		$runner->register_hooks();
		\do_action( 'wp_loaded' );
	}

	protected function record_option_writes(): void {
		\add_filter(
			'query',
			function ( string $query ): string {
				if ( 1 === \preg_match( '/^\s*(INSERT|UPDATE|DELETE|REPLACE)\b/i', $query ) && \str_contains( $query, $this->option ) ) {
					$this->writes[] = $query;
				}

				return $query;
			}
		);
	}

	protected function insert_lock( int $timestamp ): void {
		\add_option( "{$this->option}_lock", $timestamp, '', false );
	}

	protected function age_lock(): void {
		global $wpdb;

		$wpdb->update( $wpdb->options, array( 'option_value' => (string) ( \time() - 601 ) ), array( 'option_name' => "{$this->option}_lock" ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Ages the lock row the runner wrote directly.
	}

	protected function render_admin_notices(): string {
		\ob_start();
		\do_action( 'all_admin_notices' );

		return (string) \ob_get_clean();
	}

	// The handler exits after redirecting, so the redirect and wp_die() throw to return here first.
	protected function post_run_now(): string {
		\add_filter(
			'wp_redirect',
			static function ( string $location ): never {
				throw new \OutOfBoundsException( $location );
			}
		);
		\add_filter(
			'wp_die_handler',
			static fn (): \Closure => static function (): never {
				throw new \OverflowException( 'died' );
			}
		);

		try {
			\do_action( "admin_post_{$this->option}_run_now" );
		} catch ( \OutOfBoundsException | \OverflowException $stop ) {
			return $stop->getMessage();
		}

		return 'returned';
	}

	protected function count_pending_runs(): int {
		$cron = 0;
		foreach ( \_get_cron_array() as $hooks ) {
			$cron += \count( $hooks[ "{$this->option}_run" ] ?? array() );
		}
		$query = array(
			'hook'     => "{$this->option}_run",
			'status'   => \ActionScheduler_Store::STATUS_PENDING,
			'per_page' => -1,
		);

		return $cron + \count( \as_get_scheduled_actions( $query, 'ids' ) );
	}

	// Action Scheduler's queue runner claims the due actions and processes each one, as a batch does.
	protected function run_due_actions(): void {
		$store = \ActionScheduler::store();
		$claim = $store->stake_claim( 10, null, array( "{$this->option}_run" ) );
		foreach ( $claim->get_actions() as $action_id ) {
			\ActionScheduler::runner()->process_action( (int) $action_id, 'Tests' );
		}
		$store->release_claim( $claim );
	}

	// As wp-cron.php does, each due event is unscheduled, then run.
	protected function run_due_cron_events(): void {
		foreach ( \_get_cron_array() as $timestamp => $hooks ) {
			if ( $timestamp > \time() ) {
				break;
			}

			foreach ( $hooks[ "{$this->option}_run" ] ?? array() as $event ) {
				\wp_unschedule_event( $timestamp, "{$this->option}_run", $event['args'], true );
				\do_action_ref_array( "{$this->option}_run", $event['args'] );
			}
		}
	}

	protected function get_lock(): ?string {
		global $wpdb;

		return $wpdb->get_var( $wpdb->prepare( 'SELECT option_value FROM %i WHERE option_name = %s', $wpdb->options, "{$this->option}_lock" ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Reads the lock row past the options cache.
	}
}
