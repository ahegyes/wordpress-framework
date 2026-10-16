<?php declare( strict_types=1 );

namespace DeepWebSolutions\Framework\Tests\Integration\Bootstrap;

use DeepWebSolutions\Framework\Tests\Integration\Support\CreatesUsersTrait;
use DeepWebSolutions\Framework\Tests\Integration\Support\InterceptsQueriesTrait;
use DeepWebSolutions\Framework\Tests\Integration\Support\IsolatesHooksTrait;
use PHPUnit\Framework\Attributes\CoversFunction;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

use function DeepWebSolutions\Framework\Bootstrap\uninstall;

#[CoversFunction( 'DeepWebSolutions\Framework\Bootstrap\uninstall' )]
final class UninstallTest extends TestCase {
	use CreatesUsersTrait;
	use InterceptsQueriesTrait;
	use IsolatesHooksTrait;
	use UninstallFixturesTrait;

	#[\Override]
	public static function setUpBeforeClass(): void {
		require_once \dirname( __DIR__, 3 ) . '/packages/bootstrap/uninstall.php';
	}

	public static function retaining_consents(): array {
		return array(
			'no-consent-argument'      => array( static fn ( string $prefix ): array => array() ),
			'option-missing'           => array( static fn ( string $prefix ): array => array( 'consent' => array( 'option' => "{$prefix}_remove_data" ) ) ),
			'option-no'                => array( self::stored_consent( 'no' ) ),
			'option-one'               => array( self::stored_consent( '1' ) ),
			'option-uppercase-yes'     => array( self::stored_consent( 'YES' ) ),
			'option-true'              => array( self::stored_consent( true ) ),
			'grouped-row-without-key'  => array( self::stored_consent( array( 'other' => 'yes' ), 'remove_data' ) ),
			'grouped-key-no'           => array( self::stored_consent( array( 'remove_data' => 'no' ), 'remove_data' ) ),
			'flat-yes-read-as-grouped' => array( self::stored_consent( 'yes', 'remove_data' ) ),
			'constant-yes-string'      => array( self::defined_consent( 'yes' ) ),
			'constant-undefined'       => array( static fn ( string $prefix ): array => array( 'consent' => array( 'constant' => \strtoupper( "{$prefix}_undefined" ) ) ) ),
		);
	}

	public static function consents(): array {
		return array(
			'flat-yes'                => array( self::stored_consent( 'yes' ) ),
			'grouped-yes'             => array( self::stored_consent( array( 'remove_data' => 'yes' ), 'remove_data' ) ),
			'constant-true'           => array( self::defined_consent( true ) ),
			'constant-over-stored-no' => array(
				static function ( string $prefix ): array {
					\update_option( "{$prefix}_remove_data", 'no' );

					return array(
						'consent' => array(
							'option'   => "{$prefix}_remove_data",
							'constant' => self::defined_consent( true )( $prefix )['consent']['constant'],
						),
					);
				},
			),
		);
	}

	#[DataProvider( 'retaining_consents' )]
	public function test_a_run_without_consent_keeps_the_footprint_and_calls_no_handler( \Closure $consent ): void {
		$this->seed_footprint();
		$args           = $consent( $this->prefix ) + $this->recording_callables();
		$consent_stored = $this->option_exists( "{$this->prefix}_remove_data" );

		uninstall( self::PLUGIN_FILE, $this->footprint(), $args );

		$this->assert_footprint_kept();
		self::assertSame( array(), $this->calls );
		self::assertSame( $consent_stored, $this->option_exists( "{$this->prefix}_remove_data" ) );
	}

	#[DataProvider( 'consents' )]
	public function test_a_consenting_run_without_a_current_user_deletes_exactly_the_footprint( \Closure $consent ): void {
		\wp_set_current_user( 0 ); // WP-CLI uninstalls a plugin with no current user.
		$this->seed_footprint();

		uninstall( self::PLUGIN_FILE, $this->footprint(), $consent( $this->prefix ) );

		$this->assert_footprint_deleted();
		$this->assert_foreign_rows_kept();
		self::assertFalse( $this->option_exists( "{$this->prefix}_remove_data" ) );
	}

	public function test_the_consent_row_survives_every_prefix_delete_until_after_the_cleanup(): void {
		$consent_option = "{$this->prefix}_cache_remove_data";
		\update_option( $consent_option, 'yes' );
		\update_option( "{$this->prefix}_cache_a", 'a' );
		$seen = array();

		uninstall(
			self::PLUGIN_FILE,
			array(
				'options'         => array( $consent_option ),
				'option_prefixes' => array( "{$this->prefix}_cache_" ),
			),
			array(
				'consent' => array( 'option' => $consent_option ),
				'cleanup' => function () use ( $consent_option, &$seen ): void {
					$seen = array( $this->option_exists( "{$this->prefix}_cache_a" ), $this->option_exists( $consent_option ) );
				},
			)
		);

		self::assertSame( array( false, true ), $seen );
		self::assertFalse( $this->option_exists( $consent_option ) );
	}

	public function test_handlers_and_the_cleanup_run_once_with_their_values_and_the_site_context(): void {
		\update_option( "{$this->prefix}_remove_data", 'yes' );
		\update_option( "{$this->prefix}_settings", 'x' );
		$args            = $this->recording_callables( array( 'order_meta', 'order_item_meta' ) );
		$args['consent'] = array( 'option' => "{$this->prefix}_remove_data" );

		$log = $this->capture_error_log(
			fn () => uninstall(
				self::PLUGIN_FILE,
				array(
					'options'    => array( "{$this->prefix}_settings" ),
					'order_meta' => array( "_{$this->prefix}_order" ),
				),
				$args
			)
		);

		self::assertSame( array(), $log );
		$context = array(
			'scope'       => 'site',
			'site_id'     => \get_current_blog_id(),
			'plugin_file' => self::PLUGIN_FILE,
		);
		self::assertSame(
			array(
				array( 'handler', 'order_meta', array( "_{$this->prefix}_order" ), $context ),
				array( 'cleanup', null, null, $context ),
			),
			$this->calls
		);
	}

	public function test_a_footprint_key_the_engine_cannot_read_is_logged_once_and_skipped(): void {
		\update_option( "{$this->prefix}_remove_data", 'yes' );
		\update_option( "{$this->prefix}_settings", 'x' );
		\set_transient( "{$this->prefix}_transient", 'x' );
		$lines = $this->capture_error_log(
			fn () => uninstall(
				self::PLUGIN_FILE,
				array(
					'optoins'    => array( "{$this->prefix}_settings" ),
					'order_meta' => array( "_{$this->prefix}_order" ),
					'transients' => "{$this->prefix}_transient",
					'meta'       => array( 'user' => array( "{$this->prefix}_flag" ) ),
					'options'    => array( "{$this->prefix}_settings" ),
				),
				array( 'consent' => array( 'option' => "{$this->prefix}_remove_data" ) )
			)
		);

		self::assertCount( 1, $lines );
		self::assertStringEndsWith( '] Uninstall skipped the footprint keys it cannot read: optoins, order_meta, transients, meta', $lines[0] );
		self::assertFalse( $this->option_exists( "{$this->prefix}_settings" ) );
		self::assertSame( 'x', \get_transient( "{$this->prefix}_transient" ) );
	}

	#[DataProvider( 'retaining_and_consenting' )]
	public function test_cron_hooks_are_unscheduled_with_every_argument_variant_whatever_the_consent( bool $consenting ): void {
		\update_option( "{$this->prefix}_remove_data", $consenting ? 'yes' : 'no' );
		\wp_schedule_single_event( \time() + 3600, "{$this->prefix}_cron", array( 1 ) );
		\wp_schedule_single_event( \time() + 3600, "{$this->prefix}_cron", array( 2 ) );
		\wp_schedule_single_event( \time() + 3600, "{$this->prefix}_foreign_cron" );

		uninstall( self::PLUGIN_FILE, array( 'cron_hooks' => array( "{$this->prefix}_cron" ) ), array( 'consent' => array( 'option' => "{$this->prefix}_remove_data" ) ) );

		self::assertFalse( \wp_next_scheduled( "{$this->prefix}_cron", array( 1 ) ) );
		self::assertFalse( \wp_next_scheduled( "{$this->prefix}_cron", array( 2 ) ) );
		self::assertIsInt( \wp_next_scheduled( "{$this->prefix}_foreign_cron" ) );
	}

	#[DataProvider( 'retaining_and_consenting' )]
	public function test_action_scheduler_actions_are_unscheduled_by_hook_and_by_group_whatever_the_consent( bool $consenting ): void {
		\update_option( "{$this->prefix}_remove_data", $consenting ? 'yes' : 'no' );
		\as_schedule_single_action( \time() + 3600, "{$this->prefix}_as", array( 1 ) );
		\as_schedule_single_action( \time() + 3600, "{$this->prefix}_as", array( 2 ) );
		\as_schedule_single_action( \time() + 3600, "{$this->prefix}_grouped", array(), "{$this->prefix}-group" );
		\as_schedule_single_action( \time() + 3600, "{$this->prefix}_foreign_as" );

		uninstall(
			self::PLUGIN_FILE,
			array(
				'as_hooks'  => array( "{$this->prefix}_as" ),
				'as_groups' => array( "{$this->prefix}-group" ),
			),
			array( 'consent' => array( 'option' => "{$this->prefix}_remove_data" ) )
		);

		self::assertFalse( \as_has_scheduled_action( "{$this->prefix}_as" ) );
		self::assertFalse( \as_has_scheduled_action( "{$this->prefix}_grouped" ) );
		self::assertTrue( \as_has_scheduled_action( "{$this->prefix}_foreign_as" ) );
	}

	public static function failing_statements(): array {
		return array(
			'an-option-delete' => array( static fn ( \wpdb $wpdb, string $prefix ): string => "DELETE FROM `$wpdb->options` WHERE `option_name` = '{$prefix}_settings'", '_settings', 'option_exists', '_settings' ),
			'a-prefix-read'    => array( static fn ( \wpdb $wpdb, string $prefix ): string => 'SELECT DISTINCT `option_name`', '_cache_', 'option_exists', '_cache_a' ),
			'a-post-type-read' => array( static fn ( \wpdb $wpdb, string $prefix ): string => "AS type FROM `$wpdb->posts`", '_type', 'post_type_has_posts', '_type' ),
		);
	}

	#[DataProvider( 'failing_statements' )]
	public function test_a_failed_statement_keeps_the_consent_row_and_throws_after_the_rest_so_a_retry_completes( \Closure $needle, string $subject, string $kept, string $row ): void {
		global $wpdb;

		$this->seed_footprint();
		\update_option( "{$this->prefix}_remove_data", 'yes' );
		$args = array( 'consent' => array( 'option' => "{$this->prefix}_remove_data" ) ) + $this->recording_callables( array( 'order_meta' ) );
		$this->fail_queries( $needle( $wpdb, $this->prefix ) );

		$failure = null;
		try {
			uninstall( self::PLUGIN_FILE, array( 'order_meta' => array() ) + $this->footprint(), $args );
		} catch ( \RuntimeException $exception ) {
			$failure = $exception;
		}

		self::assertInstanceOf( \RuntimeException::class, $failure );
		self::assertStringContainsString( $this->prefix . $subject, $failure->getMessage() );
		self::assertTrue( $this->$kept( $this->prefix . $row ) );
		self::assertFalse( $this->table_exists( "{$this->prefix}_log" ) );
		self::assertSame( array( 'handler', 'cleanup' ), \array_column( $this->calls, 0 ) );
		self::assertTrue( $this->option_exists( "{$this->prefix}_remove_data" ) );

		\remove_all_filters( 'query' );
		$this->restore_query_errors();
		uninstall( self::PLUGIN_FILE, $this->footprint(), $args );

		self::assertFalse( $this->$kept( $this->prefix . $row ) );
		self::assertFalse( $this->option_exists( "{$this->prefix}_remove_data" ) );
	}

	public static function refused_deletes(): array {
		return array(
			'a-post'      => array( 'pre_delete_post', '_type', true ),
			'a-cron-hook' => array( 'pre_unschedule_hook', '_cron', false ),
		);
	}

	#[DataProvider( 'refused_deletes' )]
	public function test_a_delete_wordpress_refuses_is_thrown_after_the_rest( string $filter, string $subject, bool $consent_kept ): void {
		$this->seed_footprint();
		\update_option( "{$this->prefix}_remove_data", 'yes' );
		\wp_schedule_single_event( \time() + 3600, "{$this->prefix}_cron" );
		\add_filter( $filter, '__return_false' );

		$failure = null;
		try {
			uninstall(
				self::PLUGIN_FILE,
				array( 'cron_hooks' => array( "{$this->prefix}_cron" ) ) + $this->footprint(),
				array( 'consent' => array( 'option' => "{$this->prefix}_remove_data" ) )
			);
		} catch ( \RuntimeException $exception ) {
			$failure = $exception;
		} finally {
			\remove_filter( $filter, '__return_false' ); // The fixtures' own deletes must not be refused.
		}

		self::assertInstanceOf( \RuntimeException::class, $failure );
		self::assertStringContainsString( $this->prefix . $subject, $failure->getMessage() );
		self::assertFalse( $this->option_exists( "{$this->prefix}_settings" ) );
		self::assertSame( $consent_kept, $this->option_exists( "{$this->prefix}_remove_data" ) );
	}

	public static function throwing_callables(): array {
		return array(
			'handler' => array( 'handler' ),
			'cleanup' => array( 'cleanup' ),
		);
	}

	#[DataProvider( 'throwing_callables' )]
	public function test_a_throwing_handler_or_cleanup_keeps_the_consent_row_and_throws_after_the_rest( string $thrower ): void {
		\update_option( "{$this->prefix}_remove_data", 'yes' );
		\update_option( "{$this->prefix}_settings", 'x' );
		$args            = $this->recording_callables( array( 'order_meta' ), $thrower );
		$args['consent'] = array( 'option' => "{$this->prefix}_remove_data" );

		$failure = null;
		try {
			uninstall(
				self::PLUGIN_FILE,
				array(
					'options'    => array( "{$this->prefix}_settings" ),
					'order_meta' => array(),
				),
				$args
			);
		} catch ( \RuntimeException $exception ) {
			$failure = $exception;
		}

		self::assertInstanceOf( \RuntimeException::class, $failure );
		self::assertStringContainsString( "$thrower failed", $failure->getMessage() );
		self::assertSame( array( 'handler', 'cleanup' ), \array_column( $this->calls, 0 ) );
		self::assertFalse( $this->option_exists( "{$this->prefix}_settings" ) );
		self::assertTrue( $this->option_exists( "{$this->prefix}_remove_data" ) );
	}

	public static function retaining_and_consenting(): array {
		return array(
			'retaining'  => array( false ),
			'consenting' => array( true ),
		);
	}

	protected static function stored_consent( mixed $value, string $key = '' ): \Closure {
		return static function ( string $prefix ) use ( $value, $key ): array {
			\update_option( "{$prefix}_remove_data", $value );

			return array( 'consent' => array( 'option' => "{$prefix}_remove_data" ) + ( '' === $key ? array() : array( 'key' => $key ) ) );
		};
	}

	protected static function defined_consent( mixed $value ): \Closure {
		return static function ( string $prefix ) use ( $value ): array {
			$constant = \strtoupper( "{$prefix}_remove_all_data_" . \bin2hex( \random_bytes( 2 ) ) );
			\define( $constant, $value ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.VariableConstantNameFound -- A unique name per run, since a constant cannot be undefined.

			return array( 'consent' => array( 'constant' => $constant ) );
		};
	}
}
