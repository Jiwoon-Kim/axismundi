<?php
/** Limited AP C2S submission adapter. Ledger admission is deliberately broader. */
defined( 'ABSPATH' ) || exit;

/** Authenticate a WP transport without accepting an unverified Authorization header. */
function axismundi_act_outbox_permission( WP_REST_Request $request ) {
	if ( get_current_user_id() <= 0 ) {
		return new WP_Error( 'ax_c2s_authentication', 'Authentication is required.', array( 'status' => 401 ) );
	}
	if ( ! rest_get_authenticated_app_password() && ! wp_verify_nonce( (string) $request->get_header( 'X-WP-Nonce' ), 'wp_rest' ) ) {
		return new WP_Error( 'ax_c2s_nonce', 'Cookie authentication requires a REST nonce.', array( 'status' => 403 ) );
	}
	$actor = axismundi_actors_get_by_uuid( (string) $request->get_url_params()['uuid'] );
	return axismundi_act_outbox_authorize( get_current_user_id(), $actor );
}

/** Revalidate the principal at the service boundary, independently of browser selection. */
function axismundi_act_outbox_authorize( int $user_id, $actor ) {
	if ( $user_id <= 0 || ! $actor instanceof Axismundi_Actor || ! $actor->is_local()
		|| ! axismundi_actors_can_act_as( $actor, $user_id ) ) {
		return new WP_Error( 'ax_c2s_forbidden', 'The principal cannot operate this outbox.', array( 'status' => 403 ) );
	}
	$valid = axismundi_act_validate_reaction_actor( $actor );
	return is_wp_error( $valid ) ? new WP_Error( 'ax_c2s_actor', $valid->get_error_message(), array( 'status' => 403 ) ) : true;
}

/** Compare request intent, not server-generated identity or timestamps. */
function axismundi_act_outbox_same_intent( array $stored, array $submitted ) : bool {
	unset( $stored['id'], $submitted['id'] );
	if ( ! isset( $submitted['published'] ) ) { unset( $stored['published'], $submitted['published'] ); }
	$canonical = static function ( $value ) use ( &$canonical ) {
		if ( is_array( $value ) ) {
			if ( ! array_is_list( $value ) ) { ksort( $value ); }
			$value = array_map( $canonical, $value );
		}
		return $value;
	};
	return wp_json_encode( $canonical( $stored ) ) === wp_json_encode( $canonical( $submitted ) );
}

/** Submit an explicit Like or Undo(Like); never reinterpret Undo as "unlike current". */
function axismundi_act_submit_outbox( int $user_id, Axismundi_Actor $actor, array $payload, string $retry_key = '' ) {
	$allowed = axismundi_act_outbox_authorize( $user_id, $actor );
	if ( is_wp_error( $allowed ) ) { return $allowed; }
	$type = $payload['type'] ?? null;
	if ( ! is_string( $type ) || ! in_array( $type, array( 'Like', 'Undo', 'https://www.w3.org/ns/activitystreams#Like', 'https://www.w3.org/ns/activitystreams#Undo' ), true ) ) {
		return new WP_Error( 'ax_c2s_not_implemented', 'This outbox currently accepts only Like and Undo(Like).', array( 'status' => 422 ) );
	}
	$type = axismundi_act_type( $type );
	if ( ! is_string( $payload['actor'] ?? null ) || ! hash_equals( $actor->get_uri(), $payload['actor'] ) ) {
		return new WP_Error( 'ax_c2s_actor_mismatch', 'The submitted actor must match the outbox Actor.', array( 'status' => 403 ) );
	}
	if ( ! is_string( $payload['object'] ?? null ) || '' === axismundi_act_uri( $payload['object'] ) || isset( $payload['content'] ) ) {
		return new WP_Error( 'ax_c2s_object', 'This slice requires a URI object and a plain Like or Undo.', array( 'status' => 400 ) );
	}
	if ( '' !== $retry_key && ! preg_match( '/^[\x21-\x7e]{1,128}$/D', $retry_key ) ) {
		return new WP_Error( 'ax_c2s_retry_key', 'Idempotency-Key must contain 1 to 128 printable ASCII characters.', array( 'status' => 400 ) );
	}
	unset( $payload['id'] ); // AP C2S never adopts a client-supplied Activity identity.
	$payload['type'] = $type;
	return axismundi_act_submission_lock( array( 'source_owner' => 'axismundi-activities', 'source_kind' => 'outbox', 'source_ref' => $actor->get_identity_id() ), static function () use ( $user_id, $actor, $payload, $type, $retry_key ) {
		global $wpdb;
		$allowed = axismundi_act_outbox_authorize( $user_id, $actor );
		if ( is_wp_error( $allowed ) ) { return $allowed; }
		$source = '' === $retry_key ? '' : 'ap-c2s:' . $actor->get_identity_id() . ':' . $user_id . ':' . hash( 'sha256', $retry_key );
		if ( '' !== $source ) {
			$row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM %i WHERE source_event_hash = %s AND source_event_key = %s', axismundi_act_activities_table(), hash( 'sha256', $source ), $source ), ARRAY_A );
			if ( is_array( $row ) ) {
				$old = axismundi_act_hydrate( $row );
				return axismundi_act_outbox_same_intent( $old->get_payload(), $payload ) ? $old
					: new WP_Error( 'ax_c2s_retry_conflict', 'Idempotency-Key already identifies different input.', array( 'status' => 409 ) );
			}
		}
		if ( 'Undo' === $type ) {
			$target = axismundi_act_get( $payload['object'] );
			if ( ! $target instanceof Axismundi_Activity || 'Like' !== $target->get_type() || isset( $target->get_payload()['content'] )
				|| ! hash_equals( $actor->get_uri(), $target->get_actor_uri() ) ) {
				return new WP_Error( 'ax_c2s_undo_target', 'Undo must identify a Like authored by this Actor.', array( 'status' => 403 ) );
			}
		}
		return axismundi_act_record_source_activity( $payload, 'outbound', $source );
	} );
}

/** HTTP adapter: permission uses URL parameters, never JSON-shadowed route parameters. */
function axismundi_act_post_actor_outbox( WP_REST_Request $request ) {
	$permission = axismundi_act_outbox_permission( $request );
	if ( is_wp_error( $permission ) ) { return $permission; }
	if ( strlen( $request->get_body() ) > 1024 * 1024 ) {
		return new WP_Error( 'ax_c2s_size', 'Activity exceeds the submission size limit.', array( 'status' => 413 ) );
	}
	$content_type = $request->get_content_type();
	if ( ! in_array( $content_type['value'] ?? '', array( 'application/activity+json', 'application/ld+json', 'application/json' ), true ) ) {
		return new WP_Error( 'ax_c2s_media_type', 'An ActivityStreams JSON document is required.', array( 'status' => 415 ) );
	}
	$payload = $request->get_json_params();
	if ( ! is_array( $payload ) || array_is_list( $payload ) ) {
		return new WP_Error( 'ax_c2s_document', 'An Activity document is required.', array( 'status' => 400 ) );
	}
	$actor = axismundi_actors_get_by_uuid( (string) $request->get_url_params()['uuid'] );
	if ( ! $actor instanceof Axismundi_Actor ) {
		return new WP_Error( 'ax_c2s_forbidden', 'The outbox Actor is unavailable.', array( 'status' => 403 ) );
	}
	$activity = axismundi_act_submit_outbox( get_current_user_id(), $actor, $payload, (string) $request->get_header( 'Idempotency-Key' ) );
	if ( is_wp_error( $activity ) ) { return $activity; }
	return new WP_REST_Response( null, 201, array( 'Location' => $activity->get_uri(), 'Cache-Control' => 'no-store' ) );
}

function axismundi_act_register_c2s_outbox() : void {
	register_rest_route( 'axismundi/v1', '/actors/(?P<uuid>[0-9a-f-]{36})/outbox', array(
		'methods' => WP_REST_Server::CREATABLE, 'callback' => 'axismundi_act_post_actor_outbox',
		'permission_callback' => 'axismundi_act_outbox_permission',
	) );
}
add_action( 'rest_api_init', 'axismundi_act_register_c2s_outbox' );

/** Collection disclosure is separate from member content access and Activity audience. */
function axismundi_act_get_liked_collection_page( string $actor_uri, int $before = PHP_INT_MAX, int $limit = 50 ) {
	global $wpdb;
	$actor = axismundi_actors_get_by_uri( $actor_uri );
	if ( ! $actor instanceof Axismundi_Actor || ! $actor->is_local()
		|| 'public' !== axismundi_actors_liked_visibility( $actor ) ) {
		return new WP_Error( 'ax_c2s_liked_private', 'This liked collection is private.', array( 'status' => 403 ) );
	}
	$limit = max( 1, min( 50, $limit ) );
	$rows = $wpdb->get_results( $wpdb->prepare(
		"SELECT object_uri, MAX(id) AS page_id FROM %i WHERE actor_uri_hash=%s AND actor_uri=%s
		AND activity_type='Like' AND reaction_key IS NULL AND effective_status='active'
		AND direction IN ('outbound','local') AND object_uri IS NOT NULL
		GROUP BY object_uri_hash,object_uri HAVING MAX(id)<%d ORDER BY page_id DESC LIMIT %d",
		axismundi_act_activities_table(), hash( 'sha256', $actor_uri ), $actor_uri, $before, $limit + 1
	), ARRAY_A );
	if ( ! is_array( $rows ) || '' !== $wpdb->last_error ) {
		return new WP_Error( 'ax_c2s_liked_read', 'Liked membership is unavailable.', array( 'status' => 503 ) );
	}
	$more = count( $rows ) > $limit;
	$rows = array_slice( $rows, 0, $limit );
	return array( 'items' => array_column( $rows, 'object_uri' ), 'next' => $more ? (int) end( $rows )['page_id'] : null );
}
