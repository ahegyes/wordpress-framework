<?php declare( strict_types=1 );

namespace DeepWebSolutions\Framework\Tests\Integration\Core\Migration;

use DeepWebSolutions\Framework\Core\Storage\OptionRows;
use PHPUnit\Framework\Attributes\After;
use PHPUnit\Framework\Attributes\Before;

trait MigrationFixturesTrait {
	protected string $option;

	#[Before]
	protected function use_a_fresh_option(): void {
		$this->option = 'acme_db_' . \bin2hex( \random_bytes( 4 ) );
	}

	#[After]
	protected function delete_runner_options(): void {
		foreach ( array( '', '_error', '_high_water', '_first_run', '_lock' ) as $suffix ) {
			delete_option( $this->option . $suffix );
		}
	}

	protected function get_lock(): mixed {
		return OptionRows::get( "{$this->option}_lock" )?->value;
	}
}
