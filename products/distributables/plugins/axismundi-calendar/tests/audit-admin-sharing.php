<?php
/**
 * The sharing screen and the subscriptions screen (dev-only; dist-excluded).
 *
 * Both are administrative surfaces over rules that already exist, so what is asserted here is not
 * that the rules work -- `audit-calendar-acl` and `audit-subscription` do that -- but that the
 * screens cannot be used to reach past them.
 *
 * Two properties in particular:
 *
 *   sharing is `owner` alone, and a writer with a form is still a writer
 *   unsubscribing is personal, and the shared cache outlives one person leaving
 *
 * The second is the one an interface gets wrong by default. One instance-wide cache serving ten
 * subscribers is an optimisation, and it stops being one the moment the tenth person leaving takes
 * everyone else's calendar with them -- or the moment the first person leaving does.
 *
 * @package AxismundiCalendar
 */

defined( 'ABSPATH' ) || exit( 1 );

global $wpdb;
$ax_sh_results   = array();
$ax_sh_calendars = array();
$ax_sh_users     = array();
$ax_sh_sources   = array();

/** @param bool[] $results Results. */
function ax_sh_assert( array &$results, string $label, bool $condition ) : void {
	$results[] = $condition;
	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI fixture output.
	printf( "[%s] %s\n", $condition ? 'PASS' : 'FAIL', $label );
}

/** A user with a public Person Actor. */
function ax_sh_user( array &$users, string $role = 'author' ) : array {
	$login   = 'ax_sh_' . strtolower( wp_generate_password( 8, false, false ) );
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
	$ax_sh_owner  = ax_sh_user( $ax_sh_users );
	$ax_sh_writer = ax_sh_user( $ax_sh_users );
	$ax_sh_other  = ax_sh_user( $ax_sh_users );

	$ax_sh_cal = (int) axismundi_cal_calendar_save(
		array( 'name' => 'Sharing fixture', 'slug' => 'ax-sh-cal', 'timezone' => 'Asia/Seoul', 'owner_actor_uri' => $ax_sh_owner['actor_uri'] )
	);
	$ax_sh_calendars[] = $ax_sh_cal;
	$ax_sh_row = (array) axismundi_cal_calendar_get( $ax_sh_cal );
	axismundi_cal_acl_grant( $ax_sh_cal, $ax_sh_writer['actor_uri'], 'writer' );

	// -- Who may share ---------------------------------------------------------------------------

	wp_set_current_user( $ax_sh_owner['user_id'] );
	ax_sh_assert( $ax_sh_results, 'the owner may share the calendar', true === axismundi_cal_can_share_calendar( $ax_sh_row ) );

	/*
	 * The line the screen exists to hold. A writer can already add Events here, and the intuition
	 * that they can therefore invite people is exactly the one that must not be true.
	 */
	wp_set_current_user( $ax_sh_writer['user_id'] );
	ax_sh_assert( $ax_sh_results, 'a writer may not', false === axismundi_cal_can_share_calendar( $ax_sh_row ) );
	wp_set_current_user( $ax_sh_other['user_id'] );
	ax_sh_assert( $ax_sh_results, 'and neither may somebody with no relation', false === axismundi_cal_can_share_calendar( $ax_sh_row ) );
	wp_set_current_user( 0 );
	ax_sh_assert( $ax_sh_results, 'nor anybody signed out', false === axismundi_cal_can_share_calendar( $ax_sh_row ) );
	ax_sh_assert( $ax_sh_results, 'a partial calendar row is refused without emitting a notice', false === axismundi_cal_can_share_calendar( array( 'id' => $ax_sh_cal ) ) );

	// -- What the screen renders -------------------------------------------------------------------

	wp_set_current_user( $ax_sh_owner['user_id'] );
	ob_start();
	axismundi_cal_render_sharing( $ax_sh_row );
	$ax_sh_html = (string) ob_get_clean();
	ax_sh_assert( $ax_sh_results, 'the owner is shown the sharing form', str_contains( $ax_sh_html, 'ax_cal_share_calendar' ) );
	ax_sh_assert( $ax_sh_results, 'with the people who already have access', str_contains( $ax_sh_html, $ax_sh_writer['actor_uri'] ) );
	ax_sh_assert( $ax_sh_results, 'and a nonce, so a link cannot share somebody else&rsquo;s calendar', str_contains( $ax_sh_html, '_wpnonce' ) );
	/*
	 * Free/busy is a level of access in its own right, in both controls. Matched on the wording each
	 * one uses rather than on the role name, which appears in the markup of the other control too and
	 * would let either half go missing unnoticed.
	 */
	ax_sh_assert(
		$ax_sh_results,
		'the per-person menu offers free/busy as a level of its own, not folded into reading',
		str_contains( $ax_sh_html, 'See when it is busy, without titles' )
	);
	ax_sh_assert(
		$ax_sh_results,
		'and the public control offers all three states, so not-shared is a choice rather than a blank',
		str_contains( $ax_sh_html, 'Not shared publicly' )
			&& str_contains( $ax_sh_html, 'Anyone can see when it is busy' )
			&& str_contains( $ax_sh_html, 'Anyone can read every event' )
	);

	wp_set_current_user( $ax_sh_writer['user_id'] );
	ob_start();
	axismundi_cal_render_sharing( $ax_sh_row );
	ax_sh_assert( $ax_sh_results, 'a writer is shown nothing at all, not a disabled form', '' === trim( (string) ob_get_clean() ) );

	/*
	 * A subscribed Calendar is somebody else's to share. This site holds a cached copy and has
	 * nothing to grant anyone, so the section is absent rather than present and refusing.
	 */
	$ax_sh_remote = (array) array_merge( $ax_sh_row, array( 'kind' => 'remote' ) );
	wp_set_current_user( $ax_sh_owner['user_id'] );
	ax_sh_assert( $ax_sh_results, 'and a subscribed calendar cannot be shared by its subscriber', false === axismundi_cal_can_share_calendar( $ax_sh_remote ) );

	// -- The rules the form is a front for ----------------------------------------------------------

	/*
	 * Asserted through the store the handler calls, which is the point: the screen adds wording and
	 * a nonce, and adds no rules of its own that could disagree with the API.
	 */
	ax_sh_assert( $ax_sh_results, 'the world cannot be granted write access from here either', is_wp_error( axismundi_cal_acl_grant( $ax_sh_cal, '', 'writer', 'public' ) ) );
	ax_sh_assert( $ax_sh_results, 'the last owner is what the revoke guard counts', 1 === axismundi_cal_acl_owner_count( $ax_sh_cal ) );
	ax_sh_assert(
		$ax_sh_results,
		'and every role the form offers is a role the store accepts',
		array() === array_diff( array_keys( axismundi_cal_share_role_labels() ), array_keys( AXISMUNDI_CAL_ACL_ROLES ) )
	);

	// -- Subscriptions ------------------------------------------------------------------------------

	/*
	 * A source with no network: the row is written directly, because this environment cannot fetch
	 * anything and what is under test is the screen's arithmetic, not the fetch.
	 */
	$ax_sh_sub = (int) axismundi_cal_calendar_save(
		array( 'name' => 'Subscribed fixture', 'slug' => 'ax-sh-sub', 'timezone' => 'Asia/Seoul', 'kind' => 'remote' )
	);
	$ax_sh_calendars[] = $ax_sh_sub;
	$ax_sh_url = 'https://example.org/ax-sh-' . wp_generate_password( 8, false, false ) . '.ics';
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fixture row in this plugin's own table.
	$wpdb->insert(
		axismundi_cal_sources_table(),
		array(
			'calendar_id'     => $ax_sh_sub,
			'kind'            => 'ical',
			'authority'       => 'remote',
			'source_url'      => $ax_sh_url,
			'source_url_hash' => hash( 'sha256', $ax_sh_url ),
			'sync_status'     => 'error',
			'sync_error'      => 'Fixture failure',
			'last_checked_at' => gmdate( 'Y-m-d H:i:s', time() - HOUR_IN_SECONDS ),
			'created_at'      => current_time( 'mysql', true ),
			'updated_at'      => current_time( 'mysql', true ),
		)
	);
	$ax_sh_source_id = (int) $wpdb->insert_id;
	$ax_sh_sources[] = $ax_sh_source_id;

	axismundi_cal_list_set( $ax_sh_sub, $ax_sh_owner['actor_uri'] );
	axismundi_cal_list_set( $ax_sh_sub, $ax_sh_other['actor_uri'] );

	wp_set_current_user( $ax_sh_owner['user_id'] );
	$ax_sh_rows = axismundi_cal_admin_source_rows();
	ax_sh_assert( $ax_sh_results, 'a subscription appears for somebody who follows it', 1 === count( array_filter( $ax_sh_rows, static fn( array $r ) : bool => (int) $r['id'] === $ax_sh_source_id ) ) );
	ax_sh_assert( $ax_sh_results, 'reporting that it is failing rather than only that it exists', 'Failing' === axismundi_cal_source_status_label( array( 'sync_status' => 'error' ) ) );
	ax_sh_assert( $ax_sh_results, 'and how long ago it was last tried', '—' !== axismundi_cal_source_when( gmdate( 'Y-m-d H:i:s', time() - HOUR_IN_SECONDS ) ) );
	ax_sh_assert( $ax_sh_results, 'while a feed never fetched says so instead of claiming a time', '—' === axismundi_cal_source_when( null ) );
	ax_sh_assert( $ax_sh_results, 'and it counts everybody following it, not only the person looking', 2 === (int) array_values( array_filter( $ax_sh_rows, static fn( array $r ) : bool => (int) $r['id'] === $ax_sh_source_id ) )[0]['followers'] );

	$ax_sh_stranger = ax_sh_user( $ax_sh_users );
	wp_set_current_user( $ax_sh_stranger['user_id'] );
	ax_sh_assert( $ax_sh_results, 'somebody who does not follow it does not see it', array() === axismundi_cal_admin_source_rows() );
	ax_sh_assert( $ax_sh_results, 'and may not act on it', false === axismundi_cal_can_manage_source( (array) axismundi_cal_source_get( $ax_sh_source_id ) ) );
	ax_sh_assert( $ax_sh_results, 'nor conjure it by asking for every source', array() === axismundi_cal_admin_source_rows( true ) );

	$ax_sh_admin = get_users( array( 'role' => 'administrator', 'number' => 1, 'fields' => 'ID' ) );
	if ( ! empty( $ax_sh_admin ) ) {
		wp_set_current_user( (int) $ax_sh_admin[0] );
		ax_sh_assert(
			$ax_sh_results,
			'an administrator can find a failing feed nobody else can reach',
			1 === count( array_filter( axismundi_cal_admin_source_rows( true ), static fn( array $r ) : bool => (int) $r['id'] === $ax_sh_source_id ) )
		);
		ax_sh_assert( $ax_sh_results, 'which is not the same as following it', array() === axismundi_cal_admin_source_rows() );
	}

	// -- Unsubscribing is personal --------------------------------------------------------------------

	/*
	 * The property the shared cache depends on. One person leaving must not take the feed away from
	 * everyone else, and the last person leaving must not leave a cache nobody reads being refetched
	 * forever.
	 */
	$ax_sh_first_out = axismundi_cal_release_subscription( $ax_sh_source_id, $ax_sh_owner['actor_uri'] );
	ax_sh_assert( $ax_sh_results, 'one person leaving keeps the shared copy', 'unsubscribed' === $ax_sh_first_out && null !== axismundi_cal_source_get( $ax_sh_source_id ) );
	ax_sh_assert( $ax_sh_results, 'and leaves the other subscriber following it', 1 === count( axismundi_cal_calendar_list_entries( $ax_sh_sub ) ) );
	ax_sh_assert( $ax_sh_results, 'without granting the person who left any lingering access', false === axismundi_cal_can_read( $ax_sh_sub, $ax_sh_owner['actor_uri'] ) );

	$ax_sh_last_out = axismundi_cal_release_subscription( $ax_sh_source_id, $ax_sh_other['actor_uri'] );
	ax_sh_assert( $ax_sh_results, 'and the last one leaving is what drops the cache', 'source_removed' === $ax_sh_last_out && null === axismundi_cal_source_get( $ax_sh_source_id ) );

	// -- Subscribing is not ownership -------------------------------------------------------------------

	axismundi_cal_list_set( $ax_sh_sub, $ax_sh_owner['actor_uri'] );
	wp_set_current_user( $ax_sh_owner['user_id'] );
	ax_sh_assert( $ax_sh_results, 'following a remote calendar grants no authority over it', '' === axismundi_cal_calendar_authority( $ax_sh_sub ) );
	ax_sh_assert( $ax_sh_results, 'and no right to write to it', false === axismundi_cal_can_write( $ax_sh_sub, $ax_sh_owner['actor_uri'], $ax_sh_owner['user_id'] ) );
	ax_sh_assert( $ax_sh_results, 'and no right to share it', false === axismundi_cal_can_share_calendar( (array) axismundi_cal_calendar_get( $ax_sh_sub ) ) );
} finally {
	wp_set_current_user( 0 );
	foreach ( $ax_sh_sources as $ax_sh_source ) {
		axismundi_cal_remove_source( (int) $ax_sh_source );
	}
	foreach ( array_unique( $ax_sh_calendars ) as $ax_sh_calendar ) {
		axismundi_cal_calendar_delete( (int) $ax_sh_calendar );
	}
	foreach ( $ax_sh_users as $ax_sh_user_id ) {
		wp_delete_user( (int) $ax_sh_user_id );
	}
}

$ax_sh_failures = count( array_filter( $ax_sh_results, static fn( bool $result ) : bool => ! $result ) );
// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI fixture output.
printf( "\n== %d checks, %d failed ==\n", count( $ax_sh_results ), $ax_sh_failures );
if ( class_exists( 'WP_CLI' ) ) {
	WP_CLI::halt( $ax_sh_failures > 0 ? 1 : 0 );
}
exit( $ax_sh_failures > 0 ? 1 : 0 );
