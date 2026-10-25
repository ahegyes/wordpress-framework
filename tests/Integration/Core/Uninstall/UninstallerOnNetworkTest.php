<?php declare( strict_types=1 );

namespace DeepWebSolutions\Framework\Tests\Integration\Core\Uninstall;

use DeepWebSolutions\Framework\Core\Uninstall\Uninstaller;
use DeepWebSolutions\Framework\Shared\Exception\RuntimeException;
use DeepWebSolutions\Framework\Tests\Integration\Support\CreatesSitesTrait;
use DeepWebSolutions\Framework\Tests\Integration\Support\CreatesUsersTrait;
use DeepWebSolutions\Framework\Tests\Integration\Support\IsolatesHooksTrait;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;

#[CoversClass( Uninstaller::class )]
#[Group( 'multisite' )]
final class UninstallerOnNetworkTest extends TestCase {
	use CreatesSitesTrait;
	use CreatesUsersTrait;
	use IsolatesHooksTrait;
	use UninstallFixturesTrait;

	protected int $main_site;

	protected int $other_site;

	protected int $user;

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
		foreach ( \get_sites(
			array(
				'fields' => 'ids',
				'number' => 0,
			)
		) as $site_id ) {
			$this->on_site( (int) $site_id, fn () => \wp_unschedule_hook( "{$this->prefix}_cron" ) );
		}
	}

	public function test_a_subsite_only_plugin_deleted_from_the_network_admin_cleans_its_subsite_and_unschedules_everywhere(): void {
		$this->seed_site( $this->main_site, null );
		$this->seed_site( $this->other_site, 'yes' );

		Uninstaller::run( self::PLUGIN_FILE, $this->network_footprint(), $this->args() );

		self::assertSame( array( true, true, false, false ), $this->site_state( $this->main_site ) );
		self::assertSame( array( false, false, false, false ), $this->site_state( $this->other_site ) );
		self::assertTrue( $this->user_meta_exists( "{$this->prefix}_global_state" ) );
		self::assertSame( array( "cleanup:site:$this->other_site" ), $this->summarize_calls() );
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

		Uninstaller::run( self::PLUGIN_FILE, $this->network_footprint(), $this->args( $constant ) );

		// The constant consents for every site of the network, which may hold more sites than the test created.
		$consenting = $constant ? \get_sites(
			array(
				'fields' => 'ids',
				'number' => 0,
			)
		) : array( $this->main_site, $this->other_site );
		$expected   = array();
		foreach ( $consenting as $site_id ) {
			$expected[] = "cleanup:site:$site_id";
		}
		if ( $constant ) {
			$expected[] = "cleanup:network:$this->main_site";
		}
		self::assertSame( $expected, $this->summarize_calls() );
		self::assertSame( ! $constant, $this->user_meta_exists( "{$this->prefix}_global_state" ) );
	}

	public function test_a_throw_on_one_site_leaves_the_other_sites_swept_and_is_thrown_after_the_rest(): void {
		$this->seed_site( $this->main_site, 'yes' );
		$this->seed_site( $this->other_site, 'yes' );
		$main_site = $this->main_site;
		$filter    = static function ( mixed $value ) use ( $main_site ): mixed {
			if ( \get_current_blog_id() === $main_site ) {
				throw new \LogicException( 'The filter failed.' );
			}

			return $value;
		};
		\add_filter( "option_{$this->prefix}_remove_data", $filter );

		$failure = null;
		try {
			Uninstaller::run( self::PLUGIN_FILE, $this->network_footprint(), $this->args() );
		} catch ( RuntimeException $exception ) {
			$failure = $exception;
		} finally {
			\remove_filter( "option_{$this->prefix}_remove_data", $filter );
		}

		self::assertInstanceOf( RuntimeException::class, $failure );
		self::assertStringContainsString( "site $this->main_site: The filter failed.", $failure->getMessage() );
		self::assertSame( array( true, true, false, true ), $this->site_state( $this->main_site ) );
		self::assertSame( array( false, false, false, false ), $this->site_state( $this->other_site ) );
		self::assertSame( array( "cleanup:site:$this->other_site" ), $this->summarize_calls() );
	}

	public function test_a_web_request_on_a_large_network_refuses_before_any_change_and_names_wp_plugin_uninstall(): void {
		self::assertFalse( \defined( 'WP_CLI' ) );
		\add_filter( 'wp_is_large_network', '__return_true' );
		$this->seed_site( $this->main_site, 'yes' );
		$this->seed_site( $this->other_site, 'yes' );

		$failure = null;
		try {
			Uninstaller::run( self::PLUGIN_FILE, $this->network_footprint(), $this->args( true ) );
		} catch ( RuntimeException $exception ) {
			$failure = $exception;
		}

		self::assertInstanceOf( RuntimeException::class, $failure );
		self::assertStringContainsString( '`wp plugin uninstall`', $failure->getMessage() );
		self::assertSame( array( true, true, true, true ), $this->site_state( $this->main_site ) );
		self::assertSame( array( true, true, true, true ), $this->site_state( $this->other_site ) );
		self::assertTrue( $this->user_meta_exists( "{$this->prefix}_global_state" ) );
		self::assertSame( array(), $this->calls );
	}

	#[RunInSeparateProcess]
	#[PreserveGlobalState( false )]
	public function test_wp_cli_sweeps_a_large_network(): void {
		\define( 'WP_CLI', true ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedConstantFound -- WP-CLI's own constant, in a process of its own.
		\add_filter( 'wp_is_large_network', '__return_true' );
		$this->seed_site( $this->main_site, 'yes' );
		$this->seed_site( $this->other_site, 'yes' );

		Uninstaller::run( self::PLUGIN_FILE, $this->network_footprint(), $this->args() );

		self::assertSame( array( false, false, false, false ), $this->site_state( $this->main_site ) );
		self::assertSame( array( false, false, false, false ), $this->site_state( $this->other_site ) );
	}

	public function test_memory_stays_flat_across_a_sixty_site_sweep(): void {
		$site_ids = \array_map( fn ( int $number ): int => $this->make_site( "acme-sweep-$number" ), \range( 1, 60 ) );
		foreach ( $site_ids as $site_id ) {
			$this->on_site( $site_id, fn () => \wp_schedule_single_event( \time() + 3600, "{$this->prefix}_cron" ) );
		}
		\wp_cache_flush();
		\gc_collect_cycles();
		$before = \memory_get_usage();

		Uninstaller::run( self::PLUGIN_FILE, $this->network_footprint(), $this->args() );

		\gc_collect_cycles();
		$growth = \memory_get_usage() - $before;
		foreach ( $site_ids as $site_id ) {
			self::assertFalse( $this->on_site( $site_id, fn () => \wp_next_scheduled( "{$this->prefix}_cron" ) ) );
		}
		// Each visited site would otherwise keep about 35 KB of options in WordPress's runtime cache.
		self::assertLessThan( 1024 * 1024, $growth );
	}

	protected function network_footprint(): array {
		return array(
			'options'          => array( "{$this->prefix}_settings" ),
			'user_meta_site'   => array( "{$this->prefix}_site_state" ),
			'user_meta_global' => array( "{$this->prefix}_global_state" ),
			'cron_hooks'       => array( "{$this->prefix}_cron" ),
		);
	}

	protected function args( bool $constant = false ): array {
		$consent = array( 'option' => "{$this->prefix}_remove_data" );
		if ( $constant ) {
			$consent['constant'] = \strtoupper( "{$this->prefix}_remove_all_data" );
			\define( $consent['constant'], true ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.VariableConstantNameFound -- A unique name per test, since a constant cannot be undefined.
		}

		return array( 'consent' => $consent ) + $this->recording_cleanup();
	}

	// A null consent leaves the consent option unset, as on a site where the plugin never ran.
	protected function seed_site( int $site_id, ?string $consent ): void {
		$this->on_site(
			$site_id,
			function () use ( $consent ): void {
				global $wpdb;

				if ( null !== $consent ) {
					\update_option( "{$this->prefix}_remove_data", $consent );
				}
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
		return \array_map( static fn ( array $call ): string => "{$call[0]}:{$call[1]['scope']}:{$call[1]['site_id']}", $this->calls );
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
