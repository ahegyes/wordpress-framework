<?php declare( strict_types=1 );

namespace DeepWebSolutions\Framework\Tests\Integration\Core\Uninstall;

use DeepWebSolutions\Framework\Tests\Integration\Support\IsolatesStorageTrait;
use PHPUnit\Framework\Attributes\After;

trait UninstallFixturesTrait {
	use IsolatesStorageTrait;

	protected const string PLUGIN_FILE = \WP_PLUGIN_DIR . '/acme-uninstall/acme-uninstall.php';

	protected array $calls = array();

	protected array $ids = array();

	protected function footprint(): array {
		$p = $this->prefix;

		return array(
			'options'                   => array( "{$p}_settings" ),
			'option_prefixes'           => array( "{$p}_cache_" ),
			'transients'                => array( "{$p}_transient" ),
			'post_types'                => array( "{$p}_type" ),
			'meta'                      => array(
				'post'    => array( "{$p}_flag" ),
				'term'    => array( "{$p}_flag" ),
				'comment' => array( "{$p}_flag" ),
			),
			'meta_prefixes'             => array(
				'post'    => array( "{$p}_post_" ),
				'term'    => array( "{$p}_term_" ),
				'comment' => array( "{$p}_comment_" ),
			),
			'comment_types'             => array( "{$p}_note" ),
			'user_meta_site'            => array( "{$p}_site_state" ),
			'user_meta_site_prefixes'   => array( "{$p}_site_" ),
			'user_meta_global'          => array( "{$p}_global_state" ),
			'user_meta_global_prefixes' => array( "{$p}_global_" ),
			'capabilities'              => array( "manage_$p" ),
			'tables'                    => array( "{$p}_log" ),
		);
	}

	protected function seed_footprint(): void {
		global $wpdb;

		$p           = $this->prefix;
		$blog_prefix = $wpdb->get_blog_prefix();

		foreach ( array( "{$p}_settings", "{$p}_cache_a", "{$p}_cache_b", "{$p}_kept", "{$p}_cacheZz", \strtoupper( "{$p}_cache_x" ) ) as $option ) {
			\update_option( $option, 'x' );
		}
		\set_transient( "{$p}_transient", 'x' );
		\set_transient( "{$p}_kept_transient", 'x' );

		foreach ( array( 'publish', 'trash', 'auto-draft' ) as $status ) {
			$this->ids['type'][] = $this->make_post( "{$p}_type", $status );
		}
		$this->ids['foreign_post'] = $this->make_post( "{$p}_other", 'publish' );

		$term = \wp_insert_term( "$p-term", 'category' );
		self::assertIsArray( $term );
		$this->ids['term'] = (int) $term['term_id'];

		$this->ids['comment'] = $this->make_comment( 'comment' );
		$this->ids['note']    = $this->make_comment( "{$p}_note" );

		// WordPress lowercases a post type on insert, so the post type's letter-case twin is written directly.
		$this->ids['upper_post']    = $this->make_post( "{$p}_other", 'publish' );
		$this->ids['upper_comment'] = $this->make_comment( \strtoupper( "{$p}_note" ) );
		$wpdb->update( $wpdb->posts, array( 'post_type' => \strtoupper( "{$p}_type" ) ), array( 'ID' => $this->ids['upper_post'] ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Writes a row the API would not.

		$meta_objects = array(
			'post'    => $this->ids['foreign_post'],
			'term'    => $this->ids['term'],
			'comment' => $this->ids['comment'],
		);
		foreach ( $meta_objects as $type => $object_id ) {
			foreach ( array( \strtoupper( "{$p}_flag" ), \strtoupper( "{$p}_{$type}_a" ), "{$p}_flag", "{$p}_{$type}_a", "{$p}_kept", "{$p}_{$type}Za" ) as $key ) {
				\add_metadata( $type, $object_id, $key, 'x' );
			}
		}

		$this->ids['user'] = $this->make_user( 'acme_uninstall_user', 'subscriber' );
		foreach ( array( \strtoupper( "{$p}_global_state" ), \strtoupper( "$blog_prefix{$p}_site_a" ), "$blog_prefix{$p}_site_state", "$blog_prefix{$p}_site_a", "{$p}_global_state", "{$p}_global_a", "{$p}_site_state", "{$p}_kept", "$blog_prefix{$p}_kept" ) as $key ) {
			\add_user_meta( $this->ids['user'], $key, 'x' );
		}

		\get_role( 'administrator' )->add_cap( "manage_$p" );
		\get_role( 'editor' )->add_cap( "manage_$p" );
		\get_role( 'editor' )->add_cap( "edit_$p" );

		foreach ( array( "{$p}_log", "{$p}_kept" ) as $table ) {
			$wpdb->query( "CREATE TABLE `$wpdb->prefix$table` ( id INT )" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Creates the test's own tables.
		}
	}

	protected function footprint_state(): array {
		global $wpdb;

		$p           = $this->prefix;
		$blog_prefix = $wpdb->get_blog_prefix();

		return array(
			'options'      => \array_map( $this->option_exists( ... ), array( "{$p}_settings", "{$p}_cache_a", "{$p}_cache_b" ) ),
			'transient'    => false !== \get_transient( "{$p}_transient" ),
			'posts'        => \count( $this->get_posts_of_type( "{$p}_type" ) ),
			'meta'         => array(
				$this->meta_exists( 'post', "{$p}_flag" ),
				$this->meta_exists( 'post', "{$p}_post_a" ),
				$this->meta_exists( 'term', "{$p}_flag" ),
				$this->meta_exists( 'term', "{$p}_term_a" ),
				$this->meta_exists( 'comment', "{$p}_flag" ),
				$this->meta_exists( 'comment', "{$p}_comment_a" ),
			),
			'comment'      => $this->comment_exists( $this->ids['note'] ),
			'user_meta'    => \array_map( $this->user_meta_exists( ... ), array( "$blog_prefix{$p}_site_state", "$blog_prefix{$p}_site_a", "{$p}_global_state", "{$p}_global_a" ) ),
			'capabilities' => array( $this->role_has( 'administrator', "manage_$p" ), $this->role_has( 'editor', "manage_$p" ) ),
			'table'        => $this->table_exists( "{$p}_log" ),
		);
	}

	protected function assert_footprint_kept(): void {
		self::assertSame(
			array(
				'options'      => array( true, true, true ),
				'transient'    => true,
				'posts'        => 3,
				'meta'         => array( true, true, true, true, true, true ),
				'comment'      => true,
				'user_meta'    => array( true, true, true, true ),
				'capabilities' => array( true, true ),
				'table'        => true,
			),
			$this->footprint_state()
		);
	}

	protected function assert_footprint_deleted(): void {
		self::assertSame(
			array(
				'options'      => array( false, false, false ),
				'transient'    => false,
				'posts'        => 0,
				'meta'         => array( false, false, false, false, false, false ),
				'comment'      => false,
				'user_meta'    => array( false, false, false, false ),
				'capabilities' => array( false, false ),
				'table'        => false,
			),
			$this->footprint_state()
		);
	}

	protected function assert_foreign_rows_kept(): void {
		global $wpdb;

		$p           = $this->prefix;
		$blog_prefix = $wpdb->get_blog_prefix();

		self::assertSame(
			array(
				'options'      => array( true, true, true ),
				'transient'    => true,
				'posts'        => array( true, true ),
				'meta'         => array( true, true, true, true, true, true, true, true, true, true, true, true ),
				'comment'      => array( true, true ),
				'user_meta'    => array( true, true, true, true, true ),
				'capabilities' => array( true, true ),
				'table'        => true,
			),
			array(
				'options'      => \array_map( $this->option_exists( ... ), array( "{$p}_kept", "{$p}_cacheZz", \strtoupper( "{$p}_cache_x" ) ) ),
				'transient'    => false !== \get_transient( "{$p}_kept_transient" ),
				'posts'        => array( $this->post_exists( $this->ids['foreign_post'] ), $this->post_exists( $this->ids['upper_post'] ) ),
				'meta'         => array(
					$this->meta_exists( 'post', "{$p}_kept" ),
					$this->meta_exists( 'post', "{$p}_postZa" ),
					$this->meta_exists( 'term', "{$p}_kept" ),
					$this->meta_exists( 'term', "{$p}_termZa" ),
					$this->meta_exists( 'comment', "{$p}_kept" ),
					$this->meta_exists( 'comment', "{$p}_commentZa" ),
					$this->meta_exists( 'post', \strtoupper( "{$p}_flag" ) ),
					$this->meta_exists( 'post', \strtoupper( "{$p}_post_a" ) ),
					$this->meta_exists( 'term', \strtoupper( "{$p}_flag" ) ),
					$this->meta_exists( 'term', \strtoupper( "{$p}_term_a" ) ),
					$this->meta_exists( 'comment', \strtoupper( "{$p}_flag" ) ),
					$this->meta_exists( 'comment', \strtoupper( "{$p}_comment_a" ) ),
				),
				'comment'      => array( $this->comment_exists( $this->ids['comment'] ), $this->comment_exists( $this->ids['upper_comment'] ) ),
				'user_meta'    => \array_map( $this->user_meta_exists( ... ), array( "{$p}_site_state", "{$p}_kept", "$blog_prefix{$p}_kept", \strtoupper( "{$p}_global_state" ), \strtoupper( "$blog_prefix{$p}_site_a" ) ) ),
				'capabilities' => array( $this->role_has( 'administrator', 'manage_options' ), $this->role_has( 'editor', "edit_$p" ) ),
				'table'        => $this->table_exists( "{$p}_kept" ),
			)
		);
	}

	protected function recording_cleanup( bool $throws = false ): array {
		return array(
			'cleanup' => function ( array $context ) use ( $throws ): void {
				$this->calls[] = array( 'cleanup', $context );
				if ( $throws ) {
					throw new \RuntimeException( 'The cleanup failed.' );
				}
			},
		);
	}

	protected function capture_error_log( \Closure $run ): array {
		$log_file = (string) \tempnam( \sys_get_temp_dir(), 'acme-uninstall-' );
		$previous = \ini_set( 'error_log', $log_file );
		try {
			$run();
			$lines = \file( $log_file, \FILE_IGNORE_NEW_LINES );
		} finally {
			\ini_set( 'error_log', (string) $previous );
			\wp_delete_file( $log_file );
		}

		return (array) $lines;
	}

	protected function option_exists( string $name ): bool {
		global $wpdb;

		return '0' !== $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE BINARY option_name = %s', $wpdb->options, $name ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Reads past the options cache.
	}

	protected function user_meta_exists( string $key ): bool {
		global $wpdb;

		return '0' !== $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE BINARY meta_key = %s', $wpdb->usermeta, $key ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Reads past the meta cache.
	}

	protected function meta_exists( string $type, string $key ): bool {
		global $wpdb;

		return '0' !== $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE BINARY meta_key = %s', \_get_meta_table( $type ), $key ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Reads past the meta cache.
	}

	protected function post_exists( int $id ): bool {
		global $wpdb;

		return '0' !== $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE ID = %d', $wpdb->posts, $id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Reads past the post cache.
	}

	protected function comment_exists( int $id ): bool {
		global $wpdb;

		return '0' !== $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE comment_ID = %d', $wpdb->comments, $id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Reads past the comment cache.
	}

	protected function get_posts_of_type( string $type ): array {
		global $wpdb;

		return $wpdb->get_col( $wpdb->prepare( 'SELECT ID FROM %i WHERE BINARY post_type = %s', $wpdb->posts, $type ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Counts every status, trash and auto-draft included.
	}

	protected function post_type_has_posts( string $type ): bool {
		return array() !== $this->get_posts_of_type( $type );
	}

	protected function table_exists( string $name ): bool {
		global $wpdb;

		return null !== $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $wpdb->prefix . $name ) ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Schema read.
	}

	protected function role_has( string $role, string $capability ): bool {
		global $wpdb;

		$roles = \get_option( $wpdb->get_blog_prefix() . 'user_roles' );

		return ! empty( $roles[ $role ]['capabilities'][ $capability ] );
	}

	protected function make_post( string $type, string $status ): int {
		$id = \wp_insert_post(
			array(
				'post_type'   => $type,
				'post_status' => $status,
				'post_title'  => 'Acme',
			)
		);
		self::assertIsInt( $id );
		self::assertGreaterThan( 0, $id );

		return $id;
	}

	protected function make_comment( string $type ): int {
		$id = \wp_insert_comment(
			array(
				'comment_post_ID' => $this->ids['foreign_post'],
				'comment_type'    => $type,
				'comment_content' => 'Acme',
			)
		);
		self::assertIsInt( $id );

		return $id;
	}

	#[After]
	protected function delete_uninstall_fixtures(): void {
		global $wpdb;

		$p = $this->prefix;
		foreach ( \array_merge( $this->ids['type'] ?? array(), array( $this->ids['foreign_post'] ?? 0, $this->ids['upper_post'] ?? 0 ) ) as $id ) {
			if ( 0 < $id ) {
				\wp_delete_post( $id, true );
			}
		}
		if ( isset( $this->ids['term'] ) ) {
			\wp_delete_term( $this->ids['term'], 'category' );
		}
		foreach ( array( 'administrator', 'editor' ) as $role ) {
			\get_role( $role )->remove_cap( "manage_$p" );
			\get_role( $role )->remove_cap( "edit_$p" );
		}
		foreach ( array( "{$p}_log", "{$p}_kept" ) as $table ) {
			$wpdb->query( "DROP TABLE IF EXISTS `$wpdb->prefix$table`" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.SchemaChange -- Drops the test's own tables.
		}
		\delete_transient( "{$p}_transient" );
		\delete_transient( "{$p}_kept_transient" );
		foreach ( array( "{$p}_cron", "{$p}_foreign_cron" ) as $hook ) {
			\wp_unschedule_hook( $hook );
		}
		if ( \function_exists( 'as_unschedule_all_actions' ) ) {
			foreach ( array( "{$p}_as", "{$p}_grouped", "{$p}_foreign_as" ) as $hook ) {
				\as_unschedule_all_actions( $hook );
			}
		}
		$this->ids   = array();
		$this->calls = array();
	}
}
