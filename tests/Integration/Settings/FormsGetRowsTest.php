<?php declare( strict_types=1 );

namespace DeepWebSolutions\Framework\Tests\Integration\Settings;

use DeepWebSolutions\Framework\Settings\Forms;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass( Forms::class )]
final class FormsGetRowsTest extends TestCase {
	public function test_each_field_with_an_id_gets_a_row_named_under_the_prefix(): void {
		$rows = Forms::get_rows(
			array(
				array(
					'type'  => 'text',
					'id'    => 'name',
					'title' => 'Name',
				),
				array( 'type' => 'text' ),
				array(
					'type' => 'radio',
					'id'   => 'color',
				),
			),
			array(),
			'acme_details'
		);

		self::assertSame(
			array(
				array(
					'field'     => array(
						'type'  => 'text',
						'id'    => 'name',
						'title' => 'Name',
					),
					'value'     => '',
					'name'      => 'acme_details[name]',
					'id'        => 'acme_details.name',
					'label_for' => 'acme_details.name',
				),
				array(
					'field'     => array(
						'type'  => 'radio',
						'id'    => 'color',
						'title' => 'color',
					),
					'value'     => '',
					'name'      => 'acme_details[color]',
					'id'        => 'acme_details.color',
					'label_for' => null,
				),
			),
			$rows
		);
	}

	public function test_a_row_carries_the_stored_value_or_else_the_default(): void {
		$fields = array(
			array(
				'type'    => 'text',
				'id'      => 'stored',
				'default' => 'Default.',
			),
			array(
				'type'    => 'text',
				'id'      => 'empty',
				'default' => 'Default.',
			),
			array(
				'type'    => 'text',
				'id'      => 'absent',
				'default' => 'Default.',
			),
		);

		$rows = Forms::get_rows(
			$fields,
			array(
				'stored' => 'Stored.',
				'empty'  => '',
			),
			'acme'
		);

		self::assertSame( array( 'Stored.', '', 'Default.' ), \array_column( $rows, 'value' ) );
	}
}
