<?php
/** Activity-ledger vote classification and tally regression (dev-only). */

defined( 'ABSPATH' ) || exit( 1 );

global $wpdb;
$ax_npv_results = array();
$ax_npv_posts   = array();
$ax_npv_users   = array();
$ax_npv_actors  = array();
$ax_npv_events  = array();

function ax_npv_assert( array &$results, string $label, bool $condition ) : void {
	$results[] = $condition;
	printf( "[%s] %s\n", $condition ? 'PASS' : 'FAIL', $label ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI fixture output.
}

function ax_npv_user( array &$users, array &$actors ) : ?WP_User {
	$login = 'ax_npv_' . strtolower( wp_generate_password( 8, false, false ) );
	$id    = (int) wp_insert_user( array( 'user_login' => $login, 'user_pass' => wp_generate_password(), 'role' => 'author' ) );
	if ( $id <= 0 ) {
		return null;
	}
	$users[] = $id;
	$actor = axismundi_actors_ensure_for_user( $id );
	if ( $actor instanceof Axismundi_Actor ) {
		$actors[] = $actor->get_identity_id();
		axismundi_actors_register_handle( $actor->get_identity_id(), $login );
		axismundi_actors_set_status( $actor->get_identity_id(), 'public' );
	}
	return get_userdata( $id ) ?: null;
}

function ax_npv_question( array &$posts, int $author_id, string $mode, array $options ) : array {
	$post_id = (int) wp_insert_post( array( 'post_type' => AXISMUNDI_NOTE_POST_TYPE, 'post_status' => 'draft', 'post_author' => $author_id, 'post_content' => 'Question fixture.' ) );
	$posts[] = $post_id;
	axismundi_note_question_save( $post_id, array( 'mode' => $mode, 'options' => $options ) );
	wp_update_post( array( 'ID' => $post_id, 'post_status' => 'publish' ) );
	$envelope = axismundi_note_get( $post_id );
	return array( 'post_id' => $post_id, 'uri' => axismundi_note_object_uri( (string) $envelope['local_uuid'] ) );
}

function ax_npv_create( Axismundi_Actor $actor, string $parent_uri, string $name, $content = '', array $attachment = array() ) {
	return axismundi_act_record_activity(
		array(
			'type'   => 'Create',
			'actor'  => $actor->get_uri(),
			'object' => array(
				'id'           => home_url( '/?ax-vote=' . wp_generate_uuid4() ),
				'type'         => 'Note',
				'attributedTo' => $actor->get_uri(),
				'inReplyTo'    => $parent_uri,
				'name'         => $name,
				'content'      => $content,
				'attachment'   => $attachment,
			),
		),
		'outbound'
	);
}

try {
	axismundi_note_install_table();
	$owner = ax_npv_user( $ax_npv_users, $ax_npv_actors );
	$voter = ax_npv_user( $ax_npv_users, $ax_npv_actors );
	$owner_actor = axismundi_actors_get_for_user( (int) $owner->ID );
	$voter_actor = axismundi_actors_get_for_user( (int) $voter->ID );
	$one = ax_npv_question( $ax_npv_posts, (int) $owner->ID, 'oneOf', array( 'Cats', 'Dogs' ) );

	$first = ax_npv_create( $voter_actor, $one['uri'], 'Cats' );
	$ax_npv_events[] = $first instanceof Axismundi_Activity ? $first->get_uri() : '';
	$first_view = axismundi_note_question_view( $one['post_id'] );
	ax_npv_assert( $ax_npv_results, 'an exact empty Create(Note) reply to a local open Question records one vote', $first instanceof Axismundi_Activity && 1 === $first_view['voters_count'] && array( 1, 0 ) === array_column( $first_view['options'], 'votes' ) );

	$second = ax_npv_create( $voter_actor, $one['uri'], 'Dogs' );
	$ax_npv_events[] = $second instanceof Axismundi_Activity ? $second->get_uri() : '';
	$second_view = axismundi_note_question_view( $one['post_id'] );
	ax_npv_assert( $ax_npv_results, 'oneOf keeps the first active choice for one actor and records a later choice as non-tallying', $second instanceof Axismundi_Activity && 1 === $second_view['voters_count'] && array( 1, 0 ) === array_column( $second_view['options'], 'votes' ) );

	$text_reply = ax_npv_create( $owner_actor, $one['uri'], 'Cats', '<p>I prefer cats because they are quiet.</p>' );
	$ax_npv_events[] = $text_reply instanceof Axismundi_Activity ? $text_reply->get_uri() : '';
	$attachment_reply = ax_npv_create( $owner_actor, $one['uri'], 'Dogs', '', array( array( 'type' => 'Image', 'url' => 'https://example.test/image.jpg' ) ) );
	$ax_npv_events[] = $attachment_reply instanceof Axismundi_Activity ? $attachment_reply->get_uri() : '';
	$after_replies = axismundi_note_question_view( $one['post_id'] );
	ax_npv_assert( $ax_npv_results, 'a textual reply or an attachment-bearing reply is never misclassified as a vote', 1 === $after_replies['voters_count'] && array( 1, 0 ) === array_column( $after_replies['options'], 'votes' ) );

	$spoofed_delete = axismundi_act_record_activity( array( 'type' => 'Delete', 'actor' => $owner_actor->get_uri(), 'object' => (string) ( $first->get_payload()['object']['id'] ?? '' ) ), 'outbound' );
	$ax_npv_events[] = $spoofed_delete instanceof Axismundi_Activity ? $spoofed_delete->get_uri() : '';
	$after_spoof = axismundi_note_question_view( $one['post_id'] );
	ax_npv_assert( $ax_npv_results, 'a Delete from a different Actor cannot remove another Actor\'s vote', $spoofed_delete instanceof Axismundi_Activity && 1 === $after_spoof['voters_count'] && array( 1, 0 ) === array_column( $after_spoof['options'], 'votes' ) );

	$undo = axismundi_act_record_activity( array( 'type' => 'Undo', 'actor' => $voter_actor->get_uri(), 'object' => $first->get_uri() ), 'outbound' );
	$ax_npv_events[] = $undo instanceof Axismundi_Activity ? $undo->get_uri() : '';
	$after_undo = axismundi_note_question_view( $one['post_id'] );
	ax_npv_assert( $ax_npv_results, 'Undo of a vote Create removes it from the tally without deleting its immutable observation', $undo instanceof Axismundi_Activity && 0 === $after_undo['voters_count'] && array( 0, 0 ) === array_column( $after_undo['options'], 'votes' ) );

	$many = ax_npv_question( $ax_npv_posts, (int) $owner->ID, 'anyOf', array( 'Red', 'Blue' ) );
	$red = ax_npv_create( $voter_actor, $many['uri'], 'Red' );
	$blue = ax_npv_create( $voter_actor, $many['uri'], 'Blue' );
	$again = ax_npv_create( $voter_actor, $many['uri'], 'Red' );
	foreach ( array( $red, $blue, $again ) as $event ) {
		$ax_npv_events[] = $event instanceof Axismundi_Activity ? $event->get_uri() : '';
	}
	$many_view = axismundi_note_question_view( $many['post_id'] );
	ax_npv_assert( $ax_npv_results, 'anyOf accepts distinct options from one actor but ignores the same option twice', 1 === $many_view['voters_count'] && array( 1, 1 ) === array_column( $many_view['options'], 'votes' ) );

	$cast_question = ax_npv_question( $ax_npv_posts, (int) $owner->ID, 'oneOf', array( 'Tea', 'Coffee' ) );
	wp_set_current_user( (int) $voter->ID );
	$cast = axismundi_note_cast_poll_vote( $cast_question['uri'], array( 'Tea' ) );
	if ( is_array( $cast ) ) {
		$ax_npv_posts = array_merge( $ax_npv_posts, $cast );
	}
	$cast_view = axismundi_note_question_view( $cast_question['post_id'] );
	ax_npv_assert( $ax_npv_results, 'the local vote action creates a constrained Note through the normal lifecycle and increments the same tally', is_array( $cast ) && 1 === count( $cast ) && 1 === $cast_view['voters_count'] && array( 1, 0 ) === array_column( $cast_view['options'], 'votes' ) );
	$redirect_uri = axismundi_note_poll_vote_redirect_uri( $cast_question['uri'], new WP_Error( 'ax_note_vote_choice', 'Choose a valid option.' ) );
	$notice = axismundi_note_take_poll_vote_notice( $cast_question['uri'] );
	$notice_consumed = axismundi_note_take_poll_vote_notice( $cast_question['uri'] );
	$form = axismundi_note_question_actions( '', array( 'object_uri' => $cast_question['uri'] ), array( 'mode' => 'oneOf', 'options' => array( array( 'name' => 'Tea' ), array( 'name' => 'Coffee' ) ) ) );
	ax_npv_assert( $ax_npv_results, 'vote redirects keep the exact canonical Question URI and pass one-time feedback without a route-breaking query key', $cast_question['uri'] === $redirect_uri && false === strpos( $redirect_uri, 'ax_vote' ) && is_array( $notice ) && 'error' === $notice['type'] && 'Choose a valid option.' === $notice['message'] && null === $notice_consumed && false !== strpos( $form, 'required' ) );
	wp_set_current_user( 0 );

	$dedupe_question = ax_npv_question( $ax_npv_posts, (int) $owner->ID, 'anyOf', array( 'Spring', 'Autumn' ) );
	wp_set_current_user( (int) $voter->ID );
	$deduped = axismundi_note_cast_poll_vote( $dedupe_question['uri'], array( 'Spring', 'Spring' ) );
	if ( is_array( $deduped ) ) {
		$ax_npv_posts = array_merge( $ax_npv_posts, $deduped );
	}
	$dedupe_view = axismundi_note_question_view( $dedupe_question['post_id'] );
	ax_npv_assert( $ax_npv_results, 'a manipulated duplicate option submission creates one vote Note and one tally observation', is_array( $deduped ) && 1 === count( $deduped ) && 1 === $dedupe_view['voters_count'] && array( 1, 0 ) === array_column( $dedupe_view['options'], 'votes' ) );
	wp_set_current_user( 0 );

	$edge_uri = (string) ( $first->get_payload()['object']['id'] ?? '' );
	ax_npv_assert( $ax_npv_results, 'a confirmed vote object is excluded from the textual reply projection', '' !== $edge_uri && false === axismundi_note_exclude_poll_vote_reply( true, $edge_uri ) && true === axismundi_note_exclude_poll_vote_reply( true, (string) ( $text_reply->get_payload()['object']['id'] ?? '' ) ) );
} finally {
	$activity_table = axismundi_act_activities_table();
	foreach ( array_filter( $ax_npv_events ) as $uri ) {
		$wpdb->delete( $activity_table, array( 'activity_uri_hash' => hash( 'sha256', $uri ) ), array( '%s' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- fixture cleanup.
	}
	foreach ( array_unique( $ax_npv_posts ) as $post_id ) {
		$question = axismundi_note_question_row( (int) $post_id );
		if ( is_array( $question ) ) {
			$wpdb->delete( axismundi_note_poll_votes_table(), array( 'question_id' => (int) $question['id'] ), array( '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- fixture cleanup.
			$wpdb->delete( axismundi_note_question_options_table(), array( 'question_id' => (int) $question['id'] ), array( '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- fixture cleanup.
			$wpdb->delete( axismundi_note_questions_table(), array( 'id' => (int) $question['id'] ), array( '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- fixture cleanup.
		}
		$wpdb->delete( axismundi_note_table(), array( 'post_id' => (int) $post_id ), array( '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- fixture cleanup.
		if ( get_post( (int) $post_id ) instanceof WP_Post ) {
			wp_delete_post( (int) $post_id, true );
		}
	}
	foreach ( array_unique( $ax_npv_actors ) as $identity_id ) {
		foreach ( array( axismundi_actors_texts_table(), axismundi_actors_addresses_table(), axismundi_actors_endpoints_table(), axismundi_actors_asset_cache_table(), axismundi_actors_keys_table(), axismundi_actors_fetch_state_table() ) as $table ) {
			$wpdb->delete( $table, array( 'identity_id' => (int) $identity_id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- fixture cleanup.
		}
		$wpdb->delete( axismundi_actors_actors_table(), array( 'identity_id' => (int) $identity_id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- fixture cleanup.
		$wpdb->delete( axismundi_actors_identities_table(), array( 'id' => (int) $identity_id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- fixture cleanup.
	}
	if ( ! empty( $ax_npv_users ) ) {
		require_once ABSPATH . 'wp-admin/includes/user.php';
		foreach ( array_unique( $ax_npv_users ) as $id ) {
			if ( get_userdata( (int) $id ) ) {
				wp_delete_user( (int) $id );
			}
		}
	}
}

$ax_npv_failures = count( array_filter( $ax_npv_results, static fn( bool $result ) : bool => ! $result ) );
printf( "\n== %d checks, %d failed ==\n", count( $ax_npv_results ), $ax_npv_failures ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI fixture output.
if ( class_exists( 'WP_CLI' ) ) {
	WP_CLI::halt( $ax_npv_failures > 0 ? 1 : 0 );
}
exit( $ax_npv_failures > 0 ? 1 : 0 );
