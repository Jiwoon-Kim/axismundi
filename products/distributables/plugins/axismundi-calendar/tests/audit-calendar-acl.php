<?php
/**
 * Calendar access control (dev-only; dist-excluded).
 *
 * Three relations that must not collapse into each other:
 *
 *   authority   the one Actor a Calendar belongs to, and what a transfer would move
 *   ACL         what a principal may do, which several Actors can hold at once
 *   list entry  how one Actor displays it
 *
 * The Group cases are the substantive ones. A Calendar can belong to a managed Group, and that
 * Group's managers administer it -- but a Forum moderator is not a Group manager, and a Group member
 * is neither. Those are different relations in different plugins, and quietly treating one as the
 * other would grant calendar access to everyone in a community because somebody moderates it.
 *
 * @package AxismundiCalendar
 */

defined( 'ABSPATH' ) || exit( 1 );

global $wpdb;
$ax_ac_results   = array();
$ax_ac_calendars = array();
$ax_ac_users     = array();
$ax_ac_groups    = array();

/** @param bool[] $results Results. */
function ax_ac_assert( array &$results, string $label, bool $condition ) : void {
	$results[] = $condition;
	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI fixture output.
	printf( "[%s] %s\n", $condition ? 'PASS' : 'FAIL', $label );
}

/** A user with a public Person Actor. */
function ax_ac_user( array &$users, string $role ) : array {
	$login   = 'ax_ac_' . strtolower( wp_generate_password( 8, false, false ) );
	$id      = (int) wp_insert_user( array( 'user_login' => $login, 'user_pass' => wp_generate_password(), 'role' => $role ) );
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
	$ax_ac_alice   = ax_ac_user( $ax_ac_users, 'editor' );
	$ax_ac_bob     = ax_ac_user( $ax_ac_users, 'author' );
	$ax_ac_carol   = ax_ac_user( $ax_ac_users, 'author' );
	$ax_ac_stranger = ax_ac_user( $ax_ac_users, 'author' );

	$ax_ac_cal = axismundi_cal_calendar_save(
		array( 'name' => 'ACL fixture', 'slug' => 'ax-ac-cal', 'timezone' => 'Asia/Seoul', 'owner_actor_uri' => $ax_ac_alice['actor_uri'] )
	);
	$ax_ac_calendars[] = (int) $ax_ac_cal;

	// -- Authority and ACL are different facts -------------------------------------------------

	$ax_ac_row = axismundi_cal_calendar_get( (int) $ax_ac_cal );
	ax_ac_assert( $ax_ac_results, 'creating a calendar records the Actor it belongs to', $ax_ac_alice['actor_uri'] === (string) $ax_ac_row['authority_actor_uri'] );
	ax_ac_assert( $ax_ac_results, 'and hashes it, so the column can be joined on', hash( 'sha256', $ax_ac_alice['actor_uri'] ) === (string) $ax_ac_row['authority_actor_uri_hash'] );
	ax_ac_assert( $ax_ac_results, 'and gives that Actor an owner rule', 'owner' === (string) axismundi_cal_acl_rule( (int) $ax_ac_cal, $ax_ac_alice['actor_uri'] )['role'] );

	axismundi_cal_acl_grant( (int) $ax_ac_cal, $ax_ac_bob['actor_uri'], 'owner' );
	ax_ac_assert(
		$ax_ac_results,
		'a second Actor can hold the owner role without becoming the Calendar authority',
		'owner' === axismundi_cal_effective_role( (int) $ax_ac_cal, $ax_ac_bob['actor_uri'] )
			&& $ax_ac_alice['actor_uri'] === (string) axismundi_cal_calendar_get( (int) $ax_ac_cal )['authority_actor_uri']
	);

	// -- Roles ----------------------------------------------------------------------------------

	axismundi_cal_acl_grant( (int) $ax_ac_cal, $ax_ac_carol['actor_uri'], 'reader' );
	ax_ac_assert( $ax_ac_results, 'a reader may read', true === axismundi_cal_can_read( (int) $ax_ac_cal, $ax_ac_carol['actor_uri'] ) );
	ax_ac_assert( $ax_ac_results, 'and may not write', false === axismundi_cal_can_write( (int) $ax_ac_cal, $ax_ac_carol['actor_uri'] ) );

	axismundi_cal_acl_grant( (int) $ax_ac_cal, $ax_ac_carol['actor_uri'], 'writer' );
	ax_ac_assert( $ax_ac_results, 'granting again changes the role rather than adding a second rule', 1 === count( array_filter( axismundi_cal_acl_rules( (int) $ax_ac_cal ), static fn( array $r ) : bool => $r['principal_uri'] === $ax_ac_carol['actor_uri'] ) ) );
	ax_ac_assert( $ax_ac_results, 'and the new role applies', true === axismundi_cal_can_write( (int) $ax_ac_cal, $ax_ac_carol['actor_uri'] ) );

	ax_ac_assert( $ax_ac_results, 'an Actor with no rule has no access', '' === axismundi_cal_effective_role( (int) $ax_ac_cal, $ax_ac_stranger['actor_uri'] ) );
	ax_ac_assert( $ax_ac_results, 'and neither does an anonymous reader, because a calendar is private unless somebody said otherwise', false === axismundi_cal_can_read( (int) $ax_ac_cal, '' ) );
	ax_ac_assert( $ax_ac_results, 'so it is not public', false === axismundi_cal_is_publicly_readable( (int) $ax_ac_cal ) );

	// -- Public is stated, never inferred --------------------------------------------------------

	axismundi_cal_acl_grant( (int) $ax_ac_cal, '', 'reader', 'public' );
	ax_ac_assert( $ax_ac_results, 'a public rule makes it readable by anyone', true === axismundi_cal_can_read( (int) $ax_ac_cal, '' ) && true === axismundi_cal_is_publicly_readable( (int) $ax_ac_cal ) );
	ax_ac_assert( $ax_ac_results, 'without letting anyone write to it', false === axismundi_cal_can_write( (int) $ax_ac_cal, '' ) );
	ax_ac_assert(
		$ax_ac_results,
		'and granting the world write access is refused rather than stored, since nobody means that',
		is_wp_error( axismundi_cal_acl_grant( (int) $ax_ac_cal, '', 'writer', 'public' ) )
	);
	ax_ac_assert( $ax_ac_results, 'a free/busy rule discloses time without disclosing what the time is for', ! is_wp_error( axismundi_cal_acl_grant( (int) $ax_ac_cal, '', 'freeBusyReader', 'public' ) ) );
	ax_ac_assert( $ax_ac_results, 'which is less than reading, so the calendar is no longer publicly readable', false === axismundi_cal_is_publicly_readable( (int) $ax_ac_cal ) );
	/*
	 * The distinction the two helpers exist for. One rule answers yes to being busy and no to what
	 * the busy time is, and a single `is_public()` could not have said both.
	 */
	ax_ac_assert( $ax_ac_results, 'though it is still publicly free/busy, which is the whole point of the role', true === axismundi_cal_is_publicly_freebusy( (int) $ax_ac_cal ) );
	axismundi_cal_acl_grant( (int) $ax_ac_cal, '', 'reader', 'public' );
	ax_ac_assert( $ax_ac_results, 'and a full public reader is free/busy too, since reading everything includes reading when', true === axismundi_cal_is_publicly_freebusy( (int) $ax_ac_cal ) );
	axismundi_cal_acl_revoke( (int) $ax_ac_cal, '', 'public' );
	ax_ac_assert( $ax_ac_results, 'revoking the public rule closes it again', false === axismundi_cal_can_read( (int) $ax_ac_cal, '' ) );

	ax_ac_assert( $ax_ac_results, 'an invented role is refused', is_wp_error( axismundi_cal_acl_grant( (int) $ax_ac_cal, $ax_ac_bob['actor_uri'], 'admin' ) ) );
	ax_ac_assert( $ax_ac_results, 'and an Actor rule with no Actor is refused', is_wp_error( axismundi_cal_acl_grant( (int) $ax_ac_cal, '  ', 'reader' ) ) );

	// -- A Calendar belonging to a managed Group -------------------------------------------------

	if ( function_exists( 'axismundi_actors_create_managed_group' ) ) {
		$ax_ac_group = axismundi_actors_create_managed_group(
			array(
				'preferred_username' => 'axacgrp' . strtolower( wp_generate_password( 6, false, false ) ),
				'display_name'       => 'ACL fixture group',
				'owner_user_id'      => $ax_ac_alice['user_id'],
			)
		);
		if ( $ax_ac_group instanceof Axismundi_Actor ) {
			$ax_ac_group_identity = (int) $ax_ac_group->get_identity_id();
			$ax_ac_groups[]       = $ax_ac_group_identity;
			$ax_ac_group_uri      = (string) $ax_ac_group->get_uri();

			$ax_ac_gcal = axismundi_cal_calendar_save(
				array( 'name' => 'Group calendar', 'slug' => 'ax-ac-group-cal', 'timezone' => 'Asia/Seoul', 'owner_actor_uri' => $ax_ac_group_uri )
			);
			$ax_ac_calendars[] = (int) $ax_ac_gcal;

			ax_ac_assert( $ax_ac_results, 'a Group Actor can be the authority of a Calendar', $ax_ac_group_uri === (string) axismundi_cal_calendar_get( (int) $ax_ac_gcal )['authority_actor_uri'] );

			// The Group's owner manages it, through Actors' own manager kernel.
			ax_ac_assert(
				$ax_ac_results,
				'a manager of that Group administers its Calendar',
				true === axismundi_cal_can_write( (int) $ax_ac_gcal, $ax_ac_alice['actor_uri'], $ax_ac_alice['user_id'] )
			);

			/*
			 * The rule that matters. Somebody who moderates a community, or merely belongs to a
			 * Group, is not an administrator of the identity that community publishes under. Treating
			 * those as the same relation would hand a Group's calendar to everyone who moderates its
			 * forum.
			 */
			ax_ac_assert(
				$ax_ac_results,
				'while somebody who is not a manager of that Group does not, whatever else they moderate',
				false === axismundi_cal_can_write( (int) $ax_ac_gcal, $ax_ac_bob['actor_uri'], $ax_ac_bob['user_id'] )
			);
			ax_ac_assert(
				$ax_ac_results,
				'and cannot even read it without a rule',
				false === axismundi_cal_can_read( (int) $ax_ac_gcal, $ax_ac_bob['actor_uri'], $ax_ac_bob['user_id'] )
			);

			/*
			 * The threshold itself. Actors ranks Group relations owner > manager > editor, and an
			 * editor authors under the Group's identity rather than administering it -- so an editor
			 * does not thereby get its calendar. Without this case the check would pass whether it
			 * asked for `manager` or for any relation at all, because the other fixtures are an owner
			 * and somebody with no relation.
			 */
			if ( function_exists( 'axismundi_actors_add_manager' ) ) {
				axismundi_actors_add_manager( $ax_ac_group_identity, $ax_ac_carol['user_id'], 'editor' );
				ax_ac_assert(
					$ax_ac_results,
					'an editor of that Group does not thereby administer its Calendar',
					false === axismundi_cal_can_write( (int) $ax_ac_gcal, $ax_ac_carol['actor_uri'], $ax_ac_carol['user_id'] )
				);
				axismundi_actors_add_manager( $ax_ac_group_identity, $ax_ac_carol['user_id'], 'manager' );
				ax_ac_assert(
					$ax_ac_results,
					'while promoting them to manager does',
					true === axismundi_cal_can_write( (int) $ax_ac_gcal, $ax_ac_carol['actor_uri'], $ax_ac_carol['user_id'] )
				);
			}

			// Access is granted explicitly, not inferred from membership.
			axismundi_cal_acl_grant( (int) $ax_ac_gcal, $ax_ac_bob['actor_uri'], 'writer' );
			ax_ac_assert(
				$ax_ac_results,
				'an explicit rule is how somebody outside the Group gets access',
				true === axismundi_cal_can_write( (int) $ax_ac_gcal, $ax_ac_bob['actor_uri'], $ax_ac_bob['user_id'] )
			);

			// Being a manager is resolved per Calendar, not globally.
			ax_ac_assert(
				$ax_ac_results,
				'and managing that Group grants nothing on a Calendar it does not own',
				'owner' !== axismundi_cal_effective_role( (int) $ax_ac_cal, 'https://example.test/@nobody', $ax_ac_alice['user_id'] )
					|| $ax_ac_alice['actor_uri'] === (string) axismundi_cal_calendar_get( (int) $ax_ac_cal )['authority_actor_uri']
			);
		}
	}

	// -- A subscribed Calendar has no local authority ---------------------------------------------

	$ax_ac_rejected_remote = axismundi_cal_calendar_save(
		array( 'name' => 'Remote ACL fixture', 'slug' => 'ax-ac-remote', 'kind' => 'remote', 'timezone' => 'Asia/Seoul', 'owner_actor_uri' => $ax_ac_alice['actor_uri'] )
	);
	ax_ac_assert(
		$ax_ac_results,
		'a subscribed calendar refuses a local authority at the writer',
		is_wp_error( $ax_ac_rejected_remote ) && 'ax_cal_authority_remote' === $ax_ac_rejected_remote->get_error_code()
	);
	$ax_ac_remote = axismundi_cal_calendar_save(
		array( 'name' => 'Remote ACL fixture', 'slug' => 'ax-ac-remote', 'kind' => 'remote', 'timezone' => 'Asia/Seoul' )
	);
	$ax_ac_calendars[] = (int) $ax_ac_remote;
	ax_ac_assert(
		$ax_ac_results,
		'subscribing gives this site no authority over somebody else\'s calendar',
		'' === (string) axismundi_cal_calendar_get( (int) $ax_ac_remote )['authority_actor_uri']
	);

	// -- The migration verifier ---------------------------------------------------------------------

	$ax_ac_verify = axismundi_cal_verify_authority_migration();
	ax_ac_assert( $ax_ac_results, 'authority, ACL and the legacy owner agree', true === $ax_ac_verify['ok'] );
	ax_ac_assert( $ax_ac_results, 'with no local calendar left without an authority', array() === $ax_ac_verify['missing_authority'] );
	ax_ac_assert( $ax_ac_results, 'no authority without its owner rule', array() === $ax_ac_verify['missing_rule'] );
	ax_ac_assert( $ax_ac_results, 'no subscribed calendar claiming a local authority', array() === $ax_ac_verify['remote_authority'] );
	ax_ac_assert( $ax_ac_results, 'and no public rule stronger than reading', array() === $ax_ac_verify['public_write'] );

	// It has to be able to say no, or a green result before the legacy drop means nothing.
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- audit fixture forcing a bad state.
	$wpdb->update( axismundi_cal_calendars_table(), array( 'authority_actor_uri_hash' => hash( 'sha256', 'wrong' ) ), array( 'id' => (int) $ax_ac_cal ) );
	$ax_ac_bad = axismundi_cal_verify_authority_migration();
	ax_ac_assert( $ax_ac_results, 'a hash that does not match its authority is reported', in_array( (int) $ax_ac_cal, $ax_ac_bad['bad_hash'], true ) && false === $ax_ac_bad['ok'] );
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- audit fixture cleanup.
	$wpdb->update( axismundi_cal_calendars_table(), array( 'authority_actor_uri_hash' => hash( 'sha256', $ax_ac_alice['actor_uri'] ) ), array( 'id' => (int) $ax_ac_cal ) );

	// -- Rules go with the Calendar ------------------------------------------------------------------

	axismundi_cal_calendar_delete( (int) $ax_ac_cal );
	ax_ac_assert( $ax_ac_results, 'deleting a calendar drops its access rules', array() === axismundi_cal_acl_rules( (int) $ax_ac_cal ) );
} finally {
	foreach ( array_unique( $ax_ac_calendars ) as $ax_ac_id ) {
		axismundi_cal_acl_forget_calendar( (int) $ax_ac_id );
		axismundi_cal_list_forget_calendar( (int) $ax_ac_id );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- audit cleanup, including remote rows delete() refuses.
		$wpdb->delete( axismundi_cal_calendars_table(), array( 'id' => (int) $ax_ac_id ) );
	}
	/*
	 * Group fixtures are removed by the same narrow path the other audits use: a blanket delete by
	 * scope would take real managed Groups with it, and this database has some.
	 */
	foreach ( array_unique( $ax_ac_groups ) as $ax_ac_group_id ) {
		foreach ( array( axismundi_actors_texts_table(), axismundi_actors_addresses_table(), axismundi_actors_endpoints_table(), axismundi_actors_managers_table() ) as $ax_ac_table ) {
			$wpdb->delete( $ax_ac_table, array( 'identity_id' => (int) $ax_ac_group_id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		}
		$wpdb->delete( axismundi_actors_actors_table(), array( 'identity_id' => (int) $ax_ac_group_id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->delete( axismundi_actors_identities_table(), array( 'id' => (int) $ax_ac_group_id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	}
	foreach ( array_unique( $ax_ac_users ) as $ax_ac_user_id ) {
		wp_delete_user( (int) $ax_ac_user_id );
	}
}

$ax_ac_failures = count( array_filter( $ax_ac_results, static fn( bool $result ) : bool => ! $result ) );
// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI fixture output.
printf( "\n== %d checks, %d failed ==\n", count( $ax_ac_results ), $ax_ac_failures );
if ( class_exists( 'WP_CLI' ) ) {
	WP_CLI::halt( $ax_ac_failures > 0 ? 1 : 0 );
}
exit( $ax_ac_failures > 0 ? 1 : 0 );
