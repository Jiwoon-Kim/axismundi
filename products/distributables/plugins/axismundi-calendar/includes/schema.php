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

const AXISMUNDI_CAL_DB_VERSION        = '9';
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

/** @return string Calendar collection table name. */
function axismundi_cal_calendars_table() : string {
	global $wpdb;
	return $wpdb->prefix . 'ax_cal_calendars';
}

/** @return string Calendar list entry table name. */
function axismundi_cal_entries_list_table() : string {
	global $wpdb;
	return $wpdb->prefix . 'ax_cal_calendar_list_entries';
}

/** @return string Subscription source table name. */
function axismundi_cal_sources_table() : string {
	global $wpdb;
	return $wpdb->prefix . 'ax_cal_sources';
}

/** @return string Subscribed entry cache table name. */
function axismundi_cal_entries_table() : string {
	global $wpdb;
	return $wpdb->prefix . 'ax_cal_source_entries';
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
			calendar_id bigint(20) unsigned NOT NULL default 0,
			timezone varchar(64) NOT NULL default '',
			all_day tinyint(1) unsigned NOT NULL default 0,
			dtstart_local datetime NOT NULL default '0000-00-00 00:00:00',
			dtend_local datetime NOT NULL default '0000-00-00 00:00:00',
			rrule varchar(255) NOT NULL default '',
			ical_uid varchar(191) NOT NULL default '',
			sequence int(10) unsigned NOT NULL default 0,
			display_end_time tinyint(1) unsigned NOT NULL default 1,
			previous_start_utc datetime NULL,
			materialized_from_utc datetime NULL,
			materialized_until_utc datetime NULL,
			location_place_id bigint(20) unsigned NULL,
			location_text text NOT NULL,
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY ical_uid (ical_uid),
			KEY event_post_id (event_post_id),
			KEY calendar_id (calendar_id)
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

	$calendars = axismundi_cal_calendars_table();
	/*
	 * A Calendar is its own resource, not a taxonomy term. A term is a classification -- what an
	 * Event is about -- while a Calendar is a collection someone owns, publishes and may one day
	 * share or subscribe to, with a timezone, a revision and its own subscription URL. An Event has
	 * one owning Calendar; categories remain the independent classification axis.
	 */
	dbDelta(
		"CREATE TABLE {$calendars} (
			id bigint(20) unsigned NOT NULL auto_increment,
			uuid char(36) NOT NULL default '',
			slug varchar(191) NOT NULL default '',
			name text NOT NULL,
			description longtext NOT NULL,
			timezone varchar(64) NOT NULL default '',
			kind varchar(16) NOT NULL default 'local',
			visibility varchar(16) NOT NULL default 'public',
			revision bigint(20) unsigned NOT NULL default 1,
			owner_actor_uri text NOT NULL,
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY slug (slug),
			UNIQUE KEY uuid (uuid),
			KEY visibility (visibility),
			KEY kind (kind)
		) ENGINE=InnoDB {$charset};"
	);

	/*
	 * There is no membership table. An Event belongs to exactly one Calendar, which is what
	 * `schedules.calendar_id` says, and that is the model every calendar application people already
	 * use has: a calendar collects events, an event does not accumulate calendars. The many-to-many
	 * it replaces made the Calendar's timezone unusable as an authoring default -- an Event in three
	 * calendars would have had three defaults -- and made "which calendar is this event on?" a
	 * question with no answer.
	 */

	$list = axismundi_cal_entries_list_table();
	/*
	 * Ownership is a relation between an Actor and a Calendar, not a property of the Calendar. A
	 * Calendar is an independent resource; who owns it, who may write to it and who merely watches it
	 * are three answers to the same question and belong in one place. Keeping it as a column on the
	 * Calendar could only ever express the first, and only ever for one Actor.
	 *
	 * This is also what a subscribed feed needs. A remote Calendar has no local owner -- its authority
	 * is the source URL and whoever publishes it -- so the Actor who subscribed gets `reader`, and
	 * nothing pretends the subscription made them its owner.
	 *
	 * `actor_uri_hash` carries the uniqueness because an Actor URI is longer than an index key may be,
	 * which is the same reason every other Actor reference here is stored hashed beside its text.
	 */
	dbDelta(
		"CREATE TABLE {$list} (
			id bigint(20) unsigned NOT NULL auto_increment,
			calendar_id bigint(20) unsigned NOT NULL,
			actor_uri text NOT NULL,
			actor_uri_hash char(64) NOT NULL default '',
			access_role varchar(16) NOT NULL default 'reader',
			selected tinyint(1) unsigned NOT NULL default 1,
			hidden tinyint(1) unsigned NOT NULL default 0,
			summary_override text NOT NULL,
			color varchar(32) NOT NULL default '',
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY calendar_actor (calendar_id,actor_uri_hash),
			KEY calendar_id (calendar_id),
			KEY actor_uri_hash (actor_uri_hash),
			KEY access_role (access_role)
		) ENGINE=InnoDB {$charset};"
	);

	$sources = axismundi_cal_sources_table();
	/*
	 * A subscription is a remote Calendar this site reads, never one it owns. `authority` says so in
	 * the row rather than in a convention, because every rule that follows depends on it: entries
	 * from here are read-only, are not re-published to other servers, and are absent from this
	 * site's own iCalendar export.
	 *
	 * `content_hash` exists because many publishers send neither `ETag` nor `Last-Modified`. Without
	 * it every poll would re-parse a document that had not changed.
	 *
	 * One source per instance, not per Calendar. Three people wanting the national holiday feed is
	 * one document to fetch, one cache to keep and one publisher to be polite to -- and `calendar_id`
	 * here is the read-only Calendar this source *is*, not a local calendar it was mixed into.
	 */
	dbDelta(
		"CREATE TABLE {$sources} (
			id bigint(20) unsigned NOT NULL auto_increment,
			calendar_id bigint(20) unsigned NOT NULL,
			kind varchar(24) NOT NULL default 'ical',
			authority varchar(16) NOT NULL default 'remote',
			source_url text NOT NULL,
			source_url_hash char(64) NOT NULL default '',
			etag varchar(191) NOT NULL default '',
			last_modified varchar(64) NOT NULL default '',
			content_hash char(64) NOT NULL default '',
			sync_status varchar(24) NOT NULL default 'pending',
			sync_error text NOT NULL,
			last_checked_at datetime NULL,
			last_success_at datetime NULL,
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY source_url_hash (source_url_hash),
			UNIQUE KEY calendar_id (calendar_id),
			KEY sync_status (sync_status)
		) ENGINE=InnoDB {$charset};"
	);

	$entries = axismundi_cal_entries_table();
	/*
	 * An entry is identified by its source plus `UID` and `RECURRENCE-ID`, which is iCalendar's own
	 * identity for a component -- not by a local id and not by an Object URI, because a subscribed
	 * entry has neither and is not an Object this site can speak for.
	 *
	 * `last_seen_at` and `presence` are what stop a feed's retention window being read as deletion.
	 * WordCamp Central's calendar carries upcoming events only, so an entry leaving it usually means
	 * the event finished, not that it was cancelled. Absence is recorded as absence.
	 */
	dbDelta(
		"CREATE TABLE {$entries} (
			id bigint(20) unsigned NOT NULL auto_increment,
			source_id bigint(20) unsigned NOT NULL,
			ical_uid varchar(191) NOT NULL default '',
			recurrence_id varchar(32) NOT NULL default '',
			entry_hash char(64) NOT NULL default '',
			summary text NOT NULL,
			location text NOT NULL,
			url text NOT NULL,
			timezone varchar(64) NOT NULL default '',
			all_day tinyint(1) unsigned NOT NULL default 0,
			start_utc datetime NOT NULL default '0000-00-00 00:00:00',
			end_utc datetime NOT NULL default '0000-00-00 00:00:00',
			start_local datetime NOT NULL default '0000-00-00 00:00:00',
			end_local datetime NOT NULL default '0000-00-00 00:00:00',
			rrule varchar(255) NOT NULL default '',
			expansion_supported tinyint(1) unsigned NOT NULL default 1,
			status varchar(16) NOT NULL default 'confirmed',
			presence varchar(16) NOT NULL default 'present',
			last_seen_at datetime NOT NULL,
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY source_entry (source_id,entry_hash),
			KEY source_id (source_id),
			KEY start_utc (start_utc),
			KEY presence (presence)
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
	/*
	 * The old membership table is read exactly once before being dropped. Where a legacy Event was
	 * put in several Calendars, the oldest membership wins deterministically. Its schedule retains
	 * its explicit timezone as an override, so the migration never changes the instant of a live
	 * Event merely because the old model permitted an ambiguous grouping.
	 */
	axismundi_cal_assign_orphan_schedules();
	axismundi_cal_backfill_calendar_uuids();
	axismundi_cal_copy_legacy_access_roles();
	axismundi_cal_seed_owner_entries();

	/*
	 * Dropped rather than left behind. It is not read anywhere after this version, and leaving a
	 * table that once answered "which calendars is this event on?" invites someone to answer that
	 * question from it again.
	 */
	$legacy = $wpdb->prefix . 'ax_cal_calendar_items';
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- one-time removal of this plugin's own table.
	$wpdb->query( "DROP TABLE IF EXISTS {$legacy}" );
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
