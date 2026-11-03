<?php
/** Bounded parent acquisition from committed reply evidence, never from render or explorer reads. */
defined( 'ABSPATH' ) || exit;

/** Resolve only one parent. Reading a parent never recursively schedules its ancestors or replies. */
function axismundi_op_reply_parent_candidate( Axismundi_Activity $activity ) : string {
	if ( 'Create' !== $activity->get_type() || ! $activity->is_effective() || ! axismundi_act_is_publicly_renderable( $activity ) ) { return ''; }
	$object = $activity->get_payload()['object'] ?? null;
	if ( ! is_array( $object ) || 'Note' !== ( $object['type'] ?? '' ) ) { return ''; }
	$parent = axismundi_op_remote_member_uri( $object['inReplyTo'] ?? '' );
	if ( '' === $parent || $parent === ( $object['id'] ?? '' ) || 'https' !== wp_parse_url( $parent, PHP_URL_SCHEME ) ) { return ''; }
	// Local resolution (including Tombstones) always wins; never loop HTTP back to this site.
	if ( axismundi_op_resolve_source_by_uri( $parent ) || wp_parse_url( $parent, PHP_URL_HOST ) === wp_parse_url( home_url(), PHP_URL_HOST ) ) { return ''; }
	if ( 'inbound' === $activity->get_direction() ) {
		global $wpdb;
		if ( ! $wpdb->get_var( $wpdb->prepare( 'SELECT id FROM %i WHERE activity_id=%d LIMIT 1', axismundi_act_inbox_table(), $activity->get_id() ) ) ) { return ''; }
	} elseif ( 'outbound' !== $activity->get_direction() ) { return ''; }
	return $parent;
}

function axismundi_op_schedule_reply_parent( Axismundi_Activity $activity ) : void {
	if ( ! axismundi_op_background_acquisition_enabled() || '' === axismundi_op_reply_parent_candidate( $activity ) ) { return; }
	$args = array( $activity->get_uri(), 0 );
	if ( ! wp_next_scheduled( 'axismundi_op_fetch_reply_parent', $args ) ) { wp_schedule_single_event( time() + 5, 'axismundi_op_fetch_reply_parent', $args ); }
}
add_action( 'axismundi_act_activity_recorded', 'axismundi_op_schedule_reply_parent', 35 );

/** Recheck both evidence and opt-in at execution; failures never delete edges or submissions. */
function axismundi_op_fetch_reply_parent( string $activity_uri, int $attempt = 0 ) : void {
	if ( ! axismundi_op_background_acquisition_enabled() || $attempt < 0 || $attempt > 2 ) { return; }
	$activity = axismundi_act_get( $activity_uri );
	$parent = $activity instanceof Axismundi_Activity ? axismundi_op_reply_parent_candidate( $activity ) : '';
	if ( '' === $parent ) { return; }
	$result = axismundi_op_remote_object_fetch( $parent );
	if ( is_wp_error( $result ) && $attempt < 2 ) {
		$args = array( $activity_uri, $attempt + 1 );
		if ( ! wp_next_scheduled( 'axismundi_op_fetch_reply_parent', $args ) ) { wp_schedule_single_event( time() + 15 * MINUTE_IN_SECONDS, 'axismundi_op_fetch_reply_parent', $args ); }
	}
}
add_action( 'axismundi_op_fetch_reply_parent', 'axismundi_op_fetch_reply_parent', 10, 2 );
