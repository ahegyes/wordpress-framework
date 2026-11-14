<?php declare( strict_types=1 );

namespace Acme\PersonaCore;

use Acme\PersonaCore\Scoped\DeepWebSolutions\Framework\Core\AdminNotice\AdminNotices;
use Acme\PersonaCore\Scoped\DeepWebSolutions\Framework\Core\Logging\ErrorLogLogger;
use Acme\PersonaCore\Scoped\DeepWebSolutions\Framework\Core\Migration\MigrationRunner;
use Acme\PersonaCore\Scoped\DeepWebSolutions\Framework\Core\PluginBoot;
use Acme\PersonaCore\Scoped\DeepWebSolutions\Framework\Core\PluginIdentity;
use Acme\PersonaCore\Scoped\DeepWebSolutions\Framework\Core\PluginKernel;
use Acme\PersonaCore\Scoped\DeepWebSolutions\Framework\Core\Scheduling\Scheduler;
use Acme\PersonaCore\Scoped\DeepWebSolutions\Framework\Core\Scheduling\SchedulerInterface;
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
	 * The plugin's slug, which is also its text domain, its log channel and, in snake case, the root of its hook names.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @var     string
	 */
	public const string SLUG = 'acme-persona-core';

	/**
	 * The prefix of the plugin's options and scheduled actions.
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
		Scheduler::class,
		AdminNotices::class,
		Greeting::class,
	);

	/**
	 * The plugin's stored data by kind, which covers every option, user meta key and scheduled job under the plugin's prefix.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @var     array{option_prefixes: list<non-empty-string>, user_meta_site_prefixes: list<non-empty-string>, user_meta_global_prefixes: list<non-empty-string>, network_options: list<non-empty-string>, cron_hooks: list<non-empty-string>, as_groups: list<non-empty-string>}
	 */
	public const array FOOTPRINT = array(
		'option_prefixes'           => array( self::PREFIX . '_' ),
		'user_meta_site_prefixes'   => array( self::PREFIX . '_' ),
		'user_meta_global_prefixes' => array( self::PREFIX . '_' ),
		'network_options'           => array( 'acme_pc_network_notices' ),
		'cron_hooks'                => array( 'acme_persona_core/migration/run' ),
		'as_groups'                 => array( self::PREFIX ),
	);

	/**
	 * The boot boundary, whose status tells whether the plugin booted.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @var     PluginBoot
	 */
	public readonly PluginBoot $boundary;

	/**
	 * The scheduler of the plugin's background work, which the migration runner continues through.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @var     Scheduler
	 */
	public readonly Scheduler $scheduler;

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
	 * The kernel, once the composition built it, which tells which components loaded.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @var     PluginKernel|null
	 */
	protected(set) ?PluginKernel $kernel = null;

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
	 * The plugin's identity, which every framework class the root builds takes.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @var     PluginIdentity
	 */
	protected readonly PluginIdentity $identity;

	// endregion

	// region MAGIC METHODS

	/**
	 * Constructor.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 */
	public function __construct() {
		$this->identity  = new PluginIdentity( ACME_PC_FILE, self::SLUG, self::PREFIX );
		$this->logger    = new ErrorLogLogger( $this->identity );
		$this->scheduler = new Scheduler( $this->identity );
		$this->boundary  = new PluginBoot( $this->identity, $this->logger );
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
		$this->boundary->run( $this->compose( ... ) );
	}

	// endregion

	// region HELPERS

	/**
	 * Registers the migrations, then builds the kernel and boots the plugin's components, the scheduler first.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @return  bool
	 */
	protected function compose(): bool {
		$this->migrations = new MigrationRunner( $this->identity, Migrations::get_steps(), $this->logger, $this->scheduler );
		$this->migrations->register_hooks();

		$this->kernel = new PluginKernel( new ContainerBuilder()->addDefinitions( $this->get_definitions( $this->identity, $this->logger, $this->scheduler ) )->build() );
		$this->kernel->boot( self::COMPONENTS );

		return true;
	}

	/**
	 * Returns the container's definitions of the services the root builds, which the kernel's components share.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @param   PluginIdentity     $identity  The plugin's identity.
	 * @param   PsrLoggerInterface $logger    The plugin's logger.
	 * @param   Scheduler          $scheduler The plugin's scheduler.
	 *
	 * @return  array<string, \Closure(): object>
	 */
	protected function get_definitions( PluginIdentity $identity, PsrLoggerInterface $logger, Scheduler $scheduler ): array {
		return array(
			PluginIdentity::class     => static fn (): PluginIdentity => $identity,
			PsrLoggerInterface::class => static fn (): PsrLoggerInterface => $logger,
			Scheduler::class          => static fn (): Scheduler => $scheduler,
			SchedulerInterface::class => static fn (): Scheduler => $scheduler,
			AdminNotices::class       => static fn (): AdminNotices => new AdminNotices( $identity, $logger ),
		);
	}

	// endregion
}
