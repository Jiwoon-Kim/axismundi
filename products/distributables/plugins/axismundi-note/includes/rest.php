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

/** @var bool Request-local guard while the Note REST controller owns a write. */
$GLOBALS['axismundi_note_rest_write'] = false;

/** Mark internal and HTTP REST writes before wp_insert_post fires its callbacks. */
function axismundi_note_mark_rest_write( $prepared_post, WP_REST_Request $request ) {
	$GLOBALS['axismundi_note_rest_write'] = true;
	return $prepared_post;
}
add_filter( 'rest_pre_insert_' . AXISMUNDI_NOTE_POST_TYPE, 'axismundi_note_mark_rest_write', 1, 2 );

/** Clear the request-local guard even when an additional-field callback fails. */
function axismundi_note_clear_rest_write( $response, array $handler, WP_REST_Request $request ) {
	if ( preg_match( '#^/wp/v2/' . preg_quote( AXISMUNDI_NOTE_POST_TYPE, '#' ) . '(?:/|$)#', $request->get_route() ) ) {
		$GLOBALS['axismundi_note_rest_write'] = false;
	}
	return $response;
}
add_filter( 'rest_request_after_callbacks', 'axismundi_note_clear_rest_write', 100, 3 );

/** Whether a complete Note REST write currently owns persistence. */
function axismundi_note_is_rest_write() : bool {
	return ! empty( $GLOBALS['axismundi_note_rest_write'] ) || wp_is_serving_rest_request();
}

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
				if ( is_wp_error( $result ) ) {
					return $result;
				}
				// The additional-field callback can return a real REST error before
				// rest_after_insert records an Activity, so unresolved recipients never
				// look like a successful federated publication in the editor.
				if ( 'publish' === $post->post_status && function_exists( 'axismundi_note_lifecycle_object' ) ) {
					$ready = axismundi_note_lifecycle_object( $post );
					if ( is_wp_error( $ready ) ) {
						return $ready;
					}
				}
				return true;
			},
			'schema'          => array(
				'type'       => 'object',
				'context'    => array( 'edit' ),
				'properties' => array(
					'visibility'     => array( 'type' => 'string', 'enum' => array( 'public', 'unlisted', 'followers', 'mentioned' ) ),
					'language'       => array( 'type' => 'string' ),
					'inReplyTo'      => array( 'type' => 'string' ),
					'context'        => array( 'type' => 'string' ),
					'quoteTarget'    => array( 'type' => 'string' ),
					'quotePolicy'    => array( 'type' => 'string', 'enum' => array( '', 'anyone', 'followers', 'me' ) ),
					'sensitive'      => array( 'type' => 'boolean' ),
					'contentWarning' => array( 'type' => 'string' ),
					'mentions'       => array( 'type' => 'array', 'items' => array( 'type' => 'string' ) ),
					'attachments'    => array( 'type' => 'array', 'maxItems' => AXISMUNDI_NOTE_ATTACHMENT_MAX_COUNT, 'uniqueItems' => true, 'items' => array( 'type' => 'integer', 'minimum' => 1 ) ),
				),
			),
		)
	);
}
add_action( 'rest_api_init', 'axismundi_note_register_rest_field' );
