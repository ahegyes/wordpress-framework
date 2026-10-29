<?php declare( strict_types=1 );

namespace Acme\ConsumerA;

use Acme\ConsumerA\Scoped\DeepWebSolutions\Framework\Core\AdminNotice\AdminNotices;
use Acme\ConsumerA\Scoped\DeepWebSolutions\Framework\Core\Migration\MigrationRunner;
use Acme\ConsumerA\Scoped\DeepWebSolutions\Framework\Core\PluginBoot;
use Acme\ConsumerA\Scoped\DeepWebSolutions\Framework\Core\PluginKernel;
use Acme\ConsumerA\Scoped\DeepWebSolutions\Framework\Core\Scheduling\Scheduler;
use Acme\ConsumerA\Scoped\DeepWebSolutions\Framework\Core\Scheduling\SchedulerInterface;
use Acme\ConsumerA\Scoped\DeepWebSolutions\Framework\WooCommerce\Logging\WooCommerceLogger;
use Acme\ConsumerA\Scoped\DeepWebSolutions\Framework\WooCommerce\WooCommerceRequirement;
use Acme\ConsumerA\Scoped\DI\ContainerBuilder;
use Acme\ConsumerA\Scoped\Psr\Log\LoggerInterface as PsrLoggerInterface;

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
	 * The plugin's slug, which is also its text domain and its log source.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @var     string
	 */
	public const string SLUG = 'acme-consumer-a';

	/**
	 * The prefix of the plugin's hook names, options, transients and scheduled jobs.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @var     string
	 */
	public const string PREFIX = 'acme_ca';

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
		ProductData::class,
		Exports::class,
		Reports\Reports::class,
		FaultInjector::class,
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
	 * The scheduler, which registers its WP-Cron schedules even while the plugin is blocked.
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
	 * The kernel, once the composition built it, which tells which components loaded and which were gated off.
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

	// endregion

	// region MAGIC METHODS

	/**
	 * Constructor.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 */
	public function __construct() {
		$this->logger    = new WooCommerceLogger( self::SLUG, \defined( 'ACME_CA_DEBUG' ) && true === \constant( 'ACME_CA_DEBUG' ) ? 'debug' : null );
		$this->scheduler = new Scheduler( self::PREFIX );
		$this->boot      = new PluginBoot( ACME_CA_FILE, self::PREFIX, $this->logger );
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
	 * Builds the plugin and boots its components, returning false when WooCommerce is missing or too old.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @return  bool
	 */
	protected function compose(): bool {
		$this->scheduler->register_hooks(); // Before the WooCommerce check, so the plugin's recurring WP-Cron events keep their schedules while it is blocked.
		if ( ! ( new WooCommerceRequirement( ACME_CA_FILE ) )->check() ) {
			return false;
		}

		$this->migrations = new MigrationRunner( ACME_CA_FILE, self::PREFIX . '_revision', acme_ca_get_migration_steps(), $this->logger, $this->scheduler );
		$this->migrations->register_hooks();

		$this->kernel = new PluginKernel( new ContainerBuilder()->addDefinitions( $this->get_definitions( $this->logger, $this->scheduler ) )->build() );
		$this->kernel->boot( self::COMPONENTS );

		return true;
	}

	/**
	 * Returns the container's definitions of the services the root builds, which the kernel's components share.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @param   PsrLoggerInterface $logger    The plugin's logger.
	 * @param   Scheduler          $scheduler The plugin's scheduler.
	 *
	 * @return  array<string, \Closure(): object>
	 */
	protected function get_definitions( PsrLoggerInterface $logger, Scheduler $scheduler ): array {
		return array(
			PsrLoggerInterface::class => static fn (): PsrLoggerInterface => $logger,
			Scheduler::class          => static fn (): Scheduler => $scheduler,
			SchedulerInterface::class => static fn (): Scheduler => $scheduler,
			AdminNotices::class       => static fn (): AdminNotices => new AdminNotices( self::PREFIX, $logger ),
		);
	}

	// endregion
}
