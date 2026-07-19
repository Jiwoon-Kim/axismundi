<?php
/**
 * Question storage, option-list validation, and freeze-at-first-federation regression (dev-only).
 *
 * @package AxismundiNote
 */

defined( 'ABSPATH' ) || exit( 1 );

global $wpdb;
$ax_nq_results   = array();
$ax_nq_post_ids  = array();
$ax_nq_user_ids  = array();
$ax_nq_actor_ids = array();

/** @param bool[] $results Results. */
function ax_nq_assert( array &$results, string $label, bool $condition ) : void {
	$results[] = $condition;
	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI test output.
	printf( "[%s] %s\n", $condition ? 'PASS' : 'FAIL', $label );
}

/** Create a public local Actor for one fixture user. */
function ax_nq_author( array &$user_ids, array &$actor_ids ) : ?WP_User {
	$login = 'ax_nq_' . strtolower( wp_generate_password( 8, false, false ) );
	$uid   = (int) wp_insert_user( array( 'user_login' => $login, 'user_pass' => wp_generate_password(), 'role' => 'author' ) );
	if ( $uid <= 0 ) {
		return null;
	}
	$user_ids[] = $uid;
	$actor = axismundi_actors_ensure_for_user( $uid );
	if ( $actor instanceof Axismundi_Actor ) {
		$actor_ids[] = $actor->get_identity_id();
		axismundi_actors_register_handle( $actor->get_identity_id(), $login );
		axismundi_actors_set_status( $actor->get_identity_id(), 'public' );
	}
	return get_userdata( $uid ) ?: null;
}

/** Create one draft Note post (no envelope-level Question data). */
function ax_nq_draft( array &$post_ids, int $author_id, string $content ) : int {
	$post_id = (int) wp_insert_post( array( 'post_type' => AXISMUNDI_NOTE_POST_TYPE, 'post_status' => 'draft', 'post_author' => $author_id, 'post_content' => $content ) );
	$post_ids[] = $post_id;
	return $post_id;
}

try {
	$installed = axismundi_note_install_table();
	$q_table   = axismundi_note_questions_table();
	$o_table   = axismundi_note_question_options_table();
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery -- fixture schema probe.
	$q_columns = $wpdb->get_col( "SHOW COLUMNS FROM {$q_table}" );
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery -- fixture schema probe.
	$o_unique = $wpdb->get_results( "SHOW INDEX FROM {$o_table} WHERE Key_name = 'question_option_identity'", ARRAY_A );
	ax_nq_assert( $ax_nq_results, 'schema v6 installs the Question and option stores with a unique per-question option identity', $installed && in_array( 'locked_at', $q_columns, true ) && ! empty( $o_unique ) && 0 === (int) $o_unique[0]['Non_unique'] );

	$author = ax_nq_author( $ax_nq_user_ids, $ax_nq_actor_ids );
	$post_id = ax_nq_draft( $ax_nq_post_ids, (int) $author->ID, 'Pick one.' );

	ax_nq_assert( $ax_nq_results, 'an ordinary draft Note is not a Question', ! axismundi_note_is_question( $post_id ) && null === axismundi_note_question_get( $post_id ) );

	$missing_options = axismundi_note_question_save( $post_id, array( 'mode' => 'oneOf' ) );
	ax_nq_assert( $ax_nq_results, 'creating a Question without an option list fails closed', is_wp_error( $missing_options ) && 'ax_note_question_options' === $missing_options->get_error_code() );

	$too_few = axismundi_note_question_save( $post_id, array( 'options' => array( 'Only one' ) ) );
	ax_nq_assert( $ax_nq_results, 'fewer than two options is rejected', is_wp_error( $too_few ) && 'ax_note_question_option_count' === $too_few->get_error_code() );

	$too_many = axismundi_note_question_save( $post_id, array( 'options' => array_map( static fn( int $i ) : string => 'Option ' . $i, range( 1, 21 ) ) ) );
	ax_nq_assert( $ax_nq_results, 'more than twenty options is rejected', is_wp_error( $too_many ) && 'ax_note_question_option_count' === $too_many->get_error_code() );

	$duplicate = axismundi_note_question_save( $post_id, array( 'options' => array( 'Red', 'Blue', 'Red' ) ) );
	ax_nq_assert( $ax_nq_results, 'a duplicate option name is rejected rather than merged', is_wp_error( $duplicate ) && 'ax_note_question_option_duplicate' === $duplicate->get_error_code() );

	$empty_name = axismundi_note_question_save( $post_id, array( 'options' => array( 'Red', '   ' ) ) );
	$long_name  = axismundi_note_question_save( $post_id, array( 'options' => array( 'Red', str_repeat( 'x', 201 ) ) ) );
	$non_string = axismundi_note_question_save( $post_id, array( 'options' => array( 'Red', array( 'nested' ) ) ) );
	ax_nq_assert( $ax_nq_results, 'a blank, over-length, or non-string option fails closed', is_wp_error( $empty_name ) && is_wp_error( $long_name ) && is_wp_error( $non_string ) );

	$bad_mode = axismundi_note_question_save( $post_id, array( 'mode' => 'majority', 'options' => array( 'Red', 'Blue' ) ) );
	ax_nq_assert( $ax_nq_results, 'an unrecognized mode is rejected', is_wp_error( $bad_mode ) && 'ax_note_question_mode' === $bad_mode->get_error_code() );

	$saved = axismundi_note_question_save( $post_id, array( 'mode' => 'anyOf', 'options' => array( 'Red', 'Blue', 'Green' ) ) );
	ax_nq_assert( $ax_nq_results, 'a valid Question is created with ordered options and a stable uuid per option', is_array( $saved ) && 'anyOf' === $saved['mode'] && array( 'Red', 'Blue', 'Green' ) === array_column( $saved['options'], 'name' ) && array( 0, 1, 2 ) === array_column( $saved['options'], 'position' ) && wp_is_uuid( $saved['options'][0]['uuid'] ) );
	ax_nq_assert( $ax_nq_results, 'the Note now identifies as a Question', axismundi_note_is_question( $post_id ) );

	$replaced = axismundi_note_question_save( $post_id, array( 'options' => array( 'Yes', 'No' ) ) );
	ax_nq_assert( $ax_nq_results, 'saving a new option list while unlocked fully replaces the old one, not merges it', is_array( $replaced ) && array( 'Yes', 'No' ) === array_column( $replaced['options'], 'name' ) && 'anyOf' === $replaced['mode'] );

	// F1: attribution already locked (federated as an ordinary Note) closes off ever becoming a Question.
	$ordinary_post = ax_nq_draft( $ax_nq_post_ids, (int) $author->ID, 'Already a Note.' );
	wp_update_post( array( 'ID' => $ordinary_post, 'post_status' => 'publish' ) );
	$type_locked = axismundi_note_question_save( $ordinary_post, array( 'mode' => 'oneOf', 'options' => array( 'Red', 'Blue' ) ) );
	ax_nq_assert( $ax_nq_results, 'a Note already federated as an ordinary Note cannot retroactively become a Question', is_wp_error( $type_locked ) && 'ax_note_question_type_locked' === $type_locked->get_error_code() );

	// F2: locking freezes mode/options but leaves closes_at/closed_at open.
	$lock = axismundi_note_question_lock( $post_id );
	$again = axismundi_note_question_lock( $post_id );
	ax_nq_assert( $ax_nq_results, 'locking a ready Question succeeds and is idempotent', true === $lock && true === $again && ! empty( axismundi_note_question_get( $post_id )['locked_at'] ) );

	$locked_mode_change = axismundi_note_question_save( $post_id, array( 'mode' => 'oneOf' ) );
	$locked_option_change = axismundi_note_question_save( $post_id, array( 'options' => array( 'Yes', 'No', 'Maybe' ) ) );
	ax_nq_assert( $ax_nq_results, 'a locked Question keeps its original mode and options', is_wp_error( $locked_mode_change ) && 'ax_note_question_locked' === $locked_mode_change->get_error_code() && is_wp_error( $locked_option_change ) && 'ax_note_question_locked' === $locked_option_change->get_error_code() );

	$closed = axismundi_note_question_save( $post_id, array( 'closed_at' => '2026-01-01T00:00:00Z' ) );
	ax_nq_assert( $ax_nq_results, 'closed_at remains writable after mode/options lock', is_array( $closed ) && null !== $closed['closed_at'] && array( 'Yes', 'No' ) === array_column( axismundi_note_question_get( $post_id )['options'], 'name' ) );

	// F3: a Question corrupted below the minimum cannot be locked (defensive; unreachable via save()).
	$defensive_post = ax_nq_draft( $ax_nq_post_ids, (int) $author->ID, 'Defensive.' );
	axismundi_note_question_save( $defensive_post, array( 'mode' => 'oneOf', 'options' => array( 'Only', 'Two' ) ) );
	$defensive_question = axismundi_note_question_row( $defensive_post );
	$wpdb->delete( axismundi_note_question_options_table(), array( 'question_id' => (int) $defensive_question['id'] ), array( '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- fixture-induced corruption to exercise the defensive gate.
	$incomplete_lock = axismundi_note_question_lock( $defensive_post );
	ax_nq_assert( $ax_nq_results, 'locking is refused when a Question has fallen below the minimum option count', is_wp_error( $incomplete_lock ) && 'ax_note_question_incomplete' === $incomplete_lock->get_error_code() );

	// End-to-end: publishing a ready Question locks both attribution and the Question together.
	$e2e_post = ax_nq_draft( $ax_nq_post_ids, (int) $author->ID, 'End to end.' );
	axismundi_note_question_save( $e2e_post, array( 'mode' => 'oneOf', 'options' => array( 'Cats', 'Dogs' ) ) );
	wp_update_post( array( 'ID' => $e2e_post, 'post_status' => 'publish' ) );
	$e2e_envelope = axismundi_note_get( $e2e_post );
	$e2e_question = axismundi_note_question_get( $e2e_post );
	ax_nq_assert( $ax_nq_results, 'publishing a ready Question locks attribution and the Question at the same first-federation moment', ! empty( $e2e_envelope['attribution_locked_at'] ) && ! empty( $e2e_question['locked_at'] ) );

	// End-to-end failure: a Question that never reached the minimum option count blocks federation entirely.
	$blocked_post = ax_nq_draft( $ax_nq_post_ids, (int) $author->ID, 'Should stay held.' );
	axismundi_note_question_save( $blocked_post, array( 'mode' => 'oneOf', 'options' => array( 'A', 'B' ) ) );
	$blocked_question = axismundi_note_question_row( $blocked_post );
	$wpdb->delete( axismundi_note_question_options_table(), array( 'question_id' => (int) $blocked_question['id'] ), array( '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- fixture-induced corruption.
	wp_update_post( array( 'ID' => $blocked_post, 'post_status' => 'publish' ) );
	$blocked_envelope = axismundi_note_get( $blocked_post );
	$blocked_lifecycle = function_exists( 'axismundi_act_get_object_lifecycle' ) && '' !== axismundi_note_object_uri( (string) $blocked_envelope['local_uuid'] )
		? axismundi_act_get_object_lifecycle( axismundi_note_object_uri( (string) $blocked_envelope['local_uuid'] ) )
		: null;
	ax_nq_assert( $ax_nq_results, 'an under-provisioned Question never reaches attribution lock or a recorded Create', empty( $blocked_envelope['attribution_locked_at'] ) && null === $blocked_lifecycle );
} finally {
	foreach ( array_unique( $ax_nq_post_ids ) as $post_id ) {
		$question = axismundi_note_question_row( (int) $post_id );
		if ( is_array( $question ) ) {
			$wpdb->delete( axismundi_note_question_options_table(), array( 'question_id' => (int) $question['id'] ), array( '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->delete( axismundi_note_questions_table(), array( 'id' => (int) $question['id'] ), array( '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		}
		$wpdb->delete( axismundi_note_table(), array( 'post_id' => (int) $post_id ), array( '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		if ( get_post( (int) $post_id ) instanceof WP_Post ) {
			wp_delete_post( (int) $post_id, true );
		}
	}
	foreach ( array_unique( $ax_nq_actor_ids ) as $identity_id ) {
		foreach ( array( axismundi_actors_texts_table(), axismundi_actors_addresses_table(), axismundi_actors_endpoints_table(), axismundi_actors_asset_cache_table(), axismundi_actors_keys_table(), axismundi_actors_fetch_state_table() ) as $actor_table ) {
			$wpdb->delete( $actor_table, array( 'identity_id' => (int) $identity_id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		}
		$wpdb->delete( axismundi_actors_actors_table(), array( 'identity_id' => (int) $identity_id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->delete( axismundi_actors_identities_table(), array( 'id' => (int) $identity_id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	}
	if ( ! empty( $ax_nq_user_ids ) ) {
		require_once ABSPATH . 'wp-admin/includes/user.php';
		foreach ( array_unique( $ax_nq_user_ids ) as $uid ) {
			if ( get_userdata( (int) $uid ) ) {
				wp_delete_user( (int) $uid );
			}
		}
	}
}

$ax_nq_failures = count( array_filter( $ax_nq_results, static fn( bool $result ) : bool => ! $result ) );
// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI test output.
printf( "\n== %d checks, %d failed ==\n", count( $ax_nq_results ), $ax_nq_failures );
if ( class_exists( 'WP_CLI' ) ) {
	WP_CLI::halt( $ax_nq_failures > 0 ? 1 : 0 );
}
exit( $ax_nq_failures > 0 ? 1 : 0 );
