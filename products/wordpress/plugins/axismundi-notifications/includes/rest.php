<?php
/**
 * Reading one notification back, which is what a push message does not carry.
 *
 * A push says only that something arrived. This is where the app asks what -- signed in, and with
 * the manager question asked again, because a notification opened on a phone is read by whoever is
 * holding the phone and not necessarily by the person who still runs that Actor.
 *
 * @package AxismundiNotifications
 */

defined( 'ABSPATH' ) || exit;

/** @return void */
function axismundi_ntf_register_routes() : void {
	register_rest_route(
		'axismundi/v1',
		'/notifications/(?P<id>\d+)',
		array(
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => 'axismundi_ntf_rest_read',
			'permission_callback' => static function () {
				return is_user_logged_in()
					? true
					: new WP_Error( 'ax_ntf_unauthenticated', __( 'You must be signed in.', 'axismundi-notifications' ), array( 'status' => 401 ) );
			},
			'args'                => array( 'id' => array( 'type' => 'integer', 'required' => true ) ),
		)
	);
}
add_action( 'rest_api_init', 'axismundi_ntf_register_routes' );

/**
 * One notification, if it is theirs to read.
 *
 * @param WP_REST_Request $request Request.
 * @return WP_REST_Response|WP_Error
 */
function axismundi_ntf_rest_read( WP_REST_Request $request ) {
	global $wpdb;
	if ( ! axismundi_ntf_ready() ) {
		return new WP_Error( 'ax_ntf_unavailable', __( 'Notifications is not available.', 'axismundi-notifications' ), array( 'status' => 503 ) );
	}
	$id = (int) $request->get_param( 'id' );
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- primary-key lookup in this plugin's own table.
	$event = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . axismundi_ntf_events_table() . ' WHERE id = %d', $id ), ARRAY_A );
	if ( ! is_array( $event ) ) {
		return new WP_Error( 'ax_ntf_missing', __( 'There is no such notification.', 'axismundi-notifications' ), array( 'status' => 404 ) );
	}
	/*
	 * Asked here and not taken from the push message. Whoever holds the phone opened this; whether
	 * they still read for that Actor is a question about now, and a manager removed this morning must
	 * not learn what the Group was told this afternoon from a notification that reached their device
	 * first.
	 */
	if ( ! axismundi_ntf_can_read_inbox( (int) $event['recipient_actor_id'], get_current_user_id() ) ) {
		return new WP_Error( 'ax_ntf_forbidden', __( 'That notification is not yours to read.', 'axismundi-notifications' ), array( 'status' => 403 ) );
	}
	if ( 'accepted' !== (string) $event['state'] ) {
		// Held for review is read from the requests list, deliberately, and not by following a link.
		return new WP_Error( 'ax_ntf_filtered', __( 'That notification is being held for review.', 'axismundi-notifications' ), array( 'status' => 409 ) );
	}
	return new WP_REST_Response(
		array(
			'id'        => (int) $event['id'],
			'kind'      => (string) $event['kind'],
			'category'  => (string) $event['category'],
			'actor'     => (string) $event['actor_uri'],
			'object'    => (string) $event['object_uri'],
			'occurred'  => (string) $event['occurred_at'],
			// What the resolver saw at the time, which is what makes an entry about a since-deleted
			// Event still read sensibly.
			'snapshot'  => json_decode( (string) $event['snapshot'], true ),
			'inboxUrl'  => axismundi_ntf_admin_url(),
		),
		200
	);
}
