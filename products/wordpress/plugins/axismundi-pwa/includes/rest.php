<?php
/**
 * How a browser registers itself, and how anything asks what this site can do.
 *
 * Three routes and no more. Subscribing and unsubscribing are things a browser does for the person
 * signed into it; the capability route exists so a screen can say something true instead of drawing
 * a switch and hoping.
 *
 * @package AxismundiPwa
 */

defined( 'ABSPATH' ) || exit;

/** @return void */
function axismundi_pwa_register_routes() : void {
	$signed_in = static function () {
		return is_user_logged_in()
			? true
			: new WP_Error( 'ax_pwa_unauthenticated', __( 'You must be signed in.', 'axismundi-pwa' ), array( 'status' => 401 ) );
	};

	register_rest_route(
		'axismundi/v1',
		'/pwa/capability',
		array(
			'methods'  => WP_REST_Server::READABLE,
			'callback' => static function () : WP_REST_Response {
				$capability = axismundi_pwa_capability();
				// The key a browser needs to subscribe travels with the answer, because asking whether
				// you may subscribe and being given what you need to do it are one round trip.
				$capability['applicationServerKey'] = $capability['subscribe'] ? axismundi_pwa_application_server_key() : '';
				return new WP_REST_Response( $capability, 200 );
			},
			// Readable by anybody: it says what the site can do, not anything about a person.
			'permission_callback' => '__return_true',
		)
	);

	register_rest_route(
		'axismundi/v1',
		'/pwa/subscriptions',
		array(
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'permission_callback' => $signed_in,
				'callback'            => 'axismundi_pwa_rest_subscribe',
				'args'                => array(
					'endpoint' => array( 'type' => 'string', 'required' => true ),
					'keys'     => array( 'type' => 'object', 'required' => true ),
				),
			),
			array(
				'methods'             => WP_REST_Server::DELETABLE,
				'permission_callback' => $signed_in,
				'callback'            => 'axismundi_pwa_rest_unsubscribe',
				'args'                => array(
					'endpoint' => array( 'type' => 'string', 'required' => true ),
				),
			),
		)
	);
}
add_action( 'rest_api_init', 'axismundi_pwa_register_routes' );

/**
 * Register the calling browser.
 *
 * The subscription belongs to whoever is signed in here, taken from the session rather than from
 * the request: a device is claimed by the person at it, and a body that could name somebody else
 * would be a way to have another account's phone woken.
 *
 * @param WP_REST_Request $request Request.
 * @return WP_REST_Response|WP_Error
 */
function axismundi_pwa_rest_subscribe( WP_REST_Request $request ) {
	$capability = axismundi_pwa_capability();
	if ( ! $capability['subscribe'] ) {
		return new WP_Error( 'ax_pwa_unavailable', __( 'This site cannot register devices for push.', 'axismundi-pwa' ), array( 'status' => 503 ) );
	}
	$id = axismundi_pwa_subscribe(
		get_current_user_id(),
		array(
			'endpoint' => (string) $request->get_param( 'endpoint' ),
			'keys'     => (array) $request->get_param( 'keys' ),
		),
		sanitize_text_field( (string) $request->get_header( 'user_agent' ) )
	);
	if ( is_wp_error( $id ) ) {
		return $id;
	}
	/*
	 * The endpoint is not echoed back. It is the credential for waking that browser, and a response
	 * repeating it would put it somewhere a log or a cache could keep it.
	 */
	return new WP_REST_Response( array( 'registered' => $id > 0, 'deliver' => $capability['deliver'] ), 201 );
}

/**
 * Forget the calling browser.
 *
 * @param WP_REST_Request $request Request.
 * @return WP_REST_Response|WP_Error
 */
function axismundi_pwa_rest_unsubscribe( WP_REST_Request $request ) {
	// Scoped to the caller, so one account cannot silence another's device by knowing its endpoint.
	$revoked = axismundi_pwa_revoke( (string) $request->get_param( 'endpoint' ), get_current_user_id() );
	return new WP_REST_Response( array( 'revoked' => $revoked ), 200 );
}
