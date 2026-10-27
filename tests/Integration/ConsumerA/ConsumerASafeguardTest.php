<?php declare( strict_types=1 );

namespace DeepWebSolutions\Framework\Tests\Integration\ConsumerA;

use DeepWebSolutions\Framework\Tests\Integration\Support\CreatesUsersTrait;
use DeepWebSolutions\Framework\Tests\Integration\Support\InstallsConsumersTrait;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversNothing]
final class ConsumerASafeguardTest extends TestCase {
	use CreatesUsersTrait;
	use InstallsConsumersTrait;

	public static function relaxations(): array {
		return array(
			'none'         => array( array(), false ),
			'null'         => array( array( 'relaxation' => null ), false ),
			'zero'         => array( array( 'relaxation' => 0 ), false ),
			'empty-string' => array( array( 'relaxation' => '' ), false ),
			'false'        => array( array( 'relaxation' => false ), true ),
		);
	}

	#[DataProvider( 'relaxations' )]
	public function test_only_an_explicit_false_relaxes_the_safeguard_and_only_after_the_plugin_booted( array $spec, bool $relaxed ): void {
		$plugin  = $this->install_consumer( 'consumer-a', 'acme-consumer-a' );
		$request = $this->consumer_request( 'GET', 'index.php', $this->make_user( 'acme_ca_admin', 'administrator' ), array( 'activate' => $plugin ) + $spec );

		self::assertSame( 'booted', $request['records']['consumer_a']['status'] );
		self::assertFalse( $request['records']['can_export_before_boot'] );
		self::assertSame( $relaxed, $request['records']['can_export'] );
	}

	public function test_a_relaxation_scoped_to_an_operation_that_throws_is_removed_and_the_safeguard_restricts_again(): void {
		$plugin  = $this->install_consumer( 'consumer-a', 'acme-consumer-a' );
		$request = $this->consumer_request(
			'GET',
			'index.php',
			$this->make_user( 'acme_ca_admin', 'administrator' ),
			array(
				'activate'      => $plugin,
				'export_throws' => true,
			)
		);

		self::assertSame( 'The order query failed.', $request['records']['export_error'] );
		self::assertFalse( $request['records']['relaxation_left'] );
		self::assertFalse( $request['records']['can_export_after_throw'] );
		self::assertSame( 10, $request['records']['safeguard_attached_after_throw'] );
	}
}
