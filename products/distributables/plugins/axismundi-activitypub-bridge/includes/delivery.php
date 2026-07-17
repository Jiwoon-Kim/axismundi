<?php
/**
 * Bridge-owned outbound Activity delivery spool.
 *
 * @package AxismundiActivityPubBridge
 */

defined( 'ABSPATH' ) || exit;

const AXISMUNDI_ACTIVITYPUB_BRIDGE_DELIVERY_DB_VERSION        = '1';
const AXISMUNDI_ACTIVITYPUB_BRIDGE_DELIVERY_DB_VERSION_OPTION = 'ax_activitypub_bridge_delivery_db_version';
const AXISMUNDI_ACTIVITYPUB_BRIDGE_DELIVERY_HOOK              = 'axismundi_activitypub_bridge_process_delivery';
const AXISMUNDI_ACTIVITYPUB_BRIDGE_LEGACY_POST_TYPE           = 'ax_ap_delivery';

/** Delivery table for the current site. */
function axismundi_activitypub_bridge_delivery_table() : string {
	global $wpdb;
	return $wpdb->prefix . 'ax_ap_deliveries';
}

/** Create or upgrade the private delivery spool and record only a verified version. */
function axismundi_activitypub_bridge_install_delivery_table() : bool {
	global $wpdb;
	require_once ABSPATH . 'wp-admin/includes/upgrade.php';

	$table   = axismundi_activitypub_bridge_delivery_table();
	$charset = $wpdb->get_charset_collate();
	dbDelta(
		"CREATE TABLE {$table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			activity_uri text NOT NULL,
			activity_uri_hash char(64) NOT NULL,
			actor_uri text NOT NULL,
			actor_uri_hash char(64) NOT NULL,
			key_id text NOT NULL,
			payload_json longtext NOT NULL,
			inboxes_json longtext NOT NULL,
			pending_inboxes_json longtext NOT NULL,
			status varchar(16) NOT NULL,
			attempt smallint(5) unsigned NOT NULL DEFAULT 0,
			available_at datetime NOT NULL,
			lock_token varchar(64) NULL,
			locked_at datetime NULL,
			last_error text NULL,
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY activity_uri_hash (activity_uri_hash),
			KEY due (status, available_at),
			KEY locked_at (locked_at)
		) ENGINE=InnoDB {$charset};"
	);

	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Trusted table identifier.
	$columns = $wpdb->get_col( "SHOW COLUMNS FROM {$table}" );
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Trusted table identifier.
	$indexes = $wpdb->get_results( "SHOW INDEX FROM {$table} WHERE Key_name = 'activity_uri_hash'", ARRAY_A );
	$status  = $wpdb->get_row( $wpdb->prepare( 'SHOW TABLE STATUS LIKE %s', $wpdb->esc_like( $table ) ), ARRAY_A );
	$needed  = array(
		'id', 'activity_uri', 'activity_uri_hash', 'actor_uri', 'actor_uri_hash', 'key_id',
		'payload_json', 'inboxes_json', 'pending_inboxes_json', 'status', 'attempt',
		'available_at', 'lock_token', 'locked_at', 'last_error', 'created_at', 'updated_at',
	);
	$valid = empty( array_diff( $needed, $columns ) )
		&& ! empty( $indexes )
		&& 0 === (int) $indexes[0]['Non_unique']
		&& 'InnoDB' === (string) ( $status['Engine'] ?? '' );
	if ( $valid ) {
		update_option( AXISMUNDI_ACTIVITYPUB_BRIDGE_DELIVERY_DB_VERSION_OPTION, AXISMUNDI_ACTIVITYPUB_BRIDGE_DELIVERY_DB_VERSION, false );
	}
	return $valid;
}

/** Ensure upgrades run on ordinary plugin updates as well as activation. */
function axismundi_activitypub_bridge_maybe_install_delivery_table() : void {
	if ( AXISMUNDI_ACTIVITYPUB_BRIDGE_DELIVERY_DB_VERSION !== (string) get_option( AXISMUNDI_ACTIVITYPUB_BRIDGE_DELIVERY_DB_VERSION_OPTION, '' ) ) {
		axismundi_activitypub_bridge_install_delivery_table();
	}
}
add_action( 'plugins_loaded', 'axismundi_activitypub_bridge_maybe_install_delivery_table', 20 );

/** Whether the verified delivery table is ready. */
function axismundi_activitypub_bridge_delivery_ready() : bool {
	return AXISMUNDI_ACTIVITYPUB_BRIDGE_DELIVERY_DB_VERSION === (string) get_option( AXISMUNDI_ACTIVITYPUB_BRIDGE_DELIVERY_DB_VERSION_OPTION, '' );
}

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

/** Read one delivery row by id. */
function axismundi_activitypub_bridge_get_delivery( int $id ) : ?object {
	global $wpdb;
	if ( $id <= 0 || ! axismundi_activitypub_bridge_delivery_ready() ) {
		return null;
	}
	$table = axismundi_activitypub_bridge_delivery_table();
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Trusted table identifier.
	$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $id ) );
	return is_object( $row ) ? $row : null;
}

/** Find one Bridge delivery by exact Activity URI. */
function axismundi_activitypub_bridge_find_delivery( string $activity_uri ) : int {
	global $wpdb;
	if ( '' === $activity_uri || ! axismundi_activitypub_bridge_delivery_ready() ) {
		return 0;
	}
	$table = axismundi_activitypub_bridge_delivery_table();
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Trusted table identifier.
	$row = $wpdb->get_row( $wpdb->prepare( "SELECT id, activity_uri FROM {$table} WHERE activity_uri_hash = %s", hash( 'sha256', $activity_uri ) ) );
	return is_object( $row ) && hash_equals( $activity_uri, (string) $row->activity_uri ) ? (int) $row->id : 0;
}

/** Queue one immutable Activity payload for explicit recipient Inboxes. */
function axismundi_activitypub_bridge_enqueue_delivery( array $payload, array $sender, array $inboxes, bool $schedule = true ) {
	global $wpdb;
	if ( ! axismundi_activitypub_bridge_delivery_ready() ) {
		return new WP_Error( 'ax_bridge_delivery_schema', __( 'The delivery spool is unavailable.', 'axismundi-activitypub-bridge' ) );
	}
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

	$now   = current_time( 'mysql', true );
	$table = axismundi_activitypub_bridge_delivery_table();
	$ok    = $wpdb->insert(
		$table,
		array(
			'activity_uri'          => $activity_uri,
			'activity_uri_hash'     => hash( 'sha256', $activity_uri ),
			'actor_uri'             => $actor_uri,
			'actor_uri_hash'        => hash( 'sha256', $actor_uri ),
			'key_id'                => $key_id,
			'payload_json'          => $json,
			'inboxes_json'          => wp_json_encode( $recipients ),
			'pending_inboxes_json'  => wp_json_encode( $recipients ),
			'status'                => 'queued',
			'attempt'               => 0,
			'available_at'          => $now,
			'created_at'            => $now,
			'updated_at'            => $now,
		),
		array( '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%s' )
	);
	if ( false === $ok ) {
		$existing = axismundi_activitypub_bridge_find_delivery( $activity_uri );
		return $existing > 0 ? $existing : new WP_Error( 'ax_bridge_delivery_insert', __( 'The delivery job could not be queued.', 'axismundi-activitypub-bridge' ) );
	}
	$id = (int) $wpdb->insert_id;
	if ( $schedule ) {
		wp_schedule_single_event( time(), AXISMUNDI_ACTIVITYPUB_BRIDGE_DELIVERY_HOOK, array( $id, 1 ) );
	}
	return $id;
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
function axismundi_activitypub_bridge_delivery_identity( object $job ) {
	$actor = axismundi_actors_get_by_uri( (string) $job->actor_uri );
	if ( ! $actor instanceof Axismundi_Actor || ! $actor->is_local() || 'public' !== $actor->get_status() ) {
		return new WP_Error( 'ax_bridge_delivery_actor', __( 'The local signing Actor is unavailable.', 'axismundi-activitypub-bridge' ) );
	}
	$expected = axismundi_activitypub_bridge_sender( $actor );
	if ( ! hash_equals( $expected['key_id'], (string) $job->key_id ) ) {
		return new WP_Error( 'ax_bridge_delivery_key', __( 'The signing key reference no longer matches the Actor.', 'axismundi-activitypub-bridge' ) );
	}
	$user_id = $actor->get_local_user_id();
	$key     = $user_id ? Activitypub\Collection\Actors::get_private_key( $user_id ) : Activitypub\Application::get_private_key();
	return is_string( $key ) && '' !== $key
		? array( 'key_id' => (string) $job->key_id, 'private_key' => $key )
		: new WP_Error( 'ax_bridge_delivery_key', __( 'The private signing key is unavailable.', 'axismundi-activitypub-bridge' ) );
}

/** Atomically claim one due job. */
function axismundi_activitypub_bridge_claim_delivery( int $id, string $token, ?string $now = null ) : bool {
	global $wpdb;
	if ( $id <= 0 || '' === $token || ! axismundi_activitypub_bridge_delivery_ready() ) {
		return false;
	}
	$table = axismundi_activitypub_bridge_delivery_table();
	$now   = $now ?? current_time( 'mysql', true );
	$stale = gmdate( 'Y-m-d H:i:s', strtotime( $now . ' UTC' ) - ( 5 * MINUTE_IN_SECONDS ) );
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Trusted table identifier.
	$changed = $wpdb->query(
		$wpdb->prepare(
			"UPDATE {$table}
			 SET lock_token = %s, locked_at = %s, updated_at = %s
			 WHERE id = %d
			   AND status IN ('queued', 'retrying')
			   AND available_at <= %s
			   AND (lock_token IS NULL OR locked_at IS NULL OR locked_at < %s)",
			$token,
			$now,
			$now,
			$id,
			$now,
			$stale
		)
	);
	return 1 === $changed;
}

/** Release a claim only when the caller still owns it. */
function axismundi_activitypub_bridge_release_delivery( int $id, string $token ) : void {
	global $wpdb;
	$table = axismundi_activitypub_bridge_delivery_table();
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Trusted table identifier.
	$wpdb->query( $wpdb->prepare( "UPDATE {$table} SET lock_token = NULL, locked_at = NULL WHERE id = %d AND lock_token = %s", $id, $token ) );
}

/** Renew a claim while its worker still owns the token. */
function axismundi_activitypub_bridge_touch_delivery_claim( int $id, string $token ) : bool {
	global $wpdb;
	$table = axismundi_activitypub_bridge_delivery_table();
	$now   = current_time( 'mysql', true );
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Trusted table identifier.
	$changed = $wpdb->query( $wpdb->prepare( "UPDATE {$table} SET locked_at = %s, updated_at = %s WHERE id = %d AND lock_token = %s", $now, $now, $id, $token ) );
	if ( 1 === $changed ) {
		return true;
	}
	// MySQL reports zero changed rows when a same-second heartbeat writes identical values.
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Trusted table identifier.
	$owner = $wpdb->get_var( $wpdb->prepare( "SELECT lock_token FROM {$table} WHERE id = %d", $id ) );
	return is_string( $owner ) && hash_equals( $token, $owner );
}

/** Complete one transport job without changing the authoritative Activity ledger. */
function axismundi_activitypub_bridge_finish_delivery( int $id, string $token, string $status, string $error = '' ) : void {
	global $wpdb;
	$table = axismundi_activitypub_bridge_delivery_table();
	$now   = current_time( 'mysql', true );
	$error = substr( $error, 0, 4000 );
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Trusted table identifier.
	$wpdb->query(
		$wpdb->prepare(
			"UPDATE {$table} SET status = %s, last_error = %s, lock_token = NULL, locked_at = NULL, updated_at = %s WHERE id = %d AND lock_token = %s",
			$status,
			$error,
			$now,
			$id,
			$token
		)
	);
}

/** Process one Bridge-owned transport job. */
function axismundi_activitypub_bridge_process_delivery( int $id, int $attempt = 1 ) : void {
	global $wpdb;
	$token = wp_generate_uuid4();
	if ( ! axismundi_activitypub_bridge_claim_delivery( $id, $token ) ) {
		return;
	}

	try {
		$job = axismundi_activitypub_bridge_get_delivery( $id );
		if ( ! is_object( $job ) || ! hash_equals( $token, (string) $job->lock_token ) ) {
			return;
		}
		$identity = axismundi_activitypub_bridge_delivery_identity( $job );
		if ( is_wp_error( $identity ) ) {
			axismundi_activitypub_bridge_finish_delivery( $id, $token, 'failed', $identity->get_error_message() );
			return;
		}
		$pending = json_decode( (string) $job->pending_inboxes_json, true );
		if ( ! is_array( $pending ) || empty( $pending ) ) {
			axismundi_activitypub_bridge_finish_delivery( $id, $token, 'failed', 'The pending recipient set is empty or invalid.' );
			return;
		}
		$retry  = array();
		$errors = array();
		foreach ( $pending as $inbox ) {
			if ( ! axismundi_activitypub_bridge_touch_delivery_claim( $id, $token ) ) {
				return;
			}
			$response = wp_safe_remote_post(
				$inbox,
				array(
					'body'                => (string) $job->payload_json,
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

		$attempt = max( 1, $attempt );
		$now     = current_time( 'mysql', true );
		$table   = axismundi_activitypub_bridge_delivery_table();
		if ( ! empty( $retry ) && $attempt < axismundi_activitypub_bridge_delivery_max_attempts() ) {
			$next         = $attempt + 1;
			$available_at = gmdate( 'Y-m-d H:i:s', time() + axismundi_activitypub_bridge_delivery_retry_delay( $next ) );
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Trusted table identifier.
			$updated = $wpdb->query(
				$wpdb->prepare(
					"UPDATE {$table} SET status = 'retrying', attempt = %d, pending_inboxes_json = %s, available_at = %s, last_error = %s, lock_token = NULL, locked_at = NULL, updated_at = %s WHERE id = %d AND lock_token = %s",
					$attempt,
					wp_json_encode( $retry ),
					$available_at,
					substr( implode( '; ', $errors ), 0, 4000 ),
					$now,
					$id,
					$token
				)
			);
			if ( 1 === $updated ) {
				wp_schedule_single_event( strtotime( $available_at . ' UTC' ), AXISMUNDI_ACTIVITYPUB_BRIDGE_DELIVERY_HOOK, array( $id, $next ) );
			}
			return;
		}

		// Record the final attempt before the terminal transition.
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Trusted table identifier.
		$wpdb->query( $wpdb->prepare( "UPDATE {$table} SET attempt = %d, pending_inboxes_json = %s WHERE id = %d AND lock_token = %s", $attempt, wp_json_encode( $retry ), $id, $token ) );
		axismundi_activitypub_bridge_finish_delivery( $id, $token, empty( $errors ) ? 'delivered' : 'failed', implode( '; ', $errors ) );
	} finally {
		axismundi_activitypub_bridge_release_delivery( $id, $token );
	}
}
add_action( AXISMUNDI_ACTIVITYPUB_BRIDGE_DELIVERY_HOOK, 'axismundi_activitypub_bridge_process_delivery', 10, 2 );

/** Recent Bridge transport jobs for read-only administration. */
function axismundi_activitypub_bridge_delivery_jobs( int $limit = 100 ) : array {
	global $wpdb;
	if ( ! axismundi_activitypub_bridge_delivery_ready() ) {
		return array();
	}
	$table = axismundi_activitypub_bridge_delivery_table();
	$limit = max( 1, min( 200, $limit ) );
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Trusted table identifier.
	return $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} ORDER BY created_at DESC, id DESC LIMIT %d", $limit ) );
}

/** Remove scheduled events for one provisional CPT delivery. */
function axismundi_activitypub_bridge_clear_provisional_delivery_events( int $id ) : void {
	for ( $attempt = 1; $attempt <= 10; ++$attempt ) {
		wp_clear_scheduled_hook( AXISMUNDI_ACTIVITYPUB_BRIDGE_DELIVERY_HOOK, array( $id, $attempt ) );
	}
}

/** Remove fork-worker events for one legacy external Outbox row. */
function axismundi_activitypub_bridge_clear_legacy_delivery_events( int $id ) : void {
	for ( $attempt = 1; $attempt <= 10; ++$attempt ) {
		wp_clear_scheduled_hook( 'activitypub_process_external_delivery', array( $id, $attempt ) );
	}
}

/** Apply preserved transport state to one newly migrated table row. */
function axismundi_activitypub_bridge_apply_migrated_delivery_state( int $id, string $status, int $attempt, array $pending, string $error ) : bool {
	global $wpdb;
	if ( ! in_array( $status, array( 'queued', 'retrying', 'delivered', 'failed' ), true ) ) {
		$status = 'failed';
		$error  = '' !== $error ? $error : 'The source delivery status was not recognized; retransmission was suppressed.';
	}
	$table = axismundi_activitypub_bridge_delivery_table();
	$now   = current_time( 'mysql', true );
	$ok    = $wpdb->update(
		$table,
		array(
			'status'               => $status,
			'attempt'              => max( 0, $attempt ),
			'pending_inboxes_json' => wp_json_encode( $pending ),
			'last_error'           => substr( $error, 0, 4000 ),
			'available_at'         => $now,
			'updated_at'           => $now,
		),
		array( 'id' => $id ),
		array( '%s', '%d', '%s', '%s', '%s', '%s' ),
		array( '%d' )
	);
	if ( false !== $ok && in_array( $status, array( 'queued', 'retrying' ), true ) ) {
		wp_schedule_single_event( time(), AXISMUNDI_ACTIVITYPUB_BRIDGE_DELIVERY_HOOK, array( $id, max( 1, $attempt + 1 ) ) );
	}
	return false !== $ok;
}

/** Migrate provisional Bridge CPT jobs without deleting their source rows. */
function axismundi_activitypub_bridge_migrate_delivery_cpt() : bool {
	global $wpdb;
	$posts = $wpdb->posts;
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Core posts table identifier.
	$source_ids = $wpdb->get_col( $wpdb->prepare( "SELECT ID FROM {$posts} WHERE post_type = %s", AXISMUNDI_ACTIVITYPUB_BRIDGE_LEGACY_POST_TYPE ) );
	$complete   = true;
	foreach ( $source_ids as $source_id ) {
		$source_id = (int) $source_id;
		$source    = get_post( $source_id );
		$payload   = $source instanceof WP_Post ? json_decode( $source->post_content, true ) : null;
		$inboxes   = json_decode( (string) get_post_meta( $source_id, '_ax_ap_inboxes', true ), true );
		$sender    = array(
			'actor_uri' => (string) get_post_meta( $source_id, '_ax_ap_actor_uri', true ),
			'key_id'    => (string) get_post_meta( $source_id, '_ax_ap_key_id', true ),
		);
		if ( ! is_array( $payload ) || ! is_array( $inboxes ) ) {
			$complete = false;
			continue;
		}
		axismundi_activitypub_bridge_clear_provisional_delivery_events( $source_id );
		$id = axismundi_activitypub_bridge_enqueue_delivery( $payload, $sender, $inboxes, false );
		if ( is_wp_error( $id ) ) {
			$complete = false;
			continue;
		}
		$id      = (int) $id;
		$status  = (string) get_post_meta( $source_id, '_ax_ap_status', true );
		$attempt = (int) get_post_meta( $source_id, '_ax_ap_attempt', true );
		$pending = json_decode( (string) get_post_meta( $source_id, '_ax_ap_pending_inboxes', true ), true );
		$pending = is_array( $pending ) ? $pending : array();
		if ( ! in_array( $status, array( 'delivered', 'failed' ), true ) && empty( $pending ) ) {
			$pending = $inboxes;
		}
		if ( ! axismundi_activitypub_bridge_apply_migrated_delivery_state( $id, $status, $attempt, $pending, (string) get_post_meta( $source_id, '_ax_ap_last_error', true ) ) ) {
			$complete = false;
			continue;
		}
		update_post_meta( $source_id, '_ax_ap_migrated_to_table', $id );
	}
	return $complete;
}

/** Migrate experimental fork Outbox jobs without deleting their source rows. */
function axismundi_activitypub_bridge_migrate_external_outbox_rows() : bool {
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
		$source    = get_post( $source_id );
		$payload   = $source instanceof WP_Post ? json_decode( $source->post_content, true ) : null;
		$inboxes   = json_decode( (string) get_post_meta( $source_id, '_activitypub_external_inboxes', true ), true );
		$sender    = array(
			'actor_uri' => (string) get_post_meta( $source_id, '_activitypub_external_actor_uri', true ),
			'key_id'    => (string) get_post_meta( $source_id, '_activitypub_external_key_id', true ),
		);
		if ( ! is_array( $payload ) || ! is_array( $inboxes ) ) {
			$complete = false;
			continue;
		}
		$id = axismundi_activitypub_bridge_enqueue_delivery( $payload, $sender, $inboxes, false );
		if ( is_wp_error( $id ) ) {
			$complete = false;
			continue;
		}
		$id      = (int) $id;
		$status  = (string) get_post_meta( $source_id, '_activitypub_external_status', true );
		$attempt = (int) get_post_meta( $source_id, '_activitypub_external_attempt', true );
		$pending = json_decode( (string) get_post_meta( $source_id, '_activitypub_external_pending_inboxes', true ), true );
		$pending = is_array( $pending ) ? $pending : array();
		if ( ! in_array( $status, array( 'delivered', 'failed' ), true ) && empty( $pending ) ) {
			$pending = $inboxes;
		}
		if ( ! axismundi_activitypub_bridge_apply_migrated_delivery_state( $id, $status, $attempt, $pending, (string) get_post_meta( $source_id, '_activitypub_external_last_error', true ) ) ) {
			$complete = false;
			continue;
		}
		update_post_meta( $source_id, '_ax_ap_migrated_to_table', $id );
		axismundi_activitypub_bridge_clear_legacy_delivery_events( $source_id );
		if ( 'pending' === get_post_status( $source_id ) ) {
			wp_publish_post( $source_id );
		}
	}
	return $complete;
}

/** Migrate both superseded transport stores exactly once, preserving every source row. */
function axismundi_activitypub_bridge_migrate_delivery_stores() : void {
	if ( ! axismundi_activitypub_bridge_ready()
		|| ! axismundi_activitypub_bridge_delivery_ready()
		|| 2 === (int) get_option( 'ax_activitypub_bridge_delivery_migration', 0 )
	) {
		return;
	}
	if ( axismundi_activitypub_bridge_migrate_delivery_cpt() && axismundi_activitypub_bridge_migrate_external_outbox_rows() ) {
		update_option( 'ax_activitypub_bridge_delivery_migration', 2, false );
	}
}
add_action( 'init', 'axismundi_activitypub_bridge_migrate_delivery_stores', 20 );
