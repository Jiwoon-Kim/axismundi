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
	$key = '';
	if ( class_exists( 'Activitypub\\Collection\\Actors' ) ) {
		$user_id = $actor->get_local_user_id();
		if ( $user_id ) {
			$key = Activitypub\Collection\Actors::get_public_key( $user_id );
		} elseif ( class_exists( 'Activitypub\\Application' ) ) {
			$key = Activitypub\Application::get_public_key();
		} else {
			$key = Activitypub\Collection\Actors::get_public_key( 0 );
		}
	}
	$key = is_string( $key ) ? $key : '';
	/**
	 * Observe or withhold one Actor's projected signing key.
	 *
	 * Returning an empty string forces the fail-closed transport contract, so
	 * operations and regression fixtures can exercise a keyless Actor window.
	 */
	return (string) apply_filters( 'axismundi_activitypub_bridge_actor_public_key', $key, $actor );
}

/** Resolve the official plugin's private key without changing Actor ownership. */
function axismundi_activitypub_bridge_private_key( Axismundi_Actor $actor ) : string {
	if ( ! class_exists( 'Activitypub\\Collection\\Actors' ) ) {
		return '';
	}
	$user_id = $actor->get_local_user_id();
	if ( $user_id ) {
		$key = Activitypub\Collection\Actors::get_private_key( $user_id );
	} elseif ( class_exists( 'Activitypub\\Application' ) ) {
		$key = Activitypub\Application::get_private_key();
	} else {
		$key = Activitypub\Collection\Actors::get_private_key( 0 );
	}
	return is_string( $key ) ? $key : '';
}

/**
 * Supply the Inbox addresses this bridge serves for one local Actor.
 *
 * Which addresses exist is a transport fact; whether an Actor may be advertised at all is
 * the identity registry's decision, and it withholds the whole bundle when no key can be
 * projected.
 *
 * @param array<string,string> $endpoints Endpoints resolved so far.
 * @param Axismundi_Actor      $actor     Local Actor.
 * @return array<string,string>
 */
function axismundi_activitypub_bridge_local_endpoints( array $endpoints, Axismundi_Actor $actor ) : array {
	if ( ! axismundi_activitypub_bridge_ready() || 'public' !== $actor->get_status() ) {
		return $endpoints;
	}
	$endpoints['inbox']        = axismundi_activitypub_bridge_inbox_url( $actor );
	$endpoints['shared_inbox'] = axismundi_activitypub_bridge_shared_inbox_url();
	return $endpoints;
}
add_filter( 'axismundi_actors_local_endpoints', 'axismundi_activitypub_bridge_local_endpoints', 10, 2 );

/**
 * Supply the signing key descriptor the official plugin holds for one local Actor.
 *
 * The private half never leaves that store; this is the half remote servers verify with.
 *
 * @param array|null      $key   Descriptor resolved so far.
 * @param Axismundi_Actor $actor Local Actor.
 * @return array|null
 */
function axismundi_activitypub_bridge_local_public_key( $key, Axismundi_Actor $actor ) {
	if ( ! axismundi_activitypub_bridge_ready() || 'public' !== $actor->get_status() ) {
		return $key;
	}
	$pem = axismundi_activitypub_bridge_public_key( $actor );
	if ( '' === $pem ) {
		return $key;
	}
	$sender = axismundi_activitypub_bridge_sender( $actor );
	return array(
		'id'           => (string) $sender['key_id'],
		'owner'        => $actor->get_uri(),
		'publicKeyPem' => $pem,
	);
}
add_filter( 'axismundi_actors_local_public_key', 'axismundi_activitypub_bridge_local_public_key', 10, 2 );

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

/** Whether a public Create or Update is submitted directly to a known public Group. */
function axismundi_activitypub_bridge_is_direct_group_submission( Axismundi_Activity $activity ) : bool {
	if ( ! in_array( $activity->get_type(), array( 'Create', 'Update' ), true ) ) {
		return false;
	}
	$payload = $activity->get_payload();
	$object  = $payload['object'] ?? null;
	if ( ! is_array( $object ) ) {
		return false;
	}
	$group_uri = axismundi_act_member_uri( $object['audience'] ?? '' );
	$recipients = array_merge( (array) ( $activity->get_audience()['to'] ?? array() ), (array) ( $activity->get_audience()['cc'] ?? array() ) );
	if ( '' === $group_uri || ! in_array( $group_uri, $recipients, true ) ) {
		return false;
	}
	$group = axismundi_actors_get_by_uri( $group_uri );
	return $group instanceof Axismundi_Actor && 'Group' === $group->get_type() && 'public' === $group->get_status();
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
	$actor         = axismundi_actors_get_by_uri( $activity->get_actor_uri() );
	$followers_uri = $actor instanceof Axismundi_Actor && $actor->is_local() && function_exists( 'axismundi_op_actor_followers_url' )
		? axismundi_op_actor_followers_url( $actor )
		: '';
	$addresses_followers = in_array( 'https://www.w3.org/ns/activitystreams#Public', $candidates, true )
		|| in_array( 'as:Public', $candidates, true )
		|| ( '' !== $followers_uri && in_array( $followers_uri, $candidates, true ) );
	/*
	 * A Group submission carries Public for strict threadiverse object
	 * validation, but remains a direct inbox delivery. Its Group decides whether
	 * and how to redistribute it; the author's followers must not receive it.
	 */
	if ( $addresses_followers && ! axismundi_activitypub_bridge_is_direct_group_submission( $activity ) ) {
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

/**
 * Declare ActivityPub in this site's NodeInfo document once it can actually speak it.
 *
 * The document belongs to Axismundi Actors, which knows who lives here and how much they
 * post. Whether the host federates is not something the identity registry can know: it is
 * a transport fact, so the plugin that moves traffic states it. An empty `protocols` list
 * reads as "this host does not federate", which is the correct answer while this bridge is
 * unavailable and the wrong one once it is.
 *
 * Gated on the transport claim, not the representation claim, because publishing readable
 * documents is not the same as being able to send and receive.
 *
 * @param string[] $protocols Declared protocols.
 * @return string[]
 */
function axismundi_activitypub_bridge_nodeinfo_protocols( array $protocols ) : array {
	if ( ! axismundi_activitypub_bridge_ready() || in_array( 'activitypub', $protocols, true ) ) {
		return $protocols;
	}
	$protocols[] = 'activitypub';
	return $protocols;
}
add_filter( 'axismundi_actors_nodeinfo_protocols', 'axismundi_activitypub_bridge_nodeinfo_protocols' );
