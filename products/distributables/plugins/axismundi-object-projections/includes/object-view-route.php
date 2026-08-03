<?php
/**
 * Human-readable views of cached remote Objects.
 *
 * @package AxismundiObjectProjections
 */

defined( 'ABSPATH' ) || exit;

/** @var array<string,mixed>|null Claimed cached-Object HTML route. */
$GLOBALS['axismundi_op_object_html_route'] = null;

/** Stable local human-view URL for one cached remote Object URI. */
function axismundi_op_cached_object_view_url( string $object_uri ) : string {
	$valid = axismundi_op_remote_object_uri( $object_uri );
	return is_wp_error( $valid ) ? '' : add_query_arg( 'ax_object', hash( 'sha256', $valid ), home_url( '/' ) );
}

/** Whether one cached observation may have an anonymous standalone human view. */
function axismundi_op_cached_object_publicly_viewable( array $row ) : bool {
	if ( 'tombstone' === (string) ( $row['object_status'] ?? '' ) ) {
		return true;
	}
	if ( 'active' !== (string) ( $row['object_status'] ?? '' ) ) {
		return false;
	}
	$payload = is_array( $row['payload'] ?? null ) ? $row['payload'] : json_decode( (string) ( $row['payload_json'] ?? '' ), true );
	$payload = is_array( $payload ) ? $payload : array();
	$public  = array( 'https://www.w3.org/ns/activitystreams#Public', 'as:Public' );
	foreach ( array( 'to', 'cc' ) as $property ) {
		$members = $payload[ $property ] ?? array();
		$members = is_array( $members ) && array_is_list( $members ) ? $members : array( $members );
		foreach ( $members as $member ) {
			$uri = is_scalar( $member ) ? (string) $member : axismundi_op_remote_member_uri( $member );
			if ( in_array( $uri, $public, true ) ) {
				return true;
			}
		}
	}
	return false;
}

/** Make the opaque cache-view identity available to WordPress routing. */
function axismundi_op_object_view_query_vars( array $vars ) : array {
	$vars[] = 'ax_object';
	return array_values( array_unique( $vars ) );
}
add_filter( 'query_vars', 'axismundi_op_object_view_query_vars' );

/** Current claimed cached-Object route, or null outside that namespace. */
function axismundi_op_object_html_route() : ?array {
	$route = $GLOBALS['axismundi_op_object_html_route'] ?? null;
	return is_array( $route ) ? $route : null;
}

/** Remove the fallback home-loop payload without inventing a singular post. */
function axismundi_op_clear_object_main_query( WP_Query $query ) : void {
	$query->posts             = array();
	$query->post              = null;
	$query->post_count        = 0;
	$query->current_post      = -1;
	$query->found_posts       = 0;
	$query->max_num_pages     = 0;
	$query->queried_object    = null;
	$query->queried_object_id = 0;
	$query->is_home           = false;
	$query->is_front_page     = false;
	$query->is_posts_page     = false;
	$query->is_archive        = false;
	$query->is_singular       = false;
}

/** Bind cached-Object route state and its neutral view model. */
function axismundi_op_set_object_html_route( int $status, ?array $row = null, ?array $model = null, ?int $http_status = null ) : void {
	$GLOBALS['axismundi_op_object_html_route'] = array(
		'status'      => $status,
		'http_status' => $http_status ?? $status,
		'row'         => $row,
		'model'       => $model,
	);
	axismundi_op_set_current_object_view_model( $model );
}

/** Whether this request may be claimed as a cached-Object HTML document. */
function axismundi_op_is_object_html_request() : bool {
	$method = strtoupper( sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ?? 'GET' ) ) );
	return in_array( $method, array( 'GET', 'HEAD' ), true )
		&& ! is_admin()
		&& ! wp_doing_ajax()
		&& ! wp_is_json_request()
		&& ! axismundi_op_is_negotiated_request();
}

/** Exact hash requested by the local cached-Object namespace. */
function axismundi_op_requested_object_hash() : ?string {
	if ( ! isset( $_GET['ax_object'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- public read route.
		return null;
	}
	if ( 1 !== count( $_GET ) || ! is_string( $_GET['ax_object'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- exact canonical route gate.
		return '';
	}
	$hash = strtolower( sanitize_text_field( wp_unslash( $_GET['ax_object'] ) ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- public read route.
	return 1 === preg_match( '/\A[a-f0-9]{64}\z/', $hash ) ? $hash : '';
}

/** Claim one exact cached-Object route before Core falls back to the home loop. */
function axismundi_op_handle_object_html_request( bool $preempt, WP_Query $query ) : bool {
	if ( ! axismundi_op_is_object_html_request() ) {
		return $preempt;
	}
	$hash = axismundi_op_requested_object_hash();
	if ( null === $hash ) {
		return $preempt;
	}
	axismundi_op_clear_object_main_query( $query );
	$row = '' !== $hash ? axismundi_op_get_remote_object_by_hash( $hash, true ) : null;
	if ( ! is_array( $row ) ) {
		$query->set_404();
		axismundi_op_set_object_html_route( 404 );
		status_header( 404 );
		nocache_headers();
		return true;
	}
	$source = new Axismundi_Op_Remote_Source( $row );
	$model  = axismundi_op_object_view_model( $source );
	if ( ! is_array( $model ) || ! axismundi_op_cached_object_publicly_viewable( $row ) ) {
		$query->set_404();
		axismundi_op_set_object_html_route( 404 );
		status_header( 404 );
		nocache_headers();
		return true;
	}
	$query->is_404 = false;
	$status        = 'tombstone' === (string) ( $model['status'] ?? '' ) ? 410 : 200;
	/*
	 * Atomic hosting replaces every HTML 410 body with its own disabled-site page before
	 * WordPress can render the selected block template. Keep the Object lifecycle at 410,
	 * while serving its human representation as a 404 so `object-tombstone` reaches the
	 * browser. Negotiated ActivityStreams requests still emit a real 410 in router.php.
	 */
	$http_status = 410 === $status ? 404 : $status;
	axismundi_op_set_object_html_route( $status, $row, $model, $http_status );
	status_header( $http_status );
	if ( 410 === $status || ! function_exists( 'axismundi_act_no_cache_like_state' ) ) {
		nocache_headers();
	} else {
		axismundi_act_no_cache_like_state();
	}
	return true;
}
add_filter( 'pre_handle_404', 'axismundi_op_handle_object_html_request', 9, 2 );

/**
 * The block template slug one Object document is rendered through.
 *
 * Every Object route resolves its template here — the cached-remote route below and the local
 * routes owned by domain products alike — because the choice is a property of the Object, not of
 * which plugin happens to serve the request. Two routes making the same decision separately is
 * how a reply ends up looking like a root post on one path and not the other.
 *
 * A reply is split from a root post because the two pages answer different questions. A root
 * post's page is about itself; a reply's page is about a conversation it is a part of, and it
 * needs room to say where it came from. Splitting the templates is what lets those diverge in
 * the Site Editor without one of them growing conditionals for the other.
 *
 * A domain product that owns the Object's context may take over from here through the filter —
 * Forum does this for an Object bound to a community Group. This module deliberately knows
 * nothing about Groups.
 *
 * @param array<string,mixed> $model  Object view model.
 * @param int                 $status HTTP status the route resolved to.
 * @return string Template slug.
 */
function axismundi_op_object_template_slug( array $model, int $status ) : string {
	if ( 410 === $status ) {
		// A Tombstone says as little as possible; there is no reply or article variant of
		// "this was deleted", and building one would leak what it used to be.
		return 'object-tombstone';
	}
	// An Article's canonical page is its own template: the reader followed "Read more",
	// so it shows the full text rather than the stream lead-in the default page carries.
	if ( 'Article' === (string) ( $model['type'] ?? '' ) ) {
		$slug = 'single-object-article';
	} elseif ( '' !== trim( (string) ( $model['in_reply_to'] ?? '' ) ) ) {
		$slug = 'single-object-reply';
	} else {
		$slug = 'single-object';
	}
	/**
	 * Let the product that owns an Object's context route it to its own template.
	 *
	 * @param string              $slug   Template slug chosen from the Object alone.
	 * @param array<string,mixed> $model  Object view model.
	 * @param int                 $status HTTP status the route resolved to.
	 */
	return (string) apply_filters( 'axismundi_op_object_template_slug', $slug, $model, $status );
}

/** Resolve one Object template slug to a block template file. */
function axismundi_op_object_template_for_slug( string $slug ) : string {
	$templates = array( $slug . '.php', 'index.php' );
	return locate_block_template( locate_template( $templates ), $slug, $templates );
}

/** Select the editable active or Tombstone block template for a claimed route. */
function axismundi_op_object_view_template_include( string $template ) : string {
	$route = axismundi_op_object_html_route();
	if ( ! is_array( $route ) || ! in_array( (int) $route['status'], array( 200, 410 ), true ) ) {
		return $template;
	}
	$model = is_array( $route['model'] ?? null ) ? (array) $route['model'] : array();
	return axismundi_op_object_template_for_slug( axismundi_op_object_template_slug( $model, (int) $route['status'] ) );
}
add_filter( 'template_include', 'axismundi_op_object_view_template_include', 98 );

/** Preserve the remote Object URI as canonical identity for the local cached view. */
function axismundi_op_object_view_canonical_link() : void {
	$route = axismundi_op_object_html_route();
	$model = is_array( $route['model'] ?? null ) ? $route['model'] : null;
	if ( is_array( $model ) && ! empty( $model['id'] ) ) {
		echo '<link rel="canonical" href="' . esc_url( (string) $model['id'] ) . '" />' . "\n";
	}
}
add_action( 'wp_head', 'axismundi_op_object_view_canonical_link', 1 );

/** Human document title for a cached remote Object. */
function axismundi_op_object_view_document_title( string $title ) : string {
	$route = axismundi_op_object_html_route();
	$model = is_array( $route['model'] ?? null ) ? $route['model'] : null;
	if ( ! is_array( $model ) ) {
		return $title;
	}
	if ( 'tombstone' === (string) ( $model['status'] ?? '' ) ) {
		return __( 'Deleted object', 'axismundi-object-projections' );
	}
	$object_title = trim( (string) ( $model['title'] ?? '' ) );
	if ( '' !== $object_title ) {
		return $object_title;
	}
	$author = is_array( $model['author'] ?? null ) ? trim( (string) ( $model['author']['name'] ?? '' ) ) : '';
	return '' !== $author
		? sprintf( /* translators: %s: Actor display name. */ __( 'Object by %s', 'axismundi-object-projections' ), $author )
		: __( 'Object', 'axismundi-object-projections' );
}
add_filter( 'pre_get_document_title', 'axismundi_op_object_view_document_title' );

/** Keep cached views and deleted identities out of search indexes. */
function axismundi_op_object_view_robots( array $robots ) : array {
	$route = axismundi_op_object_html_route();
	if ( is_array( $route ) ) {
		$robots['noindex']  = true;
		$robots['noarchive'] = true;
		if ( 410 === (int) ( $route['status'] ?? 0 ) ) {
			$robots['nofollow'] = true;
		}
	}
	return $robots;
}
add_filter( 'wp_robots', 'axismundi_op_object_view_robots' );
