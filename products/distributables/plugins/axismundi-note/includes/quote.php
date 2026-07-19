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
 * @package AxismundiNote
 */

defined( 'ABSPATH' ) || exit;

const AXISMUNDI_NOTE_QUOTE_SELF        = 'self';
const AXISMUNDI_NOTE_QUOTE_LOCAL_OTHER = 'local-other';
const AXISMUNDI_NOTE_QUOTE_REMOTE      = 'remote';

/**
 * Resolve the owning Actor URI for one quote target without any network fetch.
 *
 * A local Note resolves from its own envelope, the authoritative no-fetch path.
 * Any other URI falls back to Object Projections' remote-object cache, itself a
 * read of the last stored observation, never a request. An address this site
 * has never cached and does not own resolves to ''.
 */
function axismundi_note_quote_target_actor_uri( string $target_uri ) : string {
	$uuid = axismundi_note_local_uuid_from_uri( $target_uri );
	if ( null !== $uuid ) {
		$envelope = axismundi_note_get_by_uuid( $uuid );
		return is_array( $envelope ) ? (string) $envelope['actor_uri'] : '';
	}
	if ( function_exists( 'axismundi_op_remote_object_get' ) ) {
		$row = axismundi_op_remote_object_get( $target_uri );
		if ( is_array( $row ) && ! empty( $row['attributed_to_uri'] ) ) {
			return (string) $row['attributed_to_uri'];
		}
	}
	return '';
}

/**
 * Classify one authored quote target against the quoting Note's author Actor.
 *
 * Three-way split per the outbound Quote contract: self (no request), local-other
 * (a different local Actor -- runs the local authorization state machine
 * synchronously in slice 3), or remote (an outbound QuoteRequest is sent). A
 * target this site cannot yet attribute to any Actor is unresolved and returns
 * a WP_Error; the caller decides whether to fetch and retry.
 *
 * @return string|WP_Error One of the AXISMUNDI_NOTE_QUOTE_* labels.
 */
function axismundi_note_quote_classify( string $target_uri, string $author_actor_uri ) {
	$target_uri       = axismundi_note_sanitize_uri( $target_uri );
	$author_actor_uri = trim( $author_actor_uri );
	if ( '' === $target_uri || '' === $author_actor_uri ) {
		return new WP_Error( 'ax_note_quote_target', __( 'A quote target requires a valid URI and a resolvable author.', 'axismundi-note' ) );
	}
	$target_actor_uri = axismundi_note_quote_target_actor_uri( $target_uri );
	if ( '' === $target_actor_uri ) {
		return new WP_Error( 'ax_note_quote_unresolved', __( 'The quoted object could not be attributed to an Actor.', 'axismundi-note' ) );
	}
	if ( hash_equals( $author_actor_uri, $target_actor_uri ) ) {
		return AXISMUNDI_NOTE_QUOTE_SELF;
	}
	$actor = function_exists( 'axismundi_actors_get_by_uri' ) ? axismundi_actors_get_by_uri( $target_actor_uri ) : null;
	return $actor instanceof Axismundi_Actor && $actor->is_local()
		? AXISMUNDI_NOTE_QUOTE_LOCAL_OTHER
		: AXISMUNDI_NOTE_QUOTE_REMOTE;
}
