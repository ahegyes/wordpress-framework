<?php declare( strict_types=1 );

namespace DeepWebSolutions\Framework\Tests\Integration\Core\Migration;

use DeepWebSolutions\Framework\Core\PluginIdentity;
use DeepWebSolutions\Framework\Core\Storage\OptionRows;
use PHPUnit\Framework\Attributes\After;
use PHPUnit\Framework\Attributes\Before;

trait MigrationFixturesTrait {
	protected PluginIdentity $identity;

	protected string $option;

	protected string $hook_root;

	// The slug differs from the prefix, so a row tells the runner's hook from its stored names.
	#[Before]
	protected function use_a_fresh_identity(): void {
		$suffix          = \bin2hex( \random_bytes( 4 ) );
		$this->identity  = new PluginIdentity( \dirname( __DIR__, 3 ) . '/Fixtures/Core/acme-boot.php', "acme-runner-$suffix", "acme_db_$suffix" );
		$this->option    = "acme_db_{$suffix}_revision";
		$this->hook_root = "acme_runner_$suffix";
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
