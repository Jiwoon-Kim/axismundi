<?php
/**
 * Media Library shared-folder OrderedCollection projection and public route.
 *
 * Media Library owns folder identity, ACL, ordering, and item selection. Object
 * Projections owns only ActivityStreams serialization and the public read route.
 *
 * @package AxismundiObjectProjections
 */

defined( 'ABSPATH' ) || exit;

const AXISMUNDI_OP_MEDIA_FOLDER_REWRITE_VERSION = 1;
const AXISMUNDI_OP_MEDIA_FOLDER_PAGE_SIZE = 20;

/** Immutable source descriptor for one folder collection or page. */
final class Axismundi_OP_Media_Folder_Collection {
	public function __construct( private WP_Term $folder, private int $page = 0 ) {}
	public function get_folder() : WP_Term { return $this->folder; }
	public function get_page() : int { return $this->page; }
}

/** Whether the optional Media Library folder federation API is available. */
function axismundi_op_media_folder_available() : bool {
	return function_exists( 'axismundi_media_folder_from_identity_uuid' )
		&& function_exists( 'axismundi_media_folder_federation_allowed' )
		&& function_exists( 'axismundi_media_folder_federation_items' )
		&& function_exists( 'axismundi_media_folder_uri' )
		&& function_exists( 'axismundi_media_folder_actor_uri' );
}

/** Canonical URI for one collection source. */
function axismundi_op_media_folder_collection_uri( Axismundi_OP_Media_Folder_Collection $source ) : string {
	$root = axismundi_media_folder_uri( $source->get_folder()->term_id, false );
	return $source->get_page() > 0 ? trailingslashit( $root ) . 'page/' . $source->get_page() : $root;
}

/** Anonymous visibility gate delegated to Media Library. */
function axismundi_op_media_folder_collection_visible( Axismundi_OP_Media_Folder_Collection $source ) : bool {
	return axismundi_media_folder_federation_allowed( $source->get_folder()->term_id );
}

/** Human folder archive URL. */
function axismundi_op_media_folder_html_url( WP_Term $folder ) : string {
	return axismundi_media_folder_url( axismundi_media_folder_owner( $folder->term_id ), $folder->term_id );
}

/** Project one folder root or page. */
function axismundi_op_media_folder_collection_transform( Axismundi_OP_Media_Folder_Collection $source ) {
	$folder = $source->get_folder();
	$page   = $source->get_page();
	$root   = axismundi_media_folder_uri( $folder->term_id, false );
	$items  = axismundi_media_folder_federation_items( $folder->term_id, max( 1, $page ), AXISMUNDI_OP_MEDIA_FOLDER_PAGE_SIZE );
	if ( is_wp_error( $items ) ) {
		return $items;
	}
	$base = array(
		'id'           => axismundi_op_media_folder_collection_uri( $source ),
		'type'         => $page > 0 ? 'OrderedCollectionPage' : 'OrderedCollection',
		'attributedTo' => axismundi_media_folder_actor_uri( $folder->term_id ),
		'name'         => $folder->name,
		'url'          => axismundi_op_media_folder_html_url( $folder ),
	);
	if ( 0 === $page ) {
		$base['totalItems'] = (int) $items['total'];
		if ( (int) $items['total'] > 0 ) {
			$base['first'] = trailingslashit( $root ) . 'page/1';
		}
		return $base;
	}

	$ordered = array();
	foreach ( $items['ids'] as $attachment_id ) {
		$attachment = get_post( (int) $attachment_id );
		if ( ! $attachment instanceof WP_Post ) {
			continue;
		}
		$projected = axismundi_op_transform_object( $attachment );
		if ( is_array( $projected ) ) {
			unset( $projected['@context'] );
			$ordered[] = $projected;
		}
	}
	$base['partOf']       = $root;
	$base['startIndex']   = ( $page - 1 ) * AXISMUNDI_OP_MEDIA_FOLDER_PAGE_SIZE;
	$base['orderedItems'] = $ordered;
	if ( $page > 1 ) {
		$base['prev'] = trailingslashit( $root ) . 'page/' . ( $page - 1 );
	}
	if ( $page < (int) $items['pages'] ) {
		$base['next'] = trailingslashit( $root ) . 'page/' . ( $page + 1 );
	}
	return $base;
}

/** Register the folder collection transformer. */
function axismundi_op_register_media_folder_transformer() : void {
	if ( ! axismundi_op_media_folder_available() ) {
		return;
	}
	axismundi_op_register_collection_transformer(
		'axismundi-media-folder',
		array(
			'supports'       => static fn( $source ) : bool => $source instanceof Axismundi_OP_Media_Folder_Collection,
			'collection_uri' => 'axismundi_op_media_folder_collection_uri',
			'transform'      => 'axismundi_op_media_folder_collection_transform',
			'visible'        => 'axismundi_op_media_folder_collection_visible',
			'priority'       => 5,
		)
	);
}
add_action( 'axismundi_op_register_transformers', 'axismundi_op_register_media_folder_transformer' );

/** Register stable UUID collection routes. */
function axismundi_op_register_media_folder_routes() : void {
	add_rewrite_rule(
		'^media/folder/([0-9a-fA-F-]{36})/page/([0-9]+)/?$',
		'index.php?ax_op_media_folder=$matches[1]&ax_op_media_folder_page=$matches[2]',
		'top'
	);
	add_rewrite_rule(
		'^media/folder/([0-9a-fA-F-]{36})/?$',
		'index.php?ax_op_media_folder=$matches[1]',
		'top'
	);
}
add_action( 'init', 'axismundi_op_register_media_folder_routes', 7 );

/** Flush once when the folder route contract changes. */
function axismundi_op_maybe_upgrade_media_folder_routes() : void {
	if ( (int) get_option( 'ax_op_media_folder_rewrite_version', 0 ) >= AXISMUNDI_OP_MEDIA_FOLDER_REWRITE_VERSION ) {
		return;
	}
	flush_rewrite_rules( false );
	update_option( 'ax_op_media_folder_rewrite_version', AXISMUNDI_OP_MEDIA_FOLDER_REWRITE_VERSION, false );
}
add_action( 'init', 'axismundi_op_maybe_upgrade_media_folder_routes', 12 );

/** Public query vars for the direct collection route and plain fallback. */
function axismundi_op_media_folder_query_vars( array $vars ) : array {
	$vars[] = 'ax_op_media_folder';
	$vars[] = 'ax_op_media_folder_page';
	return array_values( array_unique( $vars ) );
}
add_filter( 'query_vars', 'axismundi_op_media_folder_query_vars' );

/** Resolve the current direct collection request. */
function axismundi_op_current_media_folder_collection() : ?Axismundi_OP_Media_Folder_Collection {
	if ( ! axismundi_op_media_folder_available() ) {
		return null;
	}
	$uuid = strtolower( (string) get_query_var( 'ax_op_media_folder' ) );
	if ( '' === $uuid ) {
		return null;
	}
	$folder = axismundi_media_folder_from_identity_uuid( $uuid );
	return $folder instanceof WP_Term
		? new Axismundi_OP_Media_Folder_Collection( $folder, max( 0, (int) get_query_var( 'ax_op_media_folder_page' ) ) )
		: null;
}

/** Serve a Collection endpoint independently of object content negotiation. */
function axismundi_op_media_folder_template_redirect() : void {
	$requested = '' !== (string) get_query_var( 'ax_op_media_folder' );
	if ( ! $requested ) {
		return;
	}
	$method = strtoupper( sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ?? 'GET' ) ) );
	if ( ! in_array( $method, array( 'GET', 'HEAD' ), true ) ) {
		status_header( 405 );
		header( 'Allow: GET, HEAD' );
		exit;
	}
	$source = axismundi_op_current_media_folder_collection();
	if ( null === $source ) {
		axismundi_op_emit_error( 404 );
	}
	$collection = axismundi_op_transform_collection( $source );
	if ( is_wp_error( $collection ) ) {
		axismundi_op_emit_error( 'ax_op_not_public' === $collection->get_error_code() ? 404 : 500 );
	}
	status_header( 200 );
	header( 'Content-Type: application/activity+json; charset=' . get_option( 'blog_charset' ) );
	header( 'Access-Control-Allow-Origin: *' );
	header( 'Access-Control-Allow-Methods: GET, HEAD' );
	header( 'X-Content-Type-Options: nosniff' );
	header( 'Link: <' . esc_url_raw( axismundi_op_object_html_url( $collection ) ) . '>; rel="alternate"; type="text/html"', false );
	if ( 'HEAD' !== $method ) {
		echo wp_json_encode( $collection, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- JSON encoded response.
	}
	exit;
}
add_action( 'template_redirect', 'axismundi_op_media_folder_template_redirect', 0 );
