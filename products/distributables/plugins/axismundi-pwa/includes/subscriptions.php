<?php
/**
 * Devices somebody has said may be woken.
 *
 * A subscription is a browser's promise, not a person's preference. Whether they want push at all
 * is Notifications' question; this only answers where a message would go if one were sent, and
 * a device can be revoked by the browser without anybody changing a setting.
 *
 * @package AxismundiPwa
 */

defined( 'ABSPATH' ) || exit;

/**
 * Whether a string is the shape of a push endpoint.
 *
 * Checked structurally rather than with `wp_http_validate_url()`, which resolves the host and
 * refuses anything that does not answer -- turning a browser registering itself into a DNS lookup,
 * and refusing perfectly good endpoints on a machine that cannot reach the network. What actually
 * matters is what the specification requires: an absolute `https` URL with a host.
 *
 * @param string $endpoint Candidate endpoint.
 * @return bool
 */
function axismundi_pwa_is_push_endpoint( string $endpoint ) : bool {
	$parts = wp_parse_url( trim( $endpoint ) );
	return is_array( $parts )
		&& 'https' === strtolower( (string) ( $parts['scheme'] ?? '' ) )
		&& '' !== (string) ( $parts['host'] ?? '' );
}

/**
 * Remember a device.
 *
 * Keyed on the endpoint, so a browser re-subscribing with the same endpoint updates its keys rather
 * than accumulating rows -- which happens routinely, because a push service may hand back the same
 * endpoint with rotated keys.
 *
 * @param int                 $user_id      Owner.
 * @param array<string,mixed> $subscription endpoint, keys.p256dh, keys.auth.
 * @param string              $user_agent   What asked, for a person recognising their own devices.
 * @return int|WP_Error Subscription id.
 */
function axismundi_pwa_subscribe( int $user_id, array $subscription, string $user_agent = '' ) {
	global $wpdb;
	if ( $user_id <= 0 || ! axismundi_pwa_ready() ) {
		return new WP_Error( 'ax_pwa_unavailable', __( 'Push subscriptions are not available.', 'axismundi-pwa' ), array( 'status' => 503 ) );
	}
	$endpoint = trim( (string) ( $subscription['endpoint'] ?? '' ) );
	$p256dh   = trim( (string) ( $subscription['keys']['p256dh'] ?? '' ) );
	$auth     = trim( (string) ( $subscription['keys']['auth'] ?? '' ) );
	if ( '' === $endpoint || ! axismundi_pwa_is_push_endpoint( $endpoint ) || '' === $p256dh || '' === $auth ) {
		// All three or none. A subscription missing its keys cannot be encrypted to, and storing one
		// would leave a device that looks reachable and is not.
		return new WP_Error( 'ax_pwa_subscription', __( 'That is not a usable push subscription.', 'axismundi-pwa' ), array( 'status' => 400 ) );
	}
	$now  = current_time( 'mysql', true );
	$hash = hash( 'sha256', $endpoint );
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- this plugin's own table.
	$wpdb->query(
		$wpdb->prepare(
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name from this plugin.
			'INSERT INTO ' . axismundi_pwa_subscriptions_table()
			. ' ( local_user_id, endpoint, endpoint_hash, p256dh_key, auth_key, user_agent, created_at, last_seen_at, revoked_at )'
			. ' VALUES ( %d, %s, %s, %s, %s, %s, %s, %s, NULL )'
			. ' ON DUPLICATE KEY UPDATE local_user_id = VALUES( local_user_id ), p256dh_key = VALUES( p256dh_key ),'
			. ' auth_key = VALUES( auth_key ), user_agent = VALUES( user_agent ), last_seen_at = VALUES( last_seen_at ), revoked_at = NULL',
			$user_id,
			$endpoint,
			$hash,
			$p256dh,
			$auth,
			mb_substr( $user_agent, 0, 191 ),
			$now,
			$now
		)
	);
	return (int) axismundi_pwa_subscription_id( $endpoint );
}

/**
 * The id one endpoint is stored under, or 0.
 *
 * @param string $endpoint Push endpoint.
 * @return int
 */
function axismundi_pwa_subscription_id( string $endpoint ) : int {
	global $wpdb;
	if ( ! axismundi_pwa_ready() ) {
		return 0;
	}
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- keyed lookup in this plugin's own table.
	return (int) $wpdb->get_var( $wpdb->prepare( 'SELECT id FROM ' . axismundi_pwa_subscriptions_table() . ' WHERE endpoint_hash = %s', hash( 'sha256', trim( $endpoint ) ) ) );
}

/**
 * The devices one person can currently be reached on.
 *
 * @param int $user_id Owner.
 * @return array<int,array<string,mixed>>
 */
function axismundi_pwa_subscriptions_for( int $user_id ) : array {
	global $wpdb;
	if ( $user_id <= 0 || ! axismundi_pwa_ready() ) {
		return array();
	}
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- keyed lookup in this plugin's own table.
	return (array) $wpdb->get_results(
		$wpdb->prepare( 'SELECT * FROM ' . axismundi_pwa_subscriptions_table() . ' WHERE local_user_id = %d AND revoked_at IS NULL ORDER BY last_seen_at DESC', $user_id ),
		ARRAY_A
	);
}

/**
 * Stop reaching a device.
 *
 * Marked rather than deleted, so a sender that is mid-flight learns the endpoint is finished
 * instead of finding nothing and treating it as a device it has never met.
 *
 * @param string   $endpoint Push endpoint.
 * @param int|null $user_id  When given, only that person's own device.
 * @return bool
 */
function axismundi_pwa_revoke( string $endpoint, ?int $user_id = null ) : bool {
	global $wpdb;
	if ( ! axismundi_pwa_ready() ) {
		return false;
	}
	$where = array( 'endpoint_hash' => hash( 'sha256', trim( $endpoint ) ) );
	if ( null !== $user_id ) {
		$where['local_user_id'] = $user_id;
	}
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- this plugin's own table.
	return (bool) $wpdb->update(
		axismundi_pwa_subscriptions_table(),
		array( 'revoked_at' => current_time( 'mysql', true ) ),
		$where,
		array( '%s' ),
		null === $user_id ? array( '%s' ) : array( '%s', '%d' )
	);
}

/**
 * Forget every device belonging to somebody who is gone.
 *
 * @param int $user_id Deleted user.
 * @return void
 */
function axismundi_pwa_forget_user( int $user_id ) : void {
	global $wpdb;
	if ( $user_id <= 0 || ! axismundi_pwa_ready() ) {
		return;
	}
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- this plugin's own table.
	$wpdb->delete( axismundi_pwa_subscriptions_table(), array( 'local_user_id' => $user_id ), array( '%d' ) );
}
add_action( 'deleted_user', 'axismundi_pwa_forget_user' );
