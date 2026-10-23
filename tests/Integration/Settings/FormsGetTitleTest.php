<?php declare( strict_types=1 );

namespace DeepWebSolutions\Framework\Tests\Integration\Settings;

use DeepWebSolutions\Framework\Settings\Forms;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass( Forms::class )]
final class FormsGetTitleTest extends TestCase {
	public static function fields(): array {
		return array(
			'titled'      => array(
				array(
					'type'  => 'text',
					'id'    => 'acme_name',
					'title' => 'Name',
				),
				'Name',
			),
			'empty-title' => array(
				array(
					'type'  => 'text',
					'id'    => 'acme_name',
					'title' => '',
				),
				'acme_name',
			),
			'untitled'    => array(
				array(
					'type' => 'text',
					'id'   => 'acme_name',
				),
				'acme_name',
			),
			'no-id'       => array( array( 'type' => 'text' ), '' ),
		);
	}

	#[DataProvider( 'fields' )]
	public function test_a_field_is_named_by_its_title_or_else_its_id( array $field, string $title ): void {
		self::assertSame( $title, Forms::get_title( $field ) );
	}
}
