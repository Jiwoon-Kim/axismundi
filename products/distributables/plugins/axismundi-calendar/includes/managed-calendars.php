<?php
/**
 * The calendars this plugin maintains itself.
 *
 * Moon phases are not a dataset somebody curates. Nobody reviews them, nobody translates them, and
 * there is no version of the answer that belongs to this site rather than to arithmetic -- so asking
 * an administrator to create a calendar, name it, and choose what it holds is asking them to make
 * four decisions that have one correct answer each. These exist from installation.
 *
 * They are not, however, permanent. An administrator who deletes one meant to delete it, and it does
 * not come back on the next upgrade; the system calendar screen offers to add it again. Provisioning
 * is a thing that happened once, recorded as such, rather than a state continuously enforced -- the
 * alternative is a calendar that reappears every time the plugin updates, which is a calendar
 * nobody can get rid of.
 *
 * The name is not stored, for the reason a moon phase's name is not stored: it is a translation.
 * `Moon phases` in the catalog is read as 달의 위상 by a Korean reader and stays correct when the
 * site changes language, where a string written into the row at installation would be frozen in
 * whatever language happened to be active that day. An administrator who types a name over it is
 * making a different statement -- this is what we call it here -- and that one is stored and wins.
 *
 * @package AxismundiCalendar
 */

defined( 'ABSPATH' ) || exit;

/** Which managed calendars have already been offered, so a deleted one is not forced back. */
const AXISMUNDI_CAL_PROVISIONED_OPTION = 'ax_cal_provisioned_managed';

/**
 * The calendars this plugin knows how to maintain.
 *
 * Keyed by a stable identifier that never reaches a reader: it names the row for the code, the way
 * a category key names an entry, and the label is produced from it at read time.
 *
 * Everything here is computed from the site's own arithmetic. That is what makes them provisionable
 * without asking anybody anything -- there is no region to choose, no source language to declare and
 * no feed to reach, so there is no question whose answer could be wrong.
 */
const AXISMUNDI_CAL_MANAGED_CALENDARS = array(
	'moon-phases' => array(
		'provider'   => 'astronomy',
		'slug'       => 'moon-phases',
		'categories' => array( 'ASTRONOMY' ),
	),
);

/**
 * What one managed calendar is called, in the language it is being read in.
 *
 * Translated here rather than stored, which is the same rule the phase names follow one level down.
 *
 * @param string $key Managed calendar key.
 * @return string
 */
function axismundi_cal_managed_calendar_name( string $key ) : string {
	$names = array(
		'moon-phases' => __( 'Moon phases', 'axismundi-calendar' ),
	);
	return $names[ $key ] ?? '';
}

/**
 * A one-line account of what a managed calendar holds.
 *
 * @param string $key Managed calendar key.
 * @return string
 */
function axismundi_cal_managed_calendar_description( string $key ) : string {
	$descriptions = array(
		'moon-phases' => __( 'New moon, first quarter, full moon and last quarter, computed rather than fetched.', 'axismundi-calendar' ),
	);
	return $descriptions[ $key ] ?? '';
}

/**
 * What one Calendar is called.
 *
 * The single answer, so no two surfaces can disagree. A stored name wins because somebody wrote it
 * on purpose: an administrator renaming the moon phases calendar to 음력 참고 is saying what this
 * site calls it, and no generated label should overrule that.
 *
 * @param array<string,mixed> $calendar Calendar row.
 * @return string
 */
function axismundi_cal_calendar_display_name( array $calendar ) : string {
	$name = trim( (string) ( $calendar['name'] ?? '' ) );
	if ( '' !== $name ) {
		return $name;
	}
	$generated = axismundi_cal_managed_calendar_name( (string) ( $calendar['managed_key'] ?? '' ) );
	return '' !== $generated ? $generated : __( 'Untitled calendar', 'axismundi-calendar' );
}

/**
 * Whether this key has been provisioned before, whatever became of it since.
 *
 * @param string $key Managed calendar key.
 * @return bool
 */
function axismundi_cal_managed_calendar_provisioned( string $key ) : bool {
	return in_array( $key, (array) get_option( AXISMUNDI_CAL_PROVISIONED_OPTION, array() ), true );
}

/**
 * The Calendar one key currently has, if it still exists.
 *
 * @param string $key Managed calendar key.
 * @return array<string,mixed>|null
 */
function axismundi_cal_managed_calendar_get( string $key ) : ?array {
	global $wpdb;
	if ( '' === $key || ! axismundi_cal_ready() ) {
		return null;
	}
	$table = axismundi_cal_calendars_table();
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- keyed lookup in this plugin's own table.
	$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE managed_key = %s LIMIT 1", $key ), ARRAY_A );
	return is_array( $row ) ? $row : null;
}

/**
 * Create one managed Calendar and fill it.
 *
 * Called on installation for each key that has never been provisioned, and by hand afterwards from
 * the system calendar screen. Filling it here rather than leaving it to a later job is deliberate:
 * an empty Moon phases calendar looks like a broken one, and the computation is fast enough that
 * there is nothing to defer.
 *
 * @param string $key Managed calendar key.
 * @return int|WP_Error Calendar id.
 */
function axismundi_cal_provision_managed_calendar( string $key ) {
	if ( ! isset( AXISMUNDI_CAL_MANAGED_CALENDARS[ $key ] ) ) {
		return new WP_Error( 'ax_cal_managed_unknown', __( 'That is not a calendar this plugin maintains.', 'axismundi-calendar' ), array( 'status' => 400 ) );
	}
	$existing = axismundi_cal_managed_calendar_get( $key );
	if ( is_array( $existing ) ) {
		return (int) $existing['id'];
	}
	$definition = AXISMUNDI_CAL_MANAGED_CALENDARS[ $key ];

	$created = axismundi_cal_calendar_save(
		array(
			'kind'              => 'system',
			'source'            => 'manual',
			'managed_key'       => $key,
			// No name. The key names it, in whatever language it is read in.
			'slug'              => (string) $definition['slug'],
			'system_provider'   => (string) $definition['provider'],
			'system_categories' => (array) $definition['categories'],
			'description'       => '',
			/*
			 * UTC, because everything in one of these is an instant rather than a local time. A zone here
			 * would be a fact about nothing: a full moon does not happen in Seoul.
			 */
			'timezone'          => 'UTC',
		)
	);
	if ( is_wp_error( $created ) ) {
		return $created;
	}

	$provisioned = (array) get_option( AXISMUNDI_CAL_PROVISIONED_OPTION, array() );
	if ( ! in_array( $key, $provisioned, true ) ) {
		$provisioned[] = $key;
		update_option( AXISMUNDI_CAL_PROVISIONED_OPTION, $provisioned, false );
	}

	if ( 'moon-phases' === $key ) {
		list( $from_year, $to_year ) = axismundi_cal_moon_phase_default_span();
		axismundi_cal_generate_moon_phases( (int) $created, $from_year, $to_year );
	}
	return (int) $created;
}

/**
 * Provision every managed calendar that has never been provisioned.
 *
 * Asked once per key, ever. A key already recorded here is skipped whether its Calendar still exists
 * or not, which is what makes deleting one stick.
 *
 * @return int Number created.
 */
function axismundi_cal_provision_managed_calendars() : int {
	if ( ! axismundi_cal_ready() ) {
		return 0;
	}
	$created = 0;
	foreach ( array_keys( AXISMUNDI_CAL_MANAGED_CALENDARS ) as $key ) {
		if ( axismundi_cal_managed_calendar_provisioned( (string) $key ) ) {
			continue;
		}
		if ( ! is_wp_error( axismundi_cal_provision_managed_calendar( (string) $key ) ) ) {
			++$created;
		}
	}
	return $created;
}

/**
 * The managed calendars an administrator could add back.
 *
 * Provisioned once and since deleted, which is the only state the screen has anything to offer for.
 *
 * @return string[] Keys.
 */
function axismundi_cal_managed_calendars_missing() : array {
	$missing = array();
	foreach ( array_keys( AXISMUNDI_CAL_MANAGED_CALENDARS ) as $key ) {
		if ( null === axismundi_cal_managed_calendar_get( (string) $key ) ) {
			$missing[] = (string) $key;
		}
	}
	return $missing;
}

/**
 * Put the plugin-maintained calendars in one viewer's CalendarList on first visit.
 *
 * A managed Calendar exists independently of any one Actor, but it would otherwise be invisible in
 * the one screen people use to read it: CalendarList is the workspace's input. This is membership,
 * not a grant -- managed Calendars are public by policy already. It is deliberately called only by
 * the workspace, for the Actor who opened it: a person who has hidden it still has an entry and is
 * left alone, while a Calendar an administrator deleted is absent and is never recreated here.
 *
 * @param string $actor_uri Viewer Actor URI.
 * @return int Number of newly added list entries.
 */
function axismundi_cal_add_managed_calendars_to_list( string $actor_uri ) : int {
	$actor_uri = trim( $actor_uri );
	if ( '' === $actor_uri || ! axismundi_cal_ready() ) {
		return 0;
	}

	$added = 0;
	foreach ( array_keys( AXISMUNDI_CAL_MANAGED_CALENDARS ) as $key ) {
		$calendar = axismundi_cal_managed_calendar_get( (string) $key );
		if ( ! is_array( $calendar ) || is_array( axismundi_cal_list_entry( (int) $calendar['id'], $actor_uri ) ) ) {
			continue;
		}
		if ( ! is_wp_error( axismundi_cal_list_set( (int) $calendar['id'], $actor_uri, 'reader', array( 'selected' => true ) ) ) ) {
			++$added;
		}
	}
	return $added;
}
