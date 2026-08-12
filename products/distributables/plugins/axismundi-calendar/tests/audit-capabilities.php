<?php
/**
 * What can be done with one Calendar (dev-only; dist-excluded).
 *
 * The matrix itself, asserted row by row. Screens used to derive this from two questions -- is it
 * remote, is it the default -- which answers the wrong one: a subscribed Calendar and somebody's
 * default are both undeletable for entirely unrelated reasons, and collapsing them is how a rule
 * meant for one silently starts applying to the other.
 *
 * Written out in full rather than as a few spot checks, because the value of the table is that every
 * cell is stated somewhere. A capability nobody asserts is one that can quietly flip.
 *
 * None of it is a security boundary on its own -- the writers refuse the same things independently,
 * which the other suites cover. What is under test here is that the screens are told the truth.
 *
 * @package AxismundiCalendar
 */

defined( 'ABSPATH' ) || exit( 1 );

global $wpdb;
$ax_cp_results   = array();
$ax_cp_calendars = array();
$ax_cp_users     = array();

/** @param bool[] $results Results. */
function ax_cp_assert( array &$results, string $label, bool $condition ) : void {
	$results[] = $condition;
	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI fixture output.
	printf( "[%s] %s\n", $condition ? 'PASS' : 'FAIL', $label );
}

/** Assert one whole row of the matrix. */
function ax_cp_row( array &$results, string $label, ?array $calendar, array $expected ) : void {
	$actual = axismundi_cal_calendar_capabilities( $calendar );
	foreach ( $expected as $capability => $want ) {
		ax_cp_assert(
			$results,
			sprintf( '%s: %s %s', $label, $want ? 'may' : 'may not', str_replace( '_', ' ', $capability ) ),
			$want === ( $actual[ $capability ] ?? null )
		);
	}
}

/** A user with a public Person Actor. */
function ax_cp_user( array &$users ) : array {
	$login   = 'ax_cp_' . strtolower( wp_generate_password( 8, false, false ) );
	$id      = (int) wp_insert_user( array( 'user_login' => $login, 'user_pass' => wp_generate_password(), 'role' => 'author' ) );
	$users[] = $id;
	$uri     = '';
	if ( function_exists( 'axismundi_actors_ensure_for_user' ) ) {
		$actor = axismundi_actors_ensure_for_user( $id );
		if ( $actor instanceof Axismundi_Actor ) {
			axismundi_actors_register_handle( $actor->get_identity_id(), $login );
			axismundi_actors_set_status( $actor->get_identity_id(), 'public' );
			$uri = (string) $actor->get_uri();
		}
	}
	return array( 'user_id' => $id, 'actor_uri' => $uri );
}

try {
	$ax_cp_owner  = ax_cp_user( $ax_cp_users );
	$ax_cp_writer = ax_cp_user( $ax_cp_users );
	$ax_cp_reader = ax_cp_user( $ax_cp_users );

	$ax_cp_suffix = strtolower( wp_generate_password( 6, false, false ) );
	$ax_cp_local  = (int) axismundi_cal_calendar_save(
		array( 'name' => 'Capability local', 'slug' => 'ax-cp-local-' . $ax_cp_suffix, 'timezone' => 'Asia/Seoul', 'owner_actor_uri' => $ax_cp_owner['actor_uri'] )
	);
	$ax_cp_calendars[] = $ax_cp_local;
	axismundi_cal_acl_grant( $ax_cp_local, $ax_cp_writer['actor_uri'], 'writer' );
	axismundi_cal_acl_grant( $ax_cp_local, $ax_cp_reader['actor_uri'], 'reader' );

	// -- An ordinary local Calendar, seen by each role -----------------------------------------------

	wp_set_current_user( $ax_cp_owner['user_id'] );
	ax_cp_row(
		$ax_cp_results,
		'the owner of an ordinary calendar',
		axismundi_cal_calendar_get( $ax_cp_local ),
		array( 'edit_details' => true, 'change_timezone' => true, 'share' => true, 'publish' => true, 'delete' => true, 'unsubscribe' => false, 'write_events' => true, 'export' => true )
	);

	/*
	 * The line the matrix exists to hold. A writer can add Events, and the intuition that they can
	 * therefore decide who else sees the Calendar is the one that must not be true.
	 */
	wp_set_current_user( $ax_cp_writer['user_id'] );
	ax_cp_row(
		$ax_cp_results,
		'a writer',
		axismundi_cal_calendar_get( $ax_cp_local ),
		array( 'edit_details' => true, 'write_events' => true, 'share' => false, 'publish' => false, 'delete' => false, 'export' => true )
	);

	wp_set_current_user( $ax_cp_reader['user_id'] );
	ax_cp_row(
		$ax_cp_results,
		'a reader',
		axismundi_cal_calendar_get( $ax_cp_local ),
		array( 'edit_details' => false, 'change_timezone' => false, 'write_events' => false, 'share' => false, 'delete' => false, 'export' => true )
	);

	wp_set_current_user( 0 );
	ax_cp_row(
		$ax_cp_results,
		'nobody in particular',
		axismundi_cal_calendar_get( $ax_cp_local ),
		array( 'edit_details' => false, 'write_events' => false, 'share' => false, 'delete' => false, 'export' => false, 'rename_locally' => false )
	);

	// -- A default Calendar differs from an ordinary one in exactly one cell ---------------------------

	wp_set_current_user( $ax_cp_owner['user_id'] );
	axismundi_cal_set_primary( $ax_cp_local, true );
	$ax_cp_default = axismundi_cal_calendar_get( $ax_cp_local );
	ax_cp_row(
		$ax_cp_results,
		'the owner of a default calendar',
		$ax_cp_default,
		array( 'edit_details' => true, 'share' => true, 'write_events' => true, 'delete' => false, 'unsubscribe' => false )
	);
	/*
	 * One cell, and only one. A default Calendar that also stopped being editable or shareable would
	 * mean somebody's own calendar was the least usable one they have.
	 */
	$ax_cp_before = axismundi_cal_calendar_capabilities( $ax_cp_default );
	axismundi_cal_set_primary( $ax_cp_local, false );
	$ax_cp_after = axismundi_cal_calendar_capabilities( axismundi_cal_calendar_get( $ax_cp_local ) );
	ax_cp_assert(
		$ax_cp_results,
		'being the default changes deletability and nothing else',
		array( 'delete' ) === array_keys( array_diff_assoc( $ax_cp_after, $ax_cp_before ) )
	);

	// -- A subscribed Calendar is a different row entirely ----------------------------------------------

	$ax_cp_remote_saved = axismundi_cal_calendar_save(
		array( 'name' => 'Capability remote', 'slug' => 'ax-cp-remote-' . $ax_cp_suffix, 'timezone' => 'Asia/Seoul', 'kind' => 'remote' )
	);
	ax_cp_assert( $ax_cp_results, 'the subscribed fixture was really created', is_int( $ax_cp_remote_saved ) );
	$ax_cp_remote = (int) $ax_cp_remote_saved;
	$ax_cp_calendars[] = $ax_cp_remote;

	ax_cp_assert( $ax_cp_results, 'a local calendar reports where its contents come from', 'native' === axismundi_cal_calendar_source_type( (array) axismundi_cal_calendar_get( $ax_cp_local ) ) );
	ax_cp_assert( $ax_cp_results, 'and a subscribed one reports the feed it mirrors', 'ics' === axismundi_cal_calendar_source_type( (array) axismundi_cal_calendar_get( $ax_cp_remote ) ) );

	/*
	 * The case worth stating: `owner` on a subscribed Calendar still cannot edit or share it. The role
	 * says what somebody may do with this site's copy, and the copy is not the thing being published.
	 */
	axismundi_cal_acl_grant( $ax_cp_remote, $ax_cp_owner['actor_uri'], 'owner' );
	axismundi_cal_list_set( $ax_cp_remote, $ax_cp_owner['actor_uri'] );
	ax_cp_row(
		$ax_cp_results,
		'a subscriber holding owner on the local copy',
		axismundi_cal_calendar_get( $ax_cp_remote ),
		array( 'edit_details' => false, 'change_timezone' => false, 'share' => false, 'publish' => false, 'delete' => false, 'write_events' => false, 'unsubscribe' => true, 'rename_locally' => true, 'export' => true )
	);

	/*
	 * Unsubscribing and renaming belong to the person who added it, not to anyone who can read it.
	 * Somebody else's sidebar is not theirs to tidy.
	 */
	wp_set_current_user( $ax_cp_reader['user_id'] );
	axismundi_cal_acl_grant( $ax_cp_remote, $ax_cp_reader['actor_uri'], 'reader' );
	ax_cp_row(
		$ax_cp_results,
		'somebody who can read the subscription but has not added it',
		axismundi_cal_calendar_get( $ax_cp_remote ),
		array( 'export' => true, 'unsubscribe' => false, 'rename_locally' => false )
	);

	// -- Asking about nothing ------------------------------------------------------------------------------

	/*
	 * A missing Calendar answers no to everything rather than raising. Screens ask this while
	 * rendering, and a fatal there would take the page with it -- while the writers refuse
	 * independently, so answering no costs nothing.
	 */
	$ax_cp_none = axismundi_cal_calendar_capabilities( null );
	ax_cp_assert( $ax_cp_results, 'a Calendar that does not exist permits nothing', array() === array_filter( $ax_cp_none ) );
	ax_cp_assert( $ax_cp_results, 'and answers every capability rather than some of them', array() === array_diff( array_keys( axismundi_cal_calendar_capabilities( axismundi_cal_calendar_get( $ax_cp_local ) ) ), array_keys( $ax_cp_none ) ) );
	ax_cp_assert( $ax_cp_results, 'a partial row is refused rather than read', array() === array_filter( axismundi_cal_calendar_capabilities( array( 'name' => 'no id' ) ) ) );

	// -- The screens ask it rather than restating it ----------------------------------------------------------

	wp_set_current_user( $ax_cp_owner['user_id'] );
	ax_cp_assert(
		$ax_cp_results,
		'the sharing screen agrees with the capability it is derived from',
		axismundi_cal_can_share_calendar( axismundi_cal_calendar_get( $ax_cp_local ) ) === axismundi_cal_calendar_can( axismundi_cal_calendar_get( $ax_cp_local ), 'share' )
	);
	wp_set_current_user( $ax_cp_writer['user_id'] );
	ax_cp_assert(
		$ax_cp_results,
		'and still agrees for somebody it refuses',
		false === axismundi_cal_can_share_calendar( axismundi_cal_calendar_get( $ax_cp_local ) )
	);
} finally {
	wp_set_current_user( 0 );
	foreach ( array_unique( $ax_cp_calendars ) as $ax_cp_calendar ) {
		axismundi_cal_set_primary( (int) $ax_cp_calendar, false );
		if ( ! axismundi_cal_calendar_delete( (int) $ax_cp_calendar ) ) {
			// A subscribed Calendar refuses deletion by design; this fixture has no source behind it.
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fixture cleanup.
			$wpdb->delete( axismundi_cal_calendars_table(), array( 'id' => (int) $ax_cp_calendar ) );
			axismundi_cal_list_forget_calendar( (int) $ax_cp_calendar );
			axismundi_cal_acl_forget_calendar( (int) $ax_cp_calendar );
		}
	}
	foreach ( $ax_cp_users as $ax_cp_user_id ) {
		wp_delete_user( (int) $ax_cp_user_id );
	}
}

$ax_cp_failures = count( array_filter( $ax_cp_results, static fn( bool $result ) : bool => ! $result ) );
// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI fixture output.
printf( "\n== %d checks, %d failed ==\n", count( $ax_cp_results ), $ax_cp_failures );
if ( class_exists( 'WP_CLI' ) ) {
	WP_CLI::halt( $ax_cp_failures > 0 ? 1 : 0 );
}
exit( $ax_cp_failures > 0 ? 1 : 0 );
