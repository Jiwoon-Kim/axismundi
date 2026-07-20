<?php
/**
 * Activity-ledger vote classification and tally projection for local Questions.
 *
 * A vote is not an ActivityStreams subtype. It is a deliberately constrained
 * Create(Note): it names exactly one frozen option, has no meaningful body or
 * attachment, replies directly to a local authoritative Question, and the
 * Create Actor agrees with the embedded Note's attributedTo. Everything else
 * remains an ordinary reply, including a titled textual reply to a Question.
 *
 * @package AxismundiNote
 */

defined( 'ABSPATH' ) || exit;

/** Whether an arbitrary HTML/text member contains meaningful authored text. */
function axismundi_note_poll_has_content( $value ) : bool {
	if ( null === $value || '' === $value ) {
		return false;
	}
	if ( ! is_string( $value ) ) {
		return true;
	}
	$text = html_entity_decode( wp_strip_all_tags( $value ), ENT_QUOTES | ENT_HTML5, 'UTF-8' );
	return '' !== preg_replace( '/[\s\x{00A0}]+/u', '', $text );
}

/** Exact frozen option matching: inbound option names are never normalized. */
function axismundi_note_poll_option( int $question_id, string $name ) : ?array {
	global $wpdb;
	if ( $question_id <= 0 || '' === $name ) {
		return null;
	}
	$table = axismundi_note_question_options_table();
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery -- indexed exact option lookup.
	$row = $wpdb->get_row( $wpdb->prepare( "SELECT option_uuid, name FROM {$table} WHERE question_id = %d AND name_hash = %s", $question_id, hash( 'sha256', $name ) ), ARRAY_A );
	return is_array( $row ) && hash_equals( (string) $row['name'], $name ) ? $row : null;
}

/** Resolve a local, locked, still-open Question directly from a canonical URI. */
function axismundi_note_poll_question_target( string $uri ) : ?array {
	$uuid = axismundi_note_local_uuid_from_uri( $uri );
	if ( null === $uuid ) {
		return null;
	}
	$envelope = axismundi_note_get_by_uuid( $uuid );
	if ( ! is_array( $envelope ) || 'active' !== (string) ( $envelope['object_status'] ?? '' ) ) {
		return null;
	}
	$post_id  = (int) ( $envelope['post_id'] ?? 0 );
	$post     = get_post( $post_id );
	$question = axismundi_note_question_row( $post_id );
	$view     = axismundi_note_question_view( $post_id );
	if ( ! $post instanceof WP_Post || 'publish' !== $post->post_status || ! is_array( $question ) || empty( $question['locked_at'] ) || ! is_array( $view ) || '' !== (string) $view['closed_at'] ) {
		return null;
	}
	return array( 'id' => (int) $question['id'], 'mode' => (string) $question['mode'], 'uri' => $uri );
}

/** Return a vote candidate from a committed Create activity, otherwise null. */
function axismundi_note_poll_vote_candidate( Axismundi_Activity $activity ) : ?array {
	if ( 'Create' !== $activity->get_type() || ! $activity->is_effective() ) {
		return null;
	}
	$payload = $activity->get_payload();
	$object  = $payload['object'] ?? null;
	if ( ! is_array( $object ) || 'Note' !== (string) ( $object['type'] ?? '' ) ) {
		return null;
	}
	$object_uri = axismundi_act_member_uri( $object['id'] ?? '' );
	$parent_uri = axismundi_act_member_uri( $object['inReplyTo'] ?? '' );
	$actor_uri  = $activity->get_actor_uri();
	$author_uri = axismundi_act_member_uri( $object['attributedTo'] ?? '' );
	$name       = $object['name'] ?? null;
	if ( '' === $object_uri || '' === $parent_uri || ! is_string( $name ) || '' === $name || ! hash_equals( $actor_uri, $author_uri ) || ! empty( $object['attachment'] ) ) {
		return null;
	}
	if ( axismundi_note_poll_has_content( $object['content'] ?? '' ) ) {
		return null;
	}
	foreach ( (array) ( $object['contentMap'] ?? array() ) as $content ) {
		if ( axismundi_note_poll_has_content( $content ) ) {
			return null;
		}
	}
	$question = axismundi_note_poll_question_target( $parent_uri );
	if ( ! is_array( $question ) ) {
		return null;
	}
	$option = axismundi_note_poll_option( (int) $question['id'], $name );
	if ( ! is_array( $option ) ) {
		return null;
	}
	return array(
		'question_id'       => (int) $question['id'],
		'mode'              => (string) $question['mode'],
		'option_uuid'       => (string) $option['option_uuid'],
		'option_name'       => $name,
		'voter_actor_uri'   => $actor_uri,
		'vote_object_uri'   => $object_uri,
		'vote_activity_uri' => $activity->get_uri(),
	);
}

/** Write a classified vote once; duplicate choices are retained but inactive. */
function axismundi_note_poll_record_vote( array $candidate ) : bool {
	global $wpdb;
	$table          = axismundi_note_poll_votes_table();
	$questions      = axismundi_note_questions_table();
	$question_id    = (int) $candidate['question_id'];
	$actor_uri      = (string) $candidate['voter_actor_uri'];
	$option_uuid    = (string) $candidate['option_uuid'];
	$activity_uri   = (string) $candidate['vote_activity_uri'];
	$activity_hash  = hash( 'sha256', $activity_uri );
	$actor_hash     = hash( 'sha256', $actor_uri );
	$now            = current_time( 'mysql', true );
	$wpdb->query( 'START TRANSACTION' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- vote identity decision is atomic.
	// Serializes first-choice policy even when two inbox workers receive the same actor concurrently.
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery -- Question row lock.
	$question = $wpdb->get_row( $wpdb->prepare( "SELECT id, mode FROM {$questions} WHERE id = %d FOR UPDATE", $question_id ), ARRAY_A );
	if ( ! is_array( $question ) ) {
		$wpdb->query( 'ROLLBACK' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- no target remains.
		return false;
	}
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery -- exact idempotency lookup.
	$existing = $wpdb->get_row( $wpdb->prepare( "SELECT vote_activity_uri FROM {$table} WHERE vote_activity_uri_hash = %s", $activity_hash ), ARRAY_A );
	if ( is_array( $existing ) ) {
		$wpdb->query( 'COMMIT' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- immutable event already projected.
		return hash_equals( (string) $existing['vote_activity_uri'], $activity_uri );
	}
	$scope_sql = 'oneOf' === (string) $question['mode']
		? $wpdb->prepare( "SELECT id FROM {$table} WHERE question_id = %d AND voter_actor_uri_hash = %s AND voter_actor_uri = %s AND vote_status = 'active' LIMIT 1", $question_id, $actor_hash, $actor_uri )
		: $wpdb->prepare( "SELECT id FROM {$table} WHERE question_id = %d AND voter_actor_uri_hash = %s AND voter_actor_uri = %s AND option_uuid = %s AND vote_status = 'active' LIMIT 1", $question_id, $actor_hash, $actor_uri, $option_uuid );
	// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery -- branch SQL is fully prepared above.
	$duplicate = null !== $wpdb->get_var( $scope_sql );
	$inserted  = $wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- materialized immutable vote observation.
		$table,
		array(
			'question_id'            => $question_id,
			'option_uuid'            => $option_uuid,
			'option_name'            => (string) $candidate['option_name'],
			'voter_actor_uri'        => $actor_uri,
			'voter_actor_uri_hash'   => $actor_hash,
			'vote_object_uri'        => (string) $candidate['vote_object_uri'],
			'vote_object_uri_hash'   => hash( 'sha256', (string) $candidate['vote_object_uri'] ),
			'vote_activity_uri'      => $activity_uri,
			'vote_activity_uri_hash' => $activity_hash,
			'vote_status'            => $duplicate ? 'ignored' : 'active',
			'created_at'             => $now,
			'updated_at'             => $now,
		)
	);
	if ( false === $inserted ) {
		$wpdb->query( 'ROLLBACK' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- insertion failed.
		// A concurrent callback may have won the immutable Activity identity.
		// Re-read it by hash and demand the exact URI before calling this replay a
		// success; a theoretical hash collision still fails closed.
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery -- exact post-conflict idempotency lookup.
		$winner = $wpdb->get_row( $wpdb->prepare( "SELECT vote_activity_uri FROM {$table} WHERE vote_activity_uri_hash = %s", $activity_hash ), ARRAY_A );
		return is_array( $winner ) && hash_equals( (string) $winner['vote_activity_uri'], $activity_uri );
	}
	$wpdb->query( 'COMMIT' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- projection committed.
	return true;
}

/** Remove active votes when their Create is undone or their Note is deleted. */
function axismundi_note_poll_mark_votes( string $field, string $uri, string $status, string $actor_uri ) : void {
	global $wpdb;
	if ( ! in_array( $field, array( 'vote_activity_uri', 'vote_object_uri' ), true ) || ! in_array( $status, array( 'undone', 'deleted' ), true ) || '' === $uri || '' === $actor_uri ) {
		return;
	}
	$table = axismundi_note_poll_votes_table();
	$hash       = hash( 'sha256', $uri );
	$actor_hash = hash( 'sha256', $actor_uri );
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery -- allowlisted column and indexed exact URI update.
	$wpdb->query( $wpdb->prepare( "UPDATE {$table} SET vote_status = %s, updated_at = %s WHERE {$field}_hash = %s AND {$field} = %s AND voter_actor_uri_hash = %s AND voter_actor_uri = %s AND vote_status = 'active'", $status, current_time( 'mysql', true ), $hash, $uri, $actor_hash, $actor_uri ) );
}

/** Sync the vote projection after every immutable Activity ledger commit. */
function axismundi_note_sync_poll_vote( Axismundi_Activity $activity ) : void {
	if ( 'Create' === $activity->get_type() ) {
		$candidate = axismundi_note_poll_vote_candidate( $activity );
		if ( is_array( $candidate ) ) {
			axismundi_note_poll_record_vote( $candidate );
		}
		return;
	}
	if ( 'Undo' === $activity->get_type() ) {
		axismundi_note_poll_mark_votes( 'vote_activity_uri', (string) $activity->get_object_uri(), 'undone', $activity->get_actor_uri() );
		return;
	}
	if ( 'Delete' === $activity->get_type() ) {
		axismundi_note_poll_mark_votes( 'vote_object_uri', (string) $activity->get_object_uri(), 'deleted', $activity->get_actor_uri() );
	}
}
add_action( 'axismundi_act_activity_recorded', 'axismundi_note_sync_poll_vote', 25 );

/** Tally the currently effective local observations for one Question. */
function axismundi_note_poll_tally( int $question_id ) : array {
	global $wpdb;
	if ( $question_id <= 0 ) {
		return array( 'voters_count' => 0, 'options' => array() );
	}
	$table = axismundi_note_poll_votes_table();
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery -- indexed current-tally grouping.
	$rows = (array) $wpdb->get_results( $wpdb->prepare( "SELECT option_uuid, COUNT(*) AS votes FROM {$table} WHERE question_id = %d AND vote_status = 'active' GROUP BY option_uuid", $question_id ), ARRAY_A );
	$options = array();
	foreach ( $rows as $row ) {
		$options[ (string) $row['option_uuid'] ] = (int) $row['votes'];
	}
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery -- indexed distinct-voter count.
	$voters = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(DISTINCT voter_actor_uri_hash) FROM {$table} WHERE question_id = %d AND vote_status = 'active'", $question_id ) );
	return array( 'voters_count' => $voters, 'options' => $options );
}

/** Confirmed votes are poll interactions, not textual replies in a thread. */
function axismundi_note_exclude_poll_vote_reply( bool $include, string $child_uri ) : bool {
	global $wpdb;
	if ( ! $include || '' === $child_uri ) {
		return $include;
	}
	$table = axismundi_note_poll_votes_table();
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery -- indexed exact vote-object lookup.
	$found = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$table} WHERE vote_object_uri_hash = %s AND vote_object_uri = %s LIMIT 1", hash( 'sha256', $child_uri ), $child_uri ) );
	return null === $found;
}
add_filter( 'axismundi_op_thread_include_reply', 'axismundi_note_exclude_poll_vote_reply', 10, 2 );
