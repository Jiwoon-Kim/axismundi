<?php
/**
 * FEP-044f QuoteRequest policy decisions.
 *
 * @package AxismundiActivities
 */

defined( 'ABSPATH' ) || exit;

/**
 * Resolve the local quoted Object, author, and explicit policy through its domain owner.
 *
 * Activities owns the decision but never reads WordPress Post metadata or renders an Object.
 *
 * @return array{object_uri:string,author_actor_uri:string,policy:string}|WP_Error
 */
function axismundi_act_resolve_quote_request_target( string $object_uri ) {
	$object_uri = axismundi_act_uri( $object_uri );
	$unresolved = new WP_Error( 'ax_act_quote_target', __( 'The quoted Object and its policy could not be resolved.', 'axismundi-activities' ) );
	if ( '' === $object_uri ) {
		return $unresolved;
	}
	/**
	 * Supply one exact, publicly projectable local Object and its explicit Quote policy.
	 *
	 * @param array|WP_Error $target     Fail-closed default or provider result.
	 * @param string         $object_uri Canonical quoted Object URI.
	 */
	$target = apply_filters( 'axismundi_act_resolve_quote_request_target', $unresolved, $object_uri );
	if ( ! is_array( $target ) ) {
		return is_wp_error( $target ) ? $target : $unresolved;
	}
	$resolved_object = axismundi_act_uri( (string) ( $target['object_uri'] ?? '' ) );
	$author_uri      = axismundi_act_uri( (string) ( $target['author_actor_uri'] ?? '' ) );
	$policy          = sanitize_key( (string) ( $target['policy'] ?? '' ) );
	$author          = '' !== $author_uri ? axismundi_actors_get_by_uri( $author_uri ) : null;
	if ( ! hash_equals( $object_uri, $resolved_object )
		|| ! $author instanceof Axismundi_Actor
		|| ! $author->is_local()
		|| 'public' !== $author->get_status()
		|| ! $author->is_handle_locked()
		|| ! in_array( $policy, array( '', 'anyone', 'followers', 'me' ), true )
	) {
		return $unresolved;
	}
	return array( 'object_uri' => $resolved_object, 'author_actor_uri' => $author_uri, 'policy' => $policy );
}

/** Existing outbound Accept or Reject for a QuoteRequest URI. */
function axismundi_act_get_quote_request_decision( string $request_uri ) : ?Axismundi_Activity {
	$request_uri = axismundi_act_uri( $request_uri );
	if ( '' === $request_uri ) {
		return null;
	}
	foreach ( axismundi_act_get_by_object( $request_uri, 50 ) as $activity ) {
		if ( in_array( $activity->get_type(), array( 'Accept', 'Reject' ), true )
			&& in_array( $activity->get_direction(), array( 'outbound', 'local' ), true )
		) {
			return $activity;
		}
	}
	return null;
}

/** Ensure an inlined quote post, when present, does not contradict its request. */
function axismundi_act_validate_quote_request_instrument( Axismundi_Activity $request ) {
	$payload    = $request->get_payload();
	$instrument = $payload['instrument'] ?? null;
	if ( ! is_array( $instrument ) || array_is_list( $instrument ) ) {
		return true;
	}
	$attributed_to = axismundi_act_member_uri( $instrument['attributedTo'] ?? '' );
	if ( '' !== $attributed_to && ! hash_equals( $request->get_actor_uri(), $attributed_to ) ) {
		return new WP_Error( 'ax_act_quote_instrument_actor', __( 'The inlined quote post is attributed to a different Actor.', 'axismundi-activities' ) );
	}
	$quoted = axismundi_act_member_uri( $instrument['quote'] ?? '' );
	if ( '' !== $quoted && ! hash_equals( (string) $request->get_object_uri(), $quoted ) ) {
		return new WP_Error( 'ax_act_quote_instrument_target', __( 'The inlined quote post references a different quoted Object.', 'axismundi-activities' ) );
	}
	return true;
}

/** Decide whether one valid request is automatically approved by the explicit policy. */
function axismundi_act_quote_request_is_approved( Axismundi_Activity $request, array $target ) : bool {
	$requester = $request->get_actor_uri();
	$author    = (string) $target['author_actor_uri'];
	if ( hash_equals( $requester, $author ) ) {
		return true;
	}
	if ( 'anyone' === (string) $target['policy'] ) {
		return true;
	}
	if ( 'followers' !== (string) $target['policy'] ) {
		return false;
	}
	$relation = axismundi_act_get_relation( 'follow', $requester, $author );
	return is_array( $relation ) && 'accepted' === (string) ( $relation['state'] ?? '' );
}

/** Minimal FEP-044f QuoteRequest object embedded in an Accept or Reject. */
function axismundi_act_quote_request_response_object( Axismundi_Activity $request ) : array {
	return array(
		'id'         => $request->get_uri(),
		'type'       => 'QuoteRequest',
		'actor'      => $request->get_actor_uri(),
		'object'     => (string) $request->get_object_uri(),
		'instrument' => (string) $request->get_instrument_uri(),
	);
}

/**
 * Process one committed inbound QuoteRequest into one stable Accept or Reject.
 *
 * @return Axismundi_Activity|WP_Error
 */
function axismundi_act_process_quote_request( Axismundi_Activity $request ) {
	if ( 'QuoteRequest' !== $request->get_type() || ! in_array( $request->get_direction(), array( 'inbound', 'local' ), true ) ) {
		return new WP_Error( 'ax_act_quote_request', __( 'Only a committed inbound or local QuoteRequest can be decided.', 'axismundi-activities' ) );
	}
	$request_uri = $request->get_uri();
	$quoted_uri  = (string) $request->get_object_uri();
	$quoting_uri = (string) $request->get_instrument_uri();
	if ( '' === $quoted_uri || '' === $quoting_uri || hash_equals( $quoted_uri, $quoting_uri ) ) {
		return new WP_Error( 'ax_act_quote_request_members', __( 'A QuoteRequest requires distinct quoted and quoting Objects.', 'axismundi-activities' ) );
	}
	$instrument_valid = axismundi_act_validate_quote_request_instrument( $request );
	if ( is_wp_error( $instrument_valid ) ) {
		return $instrument_valid;
	}
	$existing = axismundi_act_get_quote_request_decision( $request_uri );
	if ( $existing instanceof Axismundi_Activity ) {
		return $existing;
	}
	$authorization = axismundi_act_get_quote_authorization_for_request( $request_uri );
	if ( is_array( $authorization ) ) {
		// Recover a crash between issuing consent and recording its Accept. The issued
		// authorization is the decision; a later policy edit must not reverse it.
		if ( 'active' !== (string) ( $authorization['status'] ?? '' )
			|| ! hash_equals( $quoted_uri, (string) ( $authorization['quoted_object_uri'] ?? '' ) )
			|| ! hash_equals( $quoting_uri, (string) ( $authorization['quoting_object_uri'] ?? '' ) )
			|| ! hash_equals( $request->get_actor_uri(), (string) ( $authorization['requester_actor_uri'] ?? '' ) )
		) {
			return new WP_Error( 'ax_act_quote_authorization_state', __( 'The request identifies an authorization that cannot be completed.', 'axismundi-activities' ) );
		}
		$target   = array( 'object_uri' => $quoted_uri, 'author_actor_uri' => (string) $authorization['author_actor_uri'], 'policy' => '' );
		$approved = true;
	} else {
		$target = axismundi_act_resolve_quote_request_target( $quoted_uri );
		if ( is_wp_error( $target ) ) {
			return $target;
		}
		$standing = axismundi_act_get_active_quote_authorization( $quoting_uri, $quoted_uri, (string) $target['author_actor_uri'] );
		if ( is_array( $standing ) ) {
			$authorization = $standing;
			$approved      = true;
		} else {
			$approved = axismundi_act_quote_request_is_approved( $request, $target );
		}
	}
	$payload  = array(
		'type'   => $approved ? 'Accept' : 'Reject',
		'actor'  => (string) $target['author_actor_uri'],
		'object' => axismundi_act_quote_request_response_object( $request ),
		'to'     => array( $request->get_actor_uri() ),
	);
	if ( $approved ) {
		if ( ! is_array( $authorization ) ) {
			$authorization = axismundi_act_issue_quote_authorization(
				array(
					'request_activity_uri' => $request_uri,
					'quoting_object_uri'   => $quoting_uri,
					'quoted_object_uri'    => $quoted_uri,
					'requester_actor_uri'  => $request->get_actor_uri(),
					'author_actor_uri'     => (string) $target['author_actor_uri'],
				)
			);
			if ( is_wp_error( $authorization ) ) {
				return $authorization;
			}
		}
		if ( 'active' !== (string) ( $authorization['status'] ?? '' ) ) {
			return new WP_Error( 'ax_act_quote_authorization_revoked', __( 'The request already identifies a revoked authorization.', 'axismundi-activities' ) );
		}
		$payload['result'] = (string) $authorization['authorization_uri'];
	}
	$direction = 'local' === $request->get_direction() ? 'local' : 'outbound';
	return axismundi_act_record_source_activity( $payload, $direction, 'quote-request-decision:' . hash( 'sha256', $request_uri ) );
}

/** Process a newly committed QuoteRequest after the inbound ledger transaction closes. */
function axismundi_act_maybe_process_quote_request( Axismundi_Activity $activity ) : void {
	if ( 'QuoteRequest' === $activity->get_type() && 'inbound' === $activity->get_direction() ) {
		axismundi_act_process_quote_request( $activity );
	}
}
add_action( 'axismundi_act_activity_recorded', 'axismundi_act_maybe_process_quote_request', 15 );
