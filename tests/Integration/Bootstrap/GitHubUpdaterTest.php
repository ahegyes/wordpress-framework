<?php declare( strict_types=1 );

namespace DeepWebSolutions\Framework\Tests\Integration\Bootstrap;

use DeepWebSolutions\Framework\Tests\Integration\Support\IsolatesHooksTrait;
use PHPUnit\Framework\Attributes\CoversFunction;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

use function DeepWebSolutions\Framework\Bootstrap\register_github_updater;

#[CoversFunction( 'DeepWebSolutions\Framework\Bootstrap\register_github_updater' )]
final class GitHubUpdaterTest extends TestCase {
	use IsolatesHooksTrait;

	protected string $slug;

	protected array $plugin_files = array();

	protected array $responses = array();

	protected array $requests = array();

	#[\Override]
	public static function setUpBeforeClass(): void {
		require_once \dirname( __DIR__, 3 ) . '/packages/bootstrap/load.php';
		require_once ABSPATH . 'wp-admin/includes/plugin-install.php';
	}

	#[\Override]
	protected function setUp(): void {
		$this->slug = 'acme-updater-' . \bin2hex( \random_bytes( 3 ) );
		\add_filter( 'pre_http_request', array( $this, 'answer_request' ), 10, 3 );
	}

	#[\Override]
	protected function tearDown(): void {
		foreach ( $this->plugin_files as $plugin_file ) {
			$slug = \basename( $plugin_file, '.php' );
			\delete_transient( "{$slug}_github_release_stable" );
			\delete_transient( "{$slug}_github_release_prerelease" );
			\wp_delete_file( $plugin_file );
			\rmdir( \dirname( $plugin_file ) );
		}
		\delete_site_transient( 'update_plugins' );
		\wp_clean_plugins_cache( false );
	}

	public static function unusable_releases(): array {
		return array(
			'failed-fetch'            => array( static fn ( self $test ) => $test->stage( $test->api_url( 'releases/latest' ), 500, '' ) ),
			'unreachable-api'         => array( static fn ( self $test ) => null ),
			'release-without-assets'  => array( static fn ( self $test ) => $test->stage_latest( array( 'assets' => array() ) + $test->release( 'v2.0.0' ) ) ),
			'release-without-the-zip' => array(
				static function ( self $test ): void {
					$release           = $test->release( 'v2.0.0' );
					$release['assets'] = \array_slice( $release['assets'], 0, 1 );
					$test->stage_latest( $release );
				},
			),
			'unreadable-requirements' => array(
				static function ( self $test ): void {
					$test->stage_latest( $test->release( 'v2.0.0' ) );
					$test->stage( $test->raw_url( 'v2.0.0' ), 404, '404: Not Found' );
				},
			),
		);
	}

	public static function prerelease_lists(): array {
		return array(
			'newer-prerelease-listed-second' => array( '1.2.0-beta.1', array( 'v1.2.0-beta.1', 'v1.2.0-beta.2' ), '1.2.0-beta.2' ),
			'stable-successor'               => array( '1.2.0-beta.2', array( 'v1.2.0', 'v1.2.0-beta.2' ), '1.2.0' ),
		);
	}

	public static function channel_switches(): array {
		return array(
			'prerelease-then-stable' => array( '2.0.0-beta.1', 'releases?per_page=10', '1.0.0', 'releases/latest' ),
			'stable-then-prerelease' => array( '1.0.0', 'releases/latest', '2.0.0-beta.1', 'releases?per_page=10' ),
		);
	}

	public static function foreign_details_requests(): array {
		return array(
			'another-slug'     => array( false, 'plugin_information', 'acme-another' ),
			'another-action'   => array( false, 'query_plugins', null ),
			'already-answered' => array( (object) array( 'name' => 'Answered elsewhere' ), 'plugin_information', null ),
		);
	}

	public static function plugins_without_updates(): array {
		return array(
			'no-update-uri'  => array( array( 'Update URI' => '' ) ),
			'another-host'   => array( array( 'Update URI' => 'https://gitlab.com/acme/updater' ) ),
			'no-text-domain' => array( array( 'Text Domain' => '' ) ),
		);
	}

	public static function unusable_configurations(): array {
		return array(
			'disabled'                 => array( static fn ( array $config ): array => array( 'enabled' => false ) + $config ),
			'not-an-array'             => array( static fn ( array $config ): mixed => null ),
			'repository-without-owner' => array( static fn ( array $config ): array => array( 'repository' => 'updater' ) + $config ),
			'repository-with-newline'  => array( static fn ( array $config ): array => array( 'repository' => "{$config['repository']}\n" ) + $config ),
			'empty-asset'              => array( static fn ( array $config ): array => array( 'asset' => '' ) + $config ),
			'unknown-channel'          => array( static fn ( array $config ): array => array( 'channel' => 'nightly' ) + $config ),
		);
	}

	public function test_a_stable_install_is_offered_the_latest_release_packaged_as_the_asset_named_after_its_text_domain(): void {
		register_github_updater( $this->write_plugin( $this->slug ) );
		$this->stage_latest( $this->release( 'v2.0.0' ) );

		$offer = $this->check_for_updates()->response[ $this->basename() ] ?? null;

		self::assertSame( $this->api_url( 'releases/latest' ), $this->requests[0] );
		self::assertIsObject( $offer );
		self::assertSame( '2.0.0', $offer->new_version );
		self::assertSame( $this->slug, $offer->slug );
		self::assertSame( "https://github.com/acme/$this->slug/releases/tag/v2.0.0", $offer->url );
		self::assertSame( $this->asset_url( 'v2.0.0' ), $offer->package );
	}

	public function test_a_prerelease_install_skips_drafts_and_caches_under_its_own_channel(): void {
		register_github_updater( $this->write_plugin( $this->slug, array( 'Version' => '1.1.0-beta.3' ) ) );
		$this->stage_list( array( array( 'draft' => true ) + $this->release( 'v1.2.0-beta.2' ), $this->release( 'v1.2.0-beta.1' ) ) );

		$offer = $this->check_for_updates()->response[ $this->basename() ] ?? null;

		self::assertSame( $this->api_url( 'releases?per_page=10' ), $this->requests[0] );
		self::assertIsObject( $offer );
		self::assertSame( '1.2.0-beta.1', $offer->new_version );
		self::assertIsArray( \get_transient( "{$this->slug}_github_release_prerelease" ) );
		self::assertFalse( \get_transient( "{$this->slug}_github_release_stable" ) );
	}

	#[DataProvider( 'prerelease_lists' )]
	public function test_a_prerelease_install_is_offered_the_highest_listed_version( string $installed, array $tags, string $expected ): void {
		register_github_updater( $this->write_plugin( $this->slug, array( 'Version' => $installed ) ) );
		$this->stage_list( \array_map( array( $this, 'release' ), $tags ) );

		$offer = $this->check_for_updates()->response[ $this->basename() ] ?? null;

		self::assertIsObject( $offer );
		self::assertSame( $expected, $offer->new_version );
		self::assertSame( $this->asset_url( "v$expected" ), $offer->package );
	}

	public function test_an_up_to_date_install_is_listed_without_an_update_and_its_release_is_cached_for_an_hour(): void {
		register_github_updater( $this->write_plugin( $this->slug, array( 'Version' => '2.0.0' ) ) );
		$this->stage_latest( $this->release( 'v2.0.0' ) );

		$updates = $this->check_for_updates();

		self::assertArrayNotHasKey( $this->basename(), $updates->response );
		self::assertSame( '2.0.0', $updates->no_update[ $this->basename() ]->new_version ?? null );
		self::assertEqualsWithDelta( \HOUR_IN_SECONDS, $this->cache_lifetime( 'stable' ), 5 );
	}

	#[DataProvider( 'unusable_releases' )]
	public function test_an_unusable_release_offers_nothing_and_is_fetched_again_after_five_minutes( \Closure $stage ): void {
		register_github_updater( $this->write_plugin( $this->slug ) );
		$stage( $this );

		$updates  = $this->check_for_updates();
		$requests = $this->requests;

		self::assertNotEmpty( $requests );
		self::assertArrayNotHasKey( $this->basename(), $updates->response );
		self::assertArrayNotHasKey( $this->basename(), $updates->no_update );
		self::assertSame( array(), \get_transient( "{$this->slug}_github_release_stable" ) );
		self::assertEqualsWithDelta( 5 * \MINUTE_IN_SECONDS, $this->cache_lifetime( 'stable' ), 5 );

		$this->check_for_updates();
		self::assertSame( $requests, $this->requests );

		\update_option( "_transient_timeout_{$this->slug}_github_release_stable", \time() - 1 );
		$this->check_for_updates();
		self::assertSame( \array_merge( $requests, $requests ), $this->requests );
	}

	public function test_a_cached_record_without_a_package_is_fetched_again(): void {
		register_github_updater( $this->write_plugin( $this->slug ) );
		\set_transient( "{$this->slug}_github_release_stable", array( 'version' => '9.0.0' ), \HOUR_IN_SECONDS );
		$this->stage_latest( $this->release( 'v2.0.0' ) );

		$offer = $this->check_for_updates()->response[ $this->basename() ] ?? null;

		self::assertSame( '2.0.0', $offer->new_version ?? null );
		self::assertSame( $this->asset_url( 'v2.0.0' ), \get_transient( "{$this->slug}_github_release_stable" )['package'] ?? null );
	}

	public function test_another_plugins_check_passes_through_without_a_request(): void {
		register_github_updater( $this->write_plugin( $this->slug ) );
		$this->write_plugin( "$this->slug-other" );
		$this->stage_latest( $this->release( 'v2.0.0' ) );

		$updates = $this->check_for_updates();

		self::assertArrayNotHasKey( $this->basename( "$this->slug-other" ), $updates->response );
		self::assertArrayNotHasKey( $this->basename( "$this->slug-other" ), $updates->no_update );
		self::assertSame( array( $this->api_url( 'releases/latest' ), $this->raw_url( 'v2.0.0' ) ), $this->requests );
	}

	public function test_a_check_an_earlier_filter_answered_passes_through_without_a_request(): void {
		register_github_updater( $this->write_plugin( $this->slug ) );
		$basename = $this->basename();
		\add_filter(
			'update_plugins_github.com',
			static fn ( $update, array $plugin_data, string $plugin_file ): mixed => $basename === $plugin_file ? array( 'version' => '9.9.9' ) : $update,
			9,
			3
		);

		$offer = $this->check_for_updates()->response[ $basename ] ?? null;

		self::assertSame( '9.9.9', $offer->new_version ?? null );
		self::assertSame( array(), $this->requests );
	}

	public function test_two_plugins_never_answer_for_each_other(): void {
		$other = "$this->slug-other";
		register_github_updater( $this->write_plugin( $this->slug ) );
		register_github_updater( $this->write_plugin( $other ) );
		$this->stage_latest( $this->release( 'v2.0.0' ) );
		$this->stage_latest( $this->release( 'v3.0.0', $other ), $other );

		$updates = $this->check_for_updates();

		self::assertSame( $this->asset_url( 'v2.0.0' ), $updates->response[ $this->basename() ]->package ?? null );
		self::assertSame( $this->asset_url( 'v3.0.0', $other ), $updates->response[ $this->basename( $other ) ]->package ?? null );
		self::assertCount( 4, $this->requests );
	}

	#[DataProvider( 'channel_switches' )]
	public function test_each_channel_caches_separately_so_a_switch_never_serves_the_other_channels_release( string $first_version, string $first_path, string $second_version, string $second_path ): void {
		register_github_updater( $this->write_plugin( $this->slug, array( 'Version' => $first_version ) ) );
		$this->stage_release_at( $first_path, $this->release( 'v3.0.0-beta.1' ) );
		$this->check_for_updates();

		$this->write_plugin( $this->slug, array( 'Version' => $second_version ) );
		$this->stage_release_at( $second_path, $this->release( 'v2.5.0' ) );
		$offer = $this->check_for_updates()->response[ $this->basename() ] ?? null;

		self::assertSame( $this->api_url( $second_path ), $this->requests[2] );
		self::assertIsObject( $offer );
		self::assertSame( '2.5.0', $offer->new_version );
	}

	public function test_details_for_its_own_slug_show_the_cached_release_without_another_request(): void {
		register_github_updater( $this->write_plugin( $this->slug ) );
		$requirements = array(
			'Requires at least' => '6.0',
			'Requires PHP'      => '8.1',
		);
		$this->stage_latest( array( 'body' => "Fixes <b>the</b> widget.\nAdds a setting." ) + $this->release( 'v2.0.0' ), '', $requirements );
		$this->check_for_updates();

		$details = \plugins_api( 'plugin_information', array( 'slug' => $this->slug ) );

		self::assertIsObject( $details );
		self::assertSame( 'Acme Updater', $details->name );
		self::assertSame( $this->slug, $details->slug );
		self::assertSame( '2.0.0', $details->version );
		self::assertSame( $this->asset_url( 'v2.0.0' ), $details->download_link );
		self::assertSame( '6.0', $details->requires );
		self::assertSame( '8.1', $details->requires_php );
		self::assertTrue( $details->external );
		self::assertSame( "Fixes &lt;b&gt;the&lt;/b&gt; widget.<br />\nAdds a setting.", $details->sections['changelog'] );
		self::assertCount( 2, $this->requests );
	}

	public function test_details_for_its_own_slug_stay_local_without_a_release(): void {
		register_github_updater( $this->write_plugin( $this->slug ) );
		$this->stage( $this->api_url( 'releases/latest' ), 500, '' );

		$details = \plugins_api( 'plugin_information', array( 'slug' => $this->slug ) );

		self::assertIsObject( $details );
		self::assertSame( 'Acme Updater', $details->name );
		self::assertSame( $this->slug, $details->slug );
		self::assertSame( '1.0.0', $details->version );
		self::assertTrue( $details->external );
		self::assertObjectNotHasProperty( 'download_link', $details );
		self::assertObjectNotHasProperty( 'sections', $details );
	}

	#[DataProvider( 'foreign_details_requests' )]
	public function test_other_details_requests_pass_through_without_a_request( mixed $result, string $action, ?string $slug ): void {
		register_github_updater( $this->write_plugin( $this->slug ) );

		$details = \apply_filters( 'plugins_api', $result, $action, (object) array( 'slug' => $slug ?? $this->slug ) );

		self::assertSame( $result, $details );
		self::assertSame( array(), $this->requests );
	}

	#[DataProvider( 'plugins_without_updates' )]
	public function test_a_plugin_without_a_github_update_uri_or_a_text_domain_gets_no_updates( array $headers ): void {
		$plugin_file = $this->write_plugin( $this->slug, $headers );

		register_github_updater( $plugin_file );

		self::assertFalse( \apply_filters( 'update_plugins_github.com', false, \get_plugin_data( $plugin_file, false, false ), $this->basename(), array() ) );
		self::assertFalse( \apply_filters( 'plugins_api', false, 'plugin_information', (object) array( 'slug' => $this->slug ) ) );
		self::assertSame( array(), $this->requests );
	}

	public function test_an_offer_carries_the_release_requirements_and_a_php_floor_never_withholds_it(): void {
		register_github_updater( $this->write_plugin( $this->slug ) );
		$requirements = array(
			'Requires at least' => '5.8',
			'Requires PHP'      => '99',
		);
		$this->stage_latest( $this->release( 'v2.0.0' ), '', $requirements );

		$offer = $this->check_for_updates()->response[ $this->basename() ] ?? null;

		self::assertIsObject( $offer );
		self::assertSame( '5.8', $offer->requires );
		self::assertSame( '99', $offer->requires_php );
	}

	public function test_an_offer_needing_a_newer_wordpress_is_withheld(): void {
		register_github_updater( $this->write_plugin( $this->slug ) );
		$this->stage_latest( $this->release( 'v2.0.0' ), '', array( 'Requires at least' => '99' ) );

		$updates = $this->check_for_updates();

		self::assertArrayNotHasKey( $this->basename(), $updates->response );
		self::assertArrayNotHasKey( $this->basename(), $updates->no_update );
		self::assertEqualsWithDelta( \HOUR_IN_SECONDS, $this->cache_lifetime( 'stable' ), 5 );
	}

	public function test_the_configuration_filter_runs_at_check_time_with_the_defaults_and_the_basename(): void {
		register_github_updater( $this->write_plugin( $this->slug ) );
		$calls = array();
		\add_filter(
			"dws_github_updater_config_$this->slug",
			static function ( array $config, string $plugin_basename ) use ( &$calls ): array {
				$calls[] = array( $config, $plugin_basename );

				return $config;
			},
			10,
			2
		);
		$this->stage_latest( $this->release( 'v2.0.0' ) );

		$this->check_for_updates();
		\plugins_api( 'plugin_information', array( 'slug' => $this->slug ) );

		$defaults = array(
			'enabled'    => true,
			'repository' => "acme/$this->slug",
			'asset'      => "$this->slug.zip",
			'channel'    => 'stable',
		);
		self::assertSame( \array_fill( 0, 2, array( $defaults, $this->basename() ) ), $calls );
	}

	#[DataProvider( 'unusable_configurations' )]
	public function test_a_disabled_or_unusable_configuration_offers_nothing_and_leaves_details_to_wordpress( \Closure $filter ): void {
		register_github_updater( $this->write_plugin( $this->slug ) );
		\add_filter( "dws_github_updater_config_$this->slug", $filter );
		$this->stage_latest( $this->release( 'v2.0.0' ) );

		$updates = $this->check_for_updates();

		self::assertArrayNotHasKey( $this->basename(), $updates->response );
		self::assertArrayNotHasKey( $this->basename(), $updates->no_update );
		self::assertFalse( \apply_filters( 'plugins_api', false, 'plugin_information', (object) array( 'slug' => $this->slug ) ) );
		self::assertSame( array(), $this->requests );
	}

	public function test_a_filtered_repository_asset_and_channel_drive_the_request_the_package_and_the_cache(): void {
		register_github_updater( $this->write_plugin( $this->slug ) );
		\add_filter(
			"dws_github_updater_config_$this->slug",
			static fn (): array => array(
				'repository' => 'acme/elsewhere',
				'asset'      => 'bundle.zip',
				'channel'    => 'prerelease',
			)
		);
		$release           = $this->release( 'v2.0.0-beta.1' );
		$release['assets'] = array(
			array(
				'name'                 => 'bundle.zip',
				'browser_download_url' => 'https://github.com/acme/elsewhere/releases/download/v2.0.0-beta.1/bundle.zip',
			),
		) + $release['assets'];
		$this->stage( 'https://api.github.com/repos/acme/elsewhere/releases?per_page=10', 200, (string) \wp_json_encode( array( $release ) ) );
		$this->stage( "https://raw.githubusercontent.com/acme/elsewhere/v2.0.0-beta.1/$this->slug.php", 200, "<?php\n" );

		$offer = $this->check_for_updates()->response[ $this->basename() ] ?? null;

		self::assertSame( 'https://api.github.com/repos/acme/elsewhere/releases?per_page=10', $this->requests[0] );
		self::assertSame( 'https://github.com/acme/elsewhere/releases/download/v2.0.0-beta.1/bundle.zip', $offer->package ?? null );
		self::assertIsArray( \get_transient( "{$this->slug}_github_release_prerelease" ) );
		self::assertFalse( \get_transient( "{$this->slug}_github_release_stable" ) );
	}

	public function answer_request( mixed $preempt, array $args, string $url ): mixed {
		if ( \str_starts_with( $url, 'https://api.wordpress.org/plugins/update-check/' ) ) {
			$body = array(
				'plugins'      => array(),
				'translations' => array(),
				'no_update'    => array(),
			);

			return self::response( 200, (string) \wp_json_encode( $body ) );
		}

		if ( \in_array( \wp_parse_url( $url, \PHP_URL_HOST ), array( 'api.github.com', 'raw.githubusercontent.com' ), true ) ) {
			$this->requests[] = $url;
		}

		return $this->responses[ $url ] ?? new \WP_Error( 'http_request_failed', "Nothing answers $url." );
	}

	public function release( string $tag, string $slug = '' ): array {
		$slug = '' === $slug ? $this->slug : $slug;

		return array(
			'tag_name' => $tag,
			'draft'    => false,
			'html_url' => "https://github.com/acme/$slug/releases/tag/$tag",
			'body'     => "Notes for $tag.",
			'assets'   => array(
				array(
					'name'                 => 'checksums.txt',
					'browser_download_url' => "https://github.com/acme/$slug/releases/download/$tag/checksums.txt",
				),
				array(
					'name'                 => "$slug.zip",
					'browser_download_url' => $this->asset_url( $tag, $slug ),
				),
			),
		);
	}

	public function stage( string $url, int $code, string $body ): void {
		$this->responses[ $url ] = self::response( $code, $body );
	}

	public function stage_latest( array $release, string $slug = '', array $requirements = array() ): void {
		$this->stage( $this->api_url( 'releases/latest', $slug ), 200, (string) \wp_json_encode( $release ) );
		$this->stage_entry_file( $release['tag_name'], $slug, $requirements );
	}

	public function api_url( string $path, string $slug = '' ): string {
		return 'https://api.github.com/repos/acme/' . ( '' === $slug ? $this->slug : $slug ) . "/$path";
	}

	public function raw_url( string $tag, string $slug = '' ): string {
		$slug = '' === $slug ? $this->slug : $slug;

		return "https://raw.githubusercontent.com/acme/$slug/$tag/$slug.php";
	}

	protected function stage_list( array $releases ): void {
		$this->stage( $this->api_url( 'releases?per_page=10' ), 200, (string) \wp_json_encode( $releases ) );
		foreach ( $releases as $release ) {
			$this->stage_entry_file( $release['tag_name'] );
		}
	}

	protected function stage_release_at( string $path, array $release ): void {
		$this->stage( $this->api_url( $path ), 200, (string) \wp_json_encode( 'releases/latest' === $path ? $release : array( $release ) ) );
		$this->stage_entry_file( $release['tag_name'] );
	}

	protected function stage_entry_file( string $tag, string $slug = '', array $requirements = array() ): void {
		$lines = array( 'Plugin Name: Acme Updater' );
		foreach ( $requirements as $name => $value ) {
			$lines[] = "$name: $value";
		}
		$this->stage( $this->raw_url( $tag, $slug ), 200, "<?php\n/**\n * " . \implode( "\n * ", $lines ) . " */\n" );
	}

	protected function asset_url( string $tag, string $slug = '' ): string {
		$slug = '' === $slug ? $this->slug : $slug;

		return "https://github.com/acme/$slug/releases/download/$tag/$slug.zip";
	}

	protected function basename( string $slug = '' ): string {
		$slug = '' === $slug ? $this->slug : $slug;

		return "$slug/$slug.php";
	}

	protected function write_plugin( string $slug, array $headers = array() ): string {
		$headers += array(
			'Plugin Name' => 'Acme Updater',
			'Version'     => '1.0.0',
			'Text Domain' => $slug,
			'Update URI'  => "https://github.com/acme/$slug",
		);

		$lines = array();
		foreach ( \array_filter( $headers ) as $name => $value ) {
			$lines[] = "$name: $value";
		}

		$plugin_file = \WP_PLUGIN_DIR . "/$slug/$slug.php";
		\wp_mkdir_p( \dirname( $plugin_file ) );
		\file_put_contents( $plugin_file, "<?php\n/**\n * " . \implode( "\n * ", $lines ) . "\n */\n" );
		if ( ! \in_array( $plugin_file, $this->plugin_files, true ) ) {
			$this->plugin_files[] = $plugin_file;
		}
		\wp_clean_plugins_cache( false );

		return $plugin_file;
	}

	protected function check_for_updates(): object {
		\delete_site_transient( 'update_plugins' );
		\wp_update_plugins();

		$updates = \get_site_transient( 'update_plugins' );
		self::assertIsObject( $updates );

		return $updates;
	}

	protected function cache_lifetime( string $channel ): int {
		return (int) \get_option( "_transient_timeout_{$this->slug}_github_release_$channel" ) - \time();
	}

	protected static function response( int $code, string $body ): array {
		return array(
			'headers'  => array(),
			'body'     => $body,
			'response' => array(
				'code'    => $code,
				'message' => '',
			),
			'cookies'  => array(),
			'filename' => null,
		);
	}
}
