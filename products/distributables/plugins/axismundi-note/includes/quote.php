<?php
/**
 * Outbound Quote target resolution and three-way classification (FEP-044f).
 *
 * This file resolves who owns a quote target and classifies that ownership
 * against the quoting Note's author. It performs no ledger write, no
 * QuoteRequest emission, and no network fetch: classification is a pure read
 * over data this site already has (its own envelope store and Object
 * Projections' remote-object cache). Slice 3 owns the lifecycle branch that
 * acts on the result -- self-quote (immediate Create), local-other (the
 * synchronous local authorization state machine), or remote (an outbound
 * QuoteRequest).
 *
 * Classification is ownership-only: it does not check whether a local or
 * remote-cache target is published, tombstoned, or otherwise fit to quote (a
 * remote-cache row can itself carry `object_status = tombstone`). Slice 3 must
 * verify the target's federation lifecycle state before acting on any of the
 * three results, not only `self`/`local-other`.
 *
 * @package AxismundiNote
 */

defined( 'ABSPATH' ) || exit;

const AXISMUNDI_NOTE_QUOTE_SELF        = 'self';
const AXISMUNDI_NOTE_QUOTE_LOCAL_OTHER = 'local-other';
const AXISMUNDI_NOTE_QUOTE_REMOTE      = 'remote';

/**
 * Resolve one quote target's owning Actor URI and the origin that vouches for it.
 *
 * A local Note envelope or another registered local Quote target provider is
 * authoritative: this site owns that source and its Actor binding. Any other
 * URI falls back to Object Projections' remote-object cache -- a read of the
 * last stored observation, never a request -- whose `attributedTo` is merely a
 * claim made by the cached payload. The origins stay distinct so a remote
 * payload can never be trusted to assert local ownership.
 *
 * @return array{origin:string,actor_uri:string} origin is 'local', 'remote-cache', or '' (unresolved).
 */
function axismundi_note_quote_target_origin( string $target_uri ) : array {
	$uuid = axismundi_note_local_uuid_from_uri( $target_uri );
	if ( null !== $uuid ) {
		$envelope = axismundi_note_get_by_uuid( $uuid );
		return array(
			'origin'    => 'local',
			'actor_uri' => is_array( $envelope ) ? (string) $envelope['actor_uri'] : '',
		);
	}
	if ( function_exists( 'axismundi_act_resolve_quote_request_target' ) ) {
		$local = axismundi_act_resolve_quote_request_target( $target_uri );
		if ( is_array( $local ) && hash_equals( $target_uri, (string) ( $local['object_uri'] ?? '' ) ) ) {
			return array( 'origin' => 'local', 'actor_uri' => (string) ( $local['author_actor_uri'] ?? '' ) );
		}
	}
	if ( function_exists( 'axismundi_op_remote_object_get' ) ) {
		$row = axismundi_op_remote_object_get( $target_uri );
		if ( is_array( $row ) && ! empty( $row['attributed_to_uri'] ) ) {
			return array( 'origin' => 'remote-cache', 'actor_uri' => (string) $row['attributed_to_uri'] );
		}
	}
	return array( 'origin' => '', 'actor_uri' => '' );
}

/**
 * Classify one authored quote target against the quoting Note's author Actor.
 *
 * Three-way split per the outbound Quote contract: self (no request), local-other
 * (a different local Actor -- runs the local authorization state machine
 * synchronously in slice 3), or remote (an outbound QuoteRequest is sent).
 * `self`/`local-other` are only ever returned for a URI vouched for by a local
 * target provider; a cached remote object can never earn either label no matter
 * what Actor URI its payload claims to be attributed to, so a remote payload
 * cannot spoof its way past the outbound QuoteRequest gate by forging
 * `attributedTo` to a local Actor. `remote` additionally requires a
 * genuinely registered, non-tombstone remote Actor: Bridge can only deliver a
 * QuoteRequest to a known inbox, so an `attributedTo` that names an Actor this
 * site has never registered is unresolved rather than a false `remote` that
 * would record a QuoteRequest with nowhere to deliver it. A target this site
 * cannot yet attribute to any Actor is unresolved and returns a WP_Error; the
 * caller decides whether to fetch and retry.
 *
 * @return string|WP_Error One of the AXISMUNDI_NOTE_QUOTE_* labels.
 */
function axismundi_note_quote_classify( string $target_uri, string $author_actor_uri ) {
	$target_uri       = axismundi_note_sanitize_uri( $target_uri );
	$author_actor_uri = trim( $author_actor_uri );
	if ( '' === $target_uri || '' === $author_actor_uri ) {
		return new WP_Error( 'ax_note_quote_target', __( 'A quote target requires a valid URI and a resolvable author.', 'axismundi-note' ) );
	}
	$origin = axismundi_note_quote_target_origin( $target_uri );
	if ( '' === $origin['actor_uri'] ) {
		return new WP_Error( 'ax_note_quote_unresolved', __( 'The quoted object could not be attributed to an Actor.', 'axismundi-note' ) );
	}
	if ( 'local' === $origin['origin'] ) {
		return hash_equals( $author_actor_uri, $origin['actor_uri'] )
			? AXISMUNDI_NOTE_QUOTE_SELF
			: AXISMUNDI_NOTE_QUOTE_LOCAL_OTHER;
	}
	// A remote-cache origin never earns self/local-other, even when its claimed
	// attributedTo string-matches a local Actor URI: that is a spoofed or stale
	// cache entry, not evidence of local ownership, so it fails closed instead
	// of silently granting the self-quote consent exception.
	$actor = function_exists( 'axismundi_actors_get_by_uri' ) ? axismundi_actors_get_by_uri( $origin['actor_uri'] ) : null;
	if ( ! $actor instanceof Axismundi_Actor ) {
		// An attributedTo claim this site has never registered as an Actor cannot
		// be delivered a QuoteRequest, so it is unresolved rather than a false
		// 'remote' that would hold forever with no addressable inbox.
		return new WP_Error( 'ax_note_quote_unresolved', __( 'The quoted object could not be attributed to an Actor.', 'axismundi-note' ) );
	}
	if ( $actor->is_local() ) {
		return new WP_Error( 'ax_note_quote_origin_mismatch', __( 'A cached remote object cannot be attributed to a local Actor.', 'axismundi-note' ) );
	}
	if ( 'tombstone' === $actor->get_status() ) {
		return new WP_Error( 'ax_note_quote_actor_gone', __( 'The quoted object\'s Actor is no longer available.', 'axismundi-note' ) );
	}
	return AXISMUNDI_NOTE_QUOTE_REMOTE;
}

/** Resolve one active, previously committed local Note target. */
function axismundi_note_quote_local_target( string $target_uri ) {
	$envelope = axismundi_note_get_by_uri( $target_uri );
	$post     = is_array( $envelope ) ? get_post( (int) $envelope['post_id'] ) : null;
	$actor    = is_array( $envelope ) ? axismundi_note_envelope_actor( $envelope ) : null;
	$lifecycle = function_exists( 'axismundi_act_get_object_lifecycle' ) ? axismundi_act_get_object_lifecycle( $target_uri ) : null;
	if ( ! is_array( $envelope )
		|| 'active' !== (string) ( $envelope['object_status'] ?? '' )
		|| ! $post instanceof WP_Post
		|| AXISMUNDI_NOTE_POST_TYPE !== $post->post_type
		|| 'publish' !== $post->post_status
		|| '' !== (string) $post->post_password
		|| empty( $envelope['attribution_locked_at'] )
		|| ! $actor instanceof Axismundi_Actor
		|| ! $actor->is_local()
		|| 'public' !== $actor->get_status()
		|| ! $actor->is_handle_locked()
		|| ! $lifecycle instanceof Axismundi_Activity
		|| 'Delete' === $lifecycle->get_type()
		|| ! hash_equals( $actor->get_uri(), $lifecycle->get_actor_uri() )
	) {
		return new WP_Error( 'ax_note_quote_target_state', __( 'The local quote target is not an active federated Object.', 'axismundi-note' ) );
	}
	return array(
		'object_uri'       => $target_uri,
		'author_actor_uri' => $actor->get_uri(),
		'policy'          => '',
	);
}

/** Supply a Note target to the shared local QuoteRequest decision service. */
function axismundi_note_resolve_quote_request_target( $target, string $object_uri ) {
	if ( null === axismundi_note_local_uuid_from_uri( $object_uri ) ) {
		return $target;
	}
	return axismundi_note_quote_local_target( $object_uri );
}
add_filter( 'axismundi_act_resolve_quote_request_target', 'axismundi_note_resolve_quote_request_target', 20, 2 );

/**
 * Resolve ownership plus the current lifecycle state that permits a quote attempt.
 *
 * @return array{classification:string,origin:string,target_uri:string,target_actor_uri:string,source:array<string,mixed>}|WP_Error
 */
function axismundi_note_quote_target_state( string $target_uri, string $author_actor_uri ) {
	$classification = axismundi_note_quote_classify( $target_uri, $author_actor_uri );
	if ( is_wp_error( $classification ) ) {
		return $classification;
	}
	$origin = axismundi_note_quote_target_origin( $target_uri );
	if ( 'local' === $origin['origin'] ) {
		$local_note = axismundi_note_get_by_uri( $target_uri );
		if ( is_array( $local_note ) ) {
			$source = axismundi_note_quote_local_target( $target_uri );
		} else {
			$source = function_exists( 'axismundi_act_resolve_quote_request_target' ) ? axismundi_act_resolve_quote_request_target( $target_uri ) : null;
		}
		if ( ! is_array( $source ) ) {
			return is_wp_error( $source ) ? $source : new WP_Error( 'ax_note_quote_target_state', __( 'The local quote target is unavailable.', 'axismundi-note' ) );
		}
		return array(
			'classification'   => $classification,
			'origin'           => 'local',
			'target_uri'       => $target_uri,
			'target_actor_uri' => (string) $source['author_actor_uri'],
			'source'           => $source,
		);
	}
	$row = function_exists( 'axismundi_op_remote_object_get' ) ? axismundi_op_remote_object_get( $target_uri ) : null;
	if ( ! is_array( $row )
		|| 'active' !== (string) ( $row['object_status'] ?? '' )
		|| 'Tombstone' === (string) ( $row['object_type'] ?? '' )
		|| ! hash_equals( (string) $origin['actor_uri'], (string) ( $row['attributed_to_uri'] ?? '' ) )
	) {
		return new WP_Error( 'ax_note_quote_target_state', __( 'The remote quote target is not an active cached Object.', 'axismundi-note' ) );
	}
	return array(
		'classification'   => $classification,
		'origin'           => 'remote-cache',
		'target_uri'       => $target_uri,
		'target_actor_uri' => (string) $origin['actor_uri'],
		'source'           => $row,
	);
}

/** Add the interoperable quote declaration and optional authorization evidence. */
function axismundi_note_quote_decorate_object( array $object, string $target_uri, string $authorization_uri = '' ) : array {
	$object['quote']          = $target_uri;
	$object['_misskey_quote'] = $target_uri;
	$object['quoteUrl']       = $target_uri;
	if ( '' !== $authorization_uri ) {
		$object['quoteAuthorization'] = $authorization_uri;
	}
	return $object;
}

/** Re-finalize a decorated Object so OP owns its extension context and sanitization. */
function axismundi_note_quote_finalize_object( array $object ) {
	$id = axismundi_act_member_uri( $object['id'] ?? '' );
	if ( '' === $id || ! function_exists( 'axismundi_op_finalize_object' ) ) {
		return new WP_Error( 'ax_note_quote_projection', __( 'The quoting Note cannot be finalized.', 'axismundi-note' ) );
	}
	return axismundi_op_finalize_object( $object, $id );
}

/** Find or record the one request for the current authored target. */
function axismundi_note_quote_ensure_request( array $object, array $state ) {
	$quoting = axismundi_act_member_uri( $object['id'] ?? '' );
	$quoted  = axismundi_act_member_uri( $state['target_uri'] ?? '' );
	$author = axismundi_act_member_uri( $object['attributedTo'] ?? '' );
	$target = (string) $state['target_actor_uri'];
	$request = function_exists( 'axismundi_act_get_outbound_quote_request' )
		? axismundi_act_get_outbound_quote_request( $quoting, $quoted, $author, $target )
		: null;
	$expected_direction = AXISMUNDI_NOTE_QUOTE_LOCAL_OTHER === $state['classification'] ? 'local' : 'outbound';
	if ( $request instanceof Axismundi_Activity && $expected_direction !== $request->get_direction() ) {
		return new WP_Error( 'ax_note_quote_request_state', __( 'The existing QuoteRequest has an incompatible direction.', 'axismundi-note' ) );
	}
	if ( ! $request instanceof Axismundi_Activity ) {
		$instrument = axismundi_note_quote_finalize_object( axismundi_note_quote_decorate_object( $object, $quoted ) );
		if ( is_wp_error( $instrument ) ) {
			return $instrument;
		}
		$request = axismundi_act_record_outbound_quote_request(
			array(
				'instrument'          => $instrument,
				'quoted_object_uri'   => $quoted,
				'author_actor_uri'    => $author,
				'target_author_uri'   => $target,
				'generation'          => 1,
				'direction'           => $expected_direction,
			)
		);
		if ( is_wp_error( $request ) ) {
			return $request;
		}
	}
	if ( 'local' === $request->get_direction() && null === axismundi_act_outbound_quote_decision( $request->get_uri() ) ) {
		$processed = axismundi_act_process_quote_request( $request );
		if ( is_wp_error( $processed ) ) {
			return $processed;
		}
	}
	return $request;
}

/** Validate one local authorization row against the exact request tuple. */
function axismundi_note_quote_local_authorization( string $authorization_uri, Axismundi_Activity $request, array $state ) {
	$authorization = function_exists( 'axismundi_act_get_quote_authorization' ) ? axismundi_act_get_quote_authorization( $authorization_uri ) : null;
	if ( ! is_array( $authorization )
		|| 'active' !== (string) ( $authorization['status'] ?? '' )
		|| ! hash_equals( $request->get_uri(), (string) ( $authorization['request_activity_uri'] ?? '' ) )
		|| ! hash_equals( $request->get_actor_uri(), (string) ( $authorization['requester_actor_uri'] ?? '' ) )
		|| ! hash_equals( (string) $request->get_instrument_uri(), (string) ( $authorization['quoting_object_uri'] ?? '' ) )
		|| ! hash_equals( (string) $request->get_object_uri(), (string) ( $authorization['quoted_object_uri'] ?? '' ) )
		|| ! hash_equals( (string) $state['target_actor_uri'], (string) ( $authorization['author_actor_uri'] ?? '' ) )
	) {
		return new WP_Error( 'ax_note_quote_authorization', __( 'The local QuoteAuthorization does not match the request.', 'axismundi-note' ) );
	}
	return $authorization_uri;
}

/** Fetch/cache and validate one remote QuoteAuthorization named by a signed Accept. */
function axismundi_note_quote_remote_authorization( string $authorization_uri, Axismundi_Activity $request, array $state ) {
	$row = function_exists( 'axismundi_op_remote_object_get' ) ? axismundi_op_remote_object_get( $authorization_uri ) : null;
	if ( ! is_array( $row ) && function_exists( 'axismundi_op_remote_object_fetch' ) ) {
		$row = axismundi_op_remote_object_fetch( $authorization_uri );
	}
	$payload = is_array( $row ) && is_array( $row['payload'] ?? null ) ? $row['payload'] : array();
	$id      = axismundi_act_member_uri( $payload['id'] ?? '' );
	$author  = axismundi_act_member_uri( $payload['attributedTo'] ?? '' );
	$quoting = axismundi_act_member_uri( $payload['interactingObject'] ?? '' );
	$quoted  = axismundi_act_member_uri( $payload['interactionTarget'] ?? '' );
	if ( ! is_array( $row )
		|| 'active' !== (string) ( $row['object_status'] ?? '' )
		|| 'QuoteAuthorization' !== (string) ( $payload['type'] ?? '' )
		|| ! hash_equals( $authorization_uri, $id )
		|| ! hash_equals( (string) $state['target_actor_uri'], $author )
		|| ! hash_equals( (string) $request->get_instrument_uri(), $quoting )
		|| ! hash_equals( (string) $request->get_object_uri(), $quoted )
	) {
		return new WP_Error( 'ax_note_quote_authorization', __( 'The remote QuoteAuthorization does not match the signed decision.', 'axismundi-note' ) );
	}
	return $authorization_uri;
}

/** Turn the current ledger decision into the Object that may be committed. */
function axismundi_note_quote_commit_object( WP_Post $post, array $object ) {
	$envelope = axismundi_note_get( $post->ID );
	$target   = is_array( $envelope ) ? (string) ( $envelope['quote_target_uri'] ?? '' ) : '';
	if ( '' === $target ) {
		return $object;
	}
	$author = axismundi_act_member_uri( $object['attributedTo'] ?? '' );
	$state  = axismundi_note_quote_target_state( $target, $author );
	if ( is_wp_error( $state ) ) {
		return $state;
	}
	if ( AXISMUNDI_NOTE_QUOTE_SELF === $state['classification'] ) {
		return axismundi_note_quote_finalize_object( axismundi_note_quote_decorate_object( $object, $target ) );
	}
	$request = axismundi_note_quote_ensure_request( $object, $state );
	if ( is_wp_error( $request ) ) {
		return $request;
	}
	$decision = axismundi_act_outbound_quote_decision( $request->get_uri() );
	if ( null === $decision ) {
		return new WP_Error( 'ax_note_quote_pending', __( 'The Note is waiting for quote approval.', 'axismundi-note' ) );
	}
	if ( 'accepted' !== (string) $decision['decision'] ) {
		return new WP_Error( 'ax_note_quote_rejected', __( 'The quote request was rejected.', 'axismundi-note' ) );
	}
	$authorization_uri = (string) $decision['authorization_uri'];
	$authorization = 'local' === $request->get_direction()
		? axismundi_note_quote_local_authorization( $authorization_uri, $request, $state )
		: axismundi_note_quote_remote_authorization( $authorization_uri, $request, $state );
	return is_wp_error( $authorization )
		? $authorization
		: axismundi_note_quote_finalize_object( axismundi_note_quote_decorate_object( $object, $target, $authorization_uri ) );
}

/** Whether a lifecycle result is an intentional held state rather than a write failure. */
function axismundi_note_quote_is_held_error( $result ) : bool {
	return is_wp_error( $result ) && in_array( $result->get_error_code(), array( 'ax_note_quote_pending', 'ax_note_quote_rejected' ), true );
}

/** Reconcile a published Note after an outbound decision signal or later retry. */
function axismundi_note_reconcile_quote( int $post_id ) {
	$post = get_post( $post_id );
	return $post instanceof WP_Post && 'publish' === $post->post_status
		? axismundi_note_record_commit( $post )
		: new WP_Error( 'ax_note_quote_post', __( 'The quoting Note is not published.', 'axismundi-note' ) );
}

/** Wake the held Note whose inlined Object belongs to this plugin. */
function axismundi_note_quote_decided( Axismundi_Activity $request, string $decision, string $authorization_uri ) : void {
	unset( $decision, $authorization_uri );
	$envelope = axismundi_note_get_by_uri( (string) $request->get_instrument_uri() );
	if ( ! is_array( $envelope ) ) {
		return;
	}
	$post   = get_post( (int) $envelope['post_id'] );
	$result = $post instanceof WP_Post ? axismundi_note_reconcile_quote( $post->ID ) : null;
	if ( is_wp_error( $result ) && ! axismundi_note_quote_is_held_error( $result ) && $post instanceof WP_Post ) {
		axismundi_note_lifecycle_failed( $result, $post );
	}
}
add_action( 'axismundi_act_outbound_quote_decided', 'axismundi_note_quote_decided', 20, 3 );
