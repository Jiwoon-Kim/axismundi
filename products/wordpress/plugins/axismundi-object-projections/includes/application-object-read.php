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
	if ( '' === $followers || ! in_array( axismundi_actors_collection_target_uri( $followers ), array_map( 'axismundi_actors_collection_target_uri', $recipients ), true ) || ! function_exists( 'axismundi_act_get_relation' ) ) { return false; }
	$relation = axismundi_act_get_relation( 'follow', $viewer_uri, $author_uri );
	return is_array( $relation ) && 'accepted' === $relation['state'];
}

/** Select blind recipients from a readable document, based on its own authorship. */
function axismundi_op_application_blind_members( array $document, string $author_field, string $viewer_uri ) : array {
	if ( '' === $viewer_uri || ! in_array( $viewer_uri, axismundi_op_application_recipient_uris( $document[ $author_field ] ?? null ), true ) ) {
		return array();
	}
	return array_intersect_key( $document, array( 'bto' => true, 'bcc' => true ) );
}

/** Explicit private reads are local-only; legacy browser readers retain their selection. */
function axismundi_op_read_application_object( int $id, ?Axismundi_Actor $viewer = null ) {
	$explicit = null !== $viewer;
	$public = axismundi_op_read_object_jsonld( $id );
	if ( is_wp_error( $public ) ? 'ax_op_object_read_not_found' !== $public->get_error_code() : ! $explicit ) { return $public; }
	$object = axismundi_op_get_object_by_identity( $id );
	if ( ! $object || ! in_array( $object['status'], array( 'public', 'tombstone' ), true ) ) { return $public; }
	$uri = $object['canonical_uri'];
	// Source ownership must match the index; a URI alone does not grant disclosure.
	$local = 'axismundi-object-projections' === $object['source_owner'] && 'local-object' === $object['source_kind'];
	$blind = array();
	if ( $local ) {
		$row = axismundi_op_get_local_object( $uri );
		if ( ! $row || (int) $row['id'] !== (int) $object['source_ref'] ) { return $public; }
		$document = $row['document'];
		$public_tombstone = axismundi_op_local_object_is_deleted( $row ) && ! empty( $row['publicly_readable'] );
		if ( $explicit && ! axismundi_op_local_object_is_deleted( $row ) && axismundi_actors_can_act_as( $viewer, get_current_user_id() ) ) {
			$blind = axismundi_op_application_blind_members( $document, 'attributedTo', $viewer->get_uri() );
		}
		if ( ! is_wp_error( $public ) ) {
			return $public + axismundi_op_clean_object_read_value( $blind, 0, true );
		}
	} elseif ( null === $viewer && 'remote' === $object['origin'] && 'axismundi-object-projections' === $object['source_owner'] && 'remote-object-cache' === $object['source_kind'] ) {
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
		if ( null === $viewer ) {
			$selection = axismundi_actors_resolve_account_selection( get_current_user_id(), null, '', false, true );
			if ( is_wp_error( $selection ) ) {
				return new WP_Error( 'ax_op_actor_selection_required', 'Choose an available Account to read this Object.', array( 'status' => 403 ) );
			}
			$viewer = $selection['actor'];
		} elseif ( ! axismundi_actors_can_act_as( $viewer, get_current_user_id() ) ) {
			return $public;
		}
		if ( ! axismundi_op_application_actor_can_read( $document, (string) ( $row['attributed_to_uri'] ?? '' ), $viewer ) ) { return $public; }
	}
	if ( $local ) {
		if ( 'active' !== $row['object_status'] && ! axismundi_op_local_object_is_deleted( $row ) ) { return $public; }
		$document = axismundi_op_transform_local_body( new Axismundi_OP_Local_Source( $row ) );
	} else {
		$document = axismundi_op_read_remote_jsonld_source( $source, $object, $object )['document'];
	}
	if ( ( $document['id'] ?? '' ) !== $uri || ( $document['type'] ?? '' ) !== $object['as2_type'] ) {
		return new WP_Error( 'ax_op_object_read_mismatch', 'The source contradicts its registered identity or type.', array( 'status' => 409 ) );
	}
	$document = axismundi_op_clean_object_read_value( $document + $blind, 0, ! empty( $blind ) );
	return axismundi_op_finalize_object( $document, $uri, $local, $local ? axismundi_op_local_object_is_deleted( $row ) : null );
}

/** Authenticate application reads before resolving any protected identities. */
function axismundi_op_application_read_user( WP_REST_Request $request ) {
	$user = get_current_user_id();
	if ( ! $user ) {
		return new WP_Error( 'ax_op_read_auth', 'Authentication is required.', array( 'status' => 401 ) );
	}
	if ( ! rest_get_authenticated_app_password() && ! wp_verify_nonce( (string) $request->get_header( 'X-WP-Nonce' ), 'wp_rest' ) ) {
		return new WP_Error( 'ax_op_read_auth', 'Cookie authentication requires a REST nonce.', array( 'status' => 403 ) );
	}
	return $user;
}

/** Resolve exactly the requested local Actor, never the browser selection. */
function axismundi_op_application_explicit_actor( WP_REST_Request $request ) {
	$user = axismundi_op_application_read_user( $request );
	if ( is_wp_error( $user ) ) { return $user; }
	$uri = $request->get_query_params()['actor'] ?? null;
	if ( ! is_string( $uri ) || '' === $uri ) {
		return new WP_Error( 'ax_op_read_actor_required', 'Specify the canonical Actor URI in the actor query parameter.', array( 'status' => 400 ) );
	}
	$actor = axismundi_actors_get_by_uri( $uri );
	if ( ! $actor instanceof Axismundi_Actor || ! $actor->is_local() || $actor->get_uri() !== $uri
		|| ! axismundi_actors_can_act_as( $actor, $user ) ) {
		return new WP_Error( 'ax_op_read_actor_forbidden', 'The principal cannot use this Actor.', array( 'status' => 403 ) );
	}
	return $actor;
}

function axismundi_op_application_actor_document( Axismundi_Actor $actor ) {
	$uri = $actor->get_uri();
	$document = axismundi_op_actor_transform( $actor );
	// An unactivated Actor has a canonical URI but may not yet have a profile URL.
	if ( empty( $document['url'] ) ) { $document['url'] = $uri; }
	return axismundi_op_finalize_object( $document, $uri, true );
}

function axismundi_op_application_read_ids( WP_REST_Request $request ) {
	$value = $request->get_query_params()['ids'] ?? null;
	if ( ! is_string( $value ) || strlen( $value ) > 6000 ) {
		return new WP_Error( 'ax_op_read_invalid_ids', 'Supply a comma-separated ids parameter of at most 6000 bytes.', array( 'status' => 400 ) );
	}
	$ids = explode( ',', $value, 21 );
	if ( count( $ids ) > 20 ) {
		return new WP_Error( 'ax_op_read_invalid_ids', 'Request at most 20 IDs.', array( 'status' => 400 ) );
	}
	foreach ( $ids as $uri ) {
		if ( ! preg_match( '~^[a-z][a-z0-9+.-]*:[^\x00-\x20\x7f]+$~iD', $uri ) ) {
			return new WP_Error( 'ax_op_read_invalid_ids', 'Each ID must be a nonempty absolute URI.', array( 'status' => 400 ) );
		}
	}
	return array_unique( $ids );
}

function axismundi_op_application_id_collection( WP_REST_Request $request, string $resource, Closure $read ) {
	$ids = axismundi_op_application_read_ids( $request );
	if ( is_wp_error( $ids ) ) { return $ids; }
	$items = array();
	foreach ( $ids as $uri ) {
		$item = $read( $uri );
		if ( is_wp_error( $item ) ) { return $item; }
		if ( is_array( $item ) ) { $items[] = $item; }
	}
	return axismundi_op_application_collection_response( $resource, $items );
}

function axismundi_op_application_collection_response( string $resource, array $items, ?string $next = null ) : WP_REST_Response {
	$document = array(
		'@context'     => 'https://www.w3.org/ns/activitystreams',
		'type'         => 'OrderedCollection',
		'url'          => rest_url( 'axismundi/activitypub/' . $resource ),
		'orderedItems' => $items,
	);
	if ( null !== $next ) { $document['next'] = $next; }
	return new WP_REST_Response(
		$document,
		200,
		axismundi_op_application_read_headers() + array( 'Content-Type' => 'application/activity+json' )
	);
}

function axismundi_op_application_object_collection( WP_REST_Request $request ) {
	global $wpdb;
	$query = $request->get_query_params();
	$types = array();
	if ( array_key_exists( 'type', $query ) ) {
		$types = is_string( $query['type'] ) ? explode( ',', $query['type'], 21 ) : array( '' );
		if ( count( $types ) > 20 || array_diff( $types, array_keys( axismundi_op_vocabulary_types() ) ) ) {
			return new WP_Error( 'ax_op_object_list_type', 'Supply registered AS2 types with exact spelling.', array( 'status' => 400 ) );
		}
		$types = array_values( array_unique( $types ) );
	}
	$attributed = $query['attributedTo'] ?? null;
	if ( null !== $attributed && ( ! is_string( $attributed ) || ! preg_match( '~^[a-z][a-z0-9+.-]*:[^\x00-\x20\x7f]+$~iD', $attributed ) ) ) {
		return new WP_Error( 'ax_op_object_list_attribution', 'Supply one attributedTo URI.', array( 'status' => 400 ) );
	}
	$cursor = $query['cursor'] ?? null;
	if ( null !== $cursor && ( ! is_string( $cursor ) || ! ctype_digit( $cursor ) || (int) $cursor < 1 || (string) (int) $cursor !== $cursor ) ) {
		return new WP_Error( 'ax_op_object_list_cursor', 'Invalid Object collection cursor.', array( 'status' => 400 ) );
	}
	$viewer = array_key_exists( 'actor', $query ) ? axismundi_op_application_explicit_actor( $request ) : null;
	if ( is_wp_error( $viewer ) ) { return $viewer; }
	$own = null !== $viewer && $viewer->get_uri() === $attributed;
	$where = "status = 'public' AND NOT (source_owner = 'wordpress-core' AND source_kind NOT IN ('post','attachment'))";
	$args = array( axismundi_op_objects_table() );
	if ( null !== $cursor ) {
		$where .= ' AND id < %d';
		$args[] = (int) $cursor;
	}
	if ( null !== $attributed ) {
		$where .= ' AND attributed_to_uri_hash = %s AND attributed_to_uri = %s';
		array_push( $args, hash( 'sha256', $attributed ), $attributed );
	}
	if ( $types ) {
		$where .= ' AND as2_type IN (' . implode( ', ', array_fill( 0, count( $types ), '%s' ) ) . ')';
		array_push( $args, ...$types );
	}
	// Bound work even when most candidates are stale. Continue after the last examined row.
	$ids = $wpdb->get_col( $wpdb->prepare( "SELECT id FROM %i WHERE {$where} ORDER BY id DESC LIMIT 101", $args ) );
	if ( $wpdb->last_error ) {
		return new WP_Error( 'ax_op_object_list_failed', 'Could not query the Object registry.', array( 'status' => 500 ) );
	}
	$items = array();
	$examined = 0;
	$last = null;
	foreach ( $ids as $id ) {
		if ( $examined >= 100 || count( $items ) >= 20 ) { break; }
		++$examined;
		$last = $id;
		$document = $own ? axismundi_op_read_application_object( (int) $id, $viewer ) : axismundi_op_read_object_jsonld( (int) $id );
		if ( ! is_array( $document ) || 'Tombstone' === ( $document['type'] ?? '' )
			|| ( $types && ! in_array( $document['type'] ?? null, $types, true ) )
			|| ( null !== $attributed && ! in_array( $attributed, axismundi_op_application_recipient_uris( $document['attributedTo'] ?? null ), true ) ) ) {
			continue;
		}
		$items[] = $document;
	}
	$next = null;
	if ( $examined < count( $ids ) ) {
		$filters = array_intersect_key( $query, array_flip( array( 'type', 'attributedTo', 'actor' ) ) );
		$next = add_query_arg( array_map( 'rawurlencode', $filters + array( 'cursor' => (string) $last ) ), rest_url( 'axismundi/activitypub/objects' ) );
	}
	return axismundi_op_application_collection_response( 'objects', $items, $next );
}

function axismundi_op_application_read_headers() : array {
	return array( 'Cache-Control' => 'private, no-store', 'Vary' => 'Authorization, Cookie, X-WP-Nonce', 'X-Content-Type-Options' => 'nosniff' );
}

/** A separate application surface; the public AP and registry read routes stay unchanged. */
add_action( 'rest_api_init', static function () : void {
	register_rest_route( 'axismundi/activitypub', '/actors', array(
		'methods' => WP_REST_Server::READABLE,
		'permission_callback' => '__return_true',
		'callback' => static function ( WP_REST_Request $request ) {
			$user = axismundi_op_application_read_user( $request );
			if ( is_wp_error( $user ) ) { return $user; }
			return axismundi_op_application_id_collection( $request, 'actors', static function ( string $uri ) use ( $user ) {
				$actor = axismundi_actors_get_by_uri( $uri );
				// Workspace access permits reading one's draft profile, not submitting an Activity.
				return $actor instanceof Axismundi_Actor && $actor->is_local() && $actor->get_uri() === $uri
					&& axismundi_actors_can_access_workspace( $actor, $user )
					? axismundi_op_application_actor_document( $actor ) : null;
			} );
		},
	) );
	register_rest_route( 'axismundi/activitypub', '/objects', array(
		'methods' => WP_REST_Server::READABLE,
		'permission_callback' => '__return_true',
		'callback' => static function ( WP_REST_Request $request ) {
			$query = $request->get_query_params();
			if ( ! array_key_exists( 'ids', $query ) ) {
				if ( array_key_exists( 'id', $query ) || array_key_exists( 'object', $query ) ) {
					return new WP_Error( 'ax_op_read_invalid_ids', 'Use ids to look up Object URIs.', array( 'status' => 400 ) );
				}
				return axismundi_op_application_object_collection( $request );
			}
			if ( array_intersect_key( $query, array_flip( array( 'type', 'attributedTo', 'cursor' ) ) ) ) {
				return new WP_Error( 'ax_op_object_list_arguments', 'Use ids or collection filters, not both.', array( 'status' => 400 ) );
			}
			$viewer = axismundi_op_application_explicit_actor( $request );
			if ( is_wp_error( $viewer ) ) { return $viewer; }
			return axismundi_op_application_id_collection( $request, 'objects', static function ( string $uri ) use ( $viewer ) {
				$object = axismundi_op_get_object_by_uri( $uri );
				if ( ! $object ) { return null; }
				$result = axismundi_op_read_application_object( (int) $object['id'], $viewer );
				return is_wp_error( $result ) && 404 === ( $result->get_error_data()['status'] ?? null ) ? null : $result;
			} );
		},
	) );
	register_rest_route( 'axismundi/v1', '/objects/(?P<id>[1-9][0-9]*)', array(
		'methods' => WP_REST_Server::READABLE,
		'permission_callback' => '__return_true',
		'callback' => static function ( WP_REST_Request $request ) {
			$id = (int) $request['id'];
			$viewer = array_key_exists( 'actor', $request->get_query_params() ) ? axismundi_op_application_explicit_actor( $request ) : null;
			return axismundi_op_application_object_response( $id, $viewer );
		},
	) );
} );

function axismundi_op_application_object_response( int $id, $viewer ) : WP_REST_Response {
	$result = is_wp_error( $viewer ) ? $viewer : axismundi_op_read_application_object( $id, $viewer );
	$status = 200;
	if ( is_array( $result ) ) {
		$object = axismundi_op_get_object_by_identity( $id );
		if ( 'axismundi-object-projections' === ( $object['source_owner'] ?? null ) && 'local-object' === ( $object['source_kind'] ?? null ) ) {
			$row = axismundi_op_get_local_object( $object['canonical_uri'] );
			$status = ( $row && axismundi_op_local_object_is_deleted( $row ) ) ? 410 : 200;
		} elseif ( 'Tombstone' === ( $result['type'] ?? '' ) ) {
			$status = 410;
		}
	}
	if ( is_wp_error( $result ) ) {
		$status = (int) ( $result->get_error_data()['status'] ?? 500 );
		$result = array( 'code' => $result->get_error_code(), 'message' => $result->get_error_message() );
	}
	return new WP_REST_Response( $result, $status, axismundi_op_application_read_headers() );
}
