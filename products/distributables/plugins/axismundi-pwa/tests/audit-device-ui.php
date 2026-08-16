<?php
/**
 * The section where somebody turns push on (dev-only; dist-excluded).
 *
 * Rendered here rather than clicked, so what is pinned is the part a browser cannot be asked about
 * in a shell: that it appears on the notification settings page rather than a site-wide screen,
 * that it says which thing is missing when it cannot be offered, and that the markup never carries
 * the one value it must not -- the endpoint, which is the credential for waking that browser.
 *
 * @package AxismundiPwa
 */

defined( 'ABSPATH' ) || exit( 1 );

$ax_du_results = array();
$ax_du_users   = array();

/** @param bool[] $results Results. */
function ax_du_assert( array &$results, string $label, bool $condition ) : void {
	$results[] = $condition;
	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI fixture output.
	printf( "[%s] %s\n", $condition ? 'PASS' : 'FAIL', $label );
}

/** The section as it would be drawn for the signed-in person. */
function ax_du_render() : string {
	ob_start();
	do_action( 'axismundi_notification_device_settings' );
	return (string) ob_get_clean();
}

try {
	$ax_du_user = (int) wp_insert_user(
		array( 'user_login' => 'axdu' . strtolower( wp_generate_password( 8, false, false ) ), 'user_email' => 'axdu' . strtolower( wp_generate_password( 6, false, false ) ) . '@example.test', 'user_pass' => wp_generate_password(), 'role' => 'editor' )
	);
	$ax_du_users[] = $ax_du_user;
	wp_set_current_user( $ax_du_user );

	/*
	 * It lives on somebody's own notification settings, and it gets there by answering an action
	 * rather than by either plugin reaching into the other: Notifications owns the page, this owns
	 * devices, and neither knows how the other works.
	 */
	ax_du_assert(
		$ax_du_results,
		'the device section is offered to the notification settings page, not to a screen of its own',
		has_action( 'axismundi_notification_device_settings', 'axismundi_pwa_render_device_settings' )
			&& ! function_exists( 'axismundi_pwa_settings_page' )
	);

	// -- when it cannot be offered -------------------------------------------------------------------------

	/*
	 * No keys configured on this site, so registering is impossible -- and the section says which
	 * thing is missing rather than showing a button that would fail. An install and a deployment
	 * setting are fixed by different people.
	 */
	$ax_du_unavailable = ax_du_render();
	$ax_du_can         = axismundi_pwa_capability()['subscribe'];
	ax_du_assert(
		$ax_du_results,
		'it says what is missing rather than drawing a button that cannot work',
		$ax_du_can
			// Configured here, so the button is the honest thing to draw -- the sentence is what a site
			// without keys gets, and the reason is named so whoever has to fix it knows which fix.
			? str_contains( $ax_du_unavailable, 'axismundi-pwa-push-toggle' )
			: ( str_contains( $ax_du_unavailable, 'no push keys configured' )
				&& ! str_contains( $ax_du_unavailable, 'axismundi-pwa-push-toggle' ) )
	);

	// -- when it can ---------------------------------------------------------------------------------------

	$ax_du_keys = axismundi_pwa_generate_keys();
	if ( ! defined( 'AXISMUNDI_PWA_VAPID_PUBLIC_KEY' ) ) {
		// Already configured on this site, which is the ordinary case once somebody has set up push.
		define( 'AXISMUNDI_PWA_VAPID_PUBLIC_KEY', is_wp_error( $ax_du_keys ) ? '' : $ax_du_keys['public'] );
	}
	if ( ! defined( 'AXISMUNDI_PWA_VAPID_PRIVATE_KEY' ) ) {
		// Already configured on this site, which is the ordinary case once somebody has set up push.
		define( 'AXISMUNDI_PWA_VAPID_PRIVATE_KEY', is_wp_error( $ax_du_keys ) ? '' : $ax_du_keys['private'] );
	}
	set_current_screen( 'dashboard' );
	$ax_du_offered = ax_du_render();
	ax_du_assert(
		$ax_du_results,
		'and offers the button once the site can register a browser',
		str_contains( $ax_du_offered, 'axismundi-pwa-push-toggle' )
			&& wp_script_is( 'axismundi-pwa-push', 'enqueued' )
	);
	/*
	 * The script is told which worker to register. Left to the browser, an admin page would register
	 * the admin worker -- which carries no push handlers, so the subscription would be valid and
	 * would never show anybody anything.
	 */
	$ax_du_data = wp_scripts()->get_data( 'axismundi-pwa-push', 'data' );
	ax_du_assert(
		$ax_du_results,
		'telling it to register the worker with scope over the whole site, not the admin one',
		is_string( $ax_du_data )
			&& str_contains( $ax_du_data, 'wp.serviceworker' )
			&& ! str_contains( $ax_du_data, 'wp_service_worker=admin' )
	);
	// The public key travels; nothing else about keys does.
	ax_du_assert(
		$ax_du_results,
		'and handing over the public key alone, the signing half never reaching a page',
		is_string( $ax_du_data )
			&& str_contains( $ax_du_data, (string) axismundi_pwa_application_server_key() )
			&& ! str_contains( $ax_du_data, (string) axismundi_pwa_signing_key() )
	);

	// -- the device list ------------------------------------------------------------------------------------

	$ax_du_endpoint = 'https://push.example.test/' . wp_generate_uuid4();
	axismundi_pwa_subscribe(
		$ax_du_user,
		array( 'endpoint' => $ax_du_endpoint, 'keys' => array( 'p256dh' => 'x', 'auth' => 'y' ) ),
		'Test Browser/1.0'
	);
	$ax_du_listed = ax_du_render();
	ax_du_assert(
		$ax_du_results,
		'a registered browser is listed by something a person would recognise',
		str_contains( $ax_du_listed, 'Test Browser/1.0' )
	);
	/*
	 * And never by its endpoint. That string is the credential for waking the browser, and a form
	 * field or a browser history is exactly where it must not end up -- so the list works by row id
	 * and the check is the ownership relation rather than the number.
	 */
	ax_du_assert(
		$ax_du_results,
		'and never by its endpoint, which the page has no business carrying',
		! str_contains( $ax_du_listed, $ax_du_endpoint )
			&& has_action( 'admin_post_ax_pwa_forget_device', 'axismundi_pwa_handle_forget_device' )
	);
} finally {
	global $wpdb;
	wp_set_current_user( 0 );
	foreach ( array_unique( $ax_du_users ) as $ax_du_user_id ) {
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fixture cleanup.
		$wpdb->delete( axismundi_pwa_subscriptions_table(), array( 'local_user_id' => (int) $ax_du_user_id ), array( '%d' ) );
		wp_delete_user( (int) $ax_du_user_id );
	}
}

$ax_du_failures = count( array_filter( $ax_du_results, static fn( bool $result ) : bool => ! $result ) );
// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI fixture output.
printf( "\n== %d checks, %d failed ==\n", count( $ax_du_results ), $ax_du_failures );
if ( class_exists( 'WP_CLI' ) ) {
	WP_CLI::halt( $ax_du_failures > 0 ? 1 : 0 );
}
exit( $ax_du_failures > 0 ? 1 : 0 );
