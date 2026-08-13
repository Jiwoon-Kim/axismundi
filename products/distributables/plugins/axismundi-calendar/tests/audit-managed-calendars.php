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
$ax_mc_option  = get_option( AXISMUNDI_CAL_PROVISIONED_OPTION, array() );
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
	ax_mc_assert(
		$ax_mc_results,
		'and it arrives filled, because an empty one reads as a broken one',
		is_array( $ax_mc_calendar ) && count( axismundi_cal_system_items_in_range( (int) $ax_mc_calendar['id'], gmdate( 'Y' ) . '-01-01', ( (int) gmdate( 'Y' ) + 1 ) . '-01-01' ) ) >= 48
	);
	$ax_mc_viewer = 'https://example.test/actors/moon-phase-viewer-' . wp_rand( 1000, 9999 );
	ax_mc_assert(
		$ax_mc_results,
		'a viewer receives the managed calendar in their workspace list without creating it',
		is_array( $ax_mc_calendar )
			&& 1 === axismundi_cal_add_managed_calendars_to_list( $ax_mc_viewer )
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

	// -- Deleting one sticks ---------------------------------------------------------------------------

	ax_mc_assert( $ax_mc_results, 'nothing is missing while it is there', array() === axismundi_cal_managed_calendars_missing() );

	$ax_mc_id = (int) $ax_mc_calendar['id'];
	axismundi_cal_system_items_forget_calendar( $ax_mc_id );
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fixture removal in this plugin's own table.
	$wpdb->delete( axismundi_cal_calendars_table(), array( 'id' => $ax_mc_id ) );

	ax_mc_assert( $ax_mc_results, 'a deleted one is reported as missing', array( 'moon-phases' ) === axismundi_cal_managed_calendars_missing() );
	/*
	 * The rule this whole option exists for. Provisioning asks "has this ever been offered", never
	 * "is it there now" -- the second question would recreate on every upgrade a calendar somebody
	 * deliberately removed, and resurrect a slug that subscribers had already stopped following.
	 */
	ax_mc_assert( $ax_mc_results, 'and an upgrade does not bring it back, because deleting it was a decision', 0 === axismundi_cal_provision_managed_calendars() );
	ax_mc_assert( $ax_mc_results, 'so it is still gone afterwards', null === axismundi_cal_managed_calendar_get( 'moon-phases' ) );

	// -- Adding it back ------------------------------------------------------------------------------

	$ax_mc_again = axismundi_cal_provision_managed_calendar( 'moon-phases' );
	ax_mc_assert( $ax_mc_results, 'while asking for it explicitly brings it back, filled', ! is_wp_error( $ax_mc_again ) );
	ax_mc_assert(
		$ax_mc_results,
		'with its entries recomputed rather than recovered',
		! is_wp_error( $ax_mc_again ) && count( axismundi_cal_system_items_in_range( (int) $ax_mc_again, gmdate( 'Y' ) . '-01-01', ( (int) gmdate( 'Y' ) + 1 ) . '-01-01' ) ) >= 48
	);
	/*
	 * Asking twice is not asking for two. The provisioner returns the calendar that is already there,
	 * which is what stops a double-submitted form leaving a duplicate nothing maintains.
	 */
	ax_mc_assert(
		$ax_mc_results,
		'and asking again returns the one that exists rather than making a second',
		! is_wp_error( $ax_mc_again ) && (int) $ax_mc_again === (int) axismundi_cal_provision_managed_calendar( 'moon-phases' )
	);

	// -- What cannot be created by hand ----------------------------------------------------------------

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
	update_option( AXISMUNDI_CAL_PROVISIONED_OPTION, $ax_mc_option, false );
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fixture cleanup.
	$ax_mc_strays = (array) $wpdb->get_col( $wpdb->prepare( "SELECT id FROM " . axismundi_cal_calendars_table() . " WHERE slug LIKE %s", 'ax-mc-%' ) );
	foreach ( array_map( 'intval', $ax_mc_strays ) as $ax_mc_stray ) {
		axismundi_cal_system_items_forget_calendar( $ax_mc_stray );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fixture cleanup.
		$wpdb->delete( axismundi_cal_calendars_table(), array( 'id' => $ax_mc_stray ) );
	}
	// Leave the site as this file found it: the maintained calendar is a real one, not a fixture.
	axismundi_cal_provision_managed_calendar( 'moon-phases' );
}

$ax_mc_failures = count( array_filter( $ax_mc_results, static fn( bool $result ) : bool => ! $result ) );
// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI fixture output.
printf( "\n== %d checks, %d failed ==\n", count( $ax_mc_results ), $ax_mc_failures );
if ( class_exists( 'WP_CLI' ) ) {
	WP_CLI::halt( $ax_mc_failures > 0 ? 1 : 0 );
}
exit( $ax_mc_failures > 0 ? 1 : 0 );
