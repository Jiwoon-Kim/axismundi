<?php
/**
 * Calendars this plugin maintains itself (dev-only; dist-excluded).
 *
 * Two rules are worth a check each, and both fail silently.
 *
 * The name is a translation, so it must not be stored. A string written into the row at installation
 * looks perfect on the site that installed it and is frozen in that language forever -- there is no
 * error, no missing string, and nothing to notice until somebody changes the site language and the
 * calendar keeps its old name while everything around it moves.
 *
 * Provisioning happens once. A calendar recreated on every upgrade is one an administrator cannot
 * get rid of, and they would only find that out on the second upgrade.
 *
 * @package AxismundiCalendar
 */

defined( 'ABSPATH' ) || exit( 1 );

global $wpdb;
$ax_mc_results = array();
$ax_mc_enabled_option = get_option( AXISMUNDI_CAL_MANAGED_ENABLED_OPTION, array() );
$ax_mc_viewer  = '';

/** @param bool[] $results Results. */
function ax_mc_assert( array &$results, string $label, bool $condition ) : void {
	$results[] = $condition;
	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI fixture output.
	printf( "[%s] %s\n", $condition ? 'PASS' : 'FAIL', $label );
}

try {
	// -- Provisioning ----------------------------------------------------------------------------------

	$ax_mc_calendar = axismundi_cal_managed_calendar_get( 'moon-phases' );
	ax_mc_assert( $ax_mc_results, 'the moon phase calendar exists without anybody having created it', is_array( $ax_mc_calendar ) );
	ax_mc_assert(
		$ax_mc_results,
		'as a maintained astronomy calendar rather than an ordinary one',
		is_array( $ax_mc_calendar )
			&& 'system' === (string) $ax_mc_calendar['kind']
			&& 'astronomy' === axismundi_cal_system_provider( $ax_mc_calendar )
	);
	/*
	 * UTC, because everything on one of these is an instant. A zone here would be a claim about
	 * nothing: a full moon does not happen in Seoul.
	 */
	ax_mc_assert(
		$ax_mc_results,
		'in UTC, since a moment does not happen in a timezone',
		is_array( $ax_mc_calendar ) && 'UTC' === (string) $ax_mc_calendar['timezone']
	);
	/*
	 * Asserted over the window rather than over the calendar year. The two stopped being the same
	 * thing when the span became a rolling one: in December the current year is nine months behind the
	 * window's trailing edge, so "this year holds a full set" is false by design and says nothing about
	 * whether provisioning worked.
	 */
	list( $ax_mc_from, $ax_mc_to ) = axismundi_cal_moon_phase_window();
	ax_mc_assert(
		$ax_mc_results,
		'and it arrives filled across the whole window, because an empty one reads as a broken one',
		is_array( $ax_mc_calendar ) && count( axismundi_cal_system_items_in_range( (int) $ax_mc_calendar['id'], $ax_mc_from, $ax_mc_to ) ) > 100
	);
	$ax_mc_viewer = 'https://example.test/actors/moon-phase-viewer-' . wp_rand( 1000, 9999 );
	/*
	 * Counted from what is switched on rather than fixed at one, because a second generator arriving is
	 * exactly the thing that would otherwise break this without saying anything about the rule.
	 */
	$ax_mc_offered = count( array_filter( array_keys( AXISMUNDI_CAL_MANAGED_CALENDARS ), 'axismundi_cal_managed_calendar_enabled' ) );
	ax_mc_assert(
		$ax_mc_results,
		'a viewer receives every managed calendar in their workspace list without creating any',
		is_array( $ax_mc_calendar )
			&& $ax_mc_offered === axismundi_cal_add_managed_calendars_to_list( $ax_mc_viewer )
			&& is_array( axismundi_cal_list_entry( (int) $ax_mc_calendar['id'], $ax_mc_viewer ) )
	);
	$ax_mc_entry = is_array( $ax_mc_calendar ) ? axismundi_cal_list_entry( (int) $ax_mc_calendar['id'], $ax_mc_viewer ) : null;
	ax_mc_assert(
		$ax_mc_results,
		'and it is selected, so the first workspace grid actually draws its phases',
		is_array( $ax_mc_entry ) && 1 === (int) $ax_mc_entry['selected']
	);
	if ( is_array( $ax_mc_entry ) ) {
		axismundi_cal_list_set( (int) $ax_mc_calendar['id'], $ax_mc_viewer, 'reader', array( 'hidden' => true, 'selected' => false ) );
		ax_mc_assert(
			$ax_mc_results,
			'a hidden managed calendar stays hidden rather than being added again on the next workspace visit',
			0 === axismundi_cal_add_managed_calendars_to_list( $ax_mc_viewer )
				&& 1 === (int) axismundi_cal_list_entry( (int) $ax_mc_calendar['id'], $ax_mc_viewer )['hidden']
		);
	}

	/*
	 * Provisioning and the daily job must materialize the same set. Two expressions of "which phases"
	 * drift, and the drift shows up as a calendar that is complete on the day it is added and thins out
	 * afterwards -- or the reverse, which is worse because it looks fine.
	 */
	ax_mc_assert(
		$ax_mc_results,
		'and holds exactly what the scheduled maintenance would leave, so a new calendar matches an old one',
		is_array( $ax_mc_calendar )
			&& 0 === (int) axismundi_cal_maintain_moon_phases( (int) $ax_mc_calendar['id'] )['deleted']
	);

	// -- The name is a translation, not a row --------------------------------------------------------

	ax_mc_assert(
		$ax_mc_results,
		'no name is stored, so none can be frozen in the language that happened to be active at install',
		is_array( $ax_mc_calendar ) && null === $ax_mc_calendar['name']
	);
	ax_mc_assert(
		$ax_mc_results,
		'while it still reads as a name everywhere one is wanted',
		is_array( $ax_mc_calendar ) && '' !== axismundi_cal_calendar_display_name( $ax_mc_calendar )
	);

	/*
	 * That the name actually goes through gettext, rather than being a constant that happens to be in
	 * English. Asserted by translating it: without the filter this passes on a site whose language was
	 * never changed, which is every site a developer tests on.
	 */
	$ax_mc_translate = static function ( string $translated, string $original, string $domain ) : string {
		return 'axismundi-calendar' === $domain && 'Moon phases' === $original ? '달의 위상' : $translated;
	};
	add_filter( 'gettext', $ax_mc_translate, 10, 3 );
	$ax_mc_translated = axismundi_cal_calendar_display_name( (array) $ax_mc_calendar );
	remove_filter( 'gettext', $ax_mc_translate, 10 );
	ax_mc_assert( $ax_mc_results, 'and the name is produced through the translation catalog, not returned from the row', '달의 위상' === $ax_mc_translated );

	/*
	 * A typed name outranks the generated one, exactly as an authored entry title outranks its phase
	 * key. Somebody renaming this is saying what the site calls it, and no catalog string should
	 * overrule that.
	 */
	$ax_mc_renamed = axismundi_cal_calendar_save( array( 'name' => '음력 참고' ), (int) $ax_mc_calendar['id'] );
	ax_mc_assert(
		$ax_mc_results,
		'an administrator may name one, and then that is what the site calls it',
		! is_wp_error( $ax_mc_renamed )
			&& '음력 참고' === axismundi_cal_calendar_display_name( (array) axismundi_cal_calendar_get( (int) $ax_mc_calendar['id'] ) )
	);
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- restoring the row this file borrowed.
	$wpdb->update( axismundi_cal_calendars_table(), array( 'name' => null ), array( 'id' => (int) $ax_mc_calendar['id'] ) );
	ax_mc_assert(
		$ax_mc_results,
		'and clearing it again returns the generated name rather than an empty heading',
		'' !== axismundi_cal_calendar_display_name( (array) axismundi_cal_calendar_get( (int) $ax_mc_calendar['id'] ) )
	);

	// -- The document is rendered once, not per request ------------------------------------------------

	/*
	 * A public subscription is polled by every subscriber on their own schedule and forever. Rendering
	 * per request means booting WordPress, querying a few hundred rows and re-serializing a document
	 * identical to the one served a second earlier -- the conditional GET saves the bytes and none of
	 * that work. What a computed feed says changes only when the window advances, at a moment the
	 * scheduler already computes, so it is rendered there.
	 */
	$ax_mc_doc = axismundi_cal_managed_ics( (array) $ax_mc_calendar );
	ax_mc_assert(
		$ax_mc_results,
		'a computed feed has a stored document, with its ETag worked out once rather than per request',
		isset( $ax_mc_doc['body'], $ax_mc_doc['etag'] ) && str_starts_with( (string) $ax_mc_doc['body'], 'BEGIN:VCALENDAR' )
	);

	/*
	 * That it is actually served from the store rather than rebuilt and coincidentally equal. Poisoning
	 * the stored body is the only way to tell those apart: a reader that re-renders would discard it.
	 */
	$ax_mc_poisoned          = $ax_mc_doc;
	$ax_mc_poisoned['body']  = "BEGIN:VCALENDAR

X-AX-POISON:1

END:VCALENDAR";
	update_option( axismundi_cal_managed_ics_option( 'moon-phases' ), $ax_mc_poisoned, false );
	ax_mc_assert(
		$ax_mc_results,
		'and the stored one is what a request gets, rather than a fresh render each time',
		str_contains( (string) axismundi_cal_managed_ics( (array) $ax_mc_calendar )['body'], 'X-AX-POISON' )
	);

	/*
	 * The trap a cached document sets. The dates are not the only thing in it: the name is translated
	 * at build time, so a site that changes language keeps a correct set of instants under the wrong
	 * heading -- and nothing about that goes through maintenance. Hooking every path that could touch
	 * the name is a list that ends up one entry short, so the document records what it was built from.
	 */
	$ax_mc_stale = static function ( string $translated, string $original, string $domain ) : string {
		return 'axismundi-calendar' === $domain && 'Moon phases' === $original ? '달의 위상' : $translated;
	};
	add_filter( 'gettext', $ax_mc_stale, 10, 3 );
	$ax_mc_relocalized = axismundi_cal_managed_ics( (array) $ax_mc_calendar );
	remove_filter( 'gettext', $ax_mc_stale, 10 );
	ax_mc_assert(
		$ax_mc_results,
		'so a document built under a different name is rebuilt rather than served stale',
		! str_contains( (string) $ax_mc_relocalized['body'], 'X-AX-POISON' )
			&& str_contains( (string) $ax_mc_relocalized['body'], 'X-WR-CALNAME:달의 위상' )
	);

	// -- The switch is the record of intent ------------------------------------------------------------

	/*
	 * One mechanism, not two. An earlier version recorded "has this ever been provisioned" in an option
	 * so that deleting a calendar would stick; a switch says the same thing directly, in a place an
	 * administrator can see and change. Two mechanisms answering one question is how they come to
	 * disagree, and the disagreement would read as a calendar that will not go away.
	 */
	ax_mc_assert( $ax_mc_results, 'moon phases are produced by default, since a calendar plugin has no reason to ask', axismundi_cal_managed_calendar_enabled( 'moon-phases' ) );

	$ax_mc_off = axismundi_cal_set_managed_calendar_enabled( 'moon-phases', false );
	ax_mc_assert( $ax_mc_results, 'switching one off is accepted', true === $ax_mc_off );
	/*
	 * Removed rather than hidden. A calendar left behind with its maintenance stopped is the worst
	 * available state: it goes on being subscribable while quietly ceasing to be true at its far edge,
	 * and nothing about it looks wrong.
	 */
	ax_mc_assert( $ax_mc_results, 'and takes its calendar with it rather than leaving one nothing maintains', null === axismundi_cal_managed_calendar_get( 'moon-phases' ) );
	/*
	 * And its rendered document, which would otherwise outlive the calendar it describes -- a stored
	 * body is exactly the thing that could go on being served after the rows behind it were gone.
	 */
	ax_mc_assert( $ax_mc_results, 'along with the document it had rendered, which nothing would refresh', false === get_option( axismundi_cal_managed_ics_option( 'moon-phases' ), false ) );
	ax_mc_assert( $ax_mc_results, 'so an upgrade does not bring it back, because the switch still says no', ( static function () : bool {
		axismundi_cal_sync_managed_calendars();
		return null === axismundi_cal_managed_calendar_get( 'moon-phases' );
	} )() );
	ax_mc_assert(
		$ax_mc_results,
		'and nobody is offered it in their workspace while it is off',
		0 === axismundi_cal_add_managed_calendars_to_list( 'https://example.test/actors/ax-mc-offswitch' )
	);

	$ax_mc_on = axismundi_cal_set_managed_calendar_enabled( 'moon-phases', true );
	$ax_mc_back = axismundi_cal_managed_calendar_get( 'moon-phases' );
	ax_mc_assert( $ax_mc_results, 'switching it back on recreates it', true === $ax_mc_on && is_array( $ax_mc_back ) );
	/*
	 * Recomputed rather than recovered, which is what makes removing it safe: the rows were a
	 * materialized view of arithmetic, so nothing was lost that the same arithmetic cannot restate.
	 */
	ax_mc_assert(
		$ax_mc_results,
		'with the same dates worked out again, so nothing was lost by removing it',
		is_array( $ax_mc_back ) && count( axismundi_cal_system_items_in_range( (int) $ax_mc_back['id'], $ax_mc_from, $ax_mc_to ) ) > 100
	);

	/*
	 * A managed dataset has a fixed address, so the answer after switching one off can say which of two
	 * things happened: `404` means there is nothing here and never was, `410` means this site published
	 * it and has stopped. A typo in a slug and a withdrawn dataset are different facts, and only one of
	 * them is worth a subscriber acting on.
	 */
	ax_mc_assert(
		$ax_mc_results,
		'a managed slug is recognised whether or not its calendar exists, which is what lets a withdrawal be reported',
		'moon-phases' === axismundi_cal_managed_key_for_slug( 'moon-phases' )
			&& '' === axismundi_cal_managed_key_for_slug( 'not-a-managed-calendar' )
	);

	// -- What cannot be produced yet -------------------------------------------------------------------

	/*
	 * Declared before anything can compute them, and listed on the screen as unavailable. A dataset that
	 * is simply absent reads as one this plugin has no opinion about; shown and refused says what is
	 * coming and why it is not here.
	 */
	/*
	 * Equinoxes and solstices moved from declared to produced when their generator arrived: the registry
	 * entry existed first, so turning it on was a one-line change here rather than a new mechanism.
	 */
	ax_mc_assert(
		$ax_mc_results,
		'equinoxes and solstices are produced now that something computes them',
		axismundi_cal_managed_calendar_available( 'equinox-solstice' )
			&& axismundi_cal_managed_calendar_enabled( 'equinox-solstice' )
			&& is_array( axismundi_cal_managed_calendar_get( 'equinox-solstice' ) )
	);
	ax_mc_assert(
		$ax_mc_results,
		'and are a separate calendar from the phases, since a person subscribes to one without the other',
		(string) AXISMUNDI_CAL_MANAGED_CALENDARS['equinox-solstice']['slug'] !== (string) AXISMUNDI_CAL_MANAGED_CALENDARS['moon-phases']['slug']
	);

	foreach ( array( 'lunar-eclipses' ) as $ax_mc_planned ) {
		ax_mc_assert(
			$ax_mc_results,
			sprintf( '%s is declared but has no generator yet', $ax_mc_planned ),
			isset( AXISMUNDI_CAL_MANAGED_CALENDARS[ $ax_mc_planned ] ) && ! axismundi_cal_managed_calendar_available( $ax_mc_planned )
		);
		ax_mc_assert(
			$ax_mc_results,
			sprintf( 'so %s cannot be switched on, which would leave an empty calendar that looks broken', $ax_mc_planned ),
			is_wp_error( axismundi_cal_set_managed_calendar_enabled( $ax_mc_planned, true ) )
				&& null === axismundi_cal_managed_calendar_get( $ax_mc_planned )
		);
		ax_mc_assert(
			$ax_mc_results,
			sprintf( 'and %s reads as off however the option is written', $ax_mc_planned ),
			! axismundi_cal_managed_calendar_enabled( $ax_mc_planned )
		);
	}
	/*
	 * A browser does not submit a disabled checkbox, so a screen that read absence as "unticked" would
	 * switch off anything unavailable every time the form was saved. Harmless while they are already
	 * off, and exactly wrong on the day one becomes available and is on by default.
	 */
	update_option( AXISMUNDI_CAL_MANAGED_ENABLED_OPTION, array( 'lunar-eclipses' => true ), false );
	ax_mc_assert(
		$ax_mc_results,
		'an option claiming an unavailable dataset is on is still read as off',
		! axismundi_cal_managed_calendar_enabled( 'lunar-eclipses' )
	);

	// -- What cannot be created by hand ---	// -- What cannot be created by hand ----------------------------------------------------------------

	ax_mc_assert(
		$ax_mc_results,
		'astronomy is refused on the creation form, because the plugin already maintains those',
		'' !== axismundi_cal_system_provider_unavailable_reason( 'astronomy' )
	);
	foreach ( array( 'religious', 'civic', 'academic' ) as $ax_mc_provider ) {
		ax_mc_assert(
			$ax_mc_results,
			sprintf( 'and %s is refused too, since nothing fills it yet', $ax_mc_provider ),
			'' !== axismundi_cal_system_provider_unavailable_reason( $ax_mc_provider )
		);
	}
	ax_mc_assert(
		$ax_mc_results,
		'while a holiday calendar is still something a person creates, which is the one kind that needs deciding',
		'' === axismundi_cal_system_provider_unavailable_reason( 'holiday' )
	);
	/*
	 * The reasons differ, and the screen says so. Greying four rows out with one sentence would tell
	 * somebody that astronomy is unfinished, when it is the opposite: it is already done for them.
	 */
	ax_mc_assert(
		$ax_mc_results,
		'and the two refusals say different things, since one is unfinished and the other is already done',
		axismundi_cal_system_provider_unavailable_reason( 'astronomy' ) !== axismundi_cal_system_provider_unavailable_reason( 'civic' )
	);

	// -- The key is not transferable -------------------------------------------------------------------

	$ax_mc_plain = axismundi_cal_calendar_save(
		array( 'name' => 'Not managed', 'slug' => 'ax-mc-plain-' . wp_rand( 1000, 9999 ), 'timezone' => 'UTC', 'kind' => 'system', 'source' => 'manual', 'system_provider' => 'holiday', 'provider_config' => array( 'region' => 'KR', 'source_locale' => 'ko-KR' ) )
	);
	ax_mc_assert( $ax_mc_results, 'an ordinary maintained calendar still needs a name', is_wp_error( axismundi_cal_calendar_save( array( 'slug' => 'ax-mc-nameless', 'timezone' => 'UTC', 'kind' => 'system', 'source' => 'manual', 'system_provider' => 'holiday', 'provider_config' => array( 'region' => 'KR', 'source_locale' => 'ko-KR' ) ) ) ) );
	if ( ! is_wp_error( $ax_mc_plain ) ) {
		/*
		 * A key cannot be attached to an existing calendar. It decides what names the row and what
		 * fills it, so moving one would rename somebody's holiday calendar to Moon phases and point the
		 * generator at it.
		 */
		axismundi_cal_calendar_save( array( 'managed_key' => 'moon-phases' ), (int) $ax_mc_plain );
		ax_mc_assert(
			$ax_mc_results,
			'and a managed key cannot be moved onto one after the fact',
			'' === (string) axismundi_cal_calendar_get( (int) $ax_mc_plain )['managed_key']
		);
		axismundi_cal_system_items_forget_calendar( (int) $ax_mc_plain );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fixture cleanup.
		$wpdb->delete( axismundi_cal_calendars_table(), array( 'id' => (int) $ax_mc_plain ) );
	}
	ax_mc_assert(
		$ax_mc_results,
		'a key nothing defines is refused rather than stored',
		is_wp_error( axismundi_cal_calendar_save( array( 'slug' => 'ax-mc-bogus', 'timezone' => 'UTC', 'kind' => 'system', 'source' => 'manual', 'system_provider' => 'astronomy', 'managed_key' => 'not-a-thing' ) ) )
	);
} finally {
	if ( '' !== $ax_mc_viewer && is_array( $ax_mc_calendar ) ) {
		axismundi_cal_list_remove( (int) $ax_mc_calendar['id'], $ax_mc_viewer );
	}
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fixture cleanup.
	$ax_mc_strays = (array) $wpdb->get_col( $wpdb->prepare( "SELECT id FROM " . axismundi_cal_calendars_table() . " WHERE slug LIKE %s", 'ax-mc-%' ) );
	foreach ( array_map( 'intval', $ax_mc_strays ) as $ax_mc_stray ) {
		axismundi_cal_system_items_forget_calendar( $ax_mc_stray );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fixture cleanup.
		$wpdb->delete( axismundi_cal_calendars_table(), array( 'id' => $ax_mc_stray ) );
	}
	// Leave the site as this file found it: the maintained calendar is a real one, not a fixture.
	axismundi_cal_sync_managed_calendars();
}

$ax_mc_failures = count( array_filter( $ax_mc_results, static fn( bool $result ) : bool => ! $result ) );
// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI fixture output.
printf( "\n== %d checks, %d failed ==\n", count( $ax_mc_results ), $ax_mc_failures );
if ( class_exists( 'WP_CLI' ) ) {
	WP_CLI::halt( $ax_mc_failures > 0 ? 1 : 0 );
}
exit( $ax_mc_failures > 0 ? 1 : 0 );
