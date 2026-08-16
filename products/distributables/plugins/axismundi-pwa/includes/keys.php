<?php
/**
 * The key pair that identifies this site to a push service.
 *
 * VAPID: one P-256 key pair per site. The public half is handed to a browser when it subscribes and
 * is baked into the subscription it returns -- so rotating it invalidates every subscription taken
 * out against the old one, which is why it is generated once, kept, and never regenerated casually.
 *
 * The private half is a secret in the ordinary sense: it signs the requests that ask a push service
 * to wake somebody's browser. It is stored unautoloaded so it is not carried into every page load,
 * and nothing here ever returns it to a browser.
 *
 * @package AxismundiPwa
 */

defined( 'ABSPATH' ) || exit;

const AXISMUNDI_PWA_KEYS_OPTION = 'ax_pwa_vapid_keys';

/**
 * Whether this site has an application server key.
 *
 * @return bool
 */
function axismundi_pwa_has_keys() : bool {
	$keys = (array) get_option( AXISMUNDI_PWA_KEYS_OPTION, array() );
	return '' !== (string) ( $keys['public'] ?? '' ) && '' !== (string) ( $keys['private'] ?? '' );
}

/**
 * The public key a browser needs to subscribe, base64url encoded.
 *
 * @return string
 */
function axismundi_pwa_application_server_key() : string {
	$keys = (array) get_option( AXISMUNDI_PWA_KEYS_OPTION, array() );
	return (string) ( $keys['public'] ?? '' );
}

/**
 * Make the key pair if there is not one.
 *
 * Generated on demand rather than at activation, because a site that never enables push should not
 * be carrying a secret it has no use for.
 *
 * @return bool Whether keys exist afterwards.
 */
function axismundi_pwa_ensure_keys() : bool {
	if ( axismundi_pwa_has_keys() ) {
		return true;
	}
	if ( ! function_exists( 'openssl_pkey_new' ) ) {
		return false;
	}
	$resource = openssl_pkey_new(
		array( 'curve_name' => 'prime256v1', 'private_key_type' => OPENSSL_KEYTYPE_EC )
	);
	if ( false === $resource ) {
		return false;
	}
	$details = openssl_pkey_get_details( $resource );
	if ( ! is_array( $details ) || ! isset( $details['ec']['x'], $details['ec']['y'], $details['ec']['d'] ) ) {
		return false;
	}
	/*
	 * The uncompressed point form the Push API expects: 0x04 then x then y, each padded to the
	 * curve's 32 bytes. OpenSSL hands back the shortest representation, so a coordinate with a
	 * leading zero byte arrives one byte short and has to be padded back -- a subscription taken
	 * out against a key that lost a byte fails in the browser with nothing useful said about why.
	 */
	$public = "\x04" . str_pad( (string) $details['ec']['x'], 32, "\0", STR_PAD_LEFT ) . str_pad( (string) $details['ec']['y'], 32, "\0", STR_PAD_LEFT );
	update_option(
		AXISMUNDI_PWA_KEYS_OPTION,
		array(
			'public'  => axismundi_pwa_base64url( $public ),
			'private' => axismundi_pwa_base64url( str_pad( (string) $details['ec']['d'], 32, "\0", STR_PAD_LEFT ) ),
			'created' => current_time( 'mysql', true ),
		),
		false
	);
	return axismundi_pwa_has_keys();
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
