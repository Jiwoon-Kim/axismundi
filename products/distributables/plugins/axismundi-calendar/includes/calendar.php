<?php
/**
 * The Calendar collection.
 *
 * A Calendar is a set of Events someone publishes, and a resource in its own right: it has a name,
 * a timezone, a subscription URL and a revision. It is not a taxonomy term and not a post type. A
 * term classifies what an Event is about; a Calendar is a collection an Event belongs to, and the
 * two axes are independent -- one Event sits in several Calendars while carrying its own
 * categories, and neither can express the other.
 *
 * Membership is by series, not by occurrence. A weekly meeting is one member however many times it
 * meets, and an annual birthday is one member forever. Storing occurrences as members would make a
 * Calendar grow without bound for a rule that never ends, which is the same reason iCalendar export
 * writes rules rather than expansions.
 *
 * `revision` moves whenever the membership or the Calendar's own fields change. It is not a
 * substitute for the feed's entity tag -- that is a hash of the document, which also moves when an
 * Event inside changes or the rolling window turns over -- but it gives the collection something
 * cheap to compare without serializing it.
 *
 * @package AxismundiCalendar
 */

defined( 'ABSPATH' ) || exit;

/** Member kinds. Only local Events can be authored today; the rest arrive with the providers. */
const AXISMUNDI_CAL_MEMBER_LOCAL_EVENT = 'local_event';

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

	/*
	 * A Calendar's timezone is a display default, not a fact about any Event -- each Event carries
	 * its own -- so it is optional and an unusable one is dropped rather than fatal.
	 *
	 * The default cannot simply be `wp_timezone_string()`: a site configured with a manual UTC
	 * offset returns `+09:00`, which is not an IANA identifier and fails this very check. Refusing
	 * there would make creating a calendar impossible on such a site, with an error blaming a
	 * timezone the author never typed.
	 */
	$given    = array_key_exists( 'timezone', $fields );
	$timezone = (string) ( $fields['timezone'] ?? ( $existing['timezone'] ?? wp_timezone_string() ) );
	if ( '' !== $timezone && ! in_array( $timezone, timezone_identifiers_list(), true ) ) {
		if ( $given ) {
			return new WP_Error( 'ax_cal_timezone', __( 'The timezone must be an IANA identifier.', 'axismundi-calendar' ), array( 'status' => 400 ) );
		}
		$timezone = '';
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
		'visibility'     => $visibility,
		'owner_actor_uri' => (string) ( $fields['owner_actor_uri'] ?? ( $existing['owner_actor_uri'] ?? '' ) ),
		'updated_at'     => $now,
	);

	$table = axismundi_cal_calendars_table();
	if ( is_array( $existing ) ) {
		$data['revision'] = (int) $existing['revision'] + 1;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- this plugin's own table.
		if ( false === $wpdb->update( $table, $data, array( 'id' => (int) $existing['id'] ) ) ) {
			return new WP_Error( 'ax_cal_write', __( 'The calendar could not be saved.', 'axismundi-calendar' ) );
		}
		return (int) $existing['id'];
	}

	$data['revision']   = 1;
	$data['created_at'] = $now;
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- this plugin's own table.
	if ( false === $wpdb->insert( $table, $data ) ) {
		return new WP_Error( 'ax_cal_write', __( 'The calendar could not be saved.', 'axismundi-calendar' ) );
	}
	return (int) $wpdb->insert_id;
}

/**
 * Delete a Calendar and its membership.
 *
 * The Events themselves are untouched. A Calendar is a collection, so removing it removes a way of
 * grouping Events and nothing else -- deleting the contents would make the two concepts one again.
 *
 * @param int $calendar_id Calendar id.
 * @return bool
 */
function axismundi_cal_calendar_delete( int $calendar_id ) : bool {
	global $wpdb;
	if ( $calendar_id <= 0 || ! axismundi_cal_ready() ) {
		return false;
	}
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- this plugin's own table.
	$wpdb->delete( axismundi_cal_items_table(), array( 'calendar_id' => $calendar_id ) );
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- this plugin's own table.
	return false !== $wpdb->delete( axismundi_cal_calendars_table(), array( 'id' => $calendar_id ) );
}

/**
 * The stable key for one membership.
 *
 * Derived from what identifies the member, which differs by kind: a local Event is its Object URI,
 * while a subscribed entry will be its source and UID. Hashing the pair keeps one unique key over
 * identities that have nothing structurally in common.
 *
 * @param string $member_type Member kind.
 * @param string $identity    Identifying string for that kind.
 * @return string
 */
function axismundi_cal_member_hash( string $member_type, string $identity ) : string {
	return hash( 'sha256', $member_type . "\n" . $identity );
}

/**
 * Add a local Event to a Calendar.
 *
 * Idempotent: adding the same Event twice leaves one membership, so a repeated request or a retried
 * import cannot produce a Calendar that lists an Event several times.
 *
 * @param int $calendar_id Calendar id.
 * @param int $post_id     Event post id.
 * @return true|WP_Error
 */
function axismundi_cal_add_event( int $calendar_id, int $post_id ) {
	global $wpdb;
	if ( ! axismundi_cal_ready() ) {
		return new WP_Error( 'ax_cal_store', __( 'The calendar store is unavailable.', 'axismundi-calendar' ) );
	}
	if ( null === axismundi_cal_calendar_get( $calendar_id ) ) {
		return new WP_Error( 'ax_cal_missing', __( 'That calendar does not exist.', 'axismundi-calendar' ), array( 'status' => 404 ) );
	}
	$post = get_post( $post_id );
	if ( ! $post instanceof WP_Post || AXISMUNDI_CAL_EVENT_POST_TYPE !== $post->post_type ) {
		return new WP_Error( 'ax_event_post', __( 'An Event post is required.', 'axismundi-calendar' ), array( 'status' => 400 ) );
	}

	$uri = function_exists( 'axismundi_cal_event_object_uri' ) ? axismundi_cal_event_object_uri( $post ) : '';
	if ( '' === $uri ) {
		// The Object URI is the member's identity. Falling back to the post id would make membership
		// meaningless the moment a member is something other than a local post.
		return new WP_Error( 'ax_cal_identity', __( 'That Event has no canonical address yet.', 'axismundi-calendar' ), array( 'status' => 400 ) );
	}

	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- this plugin's own table.
	$wpdb->replace(
		axismundi_cal_items_table(),
		array(
			'calendar_id'     => $calendar_id,
			'member_type'     => AXISMUNDI_CAL_MEMBER_LOCAL_EVENT,
			'member_hash'     => axismundi_cal_member_hash( AXISMUNDI_CAL_MEMBER_LOCAL_EVENT, $uri ),
			'object_uri'      => $uri,
			'object_uri_hash' => hash( 'sha256', $uri ),
			'event_post_id'   => (int) $post->ID,
			'created_at'      => current_time( 'mysql', true ),
		)
	);
	axismundi_cal_bump_revision( $calendar_id );
	return true;
}

/**
 * Remove a local Event from a Calendar.
 *
 * @param int $calendar_id Calendar id.
 * @param int $post_id     Event post id.
 * @return bool
 */
function axismundi_cal_remove_event( int $calendar_id, int $post_id ) : bool {
	global $wpdb;
	if ( ! axismundi_cal_ready() ) {
		return false;
	}
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- this plugin's own table.
	$removed = $wpdb->delete(
		axismundi_cal_items_table(),
		array( 'calendar_id' => $calendar_id, 'event_post_id' => $post_id, 'member_type' => AXISMUNDI_CAL_MEMBER_LOCAL_EVENT )
	);
	if ( $removed ) {
		axismundi_cal_bump_revision( $calendar_id );
	}
	return (bool) $removed;
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
 * The Event post ids a Calendar contains.
 *
 * Only members this site is the authority for. A subscribed entry appearing in a local Calendar is
 * a thing to display, never a thing to publish, and keeping that decision here means no caller has
 * to remember it.
 *
 * @param int $calendar_id Calendar id.
 * @return int[]
 */
function axismundi_cal_calendar_event_ids( int $calendar_id ) : array {
	global $wpdb;
	if ( $calendar_id <= 0 || ! axismundi_cal_ready() ) {
		return array();
	}
	$table = axismundi_cal_items_table();
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- membership lookup in this plugin's own table.
	return array_map(
		'intval',
		(array) $wpdb->get_col(
			$wpdb->prepare(
				"SELECT event_post_id FROM {$table} WHERE calendar_id = %d AND member_type = %s AND event_post_id IS NOT NULL",
				$calendar_id,
				AXISMUNDI_CAL_MEMBER_LOCAL_EVENT
			)
		)
	);
}

/**
 * The Calendars one Event belongs to.
 *
 * @param int $post_id Event post id.
 * @return array<int,array<string,mixed>>
 */
function axismundi_cal_event_calendars( int $post_id ) : array {
	global $wpdb;
	if ( $post_id <= 0 || ! axismundi_cal_ready() ) {
		return array();
	}
	$calendars = axismundi_cal_calendars_table();
	$items     = axismundi_cal_items_table();
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- membership lookup in this plugin's own tables.
	return (array) $wpdb->get_results(
		$wpdb->prepare(
			"SELECT c.* FROM {$calendars} c INNER JOIN {$items} i ON i.calendar_id = c.id WHERE i.event_post_id = %d ORDER BY c.name ASC",
			$post_id
		),
		ARRAY_A
	);
}

/**
 * Drop memberships when the Event they point at is deleted.
 *
 * Otherwise a Calendar keeps counting a member that no longer exists, and the count is the one thing
 * a collection is expected to be right about.
 *
 * @param int $post_id Post id.
 * @return void
 */
function axismundi_cal_forget_deleted_event( int $post_id ) : void {
	global $wpdb;
	if ( ! axismundi_cal_ready() ) {
		return;
	}
	$affected = axismundi_cal_event_calendars( $post_id );
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- this plugin's own table.
	$wpdb->delete( axismundi_cal_items_table(), array( 'event_post_id' => $post_id ) );
	foreach ( $affected as $calendar ) {
		axismundi_cal_bump_revision( (int) $calendar['id'] );
	}
}
add_action( 'deleted_post', 'axismundi_cal_forget_deleted_event', 10, 1 );
