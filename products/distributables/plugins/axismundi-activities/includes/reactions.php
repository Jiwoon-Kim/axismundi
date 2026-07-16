<?php
/**
 * URI-keyed Like and Undo state transitions.
 *
 * @package AxismundiActivities
 */

defined( 'ABSPATH' ) || exit;

/** Validate a local Person Actor as a reaction author. */
function axismundi_act_validate_reaction_actor( Axismundi_Actor $actor ) {
	return $actor->is_local()
		&& 'Person' === $actor->get_type()
		&& 'public' === $actor->get_status()
		&& $actor->is_handle_locked()
		? true
		: new WP_Error( 'ax_act_reaction_actor', __( 'An activated public local Person actor is required.', 'axismundi-activities' ) );
}

/** @return Axismundi_Activity[] Effective Likes for one exact object, newest first and unique by Actor. */
function axismundi_act_get_effective_likes( string $object_uri, int $limit = 100 ) : array {
	global $wpdb;
	$uri = axismundi_act_uri( $object_uri );
	if ( '' === $uri || AXISMUNDI_ACT_DB_VERSION !== (string) get_option( AXISMUNDI_ACT_DB_VERSION_OPTION, '' ) ) {
		return array();
	}
	$table = axismundi_act_activities_table();
	$limit = max( 1, min( 200, $limit ) );
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- exact URI reaction query in the custom ledger.
	$rows = (array) $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} WHERE activity_type = 'Like' AND object_uri_hash = %s AND object_uri = %s AND effective_status = 'active' ORDER BY COALESCE(published_at, received_at, created_at) DESC, id DESC LIMIT %d", hash( 'sha256', $uri ), $uri, $limit ), ARRAY_A );
	$seen = array();
	$out  = array();
	foreach ( $rows as $row ) {
		$actor_hash = (string) $row['actor_uri_hash'];
		if ( isset( $seen[ $actor_hash ] ) ) {
			continue;
		}
		$seen[ $actor_hash ] = true;
		$out[] = axismundi_act_hydrate( $row );
	}
	return $out;
}

/** @return Axismundi_Activity[] Effective Likes safe for an anonymous public collection. */
function axismundi_act_get_public_effective_likes( string $object_uri, int $limit = 100 ) : array {
	return array_values( array_filter( axismundi_act_get_effective_likes( $object_uri, $limit ), 'axismundi_act_has_public_audience' ) );
}

/** Count distinct Actors with an effective Like for one object URI. */
function axismundi_act_get_like_count( string $object_uri ) : int {
	global $wpdb;
	$uri = axismundi_act_uri( $object_uri );
	if ( '' === $uri || AXISMUNDI_ACT_DB_VERSION !== (string) get_option( AXISMUNDI_ACT_DB_VERSION_OPTION, '' ) ) {
		return 0;
	}
	$table = axismundi_act_activities_table();
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- indexed aggregate derived from the authoritative ledger.
	return (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(DISTINCT actor_uri_hash) FROM {$table} WHERE activity_type = 'Like' AND object_uri_hash = %s AND object_uri = %s AND effective_status = 'active'", hash( 'sha256', $uri ), $uri ) );
}

/** Latest Like by one Actor for one object, optionally requiring effective state. */
function axismundi_act_get_actor_like( string $actor_uri, string $object_uri, bool $effective_only = true ) : ?Axismundi_Activity {
	global $wpdb;
	$actor  = axismundi_act_uri( $actor_uri );
	$object = axismundi_act_uri( $object_uri );
	if ( '' === $actor || '' === $object || AXISMUNDI_ACT_DB_VERSION !== (string) get_option( AXISMUNDI_ACT_DB_VERSION_OPTION, '' ) ) {
		return null;
	}
	$table  = axismundi_act_activities_table();
	$active = $effective_only ? "AND effective_status = 'active'" : '';
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- allowlisted fixed status clause and exact URI lookup.
	$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE activity_type = 'Like' AND actor_uri_hash = %s AND actor_uri = %s AND object_uri_hash = %s AND object_uri = %s {$active} ORDER BY id DESC LIMIT 1", hash( 'sha256', $actor ), $actor, hash( 'sha256', $object ), $object ), ARRAY_A );
	return is_array( $row ) ? axismundi_act_hydrate( $row ) : null;
}

/** Whether one Actor currently likes an object. */
function axismundi_act_get_like_state( string $actor_uri, string $object_uri ) : bool {
	return axismundi_act_get_actor_like( $actor_uri, $object_uri, true ) instanceof Axismundi_Activity;
}

/** @return string[] Distinct object URIs currently liked by one Actor. */
function axismundi_act_get_liked_object_uris( string $actor_uri, int $limit = 100 ) : array {
	global $wpdb;
	$actor = axismundi_act_uri( $actor_uri );
	if ( '' === $actor || AXISMUNDI_ACT_DB_VERSION !== (string) get_option( AXISMUNDI_ACT_DB_VERSION_OPTION, '' ) ) {
		return array();
	}
	$table = axismundi_act_activities_table();
	$limit = max( 1, min( 200, $limit ) );
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- actor's effective Like projection query.
	$rows = (array) $wpdb->get_col( $wpdb->prepare( "SELECT object_uri FROM {$table} WHERE activity_type = 'Like' AND actor_uri_hash = %s AND actor_uri = %s AND effective_status = 'active' AND object_uri IS NOT NULL ORDER BY id DESC LIMIT %d", hash( 'sha256', $actor ), $actor, $limit ) );
	return array_values( array_unique( array_map( 'strval', $rows ) ) );
}

/** Number of historical Like cycles for an Actor/object pair. */
function axismundi_act_like_cycle_count( string $actor_uri, string $object_uri ) : int {
	global $wpdb;
	$table = axismundi_act_activities_table();
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- exact pair count used for a stable source-event key.
	return (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE activity_type = 'Like' AND actor_uri_hash = %s AND actor_uri = %s AND object_uri_hash = %s AND object_uri = %s", hash( 'sha256', $actor_uri ), $actor_uri, hash( 'sha256', $object_uri ), $object_uri ) );
}

/** Record or return the effective Like for one object. */
function axismundi_act_like_object( Axismundi_Actor $actor, string $object_uri, string $recipient_actor_uri = '' ) {
	$valid  = axismundi_act_validate_reaction_actor( $actor );
	$object = axismundi_act_uri( $object_uri );
	if ( is_wp_error( $valid ) ) {
		return $valid;
	}
	if ( '' === $object ) {
		return new WP_Error( 'ax_act_reaction_object', __( 'A Like requires a canonical object URI.', 'axismundi-activities' ) );
	}
	$existing = axismundi_act_get_actor_like( $actor->get_uri(), $object, true );
	if ( $existing instanceof Axismundi_Activity ) {
		return $existing;
	}
	$recipient = '' !== $recipient_actor_uri ? axismundi_actors_get_by_uri( $recipient_actor_uri ) : null;
	if ( '' !== $recipient_actor_uri && ! $recipient instanceof Axismundi_Actor ) {
		return new WP_Error( 'ax_act_reaction_recipient', __( 'The object owner Actor is unavailable.', 'axismundi-activities' ) );
	}
	$payload = array( 'type' => 'Like', 'actor' => $actor->get_uri(), 'object' => $object );
	if ( $recipient instanceof Axismundi_Actor && $recipient->get_uri() !== $actor->get_uri() ) {
		$payload['to'] = array( $recipient->get_uri() );
	}
	$direction = $recipient instanceof Axismundi_Actor && ! $recipient->is_local() ? 'outbound' : 'local';
	$cycle     = axismundi_act_like_cycle_count( $actor->get_uri(), $object ) + 1;
	$source    = 'like:' . hash( 'sha256', $actor->get_uri() ) . ':' . hash( 'sha256', $object ) . ':' . $cycle;
	return axismundi_act_record_source_activity( $payload, $direction, $source );
}

/** Undo the current Like by referring to the Like Activity URI, never the liked object URI. */
function axismundi_act_unlike_object( Axismundi_Actor $actor, string $object_uri ) {
	$valid  = axismundi_act_validate_reaction_actor( $actor );
	$object = axismundi_act_uri( $object_uri );
	if ( is_wp_error( $valid ) ) {
		return $valid;
	}
	$like = axismundi_act_get_actor_like( $actor->get_uri(), $object, true );
	if ( ! $like instanceof Axismundi_Activity ) {
		$latest = axismundi_act_get_actor_like( $actor->get_uri(), $object, false );
		if ( $latest instanceof Axismundi_Activity && ! $latest->is_effective() ) {
			$undos = array_filter( axismundi_act_get_by_object( $latest->get_uri(), 20 ), static fn( Axismundi_Activity $item ) : bool => 'Undo' === $item->get_type() && $item->is_effective() && $item->get_actor_uri() === $actor->get_uri() );
			return ! empty( $undos ) ? reset( $undos ) : new WP_Error( 'ax_act_like_missing', __( 'There is no active Like to undo.', 'axismundi-activities' ) );
		}
		return new WP_Error( 'ax_act_like_missing', __( 'There is no active Like to undo.', 'axismundi-activities' ) );
	}
	$payload = array( 'type' => 'Undo', 'actor' => $actor->get_uri(), 'object' => $like->get_uri() );
	$to      = (array) ( $like->get_audience()['to'] ?? array() );
	if ( ! empty( $to ) ) {
		$payload['to'] = $to;
	}
	return axismundi_act_record_source_activity( $payload, $like->get_direction(), 'unlike:' . $like->get_uri() );
}

/** Keep Object Projections interaction leases aligned with committed Like/Undo rows. */
function axismundi_act_sync_reaction_lease( Axismundi_Activity $activity ) : void {
	if ( ! function_exists( 'axismundi_op_remote_object_get' ) || ! function_exists( 'axismundi_op_add_lease' ) || ! function_exists( 'axismundi_op_release_lease' ) ) {
		return;
	}
	if ( 'Like' === $activity->get_type() && $activity->is_effective() && null !== $activity->get_object_uri() && axismundi_op_remote_object_get( $activity->get_object_uri() ) ) {
		axismundi_op_add_lease( $activity->get_object_uri(), 'interaction', $activity->get_uri() );
		return;
	}
	if ( 'Undo' !== $activity->get_type() || null === $activity->get_object_uri() ) {
		return;
	}
	$target = axismundi_act_get( $activity->get_object_uri() );
	if ( $target instanceof Axismundi_Activity && 'Like' === $target->get_type() && null !== $target->get_object_uri() ) {
		axismundi_op_release_lease( $target->get_object_uri(), 'interaction', $target->get_uri() );
	}
}
add_action( 'axismundi_act_activity_recorded', 'axismundi_act_sync_reaction_lease', 20 );
