<?php
/**
 * URI-keyed tag evidence and a rebuildable term usage projection.
 *
 * These are internal indexes, not public identifiers or visibility grants.
 *
 * @package AxismundiObjectProjections
 */

defined( 'ABSPATH' ) || exit;

function axismundi_op_tag_references_table() : string {
	global $wpdb;
	return $wpdb->prefix . 'ax_tag_references';
}

function axismundi_op_term_usage_table() : string {
	global $wpdb;
	return $wpdb->prefix . 'ax_term_usage';
}

/** Install both layers without changing WordPress term counts. */
function axismundi_op_install_tag_reference_schema() : bool {
	global $wpdb;
	require_once ABSPATH . 'wp-admin/includes/upgrade.php';
	$refs = axismundi_op_tag_references_table();
	$usage = axismundi_op_term_usage_table();
	$charset = $wpdb->get_charset_collate();
	dbDelta( "CREATE TABLE {$refs} (
		id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		source_uri text NOT NULL,
		source_uri_hash char(64) NOT NULL,
		provider varchar(32) NOT NULL,
		source_role varchar(20) NOT NULL,
		descriptor_hash char(64) NOT NULL,
		descriptor_json longtext NOT NULL,
		term_taxonomy_id bigint(20) unsigned DEFAULT NULL,
		position int unsigned NOT NULL,
		is_current tinyint unsigned NOT NULL DEFAULT 1,
		first_seen_at datetime NOT NULL,
		last_seen_at datetime NOT NULL,
		PRIMARY KEY  (id),
		UNIQUE KEY source_descriptor (source_uri_hash,descriptor_hash),
		KEY source_current (source_uri_hash,is_current)
	) ENGINE=InnoDB {$charset};" );
	dbDelta( "CREATE TABLE {$usage} (
		id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		term_taxonomy_id bigint(20) unsigned NOT NULL,
		source_uri text NOT NULL,
		source_uri_hash char(64) NOT NULL,
		provider varchar(32) NOT NULL,
		feed_sort_at datetime NOT NULL,
		is_current tinyint unsigned NOT NULL DEFAULT 1,
		PRIMARY KEY  (id),
		UNIQUE KEY term_source (term_taxonomy_id,source_uri_hash),
		KEY term_cursor (term_taxonomy_id,is_current,feed_sort_at,id),
		KEY source_uri_hash (source_uri_hash)
	) ENGINE=InnoDB {$charset};" );
	$valid = in_array( 'descriptor_json', (array) $wpdb->get_col( "SHOW COLUMNS FROM {$refs}" ), true )
		&& in_array( 'feed_sort_at', (array) $wpdb->get_col( "SHOW COLUMNS FROM {$usage}" ), true );
	if ( $valid ) {
		update_option( 'ax_op_tag_reference_schema', '1', false );
	}
	return $valid;
}

function axismundi_op_maybe_install_tag_references() : void {
	if ( '1' !== get_option( 'ax_op_tag_reference_schema' ) ) {
		axismundi_op_install_tag_reference_schema();
	}
}
add_action( 'init', 'axismundi_op_maybe_install_tag_references', 22 );

/** Internal evidence lookup; callers must apply their source's disclosure rules. */
function axismundi_op_tag_references( string $uri, bool $current_only = true ) : array {
	global $wpdb;
	$table = axismundi_op_tag_references_table();
	$current = $current_only ? ' AND is_current = 1' : '';
	return (array) $wpdb->get_results( $wpdb->prepare(
		"SELECT * FROM {$table} WHERE source_uri_hash = %s AND source_uri = %s{$current} ORDER BY position,id",
		hash( 'sha256', $uri ), $uri
	), ARRAY_A );
}

/** Refuse nested transactions before any taxonomy or index writes. */
function axismundi_op_tag_transaction_guard() {
	global $wpdb;
	if ( '1' !== (string) $wpdb->get_var( 'SELECT @@session.autocommit' ) ) {
		return new WP_Error( 'ax_op_tag_transaction', 'Tag projection requires autocommit and no enclosing transaction.' );
	}
	// MySQL and MariaDB reject SET TRANSACTION inside an active transaction.
	// Unlike START TRANSACTION, this statement never commits the caller's work.
	$previous = $wpdb->suppress_errors( true );
	$ready = $wpdb->query( 'SET TRANSACTION READ WRITE' );
	$wpdb->suppress_errors( $previous );
	return false === $ready ? new WP_Error( 'ax_op_tag_transaction', 'Tag projection cannot run inside an active transaction.' ) : true;
}

/**
 * Reconcile accepted source evidence. Partial observations never retract tags.
 *
 * `content` is an explicit participation grant, not an AS2 inheritance test.
 * Actor profiles and envelope Activities use `reference`; standalone Questions
 * may use `content`. This function never inspects an embedded object's tags.
 * Call after the source transaction commits; this projection owns its transaction.
 *
 * @return true|WP_Error
 */
function axismundi_op_replace_tag_references( string $uri, array $members, array $source ) {
	global $wpdb;
	$provider = (string) ( $source['provider'] ?? '' );
	$role = (string) ( $source['role'] ?? 'reference' );
	if ( ! preg_match( '~^https?://[^\s]+$~D', $uri ) || ! in_array( $provider, array( 'wordpress-taxonomy', 'remote-cache', 'c2s', 'actor-profile', 'activity-ledger' ), true ) || ! in_array( $role, array( 'content', 'reference' ), true ) || ( 'actor-profile' === $provider && 'reference' !== $role ) ) {
		return new WP_Error( 'ax_op_tag_source', 'A known source provider, absolute URI and explicit source role are required.' );
	}
	$refs = axismundi_op_tag_references_table();
	$usage = axismundi_op_term_usage_table();
	$hash = hash( 'sha256', $uri );
	$now = current_time( 'mysql', true );
	$sort = (string) ( $source['feed_sort_at'] ?? $now );
	$date = DateTimeImmutable::createFromFormat( '!Y-m-d H:i:s', $sort, new DateTimeZone( 'UTC' ) );
	if ( ! $date || $date->format( 'Y-m-d H:i:s' ) !== $sort ) {
		return new WP_Error( 'ax_op_tag_sort', 'The initial sort time must be a UTC database timestamp.' );
	}
	$guard = axismundi_op_tag_transaction_guard();
	if ( is_wp_error( $guard ) ) {
		return $guard;
	}
	$prepared = array();
	foreach ( $members as $position => $member ) {
		$json = wp_json_encode( $member, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
		if ( false === $json ) {
			return new WP_Error( 'ax_op_tag_descriptor', 'Tag evidence must be JSON encodable.' );
		}
		$term = null;
		$name = is_array( $member ) && is_string( $member['name'] ?? null ) ? $member['name'] : '';
		if ( str_starts_with( $name, '#' ) && axismundi_op_normalize_hashtag( $name ) ) {
			$term = axismundi_op_ensure_hashtag_term( $name );
			if ( is_wp_error( $term ) ) {
				return $term;
			}
		}
		$prepared[] = array( 'json' => $json, 'hash' => hash( 'sha256', $json ), 'term' => $term instanceof WP_Term ? (int) $term->term_taxonomy_id : null, 'position' => (int) $position );
	}
	if ( false === $wpdb->query( 'START TRANSACTION' ) ) {
		return new WP_Error( 'ax_op_tag_transaction', 'The tag projection transaction could not start.' );
	}
	try {
		$owners = $wpdb->get_col( $wpdb->prepare( "SELECT DISTINCT provider FROM {$refs} WHERE source_uri_hash = %s FOR UPDATE", $hash ) );
		if ( array_diff( $owners, array( $provider ) ) ) {
			throw new RuntimeException( 'Another provider owns the source evidence.' );
		}
		if ( ! empty( $source['complete'] ) ) {
			if ( false === $wpdb->update( $refs, array( 'is_current' => 0 ), array( 'source_uri_hash' => $hash, 'source_uri' => $uri ) ) ) {
				throw new RuntimeException( 'Could not retract prior evidence.' );
			}
		}
		foreach ( $prepared as $row ) {
			if ( false === $wpdb->query( $wpdb->prepare(
				"INSERT INTO %i (source_uri,source_uri_hash,provider,source_role,descriptor_hash,descriptor_json,term_taxonomy_id,position,is_current,first_seen_at,last_seen_at)
				 VALUES (%s,%s,%s,%s,%s,%s,NULLIF(%d,0),%d,1,%s,%s)
				 ON DUPLICATE KEY UPDATE source_role=VALUES(source_role),position=VALUES(position),is_current=1,last_seen_at=VALUES(last_seen_at),term_taxonomy_id=VALUES(term_taxonomy_id)",
				$refs, $uri, $hash, $provider, $role, $row['hash'], $row['json'], (int) $row['term'], $row['position'], $now, $now
			) ) ) {
				throw new RuntimeException( 'Could not persist tag evidence.' );
			}
		}
		if ( false === $wpdb->update( $usage, array( 'is_current' => 0 ), array( 'source_uri_hash' => $hash, 'source_uri' => $uri ) ) ) {
			throw new RuntimeException( 'Could not reconcile term membership.' );
		}
		// Recompute from all current evidence, including retained partial observations.
		if ( false === $wpdb->query( $wpdb->prepare(
			"INSERT INTO %i (term_taxonomy_id,source_uri,source_uri_hash,provider,feed_sort_at,is_current)
			 SELECT DISTINCT term_taxonomy_id,source_uri,source_uri_hash,provider,%s,1 FROM %i
			 WHERE source_uri_hash=%s AND source_uri=%s AND is_current=1 AND source_role='content' AND term_taxonomy_id IS NOT NULL
			 ON DUPLICATE KEY UPDATE is_current=1", $usage, $sort, $refs, $hash, $uri
		) ) || false === $wpdb->query( 'COMMIT' ) ) {
			throw new RuntimeException( 'Could not project term membership.' );
		}
		return true;
	} catch ( Throwable $error ) {
		$wpdb->query( 'ROLLBACK' );
		return new WP_Error( 'ax_op_tag_write', $error->getMessage() );
	}
}

/** Rebuild from Core taxonomy authority, never from our previous index. */
function axismundi_op_sync_post_tag_references( int $post_id, bool $skip_unchanged = false ) {
	if ( isset( $GLOBALS['axismundi_op_deleting_tag_sources'][ $post_id ] ) ) {
		return true;
	}
	$post = get_post( $post_id );
	$profile = $post instanceof WP_Post ? axismundi_op_local_object_profile( $post ) : null;
	if ( ! is_array( $profile ) ) {
		return true;
	}
	$terms = wp_get_object_terms( $post_id, AXISMUNDI_OP_HASHTAG_TAXONOMY );
	if ( is_wp_error( $terms ) ) {
		return $terms;
	}
	$members = array();
	if ( ! in_array( $post->post_status, array( 'trash', 'auto-draft' ), true ) ) {
		foreach ( $terms as $term ) {
			$members[] = array( 'name' => '#' . $term->name );
		}
	}
	$source = array(
		'provider' => 'wordpress-taxonomy', 'role' => 'content', 'complete' => true,
		'feed_sort_at' => '0000-00-00 00:00:00' !== $post->post_date_gmt ? $post->post_date_gmt : current_time( 'mysql', true ),
	);
	$fingerprint = hash( 'sha256', wp_json_encode( array( $profile['uri'], $members, $source, wp_list_pluck( $terms, 'term_taxonomy_id' ) ) ) );
	if ( $skip_unchanged && $fingerprint === ( $GLOBALS['axismundi_op_post_tag_fingerprints'][ $post_id ] ?? null ) ) {
		return true;
	}
	$result = axismundi_op_replace_tag_references( $profile['uri'], $members, $source );
	if ( true === $result ) {
		$GLOBALS['axismundi_op_post_tag_fingerprints'][ $post_id ] = $fingerprint;
	}
	return $result;
}

/** Project the accepted stored snapshot, not an incoming partial Update. */
function axismundi_op_sync_remote_tag_references( array $object ) {
	$payload = $object['payload'] ?? json_decode( (string) ( $object['payload_json'] ?? '' ), true );
	if ( ! is_array( $payload ) ) {
		return new WP_Error( 'ax_op_tag_payload', 'Stored tag evidence is unavailable.' );
	}
	$members = $payload['tag'] ?? array();
	$members = is_array( $members ) && array_is_list( $members ) ? $members : array( $members );
	return axismundi_op_replace_tag_references( (string) $object['object_uri'], 'active' === ( $object['object_status'] ?? '' ) ? $members : array(), array(
		'provider' => 'remote-cache', 'role' => 'content',
		'complete' => ! empty( $object['_relation_observation_complete'] ) || 'active' !== ( $object['object_status'] ?? '' ),
		'feed_sort_at' => $object['published_at'] ?? $object['created_at'] ?? current_time( 'mysql', true ),
	) );
}

function axismundi_op_tag_terms_changed( $object_id, $terms, $tt_ids, $taxonomy ) : void {
	if ( AXISMUNDI_OP_HASHTAG_TAXONOMY === $taxonomy ) {
		axismundi_op_sync_post_tag_references( (int) $object_id, true );
	}
}
add_action( 'set_object_terms', 'axismundi_op_tag_terms_changed', 100, 4 );

function axismundi_op_tag_terms_removed( $object_id, $tt_ids, $taxonomy ) : void {
	if ( AXISMUNDI_OP_HASHTAG_TAXONOMY === $taxonomy ) {
		axismundi_op_sync_post_tag_references( (int) $object_id, true );
	}
}
add_action( 'deleted_term_relationships', 'axismundi_op_tag_terms_removed', 100, 3 );
function axismundi_op_saved_post_tag_references( int $id ) : void {
	axismundi_op_sync_post_tag_references( $id, true );
}
add_action( 'save_post', 'axismundi_op_saved_post_tag_references', 110 );
add_action( 'edit_attachment', 'axismundi_op_saved_post_tag_references', 110 );

/** Retract only this provider's evidence; remote eviction cannot touch local rows. */
function axismundi_op_retract_tag_source( string $uri, string $provider ) : void {
	global $wpdb;
	foreach ( array( axismundi_op_tag_references_table(), axismundi_op_term_usage_table() ) as $table ) {
		$wpdb->update( $table, array( 'is_current' => 0 ), array( 'source_uri_hash' => hash( 'sha256', $uri ), 'source_uri' => $uri, 'provider' => $provider ) );
	}
}

function axismundi_op_deleted_post_tag_references( int $id, WP_Post $post ) : void {
	$profile = axismundi_op_local_object_profile( $post );
	if ( is_array( $profile ) ) {
		// Core removes term relationships after before_delete_post; its term cache
		// may still contain the previous assignment during those callbacks.
		$GLOBALS['axismundi_op_deleting_tag_sources'][ $id ] = $profile['uri'];
		axismundi_op_retract_tag_source( $profile['uri'], 'wordpress-taxonomy' );
	}
}
add_action( 'before_delete_post', 'axismundi_op_deleted_post_tag_references', 5, 2 );

function axismundi_op_finish_deleted_post_tags( int $id ) : void {
	if ( isset( $GLOBALS['axismundi_op_deleting_tag_sources'][ $id ] ) ) {
		axismundi_op_retract_tag_source( $GLOBALS['axismundi_op_deleting_tag_sources'][ $id ], 'wordpress-taxonomy' );
		unset( $GLOBALS['axismundi_op_deleting_tag_sources'][ $id ] );
	}
}
add_action( 'deleted_post', 'axismundi_op_finish_deleted_post_tags', 100 );

/** Keep descriptor evidence when a local vocabulary mapping is removed. */
function axismundi_op_deleted_tag_term( int $term_id, int $tt_id, string $taxonomy ) : void {
	if ( AXISMUNDI_OP_HASHTAG_TAXONOMY !== $taxonomy ) {
		return;
	}
	global $wpdb;
	$wpdb->update( axismundi_op_tag_references_table(), array( 'term_taxonomy_id' => null ), array( 'term_taxonomy_id' => $tt_id ) );
	$wpdb->delete( axismundi_op_term_usage_table(), array( 'term_taxonomy_id' => $tt_id ) );
	$GLOBALS['axismundi_op_post_tag_fingerprints'] = array();
}
add_action( 'delete_term', 'axismundi_op_deleted_tag_term', 100, 3 );

/** Bounded, resumable rebuild. No network requests or taxonomy count overrides. */
function axismundi_op_rebuild_tag_references( string $provider, int $after = 0, int $limit = 100 ) {
	global $wpdb;
	if ( ! in_array( $provider, array( 'wordpress-taxonomy', 'remote-cache' ), true ) ) {
		return new WP_Error( 'ax_op_tag_provider', 'This provider has no batch rebuild adapter.' );
	}
	$limit = max( 1, min( 500, $limit ) );
	$table = 'remote-cache' === $provider ? axismundi_op_remote_objects_table() : $wpdb->posts;
	if ( 'wordpress-taxonomy' === $provider ) {
		$types = axismundi_op_hashtag_object_types();
		if ( ! $types ) {
			return array( 'after' => $after, 'processed' => 0, 'failed' => 0, 'errors' => array(), 'done' => true );
		}
		$placeholders = implode( ',', array_fill( 0, count( $types ), '%s' ) );
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT ID FROM %i WHERE ID > %d AND post_type IN ({$placeholders}) ORDER BY ID LIMIT %d", array_merge( array( $table, max( 0, $after ) ), $types, array( $limit ) ) ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- placeholders contains only generated %s tokens; every value is prepared.
	} else {
		$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM %i WHERE ID > %d ORDER BY ID LIMIT %d', $table, max( 0, $after ), $limit ), ARRAY_A );
	}
	if ( $wpdb->last_error ) {
		return new WP_Error( 'ax_op_tag_rebuild_read', $wpdb->last_error );
	}
	$result = array( 'after' => $after, 'processed' => 0, 'failed' => 0, 'errors' => array(), 'done' => count( $rows ) < $limit );
	foreach ( $rows as $row ) {
		$id = (int) ( $row['ID'] ?? $row['id'] );
		$written = 'remote-cache' === $provider ? axismundi_op_sync_remote_tag_references( $row ) : axismundi_op_sync_post_tag_references( (int) $row['ID'] );
		if ( is_wp_error( $written ) ) {
			$result['failed']++;
			$result['errors'][] = array( 'id' => $id, 'code' => $written->get_error_code(), 'message' => $written->get_error_message() );
		}
		$result['after'] = $id;
		$result['processed']++;
	}
	return $result;
}
