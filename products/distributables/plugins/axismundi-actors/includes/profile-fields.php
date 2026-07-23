<?php
/**
 * Local Actor profile PropertyValue fields.
 *
 * @package AxismundiActors
 */

defined( 'ABSPATH' ) || exit;

/** @return array<int,array{id:int,name:string,url:string,position:int,verification_status:string,verified_at:string,checked_at:string,verification_error:string}> */
function axismundi_actors_get_profile_fields( int $identity_id ) : array {
	global $wpdb;
	if ( $identity_id <= 0 ) {
		return array();
	}
	$table = axismundi_actors_profile_fields_table();
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- custom table name.
	$rows = (array) $wpdb->get_results( $wpdb->prepare( "SELECT id, field_name, field_value, position, verification_status, verified_at, checked_at, verification_error FROM {$table} WHERE identity_id = %d ORDER BY position ASC, id ASC", $identity_id ), ARRAY_A );
	return array_map(
		static fn( array $row ) : array => array(
			'id'       => (int) $row['id'],
			'name'     => (string) $row['field_name'],
			'url'      => (string) $row['field_value'],
			'position' => (int) $row['position'],
			'verification_status' => (string) $row['verification_status'],
			'verified_at'         => (string) ( $row['verified_at'] ?? '' ),
			'checked_at'          => (string) ( $row['checked_at'] ?? '' ),
			'verification_error'  => (string) ( $row['verification_error'] ?? '' ),
		),
		$rows
	);
}

/** @return string Stable key for one exact, validated profile-link URL. */
function axismundi_actors_profile_field_url_hash( string $url ) : string {
	return hash( 'sha256', $url );
}

/**
 * Validate a deliberately small PropertyValue authoring surface: a label plus a
 * web URL. The protocol projection owns the HTML representation.
 *
 * @param array<int,array{name?:mixed,url?:mixed}> $fields Candidate fields.
 * @return array<int,array{name:string,url:string}>|WP_Error
 */
function axismundi_actors_normalize_profile_fields( array $fields ) {
	$normalized = array();
	$seen       = array();
	foreach ( $fields as $field ) {
		$name = sanitize_text_field( is_array( $field ) && isset( $field['name'] ) ? (string) $field['name'] : '' );
		$url  = esc_url_raw( is_array( $field ) && isset( $field['url'] ) ? trim( (string) $field['url'] ) : '' );
		if ( '' === $name && '' === $url ) {
			continue;
		}
		if ( '' === $name || '' === $url || ! in_array( strtolower( (string) wp_parse_url( $url, PHP_URL_SCHEME ) ), array( 'http', 'https' ), true ) || '' === (string) wp_parse_url( $url, PHP_URL_HOST ) ) {
			return new WP_Error( 'ax_actors_profile_field', __( 'Each profile link needs a label and a valid web address.', 'axismundi-actors' ) );
		}
		$key = axismundi_actors_profile_field_url_hash( $url );
		if ( isset( $seen[ $key ] ) ) {
			return new WP_Error( 'ax_actors_profile_field_duplicate', __( 'Each profile link must use a different web address.', 'axismundi-actors' ) );
		}
		$seen[ $key ] = true;
		$normalized[] = array( 'name' => $name, 'url' => $url );
		if ( count( $normalized ) > 8 ) {
			return new WP_Error( 'ax_actors_profile_field_limit', __( 'An actor can have at most eight profile links.', 'axismundi-actors' ) );
		}
	}
	return $normalized;
}

/**
 * Replace all local profile fields atomically after validating the complete set.
 *
 * @param Axismundi_Actor $actor Local actor.
 * @param array<int,array{name?:mixed,url?:mixed}> $fields Candidate fields.
 * @return true|WP_Error
 */
function axismundi_actors_save_profile_fields( Axismundi_Actor $actor, array $fields ) {
	global $wpdb;
	if ( ! $actor->is_local() ) {
		return new WP_Error( 'ax_actors_profile_field_origin', __( 'Only local actors can edit profile links.', 'axismundi-actors' ) );
	}
	$normalized = axismundi_actors_normalize_profile_fields( $fields );
	if ( is_wp_error( $normalized ) ) {
		return $normalized;
	}
	$table = axismundi_actors_profile_fields_table();
	$now   = current_time( 'mysql', true );
	$existing = array();
	foreach ( axismundi_actors_get_profile_fields( $actor->get_identity_id() ) as $field ) {
		$existing[ axismundi_actors_profile_field_url_hash( $field['url'] ) ] = $field;
	}
	$wpdb->query( 'START TRANSACTION' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- custom InnoDB table replacement.
	$deleted = $wpdb->delete( $table, array( 'identity_id' => $actor->get_identity_id() ), array( '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- custom table replacement.
	if ( false === $deleted ) {
		$wpdb->query( 'ROLLBACK' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		return new WP_Error( 'ax_actors_profile_field_save', __( 'Could not save profile links.', 'axismundi-actors' ) );
	}
	foreach ( $normalized as $position => $field ) {
		$previous = $existing[ axismundi_actors_profile_field_url_hash( $field['url'] ) ] ?? null;
		$inserted = $wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- custom table replacement.
			$table,
			array(
				'identity_id' => $actor->get_identity_id(),
				'field_name'  => $field['name'],
				'field_value' => $field['url'],
				'position'    => $position,
				'verification_status' => is_array( $previous ) ? $previous['verification_status'] : 'unverified',
				'verified_at'         => is_array( $previous ) && '' !== $previous['verified_at'] ? $previous['verified_at'] : null,
				'checked_at'          => is_array( $previous ) && '' !== $previous['checked_at'] ? $previous['checked_at'] : null,
				'verification_error'  => is_array( $previous ) && '' !== $previous['verification_error'] ? $previous['verification_error'] : null,
				'created_at'  => $now,
				'updated_at'  => $now,
			),
			array( '%d', '%s', '%s', '%d', '%s', '%s', '%s', '%s', '%s', '%s' )
		);
		if ( false === $inserted ) {
			$wpdb->query( 'ROLLBACK' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
			return new WP_Error( 'ax_actors_profile_field_save', __( 'Could not save profile links.', 'axismundi-actors' ) );
		}
	}
	$wpdb->query( 'COMMIT' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
	return true;
}

/**
 * Normalize a web URL for a reciprocal-link comparison.
 *
 * The comparison intentionally ignores a trailing slash, while retaining a query
 * string for plain-permalink actor profiles.
 *
 * @return string Empty when the candidate is not an absolute web URL.
 */
function axismundi_actors_normalize_profile_field_url( string $url ) : string {
	$parts  = wp_parse_url( $url );
	$scheme = strtolower( (string) ( $parts['scheme'] ?? '' ) );
	$host   = strtolower( (string) ( $parts['host'] ?? '' ) );
	if ( ! in_array( $scheme, array( 'http', 'https' ), true ) || '' === $host ) {
		return '';
	}
	$port = isset( $parts['port'] ) ? (int) $parts['port'] : 0;
	$authority = $host . ( $port > 0 && ! ( ( 'http' === $scheme && 80 === $port ) || ( 'https' === $scheme && 443 === $port ) ) ? ':' . $port : '' );
	$path = '/' . ltrim( (string) ( $parts['path'] ?? '' ), '/' );
	if ( '/' !== $path ) {
		$path = untrailingslashit( $path );
	}
	return $scheme . '://' . $authority . $path . ( isset( $parts['query'] ) && '' !== $parts['query'] ? '?' . $parts['query'] : '' );
}

/** Resolve an anchor href relative to the checked page, without dereferencing it. */
function axismundi_actors_resolve_profile_field_href( string $href, string $base_url ) : string {
	$href = trim( $href );
	if ( '' === $href || str_starts_with( $href, '#' ) ) {
		return '';
	}
	if ( '' !== axismundi_actors_normalize_profile_field_url( $href ) ) {
		return $href;
	}
	$base = wp_parse_url( $base_url );
	if ( empty( $base['scheme'] ) || empty( $base['host'] ) ) {
		return '';
	}
	$authority = $base['scheme'] . '://' . $base['host'] . ( isset( $base['port'] ) ? ':' . (int) $base['port'] : '' );
	if ( str_starts_with( $href, '//' ) ) {
		return $base['scheme'] . ':' . $href;
	}
	if ( str_starts_with( $href, '/' ) ) {
		return $authority . $href;
	}
	$path = (string) ( $base['path'] ?? '/' );
	return $authority . trailingslashit( dirname( $path ) ) . $href;
}

/**
 * Fetch one opt-in verification page. It deliberately follows no redirect and
 * accepts only bounded HTML, matching the remote-discovery SSRF posture.
 *
 * @return string|WP_Error HTML body.
 */
function axismundi_actors_remote_get_profile_html( string $url ) {
	if ( 'https' !== strtolower( (string) wp_parse_url( $url, PHP_URL_SCHEME ) ) || ! wp_http_validate_url( $url ) ) {
		return new WP_Error( 'ax_actors_profile_verify_url', __( 'Verification requires a safe HTTPS web address.', 'axismundi-actors' ) );
	}
	$response = wp_safe_remote_get(
		$url,
		array(
			'timeout'             => 10,
			'redirection'         => 0,
			'limit_response_size' => AXISMUNDI_ACTORS_REMOTE_MAX_BYTES,
			'headers'             => array( 'Accept' => 'text/html,application/xhtml+xml' ),
			'user-agent'          => 'Axismundi Actors/' . AXISMUNDI_ACTORS_VERSION . '; ' . home_url( '/' ),
		)
	);
	if ( is_wp_error( $response ) ) {
		return $response;
	}
	if ( 200 !== wp_remote_retrieve_response_code( $response ) ) {
		return new WP_Error( 'ax_actors_profile_verify_status', __( 'The linked page did not return a successful response.', 'axismundi-actors' ) );
	}
	$content_type = strtolower( trim( explode( ';', (string) wp_remote_retrieve_header( $response, 'content-type' ) )[0] ) );
	if ( ! in_array( $content_type, array( 'text/html', 'application/xhtml+xml' ), true ) ) {
		return new WP_Error( 'ax_actors_profile_verify_content_type', __( 'The linked page was not HTML.', 'axismundi-actors' ) );
	}
	$body = (string) wp_remote_retrieve_body( $response );
	if ( '' === $body || strlen( $body ) >= AXISMUNDI_ACTORS_REMOTE_MAX_BYTES ) {
		return new WP_Error( 'ax_actors_profile_verify_size', __( 'The linked page was empty or too large.', 'axismundi-actors' ) );
	}
	return $body;
}

/** Whether a bounded HTML response carries rel=me back to this human Actor profile. */
function axismundi_actors_profile_field_has_reciprocal_link( string $html, string $page_url, string $profile_url ) : bool {
	if ( ! class_exists( 'WP_HTML_Tag_Processor' ) ) {
		return false;
	}
	$expected = axismundi_actors_normalize_profile_field_url( $profile_url );
	if ( '' === $expected ) {
		return false;
	}
	$tags = new WP_HTML_Tag_Processor( $html );
	while ( $tags->next_tag( 'a' ) ) {
		$rel = strtolower( trim( (string) $tags->get_attribute( 'rel' ) ) );
		if ( ! in_array( 'me', preg_split( '/\s+/', $rel ) ?: array(), true ) ) {
			continue;
		}
		$href = axismundi_actors_resolve_profile_field_href( (string) $tags->get_attribute( 'href' ), $page_url );
		if ( hash_equals( $expected, axismundi_actors_normalize_profile_field_url( $href ) ) ) {
			return true;
		}
	}
	return false;
}

/**
 * Explicitly verify one saved URL. The remote page is never followed, crawled,
 * or rechecked in the background; this is an owner-initiated bounded request.
 *
 * @return true|WP_Error
 */
function axismundi_actors_verify_profile_field( Axismundi_Actor $actor, string $url ) {
	global $wpdb;
	if ( ! $actor->is_local() || ! axismundi_actors_can_manage( $actor ) ) {
		return new WP_Error( 'ax_actors_profile_verify_permission', __( 'You cannot verify this profile link.', 'axismundi-actors' ) );
	}
	$field = null;
	foreach ( axismundi_actors_get_profile_fields( $actor->get_identity_id() ) as $candidate ) {
		if ( hash_equals( $candidate['url'], $url ) ) {
			$field = $candidate;
			break;
		}
	}
	if ( ! is_array( $field ) || '' === $actor->get_profile_url() ) {
		return new WP_Error( 'ax_actors_profile_verify_field', __( 'Save a public actor handle and this profile link before verifying it.', 'axismundi-actors' ) );
	}
	$html   = axismundi_actors_remote_get_profile_html( $field['url'] );
	$now    = current_time( 'mysql', true );
	$table  = axismundi_actors_profile_fields_table();
	$values = array( 'checked_at' => $now, 'updated_at' => $now );
	$formats = array( '%s', '%s' );
	if ( ! is_wp_error( $html ) && axismundi_actors_profile_field_has_reciprocal_link( $html, $field['url'], $actor->get_profile_url() ) ) {
		$values['verification_status'] = 'verified';
		$values['verified_at']          = $now;
		$values['verification_error']   = null;
		$formats = array( '%s', '%s', '%s', '%s', '%s' );
	} else {
		$values['verification_status'] = 'failed';
		$values['verified_at']          = null;
		$values['verification_error']   = is_wp_error( $html ) ? $html->get_error_code() : 'ax_actors_profile_verify_reciprocal';
		$formats = array( '%s', '%s', '%s', '%s', '%s' );
	}
	$done = $wpdb->update( $table, $values, array( 'id' => $field['id'], 'identity_id' => $actor->get_identity_id() ), $formats, array( '%d', '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- profile-field verifier owns this custom table.
	if ( false === $done ) {
		return new WP_Error( 'ax_actors_profile_verify_write', __( 'The verification result could not be saved.', 'axismundi-actors' ) );
	}
	return ! is_wp_error( $html ) && 'verified' === $values['verification_status']
		? true
		: new WP_Error( (string) $values['verification_error'], __( 'The linked page does not link back to this actor profile with rel="me".', 'axismundi-actors' ) );
}

/** @return array<int,array{type:string,name:string,value:string}> */
function axismundi_actors_profile_field_attachments( Axismundi_Actor $actor ) : array {
	if ( ! $actor->is_local() ) {
		return array();
	}
	return array_map(
		static function( array $field ) : array {
			$url   = esc_url( $field['url'] );
			$label = esc_html( preg_replace( '#^https?://#', '', untrailingslashit( $field['url'] ) ) );
			return array(
				'type'  => 'PropertyValue',
				'name'  => $field['name'],
				'value' => '<a href="' . $url . '" rel="me nofollow noopener noreferrer">' . $label . '</a>',
			);
		},
		axismundi_actors_get_profile_fields( $actor->get_identity_id() )
	);
}

/** Remove child profile fields with an explicitly deleted Actor identity. */
function axismundi_actors_delete_profile_fields( int $identity_id ) : void {
	global $wpdb;
	if ( $identity_id > 0 ) {
		$wpdb->delete( axismundi_actors_profile_fields_table(), array( 'identity_id' => $identity_id ), array( '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- logical child cleanup.
	}
}
