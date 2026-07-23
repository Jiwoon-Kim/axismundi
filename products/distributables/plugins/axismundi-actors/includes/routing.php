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
	if ( ! $actor->is_local() ) {
		return 'public' === $actor->get_status() && '' !== $actor->get_uri();
	}
	return 'public' === $actor->get_status()
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

/** Local human profile hub for a local or cached remote Actor. */
function axismundi_actors_profile_hub_url( Axismundi_Actor $actor ) : string {
	if ( $actor->is_local() ) {
		return $actor->get_profile_url();
	}
	return get_option( 'permalink_structure' )
		? home_url( '/actors/' . rawurlencode( $actor->get_uuid() ) . '/' )
		: add_query_arg( 'ax_actor', $actor->get_uuid(), home_url( '/' ) );
}

/** One display avatar URL suitable for neutral Object view models. */
function axismundi_actors_avatar_url( Axismundi_Actor $actor, int $size = 96 ) : string {
	$size = max( 24, min( 512, $size ) );
	if ( ! $actor->is_local() ) {
		return function_exists( 'axismundi_actors_get_cached_asset_url' )
			? axismundi_actors_get_cached_asset_url( $actor->get_identity_id(), 'avatar', $size )
			: '';
	}
	$attachment_id = $actor->get_avatar_attachment_id();
	if ( $attachment_id > 0 ) {
		return (string) wp_get_attachment_image_url( $attachment_id, array( $size, $size ) );
	}
	if ( 'site' === $actor->get_scope() ) {
		return (string) get_site_icon_url( $size );
	}
	$user_id = $actor->get_local_user_id();
	return $user_id ? (string) get_avatar_url( $user_id, array( 'size' => $size ) ) : '';
}

/**
 * Resolve the Actor a nested Actor block should render.
 *
 * `providesContext`/`usesContext` only carries an editor-time attribute; it
 * cannot know which Actor a route resolved. The current profile route is
 * therefore always authoritative when one exists. The explicit block context
 * (an `actorId` set on the enclosing Account Header) is a fallback for the
 * cases route context can never cover: an editor preview, or an Account
 * Header embedded in a document that is not the profile route itself.
 *
 * @param string $context_actor_id Explicit `axismundi/actorId` block context value.
 * @return Axismundi_Actor|null
 */
function axismundi_actors_resolve_block_actor( string $context_actor_id ) : ?Axismundi_Actor {
	$route_actor = axismundi_actors_current_actor();
	if ( $route_actor instanceof Axismundi_Actor ) {
		return $route_actor;
	}
	if ( 1 !== preg_match( '/^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', $context_actor_id ) ) {
		return null;
	}
	$actor = axismundi_actors_get_by_uuid( strtolower( $context_actor_id ) );
	return $actor instanceof Axismundi_Actor && axismundi_actors_can_view( $actor ) ? $actor : null;
}

/** Build the normalized presentation data for one canonical Actor. */
function axismundi_actors_block_subject_from_actor( Axismundi_Actor $actor ) : array {
	$data   = axismundi_actors_profile_data( $actor );
	$handle = function_exists( 'axismundi_actors_federated_mention_name' )
		? axismundi_actors_federated_mention_name( $actor )
		: '@' . $actor->get_preferred_username();
	return array(
		'actor'              => $actor,
		'name'               => (string) ( $data['name'] ?? $actor->get_display_name() ),
		'preferred_username' => $actor->get_preferred_username(),
		'handle'             => (string) $handle,
		'url'                => function_exists( 'axismundi_actors_profile_hub_url' ) ? axismundi_actors_profile_hub_url( $actor ) : $actor->get_profile_url(),
		'avatar_url'         => function_exists( 'axismundi_actors_avatar_url' ) ? axismundi_actors_avatar_url( $actor, 192 ) : '',
		'type'               => $actor->get_type(),
	);
}

/**
 * Resolve the Actor-shaped subject a nested identity block should display.
 *
 * Profile routes and an explicit Account Header actorId still win. Other
 * products may then supply a normalized author descriptor for a current Object
 * through the filter, keeping Avatar and Identity as Actors-owned blocks even
 * when an observed remote Object predates its cached Actor record.
 *
 * @return array<string,mixed>|null
 */
function axismundi_actors_resolve_block_subject( string $context_actor_id ) : ?array {
	$actor = axismundi_actors_resolve_block_actor( $context_actor_id );
	if ( $actor instanceof Axismundi_Actor ) {
		return axismundi_actors_block_subject_from_actor( $actor );
	}
	/** @param array<string,mixed>|null $subject Normalized external Actor descriptor. */
	$subject = apply_filters( 'axismundi_actors_block_subject', null, $context_actor_id );
	if ( ! is_array( $subject ) ) {
		return null;
	}
	$normalized = array(
		'actor'              => null,
		'name'               => sanitize_text_field( (string) ( $subject['name'] ?? '' ) ),
		'preferred_username' => sanitize_text_field( (string) ( $subject['preferred_username'] ?? '' ) ),
		'handle'             => sanitize_text_field( (string) ( $subject['handle'] ?? '' ) ),
		'url'                => esc_url_raw( (string) ( $subject['url'] ?? '' ) ),
		'avatar_url'         => esc_url_raw( (string) ( $subject['avatar_url'] ?? '' ) ),
		'type'               => sanitize_text_field( (string) ( $subject['type'] ?? '' ) ),
	);
	return '' === $normalized['name'] && '' === $normalized['preferred_username'] && '' === $normalized['handle'] && '' === $normalized['avatar_url'] ? null : $normalized;
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
 * Border class/style for the Actor Avatar block's inner image, read directly from
 * the block's own `style.border`/`borderColor` attributes.
 *
 * The block skips border support serialization on its own wrapper -- WordPress
 * draws the editor's selection outline sized to that wrapper, so a wrapper that is
 * itself round and clipped would clip the outline into a circle too -- so the
 * round border belongs on the inner `<img>` instead, applied here with the same
 * values `wp_apply_border_support()` reads.
 *
 * @param array<string,mixed> $attributes Block attributes.
 * @return array{class:string,style:string}
 */
function axismundi_actors_avatar_border_attributes( array $attributes ) : array {
	$border        = (array) ( $attributes['style']['border'] ?? array() );
	$block_styles  = array(
		'radius' => $border['radius'] ?? null,
		'style'  => $border['style'] ?? null,
		'width'  => $border['width'] ?? null,
		'color'  => array_key_exists( 'borderColor', $attributes )
			? 'var:preset|color|' . $attributes['borderColor']
			: ( $border['color'] ?? null ),
	);
	$styles        = wp_style_engine_get_styles( array( 'border' => $block_styles ) );
	return array(
		'class' => (string) ( $styles['classnames'] ?? '' ),
		'style' => (string) ( $styles['css'] ?? '' ),
	);
}

/**
 * Shadow style for the Actor Avatar block's inner image, for the same reason:
 * the wrapper support is skipped, so this reads `style.shadow` and applies it to
 * the image directly.
 *
 * @param array<string,mixed> $attributes Block attributes.
 * @return string
 */
function axismundi_actors_avatar_shadow_style( array $attributes ) : string {
	$styles = wp_style_engine_get_styles( array( 'shadow' => $attributes['style']['shadow'] ?? null ) );
	return (string) ( $styles['css'] ?? '' );
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
	register_block_type( __DIR__ . '/../blocks/account-header' );
	register_block_type( __DIR__ . '/../blocks/actor-avatar' );
	register_block_type( __DIR__ . '/../blocks/actor-identity' );
	// Name and handle are separate blocks so each can carry its own typography.
	// The composite above stays registered for simple profile layouts.
	register_block_type( __DIR__ . '/../blocks/actor-name' );
	register_block_type( __DIR__ . '/../blocks/actor-handle' );
	register_block_type( __DIR__ . '/../blocks/actor-biography' );
	register_block_type( __DIR__ . '/../blocks/actor-profile-fields' );
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

/** Keep private local Actor previews out of search indexes. */
function axismundi_actors_remote_preview_robots( array $robots ) : array {
	$actor = axismundi_actors_current_actor();
	if ( $actor && ! axismundi_actors_is_public_profile( $actor ) ) {
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
		printf( '<link rel="canonical" href="%s" />', esc_url( $actor->get_uri() ) );
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
