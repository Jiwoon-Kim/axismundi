<?php
/**
 * FEP-044f QuoteAuthorization state.
 *
 * Consent state belongs to Activities and is separate from the observed fact that one
 * Object quotes another (SPEC §19). This store answers "did the quoted author authorize
 * this quote, and is that authorization still standing" — nothing about whether the quote
 * exists, which Object Projections indexes and counts independently.
 *
 * Activities mints the identity; Object Projections owns its dereferenceable JSON-LD.
 *
 * @package AxismundiActivities
 */

defined( 'ABSPATH' ) || exit;

/** QuoteAuthorization table. */
function axismundi_act_quote_authorizations_table() : string {
	global $wpdb;
	return $wpdb->prefix . 'ax_quote_authorizations';
}

/**
 * Install and verify the authorization schema.
 *
 * Called from axismundi_act_install(), which records the shared schema version only when
 * every table verifies, so a partial migration is retried instead of being recorded.
 *
 * @return bool
 */
function axismundi_act_install_quote_authorizations() : bool {
	global $wpdb;
	require_once ABSPATH . 'wp-admin/includes/upgrade.php';
	$table   = axismundi_act_quote_authorizations_table();
	$charset = $wpdb->get_charset_collate();
	dbDelta(
		"CREATE TABLE {$table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			local_uuid char(36) NOT NULL,
			authorization_uri text NOT NULL,
			authorization_uri_hash char(64) NOT NULL,
			request_activity_uri text NOT NULL,
			request_activity_uri_hash char(64) NOT NULL,
			quoted_object_uri text NOT NULL,
			quoted_object_uri_hash char(64) NOT NULL,
			quoting_object_uri text NOT NULL,
			quoting_object_uri_hash char(64) NOT NULL,
			requester_actor_uri text NOT NULL,
			requester_actor_uri_hash char(64) NOT NULL,
			author_actor_uri text NOT NULL,
			author_actor_uri_hash char(64) NOT NULL,
			status varchar(8) NOT NULL DEFAULT 'active',
			standing_key char(64) DEFAULT NULL,
			last_error text DEFAULT NULL,
			created_at datetime NOT NULL,
			revoked_at datetime DEFAULT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY local_uuid (local_uuid),
			UNIQUE KEY authorization_uri_hash (authorization_uri_hash),
			UNIQUE KEY request_activity_uri_hash (request_activity_uri_hash),
			UNIQUE KEY standing_key (standing_key),
			KEY quoted_object_uri_hash (quoted_object_uri_hash),
			KEY pair (quoting_object_uri_hash, quoted_object_uri_hash, author_actor_uri_hash, status)
		) ENGINE=InnoDB {$charset};"
	);

	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fixed custom schema verification.
	$columns = (array) $wpdb->get_col( "SHOW COLUMNS FROM {$table}" );
	$unique  = array();
	foreach ( array( 'local_uuid', 'authorization_uri_hash', 'request_activity_uri_hash', 'standing_key' ) as $key ) {
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fixed custom index verification.
		$rows = (array) $wpdb->get_results( $wpdb->prepare( "SHOW INDEX FROM {$table} WHERE Key_name = %s", $key ), ARRAY_A );
		$unique[ $key ] = ! empty( $rows ) && 0 === (int) $rows[0]['Non_unique'];
	}
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fixed table engine verification.
	$engine = (string) $wpdb->get_var( $wpdb->prepare( 'SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = %s', $table ) );

	foreach ( array( 'local_uuid', 'authorization_uri', 'request_activity_uri', 'quoted_object_uri', 'quoting_object_uri', 'requester_actor_uri', 'author_actor_uri', 'status', 'standing_key', 'revoked_at' ) as $column ) {
		if ( ! in_array( $column, $columns, true ) ) {
			return false;
		}
	}
	return ! in_array( 'blog_id', $columns, true )
		&& ! in_array( false, $unique, true )
		&& 'InnoDB' === $engine;
}

/** Whether the verified authorization store is available. */
function axismundi_act_quote_authorizations_ready() : bool {
	return AXISMUNDI_ACT_DB_VERSION === (string) get_option( AXISMUNDI_ACT_DB_VERSION_OPTION, '' );
}

/**
 * The canonical local identity for one authorization UUID.
 *
 * A query URI rather than a path: this identity must resolve without a rewrite rule, and
 * the Quote increment should not depend on permalink state to prove consent.
 *
 * @param string $uuid Authorization UUID.
 * @return string
 */
function axismundi_act_quote_authorization_uri( string $uuid ) : string {
	return add_query_arg( 'ax_quote_authorization', rawurlencode( $uuid ), home_url( '/' ) );
}

/**
 * UUID encoded by this site's authorization identity, or null when foreign.
 *
 * @param string $uri Candidate URI.
 * @return string|null
 */
function axismundi_act_quote_authorization_uuid_from_uri( string $uri ) : ?string {
	$home = wp_parse_url( home_url( '/' ) );
	$part = wp_parse_url( $uri );
	if ( ! is_array( $home ) || ! is_array( $part ) ) {
		return null;
	}
	// Origin must match exactly, and credentials or a fragment mean this is not the identity
	// we minted.
	if ( strtolower( (string) ( $home['scheme'] ?? '' ) ) !== strtolower( (string) ( $part['scheme'] ?? '' ) )
		|| strtolower( (string) ( $home['host'] ?? '' ) ) !== strtolower( (string) ( $part['host'] ?? '' ) )
		|| (int) ( $home['port'] ?? 0 ) !== (int) ( $part['port'] ?? 0 )
		|| isset( $part['user'], $part['pass'] ) || isset( $part['fragment'] )
	) {
		return null;
	}
	// The path is part of the identity. Matching the host alone would accept
	// /anything/?ax_quote_authorization={uuid} as canonical, which is a different URI that
	// this site never issued and may not even serve.
	if ( untrailingslashit( (string) ( $part['path'] ?? '' ) ) !== untrailingslashit( (string) ( $home['path'] ?? '' ) ) ) {
		return null;
	}
	$query = array();
	parse_str( (string) ( $part['query'] ?? '' ), $query );
	// Exactly one argument: extra parameters make a different URI, and accepting them would
	// let one authorization be referenced under unboundedly many spellings.
	if ( array_keys( $query ) !== array( 'ax_quote_authorization' ) ) {
		return null;
	}
	$uuid = strtolower( trim( (string) $query['ax_quote_authorization'] ) );
	return '' !== $uuid && wp_is_uuid( $uuid ) ? $uuid : null;
}

/**
 * The uniqueness key for one standing quoting/quoted/author consent.
 *
 * Held only while an authorization stands, and cleared on revocation, so one unique index
 * can enforce "at most one active per triple" while every revoked row for that triple stays
 * (SQL treats each NULL as distinct). A composite `(triple, status)` index cannot do this:
 * it would also forbid the second honest revocation.
 *
 * @param string $quoting_object_uri Quoting Object URI.
 * @param string $quoted_object_uri  Quoted Object URI.
 * @param string $author_actor_uri   Quoted Object author Actor URI.
 * @return string
 */
function axismundi_act_quote_standing_key( string $quoting_object_uri, string $quoted_object_uri, string $author_actor_uri ) : string {
	return hash( 'sha256', implode( "\n", array( $quoting_object_uri, $quoted_object_uri, $author_actor_uri ) ) );
}

/**
 * Hydrate one authorization row.
 *
 * @param array<string,mixed> $row Raw row.
 * @return array<string,mixed>
 */
function axismundi_act_hydrate_quote_authorization( array $row ) : array {
	return array(
		'id'                   => (int) $row['id'],
		'uuid'                 => (string) $row['local_uuid'],
		'authorization_uri'    => (string) $row['authorization_uri'],
		'request_activity_uri' => (string) $row['request_activity_uri'],
		'quoted_object_uri'    => (string) $row['quoted_object_uri'],
		'quoting_object_uri'   => (string) $row['quoting_object_uri'],
		'requester_actor_uri'  => (string) $row['requester_actor_uri'],
		'author_actor_uri'     => (string) $row['author_actor_uri'],
		'status'               => (string) $row['status'],
		'created_at'           => (string) $row['created_at'],
		'revoked_at'           => null !== $row['revoked_at'] ? (string) $row['revoked_at'] : null,
		'updated_at'           => (string) $row['updated_at'],
	);
}

/**
 * Look one authorization up by an indexed hash, verifying the full URI.
 *
 * The hash is an accelerator, never the identity: a match is only accepted when the stored
 * URI is byte-identical to the requested one.
 *
 * @param string $column Hash column name.
 * @param string $uri    Full URI to match.
 * @return array<string,mixed>|null
 */
function axismundi_act_query_quote_authorization( string $column, string $uri ) : ?array {
	global $wpdb;
	$uri = axismundi_act_uri( $uri );
	if ( '' === $uri || ! in_array( $column, array( 'authorization_uri_hash', 'request_activity_uri_hash' ), true ) || ! axismundi_act_quote_authorizations_ready() ) {
		return null;
	}
	$table  = axismundi_act_quote_authorizations_table();
	$source = str_replace( '_hash', '', $column );
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- allowlisted column; exact URI verified below.
	$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE {$column} = %s", hash( 'sha256', $uri ) ), ARRAY_A );
	return is_array( $row ) && hash_equals( (string) $row[ $source ], $uri ) ? axismundi_act_hydrate_quote_authorization( $row ) : null;
}

/**
 * One authorization by its own identity URI.
 *
 * @param string $uri Authorization URI.
 * @return array<string,mixed>|null
 */
function axismundi_act_get_quote_authorization( string $uri ) : ?array {
	return axismundi_act_query_quote_authorization( 'authorization_uri_hash', $uri );
}

/**
 * The authorization issued for one QuoteRequest, whatever its current status.
 *
 * @param string $request_uri QuoteRequest Activity URI.
 * @return array<string,mixed>|null
 */
function axismundi_act_get_quote_authorization_for_request( string $request_uri ) : ?array {
	return axismundi_act_query_quote_authorization( 'request_activity_uri_hash', $request_uri );
}

/**
 * The standing authorization for one quoting/quoted/author triple, if any.
 *
 * @param string $quoting_object_uri Quoting Object URI.
 * @param string $quoted_object_uri  Quoted Object URI.
 * @param string $author_actor_uri   Quoted Object author Actor URI.
 * @return array<string,mixed>|null
 */
function axismundi_act_get_active_quote_authorization( string $quoting_object_uri, string $quoted_object_uri, string $author_actor_uri ) : ?array {
	global $wpdb;
	$quoting = axismundi_act_uri( $quoting_object_uri );
	$quoted  = axismundi_act_uri( $quoted_object_uri );
	$author  = axismundi_act_uri( $author_actor_uri );
	if ( '' === $quoting || '' === $quoted || '' === $author || ! axismundi_act_quote_authorizations_ready() ) {
		return null;
	}
	$table = axismundi_act_quote_authorizations_table();
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- indexed triple lookup; exact URIs verified below.
	$rows = (array) $wpdb->get_results(
		$wpdb->prepare(
			"SELECT * FROM {$table} WHERE quoting_object_uri_hash = %s AND quoted_object_uri_hash = %s AND author_actor_uri_hash = %s AND status = 'active'",
			hash( 'sha256', $quoting ),
			hash( 'sha256', $quoted ),
			hash( 'sha256', $author )
		),
		ARRAY_A
	);
	foreach ( $rows as $row ) {
		if ( hash_equals( (string) $row['quoting_object_uri'], $quoting )
			&& hash_equals( (string) $row['quoted_object_uri'], $quoted )
			&& hash_equals( (string) $row['author_actor_uri'], $author )
		) {
			return axismundi_act_hydrate_quote_authorization( $row );
		}
	}
	return null;
}

/**
 * Issue the authorization for one accepted QuoteRequest, or return the existing one.
 *
 * Idempotent by QuoteRequest URI: a re-delivered request must return the decision already
 * issued rather than mint a second identity for the same consent (SPEC §20).
 *
 * @param array<string,string> $args request_activity_uri, quoting_object_uri,
 *                                   quoted_object_uri, requester_actor_uri, author_actor_uri.
 * @return array<string,mixed>|WP_Error
 */
function axismundi_act_issue_quote_authorization( array $args ) {
	global $wpdb;
	if ( ! axismundi_act_quote_authorizations_ready() ) {
		return new WP_Error( 'ax_act_quote_auth_schema', __( 'The QuoteAuthorization store is unavailable.', 'axismundi-activities' ) );
	}
	$fields = array();
	foreach ( array( 'request_activity_uri', 'quoting_object_uri', 'quoted_object_uri', 'requester_actor_uri', 'author_actor_uri' ) as $key ) {
		$value = axismundi_act_uri( (string) ( $args[ $key ] ?? '' ) );
		if ( '' === $value ) {
			return new WP_Error( 'ax_act_quote_auth_args', __( 'A QuoteAuthorization requires the request, both Objects, and both Actors.', 'axismundi-activities' ) );
		}
		$fields[ $key ] = $value;
	}
	if ( hash_equals( $fields['quoting_object_uri'], $fields['quoted_object_uri'] ) ) {
		return new WP_Error( 'ax_act_quote_auth_self', __( 'An Object cannot hold an authorization to quote itself.', 'axismundi-activities' ) );
	}

	$existing = axismundi_act_get_quote_authorization_for_request( $fields['request_activity_uri'] );
	if ( null !== $existing ) {
		return $existing;
	}
	// A different request for the same triple is still the same standing consent. Return the
	// authorization already standing rather than issuing a second one.
	$standing = axismundi_act_get_active_quote_authorization( $fields['quoting_object_uri'], $fields['quoted_object_uri'], $fields['author_actor_uri'] );
	if ( null !== $standing ) {
		return $standing;
	}

	$now  = current_time( 'mysql', true );
	$uuid = wp_generate_uuid4();
	$uri  = axismundi_act_quote_authorization_uri( $uuid );
	$row  = array(
		'local_uuid'                => $uuid,
		'authorization_uri'         => $uri,
		'authorization_uri_hash'    => hash( 'sha256', $uri ),
		'status'                    => 'active',
		// Set only while standing, so the unique index permits one active authorization per
		// triple while revoked rows for the same triple accumulate under SQL's many-NULLs
		// rule. The check above is a courtesy; this is the enforcement.
		'standing_key'              => axismundi_act_quote_standing_key( $fields['quoting_object_uri'], $fields['quoted_object_uri'], $fields['author_actor_uri'] ),
		'created_at'                => $now,
		'updated_at'                => $now,
	);
	foreach ( $fields as $key => $value ) {
		$row[ $key ]            = $value;
		$row[ $key . '_hash' ]  = hash( 'sha256', $value );
	}
	$inserted = $wpdb->insert( axismundi_act_quote_authorizations_table(), $row ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- custom consent store.
	if ( false === $inserted ) {
		// A concurrent issue lost a unique index: either the same request, or a different
		// request for the same triple. Converge on whichever row won.
		$winner = axismundi_act_get_quote_authorization_for_request( $fields['request_activity_uri'] )
			?? axismundi_act_get_active_quote_authorization( $fields['quoting_object_uri'], $fields['quoted_object_uri'], $fields['author_actor_uri'] );
		return null !== $winner ? $winner : new WP_Error( 'ax_act_quote_auth_write', __( 'The QuoteAuthorization could not be issued.', 'axismundi-activities' ) );
	}
	$issued = axismundi_act_get_quote_authorization( $uri );

	/**
	 * Fires after one QuoteAuthorization is issued.
	 *
	 * @since 0.0.15
	 * @param array<string,mixed> $issued The issued authorization.
	 */
	do_action( 'axismundi_act_quote_authorization_issued', $issued );

	return $issued;
}

/**
 * Revoke one standing authorization.
 *
 * The row is never deleted: a previously issued authorization URI must keep meaning
 * "revoked" rather than "never existed", and its UUID must never be reassigned. Revoking
 * says nothing about whether the quote Object still exists — that is the projection's
 * observation, not this store's decision (SPEC §19).
 *
 * @param string $authorization_uri Authorization URI.
 * @param string $reason            Optional operator-facing note.
 * @return array<string,mixed>|WP_Error
 */
function axismundi_act_revoke_quote_authorization( string $authorization_uri, string $reason = '' ) {
	global $wpdb;
	$authorization = axismundi_act_get_quote_authorization( $authorization_uri );
	if ( null === $authorization ) {
		return new WP_Error( 'ax_act_quote_auth_missing', __( 'That QuoteAuthorization is unknown.', 'axismundi-activities' ) );
	}
	if ( 'revoked' === $authorization['status'] ) {
		return $authorization;
	}
	$now = current_time( 'mysql', true );
	// Conditional on `status = 'active'`, so exactly one caller can transition the row.
	// Clearing standing_key here is what frees the triple for a later authorization.
	$updated = $wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- custom consent store.
		axismundi_act_quote_authorizations_table(),
		array( 'status' => 'revoked', 'standing_key' => null, 'revoked_at' => $now, 'updated_at' => $now, 'last_error' => '' !== $reason ? $reason : null ),
		array( 'id' => $authorization['id'], 'status' => 'active' ),
		array( '%s', '%s', '%s', '%s', '%s' ),
		array( '%d', '%s' )
	);
	if ( false === $updated ) {
		return new WP_Error( 'ax_act_quote_auth_revoke', __( 'The QuoteAuthorization could not be revoked.', 'axismundi-activities' ) );
	}
	$revoked = axismundi_act_get_quote_authorization( $authorization['authorization_uri'] );
	if ( 1 !== (int) $updated ) {
		// Zero rows changed: a concurrent caller already revoked it between our read and our
		// write. Return the current row without firing the hook — step 5 hangs Delete
		// forwarding on it, and a lost race must not send that Delete twice.
		return is_array( $revoked ) ? $revoked : new WP_Error( 'ax_act_quote_auth_revoke', __( 'The QuoteAuthorization could not be revoked.', 'axismundi-activities' ) );
	}

	/**
	 * Fires once, for the caller that actually withdrew the authorization.
	 *
	 * @since 0.0.15
	 * @param array<string,mixed> $revoked The revoked authorization.
	 */
	do_action( 'axismundi_act_quote_authorization_revoked', $revoked );

	return $revoked;
}
