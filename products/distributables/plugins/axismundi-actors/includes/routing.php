<?php
/**
 * Phase 2 actor profile routing, visibility, and block-template selection.
 *
 * @package AxismundiActors
 */

defined( 'ABSPATH' ) || exit;

/** @var Axismundi_Actor|null Actor resolved for the current front-end request. */
$GLOBALS['axismundi_actors_current_actor'] = null;

/**
 * Rewrite expressions owned by this plugin.
 *
 * @return array<string,string>
 */
function axismundi_actors_rewrite_rules() : array {
	return array(
		'^\.well-known/webfinger/?$'      => 'index.php?ax_webfinger=1',
		'^\.well-known/nodeinfo/?$'       => 'index.php?ax_nodeinfo=discovery',
		'^nodeinfo/2\.1/?$'               => 'index.php?ax_nodeinfo=2.1',
		'^actors/([0-9a-fA-F-]{36})/?$' => 'index.php?ax_actor=$matches[1]',
		'^@([^/]+)/?$'                  => 'index.php?ax_actor_handle=$matches[1]',
	);
}

/** @return void */
function axismundi_actors_register_rewrite_rules() : void {
	foreach ( axismundi_actors_rewrite_rules() as $regex => $query ) {
		add_rewrite_rule( $regex, $query, 'top' );
	}
}
add_action( 'init', 'axismundi_actors_register_rewrite_rules', 9 );

/** @return void */
function axismundi_actors_remove_rewrite_rules() : void {
	global $wp_rewrite;
	if ( ! $wp_rewrite instanceof WP_Rewrite ) {
		return;
	}
	foreach ( array_keys( axismundi_actors_rewrite_rules() ) as $regex ) {
		unset( $wp_rewrite->extra_rules_top[ $regex ], $wp_rewrite->extra_rules[ $regex ] );
	}
}

/**
 * Whether every rule this plugin owns is present in the persisted rewrite table.
 *
 * Registration and persistence are different things: add_rewrite_rule() only fills an
 * in-memory array, while WordPress routes requests from the stored `rewrite_rules`
 * option. This asks the question that matters — are the rules really there?
 *
 * @return bool
 */
function axismundi_actors_rewrite_rules_installed() : bool {
	$stored = get_option( 'rewrite_rules' );
	if ( ! is_array( $stored ) ) {
		return false;
	}
	foreach ( array_keys( axismundi_actors_rewrite_rules() ) as $regex ) {
		if ( ! isset( $stored[ $regex ] ) ) {
			return false;
		}
	}
	return true;
}

/**
 * Install the routes whenever they are missing, rather than once per version counter.
 *
 * A version counter records an *intent* to flush and then burns itself whether or not the
 * flush persisted — flush_rewrite_rules() returns void, so it can never tell. Once
 * consumed it never retries, so a rule that failed to reach the table stays missing until
 * someone saves permalinks by hand. That is not hypothetical: Object Projections shipped
 * exactly this gate in 0.0.18 and every /media/folder/{uuid} 404'd on a live site.
 *
 * Checking for the rules is self-healing for any cause, and it also removes the reason
 * this counter reached 3: a changed rule set no longer needs a manual bump to take
 * effect, because a table without the new rule simply reports as not installed.
 *
 * @return void
 */
function axismundi_actors_maybe_upgrade_rewrite_rules() : void {
	// Plain permalinks keep no rewrite table at all, so there is nothing to install and
	// nothing to compare against; without this guard the check below would flush forever.
	if ( '' === (string) get_option( 'permalink_structure', '' ) ) {
		return;
	}
	if ( axismundi_actors_rewrite_rules_installed() ) {
		return;
	}
	// Bound the retry so a rule that can never persist degrades to one flush an hour
	// rather than one per request.
	if ( get_transient( 'ax_actors_rewrite_retry' ) ) {
		return;
	}
	set_transient( 'ax_actors_rewrite_retry', 1, HOUR_IN_SECONDS );
	flush_rewrite_rules( false );
}
add_action( 'init', 'axismundi_actors_maybe_upgrade_rewrite_rules', 11 );

/**
 * @param string[] $vars Public query vars.
 * @return string[]
 */
function axismundi_actors_query_vars( array $vars ) : array {
	$vars[] = 'ax_actor';
	$vars[] = 'ax_actor_handle';
	$vars[] = 'ax_webfinger';
	$vars[] = 'ax_nodeinfo';
	return array_values( array_unique( $vars ) );
}
add_filter( 'query_vars', 'axismundi_actors_query_vars' );

/**
 * Whether the viewer owns or administrates this actor.
 *
 * @param Axismundi_Actor $actor Actor.
 * @param int|null        $user_id Viewer; defaults to current user.
 * @return bool
 */
function axismundi_actors_can_preview( Axismundi_Actor $actor, ?int $user_id = null ) : bool {
	$user_id = null === $user_id ? get_current_user_id() : $user_id;
	if ( $user_id <= 0 ) {
		return false;
	}
	if ( user_can( $user_id, 'manage_options' ) ) {
		return true;
	}
	$local_user_id = $actor->get_local_user_id();
	if ( null !== $local_user_id && $local_user_id === $user_id ) {
		return true;
	}
	return 'site' === $actor->get_scope() && (int) get_option( 'ax_actors_site_owner_user_id', 0 ) === $user_id;
}

/**
 * Whether an actor is exposed to the public. Being `public` is not enough: the
 * handle must be registered and locked (docs/DATA-MODEL §6). This prevents a
 * handle-less actor from ever being routed publicly.
 *
 * @param Axismundi_Actor $actor Actor.
 * @return bool
 */
function axismundi_actors_is_public_profile( Axismundi_Actor $actor ) : bool {
	return $actor->is_local()
		&& 'public' === $actor->get_status()
		&& $actor->is_handle_locked()
		&& '' !== $actor->get_preferred_username();
}

/**
 * @param Axismundi_Actor $actor Actor.
 * @param int|null        $user_id Viewer; defaults to current user.
 * @return bool
 */
function axismundi_actors_can_view( Axismundi_Actor $actor, ?int $user_id = null ) : bool {
	return axismundi_actors_is_public_profile( $actor )
		|| axismundi_actors_can_preview( $actor, $user_id );
}

/**
 * Resolve current query vars without changing global query state.
 *
 * @param string $uuid   UUID query value.
 * @param string $handle Handle query value.
 * @return Axismundi_Actor|null
 */
function axismundi_actors_resolve_request_actor( string $uuid, string $handle ) : ?Axismundi_Actor {
	if ( '' !== $uuid ) {
		if ( 1 !== preg_match( '/^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', $uuid ) ) {
			return null;
		}
		return axismundi_actors_get_by_uuid( strtolower( $uuid ) );
	}
	return '' !== $handle ? axismundi_actors_get_by_handle( rawurldecode( $handle ) ) : null;
}

/**
 * Current request actor after the route visibility gate.
 *
 * @return Axismundi_Actor|null
 */
function axismundi_actors_current_actor() : ?Axismundi_Actor {
	$actor = $GLOBALS['axismundi_actors_current_actor'] ?? null;
	return $actor instanceof Axismundi_Actor ? $actor : null;
}

/**
 * Resolve only actor query vars, preempt Core's empty-query 404, and conceal
 * missing/non-public identities with the same response.
 *
 * @param bool     $preempt Existing Core preemption.
 * @param WP_Query $query Main query.
 * @return bool
 */
function axismundi_actors_handle_profile_request( bool $preempt, WP_Query $query ) : bool {
	$uuid   = (string) $query->get( 'ax_actor' );
	$handle = (string) $query->get( 'ax_actor_handle' );
	if ( '' === $uuid && '' === $handle ) {
		return $preempt;
	}

	$actor = axismundi_actors_resolve_request_actor( $uuid, $handle );
	if ( ! $actor || ! axismundi_actors_can_view( $actor ) ) {
		$GLOBALS['axismundi_actors_current_actor'] = null;
		$query->set_404();
		status_header( 404 );
		nocache_headers();
		return true;
	}

	$GLOBALS['axismundi_actors_current_actor'] = $actor;
	$query->is_404     = false;
	$query->is_home    = false;
	$query->is_archive = false;
	$query->is_singular = false;
	status_header( 200 );
	if ( ! $actor->is_local() || 'public' !== $actor->get_status() ) {
		nocache_headers();
	}
	return true;
}
add_filter( 'pre_handle_404', 'axismundi_actors_handle_profile_request', 10, 2 );

/**
 * Actor profile data resolved live for local identities.
 *
 * @param Axismundi_Actor $actor Actor.
 * @return array{name:string,summary:string,content:string,url:string,avatar:string}
 */
function axismundi_actors_profile_data( Axismundi_Actor $actor ) : array {
	if ( ! $actor->is_local() ) {
		$payload = axismundi_actors_get_remote_payload( $actor->get_identity_id() );
		$url     = $actor->get_profile_url();
		if ( '' === $url && isset( $payload['url'] ) && is_string( $payload['url'] ) ) {
			$url = esc_url_raw( $payload['url'] );
		}
		return array(
			'name'    => $actor->get_display_name() ?: $actor->get_preferred_username(),
			'summary' => isset( $payload['summary'] ) && is_string( $payload['summary'] ) ? $payload['summary'] : '',
			'content' => '',
			'url'     => $url,
			'avatar'  => '',
		);
	}
	$language = axismundi_actors_profile_language( $actor );
	if ( 'site' === $actor->get_scope() ) {
		return array(
			'name'    => axismundi_actors_resolve_text( $actor, 'name', $language ),
			'summary' => axismundi_actors_resolve_text( $actor, 'summary', $language ),
			'content' => axismundi_actors_resolve_text( $actor, 'content', $language ),
			'url'     => home_url( '/' ),
			'avatar'  => (string) get_site_icon_url( 192 ),
		);
	}
	$user_id = $actor->get_local_user_id();
	return array(
		'name'    => axismundi_actors_resolve_text( $actor, 'name', $language ),
		'summary' => axismundi_actors_resolve_text( $actor, 'summary', $language ),
		'content' => axismundi_actors_resolve_text( $actor, 'content', $language ),
		'url'     => $user_id ? (string) get_the_author_meta( 'user_url', $user_id ) : '',
		'avatar'  => $user_id ? (string) get_avatar_url( $user_id, array( 'size' => 192 ) ) : '',
	);
}

/**
 * Avatar markup: the actor's avatar attachment (with srcset), else a core avatar
 * for a local Person, else the site icon for the site actor, else ''.
 *
 * @param Axismundi_Actor $actor Actor.
 * @param int             $size  Square size in px.
 * @return string
 */
function axismundi_actors_avatar_html( Axismundi_Actor $actor, int $size = 96 ) : string {
	if ( ! $actor->is_local() ) {
		$src = axismundi_actors_get_cached_asset_url( $actor->get_identity_id(), 'avatar', $size );
		if ( '' === $src ) {
			return '';
		}
		$srcset       = axismundi_actors_get_cached_asset_sources( $actor->get_identity_id(), 'avatar' );
		$srcset_value = implode( ', ', array_map( static fn( string $url, int $width ) : string => esc_url( $url ) . ' ' . $width . 'w', array_keys( $srcset ), array_values( $srcset ) ) );
		return '<img class="ax-actor-profile__avatar" src="' . esc_url( $src ) . '"' . ( '' !== $srcset_value ? ' srcset="' . esc_attr( $srcset_value ) . '" sizes="' . (int) $size . 'px"' : '' ) . ' alt="" width="' . (int) $size . '" height="' . (int) $size . '" />';
	}
	$attachment_id = $actor->get_avatar_attachment_id();
	if ( $attachment_id > 0 ) {
		return (string) wp_get_attachment_image( $attachment_id, array( $size, $size ), false, array( 'class' => 'ax-actor-profile__avatar', 'alt' => '' ) );
	}
	if ( 'site' === $actor->get_scope() ) {
		$icon = (string) get_site_icon_url( $size );
		return '' !== $icon ? '<img class="ax-actor-profile__avatar" src="' . esc_url( $icon ) . '" alt="" width="' . (int) $size . '" height="' . (int) $size . '" />' : '';
	}
	$user_id = $actor->get_local_user_id();
	return $user_id ? (string) get_avatar( $user_id, $size, '', '', array( 'class' => 'ax-actor-profile__avatar' ) ) : '';
}

/**
 * Header (cover) markup: the actor's header attachment, else '' (no fallback).
 *
 * @param Axismundi_Actor $actor Actor.
 * @return string
 */
function axismundi_actors_header_html( Axismundi_Actor $actor ) : string {
	if ( ! $actor->is_local() ) {
		$src = axismundi_actors_get_cached_asset_url( $actor->get_identity_id(), 'header', 1024 );
		if ( '' === $src ) {
			return '';
		}
		$srcset       = axismundi_actors_get_cached_asset_sources( $actor->get_identity_id(), 'header' );
		$srcset_value = implode( ', ', array_map( static fn( string $url, int $width ) : string => esc_url( $url ) . ' ' . $width . 'w', array_keys( $srcset ), array_values( $srcset ) ) );
		return '<img class="ax-actor-profile__header-image" src="' . esc_url( $src ) . '"' . ( '' !== $srcset_value ? ' srcset="' . esc_attr( $srcset_value ) . '" sizes="100vw"' : '' ) . ' alt="" />';
	}
	$attachment_id = $actor->get_header_attachment_id();
	return $attachment_id > 0
		? (string) wp_get_attachment_image( $attachment_id, 'large', false, array( 'class' => 'ax-actor-profile__header-image', 'alt' => '' ) )
		: '';
}

/** @return string */
function axismundi_actors_profile_template_content() : string {
	$path = __DIR__ . '/../templates/actor-profile.php';
	if ( ! is_readable( $path ) ) {
		return '';
	}
	ob_start();
	include $path;
	return (string) ob_get_clean();
}

/** @return void */
function axismundi_actors_register_profile_block_and_template() : void {
	register_block_type( __DIR__ . '/../blocks/actor-profile' );
	register_block_type( __DIR__ . '/../blocks/actor-projections' );
	if ( function_exists( 'register_block_template' ) ) {
		register_block_template(
			'axismundi-actors//actor-profile',
			array(
				'title'       => __( 'Actor Profile', 'axismundi-actors' ),
				'description' => __( 'A public or owner-preview actor identity hub.', 'axismundi-actors' ),
				'content'     => axismundi_actors_profile_template_content(),
			)
		);
	}
}
add_action( 'init', 'axismundi_actors_register_profile_block_and_template', 20 );

/**
 * @param string $template Located PHP template.
 * @return string
 */
function axismundi_actors_profile_template_include( string $template ) : string {
	if ( ! axismundi_actors_current_actor() || is_404() ) {
		return $template;
	}
	$templates = array( 'actor-profile.php', 'index.php' );
	return locate_block_template( locate_template( $templates ), 'actor-profile', $templates );
}
add_filter( 'template_include', 'axismundi_actors_profile_template_include', 99 );

/** Prevent administrator-only remote cache previews from entering search indexes. */
function axismundi_actors_remote_preview_robots( array $robots ) : array {
	$actor = axismundi_actors_current_actor();
	if ( $actor && ! $actor->is_local() ) {
		$robots['noindex']   = true;
		$robots['nofollow']  = true;
		$robots['noarchive'] = true;
	}
	return $robots;
}
add_filter( 'wp_robots', 'axismundi_actors_remote_preview_robots' );

/** @return void */
function axismundi_actors_print_canonical() : void {
	$actor = axismundi_actors_current_actor();
	if ( $actor ) {
		printf( '<link rel="canonical" href="%s" />\n', esc_url( $actor->get_uri() ) );
	}
}
add_action( 'wp_head', 'axismundi_actors_print_canonical', 1 );

/**
 * Put the resolved actor name in the browser/document title.
 *
 * @param array<string,string> $parts Core title parts.
 * @return array<string,string>
 */
function axismundi_actors_document_title_parts( array $parts ) : array {
	$actor = axismundi_actors_current_actor();
	if ( $actor ) {
		$parts['title'] = $actor->get_display_name();
	}
	return $parts;
}
add_filter( 'document_title_parts', 'axismundi_actors_document_title_parts' );
