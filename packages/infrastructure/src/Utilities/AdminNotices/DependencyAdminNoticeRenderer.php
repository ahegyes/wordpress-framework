<?php declare( strict_types=1 );

namespace DeepWebSolutions\Framework\Utilities\AdminNotices;

use DeepWebSolutions\Framework\Utilities\AdminNotices\ValueObjects\AdminNotice;
use DeepWebSolutions\Framework\Utilities\AdminNotices\ValueObjects\DependencyRequirement;

/**
 * Queues admin notices for unmet plugin dependencies. The consumer supplies a list of
 * DependencyRequirement descriptors; render() evaluates each and queues a notice for every unmet one
 * into the given AdminNoticesService store, for the service's own render pass to display. A required
 * dependency queues a non-dismissible error that recurs every request; an optional one queues a
 * persistent, per-user-dismissible warning. Hook render() ahead of the service's render pass (e.g. on
 * admin_init) so it sits above the kernel's pre-resolution gate, which would otherwise skip a Feature
 * whose required dependency is missing and leave nothing to report the absence.
 *
 * @since   2.0.0
 * @version 2.0.0
 */
final readonly class DependencyAdminNoticeRenderer {
	// region FIELDS AND CONSTANTS

	/**
	 * Capability a user must hold to see (and dismiss) the dependency notices.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @var     string
	 */
	public const DEFAULT_CAPABILITY = 'activate_plugins';

	// endregion

	// region MAGIC METHODS

	/**
	 * Constructor.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   AdminNoticesService         $service      Service the dependency notices are queued into.
	 * @param   list<DependencyRequirement> $requirements Dependencies evaluated on each render.
	 * @param   string|null                 $source       Plugin or feature display name woven into the notice text; null uses a generic subject.
	 * @param   string                      $store        Name of the service store to queue into. Defaults to AdminNoticesService::DEFAULT_STORE.
	 * @param   string                      $capability   Capability required to see the notices. Defaults to DEFAULT_CAPABILITY.
	 */
	public function __construct(
		protected AdminNoticesService $service,
		protected array $requirements,
		protected ?string $source = null,
		protected string $store = AdminNoticesService::DEFAULT_STORE,
		protected string $capability = self::DEFAULT_CAPABILITY,
	) {}

	// endregion

	// region METHODS

	/**
	 * Evaluate every declared dependency: queue a notice for each unmet one and clear any previously
	 * queued notice for a now-met one, so the store reflects current state. Hook ahead of the
	 * AdminNoticesService render pass (e.g. on admin_init). A conditional that throws is treated as
	 * unmet, so a broken check surfaces the dependency rather than hiding it and never aborts the pass.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 */
	public function render(): void {
		foreach ( $this->requirements as $requirement ) {
			if ( $this->is_met( $requirement ) ) {
				// Clear any notice queued on a previous render, so a now-met dependency leaves no stale
				// warning behind in a persistent store (a no-op for the per-request memory store).
				$this->service->remove_notice( $requirement->get_notice_id(), $this->store );
				continue;
			}

			$this->service->add_notice( $this->build_notice( $requirement ), $this->store );
		}
	}

	// endregion

	// region HELPERS

	/**
	 * Whether a requirement's conditional is satisfied. A conditional that throws is treated as unmet.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   DependencyRequirement $requirement Requirement to evaluate.
	 *
	 * @return  bool
	 */
	protected function is_met( DependencyRequirement $requirement ): bool {
		try {
			return $requirement->conditional->is_met();
		} catch ( \Throwable ) {
			return false;
		}
	}

	/**
	 * Build the admin notice for an unmet requirement, taking its identity and presentation from the
	 * requirement and its message from {@see self::build_message()}.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   DependencyRequirement $requirement Unmet requirement to render.
	 *
	 * @return  AdminNotice
	 */
	protected function build_notice( DependencyRequirement $requirement ): AdminNotice {
		return new AdminNotice(
			id: $requirement->get_notice_id(),
			message: $this->build_message( $requirement ),
			type: $requirement->get_notice_type(),
			is_dismissible: $requirement->is_dismissible(),
			is_persistent: $requirement->is_persistent(),
			capability: $this->capability,
		);
	}

	/**
	 * Build the user-facing message for an unmet requirement.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   DependencyRequirement $requirement Unmet requirement.
	 *
	 * @return  string
	 */
	protected function build_message( DependencyRequirement $requirement ): string {
		if ( $requirement->required ) {
			$subject = $this->source ?? \__( 'This plugin', 'wp-framework-infrastructure' );
			/* translators: 1: plugin or feature name, 2: required dependency label. */
			return \sprintf( \__( '%1$s requires %2$s to be active.', 'wp-framework-infrastructure' ), $subject, $requirement->label );
		}

		if ( null !== $this->source ) {
			/* translators: 1: optional dependency label, 2: plugin or feature name. */
			return \sprintf( \__( '%1$s is recommended for %2$s.', 'wp-framework-infrastructure' ), $requirement->label, $this->source );
		}

		/* translators: %s: optional dependency label. */
		return \sprintf( \__( '%s is recommended.', 'wp-framework-infrastructure' ), $requirement->label );
	}

	// endregion
}
