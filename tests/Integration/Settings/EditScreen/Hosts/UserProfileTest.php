<?php declare( strict_types=1 );

namespace DeepWebSolutions\Framework\Tests\Integration\Settings\EditScreen\Hosts;

use DeepWebSolutions\Framework\Core\AdminNotice\AdminNotices;
use DeepWebSolutions\Framework\Core\Logging\ErrorLogLogger;
use DeepWebSolutions\Framework\Settings\EditScreen\Hosts\UserProfile;
use DeepWebSolutions\Framework\Settings\EditScreen\ObjectForm;
use DeepWebSolutions\Framework\Tests\Integration\Support\CreatesUsersTrait;
use DeepWebSolutions\Framework\Tests\Integration\Support\IsolatesHooksTrait;
use DeepWebSolutions\Framework\Tests\Integration\Support\IsolatesStorageTrait;
use DeepWebSolutions\Framework\Tests\Integration\Support\RecordedDefinition;
use DeepWebSolutions\Framework\Tests\Integration\Support\RunsAdminRequestsTrait;
use DeepWebSolutions\Framework\Tests\Integration\Support\SerializesFormsTrait;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

#[CoversClass( ObjectForm::class )]
#[CoversClass( UserProfile::class )]
final class UserProfileTest extends TestCase {
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
	protected int $subscriber;
	protected string $id;

	#[\Override]
	protected function setUp(): void {
		$this->admin      = $this->make_profile_user( 'acme_object_forms_admin', 'administrator' );
		$this->subscriber = $this->make_profile_user( 'acme_object_forms_subscriber', 'subscriber' );
		$this->id         = "{$this->prefix}_profile";
	}

	public static function screens(): array {
		return array(
			'own-profile'   => array( 'own' ),
			'another-users' => array( 'other' ),
		);
	}

	public static function multisite_profiles(): array {
		return array(
			'network-admin' => array( 'network/profile.php' ),
			'user-admin'    => array( 'user/profile.php' ),
		);
	}

	public function test_the_host_registers_on_both_profile_screens(): void {
		$host = new UserProfile();
		( new ObjectForm( $this->id, new RecordedDefinition( static fn (): array => array() ), $host, new AdminNotices( $this->prefix, new ErrorLogLogger( 'acme' ) ) ) )->register_hooks();

		self::assertSame( 10, has_action( 'show_user_profile', array( $host, 'render_section' ) ) );
		self::assertSame( 10, has_action( 'edit_user_profile', array( $host, 'render_section' ) ) );
		self::assertSame( 10, has_action( 'personal_options_update', array( $host, 'save_profile' ) ) );
		self::assertSame( 10, has_action( 'edit_user_profile_update', array( $host, 'save_profile' ) ) );
	}

	#[DataProvider( 'screens' )]
	public function test_the_form_shows_as_a_titled_section_of_labelled_rows( string $screen ): void {
		$spec = $this->spec( array( $this->field( 'text', 'name' ), $this->field( 'checkboxes', 'color', array( 'options' => self::COLORS ) ) ) );

		$form = self::document( $this->open( $spec, $screen )['body'] )->querySelector( 'form#your-profile' );

		$section = $form?->querySelector( "input[type=hidden][name=\"{$this->id}_nonce\"] ~ h2 + table.form-table" );
		self::assertNotNull( $section );
		self::assertSame( 'Acme profile', \trim( (string) $section->previousElementSibling?->textContent ) ); // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- A DOM property.
		self::assertSame( 'Name', \trim( (string) $section->querySelector( "tr > th[scope=row] > label[for=\"{$this->id}._{$this->prefix}_name\"]" )?->textContent ) ); // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- A DOM property.
		self::assertNotNull( $section->querySelector( "tr > td > input[id=\"{$this->id}._{$this->prefix}_name\"][name=\"{$this->id}[_{$this->prefix}_name]\"]" ) );
		self::assertSame( 'Color', \trim( (string) $section->querySelector( 'tr:nth-child(2) > th[scope=row]' )?->textContent ) ); // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- A DOM property.
		self::assertSame( 'Color', \trim( (string) $section->querySelector( 'tr:nth-child(2) > td > fieldset > legend.screen-reader-text' )?->textContent ) ); // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- A DOM property.
		self::assertCount( 1, $section->querySelectorAll( 'tr:nth-child(2) legend' ) );
		self::assertCount( 1, $section->querySelectorAll( 'tr:nth-child(2) fieldset' ) );
	}

	public function test_controls_carry_the_stored_value_or_else_the_default(): void {
		add_user_meta( $this->subscriber, "_{$this->prefix}_name", 'stored' );
		add_user_meta( $this->subscriber, "_{$this->prefix}_empty", '' );
		$spec = $this->spec(
			array(
				$this->field( 'text', 'name', array( 'default' => 'fresh' ) ),
				$this->field( 'text', 'empty', array( 'default' => 'fresh' ) ),
				$this->field( 'text', 'absent', array( 'default' => 'fresh' ) ),
			)
		);

		$form = self::document( $this->open( $spec, 'other' )['body'] );

		self::assertSame( 'stored', $this->control( $form, 'name' )->getAttribute( 'value' ) );
		self::assertSame( '', $this->control( $form, 'empty' )->getAttribute( 'value' ) );
		self::assertSame( 'fresh', $this->control( $form, 'absent' )->getAttribute( 'value' ) );
	}

	#[DataProvider( 'screens' )]
	public function test_both_screens_save_the_accepted_fields_and_show_a_rejection_after_the_redirect( string $screen ): void {
		$user_id = 'own' === $screen ? $this->admin : $this->subscriber;
		add_user_meta( $user_id, "_{$this->prefix}_note", 'kept' );
		$spec = $this->spec(
			array(
				$this->field( 'text', 'name' ),
				$this->field( 'checkboxes', 'color', array( 'options' => self::COLORS ) ),
				$this->field( 'text', 'note', array( 'validate' => 'acme:reject' ) ),
			)
		);

		$saved = $this->save(
			$spec,
			$screen,
			array(
				$this->control_name( 'name' )  => 'O\'Brien C:\\dir',
				$this->control_name( 'color' ) => array( 'red', 'teal' ),
				$this->control_name( 'note' )  => 'too long',
			)
		);
		$next  = $this->follow( $spec, $saved );
		$after = $this->open( $spec, $screen );

		self::assertSame( array( 'O\'Brien C:\\dir' ), $this->meta( $user_id, 'name' ) );
		self::assertSame( array( array( 'red', 'teal' ) ), $this->meta( $user_id, 'color' ) );
		self::assertSame( array( 'kept' ), $this->meta( $user_id, 'note' ) );
		self::assertSame( array( 'Note was not saved, so it keeps its previous value. Use at most four characters.' ), self::rejections( $next['body'] ) );
		self::assertSame( array(), self::rejections( $after['body'] ) );
	}

	#[Group( 'multisite' )]
	#[DataProvider( 'multisite_profiles' )]
	public function test_a_rejection_on_a_multisite_profile_shows_after_the_redirect_within_the_same_admin( string $path ): void {
		$spec = $this->spec( array( $this->field( 'text', 'name' ), $this->field( 'text', 'note', array( 'validate' => 'acme:reject' ) ) ) );
		grant_super_admin( $this->admin );

		try {
			$data  = self::serialize_form( $this->admin_request( 'GET', $path, $this->admin, self::FIXTURE, $spec )['body'], 'form#your-profile', array( $this->control_name( 'note' ) => 'too long' ) );
			$saved = $this->admin_request( 'POST', $path, $this->admin, self::FIXTURE, $spec, $data );
			$next  = $this->follow( $spec, $saved );
		} finally {
			revoke_super_admin( $this->admin );
		}

		self::assertStringStartsWith( \explode( '/', $path )[0] . '/profile.php', \explode( '/wp-admin/', (string) $saved['location'], 2 )[1] );
		self::assertSame( array( 'Note was not saved, so it keeps its previous value. Use at most four characters.' ), self::rejections( $next['body'] ) );
	}

	public function test_a_core_profile_error_shows_the_forms_rejection_in_the_same_response(): void {
		$spec = $this->spec( array( $this->field( 'text', 'name' ), $this->field( 'text', 'note', array( 'validate' => 'acme:reject' ) ) ) );

		$saved = $this->save(
			$spec,
			'other',
			array(
				'email'                       => 'not-an-email',
				$this->control_name( 'name' ) => 'saved anyway',
				$this->control_name( 'note' ) => 'too long',
			)
		);

		self::assertNull( $saved['location'] );
		self::assertNotNull( self::document( $saved['body'] )->querySelector( 'div.error' ) );
		self::assertSame( array( 'Note was not saved, so it keeps its previous value. Use at most four characters.' ), self::rejections( $saved['body'] ) );
		self::assertSame( array( 'saved anyway' ), $this->meta( $this->subscriber, 'name' ) );
	}

	public function test_a_user_editing_themself_without_the_forms_capability_gets_no_section_and_stores_nothing(): void {
		$contributor = $this->make_profile_user( 'acme_object_forms_contributor', 'contributor' );
		add_user_meta( $contributor, "_{$this->prefix}_name", 'kept' );
		$open   = $this->spec( array( $this->field( 'text', 'name' ) ), array( 'capability' => 'read' ) );
		$closed = $this->spec( array( $this->field( 'text', 'name' ) ), array( 'capability' => 'edit_users' ) );

		$hidden = self::document( $this->admin_request( 'GET', 'profile.php', $contributor, self::FIXTURE, $closed )['body'] );
		$data   = self::serialize_form( $this->admin_request( 'GET', 'profile.php', $contributor, self::FIXTURE, $open )['body'], 'form#your-profile', array( $this->control_name( 'name' ) => 'forged' ) );
		$this->admin_request( 'POST', 'profile.php', $contributor, self::FIXTURE, $closed, $data );

		self::assertNotNull( $hidden->querySelector( 'form#your-profile' ) );
		self::assertNull( $hidden->querySelector( "input[name=\"{$this->id}_nonce\"]" ) );
		self::assertArrayHasKey( "{$this->id}_nonce", $data );
		self::assertSame( array( 'kept' ), $this->meta( $contributor, 'name' ) );
	}

	public function test_an_update_in_code_stores_nothing(): void {
		add_user_meta( $this->subscriber, "_{$this->prefix}_name", 'kept' );
		$form = new ObjectForm(
			$this->id,
			new RecordedDefinition(
				fn (): array => array(
					'title'      => 'Acme profile',
					'capability' => 'edit_users',
					'fields'     => array( $this->field( 'text', 'name' ) ),
				)
			),
			new UserProfile(),
			new AdminNotices( $this->prefix, new ErrorLogLogger( 'acme' ) )
		);
		$form->register_hooks();
		wp_set_current_user( $this->admin );
		$_POST[ $this->id ] = array( "_{$this->prefix}_name" => 'programmatic' );

		try {
			wp_update_user(
				array(
					'ID'           => $this->subscriber,
					'display_name' => 'Updated in code',
				)
			);
		} finally {
			unset( $_POST[ $this->id ] );
			wp_set_current_user( 0 );
		}

		self::assertSame( 'Updated in code', get_userdata( $this->subscriber )->display_name );
		self::assertSame( array( 'kept' ), $this->meta( $this->subscriber, 'name' ) );
	}

	protected function make_profile_user( string $login, string $role ): int {
		$user_id = $this->make_user( $login, $role );
		wp_update_user(
			array(
				'ID'         => $user_id,
				'user_email' => "$login@example.com",
			)
		);

		return $user_id;
	}

	protected function spec( array $fields, array $form = array() ): array {
		return array(
			'id'        => $this->id,
			'host'      => 'user',
			'host_args' => array(),
			'form'      => $form + array(
				'title'      => 'Acme profile',
				'capability' => 'edit_users',
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

	// The administrator opens their own profile, or the subscriber's.
	protected function open( array $spec, string $screen ): array {
		return $this->admin_request( 'GET', 'own' === $screen ? 'profile.php' : "user-edit.php?user_id={$this->subscriber}", $this->admin, self::FIXTURE, $spec );
	}

	protected function save( array $spec, string $screen, array $changes ): array {
		return $this->admin_request( 'POST', 'own' === $screen ? 'profile.php' : 'user-edit.php', $this->admin, self::FIXTURE, $spec, self::serialize_form( $this->open( $spec, $screen )['body'], 'form#your-profile', $changes ) );
	}

	protected function follow( array $spec, array $saved ): array {
		self::assertIsString( $saved['location'] );

		return $this->admin_request( 'GET', \explode( '/wp-admin/', $saved['location'], 2 )[1], $this->admin, self::FIXTURE, $spec );
	}

	protected function meta( int $user_id, string $name ): array {
		return get_user_meta( $user_id, "_{$this->prefix}_$name", false );
	}

	protected function control( \Dom\HTMLDocument $screen, string $name ): \Dom\Element {
		$control = $screen->querySelector( "form#your-profile [id=\"{$this->id}._{$this->prefix}_$name\"]" );
		self::assertNotNull( $control, "The profile printed no _{$this->prefix}_$name." );

		return $control;
	}

	protected static function rejections( string $html ): array {
		$messages = array();
		foreach ( self::document( $html )->querySelectorAll( 'div.notice-error.is-dismissible > p' ) as $message ) {
			$messages[] = \trim( $message->textContent ); // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- A DOM property.
		}

		return $messages;
	}
}
