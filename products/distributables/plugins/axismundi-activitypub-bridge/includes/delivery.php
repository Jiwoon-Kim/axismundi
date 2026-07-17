<?php
/**
 * Bridge-owned outbound Activity delivery spool.
 *
 * @package AxismundiActivityPubBridge
 */

defined( 'ABSPATH' ) || exit;

const AXISMUNDI_ACTIVITYPUB_BRIDGE_DELIVERY_POST_TYPE = 'ax_ap_delivery';
const AXISMUNDI_ACTIVITYPUB_BRIDGE_DELIVERY_HOOK      = 'axismundi_activitypub_bridge_process_delivery';

/** Register the private transport-only spool. */
function axismundi_activitypub_bridge_register_delivery_post_type() : void {
	register_post_type(
		AXISMUNDI_ACTIVITYPUB_BRIDGE_DELIVERY_POST_TYPE,
		array(
			'public'              => false,
			'publicly_queryable'  => false,
			'show_ui'             => false,
			'show_in_menu'        => false,
			'show_in_rest'        => false,
			'exclude_from_search' => true,
			'supports'            => array(),
			'can_export'          => false,
			'rewrite'             => false,
			'query_var'           => false,
		)
	);
}
add_action( 'init', 'axismundi_activitypub_bridge_register_delivery_post_type', 1 );

/** Normalize one absolute HTTP(S) URI. */
function axismundi_activitypub_bridge_delivery_uri( $value, bool $https = false ) : string {
	$uri   = is_scalar( $value ) ? trim( (string) $value ) : '';
	$parts = wp_parse_url( $uri );
	if ( ! is_array( $parts ) || empty( $parts['host'] ) || isset( $parts['user'] ) || isset( $parts['pass'] ) ) {
		return '';
	}
	$scheme = strtolower( (string) ( $parts['scheme'] ?? '' ) );
	return ( $https ? 'https' === $scheme : in_array( $scheme, array( 'http', 'https' ), true ) ) ? $uri : '';
}

/** Resolve one ActivityStreams URI member. */
function axismundi_activitypub_bridge_delivery_member_uri( $value ) : string {
	if ( is_scalar( $value ) ) {
		return axismundi_activitypub_bridge_delivery_uri( $value );
	}
	if ( is_array( $value ) && ! array_is_list( $value ) ) {
		return axismundi_activitypub_bridge_delivery_member_uri( $value['id'] ?? '' );
	}
	return '';
}

/** Find one Bridge delivery by exact Activity URI. */
function axismundi_activitypub_bridge_find_delivery( string $activity_uri ) : int {
	$ids = get_posts(
		array(
			'post_type'      => AXISMUNDI_ACTIVITYPUB_BRIDGE_DELIVERY_POST_TYPE,
			'post_status'    => array( 'pending', 'publish' ),
			'posts_per_page' => 1,
			'fields'         => 'ids',
			'meta_key'       => '_ax_ap_activity_uri_hash', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
			'meta_value'     => hash( 'sha256', $activity_uri ), // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
		)
	);
	if ( empty( $ids[0] ) ) {
		return 0;
	}
	$id = (int) $ids[0];
	return hash_equals( $activity_uri, (string) get_post_meta( $id, '_ax_ap_activity_uri', true ) ) ? $id : 0;
}

/** Queue one immutable Activity payload for explicit recipient Inboxes. */
function axismundi_activitypub_bridge_enqueue_delivery( array $payload, array $sender, array $inboxes ) {
	if ( isset( $sender['private_key'] ) ) {
		return new WP_Error( 'ax_bridge_delivery_private_key', __( 'Private key material must not be persisted in the delivery spool.', 'axismundi-activitypub-bridge' ) );
	}
	$activity_uri = axismundi_activitypub_bridge_delivery_uri( $payload['id'] ?? '' );
	$actor_uri    = axismundi_activitypub_bridge_delivery_uri( $sender['actor_uri'] ?? '' );
	$key_id       = axismundi_activitypub_bridge_delivery_uri( $sender['key_id'] ?? '' );
	if ( '' === $activity_uri || '' === $actor_uri || '' === $key_id || axismundi_activitypub_bridge_delivery_member_uri( $payload['actor'] ?? '' ) !== $actor_uri ) {
		return new WP_Error( 'ax_bridge_delivery_sender', __( 'The Activity or signing descriptor is invalid.', 'axismundi-activitypub-bridge' ) );
	}

	$recipients = array();
	foreach ( $inboxes as $inbox ) {
		$inbox = axismundi_activitypub_bridge_delivery_uri( $inbox, true );
		if ( '' === $inbox || ! wp_http_validate_url( $inbox ) ) {
			return new WP_Error( 'ax_bridge_delivery_inbox', __( 'Every recipient Inbox must be a public HTTPS URL.', 'axismundi-activitypub-bridge' ) );
		}
		$recipients[] = $inbox;
	}
	$recipients = array_values( array_unique( $recipients ) );
	if ( empty( $recipients ) ) {
		return new WP_Error( 'ax_bridge_delivery_recipients', __( 'At least one recipient Inbox is required.', 'axismundi-activitypub-bridge' ) );
	}

	$json = wp_json_encode( $payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
	if ( ! is_string( $json ) || strlen( $json ) > MB_IN_BYTES ) {
		return new WP_Error( 'ax_bridge_delivery_payload', __( 'The Activity payload is invalid or exceeds one MiB.', 'axismundi-activitypub-bridge' ) );
	}
	$existing = axismundi_activitypub_bridge_find_delivery( $activity_uri );
	if ( $existing > 0 ) {
		return $existing;
	}

	$has_kses = false !== has_filter( 'content_save_pre', 'wp_filter_post_kses' );
	if ( $has_kses ) {
		kses_remove_filters();
	}
	$post_id = wp_insert_post(
		array(
			'post_type'    => AXISMUNDI_ACTIVITYPUB_BRIDGE_DELIVERY_POST_TYPE,
			'post_status'  => 'pending',
			'post_author'  => 0,
			'post_title'   => sprintf( '[%s] %s', sanitize_text_field( (string) ( $payload['type'] ?? 'Activity' ) ), $activity_uri ),
			'post_content' => wp_slash( $json ),
			'meta_input'   => array(
				'_ax_ap_activity_uri'    => $activity_uri,
				'_ax_ap_activity_uri_hash' => hash( 'sha256', $activity_uri ),
				'_ax_ap_actor_uri'       => $actor_uri,
				'_ax_ap_key_id'          => $key_id,
				'_ax_ap_inboxes'         => wp_json_encode( $recipients ),
				'_ax_ap_pending_inboxes' => wp_json_encode( $recipients ),
				'_ax_ap_attempt'         => 0,
				'_ax_ap_status'          => 'queued',
			),
		),
		true
	);
	if ( $has_kses ) {
		kses_init_filters();
	}
	if ( is_wp_error( $post_id ) ) {
		return $post_id;
	}
	wp_schedule_single_event( time(), AXISMUNDI_ACTIVITYPUB_BRIDGE_DELIVERY_HOOK, array( (int) $post_id, 1 ) );
	return (int) $post_id;
}

/** Maximum attempts for one transport delivery. */
function axismundi_activitypub_bridge_delivery_max_attempts() : int {
	return max( 1, min( 10, (int) apply_filters( 'axismundi_activitypub_bridge_delivery_max_attempts', 5 ) ) );
}

/** Delay before the next transport attempt. */
function axismundi_activitypub_bridge_delivery_retry_delay( int $attempt ) : int {
	$delay = max( 60, $attempt * $attempt * 60 );
	return max( 60, min( DAY_IN_SECONDS, (int) apply_filters( 'axismundi_activitypub_bridge_delivery_retry_delay', $delay, $attempt ) ) );
}

/** Whether one transport result should be retried. */
function axismundi_activitypub_bridge_delivery_should_retry( $response, int $code ) : bool {
	return is_wp_error( $response ) || in_array( $code, array( 408, 425, 429, 500, 502, 503, 504 ), true );
}

/** Resolve a local Actor's private signing key only for the active request. */
function axismundi_activitypub_bridge_delivery_identity( WP_Post $job ) {
	$actor_uri = (string) get_post_meta( $job->ID, '_ax_ap_actor_uri', true );
	$key_id    = (string) get_post_meta( $job->ID, '_ax_ap_key_id', true );
	$actor     = axismundi_actors_get_by_uri( $actor_uri );
	if ( ! $actor instanceof Axismundi_Actor || ! $actor->is_local() || 'public' !== $actor->get_status() ) {
		return new WP_Error( 'ax_bridge_delivery_actor', __( 'The local signing Actor is unavailable.', 'axismundi-activitypub-bridge' ) );
	}
	$expected = axismundi_activitypub_bridge_sender( $actor );
	if ( ! hash_equals( $expected['key_id'], $key_id ) ) {
		return new WP_Error( 'ax_bridge_delivery_key', __( 'The signing key reference no longer matches the Actor.', 'axismundi-activitypub-bridge' ) );
	}
	$user_id = $actor->get_local_user_id();
	$key     = $user_id ? Activitypub\Collection\Actors::get_private_key( $user_id ) : Activitypub\Application::get_private_key();
	return is_string( $key ) && '' !== $key
		? array( 'key_id' => $key_id, 'private_key' => $key )
		: new WP_Error( 'ax_bridge_delivery_key', __( 'The private signing key is unavailable.', 'axismundi-activitypub-bridge' ) );
}

/** Complete one transport job without changing the authoritative Activity ledger. */
function axismundi_activitypub_bridge_finish_delivery( int $post_id, string $status, string $error = '' ) : void {
	update_post_meta( $post_id, '_ax_ap_status', $status );
	update_post_meta( $post_id, '_ax_ap_last_error', $error );
	wp_publish_post( $post_id );
}

/** Process one Bridge-owned transport job. */
function axismundi_activitypub_bridge_process_delivery( int $post_id, int $attempt = 1 ) : void {
	$job = get_post( $post_id );
	if ( ! $job instanceof WP_Post || AXISMUNDI_ACTIVITYPUB_BRIDGE_DELIVERY_POST_TYPE !== $job->post_type || 'pending' !== $job->post_status ) {
		return;
	}

	$lock_time = time();
	$old_lock  = (int) get_post_meta( $job->ID, '_ax_ap_worker_lock', true );
	if ( $old_lock > 0 && $old_lock < $lock_time - ( 5 * MINUTE_IN_SECONDS ) ) {
		delete_post_meta( $job->ID, '_ax_ap_worker_lock', $old_lock );
	}
	if ( ! add_post_meta( $job->ID, '_ax_ap_worker_lock', $lock_time, true ) ) {
		return;
	}

	try {
		$identity = axismundi_activitypub_bridge_delivery_identity( $job );
		if ( is_wp_error( $identity ) ) {
			axismundi_activitypub_bridge_finish_delivery( $job->ID, 'failed', $identity->get_error_message() );
			return;
		}
		$pending = json_decode( (string) get_post_meta( $job->ID, '_ax_ap_pending_inboxes', true ), true );
		$pending = is_array( $pending ) ? $pending : array();
		$retry   = array();
		$errors  = array();
		foreach ( $pending as $inbox ) {
			$response = wp_safe_remote_post(
				$inbox,
				array(
					'body'                => $job->post_content,
					'headers'             => array(
						'Accept'       => 'application/activity+json',
						'Content-Type' => 'application/activity+json',
						'Date'         => gmdate( 'D, d M Y H:i:s T' ),
					),
					'timeout'             => 10,
					'limit_response_size' => MB_IN_BYTES,
					'redirection'         => 0,
					'data_format'         => 'body',
					'key_id'              => $identity['key_id'],
					'private_key'         => $identity['private_key'],
				)
			);
			$code = is_wp_error( $response ) ? 0 : (int) wp_remote_retrieve_response_code( $response );
			if ( axismundi_activitypub_bridge_delivery_should_retry( $response, $code ) ) {
				$retry[] = $inbox;
			}
			if ( is_wp_error( $response ) || $code < 200 || $code >= 300 ) {
				$errors[] = is_wp_error( $response ) ? $response->get_error_message() : 'HTTP ' . $code;
			}
		}

		update_post_meta( $job->ID, '_ax_ap_attempt', max( 1, $attempt ) );
		update_post_meta( $job->ID, '_ax_ap_pending_inboxes', wp_json_encode( $retry ) );
		if ( ! empty( $retry ) && $attempt < axismundi_activitypub_bridge_delivery_max_attempts() ) {
			update_post_meta( $job->ID, '_ax_ap_status', 'retrying' );
			update_post_meta( $job->ID, '_ax_ap_last_error', implode( '; ', $errors ) );
			$next = $attempt + 1;
			wp_schedule_single_event( time() + axismundi_activitypub_bridge_delivery_retry_delay( $next ), AXISMUNDI_ACTIVITYPUB_BRIDGE_DELIVERY_HOOK, array( $job->ID, $next ) );
			return;
		}
		axismundi_activitypub_bridge_finish_delivery( $job->ID, empty( $errors ) ? 'delivered' : 'failed', implode( '; ', $errors ) );
	} finally {
		delete_post_meta( $job->ID, '_ax_ap_worker_lock', $lock_time );
	}
}
add_action( AXISMUNDI_ACTIVITYPUB_BRIDGE_DELIVERY_HOOK, 'axismundi_activitypub_bridge_process_delivery', 10, 2 );

/** Recent Bridge transport jobs for read-only administration. */
function axismundi_activitypub_bridge_delivery_jobs( int $limit = 100 ) : array {
	return get_posts(
		array(
			'post_type'      => AXISMUNDI_ACTIVITYPUB_BRIDGE_DELIVERY_POST_TYPE,
			'post_status'    => array( 'pending', 'publish' ),
			'posts_per_page' => max( 1, min( 200, $limit ) ),
			'orderby'        => 'date',
			'order'          => 'DESC',
		)
	);
}

/** Remove fork-worker events for one legacy external Outbox row. */
function axismundi_activitypub_bridge_clear_legacy_delivery_events( int $post_id ) : void {
	for ( $attempt = 1; $attempt <= 10; ++$attempt ) {
		wp_clear_scheduled_hook( 'activitypub_process_external_delivery', array( $post_id, $attempt ) );
	}
}

/**
 * Move transport-only rows out of the official Outbox CPT without deleting history.
 *
 * Source rows are marked published only after an exact Bridge job exists, preventing
 * the stock Outbox scheduler from claiming their `pending` status.
 */
function axismundi_activitypub_bridge_migrate_external_outbox() : void {
	if ( ! axismundi_activitypub_bridge_ready() || 1 === (int) get_option( 'ax_activitypub_bridge_delivery_migration', 0 ) ) {
		return;
	}
	$source_ids = get_posts(
		array(
			'post_type'      => 'ap_outbox',
			'post_status'    => array( 'pending', 'publish' ),
			'posts_per_page' => -1,
			'fields'         => 'ids',
			'meta_key'       => '_activitypub_external_delivery', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
			'meta_value'     => 1, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
		)
	);
	$complete = true;
	foreach ( $source_ids as $source_id ) {
		$source_id = (int) $source_id;
		$migrated  = (int) get_post_meta( $source_id, '_ax_ap_migrated_to', true );
		if ( $migrated > 0 && AXISMUNDI_ACTIVITYPUB_BRIDGE_DELIVERY_POST_TYPE === get_post_type( $migrated ) ) {
			axismundi_activitypub_bridge_clear_legacy_delivery_events( $source_id );
			if ( 'pending' === get_post_status( $source_id ) ) {
				wp_publish_post( $source_id );
			}
			continue;
		}
		$source  = get_post( $source_id );
		$payload = $source instanceof WP_Post ? json_decode( $source->post_content, true ) : null;
		$inboxes = json_decode( (string) get_post_meta( $source_id, '_activitypub_external_inboxes', true ), true );
		$sender  = array(
			'actor_uri' => (string) get_post_meta( $source_id, '_activitypub_external_actor_uri', true ),
			'key_id'    => (string) get_post_meta( $source_id, '_activitypub_external_key_id', true ),
		);
		if ( ! is_array( $payload ) || ! is_array( $inboxes ) ) {
			$complete = false;
			continue;
		}
		$job_id = axismundi_activitypub_bridge_enqueue_delivery( $payload, $sender, $inboxes );
		if ( is_wp_error( $job_id ) ) {
			$complete = false;
			continue;
		}
		$job_id       = (int) $job_id;
		$status       = (string) get_post_meta( $source_id, '_activitypub_external_status', true );
		$attempt      = (int) get_post_meta( $source_id, '_activitypub_external_attempt', true );
		$pending      = json_decode( (string) get_post_meta( $source_id, '_activitypub_external_pending_inboxes', true ), true );
		$pending      = is_array( $pending ) ? $pending : array();
		$last_error   = (string) get_post_meta( $source_id, '_activitypub_external_last_error', true );
		if ( ! in_array( $status, array( 'delivered', 'failed' ), true ) && empty( $pending ) ) {
			$pending = $inboxes;
		}
		wp_clear_scheduled_hook( AXISMUNDI_ACTIVITYPUB_BRIDGE_DELIVERY_HOOK, array( $job_id, 1 ) );
		update_post_meta( $job_id, '_ax_ap_attempt', $attempt );
		update_post_meta( $job_id, '_ax_ap_pending_inboxes', wp_json_encode( $pending ) );
		update_post_meta( $job_id, '_ax_ap_last_error', $last_error );
		if ( in_array( $status, array( 'delivered', 'failed' ), true ) ) {
			axismundi_activitypub_bridge_finish_delivery( $job_id, $status, $last_error );
		} else {
			update_post_meta( $job_id, '_ax_ap_status', 'retrying' === $status ? 'retrying' : 'queued' );
			wp_schedule_single_event( time(), AXISMUNDI_ACTIVITYPUB_BRIDGE_DELIVERY_HOOK, array( $job_id, max( 1, $attempt + 1 ) ) );
		}

		update_post_meta( $source_id, '_ax_ap_migrated_to', $job_id );
		axismundi_activitypub_bridge_clear_legacy_delivery_events( $source_id );
		if ( 'pending' === get_post_status( $source_id ) ) {
			wp_publish_post( $source_id );
		}
	}
	if ( $complete ) {
		update_option( 'ax_activitypub_bridge_delivery_migration', 1, false );
	}
}
add_action( 'init', 'axismundi_activitypub_bridge_migrate_external_outbox', 20 );
