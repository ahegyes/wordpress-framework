<?php declare( strict_types=1 );

namespace DeepWebSolutions\Framework\Tests\Integration\Settings;

use DeepWebSolutions\Framework\Settings\Forms;
use DeepWebSolutions\Framework\Tests\Integration\Support\CreatesUsersTrait;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass( Forms::class )]
final class FormsVerifyObjectFormTest extends TestCase {
	use CreatesUsersTrait;

	protected array $post_backup;

	protected int $author_id;

	protected int $post_id;

	#[\Override]
	protected function setUp(): void {
		$this->post_backup = $_POST; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Backs up the request that tearDown() restores.
		$this->author_id   = $this->make_user( 'acme-forms-author', 'author' );
		$this->post_id     = (int) \wp_insert_post(
			array(
				'post_title'  => 'Acme',
				'post_status' => 'publish',
				'post_author' => $this->author_id,
			)
		);
	}

	#[\Override]
	protected function tearDown(): void {
		$_POST = $this->post_backup;
		\wp_delete_post( $this->post_id, true );
		\wp_set_current_user( 0 );
	}

	public function test_a_valid_nonce_with_both_capabilities_passes(): void {
		\wp_set_current_user( $this->author_id );
		$this->post_nonce( 'acme_post_' . $this->post_id );

		self::assertTrue( Forms::verify_object_form( 'acme_post', $this->post_id, 'edit_post', 'edit_posts' ) );
	}

	public function test_a_user_editing_their_own_profile_with_the_plugin_capability_passes(): void {
		$subscriber_id = $this->make_user( 'acme-forms-subscriber', 'subscriber' );
		\wp_set_current_user( $subscriber_id );
		$this->post_nonce( 'acme_profile_' . $subscriber_id );

		self::assertTrue( Forms::verify_object_form( 'acme_profile', $subscriber_id, 'edit_user', 'read' ) );
	}

	public function test_a_user_without_the_object_capability_is_refused_on_another_authors_post(): void {
		\wp_set_current_user( $this->make_user( 'acme-forms-other-author', 'author' ) );
		$this->post_nonce( 'acme_post_' . $this->post_id );

		self::assertFalse( Forms::verify_object_form( 'acme_post', $this->post_id, 'edit_post', 'edit_posts' ) );
	}

	public function test_a_user_without_the_object_capability_is_refused_on_another_users_profile(): void {
		\wp_set_current_user( $this->make_user( 'acme-forms-subscriber', 'subscriber' ) );
		$this->post_nonce( 'acme_profile_' . $this->author_id );

		self::assertFalse( Forms::verify_object_form( 'acme_profile', $this->author_id, 'edit_user', 'read' ) );
	}

	public function test_a_user_editing_their_own_profile_without_the_plugin_capability_is_refused(): void {
		$subscriber_id = $this->make_user( 'acme-forms-subscriber', 'subscriber' );
		\wp_set_current_user( $subscriber_id );
		$this->post_nonce( 'acme_profile_' . $subscriber_id );

		self::assertTrue( \current_user_can( 'edit_user', $subscriber_id ) );
		self::assertFalse( Forms::verify_object_form( 'acme_profile', $subscriber_id, 'edit_user', 'list_users' ) );
	}

	public function test_a_nonce_minted_for_another_action_is_refused(): void {
		\wp_set_current_user( $this->author_id );
		$this->post_nonce( 'acme_other_' . $this->post_id );

		self::assertFalse( Forms::verify_object_form( 'acme_post', $this->post_id, 'edit_post', 'edit_posts' ) );
	}

	public function test_a_nonce_minted_for_another_object_is_refused(): void {
		\wp_set_current_user( $this->author_id );
		$this->post_nonce( 'acme_post_' . ( $this->post_id + 1 ) );

		self::assertFalse( Forms::verify_object_form( 'acme_post', $this->post_id, 'edit_post', 'edit_posts' ) );
	}

	public function test_a_nonce_for_the_action_without_the_object_is_refused(): void {
		\wp_set_current_user( $this->author_id );
		$this->post_nonce( 'acme_post' );

		self::assertFalse( Forms::verify_object_form( 'acme_post', $this->post_id, 'edit_post', 'edit_posts' ) );
	}

	public function test_a_missing_or_non_string_nonce_returns_false_without_dying(): void {
		\wp_set_current_user( $this->author_id );
		unset( $_POST['acme_post'] );

		self::assertFalse( Forms::verify_object_form( 'acme_post', $this->post_id, 'edit_post', 'edit_posts' ) );

		$_POST['acme_post'] = array( \wp_create_nonce( 'acme_post_' . $this->post_id ) );

		self::assertFalse( Forms::verify_object_form( 'acme_post', $this->post_id, 'edit_post', 'edit_posts' ) );
	}

	protected function post_nonce( string $action ): void {
		$nonce                 = \wp_slash( \wp_create_nonce( $action ) );
		$_POST['acme_post']    = $nonce;
		$_POST['acme_profile'] = $nonce;
	}
}
