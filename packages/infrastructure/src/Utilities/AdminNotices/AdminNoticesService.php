<?php declare( strict_types=1 );

namespace DeepWebSolutions\Framework\Utilities\AdminNotices;

use DeepWebSolutions\Framework\Utilities\AdminNotices\Exceptions\UnknownNoticeStoreException;
use DeepWebSolutions\Framework\Utilities\AdminNotices\ValueObjects\AdminNotice;
use DeepWebSolutions\Framework\Utilities\Exceptions\InvalidGlobalNamePrefixException;
use DeepWebSolutions\Framework\Storage\MemoryStore;

use function DeepWebSolutions\Framework\Shared\Identifier\is_valid_global_name_prefix;

/**
 * Collects admin notices across one or more named stores (in-memory, wp_options, user_meta) and
 * renders them when {@see self::render_notices()} is invoked. Each notice is gated by the current
 * user's capability and, when persistent, by a per-user dismissal record; a non-persistent notice is
 * consumed (removed from its store) after it renders once. Renders use core's wp_admin_notice().
 * When a dismiss action and a tracker are configured, it also wires the per-user AJAX dismissal
 * transport via {@see self::print_dismiss_script()} and {@see self::handle_dismiss()}. A consumer
 * calls {@see self::register_hooks()} once during boot to wire every callback to WordPress.
 *
 * @since   2.0.0
 * @version 2.0.0
 */
final readonly class AdminNoticesService {
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

	// endregion

	// region MAGIC METHODS

	/**
	 * Constructor.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   array<string, NoticeStore>   $stores         Stores to register, indexed by name; an empty array registers none. Defaults to a single in-memory store under DEFAULT_STORE.
	 * @param   DismissedNoticesTracker|null $dismissals     Per-user dismissal record. When null, persistent notices are never suppressed.
	 * @param   string|null                  $dismiss_action Plugin-unique AJAX action backing per-user dismissal. With a tracker, wires the dismiss transport; null wires none.
	 *
	 * @throws  InvalidGlobalNamePrefixException If $dismiss_action does not match the WordPress-global name charset.
	 */
	public function __construct(
		public array $stores = array( self::DEFAULT_STORE => new NoticeStore( new MemoryStore() ) ),
		protected ?DismissedNoticesTracker $dismissals = null,
		public ?string $dismiss_action = null,
	) {
		if ( null !== $dismiss_action && ! is_valid_global_name_prefix( $dismiss_action ) ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- framework-internal exception; never reaches an HTML output context unescaped.
			throw new InvalidGlobalNamePrefixException( "Invalid dismiss action: '$dismiss_action'. Use an optionally-underscore-prefixed lowercase name (a-z, 0-9, _, -) so the wp_ajax_ hook name stays well-formed." );
		}
	}

	// endregion

	// region METHODS

	/**
	 * Wires the service's callbacks to WordPress: {@see self::render_notices()} on 'admin_notices',
	 * {@see self::print_dismiss_script()} on 'admin_footer', and — when a dismiss action is
	 * configured — {@see self::handle_dismiss()} on its wp_ajax_ endpoint. Call once during boot.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 */
	public function register_hooks(): void {
		\add_action( 'admin_notices', array( $this, 'render_notices' ) );
		\add_action( 'admin_footer', array( $this, 'print_dismiss_script' ) );
		if ( null !== $this->dismiss_action ) {
			\add_action( 'wp_ajax_' . $this->dismiss_action, array( $this, 'handle_dismiss' ) );
		}
	}

	/**
	 * Queue a notice in the named store. Replaces any notice already stored under the same ID there.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   AdminNotice $notice Notice to queue.
	 * @param   string      $store  Name of the store to queue it in. Defaults to DEFAULT_STORE.
	 *
	 * @throws  UnknownNoticeStoreException When no store is registered under $store.
	 */
	public function add_notice( AdminNotice $notice, string $store = self::DEFAULT_STORE ): void {
		if ( ! isset( $this->stores[ $store ] ) ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- framework-internal exception; never reaches an HTML output context unescaped.
			throw new UnknownNoticeStoreException( "No notice store is registered under name '$store'." );
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
	 * @throws  UnknownNoticeStoreException When no store is registered under an explicit $store.
	 *
	 * @return  bool True if a notice was removed from any store, false otherwise.
	 */
	public function remove_notice( string $id, ?string $store = null ): bool {
		if ( null !== $store ) {
			if ( ! isset( $this->stores[ $store ] ) ) {
				// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- framework-internal exception; never reaches an HTML output context unescaped.
				throw new UnknownNoticeStoreException( "No notice store is registered under name '$store'." );
			}

			return $this->stores[ $store ]->remove( $id );
		}

		$removed = false;
		foreach ( $this->stores as $notice_store ) {
			if ( $notice_store->remove( $id ) ) {
				$removed = true;
			}
		}
		return $removed;
	}

	// endregion

	// region HOOKS

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

				$suppressed = $notice->persistent && $notice->dismissible
					&& true === $this->dismissals?->is_dismissed( $notice->id );
				if ( ! $suppressed ) {
					$this->render_one( $notice );
				}

				if ( ! $notice->persistent ) {
					$store->remove( $notice->id );
				}
			}
		}
	}

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
	 * dismiss action and a tracker are both configured, the nonce checks out, and the posted notice ID
	 * belongs to a queued persistent dismissible notice the current user may see.
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
			if ( '' !== $id && $this->is_dismissible_notice_known_to_current_user( $id ) ) {
				$this->dismissals->dismiss( $id );
			}
		}

		\wp_die();
	}

	// endregion

	// region HELPERS

	/**
	 * Checks whether a notice ID belongs to a queued persistent dismissible notice visible to the current user.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   string $id Notice ID to check.
	 *
	 * @return  bool
	 */
	protected function is_dismissible_notice_known_to_current_user( string $id ): bool {
		foreach ( $this->stores as $store ) {
			$notice = $store->get( $id );
			if ( null !== $notice && $notice->persistent && $notice->dismissible && \current_user_can( $notice->capability ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Render a single notice through core's wp_admin_notice(), which sanitizes the generated markup.
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
			&& $notice->persistent && $notice->dismissible
		) {
			$data_attributes['data-dismiss-action'] = $this->dismiss_action;
		}

		\wp_admin_notice(
			$notice->message,
			array(
				'id'             => 'dws-notice-' . $notice->id,
				'type'           => $notice->type->value,
				'dismissible'    => $notice->dismissible,
				'paragraph_wrap' => true,
				'attributes'     => $data_attributes,
			),
		);
	}

	// endregion
}
