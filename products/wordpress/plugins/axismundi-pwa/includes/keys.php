<?php
/**
 * The key pair that identifies this site to a push service.
 *
 * VAPID: one P-256 key pair per site. The public half is handed to a browser when it subscribes and
 * is baked into the subscription it returns -- so rotating it invalidates every subscription taken
 * out against the old one, which is why it is generated once and then left alone.
 *
 * The private half signs the requests that ask a push service to wake somebody's browser, and it
 * does not live here. Not in this plugin's source, and not in the database: a database is restored
 * into staging, dumped into a ticket and copied to a laptop, and a signing key that travels with it
 * is a signing key somebody else has. It comes from the environment, the way a deployment already
 * carries its other secrets.
 *
 * That has a consequence worth naming: a site with no keys configured cannot send, and says so
 * through the capability rather than by generating a pair to make the error go away.
 *
 * @package AxismundiPwa
 */

defined( 'ABSPATH' ) || exit;

/**
 * One configured value, from a constant or the environment.
 *
 * Constants first, because that is what `wp-config.php` and wp-env's own config write; the
 * environment second, because that is what a container deployment sets.
 *
 * @param string $name Setting name.
 * @return string
 */
function axismundi_pwa_setting( string $name ) : string {
	if ( defined( $name ) ) {
		return trim( (string) constant( $name ) );
	}
	$value = getenv( $name );
	return false === $value ? '' : trim( (string) $value );
}

/**
 * base64url, which is what every part of Web Push speaks.
 *
 * @param string $raw Raw bytes.
 * @return string
 */
function axismundi_pwa_base64url( string $raw ) : string {
	return rtrim( strtr( base64_encode( $raw ), '+/', '-_' ), '=' );
}

/** @return string The application server key a browser subscribes against, base64url. */
function axismundi_pwa_application_server_key() : string {
	return axismundi_pwa_setting( 'AXISMUNDI_PWA_VAPID_PUBLIC_KEY' );
}

/**
 * The signing key, base64url.
 *
 * Read at the moment of sending and never stored, returned or logged.
 *
 * @return string
 */
function axismundi_pwa_signing_key() : string {
	return axismundi_pwa_setting( 'AXISMUNDI_PWA_VAPID_PRIVATE_KEY' );
}

/**
 * Who a push service should complain to about this site's messages.
 *
 * VAPID wants a contact for the sender. The site's admin address is the honest answer and is not
 * published anywhere by this -- it goes in a signed token to the push service alone.
 *
 * @return string
 */
function axismundi_pwa_vapid_subject() : string {
	$configured = axismundi_pwa_setting( 'AXISMUNDI_PWA_VAPID_SUBJECT' );
	if ( '' !== $configured ) {
		return $configured;
	}
	$admin = (string) get_option( 'admin_email' );
	return '' !== $admin ? 'mailto:' . $admin : home_url( '/' );
}

/**
 * Whether this site has both halves.
 *
 * @return bool
 */
function axismundi_pwa_has_keys() : bool {
	return '' !== axismundi_pwa_application_server_key() && '' !== axismundi_pwa_signing_key();
}

/**
 * Make a pair for somebody to configure.
 *
 * Returns them rather than saving them, which is the whole point: this is a thing an operator runs
 * once and pastes into their deployment's secrets, not a thing the site does to itself.
 *
 * @return array{public:string,private:string}|WP_Error
 */
function axismundi_pwa_generate_keys() {
	if ( ! class_exists( '\\Minishlink\\WebPush\\VAPID' ) ) {
		return new WP_Error( 'ax_pwa_no_library', __( 'The Web Push library is not installed.', 'axismundi-pwa' ) );
	}
	try {
		$keys = \Minishlink\WebPush\VAPID::createVapidKeys();
	} catch ( Throwable $error ) {
		return new WP_Error( 'ax_pwa_keygen', $error->getMessage() );
	}
	return array(
		'public'  => (string) ( $keys['publicKey'] ?? '' ),
		'private' => (string) ( $keys['privateKey'] ?? '' ),
	);
}

/**
 * Print a pair to paste into a deployment.
 *
 * @return void
 */
function axismundi_pwa_cli_keys() : void {
	$keys = axismundi_pwa_generate_keys();
	if ( is_wp_error( $keys ) ) {
		WP_CLI::error( $keys->get_error_message() );
	}
	WP_CLI::line( "define( 'AXISMUNDI_PWA_VAPID_PUBLIC_KEY', '" . $keys['public'] . "' );" );
	WP_CLI::line( "define( 'AXISMUNDI_PWA_VAPID_PRIVATE_KEY', '" . $keys['private'] . "' );" );
	WP_CLI::warning( 'Keep the private key out of the database and out of version control.' );
}
if ( defined( 'WP_CLI' ) && WP_CLI ) {
	WP_CLI::add_command( 'axismundi-pwa keys', 'axismundi_pwa_cli_keys' );
}
