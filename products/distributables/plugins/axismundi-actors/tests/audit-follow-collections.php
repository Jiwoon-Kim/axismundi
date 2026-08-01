<?php
/**
 * Follow collection disclosure matrix (dev-only; dist-excluded).
 *
 * Pins the three-level policy across every surface it reaches, because the levels are
 * only meaningful if they agree with each other. The failure this guards against is not
 * a level behaving oddly on its own -- it is `private` hiding the numbers from a reader
 * while the ActivityPub root still publishes `totalItems` to anyone who asks, which is
 * exactly the state this feature shipped in before the matrix existed.
 *
 * `private` must omit the key rather than send 0: zero asserts an empty account, which
 * is a different claim from declining to answer.
 *
 * No network. Remote count caching is exercised through the repository writer, so the
 * assertions cover our storage contract and not another server's uptime.
 *
 * @package AxismundiActors
 */

defined( 'ABSPATH' ) || exit( 1 );

require_once dirname( __DIR__ ) . '/includes/repository.php';
require_once dirname( __DIR__ ) . '/includes/routing.php';
require_once dirname( __DIR__ ) . '/includes/follow-counts.php';

global $wpdb;
$ax_fc_results  = array();
$ax_fc_actor    = null;
$ax_fc_previous = null;
$ax_fc_admin    = 0;
$ax_fc_remote   = null;

/**
 * @param array  $results Accumulator.
 * @param string $label   Contract.
 * @param bool   $cond    Holds.
 * @return void
 */
function ax_fc_assert( array &$results, string $label, bool $cond ) : void {
	$results[] = $cond;
	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI test output.
	printf( "[%s] %s\n", $cond ? 'PASS' : 'FAIL', $label );
}

/**
 * The Actor Projections root document for one collection, if that plugin is present.
 *
 * @param Axismundi_Actor $actor Local Actor.
 * @param string          $kind  followers|following.
 * @param int             $page  0 for the root.
 * @return array<string,mixed>|null Null when Object Projections is not installed.
 */
function ax_fc_projection( Axismundi_Actor $actor, string $kind, int $page = 0 ) : ?array {
	if ( ! class_exists( 'Axismundi_OP_Actor_Followers' ) || ! function_exists( 'axismundi_op_actor_followers_transform' ) ) {
		return null;
	}
	return axismundi_op_actor_followers_transform( new Axismundi_OP_Actor_Followers( $actor, $kind, $page ) );
}

try {
	$ax_fc_block_registry = WP_Block_Type_Registry::get_instance();
	$ax_fc_counts_block   = $ax_fc_block_registry->get_registered( 'axismundi/actor-social-counts' );
	$ax_fc_list_block     = $ax_fc_block_registry->get_registered( 'axismundi/actor-follow-list' );
	ax_fc_assert(
		$ax_fc_results,
		'the social-counts and follow-list blocks have editor scripts, so Site Editor recognizes saved template markup',
		$ax_fc_counts_block instanceof WP_Block_Type && ! empty( $ax_fc_counts_block->editor_script_handles )
			&& $ax_fc_list_block instanceof WP_Block_Type && ! empty( $ax_fc_list_block->editor_script_handles )
	);

	$ax_fc_admin_ids = get_users( array( 'role' => 'administrator', 'number' => 1, 'fields' => 'ids' ) );
	$ax_fc_admin     = isset( $ax_fc_admin_ids[0] ) ? (int) $ax_fc_admin_ids[0] : 0;

	/*
	 * The subject has to be a *publicly routable* local Actor, not merely a local one.
	 * The site Actor is `internal`, so every level would collapse to `private` and the
	 * matrix would pass its own `private` rows while silently testing nothing else.
	 */
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- dev-only fixture lookup.
	$ax_fc_uuid = (string) $wpdb->get_var(
		'SELECT i.uuid FROM ' . axismundi_actors_identities_table() . ' i INNER JOIN ' . axismundi_actors_actors_table()
		. " a ON a.identity_id = i.id WHERE i.origin = 'local' AND i.status = 'public' AND a.handle_locked_at IS NOT NULL"
		. " AND a.preferred_username <> '' ORDER BY i.id ASC LIMIT 1"
	);
	$ax_fc_actor = '' !== $ax_fc_uuid ? axismundi_actors_get_by_uuid( $ax_fc_uuid ) : null;

	ax_fc_assert(
		$ax_fc_results,
		'a publicly routable local Actor and an administrator are available to drive the matrix',
		$ax_fc_actor instanceof Axismundi_Actor && axismundi_actors_is_public_profile( $ax_fc_actor ) && $ax_fc_admin > 0
	);
	if ( ! $ax_fc_actor instanceof Axismundi_Actor || $ax_fc_admin <= 0 ) {
		throw new RuntimeException( 'fixtures unavailable' );
	}
	$ax_fc_previous = $ax_fc_actor->get_follow_collections_visibility();

	// Routability gates the policy: an Actor nobody may view discloses nothing, whatever
	// its own setting says. The site Actor is `internal`, which makes it the fixture.
	$ax_fc_internal = axismundi_actors_get_site_actor();
	if ( $ax_fc_internal instanceof Axismundi_Actor && ! axismundi_actors_is_public_profile( $ax_fc_internal ) ) {
		axismundi_actors_set_follow_collections_visibility( $ax_fc_internal, 'public', $ax_fc_admin );
		$ax_fc_internal = axismundi_actors_get_by_uuid( $ax_fc_internal->get_uuid() );
		ax_fc_assert(
			$ax_fc_results,
			'a non-public local Actor stays private even when its policy says public',
			$ax_fc_internal instanceof Axismundi_Actor && 'private' === axismundi_actors_follow_collections_policy( $ax_fc_internal )
		);
		axismundi_actors_set_follow_collections_visibility( $ax_fc_internal, null, $ax_fc_admin );
	}

	// A never-set policy must read as public, which is what makes disclosure the default
	// without migrating existing rows.
	axismundi_actors_set_follow_collections_visibility( $ax_fc_actor, null, $ax_fc_admin );
	$ax_fc_fresh = axismundi_actors_get_by_uuid( $ax_fc_actor->get_uuid() );
	ax_fc_assert(
		$ax_fc_results,
		'an unset policy reads as public rather than as withheld',
		$ax_fc_fresh instanceof Axismundi_Actor && 'public' === axismundi_actors_follow_collections_policy( $ax_fc_fresh )
	);

	// The legacy value predates the three levels and must not silently become public.
	axismundi_actors_set_follow_collections_visibility( $ax_fc_actor, 'followers', $ax_fc_admin );
	$ax_fc_legacy = axismundi_actors_get_by_uuid( $ax_fc_actor->get_uuid() );
	ax_fc_assert(
		$ax_fc_results,
		'the legacy `followers` value maps to count-only',
		$ax_fc_legacy instanceof Axismundi_Actor && 'count-only' === axismundi_actors_follow_collections_policy( $ax_fc_legacy )
	);

	$ax_fc_expected = array(
		'public'     => array( 'counts' => true,  'lists' => true,  'page' => true,  'total' => true ),
		'count-only' => array( 'counts' => true,  'lists' => false, 'page' => false, 'total' => true ),
		'private'    => array( 'counts' => false, 'lists' => false, 'page' => false, 'total' => false ),
	);

	foreach ( $ax_fc_expected as $ax_fc_policy => $ax_fc_want ) {
		axismundi_actors_set_follow_collections_visibility( $ax_fc_actor, $ax_fc_policy, $ax_fc_admin );
		$ax_fc_subject = axismundi_actors_get_by_uuid( $ax_fc_actor->get_uuid() );
		if ( ! $ax_fc_subject instanceof Axismundi_Actor ) {
			ax_fc_assert( $ax_fc_results, sprintf( '%s: the Actor reloads after the policy write', $ax_fc_policy ), false );
			continue;
		}

		ax_fc_assert(
			$ax_fc_results,
			sprintf( '%s: the policy resolves to itself', $ax_fc_policy ),
			$ax_fc_policy === axismundi_actors_follow_collections_policy( $ax_fc_subject )
		);
		ax_fc_assert(
			$ax_fc_results,
			sprintf( '%s: counts are %s on the human profile', $ax_fc_policy, $ax_fc_want['counts'] ? 'shown' : 'withheld' ),
			$ax_fc_want['counts'] === axismundi_actors_follow_counts_are_public( $ax_fc_subject )
		);
		ax_fc_assert(
			$ax_fc_results,
			sprintf( '%s: member Actors are %s', $ax_fc_policy, $ax_fc_want['lists'] ? 'disclosed' : 'withheld' ),
			$ax_fc_want['lists'] === axismundi_actors_follow_collections_are_public( $ax_fc_subject )
		);
		ax_fc_assert(
			$ax_fc_results,
			sprintf( '%s: the human list page is %s', $ax_fc_policy, $ax_fc_want['lists'] ? 'served' : 'a 404' ),
			$ax_fc_want['lists'] === axismundi_actors_follow_collection_page_is_available( $ax_fc_subject )
		);

		foreach ( array( 'followers', 'following' ) as $ax_fc_kind ) {
			$ax_fc_root = ax_fc_projection( $ax_fc_subject, $ax_fc_kind );
			if ( null === $ax_fc_root ) {
				continue;
			}
			ax_fc_assert(
				$ax_fc_results,
				sprintf( '%s/%s: the collection root is always served, whatever the policy', $ax_fc_policy, $ax_fc_kind ),
				'OrderedCollection' === ( $ax_fc_root['type'] ?? '' ) && ! empty( $ax_fc_root['id'] )
			);
			ax_fc_assert(
				$ax_fc_results,
				sprintf( '%s/%s: totalItems is %s', $ax_fc_policy, $ax_fc_kind, $ax_fc_want['total'] ? 'published' : 'omitted, not zeroed' ),
				$ax_fc_want['total'] === array_key_exists( 'totalItems', $ax_fc_root )
			);
			ax_fc_assert(
				$ax_fc_results,
				sprintf( '%s/%s: `first` is %s, which is the enumerate/do-not-enumerate signal', $ax_fc_policy, $ax_fc_kind, $ax_fc_want['page'] ? 'offered when non-empty' : 'absent' ),
				$ax_fc_want['page'] || ! array_key_exists( 'first', $ax_fc_root )
			);

			$ax_fc_page = ax_fc_projection( $ax_fc_subject, $ax_fc_kind, 1 );
			if ( null !== $ax_fc_page && ! $ax_fc_want['page'] ) {
				ax_fc_assert(
					$ax_fc_results,
					sprintf( '%s/%s: a page never leaks members even if one is built directly', $ax_fc_policy, $ax_fc_kind ),
					array() === ( $ax_fc_page['orderedItems'] ?? null ) && ! array_key_exists( 'next', $ax_fc_page )
				);
			}
		}
	}

	// Remote Actors have no local policy: their server decides, so we publish nothing
	// about them under our own name.
	$ax_fc_remote = axismundi_actors_get_by_remote_acct( '@thaumiel999@mastodon.social', 'Person' );
	if ( $ax_fc_remote instanceof Axismundi_Actor ) {
		ax_fc_assert(
			$ax_fc_results,
			'a remote Actor has no local disclosure policy',
			'private' === axismundi_actors_follow_collections_policy( $ax_fc_remote )
		);
		ax_fc_assert(
			$ax_fc_results,
			'a remote Actor still gets a hosted list page, because its rows are our own observations',
			true === axismundi_actors_follow_collection_page_is_available( $ax_fc_remote )
		);
		$ax_fc_count_event_args = array( $ax_fc_remote->get_identity_id() );
		wp_clear_scheduled_hook( 'axismundi_actors_refresh_follow_counts', $ax_fc_count_event_args );
		do_action( 'axismundi_actors_remote_actor_discovered', $ax_fc_remote );
		ax_fc_assert(
			$ax_fc_results,
			'the remote-discovery hook accepts its Actor object and schedules a count refresh by identity id',
			false !== wp_next_scheduled( 'axismundi_actors_refresh_follow_counts', $ax_fc_count_event_args )
		);
		wp_clear_scheduled_hook( 'axismundi_actors_refresh_follow_counts', $ax_fc_count_event_args );
		ax_fc_assert(
			$ax_fc_results,
			'the remote hub link leaves this site rather than pointing back at our own page',
			str_starts_with( axismundi_actors_remote_follow_collection_url( $ax_fc_remote ), 'https://' )
				&& axismundi_actors_remote_follow_collection_url( $ax_fc_remote ) !== axismundi_actors_profile_hub_url( $ax_fc_remote )
		);

		$ax_fc_before = array(
			$ax_fc_remote->get_remote_follow_total( 'followers' ),
			$ax_fc_remote->get_remote_follow_total( 'following' ),
		);
		axismundi_actors_set_remote_follow_totals( $ax_fc_remote->get_identity_id(), 47, 0 );
		$ax_fc_cached = axismundi_actors_get_by_remote_acct( '@thaumiel999@mastodon.social', 'Person' );
		ax_fc_assert(
			$ax_fc_results,
			'a cached remote total round-trips, and a genuine 0 stays 0 rather than becoming unknown',
			$ax_fc_cached instanceof Axismundi_Actor
				&& 47 === $ax_fc_cached->get_remote_follow_total( 'followers' )
				&& 0 === $ax_fc_cached->get_remote_follow_total( 'following' )
		);

		axismundi_actors_set_remote_follow_totals( $ax_fc_remote->get_identity_id(), null, null );
		$ax_fc_unknown = axismundi_actors_get_by_remote_acct( '@thaumiel999@mastodon.social', 'Person' );
		ax_fc_assert(
			$ax_fc_results,
			'an unreachable collection stores unknown rather than zero, and still records the attempt',
			$ax_fc_unknown instanceof Axismundi_Actor
				&& null === $ax_fc_unknown->get_remote_follow_total( 'followers' )
				&& '' !== $ax_fc_unknown->get_follow_counts_fetched_at()
		);
		ax_fc_assert(
			$ax_fc_results,
			'a just-written entry is not stale, so the refresh batch does not spin on it',
			$ax_fc_unknown instanceof Axismundi_Actor && ! axismundi_actors_follow_counts_are_stale( $ax_fc_unknown )
		);

		axismundi_actors_set_remote_follow_totals( $ax_fc_remote->get_identity_id(), $ax_fc_before[0], $ax_fc_before[1] );
	}

	ax_fc_assert(
		$ax_fc_results,
		'a malformed or absent totalItems reads as unknown instead of zero',
		null === axismundi_actors_fetch_collection_total( '' ) && null === axismundi_actors_fetch_collection_total( 'not-a-url' )
	);
} catch ( Throwable $ax_fc_error ) {
	ax_fc_assert( $ax_fc_results, 'the matrix ran to completion: ' . $ax_fc_error->getMessage(), false );
} finally {
	if ( $ax_fc_actor instanceof Axismundi_Actor && $ax_fc_admin > 0 ) {
		axismundi_actors_set_follow_collections_visibility( $ax_fc_actor, $ax_fc_previous, $ax_fc_admin );
	}
}

$ax_fc_failures = count( array_filter( $ax_fc_results, static fn( bool $r ) : bool => ! $r ) );
// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI test output.
printf( "\n== %d checks, %d failed ==\n", count( $ax_fc_results ), $ax_fc_failures );

if ( class_exists( 'WP_CLI' ) ) {
	WP_CLI::halt( $ax_fc_failures > 0 ? 1 : 0 );
}
exit( $ax_fc_failures > 0 ? 1 : 0 );
