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
 * Classification is ownership-only: it does not check whether a local target
 * is published, tombstoned, or otherwise fit to quote. Slice 3 must verify the
 * target's federation lifecycle state before acting on a `self` or
 * `local-other` result.
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
 * A local Note's own envelope is authoritative: this site minted that row, so
 * the Actor it names is trustworthy by construction. Any other URI falls back
 * to Object Projections' remote-object cache -- a read of the last stored
 * observation, never a request -- whose `attributedTo` is merely a claim made
 * by whatever payload was cached. The two origins are kept distinct so a
 * remote payload can never be trusted to assert local ownership.
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
 * `self`/`local-other` are only ever returned for a URI that resolves through
 * this site's own Note envelope; a cached remote object can never earn either
 * label no matter what Actor URI its payload claims to be attributed to, so a
 * remote payload cannot spoof its way past the outbound QuoteRequest gate by
 * forging `attributedTo` to a local Actor. A target this site cannot yet
 * attribute to any Actor is unresolved and returns a WP_Error; the caller
 * decides whether to fetch and retry.
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
	if ( $actor instanceof Axismundi_Actor && $actor->is_local() ) {
		return new WP_Error( 'ax_note_quote_origin_mismatch', __( 'A cached remote object cannot be attributed to a local Actor.', 'axismundi-note' ) );
	}
	return AXISMUNDI_NOTE_QUOTE_REMOTE;
}
