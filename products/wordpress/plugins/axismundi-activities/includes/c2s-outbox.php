<?php
/** Limited AP C2S submission adapter. Ledger admission is deliberately broader. */
defined( 'ABSPATH' ) || exit;

/** Authenticate a WP transport without accepting an unverified Authorization header. */
function axismundi_act_outbox_permission( WP_REST_Request $request ) {
	if ( get_current_user_id() <= 0 ) {
		return new WP_Error( 'ax_c2s_authentication', 'Authentication is required.', array( 'status' => 401 ) );
	}
	if ( ! rest_get_authenticated_app_password()
		&& ! wp_verify_nonce( sanitize_text_field( (string) $request->get_header( 'X-WP-Nonce' ) ), 'wp_rest' ) ) {
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
	return 'public' === $actor->get_status() && $actor->is_handle_locked()
		? true : new WP_Error( 'ax_c2s_actor', 'The local Actor must be activated.', array( 'status' => 403 ) );
}

/** Compare request intent, not server-generated identity or timestamps. */
function axismundi_act_outbox_same_intent( array $stored, array $submitted ) : bool {
	unset( $stored['id'], $submitted['id'] );
	if ( isset( $stored['target'] ) && is_string( $stored['target'] ) ) { $stored['target'] = axismundi_actors_collection_target_uri( $stored['target'] ); }
	if ( isset( $submitted['target'] ) && is_string( $submitted['target'] ) ) { $submitted['target'] = axismundi_actors_collection_target_uri( $submitted['target'] ); }
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

/** Submit to the limited C2S surface; referenced posts report replay separately. */
function axismundi_act_submit_outbox( int $user_id, Axismundi_Actor $actor, array $payload, string $retry_key = '', bool &$replayed = false ) {
	$replayed = false;
	$allowed = axismundi_act_outbox_authorize( $user_id, $actor );
	if ( is_wp_error( $allowed ) ) { return $allowed; }
	$type = $payload['type'] ?? null;
	if ( 'Create' === $type && is_string( $payload['object'] ?? null ) ) {
		return axismundi_act_submit_referenced_post( $user_id, $actor, $payload, $replayed );
	}
	if ( 'Create' === $type && is_array( $payload['object'] ?? null ) && 'Question' === ( $payload['object']['type'] ?? null ) ) {
		return axismundi_act_submit_question( $user_id, $actor, $payload, $retry_key );
	}
	if ( in_array( $type, array( 'Add', 'Remove', 'https://www.w3.org/ns/activitystreams#Add', 'https://www.w3.org/ns/activitystreams#Remove' ), true ) ) {
		$group = is_string( $payload['target'] ?? null ) ? axismundi_actors_moderators_group_for_uri( $payload['target'] ) : null;
		if ( $group ) { $payload['type'] = axismundi_act_type( $type ); return axismundi_act_submit_moderators( $user_id, $actor, $group, $payload, $retry_key ); }
		return axismundi_act_submit_featured( $user_id, $actor, $payload, $retry_key );
	}
	if ( in_array( $type, array( 'Note', 'Create', 'Update', 'Delete' ), true ) ) {
		return axismundi_act_submit_local_object( $user_id, $actor, $payload, $retry_key );
	}
	$reaction_actor = axismundi_act_validate_reaction_actor( $actor );
	if ( is_wp_error( $reaction_actor ) ) { return new WP_Error( 'ax_c2s_actor', $reaction_actor->get_error_message(), array( 'status' => 403 ) ); }
	if ( ! is_string( $type ) || ! in_array( $type, array( 'Like', 'Undo', 'https://www.w3.org/ns/activitystreams#Like', 'https://www.w3.org/ns/activitystreams#Undo' ), true ) ) {
		return new WP_Error( 'ax_c2s_not_implemented', 'This outbox accepts Like, Undo(Like), Create(Note), Create(Question), bare Note and local Note edits.', array( 'status' => 422 ) );
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
			$row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM %i WHERE actor_uri_hash=%s AND (source_event_key=%s OR source_event_key LIKE %s)', axismundi_act_activities_table(), hash( 'sha256', $actor->get_uri() ), $source, $wpdb->esc_like( $source . ':' ) . '%' ), ARRAY_A );
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

/** Publish an existing core post without letting the request change its identity or audience. */
function axismundi_act_submit_referenced_post( int $user_id, Axismundi_Actor $actor, array $payload, bool &$replayed ) {
	if ( ! is_string( $payload['actor'] ?? null ) || ! hash_equals( $actor->get_uri(), $payload['actor'] ) ) {
		return new WP_Error( 'ax_c2s_actor_mismatch', 'The submitted actor must match the outbox Actor.', array( 'status' => 403 ) );
	}
	foreach ( array( 'to', 'cc', 'bto', 'bcc', 'audience' ) as $field ) {
		if ( array_key_exists( $field, $payload ) ) {
			return new WP_Error( 'ax_c2s_reference_addressing', 'A referenced post uses its existing audience settings.', array( 'status' => 400 ) );
		}
	}
	$uri = $payload['object'];
	$post = function_exists( 'axismundi_op_resolve_source_by_uri' ) ? axismundi_op_resolve_source_by_uri( $uri ) : null;
	if ( ! $post instanceof WP_Post || 'post' !== $post->post_type || ! hash_equals( axismundi_op_post_object_uri( $post ), $uri ) ) {
		return new WP_Error( 'ax_c2s_reference_source', 'The URI does not identify a supported local post.', array( 'status' => 404 ) );
	}
	if ( ! user_can( $user_id, 'edit_post', $post->ID ) ) {
		return new WP_Error( 'ax_act_c2s_capability', 'This account may not act on that post.', array( 'status' => 403 ) );
	}
	if ( ! axismundi_act_post_lifecycle_applies( $post ) || ! axismundi_op_post_article_visible( $post ) ) {
		return new WP_Error( 'ax_c2s_reference_projection', 'This post is not available for publication.', array( 'status' => 422 ) );
	}
	if ( ! axismundi_op_attribution_lock( $post->ID ) ) {
		return new WP_Error( 'ax_c2s_reference_busy', 'Publication or attribution is in progress. Retry the request.', array( 'status' => 409 ) );
	}
	try {
		$deleted = axismundi_act_has_outbound_delete( $uri );
		$first = axismundi_act_first_outbound_create_row( $uri );
		if ( true === $deleted ) {
			return new WP_Error( 'ax_act_object_deleted', 'A deleted Object URI cannot be created again.', array( 'status' => 409 ) );
		}
		if ( null === $deleted || null === $first ) {
			return new WP_Error( 'ax_act_object_history', 'The Object publication history is unavailable.', array( 'status' => 503 ) );
		}
		if ( $first ) {
			if ( ! hash_equals( $first['actor_uri'], $actor->get_uri() ) ) {
				return new WP_Error( 'ax_act_c2s_attribution', 'This object was created by another Actor.', array( 'status' => 403 ) );
			}
			$replayed = true;
			return axismundi_act_hydrate( $first );
		}
		// Only a new ledger insertion fires this hook, even if another adapter wins the Create race.
		$recorded = null;
		$observe = static function ( Axismundi_Activity $activity ) use ( $uri, &$recorded ): void {
			if ( 'Create' === $activity->get_type() && 'outbound' === $activity->get_direction() && $uri === $activity->get_object_uri() ) {
				$recorded = $activity->get_uri();
			}
		};
		add_action( 'axismundi_act_activity_recorded', $observe );
		try {
			wp_cache_delete( $post->ID, 'posts' );
			$result = axismundi_act_submit_c2s( $user_id, $actor->get_identity_id(), axismundi_act_post_command( $post, 'Create' ), array( 'surface' => 'outbox-reference' ) );
		} finally {
			remove_action( 'axismundi_act_activity_recorded', $observe );
		}
		if ( is_wp_error( $result ) ) {
			$code = $result->get_error_code();
			$status = 'ax_act_c2s_attribution' === $code ? 403 : ( in_array( $code, array( 'ax_act_c2s_stale', 'ax_act_object_deleted' ), true ) ? 409 : 422 );
			$result->add_data( array( 'status' => $status ), $code );
			return $result;
		}
		if ( $result instanceof Axismundi_Activity && $recorded === $result->get_uri() ) {
			return $result;
		}
		// The shared command can return the latest lifecycle event on a concurrent replay.
		$first = axismundi_act_first_outbound_create_row( $uri );
		if ( $first && hash_equals( $first['actor_uri'], $actor->get_uri() ) && false === axismundi_act_has_outbound_delete( $uri ) ) {
			$replayed = true;
			return axismundi_act_hydrate( $first );
		}
		return new WP_Error( 'ax_c2s_reference_conflict', 'No matching Create could be recorded.', array( 'status' => 409 ) );
	} finally {
		axismundi_op_attribution_lock( $post->ID, true );
	}
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
	$replayed = false;
	$activity = axismundi_act_submit_outbox( get_current_user_id(), $actor, $payload, (string) $request->get_header( 'Idempotency-Key' ), $replayed );
	if ( is_wp_error( $activity ) ) { return $activity; }
	$object_uri = in_array( $activity->get_type(), array( 'Create', 'Update' ), true ) ? axismundi_act_member_uri( $activity->get_payload()['object'] ?? null ) : '';
	return new WP_REST_Response( '' !== $object_uri ? array( 'object' => $object_uri ) : null, $replayed ? 200 : 201, array( 'Location' => $activity->get_uri(), 'Cache-Control' => 'no-store' ) );
}

function axismundi_act_register_c2s_outbox() : void {
	foreach ( array( 'axismundi/activitypub', 'axismundi/v1' ) as $namespace ) {
		register_rest_route( $namespace, '/actors/(?P<uuid>[0-9a-f-]{36})/outbox', array(
			'methods' => WP_REST_Server::CREATABLE, 'callback' => 'axismundi_act_post_actor_outbox',
			'permission_callback' => 'axismundi_act_outbox_permission',
		) );
	}
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
		AND direction = 'outbound' AND object_uri IS NOT NULL
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
