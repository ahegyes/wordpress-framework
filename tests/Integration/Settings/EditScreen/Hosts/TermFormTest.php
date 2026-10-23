<?php declare( strict_types=1 );

namespace DeepWebSolutions\Framework\Tests\Integration\Settings\EditScreen\Hosts;

use DeepWebSolutions\Framework\Core\AdminNotice\AdminNotices;
use DeepWebSolutions\Framework\Core\Logging\ErrorLogLogger;
use DeepWebSolutions\Framework\Settings\EditScreen\Hosts\TermForm;
use DeepWebSolutions\Framework\Settings\EditScreen\ObjectForm;
use DeepWebSolutions\Framework\Tests\Integration\Support\CreatesUsersTrait;
use DeepWebSolutions\Framework\Tests\Integration\Support\IsolatesHooksTrait;
use DeepWebSolutions\Framework\Tests\Integration\Support\IsolatesStorageTrait;
use DeepWebSolutions\Framework\Tests\Integration\Support\RecordedDefinition;
use DeepWebSolutions\Framework\Tests\Integration\Support\RunsAdminRequestsTrait;
use DeepWebSolutions\Framework\Tests\Integration\Support\SerializesFormsTrait;
use PHPUnit\Framework\Attributes\After;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass( ObjectForm::class )]
#[CoversClass( TermForm::class )]
final class TermFormTest extends TestCase {
	use CreatesUsersTrait;
	use IsolatesHooksTrait;
	use IsolatesStorageTrait;
	use RunsAdminRequestsTrait;
	use SerializesFormsTrait;

	protected const string FIXTURE = __DIR__ . '/../../../../Fixtures/Settings/acme-object-forms.php';

	protected const array COLORS = array(
		'red'  => 'Red',
		'blue' => 'Blue',
		'teal' => 'Teal',
	);

	protected int $admin;
	protected int $editor;
	protected string $id;
	protected array $terms = array();

	#[\Override]
	protected function setUp(): void {
		$this->admin  = $this->make_user( 'acme_object_forms_admin', 'administrator' );
		$this->editor = $this->make_user( 'acme_object_forms_editor', 'editor' );
		$this->id     = "{$this->prefix}_term";
	}

	public function test_the_host_registers_on_each_of_its_taxonomies(): void {
		$host = new TermForm( array( 'category', 'post_tag' ) );
		( new ObjectForm( $this->id, new RecordedDefinition( static fn (): array => array() ), $host, new AdminNotices( $this->prefix, new ErrorLogLogger( 'acme' ) ) ) )->register_hooks();

		foreach ( array( 'category', 'post_tag' ) as $taxonomy ) {
			self::assertSame( 10, \has_action( "{$taxonomy}_edit_form", array( $host, 'render_section' ) ) );
			self::assertSame( 10, \has_action( "edited_$taxonomy", array( $host, 'save_term' ) ) );
		}
	}

	public function test_the_form_shows_after_cores_fields_on_its_taxonomies_edit_screens_only(): void {
		$category = $this->make_term( 'category' );
		$tag      = $this->make_term( 'post_tag' );
		$spec     = $this->spec( array( $this->field( 'text', 'name' ), $this->field( 'radio', 'color', array( 'options' => self::COLORS ) ) ) );

		$edit  = self::document( $this->edit_screen( $spec, 'category', $category )['body'] );
		$other = self::document( $this->edit_screen( $spec, 'post_tag', $tag )['body'] );

		$section = $edit->querySelector( "form#edittag > table.form-table ~ input[type=hidden][name=\"{$this->id}_nonce\"] + h2 + table.form-table" );
		self::assertNotNull( $section );
		self::assertSame( 'Acme term', \trim( (string) $section->previousElementSibling?->textContent ) ); // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- A DOM property.
		self::assertSame( 'Name', \trim( (string) $section->querySelector( "tr > th[scope=row] > label[for=\"{$this->id}._{$this->prefix}_name\"]" )?->textContent ) ); // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- A DOM property.
		self::assertSame( 'Color', \trim( (string) $section->querySelector( 'tr:nth-child(2) > td > fieldset > legend.screen-reader-text' )?->textContent ) ); // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- A DOM property.
		self::assertCount( 1, $section->querySelectorAll( 'tr:nth-child(2) fieldset' ) );
		self::assertNotNull( $other->querySelector( 'form#edittag' ) );
		self::assertNull( $other->querySelector( "input[name=\"{$this->id}_nonce\"]" ) );
	}

	public function test_controls_carry_the_stored_value_or_else_the_default(): void {
		$term_id = $this->make_term( 'category' );
		\add_term_meta( $term_id, "_{$this->prefix}_name", 'stored' );
		$spec = $this->spec( array( $this->field( 'text', 'name', array( 'default' => 'fresh' ) ), $this->field( 'text', 'absent', array( 'default' => 'fresh' ) ) ) );

		$edit = self::document( $this->edit_screen( $spec, 'category', $term_id )['body'] );

		self::assertSame( 'stored', $edit->querySelector( "[id=\"{$this->id}._{$this->prefix}_name\"]" )?->getAttribute( 'value' ) );
		self::assertSame( 'fresh', $edit->querySelector( "[id=\"{$this->id}._{$this->prefix}_absent\"]" )?->getAttribute( 'value' ) );
	}

	public function test_a_user_lacking_the_forms_capability_gets_no_section(): void {
		$term_id = $this->make_term( 'category' );
		$spec    = $this->spec( array( $this->field( 'text', 'name' ) ), array( 'capability' => 'manage_options' ) );

		$edit = self::document( $this->admin_request( 'GET', "term.php?taxonomy=category&tag_ID=$term_id", $this->editor, self::FIXTURE, $spec )['body'] );

		self::assertNotNull( $edit->querySelector( 'form#edittag' ) );
		self::assertNull( $edit->querySelector( "input[name=\"{$this->id}_nonce\"]" ) );
	}

	public function test_update_saves_the_accepted_fields_and_shows_a_rejection_after_the_redirect(): void {
		$term_id = $this->make_term( 'category' );
		\add_term_meta( $term_id, "_{$this->prefix}_note", 'kept' );
		$spec = $this->spec( array( $this->field( 'text', 'name' ), $this->field( 'text', 'note', array( 'validate' => 'acme:reject' ) ) ) );

		$data  = self::serialize_form(
			$this->edit_screen( $spec, 'category', $term_id )['body'],
			'form#edittag',
			array(
				$this->control_name( 'name' ) => 'O\'Brien C:\\dir',
				$this->control_name( 'note' ) => 'too long',
			)
		);
		$saved = $this->admin_request( 'POST', 'edit-tags.php', $this->admin, self::FIXTURE, $spec, $data );
		self::assertIsString( $saved['location'] );
		$next  = $this->admin_request( 'GET', \explode( '/wp-admin/', $saved['location'], 2 )[1], $this->admin, self::FIXTURE, $spec );
		$after = $this->edit_screen( $spec, 'category', $term_id );

		self::assertSame( array( 'O\'Brien C:\\dir' ), $this->meta( $term_id, 'name' ) );
		self::assertSame( array( 'kept' ), $this->meta( $term_id, 'note' ) );
		self::assertSame( array( 'Note was not saved, so it keeps its previous value. Use at most four characters.' ), self::rejections( $next['body'] ) );
		self::assertSame( array(), self::rejections( $after['body'] ) );
	}

	public function test_quick_edit_stores_nothing(): void {
		$term_id = $this->make_term( 'category' );
		\add_term_meta( $term_id, "_{$this->prefix}_name", 'kept' );
		$spec = $this->spec( array( $this->field( 'text', 'name' ) ) );
		$list = self::document( $this->admin_request( 'GET', 'edit-tags.php?taxonomy=category', $this->admin, self::FIXTURE, $spec )['body'] );

		$this->admin_request(
			'POST',
			'admin-ajax.php',
			$this->admin,
			self::FIXTURE,
			$spec,
			array(
				'action'       => 'inline-save-tax',
				'_inline_edit' => (string) $list->querySelector( 'input#_inline_edit' )?->getAttribute( 'value' ),
				'taxonomy'     => 'category',
				'tax_ID'       => (string) $term_id,
				'name'         => "{$this->prefix} quick",
				'slug'         => "{$this->prefix}-quick",
				$this->id      => array( "_{$this->prefix}_name" => 'quick' ),
			)
		);

		self::assertSame( "{$this->prefix} quick", \get_term( $term_id )->name );
		self::assertSame( array( 'kept' ), $this->meta( $term_id, 'name' ) );
	}

	public function test_an_update_in_code_stores_nothing(): void {
		$term_id = $this->make_term( 'category' );
		\add_term_meta( $term_id, "_{$this->prefix}_name", 'kept' );
		$form = new ObjectForm(
			$this->id,
			new RecordedDefinition(
				fn (): array => array(
					'title'      => 'Acme term',
					'capability' => 'manage_categories',
					'fields'     => array( $this->field( 'text', 'name' ) ),
				)
			),
			new TermForm( array( 'category' ) ),
			new AdminNotices( $this->prefix, new ErrorLogLogger( 'acme' ) )
		);
		$form->register_hooks();
		\wp_set_current_user( $this->admin );
		$_POST[ $this->id ] = array( "_{$this->prefix}_name" => 'programmatic' );

		try {
			\wp_update_term( $term_id, 'category', array( 'name' => "{$this->prefix} in code" ) );
		} finally {
			unset( $_POST[ $this->id ] );
			\wp_set_current_user( 0 );
		}

		self::assertSame( "{$this->prefix} in code", \get_term( $term_id )->name );
		self::assertSame( array( 'kept' ), $this->meta( $term_id, 'name' ) );
	}

	protected function spec( array $fields, array $form = array() ): array {
		return array(
			'id'        => $this->id,
			'host'      => 'term',
			'host_args' => array( 'taxonomies' => array( 'category' ) ),
			'form'      => $form + array(
				'title'      => 'Acme term',
				'capability' => 'manage_categories',
				'fields'     => $fields,
			),
		);
	}

	protected function field( string $type, string $name, array $keys = array() ): array {
		return $keys + array(
			'type'  => $type,
			'id'    => "_{$this->prefix}_$name",
			'title' => \ucfirst( $name ),
		);
	}

	protected function control_name( string $name ): string {
		return "{$this->id}[_{$this->prefix}_$name]";
	}

	protected function make_term( string $taxonomy ): int {
		$term = \wp_insert_term( "{$this->prefix} " . \count( $this->terms ), $taxonomy );
		self::assertIsArray( $term );
		$this->terms[] = array( (int) $term['term_id'], $taxonomy );

		return (int) $term['term_id'];
	}

	protected function edit_screen( array $spec, string $taxonomy, int $term_id ): array {
		return $this->admin_request( 'GET', "term.php?taxonomy=$taxonomy&tag_ID=$term_id", $this->admin, self::FIXTURE, $spec );
	}

	protected function meta( int $term_id, string $name ): array {
		return \get_term_meta( $term_id, "_{$this->prefix}_$name", false );
	}

	protected static function rejections( string $html ): array {
		$messages = array();
		foreach ( self::document( $html )->querySelectorAll( 'div.notice-error.is-dismissible > p' ) as $message ) {
			$messages[] = \trim( $message->textContent ); // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- A DOM property.
		}

		return $messages;
	}

	#[After]
	protected function delete_terms(): void {
		foreach ( $this->terms as list( $term_id, $taxonomy ) ) {
			\wp_delete_term( $term_id, $taxonomy );
		}
	}
}
