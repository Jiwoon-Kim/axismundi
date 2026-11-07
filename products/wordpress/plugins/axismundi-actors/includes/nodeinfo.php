<?php
/**
 * Local NodeInfo 2.1. Advertises this site's software / protocol / registration
 * policy / usage at `/.well-known/nodeinfo` (discovery) and the Actors-owned REST
 * document. No table — built from WP options and live counts. A future Federation
 * plugin refines software name/version, protocols, and services via the filters.
 *
 * @package AxismundiActors
 */

defined( 'ABSPATH' ) || exit;

/** @return string The NodeInfo 2.1 document URL, independent of our application API version. */
function axismundi_actors_nodeinfo_document_url() : string {
	return rest_url( 'axismundi/nodeinfo/2.1' );
}

/** Public protocol document; the discovery link remains at its standardized well-known path. */
function axismundi_actors_register_nodeinfo_route() : void {
	register_rest_route( 'axismundi/nodeinfo', '/2.1', array(
		'methods' => WP_REST_Server::READABLE,
		'permission_callback' => '__return_true',
		'callback' => static function () {
			return new WP_REST_Response( axismundi_actors_nodeinfo_document(), 200, array( 'Cache-Control' => 'public, max-age=300' ) );
		},
	) );
}
add_action( 'rest_api_init', 'axismundi_actors_register_nodeinfo_route' );

/** A stale upstream rewrite must not take ownership of our exact discovery path. */
function axismundi_actors_route_nodeinfo_discovery( WP $wp ) : void {
	$path = wp_parse_url( wp_unslash( $_SERVER['REQUEST_URI'] ?? '' ), PHP_URL_PATH );
	$expected = wp_parse_url( home_url( '/.well-known/nodeinfo' ), PHP_URL_PATH );
	if ( ! is_string( $path ) || untrailingslashit( $path ) !== $expected ) { return; }
	$wp->query_vars['ax_nodeinfo'] = 'discovery';
	unset( $wp->query_vars['rest_route'] );
}
add_action( 'parse_request', 'axismundi_actors_route_nodeinfo_discovery', 0 );

/**
 * The `/.well-known/nodeinfo` discovery document (links to the 2.1 document).
 *
 * @return array<string,mixed>
 */
function axismundi_actors_nodeinfo_discovery() : array {
	return array(
		'links' => array(
			array(
				'rel'  => 'http://nodeinfo.diaspora.software/ns/schema/2.1',
				'href' => axismundi_actors_nodeinfo_document_url(),
			),
		),
	);
}

/** @return int Count of publicly exposed local actors (registered + locked handle). */
function axismundi_actors_nodeinfo_user_count() : int {
	global $wpdb;
	$identities = axismundi_actors_identities_table();
	$actors     = axismundi_actors_actors_table();
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- custom-table live count for NodeInfo usage.
	return (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM %i a INNER JOIN %i i ON i.id = a.identity_id WHERE i.origin = 'local' AND i.status = 'public' AND a.local_handle_key IS NOT NULL AND a.handle_locked_at IS NOT NULL", $actors, $identities ) );
}

/**
 * The NodeInfo 2.1 document. Software name/version, protocols, services, and
 * metadata are filterable so the Federation plugin can own the real values.
 *
 * @return array<string,mixed>
 */
function axismundi_actors_nodeinfo_document() : array {
	$posts    = (int) wp_count_posts()->publish;
	$comments = wp_count_comments();

	$document = array(
		'version'  => '2.1',
		/**
		 * NodeInfo `software` block. The Federation plugin should set the real
		 * federating software name/version here.
		 *
		 * @param array $software name / version / repository / homepage.
		 */
		'software' => apply_filters(
			'axismundi_actors_nodeinfo_software',
			array(
				'name'       => 'axismundi',
				'version'    => defined( 'AXISMUNDI_ACTORS_VERSION' ) ? AXISMUNDI_ACTORS_VERSION : '0',
				'repository' => 'https://github.com/Jiwoon-Kim/axismundi',
				'homepage'   => home_url( '/' ),
			)
		),
		/**
		 * Supported federation protocols (empty until the Federation plugin lands).
		 *
		 * @param string[] $protocols Protocol names.
		 */
		'protocols'         => (array) apply_filters( 'axismundi_actors_nodeinfo_protocols', array() ),
		'services'          => array( 'inbound' => array(), 'outbound' => array() ),
		'openRegistrations' => (bool) get_option( 'users_can_register' ),
		'usage'             => array(
			'users'         => array( 'total' => axismundi_actors_nodeinfo_user_count() ),
			'localPosts'    => $posts,
			'localComments' => (int) $comments->approved,
		),
		/**
		 * NodeInfo `metadata` (free-form). Node name / description by default.
		 *
		 * @param array $metadata Metadata map.
		 */
		'metadata'          => (array) apply_filters(
			'axismundi_actors_nodeinfo_metadata',
			array(
				'nodeName'        => get_bloginfo( 'name' ),
				'nodeDescription' => get_bloginfo( 'description' ),
			)
		),
	);
	return $document;
}

/** Serve the NodeInfo discovery or 2.1 document. */
function axismundi_actors_serve_nodeinfo() : void {
	$which = (string) get_query_var( 'ax_nodeinfo' );
	if ( '' === $which ) {
		return;
	}
	$method = strtoupper( sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ?? 'GET' ) ) );
	if ( ! in_array( $method, array( 'GET', 'HEAD' ), true ) ) { status_header( 405 ); header( 'Allow: GET, HEAD' ); exit; }
	if ( 'discovery' !== $which ) { wp_safe_redirect( axismundi_actors_nodeinfo_document_url(), 302 ); exit; }
	header( 'Access-Control-Allow-Origin: *' );
	header( 'Access-Control-Allow-Methods: GET, HEAD' );
	$payload = axismundi_actors_nodeinfo_discovery();
	status_header( 200 );
	header( 'Content-Type: application/json; charset=' . get_option( 'blog_charset' ) );
	header( 'Cache-Control: public, max-age=300' );
	if ( 'HEAD' !== $method ) {
		echo wp_json_encode( $payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
	}
	exit;
}
add_action( 'template_redirect', 'axismundi_actors_serve_nodeinfo', 0 );
