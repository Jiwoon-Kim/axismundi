<?php
/**
 * Reaction aggregate for the front end.
 *
 * The FEP-c0e0 collection is the federated view: whole Activities, for peers that want
 * the evidence. This is the other half — what a reader's browser needs to draw a row of
 * chips, which is counts and one presentation per reaction key.
 *
 * Kept apart from the collection on purpose. Feeding a UI from the Activity list would
 * push per-Actor identities into every page render to compute a number the ledger can
 * already produce with one indexed aggregate, and would tie a rendering concern to a
 * federation document that must not change shape for it.
 *
 * @package AxismundiActivities
 */

defined( 'ABSPATH' ) || exit;

/**
 * Who may react, named separately from who may act socially in general.
 *
 * The two answers are the same today: a social Activity belongs to an activated public
 * Person, and reacting is a social Activity. The name is kept because other plugins ask
 * this question — the Emoji catalogue gates its picker on it — and "may this visitor
 * react" is what they mean, not "does this visitor pass whatever Follow currently
 * requires". Delegating rather than restating keeps one condition: two copies of it drifted
 * apart once already, when reacting was briefly widened and Follow was not.
 */
function axismundi_act_current_reaction_actor() : ?Axismundi_Actor {
	return axismundi_act_current_local_actor();
}

/** REST permission for a reaction mutation. */
function axismundi_act_reaction_rest_permission() : bool {
	return axismundi_act_current_reaction_actor() instanceof Axismundi_Actor;
}

/**
 * How one reaction key should be shown.
 *
 * Unicode reactions are text and need nothing. Custom ones are somebody else's image,
 * and this is where the Emoji plugin's review gate applies: a declaration that is
 * pending, rejected, or whose bytes were never cached renders as its shortcode, exactly
 * as it does inline. A chip is not a special surface that gets to skip that.
 *
 * The lookup is deliberately exact. A reaction key always carries its authority, so
 * there is no bare-name fallback here — standing in another server's picture for
 * `custom:a.example:cat` would redraw someone's reaction as a different animal.
 *
 * @param string $reaction_key Stored chip identity.
 * @param string $reaction_raw Content as its sender wrote it.
 * @return array{kind:string,label:string,image:array{url:string,static_url:string}|null}
 */
function axismundi_act_reaction_presentation( string $reaction_key, string $reaction_raw ) : array {
	$plain = array( 'kind' => 'unicode', 'label' => $reaction_raw, 'image' => null );
	if ( ! str_starts_with( $reaction_key, 'custom:' ) ) {
		return $plain;
	}

	/*
	 * `custom:{authority}:{shortcode key}`, split from the right. Splitting left-to-right
	 * would work only while no authority carries a port, and `host:port` is a legitimate
	 * authority -- it is what this site reports for itself when it runs on one.
	 */
	// A custom key stays custom even when it is malformed: it is still not a Unicode
	// emoji, and calling it one would tell the UI to render the key as text.
	$presentation = array( 'kind' => 'custom', 'label' => $reaction_raw, 'image' => null );
	$rest         = substr( $reaction_key, strlen( 'custom:' ) );
	$separator    = strrpos( $rest, ':' );
	if ( false === $separator || 0 === $separator || $separator === strlen( $rest ) - 1 ) {
		return $presentation;
	}
	$authority = substr( $rest, 0, $separator );
	$shortcode = substr( $rest, $separator + 1 );
	if ( ! function_exists( 'axismundi_emoji_reaction_presentation_row' ) ) {
		return $presentation;
	}
	/*
	 * The Emoji plugin decides what may be drawn, including whether a designated authority's
	 * copy may stand in for a declaration whose own bytes we never cached. Everything
	 * identifying the reaction is settled above and is not affected by that answer: the key
	 * still names the declaring authority, the label is still the shortcode its sender
	 * wrote, and the count is still counted per key.
	 */
	$row = axismundi_emoji_reaction_presentation_row( $authority, $shortcode );
	if ( ! is_array( $row ) ) {
		return $presentation;
	}
	$presentation['image'] = array(
		'url'        => axismundi_emoji_file_url( $row ),
		// The still, for `prefers-reduced-motion`. Empty when the original is not animated.
		'static_url' => axismundi_emoji_static_url( $row ),
		/*
		 * Which authority's file is actually on screen. Named because it is the one thing a
		 * borrowed image changes, and a reader deserves to be able to tell -- `alt` still
		 * says `:misskey:` from hoto.moe while the pixels may come from misskey.io.
		 */
		'authority'  => (string) ( $row['emoji_authority'] ?? $authority ),
		'borrowed'   => (string) ( $row['emoji_authority'] ?? $authority ) !== $authority,
	);
	return $presentation;
}

/**
 * The whole reaction state of one Object, from one reader's point of view.
 *
 * `mine` is always the current Actor's own reactions and is never parameterised. An
 * endpoint that answered "what did this other Actor react with" would be a surveillance
 * tool built out of an aggregate, so the question is simply not askable.
 *
 * @param string             $object_uri Reacted-to object.
 * @param Axismundi_Actor|null $actor    Reader's local Actor, if any.
 * @return array<string,mixed>
 */
function axismundi_act_object_reaction_summary( string $object_uri, ?Axismundi_Actor $actor = null ) : array {
	$mine = $actor instanceof Axismundi_Actor
		? axismundi_act_get_actor_reactions( $actor->get_uri(), $object_uri )
		: array();
	$chips = array();
	foreach ( axismundi_act_get_reaction_counts( $object_uri ) as $chip ) {
		$key           = (string) $chip['reaction_key'];
		$presentation  = axismundi_act_reaction_presentation( $key, (string) $chip['reaction_raw'] );
		$chips[]       = array(
			'key'   => $key,
			'kind'  => $presentation['kind'],
			'label' => $presentation['label'],
			'image' => $presentation['image'],
			'count' => (int) $chip['count'],
			// Derived from the same list `mine` reports, so the two cannot drift apart.
			'mine'  => in_array( $key, $mine, true ),
		);
	}
	return array(
		'object_uri' => $object_uri,
		// Plain Likes only. FEP-c0e0 turns a `Like` with content into a reaction, so this
		// number and the chips partition the ledger rather than overlapping.
		'like_count' => axismundi_act_get_like_count( $object_uri ),
		'chips'      => $chips,
		'mine'       => array_values( $mine ),
		/*
		 * Whether the object stays on this site, which decides whether an emoji withheld
		 * from publication may be used on it. Part of the summary rather than the block's
		 * seed data so that the mutation response carries it too: a picker that lost this
		 * after the reader's first pick would start offering what the server then refuses.
		 */
		'is_local'   => axismundi_act_object_is_local( $object_uri ),
	);
}

/** Register the read-only reaction aggregate endpoint. */
function axismundi_act_register_reaction_rest_route() : void {
	register_rest_route(
		'axismundi/v1',
		'/reactions',
		array(
			array(
				'methods'  => WP_REST_Server::READABLE,
				'callback' => 'axismundi_act_rest_object_reactions',
				// Counts are already public: the same reactions are published in this
				// Object's FEP-c0e0 collection. What is gated is the object itself, below.
				'permission_callback' => '__return_true',
				'args'                => array( 'object_uri' => array( 'required' => true, 'type' => 'string', 'format' => 'uri' ) ),
			),
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => 'axismundi_act_rest_react',
				'permission_callback' => 'axismundi_act_reaction_rest_permission',
				'args'                => array(
					'object_uri' => array( 'required' => true, 'type' => 'string', 'format' => 'uri' ),
					// Not sanitized to a slug or stripped: an emoji is the content, and a
					// shortcode's colons are part of it. Validity is decided by the reaction
					// normalizer, which is the one place that knows what a reaction is.
					'content'    => array( 'required' => true, 'type' => 'string' ),
				),
			),
			array(
				'methods'             => WP_REST_Server::DELETABLE,
				'callback'            => 'axismundi_act_rest_unreact',
				'permission_callback' => 'axismundi_act_reaction_rest_permission',
				'args'                => array(
					'object_uri'   => array( 'required' => true, 'type' => 'string', 'format' => 'uri' ),
					// The key, not the content: an Actor may hold several reactions at once,
					// so naming only the object would be ambiguous about which to withdraw.
					'reaction_key' => array( 'required' => true, 'type' => 'string' ),
				),
			),
		)
	);
}
add_action( 'rest_api_init', 'axismundi_act_register_reaction_rest_route' );

/**
 * Answer the reaction aggregate for one Object.
 *
 * The object is resolved through the same gate the Like mutation uses, so an Object that
 * cannot be interacted with — unprojected, non-public, tombstoned, or a remote cache
 * entry that is no longer active — cannot have its reactions read either.
 */
function axismundi_act_rest_object_reactions( WP_REST_Request $request ) {
	$target = axismundi_act_resolve_like_target( (string) $request['object_uri'] );
	if ( is_wp_error( $target ) ) {
		return $target;
	}
	$actor    = axismundi_act_current_reaction_actor();
	$response = new WP_REST_Response( axismundi_act_object_reaction_summary( (string) $target['object_uri'], $actor instanceof Axismundi_Actor ? $actor : null ), 200 );
	/*
	 * `mine` makes every response reader-specific, so a shared cache holding one would
	 * show the next visitor somebody else's reactions as their own.
	 */
	$response->header( 'Cache-Control', 'private, no-store, max-age=0' );
	return $response;
}

/**
 * Both mutations answer with the whole aggregate, not just what changed.
 *
 * A reaction moves two numbers that are already on screen — its own chip and, when the
 * chip is new or gone, the shape of the row — and the client has no way to derive the
 * second from a per-chip response. Returning the authoritative state means the UI never
 * has to guess and never drifts from the ledger.
 */
function axismundi_act_reaction_mutation_response( Axismundi_Actor $actor, string $object_uri ) : WP_REST_Response {
	$response = new WP_REST_Response( axismundi_act_object_reaction_summary( $object_uri, $actor ), 200 );
	$response->header( 'Cache-Control', 'private, no-store, max-age=0' );
	return $response;
}

/** Add one reaction from the current Actor. */
function axismundi_act_rest_react( WP_REST_Request $request ) {
	$actor  = axismundi_act_current_reaction_actor();
	$target = axismundi_act_resolve_like_target( (string) $request['object_uri'] );
	if ( ! $actor instanceof Axismundi_Actor || is_wp_error( $target ) ) {
		return is_wp_error( $target ) ? $target : new WP_Error( 'ax_act_reaction_actor', __( 'No active local Actor is available.', 'axismundi-activities' ), array( 'status' => 403 ) );
	}
	$activity = axismundi_act_react_to_object( $actor, (string) $target['object_uri'], (string) $request['content'], (string) $target['recipient_uri'] );
	return is_wp_error( $activity ) ? $activity : axismundi_act_reaction_mutation_response( $actor, (string) $target['object_uri'] );
}

/** Withdraw one reaction from the current Actor. */
function axismundi_act_rest_unreact( WP_REST_Request $request ) {
	$actor  = axismundi_act_current_reaction_actor();
	$target = axismundi_act_resolve_like_target( (string) $request['object_uri'] );
	if ( ! $actor instanceof Axismundi_Actor || is_wp_error( $target ) ) {
		return is_wp_error( $target ) ? $target : new WP_Error( 'ax_act_reaction_actor', __( 'No active local Actor is available.', 'axismundi-activities' ), array( 'status' => 403 ) );
	}
	$activity = axismundi_act_unreact_to_object( $actor, (string) $target['object_uri'], (string) $request['reaction_key'] );
	return is_wp_error( $activity ) ? $activity : axismundi_act_reaction_mutation_response( $actor, (string) $target['object_uri'] );
}
