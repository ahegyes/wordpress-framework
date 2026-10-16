<?php declare( strict_types=1 );

namespace DeepWebSolutions\Framework\Tests\Integration\Bootstrap;

use DeepWebSolutions\Framework\Tests\Integration\Support\IsolatesHooksTrait;
use PHPUnit\Framework\Attributes\CoversFunction;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

use function DeepWebSolutions\Framework\Bootstrap\uninstall;

#[CoversFunction( 'DeepWebSolutions\Framework\Bootstrap\uninstall' )]
#[Group( 'without-woocommerce' )]
final class UninstallWithoutActionSchedulerTest extends TestCase {
	use IsolatesHooksTrait;
	use UninstallFixturesTrait;

	#[\Override]
	public static function setUpBeforeClass(): void {
		require_once \dirname( __DIR__, 3 ) . '/packages/bootstrap/uninstall.php';
	}

	public function test_a_consenting_run_without_action_scheduler_completes(): void {
		self::assertFalse( \function_exists( 'as_unschedule_all_actions' ) );
		\update_option( "{$this->prefix}_remove_data", 'yes' );
		\update_option( "{$this->prefix}_settings", 'x' );
		\wp_schedule_single_event( \time() + 3600, "{$this->prefix}_cron" );

		uninstall(
			self::PLUGIN_FILE,
			array(
				'cron_hooks' => array( "{$this->prefix}_cron" ),
				'as_hooks'   => array( "{$this->prefix}_as" ),
				'as_groups'  => array( "{$this->prefix}-group" ),
				'options'    => array( "{$this->prefix}_settings" ),
			),
			array( 'consent' => array( 'option' => "{$this->prefix}_remove_data" ) ) + $this->recording_callables()
		);

		self::assertFalse( \wp_next_scheduled( "{$this->prefix}_cron" ) );
		self::assertFalse( $this->option_exists( "{$this->prefix}_settings" ) );
		self::assertFalse( $this->option_exists( "{$this->prefix}_remove_data" ) );
		self::assertSame( array( 'cleanup' ), \array_column( $this->calls, 0 ) );
	}
}
