<?php
/**
 * Classic Editor authoring UI for the Note federation envelope.
 *
 * @package AxismundiNote
 */

defined( 'ABSPATH' ) || exit;

/** Register the envelope meta box on the Note editor. */
function axismundi_note_add_meta_box() : void {
	add_meta_box(
		'axismundi-note-envelope',
		__( 'Federation', 'axismundi-note' ),
		'axismundi_note_render_meta_box',
		AXISMUNDI_NOTE_POST_TYPE,
		'side',
		'high'
	);
}
add_action( 'add_meta_boxes_' . AXISMUNDI_NOTE_POST_TYPE, 'axismundi_note_add_meta_box' );

/** Render the envelope authoring fields. */
function axismundi_note_render_meta_box( WP_Post $post ) : void {
	$envelope    = axismundi_note_get( $post->ID );
	$visibility  = is_array( $envelope ) ? (string) $envelope['visibility'] : 'public';
	$language    = is_array( $envelope ) ? (string) $envelope['language_tag'] : '';
	$in_reply_to = is_array( $envelope ) ? (string) $envelope['in_reply_to_uri'] : '';
	$context     = is_array( $envelope ) ? (string) $envelope['context_uri'] : '';
	$sensitive   = is_array( $envelope ) && ! empty( $envelope['is_sensitive'] );
	$warning     = is_array( $envelope ) ? (string) $envelope['content_warning'] : '';
	$mentions    = array();
	if ( is_array( $envelope ) ) {
		$decoded  = json_decode( (string) $envelope['mention_actor_uris_json'], true );
		$mentions = is_array( $decoded ) ? array_map( 'strval', $decoded ) : array();
	}
	$options = array(
		'public'    => __( 'Public', 'axismundi-note' ),
		'unlisted'  => __( 'Quiet public', 'axismundi-note' ),
		'followers' => __( 'Followers', 'axismundi-note' ),
		'mentioned' => __( 'Mentioned only', 'axismundi-note' ),
	);
	wp_nonce_field( 'axismundi_note_envelope', 'axismundi_note_nonce' );
	echo '<p><label for="axismundi_note_visibility"><strong>' . esc_html__( 'Audience', 'axismundi-note' ) . '</strong></label><br />';
	echo '<select id="axismundi_note_visibility" name="axismundi_note_visibility" style="width:100%">';
	foreach ( $options as $value => $label ) {
		printf( '<option value="%s"%s>%s</option>', esc_attr( $value ), selected( $visibility, $value, false ), esc_html( $label ) );
	}
	echo '</select></p>';

	echo '<p><label for="axismundi_note_language"><strong>' . esc_html__( 'Language (BCP-47)', 'axismundi-note' ) . '</strong></label><br />';
	printf( '<input type="text" id="axismundi_note_language" name="axismundi_note_language" maxlength="35" style="width:100%%" value="%s" /></p>', esc_attr( $language ) );

	echo '<p><label for="axismundi_note_in_reply_to"><strong>' . esc_html__( 'In reply to (URI)', 'axismundi-note' ) . '</strong></label><br />';
	printf( '<input type="url" id="axismundi_note_in_reply_to" name="axismundi_note_in_reply_to" style="width:100%%" value="%s" /></p>', esc_attr( $in_reply_to ) );

	echo '<p><label for="axismundi_note_context"><strong>' . esc_html__( 'Context (URI)', 'axismundi-note' ) . '</strong></label><br />';
	printf( '<input type="url" id="axismundi_note_context" name="axismundi_note_context" style="width:100%%" value="%s" /></p>', esc_attr( $context ) );

	echo '<p><label><input type="checkbox" name="axismundi_note_sensitive" value="1"' . checked( $sensitive, true, false ) . ' /> ' . esc_html__( 'Sensitive content', 'axismundi-note' ) . '</label></p>';

	echo '<p><label for="axismundi_note_warning"><strong>' . esc_html__( 'Content warning', 'axismundi-note' ) . '</strong></label><br />';
	printf( '<input type="text" id="axismundi_note_warning" name="axismundi_note_warning" maxlength="500" style="width:100%%" value="%s" /></p>', esc_attr( $warning ) );

	echo '<p><label for="axismundi_note_mentions"><strong>' . esc_html__( 'Mentioned Actor URIs', 'axismundi-note' ) . '</strong></label><br />';
	printf( '<textarea id="axismundi_note_mentions" name="axismundi_note_mentions" rows="3" style="width:100%%">%s</textarea>', esc_textarea( implode( "\n", $mentions ) ) );
	echo '<span class="description">' . esc_html__( 'One Actor URI per line. Body @-mention anchors are merged automatically.', 'axismundi-note' ) . '</span></p>';
}

/** Persist the envelope from an authenticated Note save. */
function axismundi_note_save_meta_box( int $post_id, WP_Post $post ) : void {
	if ( ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE )
		|| wp_is_post_revision( $post_id )
		|| AXISMUNDI_NOTE_POST_TYPE !== $post->post_type
		|| ! isset( $_POST['axismundi_note_nonce'] )
		|| ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['axismundi_note_nonce'] ) ), 'axismundi_note_envelope' )
		|| ! current_user_can( 'edit_post', $post_id )
	) {
		return;
	}
	axismundi_note_save(
		$post_id,
		array(
			'visibility'         => isset( $_POST['axismundi_note_visibility'] ) ? sanitize_text_field( wp_unslash( $_POST['axismundi_note_visibility'] ) ) : 'public',
			'language_tag'       => isset( $_POST['axismundi_note_language'] ) ? sanitize_text_field( wp_unslash( $_POST['axismundi_note_language'] ) ) : '',
			'in_reply_to_uri'    => isset( $_POST['axismundi_note_in_reply_to'] ) ? esc_url_raw( wp_unslash( $_POST['axismundi_note_in_reply_to'] ) ) : '',
			'context_uri'        => isset( $_POST['axismundi_note_context'] ) ? esc_url_raw( wp_unslash( $_POST['axismundi_note_context'] ) ) : '',
			'sensitive'          => ! empty( $_POST['axismundi_note_sensitive'] ),
			'content_warning'    => isset( $_POST['axismundi_note_warning'] ) ? sanitize_text_field( wp_unslash( $_POST['axismundi_note_warning'] ) ) : '',
			'mention_actor_uris' => isset( $_POST['axismundi_note_mentions'] ) ? sanitize_textarea_field( wp_unslash( $_POST['axismundi_note_mentions'] ) ) : '',
		)
	);
}
add_action( 'save_post_' . AXISMUNDI_NOTE_POST_TYPE, 'axismundi_note_save_meta_box', 10, 2 );
