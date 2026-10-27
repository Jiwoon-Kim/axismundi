<?php
/**
 * The generic identity registry API — `wp_ax_identities` for NON-actor kinds.
 *
 * Actors owns and creates the registry table, but it does not own the objects that
 * reuse it. A shared folder, a collection, or a place is created by its owning plugin
 * (Media Library, geodata), which must reach the registry **only through this API and
 * never with direct SQL** (docs/DATA-MODEL.md §1). Keeping the generic layer in its own
 * file is what makes the documented future extraction into a shared `axismundi-core`
 * a file move rather than an untangling of actor-profile concerns.
 *
 * Actor identities are deliberately NOT creatable here: an identity row with
 * `object_kind = 'actor'` and no `wp_ax_actors` row is an orphan that no actor lookup
 * can hydrate. Actors are created only by axismundi_actors_create_local(), which writes
 * both rows in one transaction.
 *
 * @package AxismundiActors
 */

defined( 'ABSPATH' ) || exit;

/**
 * Non-actor identity kinds this API may create.
 *
 * Mirrors the kinds reserved in DATA-MODEL.md §2. `actor` is absent by design (see the
 * file docblock), so the allowlist doubles as the orphan guard.
 *
 * @return array<int,string>
 */
function axismundi_actors_identity_kinds() : array {
	return array( 'object', 'collection', 'folder', 'media', 'activity', 'place' );
}

/**
 * Hydrate one identity row into a plain array.
 *
 * Returns an array rather than an object: the registry answers "what UUID and canonical
 * URI is this, and is it local, public, alive?" and nothing domain-specific, so there is
 * no behaviour for a class to carry (DATA-MODEL.md §2).
 *
 * @param string $where Prepared WHERE clause (without the keyword).
 * @param mixed  ...$args Prepare arguments.
 * @return array<string,mixed>|null
 */
function axismundi_actors_query_identity( string $where, ...$args ) : ?array {
	global $wpdb;
	$table = axismundi_actors_identities_table();
	/*
	 * `$where` stays interpolated: it is a clause fixed by the caller, carrying
	 * its own %s/%d placeholders which $args fills. The table is %i, and it
	 * comes first in the statement, so binding it first keeps every later
	 * placeholder in the position its argument expects.
	 *
	 * prepare() now runs unconditionally. It used to be skipped when there were
	 * no arguments, which was fine while the table was interpolated and is not
	 * fine now that it has to be bound.
	 */
	$sql = "SELECT * FROM %i WHERE {$where} LIMIT 1";
	// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- caller-fixed clause; the table and every argument are prepared.
	$row = $wpdb->get_row( $wpdb->prepare( $sql, $table, ...$args ), ARRAY_A );
	if ( ! is_array( $row ) ) {
		return null;
	}
	return array(
		'identity_id'   => (int) $row['id'],
		'uuid'          => (string) $row['uuid'],
		'canonical_uri' => (string) $row['canonical_uri'],
		'object_kind'   => (string) $row['object_kind'],
		'origin'        => (string) $row['origin'],
		'status'        => (string) $row['status'],
		'created_at'    => (string) $row['created_at'],
		'updated_at'    => (string) $row['updated_at'],
	);
}

/**
 * One identity by its local row key.
 *
 * @param int $identity_id Identity key.
 * @return array<string,mixed>|null
 */
function axismundi_actors_get_identity( int $identity_id ) : ?array {
	return axismundi_actors_query_identity( 'id = %d', $identity_id );
}

/**
 * One identity by its immutable UUID anchor.
 *
 * @param string $uuid Canonical hyphenated UUIDv4.
 * @return array<string,mixed>|null
 */
function axismundi_actors_get_identity_by_uuid( string $uuid ) : ?array {
	return axismundi_actors_query_identity( 'uuid = %s', strtolower( trim( $uuid ) ) );
}

/**
 * One identity by canonical URI.
 *
 * The hash narrows the unindexable TEXT lookup, then the stored URI confirms the exact
 * identity. Keeping both terms makes a bad historical hash observable instead of silently
 * turning a changed URI-normalization rule into a miss.
 *
 * @param string $uri Canonical URI.
 * @return array<string,mixed>|null
 */
function axismundi_actors_get_identity_by_uri( string $uri ) : ?array {
	$uri = trim( $uri );
	return '' !== $uri
		? axismundi_actors_query_identity( 'canonical_uri_hash = %s AND canonical_uri = %s', hash( 'sha256', $uri ), $uri )
		: null;
}

/**
 * Audit stored identity hashes without rewriting historical identities.
 *
 * A mismatch is evidence of data drift. Repair is intentionally a separate, explicit
 * migration because changing the hash changes how every URI lookup addresses the row.
 *
 * @return array{checked:int,mismatched:int}
 */
function axismundi_actors_audit_identity_uri_hashes() : array {
	global $wpdb;
	$table      = axismundi_actors_identities_table();
	$rows       = (array) $wpdb->get_results( "SELECT canonical_uri, canonical_uri_hash FROM {$table}", ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fixed registry audit read.
	$mismatched = 0;
	foreach ( $rows as $row ) {
		if ( ! hash_equals( (string) $row['canonical_uri_hash'], hash( 'sha256', (string) $row['canonical_uri'] ) ) ) {
			++$mismatched;
		}
	}
	return array( 'checked' => count( $rows ), 'mismatched' => $mismatched );
}

/**
 * Register one non-actor identity.
 *
 * A local identity normally uses a `uri_template` with a `{uuid}` placeholder. Some
 * authoritative local sources, notably WordPress posts, already own their stable URI
 * space; they may supply `canonical_uri` directly and still receive a registry UUID.
 * A remote identity always supplies its source `canonical_uri` directly.
 *
 * @param array<string,mixed> $args {
 *     @type string $object_kind   Required; one of axismundi_actors_identity_kinds().
 *     @type string $origin        local|remote. Default local.
 *     @type string $status        public|disabled. Default public — record
 *                                 existence and public exposure are separate steps
 *                                 (SPEC.md §2.6).
 *     @type string $uri_template  Local only; must contain the literal `{uuid}` when
 *                                 canonical_uri is absent.
 *     @type string $canonical_uri Required for remote identities; optional stable URI
 *                                 for local identities whose source owns its URI.
 * }
 * @return array<string,mixed>|WP_Error The registered identity, or an error.
 */
function axismundi_actors_register_identity( array $args ) {
	global $wpdb;

	$kind = (string) ( $args['object_kind'] ?? '' );
	if ( ! in_array( $kind, axismundi_actors_identity_kinds(), true ) ) {
		return new WP_Error(
			'ax_actors_identity_kind',
			__( 'Unknown identity kind. Actor identities are created by the actor API, not the registry.', 'axismundi-actors' )
		);
	}

	$origin = (string) ( $args['origin'] ?? 'local' );
	if ( ! in_array( $origin, array( 'local', 'remote' ), true ) ) {
		return new WP_Error( 'ax_actors_identity_origin', __( 'Identity origin must be local or remote.', 'axismundi-actors' ) );
	}

	$status = (string) ( $args['status'] ?? 'public' );
	// A tombstone is an end state reached by axismundi_actors_set_status(), never a
	// birth state — a record cannot start out as the marker for its own deletion.
	if ( ! in_array( $status, array( 'public', 'disabled' ), true ) ) {
		return new WP_Error( 'ax_actors_identity_status', __( 'Identity status must be public or disabled.', 'axismundi-actors' ) );
	}

	$uuid = wp_generate_uuid4();

	if ( 'local' === $origin ) {
		$canonical_uri = trim( (string) ( $args['canonical_uri'] ?? '' ) );
		$template      = (string) ( $args['uri_template'] ?? '' );
		if ( '' !== $canonical_uri ) {
			$uri = $canonical_uri;
		} elseif ( ! str_contains( $template, '{uuid}' ) ) {
			return new WP_Error(
				'ax_actors_identity_template',
				__( 'A local identity needs a canonical_uri or a uri_template containing {uuid}.', 'axismundi-actors' )
			);
		} else {
			$uri = str_replace( '{uuid}', $uuid, $template );
		}
	} else {
		// Shape only — no DNS resolution. Object observations accept HTTP and HTTPS under
		// the shared AS2 URI contract, while endpoint identities retain their HTTPS-only
		// contract. The SSRF gate stays at the fetch site.
		$normalized = 'object' === $kind && function_exists( 'axismundi_as2_normalize_uri' )
			? axismundi_as2_normalize_uri( $args['canonical_uri'] ?? '' )
			: null;
		$uri = is_array( $normalized ) && ! empty( $normalized['resolvable'] )
			? (string) $normalized['uri']
			: axismundi_actors_normalize_endpoint_uri( $args['canonical_uri'] ?? '' );
		if ( '' === $uri ) {
			return new WP_Error( 'ax_actors_identity_uri', __( 'A remote identity needs a canonical HTTP(S) URI with a host.', 'axismundi-actors' ) );
		}
	}

	$existing = axismundi_actors_get_identity_by_uri( $uri );
	if ( null !== $existing ) {
		return new WP_Error(
			'ax_actors_identity_exists',
			__( 'That canonical URI is already registered.', 'axismundi-actors' ),
			array( 'identity' => $existing )
		);
	}

	$now = current_time( 'mysql', true );
	$ok  = $wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- custom identity table.
		axismundi_actors_identities_table(),
		array(
			'uuid'               => $uuid,
			'canonical_uri'      => $uri,
			'canonical_uri_hash' => hash( 'sha256', $uri ),
			'object_kind'        => $kind,
			'origin'             => $origin,
			'status'             => $status,
			'created_at'         => $now,
			'updated_at'         => $now,
		),
		array( '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s' )
	);
	if ( false === $ok ) {
		return new WP_Error( 'ax_actors_identity_insert', __( 'Could not register the identity.', 'axismundi-actors' ) );
	}

	$identity = axismundi_actors_get_identity( (int) $wpdb->insert_id );

	/**
	 * Fires after one non-actor identity is registered.
	 *
	 * @since 0.0.31
	 * @param array<string,mixed> $identity The registered identity.
	 */
	do_action( 'axismundi_actors_identity_registered', $identity );

	return $identity;
}

/**
 * Rewrite a local identity's canonical URI, preserving its UUID.
 *
 * The UUID is the only immutable anchor; the URI may move under an explicit migration
 * such as a domain change (DATA-MODEL.md §2). A remote identity's URI is its source of
 * truth and is never rewritten here.
 *
 * @param int    $identity_id Identity key.
 * @param string $uri         New canonical URI.
 * @return true|WP_Error
 */
function axismundi_actors_set_identity_uri( int $identity_id, string $uri ) {
	global $wpdb;
	$identity = axismundi_actors_get_identity( $identity_id );
	if ( null === $identity ) {
		return new WP_Error( 'ax_actors_identity_missing', __( 'Unknown identity.', 'axismundi-actors' ) );
	}
	if ( 'local' !== $identity['origin'] ) {
		return new WP_Error( 'ax_actors_identity_remote', __( 'A remote identity URI is its source of truth and cannot be rewritten.', 'axismundi-actors' ) );
	}
	$uri = trim( $uri );
	if ( '' === $uri ) {
		return new WP_Error( 'ax_actors_identity_uri', __( 'A canonical URI cannot be empty.', 'axismundi-actors' ) );
	}
	$collision = axismundi_actors_get_identity_by_uri( $uri );
	if ( null !== $collision && (int) $collision['identity_id'] !== $identity_id ) {
		return new WP_Error( 'ax_actors_identity_exists', __( 'That canonical URI is already registered.', 'axismundi-actors' ) );
	}
	$done = $wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- custom identity table.
		axismundi_actors_identities_table(),
		array(
			'canonical_uri'      => $uri,
			'canonical_uri_hash' => hash( 'sha256', $uri ),
			'updated_at'         => current_time( 'mysql', true ),
		),
		array( 'id' => $identity_id ),
		array( '%s', '%s', '%s' ),
		array( '%d' )
	);
	return false === $done
		? new WP_Error( 'ax_actors_identity_update', __( 'Could not rewrite the canonical URI.', 'axismundi-actors' ) )
		: true;
}
