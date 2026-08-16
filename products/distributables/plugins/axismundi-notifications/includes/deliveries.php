<?php
/**
 * From an Actor's news to a person's attention.
 *
 * The event says an Organization was invited to something. This says which people, at that moment,
 * were the ones who would have to deal with it -- and holds each of their own read state, because
 * two managers of one Group reading the same notice are two separate acts of attention.
 *
 * Two rules, and they pull in opposite directions on purpose:
 *
 *   fan-out is a snapshot   the managers as they stood when it happened, so somebody made a manager
 *                           today does not wake up to a hundred unread months
 *   access is re-asked      every read re-checks the manager relation, so somebody removed since
 *                           cannot open an inbox their old rows are still sitting in
 *
 * A row here is a record of what was delivered. It is never the authority on what may now be read.
 *
 * @package AxismundiNotifications
 */

defined( 'ABSPATH' ) || exit;

/**
 * Whether one person may read one Actor's inbox.
 *
 * Deliberately narrower than `axismundi_actors_can_manage()`, which lets a site administrator
 * administer any Actor. Administering an identity and reading what it was told are different
 * entitlements: an administrator can rename somebody's Actor or fix its handle without thereby
 * being entitled to read the invitations, replies and mentions that person received. So a
 * user-scope Actor's inbox belongs to that user alone, with no capability override, and a managed
 * Actor's belongs to its managers.
 *
 * @param int $identity_id Recipient Actor identity.
 * @param int $user_id     Reader.
 * @return bool
 */
function axismundi_ntf_can_read_inbox( int $identity_id, int $user_id ) : bool {
	if ( $identity_id <= 0 || $user_id <= 0 || ! axismundi_ntf_has_actors() ) {
		return false;
	}
	$actor = axismundi_actors_get_by_identity( $identity_id );
	if ( ! $actor instanceof Axismundi_Actor || ! $actor->is_local() ) {
		return false;
	}
	if ( $actor->is_managed() ) {
		return axismundi_actors_managed_actor_can_manage( $identity_id, $user_id );
	}
	return (int) $actor->get_local_user_id() === $user_id;
}

/**
 * The people who would have to deal with something addressed to one Actor.
 *
 * @param int $identity_id Recipient Actor identity.
 * @return int[] Local user ids.
 */
function axismundi_ntf_inbox_readers( int $identity_id ) : array {
	if ( $identity_id <= 0 || ! axismundi_ntf_has_actors() ) {
		return array();
	}
	$actor = axismundi_actors_get_by_identity( $identity_id );
	if ( ! $actor instanceof Axismundi_Actor || ! $actor->is_local() ) {
		return array();
	}
	if ( ! $actor->is_managed() ) {
		$owner = (int) $actor->get_local_user_id();
		return $owner > 0 ? array( $owner ) : array();
	}
	return array_values(
		array_filter(
			array_map(
				static fn( array $manager ) : int => (int) $manager['user_id'],
				axismundi_actors_group_managers( $identity_id )
			)
		)
	);
}

/**
 * Hand one event to the people it concerns.
 *
 * The one place "not your own act" is decided, because it is the only stage that knows people. An
 * act performed as an Organization is addressed to that Organization, and the manager who performed
 * it is the single person in that list who does not need telling.
 *
 * @param int $notification_id Event id.
 * @return int Deliveries written.
 */
function axismundi_ntf_fan_out( int $notification_id ) : int {
	global $wpdb;
	if ( $notification_id <= 0 || ! axismundi_ntf_ready() ) {
		return 0;
	}
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- primary-key lookup in this plugin's own table.
	$event = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . axismundi_ntf_events_table() . ' WHERE id = %d', $notification_id ), ARRAY_A );
	if ( ! is_array( $event ) ) {
		return 0;
	}
	if ( 'accepted' !== (string) $event['state'] ) {
		// Held for review reaches nobody's badge. It is listed among requests, and becomes a delivery
		// if somebody says it was wanted.
		return 0;
	}
	$initiator = null === $event['initiating_local_user_id'] ? 0 : (int) $event['initiating_local_user_id'];
	$now       = current_time( 'mysql', true );
	$written   = 0;
	foreach ( axismundi_ntf_inbox_readers( (int) $event['recipient_actor_id'] ) as $user_id ) {
		if ( $user_id === $initiator && $initiator > 0 ) {
			// The person who did it. Telling them what they just did is how a badge becomes noise.
			continue;
		}
		/*
		 * Asked of each person separately, which is the point of deliveries existing: two managers of
		 * one Group can want different things from the same inbox, and the event stays the Actor's fact
		 * either way. Somebody who turned this kind off simply has no copy of it.
		 */
		if ( ! axismundi_ntf_wants( $user_id, (int) $event['recipient_actor_id'], (string) $event['kind'], (string) $event['category'], 'in_app' ) ) {
			continue;
		}
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- this plugin's own table.
		$result = $wpdb->query(
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name from this plugin.
				'INSERT IGNORE INTO ' . axismundi_ntf_deliveries_table() . ' ( notification_id, local_user_id, delivered_at ) VALUES ( %d, %d, %s )',
				$notification_id,
				$user_id,
				$now
			)
		);
		if ( 1 !== (int) $result ) {
			continue;
		}
		++$written;
		/*
		 * Lined up rather than sent. Whether this becomes an email is a question about where they are
		 * when it would go out, and the only place that can be answered is the worker.
		 */
		if ( axismundi_ntf_wants( $user_id, (int) $event['recipient_actor_id'], (string) $event['kind'], (string) $event['category'], 'email' ) ) {
			axismundi_ntf_queue_transport( (int) $wpdb->insert_id, 'email' );
		}
	}
	return $written;
}

/**
 * What one person has to look at.
 *
 * Read from the events, with each person's own delivery joined on. That is what lets a new manager
 * see the Actor's history without inheriting its unread count: the entries are there because the
 * Actor was told, and the unread badge counts deliveries, which they have none of for anything
 * older than they are.
 *
 * @param int  $user_id     Reader.
 * @param int  $limit       Maximum rows.
 * @param bool $unread_only Whether to return only what they have not read.
 * @return array<int,array<string,mixed>>
 */
function axismundi_ntf_inbox( int $user_id, int $limit = 50, bool $unread_only = false ) : array {
	global $wpdb;
	if ( $user_id <= 0 || ! axismundi_ntf_ready() ) {
		return array();
	}
	$identities = axismundi_ntf_readable_identities( $user_id );
	if ( array() === $identities ) {
		return array();
	}
	$events     = axismundi_ntf_events_table();
	$deliveries = axismundi_ntf_deliveries_table();
	$in         = implode( ', ', array_fill( 0, count( $identities ), '%d' ) );
	// Only what was accepted. Held-for-review notices are the same rows, listed by
	// `axismundi_ntf_requests()` instead -- one table, two questions.
	$sql        = "SELECT e.*, d.id AS delivery_id, d.read_at, d.dismissed_at
		 FROM {$events} e
		 LEFT JOIN {$deliveries} d ON d.notification_id = e.id AND d.local_user_id = %d
		 WHERE e.recipient_actor_id IN ( {$in} ) AND e.state = 'accepted'";
	$params     = array_merge( array( $user_id ), $identities );
	if ( $unread_only ) {
		// A row somebody never had delivered is not unread for them; it is history they may read.
		$sql .= ' AND d.id IS NOT NULL AND d.read_at IS NULL AND d.dismissed_at IS NULL';
	}
	$sql     .= ' ORDER BY e.occurred_at DESC, e.id DESC LIMIT %d';
	$params[] = max( 1, $limit );
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- keyed lookup across this plugin's own tables.
	$rows = (array) $wpdb->get_results( $wpdb->prepare( $sql, $params ), ARRAY_A );

	/*
	 * A delivered notice stays delivered whatever was turned off afterwards -- settings apply to what
	 * happens next and never rewrite what happened. What preference does decide here is the other
	 * kind of row: an Actor's history from before this person could read it, or from while they had
	 * this kind switched off. Neither was ever delivered to them, so neither is theirs to be shown.
	 */
	return array_values(
		array_filter(
			$rows,
			static fn( array $row ) : bool => null !== $row['delivery_id']
				|| axismundi_ntf_wants( $user_id, (int) $row['recipient_actor_id'], (string) $row['kind'], (string) $row['category'], 'in_app' )
		)
	);
}

/**
 * The Actors whose inbox one person may currently read.
 *
 * Asked afresh every time rather than kept anywhere. Manager relations change, and a list cached
 * against a session would let somebody removed this morning keep reading until they signed out.
 *
 * @param int $user_id Reader.
 * @return int[] Identity ids.
 */
function axismundi_ntf_readable_identities( int $user_id ) : array {
	if ( $user_id <= 0 || ! axismundi_ntf_has_actors() ) {
		return array();
	}
	$identities = array();
	$own        = axismundi_actors_get_for_user( $user_id );
	if ( $own instanceof Axismundi_Actor ) {
		$identities[] = (int) $own->get_identity_id();
	}
	foreach ( axismundi_actors_list_manageable_actors( $user_id ) as $actor ) {
		$identities[] = (int) $actor->get_identity_id();
	}
	return array_values( array_unique( array_filter( $identities ) ) );
}

/**
 * What is being held for review.
 *
 * The same rows the inbox reads, asked the other question. Somebody with a policy against messages
 * from strangers has to be able to find the one legitimate stranger who wrote to them, and this is
 * where they look -- which is the whole difference between filtering and discarding.
 *
 * @param int $user_id Reader.
 * @param int $limit   Maximum rows.
 * @return array<int,array<string,mixed>>
 */
function axismundi_ntf_requests( int $user_id, int $limit = 50 ) : array {
	global $wpdb;
	if ( $user_id <= 0 || ! axismundi_ntf_ready() ) {
		return array();
	}
	$identities = axismundi_ntf_readable_identities( $user_id );
	if ( array() === $identities ) {
		return array();
	}
	$in = implode( ', ', array_fill( 0, count( $identities ), '%d' ) );
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- keyed lookup in this plugin's own table.
	return (array) $wpdb->get_results(
		$wpdb->prepare(
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name and id list built above.
			'SELECT * FROM ' . axismundi_ntf_events_table() . " WHERE recipient_actor_id IN ( {$in} ) AND state = 'filtered' ORDER BY occurred_at DESC, id DESC LIMIT %d",
			array_merge( $identities, array( max( 1, $limit ) ) )
		),
		ARRAY_A
	);
}

/**
 * Say a held notice was wanted after all.
 *
 * It becomes an ordinary notification from this moment: delivered to whoever runs that Actor now,
 * and unread for them. Not to whoever ran it when it arrived -- that fan-out never happened, and
 * inventing it retrospectively would hand somebody a delivery for a period they were not there for.
 *
 * @param int $notification_id Event id.
 * @param int $user_id         The person accepting it.
 * @return true|WP_Error
 */
function axismundi_ntf_accept_request( int $notification_id, int $user_id ) {
	global $wpdb;
	if ( ! axismundi_ntf_ready() ) {
		return new WP_Error( 'ax_ntf_unavailable', __( 'Notifications is not available.', 'axismundi-notifications' ) );
	}
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- primary-key lookup in this plugin's own table.
	$event = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . axismundi_ntf_events_table() . ' WHERE id = %d', $notification_id ), ARRAY_A );
	if ( ! is_array( $event ) ) {
		return new WP_Error( 'ax_ntf_missing', __( 'There is no such notification.', 'axismundi-notifications' ), array( 'status' => 404 ) );
	}
	if ( ! axismundi_ntf_can_read_inbox( (int) $event['recipient_actor_id'], $user_id ) ) {
		return new WP_Error( 'ax_ntf_forbidden', __( 'That inbox is not yours to read.', 'axismundi-notifications' ), array( 'status' => 403 ) );
	}
	if ( 'filtered' !== (string) $event['state'] ) {
		return new WP_Error( 'ax_ntf_not_filtered', __( 'That notification is not being held.', 'axismundi-notifications' ), array( 'status' => 409 ) );
	}
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- this plugin's own table.
	$wpdb->update( axismundi_ntf_events_table(), array( 'state' => 'accepted' ), array( 'id' => $notification_id ), array( '%s' ), array( '%d' ) );
	axismundi_ntf_fan_out( $notification_id );
	return true;
}

/**
 * How many notices one person has not read.
 *
 * @param int $user_id Reader.
 * @return int
 */
function axismundi_ntf_unread_count( int $user_id ) : int {
	return count( axismundi_ntf_inbox( $user_id, 200, true ) );
}

/**
 * Mark one notification read for one person.
 *
 * Creates the delivery row when there is none, which is what happens when somebody reads a notice
 * that predates them: they were not delivered it, they may read it, and having read it they should
 * not be shown it as new. Refused when they may not read that Actor's inbox at all -- checked here
 * rather than trusted from whatever drew the button.
 *
 * @param int $notification_id Event id.
 * @param int $user_id         Reader.
 * @return true|WP_Error
 */
function axismundi_ntf_mark_read( int $notification_id, int $user_id ) {
	global $wpdb;
	if ( ! axismundi_ntf_ready() ) {
		return new WP_Error( 'ax_ntf_unavailable', __( 'Notifications is not available.', 'axismundi-notifications' ) );
	}
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- primary-key lookup in this plugin's own table.
	$recipient = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT recipient_actor_id FROM ' . axismundi_ntf_events_table() . ' WHERE id = %d', $notification_id ) );
	if ( $recipient <= 0 ) {
		return new WP_Error( 'ax_ntf_missing', __( 'There is no such notification.', 'axismundi-notifications' ), array( 'status' => 404 ) );
	}
	if ( ! axismundi_ntf_can_read_inbox( $recipient, $user_id ) ) {
		return new WP_Error( 'ax_ntf_forbidden', __( 'That inbox is not yours to read.', 'axismundi-notifications' ), array( 'status' => 403 ) );
	}
	$now = current_time( 'mysql', true );
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- this plugin's own table.
	$wpdb->query(
		$wpdb->prepare(
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name from this plugin.
			'INSERT INTO ' . axismundi_ntf_deliveries_table() . ' ( notification_id, local_user_id, delivered_at, read_at ) VALUES ( %d, %d, %s, %s )'
			. ' ON DUPLICATE KEY UPDATE read_at = VALUES( read_at )',
			$notification_id,
			$user_id,
			$now,
			$now
		)
	);
	return true;
}

/**
 * Mark everything one person can currently see as read.
 *
 * @param int $user_id Reader.
 * @return int Rows marked.
 */
function axismundi_ntf_mark_all_read( int $user_id ) : int {
	$marked = 0;
	foreach ( axismundi_ntf_inbox( $user_id, 200, true ) as $row ) {
		if ( true === axismundi_ntf_mark_read( (int) $row['id'], $user_id ) ) {
			++$marked;
		}
	}
	return $marked;
}
