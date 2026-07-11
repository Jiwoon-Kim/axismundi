<?php
/**
 * Attachment edit fields for visibility / listed / searchable (Independent mode).
 * Rights & sensitivity fields (stored-only) land in a later Phase 1 increment.
 *
 * @package AxismundiMediaLibrary
 */

defined( 'ABSPATH' ) || exit;

/**
 * Add the visibility controls to the attachment edit form.
 *
 * @param array   $form_fields Existing fields.
 * @param WP_Post $post        Attachment.
 * @return array
 */
function axismundi_media_attachment_fields( array $form_fields, WP_Post $post ) : array {
	if ( ! axismundi_media_is_independent() ) {
		return $form_fields;
	}

	$visibility = axismundi_media_effective_visibility( $post->ID );
	$listed     = get_post_meta( $post->ID, '_ax_media_listed', true );
	$listed     = ( '' === $listed ) ? '1' : $listed;
	$searchable = get_post_meta( $post->ID, '_ax_media_searchable', true );
	$searchable = ( '' === $searchable ) ? '1' : $searchable;

	$options = array(
		'public'   => __( 'Public', 'axismundi-media-library' ),
		'unlisted' => __( 'Unlisted', 'axismundi-media-library' ),
		'private'  => __( 'Private', 'axismundi-media-library' ),
	);
	$select = '<select name="attachments[' . (int) $post->ID . '][ax_media_visibility]">';
	foreach ( $options as $value => $label ) {
		$select .= '<option value="' . esc_attr( $value ) . '"' . selected( $visibility, $value, false ) . '>' . esc_html( $label ) . '</option>';
	}
	$select .= '</select>';

	$form_fields['ax_media_visibility'] = array(
		'label' => __( 'Visibility', 'axismundi-media-library' ),
		'input' => 'html',
		'html'  => $select,
		'helps' => __( 'Public: listed. Unlisted: reachable by link only. Private: owner/editor only.', 'axismundi-media-library' ),
	);
	$form_fields['ax_media_listed'] = array(
		'label' => __( 'Listed', 'axismundi-media-library' ),
		'input' => 'html',
		'html'  => '<label><input type="checkbox" name="attachments[' . (int) $post->ID . '][ax_media_listed]" value="1"' . checked( $listed, '1', false ) . ' /> ' . esc_html__( 'Show in media archives', 'axismundi-media-library' ) . '</label>',
	);
	$form_fields['ax_media_searchable'] = array(
		'label' => __( 'Searchable', 'axismundi-media-library' ),
		'input' => 'html',
		'html'  => '<label><input type="checkbox" name="attachments[' . (int) $post->ID . '][ax_media_searchable]" value="1"' . checked( $searchable, '1', false ) . ' /> ' . esc_html__( 'Show in media search', 'axismundi-media-library' ) . '</label>',
	);

	return $form_fields;
}
add_filter( 'attachment_fields_to_edit', 'axismundi_media_attachment_fields', 10, 2 );

/**
 * Persist the visibility controls.
 *
 * @param array $post       Attachment post array being saved.
 * @param array $attachment Submitted attachment fields.
 * @return array
 */
function axismundi_media_attachment_save( array $post, array $attachment ) : array {
	if ( ! axismundi_media_is_independent() ) {
		return $post;
	}
	$post_id = (int) $post['ID'];
	if ( ! current_user_can( 'edit_post', $post_id ) ) {
		return $post;
	}

	if ( isset( $attachment['ax_media_visibility'] ) ) {
		$value = in_array( $attachment['ax_media_visibility'], array( 'public', 'unlisted', 'private' ), true )
			? $attachment['ax_media_visibility']
			: 'public';
		update_post_meta( $post_id, '_ax_media_visibility', $value );
	}
	// Unchecked checkboxes are absent from $attachment → store '0'.
	update_post_meta( $post_id, '_ax_media_listed', empty( $attachment['ax_media_listed'] ) ? '0' : '1' );
	update_post_meta( $post_id, '_ax_media_searchable', empty( $attachment['ax_media_searchable'] ) ? '0' : '1' );

	return $post;
}
add_filter( 'attachment_fields_to_save', 'axismundi_media_attachment_save', 10, 2 );
