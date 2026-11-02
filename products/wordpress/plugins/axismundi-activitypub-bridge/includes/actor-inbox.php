<?php
/** Actor-addressed transport, using the upstream verifier without rewriting signed requests. @package AxismundiActivityPubBridge */
defined( 'ABSPATH' ) || exit;

/** Only recipient-addressed Block controls may bypass the recipient's own Block gate. */
function axismundi_activitypub_bridge_is_block_control( array $payload, Axismundi_Actor $recipient ) : bool {
	$sender = axismundi_act_member_uri( $payload['actor'] ?? '' );
	if ( 'Block' === ( $payload['type'] ?? '' ) ) {
		return '' !== $sender && $recipient->get_uri() === axismundi_act_member_uri( $payload['object'] ?? '' );
	}
	if ( 'Undo' !== ( $payload['type'] ?? '' ) ) {
		return false;
	}
	$uri = axismundi_act_member_uri( $payload['object'] ?? '' );
	$block = '' !== $uri ? axismundi_act_get( $uri ) : null;
	return $block instanceof Axismundi_Activity && 'Block' === $block->get_type()
		&& $sender === $block->get_actor_uri() && $recipient->get_uri() === $block->get_object_uri();
}

/** Internal local transport: resolve the canonical ledger row, not a caller-supplied payload. */
function axismundi_activitypub_bridge_receive_local_activity( string $activity_uri, int $recipient_id ) {
	if ( ! axismundi_activitypub_bridge_ready() ) {
		return new WP_Error( 'ax_bridge_inbox_not_ready', 'The Inbox repositories are unavailable.' );
	}
	$activity = axismundi_act_get( $activity_uri );
	$recipient = axismundi_actors_get_by_identity( $recipient_id );
	$sender = $activity ? axismundi_actors_get_by_uri( $activity->get_actor_uri() ) : null;
	if ( ! $activity instanceof Axismundi_Activity || 'outbound' !== $activity->get_direction()
		|| ! $sender instanceof Axismundi_Actor || ! $sender->is_local() || 'public' !== $sender->get_status()
		|| ! $recipient instanceof Axismundi_Actor || ! $recipient->is_local() || 'public' !== $recipient->get_status()
		|| $sender->get_identity_id() === $recipient_id ) {
		return new WP_Error( 'ax_bridge_inbox_no_target', 'Local delivery requires a local sender and distinct local recipient.' );
	}
	$payload = $activity->get_payload();
	$targets = axismundi_activitypub_bridge_receipt_targets( $payload );
	$addresses = array_merge( ...array_values( $activity->get_audience() ) );
	$followers = function_exists( 'axismundi_op_actor_followers_url' ) ? axismundi_op_actor_followers_url( $sender ) : '';
	$expands_followers = ! axismundi_activitypub_bridge_is_direct_group_submission( $activity )
		&& ( in_array( 'https://www.w3.org/ns/activitystreams#Public', $addresses, true ) || in_array( 'as:Public', $addresses, true ) || ( '' !== $followers && in_array( $followers, $addresses, true ) ) );
	$follow = $expands_followers ? axismundi_act_get_relation( 'follow', $recipient->get_uri(), $sender->get_uri() ) : null;
	$accepted_follower = is_array( $follow ) && 'accepted' === $follow['state'];
	if ( ! isset( $targets[ $recipient_id ] ) && ! $accepted_follower && ! axismundi_activitypub_bridge_is_block_control( $payload, $recipient ) ) {
		return new WP_Error( 'ax_bridge_inbox_no_target', 'The stored Activity does not address this recipient.' );
	}
	$permitted = axismundi_activitypub_bridge_inbox_permitted( $payload, $recipient );
	if ( is_wp_error( $permitted ) ) {
		return $permitted;
	}
	return $permitted ? axismundi_act_receive_local_activity( $activity->get_uri(), $recipient_id )
		: new WP_Error( 'ax_bridge_inbox_blocked', 'This delivery is not permitted.' );
}

/** Retain upstream site/user rules and add the recipient Account's own Block relation. */
function axismundi_activitypub_bridge_inbox_permitted( array $payload, Axismundi_Actor $recipient ) {
	if ( ! class_exists( 'Activitypub\\Moderation' ) || ! function_exists( 'axismundi_act_actor_blocks' ) ) {
		return new WP_Error( 'ax_bridge_inbox_policy', 'The Inbox policy is unavailable.' );
	}
	$activity = Activitypub\Activity\Activity::init_from_array( $payload );
	$binding = axismundi_actors_transport_binding( $recipient );
	if ( is_wp_error( $activity ) || null === $binding ) {
		return new WP_Error( 'ax_bridge_inbox_policy', 'The Inbox policy context is unavailable.' );
	}
	if ( Activitypub\Moderation::activity_is_blocked( $activity, $binding['user_id'] ?? 0 ) ) {
		return false;
	}
	$sender = axismundi_act_member_uri( $payload['actor'] ?? '' );
	if ( axismundi_activitypub_bridge_is_block_control( $payload, $recipient ) ) {
		return true;
	}
	$blocked = axismundi_act_actor_blocks( $recipient->get_uri(), $sender );
	return is_wp_error( $blocked ) ? $blocked : ! $blocked;
}

/** Resolve explicit local addresses only; Public and attribution never imply delivery to every Actor. */
function axismundi_activitypub_bridge_receipt_targets( array $activity, array $user_ids = array() ) : array {
	$targets = array();
	foreach ( $user_ids as $user_id ) {
		$actor = axismundi_actors_get_transport_actor_for_user( (int) $user_id );
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
		|| array_diff( array( 'type', 'actor', 'object' ), array_keys( $payload ) )
		|| ( ! isset( $payload['id'] ) && ! axismundi_activitypub_bridge_featured_control( $payload ) ) ) {
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
	$permitted = axismundi_activitypub_bridge_inbox_permitted( $payload, $actor );
	if ( is_wp_error( $permitted ) ) {
		return new WP_Error( 'ax_bridge_inbox_policy', 'The Inbox policy is unavailable.', array( 'status' => 503 ) );
	}
	if ( ! $permitted ) {
		return new WP_Error( 'ax_bridge_inbox_blocked', 'This delivery is not permitted.', array( 'status' => 403 ) );
	}
	$result = axismundi_activitypub_bridge_record_inbox( $payload, array(), false, $actor );
	axismundi_activitypub_bridge_record_inbox_diagnostic( 'actor-uuid', $payload, $result );
	if ( is_wp_error( $result ) ) {
		if ( str_starts_with( $result->get_error_code(), 'ax_bridge_control_' ) ) {
			$data = $result->get_error_data();
			$data = is_array( $data ) ? $data : array();
			$data['status'] = $data['status'] ?? ( in_array( $result->get_error_code(), array( 'ax_bridge_control_store', 'ax_bridge_control_busy' ), true ) ? 503 : 400 );
			$result->add_data( $data );
			return $result;
		}
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
	// The permission callback still requires id for every non-featured Activity.
	$post_args['id']['required'] = false;
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
