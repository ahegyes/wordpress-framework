<?php declare( strict_types=1 );

namespace DeepWebSolutions\Framework\Tests\Integration\Support;

use PHPUnit\Framework\Attributes\After;

trait InstallsConsumersTrait {
	use RunsAdminRequestsTrait;

	protected const string FIXTURES = __DIR__ . '/../../Fixtures';

	protected array $consumer_copies = array();

	// Copies a built consumer fixture under WP_PLUGIN_DIR, where WordPress core re-reads a plugin's headers, then applies the removals and header values a partial deploy or a floor test needs.
	protected function install_consumer( string $fixture, string $dir, array $remove = array(), array $headers = array() ): string {
		$build = self::FIXTURES . "/$fixture";
		self::assertFileExists( "$build/vendor-prefixed/scoper-autoload.php", "Build $fixture first with `composer fixtures:build`." );
		self::assertFileDoesNotExist( "$build/vendor/humbug", "$fixture holds a development install; `composer fixtures:build` ends with its production install." );

		$target                  = WP_PLUGIN_DIR . "/$dir";
		$this->consumer_copies[] = $target;
		self::remove_tree( $target );
		\mkdir( $target ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_mkdir -- Builds the copy directly.
		$items = new \RecursiveIteratorIterator( new \RecursiveDirectoryIterator( $build, \FilesystemIterator::SKIP_DOTS ), \RecursiveIteratorIterator::SELF_FIRST );
		foreach ( $items as $item ) {
			$path = $target . '/' . $items->getSubPathname();
			$item->isDir() ? \mkdir( $path, 0777, true ) : \copy( $item->getPathname(), $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_mkdir -- Builds the copy directly.
		}

		foreach ( $remove as $relative ) {
			self::assertFileExists( "$target/$relative" );
			\unlink( "$target/$relative" ); // phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink -- Removes a file the deploy lost.
		}

		$entry = "$target/acme-$fixture.php";
		foreach ( $headers as $name => $value ) {
			$contents = (string) \preg_replace( '/^( \* ' . \preg_quote( $name, '/' ) . ':\s+).*$/m', '${1}' . $value, (string) \file_get_contents( $entry ), 1, $count );
			self::assertSame( 1, $count, "$fixture declares no '$name' header." );
			\file_put_contents( $entry, $contents ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- Edits the copy directly.
		}
		wp_clean_plugins_cache();

		return "$dir/acme-$fixture.php";
	}

	protected function consumer_request( string $method, string $path, int $user_id, array $spec, array $post = array() ): array {
		return $this->admin_request( $method, $path, $user_id, __DIR__ . '/consumer-request.php', $spec, $post );
	}

	#[After]
	protected function remove_consumers(): void {
		global $wpdb;

		foreach ( $this->consumer_copies as $copy ) {
			self::remove_tree( $copy );
		}
		$this->consumer_copies = array();

		foreach ( is_multisite() ? get_sites( array( 'fields' => 'ids' ) ) : array( 1 ) as $site_id ) {
			$wpdb->query( $wpdb->prepare( 'DELETE FROM %i WHERE option_name LIKE %s OR option_name LIKE %s OR option_name LIKE %s', $wpdb->get_blog_prefix( $site_id ) . 'options', 'acme\_ca\_%', '\_transient\_acme\_ca\_%', '\_transient\_timeout\_acme\_ca\_%' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Removes the rows consumer-a stored.
		}
		$wpdb->query( $wpdb->prepare( 'DELETE FROM %i WHERE meta_key LIKE %s', $wpdb->postmeta, '\_acme\_ca\_%' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Removes the rows consumer-a stored.
		wp_clean_plugins_cache();
		wp_cache_flush();
	}

	protected static function remove_tree( string $path ): void {
		if ( ! \is_dir( $path ) ) {
			return;
		}

		$items = new \RecursiveIteratorIterator( new \RecursiveDirectoryIterator( $path, \FilesystemIterator::SKIP_DOTS ), \RecursiveIteratorIterator::CHILD_FIRST );
		foreach ( $items as $item ) {
			$item->isDir() ? \rmdir( $item->getPathname() ) : \unlink( $item->getPathname() ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_rmdir, WordPress.WP.AlternativeFunctions.unlink_unlink -- Removes the copy directly.
		}
		\rmdir( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_rmdir -- Removes the copy directly.
	}
}
