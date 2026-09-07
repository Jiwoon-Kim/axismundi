<?php
/**
 * An Actor's own Calendar, and the promises made about its address (dev-only; dist-excluded).
 *
 * The address is the thing under test. A calendar people subscribe to cannot acquire its identity by
 * accident -- not from whoever wrote the first Event, not from a collision suffix, and not from a
 * later rename -- so what is checked here is that the address exists from the moment the handle does,
 * that nothing else can occupy it, and that it survives every act that used to be able to move it.
 *
 * @package AxismundiCalendar
 */

defined( 'ABSPATH' ) || exit( 1 );

$ax_pc_results = array();
$ax_pc_users   = array();

/** @param bool[] $results Results. */
function ax_pc_assert( array &$results, string $label, bool $condition ) : void {
	$results[] = $condition;
	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI fixture output.
	printf( "[%s] %s\n", $condition ? 'PASS' : 'FAIL', $label );
}

/** An account whose Actor activates with a known handle. */
function ax_pc_actor( array &$users, string $handle ) : ?Axismundi_Actor {
	$id = (int) wp_insert_user(
		array( 'user_login' => 'axpc' . strtolower( wp_generate_password( 8, false, false ) ), 'user_pass' => wp_generate_password(), 'role' => 'author' )
	);
	$users[] = $id;
	$actor   = axismundi_actors_ensure_for_user( $id );
	if ( ! $actor instanceof Axismundi_Actor ) {
		return null;
	}
	axismundi_actors_register_handle( $actor->get_identity_id(), $handle );
	return axismundi_actors_get_by_identity( $actor->get_identity_id() );
}

try {
	$ax_pc_handle = 'axpc' . strtolower( wp_generate_password( 8, false, false ) );
	$ax_pc_actor  = ax_pc_actor( $ax_pc_users, $ax_pc_handle );
	$ax_pc_uri    = (string) $ax_pc_actor->get_uri();

	// -- it exists because the Actor does ---------------------------------------------------------------

	/*
	 * Before this, the calendar appeared when somebody first wrote an Event -- which meant a public
	 * subscription address was minted by a request that had nothing to do with addresses.
	 */
	$ax_pc_calendar = axismundi_cal_primary_calendar( $ax_pc_uri );
	ax_pc_assert(
		$ax_pc_results,
		'locking a handle gives the Actor its calendar, without waiting for anybody to write an Event',
		is_array( $ax_pc_calendar ) && 1 === (int) $ax_pc_calendar['is_primary']
	);
	ax_pc_assert(
		$ax_pc_results,
		'and it answers at the address the handle reserves',
		is_array( $ax_pc_calendar ) && '@' . $ax_pc_handle === (string) $ax_pc_calendar['slug']
	);

	// -- nothing else may take that address -------------------------------------------------------------

	/*
	 * The reason the prefix is there. `sanitize_title()` eats `@`, so no form can reach the namespace,
	 * and a caller that constructs one is refused -- which is what makes "permanent" a promise rather
	 * than a hope that nobody asks for it first.
	 */
	$ax_pc_other = ax_pc_actor( $ax_pc_users, 'axpc' . strtolower( wp_generate_password( 8, false, false ) ) );
	$ax_pc_theft = axismundi_cal_calendar_save(
		array( 'name' => 'Not mine', 'slug' => '@' . $ax_pc_handle . 'x', 'timezone' => 'Asia/Seoul', 'owner_actor_uri' => (string) $ax_pc_other->get_uri() )
	);
	ax_pc_assert(
		$ax_pc_results,
		'an Actor cannot open a calendar in another handle\'s namespace',
		is_wp_error( $ax_pc_theft ) && 'ax_cal_slug_reserved' === $ax_pc_theft->get_error_code()
	);
	ax_pc_assert(
		$ax_pc_results,
		'and a typed slug can never reach that namespace at all',
		'' === axismundi_cal_calendar_slug_handle( axismundi_cal_sanitize_calendar_slug( '@' . $ax_pc_handle ) )
			|| '' === axismundi_cal_calendar_slug_handle( sanitize_title( '@' . $ax_pc_handle ) )
	);

	// -- the address outlives the acts that used to move it ---------------------------------------------

	$ax_pc_id     = (int) $ax_pc_calendar['id'];
	$ax_pc_rename = axismundi_cal_calendar_save( array( 'slug' => 'somewhere-else' ), $ax_pc_id );
	ax_pc_assert(
		$ax_pc_results,
		'the address cannot be edited away, because somebody already subscribed to it',
		is_wp_error( $ax_pc_rename ) && 'ax_cal_slug_locked' === $ax_pc_rename->get_error_code()
	);
	/*
	 * Demotion was the escape hatch that made deletion possible. An ordinary calendar keeps it; this
	 * one does not, or the reserved address would go on answering for a calendar the Actor's Events no
	 * longer go to.
	 */
	ax_pc_assert(
		$ax_pc_results,
		'it cannot be demoted, and therefore cannot be deleted',
		false === axismundi_cal_set_primary( $ax_pc_id, false )
			&& false === axismundi_cal_calendar_delete( $ax_pc_id )
			&& is_array( axismundi_cal_primary_calendar( $ax_pc_uri ) )
	);

	// -- what it is called is a projection, not a copy ---------------------------------------------------

	$ax_pc_user_id = (int) $ax_pc_actor->get_local_user_id();
	wp_update_user( array( 'ID' => $ax_pc_user_id, 'display_name' => 'Kim Jiwoon' ) );
	$ax_pc_named = axismundi_cal_calendar_display_name( (array) axismundi_cal_calendar_get( $ax_pc_id ) );
	wp_update_user( array( 'ID' => $ax_pc_user_id, 'display_name' => 'Somebody Else' ) );
	$ax_pc_renamed = axismundi_cal_calendar_display_name( (array) axismundi_cal_calendar_get( $ax_pc_id ) );
	ax_pc_assert(
		$ax_pc_results,
		'the calendar is called what the Actor is called, and a rename carries rather than leaving a stale copy',
		'Kim Jiwoon' === $ax_pc_named && 'Somebody Else' === $ax_pc_renamed
	);
	ax_pc_assert(
		$ax_pc_results,
		'nothing is stored for that name, so there is no second copy to disagree',
		null === ( axismundi_cal_calendar_get( $ax_pc_id )['name'] ?? null )
	);
	// Somebody who types a name means it, and the projection stops overruling them.
	axismundi_cal_calendar_save( array( 'name' => 'Work' ), $ax_pc_id );
	ax_pc_assert(
		$ax_pc_results,
		'a name typed on purpose wins over the projection',
		'Work' === axismundi_cal_calendar_display_name( (array) axismundi_cal_calendar_get( $ax_pc_id ) )
	);

	// -- an Actor with no handle has no calendar ---------------------------------------------------------

	/*
	 * The handle is the address. Creating the calendar first would mean choosing an address that has
	 * to change later, which is the one thing this contract exists to prevent.
	 */
	$ax_pc_bare_id = (int) wp_insert_user(
		array( 'user_login' => 'axpc' . strtolower( wp_generate_password( 8, false, false ) ), 'user_pass' => wp_generate_password(), 'role' => 'author' )
	);
	$ax_pc_users[] = $ax_pc_bare_id;
	$ax_pc_bare    = axismundi_actors_ensure_for_user( $ax_pc_bare_id );
	ax_pc_assert(
		$ax_pc_results,
		'an Actor with no registered handle is refused a calendar rather than given a temporary address',
		$ax_pc_bare instanceof Axismundi_Actor
			&& null === axismundi_cal_primary_calendar( (string) $ax_pc_bare->get_uri() )
			&& is_wp_error( axismundi_cal_create_actor_calendar( $ax_pc_bare ) )
	);

	// -- a managed Actor is not a special case ------------------------------------------------------------

	$ax_pc_org_handle = 'axpc' . strtolower( wp_generate_password( 8, false, false ) );
	$ax_pc_org        = axismundi_actors_create_managed_actor(
		array( 'owner_user_id' => $ax_pc_user_id, 'preferred_username' => $ax_pc_org_handle, 'actor_type' => 'Organization', 'status' => 'public' )
	);
	$ax_pc_org_cal = $ax_pc_org instanceof Axismundi_Actor ? axismundi_cal_primary_calendar( (string) $ax_pc_org->get_uri() ) : null;
	ax_pc_assert(
		$ax_pc_results,
		'an Organization gets its calendar on the same terms as a person',
		is_array( $ax_pc_org_cal ) && '@' . $ax_pc_org_handle === (string) $ax_pc_org_cal['slug']
	);
} finally {
	wp_set_current_user( 0 );
	foreach ( $ax_pc_users as $ax_pc_user_id_cleanup ) {
		wp_delete_user( (int) $ax_pc_user_id_cleanup );
	}
}

$ax_pc_failures = count( array_filter( $ax_pc_results, static fn( bool $result ) : bool => ! $result ) );
// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI fixture output.
printf( "\n== %d checks, %d failed ==\n", count( $ax_pc_results ), $ax_pc_failures );
if ( class_exists( 'WP_CLI' ) ) {
	WP_CLI::halt( $ax_pc_failures > 0 ? 1 : 0 );
}
exit( $ax_pc_failures > 0 ? 1 : 0 );
