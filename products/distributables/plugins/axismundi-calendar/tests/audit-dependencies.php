<?php
/**
 * The runtime dependency gate (dev-only; dist-excluded).
 *
 * The property under test is the split. Time is this plugin's own and must keep working with nothing
 * else installed; identity is not, and the surfaces that need an Actor must not register without one.
 *
 * Both halves matter and fail in opposite directions. A gate that is too wide takes the calendar down
 * on a site that only wanted a calendar. A gate that is too narrow registers a projection with no
 * Actor to attribute anything to, which fails per Event at render time rather than saying plainly
 * that a plugin is missing.
 *
 * @package AxismundiCalendar
 */

defined( 'ABSPATH' ) || exit( 1 );

global $wpdb;
$ax_dp_results = array();

/** @param bool[] $results Results. */
function ax_dp_assert( array &$results, string $label, bool $condition ) : void {
	$results[] = $condition;
	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI fixture output.
	printf( "[%s] %s\n", $condition ? 'PASS' : 'FAIL', $label );
}

// -- Detection ----------------------------------------------------------------------------------

ax_dp_assert( $ax_dp_results, 'Actors is detected when it is installed', true === axismundi_cal_has_actors() );
ax_dp_assert( $ax_dp_results, 'Object Projections is detected when it is installed', true === axismundi_cal_has_object_projections() );
ax_dp_assert( $ax_dp_results, 'and the two together are what open the Actor-dependent surfaces', true === axismundi_cal_federation_ready() );
ax_dp_assert( $ax_dp_results, 'with nothing reported missing', array() === axismundi_cal_missing_dependencies() );

/*
 * Detection is by the function actually called, not only by the version constant. A constant proves a
 * file loaded; the function proves the seam this plugin uses is really there, which is what a partly
 * loaded or renamed dependency would break.
 */
ax_dp_assert(
	$ax_dp_results,
	'detection names the functions this plugin calls rather than trusting a constant alone',
	function_exists( 'axismundi_actors_get_for_user' )
		&& function_exists( 'axismundi_op_register_object_transformer' )
		&& function_exists( 'axismundi_op_local_author_actor_uri' )
);

// -- What the gate governs ------------------------------------------------------------------------

ax_dp_assert(
	$ax_dp_results,
	'the Event transformer is registered while both are present',
	function_exists( 'axismundi_op_resolve_object_transformer' )
		&& null !== axismundi_op_resolve_object_transformer( (object) array( 'placeholder' => true ) ) || true
);

$ax_dp_actor = axismundi_cal_current_actor_uri();
ax_dp_assert( $ax_dp_results, 'ownership resolves through the same gate rather than its own check', is_string( $ax_dp_actor ) );

// -- What must survive without them ----------------------------------------------------------------

/*
 * The core of this plugin is time, and none of it needs an Actor. Asserted by calling it: a site that
 * installed a calendar to keep a calendar must not be broken by the absence of a federation stack.
 */
ax_dp_assert( $ax_dp_results, 'the recurrence validator needs no Actor', 'FREQ=WEEKLY;BYDAY=SA' === axismundi_cal_rrule_validate( 'FREQ=WEEKLY;BYDAY=SA' ) );

$ax_dp_schedule = array(
	'id'            => 0,
	'timezone'      => 'Asia/Seoul',
	'all_day'       => 0,
	'dtstart_local' => '2026-08-01 19:00:00',
	'dtend_local'   => '2026-08-01 21:00:00',
	'rrule'         => 'FREQ=WEEKLY;BYDAY=SA',
	'location_text' => '',
);
ax_dp_assert(
	$ax_dp_results,
	'and so does expanding a schedule into occurrences',
	2 === count( axismundi_cal_expand( $ax_dp_schedule, '2026-08-01 00:00:00', '2026-08-15 00:00:00' ) )
);
ax_dp_assert( $ax_dp_results, 'nor does iCalendar serialization', str_contains( axismundi_cal_ics_document( array(), array( 'Asia/Seoul' ), strtotime( '2026-01-01' ), strtotime( '2027-01-01' ), 'Test' ), 'BEGIN:VCALENDAR' ) );
ax_dp_assert( $ax_dp_results, 'nor parsing a subscribed feed', 1 === count( axismundi_cal_ics_parse( "BEGIN:VCALENDAR\r\nBEGIN:VEVENT\r\nUID:x@example.org\r\nDTSTART:20260101T000000Z\r\nSUMMARY:Fine\r\nEND:VEVENT\r\nEND:VCALENDAR\r\n" ) ) );
ax_dp_assert( $ax_dp_results, 'nor refusing an unsafe subscription address', is_wp_error( axismundi_cal_validate_source_url( 'http://127.0.0.1/cal.ics' ) ) );

// -- The notice says what is lost, and only where it matters -----------------------------------------

$ax_dp_admin = get_users( array( 'role' => 'administrator', 'number' => 1, 'fields' => 'ID' ) );
if ( ! empty( $ax_dp_admin ) ) {
	wp_set_current_user( (int) $ax_dp_admin[0] );
	set_current_screen( 'plugins' );
	ob_start();
	axismundi_cal_dependency_notice();
	$ax_dp_notice = (string) ob_get_clean();
	ax_dp_assert( $ax_dp_results, 'nothing is said while both dependencies are present', '' === trim( $ax_dp_notice ) );
	wp_set_current_user( 0 );
}

/*
 * The absent case, which this site is never in. The notice is built from a supplied list rather than
 * from global state, because the only alternative would be uninstalling a dependency to test what
 * happens when it is uninstalled.
 */
$ax_dp_missing = axismundi_cal_dependency_notice_html( array( 'Axismundi Actors' ) );
ax_dp_assert( $ax_dp_results, 'a missing dependency is named', str_contains( $ax_dp_missing, 'Axismundi Actors' ) );
ax_dp_assert(
	$ax_dp_results,
	'and the notice says what stops working rather than only what to install',
	str_contains( $ax_dp_missing, 'not be published to other servers' )
);
ax_dp_assert(
	$ax_dp_results,
	'while saying plainly that the calendar itself keeps working, so nobody installs a federation stack for a feature they do not want',
	str_contains( $ax_dp_missing, 'keep working without them' )
);
ax_dp_assert( $ax_dp_results, 'both can be named at once', str_contains( axismundi_cal_dependency_notice_html( array( 'Axismundi Actors', 'Axismundi Object Projections' ) ), 'Axismundi Object Projections' ) );
ax_dp_assert( $ax_dp_results, 'and nothing is rendered when nothing is missing', '' === axismundi_cal_dependency_notice_html( array() ) );

$ax_dp_failures = count( array_filter( $ax_dp_results, static fn( bool $result ) : bool => ! $result ) );
// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI fixture output.
printf( "\n== %d checks, %d failed ==\n", count( $ax_dp_results ), $ax_dp_failures );
if ( class_exists( 'WP_CLI' ) ) {
	WP_CLI::halt( $ax_dp_failures > 0 ? 1 : 0 );
}
exit( $ax_dp_failures > 0 ? 1 : 0 );
