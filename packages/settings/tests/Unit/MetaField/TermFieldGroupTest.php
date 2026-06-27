<?php declare( strict_types=1 );

namespace DeepWebSolutions\Framework\Settings\Tests\Unit\MetaField;

use DeepWebSolutions\Framework\Settings\MetaField\Exceptions\InvalidTermFieldGroupException;
use DeepWebSolutions\Framework\Settings\MetaField\ValueObjects\FieldGroup;
use DeepWebSolutions\Framework\Settings\MetaField\ValueObjects\TermFieldGroup;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass( TermFieldGroup::class )]
#[UsesClass( FieldGroup::class )]
final class TermFieldGroupTest extends TestCase {
	public function test_it_wraps_a_field_group_and_a_taxonomy(): void {
		$group = $this->group();
		$term  = new TermFieldGroup( group: $group, taxonomy: 'product_cat' );

		self::assertSame( $group, $term->group );
		self::assertSame( 'product_cat', $term->taxonomy );
	}

	public function test_a_taxonomy_may_lead_with_a_digit(): void {
		// WordPress taxonomy keys, unlike settings ids, need not begin with a letter.
		$term = new TermFieldGroup( group: $this->group(), taxonomy: '2024_archive' );

		self::assertSame( '2024_archive', $term->taxonomy );
	}

	public function test_a_taxonomy_outside_the_charset_throws(): void {
		$this->expectException( InvalidTermFieldGroupException::class );

		// The taxonomy is interpolated into term hook names; it must stay within the taxonomy-key charset.
		new TermFieldGroup( group: $this->group(), taxonomy: 'Bad Taxonomy!' );
	}

	public function test_a_taxonomy_longer_than_32_characters_throws(): void {
		$this->expectException( InvalidTermFieldGroupException::class );

		new TermFieldGroup( group: $this->group(), taxonomy: \str_repeat( 'a', 33 ) );
	}

	private function group(): FieldGroup {
		return new FieldGroup(
			id: 'meta',
			title: 'Category Meta',
			fields_provider: static fn ( int $object_id ): array => array(),
		);
	}
}
