<?php
/**
 * Phase 1b media archives: rewrite routes, scoped main-query shaping, plugin
 * block templates, and the media-preview block used inside Query Loops.
 *
 * @package AxismundiMediaLibrary
 */

defined( 'ABSPATH' ) || exit;

/**
 * Rewrite expressions owned by this plugin.
 *
 * @return array<string,string>
 */
function axismundi_media_rewrite_rules() : array {
	return array(
		'^media/page/([0-9]+)/?$'             => 'index.php?ax_media_archive=landing&paged=$matches[1]',
		'^media/([^/]+)/page/([0-9]+)/?$'     => 'index.php?ax_media_archive=owner&ax_media_owner=$matches[1]&author_name=$matches[1]&paged=$matches[2]',
		'^media/?$'                            => 'index.php?ax_media_archive=landing',
		'^media/([^/]+)/?$'                    => 'index.php?ax_media_archive=owner&ax_media_owner=$matches[1]&author_name=$matches[1]',
	);
}

/**
 * Add Media Library routes to the current rewrite build.
 *
 * @return void
 */
function axismundi_media_register_rewrite_rules() : void {
	if ( ! axismundi_media_is_independent() ) {
		return;
	}
	foreach ( axismundi_media_rewrite_rules() as $regex => $query ) {
		add_rewrite_rule( $regex, $query, 'top' );
	}
}
add_action( 'init', 'axismundi_media_register_rewrite_rules', 9 );

/**
 * Remove this plugin's rules from the in-memory rewrite build before a mode-off
 * or deactivation flush.
 *
 * @return void
 */
function axismundi_media_remove_rewrite_rules() : void {
	global $wp_rewrite;
	if ( ! $wp_rewrite instanceof WP_Rewrite ) {
		return;
	}
	foreach ( array_keys( axismundi_media_rewrite_rules() ) as $regex ) {
		unset( $wp_rewrite->extra_rules_top[ $regex ], $wp_rewrite->extra_rules[ $regex ] );
	}
}

/**
 * Register the plugin's public query vars.
 *
 * @param string[] $vars Existing public query vars.
 * @return string[]
 */
function axismundi_media_query_vars( array $vars ) : array {
	$vars[] = 'ax_media_archive';
	$vars[] = 'ax_media_owner';
	return array_values( array_unique( $vars ) );
}
add_filter( 'query_vars', 'axismundi_media_query_vars' );

/**
 * Canonical media landing URL: the assigned Media Page if set and published,
 * else the pretty /media/ alias, else the always-working plain query endpoint.
 *
 * @return string
 */
function axismundi_media_landing_url() : string {
	$page = (int) get_option( 'ax_media_page_id', 0 );
	if ( $page > 0 && 'publish' === get_post_status( $page ) ) {
		return (string) get_permalink( $page );
	}
	return get_option( 'permalink_structure' )
		? home_url( '/media/' )
		: home_url( '/?ax_media_archive=landing' );
}

/**
 * Canonical media author URL for a user: pretty alias when permalinks are on,
 * else the plain, ID-based query endpoint (robust against nicename changes).
 *
 * @param int $user_id User ID.
 * @return string
 */
function axismundi_media_author_url( int $user_id ) : string {
	if ( get_option( 'permalink_structure' ) ) {
		$user = get_userdata( $user_id );
		if ( $user ) {
			return home_url( '/media/' . rawurlencode( $user->user_nicename ) . '/' );
		}
	}
	return home_url( '/?ax_media_archive=owner&ax_media_owner=' . $user_id );
}

/**
 * Shape only this plugin's two front-end archive queries. Visibility is applied
 * by the existing flagged posts_where policy; no global Attachment query changes.
 *
 * @param WP_Query $query Query instance.
 * @return void
 */
function axismundi_media_archive_query( WP_Query $query ) : void {
	if ( is_admin() || ! $query->is_main_query() || ! axismundi_media_is_independent() ) {
		return;
	}

	// The assigned Media Page (Reading Settings) acts as the landing route — an
	// editable, stable entry point that works even when pretty permalinks are off
	// (its /?page_id={id} URL always resolves). Convert the page request into the
	// landing archive.
	$media_page = (int) get_option( 'ax_media_page_id', 0 );
	if ( $media_page > 0 && ! $query->get( 'ax_media_archive' ) ) {
		$is_media_page = ( (int) $query->get( 'page_id' ) === $media_page );
		if ( ! $is_media_page && $query->get( 'pagename' ) ) {
			$maybe = get_page_by_path( (string) $query->get( 'pagename' ) );
			$is_media_page = ( $maybe instanceof WP_Post && (int) $maybe->ID === $media_page );
		}
		if ( $is_media_page ) {
			$query->set( 'ax_media_archive', 'landing' );
			$query->set( 'page_id', '' );
			$query->set( 'pagename', '' );
			$query->is_page     = false;
			$query->is_singular = false;
		}
	}

	$route = (string) $query->get( 'ax_media_archive' );
	if ( ! in_array( $route, array( 'landing', 'owner' ), true ) ) {
		return;
	}

	$query->set( 'post_type', 'attachment' );
	$query->set( 'post_status', 'inherit' );
	$query->set( 'posts_per_page', (int) get_option( 'posts_per_page', 10 ) );
	$query->set( 'orderby', 'date' );
	$query->set( 'order', 'DESC' );
	$query->set( 'ax_media_visibility_filter', true );
	$query->is_home    = false;
	$query->is_archive = true;

	if ( 'owner' === $route ) {
		// Canonical plain endpoint uses a numeric user ID (robust against nicename
		// changes/collisions); the pretty /media/{nicename}/ alias passes a slug.
		$raw   = (string) $query->get( 'ax_media_owner' );
		$owner = ctype_digit( $raw ) ? get_user_by( 'id', (int) $raw ) : get_user_by( 'slug', $raw );
		if ( $owner ) {
			$query->set( 'author', (int) $owner->ID );
		} else {
			$query->set( 'post__in', array( 0 ) );
			$query->set( 'ax_media_owner_missing', true );
		}
	}
}
add_action( 'pre_get_posts', 'axismundi_media_archive_query' );

/**
 * Turn an unknown owner archive into a real 404 before template selection.
 *
 * @return void
 */
function axismundi_media_missing_owner_404() : void {
	global $wp_query;
	if (
		axismundi_media_is_independent()
		&& $wp_query instanceof WP_Query
		&& $wp_query->get( 'ax_media_owner_missing' )
	) {
		$wp_query->set_404();
		status_header( 404 );
		nocache_headers();
	}
}
add_action( 'template_redirect', 'axismundi_media_missing_owner_404', 1 );

/**
 * Read a plugin template file as registered block-template content.
 *
 * @param string $filename Basename within templates/.
 * @return string
 */
function axismundi_media_template_content( string $filename ) : string {
	$path = __DIR__ . '/../templates/' . basename( $filename );
	if ( ! is_readable( $path ) ) {
		return '';
	}
	ob_start();
	include $path;
	return (string) ob_get_clean();
}

/**
 * Register editable plugin templates and the media-preview dynamic block.
 *
 * @return void
 */
function axismundi_media_register_archive_blocks_and_templates() : void {
	register_block_type( __DIR__ . '/../blocks/media-preview' );

	if ( ! axismundi_media_is_independent() || ! function_exists( 'register_block_template' ) ) {
		return;
	}

	register_block_template(
		'axismundi-media-library//media-home',
		array(
			'title'       => __( 'Media Home', 'axismundi-media-library' ),
			'description' => __( 'The media hub — public media from all owners.', 'axismundi-media-library' ),
			'content'     => axismundi_media_template_content( 'media-home.php' ),
		)
	);
	register_block_template(
		'axismundi-media-library//media-author',
		array(
			'title'       => __( 'Media Author Archive', 'axismundi-media-library' ),
			'description' => __( "One WordPress user's public media (owner = post_author).", 'axismundi-media-library' ),
			'content'     => axismundi_media_template_content( 'media-author.php' ),
		)
	);
}
add_action( 'init', 'axismundi_media_register_archive_blocks_and_templates', 20 );

/**
 * Select the highest-priority PHP/block template for a Media Library route.
 *
 * @param string $template Located PHP template.
 * @return string
 */
function axismundi_media_archive_template_include( string $template ) : string {
	if ( ! axismundi_media_is_independent() || is_404() ) {
		return $template;
	}

	$route = (string) get_query_var( 'ax_media_archive' );
	if ( ! in_array( $route, array( 'landing', 'owner' ), true ) ) {
		return $template;
	}

	$slug      = 'owner' === $route ? 'media-author' : 'media-home';
	$templates = array( $slug . '.php', 'index.php' );
	$located   = locate_template( $templates );

	return locate_block_template( $located, $slug, $templates );
}
add_filter( 'template_include', 'axismundi_media_archive_template_include', 99 );
