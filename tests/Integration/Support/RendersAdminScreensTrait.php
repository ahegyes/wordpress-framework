<?php declare( strict_types=1 );

namespace DeepWebSolutions\Framework\Tests\Integration\Support;

use PHPUnit\Framework\Attributes\After;

trait RendersAdminScreensTrait {
	protected function view_as( int $user_id, string $screen_id = 'dashboard', bool $block_editor = false ): void {
		require_once ABSPATH . 'wp-admin/includes/class-wp-screen.php';
		require_once ABSPATH . 'wp-admin/includes/screen.php';

		wp_set_current_user( $user_id );
		set_current_screen( $screen_id );
		get_current_screen()?->is_block_editor( $block_editor );
	}

	protected static function notices_in( string $html ): array {
		$notices = array();
		$tags    = new \WP_HTML_Tag_Processor( $html );
		while ( $tags->next_tag( array( 'class_name' => 'notice' ) ) ) {
			$attributes = array();
			foreach ( $tags->get_attribute_names_with_prefix( '' ) ?? array() as $name ) {
				$attributes[ $name ] = $tags->get_attribute( $name );
			}
			$notices[ (string) $tags->get_attribute( 'id' ) ] = $attributes;
		}

		return $notices;
	}

	#[After]
	protected function leave_admin_screen(): void {
		$GLOBALS['current_screen'] = null;
		wp_set_current_user( 0 );
	}
}
