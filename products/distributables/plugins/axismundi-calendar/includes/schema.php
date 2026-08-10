<?php
/**
 * The event envelope: the facts that make a post an Event.
 *
 * A separate table rather than post meta because these are queried as a set — "what is on next
 * week", "what has not ended yet" — and meta cannot answer a range query without a join per field.
 * GatherPress reached the same shape for the same reason.
 *
 * Times are stored twice, local and UTC, because an Event has both and neither can be derived from
 * the other alone. FEP-8a8e sends `startTime` with an offset and `timezone` as an IANA name, and a
 * "wall time" event — 19:00 wherever the venue is — keeps its local time across a DST change while
 * its UTC instant moves. Storing only UTC would silently reschedule such an event; storing only
 * local would make ordering wrong across zones.
 *
 * @package AxismundiCalendar
 */

defined( 'ABSPATH' ) || exit;

const AXISMUNDI_CAL_DB_VERSION        = '1';
const AXISMUNDI_CAL_DB_VERSION_OPTION = 'ax_event_db_version';

/** @return string Event envelope table name. */
function axismundi_cal_events_table() : string {
	global $wpdb;
	return $wpdb->prefix . 'ax_events';
}

/**
 * Whether the envelope store is present and current.
 *
 * Every reader gates on this rather than assuming, so a half-installed plugin returns nothing
 * instead of fatal errors from a missing table.
 *
 * @return bool
 */
function axismundi_cal_ready() : bool {
	return AXISMUNDI_CAL_DB_VERSION === (string) get_option( AXISMUNDI_CAL_DB_VERSION_OPTION, '' );
}

/**
 * Install and verify the envelope schema.
 *
 * @return bool Whether the table verified.
 */
function axismundi_cal_install_schema() : bool {
	global $wpdb;
	require_once ABSPATH . 'wp-admin/includes/upgrade.php';

	$table   = axismundi_cal_events_table();
	$charset = $wpdb->get_charset_collate();
	dbDelta(
		"CREATE TABLE {$table} (
			post_id bigint(20) unsigned NOT NULL,
			starts_at datetime NOT NULL default '0000-00-00 00:00:00',
			starts_at_gmt datetime NOT NULL default '0000-00-00 00:00:00',
			ends_at datetime NOT NULL default '0000-00-00 00:00:00',
			ends_at_gmt datetime NOT NULL default '0000-00-00 00:00:00',
			timezone varchar(64) NOT NULL default '',
			display_end_time tinyint(1) unsigned NOT NULL default 1,
			previous_starts_at_gmt datetime NULL,
			event_status varchar(24) NOT NULL default 'EventScheduled',
			join_mode varchar(16) NOT NULL default 'free',
			external_participation_url text NOT NULL,
			maximum_attendee_capacity int(10) unsigned NULL,
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (post_id),
			KEY starts_at_gmt (starts_at_gmt),
			KEY ends_at_gmt (ends_at_gmt),
			KEY event_status (event_status)
		) ENGINE=InnoDB {$charset};"
	);

	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fixed custom table verification.
	$columns = (array) $wpdb->get_col( "SHOW COLUMNS FROM {$table}" );
	$required = array( 'post_id', 'starts_at_gmt', 'ends_at_gmt', 'timezone', 'event_status', 'join_mode' );
	foreach ( $required as $column ) {
		if ( ! in_array( $column, $columns, true ) ) {
			return false;
		}
	}
	update_option( AXISMUNDI_CAL_DB_VERSION_OPTION, AXISMUNDI_CAL_DB_VERSION, false );
	return true;
}

/**
 * The event statuses FEP-8a8e defines.
 *
 * Taken from the FEP rather than invented, because a peer reads these literally: a status this site
 * made up is a status every other implementation ignores.
 *
 * @return string[]
 */
function axismundi_cal_event_statuses() : array {
	return array(
		'EventScheduled',
		'EventCancelled',
		'EventPostponed',
		'EventRescheduled',
		'EventTentative',
		'EventMovedOnline',
	);
}

/**
 * The participation modes FEP-8a8e defines.
 *
 * @return string[]
 */
function axismundi_cal_event_join_modes() : array {
	return array( 'free', 'restricted', 'external', 'none', 'invite' );
}
