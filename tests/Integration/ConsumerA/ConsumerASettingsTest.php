<?php declare( strict_types=1 );

namespace DeepWebSolutions\Framework\Tests\Integration\ConsumerA;

use DeepWebSolutions\Framework\Tests\Integration\Support\CreatesUsersTrait;
use DeepWebSolutions\Framework\Tests\Integration\Support\InstallsConsumersTrait;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;

#[CoversNothing]
final class ConsumerASettingsTest extends TestCase {
	use CreatesUsersTrait;
	use InstallsConsumersTrait;

	// The fingerprint of the key set each seeding step stores, in the order Migrations::get_steps() lists the steps; a release that adds settings appends its step and its fingerprint.
	protected const array SEEDED_KEY_SETS = array( 'f146d09206a9ebd3' );

	public static function stored_reports_settings(): array {
		return array(
			'switched-on' => array( 'yes', true ),
			'never-saved' => array( null, false ),
		);
	}

	public function test_the_first_request_stores_each_default_in_an_autoloaded_row_of_its_own(): void {
		global $wpdb;

		$plugin = $this->install_consumer( 'consumer-a', 'acme-consumer-a' );
		$this->consumer_request( 'GET', 'index.php', $this->make_user( 'acme_ca_admin', 'administrator' ), array( 'activate' => $plugin ) );
		wp_cache_flush();

		$autoload = $wpdb->get_results( $wpdb->prepare( 'SELECT option_name, autoload FROM %i WHERE option_name IN ( %s, %s ) ORDER BY option_name', $wpdb->options, 'acme_ca_delete_data', 'acme_ca_reports' ), \ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Reads the rows' autoload flags.
		self::assertSame( 'no', get_option( 'acme_ca_delete_data' ) );
		self::assertSame( 'no', get_option( 'acme_ca_reports' ) );
		self::assertSame( array( 'acme_ca_delete_data', 'acme_ca_reports' ), \array_column( $autoload, 'option_name' ) );
		self::assertSame( array(), \array_diff( \array_column( $autoload, 'autoload' ), wp_autoload_values_to_autoload() ) );
		self::assertFalse( get_option( 'acme_ca_settings' ) );
	}

	public function test_seeding_leaves_a_stored_setting_as_it_is(): void {
		update_option( 'acme_ca_delete_data', 'yes' );
		$plugin = $this->install_consumer( 'consumer-a', 'acme-consumer-a' );
		$this->consumer_request( 'GET', 'index.php', $this->make_user( 'acme_ca_admin', 'administrator' ), array( 'activate' => $plugin ) );
		wp_cache_flush();

		self::assertSame( 'yes', get_option( 'acme_ca_delete_data' ) );
		self::assertSame( 'no', get_option( 'acme_ca_reports' ) );
	}

	#[DataProvider( 'stored_reports_settings' )]
	public function test_the_reports_accessor_reads_what_the_reports_gate_reads( ?string $stored, bool $enabled ): void {
		if ( ! \is_null( $stored ) ) {
			update_option( 'acme_ca_reports', $stored );
		}
		$plugin  = $this->install_consumer( 'consumer-a', 'acme-consumer-a' );
		$request = $this->consumer_request( 'GET', 'index.php', $this->make_user( 'acme_ca_admin', 'administrator' ), array( 'activate' => $plugin ) );

		self::assertSame( $enabled, $request['records']['reports_enabled'] );
		self::assertSame( $enabled, $request['records']['reports_page_class'] );
	}

	#[RunInSeparateProcess]
	public function test_each_seeding_step_records_the_key_set_it_stores(): void {
		require_once self::FIXTURES . '/consumer-a/vendor/autoload.php';

		$seeding = \array_filter( \call_user_func( array( 'Acme\\ConsumerA\\Migrations', 'get_steps' ) ), static fn ( mixed $step ): bool => $step instanceof \Closure && 'add_settings' === new \ReflectionFunction( $step )->getName() );
		$keys    = \array_keys( \constant( 'Acme\\ConsumerA\\Options::DEFAULTS' ) );
		\sort( $keys );
		$fingerprint = \hash( 'xxh3', \implode( "\n", $keys ) );

		self::assertCount( \count( self::SEEDED_KEY_SETS ), $seeding, 'Each seeding step needs its fingerprint in SEEDED_KEY_SETS.' );
		self::assertSame( \array_last( self::SEEDED_KEY_SETS ), $fingerprint, "Options::DEFAULTS' keys changed: append self::add_settings( ... ) to Migrations::get_steps() and '$fingerprint' to SEEDED_KEY_SETS." );
	}
}
