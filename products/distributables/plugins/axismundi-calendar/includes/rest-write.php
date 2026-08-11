<?php
/**
 * The write half of the Calendar API: my own list, and who may see a Calendar.
 *
 * Two different things are written here and they are deliberately not the same route. A list entry
 * is one Actor's private view of a Calendar -- whether it is shown, what colour, what they call it
 * -- and changing it affects nobody else. An ACL rule is who may read or write the Calendar itself,
 * and changing it affects everyone. Google keeps `CalendarList` and `Acl` apart for exactly this
 * reason, and folding them together would let "hide this from my sidebar" and "remove their access"
 * arrive through one request.
 *
 * A list entry never carries an authorization. Its transitional `access_role` value is never read
 * for permission, so adding a Calendar to your list cannot be a way of claiming a role in it.
 *
 * @package AxismundiCalendar
 */

defined( 'ABSPATH' ) || exit;

/**
 * The Actor writing, or the refusal that says why there is none.
 *
 * @return string|WP_Error
 */
function axismundi_cal_rest_writer_actor() {
	$actor_uri = axismundi_cal_current_actor_uri();
	if ( '' === $actor_uri ) {
		// Signed in, but with no Actor to hold a relation. Nothing here is expressible for them.
		return new WP_Error(
			'ax_cal_no_actor',
			__( 'Your account has no Actor, so it cannot hold calendar relations.', 'axismundi-calendar' ),
			array( 'status' => 409 )
		);
	}
	return $actor_uri;
}

/**
 * `PUT /axismundi/v1/actors/me/calendarList/{uuid}`.
 *
 * Adds the Calendar to the caller's list, or updates how they see it. Creating the entry is what
 * "add a shared calendar to my sidebar" is: the grant already happened, and this is the Actor
 * choosing to look at it.
 *
 * The transitional `access_role` column is not a permission input. The API response computes access
 * from the ACL, and this write only records the caller's personal view state.
 *
 * @param WP_REST_Request $request Request.
 * @return WP_REST_Response|WP_Error
 */
function axismundi_cal_rest_list_write( WP_REST_Request $request ) {
	$calendar = axismundi_cal_rest_readable_calendar( $request );
	if ( is_wp_error( $calendar ) ) {
		return $calendar;
	}
	$actor_uri = axismundi_cal_rest_writer_actor();
	if ( is_wp_error( $actor_uri ) ) {
		return $actor_uri;
	}

	$calendar_id = (int) $calendar['id'];
	$state       = array();
	foreach ( array( 'selected' => 'selected', 'hidden' => 'hidden', 'summaryOverride' => 'summary_override', 'color' => 'color' ) as $from => $to ) {
		if ( null !== $request->get_param( $from ) ) {
			$state[ $to ] = $request->get_param( $from );
		}
	}

	$result = axismundi_cal_list_set( $calendar_id, $actor_uri, 'reader', $state );
	if ( is_wp_error( $result ) ) {
		return $result;
	}
	$entry = axismundi_cal_list_entry( $calendar_id, $actor_uri );
	return new WP_REST_Response( axismundi_cal_rest_calendar( $calendar, is_array( $entry ) ? $entry : array() ), 200 );
}

/**
 * `DELETE /axismundi/v1/actors/me/calendarList/{uuid}`.
 *
 * Removes the Calendar from the caller's list. Their access is untouched: this is the sidebar, not
 * the ACL, and somebody hiding a calendar has not resigned from it.
 *
 * @param WP_REST_Request $request Request.
 * @return WP_REST_Response|WP_Error
 */
function axismundi_cal_rest_list_remove( WP_REST_Request $request ) {
	$calendar = axismundi_cal_rest_readable_calendar( $request );
	if ( is_wp_error( $calendar ) ) {
		return $calendar;
	}
	$actor_uri = axismundi_cal_rest_writer_actor();
	if ( is_wp_error( $actor_uri ) ) {
		return $actor_uri;
	}

	$calendar_id = (int) $calendar['id'];
	$entry       = axismundi_cal_list_entry( $calendar_id, $actor_uri );
	if ( ! is_array( $entry ) ) {
		return new WP_Error( 'ax_cal_no_entry', __( 'That calendar is not in your list.', 'axismundi-calendar' ), array( 'status' => 404 ) );
	}
	axismundi_cal_list_remove( $calendar_id, $actor_uri );
	return new WP_REST_Response( array( 'removed' => true, 'calendar' => (string) $calendar['uuid'] ), 200 );
}

/**
 * Resolve a Calendar the caller may administer, or the refusal.
 *
 * `owner` and nothing less. A writer may add Events; deciding who else may see the Calendar is a
 * different act, and one a writer was never granted.
 *
 * @param WP_REST_Request $request Request.
 * @return array<string,mixed>|WP_Error
 */
function axismundi_cal_rest_administrable_calendar( WP_REST_Request $request ) {
	$calendar = axismundi_cal_calendar_by_uuid( (string) $request['uuid'] );
	if ( ! is_array( $calendar ) ) {
		return new WP_Error( 'ax_cal_not_found', __( 'No calendar was found.', 'axismundi-calendar' ), array( 'status' => 404 ) );
	}
	if ( axismundi_cal_acl_rank( axismundi_cal_request_role( (int) $calendar['id'] ) ) < axismundi_cal_acl_rank( 'owner' ) ) {
		return axismundi_cal_rest_refuse();
	}
	return $calendar;
}

/**
 * One ACL rule as the API reports it.
 *
 * @param array<string,mixed> $rule Rule row.
 * @return array<string,mixed>
 */
function axismundi_cal_rest_acl_rule( array $rule ) : array {
	return array(
		'principalType' => (string) $rule['principal_type'],
		'principal'     => (string) $rule['principal_uri'],
		'role'          => (string) $rule['role'],
	);
}

/**
 * `GET /axismundi/v1/calendars/{uuid}/acl`.
 *
 * @param WP_REST_Request $request Request.
 * @return WP_REST_Response|WP_Error
 */
function axismundi_cal_rest_acl_list( WP_REST_Request $request ) {
	$calendar = axismundi_cal_rest_administrable_calendar( $request );
	if ( is_wp_error( $calendar ) ) {
		return $calendar;
	}
	return new WP_REST_Response(
		array( 'items' => array_map( 'axismundi_cal_rest_acl_rule', axismundi_cal_acl_rules( (int) $calendar['id'] ) ) ),
		200
	);
}

/**
 * `POST /axismundi/v1/calendars/{uuid}/acl`.
 *
 * Every rule the store refuses -- an unknown role, an Actor rule with no Actor, the world as a
 * writer -- is refused by `axismundi_cal_acl_grant()` rather than re-checked here, so the API and
 * any other caller cannot disagree about what a valid rule is.
 *
 * @param WP_REST_Request $request Request.
 * @return WP_REST_Response|WP_Error
 */
function axismundi_cal_rest_acl_grant( WP_REST_Request $request ) {
	$calendar = axismundi_cal_rest_administrable_calendar( $request );
	if ( is_wp_error( $calendar ) ) {
		return $calendar;
	}
	$calendar_id = (int) $calendar['id'];
	$type        = (string) $request['principalType'];
	$principal   = 'public' === $type ? '' : (string) $request['principal'];
	$role        = (string) $request['role'];

	$result = axismundi_cal_acl_grant( $calendar_id, $principal, $role, $type );
	if ( is_wp_error( $result ) ) {
		return $result;
	}
	$rule = axismundi_cal_acl_rule( $calendar_id, $principal, $type );
	return new WP_REST_Response( axismundi_cal_rest_acl_rule( (array) $rule ), 200 );
}

/**
 * `DELETE /axismundi/v1/calendars/{uuid}/acl`.
 *
 * The last owner cannot be removed. A Calendar with no owner is one nobody can share, rename or
 * delete, and the only way back would be a site administrator editing rows -- so the request that
 * would create that state is refused rather than the state being repaired afterwards.
 *
 * @param WP_REST_Request $request Request.
 * @return WP_REST_Response|WP_Error
 */
function axismundi_cal_rest_acl_revoke( WP_REST_Request $request ) {
	$calendar = axismundi_cal_rest_administrable_calendar( $request );
	if ( is_wp_error( $calendar ) ) {
		return $calendar;
	}
	$calendar_id = (int) $calendar['id'];
	$type        = (string) $request['principalType'];
	$principal   = 'public' === $type ? '' : (string) $request['principal'];

	$rule = axismundi_cal_acl_rule( $calendar_id, $principal, $type );
	if ( ! is_array( $rule ) ) {
		return new WP_Error( 'ax_cal_no_rule', __( 'There is no such rule on this calendar.', 'axismundi-calendar' ), array( 'status' => 404 ) );
	}
	if ( 'owner' === (string) $rule['role'] && 1 >= axismundi_cal_acl_owner_count( $calendar_id ) ) {
		return new WP_Error(
			'ax_cal_last_owner',
			__( 'A calendar cannot be left without an owner.', 'axismundi-calendar' ),
			array( 'status' => 409 )
		);
	}

	axismundi_cal_acl_revoke( $calendar_id, $principal, $type );
	return new WP_REST_Response( array( 'revoked' => true ), 200 );
}

/**
 * Register the write routes.
 *
 * @return void
 */
function axismundi_cal_register_write_routes() : void {
	$uuid_pattern = '[0-9a-fA-F-]{36}';
	$signed_in    = static function () {
		if ( is_user_logged_in() ) {
			return true;
		}
		return new WP_Error( 'ax_cal_unauthenticated', __( 'You must be signed in.', 'axismundi-calendar' ), array( 'status' => 401 ) );
	};

	register_rest_route(
		'axismundi/v1',
		'/actors/me/calendarList/(?P<uuid>' . $uuid_pattern . ')',
		array(
			array(
				'methods'             => 'PUT, PATCH',
				'callback'            => 'axismundi_cal_rest_list_write',
				'permission_callback' => $signed_in,
				'args'                => array(
					'selected'        => array( 'type' => 'boolean' ),
					'hidden'          => array( 'type' => 'boolean' ),
					'summaryOverride' => array( 'type' => 'string' ),
					'color'           => array( 'type' => 'string' ),
				),
			),
			array(
				'methods'             => WP_REST_Server::DELETABLE,
				'callback'            => 'axismundi_cal_rest_list_remove',
				'permission_callback' => $signed_in,
			),
		)
	);

	$principal = array(
		'principalType' => array(
			'type'    => 'string',
			'enum'    => array( 'actor', 'public' ),
			'default' => 'actor',
		),
		'principal'     => array( 'type' => 'string', 'default' => '' ),
	);

	register_rest_route(
		'axismundi/v1',
		'/calendars/(?P<uuid>' . $uuid_pattern . ')/acl',
		array(
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => 'axismundi_cal_rest_acl_list',
				'permission_callback' => '__return_true',
			),
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => 'axismundi_cal_rest_acl_grant',
				'permission_callback' => $signed_in,
				'args'                => array_merge(
					$principal,
					array(
						'role' => array(
							'type'     => 'string',
							'required' => true,
							'enum'     => array_keys( AXISMUNDI_CAL_ACL_ROLES ),
						),
					)
				),
			),
			array(
				'methods'             => WP_REST_Server::DELETABLE,
				'callback'            => 'axismundi_cal_rest_acl_revoke',
				'permission_callback' => $signed_in,
				'args'                => $principal,
			),
		)
	);
}
add_action( 'rest_api_init', 'axismundi_cal_register_write_routes' );
