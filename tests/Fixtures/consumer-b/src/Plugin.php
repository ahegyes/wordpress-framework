<?php declare( strict_types=1 );

namespace Acme\ConsumerB;

use Acme\ConsumerB\Scoped\DeepWebSolutions\Framework\Core\AdminNotice\AdminNotices;
use Acme\ConsumerB\Scoped\DeepWebSolutions\Framework\Core\Logging\ErrorLogLogger;
use Acme\ConsumerB\Scoped\DeepWebSolutions\Framework\Core\PluginBoot;
use Acme\ConsumerB\Scoped\DeepWebSolutions\Framework\Core\PluginIdentity;
use Acme\ConsumerB\Scoped\DeepWebSolutions\Framework\Core\PluginKernel;
use Acme\ConsumerB\Scoped\DI\ContainerBuilder;
use Acme\ConsumerB\Scoped\Psr\Log\LoggerInterface as PsrLoggerInterface;

\defined( 'ABSPATH' ) || exit;

/**
 * Composes the plugin from its logger and components, inside the framework's boot boundary.
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
	public const string SLUG = 'acme-consumer-b';

	/**
	 * The prefix of the plugin's options and admin page.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @var     string
	 */
	public const string PREFIX = 'acme_cb';

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
		Settings::class,
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
		$this->identity = new PluginIdentity( ACME_CB_FILE, self::SLUG, self::PREFIX );
		$this->logger   = new ErrorLogLogger( $this->identity );
		$this->boundary = new PluginBoot( $this->identity, $this->logger );
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
	 * Builds the kernel and boots the plugin's components.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @return  bool
	 */
	protected function compose(): bool {
		$this->kernel = new PluginKernel( new ContainerBuilder()->addDefinitions( $this->get_definitions( $this->identity, $this->logger ) )->build() );
		$this->kernel->boot( self::COMPONENTS );

		return true;
	}

	/**
	 * Returns the container's definitions of the services the root builds.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @param   PluginIdentity     $identity The plugin's identity.
	 * @param   PsrLoggerInterface $logger   The plugin's logger.
	 *
	 * @return  array<string, \Closure(): object>
	 */
	protected function get_definitions( PluginIdentity $identity, PsrLoggerInterface $logger ): array {
		return array(
			PluginIdentity::class => static fn (): PluginIdentity => $identity,
			AdminNotices::class   => static fn (): AdminNotices => new AdminNotices( $identity, $logger ),
		);
	}

	// endregion
}
