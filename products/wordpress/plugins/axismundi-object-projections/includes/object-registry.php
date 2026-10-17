<?php
/**
 * Object vertical slice over the shared identity registry.
 *
 * `wp_ax_objects` has no URI of its own. The identity registry remains the one
 * answer to "which IRI is this?" while this slice records which source currently
 * projects that IRI as an AS2 Object.
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
	dbDelta(
		"CREATE TABLE {$table} (
			identity_id bigint(20) unsigned NOT NULL,
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
			PRIMARY KEY  (identity_id),
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
	return in_array( 'source_owner', $columns, true )
		&& in_array( 'as2_type_observed_at', $columns, true )
		&& in_array( 'attributed_identity_id', $columns, true )
		&& ! empty( $primary )
		&& 0 === (int) $primary[0]['Non_unique'];
}

/** @return array<string,mixed>|null One Object slice by identity id. */
function axismundi_op_get_object_by_identity( int $identity_id ) : ?array {
	if ( $identity_id <= 0 ) {
		return null;
	}
	global $wpdb;
	$table = axismundi_op_objects_table();
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- indexed custom slice lookup.
	$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE identity_id = %d", $identity_id ), ARRAY_A );
	return is_array( $row ) ? $row : null;
}

/** @return array<string,mixed>|null One Object slice by its canonical Object URI. */
function axismundi_op_get_object_by_uri( string $uri ) : ?array {
	$identity = function_exists( 'axismundi_actors_get_identity_by_uri' ) ? axismundi_actors_get_identity_by_uri( $uri ) : null;
	return is_array( $identity ) ? axismundi_op_get_object_by_identity( (int) $identity['identity_id'] ) : null;
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
	$attributed_id = (int) ( $object['attributed_identity_id'] ?? 0 );
	if ( $attributed_id > 0 ) {
		return $attributed_id;
	}
	$resolved = axismundi_op_attributed_identity_id( (string) ( $object['attributed_to_uri'] ?? '' ) );
	if ( null === $resolved || (int) ( $object['identity_id'] ?? 0 ) <= 0 ) {
		return null;
	}
	global $wpdb;
	$updated = $wpdb->update(
		axismundi_op_objects_table(),
		array( 'attributed_identity_id' => $resolved, 'updated_at' => current_time( 'mysql', true ) ),
		array( 'identity_id' => (int) $object['identity_id'] ),
		array( '%d', '%s' ),
		array( '%d' )
	);
	return false === $updated ? null : $resolved;
}

/**
 * Register or refresh an Object slice. The URI is owned by the identity registry;
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
	$as2_type     = substr( sanitize_text_field( (string) ( $args['as2_type'] ?? '' ) ), 0, 64 );
	if ( '' === $uri || ! in_array( $origin, array( 'local', 'remote' ), true ) || '' === $source_owner || '' === $source_kind || '' === $as2_type ) {
		return new WP_Error( 'ax_op_object_args', __( 'An Object needs a canonical URI, origin, source provenance, and AS2 type.', 'axismundi-object-projections' ) );
	}
	if ( ! function_exists( 'axismundi_actors_get_identity_by_uri' ) || ! function_exists( 'axismundi_actors_register_identity' ) ) {
		return new WP_Error( 'ax_op_object_identity_registry', __( 'The shared identity registry is unavailable.', 'axismundi-object-projections' ) );
	}

	$identity = axismundi_actors_get_identity_by_uri( $uri );
	if ( null === $identity ) {
		$identity = axismundi_actors_register_identity(
			array(
				'object_kind'   => 'object',
				'origin'        => $origin,
				'status'        => 'local' === $origin ? 'internal' : 'public',
				'canonical_uri' => $uri,
			)
		);
	}
	if ( is_wp_error( $identity ) ) {
		return $identity;
	}
	if ( ! is_array( $identity ) || 'object' !== (string) $identity['object_kind'] ) {
		return new WP_Error( 'ax_op_object_identity_kind', __( 'The canonical URI is already owned by a non-Object identity.', 'axismundi-object-projections' ) );
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
	global $wpdb;
	$table    = axismundi_op_objects_table();
	$existing = axismundi_op_get_object_by_identity( (int) $identity['identity_id'] );
	$ok       = is_array( $existing )
		? false !== $wpdb->update( $table, $data, array( 'identity_id' => (int) $identity['identity_id'] ) )
		: false !== $wpdb->insert( $table, array_merge( $data, array( 'identity_id' => (int) $identity['identity_id'], 'created_at' => $now ) ) );
	if ( ! $ok ) {
		return new WP_Error( 'ax_op_object_write', __( 'The Object projection could not be stored.', 'axismundi-object-projections' ) );
	}
	return axismundi_op_get_object_by_identity( (int) $identity['identity_id'] ) ?? new WP_Error( 'ax_op_object_read', __( 'The Object projection could not be read.', 'axismundi-object-projections' ) );
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

/**
 * Add identity shadows to existing remote cache rows without creating any Activity.
 *
 * @return bool False only when a source row could not be represented in the shared
 *              registry; callers keep the schema version pending in that case.
 */
function axismundi_op_backfill_remote_object_identities() : bool {
	if ( ! function_exists( 'axismundi_actors_get_identity_by_uri' ) || ! function_exists( 'axismundi_actors_register_identity' ) ) {
		return false;
	}
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
