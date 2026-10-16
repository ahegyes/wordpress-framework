<?php declare( strict_types=1 );

namespace DeepWebSolutions\Framework\Tests\Integration\Bootstrap;

use DeepWebSolutions\Framework\Tests\Integration\Support\CreatesUsersTrait;
use DeepWebSolutions\Framework\Tests\Integration\Support\IsolatesHooksTrait;
use PHPUnit\Framework\Attributes\CoversFunction;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

use function DeepWebSolutions\Framework\Bootstrap\check_requirements;

#[CoversFunction( 'DeepWebSolutions\Framework\Bootstrap\check_requirements' )]
final class RequirementsTest extends TestCase {
	use CreatesUsersTrait;
	use IsolatesHooksTrait;

	protected string $slug;

	protected array $directories = array();

	#[\Override]
	public static function setUpBeforeClass(): void {
		require_once \dirname( __DIR__, 3 ) . '/packages/bootstrap/load.php';
	}

	#[\Override]
	protected function setUp(): void {
		$this->slug = 'acme-requirements-' . \bin2hex( \random_bytes( 3 ) );
		\remove_all_actions( 'all_admin_notices' );
	}

	#[\Override]
	protected function tearDown(): void {
		\wp_set_current_user( 0 );
		foreach ( $this->directories as $directory ) {
			\array_map( 'unlink', \glob( "$directory/*" ) );
			\rmdir( $directory );
		}
	}

	public static function unmet_headers(): array {
		return array(
			'php-header'       => array( array( 'Requires PHP' => '99' ) ),
			'wordpress-header' => array( array( 'Requires at least' => '99' ) ),
			'both-headers'     => array(
				array(
					'Requires PHP'      => '99',
					'Requires at least' => '99',
				),
			),
		);
	}

	public static function met_headers(): array {
		return array(
			'at-the-floor' => array(
				array(
					'Requires PHP'      => \PHP_MAJOR_VERSION . '.' . \PHP_MINOR_VERSION,
					'Requires at least' => \get_bloginfo( 'version' ),
				),
			),
			'no-headers'   => array( array() ),
		);
	}

	#[DataProvider( 'unmet_headers' )]
	public function test_a_plugin_below_a_header_is_refused_and_shows_core_message_only_to_users_who_can_activate_plugins( array $headers ): void {
		$plugin_file = $this->write_plugin( \WP_PLUGIN_DIR, $headers );

		self::assertFalse( check_requirements( $plugin_file ) );

		$error = \validate_plugin_requirements( \plugin_basename( $plugin_file ) );
		self::assertInstanceOf( \WP_Error::class, $error );
		$notice = \wp_get_admin_notice(
			$error->get_error_message(),
			array(
				'type'           => 'error',
				'paragraph_wrap' => false,
			)
		);
		self::assertSame( \wp_kses_post( $notice ), $this->render_notices_as( 'administrator' ) );
		self::assertSame( '', $this->render_notices_as( 'editor' ) );
	}

	#[DataProvider( 'met_headers' )]
	public function test_a_plugin_meeting_its_headers_passes_and_prints_nothing( array $headers ): void {
		$plugin_file = $this->write_plugin( \WP_PLUGIN_DIR, $headers );

		self::assertTrue( check_requirements( $plugin_file ) );
		self::assertSame( '', $this->render_notices_as( 'administrator' ) );
	}

	public function test_core_reads_the_translated_headers_only_when_the_notice_renders(): void {
		$plugin_file  = $this->write_plugin( \WP_PLUGIN_DIR, array( 'Requires PHP' => '99' ) );
		$translations = 0;
		\add_filter(
			"gettext_$this->slug",
			static function ( string $translation ) use ( &$translations ): string {
				++$translations;

				return $translation;
			}
		);

		check_requirements( $plugin_file );
		self::assertSame( 0, $translations );

		$this->render_notices_as( 'administrator' );
		self::assertGreaterThan( 0, $translations );
	}

	public function test_a_refused_plugin_outside_the_plugins_directory_prints_nothing(): void {
		$plugin_file = $this->write_plugin( \untrailingslashit( \get_temp_dir() ), array( 'Requires PHP' => '99' ) );
		self::assertFalse( check_requirements( $plugin_file ) );

		// Core reads the headers from the plugins directory, so it warns about the missing file and reports the requirements as met.
		$warnings = array();
		\set_error_handler(
			static function ( int $level, string $message ) use ( &$warnings ): bool {
				$warnings[] = $message;

				return true;
			},
			\E_WARNING
		);
		try {
			$html = $this->render_notices_as( 'administrator' );
		} finally {
			\restore_error_handler();
		}

		self::assertSame( '', $html );
		self::assertCount( 1, $warnings );
		self::assertStringContainsString( 'Failed to open stream', $warnings[0] );
	}

	protected function write_plugin( string $root, array $headers ): string {
		$directory = "$root/$this->slug";
		\mkdir( $directory );
		$this->directories[] = $directory;

		$lines = array( 'Plugin Name: Acme Requirements', "Text Domain: $this->slug" );
		foreach ( $headers as $name => $value ) {
			$lines[] = "$name: $value";
		}

		$plugin_file = "$directory/$this->slug.php";
		\file_put_contents( $plugin_file, "<?php\n/**\n * " . \implode( "\n * ", $lines ) . "\n */\n" );

		return $plugin_file;
	}

	protected function render_notices_as( string $role ): string {
		\wp_set_current_user( $this->make_user( "acme_requirements_$role", $role ) );

		\ob_start();
		\do_action( 'all_admin_notices' );

		return (string) \ob_get_clean();
	}
}
