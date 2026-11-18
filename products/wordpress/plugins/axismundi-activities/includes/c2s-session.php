<?php
/** Axismundi's authenticated discovery of local ActivityPub submission endpoints. */
defined( 'ABSPATH' ) || exit;

/** In-process projection for the authenticated User, independent of browser selection. */
function axismundi_act_c2s_session() {
	$user_id = get_current_user_id();
	if ( $user_id <= 0 ) {
		return new WP_Error( 'ax_c2s_authentication', 'Authentication is required.', array( 'status' => 401 ) );
	}
	if ( ! function_exists( 'axismundi_actors_account_access_ready' ) || ! axismundi_actors_account_access_ready() ) {
		return new WP_Error( 'ax_c2s_session_unavailable', 'Actor discovery is unavailable.', array( 'status' => 503 ) );
	}
	$actors = array();
	foreach ( axismundi_actors_workspace_options( $user_id ) as $actor ) {
		if ( true !== axismundi_act_outbox_authorize( $user_id, $actor ) ) {
			continue;
		}
		$actors[] = array( 'id' => $actor->get_uri() );
	}
	return array( 'actors' => $actors );
}

add_action( 'rest_api_init', static function () : void {
	register_rest_route( 'axismundi/v1', '/users/me', array(
		'methods'             => WP_REST_Server::READABLE,
		'permission_callback' => 'axismundi_act_c2s_transport_permission',
		'callback'            => static function () {
			$session = axismundi_act_c2s_session();
			if ( is_wp_error( $session ) ) {
				return $session;
			}
			return new WP_REST_Response(
				array(
					'@context' => array(
						'https://www.w3.org/ns/activitystreams',
						array(
							'c2s'             => 'https://github.com/Jiwoon-Kim/axismundi#c2s-',
							'actors'          => array( '@id' => 'c2s:actors', '@container' => '@set' ),
						),
					),
					'type'     => 'c2s:Session',
					'url'      => rest_url( 'axismundi/v1/users/me' ),
					'actors'   => $session['actors'],
				),
				200,
				array(
					'Content-Type'  => 'application/ld+json; profile="https://www.w3.org/ns/activitystreams"',
					'Cache-Control' => 'private, no-store',
				)
			);
		},
	) );
} );
