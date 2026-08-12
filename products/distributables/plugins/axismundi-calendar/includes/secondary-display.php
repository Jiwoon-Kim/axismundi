<?php
/**
 * Showing a second date under the first one.
 *
 * Kept apart from `CalendarList` on purpose, and not by accident of file layout. Ticking 대한민국의
 * 휴일 says which datasets you are a member of; ticking 음력 says how you would like days written.
 * One is membership and travels with permissions and sharing; the other is grid composition, like
 * the weekday column. Putting the second in the first would make a display preference something you
 * can be granted, and something a calendar's owner could take away.
 *
 * @package AxismundiCalendar
 */

defined( 'ABSPATH' ) || exit;

/** Where each person's choice lives. Theirs, not the site's. */
const AXISMUNDI_CAL_SECONDARY_META = 'ax_cal_secondary_calendars';

/**
 * The calendar systems somebody has turned on, in the order they will be shown.
 *
 * Filtered against what is registered, because a system can go away -- a site can drop a provider,
 * or a PHP build can arrive without a calendar it used to have -- and a preference naming one that
 * no longer answers should read as off rather than as an error.
 *
 * @param int $user_id User, or 0 for the current one.
 * @return string[]
 */
function axismundi_cal_secondary_systems( int $user_id = 0 ) : array {
	$user_id = $user_id > 0 ? $user_id : get_current_user_id();
	if ( $user_id < 1 ) {
		return array();
	}
	$stored = get_user_meta( $user_id, AXISMUNDI_CAL_SECONDARY_META, true );
	if ( ! is_array( $stored ) ) {
		return array();
	}
	$known = axismundi_cal_calendar_systems();
	return array_values( array_filter( array_map( 'strval', $stored ), static fn( string $id ) : bool => isset( $known[ $id ] ) ) );
}

/**
 * Set which systems somebody wants shown.
 *
 * @param string[] $systems System ids.
 * @param int      $user_id User, or 0 for the current one.
 * @return string[] What was stored.
 */
function axismundi_cal_secondary_systems_set( array $systems, int $user_id = 0 ) : array {
	$user_id = $user_id > 0 ? $user_id : get_current_user_id();
	if ( $user_id < 1 ) {
		return array();
	}
	$known = axismundi_cal_calendar_systems();
	$clean = array();
	foreach ( $systems as $id ) {
		$id = strtolower( trim( (string) $id ) );
		// Bounded, and only to things that exist. This arrives from a request, and a preference is not
		// a place to keep arbitrary strings somebody sent.
		if ( isset( $known[ $id ] ) && ! in_array( $id, $clean, true ) && count( $clean ) < 4 ) {
			$clean[] = $id;
		}
	}
	update_user_meta( $user_id, AXISMUNDI_CAL_SECONDARY_META, $clean );
	return $clean;
}

/**
 * Every registered system, in the shape a client needs to offer them.
 *
 * @return array<int,array<string,mixed>>
 */
function axismundi_cal_secondary_choices() : array {
	$out = array();
	foreach ( axismundi_cal_calendar_systems() as $system ) {
		$out[] = array(
			'id'    => (string) $system['id'],
			'label' => (string) $system['label'],
			'type'  => (string) $system['type'],
		);
	}
	return $out;
}

/**
 * How a second date reads in a day cell.
 *
 * The month is shown on the first of the month and nowhere else. A grid repeating `6.19 6.20 6.21`
 * down every row says the month thirty times to answer a question asked once, and the day it
 * actually changes stops standing out. On the first it reads `7.1`, or `윤 7.1` in a leap month;
 * every other day is its number alone.
 *
 * @param array{year:int,month:int,day:int,leapMonth:bool} $date Resolved date.
 * @return string
 */
function axismundi_cal_secondary_label( array $date ) : string {
	if ( 1 !== (int) $date['day'] ) {
		return (string) (int) $date['day'];
	}
	$prefix = ! empty( $date['leapMonth'] ) ? _x( 'L', 'leap month marker', 'axismundi-calendar' ) . ' ' : '';
	return $prefix . (int) $date['month'] . '.' . (int) $date['day'];
}

/**
 * Second dates for a range, keyed by system and then by ISO date.
 *
 * A day a system cannot name is absent rather than empty. Outside its coverage, and inside it for a
 * month nobody has materialised, the grid should look exactly as it did before the system was turned
 * on -- an empty slot under every number would be a promise the provider is not keeping.
 *
 * @param string[] $systems System ids.
 * @param string   $from    ISO date, inclusive.
 * @param string   $to      ISO date, inclusive.
 * @return array<string,array<string,string>>
 */
function axismundi_cal_secondary_dates( array $systems, string $from, string $to ) : array {
	$start = axismundi_cal_iso_to_absolute_day( $from );
	$end   = axismundi_cal_iso_to_absolute_day( $to );
	if ( null === $start || null === $end || $end < $start || $end - $start > 400 ) {
		return array();
	}
	$out = array();
	foreach ( $systems as $id ) {
		$labels = array();
		for ( $day = $start; $day <= $end; $day++ ) {
			$date = axismundi_cal_system_date( $id, $day );
			if ( null === $date ) {
				continue;
			}
			$labels[ axismundi_cal_absolute_day_to_iso( $day ) ] = axismundi_cal_secondary_label( $date );
		}
		if ( array() !== $labels ) {
			$out[ $id ] = $labels;
		}
	}
	return $out;
}

/**
 * `GET|PUT /axismundi/v1/actors/me/secondaryCalendars`.
 *
 * @param WP_REST_Request $request Request.
 * @return WP_REST_Response
 */
function axismundi_cal_rest_secondary( WP_REST_Request $request ) : WP_REST_Response {
	if ( 'GET' !== $request->get_method() && null !== $request->get_param( 'systems' ) ) {
		axismundi_cal_secondary_systems_set( (array) $request->get_param( 'systems' ) );
	}
	$systems = axismundi_cal_secondary_systems();
	$dates   = array();
	$from    = (string) $request->get_param( 'start' );
	$to      = (string) $request->get_param( 'end' );
	if ( '' !== $from && '' !== $to && array() !== $systems ) {
		$dates = axismundi_cal_secondary_dates( $systems, $from, $to );
	}
	return new WP_REST_Response(
		array(
			'available' => axismundi_cal_secondary_choices(),
			'selected'  => $systems,
			'dates'     => $dates,
		),
		200
	);
}

/**
 * Register the route.
 *
 * @return void
 */
function axismundi_cal_register_secondary_routes() : void {
	$signed_in = static function () {
		if ( is_user_logged_in() ) {
			return true;
		}
		// A preference belongs to somebody. There is no anonymous answer to give.
		return new WP_Error( 'ax_cal_unauthenticated', __( 'You must be signed in.', 'axismundi-calendar' ), array( 'status' => 401 ) );
	};
	$args = array(
		'start'   => array( 'type' => 'string', 'default' => '' ),
		'end'     => array( 'type' => 'string', 'default' => '' ),
		'systems' => array(
			'type'  => 'array',
			'items' => array( 'type' => 'string' ),
		),
	);
	register_rest_route(
		'axismundi/v1',
		'/actors/me/secondaryCalendars',
		array(
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => 'axismundi_cal_rest_secondary',
				'permission_callback' => $signed_in,
				'args'                => $args,
			),
			array(
				'methods'             => 'PUT, PATCH',
				'callback'            => 'axismundi_cal_rest_secondary',
				'permission_callback' => $signed_in,
				'args'                => $args,
			),
		)
	);
}
add_action( 'rest_api_init', 'axismundi_cal_register_secondary_routes' );
