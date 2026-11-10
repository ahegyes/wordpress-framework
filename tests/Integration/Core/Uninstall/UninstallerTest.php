<?php declare( strict_types=1 );

namespace DeepWebSolutions\Framework\Tests\Integration\Core\Uninstall;

use DeepWebSolutions\Framework\Core\Uninstall\Uninstaller;
use DeepWebSolutions\Framework\Shared\Exception\RuntimeException;
use DeepWebSolutions\Framework\Tests\Integration\Support\CreatesUsersTrait;
use DeepWebSolutions\Framework\Tests\Integration\Support\InterceptsQueriesTrait;
use DeepWebSolutions\Framework\Tests\Integration\Support\IsolatesHooksTrait;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass( Uninstaller::class )]
final class UninstallerTest extends TestCase {
	use CreatesUsersTrait;
	use InterceptsQueriesTrait;
	use IsolatesHooksTrait;
	use UninstallFixturesTrait;

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
					update_option( "{$prefix}_remove_data", 'no' );

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

	public static function retaining_and_consenting(): array {
		return array(
			'retaining'  => array( false ),
			'consenting' => array( true ),
		);
	}

	public static function failing_statements(): array {
		return array(
			'an-option-delete'                     => array( static fn ( \wpdb $wpdb, string $prefix ): string => "DELETE FROM `$wpdb->options` WHERE `option_name` = '{$prefix}_settings'", '_settings', static fn ( self $test ): bool => $test->option_exists( "{$test->prefix}_settings" ) ),
			'a-prefix-read'                        => array( static fn ( \wpdb $wpdb, string $prefix ): string => 'SELECT DISTINCT CAST( `option_name`', '_cache_', static fn ( self $test ): bool => $test->option_exists( "{$test->prefix}_cache_a" ) ),
			'a-post-type-read'                     => array( static fn ( \wpdb $wpdb, string $prefix ): string => "AS value FROM `$wpdb->posts`", '_type', static fn ( self $test ): bool => array() !== $test->get_posts_of_type( "{$test->prefix}_type" ) ),
			'a-network-option'                     => array( static fn ( \wpdb $wpdb, string $prefix ): string => "DELETE FROM `$wpdb->options` WHERE `option_name` = '{$prefix}_network_settings'", '_network_settings', static fn ( self $test ): bool => $test->network_option_exists( "{$test->prefix}_network_settings" ) ),
			'an-option-delete-with-prefix-consent' => array( static fn ( \wpdb $wpdb, string $prefix ): string => "DELETE FROM `$wpdb->options` WHERE `option_name` = '{$prefix}_settings'", '_settings', static fn ( self $test ): bool => $test->option_exists( "{$test->prefix}_settings" ), '_cache_remove_data' ),
		);
	}

	public static function refused_deletes(): array {
		return array(
			'a-post'     => array( 'pre_delete_post', '_type' ),
			'a-meta-row' => array( 'delete_post_metadata_by_mid', '_flag' ),
		);
	}

	#[DataProvider( 'retaining_consents' )]
	public function test_a_run_without_consent_keeps_the_footprint_and_runs_no_cleanup( \Closure $consent ): void {
		$this->seed_footprint();
		$args           = $consent( $this->prefix ) + $this->recording_cleanup();
		$consent_stored = $this->option_exists( "{$this->prefix}_remove_data" );

		Uninstaller::run( self::PLUGIN_FILE, $this->footprint(), $args );

		$this->assert_footprint_kept();
		self::assertSame( array(), $this->calls );
		self::assertSame( $consent_stored, $this->option_exists( "{$this->prefix}_remove_data" ) );
	}

	#[DataProvider( 'consents' )]
	public function test_a_consenting_run_without_a_current_user_deletes_exactly_the_footprint( \Closure $consent ): void {
		wp_set_current_user( 0 ); // WP-CLI uninstalls a plugin with no current user.
		$this->seed_footprint();

		Uninstaller::run( self::PLUGIN_FILE, $this->footprint(), $consent( $this->prefix ) );

		$this->assert_footprint_deleted();
		$this->assert_foreign_rows_kept();
		self::assertFalse( $this->option_exists( "{$this->prefix}_remove_data" ) );
	}

	public function test_a_key_on_more_rows_than_one_read_takes_is_read_in_pages_and_deleted_in_full(): void {
		$object_id = $this->make_post( 'post', 'publish' );
		for ( $row = 0; $row <= 1000; ++$row ) {
			add_metadata( 'post', $object_id, "{$this->prefix}_flag", (string) $row );
		}
		$this->record_id_reads();

		Uninstaller::run( self::PLUGIN_FILE, array( 'meta' => array( 'post' => array( "{$this->prefix}_flag" ) ) ), self::stored_consent( 'yes' )( $this->prefix ) );

		self::assertFalse( $this->meta_exists( 'post', "{$this->prefix}_flag" ) );
		$this->assert_id_reads_paged();
	}

	public function test_a_post_of_an_unregistered_type_is_deleted_with_its_term_relationships(): void {
		global $wpdb;

		$term = wp_insert_term( "$this->prefix-tagged", 'category' );
		self::assertIsArray( $term );
		$this->ids['term'] = (int) $term['term_id'];
		$post_id           = $this->make_post( "{$this->prefix}_type", 'publish' );
		wp_set_object_terms( $post_id, array( $this->ids['term'] ), 'category' );

		Uninstaller::run( self::PLUGIN_FILE, array( 'post_types' => array( "{$this->prefix}_type" ) ), self::stored_consent( 'yes' )( $this->prefix ) );

		self::assertFalse( $this->post_exists( $post_id ) );
		self::assertSame( '0', $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE object_id = %d', $wpdb->term_relationships, $post_id ) ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Reads past the term cache.
	}

	public function test_a_deleted_posts_id_keeps_its_relationships_in_a_taxonomy_of_another_object_type(): void {
		global $wpdb;

		$taxonomy = "{$this->prefix}_team";
		register_taxonomy( $taxonomy, 'user' );
		$term = wp_insert_term( "$this->prefix-team", $taxonomy );
		try {
			self::assertIsArray( $term );
			$post_id = $this->make_post( "{$this->prefix}_type", 'publish' );
			wp_set_object_terms( $post_id, array( (int) $term['term_id'] ), $taxonomy ); // Stands for the user that shares the post's id.

			Uninstaller::run( self::PLUGIN_FILE, array( 'post_types' => array( "{$this->prefix}_type" ) ), self::stored_consent( 'yes' )( $this->prefix ) );

			self::assertFalse( $this->post_exists( $post_id ) );
			self::assertSame( '1', $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE object_id = %d', $wpdb->term_relationships, $post_id ) ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Reads past the term cache.
		} finally {
			if ( \is_array( $term ) ) {
				wp_delete_term( (int) $term['term_id'], $taxonomy );
			}
			unregister_taxonomy( $taxonomy );
		}
	}

	public function test_a_post_whose_delete_wordpress_refuses_keeps_its_term_relationships(): void {
		global $wpdb;

		$term = wp_insert_term( "$this->prefix-tagged", 'category' );
		self::assertIsArray( $term );
		$this->ids['term'] = (int) $term['term_id'];
		$post_id           = $this->make_post( "{$this->prefix}_type", 'publish' );
		wp_set_object_terms( $post_id, array( $this->ids['term'] ), 'category' );
		add_filter( 'pre_delete_post', '__return_false' );

		try {
			Uninstaller::run( self::PLUGIN_FILE, array( 'post_types' => array( "{$this->prefix}_type" ) ), self::stored_consent( 'yes' )( $this->prefix ) );
			self::fail( 'The refused delete was not reported.' );
		} catch ( RuntimeException ) {
			self::assertTrue( $this->post_exists( $post_id ) );
		} finally {
			remove_filter( 'pre_delete_post', '__return_false' ); // The fixtures' own deletes must not be refused.
		}

		self::assertSame( '1', $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE object_id = %d', $wpdb->term_relationships, $post_id ) ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Reads past the term cache.
	}

	public function test_the_cleanup_runs_first_while_the_footprint_and_the_consent_row_are_stored(): void {
		$consent_option = "{$this->prefix}_cache_remove_data";
		update_option( $consent_option, 'yes' );
		update_option( "{$this->prefix}_cache_a", 'a' );
		$seen = array();

		Uninstaller::run(
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

		self::assertSame( array( true, true ), $seen );
		self::assertFalse( $this->option_exists( "{$this->prefix}_cache_a" ) );
		self::assertFalse( $this->option_exists( $consent_option ) );
	}

	public function test_the_cleanup_runs_once_with_the_site_context(): void {
		update_option( "{$this->prefix}_remove_data", 'yes' );
		update_option( "{$this->prefix}_settings", 'x' );

		$log = $this->capture_error_log(
			fn () => Uninstaller::run(
				self::PLUGIN_FILE,
				array( 'options' => array( "{$this->prefix}_settings" ) ),
				array( 'consent' => array( 'option' => "{$this->prefix}_remove_data" ) ) + $this->recording_cleanup()
			)
		);

		self::assertSame( array(), $log );
		$context = array(
			'scope'       => 'site',
			'site_id'     => get_current_blog_id(),
			'plugin_file' => self::PLUGIN_FILE,
		);
		self::assertSame( array( array( 'cleanup', $context ) ), $this->calls );
	}

	public function test_a_kind_keeps_its_non_empty_strings_and_skips_every_other_entry(): void {
		$this->seed_footprint();
		update_option( "{$this->prefix}_remove_data", 'yes' );

		Uninstaller::run(
			self::PLUGIN_FILE,
			array(
				'options'         => array( '', '0', 7, null, array( "{$this->prefix}_cache_a" ), "{$this->prefix}_settings" ),
				'option_prefixes' => array( '', '0' ),
				'transients'      => "{$this->prefix}_transient",
			),
			array( 'consent' => array( 'option' => "{$this->prefix}_remove_data" ) )
		);

		self::assertFalse( $this->option_exists( "{$this->prefix}_settings" ) );
		self::assertTrue( $this->option_exists( "{$this->prefix}_cache_a" ) );
		self::assertSame( 'x', get_transient( "{$this->prefix}_transient" ) );
		$this->assert_foreign_rows_kept();
	}

	public function test_a_meta_kind_skips_the_names_of_unknown_meta_types_and_cleans_the_rest(): void {
		$this->seed_footprint();
		update_option( "{$this->prefix}_remove_data", 'yes' );

		Uninstaller::run(
			self::PLUGIN_FILE,
			array(
				'meta'          => array(
					'user' => array( "{$this->prefix}_global_state" ),
					'acme' => array( "{$this->prefix}_global_state" ),
					'post' => array( "{$this->prefix}_flag" ),
				),
				'meta_prefixes' => array(
					'user' => array( "{$this->prefix}_global_" ),
					'post' => array( "{$this->prefix}_post_" ),
				),
			),
			array(
				'consent'    => array( 'option' => "{$this->prefix}_remove_data" ),
				'meta_types' => array( 'acme' => array( 'meta_type' => 'user' ) ),
			)
		);

		self::assertFalse( $this->meta_exists( 'post', "{$this->prefix}_flag" ) );
		self::assertFalse( $this->meta_exists( 'post', "{$this->prefix}_post_a" ) );
		self::assertTrue( $this->user_meta_exists( "{$this->prefix}_global_state" ) );
		self::assertTrue( $this->user_meta_exists( "{$this->prefix}_global_a" ) );
	}

	public function test_a_job_wordpress_refuses_to_unschedule_is_logged_without_holding_the_uninstall_back(): void {
		$this->seed_footprint();
		update_option( "{$this->prefix}_remove_data", 'yes' );
		wp_schedule_single_event( \time() + 3600, "{$this->prefix}_cron" );
		add_filter( 'pre_unschedule_hook', '__return_false' );
		try {
			$log = $this->capture_error_log(
				fn () => Uninstaller::run(
					self::PLUGIN_FILE,
					array( 'cron_hooks' => array( "{$this->prefix}_cron" ) ) + $this->footprint(),
					array( 'consent' => array( 'option' => "{$this->prefix}_remove_data" ) )
				)
			);
		} finally {
			remove_filter( 'pre_unschedule_hook', '__return_false' ); // The fixtures' own unscheduling must not be refused.
		}

		self::assertCount( 1, $log );
		self::assertStringContainsString( "] Uninstall could not unschedule: cron hook {$this->prefix}_cron: ", $log[0] );
		$this->assert_footprint_deleted();
		self::assertFalse( $this->option_exists( "{$this->prefix}_remove_data" ) );
	}

	#[DataProvider( 'retaining_and_consenting' )]
	public function test_cron_hooks_are_unscheduled_with_every_argument_variant_whatever_the_consent( bool $consenting ): void {
		update_option( "{$this->prefix}_remove_data", $consenting ? 'yes' : 'no' );
		wp_schedule_single_event( \time() + 3600, "{$this->prefix}_cron", array( 1 ) );
		wp_schedule_single_event( \time() + 3600, "{$this->prefix}_cron", array( 2 ) );
		wp_schedule_single_event( \time() + 3600, "{$this->prefix}_foreign_cron" );

		Uninstaller::run( self::PLUGIN_FILE, array( 'cron_hooks' => array( "{$this->prefix}_cron" ) ), array( 'consent' => array( 'option' => "{$this->prefix}_remove_data" ) ) );

		self::assertFalse( wp_next_scheduled( "{$this->prefix}_cron", array( 1 ) ) );
		self::assertFalse( wp_next_scheduled( "{$this->prefix}_cron", array( 2 ) ) );
		self::assertIsInt( wp_next_scheduled( "{$this->prefix}_foreign_cron" ) );
	}

	#[DataProvider( 'retaining_and_consenting' )]
	public function test_action_scheduler_actions_are_unscheduled_by_hook_and_by_group_whatever_the_consent( bool $consenting ): void {
		update_option( "{$this->prefix}_remove_data", $consenting ? 'yes' : 'no' );
		as_schedule_single_action( \time() + 3600, "{$this->prefix}_as", array( 1 ) );
		as_schedule_single_action( \time() + 3600, "{$this->prefix}_as", array( 2 ) );
		as_schedule_single_action( \time() + 3600, "{$this->prefix}_grouped", array(), "$this->prefix-group" );
		as_schedule_single_action( \time() + 3600, "{$this->prefix}_foreign_as" );

		Uninstaller::run(
			self::PLUGIN_FILE,
			array(
				'as_hooks'  => array( "{$this->prefix}_as" ),
				'as_groups' => array( "$this->prefix-group" ),
			),
			array( 'consent' => array( 'option' => "{$this->prefix}_remove_data" ) )
		);

		self::assertFalse( as_has_scheduled_action( "{$this->prefix}_as" ) );
		self::assertFalse( as_has_scheduled_action( "{$this->prefix}_grouped" ) );
		self::assertTrue( as_has_scheduled_action( "{$this->prefix}_foreign_as" ) );
	}

	public function test_an_action_scheduler_hook_or_group_named_0_cancels_no_other_action(): void {
		update_option( "{$this->prefix}_remove_data", 'yes' );
		as_schedule_single_action( \time() + 3600, "{$this->prefix}_foreign_as" );
		as_schedule_single_action( \time() + 3600, "{$this->prefix}_grouped", array(), "$this->prefix-group" );

		Uninstaller::run(
			self::PLUGIN_FILE,
			array(
				'as_hooks'  => array( '0' ),
				'as_groups' => array( '0' ),
			),
			array( 'consent' => array( 'option' => "{$this->prefix}_remove_data" ) )
		);

		self::assertTrue( as_has_scheduled_action( "{$this->prefix}_foreign_as" ) );
		self::assertTrue( as_has_scheduled_action( "{$this->prefix}_grouped" ) );
	}

	#[DataProvider( 'failing_statements' )]
	public function test_a_failed_statement_keeps_the_consent_row_and_throws_after_the_rest_so_a_retry_completes( \Closure $needle, string $subject, \Closure $kept, string $consent = '_remove_data' ): void {
		global $wpdb;

		$this->seed_footprint();
		update_option( $this->prefix . $consent, 'yes' );
		$args = array( 'consent' => array( 'option' => $this->prefix . $consent ) ) + $this->recording_cleanup();
		$this->fail_queries( $needle( $wpdb, $this->prefix ) );

		$failure = null;
		try {
			Uninstaller::run( self::PLUGIN_FILE, $this->footprint(), $args );
		} catch ( RuntimeException $exception ) {
			$failure = $exception;
		}

		self::assertInstanceOf( RuntimeException::class, $failure );
		self::assertStringContainsString( $this->prefix . $subject, $failure->getMessage() );
		self::assertTrue( $kept( $this ) );
		self::assertFalse( $this->table_exists( "{$this->prefix}_log" ) );
		self::assertSame( array( 'cleanup' ), \array_column( $this->calls, 0 ) );
		self::assertTrue( $this->option_exists( $this->prefix . $consent ) );

		remove_all_filters( 'query' );
		$this->restore_query_errors();
		Uninstaller::run( self::PLUGIN_FILE, $this->footprint(), $args );

		self::assertFalse( $kept( $this ) );
		self::assertFalse( $this->option_exists( $this->prefix . $consent ) );
	}

	#[DataProvider( 'refused_deletes' )]
	public function test_a_delete_wordpress_refuses_is_thrown_after_the_rest_and_a_retry_completes( string $filter, string $subject ): void {
		$this->seed_footprint();
		update_option( "{$this->prefix}_remove_data", 'yes' );
		wp_schedule_single_event( \time() + 3600, "{$this->prefix}_cron" );
		add_filter( $filter, '__return_false' );

		$failure = null;
		try {
			Uninstaller::run(
				self::PLUGIN_FILE,
				array( 'cron_hooks' => array( "{$this->prefix}_cron" ) ) + $this->footprint(),
				array( 'consent' => array( 'option' => "{$this->prefix}_remove_data" ) )
			);
		} catch ( RuntimeException $exception ) {
			$failure = $exception;
		} finally {
			remove_filter( $filter, '__return_false' ); // The fixtures' own deletes must not be refused.
		}

		self::assertInstanceOf( RuntimeException::class, $failure );
		self::assertStringContainsString( $this->prefix . $subject, $failure->getMessage() );
		self::assertFalse( $this->option_exists( "{$this->prefix}_settings" ) );
		self::assertTrue( $this->option_exists( "{$this->prefix}_remove_data" ) );

		Uninstaller::run(
			self::PLUGIN_FILE,
			array( 'cron_hooks' => array( "{$this->prefix}_cron" ) ) + $this->footprint(),
			array( 'consent' => array( 'option' => "{$this->prefix}_remove_data" ) )
		);

		$this->assert_footprint_deleted();
		self::assertFalse( wp_next_scheduled( "{$this->prefix}_cron" ) );
	}

	public function test_a_failing_cleanup_keeps_that_sites_footprint_and_consent_row_and_throws_after_the_rest(): void {
		update_option( "{$this->prefix}_remove_data", 'yes' );
		update_option( "{$this->prefix}_settings", 'x' );
		wp_schedule_single_event( \time() + 3600, "{$this->prefix}_cron" );

		$failure = null;
		try {
			Uninstaller::run(
				self::PLUGIN_FILE,
				array(
					'cron_hooks' => array( "{$this->prefix}_cron" ),
					'options'    => array( "{$this->prefix}_settings" ),
				),
				array( 'consent' => array( 'option' => "{$this->prefix}_remove_data" ) ) + $this->recording_cleanup( true )
			);
		} catch ( RuntimeException $exception ) {
			$failure = $exception;
		}

		self::assertInstanceOf( RuntimeException::class, $failure );
		self::assertStringEndsWith( 'cleanup: The cleanup failed.', $failure->getMessage() );
		self::assertFalse( wp_next_scheduled( "{$this->prefix}_cron" ) );
		self::assertTrue( $this->option_exists( "{$this->prefix}_settings" ) );
		self::assertTrue( $this->option_exists( "{$this->prefix}_remove_data" ) );
	}

	public function test_a_throw_from_another_plugins_delete_hook_ends_the_sites_pass_and_a_retry_without_it_completes(): void {
		$this->seed_footprint();
		update_option( "{$this->prefix}_remove_data", 'yes' );
		$settings = "{$this->prefix}_settings";
		$listener = static function ( string $option ) use ( $settings ): void {
			if ( $settings === $option ) {
				throw new \LogicException( 'The listener failed.' );
			}
		};
		add_action( 'delete_option', $listener );

		$failure = null;
		try {
			Uninstaller::run( self::PLUGIN_FILE, $this->footprint(), array( 'consent' => array( 'option' => "{$this->prefix}_remove_data" ) ) );
		} catch ( RuntimeException $exception ) {
			$failure = $exception;
		} finally {
			remove_action( 'delete_option', $listener );
		}

		self::assertInstanceOf( RuntimeException::class, $failure );
		self::assertStringContainsString( 'The listener failed.', $failure->getMessage() );
		self::assertTrue( $this->option_exists( $settings ) );
		self::assertTrue( $this->table_exists( "{$this->prefix}_log" ) );
		self::assertTrue( $this->option_exists( "{$this->prefix}_remove_data" ) );

		Uninstaller::run( self::PLUGIN_FILE, $this->footprint(), array( 'consent' => array( 'option' => "{$this->prefix}_remove_data" ) ) );

		$this->assert_footprint_deleted();
		self::assertFalse( $this->option_exists( "{$this->prefix}_remove_data" ) );
	}

	public function test_an_action_scheduler_cancel_that_throws_is_logged_without_holding_the_uninstall_back(): void {
		$this->seed_footprint();
		update_option( "{$this->prefix}_remove_data", 'yes' );
		as_schedule_single_action( \time() + 3600, "{$this->prefix}_as" );
		$listener = static function (): void {
			throw new \LogicException( 'The cancel failed.' );
		};
		add_action( 'action_scheduler_bulk_cancel_actions', $listener );
		try {
			$log = $this->capture_error_log(
				fn () => Uninstaller::run(
					self::PLUGIN_FILE,
					array( 'as_hooks' => array( "{$this->prefix}_as" ) ) + $this->footprint(),
					array( 'consent' => array( 'option' => "{$this->prefix}_remove_data" ) )
				)
			);
		} finally {
			remove_action( 'action_scheduler_bulk_cancel_actions', $listener );
		}

		self::assertCount( 1, $log );
		self::assertStringContainsString( "] Uninstall could not unschedule: Action Scheduler hook {$this->prefix}_as: The cancel failed.", $log[0] );
		$this->assert_footprint_deleted();
		self::assertFalse( $this->option_exists( "{$this->prefix}_remove_data" ) );
	}

	protected static function stored_consent( mixed $value, string $key = '' ): \Closure {
		return static function ( string $prefix ) use ( $value, $key ): array {
			update_option( "{$prefix}_remove_data", $value );

			return array( 'consent' => array( 'option' => "{$prefix}_remove_data" ) + ( '' === $key ? array() : array( 'key' => $key ) ) );
		};
	}

	protected static function defined_consent( mixed $value ): \Closure {
		return static function ( string $prefix ) use ( $value ): array {
			$constant = \strtoupper( "{$prefix}_remove_all_data" );
			\define( $constant, $value ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.VariableConstantNameFound -- A unique name per test, since a constant cannot be undefined.

			return array( 'consent' => array( 'constant' => $constant ) );
		};
	}
}
