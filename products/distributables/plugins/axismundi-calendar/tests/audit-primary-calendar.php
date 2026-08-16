<?php
/**
 * Where an Event goes when nobody said (dev-only; dist-excluded).
 *
 * The property is that an Event always lands on a Calendar somebody owns. The old answer was a
 * single site-wide `unfiled-events` Calendar belonging to nobody, which fails twice: nothing on an
 * authority-less Calendar can be federated, shared or administered, and one shared Calendar for
 * every author means the first person to publish decides what everyone else's Events are attributed
 * to.
 *
 * The new answer is the author's own Calendar, made the first time they need one. The two halves
 * that matter are that it is made lazily -- a site with two hundred accounts must not carry two
 * hundred empty public feeds -- and that a writer with no Actor is refused rather than given
 * somewhere to put it, because "whose Event is this?" has no correct answer in that case.
 *
 * @package AxismundiCalendar
 */

defined( 'ABSPATH' ) || exit( 1 );

global $wpdb;
$ax_pc_results   = array();
$ax_pc_calendars = array();
$ax_pc_posts     = array();
$ax_pc_users     = array();

/** @param bool[] $results Results. */
function ax_pc_assert( array &$results, string $label, bool $condition ) : void {
	$results[] = $condition;
	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI fixture output.
	printf( "[%s] %s\n", $condition ? 'PASS' : 'FAIL', $label );
}

/** A user with a public Person Actor. */
function ax_pc_user( array &$users ) : array {
	$login   = 'ax_pc_' . strtolower( wp_generate_password( 8, false, false ) );
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

/** Draft an Event, write its envelope, publish it. */
function ax_pc_event( array &$posts, array $fields ) {
	$post_id = (int) wp_insert_post( array( 'post_type' => AXISMUNDI_CAL_EVENT_POST_TYPE, 'post_status' => 'draft', 'post_title' => 'Primary fixture' ) );
	$posts[] = $post_id;
	$saved   = axismundi_cal_event_save( $post_id, $fields );
	if ( is_wp_error( $saved ) ) {
		return $saved;
	}
	$GLOBALS['axismundi_cal_rest_write'] = true;
	wp_update_post( array( 'ID' => $post_id, 'post_status' => 'publish' ) );
	$GLOBALS['axismundi_cal_rest_write'] = false;
	return $post_id;
}

try {
	$ax_pc_author = ax_pc_user( $ax_pc_users );

	// -- It arrives with the handle ----------------------------------------------------------------

	/*
	 * It used to be made by the first Event, back when a Calendar was a container an author happened
	 * to need. Once its address became `@handle`, the Calendar could no longer be later than the
	 * handle: locking the handle is what reserves the address, so the Calendar exists from that
	 * moment and an Actor is never briefly a person you cannot subscribe to.
	 */
	ax_pc_assert( $ax_pc_results, 'locking a handle is what gives an Actor its Calendar', is_array( axismundi_cal_primary_calendar( $ax_pc_author['actor_uri'] ) ) );

	// -- And the first Event lands on it -----------------------------------------------------------

	wp_set_current_user( $ax_pc_author['user_id'] );
	$ax_pc_first = ax_pc_event( $ax_pc_posts, array( 'timezone' => 'Asia/Seoul', 'starts_at' => '2026-09-10 19:00:00', 'ends_at' => '2026-09-10 21:00:00' ) );
	ax_pc_assert( $ax_pc_results, 'an Event saved without naming a Calendar succeeds', ! is_wp_error( $ax_pc_first ) );

	$ax_pc_primary = axismundi_cal_primary_calendar( $ax_pc_author['actor_uri'] );
	ax_pc_assert( $ax_pc_results, 'and it goes to the Calendar the author already has', is_array( $ax_pc_primary ) );
	if ( is_array( $ax_pc_primary ) ) {
		$ax_pc_calendars[] = (int) $ax_pc_primary['id'];
		ax_pc_assert( $ax_pc_results, 'which belongs to them', $ax_pc_author['actor_uri'] === axismundi_cal_calendar_authority( (int) $ax_pc_primary['id'] ) );
		ax_pc_assert( $ax_pc_results, 'and gives them an owner rule, so they can administer it', 'owner' === axismundi_cal_effective_role( (int) $ax_pc_primary['id'], $ax_pc_author['actor_uri'] ) );
		ax_pc_assert(
			$ax_pc_results,
			'and the Event is on it rather than on a Calendar belonging to nobody',
			(int) $ax_pc_primary['id'] === (int) axismundi_cal_schedule_for_event( (int) $ax_pc_first )['calendar_id']
		);

		/*
		 * The whole point of giving it an authority: an Event here is publishable, which an Event on
		 * the old unfiled Calendar could never be.
		 */
		axismundi_cal_acl_grant( (int) $ax_pc_primary['id'], '', 'reader', 'public' );
		ax_pc_assert( $ax_pc_results, 'and once shared it can actually federate', true === axismundi_cal_event_visible( get_post( (int) $ax_pc_first ) ) );
	}

	// -- And only one ----------------------------------------------------------------------------------

	$ax_pc_second = ax_pc_event( $ax_pc_posts, array( 'timezone' => 'Asia/Seoul', 'starts_at' => '2026-09-11 19:00:00', 'ends_at' => '2026-09-11 21:00:00' ) );
	// Counted from the table rather than through the inventory helper, which is a capability this
	// author does not have and would answer zero for reasons that have nothing to do with the claim.
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fixture verification.
	$ax_pc_primaries = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . axismundi_cal_calendars_table() . ' WHERE is_primary = 1 AND authority_actor_uri_hash = %s', hash( 'sha256', $ax_pc_author['actor_uri'] ) ) );
	ax_pc_assert( $ax_pc_results, 'a second Event does not make a second Calendar', ! is_wp_error( $ax_pc_second ) && 1 === $ax_pc_primaries );
	ax_pc_assert(
		$ax_pc_results,
		'and lands on the same one',
		! is_wp_error( $ax_pc_second ) && (int) $ax_pc_primary['id'] === (int) axismundi_cal_schedule_for_event( (int) $ax_pc_second )['calendar_id']
	);

	/*
	 * Naming a Calendar still wins. The fallback is for when nobody said, not a rule that overrides
	 * what an author chose.
	 */
	$ax_pc_named = (int) axismundi_cal_calendar_save(
		array( 'name' => 'Named fixture', 'slug' => 'ax-pc-named', 'timezone' => 'Asia/Seoul', 'owner_actor_uri' => $ax_pc_author['actor_uri'] )
	);
	$ax_pc_calendars[] = $ax_pc_named;
	$ax_pc_explicit = ax_pc_event( $ax_pc_posts, array( 'calendar_id' => $ax_pc_named, 'timezone' => 'Asia/Seoul', 'starts_at' => '2026-09-12 19:00:00', 'ends_at' => '2026-09-12 21:00:00' ) );
	ax_pc_assert( $ax_pc_results, 'an Event that names a Calendar goes there', ! is_wp_error( $ax_pc_explicit ) && $ax_pc_named === (int) axismundi_cal_schedule_for_event( (int) $ax_pc_explicit )['calendar_id'] );
	ax_pc_assert( $ax_pc_results, 'and naming one does not make it the primary', 0 === (int) axismundi_cal_calendar_get( $ax_pc_named )['is_primary'] );

	// -- Nobody in particular is refused ----------------------------------------------------------------

	/*
	 * The case the unfiled Calendar existed to paper over. Refusing is the honest answer: the Event
	 * has no author to attribute it to, and every alternative invents one.
	 */
	wp_set_current_user( 0 );
	$ax_pc_orphan = ax_pc_event( $ax_pc_posts, array( 'timezone' => 'Asia/Seoul', 'starts_at' => '2026-09-13 19:00:00', 'ends_at' => '2026-09-13 21:00:00' ) );
	ax_pc_assert( $ax_pc_results, 'an Event written by nobody is refused rather than filed somewhere anonymous', is_wp_error( $ax_pc_orphan ) && 'ax_cal_no_actor' === $ax_pc_orphan->get_error_code() );
	ax_pc_assert( $ax_pc_results, 'and no Calendar was created on the way to refusing', null === axismundi_cal_primary_calendar( '' ) );

	// -- The Calendars an upgrade could not attribute ------------------------------------------------------

	/*
	 * `unfiled-events` is the one this site actually has. It is migration-only and deliberately left
	 * without an authority, which is why it is surfaced to an administrator rather than repaired
	 * automatically: whose those Events become is a decision, not a migration.
	 */
	$ax_pc_unfiled = axismundi_cal_calendar_by_slug( 'unfiled-events' );
	if ( is_array( $ax_pc_unfiled ) ) {
		$ax_pc_orphans = array_column( axismundi_cal_orphan_calendars(), 'id' );
		ax_pc_assert( $ax_pc_results, 'a Calendar with no Actor is reported as one', in_array( (int) $ax_pc_unfiled['id'], array_map( 'intval', $ax_pc_orphans ), true ) );
		ax_pc_assert( $ax_pc_results, 'and Calendars that have one are not', ! in_array( $ax_pc_named, array_map( 'intval', $ax_pc_orphans ), true ) );

		/*
		 * Assigning an authority to a Calendar that has none is not a transfer -- there is nothing to
		 * move -- so it is allowed exactly once, and the lock still refuses the second attempt.
		 */
		$ax_pc_probe = (int) axismundi_cal_calendar_save( array( 'name' => 'Orphan probe', 'slug' => 'ax-pc-orphan', 'timezone' => 'Asia/Seoul', 'owner_actor_uri' => $ax_pc_author['actor_uri'] ) );
		$ax_pc_calendars[] = $ax_pc_probe;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fixture recreates the pre-v13 state.
		$wpdb->update( axismundi_cal_calendars_table(), array( 'authority_actor_uri' => '', 'authority_actor_uri_hash' => '' ), array( 'id' => $ax_pc_probe ) );
		ax_pc_assert( $ax_pc_results, 'an Actor can be assigned to a Calendar that has none', ! is_wp_error( axismundi_cal_record_owner( $ax_pc_probe, $ax_pc_author['actor_uri'], 'local' ) ) );
		ax_pc_assert( $ax_pc_results, 'and it takes', $ax_pc_author['actor_uri'] === axismundi_cal_calendar_authority( $ax_pc_probe ) );
		$ax_pc_again = axismundi_cal_record_owner( $ax_pc_probe, 'https://elsewhere.example/actors/other', 'local' );
		ax_pc_assert( $ax_pc_results, 'while assigning a different one afterwards is still a transfer, and still refused', is_wp_error( $ax_pc_again ) && 'ax_cal_authority_locked' === $ax_pc_again->get_error_code() );
	}

	// -- A default Calendar cannot be deleted out from under its Actor ---------------------------------

	/*
	 * Deleting somebody's default would leave their next Event with nowhere to be filed, and the
	 * writer would immediately make another -- so a delete that appears to work and quietly undoes
	 * itself is the outcome being refused here.
	 */
	$ax_pc_default = axismundi_cal_primary_calendar( $ax_pc_author['actor_uri'] );
	if ( is_array( $ax_pc_default ) ) {
		$ax_pc_default_id = (int) $ax_pc_default['id'];
		ax_pc_assert( $ax_pc_results, 'a default Calendar refuses to be deleted', false === axismundi_cal_calendar_delete( $ax_pc_default_id ) );
		ax_pc_assert( $ax_pc_results, 'and is still there afterwards', is_array( axismundi_cal_calendar_get( $ax_pc_default_id ) ) );
		ax_pc_assert(
			$ax_pc_results,
			'with the Events on it untouched, so a refused delete is not a partial one',
			2 === count( axismundi_cal_calendar_event_ids( $ax_pc_default_id ) )
		);

		/*
		 * Demoting is how a caller says it means it. A flag on the delete would be the same decision
		 * made in passing, and passed `true` by the next caller that had not thought about it.
		 */
		ax_pc_assert( $ax_pc_results, 'an ordinary Calendar can be made the default instead', true === axismundi_cal_set_primary( $ax_pc_named, true ) );
		ax_pc_assert( $ax_pc_results, 'which moves the default rather than creating a second one', 0 === (int) axismundi_cal_calendar_get( $ax_pc_default_id )['is_primary'] );
		ax_pc_assert( $ax_pc_results, 'and the Actor now has exactly that one', $ax_pc_named === (int) axismundi_cal_primary_calendar( $ax_pc_author['actor_uri'] )['id'] );
		ax_pc_assert( $ax_pc_results, 'so the old one can be deleted once it is nobody&rsquo;s default', true === axismundi_cal_calendar_delete( $ax_pc_default_id ) );
		ax_pc_assert( $ax_pc_results, 'and the Events on it went with it, which is what the screen warns about', array() === axismundi_cal_calendar_event_ids( $ax_pc_default_id ) );

		// Put the fixture back the way the cleanup expects to find it.
		axismundi_cal_set_primary( $ax_pc_named, false );
	}

	// -- A subscribed Calendar is not deletable this way either ------------------------------------------

	/*
	 * A slug unique to this run. A fixed one collides with a row a crashed run left behind, and
	 * `calendar_save()` then returns a WP_Error whose int cast is 1 -- so every assertion below would
	 * quietly be made about calendar #1 and pass for reasons that have nothing to do with the claim.
	 */
	$ax_pc_remote_saved = axismundi_cal_calendar_save(
		array( 'name' => 'Primary remote', 'slug' => 'ax-pc-remote-' . strtolower( wp_generate_password( 6, false, false ) ), 'timezone' => 'Asia/Seoul', 'kind' => 'remote' )
	);
	ax_pc_assert( $ax_pc_results, 'the subscribed fixture really was created, so what follows is about it', is_int( $ax_pc_remote_saved ) );
	$ax_pc_remote = (int) $ax_pc_remote_saved;
	$ax_pc_calendars[] = $ax_pc_remote;
	ax_pc_assert( $ax_pc_results, 'a subscribed Calendar is not deleted as though it were ours', false === axismundi_cal_calendar_delete( $ax_pc_remote ) );
	/*
	 * Refused on being subscribed, not merely on having no authority. Written straight into the row
	 * because the writer refuses to give a remote Calendar a local authority -- which is the state a
	 * legacy install could still be carrying, and the one the `kind` check exists for.
	 */
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fixture recreating a legacy row.
	$wpdb->update(
		axismundi_cal_calendars_table(),
		array( 'authority_actor_uri' => $ax_pc_author['actor_uri'], 'authority_actor_uri_hash' => hash( 'sha256', $ax_pc_author['actor_uri'] ) ),
		array( 'id' => $ax_pc_remote )
	);
	ax_pc_assert( $ax_pc_results, 'nor made anybody&rsquo;s default, even carrying an authority it should never have had', false === axismundi_cal_set_primary( $ax_pc_remote, true ) );
	/*
	 * Put the invalid state back immediately. `verify_authority_migration()` reports a subscribed
	 * Calendar claiming a local authority, correctly -- and a fixture that leaves one behind makes
	 * another suite fail for a reason that has nothing to do with what it is testing.
	 */
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- undoing the fixture above.
	$wpdb->update( axismundi_cal_calendars_table(), array( 'authority_actor_uri' => '', 'authority_actor_uri_hash' => '' ), array( 'id' => $ax_pc_remote ) );

	/*
	 * And a local Calendar an upgrade left without an Actor cannot be one either: a default belongs to
	 * somebody, and there is nobody here for it to be the default of.
	 */
	$ax_pc_orphaned = axismundi_cal_orphan_calendars();
	if ( array() !== $ax_pc_orphaned ) {
		ax_pc_assert(
			$ax_pc_results,
			'a Calendar with no Actor cannot be made a default, because a default belongs to somebody',
			false === axismundi_cal_set_primary( (int) $ax_pc_orphaned[0]['id'], true )
		);
	}

	// -- Who sees what ---------------------------------------------------------------------------------------

	/*
	 * The inventory is a capability, not a relation. An author must not find every Calendar on the
	 * site in the screen that is supposed to show them theirs.
	 */
	wp_set_current_user( $ax_pc_author['user_id'] );
	ax_pc_assert( $ax_pc_results, 'an author does not get the site-wide inventory', false === axismundi_cal_can_manage_all_calendars() && array() === axismundi_cal_all_calendar_rows() );

	$ax_pc_admin = get_users( array( 'role' => 'administrator', 'number' => 1, 'fields' => 'ID' ) );
	if ( ! empty( $ax_pc_admin ) ) {
		wp_set_current_user( (int) $ax_pc_admin[0] );
		ax_pc_assert( $ax_pc_results, 'an administrator does', true === axismundi_cal_can_manage_all_calendars() && count( axismundi_cal_all_calendar_rows() ) >= 2 );
		ax_pc_assert(
			$ax_pc_results,
			'and it reaches a Calendar nobody is looking after, which is the whole reason it exists',
			is_array( $ax_pc_unfiled ) && in_array( (int) $ax_pc_unfiled['id'], array_map( 'intval', array_column( axismundi_cal_all_calendar_rows(), 'id' ) ), true )
		);
		ax_pc_assert(
			$ax_pc_results,
			'without that inventory becoming their own calendar list',
			! in_array( (int) $ax_pc_unfiled['id'], axismundi_cal_actor_calendar_ids( axismundi_cal_current_actor_uri() ), true )
		);
	}
} finally {
	wp_set_current_user( 0 );
	foreach ( $ax_pc_posts as $ax_pc_post ) {
		wp_delete_post( (int) $ax_pc_post, true );
	}
	foreach ( array_unique( $ax_pc_calendars ) as $ax_pc_calendar ) {
		// Demoted first: a primary Calendar refuses to be deleted, and a fixture that wants one
		// gone has to say so rather than leaving a row behind on every run.
		axismundi_cal_set_primary( (int) $ax_pc_calendar, false );
		if ( ! axismundi_cal_calendar_delete( (int) $ax_pc_calendar ) ) {
			// A subscribed Calendar is refused too, since deleting one belongs to unsubscribing. This
			// fixture has no source behind it, so the row is removed directly.
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fixture cleanup.
			$wpdb->delete( axismundi_cal_calendars_table(), array( 'id' => (int) $ax_pc_calendar ) );
			axismundi_cal_list_forget_calendar( (int) $ax_pc_calendar );
			axismundi_cal_acl_forget_calendar( (int) $ax_pc_calendar );
		}
	}
	foreach ( $ax_pc_users as $ax_pc_user_id ) {
		wp_delete_user( (int) $ax_pc_user_id );
	}
}

$ax_pc_failures = count( array_filter( $ax_pc_results, static fn( bool $result ) : bool => ! $result ) );
// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI fixture output.
printf( "\n== %d checks, %d failed ==\n", count( $ax_pc_results ), $ax_pc_failures );
if ( class_exists( 'WP_CLI' ) ) {
	WP_CLI::halt( $ax_pc_failures > 0 ? 1 : 0 );
}
exit( $ax_pc_failures > 0 ? 1 : 0 );
