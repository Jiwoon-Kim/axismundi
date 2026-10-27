<?php
/** Request selection and browser-session transport, never an authorization grant. @package AxismundiActors */
defined( 'ABSPATH' ) || exit;

/** Switch UI and workspace consumers together, only with a browser adapter present. */
function axismundi_actors_account_selection_enabled() : bool {
	return true === apply_filters( 'axismundi_actors_account_selection_enabled', in_array( get_option( 'ax_actors_account_selection_enabled', false ), array( true, 1, '1' ), true ) )
		&& true === apply_filters( 'axismundi_actors_account_selection_adapter_available', false );
}

/** Keep installations sharing a cookie domain separate. */
function axismundi_actors_selection_cookie_name() : string {
	return 'ax_account_selection_' . substr( hash( 'sha256', home_url( '/' ) ), 0, 12 );
}

/** A live browser session only; Application Passwords and CLI identity are not cookie sessions. */
function axismundi_actors_browser_session( int $user_id ) {
	$cookie = $_COOKIE[ LOGGED_IN_COOKIE ] ?? null;
	if ( $user_id <= 0 || get_current_user_id() !== $user_id || ! is_string( $cookie ) || '' === $cookie
		|| $user_id !== wp_validate_auth_cookie( $cookie, 'logged_in' ) ) {
		return new WP_Error( 'ax_selection_session', 'A valid browser login session is required.' );
	}
	$parsed = wp_parse_auth_cookie( $cookie, 'logged_in' );
	$session = WP_Session_Tokens::get_instance( $user_id )->get( $parsed['token'] );
	$expiration = min( (int) $parsed['expiration'], (int) ( $session['expiration'] ?? 0 ) );
	if ( $expiration <= time() ) {
		return new WP_Error( 'ax_selection_session', 'The browser login session has expired.' );
	}
	return array(
		'expiration' => $expiration,
		'binding' => hash( 'sha256', home_url( '/' ) . '|' . $user_id . '|' . $parsed['token'] ),
	);
}

/** Null is absent; an invalid or stale selection is an error, never a default. */
function axismundi_actors_selection_cookie( int $user_id ) {
	$name = axismundi_actors_selection_cookie_name();
	if ( ! array_key_exists( $name, $_COOKIE ) ) {
		return null;
	}
	$value = $_COOKIE[ $name ];
	if ( ! is_string( $value ) || strlen( $value ) > 180
		|| ! preg_match( '/\Av1\|([1-9][0-9]*)\|([1-9][0-9]*)\|([1-9][0-9]*)\|([a-f0-9]{64})\z/D', $value, $parts ) ) {
		return new WP_Error( 'ax_selection_cookie', 'The Account selection is invalid; choose an Account again.' );
	}
	foreach ( array( 1, 2, 3 ) as $index ) {
		if ( (string) (int) $parts[ $index ] !== $parts[ $index ] ) {
			return new WP_Error( 'ax_selection_cookie', 'The Account selection is invalid.' );
		}
	}
	$session = axismundi_actors_browser_session( $user_id );
	if ( is_wp_error( $session ) ) {
		return $session;
	}
	$unsigned = 'v1|' . $parts[1] . '|' . $parts[2] . '|' . $parts[3];
	$expected = hash_hmac( 'sha256', $unsigned . '|' . $session['binding'], wp_salt( 'auth' ) );
	if ( (int) $parts[2] !== $user_id || (int) $parts[3] <= time() || (int) $parts[3] > $session['expiration']
		|| ! hash_equals( $expected, $parts[4] ) ) {
		return new WP_Error( 'ax_selection_cookie', 'The Account selection no longer belongs to this login session.' );
	}
	return (int) $parts[1];
}

/**
 * Trusted adapters provide the authenticated principal and transport policy.
 * Null means no explicit selection; zero is an invalid explicit selection.
 * A purpose permits an explicitly stored default, never a legacy Person fallback.
 *
 * @return array{actor: Axismundi_Actor, source: string}|WP_Error
 */
function axismundi_actors_resolve_account_selection( int $user_id, ?int $explicit_id = null, string $purpose = '', bool $publication = false, bool $browser = false ) {
	if ( $user_id <= 0 || ! get_userdata( $user_id ) ) {
		return new WP_Error( 'ax_selection_user', 'An authenticated User is required.' );
	}
	if ( ! axismundi_actors_account_access_ready() ) {
		return new WP_Error( 'ax_selection_not_ready', 'Account access is not ready.' );
	}
	$source = 'explicit';
	$id = $explicit_id;
	if ( null === $id && $browser ) {
		$id = axismundi_actors_selection_cookie( $user_id );
		if ( is_wp_error( $id ) ) {
			return $id;
		}
		$source = 'session';
	}
	if ( null === $id && '' !== $purpose ) {
		if ( '' === axismundi_actors_account_default_key( $purpose ) ) {
			return new WP_Error( 'ax_selection_purpose', 'Invalid default purpose.' );
		}
		$key = axismundi_actors_account_default_key( $purpose );
		if ( metadata_exists( 'user', $user_id, $key ) ) {
			$id = (int) get_user_meta( $user_id, $key, true );
			$source = 'default';
		}
	}
	if ( null === $id ) {
		return new WP_Error( 'ax_selection_missing', 'Choose an Account before performing this operation.' );
	}
	$actor = $id > 0 ? axismundi_actors_resolve_workspace( $id, $user_id ) : null;
	if ( ! $actor instanceof Axismundi_Actor ) {
		return new WP_Error( 'ax_selection_unavailable', 'The selected Account is unavailable or access was revoked.' );
	}
	if ( $publication && ! axismundi_actors_can_act_as( $actor, $user_id ) ) {
		return new WP_Error( 'ax_selection_publication', 'The selected Account cannot publish.' );
	}
	return array( 'actor' => $actor, 'source' => $source );
}

/** Selection cookies are HttpOnly, host/path scoped like WordPress login cookies. */
function axismundi_actors_selection_cookie_options( int $expiration ) : array {
	return array( 'expires' => $expiration, 'path' => COOKIEPATH ?: '/', 'domain' => (string) COOKIE_DOMAIN,
		'secure' => is_ssl(), 'httponly' => true, 'samesite' => 'Lax' );
}

/** Match both WordPress login-cookie paths when home and install URLs differ. */
function axismundi_actors_send_selection_cookie( string $value, int $expiration ) : bool {
	if ( headers_sent() ) {
		return false;
	}
	$options = axismundi_actors_selection_cookie_options( $expiration );
	foreach ( array_unique( array( COOKIEPATH ?: '/', SITECOOKIEPATH ?: '/' ) ) as $path ) {
		$options['path'] = $path;
		if ( ! setcookie( axismundi_actors_selection_cookie_name(), $value, $options ) ) {
			return false;
		}
	}
	return true;
}

/** The web adapter must authenticate and check CSRF before invoking this setter. */
function axismundi_actors_set_account_selection( int $identity_id ) {
	$user_id = get_current_user_id();
	$session = axismundi_actors_browser_session( $user_id );
	if ( is_wp_error( $session ) ) {
		return $session;
	}
	$result = axismundi_actors_resolve_account_selection( $user_id, $identity_id );
	if ( is_wp_error( $result ) ) {
		return $result;
	}
	$unsigned = 'v1|' . $identity_id . '|' . $user_id . '|' . $session['expiration'];
	$value = $unsigned . '|' . hash_hmac( 'sha256', $unsigned . '|' . $session['binding'], wp_salt( 'auth' ) );
	if ( ! axismundi_actors_send_selection_cookie( $value, $session['expiration'] ) ) {
		return new WP_Error( 'ax_selection_headers', 'The Account selection cookie could not be sent.' );
	}
	$_COOKIE[ axismundi_actors_selection_cookie_name() ] = $value;
	do_action( 'axismundi_actors_account_selection_changed', $identity_id, $user_id );
	return true;
}

/** No preference writes: logout invalidates the session binding even if headers are unavailable. */
function axismundi_actors_clear_account_selection() : bool {
	unset( $_COOKIE[ axismundi_actors_selection_cookie_name() ] );
	return axismundi_actors_send_selection_cookie( '', time() - YEAR_IN_SECONDS );
}
add_action( 'clear_auth_cookie', 'axismundi_actors_clear_account_selection' );

/** Hold only the auth cookie issued in this request, not a browser's previous login. */
function axismundi_actors_remember_login_cookie( string $cookie = '' ) : string {
	static $issued = '';
	$previous = $issued;
	$issued = $cookie;
	return $previous;
}
add_action( 'set_logged_in_cookie', 'axismundi_actors_remember_login_cookie' );

/** Initialize a new session once; never restore a default after a selection error. */
function axismundi_actors_initialize_login_selection( string $login, WP_User $user ) : void {
	unset( $login );
	$cookie = axismundi_actors_remember_login_cookie();
	axismundi_actors_clear_account_selection();
	if ( ! axismundi_actors_account_selection_enabled() || '' === $cookie
		|| (int) $user->ID !== wp_validate_auth_cookie( $cookie, 'logged_in' ) ) {
		return;
	}
	$actor = axismundi_actors_default_account( (int) $user->ID, 'login' );
	if ( ! $actor instanceof Axismundi_Actor ) { return; }
	$parsed = wp_parse_auth_cookie( $cookie, 'logged_in' );
	$session = WP_Session_Tokens::get_instance( $user->ID )->get( $parsed['token'] );
	$expiration = min( (int) $parsed['expiration'], (int) ( $session['expiration'] ?? 0 ) );
	if ( $expiration <= time() ) { return; }
	$binding = hash( 'sha256', home_url( '/' ) . '|' . $user->ID . '|' . $parsed['token'] );
	$unsigned = 'v1|' . $actor->get_identity_id() . '|' . $user->ID . '|' . $expiration;
	$value = $unsigned . '|' . hash_hmac( 'sha256', $unsigned . '|' . $binding, wp_salt( 'auth' ) );
	if ( axismundi_actors_send_selection_cookie( $value, $expiration ) ) {
		$_COOKIE[ axismundi_actors_selection_cookie_name() ] = $value;
	}
}
add_action( 'wp_login', 'axismundi_actors_initialize_login_selection', 10, 2 );
