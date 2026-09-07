<?php
/**
 * Storage-neutral local Object lifecycle recording.
 *
 * @package AxismundiActivities
 */

defined( 'ABSPATH' ) || exit;

/** Recursively sort associative keys while preserving meaningful list order. */
function axismundi_act_lifecycle_canonicalize( $value ) {
	if ( ! is_array( $value ) ) {
		return $value;
	}
	if ( array_is_list( $value ) ) {
		return array_map( 'axismundi_act_lifecycle_canonicalize', $value );
	}
	ksort( $value, SORT_STRING );
	foreach ( $value as $key => $member ) {
		$value[ $key ] = axismundi_act_lifecycle_canonicalize( $member );
	}
	return $value;
}

/** Stable hash for one immutable Object snapshot. */
function axismundi_act_lifecycle_fingerprint( array $object ) : string {
	$json = wp_json_encode( axismundi_act_lifecycle_canonicalize( $object ), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
	return is_string( $json ) ? hash( 'sha256', $json ) : '';
}

/**
 * Record the first Create or a later Update for one complete local Object snapshot.
 *
 * The embedded Object is intentional: it makes callback deduplication compare the
 * actual committed representation, while a return to an older state still records
 * a new Update because it differs from the immediately preceding lifecycle event.
 *
 * @return Axismundi_Activity|WP_Error
 */
function axismundi_act_record_object_commit( array $object ) {
	$object_uri = axismundi_act_member_uri( $object['id'] ?? '' );
	$actor_uri  = axismundi_act_member_uri( $object['attributedTo'] ?? '' );
	if ( '' === $object_uri || '' === $actor_uri || 'Tombstone' === (string) ( $object['type'] ?? '' ) ) {
		return new WP_Error( 'ax_act_object_snapshot', __( 'A local lifecycle commit requires a complete active Object snapshot.', 'axismundi-activities' ) );
	}

	$latest      = axismundi_act_get_object_lifecycle( $object_uri );
	$fingerprint = axismundi_act_lifecycle_fingerprint( $object );
	if ( '' === $fingerprint ) {
		return new WP_Error( 'ax_act_object_snapshot', __( 'The local Object snapshot could not be normalized.', 'axismundi-activities' ) );
	}
	if ( $latest instanceof Axismundi_Activity && 'Delete' !== $latest->get_type() ) {
		$previous = $latest->get_payload()['object'] ?? null;
		if ( is_array( $previous )
			&& hash_equals( $latest->get_actor_uri(), $actor_uri )
			&& hash_equals( axismundi_act_lifecycle_fingerprint( $previous ), $fingerprint ) ) {
			return $latest;
		}
	}

	$type       = $latest instanceof Axismundi_Activity && 'Delete' !== $latest->get_type() ? 'Update' : 'Create';
	$generation = $latest instanceof Axismundi_Activity ? $latest->get_uri() : 'initial';
	$audience   = axismundi_act_audience( $object );
	$payload    = array(
		'type'   => $type,
		'actor'  => $actor_uri,
		'object' => $object,
		'to'     => $audience['to'],
		'cc'     => $audience['cc'],
	);
	$activity = axismundi_act_record_source_activity(
		$payload,
		'outbound',
		'local-object-' . strtolower( $type ) . ':' . $object_uri . ':after:' . $generation . ':snapshot:' . $fingerprint
	);
	if ( is_wp_error( $activity ) ) {
		/** @param WP_Error $activity @param array<string,mixed> $object Failed local lifecycle write. */
		do_action( 'axismundi_act_object_commit_failed', $activity, $object );
	}
	return $activity;
}

/**
 * Record one privacy-minimal Delete using the latest committed audience snapshot.
 *
 * @return Axismundi_Activity|WP_Error|null Null means the Object was never announced.
 */
function axismundi_act_record_object_delete( string $object_uri, string $actor_uri ) {
	$object_uri = axismundi_act_uri( $object_uri );
	$actor_uri  = axismundi_act_uri( $actor_uri );
	if ( '' === $object_uri || '' === $actor_uri ) {
		return new WP_Error( 'ax_act_object_delete', __( 'A local Delete requires canonical Object and Actor URIs.', 'axismundi-activities' ) );
	}
	$latest = axismundi_act_get_object_lifecycle( $object_uri );
	if ( ! $latest instanceof Axismundi_Activity ) {
		return null;
	}
	if ( 'Delete' === $latest->get_type() ) {
		return $latest;
	}
	if ( ! hash_equals( $latest->get_actor_uri(), $actor_uri ) ) {
		return new WP_Error( 'ax_act_object_actor', __( 'The deleting Actor does not own the committed Object lifecycle.', 'axismundi-activities' ) );
	}
	$audience = $latest->get_audience();
	$activity = axismundi_act_record_source_activity(
		array(
			'type'   => 'Delete',
			'actor'  => $actor_uri,
			'object' => $object_uri,
			'to'     => (array) ( $audience['to'] ?? array() ),
			'cc'     => (array) ( $audience['cc'] ?? array() ),
		),
		'outbound',
		'local-object-delete:' . $object_uri . ':after:' . $latest->get_uri()
	);
	if ( is_wp_error( $activity ) ) {
		/** @param WP_Error $activity @param string $object_uri Failed local Delete write. */
		do_action( 'axismundi_act_object_delete_failed', $activity, $object_uri );
	}
	return $activity;
}
