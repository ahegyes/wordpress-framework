<?php declare( strict_types=1 );

namespace DeepWebSolutions\Framework\Tests\Integration\ConsumerA;

use DeepWebSolutions\Framework\Tests\Integration\Support\CreatesUsersTrait;
use DeepWebSolutions\Framework\Tests\Integration\Support\InstallsConsumersTrait;
use DeepWebSolutions\Framework\Tests\Integration\Support\ProductFixturesTrait;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;

#[CoversNothing]
final class ConsumerABootTest extends TestCase {
	use CreatesUsersTrait;
	use InstallsConsumersTrait;
	use ProductFixturesTrait;

	protected const string SCOPED = 'Acme\\ConsumerA\\Scoped\\DeepWebSolutions\\Framework\\';

	public function test_the_plugin_boots_and_its_components_register_their_hooks(): void {
		$plugin  = $this->install_consumer( 'consumer-a', 'acme-consumer-a' );
		$request = $this->consumer_request( 'GET', 'index.php', $this->make_user( 'acme_ca_admin', 'administrator' ), array( 'activate' => $plugin ) );

		self::assertNull( $request['died'] );
		self::assertStringContainsString( 'id="wpfooter"', $request['body'] );
		self::assertSame( 'booted', $request['records']['consumer_a']['status'] );
		self::assertSame( 1, $request['records']['consumer_a']['boot_succeeded'] );
		self::assertTrue( $request['records']['export_hooked'] );
		self::assertFalse( $request['records']['unloaded_include'] );
	}

	public function test_a_gated_subtree_is_skipped_and_its_children_never_load(): void {
		$plugin  = $this->install_consumer( 'consumer-a', 'acme-consumer-a' );
		$request = $this->consumer_request( 'GET', 'index.php', $this->make_user( 'acme_ca_admin', 'administrator' ), array( 'activate' => $plugin ) );

		self::assertSame( array( 'Acme\\ConsumerA\\Reports\\Reports', 'Acme\\ConsumerA\\FaultInjector' ), $request['records']['consumer_a']['skipped'] );
		self::assertFalse( $request['records']['reports_page_class'] );
	}

	public function test_the_product_edit_screen_shows_the_plugins_tab(): void {
		$product = $this->make_product();
		$plugin  = $this->install_consumer( 'consumer-a', 'acme-consumer-a' );
		$request = $this->consumer_request( 'GET', "post.php?post={$product->get_id()}&action=edit", $this->make_user( 'acme_ca_admin', 'administrator' ), array( 'activate' => $plugin ) );

		self::assertNull( $request['died'] );
		self::assertStringContainsString( 'id="acme_ca_packing_product_data"', $request['body'] );
		self::assertStringContainsString( 'Packing note', $request['body'] );
	}

	#[RunInSeparateProcess]
	public function test_no_component_appears_twice_in_the_tree_whatever_its_gates(): void {
		require_once self::FIXTURES . '/consumer-a/vendor/autoload.php';

		$walked  = array();
		$pending = \constant( 'Acme\\ConsumerA\\Plugin::COMPONENTS' );
		while ( array() !== $pending ) {
			$class    = \array_shift( $pending );
			$walked[] = $class;
			if ( \is_a( $class, self::SCOPED . 'Core\\CompositeComponentInterface', true ) ) {
				\array_push( $pending, ...$class::get_child_component_classes() );
			}
		}

		self::assertContains( 'Acme\\ConsumerA\\Reports\\ReportsPage', $walked );
		self::assertSame( \array_values( \array_unique( $walked ) ), $walked );
	}
}
