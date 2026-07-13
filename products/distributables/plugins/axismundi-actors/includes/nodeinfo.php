<?php
/**
 * Local NodeInfo 2.1. Advertises this site's software / protocol / registration
 * policy / usage at `/.well-known/nodeinfo` (discovery) and `/nodeinfo/2.1` (the
 * document). No table — built from WP options and live counts. A future Federation
 * plugin refines software name/version, protocols, and services via the filters.
 *
 * @package AxismundiActors
 */

defined( 'ABSPATH' ) || exit;

/** @return string The NodeInfo 2.1 document URL (pretty when available, else plain). */
function axismundi_actors_nodeinfo_document_url() : string {
	return get_option( 'permalink_structure' )
		? home_url( '/nodeinfo/2.1' )
		: add_query_arg( 'ax_nodeinfo', '2.1', home_url( '/' ) );
}

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
	return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$actors} a INNER JOIN {$identities} i ON i.id = a.identity_id WHERE i.origin = 'local' AND i.status = 'public' AND a.local_handle_key IS NOT NULL AND a.handle_locked_at IS NOT NULL" );
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
	$payload = 'discovery' === $which ? axismundi_actors_nodeinfo_discovery() : axismundi_actors_nodeinfo_document();
	status_header( 200 );
	header( 'Content-Type: application/json; charset=' . get_option( 'blog_charset' ) );
	header( 'Cache-Control: public, max-age=300' );
	echo wp_json_encode( $payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
	exit;
}
add_action( 'template_redirect', 'axismundi_actors_serve_nodeinfo', 0 );
