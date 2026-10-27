<?php declare( strict_types=1 );

namespace DeepWebSolutions\Framework\Tests\Unit;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversNothing]
final class PackageReadmesTest extends TestCase {
	protected const string ROOT = __DIR__ . '/../..';

	public static function packages(): array {
		return array(
			'bootstrap'   => array( 'bootstrap' ),
			'shared'      => array( 'shared' ),
			'core'        => array( 'core' ),
			'settings'    => array( 'settings' ),
			'woocommerce' => array( 'woocommerce' ),
		);
	}

	#[DataProvider( 'packages' )]
	public function test_every_php_example_is_copied_from_the_package_s_persona( string $package ): void {
		$examples = self::fences( $package, 'php' );
		self::assertNotEmpty( $examples, "The $package README shows no PHP example." );

		$sources = array();
		$files   = new \RecursiveIteratorIterator( new \RecursiveDirectoryIterator( self::ROOT . "/tests/Fixtures/personas/$package", \FilesystemIterator::SKIP_DOTS ) );
		foreach ( new \RegexIterator( $files, '#^(?!.*/vendor(-prefixed)?/).*\.php$#' ) as $file ) {
			$sources[] = \explode( "\n", (string) \file_get_contents( $file->getPathname() ) );
		}

		foreach ( $examples as $example ) {
			// A line that reads "// ..." elides persona code between two excerpts.
			foreach ( \preg_split( '#^\s*// \.\.\.\n#m', $example ) as $excerpt ) {
				$excerpt = \explode( "\n", \trim( $excerpt, "\n" ) );
				self::assertNotEmpty( \array_filter( $sources, static fn ( array $source ): bool => self::contains( $source, $excerpt ) ), "The $package persona holds no copy of:\n" . \implode( "\n", $excerpt ) );
			}
		}
	}

	#[DataProvider( 'packages' )]
	public function test_every_json_example_is_part_of_the_package_s_persona_manifest( string $package ): void {
		$examples = self::fences( $package, 'json' );
		self::assertNotEmpty( $examples, "The $package README shows no JSON example." );

		$manifest = self::read_json( "tests/Fixtures/personas/$package/composer.json" );
		foreach ( $examples as $example ) {
			self::assertTrue( self::is_part_of( \json_decode( $example, true, flags: \JSON_THROW_ON_ERROR ), $manifest ), "The $package persona's composer.json does not hold:\n$example" );
		}
	}

	#[DataProvider( 'packages' )]
	public function test_the_readme_lists_one_mirror_for_each_package_of_its_stack( string $package ): void {
		$mirrors = array();
		foreach ( self::fences( $package, 'json' ) as $example ) {
			foreach ( \json_decode( $example, true, flags: \JSON_THROW_ON_ERROR )['repositories'] ?? array() as $repository ) {
				if ( \str_starts_with( $repository['url'], 'https://github.com/ahegyes/wp-framework-' ) ) {
					$mirrors[] = $repository['url'];
				}
			}
		}

		self::assertSame( \array_map( static fn ( string $name ): string => "https://github.com/ahegyes/wp-framework-$name", self::stack( $package ) ), $mirrors );
	}

	#[DataProvider( 'packages' )]
	public function test_the_readme_states_the_contract_and_its_versioning_as_agents_md_does( string $package ): void {
		\preg_match( '/^## Contract\n\n(.*?)\n\n## /ms', (string) \file_get_contents( self::ROOT . '/AGENTS.md' ), $section );
		$paragraphs = \explode( "\n\n", $section[1] ?? '' );
		self::assertCount( 3, $paragraphs );

		$readme = (string) \file_get_contents( self::ROOT . "/packages/$package/README.md" );
		self::assertStringContainsString( $paragraphs[0], $readme );
		self::assertStringContainsString( $paragraphs[2], $readme );
	}

	public function test_a_reindented_excerpt_is_a_copy_and_a_changed_one_is_not(): void {
		$source = array( 'function acme(): void {', "\tif ( true ) {", "\t\treturn;", "\t}", '', "\techo 'acme';", '}' );

		self::assertTrue( self::contains( $source, array( 'if ( true ) {', "\treturn;", '}', '', "echo 'acme';" ) ) );
		self::assertFalse( self::contains( $source, array( 'if ( true ) {', 'return;' ) ) );
		self::assertFalse( self::contains( $source, array( "\treturn;", '}', "echo 'acme';" ) ) );
	}

	public function test_a_json_example_is_part_of_a_manifest_only_where_every_value_matches(): void {
		$manifest = \json_decode( '{"require-dev": {"acme/a": "^1.0", "acme/b": "^2.0"}, "repositories": [{"type": "path", "url": "a"}, {"type": "vcs", "url": "b"}]}', true );

		self::assertTrue( self::is_part_of( \json_decode( '{"repositories": [{"type": "vcs", "url": "b"}], "require-dev": {"acme/b": "^2.0"}}', true ), $manifest ) );
		self::assertTrue( self::is_part_of( \json_decode( '{"repositories": [{"url": "b", "type": "vcs"}]}', true ), $manifest ) );
		self::assertFalse( self::is_part_of( \json_decode( '{"repositories": [{"type": "vcs"}]}', true ), $manifest ) );
		self::assertFalse( self::is_part_of( \json_decode( '{"require-dev": {"acme/b": "^2.1"}}', true ), $manifest ) );
		self::assertFalse( self::is_part_of( \json_decode( '{"repositories": [{"type": "vcs", "url": "c"}]}', true ), $manifest ) );
		self::assertFalse( self::is_part_of( \json_decode( '{"prefer-stable": true}', true ), $manifest ) );
	}

	protected static function fences( string $package, string $language ): array {
		\preg_match_all( "/^```$language\n(.*?)^```$/ms", (string) \file_get_contents( self::ROOT . "/packages/$package/README.md" ), $matches );

		return $matches[1];
	}

	// The excerpt matches lines of the source at any one indentation, so an excerpt from inside a class or function reads at the margin.
	protected static function contains( array $source, array $excerpt ): bool {
		foreach ( \array_keys( $source ) as $start ) {
			$indent = \substr( $source[ $start ], 0, \strlen( $source[ $start ] ) - \strlen( $excerpt[0] ) );
			if ( '' !== \ltrim( $indent ) || $indent . $excerpt[0] !== $source[ $start ] ) {
				continue;
			}

			foreach ( $excerpt as $offset => $line ) {
				if ( ( '' === $line ? '' : $indent . $line ) !== ( $source[ $start + $offset ] ?? null ) ) {
					continue 2;
				}
			}

			return true;
		}

		return false;
	}

	protected static function is_part_of( mixed $part, mixed $whole ): bool {
		if ( ! \is_array( $part ) || ! \is_array( $whole ) ) {
			return $part === $whole;
		}

		foreach ( $part as $key => $value ) {
			$found = \array_is_list( $part )
				? \array_any( $whole, static fn ( mixed $item ): bool => self::is_part_of( $value, $item ) && self::is_part_of( $item, $value ) )
				: \array_key_exists( $key, $whole ) && self::is_part_of( $value, $whole[ $key ] );
			if ( ! $found ) {
				return false;
			}
		}

		return true;
	}

	protected static function stack( string $package ): array {
		$stack = array();
		foreach ( \array_keys( self::read_json( "packages/$package/composer.json" )['require'] ) as $name ) {
			if ( \str_starts_with( $name, 'ahegyes/wp-framework-' ) ) {
				$stack = \array_merge( $stack, self::stack( \substr( $name, \strlen( 'ahegyes/wp-framework-' ) ) ) );
			}
		}

		return \array_values( \array_unique( \array_merge( $stack, array( $package ) ) ) );
	}

	protected static function read_json( string $path ): array {
		return \json_decode( (string) \file_get_contents( self::ROOT . "/$path" ), true, flags: \JSON_THROW_ON_ERROR );
	}
}
