<?php
/**
 * Turning one intent into one stored fact.
 *
 * An intent is what a resolver answered: this Activity means this kind of thing to this Actor. What
 * is stored is that, plus the snapshot the resolver saw, and nothing that would let anybody
 * reconstruct the audience later -- the audience was a fact about a moment, and a second computation
 * would answer for a different one.
 *
 * @package AxismundiNotifications
 */

defined( 'ABSPATH' ) || exit;

/**
 * The local Actor an intent is addressed to, or 0.
 *
 * Local only, and this is the whole of why. A notification is something somebody logs in here to
 * read; a remote Actor reads theirs on their own server, from their own inbox, and a row here
 * addressed to them would be an inbox entry with no reader. The remote half of the same act is the
 * Activity that was delivered to them, which is the ledger's business and already done.
 *
 * @param string $actor_uri Recipient Actor URI.
 * @return int Identity id, or 0 when this is not a local Actor.
 */
function axismundi_ntf_local_recipient( string $actor_uri ) : int {
	$actor_uri = trim( $actor_uri );
	if ( '' === $actor_uri || ! axismundi_ntf_has_actors() ) {
		return 0;
	}
	$actor = axismundi_actors_get_by_uri( $actor_uri );
	if ( ! $actor instanceof Axismundi_Actor || ! $actor->is_local() ) {
		return 0;
	}
	return (int) $actor->get_identity_id();
}

/**
 * Store one resolved intent.
 *
 * @param array<string,mixed> $intent   Resolver output.
 * @param Axismundi_Activity  $activity The Activity it projects.
 * @return int|WP_Error Event id, or the id of the one already there.
 */
function axismundi_ntf_record_event( array $intent, Axismundi_Activity $activity ) {
	global $wpdb;
	if ( ! axismundi_ntf_ready() ) {
		return new WP_Error( 'ax_ntf_unavailable', __( 'Notifications is not ready to record anything.', 'axismundi-notifications' ) );
	}
	$kind = trim( (string) ( $intent['kind'] ?? '' ) );
	$registered = axismundi_ntf_kind( $kind );
	if ( null === $registered ) {
		/*
		 * Refused rather than stored under a kind nothing describes. An entry whose kind no settings
		 * screen knows about is one nobody can turn off, and it would arrive in an inbox as a row with
		 * no name and no way to stop it.
		 */
		return new WP_Error( 'ax_ntf_kind', __( 'That kind of notification has not been registered.', 'axismundi-notifications' ) );
	}
	$recipient_uri = trim( (string) ( $intent['recipient_actor_uri'] ?? '' ) );
	$recipient_id  = axismundi_ntf_local_recipient( $recipient_uri );
	if ( $recipient_id <= 0 ) {
		return new WP_Error( 'ax_ntf_recipient', __( 'Notifications are addressed to local Actors.', 'axismundi-notifications' ) );
	}
	$source = (string) $activity->get_uri();
	if ( '' === $source ) {
		// The contract, enforced rather than assumed: every entry projects a recorded Activity.
		return new WP_Error( 'ax_ntf_source', __( 'A notification needs the Activity it projects.', 'axismundi-notifications' ) );
	}
	$actor_uri = trim( (string) ( $intent['actor_uri'] ?? $activity->get_actor_uri() ) );
	if ( $actor_uri === $recipient_uri ) {
		/*
		 * Nobody hears about their own act. Told once here rather than in every resolver, because a
		 * product that forgot would teach people to ignore the badge and nothing would catch it.
		 *
		 * Note what this is not: an act by another manager of an Actor you also manage is not your own
		 * act. The Actor was told, and you are one of the people who reads what that Actor is told.
		 */
		return new WP_Error( 'ax_ntf_self', __( 'An Actor is not notified of their own act.', 'axismundi-notifications' ) );
	}

	$dedupe = hash( 'sha256', $source . "\n" . $recipient_id . "\n" . $kind );
	$now    = current_time( 'mysql', true );
	$row    = array(
		'kind'                => $kind,
		'category'            => (string) $registered['category'],
		'recipient_actor_id'  => $recipient_id,
		'recipient_actor_uri' => $recipient_uri,
		'actor_uri'           => $actor_uri,
		'object_uri'          => trim( (string) ( $intent['object_uri'] ?? (string) $activity->get_object_uri() ) ),
		'source_activity_uri' => $source,
		'source_activity_hash' => hash( 'sha256', $source ),
		'dedupe_hash'         => $dedupe,
		'grouping_key'        => substr( (string) ( $intent['grouping_key'] ?? '' ), 0, 191 ),
		'snapshot'            => (string) wp_json_encode( (array) ( $intent['snapshot'] ?? array() ) ),
		'state'               => 'accepted',
		// The Activity's own time, not this moment. A notification is dated by what happened, so a
		// redelivered inbox POST an hour later does not move an act into the present.
		'occurred_at'         => (string) ( $intent['occurred_at'] ?? $now ),
		'created_at'          => $now,
	);
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- this plugin's own table.
	$inserted = $wpdb->query(
		$wpdb->prepare(
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name from this plugin.
			'INSERT IGNORE INTO ' . axismundi_ntf_events_table() . ' ( kind, category, recipient_actor_id, recipient_actor_uri, actor_uri, object_uri, source_activity_uri, source_activity_hash, dedupe_hash, grouping_key, snapshot, state, occurred_at, created_at )'
			. ' VALUES ( %s, %s, %d, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s )',
			$row['kind'],
			$row['category'],
			$row['recipient_actor_id'],
			$row['recipient_actor_uri'],
			$row['actor_uri'],
			$row['object_uri'],
			$row['source_activity_uri'],
			$row['source_activity_hash'],
			$row['dedupe_hash'],
			$row['grouping_key'],
			$row['snapshot'],
			$row['state'],
			$row['occurred_at'],
			$row['created_at']
		)
	);
	if ( false === $inserted ) {
		return new WP_Error( 'ax_ntf_write', __( 'The notification could not be recorded.', 'axismundi-notifications' ) );
	}
	// `INSERT IGNORE` writes nothing when the constraint already holds it, which is the answer we
	// want: the second delivery of one act finds the first and returns it rather than failing.
	return 0 === (int) $inserted ? axismundi_ntf_event_id_by_dedupe( $dedupe ) : (int) $wpdb->insert_id;
}

/**
 * The event one dedupe hash already holds, or 0.
 *
 * @param string $dedupe Dedupe hash.
 * @return int
 */
function axismundi_ntf_event_id_by_dedupe( string $dedupe ) : int {
	global $wpdb;
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- keyed lookup in this plugin's own table.
	return (int) $wpdb->get_var( $wpdb->prepare( 'SELECT id FROM ' . axismundi_ntf_events_table() . ' WHERE dedupe_hash = %s', $dedupe ) );
}

/**
 * The events addressed to one Actor, newest first.
 *
 * A reader, not an inbox: read state and the manager gate arrive with the deliveries table in the
 * next slice. This exists so the contract can be audited rather than believed.
 *
 * @param int $recipient_actor_id Recipient identity id.
 * @param int $limit              Maximum rows.
 * @return array<int,array<string,mixed>>
 */
function axismundi_ntf_events_for_actor( int $recipient_actor_id, int $limit = 50 ) : array {
	global $wpdb;
	if ( $recipient_actor_id <= 0 || ! axismundi_ntf_ready() ) {
		return array();
	}
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- keyed lookup in this plugin's own table.
	return (array) $wpdb->get_results(
		$wpdb->prepare(
			'SELECT * FROM ' . axismundi_ntf_events_table() . ' WHERE recipient_actor_id = %d ORDER BY occurred_at DESC, id DESC LIMIT %d',
			$recipient_actor_id,
			max( 1, $limit )
		),
		ARRAY_A
	);
}
