<?php declare( strict_types=1 );

namespace DeepWebSolutions\Framework\Storage\Tests\Integration\ObjectMeta;

use DeepWebSolutions\Framework\Storage\ObjectMeta\MetadataRepository;
use DeepWebSolutions\Framework\Storage\ObjectMeta\MetaType;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass( MetadataRepository::class )]
#[UsesClass( MetaType::class )]
final class MetadataRepositoryTest extends TestCase {
	private int $post_id = 0;

	protected function setUp(): void {
		parent::setUp();

		// wp_delete_user() lives in the admin includes, absent from the CLI context the suite runs in.
		require_once ABSPATH . 'wp-admin/includes/user.php';

		$post_id = \wp_insert_post(
			array(
				'post_title'  => 'Probe',
				'post_status' => 'publish',
			)
		);
		\assert( \is_int( $post_id ) );
		$this->post_id = $post_id;
	}

	protected function tearDown(): void {
		\wp_delete_post( $this->post_id, true );

		parent::tearDown();
	}

	public function test_set_and_get_round_trip_on_post_meta(): void {
		$repo = new MetadataRepository( MetaType::Post );

		$repo->set( $this->post_id, '_dws_note', 'hello' );

		self::assertSame( 'hello', $repo->get( $this->post_id, '_dws_note' ) );
	}

	public function test_get_returns_the_default_when_nothing_is_stored(): void {
		$repo = new MetadataRepository( MetaType::Post );

		self::assertSame( 'fallback', $repo->get( $this->post_id, '_dws_absent', 'fallback' ) );
	}

	public function test_a_stored_empty_string_is_returned_not_the_default(): void {
		$repo = new MetadataRepository( MetaType::Post );

		// get() keys off existence, not truthiness: a stored empty value must read back as itself.
		$repo->set( $this->post_id, '_dws_empty', '' );

		self::assertTrue( $repo->has( $this->post_id, '_dws_empty' ) );
		self::assertSame( '', $repo->get( $this->post_id, '_dws_empty', 'default' ) );
	}

	public function test_has_reports_presence_and_delete_removes_the_value(): void {
		$repo = new MetadataRepository( MetaType::Post );

		self::assertFalse( $repo->has( $this->post_id, '_dws_flag' ) );

		$repo->set( $this->post_id, '_dws_flag', '1' );
		self::assertTrue( $repo->has( $this->post_id, '_dws_flag' ) );

		self::assertTrue( $repo->delete( $this->post_id, '_dws_flag' ) );
		self::assertFalse( $repo->has( $this->post_id, '_dws_flag' ) );
		self::assertFalse( $repo->delete( $this->post_id, '_dws_flag' ) );
	}

	public function test_apply_persists_sets_and_removes_deletes(): void {
		$repo = new MetadataRepository( MetaType::Post );
		$repo->set( $this->post_id, '_dws_old', 'gone' );

		$repo->apply(
			$this->post_id,
			array(
				'_dws_a' => 'A',
				'_dws_b' => 'B',
			),
			array( '_dws_old' ),
		);

		self::assertSame( 'A', $repo->get( $this->post_id, '_dws_a' ) );
		self::assertSame( 'B', $repo->get( $this->post_id, '_dws_b' ) );
		self::assertFalse( $repo->has( $this->post_id, '_dws_old' ) );
	}

	public function test_apply_skips_deleting_a_key_that_is_not_stored(): void {
		$repo = new MetadataRepository( MetaType::Post );

		// A delete of a never-set key is a no-op; the key simply stays absent.
		$repo->apply( $this->post_id, array(), array( '_dws_never_set' ) );

		self::assertFalse( $repo->has( $this->post_id, '_dws_never_set' ) );
	}

	public function test_set_preserves_backslashes(): void {
		$repo = new MetadataRepository( MetaType::Post );

		$repo->set( $this->post_id, '_dws_path', 'C:\\Users\\dev\\file.txt' );

		// update_metadata() unslashes internally; without the compensating slash the backslashes drop.
		self::assertSame( 'C:\\Users\\dev\\file.txt', $repo->get( $this->post_id, '_dws_path' ) );
	}

	public function test_set_preserves_an_array_value(): void {
		$repo = new MetadataRepository( MetaType::Post );

		$repo->set( $this->post_id, '_dws_list', array( 'x', 'y' ) );

		self::assertSame( array( 'x', 'y' ), $repo->get( $this->post_id, '_dws_list' ) );
	}

	public function test_set_preserves_a_backslash_in_the_meta_key(): void {
		$repo = new MetadataRepository( MetaType::Post );

		// update_metadata()/delete_metadata() unslash the key while get()/has() look it up raw; without the
		// compensating slash a key with a backslash would be written under a different key than it is read.
		$repo->set( $this->post_id, 'dws\\odd\\key', 'v' );

		self::assertTrue( $repo->has( $this->post_id, 'dws\\odd\\key' ) );
		self::assertSame( 'v', $repo->get( $this->post_id, 'dws\\odd\\key' ) );
		self::assertTrue( $repo->delete( $this->post_id, 'dws\\odd\\key' ) );
		self::assertFalse( $repo->has( $this->post_id, 'dws\\odd\\key' ) );
	}

	public function test_apply_round_trips_a_numeric_meta_key(): void {
		$repo = new MetadataRepository( MetaType::Post );

		// PHP normalizes a numeric-string array key to an int; apply() must cast it back to a string key.
		$repo->apply( $this->post_id, array( '123' => 'x' ), array() );

		self::assertSame( 'x', $repo->get( $this->post_id, '123' ) );
	}

	public function test_repository_targets_user_meta(): void {
		$user_id = \wp_insert_user(
			array(
				'user_login' => 'dws_meta_probe_' . $this->post_id,
				'user_pass'  => 'x',
				'role'       => 'subscriber',
			),
		);
		\assert( \is_int( $user_id ) );
		$repo = new MetadataRepository( MetaType::User );

		$repo->set( $user_id, 'dws_pref', 'on' );

		self::assertSame( 'on', $repo->get( $user_id, 'dws_pref' ) );
		self::assertSame( 'on', \get_user_meta( $user_id, 'dws_pref', true ) ); // landed in user meta specifically

		\wp_delete_user( $user_id );
	}

	public function test_repository_targets_term_meta(): void {
		$term = \wp_insert_term( 'DWS Meta Probe ' . $this->post_id, 'category' );
		\assert( \is_array( $term ) );
		$term_id = (int) $term['term_id'];
		$repo    = new MetadataRepository( MetaType::Term );

		$repo->set( $term_id, 'dws_color', 'blue' );

		self::assertSame( 'blue', $repo->get( $term_id, 'dws_color' ) );
		self::assertSame( 'blue', \get_term_meta( $term_id, 'dws_color', true ) ); // landed in term meta specifically

		\wp_delete_term( $term_id, 'category' );
	}

	public function test_repository_targets_comment_meta(): void {
		$comment_id = \wp_insert_comment(
			array(
				'comment_post_ID' => $this->post_id,
				'comment_content' => 'hi',
			)
		);
		\assert( \is_int( $comment_id ) );
		$repo = new MetadataRepository( MetaType::Comment );

		$repo->set( $comment_id, 'dws_flag', 'yes' );

		self::assertSame( 'yes', $repo->get( $comment_id, 'dws_flag' ) );
		self::assertSame( 'yes', \get_comment_meta( $comment_id, 'dws_flag', true ) ); // landed in comment meta specifically

		\wp_delete_comment( $comment_id, true );
	}
}
