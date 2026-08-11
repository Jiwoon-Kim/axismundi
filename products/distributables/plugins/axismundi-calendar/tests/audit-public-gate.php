<?php
/**
 * The public gate (dev-only; dist-excluded).
 *
 * One property, asserted on every surface that can leak: an Event is public because the Calendar it
 * lives on is public, never because the post was published. Those two facts were the same thing
 * until Calendars could be shared, and every surface written before that asked the wrong one.
 *
 * The surfaces are audited separately rather than through one helper, because they fail
 * independently -- the grid, the site feed, the Calendar feed, the Calendar page, the Event page and
 * the federation projection each decide for themselves, and a gate closed on five of six is a gate.
 *
 * The opposite direction is asserted too. A Calendar that predates access control was public, and an
 * upgrade that silently unpublishes every existing subscription URL is a worse failure than the one
 * being fixed, so grandfathering is a property under test rather than a migration side effect.
 *
 * @package AxismundiCalendar
 */

defined( 'ABSPATH' ) || exit( 1 );

global $wpdb;
$ax_pg_results   = array();
$ax_pg_calendars = array();
$ax_pg_posts     = array();

/** @param bool[] $results Results. */
function ax_pg_assert( array &$results, string $label, bool $condition ) : void {
	$results[] = $condition;
	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI fixture output.
	printf( "[%s] %s\n", $condition ? 'PASS' : 'FAIL', $label );
}

/**
 * An Event with a schedule on one Calendar.
 *
 * Drafted first and published after the schedule exists, because the publish guard holds back an
 * Event that has no times -- publishing one would be publishing an Event that does not say when.
 */
function ax_pg_event( array &$posts, int $calendar_id, string $title ) : int {
	$post_id = (int) wp_insert_post(
		array(
			'post_type'   => AXISMUNDI_CAL_EVENT_POST_TYPE,
			'post_status' => 'draft',
			'post_title'  => $title,
		)
	);
	$posts[] = $post_id;
	axismundi_cal_event_save(
		$post_id,
		array(
			'calendar_id' => $calendar_id,
			'timezone'    => 'Asia/Seoul',
			'starts_at'   => '2026-09-10 19:00:00',
			'ends_at'     => '2026-09-10 21:00:00',
		)
	);
	$GLOBALS['axismundi_cal_rest_write'] = true;
	wp_update_post( array( 'ID' => $post_id, 'post_status' => 'publish' ) );
	$GLOBALS['axismundi_cal_rest_write'] = false;
	return $post_id;
}

try {
	$ax_pg_authority = axismundi_cal_default_local_authority();
	$ax_pg_private = (int) axismundi_cal_calendar_save(
		array( 'name' => 'Gate private', 'slug' => 'ax-pg-private', 'timezone' => 'Asia/Seoul', 'owner_actor_uri' => $ax_pg_authority )
	);
	$ax_pg_public = (int) axismundi_cal_calendar_save(
		array( 'name' => 'Gate public', 'slug' => 'ax-pg-public', 'timezone' => 'Asia/Seoul', 'owner_actor_uri' => $ax_pg_authority )
	);
	$ax_pg_calendars[] = $ax_pg_private;
	$ax_pg_calendars[] = $ax_pg_public;
	axismundi_cal_acl_grant( $ax_pg_public, '', 'reader', 'public' );

	// -- A new Calendar is private ---------------------------------------------------------------

	/*
	 * The default, and the reason the grandfather migration below has to exist. Anything created from
	 * now on is private until somebody says otherwise, so the two Calendars above differ only in that
	 * one was published on purpose.
	 */
	ax_pg_assert( $ax_pg_results, 'a Calendar created today is not public until somebody says so', false === axismundi_cal_is_publicly_readable( $ax_pg_private ) );
	ax_pg_assert( $ax_pg_results, 'and the one that was published is', true === axismundi_cal_is_publicly_readable( $ax_pg_public ) );

	$ax_pg_hidden = ax_pg_event( $ax_pg_posts, $ax_pg_private, 'Gate hidden event' );
	$ax_pg_shown  = ax_pg_event( $ax_pg_posts, $ax_pg_public, 'Gate shown event' );

	// -- Listing ---------------------------------------------------------------------------------

	ax_pg_assert( $ax_pg_results, 'an Event on a private Calendar is not listable', false === axismundi_cal_event_listable( get_post( $ax_pg_hidden ) ) );
	ax_pg_assert( $ax_pg_results, 'though the post itself is published, which is what the old check asked', 'publish' === get_post_status( $ax_pg_hidden ) );
	ax_pg_assert( $ax_pg_results, 'and an Event on a public Calendar is listable', true === axismundi_cal_event_listable( get_post( $ax_pg_shown ) ) );

	// -- The grid ---------------------------------------------------------------------------------

	$ax_pg_range = axismundi_cal_occurrences_in_range( '2026-09-01 00:00:00', '2026-10-01 00:00:00' );
	$ax_pg_ids   = array_map( static fn( array $o ) : int => (int) $o['post_id'], $ax_pg_range );
	ax_pg_assert( $ax_pg_results, 'the site-wide grid does not show it', ! in_array( $ax_pg_hidden, $ax_pg_ids, true ) );
	ax_pg_assert( $ax_pg_results, 'while it does show the public one, so the gate is not simply refusing everything', in_array( $ax_pg_shown, $ax_pg_ids, true ) );

	/*
	 * Asked for the private Calendar by id, which is what an embedded block with its slug would do.
	 * Naming a Calendar is not permission to read it.
	 */
	ax_pg_assert(
		$ax_pg_results,
		'and asking the grid for that Calendar directly still returns nothing',
		array() === axismundi_cal_occurrences_in_range( '2026-09-01 00:00:00', '2026-10-01 00:00:00', 100, $ax_pg_private )
	);

	// -- Feeds -------------------------------------------------------------------------------------

	$ax_pg_site = axismundi_cal_site_feed();
	ax_pg_assert( $ax_pg_results, "the site feed omits the private Calendar's Event", is_array( $ax_pg_site ) && ! str_contains( (string) $ax_pg_site['body'], 'Gate hidden event' ) );
	ax_pg_assert( $ax_pg_results, 'and carries the public one', is_array( $ax_pg_site ) && str_contains( (string) $ax_pg_site['body'], 'Gate shown event' ) );

	/*
	 * The Calendar-scoped feed is gated where the request is served rather than where the document is
	 * built, so the predicate that gate calls is what is asserted here. `axismundi_cal_site_feed()`
	 * with a Calendar id will happily build the document -- it is the caller's job not to ask.
	 */
	$ax_pg_calendar_row = axismundi_cal_calendar_by_slug( 'ax-pg-private' );
	ax_pg_assert(
		$ax_pg_results,
		'a subscription request for the private Calendar is refused by the same predicate the page uses',
		is_array( $ax_pg_calendar_row ) && false === axismundi_cal_is_publicly_readable( (int) $ax_pg_calendar_row['id'] )
	);

	// -- Federation ---------------------------------------------------------------------------------

	ax_pg_assert( $ax_pg_results, 'the Event is withheld from federation', false === axismundi_cal_event_visible( get_post( $ax_pg_hidden ) ) );
	ax_pg_assert( $ax_pg_results, 'and the public one is not', true === axismundi_cal_event_visible( get_post( $ax_pg_shown ) ) );

	// -- The Event's own page ------------------------------------------------------------------------

	/*
	 * The route that defeats every other gate if it is left open: the permalink of a published post.
	 * The guard is asserted through the action it is registered on, since the function exits.
	 */
	ax_pg_assert( $ax_pg_results, 'the single Event page is guarded on template_redirect', false !== has_action( 'template_redirect', 'axismundi_cal_guard_event_page' ) );
	ax_pg_assert( $ax_pg_results, 'and the Calendar page with it', false !== has_action( 'template_redirect', 'axismundi_cal_serve_calendar_page' ) );

	// -- Who may still see it -------------------------------------------------------------------------

	/*
	 * Withheld from the public is not withheld from everybody. A gate nobody can see behind means an
	 * owner cannot open their own Calendar, which would make the private state useless rather than
	 * private.
	 */
	$ax_pg_reader = 'https://example.org/actors/ax-pg-reader';
	axismundi_cal_acl_grant( $ax_pg_private, $ax_pg_reader, 'reader' );
	ax_pg_assert( $ax_pg_results, 'someone granted a reader rule may read the private Calendar', true === axismundi_cal_can_read( $ax_pg_private, $ax_pg_reader ) );
	ax_pg_assert( $ax_pg_results, 'without that making it public to everyone else', false === axismundi_cal_is_publicly_readable( $ax_pg_private ) );
	ax_pg_assert( $ax_pg_results, 'and it still stays off the public grid, because the grid is nobody in particular', false === axismundi_cal_event_listable( get_post( $ax_pg_hidden ) ) );

	// -- free/busy is not readable ---------------------------------------------------------------------

	$ax_pg_busy = (int) axismundi_cal_calendar_save(
		array( 'name' => 'Gate busy', 'slug' => 'ax-pg-busy', 'timezone' => 'Asia/Seoul', 'owner_actor_uri' => $ax_pg_authority )
	);
	$ax_pg_calendars[] = $ax_pg_busy;
	axismundi_cal_acl_grant( $ax_pg_busy, '', 'freeBusyReader', 'public' );
	$ax_pg_busy_event = ax_pg_event( $ax_pg_posts, $ax_pg_busy, 'Gate busy event' );
	ax_pg_assert( $ax_pg_results, 'a publicly free/busy Calendar is not publicly readable', false === axismundi_cal_is_publicly_readable( $ax_pg_busy ) );
	ax_pg_assert( $ax_pg_results, 'so its Events do not appear with their titles', false === axismundi_cal_event_listable( get_post( $ax_pg_busy_event ) ) );
	ax_pg_assert( $ax_pg_results, 'and it is not federated either', false === axismundi_cal_event_visible( get_post( $ax_pg_busy_event ) ) );
	ax_pg_assert( $ax_pg_results, 'while the free/busy question itself still answers yes', true === axismundi_cal_is_publicly_freebusy( $ax_pg_busy ) );

	// -- Grandfathering -----------------------------------------------------------------------------

	/*
	 * What the upgrade does to Calendars that existed before any of this. They were public in every
	 * observable way, and people are subscribed to their URLs, so the migration states what was
	 * already true rather than changing it.
	 */
	$ax_pg_legacy = (int) axismundi_cal_calendar_save(
		array( 'name' => 'Gate legacy', 'slug' => 'ax-pg-legacy', 'timezone' => 'Asia/Seoul', 'owner_actor_uri' => $ax_pg_authority )
	);
	$ax_pg_calendars[] = $ax_pg_legacy;
	ax_pg_assert( $ax_pg_results, 'before the migration a pre-ACL Calendar reads as private', false === axismundi_cal_is_publicly_readable( $ax_pg_legacy ) );

	axismundi_cal_grandfather_public_calendars( '10' );
	ax_pg_assert( $ax_pg_results, 'upgrading from a version without the gate preserves its public URL', true === axismundi_cal_is_publicly_readable( $ax_pg_legacy ) );

	/*
	 * The half that keeps this from being a blanket "make everything public": on a fresh install there
	 * is nothing to preserve, and re-running at the current version must not re-open a Calendar
	 * somebody has since made private.
	 */
	axismundi_cal_acl_revoke( $ax_pg_legacy, '', 'public' );
	axismundi_cal_grandfather_public_calendars( '' );
	ax_pg_assert( $ax_pg_results, 'a fresh install grandfathers nothing', false === axismundi_cal_is_publicly_readable( $ax_pg_legacy ) );
	axismundi_cal_grandfather_public_calendars( (string) AXISMUNDI_CAL_DB_VERSION );
	ax_pg_assert( $ax_pg_results, 'and running it again at the current version does not re-open what was closed', false === axismundi_cal_is_publicly_readable( $ax_pg_legacy ) );
} finally {
	foreach ( $ax_pg_posts as $ax_pg_post ) {
		wp_delete_post( (int) $ax_pg_post, true );
	}
	foreach ( $ax_pg_calendars as $ax_pg_calendar ) {
		axismundi_cal_calendar_delete( (int) $ax_pg_calendar );
	}
}

$ax_pg_failures = count( array_filter( $ax_pg_results, static fn( bool $result ) : bool => ! $result ) );
// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI fixture output.
printf( "\n== %d checks, %d failed ==\n", count( $ax_pg_results ), $ax_pg_failures );
if ( class_exists( 'WP_CLI' ) ) {
	WP_CLI::halt( $ax_pg_failures > 0 ? 1 : 0 );
}
exit( $ax_pg_failures > 0 ? 1 : 0 );
