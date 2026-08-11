<?php
/**
 * The workspace range endpoint (dev-only; dist-excluded).
 *
 * `calendarView` is the one query the calendar screen makes, and it is the place where the public
 * gate would most plausibly be undone by accident: it exists precisely to show a person Calendars
 * that are not public, so it must reach them through the ACL rather than around it.
 *
 * Three properties:
 *
 *   every named Calendar is authorized on its own, and an unreadable one is dropped, not refused
 *   subscribed Calendars contribute their cached entries, marked read-only
 *   the cap applies after the merge, so a busy month is truncated once rather than per Calendar
 *
 * The last one sounds like housekeeping and is not: a limit applied per Calendar returns the first
 * n of each and calls the result a month, which looks complete and is not.
 *
 * The screen itself is not asserted here. It renders from these answers and holds no permission
 * logic of its own -- which is the reason it can be left to the browser.
 *
 * @package AxismundiCalendar
 */

defined( 'ABSPATH' ) || exit( 1 );

global $wpdb;
$ax_ws_results   = array();
$ax_ws_calendars = array();
$ax_ws_posts     = array();
$ax_ws_users     = array();
$ax_ws_sources   = array();

$ax_ws_workspace_script = (string) file_get_contents( dirname( __DIR__ ) . '/assets/admin/workspace.js' );
$ax_ws_workspace_admin  = (string) file_get_contents( dirname( __DIR__ ) . '/includes/admin-workspace.php' );

/** @param bool[] $results Results. */
function ax_ws_assert( array &$results, string $label, bool $condition ) : void {
	$results[] = $condition;
	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI fixture output.
	printf( "[%s] %s\n", $condition ? 'PASS' : 'FAIL', $label );
}

// -- Client configuration -------------------------------------------------------------------------

ax_ws_assert(
	$ax_ws_results,
	'the workspace normalizes WordPress&rsquo;s string-localized week start before using it for weekday names',
	str_contains( $ax_ws_workspace_script, 'var startOfWeek = Number( config.startOfWeek );' )
		&& str_contains( $ax_ws_workspace_script, 'weekdayNames( startOfWeek )' )
);
ax_ws_assert(
	$ax_ws_results,
	'the workspace supplies the current WordPress admin locale to Intl instead of adopting the browser locale',
	str_contains( $ax_ws_workspace_admin, "'locale'    => str_replace( '_', '-', determine_locale() )" )
		&& str_contains( $ax_ws_workspace_script, 'toLocaleDateString( locale,' )
);

/** A user with a public Person Actor. */
function ax_ws_user( array &$users ) : array {
	$login   = 'ax_ws_' . strtolower( wp_generate_password( 8, false, false ) );
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

/** An Event published on one Calendar. */
function ax_ws_event( array &$posts, int $calendar_id, string $title, string $day ) : int {
	$post_id = (int) wp_insert_post( array( 'post_type' => AXISMUNDI_CAL_EVENT_POST_TYPE, 'post_status' => 'draft', 'post_title' => $title ) );
	$posts[] = $post_id;
	axismundi_cal_event_save(
		$post_id,
		array( 'calendar_id' => $calendar_id, 'timezone' => 'Asia/Seoul', 'starts_at' => $day . ' 19:00:00', 'ends_at' => $day . ' 21:00:00' )
	);
	$GLOBALS['axismundi_cal_rest_write'] = true;
	wp_update_post( array( 'ID' => $post_id, 'post_status' => 'publish' ) );
	$GLOBALS['axismundi_cal_rest_write'] = false;
	return $post_id;
}

/** Ask the workspace endpoint. */
function ax_ws_view( array $uuids, string $start = '2026-09-01T00:00:00Z', string $end = '2026-10-01T00:00:00Z', int $limit = 0 ) : array {
	$request = new WP_REST_Request( 'GET', '/axismundi/v1/actors/me/calendarView' );
	$request->set_param( 'calendars', implode( ',', $uuids ) );
	$request->set_param( 'start', $start );
	$request->set_param( 'end', $end );
	if ( $limit > 0 ) {
		$request->set_param( 'limit', $limit );
	}
	$response = rest_do_request( $request );
	return array( (int) $response->get_status(), (array) $response->get_data() );
}

/** The summaries in one answer. */
function ax_ws_summaries( array $body ) : array {
	return array_map( static fn( array $item ) : string => (string) $item['summary'], (array) ( $body['items'] ?? array() ) );
}

try {
	$ax_ws_viewer = ax_ws_user( $ax_ws_users );

	$ax_ws_mine = (int) axismundi_cal_calendar_save(
		array( 'name' => 'Workspace mine', 'slug' => 'ax-ws-mine', 'timezone' => 'Asia/Seoul', 'owner_actor_uri' => $ax_ws_viewer['actor_uri'] )
	);
	$ax_ws_shared = (int) axismundi_cal_calendar_save(
		array( 'name' => 'Workspace shared', 'slug' => 'ax-ws-shared', 'timezone' => 'Asia/Seoul', 'owner_actor_uri' => $ax_ws_viewer['actor_uri'] )
	);
	$ax_ws_closed = (int) axismundi_cal_calendar_save(
		array( 'name' => 'Workspace closed', 'slug' => 'ax-ws-closed', 'timezone' => 'Asia/Seoul', 'owner_actor_uri' => $ax_ws_viewer['actor_uri'] )
	);
	$ax_ws_calendars = array( $ax_ws_mine, $ax_ws_shared, $ax_ws_closed );

	$ax_ws_uuid   = static fn( int $id ) : string => (string) axismundi_cal_calendar_get( $id )['uuid'];
	$ax_ws_mine_id   = $ax_ws_uuid( $ax_ws_mine );
	$ax_ws_shared_id = $ax_ws_uuid( $ax_ws_shared );
	$ax_ws_closed_id = $ax_ws_uuid( $ax_ws_closed );

	ax_ws_event( $ax_ws_posts, $ax_ws_mine, 'Mine event', '2026-09-10' );
	ax_ws_event( $ax_ws_posts, $ax_ws_shared, 'Shared event', '2026-09-11' );
	ax_ws_event( $ax_ws_posts, $ax_ws_closed, 'Closed event', '2026-09-12' );

	// -- The owner sees their own, public or not --------------------------------------------------

	wp_set_current_user( $ax_ws_viewer['user_id'] );
	/*
	 * Asked in the opposite order to the one they happen in, which is the only way to tell a merge
	 * that sorts from one that concatenates.
	 */
	list( $ax_ws_status, $ax_ws_body ) = ax_ws_view( array( $ax_ws_shared_id, $ax_ws_mine_id ) );
	ax_ws_assert( $ax_ws_results, 'the range answers', 200 === $ax_ws_status );
	ax_ws_assert(
		$ax_ws_results,
		'and merges several Calendars into one answer, ordered by when things happen rather than by which Calendar was named first',
		array( 'Mine event', 'Shared event' ) === ax_ws_summaries( $ax_ws_body )
	);
	ax_ws_assert(
		$ax_ws_results,
		'with each item saying which Calendar it belongs to, so the screen can tell them apart',
		$ax_ws_mine_id === (string) $ax_ws_body['items'][0]['calendar']
	);

	/*
	 * None of these Calendars is public, and the point of the endpoint is that their owner still sees
	 * them. Asserted alongside the public gate, so a change that opened one would show up as both.
	 */
	ax_ws_assert( $ax_ws_results, 'while none of them is public', false === axismundi_cal_is_publicly_readable( $ax_ws_mine ) );
	ax_ws_assert( $ax_ws_results, 'and their Events stay off the public grid', false === axismundi_cal_event_listable( get_post( $ax_ws_posts[0] ) ) );

	// -- Somebody else gets none of it ----------------------------------------------------------------

	$ax_ws_stranger = ax_ws_user( $ax_ws_users );
	wp_set_current_user( $ax_ws_stranger['user_id'] );
	list( $ax_ws_status, $ax_ws_body ) = ax_ws_view( array( $ax_ws_mine_id, $ax_ws_shared_id, $ax_ws_closed_id ) );
	ax_ws_assert( $ax_ws_results, 'naming somebody else&rsquo;s Calendars is not refused', 200 === $ax_ws_status );
	ax_ws_assert( $ax_ws_results, 'it simply returns nothing from them', array() === ax_ws_summaries( $ax_ws_body ) );

	wp_set_current_user( 0 );
	list( , $ax_ws_body ) = ax_ws_view( array( $ax_ws_mine_id ) );
	ax_ws_assert( $ax_ws_results, 'and an anonymous request gets nothing either', array() === ax_ws_summaries( $ax_ws_body ) );

	// -- One unreadable Calendar does not empty the screen ------------------------------------------------

	/*
	 * The reason an unreadable Calendar is dropped rather than refused. A stale tick in somebody's
	 * sidebar -- access revoked, calendar deleted -- would otherwise blank the whole month instead of
	 * quietly losing the one calendar they can no longer see.
	 */
	wp_set_current_user( $ax_ws_viewer['user_id'] );
	axismundi_cal_acl_revoke( $ax_ws_closed, $ax_ws_viewer['actor_uri'] );
	list( $ax_ws_status, $ax_ws_body ) = ax_ws_view( array( $ax_ws_mine_id, $ax_ws_closed_id, wp_generate_uuid4() ) );
	ax_ws_assert(
		$ax_ws_results,
		'a Calendar they may no longer read drops out without taking the rest with it',
		200 === $ax_ws_status && array( 'Mine event' ) === ax_ws_summaries( $ax_ws_body )
	);
	list( $ax_ws_status, $ax_ws_body ) = ax_ws_view( array() );
	ax_ws_assert( $ax_ws_results, 'and asking for no Calendars at all is an empty answer, not an error', 200 === $ax_ws_status && array() === ax_ws_summaries( $ax_ws_body ) );

	// -- A subscribed Calendar contributes its cache ---------------------------------------------------------

	$ax_ws_sub = (int) axismundi_cal_calendar_save(
		array( 'name' => 'Workspace subscribed', 'slug' => 'ax-ws-sub', 'timezone' => 'Asia/Seoul', 'kind' => 'remote' )
	);
	$ax_ws_calendars[] = $ax_ws_sub;
	$ax_ws_sub_id = $ax_ws_uuid( $ax_ws_sub );
	axismundi_cal_acl_grant( $ax_ws_sub, $ax_ws_viewer['actor_uri'], 'reader' );

	$ax_ws_url = 'https://example.org/ax-ws-' . wp_generate_password( 8, false, false ) . '.ics';
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fixture rows in this plugin's own tables.
	$wpdb->insert(
		axismundi_cal_sources_table(),
		array(
			'calendar_id'     => $ax_ws_sub,
			'kind'            => 'ical',
			'authority'       => 'remote',
			'source_url'      => $ax_ws_url,
			'source_url_hash' => hash( 'sha256', $ax_ws_url ),
			'sync_status'     => 'ok',
			'sync_error'      => '',
			'created_at'      => current_time( 'mysql', true ),
			'updated_at'      => current_time( 'mysql', true ),
		)
	);
	$ax_ws_source_id = (int) $wpdb->insert_id;
	$ax_ws_sources[] = $ax_ws_source_id;
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fixture row in this plugin's own table.
	$wpdb->insert(
		axismundi_cal_entries_table(),
		array(
			'source_id'   => $ax_ws_source_id,
			'ical_uid'    => 'ax-ws-holiday@example.org',
			'entry_hash'  => hash( 'sha256', 'ax-ws-holiday' ),
			'summary'     => 'Subscribed holiday',
			'location'    => '',
			'url'         => 'https://example.org/holiday',
			'timezone'    => 'Asia/Seoul',
			'all_day'     => 1,
			'start_utc'   => '2026-09-15 00:00:00',
			'end_utc'     => '2026-09-16 00:00:00',
			'start_local' => '2026-09-15 00:00:00',
			'end_local'   => '2026-09-16 00:00:00',
			'presence'    => 'present',
			'last_seen_at' => current_time( 'mysql', true ),
			'created_at'  => current_time( 'mysql', true ),
			'updated_at'  => current_time( 'mysql', true ),
		)
	);

	list( , $ax_ws_body ) = ax_ws_view( array( $ax_ws_mine_id, $ax_ws_sub_id ) );
	ax_ws_assert(
		$ax_ws_results,
		'a subscribed Calendar shows up beside a local one',
		array( 'Mine event', 'Subscribed holiday' ) === ax_ws_summaries( $ax_ws_body )
	);
	$ax_ws_holiday = array_values( array_filter( (array) $ax_ws_body['items'], static fn( array $i ) : bool => 'Subscribed holiday' === $i['summary'] ) )[0];
	ax_ws_assert( $ax_ws_results, 'marked read-only, because it changes at its source and not here', true === $ax_ws_holiday['readOnly'] );
	ax_ws_assert( $ax_ws_results, 'with no local Event behind it to offer to edit', 0 === (int) $ax_ws_holiday['eventId'] );
	ax_ws_assert( $ax_ws_results, 'while a local occurrence is not read-only', false === $ax_ws_body['items'][0]['readOnly'] );
	ax_ws_assert(
		$ax_ws_results,
		'and an all-day entry keeps its civil date, which is what stops a holiday moving a day west',
		'2026-09-15 00:00:00' === (string) $ax_ws_holiday['startLocal'] && true === $ax_ws_holiday['allDay']
	);

	// -- The cap is applied to the answer, not to each Calendar ------------------------------------------------

	/*
	 * Two Calendars, one item each, asked for one item. A per-Calendar cap returns two and reports
	 * nothing amiss; the merged cap returns one and says it is short.
	 */
	list( , $ax_ws_body ) = ax_ws_view( array( $ax_ws_mine_id, $ax_ws_sub_id ), '2026-09-01T00:00:00Z', '2026-10-01T00:00:00Z', 1 );
	ax_ws_assert( $ax_ws_results, 'the limit counts the merged answer rather than each Calendar', 1 === count( (array) $ax_ws_body['items'] ) );
	ax_ws_assert( $ax_ws_results, 'and says so, so a short month is not mistaken for a quiet one', true === $ax_ws_body['truncated'] );
	list( , $ax_ws_body ) = ax_ws_view( array( $ax_ws_mine_id, $ax_ws_sub_id ) );
	ax_ws_assert( $ax_ws_results, 'while a complete answer does not claim to be short', false === $ax_ws_body['truncated'] );

	// -- Range arguments ----------------------------------------------------------------------------------------

	ax_ws_assert( $ax_ws_results, 'a backwards range is refused', 400 === ax_ws_view( array( $ax_ws_mine_id ), '2026-10-01T00:00:00Z', '2026-09-01T00:00:00Z' )[0] );
	ax_ws_assert( $ax_ws_results, 'and a decade is refused rather than truncated', 400 === ax_ws_view( array( $ax_ws_mine_id ), '2026-01-01T00:00:00Z', '2036-01-01T00:00:00Z' )[0] );

	// -- Naming one Calendar twice ---------------------------------------------------------------------------------

	list( , $ax_ws_body ) = ax_ws_view( array( $ax_ws_mine_id, $ax_ws_mine_id ) );
	ax_ws_assert( $ax_ws_results, 'a Calendar named twice contributes once', array( 'Mine event' ) === ax_ws_summaries( $ax_ws_body ) );
} finally {
	wp_set_current_user( 0 );
	foreach ( $ax_ws_posts as $ax_ws_post ) {
		wp_delete_post( (int) $ax_ws_post, true );
	}
	foreach ( $ax_ws_sources as $ax_ws_source ) {
		axismundi_cal_remove_source( (int) $ax_ws_source );
	}
	foreach ( array_unique( $ax_ws_calendars ) as $ax_ws_calendar ) {
		axismundi_cal_calendar_delete( (int) $ax_ws_calendar );
	}
	foreach ( $ax_ws_users as $ax_ws_user_id ) {
		wp_delete_user( (int) $ax_ws_user_id );
	}
}

$ax_ws_failures = count( array_filter( $ax_ws_results, static fn( bool $result ) : bool => ! $result ) );
// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI fixture output.
printf( "\n== %d checks, %d failed ==\n", count( $ax_ws_results ), $ax_ws_failures );
if ( class_exists( 'WP_CLI' ) ) {
	WP_CLI::halt( $ax_ws_failures > 0 ? 1 : 0 );
}
exit( $ax_ws_failures > 0 ? 1 : 0 );
