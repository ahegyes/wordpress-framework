<?php declare( strict_types=1 );

namespace DeepWebSolutions\Framework\Utilities\AdminNotices;

use DeepWebSolutions\Framework\Utilities\AdminNotices\ValueObjects\AdminNotice;
use DeepWebSolutions\Framework\Storage\MemoryStore;

/**
 * Collects admin notices across one or more named stores (in-memory, wp_options, user_meta) and
 * renders them when {@see self::render_notices()} is invoked. Each notice is gated by the current
 * user's capability and, when persistent, by a per-user dismissal record; a non-persistent notice is
 * consumed (removed from its store) after it renders once. Renders use wp_admin_notice() (WP 6.4+)
 * with a wp_kses_post()-wrapped echo fallback for older WordPress sites. When a dismiss action and a
 * tracker are configured, it also wires the per-user AJAX dismissal transport via
 * {@see self::print_dismiss_script()} and {@see self::handle_dismiss()}.
 *
 * @since   2.0.0
 * @version 2.0.0
 */
final class AdminNoticesService {
	// region FIELDS AND CONSTANTS

	/**
	 * Name of the in-memory store registered by default.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @var     string
	 */
	public const DEFAULT_STORE = 'memory';

	/**
	 * Registered notice stores, indexed by name.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @var     array<string, NoticeStore>
	 */
	protected(set) array $stores;

	// endregion

	// region MAGIC METHODS

	/**
	 * Constructor.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   array<string, NoticeStore>|null $stores         Stores to register, indexed by name. Null registers a single in-memory store under DEFAULT_STORE; an empty array registers none.
	 * @param   DismissedNoticesTracker|null    $dismissals     Per-user dismissal record. When null, persistent notices are never suppressed.
	 * @param   string|null                     $dismiss_action Plugin-unique AJAX action backing per-user dismissal. With a tracker, wires the dismiss transport; null wires none.
	 */
	public function __construct(
		?array $stores = null,
		protected ?DismissedNoticesTracker $dismissals = null,
		protected ?string $dismiss_action = null,
	) {
		$this->stores = $stores ?? array( self::DEFAULT_STORE => new NoticeStore( new MemoryStore() ) );
	}

	// endregion

	// region GETTERS

	/**
	 * Returns the plugin-unique AJAX action backing per-user dismissal, or null when no transport is wired.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @return  string|null
	 */
	public function get_dismiss_action(): ?string {
		return $this->dismiss_action;
	}

	// endregion

	// region METHODS

	/**
	 * Queue a notice in the named store. Replaces any notice already stored under the same ID there.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   AdminNotice $notice Notice to queue.
	 * @param   string      $store  Name of the store to queue it in. Defaults to DEFAULT_STORE.
	 */
	public function add_notice( AdminNotice $notice, string $store = self::DEFAULT_STORE ): void {
		if ( ! isset( $this->stores[ $store ] ) ) {
			\_doing_it_wrong(
				__METHOD__,
				\esc_html( \sprintf( 'Unknown notice store "%s"; the notice was not queued.', $store ) ),
				'2.0.0'
			);
			return;
		}

		$this->stores[ $store ]->add( $notice );
	}

	/**
	 * Remove a queued notice by ID, from the named store or — when none is given — from every store.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   string      $id    ID of the notice to remove.
	 * @param   string|null $store Store to remove it from, or null to search every store.
	 *
	 * @return  bool True if a notice was removed from any store, false otherwise.
	 */
	public function remove_notice( string $id, ?string $store = null ): bool {
		if ( null !== $store ) {
			return isset( $this->stores[ $store ] ) && $this->stores[ $store ]->remove( $id );
		}

		$removed = false;
		foreach ( $this->stores as $notice_store ) {
			if ( $notice_store->remove( $id ) ) {
				$removed = true;
			}
		}
		return $removed;
	}

	/**
	 * Render every queued notice the current user may see. Hook this onto the `admin_notices` action.
	 *
	 * A notice the current user lacks the capability for is skipped and left queued. A persistent,
	 * dismissible notice the user has dismissed is suppressed (left in its store). A non-persistent
	 * notice is removed from its store once rendered, so it shows at most once.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 */
	public function render_notices(): void {
		foreach ( $this->stores as $store ) {
			foreach ( $store->get_all() as $notice ) {
				if ( ! \current_user_can( $notice->capability ) ) {
					continue;
				}

				$suppressed = $notice->is_persistent && $notice->is_dismissible
					&& true === $this->dismissals?->is_dismissed( $notice->id );
				if ( ! $suppressed ) {
					$this->render_one( $notice );
				}

				if ( ! $notice->is_persistent ) {
					$store->remove( $notice->id );
				}
			}
		}
	}

	// endregion

	// region HOOKS

	/**
	 * Prints the inline script that turns a notice's dismiss button into a persisted, per-user dismissal.
	 * Hook onto `admin_footer`. No-op unless a dismiss action and a tracker are both configured. The
	 * delegated listener is scoped by `data-dismiss-action`, so one plugin's script dismisses only its own
	 * notices; the embedded action and nonce are emitted as JS literals via wp_json_encode().
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 */
	public function print_dismiss_script(): void {
		if ( null === $this->dismiss_action || null === $this->dismissals ) {
			return;
		}

		$script = \sprintf(
			'( function () {
	var action = %1$s, nonce = %2$s;
	document.addEventListener( "click", function ( event ) {
		var button = event.target.closest( ".notice-dismiss" );
		if ( ! button ) { return; }
		var notice = button.closest( "[data-dismiss-action]" );
		if ( ! notice || notice.getAttribute( "data-dismiss-action" ) !== action ) { return; }
		var id = notice.getAttribute( "data-notice-id" );
		if ( ! id ) { return; }
		fetch( ajaxurl, { method: "POST", credentials: "same-origin", body: new URLSearchParams( { action: action, id: id, _wpnonce: nonce } ) } );
	} );
} )();',
			(string) \wp_json_encode( $this->dismiss_action ),
			(string) \wp_json_encode( \wp_create_nonce( $this->dismiss_action ) )
		);

		\wp_print_inline_script_tag( $script );
	}

	/**
	 * Records the current user's dismissal of a notice, then terminates the AJAX request. Hook onto
	 * `wp_ajax_{action}` for the configured dismiss action. No-op (still calling wp_die()) unless a
	 * dismiss action and a tracker are both configured and the nonce checks out. Reads only the posted
	 * notice ID and writes to the per-user tracker; it never reads a notice store.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 */
	public function handle_dismiss(): void {
		if ( null !== $this->dismiss_action && null !== $this->dismissals
			&& \is_user_logged_in()
			&& false !== \check_ajax_referer( $this->dismiss_action, false, false )
		) {
			$posted = $_POST['id'] ?? '';
			$posted = \is_string( $posted ) ? \wp_unslash( $posted ) : '';
			// Accept the posted ID only when it is already sanitize_key-stable, so it matches the stored
			// notice ID exactly; reject (do not lossily normalize) anything else.
			$id = ( \is_string( $posted ) && \sanitize_key( $posted ) === $posted ) ? $posted : '';
			if ( '' !== $id ) {
				$this->dismissals->dismiss( $id );
			}
		}

		\wp_die();
	}

	// endregion

	// region HELPERS

	/**
	 * Render a single notice using wp_admin_notice() when available, falling back to a
	 * sanitized echo for older WordPress versions.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   AdminNotice $notice Notice to render.
	 */
	protected function render_one( AdminNotice $notice ): void {
		$data_attributes = array( 'data-notice-id' => $notice->id );
		// The transport marker only goes on notices a dismissal would actually suppress (persistent +
		// dismissible), so clicking a one-shot's dismiss never records a stale, never-consulted row.
		if ( null !== $this->dismiss_action && null !== $this->dismissals
			&& $notice->is_persistent && $notice->is_dismissible
		) {
			$data_attributes['data-dismiss-action'] = $this->dismiss_action;
		}

		$attributes = array(
			'id'             => 'dws-notice-' . $notice->id,
			'type'           => $notice->type->value,
			'dismissible'    => $notice->is_dismissible,
			'paragraph_wrap' => true,
			'attributes'     => $data_attributes,
		);

		if ( \function_exists( 'wp_admin_notice' ) ) {
			\wp_admin_notice( $notice->message, $attributes );
			return;
		}

		// Dead at the WP 7.0 floor: wp_admin_notice() and its data-attribute support ship in WP 6.4, so
		// the dismiss transport cannot run on the pre-6.4 path reached here.
		$classes = 'notice notice-' . $notice->type->value;
		if ( $notice->is_dismissible ) {
			$classes .= ' is-dismissible';
		}
		printf(
			'<div id="%1$s" class="%2$s"><p>%3$s</p></div>',
			\esc_attr( $attributes['id'] ),
			\esc_attr( $classes ),
			\wp_kses_post( $notice->message ),
		);
	}

	// endregion
}
