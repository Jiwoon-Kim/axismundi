<?php
/**
 * Shared ActivityStreams hashtag vocabulary and remote-observation index.
 *
 * @package AxismundiObjectProjections
 */

defined( 'ABSPATH' ) || exit;

const AXISMUNDI_OP_HASHTAG_TAXONOMY     = 'ax_hashtag';
const AXISMUNDI_OP_HASHTAG_KEY_META     = '_ax_op_hashtag_key';
const AXISMUNDI_OP_HASHTAG_MAX_LENGTH   = 100;

/** @return string Remote cached Object-to-hashtag index table name. */
function axismundi_op_remote_object_hashtags_table() : string {
	global $wpdb;
	return $wpdb->prefix . 'ax_remote_object_hashtags';
}

/** Return WordPress object types that share the social hashtag vocabulary. */
function axismundi_op_hashtag_object_types() : array {
	$types = apply_filters( 'axismundi_op_hashtag_object_types', array( 'post', 'attachment' ) );
	return array_values(
		array_unique(
			array_filter(
				array_map( 'sanitize_key', (array) $types ),
				'post_type_exists'
			)
		)
	);
}

/** Register the shared, non-hierarchical ActivityStreams hashtag vocabulary. */
function axismundi_op_register_hashtag_taxonomy() : void {
	register_taxonomy(
		AXISMUNDI_OP_HASHTAG_TAXONOMY,
		axismundi_op_hashtag_object_types(),
		array(
			'labels'            => array(
				'name'          => __( 'Hashtags', 'axismundi-object-projections' ),
				'singular_name' => __( 'Hashtag', 'axismundi-object-projections' ),
			),
			'public'            => true,
			'show_ui'           => true,
			'show_in_rest'      => true,
			'show_admin_column' => true,
			'hierarchical'      => false,
			'rewrite'           => array( 'slug' => 'hashtag' ),
		)
	);
}
add_action( 'init', 'axismundi_op_register_hashtag_taxonomy', 20 );

/** Whether the registered hashtag taxonomy has a persisted pretty-permalink rule. */
function axismundi_op_hashtag_routes_installed() : bool {
	if ( '' === (string) get_option( 'permalink_structure', '' ) ) {
		return true;
	}
	foreach ( array_keys( (array) get_option( 'rewrite_rules', array() ) ) as $rule ) {
		if ( str_starts_with( (string) $rule, 'hashtag/' ) ) {
			return true;
		}
	}
	return false;
}

/** Self-heal rewrite rules after ZIP replacement or a missed activation hook. */
function axismundi_op_maybe_upgrade_hashtag_routes() : void {
	if ( axismundi_op_hashtag_routes_installed() || get_transient( 'ax_op_hashtag_rewrite_retry' ) ) {
		return;
	}
	set_transient( 'ax_op_hashtag_rewrite_retry', 1, HOUR_IN_SECONDS );
	flush_rewrite_rules( false );
}
add_action( 'init', 'axismundi_op_maybe_upgrade_hashtag_routes', 21 );

/** Normalize a hashtag name without collapsing distinct spellings or translations. */
function axismundi_op_normalize_hashtag( $value ) : array {
	$name = trim( (string) $value );
	if ( str_starts_with( $name, '#' ) ) {
		$name = ltrim( substr( $name, 1 ) );
	}
	if ( class_exists( 'Normalizer' ) ) {
		$normalized = Normalizer::normalize( $name, Normalizer::FORM_C );
		if ( is_string( $normalized ) ) {
			$name = $normalized;
		}
	}
	$name = function_exists( 'mb_substr' )
		? mb_substr( $name, 0, AXISMUNDI_OP_HASHTAG_MAX_LENGTH )
		: substr( $name, 0, AXISMUNDI_OP_HASHTAG_MAX_LENGTH );
	if ( '' === $name || preg_match( '/[\x00-\x1F\x7F]/u', $name ) ) {
		return array();
	}
	$key = function_exists( 'mb_strtolower' ) ? mb_strtolower( $name, 'UTF-8' ) : strtolower( $name );
	return array( 'name' => $name, 'key' => $key );
}

/** Find or create one normalized shared hashtag term. */
function axismundi_op_ensure_hashtag_term( $value ) {
	$hashtag = axismundi_op_normalize_hashtag( $value );
	if ( empty( $hashtag ) ) {
		return new WP_Error( 'ax_op_hashtag_name', __( 'A hashtag name is required.', 'axismundi-object-projections' ) );
	}
	$terms = get_terms(
		array(
			'taxonomy'   => AXISMUNDI_OP_HASHTAG_TAXONOMY,
			'hide_empty' => false,
			'meta_query' => array(
				array( 'key' => AXISMUNDI_OP_HASHTAG_KEY_META, 'value' => $hashtag['key'] ),
			),
			'number'      => 1,
		)
	);
	if ( ! is_wp_error( $terms ) && ! empty( $terms[0] ) ) {
		return $terms[0];
	}
	$inserted = wp_insert_term( $hashtag['name'], AXISMUNDI_OP_HASHTAG_TAXONOMY );
	if ( is_wp_error( $inserted ) ) {
		if ( 'term_exists' !== $inserted->get_error_code() ) {
			return $inserted;
		}
		$term_id = (int) $inserted->get_error_data();
	} else {
		$term_id = (int) $inserted['term_id'];
	}
	update_term_meta( $term_id, AXISMUNDI_OP_HASHTAG_KEY_META, $hashtag['key'] );
	$term = get_term( $term_id, AXISMUNDI_OP_HASHTAG_TAXONOMY );
	return $term instanceof WP_Term ? $term : new WP_Error( 'ax_op_hashtag_term', __( 'The hashtag term could not be read.', 'axismundi-object-projections' ) );
}

/** Whether one local object type may serialize its hashtags to ActivityStreams. */
function axismundi_op_hashtag_is_federated( WP_Post $post ) : bool {
	$default = 'post' === $post->post_type;
	return (bool) apply_filters( 'axismundi_op_hashtag_is_federated', $default, $post );
}

/** Return ActivityStreams Hashtag descriptors for one local, federated Object. */
function axismundi_op_post_hashtag_tags( WP_Post $post ) : array {
	if ( ! axismundi_op_hashtag_is_federated( $post ) ) {
		return array();
	}
	$terms = get_the_terms( $post, AXISMUNDI_OP_HASHTAG_TAXONOMY );
	if ( ! is_array( $terms ) ) {
		return array();
	}
	$tags = array();
	foreach ( $terms as $term ) {
		$url = get_term_link( $term );
		if ( is_wp_error( $url ) ) {
			continue;
		}
		$tags[] = array( 'type' => 'Hashtag', 'name' => '#' . $term->name, 'href' => $url );
	}
	return $tags;
}

/**
 * Renderable hashtag chips for one Object URI, local or cached remote.
 *
 * Local Objects use the taxonomy relationship; a cached remote observation uses
 * the rebuildable index. Both resolve to the same shared terms, so a chip links
 * to this site's hashtag archive rather than to a remote tag page.
 *
 * @return array<int,array{name:string,slug:string,url:string}>
 */
function axismundi_op_object_hashtag_chips( string $object_uri ) : array {
	global $wpdb;
	$uri = trim( $object_uri );
	if ( '' === $uri ) {
		return array();
	}
	$terms = array();
	$post  = function_exists( 'axismundi_op_local_source_from_object_uri' ) ? axismundi_op_local_source_from_object_uri( $uri ) : null;
	if ( $post instanceof WP_Post ) {
		$assigned = get_the_terms( $post, AXISMUNDI_OP_HASHTAG_TAXONOMY );
		$terms    = is_array( $assigned ) ? $assigned : array();
	} else {
		$index   = axismundi_op_remote_object_hashtags_table();
		$objects = axismundi_op_remote_objects_table();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fixed projection tables; URI hash prepared.
		$ids = (array) $wpdb->get_col( $wpdb->prepare( "SELECT h.term_taxonomy_id FROM {$index} h INNER JOIN {$objects} o ON o.id = h.remote_object_id WHERE o.object_uri_hash = %s", hash( 'sha256', $uri ) ) );
		foreach ( $ids as $term_taxonomy_id ) {
			$term = get_term_by( 'term_taxonomy_id', (int) $term_taxonomy_id );
			if ( $term instanceof WP_Term && AXISMUNDI_OP_HASHTAG_TAXONOMY === $term->taxonomy ) {
				$terms[] = $term;
			}
		}
	}
	$chips = array();
	foreach ( $terms as $term ) {
		$url = get_term_link( $term );
		$chips[] = array(
			'name' => (string) $term->name,
			'slug' => (string) $term->slug,
			'url'  => is_wp_error( $url ) ? '' : (string) $url,
		);
	}
	return $chips;
}

/** Create or upgrade the rebuildable remote Object-to-hashtag index. */
function axismundi_op_install_remote_object_hashtag_schema() : bool {
	global $wpdb;
	require_once ABSPATH . 'wp-admin/includes/upgrade.php';
	$table   = axismundi_op_remote_object_hashtags_table();
	$charset = $wpdb->get_charset_collate();
	dbDelta(
		"CREATE TABLE {$table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			remote_object_id bigint(20) unsigned NOT NULL,
			term_taxonomy_id bigint(20) unsigned NOT NULL,
			source_href text DEFAULT NULL,
			observed_name text NOT NULL,
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY object_term (remote_object_id,term_taxonomy_id),
			KEY term_taxonomy_id (term_taxonomy_id)
		) ENGINE=InnoDB {$charset};"
	);
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fixed schema verification.
	$columns = (array) $wpdb->get_col( "SHOW COLUMNS FROM {$table}" );
	return in_array( 'remote_object_id', $columns, true ) && in_array( 'term_taxonomy_id', $columns, true );
}

/** Extract valid ActivityStreams Hashtag evidence from a remote Object payload. */
function axismundi_op_remote_object_hashtag_descriptors( array $payload ) : array {
	$members = $payload['tag'] ?? array();
	$members = is_array( $members ) && array_is_list( $members ) ? $members : array( $members );
	$tags    = array();
	foreach ( $members as $member ) {
		if ( ! is_array( $member ) ) {
			continue;
		}
		$type = $member['type'] ?? '';
		$type = is_array( $type ) ? $type : array( $type );
		if ( ! in_array( 'Hashtag', $type, true ) ) {
			continue;
		}
		$normalized = axismundi_op_normalize_hashtag( $member['name'] ?? '' );
		if ( empty( $normalized ) ) {
			continue;
		}
		$href = axismundi_op_remote_member_uri( $member['href'] ?? $member['id'] ?? '' );
		// The first observed spelling/link is the stable display evidence for this
		// payload. Later case-only variants prove the same comparison identity.
		if ( ! isset( $tags[ $normalized['key'] ] ) ) {
			$tags[ $normalized['key'] ] = array( 'name' => $normalized['name'], 'href' => $href );
		}
	}
	return array_values( $tags );
}

/** Rebuild one remote Object's hashtag rows from its current stored payload. */
function axismundi_op_index_remote_object_hashtags( array $object ) : bool {
	global $wpdb;
	$object_id = (int) ( $object['id'] ?? 0 );
	if ( $object_id <= 0 ) {
		return false;
	}
	$table = axismundi_op_remote_object_hashtags_table();
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- rebuildable index replacement.
	$wpdb->delete( $table, array( 'remote_object_id' => $object_id ), array( '%d' ) );
	if ( 'active' !== (string) ( $object['object_status'] ?? '' ) ) {
		return true;
	}
	$payload = isset( $object['payload'] ) && is_array( $object['payload'] ) ? $object['payload'] : json_decode( (string) ( $object['payload_json'] ?? '' ), true );
	if ( ! is_array( $payload ) ) {
		return true;
	}
	$now = current_time( 'mysql', true );
	foreach ( axismundi_op_remote_object_hashtag_descriptors( $payload ) as $descriptor ) {
		$term = axismundi_op_ensure_hashtag_term( $descriptor['name'] );
		if ( ! $term instanceof WP_Term ) {
			continue;
		}
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- rebuildable index insert.
		$wpdb->insert(
			$table,
			array(
				'remote_object_id' => $object_id,
				'term_taxonomy_id' => (int) $term->term_taxonomy_id,
				'source_href'      => '' !== $descriptor['href'] ? $descriptor['href'] : null,
				'observed_name'    => $descriptor['name'],
				'created_at'       => $now,
				'updated_at'       => $now,
			),
			array( '%d', '%d', '%s', '%s', '%s', '%s' )
		);
	}
	return true;
}

/** Delete hashtag index rows for one local remote-cache row. */
function axismundi_op_delete_remote_object_hashtags( int $object_id ) : void {
	if ( $object_id <= 0 ) {
		return;
	}
	global $wpdb;
	$table = axismundi_op_remote_object_hashtags_table();
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- explicit rebuildable index cleanup.
	$wpdb->delete( $table, array( 'remote_object_id' => $object_id ), array( '%d' ) );
}

/** Purge remote hashtag rows whose observation no longer exists. */
function axismundi_op_purge_orphan_remote_object_hashtags() : void {
	global $wpdb;
	$table   = axismundi_op_remote_object_hashtags_table();
	$objects = axismundi_op_remote_objects_table();
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- bounded orphan index cleanup.
	$wpdb->query( "DELETE h FROM {$table} h LEFT JOIN {$objects} o ON o.id = h.remote_object_id WHERE o.id IS NULL" );
}
