<?php
/**
 * Contacts data with nobody left to own it (dev-only; dist-excluded).
 *
 * Two different things go wrong and only one of them looks like it. An `owner_actor_id` naming an
 * Actor the registry has never heard of is unambiguous. A Person whose account was deleted while
 * their address book stayed is not visible at all from the Contacts side: the Actor row is still
 * there, exactly as it should be, because an ended identity leaves one standing.
 *
 * So most of what this pins is what must *not* be swept up. A tombstone is the normal end of every
 * Actor and says an identity ended, not that anybody may destroy what it owned -- and the Cards
 * other people keep about a departed Actor are those people's records, written by them, and are
 * none of this sweep's business.
 *
 * The fixtures here remove their own Actors as well as their own users. A maintenance sweep that
 * had to clean up after the audit for it would be a sweep whose test could never say whether it
 * left anything behind.
 *
 * @package AxismundiContacts
 */

defined( 'ABSPATH' ) || exit( 1 );

$ax_oo_results = array();
$ax_oo_actors  = array();
$ax_oo_users   = array();

/** @param bool[] $results Results. */
function ax_oo_assert( array &$results, string $label, bool $condition ) : void {
	$results[] = $condition;
	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI fixture output.
	printf( "[%s] %s\n", $condition ? 'PASS' : 'FAIL', $label );
}

/** How many rows the three tables hold, for the count that must not move. */
function ax_oo_census() : array {
	global $wpdb;
	return array(
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fixture census.
		'actors'   => (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . axismundi_actors_actors_table() ),
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fixture census.
		'cards'    => (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . axismundi_contacts_cards_table() ),
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fixture census.
		'books'    => (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . axismundi_contacts_books_table() ),
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fixture census.
		'profiles' => (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . axismundi_contacts_profiles_table() ),
	);
}

/** An account with a Person Actor, remembered so both can be removed again. */
function ax_oo_person( array &$actors, array &$users ) : int {
	$login = 'axoo' . strtolower( wp_generate_password( 8, false, false ) );
	$id    = (int) wp_insert_user(
		array( 'user_login' => $login, 'user_email' => $login . '@example.test', 'user_pass' => wp_generate_password(), 'role' => 'administrator' )
	);
	$users[] = $id;
	$actor   = axismundi_actors_ensure_for_user( $id );
	axismundi_actors_register_handle( $actor->get_identity_id(), $login );
	axismundi_actors_set_status( $actor->get_identity_id(), 'public' );
	$identity_id = (int) axismundi_actors_get_for_user( $id )->get_identity_id();
	$actors[]    = $identity_id;
	return $identity_id;
}

/** Take an Actor out of the registry entirely, which normal use never does. */
function ax_oo_erase_actor( int $identity_id ) : void {
	global $wpdb;
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fixture cleanup.
	$wpdb->delete( axismundi_actors_actors_table(), array( 'identity_id' => $identity_id ), array( '%d' ) );
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fixture cleanup.
	$wpdb->delete( axismundi_actors_identities_table(), array( 'id' => $identity_id ), array( '%d' ) );
}

try {
	global $wpdb;

	/*
	 * The site is swept clean first. What this file measures is what it leaves behind, and starting
	 * from whatever earlier work left lying around would measure that instead.
	 */
	axismundi_contacts_purge_orphaned_contact_owners();
	$ax_oo_before = ax_oo_census();
	ax_oo_assert(
		$ax_oo_results,
		'a swept site reports nothing left to sweep',
		array() === axismundi_contacts_orphaned_contact_owners()
			&& array() === axismundi_contacts_dangling_profile_bindings()
	);

	// -- an owner the registry has never heard of ---------------------------------------------------------

	/*
	 * Whatever removed that Actor did not come through the lifecycle, so nothing was cleaned up on the
	 * way. The address book is real, it holds real data, and no account will ever be allowed to open
	 * it.
	 */
	$ax_oo_gone_id = ax_oo_person( $ax_oo_actors, $ax_oo_users );
	axismundi_contacts_book_for_actor( $ax_oo_gone_id );
	axismundi_contacts_create_profile_card( $ax_oo_gone_id );
	ax_oo_erase_actor( $ax_oo_gone_id );
	$ax_oo_found = axismundi_contacts_orphaned_contact_owners();
	ax_oo_assert(
		$ax_oo_results,
		'a book whose owner is not in the registry at all is found, and said to be that kind',
		isset( $ax_oo_found[ $ax_oo_gone_id ] )
			&& 'referential' === $ax_oo_found[ $ax_oo_gone_id ]['reason']
			&& 1 === (int) $ax_oo_found[ $ax_oo_gone_id ]['footprint']['books']
	);

	// -- a Person whose account is gone -------------------------------------------------------------------

	/*
	 * Invisible from the Contacts side: the Actor row is present and correct. What makes it an orphan
	 * is that a Person's Contacts data ends with the account, and the account has ended.
	 */
	$ax_oo_left_id = ax_oo_person( $ax_oo_actors, $ax_oo_users );
	axismundi_contacts_book_for_actor( $ax_oo_left_id );
	$ax_oo_left_user = (int) axismundi_actors_get_by_identity( $ax_oo_left_id )->get_local_user_id();
	// Deleted the way a site with the lifecycle hook switched off would have done it.
	remove_action( 'deleted_user', 'axismundi_contacts_purge_for_deleted_user', 20 );
	wp_delete_user( $ax_oo_left_user );
	add_action( 'deleted_user', 'axismundi_contacts_purge_for_deleted_user', 20 );

	$ax_oo_found = axismundi_contacts_orphaned_contact_owners();
	$ax_oo_left  = axismundi_actors_get_by_identity( $ax_oo_left_id );
	ax_oo_assert(
		$ax_oo_results,
		'a Person whose account is gone is found even though its Actor row is exactly as it should be',
		$ax_oo_left instanceof Axismundi_Actor
			&& 'tombstone' === $ax_oo_left->get_status()
			&& isset( $ax_oo_found[ $ax_oo_left_id ] )
			&& 'lifecycle' === $ax_oo_found[ $ax_oo_left_id ]['reason']
	);

	// -- what a tombstone does not mean -------------------------------------------------------------------

	/*
	 * Every Actor reaches one. It says an identity ended, and an Organization's client list is the
	 * Organization's rather than the administrator's who happened to be deleted -- so a sweep that read
	 * `tombstone` as permission would destroy exactly the data somebody kept the Actor row for.
	 */
	$ax_oo_person_id = ax_oo_person( $ax_oo_actors, $ax_oo_users );
	axismundi_contacts_book_for_actor( $ax_oo_person_id );
	axismundi_actors_set_status( $ax_oo_person_id, 'tombstone' );

	$ax_oo_org = axismundi_actors_create_managed_actor(
		array(
			'owner_user_id'      => (int) $ax_oo_users[0],
			'actor_type'         => 'Organization',
			'preferred_username' => 'axoo' . strtolower( wp_generate_password( 6, false, false ) ),
		)
	);
	$ax_oo_org_id = $ax_oo_org instanceof Axismundi_Actor ? (int) $ax_oo_org->get_identity_id() : 0;
	$ax_oo_actors[] = $ax_oo_org_id;
	axismundi_contacts_book_for_actor( $ax_oo_org_id );
	axismundi_actors_set_status( $ax_oo_org_id, 'tombstone' );

	$ax_oo_found = axismundi_contacts_orphaned_contact_owners();
	ax_oo_assert(
		$ax_oo_results,
		'a tombstoned Person whose account is still there is not swept, and neither is a tombstoned Organization',
		$ax_oo_org_id > 0
			&& ! isset( $ax_oo_found[ $ax_oo_person_id ] )
			&& ! isset( $ax_oo_found[ $ax_oo_org_id ] )
			&& 'manual' === axismundi_contacts_purge_policy( 'Organization' )
	);

	// -- what other people keep about them ----------------------------------------------------------------

	/*
	 * The boundary the purge has always drawn, checked here because a sweep is where somebody would be
	 * most tempted to cross it. A Card about the departed Actor, written by somebody else, in somebody
	 * else's book, is that person's record.
	 */
	$ax_oo_keeper_id = ax_oo_person( $ax_oo_actors, $ax_oo_users );
	$ax_oo_book      = axismundi_contacts_book_for_actor( $ax_oo_keeper_id );
	$ax_oo_about     = axismundi_contacts_save_card(
		(int) $ax_oo_book['id'],
		array( '@type' => 'Card', 'kind' => 'individual', 'name' => array( '@type' => 'Name', 'full' => 'Somebody who left' ) )
	);
	$ax_oo_about_id = is_wp_error( $ax_oo_about ) ? 0 : (int) $ax_oo_about;
	if ( $ax_oo_about_id > 0 ) {
		axismundi_contacts_link_actor( $ax_oo_about_id, (string) $ax_oo_left_id, 'Axismundi', 'axismundi' );
	}

	$ax_oo_report = axismundi_contacts_purge_orphaned_contact_owners();
	$ax_oo_kinds = array();
	foreach ( $ax_oo_report['actors'] as $ax_oo_actor_id => $ax_oo_entry ) {
		$ax_oo_kinds[ (int) $ax_oo_actor_id ] = (string) $ax_oo_entry['reason'];
	}
	ax_oo_assert(
		$ax_oo_results,
		'the sweep removes both kinds and nothing else',
		array( $ax_oo_gone_id => 'referential', $ax_oo_left_id => 'lifecycle' ) === $ax_oo_kinds
	);
	ax_oo_assert(
		$ax_oo_results,
		'a Card somebody else keeps about the departed Actor is still theirs',
		$ax_oo_about_id > 0
			&& array() !== axismundi_contacts_get_card( $ax_oo_about_id )
			&& 0 < axismundi_contacts_actor_footprint( $ax_oo_keeper_id )['books']
	);
	ax_oo_assert(
		$ax_oo_results,
		'and the two that were not orphans keep everything they had',
		1 === axismundi_contacts_actor_footprint( $ax_oo_person_id )['books']
			&& 1 === axismundi_contacts_actor_footprint( $ax_oo_org_id )['books']
	);

	// -- running it again -----------------------------------------------------------------------------------

	$ax_oo_again = axismundi_contacts_purge_orphaned_contact_owners();
	ax_oo_assert(
		$ax_oo_results,
		'a second sweep finds nothing, because the first one answered the question',
		array() === $ax_oo_again['actors']
			&& 0 === $ax_oo_again['bindings']
			&& array() === axismundi_contacts_orphaned_contact_owners()
	);

	// -- a binding pointing at a Card that is gone ----------------------------------------------------------

	/*
	 * Its own question rather than an ownership one, and the leak that made most of the orphans on this
	 * development site: deleting a Card used to leave behind whoever pointed at it as their profile.
	 */
	$ax_oo_bind_id = ax_oo_person( $ax_oo_actors, $ax_oo_users );
	$ax_oo_bind_card = axismundi_contacts_create_profile_card( $ax_oo_bind_id );
	if ( ! is_wp_error( $ax_oo_bind_card ) ) {
		axismundi_contacts_delete_card( (int) $ax_oo_bind_card );
	}
	ax_oo_assert(
		$ax_oo_results,
		'deleting a Card stops anybody pointing at it, so no binding is left resolving to nothing',
		0 === axismundi_contacts_profile_card( $ax_oo_bind_id )
			&& array() === axismundi_contacts_dangling_profile_bindings()
	);
} finally {
	global $wpdb;
	foreach ( array_unique( array_filter( $ax_oo_actors ) ) as $ax_oo_actor_id ) {
		axismundi_contacts_purge_actor( (int) $ax_oo_actor_id );
		ax_oo_erase_actor( (int) $ax_oo_actor_id );
	}
	foreach ( array_unique( $ax_oo_users ) as $ax_oo_user_id ) {
		if ( get_userdata( (int) $ax_oo_user_id ) ) {
			wp_delete_user( (int) $ax_oo_user_id );
		}
	}
	$ax_oo_after = isset( $ax_oo_before ) ? ax_oo_census() : array();
	if ( isset( $ax_oo_before ) ) {
		ax_oo_assert(
			$ax_oo_results,
			'and this audit leaves the site holding exactly what it found',
			$ax_oo_before === $ax_oo_after
		);
	}
}

$ax_oo_failures = count( array_filter( $ax_oo_results, static fn( bool $result ) : bool => ! $result ) );
// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI fixture output.
printf( "\n== %d checks, %d failed ==\n", count( $ax_oo_results ), $ax_oo_failures );
if ( class_exists( 'WP_CLI' ) ) {
	WP_CLI::halt( $ax_oo_failures > 0 ? 1 : 0 );
}
exit( $ax_oo_failures > 0 ? 1 : 0 );
