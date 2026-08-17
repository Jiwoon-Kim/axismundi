<?php
/**
 * A mention arriving in somebody's inbox (dev-only; dist-excluded).
 *
 * The seam between Note and Notifications, which neither plugin's own audit can see: Note says what
 * a note meant, Notifications stores and hands it out, and this is the check that the two are
 * actually joined. It is the second product to go through the ledger path the Calendar proved, which
 * is what makes Notifications a common consumer rather than a calendar feature.
 *
 * @package AxismundiNotifications
 */

defined( 'ABSPATH' ) || exit( 1 );

$ax_ne_results = array();
$ax_ne_users   = array();
$ax_ne_posts   = array();

/** @param bool[] $results Results. */
function ax_ne_assert( array &$results, string $label, bool $condition ) : void {
	$results[] = $condition;
	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI fixture output.
	printf( "[%s] %s\n", $condition ? 'PASS' : 'FAIL', $label );
}

if ( ! function_exists( 'axismundi_note_resolve_notification_intents' ) ) {
	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI fixture output.
	printf( "[FAIL] Axismundi Note is not active, so its notices cannot be audited\n" );
	exit( 1 );
}

/** An account with an activated, published Person Actor. */
function ax_ne_user( array &$users ) : int {
	$login = 'axne' . strtolower( wp_generate_password( 8, false, false ) );
	$id    = (int) wp_insert_user(
		array( 'user_login' => $login, 'user_email' => $login . '@example.test', 'user_pass' => wp_generate_password(), 'role' => 'administrator' )
	);
	$users[] = $id;
	$actor   = axismundi_actors_ensure_for_user( $id );
	axismundi_actors_register_handle( $actor->get_identity_id(), $login );
	axismundi_actors_set_status( $actor->get_identity_id(), 'public' );
	return $id;
}

/** One published note. */
function ax_ne_note( array &$posts, int $author, string $mentions, string $visibility, string $body ) : array {
	$post_id = (int) wp_insert_post(
		array( 'post_type' => AXISMUNDI_NOTE_POST_TYPE, 'post_content' => $body, 'post_status' => 'publish', 'post_author' => $author )
	);
	$posts[] = $post_id;
	$saved   = axismundi_note_save( $post_id, array( 'visibility' => $visibility, 'mention_actor_uris' => array( $mentions ) ) );
	if ( is_wp_error( $saved ) ) {
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI fixture output.
		printf( "  (fixture refused: %s)\n", $saved->get_error_message() );
		return array();
	}
	$envelope = axismundi_note_get( $post_id );
	return is_array( $envelope ) ? $envelope : array();
}

/** The kinds one person has been sent. */
function ax_ne_kinds( int $user_id ) : array {
	return array_map(
		static fn( array $row ) : string => (string) $row['kind'],
		axismundi_ntf_inbox( $user_id, 20 )
	);
}

try {
	$ax_ne_alice_user = ax_ne_user( $ax_ne_users );
	$ax_ne_bob_user   = ax_ne_user( $ax_ne_users );
	$ax_ne_alice      = axismundi_actors_get_for_user( $ax_ne_alice_user );
	$ax_ne_bob        = axismundi_actors_get_for_user( $ax_ne_bob_user );
	wp_set_current_user( $ax_ne_alice_user );

	// -- a mention reaches the person named -----------------------------------------------------------------

	$ax_ne_before = axismundi_ntf_unread_count( $ax_ne_bob_user );
	ax_ne_note( $ax_ne_posts, $ax_ne_alice_user, (string) $ax_ne_bob->get_uri(), 'public', 'Nice work, @bob' );
	ax_ne_assert(
		$ax_ne_results,
		'writing a note that names somebody puts it in their inbox by the end of the save',
		$ax_ne_before + 1 === axismundi_ntf_unread_count( $ax_ne_bob_user )
			&& in_array( 'axismundi-note/mentioned', ax_ne_kinds( $ax_ne_bob_user ), true )
	);
	// The author performed it, so it is not news to them.
	ax_ne_assert(
		$ax_ne_results,
		'and the person who wrote it is not told about their own note',
		0 === axismundi_ntf_unread_count( $ax_ne_alice_user )
	);

	// -- a message arrives as a message ----------------------------------------------------------------------

	$ax_ne_message = ax_ne_note( $ax_ne_posts, $ax_ne_alice_user, (string) $ax_ne_bob->get_uri(), 'mentioned', 'Are you free on Thursday?' );
	ax_ne_assert(
		$ax_ne_results,
		'while a direct note arrives as a message rather than as another mention',
		in_array( 'axismundi-note/direct-note-received', ax_ne_kinds( $ax_ne_bob_user ), true )
			&& $ax_ne_before + 2 === axismundi_ntf_unread_count( $ax_ne_bob_user )
	);

	// -- reading the conversation deals with the notice --------------------------------------------------------

	/*
	 * The seam that points the other way, and the reason it exists. Bob opens the conversation; the
	 * conversation records that he read it, and tells Notifications, which marks its own row read --
	 * because a notice about something already dealt with is noise.
	 */
	$ax_ne_context = axismundi_note_conversation_uri( $ax_ne_message );
	axismundi_note_mark_conversation_read( $ax_ne_context, $ax_ne_bob_user );
	$ax_ne_after = axismundi_ntf_inbox( $ax_ne_bob_user, 20 );
	$ax_ne_unread_kinds = array_map(
		static fn( array $row ) : string => (string) $row['kind'],
		array_filter( $ax_ne_after, static fn( array $row ) : bool => null !== $row['delivery_id'] && null === $row['read_at'] )
	);
	ax_ne_assert(
		$ax_ne_results,
		'opening the conversation marks the message notice read, without touching the mention',
		! in_array( 'axismundi-note/direct-note-received', $ax_ne_unread_kinds, true )
			&& in_array( 'axismundi-note/mentioned', $ax_ne_unread_kinds, true )
	);
	// And the conversation is the thing that knows he read it.
	ax_ne_assert(
		$ax_ne_results,
		'and the conversation is where "read" actually lives',
		false === axismundi_note_conversations_for( (string) $ax_ne_bob->get_uri() )[0]['unread']
	);
	/*
	 * The gate is still asked. Somebody who cannot read that Actor's inbox does not get to mark its
	 * notices read by having been told about a conversation.
	 */
	$ax_ne_stranger = ax_ne_user( $ax_ne_users );
	ax_ne_assert(
		$ax_ne_results,
		'somebody else saying it was dealt with marks nothing, the gate being asked either way',
		0 === axismundi_ntf_mark_read_by_source( 'https://example.test/nothing', $ax_ne_stranger )
			&& 0 === axismundi_ntf_unread_count( $ax_ne_stranger )
	);
} finally {
	global $wpdb;
	wp_set_current_user( 0 );
	foreach ( array_unique( $ax_ne_posts ) as $ax_ne_post_id ) {
		wp_delete_post( (int) $ax_ne_post_id, true );
	}
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fixture cleanup.
	$wpdb->query( $wpdb->prepare( 'DELETE FROM ' . axismundi_ntf_events_table() . ' WHERE kind LIKE %s', 'axismundi-note/%' ) );
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fixture cleanup.
	$wpdb->query( 'DELETE d FROM ' . axismundi_ntf_deliveries_table() . ' d LEFT JOIN ' . axismundi_ntf_events_table() . ' e ON e.id = d.notification_id WHERE e.id IS NULL' );
	foreach ( array_unique( $ax_ne_users ) as $ax_ne_user_id ) {
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fixture cleanup.
		$wpdb->delete( axismundi_note_conversation_state_table(), array( 'local_user_id' => (int) $ax_ne_user_id ), array( '%d' ) );
		wp_delete_user( (int) $ax_ne_user_id );
	}
}

$ax_ne_failures = count( array_filter( $ax_ne_results, static fn( bool $result ) : bool => ! $result ) );
// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI fixture output.
printf( "\n== %d checks, %d failed ==\n", count( $ax_ne_results ), $ax_ne_failures );
if ( class_exists( 'WP_CLI' ) ) {
	WP_CLI::halt( $ax_ne_failures > 0 ? 1 : 0 );
}
exit( $ax_ne_failures > 0 ? 1 : 0 );
