<?php
/**
 * Calendar management permissions and ownership (dev-only; dist-excluded).
 *
 * Ownership is recorded before anything enforces it, so the assertions here are about the record
 * being right rather than about a door being shut. That is the point: `owner_actor_uri` cannot be
 * reconstructed later, and a calendar created today without one is a calendar whose owner is a guess
 * on the day private calendars ship.
 *
 * The permission rule is asserted in both directions, because a rule that only ever returns true is
 * indistinguishable from a working one until somebody who should be refused is not.
 *
 * @package AxismundiCalendar
 */

defined( 'ABSPATH' ) || exit( 1 );

global $wpdb;
$ax_ca_results   = array();
$ax_ca_users     = array();
$ax_ca_calendars = array();

/** @param bool[] $results Results. */
function ax_ca_assert( array &$results, string $label, bool $condition ) : void {
	$results[] = $condition;
	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI fixture output.
	printf( "[%s] %s\n", $condition ? 'PASS' : 'FAIL', $label );
}

/** A user with a public Actor, so ownership has something real to record. */
function ax_ca_user( array &$users, string $role ) : int {
	$login   = 'ax_ca_' . strtolower( wp_generate_password( 8, false, false ) );
	$id      = (int) wp_insert_user( array( 'user_login' => $login, 'user_pass' => wp_generate_password(), 'role' => $role ) );
	$users[] = $id;
	if ( function_exists( 'axismundi_actors_ensure_for_user' ) ) {
		$actor = axismundi_actors_ensure_for_user( $id );
		if ( $actor instanceof Axismundi_Actor ) {
			axismundi_actors_register_handle( $actor->get_identity_id(), $login );
			axismundi_actors_set_status( $actor->get_identity_id(), 'public' );
		}
	}
	return $id;
}

try {
	$ax_ca_author   = ax_ca_user( $ax_ca_users, 'author' );
	$ax_ca_other    = ax_ca_user( $ax_ca_users, 'author' );
	$ax_ca_editor   = ax_ca_user( $ax_ca_users, 'editor' );
	$ax_ca_reader   = ax_ca_user( $ax_ca_users, 'subscriber' );

	// -- Who may reach the screen ---------------------------------------------------------------

	wp_set_current_user( $ax_ca_reader );
	ax_ca_assert( $ax_ca_results, 'a subscriber cannot manage calendars, since a calendar is a public surface', false === axismundi_cal_can_manage_calendars() );

	wp_set_current_user( $ax_ca_author );
	ax_ca_assert( $ax_ca_results, 'someone who can publish can', true === axismundi_cal_can_manage_calendars() );

	// -- Ownership is recorded at creation --------------------------------------------------------

	$ax_ca_uri = axismundi_cal_current_actor_uri();
	ax_ca_assert( $ax_ca_results, 'the current user resolves to an Actor URI when this site has Actors', '' !== $ax_ca_uri );

	$ax_ca_mine = axismundi_cal_calendar_save(
		array( 'name' => 'Mine', 'slug' => 'ax-ca-mine', 'timezone' => 'Asia/Seoul', 'owner_actor_uri' => $ax_ca_uri )
	);
	ax_ca_assert( $ax_ca_results, 'a calendar is created with an owner', is_int( $ax_ca_mine ) && $ax_ca_mine > 0 );
	$ax_ca_calendars[] = (int) $ax_ca_mine;
	$ax_ca_row = axismundi_cal_calendar_get( (int) $ax_ca_mine );
	axismundi_cal_acl_grant( (int) $ax_ca_mine, '', 'reader', 'public' );
	// Read as a relation, not a column: ownership is one of several things an Actor can be to a
	// Calendar, and asking the Calendar who owns it could only ever return the first of them.
	ax_ca_assert( $ax_ca_results, 'and the owner is stored rather than left to be worked out later', $ax_ca_uri === axismundi_cal_calendar_owner( (int) $ax_ca_mine ) );

	// -- The permission rule, in both directions ---------------------------------------------------

	ax_ca_assert( $ax_ca_results, 'the owner may manage their own calendar', true === axismundi_cal_can_manage_calendar( $ax_ca_row ) );

	wp_set_current_user( $ax_ca_other );
	ax_ca_assert(
		$ax_ca_results,
		'another author who can publish may not manage it, which is the whole reason ownership is recorded',
		false === axismundi_cal_can_manage_calendar( $ax_ca_row )
	);

	wp_set_current_user( $ax_ca_editor );
	ax_ca_assert( $ax_ca_results, 'an editor may, because moderating other people\'s content is a capability they have', true === axismundi_cal_can_manage_calendar( $ax_ca_row ) );

	wp_set_current_user( $ax_ca_reader );
	ax_ca_assert( $ax_ca_results, 'and a subscriber may not, whoever owns it', false === axismundi_cal_can_manage_calendar( $ax_ca_row ) );

	// -- A new local Calendar must name its authority -----------------------------------------------

	$ax_ca_orphan = axismundi_cal_calendar_save( array( 'name' => 'Orphan', 'slug' => 'ax-ca-orphan', 'timezone' => 'Asia/Seoul', 'owner_actor_uri' => '' ) );
	ax_ca_assert( $ax_ca_results, 'a local calendar without an authority is refused at the writer', is_wp_error( $ax_ca_orphan ) && 'ax_cal_authority' === $ax_ca_orphan->get_error_code() );

	// -- A new calendar is manageable by anyone allowed to make one -----------------------------------

	wp_set_current_user( $ax_ca_author );
	ax_ca_assert( $ax_ca_results, 'a calendar that does not exist yet may be created by anyone allowed on the screen', true === axismundi_cal_can_manage_calendar( null ) );
	wp_set_current_user( $ax_ca_reader );
	ax_ca_assert( $ax_ca_results, 'and not by anyone who is not', false === axismundi_cal_can_manage_calendar( null ) );

	wp_set_current_user( $ax_ca_author );
	ob_start();
	axismundi_cal_render_calendars_page();
	$ax_ca_new_html = (string) ob_get_clean();
	ax_ca_assert( $ax_ca_results, 'a new Calendar chooses an Actor by name and handle rather than accepting a raw URI', str_contains( $ax_ca_new_html, 'name="owner_actor_uri"' ) && str_contains( axismundi_cal_admin_actor_label( $ax_ca_uri ), '(@' ) && str_contains( $ax_ca_new_html, axismundi_cal_admin_actor_label( $ax_ca_uri ) ) );

	// -- The screen is registered where it can be found -------------------------------------------------

	wp_set_current_user( $ax_ca_editor );
	set_current_screen( 'dashboard' );
	$GLOBALS['submenu'] = array();
	$GLOBALS['menu']    = array();
	do_action( 'admin_menu' );
	$ax_ca_parent = 'edit.php?post_type=' . AXISMUNDI_CAL_EVENT_POST_TYPE;
	$ax_ca_items  = (array) ( $GLOBALS['submenu'][ $ax_ca_parent ] ?? array() );
	$ax_ca_slugs  = array_map( static fn( array $item ) : string => (string) ( $item[2] ?? '' ), $ax_ca_items );
	ax_ca_assert( $ax_ca_results, 'the Calendars screen appears under Events, where someone looking for it would look', in_array( 'ax-calendars', $ax_ca_slugs, true ) );

	// -- The screen actually renders -----------------------------------------------------------------

	ob_start();
	axismundi_cal_render_calendars_page();
	$ax_ca_html = (string) ob_get_clean();
	ax_ca_assert( $ax_ca_results, 'it renders a form that posts to admin-post', str_contains( $ax_ca_html, 'name="action" value="ax_cal_save_calendar"' ) );
	ax_ca_assert( $ax_ca_results, 'with a nonce, since it changes things', str_contains( $ax_ca_html, '_wpnonce' ) );
	ax_ca_assert( $ax_ca_results, 'and lists the calendars that exist', str_contains( $ax_ca_html, 'Mine' ) && str_contains( $ax_ca_html, 'ax-ca-mine' ) );
	ax_ca_assert(
		$ax_ca_results,
		'showing each calendar\'s subscription address, which is the thing people came for',
		str_contains( $ax_ca_html, '/calendar/ax-ca-mine.ics' )
	);
	ax_ca_assert( $ax_ca_results, 'it offers a public ICS URL subscription flow alongside local Calendar creation', str_contains( $ax_ca_html, 'name="action" value="ax_cal_subscribe_calendar"' ) && str_contains( $ax_ca_html, 'name="source_url"' ) );
	ax_ca_assert( $ax_ca_results, 'each Calendar has a human View link as well as its file address', str_contains( $ax_ca_html, '/calendar/ax-ca-mine/' ) && axismundi_cal_calendar_url( $ax_ca_row ) === home_url( '/calendar/ax-ca-mine/' ) );

	$_GET['ax_cal_edit'] = (int) $ax_ca_mine;
	ob_start();
	axismundi_cal_render_calendars_page();
	$ax_ca_integration_html = (string) ob_get_clean();
	unset( $_GET['ax_cal_edit'] );
	ax_ca_assert( $ax_ca_results, 'the saved Calendar shows its stable ID and API address', str_contains( $ax_ca_integration_html, (string) $ax_ca_row['uuid'] ) && str_contains( $ax_ca_integration_html, '/wp-json/axismundi/v1/calendars/' . $ax_ca_row['uuid'] ) );
	ax_ca_assert( $ax_ca_results, 'and, when public, its human and iCalendar subscription addresses', str_contains( $ax_ca_integration_html, '/calendar/ax-ca-mine/' ) && str_contains( $ax_ca_integration_html, '/calendar/ax-ca-mine.ics' ) );
	axismundi_cal_register_ics_routes();
	flush_rewrite_rules( false );
	$ax_ca_rules = (array) get_option( 'rewrite_rules', array() );
	ax_ca_assert( $ax_ca_results, 'the View link is backed by a calendar page rewrite rule, rather than a URL that only looks real', in_array( 'index.php?ax_cal_page=1&ax_cal_slug=$matches[1]', $ax_ca_rules, true ) );

	/*
	 * The owner field is editable only by a moderator. An author editing their own calendar must not
	 * be able to hand it to somebody else -- or to themselves, which is the same door from the other
	 * side and the one that would let anyone take over an unowned calendar.
	 */
	wp_set_current_user( $ax_ca_author );
	$_GET['ax_cal_edit'] = (int) $ax_ca_mine;
	ob_start();
	axismundi_cal_render_calendars_page();
	$ax_ca_owner_html = (string) ob_get_clean();
	unset( $_GET['ax_cal_edit'] );
	ax_ca_assert(
		$ax_ca_results,
		'an owner sees their ownership but cannot rewrite it',
		str_contains( $ax_ca_owner_html, $ax_ca_uri ) && ! str_contains( $ax_ca_owner_html, 'name="owner_actor_uri"' )
	);
} finally {
	wp_set_current_user( 0 );
	foreach ( array_unique( $ax_ca_calendars ) as $ax_ca_calendar_id ) {
		axismundi_cal_calendar_delete( (int) $ax_ca_calendar_id );
	}
	foreach ( array_unique( $ax_ca_users ) as $ax_ca_user_id ) {
		wp_delete_user( (int) $ax_ca_user_id );
	}
}

$ax_ca_failures = count( array_filter( $ax_ca_results, static fn( bool $result ) : bool => ! $result ) );
// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI fixture output.
printf( "\n== %d checks, %d failed ==\n", count( $ax_ca_results ), $ax_ca_failures );
if ( class_exists( 'WP_CLI' ) ) {
	WP_CLI::halt( $ax_ca_failures > 0 ? 1 : 0 );
}
exit( $ax_ca_failures > 0 ? 1 : 0 );
