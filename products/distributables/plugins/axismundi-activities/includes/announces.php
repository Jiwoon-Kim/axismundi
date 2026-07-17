<?php
/**
 * URI-keyed personal Announce and Undo state transitions.
 *
 * @package AxismundiActivities
 */

defined( 'ABSPATH' ) || exit;

/** @return Axismundi_Activity[] Effective personal Announces, newest first and unique by Actor. */
function axismundi_act_get_effective_announces( string $object_uri, int $limit = 100 ) : array {
	global $wpdb;
	$uri = axismundi_act_uri( $object_uri );
	if ( '' === $uri || AXISMUNDI_ACT_DB_VERSION !== (string) get_option( AXISMUNDI_ACT_DB_VERSION_OPTION, '' ) ) {
		return array();
	}
	$table = axismundi_act_activities_table();
	$limit = max( 1, min( 200, $limit ) );
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- exact URI Announce query in the custom ledger.
	$rows = (array) $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} WHERE activity_type = 'Announce' AND object_uri_hash = %s AND object_uri = %s AND effective_status = 'active' ORDER BY COALESCE(published_at, received_at, created_at) DESC, id DESC LIMIT %d", hash( 'sha256', $uri ), $uri, $limit ), ARRAY_A );
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

/** Count distinct Actors with an effective Announce for one object URI. */
function axismundi_act_get_announce_count( string $object_uri ) : int {
	global $wpdb;
	$uri = axismundi_act_uri( $object_uri );
	if ( '' === $uri || AXISMUNDI_ACT_DB_VERSION !== (string) get_option( AXISMUNDI_ACT_DB_VERSION_OPTION, '' ) ) {
		return 0;
	}
	$table = axismundi_act_activities_table();
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- indexed aggregate derived from the authoritative ledger.
	return (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(DISTINCT actor_uri_hash) FROM {$table} WHERE activity_type = 'Announce' AND object_uri_hash = %s AND object_uri = %s AND effective_status = 'active'", hash( 'sha256', $uri ), $uri ) );
}

/** Latest Announce by one Actor for one object, optionally requiring effective state. */
function axismundi_act_get_actor_announce( string $actor_uri, string $object_uri, bool $effective_only = true ) : ?Axismundi_Activity {
	global $wpdb;
	$actor  = axismundi_act_uri( $actor_uri );
	$object = axismundi_act_uri( $object_uri );
	if ( '' === $actor || '' === $object || AXISMUNDI_ACT_DB_VERSION !== (string) get_option( AXISMUNDI_ACT_DB_VERSION_OPTION, '' ) ) {
		return null;
	}
	$table  = axismundi_act_activities_table();
	$active = $effective_only ? "AND effective_status = 'active'" : '';
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- allowlisted fixed status clause and exact URI lookup.
	$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE activity_type = 'Announce' AND actor_uri_hash = %s AND actor_uri = %s AND object_uri_hash = %s AND object_uri = %s {$active} ORDER BY id DESC LIMIT 1", hash( 'sha256', $actor ), $actor, hash( 'sha256', $object ), $object ), ARRAY_A );
	return is_array( $row ) ? axismundi_act_hydrate( $row ) : null;
}

/** Whether one Actor currently announces an object. */
function axismundi_act_get_announce_state( string $actor_uri, string $object_uri ) : bool {
	return axismundi_act_get_actor_announce( $actor_uri, $object_uri, true ) instanceof Axismundi_Activity;
}

/** Number of historical Announce cycles for an Actor/object pair. */
function axismundi_act_announce_cycle_count( string $actor_uri, string $object_uri ) : int {
	global $wpdb;
	$table = axismundi_act_activities_table();
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- exact pair count used for a stable source-event key.
	return (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE activity_type = 'Announce' AND actor_uri_hash = %s AND actor_uri = %s AND object_uri_hash = %s AND object_uri = %s", hash( 'sha256', $actor_uri ), $actor_uri, hash( 'sha256', $object_uri ), $object_uri ) );
}

/** Ask object-domain providers whether one object may be redistributed publicly. */
function axismundi_act_can_announce_object( Axismundi_Actor $actor, string $object_uri ) {
	$denied = new WP_Error( 'ax_act_announce_visibility', __( 'The object is not known to be publicly announceable.', 'axismundi-activities' ), array( 'status' => 409 ) );
	/**
	 * @param true|WP_Error   $allowed    Fail-closed default or a provider decision.
	 * @param Axismundi_Actor $actor      Local announcing Actor.
	 * @param string           $object_uri Canonical object URI.
	 */
	return apply_filters( 'axismundi_act_can_announce_object', $denied, $actor, $object_uri );
}

/** Record or return the effective personal Announce for one public object. */
function axismundi_act_announce_object( Axismundi_Actor $actor, string $object_uri, string $recipient_actor_uri = '' ) {
	$valid  = axismundi_act_validate_reaction_actor( $actor );
	$object = axismundi_act_uri( $object_uri );
	if ( is_wp_error( $valid ) ) {
		return $valid;
	}
	if ( '' === $object ) {
		return new WP_Error( 'ax_act_announce_object', __( 'An Announce requires a canonical object URI.', 'axismundi-activities' ) );
	}
	$allowed = axismundi_act_can_announce_object( $actor, $object );
	if ( true !== $allowed ) {
		return is_wp_error( $allowed ) ? $allowed : new WP_Error( 'ax_act_announce_visibility', __( 'The object cannot be announced.', 'axismundi-activities' ), array( 'status' => 409 ) );
	}
	$existing = axismundi_act_get_actor_announce( $actor->get_uri(), $object, true );
	if ( $existing instanceof Axismundi_Activity ) {
		return $existing;
	}
	$recipient = '' !== $recipient_actor_uri ? axismundi_actors_get_by_uri( $recipient_actor_uri ) : null;
	if ( '' !== $recipient_actor_uri && ! $recipient instanceof Axismundi_Actor ) {
		return new WP_Error( 'ax_act_announce_recipient', __( 'The object owner Actor is unavailable.', 'axismundi-activities' ) );
	}
	$payload = array(
		'type'   => 'Announce',
		'actor'  => $actor->get_uri(),
		'object' => $object,
		'to'     => array( 'https://www.w3.org/ns/activitystreams#Public' ),
	);
	if ( $recipient instanceof Axismundi_Actor && $recipient->get_uri() !== $actor->get_uri() ) {
		// The origin server must receive Announce for its `shares` side effect.
		$payload['cc'] = array( $recipient->get_uri() );
	}
	$cycle  = axismundi_act_announce_cycle_count( $actor->get_uri(), $object ) + 1;
	$source = 'announce:' . hash( 'sha256', $actor->get_uri() ) . ':' . hash( 'sha256', $object ) . ':' . $cycle;
	return axismundi_act_record_source_activity( $payload, 'outbound', $source );
}

/** Undo the current Announce by referring to the Announce Activity URI. */
function axismundi_act_unannounce_object( Axismundi_Actor $actor, string $object_uri ) {
	$valid  = axismundi_act_validate_reaction_actor( $actor );
	$object = axismundi_act_uri( $object_uri );
	if ( is_wp_error( $valid ) ) {
		return $valid;
	}
	$announce = axismundi_act_get_actor_announce( $actor->get_uri(), $object, true );
	if ( ! $announce instanceof Axismundi_Activity ) {
		$latest = axismundi_act_get_actor_announce( $actor->get_uri(), $object, false );
		if ( $latest instanceof Axismundi_Activity && ! $latest->is_effective() ) {
			$undos = array_filter(
				axismundi_act_get_by_object( $latest->get_uri(), 20 ),
				static fn( Axismundi_Activity $item ) : bool => 'Undo' === $item->get_type()
					&& $item->is_effective()
					&& $item->get_actor_uri() === $actor->get_uri()
			);
			if ( ! empty( $undos ) ) {
				return reset( $undos );
			}
		}
		return new WP_Error( 'ax_act_announce_missing', __( 'There is no active Announce to undo.', 'axismundi-activities' ) );
	}
	$payload  = array( 'type' => 'Undo', 'actor' => $actor->get_uri(), 'object' => $announce->get_uri() );
	$audience = $announce->get_audience();
	foreach ( array( 'to', 'cc', 'audience' ) as $property ) {
		if ( ! empty( $audience[ $property ] ) ) {
			$payload[ $property ] = $audience[ $property ];
		}
	}
	return axismundi_act_record_source_activity( $payload, $announce->get_direction(), 'unannounce:' . $announce->get_uri() );
}

/** Keep remote-object interaction leases aligned with effective Announce cycles. */
function axismundi_act_sync_announce_lease( Axismundi_Activity $activity ) : void {
	if ( ! function_exists( 'axismundi_op_remote_object_get' ) || ! function_exists( 'axismundi_op_add_lease' ) || ! function_exists( 'axismundi_op_release_lease' ) ) {
		return;
	}
	if ( 'Announce' === $activity->get_type() && $activity->is_effective() && null !== $activity->get_object_uri() && axismundi_op_remote_object_get( $activity->get_object_uri() ) ) {
		axismundi_op_add_lease( $activity->get_object_uri(), 'interaction', $activity->get_uri() );
		return;
	}
	if ( 'Undo' !== $activity->get_type() || null === $activity->get_object_uri() ) {
		return;
	}
	$target = axismundi_act_get( $activity->get_object_uri() );
	if ( $target instanceof Axismundi_Activity && 'Announce' === $target->get_type() && null !== $target->get_object_uri() ) {
		axismundi_op_release_lease( $target->get_object_uri(), 'interaction', $target->get_uri() );
	}
}
add_action( 'axismundi_act_activity_recorded', 'axismundi_act_sync_announce_lease', 20 );
