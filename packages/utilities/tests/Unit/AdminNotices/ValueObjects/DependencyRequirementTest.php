<?php declare( strict_types=1 );

namespace DeepWebSolutions\Framework\Utilities\Tests\Unit\AdminNotices\ValueObjects;

use DeepWebSolutions\Framework\Core\Conditional\ConditionalInterface;
use DeepWebSolutions\Framework\Utilities\AdminNotices\ValueObjects\DependencyRequirement;
use DeepWebSolutions\Framework\Utilities\AdminNotices\ValueObjects\NoticeType;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass( DependencyRequirement::class )]
#[UsesClass( NoticeType::class )]
final class DependencyRequirementTest extends TestCase {
	public function test_constructs_with_defaults(): void {
		$requirement = new DependencyRequirement( $this->conditional( false ), 'WooCommerce' );

		self::assertSame( 'WooCommerce', $requirement->label );
		self::assertTrue( $requirement->required );
		self::assertNull( $requirement->id );
	}

	public function test_get_notice_id_derives_from_the_label(): void {
		$requirement = new DependencyRequirement( $this->conditional( false ), 'WooCommerce' );

		self::assertSame( 'dep_woocommerce', $requirement->get_notice_id() );
	}

	public function test_get_notice_id_slugs_a_multi_word_label(): void {
		$requirement = new DependencyRequirement( $this->conditional( false ), 'the cURL PHP extension' );

		self::assertSame( 'dep_the_curl_php_extension', $requirement->get_notice_id() );
	}

	public function test_get_notice_id_uses_an_explicit_id_when_given(): void {
		$requirement = new DependencyRequirement( $this->conditional( false ), 'WooCommerce', id: 'dws_lowc_dep_wc' );

		self::assertSame( 'dws_lowc_dep_wc', $requirement->get_notice_id() );
	}

	public function test_get_notice_id_for_a_degenerate_label_is_the_bare_prefix(): void {
		$requirement = new DependencyRequirement( $this->conditional( false ), '!!!' );

		self::assertSame( 'dep_', $requirement->get_notice_id() );
	}

	public function test_required_requirement_maps_to_a_non_dismissible_error(): void {
		$requirement = new DependencyRequirement( $this->conditional( false ), 'WooCommerce', required: true );

		self::assertSame( NoticeType::Error, $requirement->get_notice_type() );
		self::assertFalse( $requirement->is_dismissible() );
		self::assertFalse( $requirement->is_persistent() );
	}

	public function test_optional_requirement_maps_to_a_dismissible_persistent_warning(): void {
		$requirement = new DependencyRequirement( $this->conditional( false ), 'Jetpack', required: false );

		self::assertSame( NoticeType::Warning, $requirement->get_notice_type() );
		self::assertTrue( $requirement->is_dismissible() );
		self::assertTrue( $requirement->is_persistent() );
	}

	private function conditional( bool $met ): ConditionalInterface {
		return new class( $met ) implements ConditionalInterface {
			public function __construct( private bool $met ) {}

			public function is_met(): bool {
				return $this->met;
			}
		};
	}
}
