<?php
/**
 * The calendar grid's query and layout (dev-only; dist-excluded).
 *
 * Two rules meet here and must not be confused. Federation asks "may this be sent to other
 * servers?" and withholds recurring Events, because FEP-8a8e describes a single occurrence. The grid
 * asks "may this person see it on this site?" and shows them, because the local calendar has no such
 * limitation. Collapsing the two would either hide series from their own site or publish them
 * wrongly, and both failures look fine from the side you are not testing.
 *
 * The other property is the display zone. The grid is laid out in the site's timezone while each
 * Event keeps its own, so an event's row depends on the reader's clock rather than the venue's.
 *
 * @package AxismundiCalendar
 */

defined( 'ABSPATH' ) || exit( 1 );

global $wpdb;
$ax_ui_results = array();
$ax_ui_posts   = array();

/** @param bool[] $results Results. */
function ax_ui_assert( array &$results, string $label, bool $condition ) : void {
	$results[] = $condition;
	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI fixture output.
	printf( "[%s] %s\n", $condition ? 'PASS' : 'FAIL', $label );
}

/** Publish an Event with a schedule, through the real writers. */
function ax_ui_event( array &$posts, string $title, array $fields, string $status = 'publish' ) : int {
	$id      = (int) wp_insert_post( array( 'post_type' => AXISMUNDI_CAL_EVENT_POST_TYPE, 'post_status' => 'draft', 'post_author' => 1, 'post_title' => $title ) );
	$posts[] = $id;
	axismundi_cal_event_save( $id, $fields );
	if ( 'draft' !== $status ) {
		$GLOBALS['axismundi_cal_rest_write'] = true;
		wp_update_post( array( 'ID' => $id, 'post_status' => $status ) );
		$GLOBALS['axismundi_cal_rest_write'] = false;
	}
	return $id;
}

/** Titles of the occurrences a range returns. */
function ax_ui_titles( array $occurrences ) : array {
	return array_values( array_unique( array_map( static fn( array $o ) : string => (string) $o['title'], $occurrences ) ) );
}

try {
	$ax_ui_single = ax_ui_event(
		$ax_ui_posts,
		'Grid single',
		array( 'timezone' => 'Asia/Seoul', 'starts_at' => '2026-08-05 19:00:00', 'ends_at' => '2026-08-05 21:00:00' )
	);
	$ax_ui_series = ax_ui_event(
		$ax_ui_posts,
		'Grid series',
		array( 'timezone' => 'Asia/Seoul', 'starts_at' => '2026-08-01 10:00:00', 'ends_at' => '2026-08-01 12:00:00', 'rrule' => 'FREQ=WEEKLY;BYDAY=SA' )
	);
	$ax_ui_draft = ax_ui_event(
		$ax_ui_posts,
		'Grid draft',
		array( 'timezone' => 'Asia/Seoul', 'starts_at' => '2026-08-06 19:00:00', 'ends_at' => '2026-08-06 21:00:00' ),
		'draft'
	);

	$ax_ui_august = axismundi_cal_occurrences_in_range( '2026-07-31 15:00:00', '2026-08-31 15:00:00' );
	$ax_ui_titles = ax_ui_titles( $ax_ui_august );

	ax_ui_assert( $ax_ui_results, 'a published Event appears on the grid', in_array( 'Grid single', $ax_ui_titles, true ) );
	ax_ui_assert(
		$ax_ui_results,
		'a recurring Event appears too, even though it is withheld from federation',
		in_array( 'Grid series', $ax_ui_titles, true ) && false === axismundi_cal_event_visible( get_post( $ax_ui_series ) )
	);
	ax_ui_assert( $ax_ui_results, 'and a draft does not, because the grid is a public surface where a title is disclosure enough', ! in_array( 'Grid draft', $ax_ui_titles, true ) );

	$ax_ui_series_rows = array_filter( $ax_ui_august, static fn( array $o ) : bool => 'Grid series' === $o['title'] );
	ax_ui_assert( $ax_ui_results, 'the series contributes one row per occurrence in the month, not one row for the series', count( $ax_ui_series_rows ) >= 4 );

	ax_ui_assert(
		$ax_ui_results,
		'occurrences come back ordered by when they happen, since the grid places them in order',
		ax_ui_titles( $ax_ui_august ) === ax_ui_titles( $ax_ui_august )
			&& array_map( static fn( array $o ) : string => (string) $o['start_utc'], $ax_ui_august )
				=== ( static function ( array $rows ) : array {
					$starts = array_map( static fn( array $o ) : string => (string) $o['start_utc'], $rows );
					sort( $starts );
					return $starts;
				} )( $ax_ui_august )
	);

	// -- Password protection ------------------------------------------------------------------

	wp_update_post( array( 'ID' => $ax_ui_single, 'post_password' => 'secret' ) );
	$ax_ui_protected = ax_ui_titles( axismundi_cal_occurrences_in_range( '2026-07-31 15:00:00', '2026-08-31 15:00:00' ) );
	ax_ui_assert( $ax_ui_results, 'a password-protected Event is kept off the grid, since its title would leak past the password', ! in_array( 'Grid single', $ax_ui_protected, true ) );
	wp_update_post( array( 'ID' => $ax_ui_single, 'post_password' => '' ) );

	// -- The display zone decides which day a row lands on -------------------------------------

	$ax_ui_late = ax_ui_event(
		$ax_ui_posts,
		'Late evening Seoul',
		array( 'timezone' => 'Asia/Seoul', 'starts_at' => '2026-08-10 08:00:00', 'ends_at' => '2026-08-10 09:00:00' )
	);
	$ax_ui_rows = array_values( array_filter(
		axismundi_cal_occurrences_in_range( '2026-08-09 00:00:00', '2026-08-12 00:00:00' ),
		static fn( array $o ) : bool => 'Late evening Seoul' === $o['title']
	) );
	ax_ui_assert( $ax_ui_results, 'the fixture event is found', 1 === count( $ax_ui_rows ) );

	$ax_ui_seoul  = axismundi_cal_group_by_day( $ax_ui_rows, new DateTimeZone( 'Asia/Seoul' ) );
	$ax_ui_london = axismundi_cal_group_by_day( $ax_ui_rows, new DateTimeZone( 'Europe/London' ) );
	ax_ui_assert( $ax_ui_results, 'in the event zone it falls on its own date', isset( $ax_ui_seoul['2026-08-10'] ) );
	ax_ui_assert(
		$ax_ui_results,
		'and a site in another zone places the same instant on the day its own clock says, which is what makes the grid agree with the reader',
		isset( $ax_ui_london['2026-08-10'] ) && ! isset( $ax_ui_london['2026-08-11'] )
	);

	// -- An event spanning midnight is on both days it touches ---------------------------------

	$ax_ui_span = ax_ui_event(
		$ax_ui_posts,
		'Overnight',
		array( 'timezone' => 'UTC', 'starts_at' => '2026-08-20 22:00:00', 'ends_at' => '2026-08-21 02:00:00' )
	);
	$ax_ui_span_rows = array_values( array_filter(
		axismundi_cal_occurrences_in_range( '2026-08-19 00:00:00', '2026-08-23 00:00:00' ),
		static fn( array $o ) : bool => 'Overnight' === $o['title']
	) );
	$ax_ui_span_days = axismundi_cal_group_by_day( $ax_ui_span_rows, new DateTimeZone( 'UTC' ) );
	ax_ui_assert(
		$ax_ui_results,
		'an event running past midnight appears on both days, so a reader looking at the second one sees what is still going on',
		isset( $ax_ui_span_days['2026-08-20'] ) && isset( $ax_ui_span_days['2026-08-21'] )
	);

	// -- The block renders -----------------------------------------------------------------------

	/*
	 * The files `block.json` names must exist. Rendering does not prove they do: the grid comes from
	 * `render.php`, so a missing editor script or stylesheet leaves the front end looking perfect
	 * while the block cannot be inserted and has no styling. That is exactly how a block shipped
	 * once with two of its files written outside the repository.
	 */
	$ax_ui_dir      = dirname( __DIR__ ) . '/blocks/calendar';
	$ax_ui_manifest = json_decode( (string) file_get_contents( $ax_ui_dir . '/block.json' ), true );
	$ax_ui_missing  = array();
	foreach ( array( 'editorScript', 'script', 'viewScript', 'render' ) as $ax_ui_field ) {
		$ax_ui_value = (string) ( $ax_ui_manifest[ $ax_ui_field ] ?? '' );
		if ( str_starts_with( $ax_ui_value, 'file:' ) && ! file_exists( $ax_ui_dir . '/' . ltrim( substr( $ax_ui_value, 5 ), './' ) ) ) {
			$ax_ui_missing[] = $ax_ui_field;
		}
	}
	ax_ui_assert( $ax_ui_results, 'every file block.json names is actually present', array() === $ax_ui_missing );
	ax_ui_assert( $ax_ui_results, 'and the stylesheet the block asks for is registered, so the grid is not unstyled', wp_style_is( 'axismundi-calendar-grid', 'registered' ) );

	$ax_ui_html = do_blocks( '<!-- wp:axismundi-calendar/calendar {"view":"month"} /-->' );
	ax_ui_assert( $ax_ui_results, 'the block renders a grid', str_contains( $ax_ui_html, 'ax-cal__grid' ) && str_contains( $ax_ui_html, '<table' ) );
	ax_ui_assert( $ax_ui_results, 'with navigation that works without JavaScript, as ordinary links', str_contains( $ax_ui_html, 'ax_cal_view=month' ) && str_contains( $ax_ui_html, 'rel="prev"' ) );
	ax_ui_assert( $ax_ui_results, 'and weekday column headers, because a month grid is tabular data', str_contains( $ax_ui_html, '<th scope="col">' ) );

	$ax_ui_week = do_blocks( '<!-- wp:axismundi-calendar/calendar {"view":"week"} /-->' );
	ax_ui_assert( $ax_ui_results, 'the week view renders as its own layout', str_contains( $ax_ui_week, 'ax-cal--week' ) );

	// A hostile period is a public query argument, so it must degrade rather than error.
	$_GET['ax_cal'] = 'not-a-date';
	$ax_ui_bad      = do_blocks( '<!-- wp:axismundi-calendar/calendar /-->' );
	unset( $_GET['ax_cal'] );
	ax_ui_assert( $ax_ui_results, 'an unparseable period in the query string falls back to now instead of erroring', str_contains( $ax_ui_bad, 'ax-cal__grid' ) );
} finally {
	foreach ( array_unique( $ax_ui_posts ) as $ax_ui_post_id ) {
		$ax_ui_row = axismundi_cal_schedule_for_event( (int) $ax_ui_post_id );
		if ( is_array( $ax_ui_row ) ) {
			$wpdb->delete( axismundi_cal_occurrences_table(), array( 'schedule_id' => (int) $ax_ui_row['id'] ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->delete( axismundi_cal_schedules_table(), array( 'id' => (int) $ax_ui_row['id'] ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		}
		$wpdb->delete( axismundi_cal_events_table(), array( 'post_id' => (int) $ax_ui_post_id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		wp_delete_post( (int) $ax_ui_post_id, true );
	}
}

$ax_ui_failures = count( array_filter( $ax_ui_results, static fn( bool $result ) : bool => ! $result ) );
// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI fixture output.
printf( "\n== %d checks, %d failed ==\n", count( $ax_ui_results ), $ax_ui_failures );
if ( class_exists( 'WP_CLI' ) ) {
	WP_CLI::halt( $ax_ui_failures > 0 ? 1 : 0 );
}
exit( $ax_ui_failures > 0 ? 1 : 0 );
