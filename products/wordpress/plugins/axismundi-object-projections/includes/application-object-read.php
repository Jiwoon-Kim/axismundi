<?php
/** Session-authorized application reads. Never fetch remote sources. */
defined( 'ABSPATH' ) || exit;

/** Extract exact addressing identities, not Mention tags or display URLs. */
function axismundi_op_application_recipient_uris( $value ) : array {
	if ( is_string( $value ) ) { return array( $value ); }
	if ( ! is_array( $value ) ) { return array(); }
	if ( isset( $value['id'] ) ) { return is_string( $value['id'] ) ? array( $value['id'] ) : array(); }
	$uris = array();
	foreach ( $value as $member ) {
		if ( is_string( $member ) ) { $uris[] = $member; }
		elseif ( is_array( $member ) && is_string( $member['id'] ?? null ) ) { $uris[] = $member['id']; }
	}
	return array_values( array_unique( $uris ) );
}

/** A current Actor must be an author, an explicit recipient, or an accepted follower. */
function axismundi_op_application_actor_can_read( array $document, string $author_uri, Axismundi_Actor $viewer ) : bool {
	$viewer_uri = $viewer->get_uri();
	if ( '' !== $author_uri && $author_uri === $viewer_uri ) { return true; }
	$recipients = array();
	foreach ( array( 'to', 'cc', 'bto', 'bcc' ) as $field ) {
		$recipients = array_merge( $recipients, axismundi_op_application_recipient_uris( $document[ $field ] ?? null ) );
	}
	if ( in_array( $viewer_uri, $recipients, true ) ) { return true; }
	$author = '' !== $author_uri ? axismundi_actors_get_by_uri( $author_uri ) : null;
	$followers = $author ? axismundi_actors_get_endpoint( $author, 'followers' ) : '';
	if ( '' === $followers || ! in_array( $followers, $recipients, true ) || ! function_exists( 'axismundi_act_get_relation' ) ) { return false; }
	$relation = axismundi_act_get_relation( 'follow', $viewer_uri, $author_uri );
	return is_array( $relation ) && 'accepted' === $relation['state'];
}

/** Public first; private cached/local bodies require the server-resolved browser selection. */
function axismundi_op_read_application_object( int $id ) {
	$public = axismundi_op_read_object_jsonld( $id );
	if ( ! is_wp_error( $public ) || 'ax_op_object_read_not_found' !== $public->get_error_code() ) { return $public; }
	$object = axismundi_op_get_object_by_identity( $id );
	if ( ! $object || ! in_array( $object['status'], array( 'public', 'tombstone' ), true ) ) { return $public; }
	$uri = $object['canonical_uri'];
	// Source ownership must match the index; a URI alone does not grant disclosure.
	$local = 'axismundi-object-projections' === $object['source_owner'] && 'local-object' === $object['source_kind'];
	if ( $local ) {
		$row = axismundi_op_get_local_object( $uri );
		if ( ! $row || (int) $row['id'] !== (int) $object['source_ref'] ) { return $public; }
		$document = $row['document'];
		$public_tombstone = 'tombstone' === $row['object_status'] && ! empty( $row['publicly_readable'] );
	} elseif ( 'remote' === $object['origin'] && 'axismundi-object-projections' === $object['source_owner'] && 'remote-object-cache' === $object['source_kind'] ) {
		$source = axismundi_op_load_registered_remote_source( $object, $object );
		if ( ! $source ) { return $public; }
		$row = $source->get_row();
		if ( 'active' !== $row['object_status'] ) { return $public; }
		$document = $row['payload'] ?? array();
		$public_tombstone = false;
	} else {
		// Unpublished Core posts remain unavailable; Actor selection is not edit_post.
		return $public;
	}
	if ( ! $public_tombstone ) {
		if ( ! is_user_logged_in() ) { return $public; }
		$selection = axismundi_actors_resolve_account_selection( get_current_user_id(), null, '', false, true );
		if ( is_wp_error( $selection ) ) {
			return new WP_Error( 'ax_op_actor_selection_required', 'Choose an available Account to read this Object.', array( 'status' => 403 ) );
		}
		if ( ! axismundi_op_application_actor_can_read( $document, (string) ( $row['attributed_to_uri'] ?? '' ), $selection['actor'] ) ) { return $public; }
	}
	if ( $local ) {
		if ( ! in_array( $row['object_status'], array( 'active', 'tombstone' ), true ) ) { return $public; }
		$document = axismundi_op_transform_local_body( new Axismundi_OP_Local_Source( $row ) );
	} else {
		$document = axismundi_op_read_remote_jsonld_source( $source, $object, $object )['document'];
	}
	if ( ( $document['id'] ?? '' ) !== $uri || ( $document['type'] ?? '' ) !== $object['as2_type'] ) {
		return new WP_Error( 'ax_op_object_read_mismatch', 'The source contradicts its registered identity or type.', array( 'status' => 409 ) );
	}
	$document = axismundi_op_clean_object_read_value( $document );
	return axismundi_op_finalize_object( $document, $uri, $local );
}

/** A separate application surface; the public AP and registry read routes stay unchanged. */
add_action( 'rest_api_init', static function () : void {
	register_rest_route( 'axismundi/v1', '/objects/(?P<id>[1-9][0-9]*)', array(
		'methods' => WP_REST_Server::READABLE,
		'permission_callback' => '__return_true',
		'callback' => static function ( WP_REST_Request $request ) {
			$result = axismundi_op_read_application_object( (int) $request['id'] );
			$status = is_array( $result ) && 'Tombstone' === ( $result['type'] ?? '' ) ? 410 : 200;
			if ( is_wp_error( $result ) ) {
				$status = (int) ( $result->get_error_data()['status'] ?? 500 );
				$result = array( 'code' => $result->get_error_code(), 'message' => $result->get_error_message() );
			}
			return new WP_REST_Response( $result, $status, array( 'Cache-Control' => 'private, no-store', 'Vary' => 'Cookie, X-WP-Nonce', 'X-Content-Type-Options' => 'nosniff' ) );
		},
	) );
} );
