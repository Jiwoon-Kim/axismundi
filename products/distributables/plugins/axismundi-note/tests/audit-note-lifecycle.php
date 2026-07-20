<?php
/**
 * Note Create / Update / Delete lifecycle regression (dev-only).
 *
 * @package AxismundiNote
 */

defined( 'ABSPATH' ) || exit( 1 );

global $wpdb;
$ax_nl_results      = array();
$ax_nl_post_ids     = array();
$ax_nl_object_uris  = array();
$ax_nl_user_ids     = array();
$ax_nl_identity_ids = array();
$ax_nl_previous_user = get_current_user_id();
$GLOBALS['ax_nl_http'] = 0;

/** @param bool[] $results Results. */
function ax_nl_assert( array &$results, string $label, bool $condition ) : void {
	$results[] = $condition;
	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI fixture output.
	printf( "[%s] %s\n", $condition ? 'PASS' : 'FAIL', $label );
}

/** Prove lifecycle recording performs no transport. */
function ax_nl_http( $preempt ) {
	++$GLOBALS['ax_nl_http'];
	return $preempt;
}

/** Fail only the next Activity insert. */
function ax_nl_break_activity_insert( $query ) {
	return false !== stripos( (string) $query, 'INSERT INTO' ) && false !== strpos( (string) $query, axismundi_act_activities_table() )
		? 'INSERT INTO ax_activities_deliberately_missing (id) VALUES (1)'
		: $query;
}

/** Create one public local author Actor. */
function ax_nl_actor( array &$users, array &$identities ) : ?Axismundi_Actor {
	$login = 'ax_nl_' . strtolower( wp_generate_password( 8, false, false ) );
	$uid   = (int) wp_insert_user( array( 'user_login' => $login, 'user_pass' => wp_generate_password(), 'role' => 'administrator' ) );
	if ( $uid <= 0 ) {
		return null;
	}
	$users[] = $uid;
	$actor   = axismundi_actors_ensure_for_user( $uid );
	if ( ! $actor instanceof Axismundi_Actor ) {
		return null;
	}
	$identities[] = $actor->get_identity_id();
	axismundi_actors_register_handle( $actor->get_identity_id(), $login );
	axismundi_actors_set_status( $actor->get_identity_id(), 'public' );
	axismundi_actors_set_default_language( $actor->get_identity_id(), 'en' );
	return axismundi_actors_get_by_uri( $actor->get_uri() );
}

try {
	add_filter( 'pre_http_request', 'ax_nl_http' );
	$actor = ax_nl_actor( $ax_nl_user_ids, $ax_nl_identity_ids );
	$uid   = $actor instanceof Axismundi_Actor ? (int) $actor->get_local_user_id() : 0;
	wp_set_current_user( $uid );

	$post_id = (int) wp_insert_post(
		array(
			'post_type'    => AXISMUNDI_NOTE_POST_TYPE,
			'post_status'  => 'draft',
			'post_author'  => $uid,
			'post_content' => '<p>Lifecycle one.</p>',
		)
	);
	$ax_nl_post_ids[] = $post_id;
	axismundi_note_save_envelope( $post_id, array( 'visibility' => 'public', 'language' => 'en' ) );
	$envelope  = axismundi_note_get( $post_id );
	$object_uri = is_array( $envelope ) ? axismundi_note_object_uri( (string) $envelope['local_uuid'] ) : '';
	$ax_nl_object_uris[] = $object_uri;
	ax_nl_assert( $ax_nl_results, 'a draft Note has no outbound lifecycle', array() === axismundi_act_get_by_object( $object_uri ) );

	wp_update_post( array( 'ID' => $post_id, 'post_status' => 'publish' ) );
	$activities = axismundi_act_get_by_object( $object_uri );
	$create     = $activities[0] ?? null;
	$payload    = $create instanceof Axismundi_Activity ? $create->get_payload() : array();
	ax_nl_assert(
		$ax_nl_results,
		'first publication records one embedded-snapshot Create with matching Object and Activity audience',
		1 === count( $activities )
		&& $create instanceof Axismundi_Activity
		&& 'Create' === $create->get_type()
		&& is_array( $payload['object'] ?? null )
		&& $object_uri === ( $payload['object']['id'] ?? '' )
		&& ( $payload['object']['to'] ?? null ) === ( $payload['to'] ?? null )
		&& ( $payload['object']['cc'] ?? null ) === ( $payload['cc'] ?? null )
	);

	$replay = axismundi_note_record_commit( get_post( $post_id ) );
	ax_nl_assert( $ax_nl_results, 'replaying the same committed snapshot returns the original Create', $replay instanceof Axismundi_Activity && $create instanceof Axismundi_Activity && $replay->get_id() === $create->get_id() && 1 === count( axismundi_act_get_by_object( $object_uri ) ) );

	$rest = new WP_REST_Request( 'POST', '/wp/v2/' . AXISMUNDI_NOTE_POST_TYPE . '/' . $post_id );
	$rest->set_body_params(
		array(
			'content'                    => '<p>Lifecycle two.</p>',
			'axismundi_note_envelope' => array_merge( axismundi_note_get_envelope( $post_id ), array( 'visibility' => 'followers' ) ),
		)
	);
	$response   = rest_do_request( $rest );
	$activities = axismundi_act_get_by_object( $object_uri );
	$update     = $activities[0] ?? null;
	$update_payload = $update instanceof Axismundi_Activity ? $update->get_payload() : array();
	ax_nl_assert(
		$ax_nl_results,
		'Gutenberg publication waits for the envelope field and records one followers-only Update',
		200 === $response->get_status()
		&& 2 === count( $activities )
		&& $update instanceof Axismundi_Activity
		&& 'Update' === $update->get_type()
		&& false !== strpos( (string) ( $update_payload['object']['content'] ?? '' ), 'Lifecycle two.' )
		&& array() === ( $update_payload['cc'] ?? null )
		&& ! empty( $update_payload['to'] )
	);

	$update_replay = axismundi_note_record_commit( get_post( $post_id ) );
	ax_nl_assert( $ax_nl_results, 'a duplicate callback after REST completion does not mint another Update', $update_replay instanceof Axismundi_Activity && $update instanceof Axismundi_Activity && $update_replay->get_id() === $update->get_id() && 2 === count( axismundi_act_get_by_object( $object_uri ) ) );

	wp_update_post( array( 'ID' => $post_id, 'post_content' => '<p>Lifecycle one.</p>' ) );
	$reverted = axismundi_act_get_by_object( $object_uri );
	ax_nl_assert( $ax_nl_results, 'returning to an older representation is a new Update rather than a historical-event collision', 3 === count( $reverted ) && 'Update' === $reverted[0]->get_type() && $update instanceof Axismundi_Activity && $reverted[0]->get_id() !== $update->get_id() );

	$language_change = axismundi_note_save_envelope( $post_id, array_merge( axismundi_note_get_envelope( $post_id ), array( 'language' => 'fr' ) ) );
	ax_nl_assert( $ax_nl_results, 'the first federation exposure keeps its BCP-47 snapshot immutable', is_wp_error( $language_change ) && 'ax_note_language_locked' === $language_change->get_error_code() );

	wp_update_post( array( 'ID' => $post_id, 'post_status' => 'draft' ) );
	$withdrawn = axismundi_act_get_by_object( $object_uri );
	$delete    = $withdrawn[0] ?? null;
	ax_nl_assert( $ax_nl_results, 'leaving publish records one URI-only Delete with the preceding Update audience', 4 === count( $withdrawn ) && $delete instanceof Axismundi_Activity && 'Delete' === $delete->get_type() && ! is_array( $delete->get_payload()['object'] ?? null ) && $reverted[0]->get_audience() === $delete->get_audience() );

	wp_update_post( array( 'ID' => $post_id, 'post_status' => 'publish' ) );
	$resurrected = axismundi_act_get_by_object( $object_uri );
	ax_nl_assert( $ax_nl_results, 'republishing after Delete begins a new generation with one Create', 5 === count( $resurrected ) && 'Create' === $resurrected[0]->get_type() );

	$question_id = (int) wp_insert_post( array( 'post_type' => AXISMUNDI_NOTE_POST_TYPE, 'post_status' => 'draft', 'post_author' => $uid, 'post_content' => '<p>Question revision.</p>' ) );
	$ax_nl_post_ids[] = $question_id;
	axismundi_note_save_envelope( $question_id, array( 'visibility' => 'public', 'language' => 'en' ) );
	axismundi_note_question_save( $question_id, array( 'mode' => 'oneOf', 'options' => array( 'Yes', 'No' ) ) );
	wp_update_post( array( 'ID' => $question_id, 'post_status' => 'publish' ) );
	$question_envelope = axismundi_note_get( $question_id );
	$question_uri      = is_array( $question_envelope ) ? axismundi_note_object_uri( (string) $question_envelope['local_uuid'] ) : '';
	$ax_nl_object_uris[] = $question_uri;
	$question_before = axismundi_act_get_by_object( $question_uri );
	$question_rest = new WP_REST_Request( 'POST', '/wp/v2/' . AXISMUNDI_NOTE_POST_TYPE . '/' . $question_id );
	$question_rest->set_body_params( array( 'axismundi_note_question' => array( 'enabled' => false ) ) );
	$question_response = rest_do_request( $question_rest );
	$question_after    = axismundi_act_get_by_object( $question_uri );
	$question_update   = $question_after[0] ?? null;
	$question_payload  = $question_update instanceof Axismundi_Activity ? $question_update->get_payload() : array();
	ax_nl_assert(
		$ax_nl_results,
		'a REST Question-to-Note conversion records one same-URI Update with a Note snapshot',
		200 === $question_response->get_status()
		&& 1 === count( $question_before )
		&& 'Create' === ( $question_before[0]->get_type() ?? '' )
		&& 2 === count( $question_after )
		&& $question_update instanceof Axismundi_Activity
		&& 'Update' === $question_update->get_type()
		&& 'Note' === ( $question_payload['object']['type'] ?? '' )
		&& $question_uri === ( $question_payload['object']['id'] ?? '' )
		&& ! isset( $question_payload['object']['oneOf'] )
		&& ! isset( $question_payload['object']['anyOf'] )
	);

	$abort_id = (int) wp_insert_post( array( 'post_type' => AXISMUNDI_NOTE_POST_TYPE, 'post_status' => 'draft', 'post_author' => $uid, 'post_content' => '<p>Abort lifecycle.</p>' ) );
	$ax_nl_post_ids[] = $abort_id;
	axismundi_note_save_envelope( $abort_id, array( 'visibility' => 'public', 'language' => 'en' ) );
	wp_update_post( array( 'ID' => $abort_id, 'post_status' => 'publish' ) );
	$abort_envelope = axismundi_note_get( $abort_id );
	$abort_uri      = is_array( $abort_envelope ) ? axismundi_note_object_uri( (string) $abort_envelope['local_uuid'] ) : '';
	$ax_nl_object_uris[] = $abort_uri;
	add_filter( 'query', 'ax_nl_break_activity_insert' );
	$aborted = wp_delete_post( $abort_id, true );
	remove_filter( 'query', 'ax_nl_break_activity_insert' );
	$abort_after = axismundi_note_get( $abort_id );
	ax_nl_assert( $ax_nl_results, 'a failed durable Delete aborts hard deletion after fail-closed tombstoning', false === $aborted && get_post( $abort_id ) instanceof WP_Post && is_array( $abort_after ) && 'tombstone' === $abort_after['object_status'] && 'Create' === ( axismundi_act_get_object_lifecycle( $abort_uri )->get_type() ?? '' ) );

	$retried = wp_delete_post( $abort_id, true );
	$ax_nl_post_ids = array_values( array_diff( $ax_nl_post_ids, array( $abort_id ) ) );
	ax_nl_assert( $ax_nl_results, 'retrying the hard delete completes the one missing Delete before removing the Post', $retried instanceof WP_Post && ! get_post( $abort_id ) && ( axismundi_act_get_object_lifecycle( $abort_uri ) instanceof Axismundi_Activity ) && 'Delete' === axismundi_act_get_object_lifecycle( $abort_uri )->get_type() );

	ax_nl_assert( $ax_nl_results, 'the complete Note lifecycle performs no HTTP request', 0 === $GLOBALS['ax_nl_http'] );
} finally {
	remove_filter( 'query', 'ax_nl_break_activity_insert' );
	remove_filter( 'pre_http_request', 'ax_nl_http' );
	foreach ( array_unique( $ax_nl_object_uris ) as $uri ) {
		$wpdb->delete( axismundi_act_activities_table(), array( 'object_uri_hash' => hash( 'sha256', $uri ) ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	}
	foreach ( array_unique( $ax_nl_post_ids ) as $post_id ) {
		$question = axismundi_note_question_row( (int) $post_id );
		if ( is_array( $question ) ) {
			$question_id = (int) $question['id'];
			$wpdb->delete( axismundi_note_poll_votes_table(), array( 'question_id' => $question_id ), array( '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->delete( axismundi_note_question_options_table(), array( 'question_id' => $question_id ), array( '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->delete( axismundi_note_questions_table(), array( 'id' => $question_id ), array( '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		}
		$wpdb->delete( axismundi_note_table(), array( 'post_id' => (int) $post_id ), array( '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		if ( get_post( (int) $post_id ) instanceof WP_Post ) {
			wp_delete_post( (int) $post_id, true );
		}
	}
	foreach ( array_unique( $ax_nl_identity_ids ) as $identity_id ) {
		foreach ( array( axismundi_actors_texts_table(), axismundi_actors_addresses_table(), axismundi_actors_endpoints_table(), axismundi_actors_asset_cache_table(), axismundi_actors_keys_table(), axismundi_actors_fetch_state_table() ) as $table ) {
			$wpdb->delete( $table, array( 'identity_id' => (int) $identity_id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		}
		$wpdb->delete( axismundi_actors_actors_table(), array( 'identity_id' => (int) $identity_id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->delete( axismundi_actors_identities_table(), array( 'id' => (int) $identity_id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	}
	if ( $ax_nl_user_ids ) {
		require_once ABSPATH . 'wp-admin/includes/user.php';
		foreach ( array_unique( $ax_nl_user_ids ) as $uid ) {
			if ( get_userdata( (int) $uid ) ) {
				wp_delete_user( (int) $uid );
			}
		}
	}
	wp_set_current_user( $ax_nl_previous_user );
}

$ax_nl_failures = count( array_filter( $ax_nl_results, static fn( bool $result ) : bool => ! $result ) );
// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI fixture output.
printf( "\n== %d checks, %d failed ==\n", count( $ax_nl_results ), $ax_nl_failures );
if ( class_exists( 'WP_CLI' ) ) {
	WP_CLI::halt( $ax_nl_failures > 0 ? 1 : 0 );
}
exit( $ax_nl_failures > 0 ? 1 : 0 );
