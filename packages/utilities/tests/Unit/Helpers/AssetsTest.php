<?php declare( strict_types=1 );

namespace DeepWebSolutions\Framework\Utilities\Tests\Unit\Helpers;

use DeepWebSolutions\Framework\Utilities\Helpers\Assets;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;

#[CoversClass( Assets::class )]
final class AssetsTest extends TestCase {
	protected string $directory;

	protected function setUp(): void {
		parent::setUp();

		$directory = \sys_get_temp_dir() . '/dws-assets-' . \bin2hex( \random_bytes( 8 ) );
		\mkdir( $directory );

		$this->directory = $directory;
	}

	protected function tearDown(): void {
		foreach ( \glob( $this->directory . '/*' ) ?: array() as $file ) {
			\unlink( $file );
		}

		\rmdir( $this->directory );

		parent::tearDown();
	}

	public function test_minified_path_returns_the_minified_variant_when_script_debug_is_off_and_the_file_exists(): void {
		$path     = $this->directory . '/app.js';
		$min_path = $this->directory . '/app.min.js';
		\touch( $path );
		\touch( $min_path );

		self::assertSame( $min_path, Assets::minified_path( $path ) );
	}

	public function test_minified_path_returns_the_original_path_when_the_minified_variant_is_missing(): void {
		$path = $this->directory . '/app.js';
		\touch( $path );

		self::assertSame( $path, Assets::minified_path( $path ) );
	}

	#[RunInSeparateProcess]
	public function test_minified_path_returns_the_original_path_when_script_debug_is_on(): void {
		\define( 'SCRIPT_DEBUG', true );

		$path     = $this->directory . '/app.js';
		$min_path = $this->directory . '/app.min.js';
		\touch( $path );
		\touch( $min_path );

		self::assertSame( $path, Assets::minified_path( $path ) );
	}

	public function test_minified_path_does_not_add_a_second_min_suffix(): void {
		$path = $this->directory . '/app.min.js';
		\touch( $path );

		self::assertSame( $path, Assets::minified_path( $path ) );
	}

	public function test_version_returns_the_file_modified_time(): void {
		$path  = $this->directory . '/app.js';
		$mtime = 1_704_067_200;
		\touch( $path, $mtime );

		self::assertSame( (string) $mtime, Assets::version( $path, '2.0.0' ) );
	}

	public function test_version_returns_the_fallback_when_the_file_is_missing(): void {
		self::assertSame( '2.0.0', Assets::version( $this->directory . '/missing.js', '2.0.0' ) );
	}
}
