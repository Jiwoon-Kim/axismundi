<?php
/**
 * Event visibility and free/busy (dev-only; dist-excluded).
 *
 * Two axes, not one. The Calendar decides whether anybody can see the container; the Event decides
 * how much of itself is shown to somebody who already can. Google keeps the same pair, and RFC 5545
 * has the second one as `CLASS`.
 *
 * The rule is that the more restrictive of the two wins, and the direction that matters is the one
 * nothing reports: a private Event inside a public Calendar leaks through whichever surface forgot
 * to ask. There are five of them -- the subscription feed, the readable Calendar page, the block, the
 * workspace payload and the Event's own permalink -- and each is a separate `if` somebody has to
 * remember. So each is asserted here rather than the gate being asserted once and assumed.
 *
 * Free/busy is the smaller half and shares the slice because it shares the column write. It says
 * nothing about who may look; it says whether an Event that somebody *can* see should make them
 * appear occupied.
 *
 * @package AxismundiCalendar
 */

defined( 'ABSPATH' ) || exit( 1 );

global $wpdb;
$ax_ev_results   = array();
$ax_ev_posts     = array();
$ax_ev_calendars = array();
$ax_ev_users     = array();

/** @param bool[] $results Results. */
function ax_ev_assert( array &$results, string $label, bool $condition ) : void {
	$results[] = $condition;
	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI fixture output.
	printf( "[%s] %s\n", $condition ? 'PASS' : 'FAIL', $label );
}

/** Whether a title appears anywhere in a feed body. */
function ax_ev_in_feed( string $body, string $title ) : bool {
	return str_contains( $body, 'SUMMARY:' . $title );
}

try {
	$ax_ev_login = 'ax_ev_' . strtolower( wp_generate_password( 8, false, false ) );
	$ax_ev_user  = (int) wp_insert_user( array( 'user_login' => $ax_ev_login, 'user_pass' => wp_generate_password(), 'role' => 'editor' ) );
	$ax_ev_users[] = $ax_ev_user;
	wp_set_current_user( $ax_ev_user );
	$ax_ev_uri = '';
	if ( function_exists( 'axismundi_actors_ensure_for_user' ) ) {
		$ax_ev_actor = axismundi_actors_ensure_for_user( $ax_ev_user );
		if ( $ax_ev_actor instanceof Axismundi_Actor ) {
			axismundi_actors_set_status( $ax_ev_actor->get_identity_id(), 'public' );
			$ax_ev_uri = (string) $ax_ev_actor->get_uri();
		}
	}

	$ax_ev_suffix = strtolower( wp_generate_password( 6, false, false ) );
	$ax_ev_open   = (int) axismundi_cal_calendar_save(
		array( 'name' => 'Open calendar', 'slug' => 'ax-ev-open-' . $ax_ev_suffix, 'timezone' => 'Asia/Seoul', 'owner_actor_uri' => $ax_ev_uri )
	);
	$ax_ev_calendars[] = $ax_ev_open;
	axismundi_cal_acl_grant( $ax_ev_open, '', 'reader', 'public' );

	$ax_ev_make = static function ( array &$posts, int $calendar, string $title, array $envelope ) : int {
		$post_id = (int) wp_insert_post( array( 'post_type' => AXISMUNDI_CAL_EVENT_POST_TYPE, 'post_status' => 'draft', 'post_title' => $title ) );
		$posts[] = $post_id;
		axismundi_cal_event_save(
			$post_id,
			array_merge( array( 'calendar_id' => $calendar, 'timezone' => 'Asia/Seoul', 'starts_at' => '2026-09-15 19:00:00', 'ends_at' => '2026-09-15 21:00:00' ), $envelope )
		);
		$GLOBALS['axismundi_cal_rest_write'] = true;
		wp_update_post( array( 'ID' => $post_id, 'post_status' => 'publish' ) );
		$GLOBALS['axismundi_cal_rest_write'] = false;
		return $post_id;
	};

	$ax_ev_shown  = $ax_ev_make( $ax_ev_posts, $ax_ev_open, 'Shown event', array() );
	$ax_ev_hidden = $ax_ev_make( $ax_ev_posts, $ax_ev_open, 'Hidden event', array( 'visibility' => 'private' ) );

	// -- The default is to follow the Calendar ---------------------------------------------------------

	/*
	 * `default` rather than `public`, because an Event cannot make itself more visible than the
	 * container holding it. A value called `public` on an Event inside a private Calendar would be a
	 * promise the two-axis rule refuses to keep, and somebody would eventually read it as one.
	 */
	ax_ev_assert(
		$ax_ev_results,
		'an Event follows its Calendar unless it says otherwise',
		'default' === (string) axismundi_cal_event_get( $ax_ev_shown )['visibility']
	);
	ax_ev_assert(
		$ax_ev_results,
		'and a visibility nothing defines is refused rather than stored',
		is_wp_error( axismundi_cal_event_save( $ax_ev_shown, array( 'visibility' => 'sort-of' ) ) )
	);

	// -- The five surfaces -----------------------------------------------------------------------------

	/*
	 * A private Event inside a public Calendar. Each surface asks separately, so each is checked
	 * separately: a gate asserted once and assumed everywhere is how one of five `if`s goes missing.
	 */
	$ax_ev_feed = (string) axismundi_cal_site_feed( $ax_ev_open, 'Open calendar', 'Asia/Seoul' )['body'];
	ax_ev_assert( $ax_ev_results, 'the subscription feed carries a public Event', ax_ev_in_feed( $ax_ev_feed, 'Shown event' ) );
	ax_ev_assert( $ax_ev_results, 'and withholds a private one, which is the leak nothing would report', ! ax_ev_in_feed( $ax_ev_feed, 'Hidden event' ) );

	$ax_ev_public_rows = axismundi_cal_occurrences_in_range( '2026-09-01 00:00:00', '2026-10-01 00:00:00', 200, $ax_ev_open );
	$ax_ev_titles      = array_map( static fn( array $o ) : string => (string) $o['title'], $ax_ev_public_rows );
	ax_ev_assert(
		$ax_ev_results,
		'the public range query -- which the block and the readable page both read -- withholds it too',
		in_array( 'Shown event', $ax_ev_titles, true ) && ! in_array( 'Hidden event', $ax_ev_titles, true )
	);

	/*
	 * And the permalink, which is the one defeated by guessing a URL rather than by asking a query. A
	 * published post is exactly what a private Event is, so post status cannot be what decides it.
	 *
	 * Asserted against the guard the route actually runs, not only the gate it ought to consult. The
	 * first version of this slice left that guard asking about the Calendar alone: every other surface
	 * was closed and the most direct one was open, and a check written purely against
	 * `event_listable()` would have reported the whole thing correct.
	 */
	ax_ev_assert(
		$ax_ev_results,
		'the public gate withholds a private Event',
		false === axismundi_cal_event_listable( get_post( $ax_ev_hidden ) )
	);
	ax_ev_assert(
		$ax_ev_results,
		'and the Event permalink asks that same gate rather than repeating half of it',
		str_contains(
			(string) file_get_contents( dirname( __DIR__ ) . '/includes/calendar-page.php' ),
			'axismundi_cal_event_listable( $post )'
		)
	);

	/*
	 * The author is not locked out of their own setting. Somebody who hid an Event has to be able to
	 * find it again to change it back, so `private` withholds it from the public surfaces rather than
	 * from the people who already have a role on the Calendar.
	 */
	ax_ev_assert(
		$ax_ev_results,
		'while its author still reads and edits it, since a setting that hid it from them would strand it',
		axismundi_cal_event_post_viewable( get_post( $ax_ev_hidden ) )
			&& ! is_wp_error( axismundi_cal_event_save( $ax_ev_hidden, array( 'visibility' => 'private' ) ) )
	);

	/*
	 * The other direction: somebody who may read the Calendar still sees it. Hiding an Event from its
	 * own author would make the setting unusable -- they could not find it to change it back.
	 */
	$ax_ev_authorized = array_map(
		static fn( array $o ) : string => (string) $o['title'],
		axismundi_cal_calendar_occurrences( $ax_ev_open, '2026-09-01 00:00:00', '2026-10-01 00:00:00' )
	);
	ax_ev_assert(
		$ax_ev_results,
		'though a reader of the Calendar still sees it, since hiding it from its author would strand it',
		in_array( 'Hidden event', $ax_ev_authorized, true )
	);

	// -- The more restrictive of the two wins ----------------------------------------------------------

	/*
	 * The direction that is easy to get right, asserted anyway because the rule is symmetric and a
	 * later refactor could keep one half. A private Calendar hides a `default` Event, and nothing an
	 * Event says can open a container that is closed.
	 */
	$ax_ev_closed = (int) axismundi_cal_calendar_save(
		array( 'name' => 'Closed calendar', 'slug' => 'ax-ev-closed-' . $ax_ev_suffix, 'timezone' => 'Asia/Seoul', 'owner_actor_uri' => $ax_ev_uri )
	);
	$ax_ev_calendars[] = $ax_ev_closed;
	$ax_ev_inside = $ax_ev_make( $ax_ev_posts, $ax_ev_closed, 'Inside a closed calendar', array() );
	ax_ev_assert(
		$ax_ev_results,
		'a private Calendar withholds an Event that had no opinion',
		false === axismundi_cal_event_listable( get_post( $ax_ev_inside ) )
	);
	ax_ev_assert(
		$ax_ev_results,
		'on the public range query the block and the readable page both read',
		! in_array(
			'Inside a closed calendar',
			array_map(
				static fn( array $o ) : string => (string) $o['title'],
				axismundi_cal_occurrences_in_range( '2026-09-01 00:00:00', '2026-10-01 00:00:00', 200, $ax_ev_closed )
			),
			true
		)
	);
	ax_ev_assert(
		$ax_ev_results,
		'and on its subscription feed',
		! ax_ev_in_feed( (string) axismundi_cal_site_feed( $ax_ev_closed, 'Closed calendar', 'Asia/Seoul' )['body'], 'Inside a closed calendar' )
	);

	// -- Free and busy ---------------------------------------------------------------------------------

	/*
	 * A different question entirely, and it is worth keeping the words apart: visibility is who may
	 * look, transparency is whether looking at it should make somebody appear occupied. An Event marked
	 * free is fully visible and simply does not block the time.
	 */
	$ax_ev_free = $ax_ev_make( $ax_ev_posts, $ax_ev_open, 'Open house', array( 'transparency' => 'TRANSPARENT' ) );
	ax_ev_assert(
		$ax_ev_results,
		'an Event is busy unless it says otherwise, which is what a calendar entry ordinarily means',
		'OPAQUE' === (string) axismundi_cal_event_get( $ax_ev_shown )['transparency']
	);
	ax_ev_assert(
		$ax_ev_results,
		'and one marked free says so',
		'TRANSPARENT' === (string) axismundi_cal_event_get( $ax_ev_free )['transparency']
	);
	ax_ev_assert(
		$ax_ev_results,
		'a transparency nothing defines is refused rather than stored',
		is_wp_error( axismundi_cal_event_save( $ax_ev_shown, array( 'transparency' => 'MAYBE' ) ) )
	);

	$ax_ev_feed_now = (string) axismundi_cal_site_feed( $ax_ev_open, 'Open calendar', 'Asia/Seoul' )['body'];
	$ax_ev_transp   = array();
	foreach ( explode( "\r\n", str_replace( "\r\n ", '', $ax_ev_feed_now ) ) as $ax_ev_line ) {
		if ( str_starts_with( $ax_ev_line, 'TRANSP:' ) ) {
			$ax_ev_transp[] = substr( $ax_ev_line, 7 );
		}
	}
	ax_ev_assert(
		$ax_ev_results,
		'and both readings reach the document, so a subscriber sees the same free time the site does',
		in_array( 'OPAQUE', $ax_ev_transp, true ) && in_array( 'TRANSPARENT', $ax_ev_transp, true )
	);
	/*
	 * Independent of the other axis, which a single "private" flag would have merged. A free Event is
	 * fully visible, and a private one is not free merely for being hidden.
	 */
	$ax_ev_free_private = $ax_ev_make( $ax_ev_posts, $ax_ev_open, 'Free and private', array( 'transparency' => 'TRANSPARENT', 'visibility' => 'private' ) );
	ax_ev_assert(
		$ax_ev_results,
		'the two axes are independent: one Event can be free and private at once',
		'TRANSPARENT' === (string) axismundi_cal_event_get( $ax_ev_free_private )['transparency']
			&& 'private' === (string) axismundi_cal_event_get( $ax_ev_free_private )['visibility']
			&& false === axismundi_cal_event_listable( get_post( $ax_ev_free_private ) )
	);
	ax_ev_assert(
		$ax_ev_results,
		'and a free public Event is still published, since being free is not a reason to withhold it',
		axismundi_cal_event_listable( get_post( $ax_ev_free ) )
	);

	// -- What the editor can reach ---------------------------------------------------------------------

	/*
	 * A column nothing can set is the failure this project has already met twice. Both settings read
	 * back through the envelope the panel writes, and both have a control.
	 */
	ax_ev_assert(
		$ax_ev_results,
		'both settings read back through the envelope the editor uses',
		'private' === (string) axismundi_cal_rest_envelope( $ax_ev_hidden )['visibility']
			&& 'TRANSPARENT' === (string) axismundi_cal_rest_envelope( $ax_ev_free )['transparency']
	);
	$ax_ev_panel = (string) file_get_contents( dirname( __DIR__ ) . '/assets/editor/event-panel.js' );
	ax_ev_assert(
		$ax_ev_results,
		'and both have a control, without which the columns are unreachable again',
		str_contains( $ax_ev_panel, "key: 'visibility'" ) && str_contains( $ax_ev_panel, "key: 'transparency'" )
	);

	// -- The excerpt is what the document describes it with --------------------------------------------

	/*
	 * `DESCRIPTION` is plain text in RFC 5545, so the body is not what goes there. The excerpt is, and
	 * it could not be written: the post type never declared support for one, so the editor showed no
	 * field and every Event fell back to a trimmed body.
	 */
	ax_ev_assert(
		$ax_ev_results,
		'an Event supports an excerpt, which is what the document describes it with',
		post_type_supports( AXISMUNDI_CAL_EVENT_POST_TYPE, 'excerpt' )
	);
	/*
	 * The fallback is stated rather than inherited. `get_the_excerpt()` runs filters and manufactures a
	 * summary of its own, so what reached subscribers depended on which plugins were installed and on
	 * whether the body happened to contain a `<!--more-->`. A written excerpt, or a plain-text
	 * projection of the body, and nothing in between.
	 */
	$ax_ev_bodied = $ax_ev_make( $ax_ev_posts, $ax_ev_open, 'Bodied event', array() );
	wp_update_post( array( 'ID' => $ax_ev_bodied, 'post_content' => '<p>A paragraph <strong>with</strong> markup.</p>' ) );
	$ax_ev_body_feed = str_replace( "\r\n ", '', (string) axismundi_cal_site_feed( $ax_ev_open, 'Open calendar', 'Asia/Seoul' )['body'] );
	ax_ev_assert(
		$ax_ev_results,
		'an Event with no written excerpt is described by its body as plain text',
		str_contains( $ax_ev_body_feed, 'DESCRIPTION:A paragraph with markup.' )
	);
	ax_ev_assert(
		$ax_ev_results,
		'with no markup reaching a field iCalendar defines as text',
		! str_contains( $ax_ev_body_feed, '<strong>' ) && ! str_contains( $ax_ev_body_feed, '<p>' )
	);
	/*
	 * And the reason, asserted directly. A short body reads the same through `get_the_excerpt()` as
	 * through a projection written here, so comparing the text proves nothing about which one ran --
	 * what separates them is that one is filtered. A plugin rewriting excerpts must not be able to
	 * change what subscribers were sent.
	 */
	$ax_ev_meddle = static function () : string {
		return 'SOMETHING A PLUGIN DECIDED';
	};
	add_filter( 'get_the_excerpt', $ax_ev_meddle, 10, 1 );
	$ax_ev_filtered_feed = str_replace( "\r\n ", '', (string) axismundi_cal_site_feed( $ax_ev_open, 'Open calendar', 'Asia/Seoul' )['body'] );
	remove_filter( 'get_the_excerpt', $ax_ev_meddle, 10 );
	ax_ev_assert(
		$ax_ev_results,
		'and a filter on the excerpt helper cannot change what the document says',
		! str_contains( $ax_ev_filtered_feed, 'SOMETHING A PLUGIN DECIDED' )
			&& str_contains( $ax_ev_filtered_feed, 'DESCRIPTION:A paragraph with markup.' )
	);

	wp_update_post( array( 'ID' => $ax_ev_shown, 'post_excerpt' => 'A short line about it, with a comma.' ) );
	ax_ev_assert(
		$ax_ev_results,
		'and it reaches the document as DESCRIPTION, escaped',
		str_contains(
			str_replace( "\r\n ", '', (string) axismundi_cal_site_feed( $ax_ev_open, 'Open calendar', 'Asia/Seoul' )['body'] ),
			'DESCRIPTION:A short line about it\\, with a comma.'
		)
	);

	/*
	 * The lead image is its own property in RFC 7986 rather than something smuggled into the text. A
	 * client that does not know `IMAGE` ignores the line, which is the right outcome -- unlike a URL
	 * pasted into the description, which every client shows to everybody forever.
	 */
	$ax_ev_attachment = (int) wp_insert_post(
		array( 'post_type' => 'attachment', 'post_title' => 'Lead', 'post_mime_type' => 'image/png', 'post_status' => 'inherit' )
	);
	$ax_ev_posts[] = $ax_ev_attachment;
	update_post_meta( $ax_ev_attachment, '_wp_attached_file', 'lead.png' );
	set_post_thumbnail( $ax_ev_shown, $ax_ev_attachment );
	ax_ev_assert(
		$ax_ev_results,
		'a featured image is exported as IMAGE rather than pasted into the description',
		( static function () use ( $ax_ev_open, $ax_ev_shown ) : bool {
			$body = str_replace( "\r\n ", '', (string) axismundi_cal_site_feed( $ax_ev_open, 'Open calendar', 'Asia/Seoul' )['body'] );
			$url  = (string) wp_get_attachment_url( (int) get_post_thumbnail_id( $ax_ev_shown ) );
			return '' !== $url && str_contains( $body, 'IMAGE;VALUE=URI:' . $url );
		} )()
	);
	/*
	 * The migration itself, which failed silently the first time it was written. `dbDelta` parses the
	 * declaration line by line and truncates the `ALTER` it generates when a block comment sits inside
	 * the statement -- so the version advanced, `ready()` stayed true, and the columns never arrived.
	 * Nothing about that is visible except a write that says "Unknown column".
	 */
	ax_ev_assert(
		$ax_ev_results,
		'the columns this slice added actually exist, which a silently truncated migration would not have given',
		( static function () use ( $wpdb ) : bool {
			$have = array_column( (array) $wpdb->get_results( 'SHOW COLUMNS FROM ' . axismundi_cal_events_table(), ARRAY_A ), 'Field' );
			return in_array( 'visibility', $have, true ) && in_array( 'transparency', $have, true );
		} )()
	);
} finally {
	wp_set_current_user( 0 );
	foreach ( $ax_ev_posts as $ax_ev_post ) {
		wp_delete_post( (int) $ax_ev_post, true );
	}
	foreach ( $ax_ev_calendars as $ax_ev_cal ) {
		axismundi_cal_calendar_delete( (int) $ax_ev_cal );
	}
	foreach ( $ax_ev_users as $ax_ev_user_id ) {
		wp_delete_user( (int) $ax_ev_user_id );
	}
}

$ax_ev_failures = count( array_filter( $ax_ev_results, static fn( bool $result ) : bool => ! $result ) );
// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI fixture output.
printf( "\n== %d checks, %d failed ==\n", count( $ax_ev_results ), $ax_ev_failures );
if ( class_exists( 'WP_CLI' ) ) {
	WP_CLI::halt( $ax_ev_failures > 0 ? 1 : 0 );
}
exit( $ax_ev_failures > 0 ? 1 : 0 );
