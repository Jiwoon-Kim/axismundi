<?php
/**
 * Direct notes, read as conversations.
 *
 * A conversation is not a new thing to store. It is the notes of one thread whose visibility is
 * `mentioned` -- what Mastodon calls a direct post and Misskey calls specified -- and the thread is
 * already identified: `context_uri` is inherited from the note being replied to, which is what makes
 * a reply part of the same exchange rather than a new one. So this is a way of asking, not a place
 * to keep anything.
 *
 * What it does own is the one fact nothing else holds: whether the person reading has read it. That
 * cannot live in Notifications, because dismissing a badge is not reading a message and reading a
 * message on a phone should not require dismissing anything on a laptop. The two are different
 * facts about different things, and an inbox that owned this would make "unread messages" mean
 * "notifications not yet clicked" -- wrong in both directions the moment somebody reads elsewhere.
 *
 * The direction of the seam matters. This does not reach into Notifications' tables; it tells
 * Notifications that somebody has dealt with something, and Notifications decides what that means
 * for its own row.
 *
 * And nothing here parses anything. Who a note is addressed to and how visible it is were settled
 * when the note was written; this reads those decisions and never re-derives them, which is why a
 * mention edited into a post later cannot silently change who a conversation belongs to.
 *
 * @package AxismundiNote
 */

defined( 'ABSPATH' ) || exit;

/** The visibility that makes a note a message rather than a post. */
const AXISMUNDI_NOTE_DIRECT_VISIBILITY = 'mentioned';

/** @return string Per-person conversation state table. */
function axismundi_note_conversation_state_table() : string {
	global $wpdb;
	return $wpdb->prefix . 'ax_note_conversation_state';
}

/**
 * Install the one table a conversation needs.
 *
 * Only view state: how far somebody has read, and whether they have put it away. No
 * copy of the messages, no participant list, no thread -- all of that is already recorded, and a
 * second copy would be a second answer to a question that already has one.
 *
 * How far, rather than when. Two notes written in the same second are ordered by nothing a timestamp
 * can express, so reading and being replied to within one second would leave a message looking seen
 * that nobody saw -- a watermark over the notes themselves cannot have that problem.
 *
 * Nothing here deletes a note. Hiding is a view somebody chose; the exchange still happened, and the
 * other people in it still have it.
 *
 * @return bool
 */
function axismundi_note_install_conversation_state() : bool {
	global $wpdb;
	require_once ABSPATH . 'wp-admin/includes/upgrade.php';
	$charset = $wpdb->get_charset_collate();
	$table   = axismundi_note_conversation_state_table();
	dbDelta(
		"CREATE TABLE {$table} (
			id bigint(20) unsigned NOT NULL auto_increment,
			context_uri text NOT NULL,
			context_uri_hash char(64) NOT NULL default '',
			local_user_id bigint(20) unsigned NOT NULL,
			read_note_id bigint(20) unsigned NOT NULL default 0,
			read_at datetime NULL,
			hidden_at datetime NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY one_each (context_uri_hash,local_user_id),
			KEY reader (local_user_id,hidden_at)
		) ENGINE=InnoDB {$charset};"
	);
	return true;
}

/**
 * Whether one note is a message rather than a post.
 *
 * @param array<string,mixed> $envelope Note envelope.
 * @return bool
 */
function axismundi_note_is_direct( array $envelope ) : bool {
	return AXISMUNDI_NOTE_DIRECT_VISIBILITY === (string) ( $envelope['visibility'] ?? '' );
}

/**
 * The conversation one note belongs to.
 *
 * Its thread's context, which a reply inherits from what it answers. A note that begins an exchange
 * has none yet, and is its own context -- the exchange is identified by where it started.
 *
 * @param array<string,mixed> $envelope Note envelope.
 * @return string Context URI, or '' when this is not a message.
 */
function axismundi_note_conversation_uri( array $envelope ) : string {
	if ( ! axismundi_note_is_direct( $envelope ) ) {
		return '';
	}
	/*
	 * A context is honoured only when it names a private exchange, and this is the whole of the
	 * reason. `context_uri` is inherited from whatever a note replies to, and that can be a Forum
	 * topic or an Article -- so a direct reply under a public topic carries the topic's context, and
	 * grouping by it would put every person's private reply to that topic into one conversation with
	 * each other. Two people answering the same announcement privately are not in a conversation
	 * together, and finding out otherwise by reading each other's messages is the worst way to learn
	 * it.
	 *
	 * So a context counts when it is itself a direct note -- somebody saying "this exchange" -- and
	 * anything else falls back to the thread root, which is always right and never merges strangers.
	 */
	$context = trim( (string) ( $envelope['context_uri'] ?? '' ) );
	if ( '' !== $context && axismundi_note_context_is_private_exchange( $context ) ) {
		return $context;
	}
	/*
	 * Otherwise the exchange is identified by where it began, walked back through what each message
	 * answers. Read-side only: nothing is written onto the notes, because a context invented
	 * afterwards would be a claim about an object other servers already hold.
	 */
	return axismundi_note_thread_root_uri( $envelope );
}

/**
 * Whether a context URI names a private exchange rather than something public it hangs under.
 *
 * Answered from the note the context points at, and false for anything this site cannot read. A
 * remote context is most often a topic or an article -- and being wrong in that direction merely
 * splits a conversation, while being wrong in the other merges two.
 *
 * @param string $context_uri Context.
 * @return bool
 */
function axismundi_note_context_is_private_exchange( string $context_uri ) : bool {
	$note = axismundi_note_get_by_uri( trim( $context_uri ) );
	return is_array( $note ) && axismundi_note_is_direct( $note );
}

/**
 * The note an exchange began with.
 *
 * @param array<string,mixed> $envelope Note envelope.
 * @return string
 */
function axismundi_note_thread_root_uri( array $envelope ) : string {
	$uri    = axismundi_note_object_uri( (string) ( $envelope['local_uuid'] ?? '' ) );
	$parent = trim( (string) ( $envelope['in_reply_to_uri'] ?? '' ) );
	// Bounded, because a cycle in a reply chain is a corrupt row rather than an impossibility, and a
	// walk that trusted the data would hang the page rather than showing a slightly odd thread.
	for ( $step = 0; $step < 50 && '' !== $parent; $step++ ) {
		$above = axismundi_note_get_by_uri( $parent );
		if ( ! is_array( $above ) ) {
			// The chain leaves this site. That note is where the exchange begins as far as we can see,
			// and naming it is better than pretending the reply started one.
			return $parent;
		}
		$context = trim( (string) ( $above['context_uri'] ?? '' ) );
		if ( '' !== $context ) {
			return $context;
		}
		$uri    = axismundi_note_object_uri( (string) $above['local_uuid'] );
		$parent = trim( (string) ( $above['in_reply_to_uri'] ?? '' ) );
	}
	return $uri;
}

/**
 * The conversations one Actor is part of.
 *
 * Read from the mention index, which already answers "which objects name this Actor" for every
 * product -- so a message reaches somebody's conversations for exactly the reason it reaches their
 * mentions, and neither list is a copy of the other.
 *
 * The author's own messages come too. A conversation somebody started is one they are in.
 *
 * @param string $actor_uri Participant.
 * @param int    $limit     Maximum conversations.
 * @return array<int,array<string,mixed>> Newest first: context, last note, unread flag.
 */
function axismundi_note_conversations_for( string $actor_uri, int $limit = 50 ) : array {
	$actor_uri = trim( $actor_uri );
	if ( '' === $actor_uri || ! function_exists( 'axismundi_op_object_mentions_for_actor' ) ) {
		return array();
	}
	$user_id = axismundi_note_conversation_reader( $actor_uri );
	$threads = array();
	foreach ( axismundi_op_object_mentions_for_actor( $actor_uri ) as $mention ) {
		$envelope = axismundi_note_get_by_uri( (string) $mention['source_object_uri'] );
		if ( ! is_array( $envelope ) || ! axismundi_note_is_direct( $envelope ) ) {
			// A public post that named them is a mention, and belongs in the mentions archive rather
			// than here. Same index, different question.
			continue;
		}
		$context = axismundi_note_conversation_uri( $envelope );
		if ( '' === $context ) {
			continue;
		}
		$at = (string) ( $envelope['created_at'] ?? $mention['updated_at'] );
		$id = (int) ( $envelope['id'] ?? 0 );
		if ( ! isset( $threads[ $context ] ) || $id > (int) $threads[ $context ]['last_note_id'] ) {
			$threads[ $context ] = array(
				'context'      => $context,
				'at'           => $at,
				'last_note'    => (string) $mention['source_object_uri'],
				'last_note_id' => $id,
			);
		}
	}
	$state = axismundi_note_conversation_states( $user_id );
	$out   = array();
	foreach ( $threads as $context => $thread ) {
		$row = $state[ hash( 'sha256', $context ) ] ?? null;
		if ( is_array( $row ) && null !== $row['hidden_at'] ) {
			continue;
		}
		// Compared as positions in the exchange, so a reply written in the same second as somebody read
		// it still counts as new.
		$thread['unread'] = ! is_array( $row ) || (int) $row['read_note_id'] < (int) $thread['last_note_id'];
		$out[]            = $thread;
	}
	usort( $out, static fn( array $a, array $b ) : int => strcmp( (string) $b['at'], (string) $a['at'] ) );
	return array_slice( $out, 0, max( 1, $limit ) );
}

/**
 * One person's state across their conversations, keyed by context hash.
 *
 * @param int $user_id Reader.
 * @return array<string,array<string,mixed>>
 */
function axismundi_note_conversation_states( int $user_id ) : array {
	global $wpdb;
	if ( $user_id <= 0 ) {
		return array();
	}
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- keyed lookup in this plugin's own table.
	$rows = (array) $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM ' . axismundi_note_conversation_state_table() . ' WHERE local_user_id = %d', $user_id ), ARRAY_A );
	$out  = array();
	foreach ( $rows as $row ) {
		$out[ (string) $row['context_uri_hash'] ] = $row;
	}
	return $out;
}

/**
 * The person who reads for one Actor.
 *
 * A conversation is read by a person, and an Actor is not one. For a Person Actor that is its own
 * user; for anything managed the question is which manager is asking, and the caller knows that
 * better than this does -- so managed Actors resolve to nobody here and the caller passes the reader
 * it means.
 *
 * @param string $actor_uri Actor.
 * @return int
 */
function axismundi_note_conversation_reader( string $actor_uri ) : int {
	if ( ! function_exists( 'axismundi_actors_get_by_uri' ) ) {
		return 0;
	}
	$actor = axismundi_actors_get_by_uri( $actor_uri );
	if ( ! $actor instanceof Axismundi_Actor || ! $actor->is_local() || $actor->is_managed() ) {
		return 0;
	}
	return (int) $actor->get_local_user_id();
}

/**
 * The messages in one conversation, oldest first.
 *
 * @param string $context_uri Conversation.
 * @param int    $limit       Maximum notes.
 * @return array<int,array<string,mixed>> Note envelopes.
 */
function axismundi_note_conversation( string $context_uri, int $limit = 200 ) : array {
	global $wpdb;
	$context_uri = trim( $context_uri );
	if ( '' === $context_uri ) {
		return array();
	}
	$table = axismundi_note_table();
	/*
	 * Walked down from the root rather than looked up by a column, because the column is usually
	 * empty: a note that starts an exchange has no context, so its replies inherit none, and the only
	 * thing tying the messages together is what each one answers.
	 *
	 * Bounded on both axes -- depth and total -- because this walks data that arrives from other
	 * servers, and a loop in a reply chain is a corrupt row rather than an impossibility.
	 */
	$frontier = array( $context_uri );
	$rows     = array();
	$seen     = array();
	for ( $depth = 0; $depth < 20 && array() !== $frontier && count( $rows ) < $limit; $depth++ ) {
		$uuids = array();
		foreach ( $frontier as $uri ) {
			$uuid = axismundi_note_local_uuid_from_uri( (string) $uri );
			if ( null !== $uuid && '' !== $uuid && ! isset( $seen[ $uuid ] ) ) {
				$uuids[]        = $uuid;
				$seen[ $uuid ]  = true;
			}
		}
		$hashes = array_map( static fn( string $uri ) : string => hash( 'sha256', $uri ), array_map( 'strval', $frontier ) );
		$found  = array();
		if ( array() !== $uuids ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- keyed lookup in this plugin's own table.
			$found = (array) $wpdb->get_results(
				$wpdb->prepare(
					"SELECT * FROM {$table} WHERE local_uuid IN ( " . implode( ', ', array_fill( 0, count( $uuids ), '%s' ) ) . " ) AND visibility = %s AND object_status = 'active'",
					array_merge( $uuids, array( AXISMUNDI_NOTE_DIRECT_VISIBILITY ) )
				),
				ARRAY_A
			);
		}
		$rows = array_merge( $rows, $found );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- keyed lookup in this plugin's own table.
		$children = (array) $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE in_reply_to_uri_hash IN ( " . implode( ', ', array_fill( 0, count( $hashes ), '%s' ) ) . " ) AND visibility = %s AND object_status = 'active'",
				array_merge( $hashes, array( AXISMUNDI_NOTE_DIRECT_VISIBILITY ) )
			),
			ARRAY_A
		);
		$frontier = array();
		foreach ( $children as $child ) {
			if ( isset( $seen[ (string) $child['local_uuid'] ] ) ) {
				continue;
			}
			$frontier[] = axismundi_note_object_uri( (string) $child['local_uuid'] );
		}
	}
	usort(
		$rows,
		static fn( array $a, array $b ) : int => strcmp( (string) $a['created_at'], (string) $b['created_at'] ) ?: ( (int) $a['id'] <=> (int) $b['id'] )
	);
	return array_slice( $rows, 0, max( 1, $limit ) );
}

/**
 * Say somebody has read a conversation.
 *
 * Two things happen, in this order and for different reasons. The conversation records that they
 * have read it, which is this plugin's fact and the one an unread count is drawn from. Then
 * Notifications is told that the messages have been dealt with, and decides for itself what that
 * means for its own rows -- because a notification about something already read is noise, and
 * because neither plugin has any business writing to the other's tables.
 *
 * @param string $context_uri Conversation.
 * @param int    $user_id     Reader.
 * @return bool
 */
function axismundi_note_mark_conversation_read( string $context_uri, int $user_id ) : bool {
	global $wpdb;
	$context_uri = trim( $context_uri );
	if ( '' === $context_uri || $user_id <= 0 ) {
		return false;
	}
	$now = current_time( 'mysql', true );
	// How far the exchange has got, which is what "read" means here -- and it is read from the notes
	// rather than from the clock, so a message written in the same second is still ahead of it.
	$watermark = 0;
	foreach ( axismundi_note_conversation( $context_uri ) as $note ) {
		$watermark = max( $watermark, (int) $note['id'] );
	}
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- this plugin's own table.
	$wpdb->query(
		$wpdb->prepare(
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name from this plugin.
			'INSERT INTO ' . axismundi_note_conversation_state_table() . ' ( context_uri, context_uri_hash, local_user_id, read_note_id, read_at, updated_at )'
			. ' VALUES ( %s, %s, %d, %d, %s, %s ) ON DUPLICATE KEY UPDATE read_note_id = VALUES( read_note_id ), read_at = VALUES( read_at ), updated_at = VALUES( updated_at )',
			$context_uri,
			hash( 'sha256', $context_uri ),
			$user_id,
			$watermark,
			$now,
			$now
		)
	);
	axismundi_note_tell_notifications_conversation_read( $context_uri, $user_id );
	return true;
}

/**
 * Tell whatever keeps an inbox that these messages have been dealt with.
 *
 * One call outward, and nothing assumed about what happens to it. With no notification plugin
 * installed the conversation is still read; that is the right failure, because reading a message is
 * a fact about the message.
 *
 * @param string $context_uri Conversation.
 * @param int    $user_id     Reader.
 * @return void
 */
function axismundi_note_tell_notifications_conversation_read( string $context_uri, int $user_id ) : void {
	if ( ! function_exists( 'axismundi_ntf_mark_read_by_source' ) ) {
		return;
	}
	foreach ( axismundi_note_conversation( $context_uri ) as $note ) {
		/*
		 * The Activity that announced the note, asked of the ledger rather than kept here. A column
		 * beside the note would be a second record of something the ledger already owns, and it would
		 * be the copy that goes stale.
		 */
		foreach ( axismundi_note_create_activity_uris( axismundi_note_object_uri( (string) $note['local_uuid'] ) ) as $activity ) {
			axismundi_ntf_mark_read_by_source( $activity, $user_id );
		}
	}
}

/**
 * The `Create` activities that announced one note.
 *
 * @param string $note_uri Note object URI.
 * @return string[]
 */
function axismundi_note_create_activity_uris( string $note_uri ) : array {
	if ( '' === trim( $note_uri ) || ! function_exists( 'axismundi_act_get_by_object' ) ) {
		return array();
	}
	$out = array();
	foreach ( axismundi_act_get_by_object( $note_uri ) as $activity ) {
		if ( 'Create' === $activity->get_type() ) {
			$out[] = (string) $activity->get_uri();
		}
	}
	return $out;
}

/**
 * Put a conversation away, or bring it back.
 *
 * A view somebody chose and nothing more. The notes stay, the other participants still have the
 * exchange, and a new message in it is a new fact this does not silence -- what is hidden is a row
 * in one person's list.
 *
 * @param string $context_uri Conversation.
 * @param int    $user_id     Reader.
 * @param bool   $hidden      Whether to hide it.
 * @return bool
 */
function axismundi_note_hide_conversation( string $context_uri, int $user_id, bool $hidden = true ) : bool {
	global $wpdb;
	$context_uri = trim( $context_uri );
	if ( '' === $context_uri || $user_id <= 0 ) {
		return false;
	}
	$now = current_time( 'mysql', true );
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- this plugin's own table.
	$wpdb->query(
		$wpdb->prepare(
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name from this plugin.
			'INSERT INTO ' . axismundi_note_conversation_state_table() . ' ( context_uri, context_uri_hash, local_user_id, hidden_at, updated_at )'
			. ' VALUES ( %s, %s, %d, ' . ( $hidden ? '%s' : 'NULL' ) . ', %s ) ON DUPLICATE KEY UPDATE hidden_at = VALUES( hidden_at ), updated_at = VALUES( updated_at )',
			array_values(
				array_filter(
					array( $context_uri, hash( 'sha256', $context_uri ), $user_id, $hidden ? $now : null, $now ),
					static fn( $value ) : bool => null !== $value
				)
			)
		)
	);
	return true;
}

/**
 * The mentions somebody has received that are not messages.
 *
 * The archive, read from the same index and answering the other question: who has named me in
 * something anybody can see. Nothing is copied to build it, so clearing a notification leaves it
 * untouched -- a notification is about attention, and this is about what was said.
 *
 * @param string $actor_uri Mentioned Actor.
 * @param int    $limit     Maximum rows.
 * @return array<int,array<string,mixed>>
 */
function axismundi_note_mentions_for( string $actor_uri, int $limit = 50 ) : array {
	if ( ! function_exists( 'axismundi_op_object_mentions_for_actor' ) ) {
		return array();
	}
	$out = array();
	foreach ( axismundi_op_object_mentions_for_actor( $actor_uri ) as $mention ) {
		$envelope = axismundi_note_get_by_uri( (string) $mention['source_object_uri'] );
		// A direct note is a message and belongs to a conversation. Everything else -- including a
		// mention in something that is not a Note at all -- is an ordinary mention.
		if ( is_array( $envelope ) && axismundi_note_is_direct( $envelope ) ) {
			continue;
		}
		$out[] = $mention;
		if ( count( $out ) >= max( 1, $limit ) ) {
			break;
		}
	}
	return $out;
}
