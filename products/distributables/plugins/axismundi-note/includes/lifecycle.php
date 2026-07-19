<?php
/**
 * Note-owned Create, Update, and Delete lifecycle emission.
 *
 * @package AxismundiNote
 */

defined( 'ABSPATH' ) || exit;

/** Surface a lifecycle failure without turning a committed local post into delivery state. */
function axismundi_note_lifecycle_failed( WP_Error $error, WP_Post $post ) : void {
	/** @param WP_Error $error @param WP_Post $post Failed Note lifecycle commit. */
	do_action( 'axismundi_note_lifecycle_failed', $error, $post );
}

/**
 * Build the strict, finalized Object snapshot used by an immutable Activity.
 *
 * @return array<string,mixed>|WP_Error
 */
function axismundi_note_lifecycle_object( WP_Post $post ) {
	$prepared = axismundi_note_prepare_for_federation( $post );
	if ( is_wp_error( $prepared ) ) {
		return $prepared;
	}
	$envelope = axismundi_note_get( $post->ID );
	if ( ! is_array( $envelope ) ) {
		return new WP_Error( 'ax_note_envelope', __( 'The Note envelope is unavailable at the lifecycle boundary.', 'axismundi-note' ) );
	}
	$strict_mentions = axismundi_note_mention_tags( $post, true );
	if ( is_wp_error( $strict_mentions ) ) {
		return $strict_mentions;
	}
	$source = new Axismundi_Note_Source( $envelope, $post );
	$object = axismundi_note_transform_source( $source );
	if ( is_wp_error( $object ) || ! is_array( $object ) ) {
		return is_wp_error( $object ) ? $object : new WP_Error( 'ax_note_projection', __( 'The Note could not be projected at the lifecycle boundary.', 'axismundi-note' ) );
	}
	return function_exists( 'axismundi_op_finalize_object' )
		? axismundi_op_finalize_object( $object, $source->get_uri() )
		: new WP_Error( 'ax_note_projection', __( 'Object Projections is unavailable at the lifecycle boundary.', 'axismundi-note' ) );
}

/** Record the current published Note as Create or Update. */
function axismundi_note_record_commit( WP_Post $post ) {
	if ( ! function_exists( 'axismundi_act_record_object_commit' ) ) {
		return new WP_Error( 'ax_note_activities', __( 'The Activity lifecycle service is unavailable.', 'axismundi-note' ) );
	}
	$object = axismundi_note_lifecycle_object( $post );
	if ( is_wp_error( $object ) ) {
		return $object;
	}
	$object = function_exists( 'axismundi_note_quote_commit_object' ) ? axismundi_note_quote_commit_object( $post, $object ) : $object;
	return is_wp_error( $object ) ? $object : axismundi_act_record_object_commit( $object );
}

/** Record a Delete from the frozen Note identity and the ledger's last audience. */
function axismundi_note_record_delete( int $post_id ) {
	$envelope = axismundi_note_get( $post_id );
	if ( ! is_array( $envelope ) || empty( $envelope['attribution_locked_at'] ) ) {
		return null;
	}
	if ( ! function_exists( 'axismundi_act_record_object_delete' ) ) {
		return new WP_Error( 'ax_note_activities', __( 'The Activity lifecycle service is unavailable.', 'axismundi-note' ) );
	}
	return axismundi_act_record_object_delete(
		axismundi_note_object_uri( (string) $envelope['local_uuid'] ),
		(string) $envelope['actor_uri']
	);
}

/** Emit after a complete non-REST save; REST waits for its additional fields. */
function axismundi_note_emit_saved_lifecycle( int $post_id, WP_Post $post, bool $update, bool $rest_complete = false ) : void {
	unset( $post_id, $update );
	if ( ( defined( 'WP_IMPORTING' ) && WP_IMPORTING )
		|| ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE )
		|| wp_is_post_revision( $post->ID )
		|| ( function_exists( 'axismundi_note_is_rest_write' ) && axismundi_note_is_rest_write() && ! $rest_complete )
		|| AXISMUNDI_NOTE_POST_TYPE !== $post->post_type
		|| 'publish' !== $post->post_status
	) {
		return;
	}
	$result = axismundi_note_record_commit( $post );
	if ( is_wp_error( $result ) && ( ! function_exists( 'axismundi_note_quote_is_held_error' ) || ! axismundi_note_quote_is_held_error( $result ) ) ) {
		axismundi_note_lifecycle_failed( $result, $post );
	}
}
add_action( 'save_post_' . AXISMUNDI_NOTE_POST_TYPE, 'axismundi_note_emit_saved_lifecycle', 40, 3 );

/** Emit after Gutenberg has committed the structured envelope REST field. */
function axismundi_note_emit_rest_lifecycle( WP_Post $post, WP_REST_Request $request, bool $creating ) : void {
	unset( $request );
	axismundi_note_emit_saved_lifecycle( $post->ID, $post, ! $creating, true );
}
add_action( 'rest_after_insert_' . AXISMUNDI_NOTE_POST_TYPE, 'axismundi_note_emit_rest_lifecycle', 40, 3 );

/** Withdraw a Note when it leaves the published state. */
function axismundi_note_transition_lifecycle( string $new_status, string $old_status, WP_Post $post ) : void {
	if ( AXISMUNDI_NOTE_POST_TYPE !== $post->post_type || 'publish' !== $old_status || 'publish' === $new_status ) {
		return;
	}
	$result = axismundi_note_record_delete( $post->ID );
	if ( is_wp_error( $result ) ) {
		axismundi_note_lifecycle_failed( $result, $post );
	}
}
add_action( 'transition_post_status', 'axismundi_note_transition_lifecycle', 40, 3 );

/**
 * Refuse permanent deletion until a previously federated Note has a durable Delete.
 *
 * The earlier envelope filter tombstones first. If that write failed it returns
 * false and this filter must not publish a Delete for an object that stayed active.
 *
 * @param WP_Post|false|null $delete Short-circuit value.
 * @return WP_Post|false|null
 */
function axismundi_note_pre_delete_lifecycle( $delete, WP_Post $post ) {
	if ( false === $delete || AXISMUNDI_NOTE_POST_TYPE !== $post->post_type ) {
		return $delete;
	}
	$result = axismundi_note_record_delete( $post->ID );
	if ( is_wp_error( $result ) ) {
		axismundi_note_lifecycle_failed( $result, $post );
		return false;
	}
	return $delete;
}
add_filter( 'pre_delete_post', 'axismundi_note_pre_delete_lifecycle', 20, 2 );
