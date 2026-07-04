<?php declare( strict_types=1 );

namespace DeepWebSolutions\Framework\Settings\MetaField;

use DeepWebSolutions\Framework\Settings\MetaField\ValueObjects\FieldGroup;
use DeepWebSolutions\Framework\Settings\Schema\Exceptions\DuplicateSettingsFieldException;
use DeepWebSolutions\Framework\Settings\Schema\Field\FieldProcessor;
use DeepWebSolutions\Framework\Settings\Schema\Field\FieldRenderer;
use DeepWebSolutions\Framework\Settings\Schema\ValueObjects\SettingsField;
use DeepWebSolutions\Framework\Shared\Result\Failure;
use DeepWebSolutions\Framework\Shared\Result\Success;

use function DeepWebSolutions\Framework\Settings\Schema\is_field_editable_by_current_user;
use function DeepWebSolutions\Framework\Settings\Schema\wordpress_field_type_sanitizers;

/**
 * Shared render and save engine for object-field surfaces.
 *
 * Drives a {@see FieldGroup} against an injected object-meta repository: on render it emits an
 * object-scoped nonce and each editable field's control; on save it verifies that nonce, processes each
 * editable field, and applies the batch through a single apply() call. Object fields are revoke-based:
 * an absent value renders unset (never the field default), absent or empty submissions delete the meta
 * key, and an invalid present submission preserves the existing value.
 *
 * A surface varies only in per-field markup, supplied as a row closure (table-row surfaces wrap each
 * control; meta-box surfaces echo it raw), and in the optional bespoke render/save closures the group
 * carries. The per-object surface capability is the caller's gate, checked before save runs.
 *
 * @since   2.0.0
 * @version 2.0.0
 */
final class ObjectFieldForm {
	// region MAGIC METHODS

	/**
	 * Constructor.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   ObjectMetaRepositoryInterface $repository Repository the group's fields read from and write to.
	 * @param   FieldRenderer                 $renderer  Renderer for the field controls.
	 * @param   ?FieldProcessor               $processor Processor for sanitizing submitted values; null applies one carrying the per-type default sanitizers.
	 */
	public function __construct(
		protected ObjectMetaRepositoryInterface $repository,
		protected FieldRenderer $renderer = new FieldRenderer(),
		protected ?FieldProcessor $processor = null,
	) {
		$this->processor ??= new FieldProcessor( type_sanitizers: wordpress_field_type_sanitizers() );
	}

	// endregion

	// region METHODS

	/**
	 * Renders the group's fields for an object: an object-scoped nonce followed by each editable control.
	 *
	 * Emits the nonce for every render — bespoke renderer included — so {@see self::save()} can verify it;
	 * a bespoke group renderer then owns the rest of the markup. Otherwise each editable field renders with
	 * its value read revoke-based (an absent value renders unset), wrapped by $row when given or echoed raw.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   FieldGroup $group     Group to render.
	 * @param   int        $object_id Object the fields are rendered for.
	 * @param   ?\Closure  $row       Wraps a (SettingsField, control-html) pair into surface markup; null echoes the control raw.
	 *
	 * @throws  DuplicateSettingsFieldException If two of the group's fields share an id.
	 */
	public function render( FieldGroup $group, int $object_id, ?\Closure $row = null ): void {
		// Emitted for every group, bespoke renderer included: save() verifies this nonce before it runs the
		// bespoke save handler, so a bespoke renderer must not have to reimplement the convention.
		\wp_nonce_field( $this->nonce_action( $group, $object_id ), $this->nonce_name( $group ) );

		if ( null !== $group->render ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- bespoke renderer owns its escaping.
			echo (string) ( $group->render )( $object_id );
			return;
		}

		foreach ( $this->fields_of( $group, $object_id ) as $field ) {
			if ( ! is_field_editable_by_current_user( $field ) ) {
				continue;
			}
			// Object fields are revoke-based: an absent meta renders as unset, NOT the field default, so a
			// value cleared via delete-on-empty does not spring back to its default on the next render.
			$value   = $this->repository->get( $object_id, $field->meta_key ?? $field->id );
			$control = $this->renderer->render( $field, $value, $group->id . '[' . $field->id . ']' );
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- FieldRenderer returns escaped markup; a row closure escapes the surface chrome it adds.
			echo null !== $row ? (string) ( $row )( $field, $control ) : $control;
		}
	}

	/**
	 * Saves the group's submitted fields for an object.
	 *
	 * Verifies the object-scoped nonce, then runs the group's bespoke save handler if it has one, or
	 * processes each editable field and applies the batch through one apply() call. Object fields are
	 * revoke-based: an unsubmitted or empty field deletes its meta key rather than storing a default; an
	 * invalid present field preserves the existing meta. The per-object surface capability is the caller's
	 * responsibility, checked before this runs.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   FieldGroup $group           Group to save.
	 * @param   int        $object_id       Object whose meta to write.
	 * @param   ?int       $nonce_object_id Object id the nonce is bound to; null uses $object_id.
	 *
	 * @throws  DuplicateSettingsFieldException If two of the group's fields share an id.
	 */
	public function save( FieldGroup $group, int $object_id, ?int $nonce_object_id = null ): void {
		$name = $this->nonce_name( $group );
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce read here and verified on the next line.
		$nonce = isset( $_POST[ $name ] ) ? \sanitize_text_field( \wp_unslash( $_POST[ $name ] ) ) : '';
		if ( false === \wp_verify_nonce( $nonce, $this->nonce_action( $group, $nonce_object_id ?? $object_id ) ) ) {
			return;
		}

		if ( null !== $group->save ) {
			( $group->save )( $object_id );
			return;
		}

		// The controls namespace their names under the group id (group_id[field_id]), so read only that
		// subarray — avoiding collisions with other fields and meta boxes on the same surface.
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce verified above.
		$submitted = \wp_unslash( $_POST[ $group->id ] ?? array() );
		$submitted = \is_array( $submitted ) ? $submitted : array();

		$sets    = array();
		$deletes = array();
		foreach ( $this->fields_of( $group, $object_id ) as $field ) {
			if ( ! is_field_editable_by_current_user( $field ) ) {
				continue;
			}

			$meta_key = $field->meta_key ?? $field->id;

			// Object fields are revoke-based: an unsubmitted field deletes its meta key rather than keeping
			// or defaulting it. Checked before processing because a custom type folds an absent submission to
			// its declared default, which would otherwise resurrect a cleared value.
			if ( ! \array_key_exists( $field->id, $submitted ) ) {
				$deletes[] = $meta_key;
				continue;
			}

			// A present submission is stored when meaningful; a rejected one (invalid option, failed
			// validation) leaves the key untouched, preserving the prior value.
			$result = $this->processor()->process_or_reject( $field, $submitted );
			if ( $result instanceof Failure ) {
				continue;
			}
			\assert( $result instanceof Success );
			$value = $result->value;
			if ( $this->should_store( $value ) ) {
				$sets[ $meta_key ] = $value;
			} else {
				$deletes[] = $meta_key;
			}
		}

		$this->repository->apply( $object_id, $sets, $deletes );
	}

	/**
	 * Returns the nonce action for a group's save on a given object.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   FieldGroup $group     Group the nonce guards.
	 * @param   int        $object_id Object the nonce is bound to.
	 *
	 * @return  string
	 */
	public function get_nonce_action( FieldGroup $group, int $object_id ): string {
		return $this->nonce_action( $group, $object_id );
	}

	/**
	 * Returns the nonce field name for a group's save.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   FieldGroup $group Group the nonce guards.
	 *
	 * @return  string
	 */
	public function get_nonce_name( FieldGroup $group ): string {
		return $this->nonce_name( $group );
	}

	// endregion

	// region HELPERS

	/**
	 * Builds the group's fields for an object, rejecting a duplicate field id within the group.
	 *
	 * The ids are the form keys and the processor reads each field's submission by id, so a duplicate id
	 * would render colliding controls. Two fields sharing an effective storage key (a field's meta_key, or
	 * its id when none is set) are rejected too: an empty one would delete the value a submitted sibling wrote.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   FieldGroup $group     Group whose fields to build.
	 * @param   int        $object_id Object the fields are built for.
	 *
	 * @throws  DuplicateSettingsFieldException If two fields in the group share an id.
	 *
	 * @return  list<SettingsField>
	 */
	protected function fields_of( FieldGroup $group, int $object_id ): array {
		/** @var list<SettingsField> $fields */ // phpcs:ignore Generic.Commenting.DocComment.MissingShort -- inline @var type assertion, no description applies.
		$fields = ( $group->fields_provider )( $object_id );

		$seen_ids  = array();
		$seen_keys = array();
		foreach ( $fields as $field ) {
			if ( \array_key_exists( $field->id, $seen_ids ) ) {
				// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- framework-internal exception; never reaches an HTML output context unescaped.
				throw new DuplicateSettingsFieldException( "Duplicate object field id in group '$group->id': '$field->id'" );
			}
			$seen_ids[ $field->id ] = true;

			$meta_key = $field->meta_key ?? $field->id;
			if ( \array_key_exists( $meta_key, $seen_keys ) ) {
				// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- framework-internal exception; never reaches an HTML output context unescaped.
				throw new DuplicateSettingsFieldException( "Duplicate object field storage key in group '$group->id': '$meta_key'" );
			}
			$seen_keys[ $meta_key ] = true;
		}

		return $fields;
	}

	/**
	 * Returns the configured processor.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @return  FieldProcessor
	 */
	protected function processor(): FieldProcessor {
		\assert( $this->processor instanceof FieldProcessor );

		return $this->processor;
	}

	/**
	 * Whether a processed value should be stored. An empty value — false, a cleared field ('') or an empty
	 * multi-select (array()) — is not stored; its meta key is deleted instead (revoke semantics). A meaningful
	 * zero (0, '0') and a canonical checkbox 'no' are values and are preserved.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   mixed $value Processed field value.
	 *
	 * @return  bool
	 */
	protected function should_store( mixed $value ): bool {
		return false !== $value && '' !== $value && array() !== $value;
	}

	/**
	 * The nonce action for a group's save on a given object. Object-scoped so a token minted for one object
	 * cannot authorize a write to another.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   FieldGroup $group     Group the nonce guards.
	 * @param   int        $object_id Object the nonce is bound to.
	 *
	 * @return  string
	 */
	protected function nonce_action( FieldGroup $group, int $object_id ): string {
		return 'dws_object_field_' . $group->id . '_' . $object_id;
	}

	/**
	 * The nonce field name for a group's save.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   FieldGroup $group Group the nonce guards.
	 *
	 * @return  string
	 */
	protected function nonce_name( FieldGroup $group ): string {
		return 'dws_object_field_' . $group->id . '_nonce';
	}

	// endregion
}
