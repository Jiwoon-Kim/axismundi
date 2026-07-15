<?php
/**
 * Idempotent legacy ActivityPub import and verification.
 *
 * @package AxismundiActivityPubBridge
 */

defined( 'ABSPATH' ) || exit;

/** Empty import result. */
function axismundi_activitypub_bridge_legacy_import_result() : array {
	return array(
		'generated_at'     => current_time( 'mysql', true ),
		'official_version' => defined( 'ACTIVITYPUB_PLUGIN_VERSION' ) ? ACTIVITYPUB_PLUGIN_VERSION : '',
		'summary'          => array(),
		'rows'             => array(),
		'writes'           => 0,
		'deletes'          => 0,
		'network_requests' => 0,
		'complete'         => true,
	);
}

/** Add one bounded import result row. */
function axismundi_activitypub_bridge_legacy_import_row( array &$result, string $source, string $source_id, string $identity, string $status, string $detail ) : void {
	if ( ! isset( $result['summary'][ $source ] ) ) {
		$result['summary'][ $source ] = array();
	}
	$result['summary'][ $source ][ $status ] = 1 + (int) ( $result['summary'][ $source ][ $status ] ?? 0 );
	if ( in_array( $status, array( 'imported', 'updated' ), true ) ) {
		++$result['writes'];
	}
	if ( 'failed' === $status ) {
		$result['complete'] = false;
	}
	$limit = max( 1, (int) apply_filters( 'axismundi_activitypub_bridge_legacy_import_sample_limit', 200 ) );
	if ( count( $result['rows'] ) < $limit ) {
		$result['rows'][] = compact( 'source', 'source_id', 'identity', 'status', 'detail' );
	}
}

/** Import and verify official remote Actor snapshots. */
function axismundi_activitypub_bridge_import_legacy_actors( array &$result ) : void {
	$report = axismundi_activitypub_bridge_legacy_report();
	foreach ( axismundi_activitypub_bridge_legacy_posts( 'ap_actor', $report ) as $post ) {
		$payload = axismundi_activitypub_bridge_legacy_payload( $post );
		$uri     = is_array( $payload ) ? axismundi_act_member_uri( $payload['id'] ?? $post->guid ) : '';
		$record  = '' !== $uri && is_array( $payload )
			? axismundi_actors_normalize_remote_actor_payload( $payload, $uri )
			: new WP_Error( 'ax_bridge_legacy_actor_json', __( 'Stored Actor JSON has no valid canonical id.', 'axismundi-activitypub-bridge' ) );
		if ( is_wp_error( $record ) ) {
			axismundi_activitypub_bridge_legacy_import_row( $result, 'ap_actor', (string) $post->ID, $uri, 'failed', $record->get_error_message() );
			continue;
		}
		$before = axismundi_actors_get_by_uri( $uri );
		if ( $before instanceof Axismundi_Actor ) {
			$status = $before->get_type() === (string) $record['actor_type'] ? 'verified_existing' : 'failed';
			axismundi_activitypub_bridge_legacy_import_row(
				$result,
				'ap_actor',
				(string) $post->ID,
				$uri,
				$status,
				'verified_existing' === $status
					? __( 'The canonical Actor already exists; the older official snapshot was not written over it.', 'axismundi-activitypub-bridge' )
					: __( 'The existing Actor type conflicts with the official snapshot.', 'axismundi-activitypub-bridge' )
			);
			continue;
		}
		$actor  = axismundi_actors_upsert_remote( $record );
		if ( is_wp_error( $actor ) ) {
			axismundi_activitypub_bridge_legacy_import_row( $result, 'ap_actor', (string) $post->ID, $uri, 'failed', $actor->get_error_message() );
			continue;
		}
		$verified = axismundi_actors_get_by_uri( $uri );
		if ( ! $verified instanceof Axismundi_Actor || ! hash_equals( $verified->get_uri(), $uri ) || $verified->get_type() !== (string) $record['actor_type'] ) {
			axismundi_activitypub_bridge_legacy_import_row( $result, 'ap_actor', (string) $post->ID, $uri, 'failed', __( 'The imported Actor could not be verified by canonical URI and type.', 'axismundi-activitypub-bridge' ) );
			continue;
		}
		axismundi_activitypub_bridge_legacy_import_row(
			$result,
			'ap_actor',
			(string) $post->ID,
			$uri,
			'imported',
			__( 'Verified in Axismundi Actors. The official ap_actor row remains runtime-required.', 'axismundi-activitypub-bridge' )
		);
	}
}

/** Import and verify official remote Object snapshots. */
function axismundi_activitypub_bridge_import_legacy_objects( array &$result ) : void {
	$report = axismundi_activitypub_bridge_legacy_report();
	foreach ( axismundi_activitypub_bridge_legacy_posts( 'ap_post', $report ) as $post ) {
		$payload = axismundi_activitypub_bridge_legacy_payload( $post );
		if ( ! is_array( $payload ) ) {
			axismundi_activitypub_bridge_legacy_import_row( $result, 'ap_post', (string) $post->ID, (string) $post->guid, 'failed', __( 'Stored Object JSON is invalid.', 'axismundi-activitypub-bridge' ) );
			continue;
		}
		$normalized = axismundi_op_remote_object_normalize( $payload );
		if ( is_wp_error( $normalized ) ) {
			axismundi_activitypub_bridge_legacy_import_row( $result, 'ap_post', (string) $post->ID, (string) $post->guid, 'failed', $normalized->get_error_message() );
			continue;
		}
		$uri    = (string) $normalized['object_uri'];
		$before = axismundi_op_remote_object_get( $uri, false );
		if ( is_array( $before ) ) {
			$status = hash_equals( (string) $before['payload_hash'], (string) $normalized['payload_hash'] ) ? 'verified_existing' : 'failed';
			axismundi_activitypub_bridge_legacy_import_row(
				$result,
				'ap_post',
				(string) $post->ID,
				$uri,
				$status,
				'verified_existing' === $status
					? __( 'The identical Object snapshot already exists and was not rewritten.', 'axismundi-activitypub-bridge' )
					: __( 'The existing Object payload differs from the official snapshot; the newer cache was preserved.', 'axismundi-activitypub-bridge' )
			);
			continue;
		}
		$stored = axismundi_op_remote_object_store( $payload );
		if ( is_wp_error( $stored ) ) {
			axismundi_activitypub_bridge_legacy_import_row( $result, 'ap_post', (string) $post->ID, $uri, 'failed', $stored->get_error_message() );
			continue;
		}
		$verified = axismundi_op_remote_object_get( $uri, false );
		if ( ! is_array( $verified ) || ! hash_equals( (string) $verified['object_uri'], $uri ) || ! hash_equals( (string) $verified['payload_hash'], (string) $normalized['payload_hash'] ) ) {
			axismundi_activitypub_bridge_legacy_import_row( $result, 'ap_post', (string) $post->ID, $uri, 'failed', __( 'The imported Object could not be verified by canonical URI and payload hash.', 'axismundi-activitypub-bridge' ) );
			continue;
		}
		axismundi_activitypub_bridge_legacy_import_row( $result, 'ap_post', (string) $post->ID, $uri, 'imported', __( 'Verified in the URI-keyed remote Object repository.', 'axismundi-activitypub-bridge' ) );
	}
}

/** Sort historical Inbox rows into a stable replay order. */
function axismundi_activitypub_bridge_sort_legacy_inbox( array &$posts ) : void {
	usort(
		$posts,
		static function ( WP_Post $left, WP_Post $right ) : int {
			$left_time  = strtotime( (string) ( $left->post_date_gmt ?: $left->post_date ) );
			$right_time = strtotime( (string) ( $right->post_date_gmt ?: $right->post_date ) );
			return $left_time === $right_time ? $left->ID <=> $right->ID : $left_time <=> $right_time;
		}
	);
}

/** Import and verify signature-verified historical Inbox Activities. */
function axismundi_activitypub_bridge_import_legacy_inbox( array &$result ) : void {
	$report = axismundi_activitypub_bridge_legacy_report();
	$posts  = axismundi_activitypub_bridge_legacy_posts( 'ap_inbox', $report );
	axismundi_activitypub_bridge_sort_legacy_inbox( $posts );
	foreach ( $posts as $post ) {
		$payload      = axismundi_activitypub_bridge_legacy_payload( $post );
		$activity_uri = is_array( $payload ) ? axismundi_act_member_uri( $payload['id'] ?? $post->guid ) : '';
		$actor_uri    = is_array( $payload ) ? axismundi_act_member_uri( $payload['actor'] ?? '' ) : '';
		$recipients   = array_map( 'intval', get_post_meta( $post->ID, '_activitypub_user_id', false ) );
		$missing      = array_filter( $recipients, static fn( int $user_id ) : bool => ! axismundi_activitypub_bridge_legacy_local_actor( $user_id ) instanceof Axismundi_Actor );
		if ( ! is_array( $payload ) || '' === $activity_uri || '' === $actor_uri || empty( $recipients ) || ! empty( $missing ) || ! axismundi_actors_get_by_uri( $actor_uri ) instanceof Axismundi_Actor ) {
			axismundi_activitypub_bridge_legacy_import_row( $result, 'ap_inbox', (string) $post->ID, $activity_uri, 'failed', __( 'The Activity or its Actor/local recipient mapping is incomplete.', 'axismundi-activitypub-bridge' ) );
			continue;
		}
		$before   = axismundi_act_get( $activity_uri );
		$activity = axismundi_act_record_activity( $payload, 'inbound' );
		if ( is_wp_error( $activity ) ) {
			axismundi_activitypub_bridge_legacy_import_row( $result, 'ap_inbox', (string) $post->ID, $activity_uri, 'failed', $activity->get_error_message() );
			continue;
		}
		$verified = axismundi_act_get( $activity_uri );
		if ( ! $verified instanceof Axismundi_Activity || ! hash_equals( $verified->get_uri(), $activity_uri ) ) {
			axismundi_activitypub_bridge_legacy_import_row( $result, 'ap_inbox', (string) $post->ID, $activity_uri, 'failed', __( 'The imported Activity could not be verified by canonical URI.', 'axismundi-activitypub-bridge' ) );
			continue;
		}
		axismundi_activitypub_bridge_legacy_import_row( $result, 'ap_inbox', (string) $post->ID, $activity_uri, $before instanceof Axismundi_Activity ? 'verified_existing' : 'imported', __( 'Verified in the Activity ledger; relation state was derived by replay.', 'axismundi-activitypub-bridge' ) );
	}
}

/** Import supported legacy sources, verify them, and leave every official row intact. */
function axismundi_activitypub_bridge_import_legacy_data() : array {
	$result   = axismundi_activitypub_bridge_legacy_import_result();
	$preflight = axismundi_activitypub_bridge_scan_legacy_data();
	if ( ! empty( $preflight['truncated'] ) ) {
		$result['complete'] = false;
		axismundi_activitypub_bridge_legacy_import_row( $result, 'preflight', 'bounded-scan', '', 'failed', __( 'Import is blocked because the dry scan was truncated.', 'axismundi-activitypub-bridge' ) );
		return $result;
	}
	axismundi_activitypub_bridge_import_legacy_actors( $result );
	axismundi_activitypub_bridge_import_legacy_objects( $result );
	axismundi_activitypub_bridge_import_legacy_inbox( $result );
	ksort( $result['summary'] );
	return $result;
}
