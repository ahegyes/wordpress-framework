<?php declare( strict_types=1 );

namespace DeepWebSolutions\Framework\Utilities\Tests\Unit\AdminNotices\ValueObjects;

use DeepWebSolutions\Framework\Core\Conditional\ConditionalInterface;
use DeepWebSolutions\Framework\Utilities\AdminNotices\Exceptions\InvalidAdminNoticeException;
use DeepWebSolutions\Framework\Utilities\AdminNotices\ValueObjects\DependencyRequirement;
use DeepWebSolutions\Framework\Utilities\AdminNotices\NoticeType;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\Attributes\UsesFunction;
use PHPUnit\Framework\TestCase;

#[CoversClass( DependencyRequirement::class )]
#[UsesClass( NoticeType::class )]
#[UsesFunction( 'DeepWebSolutions\Framework\Utilities\AdminNotices\is_valid_notice_id' )]
final class DependencyRequirementTest extends TestCase {
	public function test_constructs_with_defaults(): void {
		$requirement = new DependencyRequirement( $this->conditional( false ), 'WooCommerce' );

		self::assertSame( 'WooCommerce', $requirement->label );
		self::assertTrue( $requirement->required );
		self::assertNull( $requirement->id );
	}

	public function test_get_notice_id_derives_from_the_label(): void {
		$requirement = new DependencyRequirement( $this->conditional( false ), 'WooCommerce' );

		self::assertMatchesRegularExpression( '/\Adep_woocommerce_[a-f0-9]{12}\z/', $requirement->get_notice_id() );
	}

	public function test_get_notice_id_slugs_a_multi_word_label(): void {
		$requirement = new DependencyRequirement( $this->conditional( false ), 'the cURL PHP extension' );

		self::assertMatchesRegularExpression( '/\Adep_the_curl_php_extension_[a-f0-9]{12}\z/', $requirement->get_notice_id() );
	}

	public function test_get_notice_id_uses_an_explicit_id_when_given(): void {
		$requirement = new DependencyRequirement( $this->conditional( false ), 'WooCommerce', id: 'dws_lowc_dep_wc' );

		self::assertSame( 'dws_lowc_dep_wc', $requirement->get_notice_id() );
	}

	public function test_an_explicit_unstable_id_is_rejected_at_construction(): void {
		$this->expectException( InvalidAdminNoticeException::class );

		new DependencyRequirement( $this->conditional( false ), 'WooCommerce', id: 'Bad.Id' );
	}

	public function test_degenerate_labels_still_derive_distinct_valid_notice_ids(): void {
		$first  = new DependencyRequirement( $this->conditional( false ), '!!!' );
		$second = new DependencyRequirement( $this->conditional( false ), '???' );

		self::assertMatchesRegularExpression( '/\Adep_[a-f0-9]{12}\z/', $first->get_notice_id() );
		self::assertMatchesRegularExpression( '/\Adep_[a-f0-9]{12}\z/', $second->get_notice_id() );
		self::assertNotSame( $first->get_notice_id(), $second->get_notice_id() );
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
