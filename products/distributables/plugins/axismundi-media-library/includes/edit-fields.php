<?php
/**
 * Attachment edit fields for visibility, discovery, rights, and sensitivity.
 *
 * @package AxismundiMediaLibrary
 */

defined( 'ABSPATH' ) || exit;

/**
 * Build a select element for an attachment compatibility field.
 *
 * @param int                  $post_id Attachment ID.
 * @param string               $name    Submitted field name.
 * @param string               $current Current value.
 * @param array<string,string> $options Value-label pairs.
 * @return string
 */
function axismundi_media_attachment_select( int $post_id, string $name, string $current, array $options ) : string {
	$html = '<select name="attachments[' . $post_id . '][' . esc_attr( $name ) . ']">';
	foreach ( $options as $value => $label ) {
		$html .= '<option value="' . esc_attr( $value ) . '"' . selected( $current, $value, false ) . '>' . esc_html( $label ) . '</option>';
	}
	return $html . '</select>';
}

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

	$visibility = axismundi_media_attachment_visibility( $post->ID );
	$listed     = get_post_meta( $post->ID, '_ax_media_listed', true );
	$listed     = ( '' === $listed ) ? '1' : $listed;
	$searchable = get_post_meta( $post->ID, '_ax_media_searchable', true );
	$searchable = ( '' === $searchable ) ? '1' : $searchable;

	$options = array(
		'inherit'  => __( 'Use folder visibility', 'axismundi-media-library' ),
		'public'   => __( 'Public', 'axismundi-media-library' ),
		'unlisted' => __( 'Unlisted', 'axismundi-media-library' ),
		'private'  => __( 'Private', 'axismundi-media-library' ),
	);
	$select = axismundi_media_attachment_select( $post->ID, 'ax_media_visibility', $visibility, $options );
	// Sentinel: proves our field set was submitted, so an absent checkbox on a
	// PARTIAL save payload is not misread as "unchecked".
	$select .= '<input type="hidden" name="attachments[' . (int) $post->ID . '][ax_media_fields]" value="1" />';

	$form_fields['ax_media_visibility'] = array(
		'label' => __( 'Visibility', 'axismundi-media-library' ),
		'input' => 'html',
		'html'  => $select,
		'helps' => __( 'Folder visibility can only narrow this setting. Existing media without a saved setting remains public.', 'axismundi-media-library' ),
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

	// Folder: the single virtual folder this media lives in. The dropdown lists the
	// media owner's (post_author's) folder tree; "Unfiled" is the root.
	$folder_options = array( '0' => __( 'Unfiled', 'axismundi-media-library' ) );
	foreach ( axismundi_media_user_folders( (int) $post->post_author ) as $axismundi_media_folder ) {
		$folder_options[ (string) $axismundi_media_folder['id'] ] = $axismundi_media_folder['name'];
	}
	$form_fields['ax_media_folder'] = array(
		'label' => __( 'Folder', 'axismundi-media-library' ),
		'input' => 'html',
		'html'  => axismundi_media_attachment_select(
			$post->ID,
			'ax_media_folder',
			(string) axismundi_media_attachment_folder( $post->ID ),
			$folder_options
		),
		'helps' => __( 'The single virtual folder this media lives in. Moving it never changes the file URL.', 'axismundi-media-library' ),
	);

	$form_fields['ax_media_creator_name'] = array(
		'label' => __( 'Creator', 'axismundi-media-library' ),
		'input' => 'text',
		'value' => (string) get_post_meta( $post->ID, '_ax_media_creator_name', true ),
	);
	$form_fields['ax_media_creator_url'] = array(
		'label' => __( 'Creator URL', 'axismundi-media-library' ),
		'input' => 'text',
		'value' => (string) get_post_meta( $post->ID, '_ax_media_creator_url', true ),
	);
	$form_fields['ax_media_copyright_holder_name'] = array(
		'label' => __( 'Copyright holder', 'axismundi-media-library' ),
		'input' => 'text',
		'value' => (string) get_post_meta( $post->ID, '_ax_media_copyright_holder_name', true ),
	);
	$form_fields['ax_media_copyright_holder_url'] = array(
		'label' => __( 'Copyright holder URL', 'axismundi-media-library' ),
		'input' => 'text',
		'value' => (string) get_post_meta( $post->ID, '_ax_media_copyright_holder_url', true ),
	);
	$form_fields['ax_media_copyright_notice'] = array(
		'label' => __( 'Copyright notice', 'axismundi-media-library' ),
		'input' => 'text',
		'value' => (string) get_post_meta( $post->ID, '_ax_media_copyright_notice', true ),
	);

	$license = (string) get_post_meta( $post->ID, '_ax_media_license', true );
	$license = '' === $license ? 'all-rights-reserved' : $license;
	$form_fields['ax_media_license'] = array(
		'label' => __( 'License', 'axismundi-media-library' ),
		'input' => 'html',
		'html'  => axismundi_media_attachment_select(
			$post->ID,
			'ax_media_license',
			$license,
			array(
				'all-rights-reserved' => __( 'All rights reserved', 'axismundi-media-library' ),
				'cc0'                 => 'CC0',
				'cc-by-4.0'           => 'CC BY 4.0',
				'cc-by-sa-4.0'        => 'CC BY-SA 4.0',
				'cc-by-nc-4.0'        => 'CC BY-NC 4.0',
				'cc-by-nc-sa-4.0'     => 'CC BY-NC-SA 4.0',
				'custom'              => __( 'Custom', 'axismundi-media-library' ),
				'unknown'             => __( 'Unknown', 'axismundi-media-library' ),
			)
		),
		'helps' => __( 'Stored metadata only in this release; it does not enforce reuse or downloads.', 'axismundi-media-library' ),
	);
	$form_fields['ax_media_license_url'] = array(
		'label' => __( 'License URL', 'axismundi-media-library' ),
		'input' => 'text',
		'value' => (string) get_post_meta( $post->ID, '_ax_media_license_url', true ),
	);
	$form_fields['ax_media_attribution'] = array(
		'label' => __( 'Attribution', 'axismundi-media-library' ),
		'input' => 'textarea',
		'value' => (string) get_post_meta( $post->ID, '_ax_media_attribution', true ),
	);
	$form_fields['ax_media_source_url'] = array(
		'label' => __( 'Source URL', 'axismundi-media-library' ),
		'input' => 'text',
		'value' => (string) get_post_meta( $post->ID, '_ax_media_source_url', true ),
	);

	$form_fields['ax_media_reuse_policy'] = array(
		'label' => __( 'Reuse policy', 'axismundi-media-library' ),
		'input' => 'html',
		'html'  => axismundi_media_attachment_select(
			$post->ID,
			'ax_media_reuse_policy',
			(string) get_post_meta( $post->ID, '_ax_media_reuse_policy', true ),
			array(
				''        => __( 'Not specified', 'axismundi-media-library' ),
				'denied'  => __( 'No reuse', 'axismundi-media-library' ),
				'save'    => __( 'Saving permitted', 'axismundi-media-library' ),
				'reuse'   => __( 'Reuse permitted', 'axismundi-media-library' ),
			)
		),
	);
	$form_fields['ax_media_download_policy'] = array(
		'label' => __( 'Download policy', 'axismundi-media-library' ),
		'input' => 'html',
		'html'  => axismundi_media_attachment_select(
			$post->ID,
			'ax_media_download_policy',
			(string) get_post_meta( $post->ID, '_ax_media_download_policy', true ),
			array(
				''                => __( 'Not specified', 'axismundi-media-library' ),
				'original'        => __( 'Original file', 'axismundi-media-library' ),
				'derivative-only' => __( 'Derivative only', 'axismundi-media-library' ),
				'disabled'        => __( 'Disabled', 'axismundi-media-library' ),
			)
		),
	);

	$sensitive = get_post_meta( $post->ID, '_ax_media_sensitive', true );
	$form_fields['ax_media_sensitive'] = array(
		'label' => __( 'Sensitive media', 'axismundi-media-library' ),
		'input' => 'html',
		'html'  => '<label><input type="checkbox" name="attachments[' . (int) $post->ID . '][ax_media_sensitive]" value="1"' . checked( $sensitive, '1', false ) . ' /> ' . esc_html__( 'Flag this item as sensitive', 'axismundi-media-library' ) . '</label>',
		'helps' => __( 'Stored metadata only in this release; it does not blur or hide the file.', 'axismundi-media-library' ),
	);
	$form_fields['ax_media_content_warning'] = array(
		'label' => __( 'Content warning', 'axismundi-media-library' ),
		'input' => 'text',
		'value' => (string) get_post_meta( $post->ID, '_ax_media_content_warning', true ),
	);
	$form_fields['ax_media_sensitivity_reason'] = array(
		'label' => __( 'Sensitivity reason', 'axismundi-media-library' ),
		'input' => 'text',
		'value' => (string) get_post_meta( $post->ID, '_ax_media_sensitivity_reason', true ),
	);

	$geo_visibility = (string) get_post_meta( $post->ID, '_ax_media_geo_visibility', true );
	$geo_visibility = '' === $geo_visibility ? 'hidden' : $geo_visibility;
	$form_fields['ax_media_geo_visibility'] = array(
		'label' => __( 'Location visibility', 'axismundi-media-library' ),
		'input' => 'html',
		'html'  => axismundi_media_attachment_select(
			$post->ID,
			'ax_media_geo_visibility',
			$geo_visibility,
			array(
				'public'      => __( 'Public', 'axismundi-media-library' ),
				'approximate' => __( 'Approximate', 'axismundi-media-library' ),
				'hidden'      => __( 'Hidden', 'axismundi-media-library' ),
			)
		),
		'helps' => __( 'This flag does not remove GPS metadata from the original file.', 'axismundi-media-library' ),
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
	// Only act when our field set was actually submitted; other save surfaces
	// pass partial attachment payloads that must not reset listed/searchable.
	if ( empty( $attachment['ax_media_fields'] ) ) {
		return $post;
	}
	$post_id = (int) $post['ID'];
	if ( ! current_user_can( 'edit_post', $post_id ) ) {
		return $post;
	}

	if ( isset( $attachment['ax_media_visibility'] ) ) {
		$value = in_array( $attachment['ax_media_visibility'], array( 'inherit', 'public', 'unlisted', 'private' ), true )
			? $attachment['ax_media_visibility']
			: 'public';
		update_post_meta( $post_id, '_ax_media_visibility', $value );
	}
	// Unchecked checkboxes are absent from $attachment → store '0'.
	update_post_meta( $post_id, '_ax_media_listed', empty( $attachment['ax_media_listed'] ) ? '0' : '1' );
	update_post_meta( $post_id, '_ax_media_searchable', empty( $attachment['ax_media_searchable'] ) ? '0' : '1' );

	// Folder move. We are already inside the edit_post guard (per-attachment right);
	// the target must be the root (0) or a folder the acting user may manage.
	if ( isset( $attachment['ax_media_folder'] ) ) {
		$axismundi_media_target = absint( $attachment['ax_media_folder'] );
		if ( 0 === $axismundi_media_target || ( ! axismundi_media_is_root_term( $axismundi_media_target ) && axismundi_media_can_manage_folder( $axismundi_media_target ) ) ) {
			axismundi_media_set_attachment_folder( $post_id, $axismundi_media_target );
		}
	}

	$text_fields = array(
		'ax_media_creator_name'          => '_ax_media_creator_name',
		'ax_media_copyright_holder_name' => '_ax_media_copyright_holder_name',
		'ax_media_copyright_notice'      => '_ax_media_copyright_notice',
		'ax_media_attribution'           => '_ax_media_attribution',
		'ax_media_content_warning'       => '_ax_media_content_warning',
		'ax_media_sensitivity_reason'    => '_ax_media_sensitivity_reason',
	);
	foreach ( $text_fields as $field => $meta_key ) {
		$value = isset( $attachment[ $field ] ) ? sanitize_textarea_field( $attachment[ $field ] ) : '';
		update_post_meta( $post_id, $meta_key, $value );
	}

	$url_fields = array(
		'ax_media_creator_url'          => '_ax_media_creator_url',
		'ax_media_copyright_holder_url' => '_ax_media_copyright_holder_url',
		'ax_media_license_url'          => '_ax_media_license_url',
		'ax_media_source_url'           => '_ax_media_source_url',
	);
	foreach ( $url_fields as $field => $meta_key ) {
		$value = isset( $attachment[ $field ] ) ? esc_url_raw( $attachment[ $field ] ) : '';
		update_post_meta( $post_id, $meta_key, $value );
	}

	$enum_fields = array(
		'ax_media_license' => array(
			'meta'    => '_ax_media_license',
			'allowed' => array( 'all-rights-reserved', 'cc0', 'cc-by-4.0', 'cc-by-sa-4.0', 'cc-by-nc-4.0', 'cc-by-nc-sa-4.0', 'custom', 'unknown' ),
			'default' => 'all-rights-reserved',
		),
		'ax_media_reuse_policy' => array(
			'meta'    => '_ax_media_reuse_policy',
			'allowed' => array( '', 'denied', 'save', 'reuse' ),
			'default' => '',
		),
		'ax_media_download_policy' => array(
			'meta'    => '_ax_media_download_policy',
			'allowed' => array( '', 'original', 'derivative-only', 'disabled' ),
			'default' => '',
		),
		'ax_media_geo_visibility' => array(
			'meta'    => '_ax_media_geo_visibility',
			'allowed' => array( 'public', 'approximate', 'hidden' ),
			'default' => 'hidden',
		),
	);
	foreach ( $enum_fields as $field => $config ) {
		$value = isset( $attachment[ $field ] ) && in_array( $attachment[ $field ], $config['allowed'], true )
			? $attachment[ $field ]
			: $config['default'];
		update_post_meta( $post_id, $config['meta'], $value );
	}
	update_post_meta( $post_id, '_ax_media_sensitive', empty( $attachment['ax_media_sensitive'] ) ? '0' : '1' );

	return $post;
}
add_filter( 'attachment_fields_to_save', 'axismundi_media_attachment_save', 10, 2 );
