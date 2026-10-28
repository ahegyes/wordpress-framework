<?php declare( strict_types=1 );

namespace DeepWebSolutions\Framework\Tests\Integration\ConsumerA;

use DeepWebSolutions\Framework\Tests\Integration\Support\CreatesUsersTrait;
use DeepWebSolutions\Framework\Tests\Integration\Support\InstallsConsumersTrait;
use PHPUnit\Framework\Attributes\After;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversNothing]
final class ConsumerAUninstallTest extends TestCase {
	use CreatesUsersTrait;
	use InstallsConsumersTrait;

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
			'footprint'         => array( array( 'src/Footprint.php' ), array() ),
			'below-php-floor'   => array( array(), array( 'Requires PHP' => '99' ) ),
		);
	}

	public function test_a_consenting_web_uninstall_removes_the_footprint_and_the_files(): void {
		$this->seed_footprint();
		$plugin  = $this->install_consumer( 'consumer-a', self::DIR );
		$request = $this->uninstall_on_the_web( $plugin, array() );

		self::assertTrue( $request['records']['deleted'] );
		self::assertDirectoryDoesNotExist( WP_PLUGIN_DIR . '/' . self::DIR );
		self::assertFalse( get_option( 'acme_ca_settings' ) );
		self::assertFalse( get_option( 'acme_ca_revision' ) );
		self::assertSame( '', get_post_meta( $this->product_id, '_acme_ca_packing_note', true ) );
		self::assertFalse( get_option( 'acme_ca_notices' ) );
		self::assertFalse( get_network_option( null, 'acme_ca_network_notices' ) );
		self::assertFalse( get_transient( 'acme_ca_boot_failure_seeded' ) );
		self::assertFalse( get_user_option( 'acme_ca_hidden_notices', $this->user_id ) );
		self::assertSame( '', get_user_meta( $this->user_id, 'acme_ca_network_hidden_notices', true ) );
	}

	#[DataProvider( 'engines_that_cannot_run' )]
	public function test_a_web_uninstall_that_cannot_run_the_engine_keeps_the_data_logs_once_and_deletes_the_files( array $remove, array $headers ): void {
		$this->seed_footprint();
		$plugin  = $this->install_consumer( 'consumer-a', self::DIR, $remove, $headers );
		$request = $this->uninstall_on_the_web( $plugin, $remove );

		self::assertTrue( $request['records']['deleted'] );
		self::assertDirectoryDoesNotExist( WP_PLUGIN_DIR . '/' . self::DIR );
		self::assertSame( array( 'delete_data' => 'yes' ), get_option( 'acme_ca_settings' ) );
		self::assertSame( 'kept', get_post_meta( $this->product_id, '_acme_ca_packing_note', true ) );
		self::assertSame( 1, \substr_count( (string) \file_get_contents( $this->log ), 'Acme Consumer A kept its data on uninstall' ) );
	}

	#[DataProvider( 'engines_that_cannot_run' )]
	public function test_a_wp_cli_uninstall_that_cannot_run_the_engine_fails_and_keeps_the_data_and_the_files( array $remove, array $headers ): void {
		$this->seed_footprint();
		$this->install_consumer( 'consumer-a', self::DIR, $remove, $headers );

		$descriptors = array(
			1 => array( 'pipe', 'w' ),
			2 => array( 'pipe', 'w' ),
		);
		$process     = \proc_open( array( 'wp', 'plugin', 'uninstall', self::DIR ), $descriptors, $pipes, ABSPATH ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.system_calls_proc_open -- Runs WP-CLI's own command.
		self::assertIsResource( $process );
		$output = \stream_get_contents( $pipes[1] ) . \stream_get_contents( $pipes[2] );
		\fclose( $pipes[1] );
		\fclose( $pipes[2] );
		$exit = \proc_close( $process );
		wp_cache_flush();

		self::assertNotSame( 0, $exit, (string) $output );
		self::assertFileExists( WP_PLUGIN_DIR . '/' . self::DIR . '/acme-consumer-a.php' );
		self::assertSame( array( 'delete_data' => 'yes' ), get_option( 'acme_ca_settings' ) );
		self::assertSame( 'kept', get_post_meta( $this->product_id, '_acme_ca_packing_note', true ) );
	}

	#[After]
	protected function delete_seeded_rows(): void {
		if ( 0 !== $this->product_id ) {
			wp_delete_post( $this->product_id, true );
		}
		if ( '' !== $this->log && \is_file( $this->log ) ) {
			\unlink( $this->log ); // phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink -- Removes the request's log.
		}
	}

	protected function seed_footprint(): void {
		update_option( 'acme_ca_settings', array( 'delete_data' => 'yes' ) );
		update_option( 'acme_ca_revision', 1 );
		update_option( 'acme_ca_notices', array( 'seeded' ) );
		update_network_option( null, 'acme_ca_network_notices', array( 'seeded' ) );
		set_transient( 'acme_ca_boot_failure_seeded', 1, HOUR_IN_SECONDS );
		$this->user_id = $this->make_user( 'acme_ca_admin', 'administrator' );
		update_user_option( $this->user_id, 'acme_ca_hidden_notices', array( 'seeded' ) );
		update_user_meta( $this->user_id, 'acme_ca_network_hidden_notices', array( 'seeded' ) );

		$product = wc_get_product_object( 'simple' );
		$product->set_name( 'Acme product' );
		$product->update_meta_data( '_acme_ca_packing_note', 'kept' );
		$this->product_id = $product->save();
	}

	protected function uninstall_on_the_web( string $plugin, array $missing ): array {
		$this->log = (string) \tempnam( \sys_get_temp_dir(), 'acme-ca-log-' );

		return $this->consumer_request(
			'GET',
			'index.php',
			$this->make_user( 'acme_ca_admin', 'administrator' ),
			array(
				'uninstall' => $plugin,
				'missing'   => $missing,
				'log'       => $this->log,
			)
		);
	}
}
