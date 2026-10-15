<?php declare( strict_types=1 );

namespace DeepWebSolutions\Framework\Tests\Integration\Support;

use PHPUnit\Framework\Attributes\After;

trait InterceptsQueriesTrait {
	protected ?bool $suppressed_errors = null;

	// Runs the callback once, just before the first query that contains the needle, as another request would.
	protected function before_query( string $needle, \Closure $callback ): void {
		$done = false;
		\add_filter(
			'query',
			static function ( string $query ) use ( $needle, $callback, &$done ): string {
				if ( ! $done && \str_contains( $query, $needle ) ) {
					$done = true;
					$callback();
				}

				return $query;
			}
		);
	}

	// An emptied query would return false without clearing the previous result, so each matching query becomes one the database rejects.
	protected function fail_queries( string $needle ): void {
		global $wpdb;

		$this->suppressed_errors ??= $wpdb->suppress_errors();
		\add_filter( 'query', static fn ( string $query ): string => \str_contains( $query, $needle ) ? 'SELECT acme_missing_column' : $query );
	}

	#[After]
	protected function restore_query_errors(): void {
		global $wpdb;

		if ( ! \is_null( $this->suppressed_errors ) ) {
			$wpdb->suppress_errors( $this->suppressed_errors );
			$this->suppressed_errors = null;
		}
	}
}
