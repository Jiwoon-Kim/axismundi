<?php
/**
 * Structured REST envelope field for the block-editor authoring panel.
 *
 * The React document panel reads and writes one field, `axismundi_note_envelope`.
 * The update callback runs inside the authenticated Note REST save and delegates
 * to the server-authoritative `axismundi_note_save_envelope()`, so validation and
 * authority never move to the client.
 *
 * @package AxismundiNote
 */

defined( 'ABSPATH' ) || exit;

/** Register the single structured envelope field on the Note REST resource. */
function axismundi_note_register_rest_field() : void {
	register_rest_field(
		AXISMUNDI_NOTE_POST_TYPE,
		'axismundi_note_envelope',
		array(
			'get_callback'    => static function ( array $post ) : array {
				return axismundi_note_get_envelope( (int) $post['id'] );
			},
			'update_callback' => static function ( $value, WP_Post $post ) {
				// The field is dormant until the panel sends it; a non-array value
				// (absent) leaves the meta-box authoring path untouched.
				if ( ! is_array( $value ) ) {
					return true;
				}
				if ( ! current_user_can( 'edit_post', $post->ID ) ) {
					return new WP_Error( 'ax_note_forbidden', __( 'You cannot edit this Note.', 'axismundi-note' ), array( 'status' => 403 ) );
				}
				$result = axismundi_note_save_envelope( $post->ID, $value );
				return is_wp_error( $result ) ? $result : true;
			},
			'schema'          => array(
				'type'       => 'object',
				'context'    => array( 'edit' ),
				'properties' => array(
					'visibility'     => array( 'type' => 'string', 'enum' => array( 'public', 'unlisted', 'followers', 'mentioned' ) ),
					'language'       => array( 'type' => 'string' ),
					'inReplyTo'      => array( 'type' => 'string' ),
					'context'        => array( 'type' => 'string' ),
					'sensitive'      => array( 'type' => 'boolean' ),
					'contentWarning' => array( 'type' => 'string' ),
					'mentions'       => array( 'type' => 'array', 'items' => array( 'type' => 'string' ) ),
				),
			),
		)
	);
}
add_action( 'rest_api_init', 'axismundi_note_register_rest_field' );
