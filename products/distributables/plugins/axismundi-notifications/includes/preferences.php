<?php
/**
 * What one person wants to be told about.
 *
 * A different question from what an Actor accepts, and asked of a different party. Acceptance is
 * about the sender -- blocked, muted, a stranger -- and belongs to the Actor that was written to.
 * This is about attention, and belongs to each person who reads for that Actor: two managers of one
 * Group may want entirely different things out of the same inbox, and neither is overruling the
 * other by saying so.
 *
 * Which is why it is applied when a delivery is written rather than when the event is. The event is
 * the Actor's fact and stays whatever anybody prefers; the delivery is one person's copy, and
 * somebody who turned a kind off simply has none.
 *
 * The order, most binding first:
 *
 *   1. security and moderation      always allowed; no preference reaches them
 *   2. block and notification mute  already dropped before this stage, by acceptance
 *   3. an explicit setting for the kind
 *   4. a setting for the category
 *   5. a default for that Actor context
 *   6. a global default
 *
 * At each of 3, 4 and 5 a setting made for one Actor beats the same setting made for all of them,
 * because the more specific answer is the one somebody went out of their way to give.
 *
 * Settings apply to what happens next and never rewrite what happened. Turning a kind off does not
 * take back a delivery already made, and turning one on does not manufacture deliveries for the
 * weeks it was off -- the same rule that keeps a filtered event where it is.
 *
 * @package AxismundiNotifications
 */

defined( 'ABSPATH' ) || exit;

/** The ways a notification can reach somebody. Only the first is built. */
const AXISMUNDI_NTF_TRANSPORTS = array( 'in_app', 'email', 'push' );

/** How specific a preference row is, most specific first. */
const AXISMUNDI_NTF_PREFERENCE_SCOPES = array( 'kind', 'category', 'all' );

/**
 * Record what somebody wants.
 *
 * @param int    $user_id    Reader.
 * @param int    $actor_id   Actor context, or 0 for every Actor they read for.
 * @param string $scope_type kind|category|all.
 * @param string $scope_key  The kind or category, or '' for `all`.
 * @param string $transport  in_app|email|push.
 * @param bool   $enabled    Whether they want it.
 * @return bool
 */
function axismundi_ntf_set_preference( int $user_id, int $actor_id, string $scope_type, string $scope_key, string $transport, bool $enabled ) : bool {
	global $wpdb;
	if ( $user_id <= 0 || ! axismundi_ntf_ready() ) {
		return false;
	}
	if ( ! in_array( $scope_type, AXISMUNDI_NTF_PREFERENCE_SCOPES, true ) || ! in_array( $transport, AXISMUNDI_NTF_TRANSPORTS, true ) ) {
		return false;
	}
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- this plugin's own table.
	return false !== $wpdb->replace(
		axismundi_ntf_preferences_table(),
		array(
			'local_user_id' => $user_id,
			'actor_id'      => max( 0, $actor_id ),
			'scope_type'    => $scope_type,
			'scope_key'     => 'all' === $scope_type ? '' : $scope_key,
			'transport'     => $transport,
			'enabled'       => (int) $enabled,
			'updated_at'    => current_time( 'mysql', true ),
		),
		array( '%d', '%d', '%s', '%s', '%s', '%d', '%s' )
	);
}

/**
 * Forget a setting, so the one behind it answers again.
 *
 * @param int    $user_id    Reader.
 * @param int    $actor_id   Actor context.
 * @param string $scope_type kind|category|all.
 * @param string $scope_key  The kind or category.
 * @param string $transport  Transport.
 * @return bool
 */
function axismundi_ntf_clear_preference( int $user_id, int $actor_id, string $scope_type, string $scope_key, string $transport ) : bool {
	global $wpdb;
	if ( $user_id <= 0 || ! axismundi_ntf_ready() ) {
		return false;
	}
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- this plugin's own table.
	return false !== $wpdb->delete(
		axismundi_ntf_preferences_table(),
		array(
			'local_user_id' => $user_id,
			'actor_id'      => max( 0, $actor_id ),
			'scope_type'    => $scope_type,
			'scope_key'     => 'all' === $scope_type ? '' : $scope_key,
			'transport'     => $transport,
		),
		array( '%d', '%d', '%s', '%s', '%s' )
	);
}

/**
 * One stored setting, or null when nothing was said at that specificity.
 *
 * @param int    $user_id    Reader.
 * @param int    $actor_id   Actor context.
 * @param string $scope_type kind|category|all.
 * @param string $scope_key  The kind or category.
 * @param string $transport  Transport.
 * @return bool|null
 */
function axismundi_ntf_preference( int $user_id, int $actor_id, string $scope_type, string $scope_key, string $transport ) : ?bool {
	global $wpdb;
	if ( $user_id <= 0 || ! axismundi_ntf_ready() ) {
		return null;
	}
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- keyed lookup in this plugin's own table.
	$found = $wpdb->get_var(
		$wpdb->prepare(
			'SELECT enabled FROM ' . axismundi_ntf_preferences_table()
			. ' WHERE local_user_id = %d AND actor_id = %d AND scope_type = %s AND scope_key = %s AND transport = %s',
			$user_id,
			max( 0, $actor_id ),
			$scope_type,
			'all' === $scope_type ? '' : $scope_key,
			$transport
		)
	);
	return null === $found ? null : 1 === (int) $found;
}

/**
 * Whether one person wants one kind of thing, for one Actor, by one route.
 *
 * @param int    $user_id   Reader.
 * @param int    $actor_id  Recipient Actor identity.
 * @param string $kind      Notification kind.
 * @param string $category  The kind's category.
 * @param string $transport in_app|email|push.
 * @return bool
 */
function axismundi_ntf_wants( int $user_id, int $actor_id, string $kind, string $category, string $transport = 'in_app' ) : bool {
	if ( in_array( $category, AXISMUNDI_NTF_UNFILTERABLE, true ) ) {
		/*
		 * Not a preference anybody gets to hold. A security warning nobody was shown because a setting
		 * was off is the failure this whole layer exists to avoid producing, and a moderation notice
		 * somebody turned off is one they are not answering while believing they have nothing to answer.
		 */
		return true;
	}
	foreach ( array(
		array( 'kind', $kind ),
		array( 'category', $category ),
		array( 'all', '' ),
	) as $level ) {
		// The Actor-specific answer first at each level: somebody who said something about this Group
		// in particular meant it more than what they said about all of them.
		foreach ( array( $actor_id, 0 ) as $scope_actor ) {
			$stored = axismundi_ntf_preference( $user_id, (int) $scope_actor, (string) $level[0], (string) $level[1], $transport );
			if ( null !== $stored ) {
				return $stored;
			}
		}
	}
	// Nothing said. In-app is how somebody finds out anything at all, so it is on; the transports
	// that interrupt are off until asked for, which is slice six's to honour.
	return 'in_app' === $transport;
}
