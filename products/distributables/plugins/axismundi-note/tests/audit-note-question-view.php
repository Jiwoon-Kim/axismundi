<?php
/**
 * Question JSON-LD projection and read-only Poll view-model/block regression (dev-only).
 *
 * @package AxismundiNote
 */

defined( 'ABSPATH' ) || exit( 1 );

global $wpdb;
$ax_nqv_results   = array();
$ax_nqv_post_ids  = array();
$ax_nqv_user_ids  = array();
$ax_nqv_actor_ids = array();

/** @param bool[] $results Results. */
function ax_nqv_assert( array &$results, string $label, bool $condition ) : void {
	$results[] = $condition;
	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI test output.
	printf( "[%s] %s\n", $condition ? 'PASS' : 'FAIL', $label );
}

/** Create a public local Actor for one fixture user. */
function ax_nqv_author( array &$user_ids, array &$actor_ids ) : ?WP_User {
	$login = 'ax_nqv_' . strtolower( wp_generate_password( 8, false, false ) );
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

/** Create, publish, and return the Note source for one fixture post. */
function ax_nqv_publish( array &$post_ids, int $author_id, string $content ) : array {
	$post_id = (int) wp_insert_post( array( 'post_type' => AXISMUNDI_NOTE_POST_TYPE, 'post_status' => 'draft', 'post_author' => $author_id, 'post_content' => $content ) );
	$post_ids[] = $post_id;
	return array( 'post_id' => $post_id );
}

try {
	$author = ax_nqv_author( $ax_nqv_user_ids, $ax_nqv_actor_ids );

	// An ordinary Note stays completely unaffected by the Question projection path.
	$note = ax_nqv_publish( $ax_nqv_post_ids, (int) $author->ID, 'Just a Note.' );
	wp_update_post( array( 'ID' => $note['post_id'], 'post_status' => 'publish' ) );
	$note_envelope = axismundi_note_get( $note['post_id'] );
	$note_source   = new Axismundi_Note_Source( $note_envelope, get_post( $note['post_id'] ) );
	$note_object   = axismundi_note_transform_source( $note_source );
	$note_model    = axismundi_note_object_view_model( $note_source );
	ax_nqv_assert( $ax_nqv_results, 'an ordinary Note projects as Note with no poll members and no view-model poll key', is_array( $note_object ) && 'Note' === $note_object['type'] && ! isset( $note_object['oneOf'], $note_object['anyOf'], $note_object['votersCount'] ) && is_array( $note_model ) && 'Note' === $note_model['type'] && null === $note_model['poll'] );

	axismundi_op_set_current_object_view_model( $note_model );
	$note_block_html = axismundi_op_render_question_block();
	axismundi_op_set_current_object_view_model( null );
	ax_nqv_assert( $ax_nqv_results, 'the Question block renders nothing for an ordinary Note', '' === $note_block_html );

	// A oneOf Question with a future closing time.
	$question = ax_nqv_publish( $ax_nqv_post_ids, (int) $author->ID, 'Pick one.' );
	axismundi_note_question_save( $question['post_id'], array( 'mode' => 'oneOf', 'options' => array( 'Cats', 'Dogs' ), 'closes_at' => '2030-06-01T00:00:00Z' ) );
	wp_update_post( array( 'ID' => $question['post_id'], 'post_status' => 'publish' ) );
	$q_envelope = axismundi_note_get( $question['post_id'] );
	$q_source   = new Axismundi_Note_Source( $q_envelope, get_post( $question['post_id'] ) );
	$q_object   = axismundi_note_transform_source( $q_source );
	ax_nqv_assert(
		$ax_nqv_results,
		'a oneOf Question projects as type Question with a name-only, zero-tally oneOf array and an ISO endTime',
		is_array( $q_object ) && 'Question' === $q_object['type']
			&& is_array( $q_object['oneOf'] ?? null ) && 2 === count( $q_object['oneOf'] )
			&& 'Note' === $q_object['oneOf'][0]['type'] && 'Cats' === $q_object['oneOf'][0]['name'] && 0 === $q_object['oneOf'][0]['replies']['totalItems']
			&& 0 === $q_object['votersCount']
			&& '2030-06-01T00:00:00+00:00' === ( $q_object['endTime'] ?? '' )
			&& ! isset( $q_object['closed'], $q_object['anyOf'] )
	);
	ax_nqv_assert( $ax_nqv_results, 'the ordinary Note transform members (content, attributedTo, audience) are unaffected by the Question branch', 'Pick one.' === trim( wp_strip_all_tags( (string) $q_object['content'] ) ) && '' !== $q_object['attributedTo'] && in_array( 'https://www.w3.org/ns/activitystreams#Public', (array) $q_object['to'], true ) );

	$q_model = axismundi_note_object_view_model( $q_source );
	ax_nqv_assert( $ax_nqv_results, 'the HTML view model carries the same poll shape (mode, options, zero tallies, ISO closes_at)', is_array( $q_model ) && 'Question' === $q_model['type'] && is_array( $q_model['poll'] ) && 'oneOf' === $q_model['poll']['mode'] && array( 'Cats', 'Dogs' ) === array_column( $q_model['poll']['options'], 'name' ) && 0 === $q_model['poll']['voters_count'] && '2030-06-01T00:00:00+00:00' === $q_model['poll']['closes_at'] && '' === $q_model['poll']['closed_at'] );

	axismundi_op_set_current_object_view_model( $q_model );
	$q_block_html = axismundi_op_render_question_block();
	axismundi_op_set_current_object_view_model( null );
	ax_nqv_assert( $ax_nqv_results, 'the Question block renders both options, a 0% bar for each, and an open-voting meta line', false !== strpos( $q_block_html, 'axismundi-question--open' ) && 2 === substr_count( $q_block_html, 'axismundi-question__option-name' ) && false !== strpos( $q_block_html, 'Cats' ) && false !== strpos( $q_block_html, 'Dogs' ) && false !== strpos( $q_block_html, 'width:0%' ) );

	// An anyOf Question that is already closed.
	$multi = ax_nqv_publish( $ax_nqv_post_ids, (int) $author->ID, 'Pick any.' );
	axismundi_note_question_save( $multi['post_id'], array( 'mode' => 'anyOf', 'options' => array( 'Red', 'Green', 'Blue' ), 'closed_at' => '2026-01-01T00:00:00Z' ) );
	wp_update_post( array( 'ID' => $multi['post_id'], 'post_status' => 'publish' ) );
	$multi_envelope = axismundi_note_get( $multi['post_id'] );
	$multi_source   = new Axismundi_Note_Source( $multi_envelope, get_post( $multi['post_id'] ) );
	$multi_object   = axismundi_note_transform_source( $multi_source );
	ax_nqv_assert( $ax_nqv_results, 'an anyOf Question projects into anyOf (not oneOf) with a closed ISO timestamp', is_array( $multi_object['anyOf'] ?? null ) && ! isset( $multi_object['oneOf'] ) && 3 === count( $multi_object['anyOf'] ) && '2026-01-01T00:00:00+00:00' === ( $multi_object['closed'] ?? '' ) );

	$multi_model = axismundi_note_object_view_model( $multi_source );
	axismundi_op_set_current_object_view_model( $multi_model );
	$multi_block_html = axismundi_op_render_question_block();
	axismundi_op_set_current_object_view_model( null );
	ax_nqv_assert( $ax_nqv_results, 'a closed Question renders the closed variant with final-results meta', false !== strpos( $multi_block_html, 'axismundi-question--closed' ) && false !== strpos( $multi_block_html, 'Final results' ) );

	// A tombstoned Question never exposes poll data or renders the block.
	wp_delete_post( $question['post_id'], true );
	$tombstoned_envelope = axismundi_note_get_by_uuid( (string) $q_envelope['local_uuid'] );
	$tombstoned_source   = new Axismundi_Note_Source( $tombstoned_envelope, null );
	$tombstoned_object   = axismundi_note_transform_source( $tombstoned_source );
	$tombstoned_model    = axismundi_note_object_view_model( $tombstoned_source );
	ax_nqv_assert( $ax_nqv_results, 'a tombstoned Question is a privacy-minimal Tombstone with no poll members leaked', is_array( $tombstoned_object ) && 'Tombstone' === $tombstoned_object['type'] && ! isset( $tombstoned_object['oneOf'], $tombstoned_object['anyOf'] ) && is_array( $tombstoned_model ) && 'tombstone' === $tombstoned_model['status'] && ! isset( $tombstoned_model['poll'] ) );

	axismundi_op_set_current_object_view_model( $tombstoned_model );
	$tombstoned_block_html = axismundi_op_render_question_block();
	axismundi_op_set_current_object_view_model( null );
	ax_nqv_assert( $ax_nqv_results, 'the Question block renders nothing for a tombstoned Question', '' === $tombstoned_block_html );
} finally {
	foreach ( array_unique( $ax_nqv_post_ids ) as $post_id ) {
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
	foreach ( array_unique( $ax_nqv_actor_ids ) as $identity_id ) {
		foreach ( array( axismundi_actors_texts_table(), axismundi_actors_addresses_table(), axismundi_actors_endpoints_table(), axismundi_actors_asset_cache_table(), axismundi_actors_keys_table(), axismundi_actors_fetch_state_table() ) as $actor_table ) {
			$wpdb->delete( $actor_table, array( 'identity_id' => (int) $identity_id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		}
		$wpdb->delete( axismundi_actors_actors_table(), array( 'identity_id' => (int) $identity_id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->delete( axismundi_actors_identities_table(), array( 'id' => (int) $identity_id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	}
	if ( ! empty( $ax_nqv_user_ids ) ) {
		require_once ABSPATH . 'wp-admin/includes/user.php';
		foreach ( array_unique( $ax_nqv_user_ids ) as $uid ) {
			if ( get_userdata( (int) $uid ) ) {
				wp_delete_user( (int) $uid );
			}
		}
	}
}

$ax_nqv_failures = count( array_filter( $ax_nqv_results, static fn( bool $result ) : bool => ! $result ) );
// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI test output.
printf( "\n== %d checks, %d failed ==\n", count( $ax_nqv_results ), $ax_nqv_failures );
if ( class_exists( 'WP_CLI' ) ) {
	WP_CLI::halt( $ax_nqv_failures > 0 ? 1 : 0 );
}
exit( $ax_nqv_failures > 0 ? 1 : 0 );
