<?php declare( strict_types=1 );

namespace DeepWebSolutions\Framework\Tests\Integration\Settings;

use DeepWebSolutions\Framework\Settings\Forms;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass( Forms::class )]
final class FormsRenderControlTest extends TestCase {
	public function test_output_is_escaped(): void {
		$document = self::render(
			'select',
			array(
				'desc'    => 'Help <script>desc</script>',
				'options' => array( 'a"><script>key</script>' => '<script>label</script>' ),
			),
			'"><script>value</script>'
		);

		self::assertNull( $document->querySelector( 'script' ) );
		self::assertSame( 'a"><script>key</script>', $document->querySelector( 'option' )->getAttribute( 'value' ) );
		self::assertSame( '<script>label</script>', self::text( $document->querySelector( 'option' ) ) );
	}

	public function test_stored_values_holding_entities_post_back_unchanged(): void {
		$document = self::render( 'select', array( 'options' => array( 'a&amp;b' => 'A' ) ), 'a&amp;b' );

		self::assertSame( 'Tom &amp; Jerry', self::render( 'text', array(), 'Tom &amp; Jerry' )->getElementById( 'acme.key' )->getAttribute( 'value' ) );
		self::assertSame( 'a&amp;b', $document->querySelector( 'option[selected]' )->getAttribute( 'value' ) );
	}

	public function test_labels_and_help_point_at_ids_holding_entities(): void {
		$radio    = self::render(
			'radio',
			array(
				'desc'    => 'Help.',
				'options' => array( 'a' => 'A' ),
			),
			'',
			'acme_key',
			'acme&amp;key'
		);
		$checkbox = self::render( 'checkbox', array( 'desc' => 'Enable.' ), '', 'acme_key', 'acme&amp;key' );

		self::assertSame( 'acme&amp;key.0', $radio->querySelector( 'label' )->getAttribute( 'for' ) );
		self::assertNotNull( $radio->getElementById( 'acme&amp;key.0' ) );
		self::assertSame( array( 'Help.' ), self::descriptions( $radio, $radio->querySelector( 'fieldset' ) ) );
		self::assertSame( 'acme&amp;key', $checkbox->querySelector( 'label' )->getAttribute( 'for' ) );
		self::assertNotNull( $checkbox->getElementById( 'acme&amp;key' ) );
	}

	public function test_a_textarea_keeps_a_leading_newline(): void {
		self::assertSame( "\nFirst", self::text( self::render( 'textarea', array(), "\nFirst" )->getElementById( 'acme.key' ) ) );
	}

	public function test_a_non_finite_value_renders_as_empty(): void {
		self::assertSame( '', self::render( 'text', array(), \NAN )->getElementById( 'acme.key' )->getAttribute( 'value' ) );
		self::assertSame( '', self::render( 'text', array( 'custom_attributes' => array( 'data-acme' => \NAN ) ) )->getElementById( 'acme.key' )->getAttribute( 'data-acme' ) );
	}

	public function test_a_single_control_carries_the_id_and_prints_under_the_name(): void {
		$input = self::render( 'text', array(), 'stored', 'acme_settings[key]', 'acme.key' )->getElementById( 'acme.key' );

		self::assertTrue( $input->matches( 'input[type="text"]' ) );
		self::assertSame( 'acme_settings[key]', $input->getAttribute( 'name' ) );
		self::assertSame( 'stored', $input->getAttribute( 'value' ) );
	}

	public static function input_types(): array {
		return array(
			'text'   => array( 'text' ),
			'number' => array( 'number' ),
			'email'  => array( 'email' ),
			'url'    => array( 'url' ),
		);
	}

	#[DataProvider( 'input_types' )]
	public function test_each_input_type_renders_as_that_html_input_type( string $type ): void {
		self::assertSame( $type, self::render( $type )->getElementById( 'acme.key' )->getAttribute( 'type' ) );
	}

	public function test_help_text_is_linked_from_the_control_by_aria_describedby(): void {
		$document = self::render( 'text', array( 'desc' => 'Shown below.' ) );

		self::assertSame( 'acme.key.description', $document->getElementById( 'acme.key' )->getAttribute( 'aria-describedby' ) );
		self::assertTrue( $document->getElementById( 'acme.key.description' )->matches( 'p.description' ) );
		self::assertSame( 'Shown below.', self::text( $document->getElementById( 'acme.key.description' ) ) );
	}

	public function test_a_desc_and_a_string_desc_tip_each_get_their_own_description(): void {
		$document = self::render(
			'text',
			array(
				'desc'     => 'Description.',
				'desc_tip' => 'Tip.',
			)
		);

		self::assertSame( array( 'Description.', 'Tip.' ), self::descriptions( $document, $document->getElementById( 'acme.key' ) ) );
	}

	public function test_a_desc_tip_of_true_is_ignored(): void {
		$document = self::render(
			'text',
			array(
				'desc'     => 'Description.',
				'desc_tip' => true,
			)
		);

		self::assertSame( array( 'Description.' ), self::descriptions( $document, $document->getElementById( 'acme.key' ) ) );
		self::assertCount( 1, $document->querySelectorAll( 'p.description' ) );
	}

	public function test_a_control_without_help_has_no_aria_describedby(): void {
		self::assertFalse( self::render( 'text' )->getElementById( 'acme.key' )->hasAttribute( 'aria-describedby' ) );
	}

	public function test_a_checkbox_posts_a_hidden_no_before_its_yes(): void {
		$inputs = \iterator_to_array( self::render( 'checkbox', array(), 'yes', 'acme_flag' )->querySelectorAll( 'input' ) );

		self::assertSame( array( array( 'hidden', 'acme_flag', 'no' ), array( 'checkbox', 'acme_flag', 'yes' ) ), \array_map( self::post_pair( ... ), $inputs ) );
		self::assertTrue( $inputs[1]->hasAttribute( 'checked' ) );
		self::assertFalse( self::render( 'checkbox', array(), 'no' )->getElementById( 'acme.key' )->hasAttribute( 'checked' ) );
	}

	public function test_a_checkbox_with_a_desc_is_named_by_its_title_and_labeled_by_its_desc(): void {
		$document = self::render(
			'checkbox',
			array(
				'title'    => 'Logging',
				'desc'     => 'Log every request.',
				'desc_tip' => 'Logs grow fast.',
			)
		);

		self::assertSame( 'Logging', self::text( $document->querySelector( 'fieldset > legend.screen-reader-text' ) ) );
		self::assertCount( 1, $document->querySelectorAll( 'label' ) );
		self::assertSame( 'acme.key', $document->querySelector( 'label' )->getAttribute( 'for' ) );
		self::assertSame( 'Log every request.', \trim( self::text( $document->querySelector( 'label' ) ) ) );
		self::assertSame( array( 'Logs grow fast.' ), self::descriptions( $document, $document->getElementById( 'acme.key' ) ) );
	}

	public function test_a_checkbox_without_a_desc_renders_no_fieldset_or_label(): void {
		$document = self::render( 'checkbox', array( 'title' => 'Logging' ) );

		self::assertNull( $document->querySelector( 'fieldset' ) );
		self::assertNull( $document->querySelector( 'label' ) );
	}

	public static function group_types(): array {
		return array(
			'radio'      => array( 'radio', '2' ),
			'checkboxes' => array( 'checkboxes', array( '2' ) ),
		);
	}

	#[DataProvider( 'group_types' )]
	public function test_a_group_renders_a_fieldset_named_by_its_legend_with_a_label_per_choice( string $type, string|array $value ): void {
		$document = self::render(
			$type,
			array(
				'title'   => 'Mode',
				'options' => array(
					1 => 'One',
					2 => 'Two',
				),
			),
			$value
		);

		self::assertSame( 'Mode', self::text( $document->querySelector( 'fieldset > legend.screen-reader-text' ) ) );
		self::assertSame( array( '1', '2' ), \array_map( static fn ( \Dom\Element $input ): string => $input->getAttribute( 'value' ), self::choices( $document ) ) );
		self::assertSame( array( false, true ), \array_map( static fn ( \Dom\Element $input ): bool => $input->hasAttribute( 'checked' ), self::choices( $document ) ) );
		self::assertSame( array( 'acme.key.0', 'acme.key.1' ), \array_map( static fn ( \Dom\Element $input ): string => $input->getAttribute( 'id' ), self::choices( $document ) ) );
		self::assertSame( array( 'One', 'Two' ), \array_map( static fn ( \Dom\Element $input ): string => \trim( self::text( $document->querySelector( 'label[for="' . $input->getAttribute( 'id' ) . '"]' ) ) ), self::choices( $document ) ) );
	}

	public function test_a_radio_posts_each_choice_under_the_name(): void {
		$document = self::render(
			'radio',
			array(
				'options' => array(
					'a' => 'A',
					'b' => 'B',
				),
			),
			'a',
			'acme_mode'
		);

		self::assertSame( array( array( 'radio', 'acme_mode', 'a' ), array( 'radio', 'acme_mode', 'b' ) ), \array_map( self::post_pair( ... ), \iterator_to_array( $document->querySelectorAll( 'input' ) ) ) );
	}

	public function test_a_checkboxes_group_posts_a_hidden_empty_value_before_its_choices(): void {
		$document = self::render(
			'checkboxes',
			array(
				'options' => array(
					'a' => 'A',
					'b' => 'B',
				),
			),
			array( 'b' ),
			'acme_days'
		);

		self::assertSame(
			array( array( 'hidden', 'acme_days', '' ), array( 'checkbox', 'acme_days[]', 'a' ), array( 'checkbox', 'acme_days[]', 'b' ) ),
			\array_map( self::post_pair( ... ), \iterator_to_array( $document->querySelectorAll( 'input' ) ) )
		);
	}

	public function test_a_multiselect_posts_a_hidden_empty_value_before_its_select(): void {
		$document = self::render(
			'multiselect',
			array(
				'options' => array(
					1 => 'One',
					2 => 'Two',
					3 => 'Three',
				),
			),
			array( '1', '3' ),
			'acme_ids'
		);
		$select   = $document->getElementById( 'acme.key' );

		self::assertSame( array( 'hidden', 'acme_ids', '' ), self::post_pair( $document->querySelector( 'input' ) ) );
		self::assertSame( \Dom\Node::DOCUMENT_POSITION_FOLLOWING, $document->querySelector( 'input' )->compareDocumentPosition( $select ) & \Dom\Node::DOCUMENT_POSITION_FOLLOWING );
		self::assertTrue( $select->matches( 'select[multiple]' ) );
		self::assertSame( 'acme_ids[]', $select->getAttribute( 'name' ) );
		self::assertSame( array( '1', '3' ), \array_map( static fn ( \Dom\Element $option ): string => $option->getAttribute( 'value' ), \iterator_to_array( $select->querySelectorAll( 'option[selected]' ) ) ) );
	}

	public function test_a_select_marks_the_stored_choice_of_an_integer_keyed_list(): void {
		$select = self::render(
			'select',
			array(
				'options' => array(
					1 => 'One',
					2 => 'Two',
				),
			),
			'2'
		)->getElementById( 'acme.key' );

		self::assertSame( array( '2' ), \array_map( static fn ( \Dom\Element $option ): string => $option->getAttribute( 'value' ), \iterator_to_array( $select->querySelectorAll( 'option[selected]' ) ) ) );
	}

	public function test_every_id_is_unique_even_for_choices_keyed_like_the_help_ids(): void {
		$document = self::render(
			'radio',
			array(
				'desc'     => 'Help.',
				'desc_tip' => 'Tip.',
				'options'  => array(
					'description' => 'Description',
					'help'        => 'Help',
				),
			)
		);
		$ids      = \array_map( static fn ( \Dom\Element $element ): string => $element->getAttribute( 'id' ), \iterator_to_array( $document->querySelectorAll( '[id]' ) ) );

		self::assertCount( 4, $ids );
		self::assertSame( $ids, \array_values( \array_unique( $ids ) ) );
	}

	public function test_a_group_links_its_help_from_its_fieldset(): void {
		$document = self::render(
			'checkboxes',
			array(
				'desc'    => 'Pick any.',
				'options' => array( 'a' => 'A' ),
			)
		);

		self::assertSame( array( 'Pick any.' ), self::descriptions( $document, $document->querySelector( 'fieldset' ) ) );
		self::assertFalse( $document->getElementById( 'acme.key.0' )->hasAttribute( 'aria-describedby' ) );
	}

	public static function disabled_fields(): array {
		return array(
			'checkbox-top-level'           => array( 'checkbox', array( 'disabled' => true ) ),
			'checkbox-custom-attribute'    => array( 'checkbox', array( 'custom_attributes' => array( 'disabled' => 'disabled' ) ) ),
			'checkboxes-custom-attribute'  => array(
				'checkboxes',
				array(
					'options'           => array(
						'a' => 'A',
						'b' => 'B',
					),
					'custom_attributes' => array( 'disabled' => 'disabled' ),
				),
			),
			'multiselect-custom-attribute' => array(
				'multiselect',
				array(
					'options'           => array( 'a' => 'A' ),
					'custom_attributes' => array( 'disabled' => 'disabled' ),
				),
			),
		);
	}

	#[DataProvider( 'disabled_fields' )]
	public function test_a_disabled_control_prints_no_hidden_input_and_disables_every_control( string $type, array $keys ): void {
		$document = self::render( $type, $keys );

		self::assertNull( $document->querySelector( 'input[type="hidden"]' ) );
		self::assertNotNull( $document->querySelector( 'input, select' ) );
		self::assertNull( $document->querySelector( 'input:not([disabled]), select:not([disabled])' ) );
	}

	public function test_a_radio_disables_exactly_the_choices_its_list_names_by_native_key(): void {
		$document = self::render(
			'radio',
			array(
				'options'  => array(
					1   => 'One',
					2   => 'Two',
					'x' => 'X',
				),
				'disabled' => array( 2, 'x', '1' ),
			)
		);

		self::assertSame( array( false, true, true ), \array_map( static fn ( \Dom\Element $input ): bool => $input->hasAttribute( 'disabled' ), self::choices( $document ) ) );
	}

	public function test_custom_attributes_render_without_overriding_the_frameworks_own(): void {
		$input = self::render(
			'text',
			array(
				'custom_attributes' => array(
					'name'      => 'other',
					'ID'        => 'other',
					'type'      => 'hidden',
					'maxlength' => 20,
					'data-acme' => 'x',
				),
			),
			'',
			'acme_name'
		)->querySelector( 'input' );

		self::assertSame( array( 'acme_name', 'acme.key', 'text', '20', 'x' ), \array_map( $input->getAttribute( ... ), array( 'name', 'id', 'type', 'maxlength', 'data-acme' ) ) );
	}

	public function test_custom_attributes_cannot_set_an_attribute_the_framework_leaves_off(): void {
		$checkbox = self::render( 'checkbox', array( 'custom_attributes' => array( 'checked' => false ) ), 'no' )->getElementById( 'acme.key' );
		$select   = self::render(
			'select',
			array(
				'options'           => array( 'a' => 'A' ),
				'custom_attributes' => array( 'multiple' => 'multiple' ),
			)
		)->getElementById( 'acme.key' );

		self::assertFalse( $checkbox->hasAttribute( 'checked' ) );
		self::assertFalse( $select->hasAttribute( 'multiple' ) );
	}

	public function test_custom_attributes_with_invalid_or_event_handler_names_are_dropped(): void {
		$input = self::render(
			'text',
			array(
				'custom_attributes' => array(
					'onclick'    => 'x()',
					'OnFocus'    => 'x()',
					'bad name'   => 'x',
					'x"y'        => 'x',
					'1st'        => 'x',
					'aria-label' => 'Kept',
				),
			)
		)->querySelector( 'input' );
		$names = $input->getAttributeNames();
		\sort( $names );

		self::assertSame( array( 'aria-label', 'class', 'id', 'name', 'type', 'value' ), $names );
	}

	public function test_placeholder_class_and_css_are_honoured(): void {
		$input = self::render(
			'email',
			array(
				'placeholder' => 'you@example.com',
				'class'       => 'acme-wide',
				'css'         => 'width: 50%;',
			)
		)->querySelector( 'input' );

		self::assertSame( array( 'you@example.com', 'acme-wide', 'width: 50%;' ), \array_map( $input->getAttribute( ... ), array( 'placeholder', 'class', 'style' ) ) );
	}

	public static function placeholder_types(): array {
		return array(
			'text'        => array( 'text', true ),
			'number'      => array( 'number', true ),
			'email'       => array( 'email', true ),
			'url'         => array( 'url', true ),
			'textarea'    => array( 'textarea', true ),
			'checkbox'    => array( 'checkbox', false ),
			'select'      => array( 'select', false ),
			'multiselect' => array( 'multiselect', false ),
			'radio'       => array( 'radio', false ),
			'checkboxes'  => array( 'checkboxes', false ),
		);
	}

	#[DataProvider( 'placeholder_types' )]
	public function test_a_placeholder_prints_only_on_text_like_controls( string $type, bool $printed ): void {
		$document = self::render(
			$type,
			array(
				'placeholder' => 'Example',
				'options'     => array( 'a' => 'A' ),
			)
		);

		self::assertSame( $printed, null !== $document->querySelector( '[placeholder]' ) );
	}

	public static function sizing_classes(): array {
		return array(
			'text'     => array( 'text', 'regular-text' ),
			'email'    => array( 'email', 'regular-text' ),
			'url'      => array( 'url', 'regular-text' ),
			'number'   => array( 'number', 'small-text' ),
			'textarea' => array( 'textarea', 'large-text' ),
		);
	}

	#[DataProvider( 'sizing_classes' )]
	public function test_a_text_like_control_without_a_class_gets_cores_sizing_class( string $type, string $sizing_class ): void {
		self::assertSame( $sizing_class, self::render( $type )->getElementById( 'acme.key' )->getAttribute( 'class' ) );
	}

	public function test_a_number_allows_any_step_unless_its_custom_attributes_set_one(): void {
		self::assertSame( 'any', self::render( 'number' )->getElementById( 'acme.key' )->getAttribute( 'step' ) );
		self::assertSame( '5', self::render( 'number', array( 'custom_attributes' => array( 'step' => 5 ) ) )->getElementById( 'acme.key' )->getAttribute( 'step' ) );
	}

	public function test_a_textarea_holds_its_value_as_text(): void {
		$textarea = self::render( 'textarea', array(), "Line one\n</textarea><b>two</b>" )->getElementById( 'acme.key' );

		self::assertTrue( $textarea->matches( 'textarea' ) );
		self::assertSame( "Line one\n</textarea><b>two</b>", self::text( $textarea ) );
	}

	public function test_a_field_with_a_render_callable_renders_through_it_once(): void {
		$calls = array();
		$field = array(
			'type'   => 'acme_color',
			'render' => static function ( array $field, mixed $value, string $name, string $id ) use ( &$calls ): string {
				$calls[] = array( $field['type'], $value, $name, $id );
				return '<input type="color" id="' . esc_attr( $id ) . '">';
			},
		);

		self::assertSame( '<input type="color" id="acme.key">', Forms::render_control( $field, '#fff', 'acme_color', 'acme.key' ) );
		self::assertSame( array( array( 'acme_color', '#fff', 'acme_color', 'acme.key' ) ), $calls );
	}

	public function test_an_unknown_type_without_render_prints_nothing(): void {
		self::assertSame( '', Forms::render_control( array( 'type' => 'acme_color' ), '#fff', 'acme_color', 'acme.key' ) );
	}

	protected static function render( string $type, array $keys = array(), mixed $value = '', string $name = 'acme_key', string $id = 'acme.key' ): \Dom\HTMLDocument {
		return \Dom\HTMLDocument::createFromString( Forms::render_control( array( 'type' => $type ) + $keys, $value, $name, $id ), \LIBXML_NOERROR );
	}

	protected static function text( \Dom\Node $node ): string {
		return (string) $node->textContent; // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- A DOM property.
	}

	protected static function descriptions( \Dom\HTMLDocument $document, \Dom\Element $described ): array {
		return \array_map( static fn ( string $id ): string => self::text( $document->getElementById( $id ) ), \explode( ' ', $described->getAttribute( 'aria-describedby' ) ?? '' ) );
	}

	protected static function choices( \Dom\HTMLDocument $document ): array {
		return \iterator_to_array( $document->querySelectorAll( 'fieldset input:not([type="hidden"])' ) );
	}

	protected static function post_pair( \Dom\Element $input ): array {
		return array( $input->getAttribute( 'type' ), $input->getAttribute( 'name' ), $input->getAttribute( 'value' ) );
	}
}
