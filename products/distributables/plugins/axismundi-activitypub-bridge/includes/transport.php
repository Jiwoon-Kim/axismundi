<?php
/**
 * Axismundi transport projection and official ActivityPub delivery adapter.
 *
 * @package AxismundiActivityPubBridge
 */

defined( 'ABSPATH' ) || exit;

/** Bridge-owned outbox URL for one immutable local Actor identity. */
function axismundi_activitypub_bridge_outbox_url( Axismundi_Actor $actor ) : string {
	return rest_url( 'axismundi-activitypub/v1/actors/' . rawurlencode( $actor->get_uuid() ) . '/outbox' );
}

/** Official signature-verified Inbox URL claimed by the Bridge after verification. */
function axismundi_activitypub_bridge_inbox_url( Axismundi_Actor $actor ) : string {
	$user_id = $actor->get_local_user_id();
	if ( $user_id ) {
		return rest_url( 'activitypub/1.0/actors/' . $user_id . '/inbox' );
	}
	return rest_url( 'activitypub/1.0/inbox' );
}

/** The official shared Inbox transport endpoint. */
function axismundi_activitypub_bridge_shared_inbox_url() : string {
	return rest_url( 'activitypub/1.0/inbox' );
}

/** Non-secret descriptor for resolving one local Actor's signing key on demand. */
function axismundi_activitypub_bridge_sender( Axismundi_Actor $actor ) : array {
	$user_id = $actor->get_local_user_id();
	return array(
		'actor_uri'       => $actor->get_uri(),
		'key_id'          => $actor->get_uri() . '#main-key',
		'private_key_ref' => $user_id ? 'activitypub:user:' . $user_id : 'activitypub:application',
	);
}

/** Resolve the official plugin's public key without changing Actor ownership. */
function axismundi_activitypub_bridge_public_key( Axismundi_Actor $actor ) : string {
	if ( ! class_exists( 'Activitypub\\Collection\\Actors' ) || ! class_exists( 'Activitypub\\Application' ) ) {
		return '';
	}
	$user_id = $actor->get_local_user_id();
	$key     = $user_id
		? Activitypub\Collection\Actors::get_public_key( $user_id )
		: Activitypub\Application::get_public_key();
	return is_string( $key ) ? $key : '';
}

/** Add transport-owned fields to the Object Projections-owned Actor document. */
function axismundi_activitypub_bridge_actor_transport_fields( array $fields, Axismundi_Actor $actor ) : array {
	if ( ! axismundi_activitypub_bridge_ready() || ! $actor->is_local() || 'public' !== $actor->get_status() ) {
		return $fields;
	}
	$sender = axismundi_activitypub_bridge_sender( $actor );
	$key    = axismundi_activitypub_bridge_public_key( $actor );
	$fields = array_merge(
		$fields,
		array(
			'inbox'     => axismundi_activitypub_bridge_inbox_url( $actor ),
			'outbox'    => axismundi_activitypub_bridge_outbox_url( $actor ),
			'endpoints' => array( 'sharedInbox' => axismundi_activitypub_bridge_shared_inbox_url() ),
		)
	);
	if ( '' !== $key ) {
		$fields['publicKey'] = array(
			'id'           => $sender['key_id'],
			'owner'        => $actor->get_uri(),
			'publicKeyPem' => $key,
		);
	}
	return $fields;
}
add_filter( 'axismundi_op_actor_transport_fields', 'axismundi_activitypub_bridge_actor_transport_fields', 10, 2 );

/** Register the Bridge-owned Actor outbox route. */
function axismundi_activitypub_bridge_register_routes() : void {
	register_rest_route(
		'axismundi-activitypub/v1',
		'/actors/(?P<uuid>[0-9a-f-]{36})/outbox',
		array(
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => 'axismundi_activitypub_bridge_get_outbox',
			'permission_callback' => '__return_true',
			'args'                => array(
				'uuid' => array( 'required' => true, 'type' => 'string', 'pattern' => '^[0-9a-f-]{36}$' ),
			),
		)
	);
}
add_action( 'rest_api_init', 'axismundi_activitypub_bridge_register_routes' );

/** Public-safe Activity payload, or null when the Activity is not publicly addressed. */
function axismundi_activitypub_bridge_public_activity( Axismundi_Activity $activity ) : ?array {
	$public   = 'https://www.w3.org/ns/activitystreams#Public';
	$audience = $activity->get_audience();
	$visible  = in_array( $public, (array) ( $audience['to'] ?? array() ), true )
		|| in_array( $public, (array) ( $audience['cc'] ?? array() ), true );
	if ( ! $visible ) {
		return null;
	}
	$payload = $activity->get_payload();
	unset( $payload['bto'], $payload['bcc'] );
	return $payload;
}

/** Serve the recent effective outbound Activity ledger as an OrderedCollection. */
function axismundi_activitypub_bridge_get_outbox( WP_REST_Request $request ) {
	$actor = axismundi_actors_get_by_uuid( strtolower( (string) $request['uuid'] ) );
	if ( ! $actor instanceof Axismundi_Actor || ! $actor->is_local() || 'public' !== $actor->get_status() ) {
		return new WP_Error( 'ax_bridge_outbox_not_found', __( 'The Actor outbox was not found.', 'axismundi-activitypub-bridge' ), array( 'status' => 404 ) );
	}
	$items = array();
	foreach ( axismundi_act_get_by_actor( $actor->get_uri(), 200 ) as $activity ) {
		if ( $activity instanceof Axismundi_Activity && 'outbound' === $activity->get_direction() && $activity->is_effective() ) {
			$payload = axismundi_activitypub_bridge_public_activity( $activity );
			if ( is_array( $payload ) ) {
				$items[] = $payload;
			}
		}
	}
	$id         = axismundi_activitypub_bridge_outbox_url( $actor );
	$collection = axismundi_op_finalize_object(
		array(
			'id'           => $id,
			'type'         => 'OrderedCollection',
			'attributedTo' => $actor->get_uri(),
			'url'          => $actor->get_profile_url(),
			'orderedItems' => $items,
		),
		$id
	);
	if ( is_wp_error( $collection ) ) {
		return $collection;
	}
	$response = rest_ensure_response( $collection );
	$response->header( 'Content-Type', 'application/activity+json; charset=' . get_option( 'blog_charset' ) );
	$response->header( 'Cache-Control', 'public, max-age=60' );
	return $response;
}

/** Resolve private signing material only while the official worker is sending. */
function axismundi_activitypub_bridge_resolve_signing_identity( $identity, array $descriptor ) {
	if ( null !== $identity ) {
		return $identity;
	}
	$actor = axismundi_actors_get_by_uri( (string) ( $descriptor['actor_uri'] ?? '' ) );
	if ( ! $actor instanceof Axismundi_Actor || ! $actor->is_local() || 'public' !== $actor->get_status() ) {
		return null;
	}
	$expected = axismundi_activitypub_bridge_sender( $actor );
	if ( $expected !== $descriptor ) {
		return null;
	}
	$user_id = $actor->get_local_user_id();
	$key     = $user_id
		? Activitypub\Collection\Actors::get_private_key( $user_id )
		: Activitypub\Application::get_private_key();
	return is_string( $key ) && '' !== $key
		? array( 'actor_uri' => $actor->get_uri(), 'key_id' => $expected['key_id'], 'private_key' => $key )
		: null;
}
add_filter( 'activitypub_resolve_external_signing_identity', 'axismundi_activitypub_bridge_resolve_signing_identity', 10, 2 );

/** Resolve one cached remote Actor's best Inbox endpoint. */
function axismundi_activitypub_bridge_remote_inbox( string $actor_uri ) : string {
	$actor = axismundi_actors_get_by_uri( $actor_uri );
	if ( ! $actor instanceof Axismundi_Actor || $actor->is_local() || 'tombstone' === $actor->get_status() ) {
		return '';
	}
	$shared = axismundi_actors_get_endpoint( $actor, 'shared_inbox' );
	return '' !== $shared ? $shared : axismundi_actors_get_endpoint( $actor, 'inbox' );
}

/** Derive explicit remote Inbox recipients from one outbound Activity. */
function axismundi_activitypub_bridge_activity_inboxes( Axismundi_Activity $activity ) : array {
	$candidates = array();
	foreach ( $activity->get_audience() as $members ) {
		$candidates = array_merge( $candidates, $members );
	}
	if ( in_array( $activity->get_type(), array( 'Follow', 'Block' ), true ) && $activity->get_object_uri() ) {
		$candidates[] = $activity->get_object_uri();
	}
	if ( in_array( 'https://www.w3.org/ns/activitystreams#Public', $candidates, true ) || in_array( 'as:Public', $candidates, true ) ) {
		$candidates = array_merge( $candidates, axismundi_act_get_followers( $activity->get_actor_uri(), 1000 ) );
	}
	$inboxes = array();
	foreach ( array_unique( $candidates ) as $actor_uri ) {
		$inbox = axismundi_activitypub_bridge_remote_inbox( (string) $actor_uri );
		if ( '' !== $inbox ) {
			$inboxes[] = $inbox;
		}
	}
	return array_values( array_unique( $inboxes ) );
}

/** Queue transport after an outbound Axismundi Activity commits. */
function axismundi_activitypub_bridge_queue_outbound( Axismundi_Activity $activity ) : void {
	if ( ! axismundi_activitypub_bridge_ready() || 'outbound' !== $activity->get_direction() || ! function_exists( 'Activitypub\\deliver_activity' ) ) {
		return;
	}
	$actor = axismundi_actors_get_by_uri( $activity->get_actor_uri() );
	if ( ! $actor instanceof Axismundi_Actor || ! $actor->is_local() || 'public' !== $actor->get_status() ) {
		return;
	}
	$inboxes = axismundi_activitypub_bridge_activity_inboxes( $activity );
	if ( empty( $inboxes ) ) {
		return;
	}
	Activitypub\deliver_activity( $activity->get_payload(), axismundi_activitypub_bridge_sender( $actor ), $inboxes );
}
add_action( 'axismundi_act_activity_recorded', 'axismundi_activitypub_bridge_queue_outbound' );
