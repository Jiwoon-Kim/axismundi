<?php
/**
 * The Calendar collection.
 *
 * A Calendar is the one home of an Event and a resource in its own right: it has a name, a
 * timezone, a subscription URL and a revision. It is not a taxonomy term and not a post type. A
 * term classifies what an Event is about; a Calendar establishes where it is scheduled.
 *
 * Membership is by series, not by occurrence. A weekly meeting is one member however many times it
 * meets, and an annual birthday is one member forever. Storing occurrences as members would make a
 * Calendar grow without bound for a rule that never ends, which is the same reason iCalendar export
 * writes rules rather than expansions.
 *
 * `revision` moves whenever the Calendar's own fields or its Event assignment changes. It is not a
 * substitute for the feed's entity tag -- that is a hash of the document, which also moves when an
 * Event inside changes or the rolling window turns over -- but it gives the collection something
 * cheap to compare without serializing it.
 *
 * @package AxismundiCalendar
 */

defined( 'ABSPATH' ) || exit;

/**
 * A Calendar by slug.
 *
 * @param string $slug Calendar slug.
 * @return array<string,mixed>|null
 */
function axismundi_cal_calendar_by_slug( string $slug ) : ?array {
	global $wpdb;
	$slug = sanitize_title( $slug );
	if ( '' === $slug || ! axismundi_cal_ready() ) {
		return null;
	}
	$table = axismundi_cal_calendars_table();
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- keyed lookup in this plugin's own table.
	$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE slug = %s", $slug ), ARRAY_A );
	return is_array( $row ) ? $row : null;
}

/**
 * The human-readable address of a Calendar.
 *
 * @param array<string,mixed> $calendar Calendar row.
 * @return string
 */
function axismundi_cal_calendar_url( array $calendar ) : string {
	return home_url( '/calendar/' . rawurlencode( (string) $calendar['slug'] ) . '/' );
}

/**
 * The local iCalendar subscription address of a Calendar.
 *
 * Remote Calendars intentionally have no re-export URL: this instance is a cache, not their
 * authority. Callers receive an empty string for that case.
 *
 * @param array<string,mixed> $calendar Calendar row.
 * @return string
 */
function axismundi_cal_calendar_ics_url( array $calendar ) : string {
	if ( 'local' !== (string) $calendar['kind'] ) {
		return '';
	}
	return home_url( '/calendar/' . rawurlencode( (string) $calendar['slug'] ) . '.ics' );
}

/**
 * A Calendar by id.
 *
 * @param int $calendar_id Calendar id.
 * @return array<string,mixed>|null
 */
function axismundi_cal_calendar_get( int $calendar_id ) : ?array {
	global $wpdb;
	if ( $calendar_id <= 0 || ! axismundi_cal_ready() ) {
		return null;
	}
	$table = axismundi_cal_calendars_table();
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- primary-key lookup in this plugin's own table.
	$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $calendar_id ), ARRAY_A );
	return is_array( $row ) ? $row : null;
}

/**
 * The timezone a calendar is displayed in.
 *
 * The reader's, not the calendar's. An Event happening at 09:00 in London is the same instant
 * wherever it is read, and somebody in Seoul wants to see 17:00 -- so a London calendar viewed from
 * Seoul shows Seoul times. Laying the grid out in the calendar's own zone would tell that reader an
 * event is at nine in the morning when it is not.
 *
 * The site's zone stands in for the reader's until people have their own. That is the seam this
 * function exists to be: a per-user preference replaces the body and nothing else changes.
 *
 * Two kinds of value are deliberately not converted, and the grouping handles them: an all-day date
 * is the same civil date everywhere, and a floating time means the same wall clock everywhere.
 *
 * @return DateTimeZone
 */
function axismundi_cal_viewer_timezone() : DateTimeZone {
	return wp_timezone();
}

/**
 * The timezone a Calendar names as its own.
 *
 * This is the default timezone for an Event authored on this Calendar and what a subscription feed
 * declares as its home zone. It never decides what a reader sees. An Event may retain an explicit
 * timezone override for a genuine cross-region event or for legacy data, but a new Event starts
 * from this named place rather than an arbitrary site offset.
 *
 * @param array<string,mixed>|null $calendar Calendar row, or null.
 * @return string IANA identifier, or ''.
 */
function axismundi_cal_calendar_timezone( ?array $calendar ) : string {
	$stored = is_array( $calendar ) ? trim( (string) ( $calendar['timezone'] ?? '' ) ) : '';
	return in_array( $stored, timezone_identifiers_list(), true ) ? $stored : '';
}

/**
 * A Calendar by its public identifier.
 *
 * @param string $uuid Calendar UUID.
 * @return array<string,mixed>|null
 */
function axismundi_cal_calendar_by_uuid( string $uuid ) : ?array {
	global $wpdb;
	$uuid = trim( $uuid );
	if ( '' === $uuid || ! axismundi_cal_ready() ) {
		return null;
	}
	$table = axismundi_cal_calendars_table();
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- keyed lookup in this plugin's own table.
	$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE uuid = %s", $uuid ), ARRAY_A );
	return is_array( $row ) ? $row : null;
}

/**
 * Give a public identifier to Calendars created before there was one.
 *
 * @return int Number of Calendars given a UUID.
 */
function axismundi_cal_backfill_calendar_uuids() : int {
	global $wpdb;
	$table = axismundi_cal_calendars_table();
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- one-time migration over this plugin's own table.
	$ids = array_map( 'intval', (array) $wpdb->get_col( "SELECT id FROM {$table} WHERE uuid = ''" ) );
	foreach ( $ids as $id ) {
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- as above.
		$wpdb->update( $table, array( 'uuid' => wp_generate_uuid4() ), array( 'id' => $id ) );
	}
	return count( $ids );
}

/**
 * Create or update a Calendar.
 *
 * @param array<string,mixed> $fields     Calendar fields. `slug` identifies an existing Calendar.
 * @param int                 $calendar_id Existing Calendar id, or 0.
 * @return int|WP_Error Calendar id.
 */
function axismundi_cal_calendar_save( array $fields, int $calendar_id = 0 ) {
	global $wpdb;
	if ( ! axismundi_cal_ready() ) {
		return new WP_Error( 'ax_cal_store', __( 'The calendar store is unavailable.', 'axismundi-calendar' ) );
	}
	$existing = $calendar_id > 0 ? axismundi_cal_calendar_get( $calendar_id ) : null;

	$name = trim( (string) ( $fields['name'] ?? ( $existing['name'] ?? '' ) ) );
	if ( '' === $name ) {
		return new WP_Error( 'ax_cal_name', __( 'A calendar needs a name.', 'axismundi-calendar' ), array( 'status' => 400 ) );
	}

	$slug = sanitize_title( (string) ( $fields['slug'] ?? ( $existing['slug'] ?? $name ) ) );
	if ( '' === $slug ) {
		return new WP_Error( 'ax_cal_slug', __( 'A calendar needs a slug that can appear in a URL.', 'axismundi-calendar' ), array( 'status' => 400 ) );
	}
	$clash = axismundi_cal_calendar_by_slug( $slug );
	if ( is_array( $clash ) && (int) $clash['id'] !== (int) ( $existing['id'] ?? 0 ) ) {
		// Refused rather than suffixed. The slug is the subscription URL people have already given
		// to their calendar app, so quietly minting a different one produces a feed nobody follows.
		return new WP_Error( 'ax_cal_slug_taken', __( 'Another calendar already uses that slug.', 'axismundi-calendar' ), array( 'status' => 409 ) );
	}

	$kind = (string) ( $fields['kind'] ?? ( $existing['kind'] ?? 'local' ) );
	if ( ! in_array( $kind, array( 'local', 'remote' ), true ) ) {
		return new WP_Error( 'ax_cal_kind', __( 'A calendar must be local or remote.', 'axismundi-calendar' ), array( 'status' => 400 ) );
	}
	if ( is_array( $existing ) && $kind !== (string) $existing['kind'] ) {
		return new WP_Error( 'ax_cal_kind', __( 'A calendar cannot change between local and remote.', 'axismundi-calendar' ), array( 'status' => 400 ) );
	}

	$timezone = trim( (string) ( $fields['timezone'] ?? ( $existing['timezone'] ?? '' ) ) );
	if ( '' === $timezone || ! in_array( $timezone, timezone_identifiers_list(), true ) ) {
		return new WP_Error( 'ax_cal_timezone', __( 'A calendar needs an IANA timezone such as Asia/Seoul.', 'axismundi-calendar' ), array( 'status' => 400 ) );
	}

	/*
	 * Public only, for now. Private calendars need a subscription secret, a way to revoke it and a
	 * story for the caches in between; shipping the column without those would offer a privacy
	 * guarantee nothing enforces.
	 */
	$visibility = 'public';

	$now  = current_time( 'mysql', true );
	$data = array(
		'slug'           => $slug,
		'name'           => $name,
		'description'    => (string) ( $fields['description'] ?? ( $existing['description'] ?? '' ) ),
		'timezone'       => $timezone,
		'kind'           => $kind,
		'visibility'     => $visibility,
		'updated_at'     => $now,
	);

	$table = axismundi_cal_calendars_table();
	if ( is_array( $existing ) ) {
		$data['revision'] = (int) $existing['revision'] + 1;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- this plugin's own table.
		if ( false === $wpdb->update( $table, $data, array( 'id' => (int) $existing['id'] ) ) ) {
			return new WP_Error( 'ax_cal_write', __( 'The calendar could not be saved.', 'axismundi-calendar' ) );
		}
		if ( array_key_exists( 'owner_actor_uri', $fields ) ) {
			axismundi_cal_record_owner( (int) $existing['id'], (string) $fields['owner_actor_uri'], $kind );
		}
		return (int) $existing['id'];
	}

	$data['revision']   = 1;
	// A stable public identifier. The slug is editable and appears in subscription URLs, so it cannot
	// also be the key an API or a stored reference uses -- renaming a calendar would silently break
	// every one of them.
	$data['uuid']       = wp_generate_uuid4();
	$data['created_at'] = $now;
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- this plugin's own table.
	if ( false === $wpdb->insert( $table, $data ) ) {
		return new WP_Error( 'ax_cal_write', __( 'The calendar could not be saved.', 'axismundi-calendar' ) );
	}
	$created_id = (int) $wpdb->insert_id;
	axismundi_cal_record_owner( $created_id, (string) ( $fields['owner_actor_uri'] ?? '' ), $kind );
	return $created_id;
}

/**
 * Record who owns a Calendar, as a relation.
 *
 * Nothing is written for a remote Calendar. Its authority is the feed it projects and whoever
 * publishes that, so making the subscriber its owner would be this site claiming it can speak for
 * somebody else's calendar -- and the claim would outlive the reason it was made.
 *
 * @param int    $calendar_id Calendar id.
 * @param string $actor_uri   Owning Actor URI, or '' to leave ownership unset.
 * @param string $kind        local|remote.
 * @return void
 */
function axismundi_cal_record_owner( int $calendar_id, string $actor_uri, string $kind ) : void {
	global $wpdb;
	$actor_uri = trim( $actor_uri );
	if ( 'local' !== $kind || '' === $actor_uri ) {
		return;
	}
	/*
	 * Three writes, because they are three different facts. The authority is the Actor this Calendar
	 * belongs to and is what a transfer would move. The ACL rule is what that Actor may do, which
	 * others can also be granted. The list entry is only how it appears in their own sidebar.
	 */
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- this plugin's own table.
	$wpdb->update(
		axismundi_cal_calendars_table(),
		array( 'authority_actor_uri' => $actor_uri, 'authority_actor_uri_hash' => hash( 'sha256', $actor_uri ) ),
		array( 'id' => $calendar_id )
	);
	axismundi_cal_acl_grant( $calendar_id, $actor_uri, 'owner' );
	// Creating a Calendar also puts it in the creator's sidebar, but this row grants nothing and may
	// later be removed without affecting either the authority or the ACL.
	axismundi_cal_list_set( $calendar_id, $actor_uri );
}

/**
 * Delete a local Calendar and the Events it owns.
 *
 * A Calendar is the home of its Events. Deleting it therefore deletes that local content rather
 * than leaving unfiled schedules that can appear on some later page by accident. A remote Calendar
 * is removed together with its source by `axismundi_cal_remove_source()`.
 *
 * @param int $calendar_id Calendar id.
 * @return bool
 */
function axismundi_cal_calendar_delete( int $calendar_id ) : bool {
	global $wpdb;
	if ( $calendar_id <= 0 || ! axismundi_cal_ready() ) {
		return false;
	}
	$calendar = axismundi_cal_calendar_get( $calendar_id );
	// A remote Calendar is the representation of one source. Deleting it independently would leave
	// the source and its cache pointing at a Calendar that no longer exists.
	if ( ! is_array( $calendar ) || 'remote' === (string) $calendar['kind'] ) {
		return false;
	}
	foreach ( axismundi_cal_calendar_event_ids( $calendar_id ) as $event_id ) {
		$post = get_post( $event_id );
		if ( $post instanceof WP_Post ) {
			if ( ! wp_delete_post( $event_id, true ) ) {
				return false;
			}
		} else {
			// A missing post is stale local projection data, not a reason to preserve an otherwise
			// undeletable Calendar.
			axismundi_cal_forget_deleted_event( $event_id );
		}
	}
	// The relations go with it. An entry naming a Calendar that no longer exists would show up in
	// somebody's list as a calendar they cannot open.
	axismundi_cal_list_forget_calendar( $calendar_id );
	axismundi_cal_acl_forget_calendar( $calendar_id );
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- this plugin's own table.
	return false !== $wpdb->delete( axismundi_cal_calendars_table(), array( 'id' => $calendar_id ) );
}

/**
 * Note that a Calendar's contents changed.
 *
 * @param int $calendar_id Calendar id.
 * @return void
 */
function axismundi_cal_bump_revision( int $calendar_id ) : void {
	global $wpdb;
	$table = axismundi_cal_calendars_table();
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- this plugin's own table.
	$wpdb->query( $wpdb->prepare( "UPDATE {$table} SET revision = revision + 1, updated_at = %s WHERE id = %d", current_time( 'mysql', true ), $calendar_id ) );
}

/**
 * The Event post ids a local Calendar owns.
 *
 * @param int $calendar_id Calendar id.
 * @return int[]
 */
function axismundi_cal_calendar_event_ids( int $calendar_id ) : array {
	global $wpdb;
	if ( $calendar_id <= 0 || ! axismundi_cal_ready() ) {
		return array();
	}
	$table = axismundi_cal_schedules_table();
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- ownership lookup in this plugin's own table.
	return array_map(
		'intval',
		(array) $wpdb->get_col(
			$wpdb->prepare(
				"SELECT event_post_id FROM {$table} WHERE calendar_id = %d",
				$calendar_id
			)
		)
	);
}

/**
 * The Calendar one Event belongs to.
 *
 * @param int $post_id Event post id.
 * @return array<int,array<string,mixed>>
 */
function axismundi_cal_calendar_for_event( int $post_id ) : ?array {
	$schedule = axismundi_cal_schedule_for_event( $post_id );
	return is_array( $schedule ) ? axismundi_cal_calendar_get( (int) $schedule['calendar_id'] ) : null;
}

/**
 * Drop the Schedule when its Event is deleted.
 *
 * @param int $post_id Post id.
 * @return void
 */
function axismundi_cal_forget_deleted_event( int $post_id ) : void {
	global $wpdb;
	if ( ! axismundi_cal_ready() ) {
		return;
	}
	$schedule = axismundi_cal_schedule_for_event( $post_id );
	if ( ! is_array( $schedule ) ) {
		return;
	}
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- this plugin's own tables.
	$wpdb->delete( axismundi_cal_occurrences_table(), array( 'schedule_id' => (int) $schedule['id'] ) );
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- this plugin's own table.
	$wpdb->delete( axismundi_cal_schedules_table(), array( 'id' => (int) $schedule['id'] ) );
	axismundi_cal_bump_revision( (int) $schedule['calendar_id'] );
}
add_action( 'deleted_post', 'axismundi_cal_forget_deleted_event', 10, 1 );
