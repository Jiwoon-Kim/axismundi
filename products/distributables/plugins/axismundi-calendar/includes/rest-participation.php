<?php
/**
 * Saying you are coming, and being answered, over HTTP.
 *
 * The rule this file exists for is that the Actor is never taken from the request. A body naming who
 * is joining would make impersonation a field: any signed-in user could put somebody else's Actor URI
 * in it and produce a `Join` attributed to them, and the ledger would record it as theirs because
 * nothing downstream can tell the difference. It is resolved from the session instead, so the worst a
 * caller can do is act as themselves.
 *
 * Nothing here decides policy. Eligibility, capacity, the mode and who may answer are settled in the
 * model, and these routes carry the answer out -- a screen that showed the wrong button must not be
 * able to produce a state the model would have refused.
 *
 * Three routes and no reading. What a state change needs over HTTP is knowable now: something has to
 * cause it from a browser, and the shape of that is the same whatever draws the button. What a *list*
 * should contain is not -- an admin screen reads the model in PHP and needs no route at all, and a
 * personal one has to answer for Events on other servers, which is a projection that does not exist
 * yet. Publishing those shapes before either screen exists would be guessing at them, and a guessed
 * endpoint is one that has to be kept working while it is corrected.
 *
 * @package AxismundiCalendar
 */

defined( 'ABSPATH' ) || exit;

/**
 * One participation row as a caller sees it.
 *
 * The Actor's name is looked up rather than stored beside the URI. A copy would be a second answer to
 * what somebody is called, and it would be the stale one the first time they renamed themselves.
 *
 * Activity URIs are included because they are the provenance of the state: a management screen saying
 * "accepted automatically" is reading the difference between an `Accept` the server made and one a
 * person made, and it needs somewhere to read that from.
 *
 * @param array<string,mixed> $row Participation row.
 * @return array<string,mixed>
 */
function axismundi_cal_rest_participation( array $row ) : array {
	$name   = '';
	$handle = '';
	$actor  = function_exists( 'axismundi_actors_get_by_uri' ) ? axismundi_actors_get_by_uri( (string) $row['actor_uri'] ) : null;
	if ( $actor instanceof Axismundi_Actor ) {
		$name   = (string) $actor->get_display_name();
		$handle = (string) $actor->get_preferred_username();
	}
	return array(
		'id'                  => (int) $row['id'],
		'actorUri'            => (string) $row['actor_uri'],
		'actorName'           => $name,
		'actorHandle'         => $handle,
		'state'               => (string) $row['state'],
		'source'              => (string) $row['source'],
		'partstat'            => axismundi_cal_participation_partstat( (string) $row['state'] ),
		'initiatingActivity'  => (string) $row['initiating_activity_uri'],
		'responseActivity'    => (string) ( $row['current_response_activity_uri'] ?? '' ),
		'createdAt'           => (string) $row['created_at'],
		'updatedAt'           => (string) $row['updated_at'],
	);
}

/**
 * The Event a route is about, or an error saying why not.
 *
 * @param WP_REST_Request $request Request.
 * @return int|WP_Error Event post ID.
 */
function axismundi_cal_rest_participation_event( WP_REST_Request $request ) {
	$post_id = (int) $request['event'];
	$post    = get_post( $post_id );
	if ( ! $post instanceof WP_Post || AXISMUNDI_CAL_EVENT_POST_TYPE !== $post->post_type ) {
		return new WP_Error( 'ax_cal_event_missing', __( 'That event does not exist.', 'axismundi-calendar' ), array( 'status' => 404 ) );
	}
	return $post_id;
}

/**
 * The Actor the caller is, or an error.
 *
 * A signed-in user without one is refused rather than quietly served: participation is Actor to Actor
 * on both ends, and there is no half of it a local user id could stand in for.
 *
 * @return string|WP_Error
 */
function axismundi_cal_rest_participation_actor() {
	$actor_uri = axismundi_cal_signed_in_actor_uri();
	if ( '' === $actor_uri ) {
		return new WP_Error(
			'ax_cal_no_actor',
			__( 'Replying needs an actor profile, which your account does not have yet.', 'axismundi-calendar' ),
			array( 'status' => 403 )
		);
	}
	return $actor_uri;
}

/**
 * Ask to come.
 *
 * @param WP_REST_Request $request Request.
 * @return WP_REST_Response|WP_Error
 */
function axismundi_cal_rest_join( WP_REST_Request $request ) {
	$post_id = axismundi_cal_rest_participation_event( $request );
	if ( is_wp_error( $post_id ) ) {
		return $post_id;
	}
	$actor_uri = axismundi_cal_rest_participation_actor();
	if ( is_wp_error( $actor_uri ) ) {
		return $actor_uri;
	}
	$state = axismundi_cal_event_join( $post_id, $actor_uri );
	if ( is_wp_error( $state ) ) {
		return $state;
	}
	return new WP_REST_Response( axismundi_cal_rest_participation( axismundi_cal_event_participation( $post_id, $actor_uri ) ), 201 );
}

/**
 * Take back your own request, granted or not.
 *
 * The HTTP verb is a command shape and says nothing about ActivityStreams: what this records is
 * `Undo(Join)`, never AS2 `Delete`, which means destroying an Object and would be a different claim
 * entirely.
 *
 * It now covers leaving as well as taking a request back, because those turned out to be one act. The
 * guest undoes their own `Join`; the host's `Accept` is not theirs to retract, and `Leave` would be a
 * second verb for what `Undo(Join)` already says. What it still refuses is a request that was turned
 * down or a place somebody was removed from, where the `Join` is spent.
 *
 * @param WP_REST_Request $request Request.
 * @return WP_REST_Response|WP_Error
 */
function axismundi_cal_rest_join_withdraw( WP_REST_Request $request ) {
	$post_id = axismundi_cal_rest_participation_event( $request );
	if ( is_wp_error( $post_id ) ) {
		return $post_id;
	}
	$actor_uri = axismundi_cal_rest_participation_actor();
	if ( is_wp_error( $actor_uri ) ) {
		return $actor_uri;
	}
	$done = axismundi_cal_event_withdraw_join( $post_id, $actor_uri );
	if ( is_wp_error( $done ) ) {
		return $done;
	}
	return new WP_REST_Response( axismundi_cal_rest_participation( axismundi_cal_event_participation( $post_id, $actor_uri ) ), 200 );
}

/**
 * Answer somebody's request.
 *
 * Every condition is re-established here rather than trusted from the caller. The screen only shows
 * approval on a moderated Event, but a screen is a courtesy: the mode, the state and the authority
 * are all checked again, because a request arriving from anywhere else must reach the same answer.
 *
 * @param WP_REST_Request $request  Request.
 * @param string          $decision accept|reject.
 * @return WP_REST_Response|WP_Error
 */
function axismundi_cal_rest_respond( WP_REST_Request $request, string $decision ) {
	$post_id = axismundi_cal_rest_participation_event( $request );
	if ( is_wp_error( $post_id ) ) {
		return $post_id;
	}
	if ( ! axismundi_cal_can_manage_participation( $post_id ) ) {
		return new WP_Error( 'ax_cal_cannot_manage', __( 'Answering requests for this event is not yours to do.', 'axismundi-calendar' ), array( 'status' => 403 ) );
	}
	$envelope = axismundi_cal_event_get( $post_id );
	/*
	 * The mode, checked at the moment of answering. An Event switched to `free` while a queue was open
	 * has already admitted everybody who asked, and approving one of them afterwards would be an
	 * acceptance of a request that no longer needed one.
	 */
	if ( ! is_array( $envelope ) || 'restricted' !== (string) $envelope['join_mode'] ) {
		return new WP_Error( 'ax_cal_not_moderated', __( 'This event does not hold requests for approval.', 'axismundi-calendar' ), array( 'status' => 409 ) );
	}
	$participation = axismundi_cal_participation_by_id( $post_id, (int) $request['participation'] );
	if ( ! is_array( $participation ) ) {
		return new WP_Error( 'ax_cal_participation_missing', __( 'That request is not one of this event\'s.', 'axismundi-calendar' ), array( 'status' => 404 ) );
	}
	$state = axismundi_cal_event_respond_to_join( $post_id, (string) $participation['actor_uri'], $decision );
	if ( is_wp_error( $state ) ) {
		return $state;
	}
	return new WP_REST_Response( axismundi_cal_rest_participation( axismundi_cal_participation_by_id( $post_id, (int) $participation['id'] ) ), 200 );
}

/**
 * Approve one request.
 *
 * @param WP_REST_Request $request Request.
 * @return WP_REST_Response|WP_Error
 */
function axismundi_cal_rest_accept( WP_REST_Request $request ) {
	return axismundi_cal_rest_respond( $request, 'accept' );
}

/**
 * Turn one request down.
 *
 * @param WP_REST_Request $request Request.
 * @return WP_REST_Response|WP_Error
 */
function axismundi_cal_rest_reject( WP_REST_Request $request ) {
	return axismundi_cal_rest_respond( $request, 'reject' );
}

/**
 * Register the participation routes.
 *
 * Only when the identity plugins are present. Registering a `Join` endpoint on a site that cannot
 * resolve an Actor would answer every call with the same refusal, which is a worse failure than the
 * route not existing: a client would read it as the Event refusing them rather than as a capability
 * the site does not have.
 *
 * @return void
 */
function axismundi_cal_register_participation_routes() : void {
	if ( ! axismundi_cal_federation_ready() || ! axismundi_cal_has_activities() ) {
		return;
	}
	$signed_in = static function () {
		if ( is_user_logged_in() ) {
			return true;
		}
		return new WP_Error( 'ax_cal_unauthenticated', __( 'You must be signed in.', 'axismundi-calendar' ), array( 'status' => 401 ) );
	};
	$event = '/events/(?P<event>\d+)';

	register_rest_route(
		'axismundi/v1',
		$event . '/join',
		array(
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => 'axismundi_cal_rest_join',
				'permission_callback' => $signed_in,
			),
			array(
				'methods'             => WP_REST_Server::DELETABLE,
				'callback'            => 'axismundi_cal_rest_join_withdraw',
				'permission_callback' => $signed_in,
			),
		)
	);

	foreach ( array( 'accept', 'reject' ) as $decision ) {
		register_rest_route(
			'axismundi/v1',
			$event . '/participants/(?P<participation>\d+)/' . $decision,
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => 'axismundi_cal_rest_' . $decision,
					'permission_callback' => $signed_in,
				),
			)
		);
	}

}
add_action( 'rest_api_init', 'axismundi_cal_register_participation_routes' );
