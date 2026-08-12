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

/*
 * Two settings the workspace must not decide for itself, asserted through the values it is handed
 * rather than by reading its source. A screen that respects General Settings and one whose source
 * happens to contain the right words are different claims, and only the first is worth asserting.
 */
$ax_ws_config = axismundi_cal_workspace_config();

update_option( 'start_of_week', '3' );
ax_ws_assert(
	$ax_ws_results,
	'the week starts where General Settings says',
	3 === axismundi_cal_workspace_config()['startOfWeek']
);
update_option( 'start_of_week', (string) $ax_ws_config['startOfWeek'] );
ax_ws_assert( $ax_ws_results, 'and follows that setting rather than one of its own', (int) get_option( 'start_of_week' ) === axismundi_cal_workspace_config()['startOfWeek'] );

/*
 * The type is the point. `wp_localize_script()` casts every value to a string, which is how the week
 * start reached the browser as "1" and started the weekday header on Wednesday -- "1" + 0
 * concatenates where 1 + 0 adds. The payload is JSON-encoded now, so the number survives the trip.
 */
$ax_ws_payload = json_decode( (string) wp_json_encode( axismundi_cal_workspace_config() ), true );
ax_ws_assert( $ax_ws_results, 'and reaches the browser as a number rather than as a string', is_int( $ax_ws_payload['startOfWeek'] ) );

ax_ws_assert(
	$ax_ws_results,
	'month and weekday names are formatted in the admin locale, not whichever one the browser prefers',
	str_replace( '_', '-', determine_locale() ) === $ax_ws_config['locale']
);
ax_ws_assert( $ax_ws_results, 'in the form Intl accepts, which is not the form WordPress stores', ! str_contains( (string) $ax_ws_config['locale'], '_' ) );

/*
 * Core DatePicker returns a local `Y-m-d\\TH:i:s` string. Passing a Date through its formatter then
 * parsing the response as ISO crosses the browser/site timezone boundary and selects the previous
 * day on UTC sites east of Greenwich. Keep the boundary explicitly civil instead.
 */
ax_ws_assert(
	$ax_ws_results,
	'the mini picker receives a local wall-time cursor rather than a timezone-bearing Date',
	str_contains( $ax_ws_workspace_script, 'currentDate: datePickerValue( props.cursor )' )
);
ax_ws_assert(
	$ax_ws_results,
	'and reads its selected year, month and day without ISO timezone parsing',
	str_contains( $ax_ws_workspace_script, 'var parsed = new Date( year, month, day );' ) && ! str_contains( $ax_ws_workspace_script, 'new Date( String( value ) )' )
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

/** Ask the bootstrap endpoint. */
function ax_ws_bootstrap( string $start = '2026-09-01T00:00:00Z', string $end = '2026-10-01T00:00:00Z' ) : array {
	$request = new WP_REST_Request( 'GET', '/axismundi/v1/actors/me/calendarWorkspace' );
	$request->set_param( 'start', $start );
	$request->set_param( 'end', $end );
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

	// -- The first request answers both halves ---------------------------------------------------------

	/*
	 * The waterfall this removes: asked separately, the grid cannot start until the list has come back
	 * and said which Calendars are ticked, so it arrives visibly later than the sidebar even when both
	 * are quick.
	 */
	wp_set_current_user( $ax_ws_viewer['user_id'] );
	list( $ax_ws_status, $ax_ws_boot ) = ax_ws_bootstrap();
	ax_ws_assert( $ax_ws_results, 'one request returns the sidebar and the grid together', 200 === $ax_ws_status && isset( $ax_ws_boot['calendars'], $ax_ws_boot['view'] ) );

	/*
	 * And returns exactly what the separate endpoints would have. This is the property that keeps the
	 * bootstrap from becoming a second implementation that drifts: a client fetching the two halves
	 * individually must see the same thing as one fetching them together.
	 */
	$ax_ws_list_request = new WP_REST_Request( 'GET', '/axismundi/v1/actors/me/calendarList' );
	$ax_ws_list = (array) rest_do_request( $ax_ws_list_request )->get_data();
	ax_ws_assert( $ax_ws_results, 'with a sidebar identical to the calendarList endpoint', $ax_ws_list['items'] === $ax_ws_boot['calendars'] );

	$ax_ws_ticked = array_map( static fn( array $c ) : string => (string) $c['id'], (array) $ax_ws_boot['calendars'] );
	list( , $ax_ws_separate ) = ax_ws_view( $ax_ws_ticked );
	ax_ws_assert( $ax_ws_results, 'and a range identical to asking calendarView for the same Calendars', $ax_ws_separate['items'] === $ax_ws_boot['view']['items'] );

	/*
	 * `selected` and `hidden` are the caller's own view state, and the bootstrap applies them rather
	 * than asking the client which Calendars it wants -- that round trip would be the waterfall again.
	 */
	axismundi_cal_list_set( $ax_ws_mine, $ax_ws_viewer['actor_uri'], 'reader', array( 'hidden' => true ) );
	list( , $ax_ws_boot ) = ax_ws_bootstrap();
	ax_ws_assert(
		$ax_ws_results,
		'a Calendar hidden in somebody&rsquo;s own list is left out of the range',
		! in_array( 'Mine event', ax_ws_summaries( $ax_ws_boot['view'] ), true )
	);
	ax_ws_assert(
		$ax_ws_results,
		'while staying in the sidebar, because hiding it is not leaving it',
		in_array( $ax_ws_mine_id, array_map( static fn( array $c ) : string => (string) $c['id'], (array) $ax_ws_boot['calendars'] ), true )
	);
	axismundi_cal_list_set( $ax_ws_mine, $ax_ws_viewer['actor_uri'], 'reader', array( 'hidden' => false, 'selected' => false ) );
	list( , $ax_ws_boot ) = ax_ws_bootstrap();
	ax_ws_assert( $ax_ws_results, 'and an unticked Calendar is left out too', ! in_array( 'Mine event', ax_ws_summaries( $ax_ws_boot['view'] ), true ) );
	axismundi_cal_list_set( $ax_ws_mine, $ax_ws_viewer['actor_uri'], 'reader', array( 'selected' => true ) );
	list( , $ax_ws_boot ) = ax_ws_bootstrap();
	ax_ws_assert( $ax_ws_results, 'ticking it again brings it back', in_array( 'Mine event', ax_ws_summaries( $ax_ws_boot['view'] ), true ) );

	/*
	 * A Calendar somebody has access to but has never opened has no entry at all. It counts as shown:
	 * the alternative is access that is invisible until the person guesses they should go looking.
	 */
	axismundi_cal_list_remove( $ax_ws_shared, $ax_ws_viewer['actor_uri'] );
	list( , $ax_ws_boot ) = ax_ws_bootstrap();
	ax_ws_assert(
		$ax_ws_results,
		'a Calendar with no view state yet is shown rather than silently off',
		in_array( 'Shared event', ax_ws_summaries( $ax_ws_boot['view'] ), true )
	);

	ax_ws_assert( $ax_ws_results, 'a backwards range is refused here as well', 400 === ax_ws_bootstrap( '2026-10-01T00:00:00Z', '2026-09-01T00:00:00Z' )[0] );

	wp_set_current_user( 0 );
	ax_ws_assert( $ax_ws_results, 'and `me` with nobody signed in is unauthenticated', 401 === ax_ws_bootstrap()[0] );
	wp_set_current_user( $ax_ws_viewer['user_id'] );

	// -- A maintained dataset, once however many of its languages are ticked -----------------------------

	/*
	 * 대한민국의 휴일 and Holidays in South Korea are one dataset in two languages. Ticking both must
	 * not put every holiday on the grid twice: the day is the thing, and the language is how it is
	 * written.
	 */
	$ax_ws_ko = (int) axismundi_cal_calendar_save(
		array( 'kind' => 'system', 'system_provider' => 'holiday', 'provider_config' => array( 'region' => 'KR', 'source_locale' => 'ko-KR' ), 'name' => 'WS ko', 'slug' => 'ax-ws-ko-' . wp_generate_password( 5, false, false ), 'timezone' => 'Asia/Seoul' )
	);
	$ax_ws_en2 = (int) axismundi_cal_calendar_save(
		array( 'kind' => 'system', 'system_provider' => 'holiday', 'provider_config' => array( 'region' => 'KR', 'source_locale' => 'en-US' ), 'name' => 'WS en', 'slug' => 'ax-ws-en-' . wp_generate_password( 5, false, false ), 'timezone' => 'Asia/Seoul' )
	);
	$ax_ws_calendars[] = $ax_ws_ko;
	$ax_ws_calendars[] = $ax_ws_en2;
	$ax_ws_cat = (int) axismundi_cal_holiday_catalog_save( array( 'provider' => 'holiday', 'jurisdiction' => 'KR', 'label' => 'WS catalog' ) );
	axismundi_cal_join_holiday_catalog( $ax_ws_ko, $ax_ws_cat );
	axismundi_cal_join_holiday_catalog( $ax_ws_en2, $ax_ws_cat );
	$ax_ws_concept = (int) axismundi_cal_holiday_concept_save( array( 'catalog_id' => $ax_ws_cat, 'label' => 'WS holiday', 'categories' => array( 'HOLIDAY', 'PUBLIC-HOLIDAY' ) ) );
	$ax_ws_occ = (int) axismundi_cal_holiday_occurrence_save( $ax_ws_concept, array( 'start_date' => '2026-09-20', 'role' => 'principal', 'status' => 'published' ) );
	$ax_ws_ko_item = (int) axismundi_cal_system_item_save( $ax_ws_ko, array( 'title' => '한국어 이름', 'start_date' => '2026-09-20', 'status' => 'published' ) );
	$ax_ws_en_item = (int) axismundi_cal_system_item_save( $ax_ws_en2, array( 'title' => 'English name', 'start_date' => '2026-09-20', 'status' => 'published' ) );
	axismundi_cal_link_item_to_occurrence( $ax_ws_ko_item, $ax_ws_occ );
	axismundi_cal_link_item_to_occurrence( $ax_ws_en_item, $ax_ws_occ );

	$ax_ws_ko_uuid  = (string) axismundi_cal_calendar_get( $ax_ws_ko )['uuid'];
	$ax_ws_en_uuid  = (string) axismundi_cal_calendar_get( $ax_ws_en2 )['uuid'];
	list( , $ax_ws_both ) = ax_ws_view( array( $ax_ws_ko_uuid, $ax_ws_en_uuid ), '2026-09-01T00:00:00Z', '2026-10-01T00:00:00Z' );
	ax_ws_assert( $ax_ws_results, 'a day of a holiday appears once however many of its languages are ticked', 1 === count( (array) $ax_ws_both['items'] ) );
	ax_ws_assert( $ax_ws_results, 'as a whole day, which it is everywhere', true === $ax_ws_both['items'][0]['allDay'] );
	ax_ws_assert( $ax_ws_results, 'and read-only, since nobody authored it', true === $ax_ws_both['items'][0]['readOnly'] );
	ax_ws_assert( $ax_ws_results, 'saying which language it is shown in', '' !== (string) $ax_ws_both['items'][0]['locale'] );

	/*
	 * A day nobody has related to anything is still a day. Hiding unlinked rows would make a dataset
	 * look thinner than it is while somebody is still reviewing it.
	 */
	$ax_ws_loose = (int) axismundi_cal_system_item_save( $ax_ws_ko, array( 'title' => '연결 안 된 날', 'start_date' => '2026-09-25', 'status' => 'published' ) );
	list( , $ax_ws_loose_body ) = ax_ws_view( array( $ax_ws_ko_uuid ), '2026-09-01T00:00:00Z', '2026-10-01T00:00:00Z' );
	ax_ws_assert( $ax_ws_results, 'a row linked to no holiday is shown on its own', 2 === count( (array) $ax_ws_loose_body['items'] ) );

	/*
	 * And a draft year is not on anybody's calendar. Review is what publishing means here.
	 */
	axismundi_cal_system_item_save( $ax_ws_ko, array( 'status' => 'draft' ), $ax_ws_loose );
	list( , $ax_ws_draft_body ) = ax_ws_view( array( $ax_ws_ko_uuid ), '2026-09-01T00:00:00Z', '2026-10-01T00:00:00Z' );
	ax_ws_assert( $ax_ws_results, 'while an unreviewed one is not', 1 === count( (array) $ax_ws_draft_body['items'] ) );

	/*
	 * The sidebar groups on this. Two calendars of one dataset carry the same catalog, and a calendar
	 * that is nobody's sibling carries none -- which is nearly all of them, and grouping those would
	 * be inventing a relation.
	 */
	$ax_ws_ko_row = axismundi_cal_rest_calendar( (array) axismundi_cal_calendar_get( $ax_ws_ko ) );
	$ax_ws_en_row = axismundi_cal_rest_calendar( (array) axismundi_cal_calendar_get( $ax_ws_en2 ) );
	ax_ws_assert(
		$ax_ws_results,
		'two languages of one dataset report the same dataset, so a sidebar can gather them',
		'' !== (string) $ax_ws_ko_row['catalog'] && $ax_ws_ko_row['catalog'] === $ax_ws_en_row['catalog']
	);
	ax_ws_assert(
		$ax_ws_results,
		'while an ordinary calendar reports none, being nobody sibling',
		'' === (string) axismundi_cal_rest_calendar( (array) axismundi_cal_calendar_get( $ax_ws_mine ) )['catalog']
	);

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
