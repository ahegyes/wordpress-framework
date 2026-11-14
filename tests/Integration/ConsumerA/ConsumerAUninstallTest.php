<?php declare( strict_types=1 );

namespace DeepWebSolutions\Framework\Tests\Integration\ConsumerA;

use DeepWebSolutions\Framework\Tests\Integration\Support\CreatesUsersTrait;
use DeepWebSolutions\Framework\Tests\Integration\Support\InstallsConsumersTrait;
use DeepWebSolutions\Framework\Tests\Integration\Support\ProductFixturesTrait;
use PHPUnit\Framework\Attributes\After;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversNothing]
final class ConsumerAUninstallTest extends TestCase {
	use CreatesUsersTrait;
	use InstallsConsumersTrait;
	use ProductFixturesTrait;

	protected const string DIR = 'acme-consumer-a-uninstall';

	protected int $product_id = 0;

	protected int $user_id = 0;

	protected string $log = '';

	public static function engines_that_cannot_run(): array {
		return array(
			'bootstrap-loader'  => array( array( 'vendor-prefixed/ahegyes/wp-framework-bootstrap/load.php' ), array() ),
			'autoloader'        => array( array( 'vendor/autoload.php' ), array() ),
			'scoped-autoloader' => array( array( 'vendor-prefixed/scoper-autoload.php' ), array() ),
			'composition-root'  => array( array( 'src/Plugin.php' ), array() ),
			'scoped-engine'     => array( array( 'vendor-prefixed/ahegyes/wp-framework-core/src/Uninstall/Uninstaller.php' ), array() ),
			'options'           => array( array( 'src/Options.php' ), array() ),
			'below-php-floor'   => array( array(), array( 'Requires PHP' => '99' ) ),
		);
	}

	public function test_a_consenting_web_uninstall_removes_the_footprint_and_the_files(): void {
		$this->seed_footprint();
		$plugin  = $this->install_consumer( 'consumer-a', self::DIR );
		$request = $this->uninstall_on_the_web( $plugin, array() );

		self::assertTrue( $request['records']['deleted'] );
		self::assertDirectoryDoesNotExist( WP_PLUGIN_DIR . '/' . self::DIR );
		self::assertFalse( get_option( 'acme_ca_delete_data' ) );
		self::assertFalse( get_option( 'acme_ca_revision' ) );
		self::assertSame( '', get_post_meta( $this->product_id, '_acme_ca_packing_note', true ) );
		self::assertFalse( get_option( 'acme_ca_notices' ) );
		self::assertFalse( get_network_option( null, 'acme_ca_network_notices' ) );
		self::assertFalse( get_transient( 'acme_ca_boot_failure_seeded' ) );
		self::assertFalse( get_user_option( 'acme_ca_hidden_notices', $this->user_id ) );
		self::assertSame( '', get_user_meta( $this->user_id, 'acme_ca_network_hidden_notices', true ) );
	}

	public function test_a_web_uninstall_whose_engine_fails_shows_its_message_and_keeps_the_data_and_the_files(): void {
		global $wpdb;

		$this->seed_footprint();
		$plugin  = $this->install_consumer( 'consumer-a', self::DIR );
		$request = $this->uninstall_on_the_web( $plugin, array(), "DELETE FROM `$wpdb->options` WHERE `option_name` = 'acme_ca_revision'" );

		self::assertSame( 500, $request['died']['status'] ?? null );
		self::assertStringStartsWith( 'Uninstall could not remove everything: option acme_ca_revision: ', $request['died']['message'] ?? '' );
		self::assertStringEndsWith( ' exist.', $request['died']['message'] ?? '' );
		self::assertFileExists( WP_PLUGIN_DIR . '/' . self::DIR . '/acme-consumer-a.php' );
		self::assertSame( 'yes', get_option( 'acme_ca_delete_data' ) );
		self::assertSame( '1', get_option( 'acme_ca_revision' ) );
	}

	#[DataProvider( 'engines_that_cannot_run' )]
	public function test_a_web_uninstall_that_cannot_run_the_engine_keeps_the_data_logs_once_and_deletes_the_files( array $remove, array $headers ): void {
		$this->seed_footprint();
		$plugin  = $this->install_consumer( 'consumer-a', self::DIR, $remove, $headers );
		$request = $this->uninstall_on_the_web( $plugin, $remove );

		self::assertTrue( $request['records']['deleted'] );
		self::assertDirectoryDoesNotExist( WP_PLUGIN_DIR . '/' . self::DIR );
		self::assertSame( 'yes', get_option( 'acme_ca_delete_data' ) );
		self::assertSame( 'kept', get_post_meta( $this->product_id, '_acme_ca_packing_note', true ) );
		self::assertSame( 1, \substr_count( (string) \file_get_contents( $this->log ), 'Acme Consumer A kept its data on uninstall' ) );
	}

	#[DataProvider( 'engines_that_cannot_run' )]
	public function test_a_wp_cli_uninstall_that_cannot_run_the_engine_fails_and_keeps_the_data_and_the_files( array $remove, array $headers ): void {
		$this->seed_footprint();
		$this->install_consumer( 'consumer-a', self::DIR, $remove, $headers );

		list( $exit_code, $output, $errors ) = self::run_process( array( 'wp', 'plugin', 'uninstall', self::DIR ), ABSPATH );
		wp_cache_flush();

		self::assertNotSame( 0, $exit_code, $output . $errors );
		self::assertFileExists( WP_PLUGIN_DIR . '/' . self::DIR . '/acme-consumer-a.php' );
		self::assertSame( 'yes', get_option( 'acme_ca_delete_data' ) );
		self::assertSame( 'kept', get_post_meta( $this->product_id, '_acme_ca_packing_note', true ) );
	}

	#[After]
	protected function delete_request_log(): void {
		if ( '' !== $this->log && \is_file( $this->log ) ) {
			\unlink( $this->log ); // phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink -- Removes the request's log.
		}
	}

	protected function seed_footprint(): void {
		update_option( 'acme_ca_delete_data', 'yes' );
		update_option( 'acme_ca_revision', 1 );
		update_option( 'acme_ca_notices', array( 'seeded' ) );
		update_network_option( null, 'acme_ca_network_notices', array( 'seeded' ) );
		set_transient( 'acme_ca_boot_failure_seeded', 1, HOUR_IN_SECONDS );
		$this->user_id = $this->make_user( 'acme_ca_admin', 'administrator' );
		update_user_option( $this->user_id, 'acme_ca_hidden_notices', array( 'seeded' ) );
		update_user_meta( $this->user_id, 'acme_ca_network_hidden_notices', array( 'seeded' ) );

		$this->product_id = $this->make_product( array( '_acme_ca_packing_note' => 'kept' ) )->get_id();
	}

	protected function uninstall_on_the_web( string $plugin, array $missing, string $failing_query = '' ): array {
		$this->log = (string) \tempnam( \sys_get_temp_dir(), 'acme-ca-log-' );

		return $this->consumer_request(
			'GET',
			'index.php',
			$this->make_user( 'acme_ca_admin', 'administrator' ),
			array(
				'uninstall'     => $plugin,
				'missing'       => $missing,
				'log'           => $this->log,
				'failing_query' => $failing_query,
			)
		);
	}
}
