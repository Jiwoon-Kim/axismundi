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
require_once ABSPATH . 'wp-admin/includes/user.php';
global $wpdb, $ax_ea_fixture_actors, $ax_ea_fixture_posts;
$ax_ea_fixture_actors = $ax_ea_fixture_posts = array();
$ax_ea_original_user = get_current_user_id();
$ax_ea_cookies = $_COOKIE;
$ax_ea_gate = static fn() => true;
$ax_ea_adapter = static fn() => true;
$ax_ea_principals_before = $wpdb->get_results( 'SELECT * FROM ' . axismundi_actors_managers_table() . ' ORDER BY identity_id,principal_kind,principal_id', ARRAY_A );
add_filter( 'axismundi_actors_account_selection_enabled', $ax_ea_gate, PHP_INT_MAX );
add_filter( 'axismundi_actors_account_selection_adapter_available', $ax_ea_adapter, PHP_INT_MAX );

$ax_aa_results = array();
$ax_aa_users   = array();

/** @param bool[] $results Results. */
function ax_ea_assert( array &$results, string $label, bool $condition ) : void {
	// Printing mid-audit makes headers_sent() true and prevents later selection-cookie writes.
	$results[ $label ] = $condition;
}

/** An account with an activated Person Actor. */
function ax_ea_user( array &$users ) : int {
	global $ax_ea_fixture_actors;
	$id = (int) wp_insert_user(
		array( 'user_login' => 'axea' . strtolower( wp_generate_password( 8, false, false ) ), 'user_pass' => wp_generate_password(), 'role' => 'administrator' )
	);
	$users[] = $id;
	$actor   = axismundi_actors_ensure_for_user( $id );
	if ( $actor instanceof Axismundi_Actor ) {
		$ax_ea_fixture_actors[] = $actor->get_identity_id();
		axismundi_actors_register_handle( $actor->get_identity_id(), 'axea' . strtolower( wp_generate_password( 8, false, false ) ) );
	}
	return $id;
}

/** One Event post owned by a WP account. */
function ax_ea_event( int $author, array $fields = array() ) : int {
	global $ax_ea_fixture_posts;
	$post_id = (int) wp_insert_post(
		array( 'post_type' => AXISMUNDI_CAL_EVENT_POST_TYPE, 'post_title' => 'Acting actor fixture', 'post_status' => 'publish', 'post_author' => $author )
	);
	if ( $post_id > 0 ) { $ax_ea_fixture_posts[] = $post_id; }
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
	if ( $ax_ea_org instanceof Axismundi_Actor ) { $ax_ea_fixture_actors[] = $ax_ea_org->get_identity_id(); }
	$expires = time() + 600;
	$token = WP_Session_Tokens::get_instance( $ax_ea_writer )->create( $expires );
	$_COOKIE[ LOGGED_IN_COOKIE ] = wp_generate_auth_cookie( $ax_ea_writer, $expires, 'logged_in', $token );
	if ( true !== axismundi_actors_set_account_selection( $ax_ea_person->get_identity_id() ) ) { throw new RuntimeException( 'Could not select Event author Account.' ); }

	// -- what gets recorded, and where it lands -----------------------------------------------------------

	$ax_ea_mine = ax_ea_event( $ax_ea_writer );
	ax_ea_assert(
		$ax_aa_results,
		'an Event records the explicitly selected Account',
		$ax_ea_mine > 0 && $ax_ea_person->get_identity_id() === axismundi_cal_event_acting_actor_identity( $ax_ea_mine )
	);
	/*
	 * The switcher decides where an unfiled Event goes. Posting as an Organization and having it land
	 * on your personal calendar would be the same mistake as attributing it to you.
	 */
	if ( true !== axismundi_actors_set_account_selection( $ax_ea_org->get_identity_id() ) ) { throw new RuntimeException( 'Could not select Organization.' ); }
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
	if ( true !== axismundi_actors_set_account_selection( $ax_ea_person->get_identity_id() ) ) { throw new RuntimeException( 'Could not restore Person selection.' ); }

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
	 * A missing stored identity is unknown. Neither reading nor migration may guess it from a User.
	 */
	global $wpdb;
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- simulating a pre-migration row.
	$wpdb->update( axismundi_cal_events_table(), array( 'acting_actor_identity_id' => 0 ), array( 'post_id' => $ax_ea_mine ), array( '%d' ), array( '%d' ) );
	ax_ea_assert(
		$ax_aa_results,
		'an Event with nothing recorded does not infer its Actor from the WordPress author',
		'' === axismundi_cal_event_acting_actor_uri( $ax_ea_mine )
	);
	ax_ea_assert(
		$ax_aa_results,
		'a missing identity with no recorded Create remains unknown',
		0 === axismundi_cal_event_acting_actor_identity( $ax_ea_mine )
	);
} finally {
	wp_set_current_user( 0 );
	foreach ( $ax_ea_fixture_posts as $post_id ) {
		$post = get_post( $post_id );
		if ( $post instanceof WP_Post ) {
			$uri = axismundi_cal_event_object_uri( $post );
			$wpdb->delete( axismundi_act_activities_table(), array( 'object_uri_hash' => hash( 'sha256', $uri ) ) );
		}
		foreach ( (array) $wpdb->get_col( $wpdb->prepare( 'SELECT id FROM %i WHERE event_post_id = %d', axismundi_cal_schedules_table(), $post_id ) ) as $schedule ) {
			$wpdb->delete( axismundi_cal_occurrences_table(), array( 'schedule_id' => $schedule ) );
		}
		$wpdb->delete( axismundi_cal_schedules_table(), array( 'event_post_id' => $post_id ) );
		$wpdb->delete( axismundi_cal_events_table(), array( 'post_id' => $post_id ) );
		$wpdb->delete( axismundi_cal_event_locations_table(), array( 'event_post_id' => $post_id ) );
		wp_delete_post( $post_id, true );
	}
	foreach ( $ax_aa_users as $ax_ea_user_id ) {
		wp_delete_user( (int) $ax_ea_user_id );
	}
	foreach ( $ax_ea_fixture_actors as $identity_id ) {
		$actor = axismundi_actors_get_by_identity( $identity_id );
		if ( $actor instanceof Axismundi_Actor ) {
			foreach ( (array) $wpdb->get_col( $wpdb->prepare( 'SELECT id FROM %i WHERE authority_actor_uri_hash = %s', axismundi_cal_calendars_table(), hash( 'sha256', $actor->get_uri() ) ) ) as $calendar ) {
				foreach ( array( axismundi_cal_acl_table(), axismundi_cal_entries_list_table() ) as $table ) { $wpdb->delete( $table, array( 'calendar_id' => $calendar ) ); }
				$wpdb->delete( axismundi_cal_calendars_table(), array( 'id' => $calendar ) );
			}
		}
		foreach ( array( axismundi_actors_managers_table(), axismundi_actors_addresses_table(), axismundi_actors_signing_keys_table(), axismundi_actors_actors_table() ) as $table ) { $wpdb->delete( $table, array( 'identity_id' => $identity_id ) ); }
		$wpdb->delete( axismundi_actors_identities_table(), array( 'id' => $identity_id ) );
	}
	wp_set_current_user( $ax_ea_original_user );
	$_COOKIE = $ax_ea_cookies;
	remove_filter( 'axismundi_actors_account_selection_enabled', $ax_ea_gate, PHP_INT_MAX );
	remove_filter( 'axismundi_actors_account_selection_adapter_available', $ax_ea_adapter, PHP_INT_MAX );
}
ax_ea_assert( $ax_aa_results, 'Calendar authoring fixtures leave principal rows unchanged', $ax_ea_principals_before === $wpdb->get_results( 'SELECT * FROM ' . axismundi_actors_managers_table() . ' ORDER BY identity_id,principal_kind,principal_id', ARRAY_A ) );

$ax_ea_failures = count( array_filter( $ax_aa_results, static fn( bool $result ) : bool => ! $result ) );
foreach ( $ax_aa_results as $label => $ok ) { printf( "[%s] %s\n", $ok ? 'PASS' : 'FAIL', $label ); }
// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI fixture output.
printf( "\n== %d checks, %d failed ==\n", count( $ax_aa_results ), $ax_ea_failures );
if ( class_exists( 'WP_CLI' ) ) {
	WP_CLI::halt( $ax_ea_failures > 0 ? 1 : 0 );
}
exit( $ax_ea_failures > 0 ? 1 : 0 );
