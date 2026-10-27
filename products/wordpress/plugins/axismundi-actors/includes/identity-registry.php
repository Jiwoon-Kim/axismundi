<?php
/**
 * Historical identity lookup API, retained while the Actor body is consolidated.
 * Non-Actor allocation is retired: Objects belong to OP, and User folders are
 * taxonomy terms. Actor collections will have their own storage, not this parent.
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
 * No non-Actor kinds may be allocated in the historical Actor parent table.
 *
 * @return array<int,string>
 */
function axismundi_actors_identity_kinds() : array {
	return array();
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
 * Compatibility rejection for the retired shared allocator.
 *
 * @param array<string,mixed> $args Historical registration arguments.
 * @return WP_Error Always: registration belongs to the owning domain.
 */
function axismundi_actors_register_identity( array $args ) {
	return new WP_Error( 'ax_actors_identity_kind', __( 'The shared non-Actor registry is retired. Use the owning domain repository.', 'axismundi-actors' ) );
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
