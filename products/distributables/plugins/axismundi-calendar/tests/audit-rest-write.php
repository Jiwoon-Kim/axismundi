<?php
/**
 * Calendar list writes and ACL administration (dev-only; dist-excluded).
 *
 * The property is that these are two different acts through two different routes. Putting a
 * Calendar in your sidebar changes what you see; granting a role changes what somebody else may see.
 * A list write that could carry a role would be a way of granting yourself one, and that is the case
 * asserted first.
 *
 * Authority and ACL are ownership facts; a CalendarList entry is not. Even an owner may remove
 * their own sidebar entry without changing either authority or access.
 *
 * @package AxismundiCalendar
 */

defined( 'ABSPATH' ) || exit( 1 );

global $wpdb;
$ax_rw_results   = array();
$ax_rw_calendars = array();
$ax_rw_users     = array();
$ax_rw_groups    = array();

/** @param bool[] $results Results. */
function ax_rw_assert( array &$results, string $label, bool $condition ) : void {
	$results[] = $condition;
	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI fixture output.
	printf( "[%s] %s\n", $condition ? 'PASS' : 'FAIL', $label );
}

/** A user with a public Person Actor. */
function ax_rw_user( array &$users ) : array {
	$login   = 'ax_rw_' . strtolower( wp_generate_password( 8, false, false ) );
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

/** Dispatch one request and return [status, data]. */
function ax_rw_call( string $method, string $route, array $params = array() ) : array {
	$request = new WP_REST_Request( $method, $route );
	foreach ( $params as $key => $value ) {
		$request->set_param( $key, $value );
	}
	$response = rest_do_request( $request );
	return array( (int) $response->get_status(), (array) $response->get_data() );
}

try {
	$ax_rw_owner  = ax_rw_user( $ax_rw_users );
	$ax_rw_reader = ax_rw_user( $ax_rw_users );
	$ax_rw_second = ax_rw_user( $ax_rw_users );

	$ax_rw_cal = (int) axismundi_cal_calendar_save(
		array( 'name' => 'Write fixture', 'slug' => 'ax-rw-cal', 'timezone' => 'Asia/Seoul', 'owner_actor_uri' => $ax_rw_owner['actor_uri'] )
	);
	$ax_rw_calendars[] = $ax_rw_cal;
	$ax_rw_uuid = (string) axismundi_cal_calendar_get( $ax_rw_cal )['uuid'];

	// -- Administering the ACL --------------------------------------------------------------------

	wp_set_current_user( $ax_rw_reader['user_id'] );
	list( $ax_rw_status ) = ax_rw_call( 'GET', '/axismundi/v1/calendars/' . $ax_rw_uuid . '/acl' );
	ax_rw_assert( $ax_rw_results, 'somebody with no relation cannot read the rules', 403 === $ax_rw_status );

	wp_set_current_user( $ax_rw_owner['user_id'] );
	list( $ax_rw_status, $ax_rw_body ) = ax_rw_call( 'GET', '/axismundi/v1/calendars/' . $ax_rw_uuid . '/acl' );
	ax_rw_assert( $ax_rw_results, 'the owner can', 200 === $ax_rw_status );
	ax_rw_assert(
		$ax_rw_results,
		'and sees the rule creating the Calendar gave them',
		1 === count( array_filter( (array) $ax_rw_body['items'], static fn( array $r ) : bool => 'owner' === $r['role'] ) )
	);

	list( $ax_rw_status, $ax_rw_body ) = ax_rw_call(
		'POST',
		'/axismundi/v1/calendars/' . $ax_rw_uuid . '/acl',
		array( 'principal' => $ax_rw_reader['actor_uri'], 'role' => 'reader' )
	);
	ax_rw_assert( $ax_rw_results, 'the owner may grant a reader', 200 === $ax_rw_status && 'reader' === ( $ax_rw_body['role'] ?? '' ) );

	/*
	 * The store's rules, not a second copy of them here. Granting the world write access is refused
	 * because `axismundi_cal_acl_grant()` refuses it, so the API cannot drift from the writer.
	 */
	list( $ax_rw_status ) = ax_rw_call(
		'POST',
		'/axismundi/v1/calendars/' . $ax_rw_uuid . '/acl',
		array( 'principalType' => 'public', 'role' => 'writer' )
	);
	ax_rw_assert( $ax_rw_results, 'and may not hand the world write access', 400 === $ax_rw_status );
	list( $ax_rw_status ) = ax_rw_call(
		'POST',
		'/axismundi/v1/calendars/' . $ax_rw_uuid . '/acl',
		array( 'principal' => $ax_rw_reader['actor_uri'], 'role' => 'admin' )
	);
	ax_rw_assert( $ax_rw_results, 'nor invent a role', 400 === $ax_rw_status );

	// -- A reader is a reader ------------------------------------------------------------------------

	wp_set_current_user( $ax_rw_reader['user_id'] );
	list( $ax_rw_status ) = ax_rw_call(
		'POST',
		'/axismundi/v1/calendars/' . $ax_rw_uuid . '/acl',
		array( 'principal' => $ax_rw_reader['actor_uri'], 'role' => 'owner' )
	);
	ax_rw_assert( $ax_rw_results, 'a reader cannot promote themselves', 403 === $ax_rw_status );

	/*
	 * Nor may a writer, which is the line `owner` is drawn at. Adding Events to a calendar and
	 * deciding who else may see it are different acts, and only the second is administration --
	 * without this the useful middle role would silently carry the strongest one.
	 */
	wp_set_current_user( $ax_rw_owner['user_id'] );
	ax_rw_call( 'POST', '/axismundi/v1/calendars/' . $ax_rw_uuid . '/acl', array( 'principal' => $ax_rw_second['actor_uri'], 'role' => 'writer' ) );
	wp_set_current_user( $ax_rw_second['user_id'] );
	ax_rw_assert( $ax_rw_results, 'a writer may read the Calendar', 200 === ax_rw_call( 'GET', '/axismundi/v1/calendars/' . $ax_rw_uuid )[0] );
	ax_rw_assert( $ax_rw_results, 'but may not read its rules', 403 === ax_rw_call( 'GET', '/axismundi/v1/calendars/' . $ax_rw_uuid . '/acl' )[0] );
	ax_rw_assert(
		$ax_rw_results,
		'nor grant anybody anything',
		403 === ax_rw_call( 'POST', '/axismundi/v1/calendars/' . $ax_rw_uuid . '/acl', array( 'principal' => $ax_rw_reader['actor_uri'], 'role' => 'owner' ) )[0]
	);
	wp_set_current_user( $ax_rw_reader['user_id'] );

	/*
	 * The same attempt through the list route, which is the one that writes a role column. The entry
	 * is theirs to create; the role on it is not theirs to state.
	 */
	list( $ax_rw_status, $ax_rw_body ) = ax_rw_call(
		'PUT',
		'/axismundi/v1/actors/me/calendarList/' . $ax_rw_uuid,
		array( 'selected' => true, 'summaryOverride' => 'Shared with me', 'color' => '#112233', 'accessRole' => 'owner' )
	);
	ax_rw_assert( $ax_rw_results, 'adding a shared Calendar to their own list succeeds', 200 === $ax_rw_status );
	ax_rw_assert( $ax_rw_results, 'and the role reported is the one the ACL grants', 'reader' === ( $ax_rw_body['accessRole'] ?? '' ) );
	ax_rw_assert(
		$ax_rw_results,
		'and the legacy entry column cannot alter the role the API computes',
		'reader' === (string) axismundi_cal_list_entry( $ax_rw_cal, $ax_rw_reader['actor_uri'] )['access_role']
	);
	ax_rw_assert( $ax_rw_results, 'while their own view state is kept', 'Shared with me' === ( $ax_rw_body['summaryOverride'] ?? '' ) );

	list( , $ax_rw_body ) = ax_rw_call( 'PUT', '/axismundi/v1/actors/me/calendarList/' . $ax_rw_uuid, array( 'hidden' => true ) );
	ax_rw_assert( $ax_rw_results, 'a later write changes only what it names', true === ( $ax_rw_body['hidden'] ?? false ) && 'Shared with me' === ( $ax_rw_body['summaryOverride'] ?? '' ) );

	list( $ax_rw_status ) = ax_rw_call( 'DELETE', '/axismundi/v1/actors/me/calendarList/' . $ax_rw_uuid );
	ax_rw_assert( $ax_rw_results, 'and they may take it out of their list again', 200 === $ax_rw_status );
	ax_rw_assert( $ax_rw_results, 'which removes the entry', null === axismundi_cal_list_entry( $ax_rw_cal, $ax_rw_reader['actor_uri'] ) );
	ax_rw_assert(
		$ax_rw_results,
		'without touching their access, because hiding a calendar is not resigning from it',
		true === axismundi_cal_can_read( $ax_rw_cal, $ax_rw_reader['actor_uri'] )
	);

	// -- Authority and ACL survive sidebar changes -------------------------------------------------------

	wp_set_current_user( $ax_rw_owner['user_id'] );
	list( $ax_rw_status ) = ax_rw_call( 'DELETE', '/axismundi/v1/actors/me/calendarList/' . $ax_rw_uuid );
	ax_rw_assert( $ax_rw_results, 'an owner may remove their own sidebar entry', 200 === $ax_rw_status );
	ax_rw_assert( $ax_rw_results, 'while Calendar authority remains on the Calendar itself', $ax_rw_owner['actor_uri'] === axismundi_cal_calendar_authority( $ax_rw_cal ) );
	ax_rw_assert( $ax_rw_results, 'and the ACL still lets the owner administer it', true === axismundi_cal_can_write( $ax_rw_cal, $ax_rw_owner['actor_uri'] ) );

	list( $ax_rw_status ) = ax_rw_call(
		'DELETE',
		'/axismundi/v1/calendars/' . $ax_rw_uuid . '/acl',
		array( 'principal' => $ax_rw_owner['actor_uri'] )
	);
	ax_rw_assert( $ax_rw_results, 'and the last owner rule cannot be revoked', 409 === $ax_rw_status );
	ax_rw_assert( $ax_rw_results, 'so the Calendar is never left with nobody able to administer it', 1 === axismundi_cal_acl_owner_count( $ax_rw_cal ) );

	/*
	 * With a second owner in place the first may step down. The refusal above is about the last one,
	 * not about owners being permanent.
	 */
	ax_rw_call( 'POST', '/axismundi/v1/calendars/' . $ax_rw_uuid . '/acl', array( 'principal' => $ax_rw_second['actor_uri'], 'role' => 'owner' ) );
	list( $ax_rw_status ) = ax_rw_call(
		'DELETE',
		'/axismundi/v1/calendars/' . $ax_rw_uuid . '/acl',
		array( 'principal' => $ax_rw_owner['actor_uri'] )
	);
	ax_rw_assert( $ax_rw_results, 'once a second owner exists the first may step down', 200 === $ax_rw_status );
	ax_rw_assert( $ax_rw_results, 'and loses the access with it', false === axismundi_cal_can_read( $ax_rw_cal, $ax_rw_owner['actor_uri'], $ax_rw_owner['user_id'] ) );

	$ax_rw_stale = axismundi_cal_list_entry( $ax_rw_cal, $ax_rw_owner['actor_uri'] );
	ax_rw_assert( $ax_rw_results, 'their removed entry is not recreated when the ACL changes', null === $ax_rw_stale );

	// -- A Group's calendar, and the people who manage the Group -------------------------------------------

	if ( function_exists( 'axismundi_actors_create_managed_group' ) ) {
		$ax_rw_group = axismundi_actors_create_managed_group(
			array(
				'preferred_username' => 'axrwgrp' . strtolower( wp_generate_password( 6, false, false ) ),
				'display_name'       => 'Write fixture group',
				'owner_user_id'      => $ax_rw_second['user_id'],
			)
		);
		if ( $ax_rw_group instanceof Axismundi_Actor ) {
			$ax_rw_groups[] = (int) $ax_rw_group->get_identity_id();
			$ax_rw_gcal     = (int) axismundi_cal_calendar_save(
				array( 'name' => 'Group calendar', 'slug' => 'ax-rw-gcal', 'timezone' => 'Asia/Seoul', 'owner_actor_uri' => (string) $ax_rw_group->get_uri() )
			);
			$ax_rw_calendars[] = $ax_rw_gcal;
			$ax_rw_guuid = (string) axismundi_cal_calendar_get( $ax_rw_gcal )['uuid'];

			/*
			 * Discoverable, not merely reachable. The manager could already open this Calendar by
			 * uuid; what was missing was any way to find out that it exists.
			 */
			wp_set_current_user( $ax_rw_second['user_id'] );
			list( , $ax_rw_body ) = ax_rw_call( 'GET', '/axismundi/v1/actors/me/calendarList' );
			$ax_rw_listed = array_column( (array) $ax_rw_body['items'], 'accessRole', 'id' );
			ax_rw_assert( $ax_rw_results, "a Group manager finds the Group's calendar in their own list", 'owner' === ( $ax_rw_listed[ $ax_rw_guuid ] ?? '' ) );
			ax_rw_assert( $ax_rw_results, 'and may administer it through the Group rather than a rule naming them', 200 === ax_rw_call( 'GET', '/axismundi/v1/calendars/' . $ax_rw_guuid . '/acl' )[0] );

			wp_set_current_user( $ax_rw_reader['user_id'] );
			list( , $ax_rw_body ) = ax_rw_call( 'GET', '/axismundi/v1/actors/me/calendarList' );
			$ax_rw_listed = array_column( (array) $ax_rw_body['items'], 'accessRole', 'id' );
			ax_rw_assert( $ax_rw_results, 'while somebody who manages nothing does not', ! array_key_exists( $ax_rw_guuid, $ax_rw_listed ) );
		}
	}

	// -- Signed out ---------------------------------------------------------------------------------------

	wp_set_current_user( 0 );
	ax_rw_assert( $ax_rw_results, 'no list writes without a principal', 401 === ax_rw_call( 'PUT', '/axismundi/v1/actors/me/calendarList/' . $ax_rw_uuid, array( 'hidden' => true ) )[0] );
	ax_rw_assert( $ax_rw_results, 'and no grants either', 401 === ax_rw_call( 'POST', '/axismundi/v1/calendars/' . $ax_rw_uuid . '/acl', array( 'principal' => 'https://example.org/a', 'role' => 'reader' ) )[0] );
	ax_rw_assert( $ax_rw_results, 'and the rules of a Calendar are not readable anonymously', 404 === ax_rw_call( 'GET', '/axismundi/v1/calendars/' . $ax_rw_uuid . '/acl' )[0] );
} finally {
	wp_set_current_user( 0 );
	foreach ( $ax_rw_calendars as $ax_rw_calendar ) {
		axismundi_cal_calendar_delete( (int) $ax_rw_calendar );
	}
	// The fixture Group, removed row by row: Actors has no delete for a managed identity, and a
	// tombstone would leave a permanent Actor behind for every run of this file.
	foreach ( array_unique( $ax_rw_groups ) as $ax_rw_group_id ) {
		foreach ( array( axismundi_actors_texts_table(), axismundi_actors_addresses_table(), axismundi_actors_endpoints_table(), axismundi_actors_managers_table() ) as $ax_rw_table ) {
			$wpdb->delete( $ax_rw_table, array( 'identity_id' => (int) $ax_rw_group_id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		}
		$wpdb->delete( axismundi_actors_actors_table(), array( 'identity_id' => (int) $ax_rw_group_id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->delete( axismundi_actors_identities_table(), array( 'id' => (int) $ax_rw_group_id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	}
	foreach ( $ax_rw_users as $ax_rw_user_id ) {
		wp_delete_user( (int) $ax_rw_user_id );
	}
}

$ax_rw_failures = count( array_filter( $ax_rw_results, static fn( bool $result ) : bool => ! $result ) );
// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI fixture output.
printf( "\n== %d checks, %d failed ==\n", count( $ax_rw_results ), $ax_rw_failures );
if ( class_exists( 'WP_CLI' ) ) {
	WP_CLI::halt( $ax_rw_failures > 0 ? 1 : 0 );
}
exit( $ax_rw_failures > 0 ? 1 : 0 );
