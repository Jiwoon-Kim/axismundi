<?php
/**
 * Authenticated editor search for mentionable Actors.
 *
 * @package AxismundiActors
 */

defined( 'ABSPATH' ) || exit;

/** Search public local Actors and already-cached remote Actors. */
function axismundi_actors_search_mentionable( string $search, int $limit = 10 ) : array {
	global $wpdb;
	$search     = trim( $search );
	$limit      = max( 1, min( 20, $limit ) );
	$identities = axismundi_actors_identities_table();
	$actors     = axismundi_actors_actors_table();
	$addresses  = axismundi_actors_addresses_table();
	$where      = "i.status = 'public' AND (i.origin = 'remote' OR (a.local_handle_key IS NOT NULL AND a.handle_locked_at IS NOT NULL))";
	$args       = array();
	if ( '' !== $search ) {
		$like   = '%' . $wpdb->esc_like( ltrim( $search, '@' ) ) . '%';
		$where .= " AND (a.preferred_username LIKE %s OR a.display_name LIKE %s OR i.canonical_uri LIKE %s OR EXISTS (SELECT 1 FROM {$addresses} ad WHERE ad.identity_id = i.id AND ad.address LIKE %s))";
		$args   = array( $like, $like, $like, $like );
	}
	$sql    = "SELECT i.*, a.* FROM {$identities} i INNER JOIN {$actors} a ON a.identity_id = i.id WHERE {$where} ORDER BY CASE WHEN i.origin = 'local' THEN 0 ELSE 1 END, a.display_name ASC, a.preferred_username ASC LIMIT %d";
	$args[] = $limit;
	// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fixed custom tables and clause; all values are prepared.
	$rows = (array) $wpdb->get_results( $wpdb->prepare( $sql, ...$args ), ARRAY_A );
	return array_map( static fn( array $row ) : Axismundi_Actor => Axismundi_Actor::from_row( $row ), $rows );
}

/** Human acct-style label for a mention candidate. */
function axismundi_actors_mention_handle( Axismundi_Actor $actor ) : string {
	$username = $actor->get_preferred_username();
	if ( '' === $username ) {
		return '';
	}
	if ( $actor->is_local() ) {
		return '@' . $username;
	}
	foreach ( axismundi_actors_get_addresses( $actor->get_identity_id() ) as $address ) {
		if ( 'acct' === (string) ( $address['address_type'] ?? '' ) && 'primary' === (string) ( $address['status'] ?? '' ) && ! empty( $address['verified_at'] ) ) {
			return '@' . ltrim( (string) $address['address'], '@' );
		}
	}
	$host = strtolower( (string) wp_parse_url( $actor->get_uri(), PHP_URL_HOST ) );
	return '@' . $username . ( '' !== $host ? '@' . $host : '' );
}

/** Fully-qualified ActivityStreams Mention name for remote consumers. */
function axismundi_actors_federated_mention_name( Axismundi_Actor $actor ) : string {
	$username = $actor->get_preferred_username();
	if ( '' === $username || 'public' !== $actor->get_status() ) {
		return '';
	}
	foreach ( axismundi_actors_get_addresses( $actor->get_identity_id() ) as $address ) {
		if ( 'acct' === (string) ( $address['address_type'] ?? '' ) && 'primary' === (string) ( $address['status'] ?? '' ) && ! empty( $address['verified_at'] ) ) {
			return '@' . ltrim( (string) $address['address'], '@' );
		}
	}
	$authority = function_exists( 'axismundi_actors_webfinger_authority_from_url' )
		? axismundi_actors_webfinger_authority_from_url( $actor->is_local() ? home_url( '/' ) : $actor->get_uri() )
		: strtolower( (string) wp_parse_url( $actor->is_local() ? home_url( '/' ) : $actor->get_uri(), PHP_URL_HOST ) );
	return '' !== $authority ? '@' . $username . '@' . $authority : '';
}

/** Resolve a small editor avatar without starting remote discovery. */
function axismundi_actors_mention_avatar_url( Axismundi_Actor $actor ) : string {
	$attachment_id = $actor->get_avatar_attachment_id();
	if ( $attachment_id > 0 ) {
		$url = wp_get_attachment_image_url( $attachment_id, array( 48, 48 ) );
		if ( is_string( $url ) ) {
			return $url;
		}
	}
	$user_id = $actor->get_local_user_id();
	return $user_id ? (string) get_avatar_url( $user_id, array( 'size' => 48 ) ) : '';
}

/** Permission gate for the private editor endpoint. */
function axismundi_actors_can_search_mentions() : bool {
	return current_user_can( 'edit_posts' );
}

/** REST callback for the block editor Actor completer. */
function axismundi_actors_rest_search_mentions( WP_REST_Request $request ) : WP_REST_Response {
	$search = sanitize_text_field( (string) $request->get_param( 'search' ) );
	$items  = array();
	foreach ( axismundi_actors_search_mentionable( $search, 10 ) as $actor ) {
		$handle = axismundi_actors_mention_handle( $actor );
		if ( '' === $handle ) {
			continue;
		}
		$items[] = array(
			'uri'    => $actor->get_uri(),
			'name'   => $actor->get_display_name() ?: $actor->get_preferred_username(),
			'handle' => $handle,
			'avatar' => axismundi_actors_mention_avatar_url( $actor ),
		);
	}
	return rest_ensure_response( $items );
}

/** Register the authenticated Actor mention search route. */
function axismundi_actors_register_mention_search_route() : void {
	register_rest_route(
		'axismundi/v1',
		'/actors/mention-search',
		array(
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => 'axismundi_actors_rest_search_mentions',
			'permission_callback' => 'axismundi_actors_can_search_mentions',
			'args'                => array(
				'search' => array( 'type' => 'string', 'default' => '', 'sanitize_callback' => 'sanitize_text_field' ),
			),
		)
	);
}
add_action( 'rest_api_init', 'axismundi_actors_register_mention_search_route' );
