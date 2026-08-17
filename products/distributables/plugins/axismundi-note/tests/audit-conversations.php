<?php
/**
 * Messages, read as conversations (dev-only; dist-excluded).
 *
 * What is pinned here is a boundary rather than a feature. A direct note is a message and belongs to
 * a conversation; a public note that names somebody is a mention and belongs in the archive; and the
 * same index answers both, because neither list is a copy of anything.
 *
 * The other half is ownership. Whether a message has been read is this plugin's fact -- an inbox
 * that owned it would make "unread messages" mean "notifications not yet clicked" -- and the only
 * thing crossing the boundary is one call outward saying somebody has dealt with it.
 *
 * @package AxismundiNote
 */

defined( 'ABSPATH' ) || exit( 1 );

$ax_cv_results = array();
$ax_cv_users   = array();
$ax_cv_posts   = array();

/** @param bool[] $results Results. */
function ax_cv_assert( array &$results, string $label, bool $condition ) : void {
	$results[] = $condition;
	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI fixture output.
	printf( "[%s] %s\n", $condition ? 'PASS' : 'FAIL', $label );
}

/** An account with an activated, published Person Actor. */
function ax_cv_user( array &$users ) : int {
	$login = 'axcv' . strtolower( wp_generate_password( 8, false, false ) );
	$id    = (int) wp_insert_user(
		array( 'user_login' => $login, 'user_email' => $login . '@example.test', 'user_pass' => wp_generate_password(), 'role' => 'administrator' )
	);
	$users[] = $id;
	$actor   = axismundi_actors_ensure_for_user( $id );
	axismundi_actors_register_handle( $actor->get_identity_id(), $login );
	axismundi_actors_set_status( $actor->get_identity_id(), 'public' );
	return $id;
}

/** One note by somebody, mentioning somebody, at a visibility. */
function ax_cv_note( array &$posts, int $author, string $mentions, string $visibility, string $body, string $in_reply_to = '' ) : array {
	$post_id = (int) wp_insert_post(
		array( 'post_type' => AXISMUNDI_NOTE_POST_TYPE, 'post_title' => '', 'post_content' => $body, 'post_status' => 'publish', 'post_author' => $author )
	);
	$posts[] = $post_id;
	$saved   = axismundi_note_save(
		$post_id,
		array_filter(
			array(
				'visibility'         => $visibility,
				'mention_actor_uris' => array( $mentions ),
				'in_reply_to_uri'    => $in_reply_to,
			)
		)
	);
	if ( is_wp_error( $saved ) ) {
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI fixture output.
		printf( "  (fixture refused: %s)\n", $saved->get_error_message() );
		return array();
	}
	$envelope = axismundi_note_get( $post_id );
	return is_array( $envelope ) ? $envelope : array();
}

try {
	$ax_cv_alice_user = ax_cv_user( $ax_cv_users );
	$ax_cv_bob_user   = ax_cv_user( $ax_cv_users );
	$ax_cv_alice      = axismundi_actors_get_for_user( $ax_cv_alice_user );
	$ax_cv_bob        = axismundi_actors_get_for_user( $ax_cv_bob_user );

	// -- a message and a mention are different things -------------------------------------------------------

	wp_set_current_user( $ax_cv_alice_user );
	$ax_cv_message = ax_cv_note( $ax_cv_posts, $ax_cv_alice_user, (string) $ax_cv_bob->get_uri(), 'mentioned', 'Are you free on Thursday?' );
	$ax_cv_public  = ax_cv_note( $ax_cv_posts, $ax_cv_alice_user, (string) $ax_cv_bob->get_uri(), 'public', 'Congratulations to @bob' );
	ax_cv_assert(
		$ax_cv_results,
		'a note only its recipients can see is a message, and a public one naming them is not',
		array() !== $ax_cv_message && axismundi_note_is_direct( $ax_cv_message )
			&& array() !== $ax_cv_public && ! axismundi_note_is_direct( $ax_cv_public )
	);
	/*
	 * Both reached the same index, and the two lists divide them by the question being asked rather
	 * than by anything being copied anywhere.
	 */
	$ax_cv_conversations = axismundi_note_conversations_for( (string) $ax_cv_bob->get_uri() );
	$ax_cv_mentions      = axismundi_note_mentions_for( (string) $ax_cv_bob->get_uri() );
	ax_cv_assert(
		$ax_cv_results,
		'the message goes to conversations and the mention to the archive, off one index',
		1 === count( $ax_cv_conversations )
			&& 1 === count( $ax_cv_mentions )
			&& axismundi_note_object_uri( (string) $ax_cv_public['local_uuid'] ) === (string) $ax_cv_mentions[0]['source_object_uri']
	);

	// -- a conversation is a thread, not a new record --------------------------------------------------------

	/*
	 * A reply inherits the context of what it answers, which is what makes it part of the same
	 * exchange -- so the conversation is identified by something already stored rather than by a
	 * grouping key invented for it.
	 */
	wp_set_current_user( $ax_cv_bob_user );
	$ax_cv_reply = ax_cv_note(
		$ax_cv_posts,
		$ax_cv_bob_user,
		(string) $ax_cv_alice->get_uri(),
		'mentioned',
		'Thursday works.',
		axismundi_note_object_uri( (string) $ax_cv_message['local_uuid'] )
	);
	$ax_cv_context = axismundi_note_conversation_uri( $ax_cv_message );
	ax_cv_assert(
		$ax_cv_results,
		'a reply joins the exchange it answers rather than starting another',
		array() !== $ax_cv_reply
			&& axismundi_note_conversation_uri( $ax_cv_reply ) === $ax_cv_context
			&& 2 === count( axismundi_note_conversation( $ax_cv_context ) )
	);
	// One conversation for each of them, not two.
	ax_cv_assert(
		$ax_cv_results,
		'and each of them has one conversation rather than one per message',
		1 === count( axismundi_note_conversations_for( (string) $ax_cv_alice->get_uri() ) )
			&& 1 === count( axismundi_note_conversations_for( (string) $ax_cv_bob->get_uri() ) )
	);

	// -- a shared topic is not a shared conversation --------------------------------------------------------------

	/*
	 * The trap this rule exists for. `context_uri` is inherited from whatever a note answers, and that
	 * can be a Forum topic or an Article -- so two people replying privately to the same announcement
	 * carry the same context. Grouping by it would put them in a conversation with each other, and
	 * they would find out by reading each other's messages.
	 */
	$ax_cv_topic = home_url( '/?ax_topic=' . wp_generate_uuid4() );
	$ax_cv_carol_user = ax_cv_user( $ax_cv_users );
	$ax_cv_carol      = axismundi_actors_get_for_user( $ax_cv_carol_user );
	wp_set_current_user( $ax_cv_alice_user );
	$ax_cv_under_topic_one = ax_cv_note( $ax_cv_posts, $ax_cv_alice_user, (string) $ax_cv_carol->get_uri(), 'mentioned', 'A quiet word about the announcement.' );
	axismundi_note_save( $ax_cv_under_topic_one['post_id'], array( 'context_uri' => $ax_cv_topic ) );
	$ax_cv_under_topic_one = axismundi_note_get( (int) $ax_cv_under_topic_one['post_id'] );
	wp_set_current_user( $ax_cv_bob_user );
	$ax_cv_under_topic_two = ax_cv_note( $ax_cv_posts, $ax_cv_bob_user, (string) $ax_cv_carol->get_uri(), 'mentioned', 'Also about the announcement.' );
	axismundi_note_save( $ax_cv_under_topic_two['post_id'], array( 'context_uri' => $ax_cv_topic ) );
	$ax_cv_under_topic_two = axismundi_note_get( (int) $ax_cv_under_topic_two['post_id'] );
	ax_cv_assert(
		$ax_cv_results,
		'two people writing privately under one topic are not put in a conversation together',
		axismundi_note_conversation_uri( $ax_cv_under_topic_one ) !== axismundi_note_conversation_uri( $ax_cv_under_topic_two )
			&& 2 === count( axismundi_note_conversations_for( (string) $ax_cv_carol->get_uri() ) )
	);
	// A context that does name a private exchange is still honoured, which is what it is for.
	ax_cv_assert(
		$ax_cv_results,
		'while a context naming a message of its own is honoured, that being somebody saying "this exchange"',
		axismundi_note_context_is_private_exchange( axismundi_note_object_uri( (string) $ax_cv_message['local_uuid'] ) )
			&& ! axismundi_note_context_is_private_exchange( $ax_cv_topic )
	);

	// -- who has read it ---------------------------------------------------------------------------------------

	$ax_cv_for_alice = axismundi_note_conversations_for( (string) $ax_cv_alice->get_uri() );
	ax_cv_assert(
		$ax_cv_results,
		'a message nobody has opened is unread for the person it was sent to',
		true === $ax_cv_for_alice[0]['unread']
	);
	axismundi_note_mark_conversation_read( $ax_cv_context, $ax_cv_alice_user );
	ax_cv_assert(
		$ax_cv_results,
		'opening it marks it read for them, and for nobody else',
		false === axismundi_note_conversations_for( (string) $ax_cv_alice->get_uri() )[0]['unread']
			&& true === axismundi_note_conversations_for( (string) $ax_cv_bob->get_uri() )[0]['unread']
	);
	/*
	 * And a later message makes it unread again. Read state is a point in the exchange rather than a
	 * flag, or answering somebody would leave their reply looking already seen.
	 */
	wp_set_current_user( $ax_cv_bob_user );
	ax_cv_note(
		$ax_cv_posts,
		$ax_cv_bob_user,
		(string) $ax_cv_alice->get_uri(),
		'mentioned',
		'Actually, could we say six?',
		axismundi_note_object_uri( (string) $ax_cv_message['local_uuid'] )
	);
	ax_cv_assert(
		$ax_cv_results,
		'while a new message in it is unread again, read being a point rather than a flag',
		true === axismundi_note_conversations_for( (string) $ax_cv_alice->get_uri() )[0]['unread']
	);

	// -- putting one away is a view, not a deletion --------------------------------------------------------------

	axismundi_note_hide_conversation( $ax_cv_context, $ax_cv_alice_user );
	ax_cv_assert(
		$ax_cv_results,
		'hiding a conversation takes it out of one person\'s list and out of nobody else\'s',
		array() === axismundi_note_conversations_for( (string) $ax_cv_alice->get_uri() )
			&& 1 === count( axismundi_note_conversations_for( (string) $ax_cv_bob->get_uri() ) )
	);
	// The messages are untouched: the exchange happened, and the other person still has all of it.
	ax_cv_assert(
		$ax_cv_results,
		'and the messages themselves are all still there',
		3 === count( axismundi_note_conversation( $ax_cv_context ) )
	);
	axismundi_note_hide_conversation( $ax_cv_context, $ax_cv_alice_user, false );
	ax_cv_assert(
		$ax_cv_results,
		'and it comes back when they say so',
		1 === count( axismundi_note_conversations_for( (string) $ax_cv_alice->get_uri() ) )
	);

	// -- the boundary ---------------------------------------------------------------------------------------------

	/*
	 * The seam points one way. This plugin tells whatever keeps an inbox that the messages have been
	 * dealt with; it does not write there, and nothing there writes here. With no notification plugin
	 * at all the conversation is still read, because reading a message is a fact about the message.
	 */
	ax_cv_assert(
		$ax_cv_results,
		'reading a conversation tells the inbox rather than reaching into it',
		function_exists( 'axismundi_note_tell_notifications_conversation_read' )
			&& ! function_exists( 'axismundi_note_notification_delivery_table' )
	);
	// And nothing here re-parses a note to work out who it was for.
	ax_cv_assert(
		$ax_cv_results,
		'and who a message was for is read from what the author settled, never parsed again',
		! str_contains( (string) file_get_contents( dirname( __DIR__ ) . '/includes/conversations.php' ), 'preg_match' )
			&& str_contains( (string) file_get_contents( dirname( __DIR__ ) . '/includes/conversations.php' ), 'axismundi_op_object_mentions_for_actor' )
	);
} finally {
	global $wpdb;
	wp_set_current_user( 0 );
	foreach ( array_unique( $ax_cv_posts ) as $ax_cv_post_id ) {
		wp_delete_post( (int) $ax_cv_post_id, true );
	}
	foreach ( array_unique( $ax_cv_users ) as $ax_cv_user_id ) {
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fixture cleanup.
		$wpdb->delete( axismundi_note_conversation_state_table(), array( 'local_user_id' => (int) $ax_cv_user_id ), array( '%d' ) );
		wp_delete_user( (int) $ax_cv_user_id );
	}
}

$ax_cv_failures = count( array_filter( $ax_cv_results, static fn( bool $result ) : bool => ! $result ) );
// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI fixture output.
printf( "\n== %d checks, %d failed ==\n", count( $ax_cv_results ), $ax_cv_failures );
if ( class_exists( 'WP_CLI' ) ) {
	WP_CLI::halt( $ax_cv_failures > 0 ? 1 : 0 );
}
exit( $ax_cv_failures > 0 ? 1 : 0 );
