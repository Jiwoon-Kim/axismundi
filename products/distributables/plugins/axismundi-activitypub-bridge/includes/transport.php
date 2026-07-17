<?php
/**
 * Axismundi transport projection and official ActivityPub delivery adapter.
 *
 * @package AxismundiActivityPubBridge
 */

defined( 'ABSPATH' ) || exit;

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
	return array(
		'actor_uri' => $actor->get_uri(),
		'key_id'    => $actor->get_uri() . '#main-key',
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

/** Advertise the canonical ActivityStreams Actor document through WebFinger. */
function axismundi_activitypub_bridge_webfinger_links( array $links, Axismundi_Actor $actor ) : array {
	if ( ! axismundi_activitypub_bridge_ready() || ! $actor->is_local() || 'public' !== $actor->get_status() ) {
		return $links;
	}
	$links[] = array(
		'rel'  => 'self',
		'type' => 'application/activity+json',
		'href' => $actor->get_uri(),
	);
	return $links;
}
add_filter( 'axismundi_actors_webfinger_links', 'axismundi_activitypub_bridge_webfinger_links', 10, 2 );

/**
 * Supply Axismundi local Actors through the official WebFinger controller.
 *
 * The late priority intentionally follows the official pseudo-user resolver. A
 * resource outside the Axismundi namespace is returned untouched so official
 * and third-party Actor providers retain ownership of their own handles.
 *
 * @param mixed  $data     Existing WebFinger result.
 * @param string $resource Requested resource.
 * @return mixed
 */
function axismundi_activitypub_bridge_webfinger_data( $data, string $resource ) {
	if ( ! axismundi_activitypub_bridge_ready() || ! function_exists( 'axismundi_actors_webfinger_descriptor' ) ) {
		return $data;
	}
	$descriptor = axismundi_actors_webfinger_descriptor( $resource );
	return is_wp_error( $descriptor ) ? $data : $descriptor;
}
add_filter( 'webfinger_data', 'axismundi_activitypub_bridge_webfinger_data', 100, 2 );

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
	if ( ! axismundi_activitypub_bridge_ready() || 'outbound' !== $activity->get_direction() ) {
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
	$payload = function_exists( 'axismundi_op_finalize_activity' )
		? axismundi_op_finalize_activity( $activity->get_payload(), $activity->get_uri() )
		: new WP_Error( 'ax_bridge_activity_renderer', __( 'The Activity JSON-LD renderer is unavailable.', 'axismundi-activitypub-bridge' ) );
	if ( is_wp_error( $payload ) ) {
		return;
	}
	axismundi_activitypub_bridge_enqueue_delivery( $payload, axismundi_activitypub_bridge_sender( $actor ), $inboxes );
}
add_action( 'axismundi_act_activity_recorded', 'axismundi_activitypub_bridge_queue_outbound' );
