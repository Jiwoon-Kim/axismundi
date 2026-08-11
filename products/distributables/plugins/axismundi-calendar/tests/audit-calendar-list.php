<?php
/**
 * The CalendarList, and the migration out of `owner_actor_uri` (dev-only; dist-excluded).
 *
 * This is the audit that gates dropping the column. Once it is gone there is nothing left to compare
 * against, so every claim the drop depends on has to be true before it runs: every local Calendar
 * that named an owner has exactly one owner entry, the entries hash correctly, and no subscribed
 * Calendar acquired an owner it never had.
 *
 * The last one is the substantive rule rather than a formality. A remote Calendar's authority is the
 * feed it projects; recording the subscriber as its owner would be this site claiming it can speak
 * for somebody else's calendar, and nothing later would distinguish that claim from a real one.
 *
 * @package AxismundiCalendar
 */

defined( 'ABSPATH' ) || exit( 1 );

global $wpdb;
$ax_ls_results   = array();
$ax_ls_calendars = array();

/** @param bool[] $results Results. */
function ax_ls_assert( array &$results, string $label, bool $condition ) : void {
	$results[] = $condition;
	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI fixture output.
	printf( "[%s] %s\n", $condition ? 'PASS' : 'FAIL', $label );
}

try {
	$ax_ls_alice = 'https://example.test/@alice-' . wp_generate_password( 6, false, false );
	$ax_ls_bob   = 'https://example.test/@bob-' . wp_generate_password( 6, false, false );

	// -- The relation itself -------------------------------------------------------------------

	$ax_ls_cal = axismundi_cal_calendar_save(
		array( 'name' => 'List fixture', 'slug' => 'ax-ls-cal', 'timezone' => 'Asia/Seoul', 'owner_actor_uri' => $ax_ls_alice )
	);
	ax_ls_assert( $ax_ls_results, 'a calendar is created', is_int( $ax_ls_cal ) && $ax_ls_cal > 0 );
	$ax_ls_calendars[] = (int) $ax_ls_cal;

	ax_ls_assert(
		$ax_ls_results,
		'creating one records its authority on the Calendar itself',
		$ax_ls_alice === axismundi_cal_calendar_authority( (int) $ax_ls_cal )
	);

	$ax_ls_entry = axismundi_cal_list_entry( (int) $ax_ls_cal, $ax_ls_alice );
	ax_ls_assert(
		$ax_ls_results,
		'the default sidebar entry carries a hash of the Actor URI but not authority',
		is_array( $ax_ls_entry ) && 'reader' === $ax_ls_entry['access_role'] && hash( 'sha256', $ax_ls_alice ) === $ax_ls_entry['actor_uri_hash']
	);

	axismundi_cal_list_set( (int) $ax_ls_cal, $ax_ls_bob, 'reader' );
	ax_ls_assert( $ax_ls_results, 'a second Actor can stand in a different relation to the same calendar', 2 === count( axismundi_cal_calendar_list_entries( (int) $ax_ls_cal ) ) );
	ax_ls_assert( $ax_ls_results, 'and the owner is still the owner', $ax_ls_alice === axismundi_cal_calendar_owner( (int) $ax_ls_cal ) );

	axismundi_cal_list_set( (int) $ax_ls_cal, $ax_ls_bob, 'writer', array( 'selected' => false ) );
	ax_ls_assert(
		$ax_ls_results,
		'setting an Actor again updates their view state instead of adding a second entry',
		2 === count( axismundi_cal_calendar_list_entries( (int) $ax_ls_cal ) )
			&& 0 === (int) axismundi_cal_list_entry( (int) $ax_ls_cal, $ax_ls_bob )['selected']
	);

	ax_ls_assert( $ax_ls_results, 'an entry with no Actor is refused, since it would name no relation', is_wp_error( axismundi_cal_list_set( (int) $ax_ls_cal, '  ', 'reader' ) ) );
	ax_ls_assert( $ax_ls_results, 'and an invented role is refused', is_wp_error( axismundi_cal_list_set( (int) $ax_ls_cal, $ax_ls_bob, 'admin' ) ) );

	// -- A stable public identifier ----------------------------------------------------------------

	/*
	 * The slug is editable and is the subscription address people already hold, so it cannot also be
	 * the key an API or a stored reference uses: renaming a calendar would break every one of them
	 * silently. The UUID is what survives a rename.
	 */
	$ax_ls_created = axismundi_cal_calendar_get( (int) $ax_ls_cal );
	ax_ls_assert( $ax_ls_results, 'a calendar is given a public identifier', 36 === strlen( (string) $ax_ls_created['uuid'] ) );
	ax_ls_assert( $ax_ls_results, 'which resolves back to it', (int) $ax_ls_cal === (int) axismundi_cal_calendar_by_uuid( (string) $ax_ls_created['uuid'] )['id'] );

	axismundi_cal_calendar_save( array( 'slug' => 'ax-ls-renamed' ), (int) $ax_ls_cal );
	$ax_ls_renamed = axismundi_cal_calendar_get( (int) $ax_ls_cal );
	ax_ls_assert(
		$ax_ls_results,
		'and survives a rename, which is the whole reason it is not the slug',
		'ax-ls-renamed' === (string) $ax_ls_renamed['slug'] && (string) $ax_ls_created['uuid'] === (string) $ax_ls_renamed['uuid']
	);

	// -- Writing is owner or writer, not owner alone -------------------------------------------------

	ax_ls_assert( $ax_ls_results, 'an owner may write', true === axismundi_cal_actor_may_write( (int) $ax_ls_cal, $ax_ls_alice ) );
	axismundi_cal_acl_grant( (int) $ax_ls_cal, $ax_ls_bob, 'writer' );
	ax_ls_assert( $ax_ls_results, 'a writer may too', true === axismundi_cal_actor_may_write( (int) $ax_ls_cal, $ax_ls_bob ) );
	axismundi_cal_acl_grant( (int) $ax_ls_cal, $ax_ls_bob, 'reader' );
	ax_ls_assert( $ax_ls_results, 'and a reader may not, which is what makes the role mean anything', false === axismundi_cal_actor_may_write( (int) $ax_ls_cal, $ax_ls_bob ) );
	ax_ls_assert( $ax_ls_results, 'nor may an Actor with no relation at all', false === axismundi_cal_actor_may_write( (int) $ax_ls_cal, 'https://example.test/@stranger' ) );

	// -- The view state belongs to the entry, not the Calendar ----------------------------------------

	axismundi_cal_list_set( (int) $ax_ls_cal, $ax_ls_bob, 'reader', array( 'hidden' => true, 'summary_override' => 'Bob calls it this', 'color' => '#123456' ) );
	$ax_ls_bob_entry = axismundi_cal_list_entry( (int) $ax_ls_cal, $ax_ls_bob );
	ax_ls_assert(
		$ax_ls_results,
		'one Actor can rename and hide a calendar in their own list',
		1 === (int) $ax_ls_bob_entry['hidden'] && 'Bob calls it this' === (string) $ax_ls_bob_entry['summary_override']
	);
	$ax_ls_alice_entry = axismundi_cal_list_entry( (int) $ax_ls_cal, $ax_ls_alice );
	ax_ls_assert(
		$ax_ls_results,
		'without changing it for anyone else, or changing the Calendar itself',
		0 === (int) $ax_ls_alice_entry['hidden'] && '' === (string) $ax_ls_alice_entry['summary_override']
			&& 'List fixture' === (string) axismundi_cal_calendar_get( (int) $ax_ls_cal )['name']
	);

	// -- The Actor's own list --------------------------------------------------------------------

	$ax_ls_second = axismundi_cal_calendar_save(
		array( 'name' => 'Second fixture', 'slug' => 'ax-ls-second', 'timezone' => 'Asia/Seoul', 'owner_actor_uri' => $ax_ls_alice )
	);
	$ax_ls_calendars[] = (int) $ax_ls_second;

	$ax_ls_owned = axismundi_cal_actor_calendar_list( $ax_ls_alice );
	ax_ls_assert( $ax_ls_results, 'an Actor can be asked which calendars they added to their list', 2 === count( $ax_ls_owned ) );
	ax_ls_assert( $ax_ls_results, 'and the rows carry the calendar itself, not only the relation', isset( $ax_ls_owned[0]['name'], $ax_ls_owned[0]['access_role'] ) );
	ax_ls_assert( $ax_ls_results, 'while another Actor sees only what they are related to', 1 === count( axismundi_cal_actor_calendar_list( $ax_ls_bob ) ) );

	// -- Permission is judged on the relation ------------------------------------------------------

	$ax_ls_row = axismundi_cal_calendar_get( (int) $ax_ls_cal );
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- audit fixture proving the column is no longer read.
	$wpdb->update( axismundi_cal_calendars_table(), array( 'owner_actor_uri' => 'https://example.test/@someone-else' ), array( 'id' => (int) $ax_ls_cal ) );
	ax_ls_assert(
		$ax_ls_results,
		'the legacy column is no longer consulted: corrupting it does not change who owns the calendar',
		$ax_ls_alice === axismundi_cal_calendar_owner( (int) $ax_ls_cal )
	);
	/*
	 * Put it back before going on. The migration below reads this column by design, so leaving it
	 * corrupted would seed a second owner from a value written here to prove a different point --
	 * the verifier would then correctly report an ambiguity this fixture invented.
	 */
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- audit fixture cleanup.
	$wpdb->update( axismundi_cal_calendars_table(), array( 'owner_actor_uri' => $ax_ls_alice ), array( 'id' => (int) $ax_ls_cal ) );
	unset( $ax_ls_row );

	// -- A subscribed Calendar has no local owner ---------------------------------------------------

	$ax_ls_remote = axismundi_cal_calendar_save(
		array( 'name' => 'Remote fixture', 'slug' => 'ax-ls-remote', 'kind' => 'remote', 'timezone' => 'Asia/Seoul', 'owner_actor_uri' => $ax_ls_alice )
	);
	$ax_ls_calendars[] = (int) $ax_ls_remote;
	ax_ls_assert(
		$ax_ls_results,
		'subscribing does not make the subscriber the owner of somebody else\'s calendar',
		'' === axismundi_cal_calendar_owner( (int) $ax_ls_remote )
	);
	axismundi_cal_list_set( (int) $ax_ls_remote, $ax_ls_alice, 'reader' );
	ax_ls_assert( $ax_ls_results, 'but they can still have it in their list as a reader', 'reader' === (string) axismundi_cal_list_entry( (int) $ax_ls_remote, $ax_ls_alice )['access_role'] );

	// -- The migration, and what gates dropping the column --------------------------------------------

	$ax_ls_legacy = axismundi_cal_calendar_save( array( 'name' => 'Legacy fixture', 'slug' => 'ax-ls-legacy', 'timezone' => 'Asia/Seoul' ) );
	$ax_ls_calendars[] = (int) $ax_ls_legacy;
	// A row as it existed before the relation table: a column value and no entry.
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- audit fixture standing in for a pre-migration row.
	$wpdb->update( axismundi_cal_calendars_table(), array( 'owner_actor_uri' => $ax_ls_bob ), array( 'id' => (int) $ax_ls_legacy ) );
	axismundi_cal_list_remove( (int) $ax_ls_legacy, $ax_ls_bob );

	ax_ls_assert( $ax_ls_results, 'a pre-migration calendar starts with no owner relation', '' === axismundi_cal_calendar_owner( (int) $ax_ls_legacy ) );
	$ax_ls_seeded = axismundi_cal_seed_owner_entries();
	$ax_ls_backfilled = axismundi_cal_backfill_authority();
	axismundi_cal_seed_authority_acl();
	ax_ls_assert( $ax_ls_results, 'the migration carries the legacy owner to Calendar authority', $ax_ls_seeded >= 1 && $ax_ls_backfilled >= 1 && $ax_ls_bob === axismundi_cal_calendar_authority( (int) $ax_ls_legacy ) );

	$ax_ls_before = count( axismundi_cal_calendar_list_entries( (int) $ax_ls_legacy ) );
	axismundi_cal_seed_owner_entries();
	ax_ls_assert(
		$ax_ls_results,
		'running it twice does not produce a second sidebar entry',
		$ax_ls_before === count( axismundi_cal_calendar_list_entries( (int) $ax_ls_legacy ) )
	);

	$ax_ls_verify = axismundi_cal_verify_authority_migration();
	ax_ls_assert( $ax_ls_results, 'the transition verifies, which is what the column drop is gated on', true === $ax_ls_verify['ok'] );
	ax_ls_assert( $ax_ls_results, 'with no legacy owner missing authority', array() === $ax_ls_verify['missing_authority'] );
	ax_ls_assert( $ax_ls_results, 'and no legacy owner mismatched to authority', array() === $ax_ls_verify['mismatched_authority'] );

	// The verifier has to be able to say no, or a green result before the drop means nothing.
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- audit fixture forcing a bad state.
	$wpdb->update( axismundi_cal_calendars_table(), array( 'authority_actor_uri_hash' => hash( 'sha256', 'not-the-right-uri' ) ), array( 'id' => (int) $ax_ls_cal ) );
	$ax_ls_bad = axismundi_cal_verify_authority_migration();
	ax_ls_assert( $ax_ls_results, 'a bad authority hash is reported rather than passing quietly', in_array( (int) $ax_ls_cal, $ax_ls_bad['bad_hash'], true ) );
	ax_ls_assert( $ax_ls_results, 'and the verification says no overall', false === $ax_ls_bad['ok'] );
	$wpdb->update( axismundi_cal_calendars_table(), array( 'authority_actor_uri_hash' => hash( 'sha256', $ax_ls_alice ) ), array( 'id' => (int) $ax_ls_cal ) );

	// -- An upgrade must not cost anything the table alone knows ------------------------------------

	/*
	 * The failure this pins is one this migration nearly shipped. Recreating the table to force a
	 * renamed column looked safe while it held only owners, because those could be reseeded from
	 * `owner_actor_uri`. It stopped being safe the moment the table also held a subscriber's reader
	 * entry on a remote Calendar and everyone's alias, colour and hidden flag -- none of which exist
	 * anywhere else. A rebuild would have returned the owners and quietly lost the rest.
	 */
	axismundi_cal_list_set( (int) $ax_ls_remote, $ax_ls_bob, 'reader', array( 'hidden' => true, 'color' => '#abcdef', 'summary_override' => 'Bob subscribes' ) );
	$ax_ls_pre = axismundi_cal_list_entry( (int) $ax_ls_remote, $ax_ls_bob );

	axismundi_cal_install_schema();

	$ax_ls_post = axismundi_cal_list_entry( (int) $ax_ls_remote, $ax_ls_bob );
	ax_ls_assert(
		$ax_ls_results,
		'running the upgrade again keeps a subscriber entry that no other table could rebuild',
		is_array( $ax_ls_post ) && 'reader' === (string) $ax_ls_post['access_role']
	);
	ax_ls_assert(
		$ax_ls_results,
		'and keeps the view state that belongs to that Actor alone',
		is_array( $ax_ls_post ) && (int) $ax_ls_pre['hidden'] === (int) $ax_ls_post['hidden']
			&& (string) $ax_ls_pre['color'] === (string) $ax_ls_post['color']
			&& (string) $ax_ls_pre['summary_override'] === (string) $ax_ls_post['summary_override']
	);
	ax_ls_assert(
		$ax_ls_results,
		'while the remote Calendar still has no local owner after an upgrade',
		'' === axismundi_cal_calendar_owner( (int) $ax_ls_remote )
	);

	$ax_ls_roles = axismundi_cal_verify_access_role_migration();
	ax_ls_assert( $ax_ls_results, 'and no entry disagrees with the column it was carried from', true === $ax_ls_roles['ok'] );

	/*
	 * The rename itself, exercised on a table that actually has the old column. Without this the copy
	 * is never run: an installation that already went through the reshape has no `role` column left,
	 * so every verifier answers yes about nothing. The legacy shape is recreated deliberately here.
	 */
	$ax_ls_table = axismundi_cal_entries_list_table();
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- audit fixture recreating the pre-rename shape.
	$wpdb->query( "ALTER TABLE {$ax_ls_table} ADD COLUMN role varchar(16) NOT NULL default ''" );
	// A row as it existed before the rename: the old column set, the new one empty.
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- audit fixture.
	$wpdb->update( $ax_ls_table, array( 'role' => 'writer', 'access_role' => '' ), array( 'calendar_id' => (int) $ax_ls_second, 'actor_uri_hash' => hash( 'sha256', $ax_ls_alice ) ) );

	$ax_ls_pending = axismundi_cal_verify_access_role_migration();
	ax_ls_assert( $ax_ls_results, 'before the copy, the verifier sees the old column and says so', true === $ax_ls_pending['legacy_column'] );
	ax_ls_assert( $ax_ls_results, 'and reports the entry that has not been carried across yet', false === $ax_ls_pending['ok'] );

	$ax_ls_copied = axismundi_cal_copy_legacy_access_roles();
	ax_ls_assert( $ax_ls_results, 'the copy carries the old value into the new column', $ax_ls_copied >= 1 );
	ax_ls_assert(
		$ax_ls_results,
		'and the entry now reads as what it always was',
		'writer' === (string) axismundi_cal_list_entry( (int) $ax_ls_second, $ax_ls_alice )['access_role']
	);
	ax_ls_assert( $ax_ls_results, 'after which the verifier agrees, which is what gates dropping the old column', true === axismundi_cal_verify_access_role_migration()['ok'] );

	// Only empty targets are written, so a rerun cannot stamp over a role changed since the copy.
	axismundi_cal_list_set( (int) $ax_ls_second, $ax_ls_alice, 'owner' );
	axismundi_cal_copy_legacy_access_roles();
	ax_ls_assert(
		$ax_ls_results,
		'running the copy again does not undo a role changed after it',
		'owner' === (string) axismundi_cal_list_entry( (int) $ax_ls_second, $ax_ls_alice )['access_role']
	);

	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- audit fixture cleanup, returning the table to its migrated shape.
	$wpdb->query( "ALTER TABLE {$ax_ls_table} DROP COLUMN role" );

	// -- Entries go with the Calendar ------------------------------------------------------------------

	$ax_ls_doomed = axismundi_cal_calendar_save( array( 'name' => 'Doomed', 'slug' => 'ax-ls-doomed', 'timezone' => 'Asia/Seoul', 'owner_actor_uri' => $ax_ls_alice ) );
	axismundi_cal_calendar_delete( (int) $ax_ls_doomed );
	ax_ls_assert(
		$ax_ls_results,
		'deleting a calendar drops its relations, so nobody keeps a list entry for a calendar they cannot open',
		array() === axismundi_cal_calendar_list_entries( (int) $ax_ls_doomed )
	);
} finally {
	foreach ( array_unique( $ax_ls_calendars ) as $ax_ls_id ) {
		axismundi_cal_list_forget_calendar( (int) $ax_ls_id );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- audit fixture cleanup, including remote rows delete() refuses.
		$wpdb->delete( axismundi_cal_calendars_table(), array( 'id' => (int) $ax_ls_id ) );
	}
}

$ax_ls_failures = count( array_filter( $ax_ls_results, static fn( bool $result ) : bool => ! $result ) );
// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI fixture output.
printf( "\n== %d checks, %d failed ==\n", count( $ax_ls_results ), $ax_ls_failures );
if ( class_exists( 'WP_CLI' ) ) {
	WP_CLI::halt( $ax_ls_failures > 0 ? 1 : 0 );
}
exit( $ax_ls_failures > 0 ? 1 : 0 );
