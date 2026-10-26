<?php declare( strict_types=1 );

namespace DeepWebSolutions\Framework\Tests\Integration\Settings\EditScreen;

use DeepWebSolutions\Framework\Core\AdminNotice\AdminNotices;
use DeepWebSolutions\Framework\Core\AdminNotice\NoticeScope;
use DeepWebSolutions\Framework\Core\AdminNotice\NoticeType;
use DeepWebSolutions\Framework\Core\Logging\ErrorLogLogger;
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
use DeepWebSolutions\Framework\Tests\Integration\Support\RecordedDefinition;
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

	protected ?RecordedDefinition $definition = null;

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
		self::assertSame( array( array( "{$this->prefix}_details", $this->post_id ) ), $this->definition->calls );
	}

	public function test_the_definition_receives_the_forms_id_and_each_object_once(): void {
		\wp_set_current_user( $this->author_id );
		$other_id = (int) \wp_insert_post(
			array(
				'post_title'  => 'Acme',
				'post_status' => 'publish',
				'post_author' => $this->author_id,
			)
		);
		$form     = $this->form( new PostMetaBox( array( 'post' ) ) );

		$form->can_render( $this->post_id );
		$form->can_render( $other_id );
		$form->can_render( $this->post_id );
		\wp_delete_post( $other_id, true );

		self::assertSame( array( array( "{$this->prefix}_details", $this->post_id ), array( "{$this->prefix}_details", $other_id ) ), $this->definition->calls );
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

		self::assertSame( array(), $this->definition->calls );
		self::assertSame( '', \get_post_meta( $this->post_id, "_{$this->prefix}_name", true ) );
	}

	public function test_a_save_without_the_forms_nonce_stores_nothing_and_evaluates_no_definition(): void {
		\wp_set_current_user( $this->author_id );
		$this->post_form( $this->post_id );
		unset( $_POST[ "{$this->prefix}_details_nonce" ] );

		$this->form( new PostMetaBox( array( 'post' ) ) )->save( $this->post_id );

		self::assertSame( array(), $this->definition->calls );
		self::assertSame( '', \get_post_meta( $this->post_id, "_{$this->prefix}_name", true ) );
	}

	public function test_each_rejection_becomes_a_one_shot_error_notice_for_the_user_who_saved(): void {
		\wp_set_current_user( $this->author_id );
		$this->post_form( $this->post_id );
		$notices = new AdminNotices( $this->prefix, new ErrorLogLogger( 'acme' ) );

		$this->rejecting_form( $notices )->save( $this->post_id );

		$stored = $notices->get_notices( NoticeScope::User, $this->author_id );
		self::assertCount( 1, $stored );
		self::assertSame( 'Note &amp; more was not saved, so it keeps its previous value. Use at most four characters.', $stored[0]->message );
		self::assertSame( NoticeType::Error, $stored[0]->type );
		self::assertFalse( $stored[0]->persistent );
		self::assertSame( 'Saved.', \get_post_meta( $this->post_id, "_{$this->prefix}_name", true ) );
	}

	public function test_a_rejection_whose_notice_cannot_be_stored_is_logged_and_keeps_the_accepted_values(): void {
		\wp_set_current_user( $this->author_id );
		$this->post_form( $this->post_id );
		\add_filter( 'update_user_metadata', static fn (): never => throw new \RuntimeException( 'The user meta is unavailable.' ) );
		$log_file = (string) \tempnam( \sys_get_temp_dir(), 'acme-log-' );
		$previous = \ini_set( 'error_log', $log_file );

		try {
			$this->rejecting_form( new AdminNotices( $this->prefix, new ErrorLogLogger( 'acme' ) ) )->save( $this->post_id );
			$log = (string) \file_get_contents( $log_file );
		} finally {
			\ini_set( 'error_log', (string) $previous );
			\wp_delete_file( $log_file );
		}

		self::assertSame( 'Saved.', \get_post_meta( $this->post_id, "_{$this->prefix}_name", true ) );
		self::assertStringContainsString( '[acme.error] Could not add a user notice: The user meta is unavailable.', $log );
	}

	public function test_a_host_serves_one_form(): void {
		$host = new PostMetaBox( array( 'post' ) );
		$this->form( $host )->register_hooks();

		$this->expectException( LogicException::class );

		( new ObjectForm( "{$this->prefix}_other", new RecordedDefinition( static fn (): array => array() ), $host, new AdminNotices( $this->prefix, new ErrorLogLogger( 'acme' ) ) ) )->register_hooks();
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

		( new ObjectForm( "{$this->prefix}_other", new RecordedDefinition( static fn (): array => array() ), $host, new AdminNotices( $this->prefix, new ErrorLogLogger( 'acme' ) ) ) )->register_hooks();
	}

	protected function form( ObjectFormHostInterface $host, string $capability = 'edit_posts' ): ObjectForm {
		$this->definition = new RecordedDefinition(
			fn (): array => array(
				'title'      => 'Details',
				'capability' => $capability,
				'fields'     => array(
					array(
						'type' => 'text',
						'id'   => "_{$this->prefix}_name",
					),
				),
			)
		);

		return new ObjectForm(
			"{$this->prefix}_details",
			$this->definition,
			$host,
			new AdminNotices( $this->prefix, new ErrorLogLogger( 'acme' ) )
		);
	}

	protected function rejecting_form( AdminNotices $notices ): ObjectForm {
		$_POST[ "{$this->prefix}_details" ][ "_{$this->prefix}_note" ] = 'Too long.';

		return new ObjectForm(
			"{$this->prefix}_details",
			new RecordedDefinition(
				fn (): array => array(
					'title'      => 'Details',
					'capability' => 'edit_posts',
					'fields'     => array(
						array(
							'type' => 'text',
							'id'   => "_{$this->prefix}_name",
						),
						array(
							'type'     => 'text',
							'id'       => "_{$this->prefix}_note",
							'title'    => 'Note & more',
							'validate' => static fn (): \WP_Error => new \WP_Error( 'acme_rejected', 'Use at most four characters.' ),
						),
					),
				)
			),
			new PostMetaBox( array( 'post' ) ),
			$notices
		);
	}

	protected function post_form( int $object_id ): void {
		$_POST = array(
			"{$this->prefix}_details_nonce" => \wp_slash( \wp_create_nonce( "{$this->prefix}_details_nonce_$object_id" ) ),
			"{$this->prefix}_details"       => array( "_{$this->prefix}_name" => 'Saved.' ),
		);
	}
}
