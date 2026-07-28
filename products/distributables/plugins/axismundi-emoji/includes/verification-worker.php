<?php
/**
 * Authority-side verification for hearsay emoji observations.
 *
 * A qualified emoji seen on another server is evidence that it is in use, not proof
 * of its icon or licence. This worker fetches only an explicitly queued Emoji `id`
 * from the named authority, then promotes the row through the same first-party
 * observation path used for a direct declaration. No front-end or admin request
 * performs network I/O.
 *
 * @package AxismundiEmoji
 */

defined( 'ABSPATH' ) || exit;

const AXISMUNDI_EMOJI_VERIFICATION_BATCH_SIZE = 5;
const AXISMUNDI_EMOJI_VERIFICATION_MAX_BYTES  = 262144;

/**
 * Attempts before a queued verification is abandoned.
 *
 * Abandonment is not rejection. The row leaves the worker's queue and reappears in the
 * admin list as failed, where a person can queue it again — which is the correct place
 * for the judgement "this host is back up now".
 */
const AXISMUNDI_EMOJI_VERIFICATION_MAX_FAILURES = 6;

/** First retry delay; doubles per failure up to the cap below. */
const AXISMUNDI_EMOJI_VERIFICATION_BACKOFF_BASE = 300;
const AXISMUNDI_EMOJI_VERIFICATION_BACKOFF_CAP  = 43200;

/**
 * When a row that has just failed may be tried again.
 *
 * Exponential, because the failures worth retrying are transient and the ones that are
 * not must stop costing somebody else's server a request. Without this the queue is a
 * hot loop: a failure changes nothing about a row's eligibility, so the same batch is
 * selected again on the next pass, forever.
 *
 * @param int $failures Failure count after the attempt.
 * @return string UTC datetime.
 */
function axismundi_emoji_verification_backoff( int $failures ) : string {
	$delay = AXISMUNDI_EMOJI_VERIFICATION_BACKOFF_BASE * ( 2 ** max( 0, $failures - 1 ) );
	return gmdate( 'Y-m-d H:i:s', time() + (int) min( AXISMUNDI_EMOJI_VERIFICATION_BACKOFF_CAP, $delay ) );
}

/**
 * Fetch one bounded ActivityStreams Emoji document without following redirects.
 *
 * @param string $uri Authority-hosted Emoji `id`.
 * @return array<string,mixed>|WP_Error
 */
function axismundi_emoji_fetch_verification_document( string $uri ) {
	if ( 'https' !== strtolower( (string) wp_parse_url( $uri, PHP_URL_SCHEME ) ) || ! wp_http_validate_url( $uri ) ) {
		return new WP_Error( 'ax_emoji_verify_url', __( 'The verification URL is unsafe.', 'axismundi-emoji' ) );
	}
	$response = wp_safe_remote_get(
		$uri,
		array(
			'timeout'             => 10,
			'redirection'         => 0,
			'limit_response_size' => AXISMUNDI_EMOJI_VERIFICATION_MAX_BYTES,
			'headers'             => array( 'Accept' => 'application/activity+json, application/ld+json' ),
			'user-agent'          => 'Axismundi Emoji/' . AXISMUNDI_EMOJI_VERSION . '; ' . home_url( '/' ),
		)
	);
	if ( is_wp_error( $response ) ) {
		return $response;
	}
	if ( 200 !== (int) wp_remote_retrieve_response_code( $response ) ) {
		return new WP_Error( 'ax_emoji_verify_status', __( 'The authority returned an unexpected status.', 'axismundi-emoji' ) );
	}
	$content_type = strtolower( trim( explode( ';', (string) wp_remote_retrieve_header( $response, 'content-type' ) )[0] ) );
	if ( ! in_array( $content_type, array( 'application/activity+json', 'application/ld+json' ), true ) ) {
		return new WP_Error( 'ax_emoji_verify_content_type', __( 'The authority did not return an ActivityStreams document.', 'axismundi-emoji' ) );
	}
	$body = (string) wp_remote_retrieve_body( $response );
	if ( '' === $body || strlen( $body ) >= AXISMUNDI_EMOJI_VERIFICATION_MAX_BYTES ) {
		return new WP_Error( 'ax_emoji_verify_size', __( 'The authority document was empty or too large.', 'axismundi-emoji' ) );
	}
	try {
		$data = json_decode( $body, true, 64, JSON_THROW_ON_ERROR );
	} catch ( JsonException $error ) {
		return new WP_Error( 'ax_emoji_verify_json', __( 'The authority document was not valid JSON.', 'axismundi-emoji' ) );
	}
	return is_array( $data ) ? $data : new WP_Error( 'ax_emoji_verify_json', __( 'The authority document was not a JSON object.', 'axismundi-emoji' ) );
}

/**
 * Mark a verification attempt failed without changing its review state.
 *
 * Failure is not rejection: temporary network errors remain retryable and never make
 * a remote host's outage look like a human decision.
 *
 * @param int $emoji_id Registry row id.
 * @return void
 */
function axismundi_emoji_mark_verification_failure( int $emoji_id ) : void {
	global $wpdb;
	if ( $emoji_id <= 0 || ! axismundi_emoji_ready() ) {
		return;
	}
	$table = axismundi_emoji_table();
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- plugin-owned table; value prepared.
	$failures = 1 + (int) $wpdb->get_var( $wpdb->prepare( "SELECT failure_count FROM {$table} WHERE id = %d", $emoji_id ) );

	$fields = array(
		'failure_count'   => $failures,
		'next_attempt_at' => axismundi_emoji_verification_backoff( $failures ),
	);
	if ( $failures >= AXISMUNDI_EMOJI_VERIFICATION_MAX_FAILURES ) {
		// Out of the queue, still in the registry, still `pending`. The shortcode keeps
		// rendering as text, which was already the case, and the row stays visible to an
		// operator who can decide the host is worth another try.
		$fields['review_reason']   = 'verify_failed';
		$fields['next_attempt_at'] = null;
	}
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- plugin-owned table.
	$wpdb->update( $table, $fields, array( 'id' => $emoji_id ) );
}

/**
 * Verify and promote one queued hearsay row.
 *
 * The fetched resource must affirm all three pieces of the row's identity: it is
 * served by the named authority, its own `id` is exactly the queued URI, and parsing
 * it as a first-party Emoji yields the same authority and shortcode key. A response
 * that merely looks Emoji-shaped is not enough to overwrite canonical metadata.
 *
 * @param array<string,mixed> $row Registry row.
 * @return bool Whether canonical metadata was promoted.
 */
function axismundi_emoji_verify_queued_row( array $row ) : bool {
	$emoji_id  = (int) ( $row['id'] ?? 0 );
	$authority = strtolower( (string) ( $row['emoji_authority'] ?? '' ) );
	$key       = (string) ( $row['shortcode_key'] ?? '' );
	$uri       = (string) ( $row['verification_uri'] ?? '' );
	$host      = strtolower( (string) wp_parse_url( $uri, PHP_URL_HOST ) );
	if ( $emoji_id <= 0 || '' === $authority || '' === $key || $authority !== $host || '' === $uri ) {
		axismundi_emoji_mark_verification_failure( $emoji_id );
		return false;
	}

	$document = axismundi_emoji_fetch_verification_document( $uri );
	if ( is_wp_error( $document ) || $uri !== (string) ( $document['id'] ?? '' ) ) {
		axismundi_emoji_mark_verification_failure( $emoji_id );
		return false;
	}
	$descriptor = axismundi_emoji_descriptor_from_tag( $document, $authority );
	if ( null === $descriptor
		|| empty( $descriptor['first_party'] )
		|| $authority !== (string) $descriptor['emoji_authority']
		|| $key !== (string) $descriptor['shortcode_key'] ) {
		axismundi_emoji_mark_verification_failure( $emoji_id );
		return false;
	}

	return $emoji_id === axismundi_emoji_observe( $descriptor );
}

/**
 * Return the next explicitly queued verification rows.
 *
 * @param int $limit Batch limit.
 * @return array<int,array<string,mixed>>
 */
function axismundi_emoji_verification_queue( int $limit = AXISMUNDI_EMOJI_VERIFICATION_BATCH_SIZE ) : array {
	global $wpdb;
	if ( ! axismundi_emoji_ready() ) {
		return array();
	}
	$table = axismundi_emoji_table();
	$limit = max( 1, min( 25, $limit ) );
	/*
	 * `next_attempt_at` does double duty. It withholds a row that failed recently, and
	 * because it also orders the queue it stops a failing row from holding the head
	 * position: previously the same five rows were selected on every pass and anything
	 * behind them was never attempted at all.
	 */
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- plugin-owned table; static predicates and prepared values.
	return (array) $wpdb->get_results(
		$wpdb->prepare(
			"SELECT * FROM {$table}
			 WHERE scope = 'remote' AND review_status = 'pending' AND review_reason = 'verify_queued'
			   AND verification_uri IS NOT NULL AND verification_uri <> ''
			   AND failure_count < %d
			   AND ( next_attempt_at IS NULL OR next_attempt_at <= %s )
			 ORDER BY next_attempt_at IS NOT NULL, next_attempt_at ASC, last_seen_at ASC, id ASC
			 LIMIT %d",
			AXISMUNDI_EMOJI_VERIFICATION_MAX_FAILURES,
			current_time( 'mysql', true ),
			$limit
		),
		ARRAY_A
	);
}

/** Schedule a bounded verification pass, collapsing repeated admin clicks. */
function axismundi_emoji_schedule_verification_pass( int $delay = 10 ) : void {
	if ( ! wp_next_scheduled( 'axismundi_emoji_process_verification_batch' ) ) {
		wp_schedule_single_event( time() + max( 1, $delay ), 'axismundi_emoji_process_verification_batch' );
	}
}

/** Hook adapter: WordPress may pass an empty value to argument-less actions. */
function axismundi_emoji_queue_verification_pass() : void {
	axismundi_emoji_schedule_verification_pass();
}
add_action( 'axismundi_emoji_verification_queued', 'axismundi_emoji_queue_verification_pass' );

/**
 * Process one small, explicit verification batch.
 *
 * A follow-up pass is scheduled on **work remaining**, not on how many rows this pass
 * happened to take. The earlier form asked whether the batch was full, which a batch of
 * permanently unreachable rows always is — and since a failure did not change their
 * eligibility, the same five were re-selected every fifteen seconds indefinitely. The
 * backoff written by the failure path is what makes this query the right question to
 * ask: rows just attempted are no longer due.
 *
 * @return void
 */
function axismundi_emoji_process_verification_batch() : void {
	$rows = axismundi_emoji_verification_queue();
	foreach ( $rows as $row ) {
		axismundi_emoji_verify_queued_row( $row );
	}
	if ( array() !== axismundi_emoji_verification_queue( 1 ) ) {
		axismundi_emoji_schedule_verification_pass( 15 );
	}
}
add_action( 'axismundi_emoji_process_verification_batch', 'axismundi_emoji_process_verification_batch' );

/** Retry queued authority checks at a low cadence after transient failures. */
function axismundi_emoji_schedule_verification_recovery() : void {
	if ( ! wp_next_scheduled( 'axismundi_emoji_verification_recovery' ) ) {
		wp_schedule_event( time() + HOUR_IN_SECONDS, 'hourly', 'axismundi_emoji_verification_recovery' );
	}
}
add_action( 'init', 'axismundi_emoji_schedule_verification_recovery' );
add_action( 'axismundi_emoji_verification_recovery', 'axismundi_emoji_queue_verification_pass' );
