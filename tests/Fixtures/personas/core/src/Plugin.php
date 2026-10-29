<?php declare( strict_types=1 );

namespace Acme\PersonaCore;

use Acme\PersonaCore\Scoped\DeepWebSolutions\Framework\Core\AdminNotice\AdminNotices;
use Acme\PersonaCore\Scoped\DeepWebSolutions\Framework\Core\Logging\ErrorLogLogger;
use Acme\PersonaCore\Scoped\DeepWebSolutions\Framework\Core\Migration\MigrationRunner;
use Acme\PersonaCore\Scoped\DeepWebSolutions\Framework\Core\PluginBoot;
use Acme\PersonaCore\Scoped\DeepWebSolutions\Framework\Core\PluginKernel;
use Acme\PersonaCore\Scoped\DeepWebSolutions\Framework\Core\Scheduling\Scheduler;
use Acme\PersonaCore\Scoped\DI\ContainerBuilder;
use Acme\PersonaCore\Scoped\Psr\Log\LoggerInterface as PsrLoggerInterface;

\defined( 'ABSPATH' ) || exit;

/**
 * Composes the plugin from its logger, scheduler, migrations and components, inside the framework's boot boundary.
 *
 * @since   1.0.0
 * @version 1.0.0
 */
final class Plugin {
	// region FIELDS AND CONSTANTS

	/**
	 * The plugin's slug, which is also its text domain and its log channel.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @var     string
	 */
	public const string SLUG = 'acme-persona-core';

	/**
	 * The prefix of the plugin's hook names, options and scheduled hooks.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @var     string
	 */
	public const string PREFIX = 'acme_pc';

	/**
	 * The top-level components, in hook-registration order.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @var     list<class-string>
	 */
	public const array COMPONENTS = array(
		AdminNotices::class,
		Greeting::class,
	);

	/**
	 * The boot boundary, whose status tells whether the plugin booted.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @var     PluginBoot
	 */
	public readonly PluginBoot $boot;

	/**
	 * The kernel, once the composition built it, which tells which components loaded.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @var     PluginKernel|null
	 */
	protected(set) ?PluginKernel $kernel = null;

	/**
	 * The migration runner, once the composition built it.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @var     MigrationRunner|null
	 */
	protected(set) ?MigrationRunner $migrations = null;

	/**
	 * The logger every part of the plugin writes to.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @var     PsrLoggerInterface
	 */
	protected readonly PsrLoggerInterface $logger;

	/**
	 * The scheduler of the plugin's background work, which the migration runner continues through.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @var     Scheduler
	 */
	protected readonly Scheduler $scheduler;

	// endregion

	// region MAGIC METHODS

	/**
	 * Constructor.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 */
	public function __construct() {
		$this->logger    = new ErrorLogLogger( self::SLUG );
		$this->boot      = new PluginBoot( ACME_PC_FILE, self::PREFIX, $this->logger );
		$this->scheduler = new Scheduler( self::PREFIX );
	}

	// endregion

	// region METHODS

	/**
	 * Runs the composition inside the framework's boot boundary.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @return  void
	 */
	public function boot(): void {
		$this->boot->run( $this->compose( ... ) );
	}

	// endregion

	// region HELPERS

	/**
	 * Registers the scheduler and the migrations, then builds the kernel and boots the plugin's components.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @return  bool
	 */
	protected function compose(): bool {
		$this->scheduler->register_hooks();
		$this->migrations = new MigrationRunner( ACME_PC_FILE, self::PREFIX . '_revision', acme_pc_get_migration_steps(), $this->logger, $this->scheduler );
		$this->migrations->register_hooks();

		$this->kernel = new PluginKernel( new ContainerBuilder()->addDefinitions( $this->get_definitions( $this->logger ) )->build() );
		$this->kernel->boot( self::COMPONENTS );

		return true;
	}

	/**
	 * Returns the container's definitions of the services the root builds.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @param   PsrLoggerInterface $logger The plugin's logger.
	 *
	 * @return  array<string, \Closure(): object>
	 */
	protected function get_definitions( PsrLoggerInterface $logger ): array {
		return array(
			AdminNotices::class => static fn (): AdminNotices => new AdminNotices( self::PREFIX, $logger ),
		);
	}

	// endregion
}
