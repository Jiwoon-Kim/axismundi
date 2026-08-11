<?php
/**
 * The read API: what one principal may see of the Calendars on this site.
 *
 * Two judgements, never one. `axismundi_cal_is_publicly_readable()` answers what an anonymous
 * request may have and governs the HTML, ICS and ActivityPub surfaces; the effective ACL role
 * answers what a particular principal may have and governs these routes. Collapsing them would
 * either publish shared Calendars or make a reader unable to read their own.
 *
 * `freeBusyReader` is deliberately not enough for anything here. That role exists to say a time is
 * occupied without saying what occupies it, so every endpoint in this file -- all of which return
 * titles, descriptions or both -- requires `reader`. The free/busy surface it is for does not exist
 * yet, and when it does it is a separate route that returns intervals.
 *
 * Refusals distinguish the two askers. An anonymous request for a Calendar it may not read is
 * answered `404`, exactly as a Calendar that does not exist would be, because `403` would confirm
 * there is something there. A request from a signed-in user is answered `403`, since they already
 * know the URL resolves to something and the useful answer is that they lack the role.
 *
 * @package AxismundiCalendar
 */

defined( 'ABSPATH' ) || exit;

/**
 * The widest window one range request may ask for.
 *
 * A year and a day. Wide enough for any calendar view somebody actually renders, narrow enough that
 * an unbounded `start`/`end` cannot turn a recurring rule into an expansion of millions of rows.
 */
const AXISMUNDI_CAL_REST_MAX_WINDOW = 367 * DAY_IN_SECONDS;

/**
 * The strongest role the current request holds on a Calendar.
 *
 * The request's principal is its Actor when it has one and the public otherwise, and the WP user is
 * carried along so a Calendar belonging to a managed Group resolves through that Group's managers.
 * This is the authenticated counterpart to `axismundi_cal_is_publicly_readable()`, and the only
 * thing the routes below ask.
 *
 * @param int $calendar_id Calendar id.
 * @return string Role, or '' for no access at all.
 */
function axismundi_cal_request_role( int $calendar_id ) : string {
	return axismundi_cal_effective_role( $calendar_id, axismundi_cal_current_actor_uri(), get_current_user_id() );
}

/**
 * Whether the current request may read a Calendar's detail.
 *
 * @param int $calendar_id Calendar id.
 * @return bool
 */
function axismundi_cal_request_can_read( int $calendar_id ) : bool {
	return axismundi_cal_acl_rank( axismundi_cal_request_role( $calendar_id ) ) >= axismundi_cal_acl_rank( 'reader' );
}

/**
 * The refusal appropriate to who is asking.
 *
 * @return WP_Error
 */
function axismundi_cal_rest_refuse() : WP_Error {
	if ( is_user_logged_in() ) {
		return new WP_Error(
			'ax_cal_forbidden',
			__( 'You do not have access to that calendar.', 'axismundi-calendar' ),
			array( 'status' => 403 )
		);
	}
	return new WP_Error(
		'ax_cal_not_found',
		__( 'No calendar was found.', 'axismundi-calendar' ),
		array( 'status' => 404 )
	);
}

/**
 * Resolve a Calendar by uuid, or the refusal that hides whether it exists.
 *
 * An unknown uuid and an unreadable one return the same thing to an anonymous caller on purpose:
 * enumeration is exactly what distinguishable answers would allow.
 *
 * @param WP_REST_Request $request Request.
 * @return array<string,mixed>|WP_Error
 */
function axismundi_cal_rest_readable_calendar( WP_REST_Request $request ) {
	$calendar = axismundi_cal_calendar_by_uuid( (string) $request['uuid'] );
	if ( ! is_array( $calendar ) ) {
		// Not found, for everybody. There is nothing here to be forbidden from.
		return new WP_Error( 'ax_cal_not_found', __( 'No calendar was found.', 'axismundi-calendar' ), array( 'status' => 404 ) );
	}
	if ( ! axismundi_cal_request_can_read( (int) $calendar['id'] ) ) {
		return axismundi_cal_rest_refuse();
	}
	return $calendar;
}

/**
 * One Calendar as this request sees it.
 *
 * `accessRole` is computed from the ACL rather than read from a list entry. The entry holds a copy
 * for display, and a copy is what a revoked rule leaves behind -- reporting it would tell a client
 * it may still write to something it may no longer read.
 *
 * The subscription URL appears only for a publicly readable Calendar, because that is the only case
 * where it resolves: handing a private Calendar's `.ics` address to its reader would be handing them
 * a URL that answers `404`.
 *
 * @param array<string,mixed> $calendar Calendar row.
 * @param array<string,mixed> $entry    The asking Actor's list entry, if any.
 * @return array<string,mixed>
 */
function axismundi_cal_rest_calendar( array $calendar, array $entry = array() ) : array {
	$calendar_id = (int) $calendar['id'];
	$public      = axismundi_cal_is_publicly_readable( $calendar_id );

	$out = array(
		'id'          => (string) $calendar['uuid'],
		'summary'     => (string) $calendar['name'],
		'description' => (string) $calendar['description'],
		'timezone'    => axismundi_cal_calendar_timezone( $calendar ),
		'kind'        => (string) $calendar['kind'],
		'accessRole'  => axismundi_cal_request_role( $calendar_id ),
		'public'      => $public,
		'revision'    => (int) $calendar['revision'],
	);
	if ( $public ) {
		$out['url']    = axismundi_cal_calendar_url( $calendar );
		$out['icsUrl'] = axismundi_cal_calendar_ics_url( $calendar );
	}
	if ( array() !== $entry ) {
		/*
		 * The asking Actor's own view of it, which is theirs and not the Calendar's: an alias and a
		 * colour one person chose must not travel to everyone else in the Calendar.
		 */
		$out['selected']        = (bool) $entry['selected'];
		$out['hidden']          = (bool) $entry['hidden'];
		$out['summaryOverride'] = (string) $entry['summary_override'];
		$out['color']           = (string) $entry['color'];
	}
	return $out;
}

/**
 * One occurrence as the API reports it.
 *
 * Both the UTC instant and the Calendar's own local wall time, because neither alone is enough: UTC
 * is what sorts and compares, and the local time is what the event actually claims to happen at.
 * Rendering in the reader's zone is the client's to do, from the instant.
 *
 * @param array<string,mixed> $occurrence Occurrence row.
 * @return array<string,mixed>
 */
function axismundi_cal_rest_occurrence( array $occurrence ) : array {
	return array(
		'eventId'    => (int) $occurrence['post_id'],
		'summary'    => (string) $occurrence['title'],
		'url'        => (string) $occurrence['permalink'],
		'startUtc'   => (string) $occurrence['start_utc'],
		'endUtc'     => (string) $occurrence['end_utc'],
		'startLocal' => (string) ( $occurrence['start_local'] ?? '' ),
		'endLocal'   => (string) ( $occurrence['end_local'] ?? '' ),
		'allDay'     => (bool) $occurrence['all_day'],
		'recurring'  => (bool) $occurrence['recurring'],
	);
}

/**
 * `GET /axismundi/v1/actors/me/calendarList`.
 *
 * The Calendars the signed-in user's Actor stands in some relation to. A user with no Actor stands
 * in no relation to any of them and gets an empty list rather than an error: that is the true
 * answer, and this route is not the place to complain that identity is not installed.
 *
 * Entries whose rule has since been revoked are dropped rather than reported with a stale role.
 *
 * @param WP_REST_Request $request Request.
 * @return WP_REST_Response
 */
function axismundi_cal_rest_calendar_list( WP_REST_Request $request ) : WP_REST_Response {
	$actor_uri = axismundi_cal_current_actor_uri();
	$items     = array();
	if ( '' !== $actor_uri ) {
		foreach ( axismundi_cal_actor_calendar_ids( $actor_uri ) as $calendar_id ) {
			$calendar = axismundi_cal_calendar_get( $calendar_id );
			if ( ! is_array( $calendar ) || ! axismundi_cal_request_can_read( $calendar_id ) ) {
				continue;
			}
			$entry   = axismundi_cal_list_entry( $calendar_id, $actor_uri );
			$items[] = axismundi_cal_rest_calendar( $calendar, is_array( $entry ) ? $entry : array() );
		}
	}
	return new WP_REST_Response( array( 'items' => $items ), 200 );
}

/**
 * `GET /axismundi/v1/calendars/{uuid}`.
 *
 * @param WP_REST_Request $request Request.
 * @return WP_REST_Response|WP_Error
 */
function axismundi_cal_rest_calendar_detail( WP_REST_Request $request ) {
	$calendar = axismundi_cal_rest_readable_calendar( $request );
	if ( is_wp_error( $calendar ) ) {
		return $calendar;
	}
	$actor_uri = axismundi_cal_current_actor_uri();
	$entry     = '' === $actor_uri ? null : axismundi_cal_list_entry( (int) $calendar['id'], $actor_uri );
	return new WP_REST_Response( axismundi_cal_rest_calendar( $calendar, is_array( $entry ) ? $entry : array() ), 200 );
}

/**
 * `GET /axismundi/v1/calendars/{uuid}/events`.
 *
 * Access is decided here and the occurrences are fetched through the serializer that performs no
 * check of its own, which is what lets an authorized reader of a private Calendar be served without
 * anything having to bypass the public gate.
 *
 * @param WP_REST_Request $request Request.
 * @return WP_REST_Response|WP_Error
 */
function axismundi_cal_rest_calendar_events( WP_REST_Request $request ) {
	$calendar = axismundi_cal_rest_readable_calendar( $request );
	if ( is_wp_error( $calendar ) ) {
		return $calendar;
	}

	$start = (int) strtotime( (string) $request['start'] );
	$end   = (int) strtotime( (string) $request['end'] );
	if ( $start <= 0 || $end <= 0 || $end <= $start ) {
		return new WP_Error( 'ax_cal_range', __( 'A range needs a start and a later end.', 'axismundi-calendar' ), array( 'status' => 400 ) );
	}
	if ( $end - $start > AXISMUNDI_CAL_REST_MAX_WINDOW ) {
		// Refused rather than silently clamped: a client asking for ten years and receiving one would
		// believe the missing nine were empty.
		return new WP_Error(
			'ax_cal_range_too_wide',
			__( 'That range is wider than this API will expand at once.', 'axismundi-calendar' ),
			array( 'status' => 400 )
		);
	}

	$occurrences = axismundi_cal_calendar_occurrences(
		(int) $calendar['id'],
		gmdate( 'Y-m-d H:i:s', $start ),
		gmdate( 'Y-m-d H:i:s', $end ),
		(int) $request['limit']
	);
	return new WP_REST_Response(
		array(
			'calendar' => (string) $calendar['uuid'],
			'start'    => gmdate( 'c', $start ),
			'end'      => gmdate( 'c', $end ),
			'items'    => array_map( 'axismundi_cal_rest_occurrence', $occurrences ),
		),
		200
	);
}

/**
 * Register the read routes.
 *
 * Read-only by design: `WP_REST_Server::READABLE` and nothing else. Writing a calendar list entry,
 * changing an ACL and responding to an invitation are each their own slice with their own rules, and
 * none of them belongs behind a route whose permission callback only knows how to say `reader`.
 *
 * @return void
 */
function axismundi_cal_register_read_routes() : void {
	register_rest_route(
		'axismundi/v1',
		'/actors/me/calendarList',
		array(
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => 'axismundi_cal_rest_calendar_list',
			'permission_callback' => static function () {
				if ( is_user_logged_in() ) {
					return true;
				}
				// `me` with nobody signed in is not a forbidden calendar, it is no principal at all.
				return new WP_Error( 'ax_cal_unauthenticated', __( 'You must be signed in.', 'axismundi-calendar' ), array( 'status' => 401 ) );
			},
		)
	);

	$uuid = array(
		'uuid' => array(
			'type'              => 'string',
			'required'          => true,
			'validate_callback' => static fn( $value ) : bool => 1 === preg_match( '/^[0-9a-f-]{36}$/i', (string) $value ),
		),
	);

	register_rest_route(
		'axismundi/v1',
		'/calendars/(?P<uuid>[0-9a-fA-F-]{36})',
		array(
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => 'axismundi_cal_rest_calendar_detail',
			// Every refusal here depends on which Calendar was asked for, so it is decided in the
			// handler where the row is already resolved rather than resolved twice.
			'permission_callback' => '__return_true',
			'args'                => $uuid,
		)
	);

	register_rest_route(
		'axismundi/v1',
		'/calendars/(?P<uuid>[0-9a-fA-F-]{36})/events',
		array(
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => 'axismundi_cal_rest_calendar_events',
			'permission_callback' => '__return_true',
			'args'                => array_merge(
				$uuid,
				array(
					'start' => array(
						'type'    => 'string',
						'default' => gmdate( 'Y-m-d\TH:i:s\Z' ),
					),
					'end'   => array(
						'type'    => 'string',
						'default' => gmdate( 'Y-m-d\TH:i:s\Z', time() + ( 90 * DAY_IN_SECONDS ) ),
					),
					'limit' => array(
						'type'    => 'integer',
						'default' => AXISMUNDI_CAL_RANGE_MAX,
						'minimum' => 1,
						'maximum' => AXISMUNDI_CAL_RANGE_MAX,
					),
				)
			),
		)
	);
}
add_action( 'rest_api_init', 'axismundi_cal_register_read_routes' );
