<?php
/**
 * Question storage: a Note-owned sibling object type on the same ax_note CPT.
 *
 * A Question is not a subtype or a separate post type -- it is an ordinary
 * ax_note post that additionally owns one row here. Presence of that row is
 * the only signal: there is no envelope column to keep in sync. Mode and the
 * option list are authored fields, validated and stored structurally (never
 * in post_content or a block attribute), and freeze at first federation --
 * the same moment attribution locks -- exactly like the language and Actor
 * snapshot. Only `closes_at`/`closed_at` and vote tallies may change after
 * that; this file owns no vote storage yet (a later increment).
 *
 * An option's `name` is the only wire identifier a vote Create(Note) can use
 * to name its choice (FEP has no separate Answer type), so it must be unique
 * within one Question, exact-match comparable, and never silently
 * re-normalized once federated.
 *
 * @package AxismundiNote
 */

defined( 'ABSPATH' ) || exit;

const AXISMUNDI_NOTE_QUESTION_MODES        = array( 'oneOf', 'anyOf' );
const AXISMUNDI_NOTE_QUESTION_OPTION_MIN   = 2;
const AXISMUNDI_NOTE_QUESTION_OPTION_MAX   = 20;
const AXISMUNDI_NOTE_QUESTION_NAME_MAX     = 200;

/** Question table for the current site. */
function axismundi_note_questions_table() : string {
	global $wpdb;
	return $wpdb->prefix . 'ax_questions';
}

/** Question option table for the current site. */
function axismundi_note_question_options_table() : string {
	global $wpdb;
	return $wpdb->prefix . 'ax_question_options';
}

/** Materialized, Activity-ledger-derived local vote observations. */
function axismundi_note_poll_votes_table() : string {
	global $wpdb;
	return $wpdb->prefix . 'ax_poll_votes';
}

/** Install and verify the Question and option stores. */
function axismundi_note_install_question_schema() : bool {
	global $wpdb;
	require_once ABSPATH . 'wp-admin/includes/upgrade.php';

	$questions = axismundi_note_questions_table();
	$options   = axismundi_note_question_options_table();
	$votes     = axismundi_note_poll_votes_table();
	$charset   = $wpdb->get_charset_collate();
	dbDelta(
		"CREATE TABLE {$questions} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			note_post_id bigint(20) unsigned NOT NULL,
			mode varchar(8) NOT NULL DEFAULT 'oneOf',
			closes_at datetime NULL,
			closed_at datetime NULL,
			locked_at datetime NULL,
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY note_post_id (note_post_id)
		) ENGINE=InnoDB {$charset};\n\n"
		. "CREATE TABLE {$options} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			question_id bigint(20) unsigned NOT NULL,
			option_uuid char(36) NOT NULL,
			name varchar(200) NOT NULL,
			name_hash char(64) NOT NULL,
			position smallint(5) unsigned NOT NULL DEFAULT 0,
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY option_uuid (option_uuid),
			UNIQUE KEY question_option_identity (question_id, name_hash),
			KEY question_id (question_id)
		) ENGINE=InnoDB {$charset};\n\n"
		. "CREATE TABLE {$votes} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			question_id bigint(20) unsigned NOT NULL,
			option_uuid char(36) NOT NULL,
			option_name varchar(200) NOT NULL,
			voter_actor_uri text NOT NULL,
			voter_actor_uri_hash char(64) NOT NULL,
			vote_object_uri text NOT NULL,
			vote_object_uri_hash char(64) NOT NULL,
			vote_activity_uri text NOT NULL,
			vote_activity_uri_hash char(64) NOT NULL,
			vote_status varchar(16) NOT NULL DEFAULT 'active',
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY vote_activity_uri_hash (vote_activity_uri_hash),
			KEY question_option_status (question_id, option_uuid, vote_status),
			KEY question_voter_status (question_id, voter_actor_uri_hash, vote_status),
			KEY vote_object_uri_hash (vote_object_uri_hash)
		) ENGINE=InnoDB {$charset};"
	);

	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery -- fixed custom schema verification.
	$question_columns = (array) $wpdb->get_col( "SHOW COLUMNS FROM {$questions}" );
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery -- fixed custom index verification.
	$question_key = (array) $wpdb->get_results( "SHOW INDEX FROM {$questions} WHERE Key_name = 'note_post_id'", ARRAY_A );
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery -- fixed custom engine verification.
	$question_engine = (string) $wpdb->get_var( "SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '{$questions}'" );
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery -- fixed custom schema verification.
	$option_columns = (array) $wpdb->get_col( "SHOW COLUMNS FROM {$options}" );
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery -- fixed custom index verification.
	$option_identity_key = (array) $wpdb->get_results( "SHOW INDEX FROM {$options} WHERE Key_name = 'question_option_identity'", ARRAY_A );
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery -- fixed custom index verification.
	$option_uuid_key = (array) $wpdb->get_results( "SHOW INDEX FROM {$options} WHERE Key_name = 'option_uuid'", ARRAY_A );
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery -- fixed custom engine verification.
	$option_engine = (string) $wpdb->get_var( "SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '{$options}'" );
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery -- fixed custom schema verification.
	$vote_columns = (array) $wpdb->get_col( "SHOW COLUMNS FROM {$votes}" );
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery -- fixed custom index verification.
	$vote_identity_key = (array) $wpdb->get_results( "SHOW INDEX FROM {$votes} WHERE Key_name = 'vote_activity_uri_hash'", ARRAY_A );
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery -- fixed custom engine verification.
	$vote_engine = (string) $wpdb->get_var( "SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '{$votes}'" );

	return in_array( 'locked_at', $question_columns, true )
		&& in_array( 'closes_at', $question_columns, true )
		&& in_array( 'closed_at', $question_columns, true )
		&& ! empty( $question_key ) && 0 === (int) $question_key[0]['Non_unique']
		&& 'InnoDB' === $question_engine
		&& in_array( 'name_hash', $option_columns, true )
		&& in_array( 'position', $option_columns, true )
		&& ! empty( $option_identity_key ) && 0 === (int) $option_identity_key[0]['Non_unique']
		&& ! empty( $option_uuid_key ) && 0 === (int) $option_uuid_key[0]['Non_unique']
		&& 'InnoDB' === $option_engine
		&& in_array( 'vote_status', $vote_columns, true )
		&& in_array( 'vote_activity_uri_hash', $vote_columns, true )
		&& ! empty( $vote_identity_key ) && 0 === (int) $vote_identity_key[0]['Non_unique']
		&& 'InnoDB' === $vote_engine;
}

/** Whether one Note post owns a Question row. */
function axismundi_note_is_question( int $post_id ) : bool {
	return null !== axismundi_note_question_row( $post_id );
}

/**
 * Remove an unfederated Question definition and restore its Note to ordinary state.
 *
 * A federated Question is intentionally retained until poll-revision lifecycle support
 * can retire its historical options and vote observations without losing their ledger
 * provenance.
 *
 * @return true|WP_Error
 */
function axismundi_note_question_remove( int $post_id ) {
	global $wpdb;
	if ( ! axismundi_note_ready() ) {
		return new WP_Error( 'ax_note_store', __( 'The Note envelope store is unavailable.', 'axismundi-note' ) );
	}
	$existing = axismundi_note_question_row( $post_id );
	if ( ! is_array( $existing ) ) {
		return true;
	}
	$questions_table = axismundi_note_questions_table();
	$options_table   = axismundi_note_question_options_table();
	$votes_table     = axismundi_note_poll_votes_table();
	$question_id     = (int) $existing['id'];
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- atomic Question removal.
	$wpdb->query( 'START TRANSACTION' );
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- indexed row lock at the type-change boundary.
	$wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$questions_table} WHERE id = %d FOR UPDATE", $question_id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery -- serialize Question removal against vote writes and the initial federation lock.
	$ok = false !== $wpdb->delete( $votes_table, array( 'question_id' => $question_id ), array( '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- derived rows belong to the discarded draft Question.
	$ok = $ok && false !== $wpdb->delete( $options_table, array( 'question_id' => $question_id ), array( '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- draft Question options.
	$ok = $ok && false !== $wpdb->delete( $questions_table, array( 'id' => $question_id ), array( '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- draft Question definition.
	if ( ! $ok ) {
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- revert incomplete removal.
		$wpdb->query( 'ROLLBACK' );
		return new WP_Error( 'ax_note_question_write', __( 'The Question could not be removed.', 'axismundi-note' ) );
	}
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- commit complete removal.
	$wpdb->query( 'COMMIT' );
	return true;
}

/** Raw Question row for one Note post, or null. */
function axismundi_note_question_row( int $post_id ) : ?array {
	global $wpdb;
	if ( $post_id <= 0 || ! axismundi_note_ready() ) {
		return null;
	}
	$table = axismundi_note_questions_table();
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery -- URI-keyed custom repository lookup.
	$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE note_post_id = %d", $post_id ), ARRAY_A );
	return is_array( $row ) ? $row : null;
}

/** Ordered options for one Question id. */
function axismundi_note_question_options( int $question_id ) : array {
	global $wpdb;
	if ( $question_id <= 0 ) {
		return array();
	}
	$table = axismundi_note_question_options_table();
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery -- indexed ordered lookup.
	return (array) $wpdb->get_results( $wpdb->prepare( "SELECT option_uuid, name, position FROM {$table} WHERE question_id = %d ORDER BY position ASC, id ASC", $question_id ), ARRAY_A );
}

/**
 * Structured Question view for one Note post, or null when it is not a Question.
 *
 * @return array{mode:string,options:array<int,array{uuid:string,name:string,position:int}>,closes_at:?string,closed_at:?string,locked_at:?string}|null
 */
function axismundi_note_question_get( int $post_id ) : ?array {
	$question = axismundi_note_question_row( $post_id );
	if ( ! is_array( $question ) ) {
		return null;
	}
	$options = array();
	foreach ( axismundi_note_question_options( (int) $question['id'] ) as $option ) {
		$options[] = array(
			'uuid'     => (string) $option['option_uuid'],
			'name'     => (string) $option['name'],
			'position' => (int) $option['position'],
		);
	}
	return array(
		'mode'      => (string) $question['mode'],
		'options'   => $options,
		'closes_at' => $question['closes_at'] ?? null,
		'closed_at' => $question['closed_at'] ?? null,
		'locked_at' => $question['locked_at'] ?? null,
	);
}

/** UTC SQL datetime to ISO-8601, or '' when empty or unparsable. */
function axismundi_note_question_iso( ?string $sql_datetime ) : string {
	if ( null === $sql_datetime || '' === $sql_datetime ) {
		return '';
	}
	$timestamp = strtotime( $sql_datetime . ' UTC' );
	return false !== $timestamp ? gmdate( 'c', $timestamp ) : '';
}

/**
 * Neutral, tally-agnostic poll view for one Question, shared by the JSON-LD
 * projection and the HTML view model so both stay in lockstep.
 *
 * Vote observations are derived from immutable Activity rows. This presentation
 * never inspects reply content directly, so a textual reply and a vote cannot
 * be conflated by a renderer.
 *
 * `closed_at` reflects the effective close, not only an explicit one: a Question
 * whose scheduled `closes_at` has already passed is closed at that scheduled
 * moment even if nothing ever recorded a `closed_at`, so the JSON-LD `closed`
 * member and every display of this shape agree with the wire's own `endTime`
 * semantics instead of only the locally-known explicit-close flag.
 *
 * @return array{mode:string,options:array<int,array{name:string,votes:int}>,voters_count:int,closes_at:string,closed_at:string}|null
 */
function axismundi_note_question_view( int $post_id ) : ?array {
	$question = axismundi_note_question_get( $post_id );
	if ( ! is_array( $question ) ) {
		return null;
	}
	$closes_at_iso = axismundi_note_question_iso( $question['closes_at'] );
	$closed_at_iso = axismundi_note_question_iso( $question['closed_at'] );
	if ( '' === $closed_at_iso && '' !== $closes_at_iso ) {
		$closes_timestamp = strtotime( $closes_at_iso );
		if ( false !== $closes_timestamp && $closes_timestamp <= time() ) {
			$closed_at_iso = gmdate( 'c', $closes_timestamp );
		}
	}
	$question_row = axismundi_note_question_row( $post_id );
	$tally = function_exists( 'axismundi_note_poll_tally' )
		? axismundi_note_poll_tally( is_array( $question_row ) ? (int) $question_row['id'] : 0 )
		: array( 'voters_count' => 0, 'options' => array() );
	return array(
		'mode'         => $question['mode'],
		'options'      => array_map( static fn( array $option ) : array => array( 'name' => $option['name'], 'votes' => (int) ( $tally['options'][ $option['uuid'] ] ?? 0 ) ), $question['options'] ),
		'voters_count' => (int) ( $tally['voters_count'] ?? 0 ),
		'closes_at'    => $closes_at_iso,
		'closed_at'    => $closed_at_iso,
	);
}

/** Whether one Question has enough options to be federated. */
function axismundi_note_question_ready( int $post_id ) : bool {
	$question = axismundi_note_question_get( $post_id );
	return is_array( $question ) && count( $question['options'] ) >= AXISMUNDI_NOTE_QUESTION_OPTION_MIN;
}

/**
 * Validate one authored option-name list, failing closed on any bad or duplicate entry.
 *
 * Comparison and storage are exact-string: a poll option is identified on the
 * wire only by its `name`, so silently trimming case or whitespace differences
 * here would let two authored options collide later at vote time. Duplicates
 * are rejected rather than merged for the same reason.
 *
 * @return string[]|WP_Error
 */
function axismundi_note_validate_question_options( $value ) {
	if ( ! is_array( $value ) || array_is_list( $value ) === false ) {
		return new WP_Error( 'ax_note_question_options', __( 'A Question requires a list of option names.', 'axismundi-note' ) );
	}
	$names = array();
	$seen  = array();
	foreach ( $value as $candidate ) {
		if ( ! is_string( $candidate ) ) {
			return new WP_Error( 'ax_note_question_option', __( 'Every Question option must be plain text.', 'axismundi-note' ) );
		}
		$name = sanitize_text_field( trim( $candidate ) );
		if ( '' === $name || mb_strlen( $name ) > AXISMUNDI_NOTE_QUESTION_NAME_MAX ) {
			return new WP_Error( 'ax_note_question_option', __( 'Every Question option must be non-empty and within the length limit.', 'axismundi-note' ) );
		}
		if ( isset( $seen[ $name ] ) ) {
			return new WP_Error( 'ax_note_question_option_duplicate', __( 'Question options must be unique.', 'axismundi-note' ) );
		}
		$seen[ $name ] = true;
		$names[]       = $name;
	}
	if ( count( $names ) < AXISMUNDI_NOTE_QUESTION_OPTION_MIN || count( $names ) > AXISMUNDI_NOTE_QUESTION_OPTION_MAX ) {
		return new WP_Error( 'ax_note_question_option_count', __( 'A Question requires between 2 and 20 options.', 'axismundi-note' ) );
	}
	return $names;
}

/** Validate an explicit Question mode. */
function axismundi_note_validate_question_mode( $value ) {
	if ( ! is_string( $value ) || ! in_array( $value, AXISMUNDI_NOTE_QUESTION_MODES, true ) ) {
		return new WP_Error( 'ax_note_question_mode', __( 'The Question mode is not recognized.', 'axismundi-note' ) );
	}
	return $value;
}

/**
 * Create or update one Note's Question definition.
 *
 * `mode` and `options` are frozen the moment `axismundi_note_question_lock()`
 * runs (first federation); after that only `closes_at`/`closed_at` may still
 * change. A wholly new Question cannot be created on a Note whose attribution
 * is already locked -- an object's type does not change after it has already
 * been federated as something else.
 *
 * @param array<string,mixed> $fields mode, options (string[]), closes_at, closed_at.
 * @return array<string,mixed>|WP_Error
 */
function axismundi_note_question_save( int $post_id, array $fields ) {
	global $wpdb;
	if ( ! axismundi_note_ready() ) {
		return new WP_Error( 'ax_note_store', __( 'The Note envelope store is unavailable.', 'axismundi-note' ) );
	}
	$post = get_post( $post_id );
	if ( ! $post instanceof WP_Post || AXISMUNDI_NOTE_POST_TYPE !== $post->post_type ) {
		return new WP_Error( 'ax_note_post', __( 'A Note post is required.', 'axismundi-note' ) );
	}
	$existing = axismundi_note_question_row( $post_id );
	// This non-transactional read only powers a fast-path rejection for the
	// common case. It is not the enforcement boundary: a concurrent
	// axismundi_note_question_lock() can still commit between this read and the
	// write below, so freezing mode/options is re-verified atomically there
	// against the row's live state, not this snapshot.
	$locked = is_array( $existing ) && ! empty( $existing['locked_at'] );

	if ( ! is_array( $existing ) ) {
		$envelope = axismundi_note_get( $post_id );
		if ( is_array( $envelope ) && ! empty( $envelope['attribution_locked_at'] ) ) {
			return new WP_Error( 'ax_note_question_type_locked', __( 'A Note already federated as an ordinary Note cannot become a Question.', 'axismundi-note' ) );
		}
	}
	$changing_frozen_fields = array_key_exists( 'mode', $fields ) || array_key_exists( 'options', $fields );
	if ( $locked && $changing_frozen_fields ) {
		return new WP_Error( 'ax_note_question_locked', __( 'A federated Question keeps its original mode and options.', 'axismundi-note' ) );
	}

	if ( array_key_exists( 'mode', $fields ) ) {
		$mode = axismundi_note_validate_question_mode( $fields['mode'] );
		if ( is_wp_error( $mode ) ) {
			return $mode;
		}
	} else {
		$mode = is_array( $existing ) ? (string) $existing['mode'] : 'oneOf';
	}

	$options = null;
	if ( array_key_exists( 'options', $fields ) ) {
		$options = axismundi_note_validate_question_options( $fields['options'] );
		if ( is_wp_error( $options ) ) {
			return $options;
		}
	} elseif ( ! is_array( $existing ) ) {
		return new WP_Error( 'ax_note_question_options', __( 'A new Question requires an option list.', 'axismundi-note' ) );
	}

	$closes_at = array_key_exists( 'closes_at', $fields ) ? axismundi_note_question_sanitize_datetime( $fields['closes_at'] ) : ( $existing['closes_at'] ?? null );
	$closed_at = array_key_exists( 'closed_at', $fields ) ? axismundi_note_question_sanitize_datetime( $fields['closed_at'] ) : ( $existing['closed_at'] ?? null );
	$now       = current_time( 'mysql', true );

	$questions_table = axismundi_note_questions_table();
	$wpdb->query( 'START TRANSACTION' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- atomic Question + option replacement.
	if ( is_array( $existing ) ) {
		$question_id = (int) $existing['id'];
		if ( $changing_frozen_fields ) {
			// The actual freeze boundary. A row lock -- not an UPDATE affected-rows
			// count, which mysqli reports as changed-values rather than
			// matched-rows and would false-positive whenever the new value equals
			// the old one -- so a concurrent axismundi_note_question_lock() that
			// commits after the non-transactional read above but before this
			// statement is still caught: FOR UPDATE blocks until that lock's own
			// UPDATE (ordinary autocommit, still row-locked by InnoDB) finishes,
			// then this SELECT sees its result.
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery -- indexed row lock inside this transaction.
			$locked_now = $wpdb->get_var( $wpdb->prepare( "SELECT locked_at FROM {$questions_table} WHERE id = %d FOR UPDATE", $question_id ) );
			if ( null !== $locked_now ) {
				$wpdb->query( 'ROLLBACK' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- a concurrent lock won the race; discard this write entirely.
				return new WP_Error( 'ax_note_question_locked', __( 'A federated Question keeps its original mode and options.', 'axismundi-note' ) );
			}
		}
		$ok = false !== $wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- atomic Question update.
			$questions_table,
			array( 'mode' => $mode, 'closes_at' => $closes_at, 'closed_at' => $closed_at, 'updated_at' => $now ),
			array( 'id' => $question_id ),
			array( '%s', '%s', '%s', '%s' ),
			array( '%d' )
		);
	} else {
		$ok = false !== $wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- atomic Question insertion.
			$questions_table,
			array( 'note_post_id' => $post_id, 'mode' => $mode, 'closes_at' => $closes_at, 'closed_at' => $closed_at, 'created_at' => $now, 'updated_at' => $now )
		);
		$question_id = (int) $wpdb->insert_id;
	}
	if ( $ok && null !== $options ) {
		$options_table = axismundi_note_question_options_table();
		$ok            = false !== $wpdb->delete( $options_table, array( 'question_id' => $question_id ), array( '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- full replacement of one Question's options.
		foreach ( $options as $position => $name ) {
			if ( ! $ok ) {
				break;
			}
			$ok = false !== $wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- atomic option insertion.
				$options_table,
				array(
					'question_id' => $question_id,
					'option_uuid' => wp_generate_uuid4(),
					'name'        => $name,
					'name_hash'   => hash( 'sha256', $name ),
					'position'    => $position,
					'created_at'  => $now,
					'updated_at'  => $now,
				)
			);
		}
	}
	if ( ! $ok ) {
		$wpdb->query( 'ROLLBACK' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- roll back failed Question write.
		return new WP_Error( 'ax_note_question_write', __( 'The Question could not be saved.', 'axismundi-note' ) );
	}
	$wpdb->query( 'COMMIT' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- commit Question write.

	$saved = axismundi_note_question_get( $post_id );
	return is_array( $saved ) ? $saved : new WP_Error( 'ax_note_question_write', __( 'The Question could not be saved.', 'axismundi-note' ) );
}

/** Bounded SQL datetime from an ISO-8601-ish input, or null when empty/invalid. */
function axismundi_note_question_sanitize_datetime( $value ) : ?string {
	$raw = is_scalar( $value ) ? trim( (string) $value ) : '';
	if ( '' === $raw ) {
		return null;
	}
	$timestamp = strtotime( $raw );
	return false !== $timestamp ? gmdate( 'Y-m-d H:i:s', $timestamp ) : null;
}

/**
 * Freeze one Question's mode and options at first federation, idempotently.
 *
 * Mirrors `axismundi_note_lock_attribution()` exactly: the conditional UPDATE
 * only sets the timestamp while it is still NULL, so a concurrent caller can
 * never race past this into a mode/option change after the first Create.
 *
 * @return bool|WP_Error
 */
function axismundi_note_question_lock( int $post_id ) {
	global $wpdb;
	$existing = axismundi_note_question_row( $post_id );
	if ( ! is_array( $existing ) ) {
		return new WP_Error( 'ax_note_question_missing', __( 'There is no Question to lock.', 'axismundi-note' ) );
	}
	if ( ! empty( $existing['locked_at'] ) ) {
		return true;
	}
	if ( ! axismundi_note_question_ready( $post_id ) ) {
		return new WP_Error( 'ax_note_question_incomplete', __( 'A Question requires at least two options before it can be federated.', 'axismundi-note' ) );
	}
	$now   = current_time( 'mysql', true );
	$table = axismundi_note_questions_table();
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery -- Trusted table identifier.
	$result = $wpdb->query( $wpdb->prepare( "UPDATE {$table} SET locked_at = %s, updated_at = %s WHERE id = %d AND locked_at IS NULL", $now, $now, (int) $existing['id'] ) );
	if ( false === $result ) {
		return new WP_Error( 'ax_note_question_write', __( 'The Question could not be locked.', 'axismundi-note' ) );
	}
	if ( $result >= 1 ) {
		return true;
	}
	$refreshed = axismundi_note_question_row( $post_id );
	return is_array( $refreshed ) && ! empty( $refreshed['locked_at'] )
		? true
		: new WP_Error( 'ax_note_question_write', __( 'The Question could not be locked.', 'axismundi-note' ) );
}
