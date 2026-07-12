<?php
/**
 * Phase 1b media archives: rewrite routes, scoped main-query shaping, plugin
 * block templates, and the media-preview block used inside Query Loops.
 *
 * @package AxismundiMediaLibrary
 */

defined( 'ABSPATH' ) || exit;

const AXISMUNDI_MEDIA_REWRITE_VERSION = 2;

/**
 * Rewrite expressions owned by this plugin.
 *
 * @return array<string,string>
 */
function axismundi_media_rewrite_rules() : array {
	return array(
		'^media/(image|video|audio|file)/([0-9]+)/?$'                       => 'index.php?attachment_id=$matches[2]&ax_media_type=$matches[1]',
		'^media/author/([^/]+)/folder/(.+)/page/([0-9]+)/?$'               => 'index.php?ax_media_archive=folder&ax_media_owner=$matches[1]&ax_media_folder_path=$matches[2]&paged=$matches[3]',
		'^media/author/([^/]+)/folder/(.+)/?$'                              => 'index.php?ax_media_archive=folder&ax_media_owner=$matches[1]&ax_media_folder_path=$matches[2]',
		'^media/author/([^/]+)/page/([0-9]+)/?$'                            => 'index.php?ax_media_archive=owner&ax_media_owner=$matches[1]&paged=$matches[2]',
		'^media/author/([^/]+)/?$'                                         => 'index.php?ax_media_archive=owner&ax_media_owner=$matches[1]',
		'^media/page/([0-9]+)/?$'                                          => 'index.php?ax_media_archive=landing&paged=$matches[1]',
		'^media/?$'                                                         => 'index.php?ax_media_archive=landing',
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
 * Flush once after an upgrade changes this plugin's rewrite contract.
 *
 * @return void
 */
function axismundi_media_maybe_upgrade_rewrite_rules() : void {
	if ( (int) get_option( 'ax_media_rewrite_version', 0 ) >= AXISMUNDI_MEDIA_REWRITE_VERSION ) {
		return;
	}
	if ( axismundi_media_is_independent() ) {
		flush_rewrite_rules( false );
	}
	update_option( 'ax_media_rewrite_version', AXISMUNDI_MEDIA_REWRITE_VERSION, false );
}
add_action( 'init', 'axismundi_media_maybe_upgrade_rewrite_rules', 11 );

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
	$vars[] = 'ax_media_folder';
	$vars[] = 'ax_media_folder_path';
	$vars[] = 'ax_media_type';
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
 * Stable routing family derived from an Attachment MIME type.
 *
 * @param int $attachment_id Attachment ID.
 * @return string image|video|audio|file
 */
function axismundi_media_object_type( int $attachment_id ) : string {
	$family = strtok( (string) get_post_mime_type( $attachment_id ), '/' );
	return in_array( $family, array( 'image', 'video', 'audio' ), true ) ? $family : 'file';
}

/**
 * Canonical HTML URL for one Attachment object.
 *
 * @param int $attachment_id Attachment ID.
 * @return string
 */
function axismundi_media_object_url( int $attachment_id ) : string {
	if ( get_option( 'permalink_structure' ) ) {
		return home_url( '/media/' . axismundi_media_object_type( $attachment_id ) . '/' . $attachment_id . '/' );
	}
	return home_url( '/?attachment_id=' . $attachment_id );
}

/**
 * Use the Object URL contract for attachment links in Independent mode.
 */
add_filter(
	'attachment_link',
	static function ( $link, $post_id ) {
		return axismundi_media_is_independent() ? axismundi_media_object_url( (int) $post_id ) : $link;
	},
	10,
	2
);

/**
 * Correct a stale/wrong MIME hint without changing object identity.
 * Visibility's attachment guard runs first and turns inaccessible objects into
 * 404s, so this redirect never discloses private IDs.
 *
 * @return void
 */
function axismundi_media_correct_object_type() : void {
	if ( ! axismundi_media_is_independent() || ! is_attachment() || is_404() ) {
		return;
	}
	$requested = (string) get_query_var( 'ax_media_type' );
	$post_id   = (int) get_queried_object_id();
	if ( $requested && $post_id > 0 && $requested !== axismundi_media_object_type( $post_id ) ) {
		wp_safe_redirect( axismundi_media_object_url( $post_id ), 301, 'Axismundi Media Library' );
		exit;
	}
}
add_action( 'template_redirect', 'axismundi_media_correct_object_type', 20 );

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
			return home_url( '/media/author/' . rawurlencode( $user->user_nicename ) . '/' );
		}
	}
	return home_url( '/?ax_media_archive=owner&ax_media_owner=' . $user_id );
}

/**
 * Resolve a URL path beneath one owner's hidden root.
 *
 * Segments are generated from folder names rather than globally-unique term
 * slugs, allowing different owners to use the same visible paths.
 *
 * @param int    $owner_id User ID.
 * @param string $path     Slash-separated path.
 * @return int Folder term ID or 0.
 */
function axismundi_media_folder_from_path( int $owner_id, string $path ) : int {
	$parent   = axismundi_media_user_root( $owner_id, false );
	$segments = array_values( array_filter( array_map( 'sanitize_title', explode( '/', trim( rawurldecode( $path ), '/' ) ) ) ) );
	if ( $parent <= 0 || empty( $segments ) ) {
		return 0;
	}
	foreach ( $segments as $segment ) {
		$children = get_terms(
			array(
				'taxonomy'   => AXISMUNDI_MEDIA_FOLDER_TAX,
				'hide_empty' => false,
				'parent'     => $parent,
			)
		);
		$match = 0;
		if ( ! is_wp_error( $children ) ) {
			foreach ( $children as $child ) {
				if ( sanitize_title( $child->name ) === $segment && axismundi_media_folder_owner( (int) $child->term_id ) === $owner_id ) {
					$match = (int) $child->term_id;
					break;
				}
			}
		}
		if ( 0 === $match ) {
			return 0;
		}
		$parent = $match;
	}
	return $parent;
}

/**
 * Canonical folder archive URL.
 *
 * @param int $user_id User ID.
 * @param int $term_id Folder term ID.
 * @return string
 */
function axismundi_media_folder_url( int $user_id, int $term_id ) : string {
	if ( get_option( 'permalink_structure' ) ) {
		$user = get_userdata( $user_id );
		$path = array();
		$current = get_term( $term_id, AXISMUNDI_MEDIA_FOLDER_TAX );
		while ( $current instanceof WP_Term && ! axismundi_media_is_root_term( (int) $current->term_id ) ) {
			array_unshift( $path, rawurlencode( sanitize_title( $current->name ) ) );
			$current = $current->parent > 0 ? get_term( (int) $current->parent, AXISMUNDI_MEDIA_FOLDER_TAX ) : null;
		}
		if ( $user && ! empty( $path ) && axismundi_media_folder_owner( $term_id ) === $user_id ) {
			return home_url( '/media/author/' . rawurlencode( $user->user_nicename ) . '/folder/' . implode( '/', $path ) . '/' );
		}
	}
	return home_url( '/?ax_media_archive=folder&ax_media_owner=' . $user_id . '&ax_media_folder=' . $term_id );
}

/**
 * Shape only this plugin's front-end collection queries. Visibility is applied
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
	if ( ! in_array( $route, array( 'landing', 'owner', 'folder' ), true ) ) {
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

	if ( in_array( $route, array( 'owner', 'folder' ), true ) ) {
		// Canonical plain endpoint uses a numeric user ID (robust against nicename
		// changes/collisions); the pretty /media/{nicename}/ alias passes a slug.
		$raw   = (string) $query->get( 'ax_media_owner' );
		$owner = ctype_digit( $raw ) ? get_user_by( 'id', (int) $raw ) : get_user_by( 'slug', $raw );
		if ( $owner ) {
			$query->set( 'author', (int) $owner->ID );
			if ( 'folder' === $route ) {
				$raw_folder = (string) $query->get( 'ax_media_folder' );
				$folder_id  = ctype_digit( $raw_folder )
					? (int) $raw_folder
					: axismundi_media_folder_from_path( (int) $owner->ID, (string) $query->get( 'ax_media_folder_path' ) );
				$folder = get_term( $folder_id, AXISMUNDI_MEDIA_FOLDER_TAX );
				if ( ! $folder instanceof WP_Term || axismundi_media_is_root_term( $folder_id ) || axismundi_media_folder_owner( $folder_id ) !== (int) $owner->ID ) {
					$query->set( 'post__in', array( 0 ) );
					$query->set( 'ax_media_folder_missing', true );
				} else {
					$rank = axismundi_media_folder_effective_tier_rank( $folder_id );
					if ( 2 === $rank && ! axismundi_media_can_manage_folder( $folder_id ) ) {
						$query->set( 'post__in', array( 0 ) );
						$query->set( 'ax_media_folder_missing', true );
					} else {
						$query->set( 'ax_media_folder', $folder_id );
						$gate = function_exists( 'axismundi_media_locked_folder_gate' ) ? axismundi_media_locked_folder_gate( $folder_id ) : 0;
						if ( $gate > 0 ) {
							$query->set( 'post__in', array( 0 ) );
							$query->set( 'ax_media_gate_required', $gate );
						} else {
							$query->set( 'ax_media_visibility_max_rank', min( 1, $rank ) );
							if ( function_exists( 'axismundi_media_folder_effective_gate' ) && axismundi_media_folder_effective_gate( $folder_id ) ) {
								$query->set( 'ax_media_allow_gated', true );
							}
							$query->set(
								'tax_query',
								array(
									array(
										'taxonomy' => AXISMUNDI_MEDIA_FOLDER_TAX,
										'field'    => 'term_id',
										'terms'    => array( $folder_id ),
									),
								)
							);
						}
					}
				}
			}
		} else {
			$query->set( 'post__in', array( 0 ) );
			$query->set( 'ax_media_owner_missing', true );
		}
	}
}
add_action( 'pre_get_posts', 'axismundi_media_archive_query' );

/**
 * A valid media collection remains a real archive when it has zero items.
 * WordPress otherwise treats an empty custom main query as a generic 404.
 * Missing/private owner and folder routes retain their explicit 404 flags.
 *
 * @param bool     $preempt Whether core 404 handling is already preempted.
 * @param WP_Query $query   Main query.
 * @return bool
 */
function axismundi_media_allow_empty_archive( bool $preempt, WP_Query $query ) : bool {
	$route = (string) $query->get( 'ax_media_archive' );
	if (
		axismundi_media_is_independent()
		&& in_array( $route, array( 'landing', 'owner', 'folder' ), true )
		&& ! $query->get( 'ax_media_owner_missing' )
		&& ! $query->get( 'ax_media_folder_missing' )
	) {
		$query->is_404 = false;
		status_header( 200 );
		return true;
	}
	return $preempt;
}
add_filter( 'pre_handle_404', 'axismundi_media_allow_empty_archive', 10, 2 );

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
		&& ( $wp_query->get( 'ax_media_owner_missing' ) || $wp_query->get( 'ax_media_folder_missing' ) )
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
	register_block_type( __DIR__ . '/../blocks/media-archive-title' );
	register_block_type( __DIR__ . '/../blocks/media-folder-navigation' );
	register_block_type( __DIR__ . '/../blocks/media-gate' );

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
		'axismundi-media-library//media-protected',
		array(
			'title'       => __( 'Protected Media', 'axismundi-media-library' ),
			'description' => __( 'Password challenge for a protected media folder or object.', 'axismundi-media-library' ),
			'content'     => axismundi_media_template_content( 'media-protected.php' ),
		)
	);
	register_block_template(
		'axismundi-media-library//media-folder',
		array(
			'title'       => __( 'Media Folder Archive', 'axismundi-media-library' ),
			'description' => __( 'One virtual media folder.', 'axismundi-media-library' ),
			'content'     => axismundi_media_template_content( 'media-folder.php' ),
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
	if ( ! in_array( $route, array( 'landing', 'owner', 'folder' ), true ) ) {
		return $template;
	}

	$slug      = 'folder' === $route ? 'media-folder' : ( 'owner' === $route ? 'media-author' : 'media-home' );
	$templates = array( $slug . '.php', 'index.php' );
	$located   = locate_template( $templates );

	return locate_block_template( $located, $slug, $templates );
}
add_filter( 'template_include', 'axismundi_media_archive_template_include', 99 );
