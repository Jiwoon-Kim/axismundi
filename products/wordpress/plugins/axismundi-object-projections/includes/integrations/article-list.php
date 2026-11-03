<?php
/** Article stream policy. Apply only after the caller has authorized and sanitized the read. */
defined( 'ABSPATH' ) || exit;

/**
 * Select an Article response, without changing storage or recursively trimming nested Objects.
 *
 * This is Axismundi policy, not a FEP-b2b8 requirement or a completeness signal.
 * Attachment inclusion provides descriptors, never permission to fetch or cache their binaries.
 *
 * @return array|WP_Error
 */
function axismundi_op_article_response( array $document, string $representation = 'detail', string $attachments = 'omit' ) {
	if ( ! in_array( $representation, array( 'list', 'detail' ), true ) || ! in_array( $attachments, array( 'omit', 'include' ), true ) ) {
		return new WP_Error( 'ax_op_article_representation', 'Unknown Article response policy.', array( 'status' => 400 ) );
	}
	if ( 'Article' !== ( $document['type'] ?? null ) || 'detail' === $representation ) {
		return $document;
	}
	unset( $document['content'], $document['contentMap'] );
	if ( 'omit' === $attachments ) {
		unset( $document['attachment'] );
	}
	return $document;
}

/** Application list consumers retain exactly the existing read authorization and no-fetch policy. */
function axismundi_op_read_application_article_list( int $id, string $attachments = 'omit' ) {
	$document = axismundi_op_read_application_object( $id );
	return is_wp_error( $document ) ? $document : axismundi_op_article_response( $document, 'list', $attachments );
}
