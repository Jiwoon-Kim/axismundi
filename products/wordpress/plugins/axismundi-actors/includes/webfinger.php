<?php
/**
 * Local WebFinger discovery. This increment resolves acct handles and exposes an
 * honest JRD descriptor; ActivityStreams JSON remains owned by the future
 * Federation plugin, which may add its `rel=self` link through the links filter.
 *
 * @package AxismundiActors
 */

defined( 'ABSPATH' ) || exit;

/**
 * Normalize a host authority, retaining a non-default port for local development.
 *
 * @param string $url Absolute URL.
 * @return string
 */
function axismundi_actors_webfinger_authority_from_url( string $url ) : string {
	$host = strtolower( rtrim( (string) wp_parse_url( $url, PHP_URL_HOST ), '.' ) );
	if ( '' === $host ) {
		return '';
	}
	if ( function_exists( 'idn_to_ascii' ) ) {
		$ascii = idn_to_ascii( $host, 0, defined( 'INTL_IDNA_VARIANT_UTS46' ) ? INTL_IDNA_VARIANT_UTS46 : 0 );
		$host  = false !== $ascii ? strtolower( $ascii ) : $host;
	}
	$port   = (int) wp_parse_url( $url, PHP_URL_PORT );
	$scheme = strtolower( (string) wp_parse_url( $url, PHP_URL_SCHEME ) );
	if ( $port > 0 && ! ( 80 === $port && 'http' === $scheme ) && ! ( 443 === $port && 'https' === $scheme ) ) {
		$host .= ':' . $port;
	}
	return $host;
}

/** @return string Current site's acct authority. */
function axismundi_actors_webfinger_authority() : string {
	return axismundi_actors_webfinger_authority_from_url( home_url( '/' ) );
}

/**
 * Pure fail-closed multisite policy used by runtime and regression tests.
 *
 * @param bool   $multisite    Whether this is multisite.
 * @param bool   $subdomain    Whether the network uses subdomains.
 * @param string $site_host    Current site's host authority.
 * @param string $network_host Main network host authority.
 * @return bool
 */
function axismundi_actors_webfinger_policy_allows( bool $multisite, bool $subdomain, string $site_host, string $network_host ) : bool {
	if ( ! $multisite ) {
		return true;
	}
	if ( $subdomain ) {
		return true;
	}
	return '' !== $site_host && '' !== $network_host && strtolower( $site_host ) !== strtolower( $network_host );
}

/** @return bool Whether this site can expose an unambiguous acct namespace. */
function axismundi_actors_webfinger_enabled() : bool {
	$site_host    = axismundi_actors_webfinger_authority();
	$network_host = function_exists( 'network_home_url' )
		? axismundi_actors_webfinger_authority_from_url( network_home_url( '/' ) )
		: $site_host;
	$subdomain = function_exists( 'is_subdomain_install' ) ? is_subdomain_install() : false;
	$enabled   = axismundi_actors_webfinger_policy_allows( is_multisite(), $subdomain, $site_host, $network_host );
	return (bool) apply_filters( 'axismundi_actors_webfinger_enabled', $enabled, $site_host, $network_host );
}

/**
 * Parse and validate a local acct resource.
 *
 * @param string $resource Resource URI.
 * @return array{handle:string,authority:string,acct:string}|WP_Error
 */
function axismundi_actors_parse_local_acct_resource( string $resource ) {
	$resource = strtolower( trim( rawurldecode( $resource ) ) );
	if ( ! str_starts_with( $resource, 'acct:' ) ) {
		return new WP_Error( 'ax_actors_webfinger_resource', __( 'Unsupported WebFinger resource.', 'axismundi-actors' ) );
	}
	$bare = substr( $resource, 5 );
	$at   = strrpos( $bare, '@' );
	if ( false === $at ) {
		return new WP_Error( 'ax_actors_webfinger_resource', __( 'Invalid acct resource.', 'axismundi-actors' ) );
	}
	$handle    = substr( $bare, 0, $at );
	$authority = rtrim( substr( $bare, $at + 1 ), '.' );
	if ( ! axismundi_actors_is_valid_handle( $handle ) || $authority !== axismundi_actors_webfinger_authority() ) {
		return new WP_Error( 'ax_actors_webfinger_not_found', __( 'No such local WebFinger resource.', 'axismundi-actors' ) );
	}
	return array( 'handle' => $handle, 'authority' => $authority, 'acct' => $handle . '@' . $authority );
}

/**
 * Resolve one JRD descriptor. The canonical Actor URI is an alias until the
 * Federation plugin supplies an ActivityStreams representation and adds a self
 * link via `axismundi_actors_webfinger_links`.
 *
 * @param string $resource acct resource.
 * @return array<string,mixed>|WP_Error
 */
function axismundi_actors_webfinger_descriptor( string $resource ) {
	if ( ! axismundi_actors_webfinger_enabled() ) {
		return new WP_Error( 'ax_actors_webfinger_disabled', __( 'WebFinger is unavailable for this site topology.', 'axismundi-actors' ) );
	}
	$parsed = axismundi_actors_parse_local_acct_resource( $resource );
	if ( is_wp_error( $parsed ) ) {
		return $parsed;
	}
	$actor = axismundi_actors_get_by_handle( $parsed['handle'] );
	if ( ! $actor || ! axismundi_actors_is_public_profile( $actor ) ) {
		return new WP_Error( 'ax_actors_webfinger_not_found', __( 'No such local WebFinger resource.', 'axismundi-actors' ) );
	}
	if ( ! axismundi_actors_record_local_acct_address( $actor->get_identity_id(), $parsed['acct'] ) ) {
		return new WP_Error( 'ax_actors_webfinger_address', __( 'Could not record the local acct address.', 'axismundi-actors' ) );
	}
	$links = array(
		array(
			'rel'  => 'http://webfinger.net/rel/profile-page',
			'type' => 'text/html',
			'href' => $actor->get_profile_url(),
		),
	);
	$links = apply_filters( 'axismundi_actors_webfinger_links', $links, $actor );
	return array(
		'subject' => 'acct:' . $parsed['acct'],
		'aliases' => array_values( array_unique( array_filter( array( $actor->get_uri(), $actor->get_profile_url() ) ) ) ),
		'links'   => is_array( $links ) ? array_values( $links ) : array(),
	);
}

/**
 * Keep the local acct ledger current when a handle is registered or an actor is
 * published. The descriptor still repairs a missing row on read for upgraded sites.
 *
 * @param int $identity_id Actor identity id.
 * @return void
 */
function axismundi_actors_sync_local_acct_address( int $identity_id ) : void {
	if ( ! axismundi_actors_webfinger_enabled() ) {
		return;
	}
	$actor = axismundi_actors_get_by_identity( $identity_id );
	if ( ! $actor || ! axismundi_actors_is_public_profile( $actor ) ) {
		return;
	}
	$handle = $actor->get_preferred_username();
	axismundi_actors_record_local_acct_address( $identity_id, $handle . '@' . axismundi_actors_webfinger_authority() );
}
add_action( 'axismundi_actors_handle_registered', 'axismundi_actors_sync_local_acct_address' );
add_action( 'axismundi_actors_status_changed', 'axismundi_actors_sync_local_acct_address' );

/** Output the WebFinger JRD response. */
function axismundi_actors_serve_webfinger() : void {
	if ( 1 !== (int) get_query_var( 'ax_webfinger' ) ) {
		return;
	}
	$resource  = isset( $_GET['resource'] ) ? sanitize_text_field( wp_unslash( $_GET['resource'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- public read-only discovery endpoint.
	$descriptor = axismundi_actors_webfinger_descriptor( $resource );
	if ( is_wp_error( $descriptor ) ) {
		status_header( 404 );
		nocache_headers();
		header( 'Content-Type: application/jrd+json; charset=' . get_option( 'blog_charset' ) );
		echo wp_json_encode( array( 'error' => 'not_found' ) );
		exit;
	}
	status_header( 200 );
	header( 'Content-Type: application/jrd+json; charset=' . get_option( 'blog_charset' ) );
	header( 'Cache-Control: public, max-age=300' );
	echo wp_json_encode( $descriptor, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
	exit;
}
add_action( 'template_redirect', 'axismundi_actors_serve_webfinger', 0 );
