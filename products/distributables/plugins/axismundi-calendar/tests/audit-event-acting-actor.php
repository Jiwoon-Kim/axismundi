<?php
/**
 * Who an Event was published by (dev-only; dist-excluded).
 *
 * Three identities meet on one Event -- the account that typed it, the Actor whose Calendar it is
 * filed on, and the Actor it is published by -- and the case that separates them is the ordinary one:
 * somebody with write access adds an Event to a Calendar that is not theirs. Before this, the
 * projection attributed it to the Calendar's owner, which hands one person's work to another and, on
 * the wire, sends replies to somebody who was never involved.
 *
 * @package AxismundiCalendar
 */

defined( 'ABSPATH' ) || exit( 1 );

$ax_aa_results = array();
$ax_aa_users   = array();

/** @param bool[] $results Results. */
function ax_ea_assert( array &$results, string $label, bool $condition ) : void {
	$results[] = $condition;
	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI fixture output.
	printf( "[%s] %s\n", $condition ? 'PASS' : 'FAIL', $label );
}

/** An account with an activated Person Actor. */
function ax_ea_user( array &$users ) : int {
	$id = (int) wp_insert_user(
		array( 'user_login' => 'axea' . strtolower( wp_generate_password( 8, false, false ) ), 'user_pass' => wp_generate_password(), 'role' => 'administrator' )
	);
	$users[] = $id;
	$actor   = axismundi_actors_ensure_for_user( $id );
	if ( $actor instanceof Axismundi_Actor ) {
		axismundi_actors_register_handle( $actor->get_identity_id(), 'axea' . strtolower( wp_generate_password( 8, false, false ) ) );
	}
	return $id;
}

/** One Event post owned by a WP account. */
function ax_ea_event( int $author, array $fields = array() ) : int {
	$post_id = (int) wp_insert_post(
		array( 'post_type' => AXISMUNDI_CAL_EVENT_POST_TYPE, 'post_title' => 'Acting actor fixture', 'post_status' => 'publish', 'post_author' => $author )
	);
	$saved = axismundi_cal_event_save(
		$post_id,
		array_merge( array( 'starts_at' => '2026-09-01 10:00:00', 'ends_at' => '2026-09-01 11:00:00', 'timezone' => 'Asia/Seoul' ), $fields )
	);
	return is_wp_error( $saved ) ? 0 : $post_id;
}

try {
	$ax_ea_writer = ax_ea_user( $ax_aa_users );
	$ax_ea_other  = ax_ea_user( $ax_aa_users );
	wp_set_current_user( $ax_ea_writer );
	$ax_ea_person = axismundi_actors_get_for_user( $ax_ea_writer );
	$ax_ea_org    = axismundi_actors_create_managed_actor(
		array( 'owner_user_id' => $ax_ea_writer, 'preferred_username' => 'axea' . strtolower( wp_generate_password( 8, false, false ) ), 'actor_type' => 'Organization', 'status' => 'public' )
	);

	// -- what gets recorded, and where it lands -----------------------------------------------------------

	$ax_ea_mine = ax_ea_event( $ax_ea_writer );
	ax_ea_assert(
		$ax_aa_results,
		'an Event records the Actor it was published by, without anybody having said so',
		$ax_ea_mine > 0 && $ax_ea_person->get_identity_id() === axismundi_cal_event_acting_actor_identity( $ax_ea_mine )
	);
	/*
	 * The switcher decides where an unfiled Event goes. Posting as an Organization and having it land
	 * on your personal calendar would be the same mistake as attributing it to you.
	 */
	axismundi_actors_set_acting_actor( $ax_ea_writer, $ax_ea_org->get_identity_id() );
	$ax_ea_org_event = ax_ea_event( $ax_ea_writer );
	$ax_ea_schedule  = axismundi_cal_schedule_for_event( $ax_ea_org_event );
	$ax_ea_org_cal   = axismundi_cal_primary_calendar( (string) $ax_ea_org->get_uri() );
	ax_ea_assert(
		$ax_aa_results,
		'posting as an Organization files the Event on the Organization\'s calendar, not the writer\'s own',
		$ax_ea_org->get_identity_id() === axismundi_cal_event_acting_actor_identity( $ax_ea_org_event )
			&& is_array( $ax_ea_schedule ) && is_array( $ax_ea_org_cal )
			&& (int) $ax_ea_org_cal['id'] === (int) $ax_ea_schedule['calendar_id']
	);
	// The WordPress account is untouched by any of this: it is still who may edit and who is responsible.
	ax_ea_assert(
		$ax_aa_results,
		'and the WordPress author is still the person who typed it',
		$ax_ea_writer === (int) get_post_field( 'post_author', $ax_ea_org_event )
	);
	axismundi_actors_set_acting_actor( $ax_ea_writer, 0 );

	// -- the projection stops handing authorship to the calendar owner -------------------------------------

	/*
	 * The case from Google's event card: a writer adds an Event to somebody else's calendar and stays
	 * its author. Filed on the Organization's calendar, published by the person.
	 */
	$ax_ea_shared = ax_ea_event( $ax_ea_writer, array( 'calendar_id' => (int) $ax_ea_org_cal['id'] ) );
	$ax_ea_object = axismundi_cal_event_transform( get_post( $ax_ea_shared ) );
	ax_ea_assert(
		$ax_aa_results,
		'an Event on somebody else\'s calendar is still attributed to whoever published it',
		is_array( $ax_ea_object )
			&& (string) $ax_ea_person->get_uri() === (string) ( $ax_ea_object['attributedTo'] ?? '' )
			&& array( (string) $ax_ea_person->get_uri() ) === (array) ( $ax_ea_object['organizers']['items'] ?? array() )
	);
	ax_ea_assert(
		$ax_aa_results,
		'the calendar authority stays a different answer to a different question',
		(string) $ax_ea_org->get_uri() === axismundi_cal_calendar_authority( (int) $ax_ea_org_cal['id'] )
			&& (string) $ax_ea_org->get_uri() !== (string) ( $ax_ea_object['attributedTo'] ?? '' )
	);
	// A Join is addressed to the Actor running the event, which is the same stored answer.
	ax_ea_assert(
		$ax_aa_results,
		'a request to come is addressed to the Actor that published it',
		(string) $ax_ea_person->get_uri() === axismundi_cal_event_owner_actor_uri( $ax_ea_shared )
	);

	// -- the choice is not a permission --------------------------------------------------------------------

	/*
	 * The switcher's promise, restated where it actually costs something. A manager role can be
	 * revoked between two saves, and the check is made when the write happens rather than when the
	 * Actor was chosen.
	 */
	wp_set_current_user( $ax_ea_other );
	$ax_ea_stolen = ax_ea_event( $ax_ea_other, array( 'acting_actor_identity_id' => $ax_ea_org->get_identity_id() ) );
	ax_ea_assert(
		$ax_aa_results,
		'somebody who does not run the Organization cannot publish an Event as it',
		0 === $ax_ea_stolen
	);
	wp_set_current_user( $ax_ea_writer );

	// -- an edit is not a republication ---------------------------------------------------------------------

	/*
	 * An administrator fixing a typo on an Organization's Event must not quietly become its author.
	 * Nothing was said about the acting Actor in this save, so nothing about it changes.
	 */
	$ax_ea_before = axismundi_cal_event_acting_actor_identity( $ax_ea_org_event );
	wp_set_current_user( $ax_ea_other );
	axismundi_cal_event_save( $ax_ea_org_event, array( 'transparency' => 'TRANSPARENT' ) );
	ax_ea_assert(
		$ax_aa_results,
		'editing somebody else\'s Event does not transfer it to the editor',
		$ax_ea_before === axismundi_cal_event_acting_actor_identity( $ax_ea_org_event )
			&& $ax_ea_org->get_identity_id() === $ax_ea_before
	);
	wp_set_current_user( $ax_ea_writer );

	// -- Events written before the column existed ------------------------------------------------------------

	/*
	 * The migration writes down the answer that was already being derived, so an old Event keeps the
	 * attribution it had rather than acquiring a new one.
	 */
	global $wpdb;
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- simulating a pre-migration row.
	$wpdb->update( axismundi_cal_events_table(), array( 'acting_actor_identity_id' => 0 ), array( 'post_id' => $ax_ea_mine ), array( '%d' ), array( '%d' ) );
	ax_ea_assert(
		$ax_aa_results,
		'an Event with nothing recorded still answers with the Actor it was always attributed to',
		(string) $ax_ea_person->get_uri() === axismundi_cal_event_acting_actor_uri( $ax_ea_mine )
	);
	ax_ea_assert(
		$ax_aa_results,
		'and the migration writes that same answer down rather than computing a different one',
		axismundi_cal_backfill_event_acting_actors() >= 1
			&& $ax_ea_person->get_identity_id() === axismundi_cal_event_acting_actor_identity( $ax_ea_mine )
	);
} finally {
	wp_set_current_user( 0 );
	foreach ( $ax_aa_users as $ax_ea_user_id ) {
		wp_delete_user( (int) $ax_ea_user_id );
	}
}

$ax_ea_failures = count( array_filter( $ax_aa_results, static fn( bool $result ) : bool => ! $result ) );
// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI fixture output.
printf( "\n== %d checks, %d failed ==\n", count( $ax_aa_results ), $ax_ea_failures );
if ( class_exists( 'WP_CLI' ) ) {
	WP_CLI::halt( $ax_ea_failures > 0 ? 1 : 0 );
}
exit( $ax_ea_failures > 0 ? 1 : 0 );
