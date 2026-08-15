<?php
/**
 * Subscribed calendars: reading a remote iCalendar feed into a local cache.
 *
 * A subscription is a Calendar this site reads and does not own, and every rule follows from that.
 * Entries are read-only, are never re-published to other servers, and never appear in this site's
 * own iCalendar export. Two instances subscribing to one feed hold two local caches of somebody
 * else's events, not two copies of the same Object, and nothing here pretends otherwise.
 *
 * Absence is not deletion. A feed may carry a retention window -- WordCamp Central's carries
 * upcoming events only -- so an entry disappearing usually means it finished, not that it was
 * cancelled. Missing entries are marked missing and kept, and only an explicit `STATUS:CANCELLED`
 * means cancelled.
 *
 * The URL is attacker-influenced input by nature: an administrator pastes an address and this server
 * fetches it. That makes every subscription a request forgery vector, so fetching goes through
 * `wp_safe_remote_get()`, which validates the host on the initial request and again on each
 * redirect, and the response is bounded in size and time.
 *
 * @package AxismundiCalendar
 */

defined( 'ABSPATH' ) || exit;

/** How long a feed fetch may take. */
const AXISMUNDI_CAL_FETCH_TIMEOUT = 15;

/** The largest feed body accepted, in bytes. A national holiday calendar is tens of kilobytes. */
const AXISMUNDI_CAL_FETCH_MAX_BYTES = 5242880;

/**
 * Whether a URL may be fetched as a subscription source.
 *
 * `wp_http_validate_url()` is what refuses loopback, private and link-local addresses, and it is
 * also what the safe HTTP helpers use on every redirect hop -- so the check here and the check
 * during the request agree rather than being two opinions.
 *
 * @param string $url Candidate URL.
 * @return true|WP_Error
 */
function axismundi_cal_validate_source_url( string $url ) {
	$url    = trim( $url );
	$scheme = strtolower( (string) wp_parse_url( $url, PHP_URL_SCHEME ) );
	if ( ! in_array( $scheme, array( 'http', 'https' ), true ) ) {
		// `webcal:` is what calendar apps hand out, and it is the same document over HTTP. Rewritten
		// rather than refused would be friendlier, but it is the caller's job to be explicit here.
		return new WP_Error( 'ax_cal_source_scheme', __( 'A subscription address must start with http:// or https://.', 'axismundi-calendar' ), array( 'status' => 400 ) );
	}
	if ( false === wp_http_validate_url( $url ) ) {
		return new WP_Error(
			'ax_cal_source_url',
			__( 'That address cannot be fetched. Addresses on this machine or on a private network are refused.', 'axismundi-calendar' ),
			array( 'status' => 400 )
		);
	}

	/*
	 * Two overlapping checks follow, and the overlap is deliberate. Between them they are what
	 * refuses `localhost`; either one alone still does, which is the point of keeping both. Removing
	 * both leaves only `wp_http_validate_url()`, and that permits `localhost` whenever the site is
	 * itself served from it -- verified by disabling them.
	 *
	 * Stricter than `wp_http_validate_url()` on purpose. That function exempts URLs on the site's
	 * own host, which is reasonable for a plugin fetching its own API and wrong here: a site served
	 * from `localhost`, or from the same machine as anything else, would accept a subscription to
	 * itself and to whatever else answers on that interface. A calendar feed on a private address is
	 * never a legitimate subscription, so the exemption does not apply.
	 */
	$host = strtolower( trim( (string) wp_parse_url( $url, PHP_URL_HOST ), '[]' ) );
	if ( '' === $host || in_array( $host, array( 'localhost', 'localhost.localdomain', '::1' ), true ) || str_ends_with( $host, '.localhost' ) ) {
		return new WP_Error( 'ax_cal_source_private', __( 'That address is on this machine, so it cannot be a subscription.', 'axismundi-calendar' ), array( 'status' => 400 ) );
	}

	$addresses = filter_var( $host, FILTER_VALIDATE_IP ) ? array( $host ) : axismundi_cal_resolve_host( $host );
	if ( empty( $addresses ) ) {
		// Refused rather than allowed: a name that does not resolve cannot be fetched anyway, and
		// letting it through would mean the address check simply did not run.
		return new WP_Error( 'ax_cal_source_unresolvable', __( 'That address could not be resolved.', 'axismundi-calendar' ), array( 'status' => 400 ) );
	}
	foreach ( $addresses as $address ) {
		if ( ! filter_var( $address, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE ) ) {
			return new WP_Error( 'ax_cal_source_private', __( 'That address resolves to a private network, so it cannot be a subscription.', 'axismundi-calendar' ), array( 'status' => 400 ) );
		}
	}

	/*
	 * What this does not close: the name is resolved here and again by the HTTP client, so a name
	 * that answers differently between the two calls could still be pointed somewhere private. Fully
	 * closing that means connecting to a pinned address, which the HTTP layer does not offer.
	 * `wp_safe_remote_get()` at least re-validates every redirect hop, so the remaining window is one
	 * DNS answer wide rather than one redirect chain wide.
	 */
	return true;
}

/**
 * Resolve a hostname to its addresses.
 *
 * Both families, because a name with a harmless A record and a loopback AAAA record would otherwise
 * pass a check that only looked at one of them.
 *
 * @param string $host Hostname.
 * @return string[]
 */
function axismundi_cal_resolve_host( string $host ) : array {
	$addresses = array();
	// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- resolution failure is an answer here, not an error to report.
	$ipv4 = @gethostbynamel( $host );
	if ( is_array( $ipv4 ) ) {
		$addresses = $ipv4;
	}
	if ( defined( 'DNS_AAAA' ) ) {
		// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- as above.
		$ipv6 = @dns_get_record( $host, DNS_AAAA );
		foreach ( (array) $ipv6 as $record ) {
			if ( ! empty( $record['ipv6'] ) ) {
				$addresses[] = (string) $record['ipv6'];
			}
		}
	}
	return array_values( array_unique( array_filter( $addresses ) ) );
}

/**
 * The source whose read-only Calendar this is.
 *
 * @param int $calendar_id Calendar id.
 * @return array<string,mixed>|null
 */
function axismundi_cal_source_for_calendar( int $calendar_id ) : ?array {
	global $wpdb;
	if ( $calendar_id <= 0 || ! axismundi_cal_ready() ) {
		return null;
	}
	$table = axismundi_cal_sources_table();
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- keyed lookup in this plugin's own table.
	$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE calendar_id = %d", $calendar_id ), ARRAY_A );
	return is_array( $row ) ? $row : null;
}

/**
 * One source by id.
 *
 * @param int $source_id Source id.
 * @return array<string,mixed>|null
 */
function axismundi_cal_source_get( int $source_id ) : ?array {
	global $wpdb;
	if ( $source_id <= 0 || ! axismundi_cal_ready() ) {
		return null;
	}
	$table = axismundi_cal_sources_table();
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- primary-key lookup in this plugin's own table.
	$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $source_id ), ARRAY_A );
	return is_array( $row ) ? $row : null;
}

/**
 * Subscribe this instance to a remote Calendar.
 *
	* @param string $url         Feed URL.
 * @return int|WP_Error Remote Calendar id.
 */
/**
 * The Calendar on this site that an address names, if it names one.
 *
 * Both forms it can be given in: the page and the feed. Matched on this site's own host so that a
 * calendar somewhere else with a similar path is still a subscription.
 *
 * @param string $url Address somebody typed.
 * @return array<string,mixed>|null Calendar row.
 */
function axismundi_cal_local_calendar_for_url( string $url ) : ?array {
	$url  = trim( $url );
	$host = (string) wp_parse_url( $url, PHP_URL_HOST );
	if ( '' === $host || $host !== (string) wp_parse_url( home_url( '/' ), PHP_URL_HOST ) ) {
		return null;
	}
	$path = (string) wp_parse_url( $url, PHP_URL_PATH );
	if ( 1 !== preg_match( '#/calendar/([^/]+?)(?:\.ics)?/?$#', $path, $matches ) ) {
		return null;
	}
	return axismundi_cal_calendar_by_slug( rawurldecode( (string) $matches[1] ) );
}

function axismundi_cal_subscribe_url( string $url ) {
	global $wpdb;
	if ( ! axismundi_cal_ready() ) {
		return new WP_Error( 'ax_cal_store', __( 'The calendar store is unavailable.', 'axismundi-calendar' ) );
	}
	$url = trim( $url );

	/*
	 * An address on this site is not a feed to mirror. Mirroring our own calendar would make a second
	 * copy that goes stale, publishes under a different id, and answers to nobody -- so the address is
	 * resolved to the Calendar it names and taken into the reader's list instead.
	 *
	 * And it is taken only if they may read it. A private calendar cannot be subscribed to by knowing
	 * its address: that is what an invitation is for, and letting the address stand in for one would
	 * make sharing decorative.
	 */
	$local = axismundi_cal_local_calendar_for_url( $url );
	if ( is_array( $local ) ) {
		$actor_uri = axismundi_cal_authoring_actor_uri();
		if ( ! axismundi_cal_can_read( (int) $local['id'], $actor_uri, get_current_user_id() ) ) {
			return new WP_Error(
				'ax_cal_subscribe_private',
				__( 'That calendar is not public. Ask its owner to share it with you; an invitation is what adds it to your list.', 'axismundi-calendar' ),
				array( 'status' => 403 )
			);
		}
		if ( '' === $actor_uri ) {
			return new WP_Error( 'ax_cal_subscribe_actor', __( 'Adding a calendar to your list needs an Actor profile.', 'axismundi-calendar' ), array( 'status' => 409 ) );
		}
		$entry = axismundi_cal_list_set( (int) $local['id'], $actor_uri );
		return is_wp_error( $entry ) ? $entry : (int) $local['id'];
	}

	/*
	 * Only now, because this validates an address this site is about to *fetch*: it refuses private and
	 * loopback hosts, which is exactly right for a foreign feed and exactly wrong for our own calendar,
	 * whose address is resolved above without any request being made at all.
	 */
	$valid = axismundi_cal_validate_source_url( $url );
	if ( is_wp_error( $valid ) ) {
		return $valid;
	}

	$hash = hash( 'sha256', $url );
	$now  = current_time( 'mysql', true );

	$existing = axismundi_cal_source_by_hash( $hash );
	if ( is_array( $existing ) ) {
		$calendar = axismundi_cal_calendar_get( (int) $existing['calendar_id'] );
		if ( is_array( $calendar ) && 'remote' === (string) $calendar['kind'] ) {
			return (int) $existing['calendar_id'];
		}
		return new WP_Error( 'ax_cal_source_corrupt', __( 'This subscription needs repair before it can be used.', 'axismundi-calendar' ) );
	}
	$host     = (string) wp_parse_url( $url, PHP_URL_HOST );
	$slug     = 'subscription-' . substr( $hash, 0, 12 );
	$orphan   = axismundi_cal_calendar_by_slug( $slug );
	$created  = false;
	if ( is_array( $orphan ) && 'remote' === (string) $orphan['kind'] && $url === (string) $orphan['description'] && null === axismundi_cal_source_for_calendar( (int) $orphan['id'] ) ) {
		// A request can stop between creating the remote Calendar and its source row. Its deterministic
		// slug makes that partial write safely recoverable instead of turning a retry into a conflict.
		$calendar = (int) $orphan['id'];
	} else {
		$calendar = axismundi_cal_calendar_save(
			array(
				'name'        => '' !== $host ? $host : __( 'Subscribed calendar', 'axismundi-calendar' ),
				'slug'        => $slug,
				'description' => $url,
				'timezone'    => 'UTC',
				'kind'        => 'remote',
			)
		);
		if ( is_wp_error( $calendar ) ) {
			return $calendar;
		}
		$created = true;
	}

	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- this plugin's own table.
	$inserted = $wpdb->insert(
		axismundi_cal_sources_table(),
		array(
			'calendar_id'     => (int) $calendar,
			'kind'            => 'ical',
			// Stated in the row, because everything downstream -- no re-publishing, no export, no
			// editing -- is decided by it rather than by remembering where a row came from.
			'authority'       => 'remote',
			'source_url'      => $url,
			'source_url_hash' => $hash,
			'sync_error'      => '',
			'created_at'      => $now,
			'updated_at'      => $now,
		)
	);
	if ( false === $inserted ) {
		if ( $created ) {
			// The source row never existed, so this is the same safe recovery path as the orphan above.
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- cleanup of this just-created remote Calendar.
			$wpdb->delete( axismundi_cal_calendars_table(), array( 'id' => (int) $calendar, 'kind' => 'remote' ) );
		}
		return new WP_Error( 'ax_cal_source_write', __( 'The subscription could not be saved.', 'axismundi-calendar' ) );
	}
	return (int) $calendar;
}

/**
 * A source by URL hash.
 *
	* @param string $hash        URL hash.
 * @return array<string,mixed>|null
 */
function axismundi_cal_source_by_hash( string $hash ) : ?array {
	global $wpdb;
	$table = axismundi_cal_sources_table();
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- keyed lookup in this plugin's own table.
	$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE source_url_hash = %s", $hash ), ARRAY_A );
	return is_array( $row ) ? $row : null;
}

/**
 * Detach a source and its cached entries.
 *
 * Removing a subscription removes this site's copy of somebody else's calendar. Nothing is deleted
 * anywhere else, and no local Event is affected, because none was ever created from it.
 *
 * @param int $source_id Source id.
 * @return bool
 */
function axismundi_cal_remove_source( int $source_id ) : bool {
	global $wpdb;
	$source = axismundi_cal_source_get( $source_id );
	if ( null === $source ) {
		return false;
	}
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- this plugin's own table.
	$wpdb->delete( axismundi_cal_entries_table(), array( 'source_id' => $source_id ) );
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- this plugin's own table.
	$removed = $wpdb->delete( axismundi_cal_sources_table(), array( 'id' => $source_id ) );
	if ( $removed ) {
		// `calendar_delete()` deliberately refuses remote Calendars: deleting one independently would
		// orphan its source. The source is gone here, so this is the single path that removes its
		// read-only Calendar representation too.
		// Whoever subscribed has an entry pointing at that Calendar, and this path does not go through
		// `calendar_delete()`, so the relations are dropped here rather than left naming nothing.
		axismundi_cal_list_forget_calendar( (int) $source['calendar_id'] );
		axismundi_cal_acl_forget_calendar( (int) $source['calendar_id'] );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- paired deletion of this plugin's source representation.
		$wpdb->delete( axismundi_cal_calendars_table(), array( 'id' => (int) $source['calendar_id'], 'kind' => 'remote' ) );
	}
	return (bool) $removed;
}

/**
 * Fetch a source and refresh its cache.
 *
 * @param int  $source_id Source id.
 * @param bool $force     Ignore the stored validators and re-parse regardless.
 * @return array{status:string,entries:int,missing:int}|WP_Error
 */
function axismundi_cal_sync_source( int $source_id, bool $force = false ) {
	global $wpdb;
	$source = axismundi_cal_source_get( $source_id );
	if ( null === $source ) {
		return new WP_Error( 'ax_cal_source_missing', __( 'That subscription does not exist.', 'axismundi-calendar' ), array( 'status' => 404 ) );
	}
	$url   = (string) $source['source_url'];
	$valid = axismundi_cal_validate_source_url( $url );
	if ( is_wp_error( $valid ) ) {
		// Re-checked at fetch time, not only when it was added: a hostname can be repointed at a
		// private address after the fact, and the stored row is not evidence that it is still safe.
		axismundi_cal_record_sync( $source_id, 'error', $valid->get_error_message() );
		return $valid;
	}

	$headers = array( 'Accept' => 'text/calendar, text/plain;q=0.5' );
	if ( ! $force && '' !== (string) $source['etag'] ) {
		$headers['If-None-Match'] = (string) $source['etag'];
	}
	if ( ! $force && '' !== (string) $source['last_modified'] ) {
		$headers['If-Modified-Since'] = (string) $source['last_modified'];
	}

	$response = wp_safe_remote_get(
		$url,
		array(
			'timeout'             => AXISMUNDI_CAL_FETCH_TIMEOUT,
			'redirection'         => 3,
			'limit_response_size' => AXISMUNDI_CAL_FETCH_MAX_BYTES,
			'headers'             => $headers,
			'user-agent'          => 'Axismundi Calendar/' . AXISMUNDI_CAL_VERSION . '; ' . home_url( '/' ),
		)
	);
	if ( is_wp_error( $response ) ) {
		axismundi_cal_record_sync( $source_id, 'error', $response->get_error_message() );
		return $response;
	}

	$code = (int) wp_remote_retrieve_response_code( $response );
	if ( 304 === $code ) {
		axismundi_cal_record_sync( $source_id, 'unchanged', '' );
		return array( 'status' => 'unchanged', 'entries' => 0, 'missing' => 0 );
	}
	if ( $code < 200 || $code >= 300 ) {
		axismundi_cal_record_sync( $source_id, 'error', sprintf( /* translators: %d: HTTP status code. */ __( 'The feed answered %d.', 'axismundi-calendar' ), $code ) );
		return new WP_Error( 'ax_cal_source_http', sprintf( /* translators: %d: HTTP status code. */ __( 'The feed answered %d.', 'axismundi-calendar' ), $code ), array( 'status' => 502 ) );
	}

	$body = (string) wp_remote_retrieve_body( $response );
	if ( ! str_contains( $body, 'BEGIN:VCALENDAR' ) ) {
		// A login page or an error document served with 200. Refused rather than parsed into zero
		// entries, which would look exactly like a feed that had emptied.
		axismundi_cal_record_sync( $source_id, 'error', __( 'That address did not return an iCalendar document.', 'axismundi-calendar' ) );
		return new WP_Error( 'ax_cal_source_body', __( 'That address did not return an iCalendar document.', 'axismundi-calendar' ), array( 'status' => 422 ) );
	}

	$hash = hash( 'sha256', $body );
	if ( ! $force && $hash === (string) $source['content_hash'] ) {
		// The content-hash fallback. Many publishers send neither validator, so without this every
		// poll would re-parse and rewrite an unchanged document.
		axismundi_cal_record_sync( $source_id, 'unchanged', '', wp_remote_retrieve_header( $response, 'etag' ), wp_remote_retrieve_header( $response, 'last-modified' ), $hash );
		return array( 'status' => 'unchanged', 'entries' => 0, 'missing' => 0 );
	}

	$entries = axismundi_cal_ics_parse( $body );
	$now     = current_time( 'mysql', true );
	$seen    = array();
	$table   = axismundi_cal_entries_table();

	foreach ( $entries as $entry ) {
		$entry_hash   = hash( 'sha256', $entry['ical_uid'] . "\n" . $entry['recurrence_id'] );
		$seen[]       = $entry_hash;
		$row          = array_merge(
			$entry,
			array(
				'source_id'    => $source_id,
				'entry_hash'   => $entry_hash,
				'presence'     => 'present',
				'last_seen_at' => $now,
				'updated_at'   => $now,
				'created_at'   => $now,
			)
		);
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- this plugin's own table.
		$wpdb->replace( $table, $row );
	}

	/*
	 * Anything not in this snapshot is marked missing rather than deleted. A feed with a retention
	 * window drops finished events, and reading that as cancellation would tell a reader an event
	 * was called off when it simply happened.
	 */
	$missing = 0;
	if ( ! empty( $seen ) ) {
		$placeholders = implode( ',', array_fill( 0, count( $seen ), '%s' ) );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- this plugin's own table.
		$missing = (int) $wpdb->query(
			$wpdb->prepare(
				"UPDATE {$table} SET presence = 'missing', updated_at = %s WHERE source_id = %d AND presence = 'present' AND entry_hash NOT IN ({$placeholders})",
				array_merge( array( $now, $source_id ), $seen )
			)
		);
	}

	axismundi_cal_record_sync(
		$source_id,
		'ok',
		'',
		wp_remote_retrieve_header( $response, 'etag' ),
		wp_remote_retrieve_header( $response, 'last-modified' ),
		$hash
	);
	axismundi_cal_bump_revision( (int) $source['calendar_id'] );
	return array( 'status' => 'ok', 'entries' => count( $entries ), 'missing' => $missing );
}

/**
 * Record the outcome of a sync attempt.
 *
 * A failed attempt updates when it was tried but leaves the last success alone, so a feed that has
 * been broken for a week can say so instead of looking as though it just synced.
 *
 * @param int    $source_id     Source id.
 * @param string $status        Sync status.
 * @param string $error         Error message, or ''.
 * @param string $etag          Response ETag.
 * @param string $last_modified Response Last-Modified.
 * @param string $hash          Body hash.
 * @return void
 */
function axismundi_cal_record_sync( int $source_id, string $status, string $error, string $etag = '', string $last_modified = '', string $hash = '' ) : void {
	global $wpdb;
	$now  = current_time( 'mysql', true );
	$data = array(
		'sync_status'     => $status,
		'sync_error'      => $error,
		'last_checked_at' => $now,
		'updated_at'      => $now,
	);
	if ( 'error' !== $status ) {
		$data['last_success_at'] = $now;
	}
	if ( '' !== $etag ) {
		$data['etag'] = $etag;
	}
	if ( '' !== $last_modified ) {
		$data['last_modified'] = $last_modified;
	}
	if ( '' !== $hash ) {
		$data['content_hash'] = $hash;
	}
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- this plugin's own table.
	$wpdb->update( axismundi_cal_sources_table(), $data, array( 'id' => $source_id ) );
}

/**
 * Cached entries of a Calendar's subscriptions within a range.
 *
 * Entries carrying a rule this engine cannot expand are returned as they stand rather than expanded,
 * so a series appears once at its own start instead of being given dates nobody published.
 *
 * @param int    $calendar_id Calendar id.
 * @param string $from_utc    Range start, UTC.
 * @param string $to_utc      Range end, UTC.
 * @return array<int,array<string,mixed>>
 */
function axismundi_cal_subscribed_entries( int $calendar_id, string $from_utc, string $to_utc ) : array {
	global $wpdb;
	if ( $calendar_id <= 0 || ! axismundi_cal_ready() ) {
		return array();
	}
	$sources = axismundi_cal_sources_table();
	$entries = axismundi_cal_entries_table();
	// Missing entries are excluded from display but kept in the table: absence from the feed is a
	// fact worth remembering, and not the same as the event having been called off.
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- range query over this plugin's own tables.
	return (array) $wpdb->get_results(
		$wpdb->prepare(
			"SELECT e.*, s.source_url FROM {$entries} e INNER JOIN {$sources} s ON s.id = e.source_id
			 WHERE s.calendar_id = %d AND e.presence = 'present' AND e.end_utc > %s AND e.start_utc < %s
			 ORDER BY e.start_utc ASC",
			$calendar_id,
			$from_utc,
			$to_utc
		),
		ARRAY_A
	);
}
