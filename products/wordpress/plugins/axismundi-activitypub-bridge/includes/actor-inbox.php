<?php
/** Actor-addressed transport, using the upstream verifier without rewriting signed requests. @package AxismundiActivityPubBridge */
defined( 'ABSPATH' ) || exit;

/** Resolve explicit local addresses only; Public and attribution never imply delivery to every Actor. */
function axismundi_activitypub_bridge_receipt_targets( array $activity, array $user_ids = array() ) : array {
	$targets = array();
	foreach ( $user_ids as $user_id ) {
		$actor = axismundi_actors_get_for_user( (int) $user_id );
		if ( $actor instanceof Axismundi_Actor && $actor->is_local() && 'public' === $actor->get_status() ) {
			$targets[ $actor->get_identity_id() ] = $actor;
		}
	}
	$addresses = array();
	foreach ( array( $activity, is_array( $activity['object'] ?? null ) ? $activity['object'] : array() ) as $object ) {
		foreach ( array( 'to', 'cc', 'bto', 'bcc', 'audience' ) as $field ) {
			$addresses = array_merge( $addresses, axismundi_activitypub_bridge_member_uris( $object[ $field ] ?? array() ) );
		}
	}
	if ( in_array( $activity['type'] ?? '', array( 'Follow', 'Block' ), true ) ) {
		$addresses = array_merge( $addresses, axismundi_activitypub_bridge_member_uris( $activity['object'] ?? '' ) );
	}
	foreach ( array_unique( $addresses ) as $uri ) {
		// A collection address is not proof of an individual historical receipt.
		$actor = axismundi_actors_get_by_uri( $uri );
		if ( $actor instanceof Axismundi_Actor && $actor->is_local() && 'public' === $actor->get_status() ) {
			$targets[ $actor->get_identity_id() ] = $actor;
		}
	}
	return $targets;
}

function axismundi_activitypub_bridge_actor_inbox_url( Axismundi_Actor $actor ) : string {
	return rest_url( 'axismundi/v1/actors/' . rawurlencode( $actor->get_uuid() ) . '/inbox' );
}

/** Withhold the advertised route when the verifier or recipient store is unavailable. */
function axismundi_activitypub_bridge_actor_inbox_available() : bool {
	return axismundi_activitypub_bridge_ready() && function_exists( 'axismundi_act_get_actor_inbox_page' )
		&& class_exists( 'Activitypub\\Rest\\Inbox_Controller' )
		&& method_exists( 'Activitypub\\Rest\\Inbox_Controller', 'verify_signature' )
		&& AXISMUNDI_ACT_DB_VERSION === (string) get_option( AXISMUNDI_ACT_DB_VERSION_OPTION, '' );
}

/** One route target, never a WordPress-user id or current acting-Actor fallback. */
function axismundi_activitypub_bridge_inbox_route_actor( WP_REST_Request $request ) : ?Axismundi_Actor {
	// Body/query parameters must not override the recipient in the signed URL.
	$actor = axismundi_actors_get_by_uuid( strtolower( (string) ( $request->get_url_params()['uuid'] ?? '' ) ) );
	return $actor instanceof Axismundi_Actor && $actor->is_local() && 'public' === $actor->get_status() ? $actor : null;
}

/** Upstream validates the original HTTP target, digest and signer; no internal REST redispatch. */
function axismundi_activitypub_bridge_verify_actor_inbox( WP_REST_Request $request ) {
	if ( ! axismundi_activitypub_bridge_actor_inbox_available() || ! axismundi_activitypub_bridge_inbox_route_actor( $request ) ) {
		return new WP_Error( 'ax_bridge_inbox_missing', 'The Actor Inbox is unavailable.', array( 'status' => 404 ) );
	}
	$payload = $request->get_json_params();
	if ( ! is_array( $payload ) || array_is_list( $payload )
		|| array_diff( array( 'id', 'type', 'actor', 'object' ), array_keys( $payload ) ) ) {
		return new WP_Error( 'ax_bridge_inbox_body', 'An Activity JSON object is required.', array( 'status' => 400 ) );
	}
	return ( new Activitypub\Rest\Inbox_Controller() )->verify_signature( $request, true );
}

function axismundi_activitypub_bridge_receive_actor_inbox( WP_REST_Request $request ) {
	$actor = axismundi_activitypub_bridge_inbox_route_actor( $request );
	if ( ! $actor || ! axismundi_activitypub_bridge_ready() ) {
		return new WP_Error( 'ax_bridge_inbox_missing', 'The Actor Inbox is unavailable.', array( 'status' => 404 ) );
	}
	$payload = $request->get_json_params();
	$activity = Activitypub\Activity\Activity::init_from_array( $payload );
	if ( is_wp_error( $activity ) || Activitypub\Moderation::activity_is_blocked( $activity, $actor->get_local_user_id() ?? 0 ) ) {
		return new WP_Error( 'ax_bridge_inbox_blocked', 'This delivery is not permitted.', array( 'status' => 403 ) );
	}
	$result = axismundi_activitypub_bridge_record_inbox( $payload, array(), false, $actor );
	axismundi_activitypub_bridge_record_inbox_diagnostic( 'actor-uuid', $payload, $result );
	if ( is_wp_error( $result ) ) {
		return new WP_Error( 'ax_bridge_inbox_store', 'The Activity could not be received.', array( 'status' => 503 ) );
	}
	$response = new WP_REST_Response( null, 202 );
	$response->header( 'Cache-Control', 'private, no-store' );
	return $response;
}

/** Authenticated Account operators only; no implicit site-administrator or remote signer grant. */
function axismundi_activitypub_bridge_read_actor_inbox( WP_REST_Request $request ) {
	$actor = axismundi_activitypub_bridge_inbox_route_actor( $request );
	if ( ! $actor ) {
		return new WP_Error( 'ax_bridge_inbox_missing', 'The Actor Inbox is unavailable.', array( 'status' => 404 ) );
	}
	$page = axismundi_act_get_actor_inbox_page( $actor->get_uri(), 20, (string) $request['cursor'] );
	if ( is_wp_error( $page ) ) {
		return $page;
	}
	$url = axismundi_activitypub_bridge_actor_inbox_url( $actor );
	$data = array( 'id' => $url, 'type' => 'OrderedCollection', 'url' => $url );
	if ( $request['page'] ) {
		$data = array(
			'id' => add_query_arg( array_filter( array( 'page' => 1, 'cursor' => (string) $request['cursor'] ) ), $url ),
			'type' => 'OrderedCollectionPage', 'url' => $url, 'partOf' => $url, 'orderedItems' => $page['items'],
		);
		if ( $page['has_more'] ) {
			$data['next'] = add_query_arg( array( 'page' => 1, 'cursor' => $page['next_cursor'] ), $url );
		}
	} else {
		$data['first'] = add_query_arg( 'page', 1, $url );
	}
	$data = axismundi_op_finalize_object( $data, $data['id'], true );
	if ( is_wp_error( $data ) ) {
		return $data;
	}
	$response = rest_ensure_response( $data );
	$response->header( 'Content-Type', 'application/activity+json; charset=' . get_option( 'blog_charset' ) );
	$response->header( 'Cache-Control', 'private, no-store' );
	$response->header( 'Vary', 'Authorization, Cookie' );
	return $response;
}

/** Borrow the installed upstream POST schema as well as its verifier, not a stale copy. */
function axismundi_activitypub_bridge_register_actor_inbox() : void {
	if ( ! axismundi_activitypub_bridge_actor_inbox_available() ) {
		return;
	}
	$post_args = null;
	foreach ( rest_get_server()->get_routes()['/activitypub/1.0/inbox'] ?? array() as $handler ) {
		if ( ! empty( $handler['methods']['POST'] ) ) {
			$post_args = $handler['args'] ?? null;
			break;
		}
	}
	if ( ! is_array( $post_args ) ) {
		return;
	}
	register_rest_route( 'axismundi/v1', '/actors/(?P<uuid>[0-9a-f-]{36})/inbox', array(
		'args' => array( 'uuid' => array( 'required' => true, 'type' => 'string', 'pattern' => '^[0-9a-f-]{36}$' ) ),
		array( 'methods' => WP_REST_Server::READABLE, 'callback' => 'axismundi_activitypub_bridge_read_actor_inbox', 'permission_callback' => '__return_true',
			'args' => array( 'page' => array( 'type' => 'integer', 'minimum' => 1 ), 'cursor' => array( 'type' => 'string', 'default' => '' ) ) ),
		array( 'methods' => WP_REST_Server::CREATABLE, 'callback' => 'axismundi_activitypub_bridge_receive_actor_inbox',
			'permission_callback' => 'axismundi_activitypub_bridge_verify_actor_inbox', 'args' => $post_args ),
	) );
}
add_action( 'rest_api_init', 'axismundi_activitypub_bridge_register_actor_inbox', 100 );

/** Explicit, bounded backfill. Missing historical route/follower evidence is reported, never invented. */
function axismundi_activitypub_bridge_backfill_inbox( int $after = 0, int $limit = 200 ) {
	global $wpdb;
	if ( AXISMUNDI_ACT_DB_VERSION !== (string) get_option( AXISMUNDI_ACT_DB_VERSION_OPTION, '' ) ) {
		return new WP_Error( 'ax_act_schema', 'The receipt schema is unavailable.' );
	}
	$rows = $wpdb->get_results( $wpdb->prepare(
		"SELECT * FROM %i WHERE direction = 'inbound' AND id > %d ORDER BY id ASC LIMIT %d",
		axismundi_act_activities_table(), max( 0, $after ), max( 1, min( 500, $limit ) )
	), ARRAY_A );
	if ( null === $rows || '' !== $wpdb->last_error ) {
		return new WP_Error( 'ax_bridge_inbox_backfill', 'The historical ledger could not be read.' );
	}
	$report = array( 'after' => $after, 'scanned' => 0, 'without_explicit_target' => 0, 'done' => empty( $rows ) );
	foreach ( $rows as $row ) {
		$activity = axismundi_act_hydrate( $row );
		$targets = axismundi_activitypub_bridge_receipt_targets( $activity->get_payload() );
		if ( empty( $targets ) ) {
			++$report['without_explicit_target'];
		} else {
			$result = axismundi_act_record_activity( $activity->get_payload(), 'inbound', array_keys( $targets ) );
			if ( is_wp_error( $result ) ) {
				return $result;
			}
		}
		$report['after'] = (int) $row['id'];
		++$report['scanned'];
	}
	return $report;
}

if ( defined( 'WP_CLI' ) && WP_CLI ) {
	WP_CLI::add_command( 'axismundi inbox backfill', static function ( $args, $assoc ) : void {
		$result = axismundi_activitypub_bridge_backfill_inbox( (int) ( $assoc['after'] ?? 0 ), (int) ( $assoc['limit'] ?? 200 ) );
		if ( is_wp_error( $result ) ) {
			WP_CLI::error( $result->get_error_message() );
		}
		WP_CLI::line( wp_json_encode( $result ) );
	} );
}
