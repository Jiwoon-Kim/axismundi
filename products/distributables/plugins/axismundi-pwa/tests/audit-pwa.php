<?php
/**
 * Devices, keys, and saying what this site cannot do (dev-only; dist-excluded).
 *
 * Two things are pinned here. One is ordinary: a browser can register itself, re-register without
 * multiplying, and be forgotten. The other is the reason this plugin reports a shape instead of a
 * boolean -- nothing here can encrypt and post a Web Push message yet, and a site that said
 * otherwise would grow a settings switch that silently does nothing.
 *
 * @package AxismundiPwa
 */

defined( 'ABSPATH' ) || exit( 1 );

$ax_pw_results = array();
$ax_pw_users   = array();

/** @param bool[] $results Results. */
function ax_pw_assert( array &$results, string $label, bool $condition ) : void {
	$results[] = $condition;
	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI fixture output.
	printf( "[%s] %s\n", $condition ? 'PASS' : 'FAIL', $label );
}

/** One account. */
function ax_pw_user( array &$users ) : int {
	$login = 'axpw' . strtolower( wp_generate_password( 8, false, false ) );
	$id    = (int) wp_insert_user(
		array( 'user_login' => $login, 'user_email' => $login . '@example.test', 'user_pass' => wp_generate_password(), 'role' => 'editor' )
	);
	$users[] = $id;
	return $id;
}

/** One browser's subscription. */
function ax_pw_subscription( string $endpoint ) : array {
	return array(
		'endpoint' => $endpoint,
		'keys'     => array( 'p256dh' => wp_generate_password( 87, false, false ), 'auth' => wp_generate_password( 22, false, false ) ),
	);
}

try {
	$ax_pw_owner = ax_pw_user( $ax_pw_users );
	$ax_pw_other = ax_pw_user( $ax_pw_users );
	$ax_pw_phone = 'https://push.example.test/' . wp_generate_uuid4();
	$ax_pw_laptop = 'https://push.example.test/' . wp_generate_uuid4();

	// -- the site says what it can do, and what it cannot ------------------------------------------------

	/*
	 * Keys live in the deployment's secrets, so whether this site has any depends on the deployment --
	 * and the audit asks about the relationship between what it has and what it says, which is true
	 * either way. A site with none must say `no_keys` rather than generate a pair to look ready; a
	 * site with them must offer both.
	 */
	$ax_pw_capability = axismundi_pwa_capability();
	$ax_pw_configured = axismundi_pwa_has_keys();
	ax_pw_assert(
		$ax_pw_results,
		'what the site says it can do matches what it has been given, in either direction',
		$ax_pw_configured
			? ( true === $ax_pw_capability['subscribe'] && true === $ax_pw_capability['deliver'] )
			: ( false === $ax_pw_capability['subscribe'] && 'no_keys' === (string) $ax_pw_capability['reason'] && ! axismundi_pwa_can_deliver_push() )
	);
	// The key an operator would configure is the uncompressed P-256 point the Push API expects: a
	// leading 0x04 and two 32-byte coordinates.
	$ax_pw_keys = axismundi_pwa_generate_keys();
	$ax_pw_key  = is_wp_error( $ax_pw_keys ) ? '' : base64_decode( strtr( $ax_pw_keys['public'], '-_', '+/' ) );
	ax_pw_assert(
		$ax_pw_results,
		'and the pair it offers an operator is of the exact shape a browser will accept',
		! is_wp_error( $ax_pw_keys )
			&& 65 === strlen( (string) $ax_pw_key )
			&& "" === substr( (string) $ax_pw_key, 0, 1 )
			&& '' !== (string) $ax_pw_keys['private']
	);
	/*
	 * Handed back and nowhere else. Generating a pair changes nothing about the site: it does not
	 * write an option, and it does not become the key this site signs with -- which is what keeps the
	 * signing half in a deployment's secrets rather than in a database somebody restores into staging.
	 */
	ax_pw_assert(
		$ax_pw_results,
		'which it hands back rather than storing, the signing half being no database to hold',
		array() === (array) get_option( 'ax_pwa_vapid_keys', array() )
			&& ( is_wp_error( $ax_pw_keys ) || (string) $ax_pw_keys['private'] !== axismundi_pwa_signing_key() )
	);

	// -- a device, remembered ------------------------------------------------------------------------------

	$ax_pw_id = axismundi_pwa_subscribe( $ax_pw_owner, ax_pw_subscription( $ax_pw_phone ), 'Test/1.0' );
	ax_pw_assert(
		$ax_pw_results,
		'a browser registers itself and is listed among that person\'s devices',
		! is_wp_error( $ax_pw_id ) && $ax_pw_id > 0
			&& 1 === count( axismundi_pwa_subscriptions_for( $ax_pw_owner ) )
	);
	/*
	 * A push service may hand back the same endpoint with rotated keys, which happens routinely --
	 * so the same device re-registering updates rather than accumulating.
	 */
	$ax_pw_second = axismundi_pwa_subscribe( $ax_pw_owner, ax_pw_subscription( $ax_pw_phone ), 'Test/1.1' );
	ax_pw_assert(
		$ax_pw_results,
		'and re-registering the same device updates it rather than making a second one',
		$ax_pw_second === $ax_pw_id && 1 === count( axismundi_pwa_subscriptions_for( $ax_pw_owner ) )
	);
	// A second device is a second row: a person has as many as they have browsers.
	axismundi_pwa_subscribe( $ax_pw_owner, ax_pw_subscription( $ax_pw_laptop ), 'Test/2.0' );
	ax_pw_assert(
		$ax_pw_results,
		'while another browser is another device, a person having as many as they sit at',
		2 === count( axismundi_pwa_subscriptions_for( $ax_pw_owner ) )
	);
	// A subscription with no keys cannot be encrypted to, so storing one would leave a device that
	// looks reachable and is not.
	$ax_pw_broken = axismundi_pwa_subscribe( $ax_pw_owner, array( 'endpoint' => 'https://push.example.test/x', 'keys' => array() ) );
	ax_pw_assert(
		$ax_pw_results,
		'and a subscription missing its keys is refused rather than stored as unreachable',
		is_wp_error( $ax_pw_broken ) && 2 === count( axismundi_pwa_subscriptions_for( $ax_pw_owner ) )
	);

	// -- and forgotten --------------------------------------------------------------------------------------

	/*
	 * Scoped to the owner. An endpoint is a capability for waking a browser, so knowing one must not
	 * let anybody else silence it -- or claim it.
	 */
	ax_pw_assert(
		$ax_pw_results,
		'somebody else knowing the endpoint cannot revoke that device',
		! axismundi_pwa_revoke( $ax_pw_phone, $ax_pw_other )
			&& 2 === count( axismundi_pwa_subscriptions_for( $ax_pw_owner ) )
	);
	ax_pw_assert(
		$ax_pw_results,
		'while its owner can, and it stops being somewhere they can be reached',
		axismundi_pwa_revoke( $ax_pw_phone, $ax_pw_owner )
			&& 1 === count( axismundi_pwa_subscriptions_for( $ax_pw_owner ) )
	);
	// Marked rather than deleted, so a sender mid-flight learns it is finished instead of finding
	// nothing and treating it as a device it has never met.
	ax_pw_assert(
		$ax_pw_results,
		'and the row stays, so a sender learns it ended rather than that it never existed',
		axismundi_pwa_subscription_id( $ax_pw_phone ) > 0
	);
	// An account that is gone takes its devices with it.
	axismundi_pwa_forget_user( $ax_pw_owner );
	ax_pw_assert(
		$ax_pw_results,
		'an account that is deleted leaves no devices behind',
		array() === axismundi_pwa_subscriptions_for( $ax_pw_owner )
			&& 0 === axismundi_pwa_subscription_id( $ax_pw_laptop )
	);

	// -- one service worker, ours composed into it -------------------------------------------------------------

	/*
	 * Not a second worker. A site has exactly one, and the provider exists so several products can
	 * share it -- registering our own would recreate the fight it was written to prevent.
	 */
	$ax_pw_script = axismundi_pwa_service_worker_script();
	ax_pw_assert(
		$ax_pw_results,
		'the push handlers go into the site\'s one service worker through its provider',
		axismundi_pwa_has_provider()
			&& has_action( 'wp_front_service_worker', 'axismundi_pwa_register_service_worker_script' )
			&& str_contains( $ax_pw_script, "addEventListener( 'push'" )
			&& str_contains( $ax_pw_script, "addEventListener( 'notificationclick'" )
	);
	/*
	 * And they carry nothing. A push payload crosses a push service and can land on a lock screen,
	 * so what travels is an id and a category -- the opened, authenticated app fetches the rest.
	 */
	ax_pw_assert(
		$ax_pw_results,
		'and say only that something arrived, the contents staying behind a signed-in fetch',
		str_contains( $ax_pw_script, 'payload.delivery' )
			&& ! str_contains( $ax_pw_script, 'payload.title' )
			&& ! str_contains( $ax_pw_script, 'payload.body' )
	);
} finally {
	global $wpdb;
	foreach ( array_unique( $ax_pw_users ) as $ax_pw_user_id ) {
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fixture cleanup.
		$wpdb->delete( axismundi_pwa_subscriptions_table(), array( 'local_user_id' => (int) $ax_pw_user_id ), array( '%d' ) );
		wp_delete_user( (int) $ax_pw_user_id );
	}
}

$ax_pw_failures = count( array_filter( $ax_pw_results, static fn( bool $result ) : bool => ! $result ) );
// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI fixture output.
printf( "\n== %d checks, %d failed ==\n", count( $ax_pw_results ), $ax_pw_failures );
if ( class_exists( 'WP_CLI' ) ) {
	WP_CLI::halt( $ax_pw_failures > 0 ? 1 : 0 );
}
exit( $ax_pw_failures > 0 ? 1 : 0 );
