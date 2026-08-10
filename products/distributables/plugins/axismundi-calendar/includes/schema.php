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

const AXISMUNDI_CAL_DB_VERSION        = '3';
const AXISMUNDI_CAL_DB_VERSION_OPTION = 'ax_event_db_version';

/** @return string Event envelope table name. */
function axismundi_cal_events_table() : string {
	global $wpdb;
	return $wpdb->prefix . 'ax_events';
}

/** @return string Schedule table name. */
function axismundi_cal_schedules_table() : string {
	global $wpdb;
	return $wpdb->prefix . 'ax_cal_schedules';
}

/** @return string Occurrence table name. */
function axismundi_cal_occurrences_table() : string {
	global $wpdb;
	return $wpdb->prefix . 'ax_cal_occurrences';
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

	$schedules = axismundi_cal_schedules_table();
	/*
	 * No UTC columns here, deliberately. A start time plus a rule does not have one UTC instant: a
	 * weekly 19:00 keeps its wall time across a DST change while the instant beneath it moves, so a
	 * UTC pair stored beside the rule would be correct only for the first occurrence and quietly
	 * wrong for the rest of the year. UTC belongs to the Occurrence, which is the thing that has one.
	 *
	 * `all_day` is a flag rather than a convention, because a date is not midnight in a timezone.
	 * An all-day event is the same day everywhere; midnight-with-a-zone is a different instant for
	 * every reader and shifts across DST.
	 */
	dbDelta(
		"CREATE TABLE {$schedules} (
			id bigint(20) unsigned NOT NULL auto_increment,
			event_post_id bigint(20) unsigned NOT NULL,
			timezone varchar(64) NOT NULL default '',
			all_day tinyint(1) unsigned NOT NULL default 0,
			dtstart_local datetime NOT NULL default '0000-00-00 00:00:00',
			dtend_local datetime NOT NULL default '0000-00-00 00:00:00',
			rrule varchar(255) NOT NULL default '',
			ical_uid varchar(191) NOT NULL default '',
			sequence int(10) unsigned NOT NULL default 0,
			display_end_time tinyint(1) unsigned NOT NULL default 1,
			previous_start_utc datetime NULL,
			materialized_until_utc datetime NULL,
			location_place_id bigint(20) unsigned NULL,
			location_text text NOT NULL,
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY ical_uid (ical_uid),
			KEY event_post_id (event_post_id)
		) ENGINE=InnoDB {$charset};"
	);

	$occurrences = axismundi_cal_occurrences_table();
	/*
	 * `recurrence_id` is the local wall time the rule produced, and is the stable identity of one
	 * instance -- the same thing iCalendar's RECURRENCE-ID is. It is not derived from `start_utc`,
	 * because moving an occurrence or crossing a DST boundary changes the instant while leaving the
	 * question "which Saturday is this?" unchanged. Cancelling the 22nd has to keep pointing at the
	 * 22nd afterwards.
	 *
	 * `origin` is what makes the cache safe to discard. Rule-derived rows are a materialization of
	 * something recomputable; `rdate` and `override` rows are authored facts that exist nowhere else
	 * and must survive any rebuild.
	 *
	 * EXDATE has no column: a cancelled occurrence is a row with `status = 'cancelled'`, and the
	 * EXDATE lines in exported ICS are generated from those rows. Storing both would be two
	 * spellings of one fact, free to disagree.
	 */
	dbDelta(
		"CREATE TABLE {$occurrences} (
			id bigint(20) unsigned NOT NULL auto_increment,
			schedule_id bigint(20) unsigned NOT NULL,
			recurrence_id varchar(20) NOT NULL default '',
			start_utc datetime NOT NULL default '0000-00-00 00:00:00',
			end_utc datetime NOT NULL default '0000-00-00 00:00:00',
			start_local datetime NOT NULL default '0000-00-00 00:00:00',
			end_local datetime NOT NULL default '0000-00-00 00:00:00',
			status varchar(16) NOT NULL default 'scheduled',
			origin varchar(16) NOT NULL default 'rule',
			location_place_id bigint(20) unsigned NULL,
			location_text text NOT NULL,
			override_json longtext NOT NULL,
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY schedule_recurrence (schedule_id,recurrence_id),
			KEY start_utc (start_utc),
			KEY schedule_start (schedule_id,start_utc),
			KEY status (status)
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
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fixed custom table verification.
	$occurrence_columns = (array) $wpdb->get_col( "SHOW COLUMNS FROM {$occurrences}" );
	foreach ( array( 'schedule_id', 'recurrence_id', 'start_utc', 'status', 'origin' ) as $column ) {
		if ( ! in_array( $column, $occurrence_columns, true ) ) {
			return false;
		}
	}
	update_option( AXISMUNDI_CAL_DB_VERSION_OPTION, AXISMUNDI_CAL_DB_VERSION, false );
	/*
	 * The one-time conversion out of the legacy envelope. Run here rather than from a version
	 * guard because it is idempotent by existence: a schedule is created only for an Event that
	 * has none, so a rerun cannot duplicate one or overwrite one that has since been edited.
	 */
	axismundi_cal_convert_legacy_envelopes();
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
