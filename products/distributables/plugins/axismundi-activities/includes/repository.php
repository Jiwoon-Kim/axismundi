<?php
/**
 * Phase 1 - immutable URI-keyed Activity repository.
 *
 * @package AxismundiActivities
 */

defined( 'ABSPATH' ) || exit;

const AXISMUNDI_ACT_DB_VERSION        = '4';
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

	$table   = axismundi_act_activities_table();
	$charset = $wpdb->get_charset_collate();
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
			source_event_key text DEFAULT NULL,
			source_event_hash char(64) DEFAULT NULL,
			direction varchar(8) NOT NULL,
			effective_status varchar(8) NOT NULL DEFAULT 'active',
			audience_json longtext NOT NULL,
			payload_json longtext NOT NULL,
			payload_hash char(64) NOT NULL,
			published_at datetime DEFAULT NULL,
			received_at datetime DEFAULT NULL,
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY activity_uri_hash (activity_uri_hash),
			UNIQUE KEY local_uuid (local_uuid),
			UNIQUE KEY source_event_hash (source_event_hash),
			KEY actor_uri_hash (actor_uri_hash),
			KEY object_uri_hash (object_uri_hash),
			KEY target_uri_hash (target_uri_hash),
			KEY direction_status (direction, effective_status)
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
		&& ! in_array( 'blog_id', $columns, true )
		&& ! empty( $uri_index )
		&& 0 === (int) $uri_index[0]['Non_unique']
		&& ! empty( $uuid_index )
		&& 0 === (int) $uuid_index[0]['Non_unique']
		&& ! empty( $source_index )
		&& 0 === (int) $source_index[0]['Non_unique']
		&& 'InnoDB' === $engine;
	$relations_valid = function_exists( 'axismundi_act_install_relations' ) && axismundi_act_install_relations();
	$valid           = $base_valid && $relations_valid;
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
	public function get_direction() : string { return (string) $this->row['direction']; }
	public function get_effective_status() : string { return (string) $this->row['effective_status']; }
	public function is_effective() : bool { return 'active' === $this->get_effective_status(); }
	/** @return array<string,string[]> */
	public function get_audience() : array { return (array) $this->row['audience']; }
	/** @return array<string,mixed> */
	public function get_payload() : array { return (array) $this->row['payload']; }
	public function get_published_at() : ?string { return null !== $this->row['published_at'] ? (string) $this->row['published_at'] : null; }
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
	$types = array( 'Follow', 'Accept', 'Reject', 'Undo', 'Like', 'Announce', 'Create', 'Update', 'Delete', 'Add', 'Remove', 'Move', 'Join', 'Leave', 'Block', 'Flag' );
	/** @param string[] $types Supported ActivityStreams activity types. */
	return array_values( array_unique( array_filter( array_map( 'sanitize_text_field', (array) apply_filters( 'axismundi_act_types', $types ) ) ) ) );
}

/** First supported Activity type from a scalar/list. */
function axismundi_act_type( $value ) : string {
	foreach ( is_array( $value ) ? $value : array( $value ) as $type ) {
		$type = is_scalar( $type ) ? substr( sanitize_text_field( (string) $type ), 0, 32 ) : '';
		if ( in_array( $type, axismundi_act_types(), true ) ) {
			return $type;
		}
	}
	return '';
}

/** UTC SQL datetime from an ISO value. */
function axismundi_act_datetime( $value ) : ?string {
	if ( ! is_scalar( $value ) || '' === trim( (string) $value ) ) {
		return null;
	}
	$time = strtotime( (string) $value );
	return false === $time ? null : gmdate( 'Y-m-d H:i:s', $time );
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

/** Whether an Activity declares the ActivityStreams Public audience. */
function axismundi_act_has_public_audience( Axismundi_Activity $activity ) : bool {
	$audience = $activity->get_audience();
	$public   = array( 'https://www.w3.org/ns/activitystreams#Public', 'as:Public' );
	return (bool) array_intersect( $public, (array) ( $audience['to'] ?? array() ) )
		|| (bool) array_intersect( $public, (array) ( $audience['cc'] ?? array() ) );
}

/** Whether one effective outbound Activity is addressed to the public. */
function axismundi_act_is_public( Axismundi_Activity $activity ) : bool {
	return 'outbound' === $activity->get_direction() && $activity->is_effective() && axismundi_act_has_public_audience( $activity );
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
	if ( '' === $object_uri ) {
		return new WP_Error( 'ax_act_object', __( 'This Activity type requires an object URI.', 'axismundi-activities' ) );
	}
	if ( in_array( $type, array( 'Add', 'Remove', 'Move' ), true ) && '' === $target_uri ) {
		return new WP_Error( 'ax_act_target', __( 'This collection Activity requires a target URI.', 'axismundi-activities' ) );
	}
	$now = current_time( 'mysql', true );
	if ( ! isset( $payload['published'] ) && 'inbound' !== $direction ) {
		$payload['published'] = gmdate( 'c', strtotime( $now . ' UTC' ) );
	}
	$payload['id'] = $activity_uri;
	$json          = wp_json_encode( $payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
	$audience_json = wp_json_encode( axismundi_act_audience( $payload ), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
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
		'direction'         => $direction,
		'effective_status'  => 'active',
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
