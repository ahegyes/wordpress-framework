<?php declare( strict_types=1 );

namespace DeepWebSolutions\Framework\Tests\Integration\Personas;

use DeepWebSolutions\Framework\Tests\Integration\Support\CreatesUsersTrait;
use DeepWebSolutions\Framework\Tests\Integration\Support\InstallsConsumersTrait;
use DeepWebSolutions\Framework\Tests\Integration\Support\SerializesFormsTrait;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

#[CoversNothing]
#[Group( 'without-woocommerce' )]
final class SettingsPersonaTest extends TestCase {
	use CreatesUsersTrait;
	use InstallsConsumersTrait;
	use SerializesFormsTrait;

	#[\Override]
	protected function tearDown(): void {
		delete_transient( 'settings_errors' );
	}

	public function test_a_valid_limit_saved_through_options_php_is_stored(): void {
		$saved = $this->save_limit( '5' );

		self::assertNull( $saved['died'] );
		self::assertSame( array( 'limit' => 5 ), get_option( 'acme_ps_settings' ) );
	}

	public function test_a_rejected_limit_keeps_the_stored_one_and_its_message_shows_on_the_page(): void {
		update_option( 'acme_ps_settings', array( 'limit' => 5 ) );

		$saved = $this->save_limit( '0' );
		self::assertSame( array( 'limit' => 5 ), get_option( 'acme_ps_settings' ) );
		self::assertIsString( $saved['location'] );

		$page = $this->consumer_request( 'GET', \explode( '/wp-admin/', $saved['location'], 2 )[1], $saved['user'], $saved['spec'] );
		self::assertStringContainsString( 'Limit was not saved, so it keeps its previous value. Enter a whole number of at least 1.', $page['body'] );
	}

	// Posts the settings tab as the browser submits it after the user typed the limit.
	protected function save_limit( string $limit ): array {
		$user = $this->make_user( 'acme_ps_admin', 'administrator' );
		$spec = array( 'activate' => $this->install_consumer( 'personas/settings', 'acme-persona-settings' ) );
		$data = self::serialize_form( $this->consumer_request( 'GET', 'options-general.php?page=acme_ps', $user, $spec )['body'], 'form[action="options.php"]', array( 'acme_ps_settings[limit]' => $limit ) );

		return $this->consumer_request( 'POST', 'options.php', $user, $spec, $data ) + array(
			'user' => $user,
			'spec' => $spec,
		);
	}
}
