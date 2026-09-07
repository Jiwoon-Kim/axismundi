<?php
/**
 * What a note meant to the people it named (dev-only; dist-excluded).
 *
 * The split this pins is the one both Mastodon and Misskey make: a mention is a social act and a
 * line in a list, while a direct note is a message whose home is a conversation. One Activity, two
 * kinds, told apart by who may see the note.
 *
 * The rest is about what must not travel. A notification names the author and where to read it and
 * carries nothing of what was said, because a private message exists in one place and a notice that
 * copied it would be a second place to read it from.
 *
 * @package AxismundiNote
 */

defined( 'ABSPATH' ) || exit( 1 );

$ax_nn_results = array();
$ax_nn_users   = array();
$ax_nn_posts   = array();

/** @param bool[] $results Results. */
function ax_nn_assert( array &$results, string $label, bool $condition ) : void {
	$results[] = $condition;
	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI fixture output.
	printf( "[%s] %s\n", $condition ? 'PASS' : 'FAIL', $label );
}

/** An account with an activated, published Person Actor. */
function ax_nn_user( array &$users ) : int {
	$login = 'axnn' . strtolower( wp_generate_password( 8, false, false ) );
	$id    = (int) wp_insert_user(
		array( 'user_login' => $login, 'user_email' => $login . '@example.test', 'user_pass' => wp_generate_password(), 'role' => 'administrator' )
	);
	$users[] = $id;
	$actor   = axismundi_actors_ensure_for_user( $id );
	axismundi_actors_register_handle( $actor->get_identity_id(), $login );
	axismundi_actors_set_status( $actor->get_identity_id(), 'public' );
	return $id;
}

/** One note, and the intents its Create produced. */
function ax_nn_note( array &$posts, int $author, string $mentions, string $visibility, string $body ) : array {
	$post_id = (int) wp_insert_post(
		array( 'post_type' => AXISMUNDI_NOTE_POST_TYPE, 'post_content' => $body, 'post_status' => 'publish', 'post_author' => $author )
	);
	$posts[] = $post_id;
	$saved   = axismundi_note_save(
		$post_id,
		array( 'visibility' => $visibility, 'mention_actor_uris' => array( $mentions ) )
	);
	if ( is_wp_error( $saved ) ) {
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI fixture output.
		printf( "  (fixture refused: %s)\n", $saved->get_error_message() );
		return array();
	}
	$envelope = axismundi_note_get( $post_id );
	return is_array( $envelope ) ? $envelope : array();
}

/** What the resolver says the Create of one note meant. */
function ax_nn_intents( array $note ) : array {
	$uri = axismundi_note_object_uri( (string) $note['local_uuid'] );
	foreach ( axismundi_act_get_by_object( $uri ) as $activity ) {
		if ( 'Create' === $activity->get_type() ) {
			return (array) apply_filters( 'axismundi_notification_intents', array(), $activity );
		}
	}
	return array();
}

try {
	$ax_nn_alice_user = ax_nn_user( $ax_nn_users );
	$ax_nn_bob_user   = ax_nn_user( $ax_nn_users );
	$ax_nn_alice      = axismundi_actors_get_for_user( $ax_nn_alice_user );
	$ax_nn_bob        = axismundi_actors_get_for_user( $ax_nn_bob_user );
	wp_set_current_user( $ax_nn_alice_user );

	// -- two kinds, told apart by who may see it ---------------------------------------------------------

	$ax_nn_public  = ax_nn_note( $ax_nn_posts, $ax_nn_alice_user, (string) $ax_nn_bob->get_uri(), 'public', 'Nice work, @bob' );
	$ax_nn_message = ax_nn_note( $ax_nn_posts, $ax_nn_alice_user, (string) $ax_nn_bob->get_uri(), 'mentioned', 'Can we talk about Thursday?' );
	$ax_nn_mention_intents = ax_nn_intents( $ax_nn_public );
	$ax_nn_message_intents = ax_nn_intents( $ax_nn_message );
	ax_nn_assert(
		$ax_nn_results,
		'a note anybody can read is a mention, and one only its recipients can read is a message',
		1 === count( $ax_nn_mention_intents )
			&& 'axismundi-note/mentioned' === (string) $ax_nn_mention_intents[0]['kind']
			&& 1 === count( $ax_nn_message_intents )
			&& 'axismundi-note/direct-note-received' === (string) $ax_nn_message_intents[0]['kind']
	);
	ax_nn_assert(
		$ax_nn_results,
		'and both are addressed to the Actor the author named',
		(string) $ax_nn_bob->get_uri() === (string) $ax_nn_mention_intents[0]['recipient_actor_uri']
			&& (string) $ax_nn_bob->get_uri() === (string) $ax_nn_message_intents[0]['recipient_actor_uri']
	);

	// -- what does not travel ------------------------------------------------------------------------------

	/*
	 * The check that would fail the day somebody adds an excerpt "so the list reads better". A private
	 * message exists in one place; a notification carrying it would be a second place to read it from
	 * and a second place to have to delete it.
	 */
	$ax_nn_snapshot = (array) $ax_nn_message_intents[0]['snapshot'];
	ax_nn_assert(
		$ax_nn_results,
		'a message notification says who and where, and nothing of what was said',
		! in_array( 'Can we talk about Thursday?', array_map( 'strval', $ax_nn_snapshot ), true )
			&& (string) $ax_nn_alice->get_uri() === (string) $ax_nn_snapshot['author']
			&& '' !== (string) $ax_nn_snapshot['context']
	);
	// A message points at the exchange; a mention points at the note, there being no exchange to open.
	ax_nn_assert(
		$ax_nn_results,
		'a message points at the conversation to read it in, and a mention at the note itself',
		axismundi_note_conversation_uri( $ax_nn_message ) === (string) $ax_nn_snapshot['context']
			&& '' === (string) $ax_nn_mention_intents[0]['snapshot']['context']
	);
	/*
	 * Messages in one exchange collapse together, because ten messages in a conversation are one thing
	 * to look at. Mentions do not: each is its own event by somebody who chose to say it.
	 */
	ax_nn_assert(
		$ax_nn_results,
		'messages in one exchange group together while mentions stay separate',
		str_starts_with( (string) $ax_nn_message_intents[0]['grouping_key'], 'conversation:' )
			&& ! str_starts_with( (string) $ax_nn_mention_intents[0]['grouping_key'], 'conversation:' )
	);

	// -- who is named is not re-read from the body -----------------------------------------------------------

	/*
	 * A note says who it is addressed to, and the resolver reads that. Working it out from the text
	 * again would let an edit notify somebody about a note that was never addressed to them -- and
	 * would make "@bob" inside a code sample into a message.
	 */
	$ax_nn_text_only = ax_nn_note( $ax_nn_posts, $ax_nn_alice_user, (string) $ax_nn_bob->get_uri(), 'public', 'Ask @carol about it' );
	ax_nn_assert(
		$ax_nn_results,
		'the Actors told are the ones the author addressed, not the handles in the text',
		1 === count( ax_nn_intents( $ax_nn_text_only ) )
			&& (string) $ax_nn_bob->get_uri() === (string) ax_nn_intents( $ax_nn_text_only )[0]['recipient_actor_uri']
	);
	// Naming yourself in your own note is not being told about it.
	$ax_nn_self = ax_nn_note( $ax_nn_posts, $ax_nn_alice_user, (string) $ax_nn_alice->get_uri(), 'public', 'A note to self' );
	ax_nn_assert(
		$ax_nn_results,
		'and naming yourself in your own note tells nobody anything',
		array() === ax_nn_intents( $ax_nn_self )
	);

	// -- the boundary ------------------------------------------------------------------------------------------

	ax_nn_assert(
		$ax_nn_results,
		'this plugin answers what a note meant and keeps no inbox of its own',
		has_filter( 'axismundi_notification_intents', 'axismundi_note_resolve_notification_intents' )
			&& ! function_exists( 'axismundi_note_notifications_table' )
			&& 2 === count( AXISMUNDI_NOTE_NOTICE_KINDS )
	);
	/*
	 * And it says the work is done rather than leaving it to the end of the request -- on the envelope
	 * being saved, not on the object being published, those being two different moments. Publishing
	 * happens while the post is being written, before this plugin has recorded who the note names, so
	 * resolving there asks about a note that does not exist yet and correctly hears nothing.
	 */
	ax_nn_assert(
		$ax_nn_results,
		'and flushes once the note exists to compute intents from, not while it is still being written',
		has_action( 'axismundi_note_envelope_saved', 'axismundi_note_flush_notifications' )
			&& ! has_action( 'axismundi_op_object_publish_candidate', 'axismundi_note_flush_notifications' )
	);
} finally {
	global $wpdb;
	wp_set_current_user( 0 );
	foreach ( array_unique( $ax_nn_posts ) as $ax_nn_post_id ) {
		wp_delete_post( (int) $ax_nn_post_id, true );
	}
	if ( function_exists( 'axismundi_ntf_events_table' ) ) {
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fixture cleanup.
		$wpdb->query( $wpdb->prepare( 'DELETE FROM ' . axismundi_ntf_events_table() . ' WHERE kind LIKE %s', 'axismundi-note/%' ) );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fixture cleanup.
		$wpdb->query( 'DELETE d FROM ' . axismundi_ntf_deliveries_table() . ' d LEFT JOIN ' . axismundi_ntf_events_table() . ' e ON e.id = d.notification_id WHERE e.id IS NULL' );
	}
	foreach ( array_unique( $ax_nn_users ) as $ax_nn_user_id ) {
		wp_delete_user( (int) $ax_nn_user_id );
	}
}

$ax_nn_failures = count( array_filter( $ax_nn_results, static fn( bool $result ) : bool => ! $result ) );
// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI fixture output.
printf( "\n== %d checks, %d failed ==\n", count( $ax_nn_results ), $ax_nn_failures );
if ( class_exists( 'WP_CLI' ) ) {
	WP_CLI::halt( $ax_nn_failures > 0 ? 1 : 0 );
}
exit( $ax_nn_failures > 0 ? 1 : 0 );
