<?php
/**
 * An Event may not be published without its envelope.
 *
 * Without this, publishing is silently ineffective. `axismundi_cal_event_transformer_supports()`
 * requires an envelope, and nothing else claims this post type -- the Core Post transformer gates
 * on `post` -- so an Event published with no times projects to no Object at all. The author gets a
 * public, permalinked page and no federation, no index row and no error: the page looks right, and
 * the Event does not exist to any peer.
 *
 * Fail-closed by keeping the post out of `publish` rather than by publishing and warning, because
 * the wrong state here is one that looks correct from the editor.
 *
 * @package AxismundiCalendar
 */

defined( 'ABSPATH' ) || exit;

/** @var bool Request-local guard while an Event REST write owns persistence. */
$GLOBALS['axismundi_cal_rest_write'] = false;

/**
 * Clear the request-local guard, even when a field callback failed.
 *
 * @param mixed                $response Response.
 * @param array<string,mixed>  $handler  Route handler.
 * @param WP_REST_Request      $request  Request.
 * @return mixed
 */
function axismundi_cal_clear_rest_write( $response, $handler, WP_REST_Request $request ) {
	if ( preg_match( '#^/wp/v2/' . preg_quote( AXISMUNDI_CAL_EVENT_POST_TYPE, '#' ) . '(?:/|$)#', $request->get_route() ) ) {
		$GLOBALS['axismundi_cal_rest_write'] = false;
	}
	return $response;
}
add_filter( 'rest_request_after_callbacks', 'axismundi_cal_clear_rest_write', 100, 3 );

/**
 * Refuse a REST publish of an Event whose envelope will still be missing afterwards.
 *
 * Checked on the incoming request rather than on stored state because the envelope field is an
 * additional REST field: it is written *after* the post is inserted, so at insert time a first
 * publish legitimately has no stored envelope yet. The request is what knows whether one is
 * arriving in the same write.
 *
 * @param stdClass        $prepared Post about to be inserted.
 * @param WP_REST_Request $request  Request.
 * @return stdClass|WP_Error
 */
function axismundi_cal_guard_rest_publish( $prepared, WP_REST_Request $request ) {
	// Set before any decision: this marks the REST write as the owner of persistence for the rest
	// of the request, which is what tells the non-REST guard below to stand down. The envelope
	// arrives as an additional REST field, written after the insert, so that guard would otherwise
	// see a publish with no stored envelope and hold back a perfectly complete Event.
	$GLOBALS['axismundi_cal_rest_write'] = true;
	if ( ! is_object( $prepared ) || 'publish' !== (string) ( $prepared->post_status ?? '' ) ) {
		return $prepared;
	}
	$post_id = (int) ( $prepared->ID ?? 0 );
	if ( $post_id > 0 && null !== axismundi_cal_event_get( $post_id ) ) {
		return $prepared;
	}
	$incoming = $request['axismundi_cal_envelope'];
	if ( is_array( $incoming ) && axismundi_cal_rest_envelope_is_writable( $incoming ) ) {
		return $prepared;
	}
	return new WP_Error(
		'ax_event_incomplete',
		__( 'An Event needs a start, an end and a timezone before it can be published.', 'axismundi-calendar' ),
		array( 'status' => 400 )
	);
}
add_filter( 'rest_pre_insert_' . AXISMUNDI_CAL_EVENT_POST_TYPE, 'axismundi_cal_guard_rest_publish', 10, 2 );

/**
 * Hold back a non-REST publish of an envelope-less Event.
 *
 * The block editor goes through the filter above, which can report why. Quick Edit, the classic
 * screen and `wp_insert_post()` callers cannot be told anything useful mid-write, so the status is
 * held at `draft` instead -- visibly not-published, rather than published and invisible.
 *
 * The REST exemption is the request-local flag rather than `wp_is_serving_rest_request()`, which
 * answers a different question: it reports whether the REST constant is defined, so an internally
 * dispatched request -- WP-CLI, an importer, a test -- reads as non-REST and would have its
 * complete Event held at draft. The flag tracks the write, not the transport.
 *
 * @param array<string,mixed> $data    Sanitized post data.
 * @param array<string,mixed> $postarr Raw post data.
 * @return array<string,mixed>
 */
function axismundi_cal_guard_publish( array $data, array $postarr ) : array {
	if ( AXISMUNDI_CAL_EVENT_POST_TYPE !== ( $data['post_type'] ?? '' ) || 'publish' !== ( $data['post_status'] ?? '' ) ) {
		return $data;
	}
	if ( ! empty( $GLOBALS['axismundi_cal_rest_write'] ) ) {
		return $data;
	}
	$post_id = (int) ( $postarr['ID'] ?? 0 );
	if ( $post_id > 0 && null !== axismundi_cal_event_get( $post_id ) ) {
		return $data;
	}
	$data['post_status'] = 'draft';
	return $data;
}
add_filter( 'wp_insert_post_data', 'axismundi_cal_guard_publish', 10, 2 );
