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
 * License vocabulary (value => label). The Creative Commons / Public Domain set
 * mirrors Openverse so federation and reuse checks map 1:1; `all-rights-reserved` is
 * kept as the conservative default for a creator's own copyrighted work (Openverse,
 * being an open-media index, has no such value). One source of truth for the field
 * and its save whitelist. CC conditions are derived from the license — there is no
 * separate reuse-policy value (0.0.16).
 *
 * @return array<string,string>
 */
function axismundi_media_license_options() : array {
	return array(
		'all-rights-reserved' => __( 'All rights reserved', 'axismundi-media-library' ),
		'pdm'                 => __( 'Public Domain Mark 1.0', 'axismundi-media-library' ),
		'cc0'                 => 'CC0 1.0',
		'cc-by'               => 'CC BY 4.0',
		'cc-by-sa'            => 'CC BY-SA 4.0',
		'cc-by-nd'            => 'CC BY-ND 4.0',
		'cc-by-nc'            => 'CC BY-NC 4.0',
		'cc-by-nc-sa'         => 'CC BY-NC-SA 4.0',
		'cc-by-nc-nd'         => 'CC BY-NC-ND 4.0',
		'unknown'             => __( 'Unknown', 'axismundi-media-library' ),
	);
}

/**
 * Human labels for relation roles shown in Attachment Details.
 *
 * @return array<string,string>
 */
function axismundi_media_relation_role_labels() : array {
	return array(
		'featured'  => __( 'Featured image', 'axismundi-media-library' ),
		'content'   => __( 'Content', 'axismundi-media-library' ),
		'gallery'   => __( 'Gallery', 'axismundi-media-library' ),
		'cover'     => __( 'Cover', 'axismundi-media-library' ),
		'media_text' => __( 'Media & Text', 'axismundi-media-library' ),
		'file'      => __( 'File', 'axismundi-media-library' ),
		'audio'     => __( 'Audio', 'axismundi-media-library' ),
		'video'     => __( 'Video', 'axismundi-media-library' ),
		'poster'    => __( 'Poster', 'axismundi-media-library' ),
		'decorative' => __( 'Decorative', 'axismundi-media-library' ),
	);
}

/**
 * Read-filtered Used-in list for one Attachment. Multiple roles in one subject are
 * grouped so the source post appears once. Remote subjects are deferred to Phase 7.
 *
 * @param int $attachment_id Attachment ID.
 * @return string Safe HTML.
 */
function axismundi_media_attachment_used_in_html( int $attachment_id ) : string {
	$rows = axismundi_media_relations_used_in( $attachment_id );
	if ( empty( $rows ) ) {
		return '<span class="ax-media-used-in-empty">' . esc_html__( 'No indexed usage.', 'axismundi-media-library' ) . '</span>';
	}

	$groups = array();
	foreach ( $rows as $row ) {
		$post_id = (int) $row['subject_post_id'];
		if ( 'post' !== $row['subject_type'] || $post_id <= 0 ) {
			continue;
		}
		if ( ! isset( $groups[ $post_id ] ) ) {
			$groups[ $post_id ] = array( 'roles' => array() );
		}
		$role = (string) $row['role'];
		$groups[ $post_id ]['roles'][ $role ] = ( $groups[ $post_id ]['roles'][ $role ] ?? 0 ) + (int) $row['occurrence_count'];
	}
	if ( empty( $groups ) ) {
		return '<span class="ax-media-used-in-empty">' . esc_html__( 'No indexed usage.', 'axismundi-media-library' ) . '</span>';
	}

	$role_labels = axismundi_media_relation_role_labels();
	$html        = '<ul class="ax-media-used-in">';
	foreach ( $groups as $post_id => $group ) {
		$post = get_post( (int) $post_id );
		if ( ! $post ) {
			continue;
		}
		$title = '' !== $post->post_title ? $post->post_title : __( '(untitled)', 'axismundi-media-library' );
		$url   = current_user_can( 'edit_post', $post->ID ) ? get_edit_post_link( $post->ID, 'raw' ) : get_permalink( $post );
		$item  = $url
			? '<a href="' . esc_url( $url ) . '">' . esc_html( $title ) . '</a>'
			: esc_html( $title );

		$roles = array();
		foreach ( $group['roles'] as $role => $count ) {
			$label   = $role_labels[ $role ] ?? ucfirst( str_replace( '_', ' ', $role ) );
			$roles[] = $count > 1 ? sprintf( '%1$s ×%2$d', $label, $count ) : $label;
		}
		$html .= '<li>' . $item . ' <span class="ax-media-used-in-roles">(' . esc_html( implode( ', ', $roles ) ) . ')</span></li>';
	}
	return $html . '</ul>';
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
		'label' => __( 'Location', 'axismundi-media-library' ),
		'input' => 'html',
		'html'  => axismundi_media_attachment_select(
			$post->ID,
			'ax_media_folder',
			(string) axismundi_media_attachment_folder( $post->ID ),
			$folder_options
		),
		'helps' => __( 'The single virtual folder this media lives in. Moving it never changes the file URL.', 'axismundi-media-library' ),
	);
	$form_fields['ax_media_used_in'] = array(
		'label' => __( 'Used in', 'axismundi-media-library' ),
		'input' => 'html',
		'html'  => axismundi_media_attachment_used_in_html( $post->ID ),
		'helps' => __( 'Indexed references you can read. Saved collections are shown separately in a later phase.', 'axismundi-media-library' ),
	);
	$used_in_count = axismundi_media_relations_used_in_subject_count( $post->ID );
	if ( $used_in_count > 0 ) {
		$form_fields['ax_media_delete_warning'] = array(
			'label' => __( 'Deletion warning', 'axismundi-media-library' ),
			'input' => 'html',
			'html'  => '<strong class="ax-media-delete-warning">' . esc_html(
				sprintf(
					/* translators: %s: number of readable source items using the media. */
					_n( 'Deleting this file may break %s indexed item.', 'Deleting this file may break %s indexed items.', $used_in_count, 'axismundi-media-library' ),
					number_format_i18n( $used_in_count )
				)
			) . '</strong>',
			'helps' => __( 'Review Used in before deleting permanently. Sources you cannot read are not identified here.', 'axismundi-media-library' ),
		);
	}

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
			axismundi_media_license_options()
		),
		'helps' => __( 'Rights metadata only in this release. Creative Commons conditions are derived from the selected license.', 'axismundi-media-library' ),
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

	// Sensitivity authority (Phase 4a): moderators pick a state; owners get a self-mark
	// checkbox only while it is not locked; a moderator/automated lock is read-only.
	$sensitive_state  = axismundi_media_sensitive_state( $post->ID );
	$sensitive_labels = axismundi_media_sensitive_state_labels();
	if ( current_user_can( 'moderate_media_sensitivity' ) ) {
		$sensitive_html = axismundi_media_attachment_select( $post->ID, 'ax_media_sensitive_state', $sensitive_state, $sensitive_labels );
	} elseif ( ! axismundi_media_sensitive_locked( $post->ID ) && in_array( $sensitive_state, array( 'none', 'self_marked' ), true ) ) {
		$sensitive_html = '<label><input type="checkbox" name="attachments[' . (int) $post->ID . '][ax_media_sensitive]" value="1"' . checked( $sensitive_state, 'self_marked', false ) . ' /> ' . esc_html__( 'Flag this item as sensitive', 'axismundi-media-library' ) . '</label>';
	} else {
		$sensitive_note = 'automated_flagged' === $sensitive_state
			? esc_html__( 'Automatically flagged — you may request review; a moderator can clear it.', 'axismundi-media-library' )
			: esc_html__( 'Set by a moderator — you cannot change this.', 'axismundi-media-library' );
		$sensitive_html = '<strong>' . esc_html( $sensitive_labels[ $sensitive_state ] ?? $sensitive_state ) . '</strong><br /><span class="description">' . $sensitive_note . '</span>';
	}
	$form_fields['ax_media_sensitive'] = array(
		'label' => __( 'Sensitive media', 'axismundi-media-library' ),
		'input' => 'html',
		'html'  => $sensitive_html,
		'helps' => __( 'Owners can self-mark; a moderator lock cannot be cleared by the owner. Stored metadata only in this release.', 'axismundi-media-library' ),
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
		'helps' => __( 'Controls location metadata shown by Axismundi only. Original and derivative file EXIF is never changed and may remain publicly readable.', 'axismundi-media-library' ),
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

	// Attachment Details sends partial payloads. Folder movement is an
	// independent field and must not depend on the visibility-field sentinel.
	if ( isset( $attachment['ax_media_folder'] ) ) {
		$axismundi_media_target = absint( $attachment['ax_media_folder'] );
		if (
			0 === $axismundi_media_target
			|| ( axismundi_media_folder_accepts_attachment( $post_id, $axismundi_media_target ) && axismundi_media_can_manage_folder( $axismundi_media_target ) )
		) {
			axismundi_media_set_attachment_folder( $post_id, $axismundi_media_target );
		}
	}

	// Only act when our field set was actually submitted; other save surfaces
	// pass partial attachment payloads that must not reset listed/searchable.
	if ( empty( $attachment['ax_media_fields'] ) ) {
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
			'allowed' => array_keys( axismundi_media_license_options() ),
			'default' => 'all-rights-reserved',
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
	// Sensitivity authority: route through the enforcing setter (defense in depth —
	// it denies a moderator-only target or a locked change even if the control was
	// bypassed). Moderators submit a state; owners submit the self-mark checkbox.
	if ( array_key_exists( 'ax_media_sensitive_state', $attachment ) ) {
		axismundi_media_set_sensitive_state( $post_id, (string) $attachment['ax_media_sensitive_state'] );
	} elseif ( ! axismundi_media_sensitive_locked( $post_id ) && in_array( axismundi_media_sensitive_state( $post_id ), array( 'none', 'self_marked' ), true ) ) {
		axismundi_media_set_sensitive_state( $post_id, empty( $attachment['ax_media_sensitive'] ) ? 'none' : 'self_marked' );
	}

	return $post;
}
add_filter( 'attachment_fields_to_save', 'axismundi_media_attachment_save', 10, 2 );
