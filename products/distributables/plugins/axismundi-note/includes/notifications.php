<?php
/**
 * What a note meant to the people it named.
 *
 * Two kinds from one Activity, told apart by the thing that already decides everything else about a
 * note: who may see it. A note anybody can read that names somebody is a mention -- a social act,
 * and a line in a list. A note only its recipients can read is a message, and its home is the
 * conversation; the notification is a nudge towards it and not a copy of it.
 *
 * Nothing here parses a body. Who a note names was settled by its author when it was written, and
 * this reads that decision -- so a mention typed into a post afterwards cannot notify somebody about
 * a note that was never addressed to them.
 *
 * And nothing here carries what was said. The snapshot names the author and the conversation, and
 * stops: a direct note exists in one place, and a notification that copied it would be a second
 * place to read a private message from and a second place to have to delete it.
 *
 * @package AxismundiNote
 */

defined( 'ABSPATH' ) || exit;

/** The kinds a note produces, and the attention each asks for. */
const AXISMUNDI_NOTE_NOTICE_KINDS = array(
	'axismundi-note/mentioned'            => 'immediate',
	'axismundi-note/direct-note-received' => 'immediate',
);

/**
 * Tell Notifications what a note can produce.
 *
 * @return void
 */
function axismundi_note_register_notice_kinds() : void {
	if ( ! function_exists( 'axismundi_ntf_register_kind' ) ) {
		return;
	}
	foreach ( AXISMUNDI_NOTE_NOTICE_KINDS as $kind => $urgency ) {
		axismundi_ntf_register_kind( $kind, array( 'category' => 'conversation', 'urgency' => $urgency ) );
	}
}
add_action( 'axismundi_notification_register_kinds', 'axismundi_note_register_notice_kinds' );

/**
 * Answer for the notes this plugin wrote.
 *
 * @param array<int,array<string,mixed>> $intents  Intents so far.
 * @param Axismundi_Activity             $activity Committed Activity.
 * @return array<int,array<string,mixed>>
 */
function axismundi_note_resolve_notification_intents( array $intents, Axismundi_Activity $activity ) : array {
	if ( 'Create' !== $activity->get_type() ) {
		return $intents;
	}
	$note = axismundi_note_get_by_uri( (string) $activity->get_object_uri() );
	if ( ! is_array( $note ) ) {
		return $intents;
	}
	$recipients = axismundi_note_notice_recipients( $note );
	if ( array() === $recipients ) {
		return $intents;
	}
	/*
	 * The one decision this resolver makes, and it is made from the note's own visibility rather than
	 * from anything about the notification: a message and a mention are different things to receive,
	 * and somebody scanning a list needs them to look different.
	 */
	$direct   = axismundi_note_is_direct( $note );
	$kind     = $direct ? 'axismundi-note/direct-note-received' : 'axismundi-note/mentioned';
	$uri      = axismundi_note_object_uri( (string) $note['local_uuid'] );
	$snapshot = array(
		'note'   => $uri,
		'author' => (string) $note['actor_uri'],
		// Where to go to read it, which for a message is the exchange rather than the note. Nothing of
		// what was said travels with this.
		'context' => $direct ? axismundi_note_conversation_uri( $note ) : '',
	);
	foreach ( $recipients as $recipient ) {
		$intents[] = array(
			'kind'                     => $kind,
			'recipient_actor_uri'      => $recipient,
			'actor_uri'                => (string) $note['actor_uri'],
			'object_uri'               => $uri,
			// Messages in one exchange collapse together; mentions do not, each being its own event.
			'grouping_key'             => $direct ? 'conversation:' . $snapshot['context'] : $kind . ':' . $uri,
			'initiating_local_user_id' => get_current_user_id(),
			'snapshot'                 => $snapshot,
		);
	}
	return $intents;
}
add_filter( 'axismundi_notification_intents', 'axismundi_note_resolve_notification_intents', 10, 2 );

/**
 * The Actors one note named.
 *
 * Read from what the author settled, and from the projection index when the envelope predates it.
 * Never from the body: a note says who it is addressed to, and re-deriving that later would let an
 * edit change who was told about something written yesterday.
 *
 * @param array<string,mixed> $note Note envelope.
 * @return string[]
 */
function axismundi_note_notice_recipients( array $note ) : array {
	$mentions = json_decode( (string) ( $note['mention_actor_uris_json'] ?? '[]' ), true );
	if ( ! is_array( $mentions ) || array() === $mentions ) {
		return array();
	}
	$out = array();
	foreach ( $mentions as $mention ) {
		$uri = trim( (string) $mention );
		if ( '' !== $uri && $uri !== (string) $note['actor_uri'] ) {
			// Naming yourself in your own note is not being told about it.
			$out[] = $uri;
		}
	}
	return array_values( array_unique( $out ) );
}

/**
 * Resolve what a note meant, once the note exists.
 *
 * On the envelope being saved rather than on the object being published, because the two are not the
 * same moment: the publish candidate fires while the post is being written, before this plugin has
 * recorded who the note is addressed to and how visible it is. Flushing there asked the resolver a
 * question about a note that did not exist yet, and got the only honest answer -- nothing.
 *
 * Explicitly, rather than leaving it to the end of the request, which is the contract every product
 * here follows: a fatal or an `exit` in between would leave the Activity recorded and nobody told.
 *
 * @param array<string,mixed> $envelope Saved envelope.
 * @param WP_Post             $post     The note.
 * @return void
 */
function axismundi_note_flush_notifications( array $envelope, WP_Post $post ) : void {
	if ( ! function_exists( 'axismundi_notification_flush' ) ) {
		return;
	}
	axismundi_notification_flush();
}
add_action( 'axismundi_note_envelope_saved', 'axismundi_note_flush_notifications', 20, 2 );
