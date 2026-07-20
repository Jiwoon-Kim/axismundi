<?php
/**
 * Human-readable Note route and plugin-owned block template.
 *
 * @package AxismundiNote
 */

defined( 'ABSPATH' ) || exit;

/** @var array<string,mixed>|null Claimed HTML route state for this request. */
$GLOBALS['axismundi_note_html_route'] = null;

/** Read the canonical functional block template bundled with Note. */
function axismundi_note_object_template_content() : string {
	$path = dirname( __DIR__ ) . '/templates/axismundi-note-object.php';
	if ( ! is_readable( $path ) ) {
		return '';
	}
	ob_start();
	include $path;
	return (string) ob_get_clean();
}

/** Register the default template; a theme or saved template may override it. */
function axismundi_note_register_object_template() : void {
	if ( ! function_exists( 'register_block_template' ) ) {
		return;
	}
	register_block_template(
		'axismundi-note//axismundi-note-object',
		array(
			'title'       => __( 'Axismundi Note Object', 'axismundi-note' ),
			'description' => __( 'The canonical human-readable view for a Note or deleted Note.', 'axismundi-note' ),
			'content'     => axismundi_note_object_template_content(),
		)
	);
}
add_action( 'init', 'axismundi_note_register_object_template', 20 );

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
	if ( ! $source->is_tombstone() && ! axismundi_note_source_visible( $source ) ) {
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
	$templates = array( 'axismundi-note-object.php', 'index.php' );
	return locate_block_template( locate_template( $templates ), 'axismundi-note-object', $templates );
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

/** Keep deleted objects out of indexes while preserving their 410 document. */
function axismundi_note_object_robots( array $robots ) : array {
	$route = axismundi_note_html_route();
	if ( is_array( $route ) && 410 === (int) $route['status'] ) {
		$robots['noindex']   = true;
		$robots['nofollow']  = true;
		$robots['noarchive'] = true;
	}
	return $robots;
}
add_filter( 'wp_robots', 'axismundi_note_object_robots' );

/**
 * Render a public Create(Note) ledger entry as the canonical compact Note view.
 *
 * Activities owns which public ledger entries appear in the Actor feed. Note
 * claims only its own active, public sources and deliberately suppresses the
 * personalized interaction slot: an Actor profile can be shared-cached.
 *
 * @param string              $html Existing product renderer output.
 * @param array<string,mixed> $item Public-safe Activity feed item.
 */
function axismundi_note_actor_feed_object_html( string $html, array $item ) : string {
	if ( '' !== $html || 'Create' !== (string) ( $item['type'] ?? '' ) ) {
		return $html;
	}
	$object_uri = (string) ( $item['object_uri'] ?? '' );
	$actor_uri  = (string) ( $item['actor_uri'] ?? '' );
	if ( '' === $object_uri || '' === $actor_uri
		|| ! function_exists( 'axismundi_op_resolve_source_by_uri' )
		|| ! function_exists( 'axismundi_op_object_view_model' )
		|| ! function_exists( 'axismundi_op_render_object_view_block' )
	) {
		return $html;
	}
	$source = axismundi_op_resolve_source_by_uri( $object_uri );
	if ( ! $source instanceof Axismundi_Note_Source || $source->is_tombstone() || ! axismundi_note_source_visible( $source ) ) {
		return $html;
	}
	$model = axismundi_op_object_view_model( $source );
	if ( ! is_array( $model ) || $actor_uri !== (string) ( $model['author']['url'] ?? '' ) ) {
		return $html;
	}
	$previous = function_exists( 'axismundi_op_current_object_view_model' ) ? axismundi_op_current_object_view_model() : null;
	axismundi_op_set_current_object_view_model( $model );
	try {
		$view = axismundi_op_render_object_view_block( array( 'headingTag' => 'h3', 'interactions' => false ) );
		if ( 'Question' === (string) ( $model['type'] ?? '' ) && function_exists( 'axismundi_op_render_question_block' ) ) {
			$view .= axismundi_op_render_question_block();
		}
		return $view;
	} finally {
		axismundi_op_set_current_object_view_model( $previous );
	}
}
add_filter( 'axismundi_act_actor_feed_object_html', 'axismundi_note_actor_feed_object_html', 10, 2 );
