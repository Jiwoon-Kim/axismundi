<?php
/**
 * Cached sizes of remote Actors' own Follow collections.
 *
 * A remote profile is worth less without "47 followers" on it, and that number is one
 * dereference away: every implementation we have checked -- Mastodon, Misskey, the
 * WordPress ActivityPub plugin -- answers its published `followers`/`following` URI
 * with an `OrderedCollection` carrying `totalItems`, unauthenticated.
 *
 * What we must not do is fetch it while rendering. This file follows the same rule the
 * binary asset cache states in its own header: network access happens in cron workers,
 * and render-time helpers read only what is already stored locally. A profile page that
 * blocks on two third-party HTTP requests is a profile page that hangs when the other
 * server does.
 *
 * The numbers are an observation, never a claim of ours. `null` means we do not know --
 * never fetched, unreachable, authorized-fetch, or a server that omits the field -- and
 * it must render as absence. Only the remote server can say a number is zero.
 *
 * @package AxismundiActors
 */

defined( 'ABSPATH' ) || exit;

/** How long a cached pair of counts stays fresh. */
const AXISMUNDI_ACTORS_FOLLOW_COUNTS_TTL = 6 * HOUR_IN_SECONDS;

/** Remote identities refreshed per cron pass. */
const AXISMUNDI_ACTORS_FOLLOW_COUNTS_BATCH = 5;

/**
 * Whether the stored counts for one Actor are older than the refresh interval.
 *
 * A never-fetched Actor is stale by definition, which is what schedules the first pass.
 *
 * @param Axismundi_Actor $actor Remote Actor.
 * @return bool
 */
function axismundi_actors_follow_counts_are_stale( Axismundi_Actor $actor ) : bool {
	$fetched = $actor->get_follow_counts_fetched_at();
	if ( '' === $fetched ) {
		return true;
	}
	return ( time() - (int) mysql2date( 'U', $fetched, false ) ) > AXISMUNDI_ACTORS_FOLLOW_COUNTS_TTL;
}

/**
 * Read `totalItems` from one collection URI.
 *
 * Anything other than a plain non-negative integer is treated as unknown rather than
 * coerced: a collection that omits the field is making no claim about its size, and
 * turning that silence into 0 would publish a number the other server never said.
 *
 * @param string $uri Collection address.
 * @return int|null
 */
function axismundi_actors_fetch_collection_total( string $uri ) : ?int {
	if ( '' === $uri || ! wp_http_validate_url( $uri ) ) {
		return null;
	}
	$response = wp_safe_remote_get(
		$uri,
		array(
			'timeout'    => 8,
			'redirection' => 3,
			'headers'    => array( 'Accept' => 'application/activity+json, application/ld+json' ),
		)
	);
	if ( is_wp_error( $response ) || 200 !== (int) wp_remote_retrieve_response_code( $response ) ) {
		return null;
	}
	$body = json_decode( (string) wp_remote_retrieve_body( $response ), true );
	if ( ! is_array( $body ) || ! array_key_exists( 'totalItems', $body ) ) {
		return null;
	}
	$total = $body['totalItems'];
	return is_int( $total ) || ( is_string( $total ) && ctype_digit( $total ) ) ? max( 0, (int) $total ) : null;
}

/**
 * Refresh one remote Actor's cached Follow counts.
 *
 * @param int $identity_id Remote identity.
 * @return bool Whether anything was written.
 */
function axismundi_actors_refresh_follow_counts( int $identity_id ) : bool {
	$actor = axismundi_actors_get_by_identity( $identity_id );
	if ( ! $actor instanceof Axismundi_Actor || $actor->is_local() ) {
		return false;
	}
	$totals = array();
	foreach ( array( 'followers', 'following' ) as $kind ) {
		$totals[ $kind ] = axismundi_actors_fetch_collection_total( axismundi_actors_get_endpoint( $actor, $kind ) );
	}
	return axismundi_actors_set_remote_follow_totals( $identity_id, $totals['followers'], $totals['following'] );
}
add_action( 'axismundi_actors_refresh_follow_counts', 'axismundi_actors_refresh_follow_counts' );

/**
 * Queue a first fetch as soon as a remote Actor is discovered, alongside the existing
 * asset and instance workers that already hang off this hook.
 *
 * @param Axismundi_Actor $actor Discovered remote Actor.
 * @return void
 */

function axismundi_actors_queue_follow_counts( Axismundi_Actor $actor ) : void {
	if ( $actor->is_local() ) {
		return;
	}
	$identity_id = $actor->get_identity_id();
	if ( $identity_id <= 0 || wp_next_scheduled( 'axismundi_actors_refresh_follow_counts', array( $identity_id ) ) ) {
		return;
	}
	wp_schedule_single_event( time() + 15, 'axismundi_actors_refresh_follow_counts', array( $identity_id ) );
}
add_action( 'axismundi_actors_remote_actor_discovered', 'axismundi_actors_queue_follow_counts', 30 );

/**
 * Refresh the oldest stale entries, a few at a time.
 *
 * Ordering by `follow_counts_fetched_at` with nulls first means newly cached Actors are
 * served before anything is re-checked, so a profile that has never shown a count stops
 * waiting sooner than one whose number is merely a few hours old.
 *
 * @return void
 */
function axismundi_actors_refresh_stale_follow_counts() : void {
	global $wpdb;
	$actors     = axismundi_actors_actors_table();
	$identities = axismundi_actors_identities_table();
	$cutoff     = gmdate( 'Y-m-d H:i:s', time() - AXISMUNDI_ACTORS_FOLLOW_COUNTS_TTL );
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Actors repository owns these custom tables.
	$ids = (array) $wpdb->get_col(
		$wpdb->prepare(
			"SELECT a.identity_id FROM {$actors} a INNER JOIN {$identities} i ON i.id = a.identity_id
			 WHERE i.origin = 'remote' AND ( a.follow_counts_fetched_at IS NULL OR a.follow_counts_fetched_at < %s )
			 ORDER BY a.follow_counts_fetched_at IS NOT NULL, a.follow_counts_fetched_at ASC LIMIT %d",
			$cutoff,
			AXISMUNDI_ACTORS_FOLLOW_COUNTS_BATCH
		)
	);
	foreach ( $ids as $id ) {
		axismundi_actors_refresh_follow_counts( (int) $id );
	}
}
add_action( 'axismundi_actors_follow_counts_cron', 'axismundi_actors_refresh_stale_follow_counts' );

/** Keep the recurring refresh scheduled without waiting for a reactivation. */
function axismundi_actors_schedule_follow_counts_cron() : void {
	if ( ! wp_next_scheduled( 'axismundi_actors_follow_counts_cron' ) ) {
		wp_schedule_event( time() + HOUR_IN_SECONDS, 'hourly', 'axismundi_actors_follow_counts_cron' );
	}
}
add_action( 'init', 'axismundi_actors_schedule_follow_counts_cron', 12 );
