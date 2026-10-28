<?php
/**
 * OP-owned Object identifiers and explicit source provenance.
 * Actor identifiers and collection membership are separate domains.
 *
 * @package AxismundiObjectProjections
 */

defined( 'ABSPATH' ) || exit;

require_once __DIR__ . '/vocabulary-registry.php';

/** @var array<string,array<string,mixed>> Explicit local source profiles, in registration order. */
$GLOBALS['axismundi_op_local_source_profiles'] = array();
$GLOBALS['axismundi_op_local_source_profiles_loaded'] = false;
$GLOBALS['axismundi_op_local_source_profile_sequence'] = 0;

/** Register a local source profile without changing the legacy transformer contract. */
function axismundi_op_register_local_source_profile( string $id, array $profile ) {
	if ( '' === sanitize_key( $id ) || ! is_callable( $profile['supports'] ?? null ) || ! is_callable( $profile['resolve'] ?? null ) ) {
		return new WP_Error( 'ax_op_source_profile', __( 'A local source profile needs a lowercase id plus supports and resolve callbacks.', 'axismundi-object-projections' ) );
	}
	$profile['priority'] = isset( $profile['priority'] ) ? (int) $profile['priority'] : 10;
	$profile['order']    = ++$GLOBALS['axismundi_op_local_source_profile_sequence'];
	$GLOBALS['axismundi_op_local_source_profiles'][ $id ] = $profile;
	return true;
}

/** @return void */
function axismundi_op_load_local_source_profiles() : void {
	if ( $GLOBALS['axismundi_op_local_source_profiles_loaded'] ) {
		return;
	}
	$GLOBALS['axismundi_op_local_source_profiles_loaded'] = true;
	do_action( 'axismundi_op_register_local_source_profiles' );
}

/** @return array<int,array<string,mixed>> */
function axismundi_op_local_source_profiles() : array {
	axismundi_op_load_local_source_profiles();
	$profiles = array_values( $GLOBALS['axismundi_op_local_source_profiles'] );
	usort( $profiles, static fn( array $a, array $b ) : int => array( $a['priority'], $a['order'] ) <=> array( $b['priority'], $b['order'] ) );
	return $profiles;
}

/** @return array<string,mixed>|WP_Error|null */
function axismundi_op_resolve_local_source_profile( WP_Post $post ) {
	foreach ( axismundi_op_local_source_profiles() as $profile ) {
		if ( true !== call_user_func( $profile['supports'], $post ) ) {
			continue;
		}
		$result = call_user_func( $profile['resolve'], $post );
		if ( ! is_array( $result ) || null === axismundi_op_local_object_type( (string) ( $result['type'] ?? '' ) ) ) {
			return new WP_Error( 'ax_op_unknown_profile_type', __( 'A local source profile claimed a source with an unknown vocabulary type.', 'axismundi-object-projections' ) );
		}
		return $result;
	}
	return null;
}

/** Register WordPress core's opt-in profiles; page deliberately has none. */
function axismundi_op_register_core_local_source_profiles() : void {
	axismundi_op_register_local_source_profile(
		'core-post-article',
		array(
			'supports' => static fn( WP_Post $post ) : bool => 'post' === $post->post_type && function_exists( 'axismundi_op_post_object_uri' ),
			'resolve' => static fn( WP_Post $post ) : array => array( 'uri' => axismundi_op_post_object_uri( $post ), 'type' => 'Article', 'owner' => 'wordpress-core', 'kind' => 'post', 'attributed_to_uri' => function_exists( 'axismundi_op_post_actor_uri' ) ? axismundi_op_post_actor_uri( $post ) : '' ),
		)
	);
	axismundi_op_register_local_source_profile(
		'core-attachment',
		array(
			'supports' => static fn( WP_Post $post ) : bool => 'attachment' === $post->post_type,
			'resolve' => static fn( WP_Post $post ) : array => array( 'uri' => function_exists( 'axismundi_op_core_attachment_uri' ) ? axismundi_op_core_attachment_uri( $post ) : add_query_arg( 'attachment_id', $post->ID, home_url( '/' ) ), 'type' => axismundi_op_core_attachment_type( $post ), 'owner' => 'wordpress-core', 'kind' => 'attachment', 'attributed_to_uri' => function_exists( 'axismundi_op_attachment_actor_uri' ) ? axismundi_op_attachment_actor_uri( $post ) : '' ),
		)
	);
}
add_action( 'axismundi_op_register_local_source_profiles', 'axismundi_op_register_core_local_source_profiles' );

/** @return string Object vertical-slice table. */
function axismundi_op_objects_table() : string {
	global $wpdb;
	return $wpdb->prefix . 'ax_objects';
}

/** Install and verify the Object vertical slice. */
function axismundi_op_install_objects_schema() : bool {
	global $wpdb;
	require_once ABSPATH . 'wp-admin/includes/upgrade.php';
	$table   = axismundi_op_objects_table();
	$charset = $wpdb->get_charset_collate();
	$exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $table ) ) ) === $table;
	$columns = $exists ? (array) $wpdb->get_col( "SHOW COLUMNS FROM {$table}" ) : array();
	if ( in_array( 'identity_id', $columns, true ) && ! in_array( 'id', $columns, true ) ) {
		if ( false === $wpdb->query( "ALTER TABLE {$table} CHANGE identity_id id bigint(20) unsigned NOT NULL AUTO_INCREMENT" ) ) { return false; }
	}
	dbDelta(
		"CREATE TABLE {$table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			uuid char(36) DEFAULT NULL,
			canonical_uri text DEFAULT NULL,
			canonical_uri_hash char(64) DEFAULT NULL,
			origin varchar(8) DEFAULT NULL,
			status varchar(12) NOT NULL DEFAULT 'public',
			source_owner varchar(64) NOT NULL,
			source_kind varchar(64) NOT NULL,
			source_ref bigint(20) unsigned DEFAULT NULL,
			as2_type varchar(64) DEFAULT NULL,
			as2_type_observed_at datetime DEFAULT NULL,
			attributed_to_uri text DEFAULT NULL,
			attributed_to_uri_hash char(64) DEFAULT NULL,
			attributed_identity_id bigint(20) unsigned DEFAULT NULL,
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY uuid (uuid),
			UNIQUE KEY canonical_uri_hash (canonical_uri_hash),
			KEY source (source_owner, source_kind, source_ref),
			KEY attributed_identity_id (attributed_identity_id),
			KEY attributed_to_uri_hash (attributed_to_uri_hash),
			KEY type_observed (as2_type, as2_type_observed_at)
		) ENGINE=InnoDB {$charset};"
	);
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fixed custom schema verification.
	$columns = (array) $wpdb->get_col( "SHOW COLUMNS FROM {$table}" );
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fixed custom schema verification.
	$primary = (array) $wpdb->get_results( "SHOW INDEX FROM {$table} WHERE Key_name = 'PRIMARY'", ARRAY_A );
	return in_array( 'canonical_uri_hash', $columns, true )
		&& in_array( 'source_owner', $columns, true )
		&& in_array( 'as2_type_observed_at', $columns, true )
		&& in_array( 'attributed_identity_id', $columns, true )
		&& ! empty( $primary )
		&& 0 === (int) $primary[0]['Non_unique']
		&& axismundi_op_migrate_object_identifiers();
}

/** Return historical field aliases only at the PHP boundary, not a shared PK. */
function axismundi_op_hydrate_object( $row ) : ?array {
	if ( ! is_array( $row ) ) { return null; }
	$row['id'] = (int) $row['id'];
	$row['identity_id'] = $row['id'];
	$row['object_kind'] = 'object';
	return $row;
}

/** Copy and verify every old anchor before removing any shared Object row. */
function axismundi_op_migrate_object_identifiers() : bool {
	if ( '1' === get_option( 'ax_op_object_identifiers_migrated' ) ) { return true; }
	global $wpdb;
	$table = axismundi_op_objects_table();
	$legacy = $wpdb->prefix . 'ax_identities';
	$questions = $wpdb->prefix . 'ax_activity_questions';
	if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $questions ) ) ) === $questions ) {
		if ( ! function_exists( 'axismundi_act_install_questions_schema' ) || ! axismundi_act_install_questions_schema() ) { return false; }
	}
	if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $legacy ) ) ) !== $legacy ) {
		if ( 0 !== (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE canonical_uri IS NULL" ) || $wpdb->last_error ) { return false; }
		update_option( 'ax_op_object_identifiers_migrated', '1', false );
		return true;
	}
	$wpdb->query( 'START TRANSACTION' );
	try {
		// Lock old anchors until their verified copies have replaced them.
		$old = $wpdb->get_results( "SELECT * FROM {$legacy} WHERE object_kind='object' ORDER BY id FOR UPDATE", ARRAY_A );
		if ( $wpdb->last_error ) { throw new RuntimeException( $wpdb->last_error ); }
		foreach ( $old as $row ) {
			$existing = axismundi_op_get_object_by_identity( (int) $row['id'] );
			if ( ! hash_equals( $row['canonical_uri_hash'], hash( 'sha256', $row['canonical_uri'] ) )
				|| ( $existing && null !== $existing['canonical_uri'] && ( $existing['canonical_uri'] !== $row['canonical_uri'] || $existing['uuid'] !== $row['uuid'] || $existing['origin'] !== $row['origin'] ) ) ) {
				throw new RuntimeException( 'Object anchor conflict.' );
			}
			$data = array_intersect_key( $row, array_flip( array( 'uuid', 'canonical_uri', 'canonical_uri_hash', 'origin', 'status' ) ) );
			if ( $existing ) {
				$ok = $wpdb->update( $table, $data, array( 'id' => $row['id'] ) );
			} else {
				// No source was recorded: preserve existence, never invent a readable source.
				$ok = $wpdb->insert( $table, array_merge( $data, array( 'id' => $row['id'], 'source_owner' => 'legacy-registry', 'source_kind' => 'unbound', 'created_at' => $row['created_at'], 'updated_at' => $row['updated_at'] ) ) );
			}
			if ( false === $ok ) { throw new RuntimeException( $wpdb->last_error ); }
			$copy = axismundi_op_get_object_by_identity( (int) $row['id'] );
			foreach ( $data as $key => $value ) {
				if ( ! $copy || $copy[ $key ] !== $value ) { throw new RuntimeException( 'Object anchor copy mismatch.' ); }
			}
		}
		// Pre-existing orphan metadata has no anchor to copy. Retain it unreadable;
		// allocating a replacement UUID would fabricate an identity that never existed.
		if ( 0 !== (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE canonical_uri IS NOT NULL AND (uuid IS NULL OR canonical_uri_hash<>SHA2(canonical_uri,256))" ) || $wpdb->last_error ) { throw new RuntimeException( 'Incomplete Object index.' ); }
		// Actor-owned relationship rows must never be orphaned by this migration.
		foreach ( array( 'ax_actors', 'ax_actor_managers', 'ax_identity_relations' ) as $suffix ) {
			$dependent = $wpdb->prefix . $suffix;
			if ( 0 !== (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$dependent} d INNER JOIN {$legacy} i ON i.id=d.identity_id WHERE i.object_kind='object'" ) || $wpdb->last_error ) { throw new RuntimeException( 'Object anchor has Actor-owned dependents.' ); }
		}
		if ( false === $wpdb->query( "DELETE FROM {$legacy} WHERE object_kind='object'" ) ) { throw new RuntimeException( $wpdb->last_error ); }
		if ( false === $wpdb->query( 'COMMIT' ) ) { throw new RuntimeException( $wpdb->last_error ); }
	} catch ( Throwable $error ) {
		$wpdb->query( 'ROLLBACK' );
		return false;
	}
	update_option( 'ax_op_object_identifiers_migrated', '1', false );
	return true;
}

/** @return array<string,mixed>|null One Object slice by identity id. */
function axismundi_op_get_object_by_identity( int $identity_id ) : ?array {
	if ( $identity_id <= 0 ) {
		return null;
	}
	global $wpdb;
	$table = axismundi_op_objects_table();
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- indexed custom slice lookup.
	$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $identity_id ), ARRAY_A );
	return axismundi_op_hydrate_object( $row );
}

/** @return array<string,mixed>|null One Object slice by its canonical Object URI. */
function axismundi_op_get_object_by_uri( string $uri ) : ?array {
	global $wpdb;
	$uri = trim( $uri );
	return '' !== $uri ? axismundi_op_hydrate_object( $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM %i WHERE canonical_uri_hash=%s AND canonical_uri=%s', axismundi_op_objects_table(), hash( 'sha256', $uri ), $uri ), ARRAY_A ) ) : null;
}

/** Resolve a known Actor without making an object cache depend on actor discovery. */
function axismundi_op_attributed_identity_id( string $uri ) : ?int {
	if ( '' === $uri || ! function_exists( 'axismundi_actors_get_by_uri' ) ) {
		return null;
	}
	$actor = axismundi_actors_get_by_uri( $uri );
	return $actor instanceof Axismundi_Actor ? $actor->get_identity_id() : null;
}

/**
 * Resolve a deferred attributedTo join without changing the canonical URI.
 *
 * Objects can arrive before their Actor. The URI remains authoritative; this
 * fills its nullable accelerator only once the Actor ledger knows that URI.
 *
 * @param array<string,mixed> $object Object vertical-slice row.
 * @return int|null Known Actor identity id, if any.
 */
function axismundi_op_resolve_object_attributed_identity( array $object ) : ?int {
	$object_id = (int) ( $object['id'] ?? $object['identity_id'] ?? 0 );
	$attributed_id = (int) ( $object['attributed_identity_id'] ?? 0 );
	if ( $attributed_id > 0 ) {
		return $attributed_id;
	}
	$resolved = axismundi_op_attributed_identity_id( (string) ( $object['attributed_to_uri'] ?? '' ) );
	if ( null === $resolved || $object_id <= 0 ) {
		return null;
	}
	global $wpdb;
	$updated = $wpdb->update(
		axismundi_op_objects_table(),
		array( 'attributed_identity_id' => $resolved, 'updated_at' => current_time( 'mysql', true ) ),
		array( 'id' => $object_id ),
		array( '%d', '%s' ),
		array( '%d' )
	);
	return false === $updated ? null : $resolved;
}

/**
 * Register or refresh an OP Object. The URI is owned by this Object index;
 * source_ref is intentionally provenance rather than a database foreign key.
 *
 * @param array<string,mixed> $args Object identity, source and projection fields.
 * @return array<string,mixed>|WP_Error
 */
function axismundi_op_register_object( array $args ) {
	$uri          = trim( (string) ( $args['canonical_uri'] ?? '' ) );
	$origin       = (string) ( $args['origin'] ?? 'local' );
	$source_owner = sanitize_key( (string) ( $args['source_owner'] ?? '' ) );
	$source_kind  = sanitize_key( (string) ( $args['source_kind'] ?? '' ) );
	if ( 'wordpress-core' === $source_owner ) {
		$recorded = function_exists( 'axismundi_act_get_object_attribution' ) ? axismundi_act_get_object_attribution( $uri ) : '';
		if ( '' === $recorded || $recorded !== (string) ( $args['attributed_to_uri'] ?? '' ) ) {
			return new WP_Error( 'ax_op_unpublished_wp', 'WordPress registry admission requires a recorded Create and matching attribution.' );
		}
	}
	$as2_type     = substr( sanitize_text_field( (string) ( $args['as2_type'] ?? '' ) ), 0, 64 );
	if ( isset( $args['status'] ) && ! in_array( $args['status'], array( 'public', 'disabled' ), true ) ) {
		return new WP_Error( 'ax_op_object_status', 'Object status must be public or disabled; disclosure belongs to its source.' );
	}
	if ( '' === $uri || ! in_array( $origin, array( 'local', 'remote' ), true ) || '' === $source_owner || '' === $source_kind || '' === $as2_type ) {
		return new WP_Error( 'ax_op_object_args', __( 'An Object needs a canonical URI, origin, source provenance, and AS2 type.', 'axismundi-object-projections' ) );
	}
	$normalized = function_exists( 'axismundi_as2_normalize_uri' ) ? axismundi_as2_normalize_uri( $uri ) : null;
	if ( ! is_array( $normalized ) || empty( $normalized['resolvable'] ) || $normalized['uri'] !== $uri ) {
		return new WP_Error( 'ax_op_object_uri', 'An Object needs a canonical HTTP(S) URI.' );
	}
	if ( '1' !== get_option( 'ax_op_object_identifiers_migrated' ) ) {
		return new WP_Error( 'ax_op_object_migration_pending', 'The Object identifier migration must finish first.' );
	}
	$identity = axismundi_op_get_object_by_uri( $uri );
	if ( is_array( $identity ) && $identity['origin'] !== $origin ) {
		return new WP_Error( 'ax_op_object_origin', 'An Object origin cannot be changed.' );
	}

	$attributed_to_uri = trim( (string) ( $args['attributed_to_uri'] ?? '' ) );
	$attributed_id     = '' !== $attributed_to_uri ? axismundi_op_attributed_identity_id( $attributed_to_uri ) : null;
	$now               = current_time( 'mysql', true );
	$data              = array(
		'source_owner'             => $source_owner,
		'source_kind'              => $source_kind,
		'source_ref'               => isset( $args['source_ref'] ) && (int) $args['source_ref'] > 0 ? (int) $args['source_ref'] : null,
		'as2_type'                 => $as2_type,
		'as2_type_observed_at'     => $now,
		'attributed_to_uri'         => '' !== $attributed_to_uri ? $attributed_to_uri : null,
		'attributed_to_uri_hash'    => '' !== $attributed_to_uri ? hash( 'sha256', $attributed_to_uri ) : null,
		'attributed_identity_id'    => $attributed_id,
		'updated_at'                => $now,
	);
	if ( isset( $args['status'] ) ) { $data['status'] = $args['status']; }
	global $wpdb;
	$table    = axismundi_op_objects_table();
	$existing = $identity;
	$ok       = is_array( $existing )
		? false !== $wpdb->update( $table, $data, array( 'id' => (int) $existing['id'] ) )
		: false !== $wpdb->insert( $table, array_merge( $data, array( 'uuid' => wp_generate_uuid4(), 'canonical_uri' => $uri, 'canonical_uri_hash' => hash( 'sha256', $uri ), 'origin' => $origin, 'created_at' => $now ) ) );
	if ( ! $ok ) {
		return new WP_Error( 'ax_op_object_write', __( 'The Object projection could not be stored.', 'axismundi-object-projections' ) );
	}
	return axismundi_op_get_object_by_uri( $uri ) ?? new WP_Error( 'ax_op_object_read', __( 'The Object projection could not be read.', 'axismundi-object-projections' ) );
}

/** Core attachment type without requiring the optional Media Library plugin. */
function axismundi_op_core_attachment_type( WP_Post $post ) : string {
	$mime = (string) get_post_mime_type( $post );
	if ( str_starts_with( $mime, 'image/' ) ) {
		return 'Image';
	}
	if ( str_starts_with( $mime, 'audio/' ) ) {
		return 'Audio';
	}
	if ( str_starts_with( $mime, 'video/' ) ) {
		return 'Video';
	}
	return 'Document';
}

/** A local source can only claim a type the vocabulary resolver knows. */
function axismundi_op_local_object_type( string $type ) : ?string {
	return function_exists( 'axismundi_op_vocabulary_type' ) && null !== axismundi_op_vocabulary_type( $type ) ? $type : null;
}

/** Return the explicit local post types this slice may register. */
function axismundi_op_local_object_profile( WP_Post $post ) : ?array {
	if ( wp_is_post_revision( $post ) || wp_is_post_autosave( $post ) ) {
		return null;
	}
	$core_profile = axismundi_op_resolve_local_source_profile( $post );
	if ( is_wp_error( $core_profile ) ) {
		/**
		 * A claiming profile must not silently yield to a later profile when its type is invalid.
		 * Consumers that retain the historical nullable profile contract can observe the failure here.
		 */
		do_action( 'axismundi_op_local_source_profile_error', $core_profile, $post );
		return null;
	}
	if ( is_array( $core_profile ) ) {
		return $core_profile;
	}
	/*
	 * `page` is deliberately absent. A published page is site furniture -- a contact page, a
	 * policy, a landing page -- and publishing it as a federated Object says it was authored
	 * for an audience, which `post_status = publish` never meant. The architecture registry
	 * records this as "no default, explicit opt-in only"; nothing registers that opt-in yet,
	 * so there is no page profile rather than an unused one.
	 */
	if ( defined( 'AXISMUNDI_NOTE_POST_TYPE' ) && AXISMUNDI_NOTE_POST_TYPE === $post->post_type && function_exists( 'axismundi_note_get' ) && function_exists( 'axismundi_note_object_uri' ) ) {
		$envelope = axismundi_note_get( $post->ID );
		if ( ! is_array( $envelope ) || '' === (string) ( $envelope['local_uuid'] ?? '' ) ) {
			return null;
		}
		return array( 'uri' => axismundi_note_object_uri( (string) $envelope['local_uuid'] ), 'type' => function_exists( 'axismundi_note_question_row' ) && is_array( axismundi_note_question_row( $post->ID ) ) ? 'Question' : 'Note', 'owner' => 'axismundi-note', 'kind' => 'note-post', 'attributed_to_uri' => (string) ( $envelope['actor_uri'] ?? '' ) );
	}
	return null;
}

/** Register the Object slice for one explicit local post profile. */
function axismundi_op_register_local_post_object( WP_Post $post ) {
	$profile = axismundi_op_local_object_profile( $post );
	if ( ! is_array( $profile ) || empty( $profile['attributed_to_uri'] ) || ! function_exists( 'axismundi_act_get_object_attribution' )
		|| $profile['attributed_to_uri'] !== axismundi_act_get_object_attribution( $profile['uri'] ) ) { return null; }
	return is_array( $profile )
		? axismundi_op_register_object(
			array(
				'canonical_uri'      => $profile['uri'],
				'origin'             => 'local',
				'source_owner'       => $profile['owner'],
				'source_kind'        => $profile['kind'],
				'source_ref'         => $post->ID,
				'as2_type'           => $profile['type'],
				'attributed_to_uri'  => $profile['attributed_to_uri'],
			)
		)
		: null;
}

/** Keep the post-backed shadow reference fresh after every source-specific writer. */
function axismundi_op_register_saved_local_post_object( int $post_id, WP_Post $post, bool $update ) : void {
	axismundi_op_register_local_post_object( $post );
}
add_action( 'save_post', 'axismundi_op_register_saved_local_post_object', 100, 3 );

/** A published WP source enters the common index only after its Create commits. */
function axismundi_op_register_recorded_wp_source( Axismundi_Activity $activity ) : void {
	if ( ! in_array( $activity->get_type(), array( 'Create', 'Update', 'Delete' ), true ) || 'inbound' === $activity->get_direction() ) { return; }
	$uri = (string) $activity->get_object_uri();
	$parts = wp_parse_url( $uri );
	$query = array();
	parse_str( $parts['query'] ?? '', $query );
	$id = absint( $query['p'] ?? $query['attachment_id'] ?? 0 );
	$post = $id ? get_post( $id ) : null;
	$profile = $post instanceof WP_Post ? axismundi_op_local_object_profile( $post ) : null;
	if ( is_array( $profile ) && $profile['uri'] === $uri ) { axismundi_op_register_local_post_object( $post ); }
}
add_action( 'axismundi_act_activity_recorded', 'axismundi_op_register_recorded_wp_source', 30 );

/**
 * Link existing remote cache rows to OP Objects without creating any Activity.
 *
 * @return bool False when a source row could not be represented in the OP index;
 *              callers keep the schema version pending in that case.
 */
function axismundi_op_backfill_remote_object_identities() : bool {
	global $wpdb;
	$table = axismundi_op_remote_objects_table();
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- bounded schema-upgrade scan of this repository's rows.
	$rows = (array) $wpdb->get_results( "SELECT id, object_uri, object_type, attributed_to_uri FROM {$table} WHERE identity_id IS NULL OR attributed_identity_id IS NULL", ARRAY_A );
	foreach ( $rows as $row ) {
		$object = axismundi_op_register_object(
			array(
				'canonical_uri'     => (string) $row['object_uri'],
				'origin'            => 'remote',
				'source_owner'      => 'axismundi-object-projections',
				'source_kind'       => 'remote-object-cache',
				'source_ref'        => (int) $row['id'],
				'as2_type'          => (string) $row['object_type'],
				'attributed_to_uri' => (string) ( $row['attributed_to_uri'] ?? '' ),
			)
		);
		if ( is_wp_error( $object ) || ! is_array( $object ) ) {
			return false;
		}
		$ok = false !== $wpdb->update(
			$table,
			array(
				'identity_id'            => (int) $object['identity_id'],
				'attributed_identity_id' => $object['attributed_identity_id'],
			),
			array( 'id' => (int) $row['id'] ),
			array( '%d', '%d' ),
			array( '%d' )
		);
		if ( ! $ok ) {
			return false;
		}
	}
	return true;
}
