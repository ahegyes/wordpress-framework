<?php declare( strict_types=1 );

namespace DeepWebSolutions\Framework\Settings\Tests\Support;

use PHPUnit\Framework\Attributes\After;
use PHPUnit\Framework\Attributes\Before;

/**
 * Snapshots the using class's ISOLATED_HOOKS tags out of the live $wp_filter table before each
 * test and restores the saved entries afterwards, so hooks the test registers cannot leak into
 * other tests and pre-registered core handlers cannot fire inside the test.
 */
trait IsolatesHooks {
	/**
	 * @var array<string, mixed>
	 */
	private array $saved_hooks = array();

	#[Before]
	protected function snapshot_isolated_hooks(): void {
		global $wp_filter;
		foreach ( static::ISOLATED_HOOKS as $hook ) {
			$this->saved_hooks[ $hook ] = $wp_filter[ $hook ] ?? null;
			unset( $wp_filter[ $hook ] );
		}
	}

	#[After]
	protected function restore_isolated_hooks(): void {
		global $wp_filter;
		foreach ( $this->saved_hooks as $hook => $saved ) {
			if ( null !== $saved ) {
				$wp_filter[ $hook ] = $saved;
			} else {
				unset( $wp_filter[ $hook ] );
			}
		}

		$this->saved_hooks = array();
	}
}
