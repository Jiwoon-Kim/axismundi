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
		'^@([^/]+)/(followers|following)/?$' => 'index.php?ax_actor_handle=$matches[1]&ax_actor_collection=$matches[2]',
		'^actors/([0-9a-fA-F-]{36})/(followers|following)/?$' => 'index.php?ax_actor=$matches[1]&ax_actor_collection=$matches[2]',
		'^actors/([0-9a-fA-F-]{36})/?$'                       => 'index.php?ax_actor=$matches[1]',
		// Keep the tolerated trailing-slash alias explicit. Some hosted rewrite tables
		// retain the older slashless-only rule across an upgrade, which lets `/@name/`
		// fall through to the home query instead of reaching our 301 normalizer.
		'^@([^/]+)/$'                   => 'index.php?ax_actor_handle=$matches[1]',
		'^@([^/]+)$'                    => 'index.php?ax_actor_handle=$matches[1]',
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
	$vars[] = 'ax_actor_collection';
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
	// A managed actor (Group/Service/Organization) has no owning WP user; its
	// preview/manage authority is the manager relation, not user identity.
	if ( $actor->is_managed() && function_exists( 'axismundi_actors_managed_actor_can_manage' ) ) {
		return axismundi_actors_managed_actor_can_manage( $actor->get_identity_id(), $user_id );
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
	$handle = rawurldecode( $handle );
	if ( '' === $handle ) {
		return null;
	}
	return str_contains( $handle, '@' )
		? axismundi_actors_get_by_remote_acct( $handle )
		: axismundi_actors_get_by_handle( $handle );
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
	$acct = function_exists( 'axismundi_actors_primary_acct_address' ) ? axismundi_actors_primary_acct_address( $actor ) : '';
	if ( '' !== $acct ) {
		// Keep the acct separator readable while safely encoding any other path bytes.
		return home_url( '/@' . str_replace( '%40', '@', rawurlencode( $acct ) ) );
	}
	// Slashless, matching both the published identity URI and the `@handle` hub. Minting
	// the trailing-slash variant here would hand out a link that only redirects.
	return get_option( 'permalink_structure' )
		? home_url( '/actors/' . rawurlencode( $actor->get_uuid() ) )
		: add_query_arg( 'ax_actor', $actor->get_uuid(), home_url( '/' ) );
}

/** Human-facing Followers or Following list URL. */
function axismundi_actors_follow_collection_url( Axismundi_Actor $actor, string $collection, int $page = 1 ) : string {
	$collection = in_array( $collection, array( 'followers', 'following' ), true ) ? $collection : 'followers';
	$url        = trailingslashit( axismundi_actors_profile_hub_url( $actor ) ) . $collection;
	return $page > 1 ? add_query_arg( 'page', $page, $url ) : $url;
}

/**
 * Where to send a human who wants a remote Actor's Follow lists.
 *
 * Deliberately the profile page and not a deep link. The obvious-looking address --
 * the `followers`/`following` URI the remote Actor publishes -- is an ActivityPub
 * endpoint, and only Mastodon content-negotiates it back to a readable page; Misskey
 * and the WordPress ActivityPub plugin both answer `text/html` with raw
 * `application/activity+json`. Guessing `{profile}/followers` instead is worse than
 * useless: that plugin serves its collections from `/wp-api/activitypub/1.0/actors/N/`,
 * so the guess resolves to an unrelated page on the same site and the visitor never
 * learns they were sent somewhere wrong.
 *
 * The profile page is the one address every implementation renders for people, and
 * every one of them links its own lists from there.
 *
 * Note this is `get_profile_url()` and not `axismundi_actors_profile_hub_url()`: the hub
 * is our own `/@user@host` page for a cached remote Actor, so linking to it would send a
 * reader back to the page they are already standing on.
 *
 * @param Axismundi_Actor $actor Remote Actor.
 * @return string Empty for a local Actor or one with no known profile page.
 */
function axismundi_actors_remote_follow_collection_url( Axismundi_Actor $actor ) : string {
	return $actor->is_local() ? '' : $actor->get_profile_url();
}

/**
 * Disclosure policy for one local Actor's Follow collections.
 *
 * Three levels, because "how many" and "who" are separate disclosures:
 *   public     - counts and lists.
 *   count-only - counts, no lists. `totalItems` without `first`.
 *   private    - neither. `totalItems` is omitted rather than sent as 0, because a
 *                zeroed count reads as an empty account instead of a withheld one.
 *
 * A null column means the policy was never set and reads as `public`, which is what
 * makes disclosure the default without a migration. `followers` is a legacy value from
 * before this policy existed and maps to `count-only`.
 *
 * Only local Actors have a policy here. A remote Actor's disclosure is decided by its
 * own server, so this returns `private` for one -- not as a judgement, but because we
 * have no authority to publish anything about it under our own name.
 *
 * @param Axismundi_Actor $actor Actor.
 * @return string public|count-only|private
 */
function axismundi_actors_follow_collections_policy( Axismundi_Actor $actor ) : string {
	if ( ! $actor->is_local() || ! axismundi_actors_is_public_profile( $actor ) ) {
		return 'private';
	}
	$value = $actor->get_follow_collections_visibility();
	if ( null === $value || 'public' === $value ) {
		return 'public';
	}
	return in_array( $value, array( 'count-only', 'followers' ), true ) ? 'count-only' : 'private';
}

/** Whether the member Actors themselves may be disclosed. */
function axismundi_actors_follow_collections_are_public( Axismundi_Actor $actor ) : bool {
	return 'public' === axismundi_actors_follow_collections_policy( $actor );
}

/** Whether the size of the collections may be disclosed, without their members. */
function axismundi_actors_follow_counts_are_public( Axismundi_Actor $actor ) : bool {
	return 'private' !== axismundi_actors_follow_collections_policy( $actor );
}

/**
 * Whether we will render a human Follow list page for this Actor.
 *
 * The two cases answer different questions. For a LOCAL Actor the page publishes our
 * own collection under our own name, so it obeys that Actor's disclosure policy.
 *
 * For a REMOTE Actor the page publishes nothing of theirs: every row is an edge this
 * server observed while federating, which is our own record of our own traffic. That is
 * the same thing Mastodon and Misskey show on a remote profile -- Misskey lists 4 of
 * pfefferle's 4,870 followers -- and the honesty problem there is not that they show
 * their fragment, it is that they present it as the whole. We show the remote's own
 * published total beside it, so the fragment is never mistaken for the collection.
 *
 * @param Axismundi_Actor $actor Actor.
 * @return bool
 */
function axismundi_actors_follow_collection_page_is_available( Axismundi_Actor $actor ) : bool {
	return $actor->is_local()
		? axismundi_actors_follow_collections_are_public( $actor )
		: axismundi_actors_is_public_profile( $actor );
}

/**
 * Keep `@handle` and `@handle@domain` hubs slashless without changing Actor URIs.
 *
 * WordPress normally appends a trailing slash to a pretty permalink. The profile
 * alias is a compact human address, so it deliberately opts out while its
 * immutable ActivityPub identity stays at `/actors/{uuid}`. The rewrite rule
 * accepts both forms, and the explicit redirect below consolidates old links.
 *
 * @return Axismundi_Actor|null Actor addressed through a local or cached remote alias.
 */
function axismundi_actors_current_handle_alias_actor() : ?Axismundi_Actor {
	$actor = axismundi_actors_current_actor();
	return '' !== (string) get_query_var( 'ax_actor_handle' ) && $actor instanceof Axismundi_Actor
		? $actor
		: null;
}

/**
 * Resolve the routed handle alias before the front-end request handler has set globals.
 *
 * `redirect_canonical` runs before `pre_handle_404`, where the normal request path sets
 * `axismundi_actors_current_actor`. A parse-request fallback can therefore have the
 * correct query var while the global is still null. Core must see the routed alias at
 * this earlier point or it appends a slash, which then bounces off our slashless
 * normaliser.
 *
 * @return Axismundi_Actor|null Viewable Actor addressed through a handle alias.
 */
function axismundi_actors_routed_handle_alias_actor() : ?Axismundi_Actor {
	$handle = (string) get_query_var( 'ax_actor_handle' );
	if ( '' === $handle ) {
		return null;
	}
	$actor = axismundi_actors_resolve_request_actor( '', $handle );
	return $actor instanceof Axismundi_Actor && axismundi_actors_can_view( $actor ) ? $actor : null;
}

/**
 * Prevent Core from redirecting the slashless local handle alias to `/@handle/`.
 *
 * @param string|false $redirect_url Core redirect target.
 * @return string|false
 */
function axismundi_actors_handle_alias_canonical_redirect( $redirect_url ) {
	return axismundi_actors_routed_handle_alias_actor() instanceof Axismundi_Actor ? false : $redirect_url;
}
add_filter( 'redirect_canonical', 'axismundi_actors_handle_alias_canonical_redirect' );

/**
 * The local Actor identity route, when this request is one.
 *
 * @return Axismundi_Actor|null Local Actor addressed by its own `/actors/{uuid}`.
 */
function axismundi_actors_current_identity_route_actor() : ?Axismundi_Actor {
	$actor = axismundi_actors_current_actor();
	return '' !== (string) get_query_var( 'ax_actor' ) && $actor instanceof Axismundi_Actor && $actor->is_local()
		? $actor
		: null;
}

/**
 * Serve the published identity URI itself, rather than redirecting away from it.
 *
 * `/actors/{uuid}` is the Actor's `id`: it is what the JSON publishes, what WebFinger
 * points `rel=self` at, and what remote servers store forever. WordPress would append a
 * trailing slash to the browser request, so the one address the whole network is told to
 * use answered 301 while a variant answered 200 — the reverse of the `@handle` rule
 * beside it. Suppressing that keeps a single slashless policy across both routes: one
 * canonical URL each, with the slashed form redirecting to it.
 *
 * Only the HTML view was ever affected; the ActivityStreams representation already
 * answered 200 on both forms, so federation behaviour is unchanged either way.
 *
 * @param string|false $redirect_url Core redirect target.
 * @return string|false
 */
function axismundi_actors_identity_canonical_redirect( $redirect_url ) {
	return axismundi_actors_current_identity_route_actor() instanceof Axismundi_Actor ? false : $redirect_url;
}
add_filter( 'redirect_canonical', 'axismundi_actors_identity_canonical_redirect' );

/** Redirect the tolerated `/actors/{uuid}/` form to the published identity URI. */
function axismundi_actors_redirect_identity_trailing_slash() : void {
	$actor = axismundi_actors_current_identity_route_actor();
	if ( ! $actor instanceof Axismundi_Actor ) {
		return;
	}
	$request_path  = wp_parse_url( (string) wp_unslash( $_SERVER['REQUEST_URI'] ?? '' ), PHP_URL_PATH );
	$identity_path = wp_parse_url( $actor->get_uri(), PHP_URL_PATH );
	if ( ! is_string( $request_path ) || ! is_string( $identity_path ) || '/' !== substr( $request_path, -1 )
		|| untrailingslashit( rawurldecode( $request_path ) ) !== $identity_path
	) {
		return;
	}
	wp_safe_redirect( $actor->get_uri(), 301 );
	exit;
}
add_action( 'template_redirect', 'axismundi_actors_redirect_identity_trailing_slash', 1 );

/** Redirect the tolerated trailing-slash alias to the compact canonical hub. */
function axismundi_actors_redirect_handle_alias_trailing_slash() : void {
	$actor = axismundi_actors_current_handle_alias_actor();
	if ( ! $actor instanceof Axismundi_Actor ) {
		return;
	}
	$request_path = wp_parse_url( (string) wp_unslash( $_SERVER['REQUEST_URI'] ?? '' ), PHP_URL_PATH );
	$profile_url  = axismundi_actors_profile_hub_url( $actor );
	$profile_path = wp_parse_url( $profile_url, PHP_URL_PATH );
	if ( ! is_string( $request_path ) || ! is_string( $profile_path ) || '/' !== substr( $request_path, -1 ) || untrailingslashit( rawurldecode( $request_path ) ) !== $profile_path ) {
		return;
	}
	wp_safe_redirect( $profile_url, 301 );
	exit;
}
add_action( 'template_redirect', 'axismundi_actors_redirect_handle_alias_trailing_slash', 1 );

/**
 * Route an Actor address the stored rewrite table failed to match.
 *
 * Some hosted installs end up without these rules even though the plugin registered
 * them, and the request then falls through to the front-page query. Redirecting from
 * there cannot work and actively makes things worse: an earlier release answered
 * `/@handle/` with a 301 to `/@handle`, which was itself unrouted, so Core's
 * `redirect_canonical` put the slash back and the two bounced forever. Production
 * served fifty redirects before the browser gave up.
 *
 * The request does reach PHP — the front page proves it — so the fix is to finish the
 * routing here rather than to bounce the browser. Setting the query vars puts the
 * request on exactly the path the rewrite rule would have produced, which means the
 * canonical-redirect guard and the trailing-slash normaliser downstream both behave
 * normally instead of fighting each other.
 *
 * This is a fallback and stays deliberately narrow: it claims a path only when it names
 * an Actor that already exists and is viewable, so an ordinary page called `/@notes/`
 * is untouched.
 *
 * @param WP $wp Request object.
 * @return void
 */
function axismundi_actors_resolve_unrouted_actor_request( WP $wp ) : void {
	if ( ! empty( $wp->query_vars['ax_actor_handle'] ) || ! empty( $wp->query_vars['ax_actor'] ) ) {
		return; // The rewrite table did its job.
	}
	$path = wp_parse_url( (string) wp_unslash( $_SERVER['REQUEST_URI'] ?? '' ), PHP_URL_PATH );
	if ( ! is_string( $path ) || '' === $path ) {
		return;
	}
	$path = rawurldecode( $path );

	$collection = '';
	$handle     = '';
	$uuid       = '';
	if ( 1 === preg_match( '#^/@([^/]+?)(?:/(followers|following))?/?$#', $path, $matches ) ) {
		$handle     = $matches[1];
		$collection = $matches[2] ?? '';
	} elseif ( 1 === preg_match( '#^/actors/([0-9a-fA-F-]{36})(?:/(followers|following))?/?$#', $path, $matches ) ) {
		$uuid       = $matches[1];
		$collection = $matches[2] ?? '';
	} else {
		return;
	}

	$actor = axismundi_actors_resolve_request_actor( $uuid, $handle );
	if ( ! $actor instanceof Axismundi_Actor || ! axismundi_actors_can_view( $actor ) ) {
		return;
	}

	if ( '' !== $uuid ) {
		$wp->query_vars['ax_actor'] = $uuid;
	} else {
		$wp->query_vars['ax_actor_handle'] = $handle;
	}
	if ( '' !== $collection ) {
		$wp->query_vars['ax_actor_collection'] = $collection;
	}
}
add_action( 'parse_request', 'axismundi_actors_resolve_unrouted_actor_request' );

/** Keep human Follow collection pages slashless like their profile hub. */
function axismundi_actors_redirect_follow_collection_trailing_slash() : void {
	$actor      = axismundi_actors_current_handle_alias_actor();
	$collection = (string) get_query_var( 'ax_actor_collection' );
	if ( ! $actor instanceof Axismundi_Actor || ! in_array( $collection, array( 'followers', 'following' ), true ) || ! isset( $_SERVER['REQUEST_URI'] ) ) {
		return;
	}
	$request_path = wp_parse_url( (string) wp_unslash( $_SERVER['REQUEST_URI'] ), PHP_URL_PATH );
	$target_path  = wp_parse_url( axismundi_actors_follow_collection_url( $actor, $collection ), PHP_URL_PATH );
	if ( is_string( $request_path ) && is_string( $target_path ) && '/' === substr( $request_path, -1 ) && untrailingslashit( rawurldecode( $request_path ) ) === $target_path ) {
		wp_safe_redirect( axismundi_actors_follow_collection_url( $actor, $collection ), 301 );
		exit;
	}
}
add_action( 'template_redirect', 'axismundi_actors_redirect_follow_collection_trailing_slash', 2 );

/** Redirect cached remote UUID profile hubs to their verified local acct alias. */
function axismundi_actors_redirect_remote_uuid_profile_alias() : void {
	$actor = axismundi_actors_current_actor();
	if ( ! $actor instanceof Axismundi_Actor || $actor->is_local() || '' === (string) get_query_var( 'ax_actor' ) ) {
		return;
	}
	$alias = axismundi_actors_profile_hub_url( $actor );
	if ( false === strpos( $alias, '/@' ) ) {
		return;
	}
	$collection = (string) get_query_var( 'ax_actor_collection' );
	$target     = in_array( $collection, array( 'followers', 'following' ), true )
		? axismundi_actors_follow_collection_url( $actor, $collection )
		: $alias;
	wp_safe_redirect( $target, 301 );
	exit;
}
add_action( 'template_redirect', 'axismundi_actors_redirect_remote_uuid_profile_alias', 1 );

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
 * A nested product can replace the route or explicit Account Header subject
 * through the filter. This lets an Object Card on an Actor timeline display the
 * Object's attributed author rather than the profile owner, while the profile
 * header itself keeps using the routed Actor when no product context is active.
 *
 * @return array<string,mixed>|null
 */
function axismundi_actors_resolve_block_subject( string $context_actor_id ) : ?array {
	$actor = axismundi_actors_resolve_block_actor( $context_actor_id );
	$subject = $actor instanceof Axismundi_Actor ? axismundi_actors_block_subject_from_actor( $actor ) : null;
	/** @param array<string,mixed>|null $subject Normalized external Actor descriptor. */
	$subject = apply_filters( 'axismundi_actors_block_subject', $subject, $context_actor_id );
	if ( ! is_array( $subject ) ) {
		return null;
	}
	/*
	 * Carry the Actor through when it survived the filter, because an Actor can be
	 * rendered better than a descriptor can: `axismundi_actors_avatar_html()` picks the
	 * closest registered image size and emits a `srcset`, while a descriptor only ever
	 * carries one already-chosen URL. Dropping it here unconditionally is what made
	 * every Actor Avatar fall back to that single URL -- minted at a fixed 192px by
	 * `axismundi_actors_block_subject_from_actor()`, so a 48px slot downloaded the
	 * full-size original with no `srcset` at all.
	 *
	 * The instanceof check is the whole safety story: a product that replaces the
	 * subject through the filter returns its own array, which carries no `actor` key,
	 * so its descriptor stays authoritative and no stale identity leaks past it.
	 */
	$normalized = array(
		'actor'              => ( $subject['actor'] ?? null ) instanceof Axismundi_Actor ? $subject['actor'] : null,
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
	$collection = (string) $query->get( 'ax_actor_collection' );
	if ( ! $actor || ! axismundi_actors_can_view( $actor ) || ( '' !== $collection && ! axismundi_actors_follow_collection_page_is_available( $actor ) ) ) {
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

/** @return string */
function axismundi_actors_follow_collection_template_content() : string {
	$path = __DIR__ . '/../templates/actor-follow-collection.php';
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
	register_block_type( __DIR__ . '/../blocks/actor-social-counts' );
	register_block_type( __DIR__ . '/../blocks/actor-follow-list' );
	if ( function_exists( 'register_block_template' ) ) {
		register_block_template(
			'axismundi-actors//actor-profile',
			array(
				'title'       => __( 'Actor Profile', 'axismundi-actors' ),
				'description' => __( 'A public or owner-preview actor identity hub.', 'axismundi-actors' ),
				'content'     => axismundi_actors_profile_template_content(),
			)
		);
		register_block_template(
			'axismundi-actors//actor-follow-collection',
			array(
				'title'       => __( 'Actor Follow Collection', 'axismundi-actors' ),
				'description' => __( 'A public Followers or Following list for the current Actor.', 'axismundi-actors' ),
				'content'     => axismundi_actors_follow_collection_template_content(),
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
	$is_collection = '' !== (string) get_query_var( 'ax_actor_collection' );
	$slug = $is_collection ? 'actor-follow-collection' : 'actor-profile';
	$templates = array( $slug . '.php', 'index.php' );
	return locate_block_template( locate_template( $templates ), $slug, $templates );
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
		$collection = (string) get_query_var( 'ax_actor_collection' );
		$url = '' !== $collection ? axismundi_actors_follow_collection_url( $actor, $collection, max( 1, absint( get_query_var( 'page' ) ) ) ) : $actor->get_uri();
		printf( '<link rel="canonical" href="%s" />', esc_url( $url ) );
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
		$collection = (string) get_query_var( 'ax_actor_collection' );
		$parts['title'] = '' !== $collection ? sprintf( '%s - %s', $actor->get_display_name(), ucfirst( $collection ) ) : $actor->get_display_name();
	}
	return $parts;
}
add_filter( 'document_title_parts', 'axismundi_actors_document_title_parts' );
