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

const AXISMUNDI_CAL_DB_VERSION        = '37';
const AXISMUNDI_CAL_DB_VERSION_OPTION = 'ax_event_db_version';
const AXISMUNDI_CAL_SCHEMA_BAIL_OPTION = 'ax_cal_schema_bail';

/**
 * Record why an upgrade stopped, and stop.
 *
 * A failed migration does not degrade this plugin, it switches it off: `axismundi_cal_ready()`
 * compares the stored version against the constant, so a bail leaves every calendar surface dead
 * with nothing anywhere saying which step refused. The reason is worth a row in the options table.
 *
 * @param string $reason What refused.
 * @return false
 */
function axismundi_cal_schema_bail( string $reason ) : bool {
	update_option( AXISMUNDI_CAL_SCHEMA_BAIL_OPTION, $reason, false );
	return false;
}

/** @return string Why the last upgrade stopped, or '' if it did not. */
function axismundi_cal_schema_bail_reason() : string {
	return (string) get_option( AXISMUNDI_CAL_SCHEMA_BAIL_OPTION, '' );
}

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

/**
 * Let an entry have no source uid at all.
 *
 * @return void
 */
function axismundi_cal_upgrade_system_item_uid() : void {
	global $wpdb;
	$table = axismundi_cal_system_items_table();
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- schema migration over this plugin's own table.
	if ( $table !== $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) ) {
		return;
	}
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- as above.
	$column = $wpdb->get_row( "SHOW COLUMNS FROM {$table} LIKE 'source_uid'", ARRAY_A );
	if ( ! is_array( $column ) || 'NO' !== (string) ( $column['Null'] ?? '' ) ) {
		return;
	}
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- as above.
	$wpdb->query( "ALTER TABLE {$table} MODIFY source_uid varchar(191) NULL DEFAULT NULL" );
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- as above.
	$wpdb->query( "UPDATE {$table} SET source_uid = NULL WHERE source_uid = ''" );
}

/** @return string Holiday catalog table name. */
function axismundi_cal_holiday_catalogs_table() : string {
	global $wpdb;
	return $wpdb->prefix . 'ax_cal_holiday_catalogs';
}

/** @return string Holiday concept table name. */
function axismundi_cal_holiday_concepts_table() : string {
	global $wpdb;
	return $wpdb->prefix . 'ax_cal_holiday_concepts';
}

/** @return string Holiday occurrence table name. */
function axismundi_cal_holiday_occurrences_table() : string {
	global $wpdb;
	return $wpdb->prefix . 'ax_cal_holiday_occurrences';
}

/** @return string System calendar item table name. */
function axismundi_cal_system_items_table() : string {
	global $wpdb;
	return $wpdb->prefix . 'ax_cal_system_items';
}

/** @return string Calendar access control table name. */
function axismundi_cal_acl_table() : string {
	global $wpdb;
	return $wpdb->prefix . 'ax_cal_acl';
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

/**
 * Bring the source table's one-source-per-Calendar index to its final shape.
 *
 * Early builds used a non-unique `calendar_id` key alongside a composite unique key. The composite
 * key never enforced the relationship the model promises, and its name prevented dbDelta from
 * replacing the ordinary key with the required unique one. Do this explicitly before dbDelta sees
 * the declaration, preserving rows rather than asking schema reconciliation to guess at indexes.
 *
 * @return bool Whether the existing table is compatible or was upgraded.
 */
function axismundi_cal_upgrade_sources_index() : bool {
	global $wpdb;
	$table = axismundi_cal_sources_table();
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- schema migration over this plugin's own table.
	$exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );
	if ( $table !== $exists ) {
		return true;
	}
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- index inspection for this plugin's own table.
	$indexes = (array) $wpdb->get_results( "SHOW INDEX FROM {$table}", ARRAY_A );
	$has_unique_calendar = false;
	$has_old_calendar    = false;
	$has_old_composite   = false;
	foreach ( $indexes as $index ) {
		if ( 'calendar_id' === (string) $index['Key_name'] ) {
			$has_unique_calendar = 0 === (int) $index['Non_unique'];
			$has_old_calendar    = ! $has_unique_calendar;
		}
		if ( 'calendar_source' === (string) $index['Key_name'] ) {
			$has_old_composite = true;
		}
	}
	if ( $has_unique_calendar ) {
		return true;
	}
	// Do not discard a source silently just to make a new uniqueness promise true.
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- migration precondition over this plugin's own table.
	$duplicate = $wpdb->get_var( "SELECT calendar_id FROM {$table} GROUP BY calendar_id HAVING COUNT(*) > 1 LIMIT 1" );
	if ( null !== $duplicate ) {
		return false;
	}
	if ( $has_old_calendar ) {
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- replacing an obsolete index on this plugin's own table.
		$wpdb->query( "ALTER TABLE {$table} DROP INDEX calendar_id" );
	}
	if ( $has_old_composite ) {
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- replacing an obsolete index on this plugin's own table.
		$wpdb->query( "ALTER TABLE {$table} DROP INDEX calendar_source" );
	}
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- adding the declared invariant to this plugin's own table.
	return false !== $wpdb->query( "ALTER TABLE {$table} ADD UNIQUE KEY calendar_id (calendar_id)" );
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

	// Captured before anything writes it, because one migration below needs to know whether this is
	// an upgrade or a first install -- they call for opposite defaults.
	$previous_version = (string) get_option( AXISMUNDI_CAL_DB_VERSION_OPTION, '' );

	$table   = axismundi_cal_events_table();
	$charset = $wpdb->get_charset_collate();
	/*
	 * `visibility` and `transparency` are two questions a single "private" flag would merge.
	 *
	 * The first is how much of one Event is shown to somebody who may already see its Calendar. The
	 * Calendar stays the outer gate, which is why there is no `public` value here: an Event cannot open
	 * a container that is closed, and a word promising it could would be a setting that does nothing in
	 * exactly the case somebody relied on it.
	 *
	 * The second is whether being able to see it should make that person look occupied. An open house
	 * is fully visible and blocks nobody's afternoon.
	 *
	 * Documented here rather than inside the statement: `dbDelta` parses the declaration line by line
	 * and a block comment within it truncates the `ALTER` it generates, which fails silently as a
	 * column that never arrives.
	 *
	 * `join_mode` and `join_eligibility` are likewise two questions one column was answering. The mode
	 * is how a request is admitted -- immediately, by somebody deciding, elsewhere, or not at all --
	 * and the eligibility is who is allowed to make one. A single field could offer "open to anyone"
	 * and "invitation only" but never "followers, admitted automatically", because that is one value
	 * from each question.
	 *
	 * `public` is the default and the upgrade value both, because it is what every Event written
	 * before this column meant: nothing was restricting who could ask.
	 */
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
			visibility varchar(16) NOT NULL default 'default',
			transparency varchar(16) NOT NULL default 'OPAQUE',
			join_mode varchar(16) NOT NULL default 'none',
			join_eligibility varchar(16) NOT NULL default 'public',
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

	/*
	 * An Event happens in a list of places, because FEP-8a8e takes `location` as one and a hybrid Event
	 * is the ordinary reason: a room, a meeting link, and often a stream of the same thing.
	 *
	 * `position` is a fact rather than a detail. iCalendar's `LOCATION` is a single property, so the
	 * first physical row is the one a document can name and the order is what decides which that is.
	 *
	 * `place_id` is a reference the geodata plugin will own. It is unvalidated here on purpose -- taking
	 * a bare id as proof of a Place would leave this plugin half-owning a model it cannot check.
	 *
	 * `access` is per location rather than inherited from the Event. A public event with a private
	 * joining link is the ordinary case: the address is announced and the meeting URL is for the people
	 * coming, and publishing it in the open feed hands it to everybody who has the calendar.
	 */
	$locations = axismundi_cal_event_locations_table();
	dbDelta(
		"CREATE TABLE {$locations} (
			id bigint(20) unsigned NOT NULL auto_increment,
			event_post_id bigint(20) unsigned NOT NULL,
			position smallint(5) unsigned NOT NULL default 0,
			kind varchar(16) NOT NULL default 'physical',
			features varchar(64) NOT NULL default '',
			access varchar(16) NOT NULL default 'public',
			label text NOT NULL,
			url text NOT NULL,
			address_text text NOT NULL,
			place_id bigint(20) unsigned NULL,
			PRIMARY KEY  (id),
			KEY event_post_id (event_post_id)
		) ENGINE=InnoDB {$charset};"
	);

	/*
	 * Who said they are coming. FEP-8a8e puts this on `Join`, answered with `Accept` or `Reject`, so the
	 * row is one Actor's standing answer rather than a log -- asking twice changes an answer instead of
	 * adding one, which is why the pair is unique.
	 *
	 * Keyed on the Event's Object URI rather than on a post id, because half of what belongs here has
	 * no post. A reply I sent to somebody else's Event is mine to remember and their Event to own, and
	 * a local id could not name it -- so the post id stays as a shortcut for the local case and the URI
	 * is the identity. Whether the Event is cached locally is a display question, not a storage one.
	 *
	 * Two activities, because the pair does not move together. `initiating_activity_uri` is what began
	 * the relationship -- an `Invite` or a `Join` -- and never changes; `current_response_activity_uri`
	 * is the answer the state is currently reading, and is replaced each time somebody answers again.
	 * Somebody who accepts an invitation and later declines it has one `Invite` and a second answer,
	 * and a single column would have to forget one of them.
	 *
	 * The response is NULL while nothing has answered, and stays NULL for a reply that arrived from
	 * outside ActivityPub -- an iTIP `PARTSTAT` has no activity URI to point at, and inventing one
	 * would put a local fiction in a column whose whole job is provenance.
	 *
	 * `source` is who started it, and it is not decoration. `Join` is sent by the person coming and
	 * answered by the organizer; `Invite` is sent by the organizer and answered by the person. The two
	 * produce the same states and mirror who they belong to -- `rejected` is the responder declining
	 * and `withdrawn` is the initiator taking it back, so which role each names depends on this.
	 *
	 * Both URIs are indexed by hash: a URI is a URL and too long to key on directly.
	 *
	 * `withdrawn` is a state rather than a deleted row. Somebody who came and then could not is a
	 * different fact from somebody who never answered, and an organizer counting heads is entitled to
	 * the difference.
	 */
	$participation = axismundi_cal_participation_table();
	axismundi_cal_upgrade_participation_identity();
	dbDelta(
		"CREATE TABLE {$participation} (
			id bigint(20) unsigned NOT NULL auto_increment,
			event_uri text NOT NULL,
			event_uri_hash char(64) NOT NULL default '',
			event_post_id bigint(20) unsigned NULL,
			actor_uri text NOT NULL,
			actor_uri_hash char(64) NOT NULL default '',
			initiating_activity_uri text NOT NULL,
			current_response_activity_uri text NULL,
			source varchar(16) NOT NULL default 'join',
			state varchar(16) NOT NULL default 'pending',
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY event_actor (event_uri_hash, actor_uri_hash),
			KEY event_post_id (event_post_id),
			KEY state (state)
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
			name text NULL,
			managed_key varchar(64) NOT NULL default '',
			description longtext NOT NULL,
			timezone varchar(64) NOT NULL default '',
			kind varchar(16) NOT NULL default 'local',
			source varchar(24) NOT NULL default 'native',
			system_categories varchar(191) NOT NULL default '',
			system_provider varchar(32) NOT NULL default '',
			holiday_catalog_id bigint(20) unsigned NOT NULL default 0,
			provider_config longtext NOT NULL,
			authority_actor_uri text NOT NULL,
			authority_actor_uri_hash char(64) NOT NULL default '',
			visibility varchar(16) NOT NULL default 'public',
			revision bigint(20) unsigned NOT NULL default 1,
			is_primary tinyint(1) NOT NULL default 0,
			owner_actor_uri text NOT NULL,
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY slug (slug),
			UNIQUE KEY uuid (uuid),
			KEY visibility (visibility),
			KEY kind (kind),
			KEY source (source),
			KEY authority_actor_uri_hash (authority_actor_uri_hash),
			KEY authority_primary (authority_actor_uri_hash,is_primary)
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

	$acl = axismundi_cal_acl_table();
	/*
	 * Access control, which is not the same thing as authority. A Calendar has exactly one authority
	 * -- the Actor it belongs to, and the thing a transfer moves -- while `owner` here is a role
	 * several Actors can hold at once. Google keeps the same two apart, and collapsing them would
	 * make "hand this calendar to the group" unexpressible, because there would be no single thing
	 * to hand over.
	 *
	 * `principal_type` distinguishes an Actor from the public at large. Anonymous readers are not an
	 * Actor with an empty URI: that would be one row every unauthenticated request matches by
	 * accident, so being public is stated rather than being the absence of a principal.
	 */
	dbDelta(
		"CREATE TABLE {$acl} (
			id bigint(20) unsigned NOT NULL auto_increment,
			calendar_id bigint(20) unsigned NOT NULL,
			principal_type varchar(16) NOT NULL default 'actor',
			principal_uri text NOT NULL,
			principal_uri_hash char(64) NOT NULL default '',
			role varchar(24) NOT NULL default 'reader',
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY calendar_principal (calendar_id,principal_type,principal_uri_hash),
			KEY calendar_id (calendar_id),
			KEY principal_uri_hash (principal_uri_hash),
			KEY role (role)
		) ENGINE=InnoDB {$charset};"
	);

	$catalogs = axismundi_cal_holiday_catalogs_table();
	/*
	 * The dataset itself, apart from any language. Wikidata's arrangement one level up: an item is
	 * language-neutral and each Wikipedia is a sitelink onto it, so no edition is the original. Here
	 * the catalog is what "Korean public holidays" means, and 대한민국의 휴일 and Holidays in South
	 * Korea are two sitelinks onto it rather than an original and a translation.
	 *
	 * `scope` is what stops one catalog quietly meaning two things. A site covering public holidays
	 * and a site covering every commemoration are describing different datasets, and joining them
	 * would give a reader the second while they asked for the first.
	 */
	dbDelta(
		"CREATE TABLE {$catalogs} (
			id bigint(20) unsigned NOT NULL auto_increment,
			uuid char(36) NOT NULL default '',
			provider varchar(32) NOT NULL default 'holiday',
			jurisdiction char(2) NOT NULL default '',
			scope varchar(48) NOT NULL default 'public-holidays-and-observances',
			label text NOT NULL,
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY uuid (uuid),
			KEY provider_jurisdiction (provider,jurisdiction,scope)
		) ENGINE=InnoDB {$charset};"
	);

	$concepts            = axismundi_cal_holiday_concepts_table();
	// Not `$occurrences`: that name already holds the Event occurrence table in this function, and
	// taking it made the verification below check the wrong table for a column it never had.
	$holiday_occurrences = axismundi_cal_holiday_occurrences_table();
	/*
	 * The holiday itself, and its days. Two feeds hold 설날 and Lunar New Year, and nothing in either
	 * relates them: the identities they carry are their publisher's, and dates move between years. So
	 * the relation is a third thing neither contains, recorded when somebody makes it.
	 *
	 * Wikipedia's arrangement and its reason: a sitelink says two pages are about one subject, not
	 * that they say the same thing, and both stay editable by whoever maintains them.
	 *
	 * Categories sit on the concept. That is what the linking buys -- 설날 is a public holiday once
	 * rather than once per language and again every year.
	 */
	dbDelta(
		"CREATE TABLE {$concepts} (
			id bigint(20) unsigned NOT NULL auto_increment,
			uuid char(36) NOT NULL default '',
			catalog_id bigint(20) unsigned NOT NULL default 0,
			wikidata_qid varchar(24) NOT NULL default '',
			jurisdiction char(2) NOT NULL default '',
			label text NOT NULL,
			categories varchar(191) NOT NULL default '',
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY uuid (uuid),
			KEY catalog_id (catalog_id),
			KEY wikidata_qid (wikidata_qid),
			KEY jurisdiction (jurisdiction)
		) ENGINE=InnoDB {$charset};"
	);
	/*
	 * The three days of 설날 are three occurrences of one concept rather than three concepts. They are
	 * one holiday lasting three days, and splitting them would make "when is 설날" unanswerable and
	 * leave the substitute relation pointing at whichever fragment held the name.
	 *
	 * `substitute_for` names the day being stood in for, rather than a flag saying that one is. A
	 * screen explaining why the 3rd is a holiday needs the 1st, and a flag cannot say it.
	 */
	dbDelta(
		"CREATE TABLE {$holiday_occurrences} (
			id bigint(20) unsigned NOT NULL auto_increment,
			uuid char(36) NOT NULL default '',
			concept_id bigint(20) unsigned NOT NULL,
			start_date date NOT NULL,
			end_date date NOT NULL,
			batch_year smallint(5) unsigned NOT NULL default 0,
			role varchar(24) NOT NULL default 'principal',
			substitute_for bigint(20) unsigned NOT NULL default 0,
			status varchar(16) NOT NULL default 'draft',
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY uuid (uuid),
			KEY concept_year (concept_id,batch_year),
			KEY start_date (start_date)
		) ENGINE=InnoDB {$charset};"
	);
	$system = axismundi_cal_system_items_table();
	/*
	 * `dbDelta` does not relax NOT NULL on a column that already exists, so the nullability is stated
	 * explicitly. It is load-bearing rather than cosmetic: uniqueness on (calendar, source_uid) is
	 * what stops an import writing the same entry twice, and in SQL every NULL is distinct while
	 * every '' is the same value -- stored as '' it would allow exactly one hand-typed entry per
	 * Calendar, forever, and the second would fail with a duplicate key.
	 */
	axismundi_cal_upgrade_system_item_uid();
	/*
	 * Entries of a maintained dataset -- holidays, observances, moon phases. Deliberately not
	 * `ax_event` posts: nobody authored these, nobody is their organizer, they federate as nothing,
	 * and giving each one a post would put a country's public holidays into the same list a person
	 * writes their own Events in.
	 *
	 * A row is one of two shapes, and `temporal_kind` says which.
	 *
	 * `all_day` is civil, in `start_date`/`end_date`. A holiday on the 15th is the 15th in every
	 * timezone, and the moment one becomes an instant it starts moving a day for somebody. `end_date`
	 * is exclusive, as `DTEND` is for an all-day VEVENT, so a one-day entry ends on the following day.
	 *
	 * `instant` is a moment, in `start_utc`. A full moon at 2026-08-28T00:30Z is the 28th in Seoul and
	 * the 27th in Los Angeles, and there is no civil date that is true for both -- the date is
	 * something a viewer's timezone produces, not something this table can hold.
	 *
	 * The two live in separate columns on purpose. One DATETIME with `00:00:00` meaning all-day would
	 * work until the first piece of code forgot the convention and read a holiday as a UTC midnight
	 * instant, which is 광복절 a day early for anyone west of Greenwich. Separate columns cannot be
	 * misread that way: the wrong one is NULL.
	 *
	 * Julian dates are deliberately absent. They are the coordinate the astronomy calculations work
	 * in, they roll at noon, and they are floats -- none of which belongs in a stored value that has
	 * to answer "which day is this" the same way twice.
	 *
	 * `categories` is a comma-separated list of vocabulary keys, queried with FIND_IN_SET. A join
	 * table would be the normal answer, and is the right one at a volume this will not reach: a
	 * country's holidays are tens of rows a year, and the second table costs more in write paths than
	 * it saves in reads.
	 *
	 * `batch_year` and `status` are what make a year of data reviewable before anyone sees it. Which
	 * year an entry belongs to is stored rather than read off its date, because a substitute holiday
	 * for the 31st of December falls in January and still belongs to the year being reviewed.
	 */
	dbDelta(
		"CREATE TABLE {$system} (
			id bigint(20) unsigned NOT NULL auto_increment,
			calendar_id bigint(20) unsigned NOT NULL,
			temporal_kind varchar(16) NOT NULL default 'all_day',
			start_date date NULL,
			end_date date NULL,
			start_utc datetime NULL,
			end_utc datetime NULL,
			title text NULL,
			description longtext NOT NULL,
			categories varchar(191) NOT NULL default '',
			transparency varchar(16) NOT NULL default 'TRANSPARENT',
			batch_year smallint(5) unsigned NOT NULL default 0,
			status varchar(16) NOT NULL default 'draft',
			holiday_occurrence_id bigint(20) unsigned NOT NULL default 0,
			source_uid varchar(191) NULL default NULL,
			source_url text NOT NULL,
			imported_at datetime NULL,
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY calendar_source_uid (calendar_id,source_uid),
			KEY calendar_range (calendar_id,start_date),
			KEY calendar_instant (calendar_id,start_utc),
			KEY holiday_occurrence_id (holiday_occurrence_id),
			KEY calendar_batch (calendar_id,batch_year,status),
			KEY status (status)
		) ENGINE=InnoDB {$charset};"
	);
	/*
	 * A localized item is a label for an occurrence, not a second publication decision. Existing
	 * reviewed rows predate this column, so lift their decision once when the schema grows.
	 */
	$wpdb->query(
		"UPDATE {$holiday_occurrences} o
		 INNER JOIN {$system} i ON i.holiday_occurrence_id = o.id
		 SET o.status = 'published'
		 WHERE i.status = 'published'"
	);
	// Linked rows are localized labels. Remove historic per-language classifications and mirror the
	// occurrence status so old admin tables and the new read path agree immediately after upgrade.
	$wpdb->query(
		"UPDATE {$system} i
		 INNER JOIN {$holiday_occurrences} o ON o.id = i.holiday_occurrence_id
		 SET i.categories = '', i.status = o.status
		 WHERE i.holiday_occurrence_id > 0"
	);

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
	if ( ! axismundi_cal_upgrade_sources_index() ) {
		return axismundi_cal_schema_bail( 'sources-index' );
	}
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
	$required = array( 'post_id', 'starts_at_gmt', 'ends_at_gmt', 'timezone', 'event_status', 'join_mode', 'join_eligibility' );
	foreach ( $required as $column ) {
		if ( ! in_array( $column, $columns, true ) ) {
			return axismundi_cal_schema_bail( 'events-column:' . $column );
		}
	}
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fixed custom table verification.
	$occurrence_columns = (array) $wpdb->get_col( "SHOW COLUMNS FROM {$occurrences}" );
	foreach ( array( 'schedule_id', 'recurrence_id', 'start_utc', 'status', 'origin' ) as $column ) {
		if ( ! in_array( $column, $occurrence_columns, true ) ) {
			return axismundi_cal_schema_bail( 'occurrences-column:' . $column );
		}
	}
	// The holiday occurrence is a separate table; do not let a successful Event-table check mask a
	// partial v22 upgrade, which would make localized items disagree about publication.
	$holiday_occurrence_columns = (array) $wpdb->get_col( "SHOW COLUMNS FROM {$holiday_occurrences}" );
	if ( ! in_array( 'status', $holiday_occurrence_columns, true ) ) {
		return axismundi_cal_schema_bail( 'holiday-occurrences-column:status' );
	}
	// Reached the end, so whatever refused last time no longer does.
	delete_option( AXISMUNDI_CAL_SCHEMA_BAIL_OPTION );
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
	axismundi_cal_backfill_source();
	axismundi_cal_backfill_system_provider();
	axismundi_cal_backfill_holiday_catalogs();
	axismundi_cal_backfill_authority();
	axismundi_cal_clear_v12_unfiled_authority( $previous_version );
	axismundi_cal_seed_authority_acl();
	axismundi_cal_grandfather_public_calendars( $previous_version );

	/*
	 * Dropped rather than left behind. It is not read anywhere after this version, and leaving a
	 * table that once answered "which calendars is this event on?" invites someone to answer that
	 * question from it again.
	 */
	/*
	 * The lunar month cache, dropped with the provider that filled it. Korean lunisolar dates are
	 * computed now, so these rows answer nothing: keeping a table that once held the authoritative
	 * months invites somebody to answer from it again and get a different date than the grid shows.
	 */
	/*
	 * `dbDelta` adds `start_date`/`end_date` where they are missing but will not take NOT NULL off
	 * columns that already have it, and an instant row has no civil date to put there. Freed here.
	 */
	axismundi_cal_relax_system_item_dates();

	/*
	 * Same reason, one column over: a row whose categories name it stores no title.
	 */
	axismundi_cal_relax_system_item_title();

	/*
	 * The same relaxation one level up: a calendar this plugin maintains is named by its key, in the
	 * language of whoever is reading, so there is no string for the column to hold.
	 */
	axismundi_cal_relax_calendar_name();

	/*
	 * The top-level classification moved to the Calendar, where it was always true. Rows written before
	 * that still carry their copy, and a stale copy is worse than a missing one -- it can disagree.
	 */
	axismundi_cal_drop_inherited_item_categories();

	$lunar_cache = $wpdb->prefix . 'ax_cal_lunar_months';
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange -- this plugin's own table.
	$wpdb->query( "DROP TABLE IF EXISTS {$lunar_cache}" );
	delete_option( 'ax_cal_kasi_service_key' );

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

/**
 * Let a system item have no civil date, for the rows that are instants.
 *
 * @return void
 */
function axismundi_cal_relax_system_item_dates() : void {
	global $wpdb;
	$table = axismundi_cal_system_items_table();
	foreach ( array( 'start_date', 'end_date' ) as $column ) {
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- this plugin's own table.
		$definition = $wpdb->get_row( $wpdb->prepare( "SHOW COLUMNS FROM {$table} LIKE %s", $column ), ARRAY_A );
		if ( ! is_array( $definition ) || 'NO' !== ( $definition['Null'] ?? '' ) ) {
			continue;
		}
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange -- this plugin's own table.
		$wpdb->query( "ALTER TABLE {$table} MODIFY {$column} date NULL DEFAULT NULL" );
	}
}

/**
 * Take the Calendar's own classification back off its entries.
 *
 * `ASTRONOMY` on every row of the moon phase calendar was the same fact several hundred times, and
 * `item_effective_categories()` now supplies it from the Calendar. Removed rather than left to rot:
 * a row that kept `HOLIDAY` after being moved would contradict the Calendar it is on, and nothing
 * reads the stored copy any more to notice.
 *
 * Done in PHP rather than in SQL string surgery, because `categories` is a comma-separated set and
 * `REPLACE()` on it would turn `PUBLIC-HOLIDAY` into `PUBLIC-` given the wrong key.
 *
 * @return void
 */
function axismundi_cal_drop_inherited_item_categories() : void {
	global $wpdb;
	$items     = axismundi_cal_system_items_table();
	$calendars = axismundi_cal_calendars_table();
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- one-time migration over this plugin's own tables.
	$rows = (array) $wpdb->get_results(
		"SELECT i.id, i.categories, c.system_categories
		 FROM {$items} i INNER JOIN {$calendars} c ON c.id = i.calendar_id
		 WHERE i.categories <> '' AND c.system_categories <> ''",
		ARRAY_A
	);
	foreach ( $rows as $row ) {
		$inherited = axismundi_cal_normalize_system_calendar_categories( (string) $row['system_categories'] );
		$own       = axismundi_cal_normalize_categories( (string) $row['categories'] );
		$kept      = array_values( array_diff( $own, $inherited ) );
		if ( $kept === $own ) {
			continue;
		}
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- as above.
		$wpdb->update( $items, array( 'categories' => implode( ',', $kept ) ), array( 'id' => (int) $row['id'] ) );
	}
}

/**
 * Let a maintained calendar have no name, for the ones their key names.
 *
 * `dbDelta` will not take NOT NULL off a column that already has it, the same reason the item title
 * and the item dates needed freeing.
 *
 * @return void
 */
function axismundi_cal_relax_calendar_name() : void {
	global $wpdb;
	$table = axismundi_cal_calendars_table();
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- this plugin's own table.
	$definition = $wpdb->get_row( $wpdb->prepare( "SHOW COLUMNS FROM {$table} LIKE %s", 'name' ), ARRAY_A );
	if ( ! is_array( $definition ) || 'NO' !== ( $definition['Null'] ?? '' ) ) {
		return;
	}
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange -- this plugin's own table.
	$wpdb->query( "ALTER TABLE {$table} MODIFY name text NULL DEFAULT NULL" );
}

/**
 * Move participation identity from the local post to the Event URI.
 *
 * `dbDelta` adds columns and creates indexes but never drops one, so the old unique key on
 * `(event_post_id, actor_uri_hash)` would survive beside the new one -- and go on refusing a second
 * row for the same local Event even after the URI became the identity.
 *
 * @return void
 */
function axismundi_cal_upgrade_participation_identity() : void {
	global $wpdb;
	$table = axismundi_cal_participation_table();
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- this plugin's own table.
	if ( $table !== $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) ) {
		return;
	}
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- as above.
	$columns = array_column( (array) $wpdb->get_results( "SHOW COLUMNS FROM {$table}", ARRAY_A ), 'Field' );

	/*
	 * Renamed rather than added beside. `dbDelta` would create the new column and leave the old one
	 * full of the same URIs, and the pair would be two answers to where a row came from.
	 */
	/*
	 * The tentative pair, folded. `PARTSTAT=TENTATIVE` and `TentativeAccept` lean the same way, so
	 * storing "tentative from ActivityPub" apart from "tentative from iCalendar" was recording where an
	 * answer came from in the state -- which is what the response activity column now answers.
	 */
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- as above.
	$wpdb->query( "UPDATE {$table} SET state = 'tentative' WHERE state = 'tentative_accept'" );
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- as above.
	$wpdb->query( "UPDATE {$table} SET state = 'tentative_rejected' WHERE state = 'tentative_reject'" );

	if ( in_array( 'join_activity_uri', $columns, true ) && ! in_array( 'initiating_activity_uri', $columns, true ) ) {
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange -- as above.
		$wpdb->query( "ALTER TABLE {$table} CHANGE COLUMN `join_activity_uri` `initiating_activity_uri` text NOT NULL" );
	}

	if ( ! in_array( 'event_uri_hash', $columns, true ) ) {
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange -- as above.
		$wpdb->query( "ALTER TABLE {$table} DROP INDEX event_actor" );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange -- as above.
		$wpdb->query( "ALTER TABLE {$table} MODIFY event_post_id bigint(20) unsigned NULL" );
		return;
	}

	/*
	 * The rows that were written before the column existed. Every one of them has an empty hash, so
	 * they collide with each other the moment the new unique key is attempted -- and `dbDelta` reports
	 * that as a database error and carries on, leaving the identity unenforced.
	 *
	 * Filled in from the post they named, which is the identity they always had; a row whose post has
	 * since gone names nothing that can be recovered and is dropped rather than left to block the key.
	 */
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- as above.
	$orphans = (array) $wpdb->get_results( "SELECT id, event_post_id FROM {$table} WHERE event_uri_hash = ''", ARRAY_A );
	foreach ( $orphans as $orphan ) {
		$uri = axismundi_cal_event_uri( (int) $orphan['event_post_id'] );
		if ( '' === $uri ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- as above.
			$wpdb->delete( $table, array( 'id' => (int) $orphan['id'] ) );
			continue;
		}
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- as above.
		$wpdb->update(
			$table,
			array( 'event_uri' => $uri, 'event_uri_hash' => hash( 'sha256', $uri ) ),
			array( 'id' => (int) $orphan['id'] )
		);
	}
}

/**
 * Let a system item have no title, for the rows their categories name.
 *
 * `dbDelta` will not take NOT NULL off a column that already has it, the same reason the dates
 * needed freeing. A moon phase carries `FULL-MOON` and is named from it at read time, so there is no
 * string for this column to hold and '' would claim somebody had cleared the name.
 *
 * @return void
 */
function axismundi_cal_relax_system_item_title() : void {
	global $wpdb;
	$table = axismundi_cal_system_items_table();
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- this plugin's own table.
	$definition = $wpdb->get_row( $wpdb->prepare( "SHOW COLUMNS FROM {$table} LIKE %s", 'title' ), ARRAY_A );
	if ( ! is_array( $definition ) || 'NO' !== ( $definition['Null'] ?? '' ) ) {
		return;
	}
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange -- this plugin's own table.
	$wpdb->query( "ALTER TABLE {$table} MODIFY title text NULL DEFAULT NULL" );
}
