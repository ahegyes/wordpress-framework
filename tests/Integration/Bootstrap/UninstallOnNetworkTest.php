<?php declare( strict_types=1 );

namespace DeepWebSolutions\Framework\Tests\Integration\Bootstrap;

use DeepWebSolutions\Framework\Tests\Integration\Support\CreatesSitesTrait;
use DeepWebSolutions\Framework\Tests\Integration\Support\CreatesUsersTrait;
use DeepWebSolutions\Framework\Tests\Integration\Support\IsolatesHooksTrait;
use PHPUnit\Framework\Attributes\CoversFunction;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

use function DeepWebSolutions\Framework\Bootstrap\uninstall;

#[CoversFunction( 'DeepWebSolutions\Framework\Bootstrap\uninstall' )]
#[Group( 'multisite' )]
final class UninstallOnNetworkTest extends TestCase {
	use CreatesSitesTrait;
	use CreatesUsersTrait;
	use IsolatesHooksTrait;
	use UninstallFixturesTrait;

	protected int $main_site;

	protected int $other_site;

	protected int $user;

	#[\Override]
	public static function setUpBeforeClass(): void {
		require_once \dirname( __DIR__, 3 ) . '/packages/bootstrap/uninstall.php';
	}

	#[\Override]
	protected function setUp(): void {
		$this->main_site  = \get_main_site_id();
		$this->other_site = $this->make_site( 'acme-uninstall' );
		$this->user       = $this->make_user( 'acme_uninstall_network_user', 'subscriber' );
		\add_user_meta( $this->user, "{$this->prefix}_global_state", 'x' );
	}

	#[\Override]
	protected function tearDown(): void {
		while ( \ms_is_switched() ) {
			\restore_current_blog();
		}
		$this->on_site( $this->other_site, fn () => \wp_unschedule_hook( "{$this->prefix}_cron" ) );
	}

	public function test_a_default_run_cleans_only_the_current_site_and_keeps_global_user_meta(): void {
		$this->seed_site( $this->main_site, 'yes' );
		$this->seed_site( $this->other_site, 'yes' );

		uninstall( self::PLUGIN_FILE, $this->network_footprint(), $this->args() );

		self::assertSame( array( false, false, false, false ), $this->site_state( $this->main_site ) );
		self::assertSame( array( true, true, true, true ), $this->site_state( $this->other_site ) );
		self::assertTrue( $this->user_meta_exists( "{$this->prefix}_global_state" ) );
		self::assertSame( array( "handler:site:$this->main_site", "cleanup:site:$this->main_site" ), $this->summarize_calls() );
	}

	public function test_a_network_run_cleans_each_site_by_its_own_consent_and_unschedules_on_every_site(): void {
		$this->seed_site( $this->main_site, 'no' );
		$this->seed_site( $this->other_site, 'yes' );

		uninstall( self::PLUGIN_FILE, $this->network_footprint(), array( 'network' => true ) + $this->args() );

		self::assertSame( array( true, true, false, true ), $this->site_state( $this->main_site ) );
		self::assertSame( array( false, false, false, false ), $this->site_state( $this->other_site ) );
		self::assertTrue( $this->user_meta_exists( "{$this->prefix}_global_state" ) );
		self::assertSame( array( "handler:site:$this->other_site", "cleanup:site:$this->other_site" ), $this->summarize_calls() );
	}

	public static function constant_consents(): array {
		return array(
			'with-the-constant'    => array( true ),
			'without-the-constant' => array( false ),
		);
	}

	#[DataProvider( 'constant_consents' )]
	public function test_the_network_pass_runs_only_on_the_consent_constant( bool $constant ): void {
		$this->seed_site( $this->main_site, 'yes' );
		$this->seed_site( $this->other_site, 'yes' );

		uninstall( self::PLUGIN_FILE, $this->network_footprint(), array( 'network' => true ) + $this->args( $constant ) );

		// The constant consents for every site of the network, which may hold more sites than the test created.
		$consenting = $constant ? \get_sites(
			array(
				'fields' => 'ids',
				'number' => 0,
			)
		) : array( $this->main_site, $this->other_site );
		$expected   = array();
		foreach ( $consenting as $site_id ) {
			\array_push( $expected, "handler:site:$site_id", "cleanup:site:$site_id" );
		}
		if ( $constant ) {
			\array_push( $expected, "handler:network:$this->main_site", "cleanup:network:$this->main_site" );
		}
		self::assertSame( $expected, $this->summarize_calls() );
		self::assertSame( ! $constant, $this->user_meta_exists( "{$this->prefix}_global_state" ) );
	}

	public function test_a_large_network_cleans_only_the_current_site_and_logs_it(): void {
		\add_filter( 'wp_is_large_network', '__return_true' );
		$this->seed_site( $this->main_site, 'yes' );
		$this->seed_site( $this->other_site, 'yes' );

		$log = $this->capture_error_log( fn () => uninstall( self::PLUGIN_FILE, $this->network_footprint(), array( 'network' => true ) + $this->args( true ) ) );

		self::assertCount( 1, $log );
		self::assertStringEndsWith( '] Uninstall cleaned only the current site, because the network is large.', $log[0] );
		self::assertSame( array( false, false, false, false ), $this->site_state( $this->main_site ) );
		self::assertSame( array( true, true, true, true ), $this->site_state( $this->other_site ) );
		self::assertTrue( $this->user_meta_exists( "{$this->prefix}_global_state" ) );
		self::assertSame( array( "handler:site:$this->main_site", "cleanup:site:$this->main_site" ), $this->summarize_calls() );
	}

	protected function network_footprint(): array {
		return array(
			'options'          => array( "{$this->prefix}_settings" ),
			'user_meta_site'   => array( "{$this->prefix}_site_state" ),
			'user_meta_global' => array( "{$this->prefix}_global_state" ),
			'cron_hooks'       => array( "{$this->prefix}_cron" ),
			'order_meta'       => array(),
		);
	}

	protected function args( bool $constant = false ): array {
		$consent = array( 'option' => "{$this->prefix}_remove_data" );
		if ( $constant ) {
			$consent['constant'] = \strtoupper( "{$this->prefix}_remove_all_data" );
			\define( $consent['constant'], true ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.VariableConstantNameFound -- A unique name per test, since a constant cannot be undefined.
		}

		return array( 'consent' => $consent ) + $this->recording_callables( array( 'order_meta' ) );
	}

	protected function seed_site( int $site_id, string $consent ): void {
		$this->on_site(
			$site_id,
			function () use ( $consent ): void {
				global $wpdb;

				\update_option( "{$this->prefix}_remove_data", $consent );
				\update_option( "{$this->prefix}_settings", 'x' );
				\add_user_meta( $this->user, $wpdb->get_blog_prefix() . "{$this->prefix}_site_state", 'x' );
				\wp_schedule_single_event( \time() + 3600, "{$this->prefix}_cron" );
			}
		);
	}

	protected function site_state( int $site_id ): array {
		return $this->on_site(
			$site_id,
			function (): array {
				global $wpdb;

				return array(
					$this->option_exists( "{$this->prefix}_settings" ),
					$this->user_meta_exists( $wpdb->get_blog_prefix() . "{$this->prefix}_site_state" ),
					false !== \wp_next_scheduled( "{$this->prefix}_cron" ),
					$this->option_exists( "{$this->prefix}_remove_data" ),
				);
			}
		);
	}

	protected function summarize_calls(): array {
		return \array_map( static fn ( array $call ): string => "{$call[0]}:{$call[3]['scope']}:{$call[3]['site_id']}", $this->calls );
	}

	protected function on_site( int $site_id, \Closure $operation ): mixed {
		\switch_to_blog( $site_id );
		try {
			return $operation();
		} finally {
			\restore_current_blog();
		}
	}
}
