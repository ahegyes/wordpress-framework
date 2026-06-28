<?php declare( strict_types=1 );

namespace DeepWebSolutions\Framework\Utilities\Tests\Integration\AdminNotices;

use DeepWebSolutions\Framework\Core\Installer\Exceptions\InstallationException;
use DeepWebSolutions\Framework\Core\Installer\Exceptions\UpdateException;
use DeepWebSolutions\Framework\Core\Installer\InstallerInterface;
use DeepWebSolutions\Framework\Core\PluginInterface;
use DeepWebSolutions\Framework\Core\PluginKernel;
use DeepWebSolutions\Framework\Core\ValueObjects\PluginHeader;
use DeepWebSolutions\Framework\Core\Feature\FeatureInterface;
use DeepWebSolutions\Framework\Shared\Version\Version;
use DeepWebSolutions\Framework\Storage\OptionsStore;
use DeepWebSolutions\Framework\Utilities\AdminNotices\AdminNoticeLogger;
use DeepWebSolutions\Framework\Utilities\AdminNotices\AdminNoticesService;
use DeepWebSolutions\Framework\Utilities\AdminNotices\NoticeStore;
use DeepWebSolutions\Framework\Utilities\AdminNotices\NoticeType;
use DeepWebSolutions\Framework\Utilities\AdminNotices\ValueObjects\AdminNotice;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;

/**
 * Exercises the on-ramp recipe end-to-end: a consumer installer that records {slug}_version through a
 * wp_options store, an {@see AdminNoticeLogger} wired as the kernel's optional logger, and the kernel
 * surfacing a failed migration as a persistent admin notice instead of fataling every request.
 */
#[CoversClass( AdminNoticeLogger::class )]
#[UsesClass( AdminNoticesService::class )]
#[UsesClass( NoticeStore::class )]
#[UsesClass( AdminNotice::class )]
#[UsesClass( NoticeType::class )]
#[UsesClass( OptionsStore::class )]
final class AdminNoticeLoggerTest extends TestCase {
	private const VERSION_OPTION = 'dws_reference_plugin_version';
	private const NOTICE_OPTION  = 'dws_reference_plugin_notices';
	private const NOTICE_ID      = 'plugin-install-failure';

	private int $admin;
	private int $original_user;

	protected function setUp(): void {
		parent::setUp();
		$this->original_user = \get_current_user_id();
		$this->admin         = $this->make_admin();
		\wp_set_current_user( $this->admin );
	}

	protected function tearDown(): void {
		\wp_set_current_user( $this->original_user );
		\delete_option( self::VERSION_OPTION );
		\delete_option( self::NOTICE_OPTION );
		$this->delete_user( $this->admin );
		parent::tearDown();
	}

	public function test_a_failed_install_surfaces_a_persistent_admin_notice(): void {
		$notices   = $this->notice_service();
		$installer = new ReferenceInstaller( new OptionsStore( self::VERSION_OPTION ), $notices, self::NOTICE_ID, Version::from_string( '2.0.0' ), fail: true );

		PluginKernel::run( $this->make_plugin( $installer ), new AdminNoticeLogger( $notices, self::NOTICE_ID, 'options' ) );

		// The failed migration left the version unrecorded, so the next boot retries it.
		self::assertNull( $installer->get_stored_version() );

		// The notice persisted to wp_options, so a fresh service — a later request — still reads it.
		$notice = $this->notice_service()->stores['options']->get( self::NOTICE_ID );
		self::assertNotNull( $notice );
		self::assertTrue( $notice->is_persistent );
		self::assertSame( NoticeType::Error, $notice->type );

		// And it renders for a capable admin.
		self::assertStringContainsString( $notice->message, $this->capture_render( $this->notice_service() ) );
	}

	public function test_a_successful_install_records_the_version_and_surfaces_no_notice(): void {
		$notices   = $this->notice_service();
		$installer = new ReferenceInstaller( new OptionsStore( self::VERSION_OPTION ), $notices, self::NOTICE_ID, Version::from_string( '2.0.0' ), fail: false );

		PluginKernel::run( $this->make_plugin( $installer ), new AdminNoticeLogger( $notices, self::NOTICE_ID, 'options' ) );

		self::assertSame( '2.0.0', $installer->get_stored_version()?->value );
		self::assertSame( array(), $this->notice_service()->stores['options']->get_all() );
	}

	public function test_a_recovered_install_clears_the_prior_failure_notice(): void {
		$versions = new OptionsStore( self::VERSION_OPTION );

		// A first boot fails and surfaces the persistent notice.
		$failing = $this->notice_service();
		PluginKernel::run(
			$this->make_plugin( new ReferenceInstaller( $versions, $failing, self::NOTICE_ID, Version::from_string( '2.0.0' ), fail: true ) ),
			new AdminNoticeLogger( $failing, self::NOTICE_ID, 'options' ),
		);
		self::assertTrue( $this->notice_service()->stores['options']->has( self::NOTICE_ID ) );

		// The next boot recovers: the install succeeds, records the version, and clears the notice.
		$recovered = new ReferenceInstaller( $versions, $this->notice_service(), self::NOTICE_ID, Version::from_string( '2.0.0' ), fail: false );
		PluginKernel::run( $this->make_plugin( $recovered ), new AdminNoticeLogger( $this->notice_service(), self::NOTICE_ID, 'options' ) );

		self::assertSame( '2.0.0', $recovered->get_stored_version()?->value );
		self::assertFalse( $this->notice_service()->stores['options']->has( self::NOTICE_ID ) );
	}

	public function test_a_failed_update_surfaces_a_persistent_admin_notice(): void {
		$versions = new OptionsStore( self::VERSION_OPTION );
		$versions->set( 'version', '1.0.0' );

		$notices   = $this->notice_service();
		$installer = new ReferenceInstaller( $versions, $notices, self::NOTICE_ID, Version::from_string( '2.0.0' ), fail: true );

		PluginKernel::run( $this->make_plugin( $installer ), new AdminNoticeLogger( $notices, self::NOTICE_ID, 'options' ) );

		// The failed update left the prior version in place for the next boot to retry.
		self::assertSame( '1.0.0', $installer->get_stored_version()?->value );
		self::assertTrue( $this->notice_service()->stores['options']->has( self::NOTICE_ID ) );
	}

	private function notice_service(): AdminNoticesService {
		return new AdminNoticesService( array( 'options' => new NoticeStore( new OptionsStore( self::NOTICE_OPTION ) ) ) );
	}

	private function make_plugin( InstallerInterface $installer ): PluginInterface {
		return new class( $installer, $this->empty_container() ) implements PluginInterface {
			public function __construct(
				private InstallerInterface $installer,
				private ContainerInterface $container,
			) {}

			public function get_plugin_file(): string {
				return '/tmp/reference-plugin.php';
			}

			public function get_plugin_header(): PluginHeader {
				throw new \RuntimeException( 'boot must not read the header' );
			}

			public function get_container(): ContainerInterface {
				return $this->container;
			}

			/**
			 * @return list<class-string<FeatureInterface>>
			 */
			public function get_feature_classes(): array {
				return array();
			}

			public function get_installer(): InstallerInterface {
				return $this->installer;
			}
		};
	}

	private function empty_container(): ContainerInterface {
		return new class() implements ContainerInterface {
			public function get( string $id ): mixed {
				throw new \OutOfBoundsException( 'no services bound: ' . $id );
			}

			public function has( string $id ): bool {
				return false;
			}
		};
	}

	private function capture_render( AdminNoticesService $service ): string {
		\ob_start();
		$service->render_notices();
		return (string) \ob_get_clean();
	}

	private function make_admin(): int {
		$existing = \get_user_by( 'login', 'dws_reference_admin' );
		if ( $existing instanceof \WP_User ) {
			return $existing->ID;
		}

		$id = \wp_insert_user(
			array(
				'user_login' => 'dws_reference_admin',
				'user_pass'  => 'password',
				'role'       => 'administrator',
			),
		);
		self::assertIsInt( $id );
		return $id;
	}

	private function delete_user( int $id ): void {
		if ( ! \function_exists( 'wp_delete_user' ) ) {
			require_once \ABSPATH . 'wp-admin/includes/user.php';
		}
		\wp_delete_user( $id );
	}
}

/**
 * Reference installer for the on-ramp: it records the plugin version through a wp_options store, throws
 * to exercise the kernel's fail-closed boot, and clears the failure notice once a run succeeds so a
 * recovered site stops showing it. The cleared notice id must match the one its paired AdminNoticeLogger
 * queues under, or a stale notice survives recovery. A real consumer reads its current version from the
 * main-file header and does install/update work — granting capabilities, seeding options — in
 * install()/update().
 */
final class ReferenceInstaller implements InstallerInterface {
	/**
	 * @param OptionsStore<string> $versions  Store the recorded version lives in.
	 * @param AdminNoticesService  $notices   Service the failure notice is cleared through on a successful run.
	 * @param string               $notice_id Id of the failure notice this installer owns.
	 * @param Version              $current   Version install()/update() bring the site to.
	 * @param bool                 $fail      Whether the migration step throws, to exercise the failure path.
	 */
	public function __construct(
		protected OptionsStore $versions,
		protected AdminNoticesService $notices,
		protected string $notice_id,
		protected Version $current,
		protected bool $fail = false,
	) {}

	public function install(): void {
		if ( $this->fail ) {
			throw new InstallationException( 'install failed' );
		}
		$this->clear_failure_notice();
	}

	public function update( Version $from_version ): void {
		if ( $this->fail ) {
			throw new UpdateException( 'update failed' );
		}
		$this->clear_failure_notice();
	}

	public function get_current_version(): Version {
		return $this->current;
	}

	public function get_stored_version(): ?Version {
		$stored = $this->versions->get( 'version' );
		return \is_string( $stored ) ? Version::from_string( $stored ) : null;
	}

	public function set_stored_version( Version $version ): void {
		$this->versions->set( 'version', $version->value );
	}

	public function activate( bool $network_wide = false ): void {}

	public function deactivate( bool $network_deactivating = false ): void {}

	public function uninstall(): void {}

	protected function clear_failure_notice(): void {
		$this->notices->remove_notice( $this->notice_id );
	}
}
