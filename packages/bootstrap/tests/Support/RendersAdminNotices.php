<?php declare( strict_types=1 );

namespace DeepWebSolutions\Framework\Bootstrap\Tests\Support;

use PHPUnit\Framework\Attributes\Before;

trait RendersAdminNotices {
	#[Before]
	protected function reset_admin_notices(): void {
		\remove_all_actions( 'all_admin_notices' );
	}

	protected function render_admin_notices(): string {
		\ob_start();
		\do_action( 'all_admin_notices' );
		return (string) \ob_get_clean();
	}
}
