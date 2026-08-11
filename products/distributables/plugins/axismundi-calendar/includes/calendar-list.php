<?php
/**
 * The CalendarList: which Actor stands in what relation to which Calendar.
 *
 * A Calendar is an independent resource. Ownership, write access and mere subscription are three
 * answers to one question -- what is this Actor to this Calendar? -- and they belong together in one
 * relation rather than as a column that could only express the first, and only for one Actor.
 *
 * The distinction that matters for subscribed feeds: a remote Calendar has no local owner. Its
 * authority is the source URL and whoever publishes it, so the Actor who subscribed is a `reader`.
 * Recording them as `owner` would claim this site can speak for somebody else's calendar.
 *
 * `selected` and `hidden` are the personal view state Google keeps on a CalendarList entry rather
 * than on the Calendar, and for the same reason: one person hiding a calendar must not hide it for
 * everyone.
 *
 * @package AxismundiCalendar
 */

defined( 'ABSPATH' ) || exit;

/** The relations an Actor can stand in to a Calendar. */
const AXISMUNDI_CAL_ACCESS_ROLES = array( 'owner', 'writer', 'reader' );

/**
 * Record or update one Actor's relation to a Calendar.
 *
 * Idempotent on `(calendar_id, actor_uri)`, so a repeated subscription or a rerun migration updates
 * the one row rather than adding a second relation for the same pair.
 *
 * @param int                 $calendar_id Calendar id.
 * @param string              $actor_uri   Actor URI.
 * @param string              $access_role One of owner|writer|reader.
 * @param array<string,mixed> $state       Optional `selected`, `hidden`, `summary_override`, `color`.
 * @return int|WP_Error Entry id.
 */
function axismundi_cal_list_set( int $calendar_id, string $actor_uri, string $access_role = 'reader', array $state = array() ) {
	global $wpdb;
	if ( ! axismundi_cal_ready() ) {
		return new WP_Error( 'ax_cal_store', __( 'The calendar store is unavailable.', 'axismundi-calendar' ) );
	}
	$actor_uri = trim( $actor_uri );
	if ( '' === $actor_uri ) {
		// An entry with no Actor names no relation, and would be indistinguishable from every other
		// entry with no Actor under the uniqueness key.
		return new WP_Error( 'ax_cal_list_actor', __( 'A calendar list entry needs an Actor.', 'axismundi-calendar' ), array( 'status' => 400 ) );
	}
	if ( ! in_array( $access_role, AXISMUNDI_CAL_ACCESS_ROLES, true ) ) {
		return new WP_Error( 'ax_cal_list_role', __( 'That is not a calendar access role.', 'axismundi-calendar' ), array( 'status' => 400 ) );
	}
	if ( null === axismundi_cal_calendar_get( $calendar_id ) ) {
		return new WP_Error( 'ax_cal_missing', __( 'That calendar does not exist.', 'axismundi-calendar' ), array( 'status' => 404 ) );
	}

	$hash     = hash( 'sha256', $actor_uri );
	$now      = current_time( 'mysql', true );
	$existing = axismundi_cal_list_entry( $calendar_id, $actor_uri );
	$table    = axismundi_cal_entries_list_table();

	$data = array(
		'calendar_id'    => $calendar_id,
		'actor_uri'      => $actor_uri,
		'actor_uri_hash' => $hash,
		'access_role'    => $access_role,
		'selected'       => array_key_exists( 'selected', $state ) ? (int) (bool) $state['selected'] : (int) ( $existing['selected'] ?? 1 ),
		'hidden'         => array_key_exists( 'hidden', $state ) ? (int) (bool) $state['hidden'] : (int) ( $existing['hidden'] ?? 0 ),
		// The name and colour one Actor gives a Calendar, which is theirs and not the Calendar's:
		// renaming a shared calendar in your own list must not rename it for everyone in it.
		'summary_override' => (string) ( $state['summary_override'] ?? ( $existing['summary_override'] ?? '' ) ),
		'color'          => (string) ( $state['color'] ?? ( $existing['color'] ?? '' ) ),
		'updated_at'     => $now,
	);

	if ( is_array( $existing ) ) {
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- this plugin's own table.
		$wpdb->update( $table, $data, array( 'id' => (int) $existing['id'] ) );
		return (int) $existing['id'];
	}
	$data['created_at'] = $now;
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- this plugin's own table.
	if ( false === $wpdb->insert( $table, $data ) ) {
		return new WP_Error( 'ax_cal_list_write', __( 'The calendar list entry could not be saved.', 'axismundi-calendar' ) );
	}
	return (int) $wpdb->insert_id;
}

/**
 * One Actor's entry for one Calendar.
 *
 * @param int    $calendar_id Calendar id.
 * @param string $actor_uri   Actor URI.
 * @return array<string,mixed>|null
 */
function axismundi_cal_list_entry( int $calendar_id, string $actor_uri ) : ?array {
	global $wpdb;
	$actor_uri = trim( $actor_uri );
	if ( $calendar_id <= 0 || '' === $actor_uri || ! axismundi_cal_ready() ) {
		return null;
	}
	$table = axismundi_cal_entries_list_table();
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- keyed lookup in this plugin's own table.
	$row = $wpdb->get_row(
		$wpdb->prepare( "SELECT * FROM {$table} WHERE calendar_id = %d AND actor_uri_hash = %s", $calendar_id, hash( 'sha256', $actor_uri ) ),
		ARRAY_A
	);
	return is_array( $row ) ? $row : null;
}

/**
 * Remove one Actor's relation to a Calendar.
 *
 * @param int    $calendar_id Calendar id.
 * @param string $actor_uri   Actor URI.
 * @return bool
 */
function axismundi_cal_list_remove( int $calendar_id, string $actor_uri ) : bool {
	global $wpdb;
	if ( ! axismundi_cal_ready() ) {
		return false;
	}
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- this plugin's own table.
	return (bool) $wpdb->delete(
		axismundi_cal_entries_list_table(),
		array( 'calendar_id' => $calendar_id, 'actor_uri_hash' => hash( 'sha256', trim( $actor_uri ) ) )
	);
}

/**
 * The Actor a Calendar belongs to, or '' when nobody owns it.
 *
 * @param int $calendar_id Calendar id.
 * @return string
 */
function axismundi_cal_calendar_owner( int $calendar_id ) : string {
	global $wpdb;
	if ( $calendar_id <= 0 || ! axismundi_cal_ready() ) {
		return '';
	}
	$table = axismundi_cal_entries_list_table();
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- keyed lookup in this plugin's own table.
	return (string) $wpdb->get_var( $wpdb->prepare( "SELECT actor_uri FROM {$table} WHERE calendar_id = %d AND access_role = 'owner' ORDER BY id ASC LIMIT 1", $calendar_id ) );
}

/**
 * Whether an Actor may change a Calendar.
 *
 * Owner and writer both may; a reader may not. Kept here rather than in the admin screen so that the
 * REST API and any later sharing UI answer the same question the same way.
 *
 * @param int    $calendar_id Calendar id.
 * @param string $actor_uri   Actor URI.
 * @return bool
 */
function axismundi_cal_actor_may_write( int $calendar_id, string $actor_uri ) : bool {
	$entry = axismundi_cal_list_entry( $calendar_id, $actor_uri );
	return is_array( $entry ) && in_array( (string) $entry['access_role'], array( 'owner', 'writer' ), true );
}

/**
 * Every entry for one Calendar.
 *
 * @param int $calendar_id Calendar id.
 * @return array<int,array<string,mixed>>
 */
function axismundi_cal_calendar_list_entries( int $calendar_id ) : array {
	global $wpdb;
	if ( $calendar_id <= 0 || ! axismundi_cal_ready() ) {
		return array();
	}
	$table = axismundi_cal_entries_list_table();
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- keyed lookup in this plugin's own table.
	return (array) $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} WHERE calendar_id = %d ORDER BY id ASC", $calendar_id ), ARRAY_A );
}

/**
 * The Calendars one Actor stands in some relation to.
 *
 * @param string $actor_uri Actor URI.
 * @param string $access_role Restrict to one access role, or '' for all.
 * @return array<int,array<string,mixed>> Calendar rows with the entry's role and view state.
 */
function axismundi_cal_actor_calendar_list( string $actor_uri, string $access_role = '' ) : array {
	global $wpdb;
	$actor_uri = trim( $actor_uri );
	if ( '' === $actor_uri || ! axismundi_cal_ready() ) {
		return array();
	}
	$entries   = axismundi_cal_entries_list_table();
	$calendars = axismundi_cal_calendars_table();
	$hash      = hash( 'sha256', $actor_uri );

	if ( '' !== $access_role ) {
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- keyed lookup in this plugin's own tables.
		return (array) $wpdb->get_results(
			$wpdb->prepare(
				"SELECT c.*, e.access_role, e.selected, e.hidden, e.summary_override, e.color FROM {$entries} e INNER JOIN {$calendars} c ON c.id = e.calendar_id
				 WHERE e.actor_uri_hash = %s AND e.access_role = %s ORDER BY c.name ASC",
				$hash,
				$access_role
			),
			ARRAY_A
		);
	}
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- keyed lookup in this plugin's own tables.
	return (array) $wpdb->get_results(
		$wpdb->prepare(
			"SELECT c.*, e.access_role, e.selected, e.hidden, e.summary_override, e.color FROM {$entries} e INNER JOIN {$calendars} c ON c.id = e.calendar_id
			 WHERE e.actor_uri_hash = %s ORDER BY c.name ASC",
			$hash
		),
		ARRAY_A
	);
}

/**
 * Every Calendar one Actor has any relation to, by id.
 *
 * The union of two tables, because they answer different halves of the question. A list entry is how
 * an Actor displays a Calendar; an ACL rule is what they may do with it. Being granted access does
 * not create an entry, so an Actor shared into a Calendar has a rule and no entry -- reading only the
 * entry table would leave that Calendar out of their own list, which is the case sharing exists for.
 *
 * The reverse also happens: an entry whose rule was revoked. That one is left here and dropped by
 * the caller, which is where the effective role is computed anyway.
 *
 * Calendars belonging to a managed Group this user administers are not included. That relation is
 * resolved per Calendar through Actors, and there is no reverse index to enumerate it from; those
 * Calendars are reachable by uuid and will join this list when they gain an entry.
 *
 * @param string $actor_uri Actor URI.
 * @return int[] Calendar ids.
 */
function axismundi_cal_actor_calendar_ids( string $actor_uri ) : array {
	global $wpdb;
	$actor_uri = trim( $actor_uri );
	if ( '' === $actor_uri || ! axismundi_cal_ready() ) {
		return array();
	}
	$hash    = hash( 'sha256', $actor_uri );
	$entries = axismundi_cal_entries_list_table();
	$acl     = axismundi_cal_acl_table();
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- keyed lookup in this plugin's own tables.
	$ids = (array) $wpdb->get_col(
		$wpdb->prepare(
			"SELECT calendar_id FROM {$entries} WHERE actor_uri_hash = %s
			 UNION
			 SELECT calendar_id FROM {$acl} WHERE principal_type = 'actor' AND principal_uri_hash = %s",
			$hash,
			$hash
		)
	);
	return array_values( array_unique( array_map( 'intval', $ids ) ) );
}

/**
 * Carry a renamed column's values across, without rebuilding the table.
 *
 * `dbDelta` adds `access_role` but cannot rename `role` into it, and the table must not be recreated
 * to force the shape: it stopped being derivable the moment it began holding things no other table
 * knows. A subscriber's `reader` entry on a remote Calendar, and everyone's `selected`, `hidden`,
 * alias and colour, exist here and nowhere else, so a rebuild reseeded from `owner_actor_uri` would
 * silently return owners and lose all the rest.
 *
 * Only empty targets are written, so running twice cannot overwrite a role somebody has since
 * changed. The old column is dropped in a later version, once this has been verified.
 *
 * @return int Number of entries carried across.
 */
function axismundi_cal_copy_legacy_access_roles() : int {
	global $wpdb;
	$table = axismundi_cal_entries_list_table();
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- one-time migration over this plugin's own table.
	$columns = (array) $wpdb->get_col( "SHOW COLUMNS FROM {$table}" );
	if ( ! in_array( 'role', $columns, true ) || ! in_array( 'access_role', $columns, true ) ) {
		return 0;
	}
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- as above.
	return (int) $wpdb->query( "UPDATE {$table} SET access_role = role WHERE ( access_role = '' OR access_role IS NULL ) AND role <> ''" );
}

/**
 * Whether the legacy access-role column is still present and still disagrees with its replacement.
 *
 * What gates dropping it. Reported rather than assumed, because after the drop there is nothing left
 * to compare against.
 *
 * @return array<string,mixed>
 */
function axismundi_cal_verify_access_role_migration() : array {
	global $wpdb;
	$table = axismundi_cal_entries_list_table();
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- migration verification.
	$columns = (array) $wpdb->get_col( "SHOW COLUMNS FROM {$table}" );
	if ( ! in_array( 'role', $columns, true ) ) {
		return array( 'ok' => true, 'legacy_column' => false, 'disagreeing' => array() );
	}
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- migration verification.
	$disagreeing = array_map( 'intval', (array) $wpdb->get_col( "SELECT id FROM {$table} WHERE role <> '' AND role <> access_role" ) );
	return array( 'ok' => empty( $disagreeing ), 'legacy_column' => true, 'disagreeing' => $disagreeing );
}

/**
 * Create the owner entry each local Calendar's column implies.
 *
 * The migration out of `owner_actor_uri`. Idempotent by the entry's own uniqueness, so a rerun
 * updates the row it already made rather than adding a second owner -- which is the failure that
 * would make "who owns this?" ambiguous in exactly the table built to answer it.
 *
 * Remote Calendars are skipped. A subscription does not make the subscriber the owner of somebody
 * else's calendar, and seeding one would encode that claim permanently.
 *
 * @return int Number of owner entries written.
 */
function axismundi_cal_seed_owner_entries() : int {
	global $wpdb;
	$calendars = axismundi_cal_calendars_table();
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- one-time migration over this plugin's own table.
	$columns = (array) $wpdb->get_col( "SHOW COLUMNS FROM {$calendars}" );
	if ( ! in_array( 'owner_actor_uri', $columns, true ) ) {
		// Already migrated and the column dropped. Nothing to read from.
		return 0;
	}
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- one-time migration over this plugin's own table.
	$rows = (array) $wpdb->get_results( "SELECT id, owner_actor_uri, kind FROM {$calendars} WHERE owner_actor_uri <> ''", ARRAY_A );

	$written = 0;
	foreach ( $rows as $row ) {
		if ( 'local' !== (string) $row['kind'] ) {
			continue;
		}
		$result = axismundi_cal_list_set( (int) $row['id'], (string) $row['owner_actor_uri'], 'owner' );
		if ( ! is_wp_error( $result ) ) {
			++$written;
		}
	}
	return $written;
}

/**
 * Whether the CalendarList is a faithful replacement for the column it came from.
 *
 * Run before the column is dropped, because after that there is nothing left to compare against.
 * Reports what is wrong rather than a bare boolean, so a failed migration says which Calendar.
 *
 * @return array<string,mixed> `ok`, plus the ids behind any disagreement.
 */
function axismundi_cal_verify_owner_migration() : array {
	global $wpdb;
	$calendars = axismundi_cal_calendars_table();
	$entries   = axismundi_cal_entries_list_table();

	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- migration verification over this plugin's own tables.
	$columns = (array) $wpdb->get_col( "SHOW COLUMNS FROM {$calendars}" );
	$legacy  = in_array( 'owner_actor_uri', $columns, true );

	$missing   = array();
	$mismatched = array();
	if ( $legacy ) {
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- migration verification.
		$rows = (array) $wpdb->get_results( "SELECT id, owner_actor_uri FROM {$calendars} WHERE kind = 'local' AND owner_actor_uri <> ''", ARRAY_A );
		foreach ( $rows as $row ) {
			$owner = axismundi_cal_calendar_owner( (int) $row['id'] );
			if ( '' === $owner ) {
				$missing[] = (int) $row['id'];
			} elseif ( $owner !== (string) $row['owner_actor_uri'] ) {
				$mismatched[] = (int) $row['id'];
			}
		}
	}

	// More than one owner is the ambiguity this table exists to remove, so it is checked directly
	// rather than assumed from the uniqueness key -- which constrains one Actor per Calendar, not one
	// owner per Calendar.
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- migration verification.
	$multiple = array_map( 'intval', (array) $wpdb->get_col( "SELECT calendar_id FROM {$entries} WHERE access_role = 'owner' GROUP BY calendar_id HAVING COUNT(*) > 1" ) );

	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- migration verification.
	$bad_hash = array_map( 'intval', (array) $wpdb->get_col( "SELECT id FROM {$entries} WHERE actor_uri_hash <> SHA2(actor_uri, 256)" ) );

	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- migration verification.
	$remote_owned = array_map(
		'intval',
		(array) $wpdb->get_col( "SELECT e.calendar_id FROM {$entries} e INNER JOIN {$calendars} c ON c.id = e.calendar_id WHERE e.access_role = 'owner' AND c.kind <> 'local'" )
	);

	return array(
		'ok'           => empty( $missing ) && empty( $mismatched ) && empty( $multiple ) && empty( $bad_hash ) && empty( $remote_owned ),
		'legacy_column' => $legacy,
		'missing'      => $missing,
		'mismatched'   => $mismatched,
		'multiple'     => $multiple,
		'bad_hash'     => $bad_hash,
		'remote_owned' => $remote_owned,
	);
}

/**
 * Drop a Calendar's entries with the Calendar.
 *
 * @param int $calendar_id Calendar id.
 * @return void
 */
function axismundi_cal_list_forget_calendar( int $calendar_id ) : void {
	global $wpdb;
	if ( ! axismundi_cal_ready() ) {
		return;
	}
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- this plugin's own table.
	$wpdb->delete( axismundi_cal_entries_list_table(), array( 'calendar_id' => $calendar_id ) );
}
