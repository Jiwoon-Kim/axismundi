<?php
/**
 * Read-only legacy ActivityPub storage scanner.
 *
 * @package AxismundiActivityPubBridge
 */

defined( 'ABSPATH' ) || exit;

/** Empty dry-run report. */
function axismundi_activitypub_bridge_legacy_report() : array {
	return array(
		'generated_at'     => current_time( 'mysql', true ),
		'official_version' => defined( 'ACTIVITYPUB_PLUGIN_VERSION' ) ? ACTIVITYPUB_PLUGIN_VERSION : '',
		'summary'          => array(),
		'rows'             => array(),
		'truncated'        => false,
		'writes'           => 0,
		'network_requests' => 0,
	);
}

/** Add one classified source row and aggregate its two independent decisions. */
function axismundi_activitypub_bridge_legacy_add_row( array &$report, string $source, string $source_id, string $identity, string $import, string $purge, string $detail ) : void {
	if ( ! isset( $report['summary'][ $source ] ) ) {
		$report['summary'][ $source ] = array(
			'scanned' => 0,
			'available' => 0,
			'import' => array(),
			'purge' => array(),
		);
	}
	++$report['summary'][ $source ]['scanned'];
	$report['summary'][ $source ]['import'][ $import ] = 1 + (int) ( $report['summary'][ $source ]['import'][ $import ] ?? 0 );
	$report['summary'][ $source ]['purge'][ $purge ]   = 1 + (int) ( $report['summary'][ $source ]['purge'][ $purge ] ?? 0 );
	$sample_limit = (int) apply_filters( 'axismundi_activitypub_bridge_legacy_scan_sample_limit', 200 );
	if ( count( $report['rows'] ) < max( 1, $sample_limit ) ) {
		$report['rows'][] = compact( 'source', 'source_id', 'identity', 'import', 'purge', 'detail' );
	}
}

/** Read one official JSON CPT payload without mutation. */
function axismundi_activitypub_bridge_legacy_payload( WP_Post $post ) : ?array {
	$payload = json_decode( (string) $post->post_content, true );
	return is_array( $payload ) ? $payload : null;
}

/** Bounded post query for one official source type. */
function axismundi_activitypub_bridge_legacy_posts( string $post_type, array &$report ) : array {
	$limit = max( 1, (int) apply_filters( 'axismundi_activitypub_bridge_legacy_scan_limit', 10000, $post_type ) );
	$query = new WP_Query(
		array(
			'post_type'              => $post_type,
			'post_status'            => 'any',
			'posts_per_page'         => $limit,
			'orderby'                => 'ID',
			'order'                  => 'ASC',
			'no_found_rows'          => false,
			'update_post_meta_cache' => true,
			'update_post_term_cache' => false,
		)
	);
	if ( ! isset( $report['summary'][ $post_type ] ) ) {
		$report['summary'][ $post_type ] = array( 'scanned' => 0, 'available' => (int) $query->found_posts, 'import' => array(), 'purge' => array() );
	} else {
		$report['summary'][ $post_type ]['available'] = (int) $query->found_posts;
	}
	if ( $query->found_posts > count( $query->posts ) ) {
		$report['truncated'] = true;
	}
	return $query->posts;
}

/** Map an official local user identifier without creating an Actor while scanning. */
function axismundi_activitypub_bridge_legacy_local_actor( int $user_id ) : ?Axismundi_Actor {
	return 0 === $user_id
		? axismundi_actors_get_site_actor()
		: axismundi_actors_get_for_user( $user_id );
}

/** Scan official remote Actor rows and follower snapshots. */
function axismundi_activitypub_bridge_scan_legacy_actors( array &$report ) : array {
	$known = array();
	foreach ( axismundi_activitypub_bridge_legacy_posts( 'ap_actor', $report ) as $post ) {
		$payload = axismundi_activitypub_bridge_legacy_payload( $post );
		$uri     = is_array( $payload ) ? axismundi_act_member_uri( $payload['id'] ?? $post->guid ) : '';
		if ( '' === $uri ) {
			axismundi_activitypub_bridge_legacy_add_row( $report, 'ap_actor', (string) $post->ID, '', 'failed', 'blocked', __( 'Stored Actor JSON has no valid canonical id.', 'axismundi-activitypub-bridge' ) );
			continue;
		}
		$known[ $uri ] = true;
		$normalized = function_exists( 'axismundi_actors_normalize_remote_actor_payload' )
			? axismundi_actors_normalize_remote_actor_payload( $payload, $uri )
			: new WP_Error( 'ax_bridge_legacy_actor_api', 'Actor normalizer unavailable.' );
		if ( is_wp_error( $normalized ) ) {
			axismundi_activitypub_bridge_legacy_add_row( $report, 'ap_actor', (string) $post->ID, $uri, 'failed', 'blocked', $normalized->get_error_message() );
			continue;
		}
		$existing = axismundi_actors_get_by_uri( $uri );
		axismundi_activitypub_bridge_legacy_add_row(
			$report,
			'ap_actor',
			(string) $post->ID,
			$uri,
			$existing instanceof Axismundi_Actor ? 'duplicate' : 'importable',
			'runtime_required',
			__( 'Official signature verification still resolves remote public keys through ap_actor.', 'axismundi-activitypub-bridge' )
		);

		foreach ( get_post_meta( $post->ID, '_activitypub_following', false ) as $local_user_id ) {
			$local = axismundi_activitypub_bridge_legacy_local_actor( (int) $local_user_id );
			if ( ! $local instanceof Axismundi_Actor ) {
				axismundi_activitypub_bridge_legacy_add_row( $report, 'follower_snapshot', $post->ID . ':' . (int) $local_user_id, $uri, 'failed', 'blocked', __( 'The referenced local user has no Axismundi Actor.', 'axismundi-activitypub-bridge' ) );
				continue;
			}
			$followers = function_exists( 'axismundi_act_get_followers' ) ? axismundi_act_get_followers( $local->get_uri(), 1000 ) : array();
			$matched   = in_array( $uri, $followers, true );
			axismundi_activitypub_bridge_legacy_add_row(
				$report,
				'follower_snapshot',
				$post->ID . ':' . (int) $local_user_id,
				$uri . ' -> ' . $local->get_uri(),
				$matched ? 'duplicate' : 'snapshot_only',
				$matched ? 'runtime_required' : 'blocked',
				$matched
					? __( 'The accepted relation already exists; the ap_actor row remains a transport cache.', 'axismundi-activitypub-bridge' )
					: __( 'No source Follow history has reconstructed this snapshot; direct relation writes are forbidden.', 'axismundi-activitypub-bridge' )
			);
		}
	}
	return $known;
}

/** Scan official remote Object rows. */
function axismundi_activitypub_bridge_scan_legacy_objects( array &$report ) : void {
	foreach ( axismundi_activitypub_bridge_legacy_posts( 'ap_post', $report ) as $post ) {
		$payload = axismundi_activitypub_bridge_legacy_payload( $post );
		$normalized = is_array( $payload ) && function_exists( 'axismundi_op_remote_object_normalize' )
			? axismundi_op_remote_object_normalize( $payload )
			: new WP_Error( 'ax_bridge_legacy_object_json', __( 'Stored Object JSON is invalid.', 'axismundi-activitypub-bridge' ) );
		if ( is_wp_error( $normalized ) ) {
			axismundi_activitypub_bridge_legacy_add_row( $report, 'ap_post', (string) $post->ID, (string) $post->guid, 'failed', 'blocked', $normalized->get_error_message() );
			continue;
		}
		$uri      = (string) $normalized['object_uri'];
		$existing = axismundi_op_remote_object_get( $uri, false );
		axismundi_activitypub_bridge_legacy_add_row( $report, 'ap_post', (string) $post->ID, $uri, is_array( $existing ) ? 'duplicate' : 'importable', 'purgeable', __( 'Purge becomes available only after URI and payload verification.', 'axismundi-activitypub-bridge' ) );
	}
}

/** Scan signature-verified historical Inbox rows without replaying them. */
function axismundi_activitypub_bridge_scan_legacy_inbox( array &$report, array $known_actor_uris ) : void {
	foreach ( axismundi_activitypub_bridge_legacy_posts( 'ap_inbox', $report ) as $post ) {
		$payload      = axismundi_activitypub_bridge_legacy_payload( $post );
		$activity_uri = is_array( $payload ) ? axismundi_act_member_uri( $payload['id'] ?? $post->guid ) : '';
		$actor_uri    = is_array( $payload ) ? axismundi_act_member_uri( $payload['actor'] ?? '' ) : '';
		$type         = is_array( $payload ) ? axismundi_act_type( $payload['type'] ?? '' ) : '';
		$object_uri   = is_array( $payload ) ? axismundi_act_member_uri( $payload['object'] ?? '' ) : '';
		$recipients   = array_map( 'intval', get_post_meta( $post->ID, '_activitypub_user_id', false ) );
		$missing_local = array_filter( $recipients, static fn( int $user_id ) : bool => ! axismundi_activitypub_bridge_legacy_local_actor( $user_id ) instanceof Axismundi_Actor );
		if ( '' === $activity_uri || '' === $actor_uri || '' === $type || '' === $object_uri || ! empty( $missing_local ) ) {
			axismundi_activitypub_bridge_legacy_add_row( $report, 'ap_inbox', (string) $post->ID, $activity_uri, 'failed', 'blocked', __( 'The Activity id, actor, object, type, or local recipient mapping is incomplete.', 'axismundi-activitypub-bridge' ) );
			continue;
		}
		$actor_known = axismundi_actors_get_by_uri( $actor_uri ) instanceof Axismundi_Actor || isset( $known_actor_uris[ $actor_uri ] );
		if ( ! $actor_known ) {
			axismundi_activitypub_bridge_legacy_add_row( $report, 'ap_inbox', (string) $post->ID, $activity_uri, 'failed', 'blocked', __( 'The remote Activity actor is absent from both Actor repositories.', 'axismundi-activitypub-bridge' ) );
			continue;
		}
		$existing = axismundi_act_get( $activity_uri );
		axismundi_activitypub_bridge_legacy_add_row( $report, 'ap_inbox', (string) $post->ID, $activity_uri, $existing instanceof Axismundi_Activity ? 'duplicate' : 'importable', 'purgeable', __( 'Local recipient aliases are calculated from stored recipient user IDs; payload URIs are not rewritten.', 'axismundi-activitypub-bridge' ) );
	}
}

/** Scan official transport spool rows; they are never imported as domain Activities. */
function axismundi_activitypub_bridge_scan_legacy_outbox( array &$report ) : void {
	foreach ( axismundi_activitypub_bridge_legacy_posts( 'ap_outbox', $report ) as $post ) {
		$status = (string) get_post_meta( $post->ID, '_activitypub_external_status', true );
		$pending = 'publish' !== $post->post_status || ( '' !== $status && 'delivered' !== $status );
		axismundi_activitypub_bridge_legacy_add_row(
			$report,
			'ap_outbox',
			(string) $post->ID,
			(string) $post->guid,
			$pending ? 'transport_pending' : 'deferred',
			$pending ? 'blocked' : 'deferred',
			$pending
				? __( 'Pending or failed transport must be drained or explicitly abandoned.', 'axismundi-activitypub-bridge' )
				: __( 'Delivered transport history is not an authoritative Axismundi Activity and is handled by a separate purge scope.', 'axismundi-activitypub-bridge' )
		);
	}
}

/** Scan local object federation markers to prevent accidental second Create activities. */
function axismundi_activitypub_bridge_scan_legacy_lifecycle( array &$report ) : void {
	$limit = max( 1, (int) apply_filters( 'axismundi_activitypub_bridge_legacy_scan_limit', 10000, 'activitypub_status' ) );
	$query = new WP_Query(
		array(
			'post_type'              => 'any',
			'post_status'            => 'any',
			'posts_per_page'         => $limit,
			'orderby'                => 'ID',
			'order'                  => 'ASC',
			'meta_key'               => 'activitypub_status', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
			'no_found_rows'          => false,
			'update_post_meta_cache' => true,
			'update_post_term_cache' => false,
		)
	);
	$report['summary']['activitypub_status'] = array( 'scanned' => 0, 'available' => (int) $query->found_posts, 'import' => array(), 'purge' => array() );
	if ( $query->found_posts > count( $query->posts ) ) {
		$report['truncated'] = true;
	}
	foreach ( $query->posts as $post ) {
		$state      = (string) get_post_meta( $post->ID, 'activitypub_status', true );
		$object_uri = function_exists( 'axismundi_op_post_object_uri' ) ? axismundi_op_post_object_uri( $post ) : '';
		$lifecycle  = '' !== $object_uri ? axismundi_act_get_object_lifecycle( $object_uri ) : null;
		$resolved   = 'federated' === $state && $lifecycle instanceof Axismundi_Activity;
		axismundi_activitypub_bridge_legacy_add_row(
			$report,
			'activitypub_status',
			(string) $post->ID,
			$object_uri,
			$resolved ? 'duplicate' : 'deferred',
			$resolved ? 'purgeable' : 'blocked',
			$resolved
				? __( 'An Axismundi lifecycle already protects this object from a second Create.', 'axismundi-activitypub-bridge' )
				: sprintf( /* translators: %s: official state. */ __( 'Official state "%s" requires a lifecycle baseline contract before cleanup.', 'axismundi-activitypub-bridge' ), $state )
		);
	}
}

/** Add deferred feature stores and signing-key custody to the report. */
function axismundi_activitypub_bridge_scan_legacy_deferred( array &$report ) : void {
	foreach ( array( 'ap_extrafield', 'ap_extrafield_blog' ) as $post_type ) {
		foreach ( axismundi_activitypub_bridge_legacy_posts( $post_type, $report ) as $post ) {
			axismundi_activitypub_bridge_legacy_add_row( $report, $post_type, (string) $post->ID, '', 'deferred', 'deferred', __( 'Actor verified-link migration is not implemented yet.', 'axismundi-activitypub-bridge' ) );
		}
	}
	$comment_count = (int) get_comments(
		array(
			'count'      => true,
			'meta_query' => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
				'relation' => 'OR',
				array( 'key' => 'activitypub_status', 'compare' => 'EXISTS' ),
				array( 'key' => '_activitypub_remote_actor_id', 'compare' => 'EXISTS' ),
			),
		)
	);
	if ( $comment_count > 0 ) {
		$report['summary']['activitypub_comments'] = array( 'scanned' => 0, 'available' => $comment_count, 'import' => array(), 'purge' => array() );
		axismundi_activitypub_bridge_legacy_add_row( $report, 'activitypub_comments', 'aggregate', '', 'deferred', 'deferred', __( 'Remote reply/comment migration waits for the Notes and Reply contract.', 'axismundi-activitypub-bridge' ) );
	}
	axismundi_activitypub_bridge_legacy_add_row( $report, 'signing_keys', 'official-options-usermeta', '', 'deferred', 'runtime_required', __( 'Official signing key material must remain available to Bridge transport.', 'axismundi-activitypub-bridge' ) );
}

/** Build a complete bounded report without writes or network requests. */
function axismundi_activitypub_bridge_scan_legacy_data() : array {
	$report = axismundi_activitypub_bridge_legacy_report();
	$known  = axismundi_activitypub_bridge_scan_legacy_actors( $report );
	axismundi_activitypub_bridge_scan_legacy_objects( $report );
	axismundi_activitypub_bridge_scan_legacy_inbox( $report, $known );
	axismundi_activitypub_bridge_scan_legacy_outbox( $report );
	axismundi_activitypub_bridge_scan_legacy_lifecycle( $report );
	axismundi_activitypub_bridge_scan_legacy_deferred( $report );
	foreach ( $report['summary'] as &$summary ) {
		if ( 0 === (int) $summary['available'] && (int) $summary['scanned'] > 0 ) {
			$summary['available'] = (int) $summary['scanned'];
		}
	}
	unset( $summary );
	ksort( $report['summary'] );
	return $report;
}
