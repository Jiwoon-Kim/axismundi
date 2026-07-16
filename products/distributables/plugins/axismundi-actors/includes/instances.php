<?php
/**
 * Remote instance (host) ledger. NodeInfo about a *host* — software, version,
 * registration policy — is stored once per host in `wp_ax_instances`, never
 * duplicated across the actors that live there. This increment fetches and caches
 * NodeInfo; background refresh / backoff and delivery stay with the Federation
 * plugin. Fetching reuses the bounded, private-network-safe HTTPS helper.
 *
 * @package AxismundiActors
 */

defined( 'ABSPATH' ) || exit;

/**
 * Discover and cache one remote host's NodeInfo. Best-effort: a fetch failure still
 * records the attempt (`fetch_status = error`) for later backoff.
 *
 * @param string $host Host authority (host[:port]).
 * @return array<string,mixed>|WP_Error The cached row, or an error.
 */
function axismundi_actors_discover_remote_instance( string $host ) {
	$host = strtolower( rtrim( trim( $host ), '.' ) );
	if ( '' === $host || $host === axismundi_actors_webfinger_authority() ) {
		return new WP_Error( 'ax_actors_instance_host', __( 'Refusing to treat the local host as a remote instance.', 'axismundi-actors' ) );
	}

	$discovery = axismundi_actors_remote_get_json( 'https://' . $host . '/.well-known/nodeinfo', array( 'application/json', 'application/jrd+json' ) );
	if ( is_wp_error( $discovery ) ) {
		axismundi_actors_upsert_instance( $host, array( 'fetch_status' => 'error' ) );
		return $discovery;
	}

	$links = isset( $discovery['links'] ) && is_array( $discovery['links'] ) ? $discovery['links'] : array();
	$doc_url = '';
	$schema  = '';
	foreach ( array( '2.1', '2.0' ) as $want ) {
		foreach ( $links as $link ) {
			if ( is_array( $link ) && 'http://nodeinfo.diaspora.software/ns/schema/' . $want === (string) ( $link['rel'] ?? '' ) && '' !== (string) ( $link['href'] ?? '' ) ) {
				$doc_url = (string) $link['href'];
				$schema  = $want;
				break 2;
			}
		}
	}
	if ( '' === $doc_url ) {
		axismundi_actors_upsert_instance( $host, array( 'fetch_status' => 'error' ) );
		return new WP_Error( 'ax_actors_instance_schema', __( 'No supported NodeInfo schema link.', 'axismundi-actors' ) );
	}

	$doc = axismundi_actors_remote_get_json( $doc_url, array( 'application/json' ) );
	if ( is_wp_error( $doc ) ) {
		axismundi_actors_upsert_instance( $host, array( 'fetch_status' => 'error' ) );
		return $doc;
	}

	$software = isset( $doc['software'] ) && is_array( $doc['software'] ) ? $doc['software'] : array();
	$metadata = isset( $doc['metadata'] ) && is_array( $doc['metadata'] ) ? $doc['metadata'] : array();
	$fields   = array(
		'software_name'      => axismundi_actors_remote_limit_text( sanitize_text_field( (string) ( $software['name'] ?? '' ) ), 64 ),
		'software_version'   => axismundi_actors_remote_limit_text( sanitize_text_field( (string) ( $software['version'] ?? '' ) ), 64 ),
		'nodeinfo_schema'    => $schema,
		'name'               => axismundi_actors_remote_limit_text( sanitize_text_field( (string) ( $metadata['nodeName'] ?? '' ) ), 191 ),
		'description'        => wp_strip_all_tags( (string) ( $metadata['nodeDescription'] ?? '' ) ),
		'open_registrations' => isset( $doc['openRegistrations'] ) ? (int) (bool) $doc['openRegistrations'] : null,
		'fetch_status'       => 'ok',
		'payload_json'       => wp_json_encode( $doc ),
	);
	axismundi_actors_upsert_instance( $host, $fields );
	return axismundi_actors_get_instance( $host );
}

/**
 * Queue one first-time host cache fill without delaying an Inbox or discovery request.
 *
 * @param Axismundi_Actor $actor Discovered remote actor.
 * @return bool Whether the host is already cached or a fill is scheduled.
 */
function axismundi_actors_ensure_remote_instance_cached( Axismundi_Actor $actor ) : bool {
	if ( $actor->is_local() ) {
		return false;
	}
	$host = axismundi_actors_webfinger_authority_from_url( $actor->get_uri() );
	if ( '' === $host ) {
		return false;
	}
	if ( axismundi_actors_get_instance( $host ) ) {
		return true;
	}
	$args = array( $host );
	if ( ! wp_next_scheduled( 'axismundi_actors_cache_remote_instance', $args ) ) {
		wp_schedule_single_event( time() + 1, 'axismundi_actors_cache_remote_instance', $args );
	}
	return true;
}

/** Execute one queued first-time NodeInfo cache fill. */
function axismundi_actors_cache_remote_instance( string $host ) : void {
	if ( null === axismundi_actors_get_instance( $host ) ) {
		axismundi_actors_discover_remote_instance( $host );
	}
}
add_action( 'axismundi_actors_cache_remote_instance', 'axismundi_actors_cache_remote_instance' );
add_action( 'axismundi_actors_remote_actor_discovered', 'axismundi_actors_ensure_remote_instance_cached' );
add_action( 'axismundi_actors_remote_actor_updated', 'axismundi_actors_ensure_remote_instance_cached' );
