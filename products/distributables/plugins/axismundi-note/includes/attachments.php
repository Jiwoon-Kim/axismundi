<?php
/**
 * Optional Media Library relation integration for authored Note attachments.
 *
 * @package AxismundiNote
 */

defined( 'ABSPATH' ) || exit;

const AXISMUNDI_NOTE_ATTACHMENT_PROVIDER  = 'axismundi-note-picker';
const AXISMUNDI_NOTE_ATTACHMENT_MAX_COUNT = 50;

/** Whether the full relation + public descriptor contract is available. */
function axismundi_note_attachments_available() : bool {
	return function_exists( 'axismundi_media_relations_replace' )
		&& function_exists( 'axismundi_media_relations_for_subject' )
		&& function_exists( 'axismundi_op_media_library_available' )
		&& function_exists( 'axismundi_op_media_attachment_descriptor' )
		&& axismundi_op_media_library_available();
}

/** Ordered attachment IDs selected explicitly for one Note. */
function axismundi_note_attachment_ids( int $post_id ) : array {
	if ( ! function_exists( 'axismundi_media_relations_for_subject' ) ) {
		return array();
	}
	$ids = array();
	foreach ( axismundi_media_relations_for_subject( $post_id, AXISMUNDI_NOTE_ATTACHMENT_PROVIDER ) as $row ) {
		if ( 'usage' !== (string) ( $row['relation_kind'] ?? '' )
			|| 'active' !== (string) ( $row['status'] ?? '' )
			|| 'as:attachment' !== (string) ( $row['predicate'] ?? '' ) ) {
			continue;
		}
		$id = (int) ( $row['object_attachment_id'] ?? 0 );
		if ( $id > 0 && ! isset( $ids[ $id ] ) ) {
			$ids[ $id ] = $id;
		}
	}
	return array_values( $ids );
}

/**
 * Validate an explicit picker list without mutating existing relations.
 *
 * @return int[]|WP_Error
 */
function axismundi_note_validate_attachment_ids( int $post_id, $value ) {
	if ( ! is_array( $value ) || count( $value ) > AXISMUNDI_NOTE_ATTACHMENT_MAX_COUNT ) {
		return new WP_Error( 'ax_note_attachments', __( 'A Note requires a bounded attachment list.', 'axismundi-note' ) );
	}
	$ids = array();
	foreach ( $value as $candidate ) {
		if ( ! is_int( $candidate ) && ! ( is_string( $candidate ) && ctype_digit( $candidate ) ) ) {
			return new WP_Error( 'ax_note_attachment', __( 'Every Note attachment must be a local Media Library ID.', 'axismundi-note' ) );
		}
		$id         = (int) $candidate;
		$attachment = $id > 0 ? get_post( $id ) : null;
		if ( ! $attachment instanceof WP_Post
			|| 'attachment' !== $attachment->post_type
			|| 'trash' === $attachment->post_status
			|| ! current_user_can( 'edit_post', $id ) ) {
			return new WP_Error( 'ax_note_attachment', __( 'A selected attachment is unavailable or cannot be used.', 'axismundi-note' ) );
		}
		if ( null === axismundi_op_media_attachment_descriptor( $attachment ) ) {
			return new WP_Error( 'ax_note_attachment_rendition', __( 'Every Note attachment must have a federatable media rendition.', 'axismundi-note' ) );
		}
		$ids[ $id ] = $id;
	}
	if ( $ids && ! axismundi_note_attachments_available() ) {
		return new WP_Error( 'ax_note_attachment_provider', __( 'Axismundi Media Library Independent mode is required for Note attachments.', 'axismundi-note' ) );
	}
	$post = get_post( $post_id );
	if ( ! $post instanceof WP_Post || AXISMUNDI_NOTE_POST_TYPE !== $post->post_type ) {
		return new WP_Error( 'ax_note_post', __( 'The attachment subject is not a Note.', 'axismundi-note' ) );
	}
	return array_values( $ids );
}

/** Atomically replace only the Note picker's provider rows. */
function axismundi_note_replace_attachments( int $post_id, array $ids ) {
	if ( ! function_exists( 'axismundi_media_relations_replace' ) ) {
		return $ids
			? new WP_Error( 'ax_note_attachment_provider', __( 'The Media Library relation store is unavailable.', 'axismundi-note' ) )
			: 0;
	}
	$envelope = axismundi_note_get( $post_id );
	$subject  = array(
		'post_id' => $post_id,
		'uri'     => is_array( $envelope ) ? axismundi_note_object_uri( (string) $envelope['local_uuid'] ) : '',
		'type'    => 'post',
	);
	$relations = array();
	foreach ( array_values( $ids ) as $position => $id ) {
		$relations[] = array(
			'object_attachment_id' => (int) $id,
			'predicate'            => 'as:attachment',
			'role'                 => 'content',
			'source_key'           => 'picker:' . $position,
		);
	}
	return axismundi_media_relations_replace( $subject, AXISMUNDI_NOTE_ATTACHMENT_PROVIDER, $relations );
}

/** Supply Note's custom-route visibility to Media Library reverse provenance. */
function axismundi_note_media_relation_can_read_subject( bool $readable, WP_Post $post, int $viewer_id ) : bool {
	if ( $readable || AXISMUNDI_NOTE_POST_TYPE !== $post->post_type ) {
		return $readable;
	}
	$envelope = axismundi_note_get( $post->ID );
	if ( ! is_array( $envelope ) ) {
		return false;
	}
	$source = new Axismundi_Note_Source( $envelope, $post );
	return axismundi_note_source_visible( $source );
}
add_filter( 'axismundi_media_relation_can_read_subject', 'axismundi_note_media_relation_can_read_subject', 10, 3 );
