<?php declare( strict_types=1 );

namespace DeepWebSolutions\Framework\Tests\Support;

trait CopiesFixturesTrait {
	protected const string FIXTURES = __DIR__ . '/../Fixtures';

	// Copies a built fixture plugin into the target directory, then applies the removals and header values a partial deploy or a floor test needs, and returns the name of its entry file, the build's one acme-*.php.
	protected static function copy_fixture( string $fixture, string $target, array $remove = array(), array $headers = array() ): string {
		$build = self::FIXTURES . "/$fixture";
		self::assertFileExists( "$build/vendor-prefixed/scoper-autoload.php", "Build $fixture first with `composer fixtures:build`." );
		self::assertFileDoesNotExist( "$build/vendor/humbug", "$fixture holds a development install; `composer fixtures:build` ends with its production install." );
		$entries = (array) \glob( "$build/acme-*.php" );
		self::assertCount( 1, $entries, "$fixture has no single acme-*.php entry file." );
		$entry = \basename( (string) $entries[0] );

		self::remove_tree( $target );
		\mkdir( $target, 0777, true ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_mkdir -- Builds the copy directly.
		$items = new \RecursiveIteratorIterator( new \RecursiveDirectoryIterator( $build, \FilesystemIterator::SKIP_DOTS ), \RecursiveIteratorIterator::SELF_FIRST );
		foreach ( $items as $item ) {
			$path = $target . '/' . $items->getSubPathname();
			$item->isDir() ? \mkdir( $path, 0777, true ) : \copy( $item->getPathname(), $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_mkdir -- Builds the copy directly.
		}

		foreach ( $remove as $relative ) {
			self::assertFileExists( "$target/$relative" );
			\unlink( "$target/$relative" ); // phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink -- Removes a file the deploy lost.
		}

		foreach ( $headers as $name => $value ) {
			$contents = (string) \preg_replace( '/^( \* ' . \preg_quote( $name, '/' ) . ':\s+).*$/m', '${1}' . $value, (string) \file_get_contents( "$target/$entry" ), 1, $count );
			self::assertSame( 1, $count, "$fixture declares no '$name' header." );
			\file_put_contents( "$target/$entry", $contents ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- Edits the copy directly.
		}

		return $entry;
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
