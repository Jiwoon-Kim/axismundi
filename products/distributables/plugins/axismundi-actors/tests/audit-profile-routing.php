<?php
/**
 * Phase 2 actor profile routing regression (dev-only; dist-excluded).
 *
 * @package AxismundiActors
 */

defined( 'ABSPATH' ) || exit( 1 );

require_once dirname( __DIR__ ) . '/includes/repository.php';
require_once dirname( __DIR__ ) . '/includes/routing.php';
require_once ABSPATH . 'wp-admin/includes/user.php';

global $wpdb;
$ax_profile_results = array();
$ax_profile_ids     = array();
$ax_profile_users   = array();
$ax_old_permalink  = (string) get_option( 'permalink_structure', '' );

/**
 * @param array  $results Accumulator.
 * @param string $label Contract.
 * @param bool   $condition Holds.
 * @return void
 */
function ax_profile_assert( array &$results, string $label, bool $condition ) : void {
	$results[] = $condition;
	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI test output.
	printf( "[%s] %s\n", $condition ? 'PASS' : 'FAIL', $label );
}

try {
	axismundi_actors_install();
	$user_id = (int) wp_insert_user(
		array(
			'user_login'   => 'ax_profile_alice',
			'user_pass'    => wp_generate_password(),
			'user_email'   => 'alice-private@example.test',
			'display_name' => 'Alice Profile',
			'user_url'     => 'https://alice.example/',
			'description'  => 'A live profile summary.',
			'role'         => 'author',
		)
	);
	$ax_profile_users[] = $user_id;
	$actor = axismundi_actors_ensure_for_user( $user_id );
	if ( $actor instanceof Axismundi_Actor ) {
		$ax_profile_ids[] = $actor->get_identity_id();
	}

	ax_profile_assert( $ax_profile_results, 'fixture creates one internal local Person', $actor instanceof Axismundi_Actor && 'internal' === $actor->get_status() );
	ax_profile_assert( $ax_profile_results, 'anonymous viewers cannot see an internal actor', $actor instanceof Axismundi_Actor && ! axismundi_actors_can_view( $actor, 0 ) );
	ax_profile_assert( $ax_profile_results, 'the linked user can preview their internal actor', $actor instanceof Axismundi_Actor && axismundi_actors_can_preview( $actor, $user_id ) );

	$admin_id = (int) wp_insert_user(
		array(
			'user_login' => 'ax_profile_admin',
			'user_pass'  => wp_generate_password(),
			'role'       => 'administrator',
		)
	);
	$ax_profile_users[] = $admin_id;
	ax_profile_assert( $ax_profile_results, 'manage_options can preview another internal actor', $actor instanceof Axismundi_Actor && axismundi_actors_can_preview( $actor, $admin_id ) );

	$live = axismundi_actors_profile_data( $actor );
	ax_profile_assert( $ax_profile_results, 'local display name, bio, and website are read live from WP_User', 'Alice Profile' === $live['name'] && 'A live profile summary.' === $live['summary'] && 'https://alice.example/' === $live['url'] );

	$original_uuid = $actor->get_uuid();
	$original_uri  = $actor->get_uri();

	// A user's actor is handle-less until they activate; the handle is registered once.
	ax_profile_assert( $ax_profile_results, 'a freshly ensured Person is handle-less until activation', '' === $actor->get_preferred_username() && '' === $actor->get_profile_url() );
	$registered = axismundi_actors_register_handle( $actor->get_identity_id(), 'alice_profile' );
	$actor      = axismundi_actors_get_by_uuid( $original_uuid );
	ax_profile_assert( $ax_profile_results, 'activation registers and locks the handle', true === $registered && $actor instanceof Axismundi_Actor && 'alice_profile' === $actor->get_preferred_username() && $actor->is_handle_locked() );

	$by_handle = $actor instanceof Axismundi_Actor ? axismundi_actors_get_by_handle( $actor->get_preferred_username() ) : null;
	$by_uuid   = axismundi_actors_resolve_request_actor( $original_uuid, '' );
	ax_profile_assert( $ax_profile_results, 'local handle resolves through local_handle_key', $actor instanceof Axismundi_Actor && $by_handle instanceof Axismundi_Actor && $actor->get_identity_id() === $by_handle->get_identity_id() );
	ax_profile_assert( $ax_profile_results, 'UUID route resolves the same local actor', $actor instanceof Axismundi_Actor && $by_uuid instanceof Axismundi_Actor && $actor->get_identity_id() === $by_uuid->get_identity_id() );
	ax_profile_assert( $ax_profile_results, 'malformed UUIDs do not resolve', null === axismundi_actors_resolve_request_actor( 'not-a-uuid', '' ) );

	update_option( 'permalink_structure', '' );
	ax_profile_assert( $ax_profile_results, 'plain profile fallback works without pretty permalinks', false !== strpos( $actor->get_profile_url(), 'ax_actor_handle=' ) );
	update_option( 'permalink_structure', '/%postname%/' );
	ax_profile_assert( $ax_profile_results, 'pretty profile alias uses the mutable handle without a trailing slash', home_url( '/@' . rawurlencode( $actor->get_preferred_username() ) ) === $actor->get_profile_url() );

	// The handle is immutable once registered: re-registration is refused and the alias holds.
	$old_handle = $actor->get_preferred_username();
	$again      = axismundi_actors_register_handle( $actor->get_identity_id(), 'alice_profile_moved' );
	$after      = axismundi_actors_get_by_uuid( $original_uuid );
	ax_profile_assert(
		$ax_profile_results,
		'a registered handle is immutable; UUID, URI, and alias stay stable',
		is_wp_error( $again ) && $after instanceof Axismundi_Actor && $after->get_uri() === $original_uri && $after->get_preferred_username() === $old_handle && null === axismundi_actors_get_by_handle( 'alice_profile_moved' )
	);

	axismundi_actors_set_status( $actor->get_identity_id(), 'public' );
	$public_actor = axismundi_actors_get_by_uuid( $original_uuid );
	ax_profile_assert( $ax_profile_results, 'a public actor with a registered handle is visible anonymously', $public_actor instanceof Axismundi_Actor && axismundi_actors_can_view( $public_actor, 0 ) );
	ax_profile_assert(
		$ax_profile_results,
		'a legacy WordPress author archive redirects a public Person to the Actor profile hub',
		$public_actor instanceof Axismundi_Actor && axismundi_actors_profile_hub_url( $public_actor ) === axismundi_actors_legacy_author_profile_redirect_url( $user_id )
	);
	ax_profile_assert(
		$ax_profile_results,
		'a legacy author archive does not claim an internal Person',
		'' === axismundi_actors_legacy_author_profile_redirect_url( $admin_id )
	);
	ax_profile_assert(
		$ax_profile_results,
		'a public Person profile disables shared caching because its feed can vary by viewer',
		$public_actor instanceof Axismundi_Actor && axismundi_actors_profile_requires_nocache( $public_actor )
	);
	ax_profile_assert(
		$ax_profile_results,
		'Person and Group profile templates expose separate feed surfaces while retaining the legacy Person template',
		false !== strpos( axismundi_actors_profile_template_content(), 'ax-person-profile__timeline' )
			&& false !== strpos( axismundi_actors_profile_template_content( 'actor-person-profile' ), 'ax-person-profile__timeline' )
			&& false !== strpos( axismundi_actors_profile_template_content( 'actor-group-profile' ), 'ax-group-profile__community' )
	);
	ax_profile_assert(
		$ax_profile_results,
		'a Person route selects the Person profile template rather than the community surface',
		$public_actor instanceof Axismundi_Actor && 'actor-person-profile' === axismundi_actors_profile_template_slug( $public_actor )
	);
	/*
	 * The fallback for a rewrite table that lost these rules *routes*; it must never
	 * redirect. An earlier release answered `/@handle/` with a 301 to `/@handle`, which
	 * on such an install is equally unrouted, so Core's `redirect_canonical` restored the
	 * slash and the two bounced until the browser gave up — fifty hops in production.
	 * Setting the query vars instead puts the request exactly where the rewrite rule
	 * would have, so the canonical guard and the trailing-slash normaliser downstream
	 * cooperate rather than fight.
	 */
	$ax_profile_route = static function ( string $path ) : array {
		$_SERVER['REQUEST_URI'] = $path;
		$wp                     = new WP();
		$wp->query_vars         = array();
		axismundi_actors_resolve_unrouted_actor_request( $wp );
		return $wp->query_vars;
	};
	/*
	 * A Group to route through the other namespace. The fallback resolves the kind from the
	 * path because the query var that carries it is set by the rewrite rule this function
	 * stands in for, so on an install missing those rules the var is never there to read.
	 */
	$ax_profile_group = axismundi_actors_create_managed_group(
		array(
			'owner_user_id'      => $admin_id,
			'preferred_username' => 'ax_profile_forum',
			'status'             => 'public',
		)
	);
	if ( $ax_profile_group instanceof Axismundi_Actor ) {
		$ax_profile_ids[] = $ax_profile_group->get_identity_id();
		axismundi_actors_register_handle( $ax_profile_group->get_identity_id(), 'ax_profile_forum' );
		axismundi_actors_set_status( $ax_profile_group->get_identity_id(), 'public' );
	}

	$ax_profile_slashed   = $ax_profile_route( '/@alice_profile/' );
	$ax_profile_slashless = $ax_profile_route( '/@alice_profile' );
	$ax_profile_stranger  = $ax_profile_route( '/@not_an_actor/' );
	$ax_profile_following = $ax_profile_route( '/@alice_profile/following' );

	ax_profile_assert(
		$ax_profile_results,
		'an unrouted alias is resolved into the same query vars the rewrite rule would have set',
		'alice_profile' === ( $ax_profile_slashed['ax_actor_handle'] ?? '' )
			&& 'alice_profile' === ( $ax_profile_slashless['ax_actor_handle'] ?? '' )
	);
	ax_profile_assert(
		$ax_profile_results,
		'both slash forms resolve, so neither can bounce off the other into a redirect loop',
		( $ax_profile_slashed['ax_actor_handle'] ?? '' ) === ( $ax_profile_slashless['ax_actor_handle'] ?? '' )
	);
	ax_profile_assert(
		$ax_profile_results,
		'an unrouted collection address keeps its collection',
		'following' === ( $ax_profile_following['ax_actor_collection'] ?? '' )
	);
	ax_profile_assert(
		$ax_profile_results,
		'the fallback claims nothing that does not name an existing viewable Actor',
		array() === $ax_profile_stranger
	);
	/*
	 * The two namespaces are the whole reason the resolver takes a kind: a handle alone does
	 * not identify an Actor, so `/group/@x` and `/@x` must not answer with each other. The
	 * fallback has to reproduce that itself, including recording the kind it resolved on —
	 * the template selector and the 404 guard downstream read it back, so resolving a Group
	 * without setting the var routes it onto the Person profile surface.
	 */
	$ax_profile_group_route  = $ax_profile_route( '/group/@ax_profile_forum' );
	$ax_profile_group_person = $ax_profile_route( '/@ax_profile_forum' );
	$ax_profile_person_group = $ax_profile_route( '/group/@alice_profile' );
	ax_profile_assert(
		$ax_profile_results,
		'an unrouted Group address resolves on the Group namespace and records the kind the rewrite rule would have set',
		$ax_profile_group instanceof Axismundi_Actor
			&& 'ax_profile_forum' === ( $ax_profile_group_route['ax_actor_handle'] ?? '' )
			&& 'Group' === ( $ax_profile_group_route['ax_actor_kind'] ?? '' )
	);
	ax_profile_assert(
		$ax_profile_results,
		'neither namespace answers for the other kind, so one handle never means two addresses',
		array() === $ax_profile_group_person && array() === $ax_profile_person_group
	);
	ax_profile_assert(
		$ax_profile_results,
		'the Person namespace does not claim a kind, leaving the default in one place',
		! isset( $ax_profile_slashed['ax_actor_kind'] )
	);
	$ax_previous_current_actor = $GLOBALS['axismundi_actors_profile_actor'];
	$ax_previous_query         = $GLOBALS['wp_query'] ?? null;
	$GLOBALS['axismundi_actors_profile_actor'] = null;
	$GLOBALS['wp_query'] = new WP_Query();
	$GLOBALS['wp_query']->set( 'ax_actor_handle', 'alice_profile' );
	ax_profile_assert(
		$ax_profile_results,
		'the canonical guard resolves a fallback-routed alias before pre_handle_404 sets the current Actor',
		false === axismundi_actors_handle_alias_canonical_redirect( 'https://example.test/@alice_profile/' )
	);
	$GLOBALS['axismundi_actors_profile_actor'] = $ax_previous_current_actor;
	$GLOBALS['wp_query'] = $ax_previous_query;

	// A public status without a registered handle stays hidden from the public.
	$nohandle = axismundi_actors_ensure_for_user( $admin_id );
	if ( $nohandle instanceof Axismundi_Actor ) {
		$ax_profile_ids[] = $nohandle->get_identity_id();
		axismundi_actors_set_status( $nohandle->get_identity_id(), 'public' );
		$nohandle = axismundi_actors_get_by_uuid( $nohandle->get_uuid() );
	}
	ax_profile_assert( $ax_profile_results, 'public status without a registered handle is not publicly viewable', $nohandle instanceof Axismundi_Actor && ! axismundi_actors_is_public_profile( $nohandle ) && ! axismundi_actors_can_view( $nohandle, 0 ) );

	$GLOBALS['axismundi_actors_profile_actor'] = $public_actor;
	$rendered = render_block( array( 'blockName' => 'axismundi/actor-profile', 'attrs' => array(), 'innerBlocks' => array(), 'innerHTML' => '', 'innerContent' => array() ) );
	ax_profile_assert( $ax_profile_results, 'profile block renders a banner, overlapping profile head, and identity data without exposing email', false !== strpos( $rendered, 'ax-actor-profile__cover' ) && false !== strpos( $rendered, 'ax-actor-profile__head' ) && false !== strpos( $rendered, 'ax-actor-profile__avatar-frame' ) && false !== strpos( $rendered, 'Alice Profile' ) && false !== strpos( $rendered, 'A live profile summary.' ) && false === strpos( $rendered, 'alice-private@example.test' ) );
	$title_parts = axismundi_actors_document_title_parts( array( 'title' => 'Fallback' ) );
	ax_profile_assert( $ax_profile_results, 'document title uses the resolved actor display name', 'Alice Profile' === $title_parts['title'] );

	ob_start();
	axismundi_actors_print_canonical();
	$canonical = (string) ob_get_clean();
	ax_profile_assert( $ax_profile_results, 'canonical link always uses the UUID identity URI', false !== strpos( $canonical, esc_url( $original_uri ) ) && false === strpos( $canonical, '/@' ) );

	$rules = axismundi_actors_rewrite_rules();
	ax_profile_assert( $ax_profile_results, 'canonical, follow-collection, and both slash forms of the human alias rewrite rules are all registered', isset( $rules['^actors/([0-9a-fA-F-]{36})/?$'], $rules['^actors/([0-9a-fA-F-]{36})/(followers|following)/?$'], $rules['^@([^/]+)$'], $rules['^@([^/]+)/$'], $rules['^@([^/]+)/(followers|following)/?$'] ) );
	$ax_previous_current_actor = $GLOBALS['axismundi_actors_profile_actor'];
	$ax_previous_query        = $GLOBALS['wp_query'] ?? null;
	$GLOBALS['axismundi_actors_profile_actor'] = $public_actor;
	$GLOBALS['wp_query'] = new WP_Query();
	$GLOBALS['wp_query']->set( 'ax_actor_handle', $public_actor instanceof Axismundi_Actor ? $public_actor->get_preferred_username() : '' );
	ax_profile_assert( $ax_profile_results, 'the local handle alias opts out of Core trailing-slash canonicalization', false === axismundi_actors_handle_alias_canonical_redirect( 'https://example.test/@alice_profile/' ) );
	$GLOBALS['axismundi_actors_profile_actor'] = $ax_previous_current_actor;
	$GLOBALS['wp_query'] = $ax_previous_query;
} finally {
	$GLOBALS['axismundi_actors_profile_actor'] = null;
	update_option( 'permalink_structure', $ax_old_permalink );
	foreach ( array_unique( $ax_profile_ids ) as $identity_id ) {
		$wpdb->delete( axismundi_actors_addresses_table(), array( 'identity_id' => (int) $identity_id ), array( '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fixture cleanup.
		$wpdb->delete( axismundi_actors_actors_table(), array( 'identity_id' => (int) $identity_id ), array( '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fixture cleanup.
		$wpdb->delete( axismundi_actors_identities_table(), array( 'id' => (int) $identity_id ), array( '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fixture cleanup.
	}
	foreach ( $ax_profile_users as $fixture_user_id ) {
		if ( get_userdata( $fixture_user_id ) ) {
			wp_delete_user( $fixture_user_id );
		}
	}
}

/*
 * One slash policy across both Actor routes.
 *
 * An Actor has exactly two addresses — the identity URI it publishes and the human hub —
 * and each should have exactly one canonical form. Before this, `@handle` was slashless
 * while `/actors/{uuid}` answered the browser with a redirect to a slashed variant, which
 * meant the very URL the JSON publishes as `id`, and WebFinger points `rel=self` at, was
 * not the one that served the page.
 */
$ax_profile_results[] = ( static function () : bool {
	$actor = axismundi_actors_get_by_handle( 'admin2' );
	if ( ! $actor instanceof Axismundi_Actor ) {
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI test output.
		printf( "[FAIL] a slash policy fixture Actor exists\n" );
		return false;
	}
	$identity = $actor->get_uri();
	$hub      = axismundi_actors_profile_hub_url( $actor );

	// Both opt-outs read the routed request, so the route has to be simulated the way
	// the handle-alias check above does; without it they simply see no Actor.
	$previous_actor = $GLOBALS['axismundi_actors_profile_actor'] ?? null;
	$previous_query = $GLOBALS['wp_query'] ?? null;
	$GLOBALS['axismundi_actors_profile_actor'] = $actor;

	$GLOBALS['wp_query'] = new WP_Query();
	$GLOBALS['wp_query']->set( 'ax_actor', $actor->get_uuid() );
	$identity_opts_out = false === axismundi_actors_identity_canonical_redirect( $identity . '/' );

	$GLOBALS['wp_query'] = new WP_Query();
	$GLOBALS['wp_query']->set( 'ax_actor_handle', $actor->get_preferred_username() );
	$hub_opts_out = false === axismundi_actors_handle_alias_canonical_redirect( $hub . '/' );

	$GLOBALS['axismundi_actors_profile_actor'] = $previous_actor;
	$GLOBALS['wp_query'] = $previous_query;

	$pass = $identity === untrailingslashit( $identity )
		&& $hub === untrailingslashit( $hub )
		// The identity route opts out of Core's canonicalisation the same way the handle
		// alias does, so the published URI is the one that answers.
		&& $identity_opts_out
		&& $hub_opts_out;
	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI test output.
	printf( "[%s] both Actor addresses are slashless and neither is canonicalised away\n", $pass ? 'PASS' : 'FAIL' );
	return $pass;
} )();

$ax_profile_results[] = ( static function () : bool {
	// The hub fallback for a cached remote Actor with no verified acct must not mint the
	// slashed variant, or a link would be handed out that only ever redirects.
	$source = (string) file_get_contents( dirname( __DIR__ ) . '/includes/routing.php' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- local bundled source read.
	$pass   = false === strpos( $source, "'/actors/' . rawurlencode( \$actor->get_uuid() ) . '/'" );
	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI test output.
	printf( "[%s] the UUID hub fallback builds the canonical slashless form\n", $pass ? 'PASS' : 'FAIL' );
	return $pass;
} )();

$ax_profile_failures = count( array_filter( $ax_profile_results, static fn( bool $result ) : bool => ! $result ) );
// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI test output.
printf( "\n== %d checks, %d failed ==\n", count( $ax_profile_results ), $ax_profile_failures );

if ( class_exists( 'WP_CLI' ) ) {
	WP_CLI::halt( $ax_profile_failures > 0 ? 1 : 0 );
}
exit( $ax_profile_failures > 0 ? 1 : 0 );
