<?php declare( strict_types=1 );

namespace DeepWebSolutions\Framework\Tests\Integration\Support;

use PHPUnit\Framework\Attributes\After;
use PHPUnit\Framework\Attributes\Before;

trait IsolatesHooksTrait {
	protected array $saved_hook_globals = array();

	#[Before]
	protected function back_up_hooks(): void {
		$this->saved_hook_globals = array(
			'wp_filter'         => \array_map( static fn ( \WP_Hook $hook ): \WP_Hook => clone $hook, $GLOBALS['wp_filter'] ),
			'wp_actions'        => $GLOBALS['wp_actions'],
			'wp_filters'        => $GLOBALS['wp_filters'],
			'wp_current_filter' => $GLOBALS['wp_current_filter'],
		);
	}

	#[After]
	protected function restore_hooks(): void {
		foreach ( $this->saved_hook_globals as $name => $value ) {
			$GLOBALS[ $name ] = $value;
		}
	}
}
