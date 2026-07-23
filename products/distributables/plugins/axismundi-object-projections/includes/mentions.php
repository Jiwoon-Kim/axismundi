<?php
/**
 * Rebuildable Object-to-Actor Mention relation projection.
 *
 * @package AxismundiObjectProjections
 */

defined( 'ABSPATH' ) || exit;

/** @return string Mention relation table name. */
function axismundi_op_object_mentions_table() : string {
	global $wpdb;
	return $wpdb->prefix . 'ax_object_mentions';
}

/** Install and verify the Object-to-Actor Mention projection. */
function axismundi_op_install_object_mentions() : bool {
	global $wpdb;
	require_once ABSPATH . 'wp-admin/includes/upgrade.php';
	$table   = axismundi_op_object_mentions_table();
	$charset = $wpdb->get_charset_collate();
	dbDelta(
		"CREATE TABLE {$table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			source_object_uri text NOT NULL,
			source_object_uri_hash char(64) NOT NULL,
			target_actor_uri text NOT NULL,
			target_actor_uri_hash char(64) NOT NULL,
			origin varchar(16) NOT NULL,
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY source_target_origin (source_object_uri_hash,target_actor_uri_hash,origin),
			KEY target_lookup (target_actor_uri_hash),
			KEY source_lookup (source_object_uri_hash)
		) ENGINE=InnoDB {$charset};"
	);
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fixed schema verification.
	$columns = (array) $wpdb->get_col( "SHOW COLUMNS FROM {$table}" );
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fixed schema verification.
	$index = (array) $wpdb->get_results( "SHOW INDEX FROM {$table} WHERE Key_name = 'source_target_origin'", ARRAY_A );
	return in_array( 'origin', $columns, true ) && count( $index ) >= 3 && 0 === (int) $index[0]['Non_unique'];
}

/** Normalize one Object or Actor relation URI without issuing a network request. */
function axismundi_op_mention_uri( $value ) : string {
	return function_exists( 'axismundi_op_relation_uri' ) ? axismundi_op_relation_uri( $value ) : '';
}

/** Replace one source Object's Mention rows from a provenance-keyed URI map. */
function axismundi_op_replace_object_mentions( string $source_uri, array $mentions ) : bool {
	global $wpdb;
	$source = axismundi_op_mention_uri( $source_uri );
	if ( '' === $source ) {
		return false;
	}
	$allowed = array( 'explicit', 'inline', 'remote' );
	$rows    = array();
	foreach ( $mentions as $origin => $uris ) {
		if ( ! in_array( $origin, $allowed, true ) ) {
			continue;
		}
		foreach ( array_values( array_unique( array_map( 'strval', (array) $uris ) ) ) as $uri ) {
			$target = axismundi_op_mention_uri( $uri );
			if ( '' !== $target ) {
				$rows[ $origin . '\n' . $target ] = array( 'origin' => $origin, 'target' => $target );
			}
		}
	}
	$table = axismundi_op_object_mentions_table();
	$now   = current_time( 'mysql', true );
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- atomic rebuildable relation replacement.
	$wpdb->query( 'START TRANSACTION' );
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- exact source replacement.
	$deleted = $wpdb->query( $wpdb->prepare( "DELETE FROM {$table} WHERE source_object_uri_hash = %s AND source_object_uri = %s", hash( 'sha256', $source ), $source ) );
	if ( false === $deleted ) {
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- transaction rollback.
		$wpdb->query( 'ROLLBACK' );
		return false;
	}
	foreach ( $rows as $row ) {
		$ok = $wpdb->insert(
			$table,
			array(
				'source_object_uri'      => $source,
				'source_object_uri_hash' => hash( 'sha256', $source ),
				'target_actor_uri'       => $row['target'],
				'target_actor_uri_hash'  => hash( 'sha256', $row['target'] ),
				'origin'                 => $row['origin'],
				'created_at'             => $now,
				'updated_at'             => $now,
			),
			array( '%s', '%s', '%s', '%s', '%s', '%s', '%s' )
		); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- rebuildable relation insertion.
		if ( false === $ok ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- transaction rollback.
			$wpdb->query( 'ROLLBACK' );
			return false;
		}
	}
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- transaction commit.
	$wpdb->query( 'COMMIT' );
	return true;
}

/** Extract unresolved-safe remote Mention Actor URI evidence. */
function axismundi_op_remote_object_mention_uris( array $payload ) : array {
	$tags = $payload['tag'] ?? array();
	$tags = is_array( $tags ) && array_is_list( $tags ) ? $tags : array( $tags );
	$uris = array();
	foreach ( $tags as $tag ) {
		if ( ! is_array( $tag ) || ! in_array( 'Mention', (array) ( $tag['type'] ?? array() ), true ) ) {
			continue;
		}
		$uri = axismundi_op_mention_uri( $tag['href'] ?? $tag['id'] ?? '' );
		if ( '' !== $uri ) {
			$uris[] = $uri;
		}
	}
	return array_values( array_unique( $uris ) );
}

/** Rebuild one remote Object's Mention rows from its stored payload snapshot. */
function axismundi_op_index_remote_object_mentions( array $object ) : bool {
	$source = axismundi_op_mention_uri( $object['object_uri'] ?? '' );
	if ( '' === $source ) {
		return false;
	}
	$payload = is_array( $object['payload'] ?? null ) ? $object['payload'] : json_decode( (string) ( $object['payload_json'] ?? '' ), true );
	return axismundi_op_replace_object_mentions( $source, array( 'remote' => is_array( $payload ) && 'active' === (string) ( $object['object_status'] ?? '' ) ? axismundi_op_remote_object_mention_uris( $payload ) : array() ) );
}

/** Return indexed Mention rows for one Actor URI. */
function axismundi_op_object_mentions_for_actor( string $actor_uri ) : array {
	global $wpdb;
	$actor = axismundi_op_mention_uri( $actor_uri );
	if ( '' === $actor ) {
		return array();
	}
	$table = axismundi_op_object_mentions_table();
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- indexed exact Actor lookup.
	return (array) $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} WHERE target_actor_uri_hash = %s AND target_actor_uri = %s ORDER BY updated_at DESC, id DESC", hash( 'sha256', $actor ), $actor ), ARRAY_A );
}

/**
 * Return the Actors one Object mentions, resolved for display where possible.
 *
 * An unresolved target stays in the result: an observed Object is valid even
 * when its mentioned Actor has never been fetched, and discovery must never be
 * a synchronous rendering dependency.
 *
 * The index stores identity, not the authored spelling, so an unresolved target
 * has no handle to display until its Actor is discovered.
 *
 * @return array<int,array{uri:string,name:string,handle:string,url:string,origin:string,resolved:bool}>
 */
function axismundi_op_mentions_for_object( string $source_uri ) : array {
	global $wpdb;
	$source = axismundi_op_mention_uri( $source_uri );
	if ( '' === $source ) {
		return array();
	}
	$table = axismundi_op_object_mentions_table();
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- indexed exact source Object lookup.
	$rows  = (array) $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} WHERE source_object_uri_hash = %s AND source_object_uri = %s ORDER BY id ASC", hash( 'sha256', $source ), $source ), ARRAY_A );
	$items = array();
	foreach ( $rows as $row ) {
		$target = (string) ( $row['target_actor_uri'] ?? '' );
		$actor  = '' !== $target && function_exists( 'axismundi_actors_get_by_uri' ) ? axismundi_actors_get_by_uri( $target ) : null;
		$handle = $actor instanceof Axismundi_Actor && function_exists( 'axismundi_actors_federated_mention_name' )
			? axismundi_actors_federated_mention_name( $actor )
			: '';
		$items[] = array(
			'uri'      => $target,
			'name'     => $actor instanceof Axismundi_Actor ? $actor->get_display_name() : '',
			'handle'   => $handle,
			'url'      => $actor instanceof Axismundi_Actor && function_exists( 'axismundi_actors_profile_hub_url' ) ? axismundi_actors_profile_hub_url( $actor ) : $target,
			'origin'   => (string) ( $row['origin'] ?? '' ),
			'resolved' => $actor instanceof Axismundi_Actor,
		);
	}
	return $items;
}

/** Index one local Core Post after its block content and REST meta are committed. */
function axismundi_op_index_local_post_mentions( int $post_id, WP_Post $post, bool $update, ?WP_Post $post_before ) : void {
	unset( $post_id, $update, $post_before );
	if ( ! function_exists( 'axismundi_op_post_article_supports' ) || ! axismundi_op_post_article_supports( $post ) ) {
		return;
	}
	$explicit = axismundi_op_sanitize_post_mentions( get_post_meta( $post->ID, AXISMUNDI_OP_POST_MENTIONS_META, true ) );
	$inline   = function_exists( 'axismundi_op_post_content_mentions' ) ? axismundi_op_post_content_mentions( $post ) : array();
	axismundi_op_replace_object_mentions( axismundi_op_post_object_uri( $post ), array( 'explicit' => $explicit, 'inline' => $inline ) );
}
add_action( 'wp_after_insert_post', 'axismundi_op_index_local_post_mentions', 100, 4 );

/** Refresh a Core Post edge projection when the REST/editor mention meta changes. */
function axismundi_op_refresh_local_post_mentions_meta( $meta_id, int $post_id, string $meta_key, $meta_value ) : void {
	unset( $meta_id, $meta_value );
	if ( AXISMUNDI_OP_POST_MENTIONS_META !== $meta_key || ! empty( $GLOBALS['axismundi_op_deleting_mention_posts'][ $post_id ] ) ) {
		return;
	}
	$post = get_post( $post_id );
	if ( $post instanceof WP_Post ) {
		axismundi_op_index_local_post_mentions( $post_id, $post, true, null );
	}
}
add_action( 'added_post_meta', 'axismundi_op_refresh_local_post_mentions_meta', 10, 4 );
add_action( 'updated_post_meta', 'axismundi_op_refresh_local_post_mentions_meta', 10, 4 );
add_action( 'deleted_post_meta', 'axismundi_op_refresh_local_post_mentions_meta', 10, 4 );

/** Remove local source edges before a Post or Note is permanently deleted. */
function axismundi_op_delete_local_object_mentions( int $post_id, WP_Post $post ) : void {
	$GLOBALS['axismundi_op_deleting_mention_posts'][ $post_id ] = true;
	if ( 'post' === $post->post_type && function_exists( 'axismundi_op_post_object_uri' ) ) {
		axismundi_op_replace_object_mentions( axismundi_op_post_object_uri( $post ), array() );
		return;
	}
	if ( defined( 'AXISMUNDI_NOTE_POST_TYPE' )
		&& AXISMUNDI_NOTE_POST_TYPE === $post->post_type
		&& function_exists( 'axismundi_note_get' )
		&& function_exists( 'axismundi_note_object_uri' )
	) {
		$envelope = axismundi_note_get( $post_id );
		if ( is_array( $envelope ) ) {
			axismundi_op_replace_object_mentions( axismundi_note_object_uri( (string) ( $envelope['local_uuid'] ?? '' ) ), array() );
		}
	}
}
add_action( 'before_delete_post', 'axismundi_op_delete_local_object_mentions', 10, 2 );

/** Index a Note after its owning product commits its envelope. */
function axismundi_op_index_local_note_mentions( array $envelope, WP_Post $post ) : void {
	if ( ! function_exists( 'axismundi_note_object_uri' ) || ! function_exists( 'axismundi_note_mentions' ) ) {
		return;
	}
	$explicit = json_decode( (string) ( $envelope['mention_actor_uris_json'] ?? '' ), true );
	$inline   = function_exists( 'axismundi_op_content_mention_uris' ) ? axismundi_op_content_mention_uris( $post->post_content ) : array();
	axismundi_op_replace_object_mentions(
		axismundi_note_object_uri( (string) ( $envelope['local_uuid'] ?? '' ) ),
		array( 'explicit' => is_array( $explicit ) ? $explicit : array(), 'inline' => $inline )
	);
}
add_action( 'axismundi_note_envelope_saved', 'axismundi_op_index_local_note_mentions', 10, 2 );
