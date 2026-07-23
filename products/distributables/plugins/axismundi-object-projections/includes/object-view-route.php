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
function axismundi_op_set_object_html_route( int $status, ?array $row = null, ?array $model = null ) : void {
	$GLOBALS['axismundi_op_object_html_route'] = array(
		'status' => $status,
		'row'    => $row,
		'model'  => $model,
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
	$row = '' !== $hash ? axismundi_op_remote_object_get_by_hash( $hash, true ) : null;
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
	axismundi_op_set_object_html_route( $status, $row, $model );
	status_header( $status );
	if ( 410 === $status || ! function_exists( 'axismundi_act_no_cache_like_state' ) ) {
		nocache_headers();
	} else {
		axismundi_act_no_cache_like_state();
	}
	return true;
}
add_filter( 'pre_handle_404', 'axismundi_op_handle_object_html_request', 9, 2 );

/** Select the editable active or Tombstone block template for a claimed route. */
function axismundi_op_object_view_template_include( string $template ) : string {
	$route = axismundi_op_object_html_route();
	if ( ! is_array( $route ) || ! in_array( (int) $route['status'], array( 200, 410 ), true ) ) {
		return $template;
	}
	$slug      = 410 === (int) $route['status'] ? 'object-tombstone' : 'single-object';
	$templates = array( $slug . '.php', 'index.php' );
	return locate_block_template( locate_template( $templates ), $slug, $templates );
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
