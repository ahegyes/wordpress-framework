<?php declare( strict_types=1 );

namespace DeepWebSolutions\Framework\Tests\Integration\Settings\EditScreen;

use DeepWebSolutions\Framework\Settings\EditScreen\Hosts\PostMetaBox;
use DeepWebSolutions\Framework\Settings\EditScreen\Hosts\UserProfile;
use DeepWebSolutions\Framework\Settings\EditScreen\ObjectForm;
use DeepWebSolutions\Framework\Settings\EditScreen\ObjectFormHostInterface;
use DeepWebSolutions\Framework\Settings\EditScreen\ObjectStoreInterface;
use DeepWebSolutions\Framework\Settings\EditScreen\Stores\MetadataStore;
use DeepWebSolutions\Framework\Shared\Exception\LogicException;
use DeepWebSolutions\Framework\Tests\Integration\Support\CreatesUsersTrait;
use DeepWebSolutions\Framework\Tests\Integration\Support\IsolatesHooksTrait;
use DeepWebSolutions\Framework\Tests\Integration\Support\IsolatesStorageTrait;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass( ObjectForm::class )]
final class ObjectFormTest extends TestCase {
	use CreatesUsersTrait;
	use IsolatesHooksTrait;
	use IsolatesStorageTrait;

	protected array $post_backup;

	protected int $author_id;

	protected int $post_id;

	protected int $evaluations = 0;

	#[\Override]
	protected function setUp(): void {
		$this->post_backup = $_POST; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Backs up the request that tearDown() restores.
		$this->author_id   = $this->make_user( 'acme-object-form-author', 'author' );
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

	public static function foreign_nonces(): array {
		return array(
			'another-object' => array( static fn ( string $form_id, int $object_id ): mixed => \wp_create_nonce( "{$form_id}_nonce_" . ( $object_id + 1 ) ) ),
			'no-object'      => array( static fn ( string $form_id, int $object_id ): mixed => \wp_create_nonce( "{$form_id}_nonce" ) ),
			'another-form'   => array( static fn ( string $form_id, int $object_id ): mixed => \wp_create_nonce( "{$form_id}_other_nonce_$object_id" ) ),
			'not-a-string'   => array( static fn ( string $form_id, int $object_id ): mixed => array( \wp_create_nonce( "{$form_id}_nonce_$object_id" ) ) ),
		);
	}

	public function test_a_valid_nonce_with_both_capabilities_stores_the_fields(): void {
		\wp_set_current_user( $this->author_id );
		$this->post_form( $this->post_id );

		$this->form( new PostMetaBox( array( 'post' ) ) )->save( $this->post_id );

		self::assertSame( 'Saved.', \get_post_meta( $this->post_id, "_{$this->prefix}_name", true ) );
	}

	public function test_a_user_editing_their_own_profile_with_the_forms_capability_stores_the_fields(): void {
		$subscriber_id = $this->make_user( 'acme-object-form-subscriber', 'subscriber' );
		\wp_set_current_user( $subscriber_id );
		$this->post_form( $subscriber_id );

		$this->form( new UserProfile(), 'read' )->save( $subscriber_id );

		self::assertSame( 'Saved.', \get_user_meta( $subscriber_id, "_{$this->prefix}_name", true ) );
	}

	public function test_a_user_without_the_object_capability_stores_nothing_on_another_authors_post(): void {
		\wp_set_current_user( $this->make_user( 'acme-object-form-other-author', 'author' ) );
		$this->post_form( $this->post_id );

		$this->form( new PostMetaBox( array( 'post' ) ) )->save( $this->post_id );

		self::assertSame( '', \get_post_meta( $this->post_id, "_{$this->prefix}_name", true ) );
	}

	public function test_a_user_without_the_object_capability_stores_nothing_on_another_users_profile(): void {
		\wp_set_current_user( $this->make_user( 'acme-object-form-subscriber', 'subscriber' ) );
		$this->post_form( $this->author_id );

		$this->form( new UserProfile(), 'read' )->save( $this->author_id );

		self::assertSame( '', \get_user_meta( $this->author_id, "_{$this->prefix}_name", true ) );
	}

	public function test_a_user_editing_their_own_profile_without_the_forms_capability_stores_nothing(): void {
		$subscriber_id = $this->make_user( 'acme-object-form-subscriber', 'subscriber' );
		\wp_set_current_user( $subscriber_id );
		$this->post_form( $subscriber_id );

		$this->form( new UserProfile(), 'list_users' )->save( $subscriber_id );

		self::assertTrue( \current_user_can( 'edit_user', $subscriber_id ) );
		self::assertSame( '', \get_user_meta( $subscriber_id, "_{$this->prefix}_name", true ) );
	}

	#[DataProvider( 'foreign_nonces' )]
	public function test_a_nonce_minted_for_anything_but_the_form_and_object_stores_nothing_and_evaluates_no_definition( \Closure $nonce ): void {
		\wp_set_current_user( $this->author_id );
		$this->post_form( $this->post_id );
		$_POST[ "{$this->prefix}_details_nonce" ] = \wp_slash( $nonce( "{$this->prefix}_details", $this->post_id ) );

		$this->form( new PostMetaBox( array( 'post' ) ) )->save( $this->post_id );

		self::assertSame( 0, $this->evaluations );
		self::assertSame( '', \get_post_meta( $this->post_id, "_{$this->prefix}_name", true ) );
	}

	public function test_a_save_without_the_forms_nonce_stores_nothing_and_evaluates_no_definition(): void {
		\wp_set_current_user( $this->author_id );
		$this->post_form( $this->post_id );
		unset( $_POST[ "{$this->prefix}_details_nonce" ] );

		$this->form( new PostMetaBox( array( 'post' ) ) )->save( $this->post_id );

		self::assertSame( 0, $this->evaluations );
		self::assertSame( '', \get_post_meta( $this->post_id, "_{$this->prefix}_name", true ) );
	}

	public function test_a_host_serves_one_form(): void {
		$host = new PostMetaBox( array( 'post' ) );
		$this->form( $host )->register_hooks();

		$this->expectException( LogicException::class );

		( new ObjectForm( "{$this->prefix}_other", static fn (): array => array(), $host ) )->register_hooks();
	}

	public function test_a_host_the_consumer_writes_serves_one_form_too(): void {
		$host = new class() implements ObjectFormHostInterface {
			public function register_hooks( ObjectForm $form ): void {}

			public function get_object_capability(): string {
				return 'edit_post';
			}

			public function get_default_store(): ObjectStoreInterface {
				return new MetadataStore( 'post' );
			}

			public function render_rows( string $title, array $rows ): void {}
		};
		$form = $this->form( $host );
		$form->register_hooks();
		$form->register_hooks();

		$this->expectException( LogicException::class );

		( new ObjectForm( "{$this->prefix}_other", static fn (): array => array(), $host ) )->register_hooks();
	}

	protected function form( ObjectFormHostInterface $host, string $capability = 'edit_posts' ): ObjectForm {
		return new ObjectForm(
			"{$this->prefix}_details",
			function () use ( $capability ): array {
				++$this->evaluations;

				return array(
					'title'      => 'Details',
					'capability' => $capability,
					'fields'     => array(
						array(
							'type' => 'text',
							'id'   => "_{$this->prefix}_name",
						),
					),
				);
			},
			$host
		);
	}

	protected function post_form( int $object_id ): void {
		$_POST = array(
			"{$this->prefix}_details_nonce" => \wp_slash( \wp_create_nonce( "{$this->prefix}_details_nonce_$object_id" ) ),
			"{$this->prefix}_details"       => array( "_{$this->prefix}_name" => 'Saved.' ),
		);
	}
}
