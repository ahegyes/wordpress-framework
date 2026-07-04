<?php declare( strict_types=1 );

namespace DeepWebSolutions\Framework\Settings\Tests\Unit;

use DeepWebSolutions\Framework\Settings\Schema\Exceptions\InvalidSettingsOptionsException;
use DeepWebSolutions\Framework\Settings\Schema\Options\OptionsResolver;
use DeepWebSolutions\Framework\Settings\Schema\Options\SettingsOptionsProviderInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass( OptionsResolver::class )]
final class OptionsResolverTest extends TestCase {
	public function test_resolves_an_array_source_as_is(): void {
		$result = ( new OptionsResolver() )->resolve(
			array(
				'a' => 'A',
				'b' => 'B',
			)
		);

		self::assertSame(
			array(
				'a' => 'A',
				'b' => 'B',
			),
			$result
		);
	}

	public function test_resolves_a_closure_source(): void {
		$result = ( new OptionsResolver() )->resolve( static fn (): array => array( 'a' => 'A' ) );

		self::assertSame( array( 'a' => 'A' ), $result );
	}

	public function test_resolves_a_provider_source(): void {
		$provider = new class() implements SettingsOptionsProviderInterface {
			public function get_options(): array {
				return array( 'a' => 'A' );
			}
		};

		$result = ( new OptionsResolver() )->resolve( $provider );

		self::assertSame( array( 'a' => 'A' ), $result );
	}

	public function test_all_three_sources_yield_the_same_resolved_set(): void {
		$expected = array(
			'x' => 'X',
			'y' => 'Y',
		);
		$provider = new class() implements SettingsOptionsProviderInterface {
			public function get_options(): array {
				return array(
					'x' => 'X',
					'y' => 'Y',
				);
			}
		};
		$resolver = new OptionsResolver();

		self::assertSame( $expected, $resolver->resolve( $expected ) );
		self::assertSame(
			$expected,
			$resolver->resolve(
				static fn (): array => array(
					'x' => 'X',
					'y' => 'Y',
				)
			)
		);
		self::assertSame( $expected, $resolver->resolve( $provider ) );
	}

	public function test_a_closure_returning_a_non_array_throws(): void {
		$this->expectException( InvalidSettingsOptionsException::class );

		( new OptionsResolver() )->resolve( static fn (): mixed => 'not-an-array' );
	}
}
