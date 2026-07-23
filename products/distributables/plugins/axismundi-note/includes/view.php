<?php
/**
 * Human-readable Note route and plugin-owned block template.
 *
 * @package AxismundiNote
 */

defined( 'ABSPATH' ) || exit;

/** @var array<string,mixed>|null Claimed HTML route state for this request. */
$GLOBALS['axismundi_note_html_route'] = null;

/** Whether this request may be handled as a human-readable Note document. */
function axismundi_note_is_html_document_request() : bool {
	$method = strtoupper( sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ?? 'GET' ) ) );
	return in_array( $method, array( 'GET', 'HEAD' ), true )
		&& ! is_admin()
		&& ! wp_doing_ajax()
		&& ! wp_is_json_request()
		&& ( ! function_exists( 'axismundi_op_is_negotiated_request' ) || ! axismundi_op_is_negotiated_request() );
}

/** Remove the fallback home-loop payload without manufacturing a singular Post. */
function axismundi_note_clear_main_query( WP_Query $query ) : void {
	$query->posts          = array();
	$query->post           = null;
	$query->post_count     = 0;
	$query->current_post   = -1;
	$query->found_posts    = 0;
	$query->max_num_pages  = 0;
	$query->queried_object = null;
	$query->queried_object_id = 0;
	$query->is_home        = false;
	$query->is_front_page  = false;
	$query->is_posts_page  = false;
	$query->is_archive     = false;
	$query->is_singular    = false;
}

/** Bind a claimed route state and its neutral object view model. */
function axismundi_note_set_html_route( int $status, ?Axismundi_Note_Source $source = null, ?array $model = null ) : void {
	$GLOBALS['axismundi_note_html_route'] = array(
		'status' => $status,
		'source' => $source,
		'model'  => $model,
	);
	if ( function_exists( 'axismundi_op_set_current_object_view_model' ) ) {
		axismundi_op_set_current_object_view_model( $model );
	}
}

/** Current claimed HTML route, or null outside the Note namespace. */
function axismundi_note_html_route() : ?array {
	$route = $GLOBALS['axismundi_note_html_route'] ?? null;
	return is_array( $route ) ? $route : null;
}

/**
 * Add the canonical object document to the private CPT's admin row actions.
 *
 * Core omits its usual View action because ax_note deliberately has no public
 * permalink. Reuse the route's combined public/owner-preview gate, so an
 * admin-list link cannot disclose a private Note but can preview a draft for
 * its author or an administrator.
 *
 * @param array<string,string> $actions Existing row actions.
 * @param WP_Post              $post    Listed post.
 * @return array<string,string>
 */
function axismundi_note_admin_view_row_action( array $actions, WP_Post $post ) : array {
	if ( AXISMUNDI_NOTE_POST_TYPE !== $post->post_type ) {
		return $actions;
	}
	$envelope = axismundi_note_get( $post->ID );
	if ( ! is_array( $envelope ) ) {
		return $actions;
	}
	$source = new Axismundi_Note_Source( $envelope, $post );
	if ( ! axismundi_note_can_view( $source ) ) {
		return $actions;
	}
	$uri = $source->get_uri();
	if ( '' === $uri ) {
		return $actions;
	}
	$label           = axismundi_note_source_visible( $source ) || $source->is_tombstone()
		? __( 'View', 'axismundi-note' )
		: __( 'Preview', 'axismundi-note' );
	$actions['view'] = '<a href="' . esc_url( $uri ) . '">' . esc_html( $label ) . '</a>';
	return $actions;
}
add_filter( 'post_row_actions', 'axismundi_note_admin_view_row_action', 10, 2 );

/** Conceal a missing or non-public Note with a real empty 404 query. */
function axismundi_note_set_html_not_found( WP_Query $query ) : void {
	axismundi_note_clear_main_query( $query );
	$query->set_404();
	axismundi_note_set_html_route( 404 );
	status_header( 404 );
	nocache_headers();
}

/**
 * Claim exact Note HTML routes before Core turns an ignored query into the home page.
 *
 * @param bool     $preempt Existing Core preemption.
 * @param WP_Query $query   Main query.
 */
function axismundi_note_handle_html_request( bool $preempt, WP_Query $query ) : bool {
	if ( ! axismundi_note_is_html_document_request() ) {
		return $preempt;
	}
	$uuid = axismundi_note_request_uuid();
	if ( null === $uuid ) {
		return $preempt;
	}
	if ( is_wp_error( $uuid ) ) {
		axismundi_note_set_html_not_found( $query );
		return true;
	}

	$envelope = axismundi_note_get_by_uuid( $uuid );
	if ( ! is_array( $envelope ) ) {
		axismundi_note_set_html_not_found( $query );
		return true;
	}
	$post   = get_post( (int) $envelope['post_id'] );
	$source = new Axismundi_Note_Source( $envelope, $post instanceof WP_Post ? $post : null );
	if ( ! axismundi_note_can_view( $source ) ) {
		axismundi_note_set_html_not_found( $query );
		return true;
	}
	$model = function_exists( 'axismundi_op_object_view_model' ) ? axismundi_op_object_view_model( $source ) : null;
	if ( ! is_array( $model ) ) {
		axismundi_note_set_html_not_found( $query );
		return true;
	}

	axismundi_note_clear_main_query( $query );
	$query->is_404 = false;
	$status        = $source->is_tombstone() ? 410 : 200;
	axismundi_note_set_html_route( $status, $source, $model );
	status_header( $status );
	if ( 410 === $status ) {
		nocache_headers();
	} elseif ( function_exists( 'axismundi_act_no_cache_like_state' ) ) {
		// The view contains visitor-specific reaction state and REST nonces.
		axismundi_act_no_cache_like_state();
	}
	return true;
}
add_filter( 'pre_handle_404', 'axismundi_note_handle_html_request', 10, 2 );

/** Select the plugin/theme/user block template only for an exposable Note route. */
function axismundi_note_object_template_include( string $template ) : string {
	$route = axismundi_note_html_route();
	if ( ! is_array( $route ) || ! in_array( (int) $route['status'], array( 200, 410 ), true ) ) {
		return $template;
	}
	$slug      = 410 === (int) $route['status'] ? 'object-tombstone' : 'single-object';
	$templates = array( $slug . '.php', 'index.php' );
	return locate_block_template( locate_template( $templates ), $slug, $templates );
}
add_filter( 'template_include', 'axismundi_note_object_template_include', 99 );

/** Emit one canonical link for the claimed object document. */
function axismundi_note_object_canonical_link() : void {
	$route = axismundi_note_html_route();
	$model = is_array( $route ) && isset( $route['model'] ) && is_array( $route['model'] ) ? $route['model'] : null;
	if ( is_array( $model ) && ! empty( $model['id'] ) ) {
		echo '<link rel="canonical" href="' . esc_url( (string) $model['id'] ) . '" />' . "\n";
	}
}
add_action( 'wp_head', 'axismundi_note_object_canonical_link', 1 );

/** Give a virtual Note document a useful title without faking a singular Post. */
function axismundi_note_object_document_title( string $title ) : string {
	$route = axismundi_note_html_route();
	$model = is_array( $route ) && isset( $route['model'] ) && is_array( $route['model'] ) ? $route['model'] : null;
	if ( ! is_array( $model ) ) {
		return $title;
	}
	if ( 'tombstone' === (string) ( $model['status'] ?? '' ) ) {
		return __( 'Deleted object', 'axismundi-note' );
	}
	$object_title = trim( (string) ( $model['title'] ?? '' ) );
	if ( '' !== $object_title ) {
		return $object_title;
	}
	$author = isset( $model['author'] ) && is_array( $model['author'] ) ? trim( (string) ( $model['author']['name'] ?? '' ) ) : '';
	return '' !== $author
		? sprintf( /* translators: %s: Actor display name. */ __( 'Note by %s', 'axismundi-note' ), $author )
		: __( 'Note', 'axismundi-note' );
}
add_filter( 'pre_get_document_title', 'axismundi_note_object_document_title' );

/**
 * Keep deleted objects, and an owner/administrator's preview of a not-yet-public
 * source, out of indexes. A 200 response here is only ever a genuinely public
 * document or a preview the visibility gate would otherwise have 404'd; the
 * latter must never be indexable just because it currently returns 200.
 */
function axismundi_note_object_robots( array $robots ) : array {
	$route = axismundi_note_html_route();
	if ( ! is_array( $route ) ) {
		return $robots;
	}
	$source    = $route['source'] ?? null;
	$is_preview = 200 === (int) $route['status'] && $source instanceof Axismundi_Note_Source && ! axismundi_note_source_visible( $source );
	if ( 410 === (int) $route['status'] || $is_preview ) {
		$robots['noindex']   = true;
		$robots['nofollow']  = true;
		$robots['noarchive'] = true;
	}
	return $robots;
}
add_filter( 'wp_robots', 'axismundi_note_object_robots' );

// The Actor feed no longer needs a Note-specific object renderer: Object
// Projections resolves any object URI (local Note via this plugin's registered
// resolve/view-model adapters, or a cached remote object) and renders the same
// compact card, gated by the shared `axismundi_op_source_publicly_visible()`
// predicate. Note keeps only its domain adapters; it owns no feed rendering.
