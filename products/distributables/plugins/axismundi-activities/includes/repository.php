<?php
/**
 * Phase 1 - immutable URI-keyed Activity repository.
 *
 * @package AxismundiActivities
 */

defined( 'ABSPATH' ) || exit;

const AXISMUNDI_ACT_DB_VERSION        = '9';
const AXISMUNDI_ACT_DB_VERSION_OPTION = 'axismundi_activities_db_version';
const AXISMUNDI_ACT_PAYLOAD_MAX       = 1048576;

/** Activity ledger table. */
function axismundi_act_activities_table() : string {
	global $wpdb;
	return $wpdb->prefix . 'ax_activities';
}

/** Install and verify the Phase 1 schema. */
function axismundi_act_install() : bool {
	global $wpdb;
	require_once ABSPATH . 'wp-admin/includes/upgrade.php';

	$table            = axismundi_act_activities_table();
	$charset          = $wpdb->get_charset_collate();
	$previous_version = (string) get_option( AXISMUNDI_ACT_DB_VERSION_OPTION, '' );
	dbDelta(
		"CREATE TABLE {$table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			local_uuid char(36) DEFAULT NULL,
			activity_uri text NOT NULL,
			activity_uri_hash char(64) NOT NULL,
			activity_type varchar(32) NOT NULL,
			actor_uri text NOT NULL,
			actor_uri_hash char(64) NOT NULL,
			object_uri text DEFAULT NULL,
			object_uri_hash char(64) DEFAULT NULL,
			target_uri text DEFAULT NULL,
			target_uri_hash char(64) DEFAULT NULL,
			instrument_uri text DEFAULT NULL,
			instrument_uri_hash char(64) DEFAULT NULL,
			source_event_key text DEFAULT NULL,
			source_event_hash char(64) DEFAULT NULL,
			direction varchar(8) NOT NULL,
			effective_status varchar(8) NOT NULL DEFAULT 'active',
			has_public_audience tinyint(1) NOT NULL DEFAULT 0,
			reaction_key varchar(191) DEFAULT NULL,
			reaction_key_hash char(64) DEFAULT NULL,
			reaction_raw varchar(191) DEFAULT NULL,
			audience_json longtext NOT NULL,
			payload_json longtext NOT NULL,
			payload_hash char(64) NOT NULL,
			published_at datetime DEFAULT NULL,
			received_at datetime DEFAULT NULL,
			feed_sort_at datetime DEFAULT NULL,
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY activity_uri_hash (activity_uri_hash),
			UNIQUE KEY local_uuid (local_uuid),
			UNIQUE KEY source_event_hash (source_event_hash),
			KEY actor_uri_hash (actor_uri_hash),
			KEY object_uri_hash (object_uri_hash),
			KEY target_uri_hash (target_uri_hash),
			KEY instrument_uri_hash (instrument_uri_hash),
			KEY feed_cursor (actor_uri_hash, effective_status, feed_sort_at, id),
			KEY feed_public_cursor (actor_uri_hash, effective_status, has_public_audience, feed_sort_at, id),
			KEY direction_status (direction, effective_status),
			KEY reaction_chip (object_uri_hash, effective_status, reaction_key_hash, actor_uri_hash)
		) ENGINE=InnoDB {$charset};"
	);

	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fixed custom schema verification.
	$columns = (array) $wpdb->get_col( "SHOW COLUMNS FROM {$table}" );
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fixed custom index verification.
	$uri_index = (array) $wpdb->get_results( "SHOW INDEX FROM {$table} WHERE Key_name = 'activity_uri_hash'", ARRAY_A );
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fixed custom index verification.
	$uuid_index = (array) $wpdb->get_results( "SHOW INDEX FROM {$table} WHERE Key_name = 'local_uuid'", ARRAY_A );
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fixed custom index verification.
	$source_index = (array) $wpdb->get_results( "SHOW INDEX FROM {$table} WHERE Key_name = 'source_event_hash'", ARRAY_A );
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fixed custom index verification.
	$instrument_index = (array) $wpdb->get_results( "SHOW INDEX FROM {$table} WHERE Key_name = 'instrument_uri_hash'", ARRAY_A );
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fixed custom index verification.
	$reaction_index = (array) $wpdb->get_results( "SHOW INDEX FROM {$table} WHERE Key_name = 'reaction_chip'", ARRAY_A );
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fixed feed cursor index verification.
	$feed_index = (array) $wpdb->get_results( "SHOW INDEX FROM {$table} WHERE Key_name = 'feed_cursor'", ARRAY_A );
	usort( $feed_index, static fn( array $left, array $right ) : int => (int) $left['Seq_in_index'] <=> (int) $right['Seq_in_index'] );
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fixed candidate index verification.
	$public_feed_index = (array) $wpdb->get_results( "SHOW INDEX FROM {$table} WHERE Key_name = 'feed_public_cursor'", ARRAY_A );
	usort( $public_feed_index, static fn( array $left, array $right ) : int => (int) $left['Seq_in_index'] <=> (int) $right['Seq_in_index'] );
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fixed table engine verification.
	$engine = (string) $wpdb->get_var( $wpdb->prepare( 'SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = %s', $table ) );
	if ( 'InnoDB' !== $engine && ! empty( $columns ) ) {
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange -- one-off custom table engine correction.
		$wpdb->query( "ALTER TABLE {$table} ENGINE=InnoDB" );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- verify engine correction.
		$engine = (string) $wpdb->get_var( $wpdb->prepare( 'SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = %s', $table ) );
	}

	$base_valid = in_array( 'activity_uri', $columns, true )
		&& in_array( 'actor_uri', $columns, true )
		&& in_array( 'payload_hash', $columns, true )
		&& in_array( 'effective_status', $columns, true )
		&& in_array( 'source_event_key', $columns, true )
		// v5. Verified before the version is stored: a site that recorded v5 while dbDelta
		// failed to add the column would never retry, and every QuoteRequest would silently
		// lose its instrument.
		&& in_array( 'instrument_uri', $columns, true )
		&& in_array( 'instrument_uri_hash', $columns, true )
		&& ! empty( $instrument_index )
		// v7, verified for the same reason. Without the column every `Like` reads as a plain
		// Like, so a site that stored v7 on a failed migration would count every emoji
		// reaction as a favourite and never try again.
		&& in_array( 'reaction_key', $columns, true )
		&& in_array( 'reaction_key_hash', $columns, true )
		&& in_array( 'reaction_raw', $columns, true )
		&& in_array( 'feed_sort_at', $columns, true )
		&& in_array( 'has_public_audience', $columns, true )
		// The chip aggregate's covering index, verified for a quieter reason: without it
		// every chip query still returns the right answer, by scanning. Nothing looks broken
		// until an object collects thousands of reactions, and by then the version is stored.
		&& ! empty( $reaction_index )
		&& array( 'actor_uri_hash', 'effective_status', 'feed_sort_at', 'id' ) === array_column( $feed_index, 'Column_name' )
		&& array( 'actor_uri_hash', 'effective_status', 'has_public_audience', 'feed_sort_at', 'id' ) === array_column( $public_feed_index, 'Column_name' )
		&& ! in_array( 'blog_id', $columns, true )
		&& ! empty( $uri_index )
		&& 0 === (int) $uri_index[0]['Non_unique']
		&& ! empty( $uuid_index )
		&& 0 === (int) $uuid_index[0]['Non_unique']
		&& ! empty( $source_index )
		&& 0 === (int) $source_index[0]['Non_unique']
		&& 'InnoDB' === $engine;
	$relations_valid = function_exists( 'axismundi_act_install_relations' ) && axismundi_act_install_relations();
	$quotes_valid    = function_exists( 'axismundi_act_install_quote_authorizations' ) && axismundi_act_install_quote_authorizations();
	$valid           = $base_valid && $relations_valid && $quotes_valid;
	if ( $valid && version_compare( $previous_version, '8', '<' ) ) {
		$valid = axismundi_act_backfill_feed_sort_at();
	}
	if ( $valid && version_compare( $previous_version, '9', '<' ) ) {
		$valid = axismundi_act_backfill_public_audience();
	}
	if ( $valid ) {
		update_option( AXISMUNDI_ACT_DB_VERSION_OPTION, AXISMUNDI_ACT_DB_VERSION, false );
	}
	return $valid;
}

/** Upgrade without requiring reactivation. */
function axismundi_act_maybe_upgrade() : void {
	if ( AXISMUNDI_ACT_DB_VERSION !== (string) get_option( AXISMUNDI_ACT_DB_VERSION_OPTION, '' ) ) {
		axismundi_act_install();
	}
}
add_action( 'plugins_loaded', 'axismundi_act_maybe_upgrade', 20 );

/** Immutable Activity value object. */
final class Axismundi_Activity {
	/** @var array<string,mixed> */
	private array $row;

	/** @param array<string,mixed> $row Repository row. */
	private function __construct( array $row ) {
		$this->row = $row;
	}

	/** @param array<string,mixed> $row Repository row. */
	public static function from_row( array $row ) : self {
		return new self( $row );
	}

	public function get_id() : int { return (int) $this->row['id']; }
	public function get_uri() : string { return (string) $this->row['activity_uri']; }
	public function get_local_uuid() : ?string { return null !== $this->row['local_uuid'] ? (string) $this->row['local_uuid'] : null; }
	public function get_type() : string { return (string) $this->row['activity_type']; }
	public function get_actor_uri() : string { return (string) $this->row['actor_uri']; }
	public function get_object_uri() : ?string { return null !== $this->row['object_uri'] ? (string) $this->row['object_uri'] : null; }
	public function get_target_uri() : ?string { return null !== $this->row['target_uri'] ? (string) $this->row['target_uri'] : null; }
	/** The AS2 `instrument`: for a QuoteRequest, the independent Object doing the quoting. */
	public function get_instrument_uri() : ?string { return null !== ( $this->row['instrument_uri'] ?? null ) ? (string) $this->row['instrument_uri'] : null; }
	public function get_direction() : string { return (string) $this->row['direction']; }
	public function get_effective_status() : string { return (string) $this->row['effective_status']; }
	public function is_effective() : bool { return 'active' === $this->get_effective_status(); }
	/** @return array<string,string[]> */
	public function get_audience() : array { return (array) $this->row['audience']; }
	/** @return array<string,mixed> */
	public function get_payload() : array { return (array) $this->row['payload']; }
	public function get_published_at() : ?string { return null !== $this->row['published_at'] ? (string) $this->row['published_at'] : null; }
	/**
	 * The time the ledger orders by.
	 *
	 * `feed_sort_at` materializes the published/received/created priority at write time. It keeps
	 * a continuation cursor on the exact same immutable key as the indexed ORDER BY. The fallback
	 * only protects hydrated legacy fixture rows while the v8 upgrade backfills the column.
	 */
	public function get_effective_time() : string {
		$sort_at = trim( (string) ( $this->row['feed_sort_at'] ?? '' ) );
		if ( '' !== $sort_at ) {
			return $sort_at;
		}
		foreach ( array( 'published_at', 'received_at', 'created_at' ) as $column ) {
			$value = $this->row[ $column ] ?? null;
			if ( null !== $value && '' !== (string) $value ) {
				return (string) $value;
			}
		}
		return '';
	}
}

/** Absolute HTTP(S) URI validation without performing a request. */
function axismundi_act_uri( $value ) : string {
	$uri   = is_scalar( $value ) ? trim( (string) $value ) : '';
	$parts = wp_parse_url( $uri );
	if ( ! is_array( $parts )
		|| ! in_array( strtolower( (string) ( $parts['scheme'] ?? '' ) ), array( 'http', 'https' ), true )
		|| empty( $parts['host'] )
		|| isset( $parts['user'] )
		|| isset( $parts['pass'] )
	) {
		return '';
	}
	return $uri;
}

/** First URI represented by a scalar, object, or list. */
function axismundi_act_member_uri( $value ) : string {
	if ( is_scalar( $value ) ) {
		return axismundi_act_uri( $value );
	}
	if ( is_array( $value ) && array_is_list( $value ) ) {
		foreach ( $value as $member ) {
			$uri = axismundi_act_member_uri( $member );
			if ( '' !== $uri ) {
				return $uri;
			}
		}
		return '';
	}
	if ( is_array( $value ) ) {
		return axismundi_act_member_uri( $value['id'] ?? $value['href'] ?? '' );
	}
	return '';
}

/** Supported Activity types. */
function axismundi_act_types() : array {
	/*
	 * `Dislike` is accepted for the same reason `Block` and `Flag` are: the ledger records what
	 * happened, and what any product chooses to display is a separate question. A Lemmy
	 * downvote on one of our Topics arrives whether or not a community feature is installed,
	 * and refusing it here would not stop the vote — it would only lose the record of it, so
	 * the same object's score would depend on which plugins happened to be active when the
	 * Activity landed.
	 *
	 * Recording is not endorsement, and it is not a score. ActivityStreams does not make Like
	 * and Dislike cancel each other; "one vote per person" is a community rule, so the mutual
	 * exclusion and the tally belong to whichever product owns that community.
	 */
	$types = array( 'Follow', 'Accept', 'Reject', 'Undo', 'Like', 'Dislike', 'EmojiReact', 'Announce', 'QuoteRequest', 'Create', 'Update', 'Delete', 'Add', 'Remove', 'Move', 'Join', 'Leave', 'Block', 'Flag' );
	/** @param string[] $types Supported ActivityStreams activity types. */
	return array_values( array_unique( array_filter( array_map( 'sanitize_text_field', (array) apply_filters( 'axismundi_act_types', $types ) ) ) ) );
}

/**
 * First supported Activity type from a scalar/list, canonicalized.
 *
 * `EmojiReact` reaches us in two spellings: the bare term FEP-c0e0 uses, and the LitePub
 * IRI Akkoma sends. Matching exact strings would accept one and silently drop the other,
 * with no error anywhere — a whole implementation's reactions would simply not exist. The
 * fragment of a known extension IRI is the term it names, so the two converge before the
 * vocabulary is consulted.
 *
 * @param mixed $value Type term, IRI, or list of either.
 * @return string
 */
function axismundi_act_type( $value ) : string {
	$supported = axismundi_act_types();
	foreach ( is_array( $value ) ? $value : array( $value ) as $type ) {
		$type = is_scalar( $type ) ? substr( sanitize_text_field( (string) $type ), 0, 128 ) : '';
		if ( '' === $type ) {
			continue;
		}
		if ( in_array( $type, $supported, true ) ) {
			return $type;
		}
		// Only the fragment of an absolute IRI, so a term is never invented from arbitrary text.
		if ( 1 === preg_match( '~^https?://\S+#([A-Za-z]+)$~', $type, $matches ) && in_array( $matches[1], $supported, true ) ) {
			return $matches[1];
		}
	}
	return '';
}

/**
 * The single `Emoji` tag a custom reaction declares, if it carries one.
 *
 * A custom reaction's `content` is a shortcode, which names nothing on its own: two
 * servers both ship `:misskey:`. The accompanying declaration is what says whose.
 *
 * @param array<string,mixed> $payload Activity payload.
 * @return array<string,mixed>
 */
function axismundi_act_reaction_emoji_tag( array $payload ) : array {
	$tags = $payload['tag'] ?? array();
	$tags = is_array( $tags ) && array_is_list( $tags ) ? $tags : array( $tags );
	$emoji_tags = array();
	foreach ( $tags as $tag ) {
		if ( ! is_array( $tag ) ) {
			continue;
		}
		foreach ( (array) ( $tag['type'] ?? array() ) as $type ) {
			if ( is_string( $type ) && str_contains( $type, 'Emoji' ) ) {
				$emoji_tags[] = $tag;
				break;
			}
		}
	}
	// FEP-c0e0 names one declaration. Selecting the first of several would let an
	// unrelated second declaration silently change which asset the reaction means.
	return 1 === count( $emoji_tags ) ? $emoji_tags[0] : array();
}

/** UTC SQL datetime from an ISO value. */
function axismundi_act_datetime( $value ) : ?string {
	if ( ! is_scalar( $value ) || '' === trim( (string) $value ) ) {
		return null;
	}
	$time = strtotime( (string) $value );
	return false === $time ? null : gmdate( 'Y-m-d H:i:s', $time );
}

/** One immutable feed ordering value, shared by writes, upgrades, and cursor reads. */
function axismundi_act_feed_sort_at( array $row, string $created_at ) : string {
	foreach ( array( 'published_at', 'received_at' ) as $column ) {
		$value = trim( (string) ( $row[ $column ] ?? '' ) );
		if ( '' !== $value ) {
			return $value;
		}
	}
	return $created_at;
}

/** Materialize the legacy feed ordering expression before cursor reads depend on it. */
function axismundi_act_backfill_feed_sort_at() : bool {
	global $wpdb;
	$table = axismundi_act_activities_table();
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- one-time derivation from immutable ledger timestamps.
	$result = $wpdb->query( "UPDATE {$table} SET feed_sort_at = COALESCE(published_at, received_at, created_at) WHERE feed_sort_at IS NULL" );
	if ( false === $result ) {
		return false;
	}
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- migration verification before recording v8.
	return 0 === (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE feed_sort_at IS NULL" );
}

/** Whether one normalized audience contains the ActivityStreams Public recipient. */
function axismundi_act_audience_is_public( array $audience ) : bool {
	$public = array( 'https://www.w3.org/ns/activitystreams#Public', 'as:Public' );
	return (bool) array_intersect( $public, (array) ( $audience['to'] ?? array() ) )
		|| (bool) array_intersect( $public, (array) ( $audience['cc'] ?? array() ) );
}

/** Materialize public audience from immutable normalized audience snapshots for candidate queries. */
function axismundi_act_backfill_public_audience() : bool {
	global $wpdb;
	$table = axismundi_act_activities_table();
	$after = 0;
	$batch = 200;
	$valid = true;
	do {
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- bounded upgrade derivation from Activity's immutable normalized audience snapshots.
		$rows = (array) $wpdb->get_results( $wpdb->prepare( "SELECT id, audience_json FROM {$table} WHERE id > %d ORDER BY id ASC LIMIT %d", $after, $batch ), ARRAY_A );
		foreach ( $rows as $row ) {
			$after    = (int) $row['id'];
			$audience = json_decode( (string) $row['audience_json'], true );
			$updated  = $wpdb->update( $table, array( 'has_public_audience' => axismundi_act_audience_is_public( is_array( $audience ) ? $audience : array() ) ? 1 : 0 ), array( 'id' => $after ), array( '%d' ), array( '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- bounded materialized-fact upgrade.
			$valid    = false !== $updated && $valid;
		}
	} while ( count( $rows ) === $batch );
	return $valid;
}

/** Normalize to/cc/bto/bcc/audience into URI lists. */
function axismundi_act_audience( array $payload ) : array {
	$out = array();
	foreach ( array( 'to', 'cc', 'bto', 'bcc', 'audience' ) as $property ) {
		$members = $payload[ $property ] ?? array();
		$members = is_array( $members ) && array_is_list( $members ) ? $members : array( $members );
		$uris    = array();
		foreach ( $members as $member ) {
			$uri = is_scalar( $member ) && 'as:Public' === (string) $member
				? 'as:Public'
				: axismundi_act_member_uri( $member );
			if ( '' !== $uri ) {
				$uris[] = $uri;
			}
		}
		$out[ $property ] = array_values( array_unique( $uris ) );
	}
	return $out;
}

/** Canonical local Activity URI. */
function axismundi_act_local_uri( string $uuid ) : string {
	return home_url( '/activities/' . rawurlencode( $uuid ) . '/' );
}

/** UUID encoded by this site's canonical local Activity path, or null. */
function axismundi_act_local_uuid_from_uri( string $uri ) : ?string {
	$home = wp_parse_url( home_url( '/' ) );
	$part = wp_parse_url( $uri );
	if ( ! is_array( $home ) || ! is_array( $part )
		|| strtolower( (string) ( $home['scheme'] ?? '' ) ) !== strtolower( (string) ( $part['scheme'] ?? '' ) )
		|| strtolower( (string) ( $home['host'] ?? '' ) ) !== strtolower( (string) ( $part['host'] ?? '' ) )
		|| (int) ( $home['port'] ?? 0 ) !== (int) ( $part['port'] ?? 0 )
	) {
		return null;
	}
	$path = (string) ( $part['path'] ?? '' );
	return preg_match( '#/activities/([0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12})/?$#i', $path, $match ) ? strtolower( $match[1] ) : null;
}

/** Hydrate one database row. */
function axismundi_act_hydrate( array $row ) : Axismundi_Activity {
	$payload         = json_decode( (string) $row['payload_json'], true );
	$audience        = json_decode( (string) $row['audience_json'], true );
	$row['payload']  = is_array( $payload ) ? $payload : array();
	$row['audience'] = is_array( $audience ) ? $audience : array();
	return Axismundi_Activity::from_row( $row );
}

/** Exact URI lookup by hash plus full comparison. */
function axismundi_act_get( string $activity_uri ) : ?Axismundi_Activity {
	global $wpdb;
	$uri = axismundi_act_uri( $activity_uri );
	if ( '' === $uri || AXISMUNDI_ACT_DB_VERSION !== (string) get_option( AXISMUNDI_ACT_DB_VERSION_OPTION, '' ) ) {
		return null;
	}
	$table = axismundi_act_activities_table();
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- URI-keyed custom repository lookup.
	$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE activity_uri_hash = %s", hash( 'sha256', $uri ) ), ARRAY_A );
	return is_array( $row ) && hash_equals( (string) $row['activity_uri'], $uri ) ? axismundi_act_hydrate( $row ) : null;
}

/** Query recent activities by one exact Actor URI. */
function axismundi_act_get_by_actor( string $actor_uri, int $limit = 50 ) : array {
	return axismundi_act_get_by_reference( 'actor', $actor_uri, $limit );
}

/**
 * One Actor's still-effective activities of given types, newest first.
 *
 * `axismundi_act_get_by_actor()` answers "everything this Actor ever did", which is the wrong
 * question for a surface that lists one kind of act and has to page through it. A Group's
 * community archive is the case in point: what it wants is that Group's `Announce` rows and
 * nothing else, and reading two hundred mixed rows to find them would both miss some and cost
 * more than the query it replaced.
 *
 * Withdrawn rows are excluded here rather than by the caller, because every caller wants the
 * same thing — an `Undo` means the act no longer stands, and a list that showed it anyway would
 * republish what someone deliberately took back.
 *
 * @param string   $actor_uri Actor URI.
 * @param string[] $types     Activity types to include.
 * @param int      $limit     Bounded row count.
 * @param int      $offset    Rows to skip.
 * @return Axismundi_Activity[]
 */
function axismundi_act_get_effective_by_actor_and_type( string $actor_uri, array $types, int $limit = 50, int $offset = 0 ) : array {
	global $wpdb;
	$uri   = axismundi_act_uri( $actor_uri );
	$types = array_values( array_filter( array_map( 'axismundi_act_type', $types ) ) );
	if ( '' === $uri || empty( $types ) || AXISMUNDI_ACT_DB_VERSION !== (string) get_option( AXISMUNDI_ACT_DB_VERSION_OPTION, '' ) ) {
		return array();
	}
	$table        = axismundi_act_activities_table();
	$limit        = max( 1, min( 200, $limit ) );
	$offset       = max( 0, $offset );
	$placeholders = implode( ', ', array_fill( 0, count( $types ), '%s' ) );
	$args         = array_merge( array( hash( 'sha256', $uri ), $uri ), $types, array( $limit, $offset ) );
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fixed table, generated placeholders, values prepared.
	$rows = (array) $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} WHERE actor_uri_hash = %s AND actor_uri = %s AND activity_type IN ({$placeholders}) AND effective_status = 'active' ORDER BY COALESCE(published_at, received_at, created_at) DESC, id DESC LIMIT %d OFFSET %d", $args ), ARRAY_A );
	return array_map( 'axismundi_act_hydrate', $rows );
}

/** Whether an Activity declares the ActivityStreams Public audience. */
function axismundi_act_has_public_audience( Axismundi_Activity $activity ) : bool {
	return axismundi_act_audience_is_public( $activity->get_audience() );
}

/** Whether one effective outbound Activity is addressed to the public. */
function axismundi_act_is_public( Axismundi_Activity $activity ) : bool {
	return 'outbound' === $activity->get_direction() && $activity->is_effective() && axismundi_act_has_public_audience( $activity );
}

/**
 * Whether a verified ledger row may appear in a human public feed.
 *
 * Outboxes only expose this site's outbound activities, while an Actor profile
 * can also render a cached remote Actor's inbound activities. Both surfaces
 * require an effective Activity explicitly addressed to Public; the profile
 * renderer still strips blind recipients from its payload copy below.
 */
function axismundi_act_is_publicly_renderable( Axismundi_Activity $activity ) : bool {
	return $activity->is_effective()
		&& in_array( $activity->get_direction(), array( 'inbound', 'outbound', 'local' ), true )
		&& axismundi_act_has_public_audience( $activity );
}

/** Public-safe payload copy; the lossless ledger payload remains unchanged. */
function axismundi_act_public_payload( Axismundi_Activity $activity ) : ?array {
	if ( ! axismundi_act_is_public( $activity ) ) {
		return null;
	}
	$payload = $activity->get_payload();
	unset( $payload['bto'], $payload['bcc'] );
	return $payload;
}

/** Public-safe recent outbound payloads for an Actor's Outbox projection. */
function axismundi_act_get_public_outbox( string $actor_uri, int $limit = 200 ) : array {
	$items = array();
	foreach ( axismundi_act_get_by_actor( $actor_uri, $limit ) as $activity ) {
		if ( ! $activity instanceof Axismundi_Activity ) {
			continue;
		}
		$payload = axismundi_act_public_payload( $activity );
		/*
		 * An Actor-domain product may opt a non-Public activity into its public Outbox only
		 * when that Actor's public representation makes the activity public by definition.
		 * The default remains strict; no recipient-addressed Activity leaks without its owner.
		 *
		 * @param array<string,mixed>|null $payload Public-safe payload, or null when not Public.
		 * @param Axismundi_Activity        $activity Candidate outbound Activity.
		 */
		$payload = apply_filters( 'axismundi_act_public_outbox_payload', $payload, $activity );
		if ( is_array( $payload ) ) {
			$items[] = $payload;
		}
	}
	return $items;
}

/** Query recent activities by one exact Object URI. */
function axismundi_act_get_by_object( string $object_uri, int $limit = 50 ) : array {
	return axismundi_act_get_by_reference( 'object', $object_uri, $limit );
}

/** Latest effective Create, Update, or Delete for one object URI. */
function axismundi_act_get_object_lifecycle( string $object_uri ) : ?Axismundi_Activity {
	global $wpdb;
	$uri = axismundi_act_uri( $object_uri );
	if ( '' === $uri || AXISMUNDI_ACT_DB_VERSION !== (string) get_option( AXISMUNDI_ACT_DB_VERSION_OPTION, '' ) ) {
		return null;
	}
	$table = axismundi_act_activities_table();
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- exact URI lifecycle lookup in the custom ledger.
	$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE object_uri_hash = %s AND object_uri = %s AND activity_type IN ('Create','Update','Delete') AND effective_status = 'active' ORDER BY COALESCE(published_at, received_at, created_at) DESC, id DESC LIMIT 1", hash( 'sha256', $uri ), $uri ), ARRAY_A );
	return is_array( $row ) ? axismundi_act_hydrate( $row ) : null;
}

/**
 * Return the immutable recipient snapshots emitted by one local Object lifecycle.
 *
 * Each Create or Update keeps its own address list. Consumers that need to
 * answer whether a local account was ever addressed must use this ledger view,
 * not re-parse the current editable Object body. The cursor is internal so an
 * object with more than one page of revisions cannot silently lose recipients.
 *
 * @param string   $object_uri Canonical Object URI.
 * @param string   $actor_uri  Canonical owning Actor URI.
 * @param string[] $types      Lifecycle Activity types to include.
 * @return string[] Unique addressed URIs from effective local lifecycle rows.
 */
function axismundi_act_get_object_audience_members( string $object_uri, string $actor_uri, array $types = array( 'Create', 'Update' ) ) : array {
	global $wpdb;
	$object_uri = axismundi_act_uri( $object_uri );
	$actor_uri  = axismundi_act_uri( $actor_uri );
	$types      = array_values( array_intersect( array( 'Create', 'Update', 'Delete' ), $types ) );
	if ( '' === $object_uri || '' === $actor_uri || empty( $types ) || AXISMUNDI_ACT_DB_VERSION !== (string) get_option( AXISMUNDI_ACT_DB_VERSION_OPTION, '' ) ) {
		return array();
	}

	$table        = axismundi_act_activities_table();
	$type_sql     = implode( ',', array_fill( 0, count( $types ), '%s' ) );
	$after_id     = 0;
	$recipients   = array();
	$per_page     = 200;
	do {
		$sql = "SELECT id, audience_json FROM {$table}
			WHERE id > %d
				AND object_uri_hash = %s AND object_uri = %s
				AND actor_uri_hash = %s AND actor_uri = %s
				AND direction = 'outbound' AND effective_status = 'active'
				AND activity_type IN ({$type_sql})
			ORDER BY id ASC LIMIT %d";
		$args = array_merge(
			array( $after_id, hash( 'sha256', $object_uri ), $object_uri, hash( 'sha256', $actor_uri ), $actor_uri ),
			$types,
			array( $per_page )
		);
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fixed table and placeholder count; exact local lifecycle lookup.
		$rows = (array) $wpdb->get_results( $wpdb->prepare( $sql, $args ), ARRAY_A );
		foreach ( $rows as $row ) {
			$after_id = (int) $row['id'];
			$audience = json_decode( (string) $row['audience_json'], true );
			if ( ! is_array( $audience ) ) {
				continue;
			}
			foreach ( array( 'to', 'cc', 'bto', 'bcc', 'audience' ) as $field ) {
				foreach ( (array) ( $audience[ $field ] ?? array() ) as $member ) {
					$member = axismundi_act_member_uri( $member );
					if ( '' !== $member ) {
						$recipients[ $member ] = true;
					}
				}
			}
		}
	} while ( count( $rows ) === $per_page );

	return array_keys( $recipients );
}

/**
 * Effective Create and Announce rows for an Actor's profile feed.
 *
 * The type allowlist collapses Update/Delete/Like/Undo out of the timeline: an
 * Update never becomes its own row (the object card reads the latest source
 * state), and `effective_status = 'active'` drops an undone Announce. Ordering by
 * the published fallback keeps a boosted object at the Announce time. The public
 * audience gate stays with `axismundi_act_is_publicly_renderable()` at the call site.
 *
 * @return Axismundi_Activity[]
 */
function axismundi_act_get_actor_feed( string $actor_uri, int $limit = 20, int $offset = 0 ) : array {
	global $wpdb;
	$uri = axismundi_act_uri( $actor_uri );
	if ( '' === $uri || AXISMUNDI_ACT_DB_VERSION !== (string) get_option( AXISMUNDI_ACT_DB_VERSION_OPTION, '' ) || ! function_exists( 'axismundi_actors_get_by_uri' ) ) {
		return array();
	}
	$actor = axismundi_actors_get_by_uri( $uri );
	if ( ! $actor instanceof Axismundi_Actor ) {
		return array();
	}
	$table = axismundi_act_activities_table();
	$limit = max( 1, min( 50, $limit ) );
	$offset = max( 0, $offset );
	// Direction is a transport fact, not a feed policy. The repository rejects
	// local Actors as inbound and remote Actors as outbound/local, so this keeps
	// both profile kinds on the same API without mixing their activity origins.
	$directions = $actor->is_local() ? array( 'outbound', 'local' ) : array( 'inbound' );
	$direction_sql = implode( ',', array_fill( 0, count( $directions ), '%s' ) );
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- actor-keyed feed selection in the custom ledger; direction placeholders and every value are allowlisted/prepared.
	$rows = (array) $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} WHERE actor_uri_hash = %s AND actor_uri = %s AND direction IN ({$direction_sql}) AND activity_type IN ('Create','Announce') AND effective_status = 'active' ORDER BY feed_sort_at DESC, id DESC LIMIT %d OFFSET %d", array_merge( array( hash( 'sha256', $uri ), $uri ), $directions, array( $limit, $offset ) ) ), ARRAY_A );
	return array_map( 'axismundi_act_hydrate', $rows );
}

/**
 * The feed's sort key for one ledger row, as an opaque continuation cursor.
 *
 * The feed is ordered by effective time and then by id, so a cursor has to carry both. Time
 * alone is not unique — a batch of activities imported together shares one timestamp, and a
 * cursor that only remembered the time would either repeat that whole batch or skip it.
 *
 * @param Axismundi_Activity $activity Activity to describe.
 * @return string Cursor, or '' when the row cannot be positioned.
 */
function axismundi_act_feed_cursor( Axismundi_Activity $activity ) : string {
	$time = $activity->get_effective_time();
	return '' === $time ? '' : $time . '@' . $activity->get_id();
}

/** Split a continuation cursor back into its time and id halves. */
function axismundi_act_parse_feed_cursor( string $cursor ) : ?array {
	$parts = explode( '@', $cursor );
	if ( 2 !== count( $parts ) || '' === trim( $parts[0] ) || ! ctype_digit( $parts[1] ) ) {
		return null;
	}
	$time = trim( $parts[0] );
	// A cursor arrives from a URL, so it is attacker-controlled. Accept only the exact
	// datetime shape the ledger stores; anything else is discarded rather than passed to a
	// comparison that would silently match everything or nothing.
	if ( 1 !== preg_match( '/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $time ) ) {
		return null;
	}
	return array( 'time' => $time, 'id' => (int) $parts[1] );
}

/**
 * One page of an Actor's feed strictly older than a cursor.
 *
 * Offset paging is wrong for this feed and gets more wrong the more active the Actor is: the
 * ledger grows at the head, so by the time a reader asks for the next page the rows have all
 * shifted down by however many arrived, and the reader sees duplicates. A cursor names a
 * position in the ordering itself, so what comes back is always what follows what was shown.
 *
 * @param string $actor_uri Actor whose feed is read.
 * @param int    $limit     Maximum rows.
 * @param string $cursor    Continuation cursor, or '' for the newest page.
 * @param bool   $inclusive Whether the cursor's own row is included.
 * @return Axismundi_Activity[]
 */
function axismundi_act_get_actor_feed_after( string $actor_uri, int $limit = 20, string $cursor = '', bool $inclusive = false ) : array {
	global $wpdb;
	$uri = axismundi_act_uri( $actor_uri );
	if ( '' === $uri || AXISMUNDI_ACT_DB_VERSION !== (string) get_option( AXISMUNDI_ACT_DB_VERSION_OPTION, '' ) || ! function_exists( 'axismundi_actors_get_by_uri' ) ) {
		return array();
	}
	$actor = axismundi_actors_get_by_uri( $uri );
	if ( ! $actor instanceof Axismundi_Actor ) {
		return array();
	}
	$position = '' === $cursor ? null : axismundi_act_parse_feed_cursor( $cursor );
	if ( '' !== $cursor && null === $position ) {
		// An unreadable cursor is refused rather than quietly restarting at the newest page,
		// which would loop a scrolling reader back to the top forever.
		return array();
	}
	$table         = axismundi_act_activities_table();
	$limit         = max( 1, min( 50, $limit ) );
	$directions    = $actor->is_local() ? array( 'outbound', 'local' ) : array( 'inbound' );
	$direction_sql = implode( ',', array_fill( 0, count( $directions ), '%s' ) );
	$sort          = 'feed_sort_at';
	$where         = '';
	$args          = array_merge( array( hash( 'sha256', $uri ), $uri ), $directions );
	if ( null !== $position ) {
		/*
		 * Inclusive is what an infinite-scrolling window needs: it anchors to the newest row the
		 * reader has already been shown, so growing the window re-renders from exactly that row
		 * and activity arriving at the head afterwards cannot shift what is below it. Exclusive
		 * is what a next-page link needs. The two differ by one character, so they share a query
		 * rather than becoming two that can disagree about a boundary row.
		 */
		$comparison = $inclusive ? '<=' : '<';
		$where      = " AND ( {$sort} < %s OR ( {$sort} = %s AND id {$comparison} %d ) )";
		$args[]     = $position['time'];
		$args[]     = $position['time'];
		$args[]     = $position['id'];
	}
	$args[] = $limit;
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- actor-keyed feed selection in the custom ledger; direction placeholders and every value are allowlisted/prepared.
	$rows = (array) $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} WHERE actor_uri_hash = %s AND actor_uri = %s AND direction IN ({$direction_sql}) AND activity_type IN ('Create','Announce') AND effective_status = 'active'{$where} ORDER BY {$sort} DESC, id DESC LIMIT %d", $args ), ARRAY_A );
	return array_map( 'axismundi_act_hydrate', $rows );
}

/**
 * One SQL predicate for a numbered, publicly renderable Actor feed.
 *
 * Cursor feeds deliberately keep their immutable-ledger scan: an uncached Announce can still be
 * rendered as an external reference, while a vanished Create cannot. A numbered collection makes
 * a stronger promise -- its COUNT and LIMIT/OFFSET must name exactly the same cards -- so it reads
 * OP's materialized listing facts before it counts anything.
 *
 * @param Axismundi_Actor $actor           Actor whose ledger is read.
 * @param string          $filter          posts|posts-and-boosts|posts-and-replies|all.
 * @param string          $group_context   in|out|both.
 * @param bool|null       $object_is_reply Narrow to Objects that are, or are not, replies;
 *                                         null leaves the selection unnarrowed.
 * @return array{from:string,where:string,args:array<int,mixed>}|null
 */
function axismundi_act_countable_actor_feed_candidate_sql( Axismundi_Actor $actor, string $filter = 'all', string $group_context = 'both', ?bool $object_is_reply = null ) : ?array {
	if (
		AXISMUNDI_ACT_DB_VERSION !== (string) get_option( AXISMUNDI_ACT_DB_VERSION_OPTION, '' )
		|| ! function_exists( 'axismundi_op_object_index_table' )
	) {
		return null;
	}
	$actor_uri = axismundi_act_uri( $actor->get_uri() );
	if ( '' === $actor_uri ) {
		return null;
	}
	$directions    = $actor->is_local() ? array( 'outbound', 'local' ) : array( 'inbound' );
	$direction_sql = implode( ', ', array_fill( 0, count( $directions ), '%s' ) );
	$table         = axismundi_act_activities_table();
	$index         = axismundi_op_object_index_table();
	$rules         = function_exists( 'axismundi_act_actor_feed_filters' )
		? axismundi_act_actor_feed_filters()
		: array();
	$rules         = (array) ( $rules[ $filter ] ?? $rules['all'] ?? array( 'replies' => true, 'boosts' => true ) );
	$where         = array(
		'a.actor_uri_hash = %s',
		'a.actor_uri = %s',
		"a.direction IN ({$direction_sql})",
		"a.activity_type IN ('Create', 'Announce')",
		"a.effective_status = 'active'",
		'a.has_public_audience = 1',
		/*
		 * A Create can only be rendered from its own public Object, while an Announce keeps the
		 * existing external-reference contract when acquisition has not populated an index row.
		 * The author equality must stay inside the Create arm: an Announce normally names somebody
		 * else's Object, so moving it outside would erase every boost.
		 */
		"( ( a.activity_type = 'Create' AND i.publicly_listable = 1 AND i.attributed_to_uri_hash = a.actor_uri_hash ) OR ( a.activity_type = 'Announce' AND ( i.object_uri_hash IS NULL OR i.publicly_listable = 1 ) ) )",
	);
	if ( 'in' === $group_context ) {
		$where[] = 'i.has_group_context = 1';
	} elseif ( 'out' === $group_context ) {
		// An uncached Announce has no contrary addressing fact, so it stays on the ordinary feed.
		$where[] = '( i.object_uri_hash IS NULL OR i.has_group_context = 0 )';
	}
	if ( empty( $rules['boosts'] ) ) {
		$where[] = "a.activity_type = 'Create'";
	}
	if ( empty( $rules['replies'] ) ) {
		$where[] = "( a.activity_type = 'Announce' OR i.is_reply = 0 )";
	}
	if ( null !== $object_is_reply ) {
		/*
		 * A fact about the announced Object, deliberately not a change to the rule above.
		 *
		 * The `replies` rule asks what the *Actor* did, which is why an Announce always passes it:
		 * boosting is an act of its own regardless of what it wraps, so a Person reading "posts and
		 * boosts" keeps seeing a boosted reply. This asks what the *Object* is, which is a different
		 * question and the only one that can split a collection whose every row is an Announce.
		 * Making the reply rule reach through the Announce instead would have answered both
		 * questions with one predicate and silently changed what a Person's timeline means.
		 *
		 * The index row is required rather than tolerated here. Everywhere else an uncached Announce
		 * keeps its external-reference contract, but a collection that says "these are the comments"
		 * has to know that of every row it counts, and an Object with no listing facts is not known
		 * to be either. Admitting it would put the same entry in both collections or in the wrong
		 * one, and a numbered page cannot absorb that: COUNT and the visible cards must name the
		 * same set.
		 *
		 * A surface asking for replies while its filter excludes them is asking for nothing, and
		 * correctly gets nothing rather than one rule quietly overriding the other.
		 */
		$where[] = $object_is_reply
			? '( i.object_uri_hash IS NOT NULL AND i.is_reply = 1 )'
			: '( i.object_uri_hash IS NOT NULL AND i.is_reply = 0 )';
	}
	return array(
		/*
		 * Both ledger indexes preserve chronology, but only this one narrows at the public
		 * audience fact before walking it. MySQL will choose the older cursor index on a tiny,
		 * all-public fixture because both answer in order; forcing the candidate key prevents that
		 * cost estimate from turning a public archive into a scan of private ledger history.
		 */
		/*
		 * A Group admission can Announce the submitted Create Activity rather than its Object.
		 * Join that one ledger hop before Object facts: the Group Actor still supplies chronology
		 * and admission, while `i` supplies renderability and the root/reply fact. Direct Object
		 * Announces simply leave `wrapped` null and join `i` on their own object URI hash.
		 */
		'from'  => "{$table} AS a FORCE INDEX (feed_public_cursor) LEFT JOIN {$table} AS wrapped ON wrapped.activity_uri_hash = a.object_uri_hash LEFT JOIN {$index} AS i ON i.object_uri_hash = IF( a.activity_type = 'Announce' AND wrapped.object_uri_hash IS NOT NULL, wrapped.object_uri_hash, a.object_uri_hash )",
		'where' => implode( ' AND ', $where ),
		'args'  => array_merge( array( hash( 'sha256', $actor_uri ), $actor_uri ), $directions ),
	);
}

/**
 * Read one numbered page and its total from the exact same countable candidate set.
 *
 * Observed Objects intentionally do not participate: they have no ledger position, so including
 * their head-window fallback would make page counts depend on which page a reader opened first.
 *
 * @param bool|null $object_is_reply Narrow to Objects that are, or are not, replies; null leaves
 *                                   the selection unnarrowed.
 * @return array{activities:array<int,Axismundi_Activity>,total:int,page:int,pages:int}
 */
function axismundi_act_get_countable_actor_feed_page( Axismundi_Actor $actor, int $per_page = 20, int $page = 1, string $filter = 'all', string $group_context = 'both', ?bool $object_is_reply = null ) : array {
	global $wpdb;
	$query = axismundi_act_countable_actor_feed_candidate_sql( $actor, $filter, $group_context, $object_is_reply );
	if ( null === $query ) {
		return array( 'activities' => array(), 'total' => 0, 'page' => 1, 'pages' => 1 );
	}
	$per_page = max( 1, min( 50, $per_page ) );
	$page     = max( 1, $page );
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- the FROM/WHERE come from the fixed predicate above; all values are prepared there.
	$total = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$query['from']} WHERE {$query['where']}", $query['args'] ) );
	$pages = max( 1, (int) ceil( $total / $per_page ) );
	$page  = min( $page, $pages );
	$args  = array_merge( $query['args'], array( $per_page, ( $page - 1 ) * $per_page ) );
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- the FROM/WHERE come from the fixed predicate above; limit and offset are prepared.
	$rows = (array) $wpdb->get_results( $wpdb->prepare( "SELECT a.* FROM {$query['from']} WHERE {$query['where']} ORDER BY a.feed_sort_at DESC, a.id DESC LIMIT %d OFFSET %d", $args ), ARRAY_A );
	return array(
		'activities' => array_map( 'axismundi_act_hydrate', $rows ),
		'total'      => $total,
		'page'       => $page,
		'pages'      => $pages,
	);
}

/** Recent Activity ledger rows for administration and collection providers. */
function axismundi_act_get_recent( int $limit = 50 ) : array {
	global $wpdb;
	if ( AXISMUNDI_ACT_DB_VERSION !== (string) get_option( AXISMUNDI_ACT_DB_VERSION_OPTION, '' ) ) {
		return array();
	}
	$table = axismundi_act_activities_table();
	$limit = max( 1, min( 200, $limit ) );
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fixed custom table; numeric limit prepared.
	$rows = (array) $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} ORDER BY COALESCE(published_at, received_at, created_at) DESC, id DESC LIMIT %d", $limit ), ARRAY_A );
	return array_map( 'axismundi_act_hydrate', $rows );
}

/** @return Axismundi_Activity[] */
function axismundi_act_get_by_reference( string $field, string $uri, int $limit ) : array {
	global $wpdb;
	if ( AXISMUNDI_ACT_DB_VERSION !== (string) get_option( AXISMUNDI_ACT_DB_VERSION_OPTION, '' )
		|| ! in_array( $field, array( 'actor', 'object' ), true )
		|| '' === axismundi_act_uri( $uri )
	) {
		return array();
	}
	$table  = axismundi_act_activities_table();
	$limit  = max( 1, min( 200, $limit ) );
	$column = $field . '_uri';
	$hash   = $field . '_uri_hash';
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- allowlisted columns and fixed table; values prepared.
	$rows = (array) $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} WHERE {$hash} = %s AND {$column} = %s ORDER BY published_at DESC, id DESC LIMIT %d", hash( 'sha256', $uri ), $uri, $limit ), ARRAY_A );
	return array_map( 'axismundi_act_hydrate', $rows );
}

/** Validate and normalize an Activity before database mutation. */
function axismundi_act_normalize( array $payload, string $direction = 'local' ) {
	$direction = sanitize_key( $direction );
	if ( ! in_array( $direction, array( 'inbound', 'outbound', 'local' ), true ) ) {
		return new WP_Error( 'ax_act_direction', __( 'The Activity direction is invalid.', 'axismundi-activities' ) );
	}
	$type = axismundi_act_type( $payload['type'] ?? '' );
	if ( '' === $type ) {
		return new WP_Error( 'ax_act_type', __( 'The Activity type is unsupported.', 'axismundi-activities' ) );
	}
	$actor_uri = axismundi_act_member_uri( $payload['actor'] ?? '' );
	if ( '' === $actor_uri || ! function_exists( 'axismundi_actors_get_by_uri' ) ) {
		return new WP_Error( 'ax_act_actor', __( 'The Activity requires an Actor known to Axismundi Actors.', 'axismundi-activities' ) );
	}
	$actor = axismundi_actors_get_by_uri( $actor_uri );
	if ( ! $actor instanceof Axismundi_Actor ) {
		return new WP_Error( 'ax_act_actor', __( 'The Activity requires an Actor known to Axismundi Actors.', 'axismundi-activities' ) );
	}
	if ( ( $actor->is_local() && 'inbound' === $direction ) || ( ! $actor->is_local() && 'inbound' !== $direction ) ) {
		return new WP_Error( 'ax_act_direction', __( 'The Activity direction conflicts with its Actor origin.', 'axismundi-activities' ) );
	}

	$local_uuid  = null;
	$activity_uri = axismundi_act_member_uri( $payload['id'] ?? '' );
	if ( 'inbound' === $direction ) {
		if ( '' === $activity_uri ) {
			return new WP_Error( 'ax_act_identity', __( 'An inbound Activity requires its canonical id.', 'axismundi-activities' ) );
		}
	} else {
		if ( '' === $activity_uri ) {
			$local_uuid   = wp_generate_uuid4();
			$activity_uri = axismundi_act_local_uri( $local_uuid );
		} else {
			$local_uuid = axismundi_act_local_uuid_from_uri( $activity_uri );
			if ( null === $local_uuid ) {
				return new WP_Error( 'ax_act_identity', __( 'A local Activity id must use this site\'s UUID Activity path.', 'axismundi-activities' ) );
			}
		}
	}

	$object_uri = axismundi_act_member_uri( $payload['object'] ?? '' );
	$target_uri = axismundi_act_member_uri( $payload['target'] ?? '' );
	// A general AS2 member, not a Quote alias: FEP-044f sends the quoting Object here as an
	// embedded object, and member_uri() reduces that to its id. Stored whenever present so
	// the column is not Quote-specific. QuoteRequest is the first type that requires it.
	$instrument_uri = axismundi_act_member_uri( $payload['instrument'] ?? '' );
	if ( '' === $object_uri ) {
		return new WP_Error( 'ax_act_object', __( 'This Activity type requires an object URI.', 'axismundi-activities' ) );
	}
	if ( in_array( $type, array( 'Add', 'Remove', 'Move' ), true ) && '' === $target_uri ) {
		return new WP_Error( 'ax_act_target', __( 'This collection Activity requires a target URI.', 'axismundi-activities' ) );
	}
	if ( 'QuoteRequest' === $type && '' === $instrument_uri ) {
		return new WP_Error( 'ax_act_instrument', __( 'A QuoteRequest requires a quoting Object in instrument.', 'axismundi-activities' ) );
	}
	$now = current_time( 'mysql', true );
	if ( ! isset( $payload['published'] ) && 'inbound' !== $direction ) {
		$payload['published'] = gmdate( 'c', strtotime( $now . ' UTC' ) );
	}
	/*
	 * Reaction classification, decided once and stored, because every later query would
	 * otherwise have to re-parse `content` to know whether a `Like` is a like.
	 *
	 * FEP-c0e0: "Implementations MUST process `Like` with `content` in the same way as
	 * `EmojiReact`." So the two spellings converge here, and a `Like` that carries a real
	 * reaction stops being a plain Like — `reaction_key IS NULL` is what the like count
	 * means from now on.
	 */
	$reaction = null;
	if ( in_array( $type, array( 'Like', 'EmojiReact' ), true ) && isset( $payload['content'] ) && function_exists( 'axismundi_act_normalize_reaction' ) ) {
		/*
		 * Misskey states the reaction twice, in `content` and `_misskey_reaction`. Agreeing,
		 * that is one fact said twice. Disagreeing, there is no principled way to choose, and
		 * picking one silently would record a reaction nobody sent.
		 */
		$vendor = $payload['_misskey_reaction'] ?? null;
		if ( is_scalar( $vendor ) && trim( (string) $vendor ) !== trim( (string) $payload['content'] ) ) {
			return new WP_Error( 'ax_act_reaction_conflict', __( 'The Activity states two different reactions.', 'axismundi-activities' ) );
		}
		$tag = axismundi_act_reaction_emoji_tag( $payload );
		/*
		 * Only an Activity we authored may key a bare custom shortcode to this site. It is
		 * how an emoji withheld from publication is reacted with: the declaration is dropped
		 * from the payload so it does not travel, which leaves nothing here to read the
		 * authority back from. Inbound keeps requiring the declaration, because there the
		 * shortcode alone could name any server's emoji.
		 */
		$self_authority = 'inbound' !== $direction && function_exists( 'axismundi_emoji_local_authority' ) ? axismundi_emoji_local_authority() : '';
		$reaction       = axismundi_act_normalize_reaction( $payload['content'], $tag, $self_authority );
		$looks_custom = is_scalar( $payload['content'] ) && str_starts_with( trim( (string) $payload['content'] ), ':' ) && str_ends_with( trim( (string) $payload['content'] ), ':' );
		if ( null === $reaction && ( 'EmojiReact' === $type || $looks_custom ) ) {
			// A Like whose content is prose is still a Like; an EmojiReact whose content is
			// not a reaction is nothing at all. A colon-wrapped custom name is likewise not
			// a plain Like: without its matching Emoji declaration, it has no identity.
			return new WP_Error( 'ax_act_reaction_content', __( 'An EmojiReact requires a single emoji as its content.', 'axismundi-activities' ) );
		}
	}

	$payload['id'] = $activity_uri;
	$json          = wp_json_encode( $payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
	$audience      = axismundi_act_audience( $payload );
	$audience_json = wp_json_encode( $audience, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
	if ( ! is_string( $json ) || ! is_string( $audience_json ) || strlen( $json ) > AXISMUNDI_ACT_PAYLOAD_MAX ) {
		return new WP_Error( 'ax_act_payload_size', __( 'The Activity payload exceeds one MiB.', 'axismundi-activities' ) );
	}
	return array(
		'local_uuid'        => $local_uuid,
		'activity_uri'      => $activity_uri,
		'activity_uri_hash' => hash( 'sha256', $activity_uri ),
		'activity_type'     => $type,
		'actor_uri'         => $actor_uri,
		'actor_uri_hash'    => hash( 'sha256', $actor_uri ),
		'object_uri'        => '' !== $object_uri ? $object_uri : null,
		'object_uri_hash'   => '' !== $object_uri ? hash( 'sha256', $object_uri ) : null,
		'target_uri'        => '' !== $target_uri ? $target_uri : null,
		'target_uri_hash'   => '' !== $target_uri ? hash( 'sha256', $target_uri ) : null,
		'instrument_uri'      => '' !== $instrument_uri ? $instrument_uri : null,
		'instrument_uri_hash' => '' !== $instrument_uri ? hash( 'sha256', $instrument_uri ) : null,
		'direction'         => $direction,
		'effective_status'  => 'active',
		'has_public_audience' => axismundi_act_audience_is_public( $audience ) ? 1 : 0,
		'reaction_key'      => null === $reaction ? null : $reaction['key'],
		'reaction_key_hash' => null === $reaction ? null : hash( 'sha256', $reaction['key'] ),
		// Stored verbatim so a chip can be labelled without re-parsing the payload; the
		// audit asserts it never drifts from the `content` it came out of.
		'reaction_raw'      => null === $reaction ? null : $reaction['raw'],
		'audience_json'     => $audience_json,
		'payload_json'      => $json,
		'payload_hash'      => hash( 'sha256', $json ),
		'published_at'      => axismundi_act_datetime( $payload['published'] ?? '' ),
		'received_at'       => 'inbound' === $direction ? $now : null,
	);
}

/** Exact row lookup used inside repository transactions. */
function axismundi_act_transaction_row( string $uri ) : ?array {
	global $wpdb;
	$table = axismundi_act_activities_table();
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- repository transaction lookup.
	$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE activity_uri_hash = %s", hash( 'sha256', $uri ) ), ARRAY_A );
	return is_array( $row ) && hash_equals( (string) $row['activity_uri'], $uri ) ? $row : null;
}

/** Recompute whether active, same-Actor Undo rows neutralize one Activity. */
function axismundi_act_recompute_effectiveness( string $activity_uri, int $depth = 0 ) : bool {
	global $wpdb;
	if ( $depth > 16 ) {
		return false;
	}
	$row = axismundi_act_transaction_row( $activity_uri );
	if ( null === $row ) {
		return true;
	}
	$table = axismundi_act_activities_table();
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- repository state derivation.
	$undos = (array) $wpdb->get_results( $wpdb->prepare( "SELECT activity_uri, object_uri, actor_uri FROM {$table} WHERE activity_type = 'Undo' AND object_uri_hash = %s AND effective_status = 'active'", hash( 'sha256', $activity_uri ) ), ARRAY_A );
	$undone = false;
	foreach ( $undos as $undo ) {
		if ( hash_equals( (string) $undo['object_uri'], $activity_uri ) && hash_equals( (string) $undo['actor_uri'], (string) $row['actor_uri'] ) ) {
			$undone = true;
			break;
		}
	}
	$desired = $undone ? 'undone' : 'active';
	if ( $desired === (string) $row['effective_status'] ) {
		return true;
	}
	$updated = $wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- denormalized effective state in custom repository.
		$table,
		array( 'effective_status' => $desired, 'updated_at' => current_time( 'mysql', true ) ),
		array( 'id' => (int) $row['id'] ),
		array( '%s', '%s' ),
		array( '%d' )
	);
	if ( false === $updated ) {
		return false;
	}
	return 'Undo' !== (string) $row['activity_type'] || empty( $row['object_uri'] )
		? true
		: axismundi_act_recompute_effectiveness( (string) $row['object_uri'], $depth + 1 );
}

/**
 * Record one immutable Activity, or return the identical existing row.
 *
 * @param array<string,mixed> $payload   ActivityStreams payload.
 * @param string              $direction inbound|outbound|local.
 * @return Axismundi_Activity|WP_Error
 */
function axismundi_act_record_activity( array $payload, string $direction = 'local' ) {
	return axismundi_act_record_source_activity( $payload, $direction, '' );
}

/**
 * Record one Activity generated by a stable local source event.
 *
 * Replaying the same source event returns the winning committed row even when a
 * concurrent request minted a different candidate Activity URI.
 *
 * @param array<string,mixed> $payload          ActivityStreams payload.
 * @param string              $direction        inbound|outbound|local.
 * @param string              $source_event_key Stable local event identity, or empty.
 * @return Axismundi_Activity|WP_Error
 */
function axismundi_act_record_source_activity( array $payload, string $direction, string $source_event_key ) {
	global $wpdb;
	$source_event_key = trim( $source_event_key );
	if ( strlen( $source_event_key ) > 2048 ) {
		return new WP_Error( 'ax_act_source_event', __( 'The source event identity is invalid.', 'axismundi-activities' ) );
	}
	$normalized = axismundi_act_normalize( $payload, $direction );
	if ( is_wp_error( $normalized ) ) {
		return $normalized;
	}
	if ( AXISMUNDI_ACT_DB_VERSION !== (string) get_option( AXISMUNDI_ACT_DB_VERSION_OPTION, '' ) && ! axismundi_act_install() ) {
		return new WP_Error( 'ax_act_schema', __( 'The Activity repository is unavailable.', 'axismundi-activities' ) );
	}
	$table = axismundi_act_activities_table();
	$now   = current_time( 'mysql', true );
	$normalized['feed_sort_at'] = axismundi_act_feed_sort_at( $normalized, $now );
	if ( '' !== $source_event_key ) {
		$normalized['source_event_key']  = $source_event_key;
		$normalized['source_event_hash'] = hash( 'sha256', $source_event_key );
	}
	$wpdb->query( 'START TRANSACTION' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- atomic custom repository transaction.
	if ( '' !== $source_event_key ) {
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- source-event idempotency lock.
		$source_row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE source_event_hash = %s FOR UPDATE", $normalized['source_event_hash'] ), ARRAY_A );
		if ( is_array( $source_row ) ) {
			$wpdb->query( 'COMMIT' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			if ( ! hash_equals( (string) $source_row['source_event_key'], $source_event_key ) ) {
				return new WP_Error( 'ax_act_source_collision', __( 'A source event hash collision was detected.', 'axismundi-activities' ) );
			}
			if ( (string) $source_row['activity_type'] !== (string) $normalized['activity_type']
				|| (string) $source_row['direction'] !== (string) $normalized['direction']
				|| ! hash_equals( (string) $source_row['actor_uri'], (string) $normalized['actor_uri'] )
				|| ! hash_equals( (string) ( $source_row['object_uri'] ?? '' ), (string) ( $normalized['object_uri'] ?? '' ) )
				|| ! hash_equals( (string) ( $source_row['target_uri'] ?? '' ), (string) ( $normalized['target_uri'] ?? '' ) )
				|| ! hash_equals( (string) ( $source_row['instrument_uri'] ?? '' ), (string) ( $normalized['instrument_uri'] ?? '' ) )
			) {
				return new WP_Error( 'ax_act_source_conflict', __( 'That source event already identifies a different Activity.', 'axismundi-activities' ) );
			}
			return axismundi_act_hydrate( $source_row );
		}
	}
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- URI-keyed row lock.
	$existing = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE activity_uri_hash = %s FOR UPDATE", $normalized['activity_uri_hash'] ), ARRAY_A );
	if ( is_array( $existing ) ) {
		if ( ! hash_equals( (string) $existing['activity_uri'], (string) $normalized['activity_uri'] ) ) {
			$wpdb->query( 'ROLLBACK' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			return new WP_Error( 'ax_act_hash_collision', __( 'An Activity URI hash collision was detected.', 'axismundi-activities' ) );
		}
		if ( ! hash_equals( (string) $existing['payload_hash'], (string) $normalized['payload_hash'] ) ) {
			$wpdb->query( 'ROLLBACK' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			return new WP_Error( 'ax_act_identity_conflict', __( 'That Activity URI already identifies a different immutable payload.', 'axismundi-activities' ) );
		}
		$wpdb->query( 'COMMIT' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return axismundi_act_hydrate( $existing );
	}

	if ( 'Undo' === $normalized['activity_type'] && ! empty( $normalized['object_uri'] ) ) {
		$target = axismundi_act_transaction_row( (string) $normalized['object_uri'] );
		if ( is_array( $target ) && ! hash_equals( (string) $target['actor_uri'], (string) $normalized['actor_uri'] ) ) {
			$wpdb->query( 'ROLLBACK' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			return new WP_Error( 'ax_act_undo_actor', __( 'Undo must be authored by the Actor that authored its target Activity.', 'axismundi-activities' ) );
		}
	}

	$inserted = $wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- custom Activity repository.
		$table,
		array_merge( $normalized, array( 'created_at' => $now, 'updated_at' => $now ) )
	);
	if ( false === $inserted ) {
		$wpdb->query( 'ROLLBACK' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		if ( '' !== $source_event_key ) {
			// A concurrent transaction may have won the unique source-event insert.
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- post-conflict source-event read.
			$winner = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE source_event_hash = %s", $normalized['source_event_hash'] ), ARRAY_A );
			if ( is_array( $winner ) && hash_equals( (string) $winner['source_event_key'], $source_event_key ) ) {
				if ( (string) $winner['activity_type'] !== (string) $normalized['activity_type']
					|| (string) $winner['direction'] !== (string) $normalized['direction']
					|| ! hash_equals( (string) $winner['actor_uri'], (string) $normalized['actor_uri'] )
					|| ! hash_equals( (string) ( $winner['object_uri'] ?? '' ), (string) ( $normalized['object_uri'] ?? '' ) )
					|| ! hash_equals( (string) ( $winner['target_uri'] ?? '' ), (string) ( $normalized['target_uri'] ?? '' ) )
					|| ! hash_equals( (string) ( $winner['instrument_uri'] ?? '' ), (string) ( $normalized['instrument_uri'] ?? '' ) )
				) {
					return new WP_Error( 'ax_act_source_conflict', __( 'That source event already identifies a different Activity.', 'axismundi-activities' ) );
				}
				return axismundi_act_hydrate( $winner );
			}
		}
		return new WP_Error( 'ax_act_write', __( 'The Activity could not be recorded.', 'axismundi-activities' ) );
	}
	if ( 'Undo' === $normalized['activity_type'] && ! empty( $normalized['object_uri'] ) && ! axismundi_act_recompute_effectiveness( (string) $normalized['object_uri'] ) ) {
		$wpdb->query( 'ROLLBACK' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return new WP_Error( 'ax_act_transition', __( 'The Undo transition could not be applied.', 'axismundi-activities' ) );
	}
	if ( ! axismundi_act_recompute_effectiveness( (string) $normalized['activity_uri'] ) ) {
		$wpdb->query( 'ROLLBACK' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return new WP_Error( 'ax_act_transition', __( 'The Activity effective state could not be derived.', 'axismundi-activities' ) );
	}
	$relation_events = array();
	if ( function_exists( 'axismundi_act_apply_relation_activity' ) ) {
		$relation_events = axismundi_act_apply_relation_activity( $normalized );
		if ( is_wp_error( $relation_events ) ) {
			$wpdb->query( 'ROLLBACK' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			return $relation_events;
		}
	}
	$wpdb->query( 'COMMIT' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	$activity = axismundi_act_get( (string) $normalized['activity_uri'] );
	if ( ! $activity instanceof Axismundi_Activity ) {
		return new WP_Error( 'ax_act_read_after_write', __( 'The recorded Activity could not be read.', 'axismundi-activities' ) );
	}
	/** @param Axismundi_Activity $activity Newly committed Activity. */
	do_action( 'axismundi_act_activity_recorded', $activity );
	foreach ( $relation_events as $relation ) {
		/** @param array<string,mixed> $relation Committed relation snapshot. */
		do_action( 'axismundi_act_relation_changed', $relation );
	}
	return $activity;
}
