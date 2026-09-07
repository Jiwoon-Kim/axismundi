<?php
/**
 * Carrying a notification somewhere else.
 *
 * The inbox is where a notification lives. A transport is one attempt at telling somebody about it
 * when they are not looking at it -- and those are separate rows for a reason: a delivery is the
 * fact that this is one of yours, while an attempt is a try that can be queued, sent, refused, worth
 * repeating, or overtaken by the person reading it in the app before it ever went out. One row
 * cannot be both "you have this" and "the mail server accepted it".
 *
 * Email is built here. Push arrives later against the same shape, as another value in `transport`
 * rather than another schema -- which is the point of writing it this way now.
 *
 * Where the address comes from, which is three separate facts that look like one:
 *
 *   the account address     WordPress already holds it, already verifies changes to it, and it is
 *                           already how this site writes to this person privately. It is the default
 *                           notification destination, and nothing here re-implements owning it.
 *   an alternate address    somebody wanting notifications elsewhere gives one and confirms it. Only
 *                           then, because anybody can type anybody's address into a form.
 *   an Actor's contact      a public fact about an identity, published in JSContact, vCard and
 *                           ActivityPub -- and not this. The account address is never promoted into
 *                           it: having an account is not publishing an address.
 *
 * An Organization has no mailbox at all. It is not a person and nothing is written to it; what
 * happens is that each manager is written to at their own address, which falls out of deliveries
 * being per-person and needs no rule of its own.
 *
 * What the reader controls here is the transport, not the address: turning email off is a
 * preference, and it is off until somebody asks for it.
 *
 * Whether somebody is active is judged when the mail would go out, not when the notification was
 * made. Something that arrived while they were reading is exactly what should not become an email,
 * and the only moment that can be known is at the point of sending.
 *
 * @package AxismundiNotifications
 */

defined( 'ABSPATH' ) || exit;

/** What can have happened to one attempt. */
const AXISMUNDI_NTF_ATTEMPT_STATES = array( 'queued', 'sent', 'failed', 'retryable', 'skipped' );

/** How long somebody has to have been away before a notification is worth mailing. */
const AXISMUNDI_NTF_QUIET_MINUTES = 5;

/** How long an unsent attempt keeps waiting for somebody to stop reading before it is given up on. */
const AXISMUNDI_NTF_ATTEMPT_MAX_AGE_HOURS = 24;

/** How many times a transport failure is worth repeating. */
const AXISMUNDI_NTF_ATTEMPT_MAX_TRIES = 3;

/** The meta key holding when somebody was last seen. */
const AXISMUNDI_NTF_LAST_ACTIVE_META = 'ax_ntf_last_active';

/**
 * Note that somebody is here.
 *
 * Written at most once a minute, because this is a heuristic and not an audit log: what it has to
 * support is "were they reading five minutes ago", and a row per page view would cost more than the
 * question is worth.
 *
 * @return void
 */
function axismundi_ntf_touch_activity() : void {
	$user_id = get_current_user_id();
	if ( $user_id <= 0 ) {
		return;
	}
	$last = (int) get_user_meta( $user_id, AXISMUNDI_NTF_LAST_ACTIVE_META, true );
	if ( $last > ( time() - MINUTE_IN_SECONDS ) ) {
		return;
	}
	update_user_meta( $user_id, AXISMUNDI_NTF_LAST_ACTIVE_META, time() );
}
add_action( 'admin_init', 'axismundi_ntf_touch_activity' );

/**
 * When somebody was last seen, as a unix timestamp.
 *
 * @param int $user_id Reader.
 * @return int Zero when never.
 */
function axismundi_ntf_last_active( int $user_id ) : int {
	return (int) get_user_meta( $user_id, AXISMUNDI_NTF_LAST_ACTIVE_META, true );
}

/**
 * The alternate address somebody asked for, confirmed or not.
 *
 * @param int  $user_id        Reader.
 * @param bool $only_confirmed Whether an unconfirmed request counts.
 * @return array<string,mixed>|null
 */
function axismundi_ntf_alternate_mailbox( int $user_id, bool $only_confirmed = true ) : ?array {
	global $wpdb;
	if ( $user_id <= 0 || ! axismundi_ntf_ready() ) {
		return null;
	}
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- primary-key lookup in this plugin's own table.
	$row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . axismundi_ntf_mailboxes_table() . ' WHERE local_user_id = %d', $user_id ), ARRAY_A );
	if ( ! is_array( $row ) ) {
		return null;
	}
	return ( $only_confirmed && null === $row['confirmed_at'] ) ? null : $row;
}

/**
 * Where notifications for one person go.
 *
 * The account address by default. WordPress holds it, verifies changes to it and already writes to
 * it privately -- so re-asking somebody to confirm the address they sign in with would be ceremony
 * over a permission they have already given, and a second copy of a thing WordPress owns.
 *
 * An alternate address, once confirmed, takes precedence: it is the more specific answer, and the
 * only one that needed confirming because it is the only one this plugin learned from a form.
 *
 * None of this reaches an Actor's public contact. That is a published fact about an identity and
 * has its own opt-in; this is a private destination for a local account.
 *
 * @param int $user_id Reader.
 * @return array{address:string,source:string}|null
 */
function axismundi_ntf_mailbox( int $user_id ) : ?array {
	$alternate = axismundi_ntf_alternate_mailbox( $user_id );
	if ( is_array( $alternate ) && is_email( (string) $alternate['address'] ) ) {
		return array( 'address' => (string) $alternate['address'], 'source' => 'confirmed' );
	}
	$user = $user_id > 0 ? get_userdata( $user_id ) : null;
	if ( ! $user instanceof WP_User || ! is_email( (string) $user->user_email ) ) {
		return null;
	}
	return array( 'address' => (string) $user->user_email, 'source' => 'account' );
}

/**
 * Ask to be written to at an address.
 *
 * Sends a confirmation to the address itself, which is what makes this consent rather than an
 * assertion: anybody can type anybody's address into a form, and only the person reading that
 * mailbox can agree to receive things there.
 *
 * @param int    $user_id Reader.
 * @param string $address Address to confirm.
 * @return true|WP_Error
 */
function axismundi_ntf_request_mailbox( int $user_id, string $address ) {
	global $wpdb;
	$address = sanitize_email( trim( $address ) );
	if ( $user_id <= 0 || ! is_email( $address ) || ! axismundi_ntf_ready() ) {
		return new WP_Error( 'ax_ntf_mailbox_address', __( 'That is not an address this can write to.', 'axismundi-notifications' ) );
	}
	$token = wp_generate_password( 32, false, false );
	$now   = current_time( 'mysql', true );
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- this plugin's own table.
	$wpdb->replace(
		axismundi_ntf_mailboxes_table(),
		array(
			'local_user_id' => $user_id,
			'address'       => $address,
			'token_hash'    => hash( 'sha256', $token ),
			'requested_at'  => $now,
			'confirmed_at'  => null,
		),
		array( '%d', '%s', '%s', '%s', '%s' )
	);
	$sent = wp_mail(
		$address,
		__( 'Confirm notification emails', 'axismundi-notifications' ),
		sprintf(
			/* translators: 1: site name, 2: confirmation URL. */
			__( "Confirm that %1\$s may send notifications to this address:\n\n%2\$s\n\nIf you did not ask for this, ignore it and nothing will be sent.", 'axismundi-notifications' ),
			wp_specialchars_decode( (string) get_bloginfo( 'name' ), ENT_QUOTES ),
			add_query_arg(
				array( 'ax_ntf_confirm' => rawurlencode( $token ), 'user' => $user_id ),
				axismundi_ntf_admin_url()
			)
		)
	);
	return $sent ? true : new WP_Error( 'ax_ntf_mailbox_unsent', __( 'The confirmation could not be sent.', 'axismundi-notifications' ) );
}

/**
 * Agree to be written to.
 *
 * @param int    $user_id Reader.
 * @param string $token   Token from the confirmation message.
 * @return bool
 */
function axismundi_ntf_confirm_mailbox( int $user_id, string $token ) : bool {
	global $wpdb;
	$row = axismundi_ntf_alternate_mailbox( $user_id, false );
	if ( ! is_array( $row ) || '' === trim( $token ) ) {
		return false;
	}
	if ( ! hash_equals( (string) $row['token_hash'], hash( 'sha256', trim( $token ) ) ) ) {
		return false;
	}
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- this plugin's own table.
	$wpdb->update(
		axismundi_ntf_mailboxes_table(),
		array( 'confirmed_at' => current_time( 'mysql', true ), 'token_hash' => '' ),
		array( 'local_user_id' => $user_id ),
		array( '%s', '%s' ),
		array( '%d' )
	);
	return true;
}

/**
 * Go back to the account address.
 *
 * The row goes rather than being marked, so nothing is left that a later bug could read as a
 * destination. Stopping email altogether is a different act and belongs to preferences -- this only
 * says where it goes when it goes.
 *
 * @param int $user_id Reader.
 * @return bool
 */
function axismundi_ntf_forget_mailbox( int $user_id ) : bool {
	global $wpdb;
	if ( $user_id <= 0 || ! axismundi_ntf_ready() ) {
		return false;
	}
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- this plugin's own table.
	return false !== $wpdb->delete( axismundi_ntf_mailboxes_table(), array( 'local_user_id' => $user_id ), array( '%d' ) );
}

/**
 * Line one delivery up to be carried somewhere.
 *
 * Scheduled a few minutes out rather than immediately, which is what makes "only when they are not
 * here" answerable at all: a notification somebody reads while it is still queued never becomes an
 * email, and that judgement can only be made later than now.
 *
 * @param int    $delivery_id Delivery id.
 * @param string $transport   email|push.
 * @return bool
 */
function axismundi_ntf_queue_transport( int $delivery_id, string $transport = 'email' ) : bool {
	global $wpdb;
	if ( $delivery_id <= 0 || ! in_array( $transport, AXISMUNDI_NTF_TRANSPORTS, true ) || ! axismundi_ntf_ready() ) {
		return false;
	}
	$now = current_time( 'mysql', true );
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- this plugin's own table.
	return false !== $wpdb->query(
		$wpdb->prepare(
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name from this plugin.
			'INSERT IGNORE INTO ' . axismundi_ntf_attempts_table() . ' ( delivery_id, transport, state, attempts, last_error, scheduled_at, updated_at )'
			. ' VALUES ( %d, %s, %s, 0, %s, %s, %s )',
			$delivery_id,
			$transport,
			'queued',
			'',
			gmdate( 'Y-m-d H:i:s', strtotime( $now . ' UTC' ) + ( AXISMUNDI_NTF_QUIET_MINUTES * MINUTE_IN_SECONDS ) ),
			$now
		)
	);
}

/**
 * Record what became of one attempt.
 *
 * @param int    $attempt_id Attempt id.
 * @param string $state      One of the attempt states.
 * @param string $error      What went wrong, when something did.
 * @return void
 */
function axismundi_ntf_settle_attempt( int $attempt_id, string $state, string $error = '' ) : void {
	global $wpdb;
	if ( ! in_array( $state, AXISMUNDI_NTF_ATTEMPT_STATES, true ) ) {
		return;
	}
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- this plugin's own table.
	$wpdb->query(
		$wpdb->prepare(
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name from this plugin.
			'UPDATE ' . axismundi_ntf_attempts_table() . ' SET state = %s, last_error = %s, attempts = attempts + 1, updated_at = %s WHERE id = %d',
			$state,
			$error,
			current_time( 'mysql', true ),
			$attempt_id
		)
	);
}

/**
 * Carry what is waiting.
 *
 * The worker, and the only place any of this is decided: whether they are here, whether they already
 * read it, whether there is an address to write to, and whether a failure is worth repeating.
 *
 * @param int $limit How many to attempt in one run.
 * @return array<string,int> What happened, by state.
 */
function axismundi_ntf_process_transport_queue( int $limit = 25 ) : array {
	global $wpdb;
	$tally = array( 'sent' => 0, 'skipped' => 0, 'failed' => 0, 'retryable' => 0, 'waiting' => 0 );
	if ( ! axismundi_ntf_ready() ) {
		return $tally;
	}
	$now = current_time( 'mysql', true );
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- keyed lookup across this plugin's own tables.
	$due = (array) $wpdb->get_results(
		$wpdb->prepare(
			'SELECT a.*, d.local_user_id, d.read_at, d.notification_id, e.recipient_actor_id, e.kind, e.category'
			. ' FROM ' . axismundi_ntf_attempts_table() . ' a'
			. ' INNER JOIN ' . axismundi_ntf_deliveries_table() . ' d ON d.id = a.delivery_id'
			. ' INNER JOIN ' . axismundi_ntf_events_table() . ' e ON e.id = d.notification_id'
			. ' WHERE a.state IN ( %s, %s ) AND a.scheduled_at <= %s ORDER BY a.scheduled_at ASC LIMIT %d',
			'queued',
			'retryable',
			$now,
			max( 1, $limit )
		),
		ARRAY_A
	);

	foreach ( $due as $attempt ) {
		$user_id = (int) $attempt['local_user_id'];
		/*
		 * Read in the app already. The mail would be telling somebody about something they have
		 * seen, which is the noise that teaches people to filter these into a folder they never open.
		 */
		if ( null !== $attempt['read_at'] ) {
			axismundi_ntf_settle_attempt( (int) $attempt['id'], 'skipped', 'read in app' );
			++$tally['skipped'];
			continue;
		}
		/*
		 * Everything else is re-asked here rather than trusted from when the attempt was queued,
		 * because all of it can have changed in the meantime and the consequences differ. Somebody
		 * removed as a manager since must not be told what that Group was told; somebody who turned
		 * the transport off since asked not to be interrupted. Neither is the device's fault, so
		 * neither touches the subscription -- this ends as `skipped` and nothing else moves.
		 */
		if ( ! axismundi_ntf_can_read_inbox( (int) $attempt['recipient_actor_id'], $user_id ) ) {
			axismundi_ntf_settle_attempt( (int) $attempt['id'], 'skipped', 'no longer reads for that Actor' );
			++$tally['skipped'];
			continue;
		}
		if ( ! axismundi_ntf_wants( $user_id, (int) $attempt['recipient_actor_id'], (string) $attempt['kind'], (string) $attempt['category'], (string) $attempt['transport'] ) ) {
			axismundi_ntf_settle_attempt( (int) $attempt['id'], 'skipped', 'no longer wanted' );
			++$tally['skipped'];
			continue;
		}

		// Email from here down.
		$mailbox = axismundi_ntf_mailbox( $user_id );
		if ( ! is_array( $mailbox ) ) {
			// An account with no usable address at all. Nothing to retry either: an address is not
			// something a repeat can produce.
			axismundi_ntf_settle_attempt( (int) $attempt['id'], 'failed', 'no address' );
			++$tally['failed'];
			continue;
		}
		/*
		 * Still here. Judged now rather than when the notification was made, which is the whole reason
		 * this waits -- and the wait is bounded, so somebody who never leaves does not accumulate a
		 * queue that would arrive all at once the day they do.
		 */
		$active_since = axismundi_ntf_last_active( $user_id );
		if ( $active_since > ( time() - ( AXISMUNDI_NTF_QUIET_MINUTES * MINUTE_IN_SECONDS ) ) ) {
			$age = time() - (int) strtotime( (string) $attempt['scheduled_at'] . ' UTC' );
			if ( $age > ( AXISMUNDI_NTF_ATTEMPT_MAX_AGE_HOURS * HOUR_IN_SECONDS ) ) {
				axismundi_ntf_settle_attempt( (int) $attempt['id'], 'skipped', 'still active' );
				++$tally['skipped'];
				continue;
			}
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- this plugin's own table.
			$wpdb->update(
				axismundi_ntf_attempts_table(),
				array( 'scheduled_at' => gmdate( 'Y-m-d H:i:s', time() + ( AXISMUNDI_NTF_QUIET_MINUTES * MINUTE_IN_SECONDS ) ), 'updated_at' => $now ),
				array( 'id' => (int) $attempt['id'] ),
				array( '%s', '%s' ),
				array( '%d' )
			);
			++$tally['waiting'];
			continue;
		}

		/*
		 * Only now, and this is where the push branch belongs. It used to sit above the quiet window,
		 * so a phone buzzed while its owner was reading the very page the notification was about --
		 * and the screen promising "only while you are away" was telling the truth about email and a
		 * lie about push. Both transports interrupt somebody, so both wait for the same answer.
		 */
		if ( 'push' === (string) $attempt['transport'] ) {
			$pushed = axismundi_ntf_attempt_push( $attempt );
			++$tally[ $pushed ];
			continue;
		}

		$message = axismundi_ntf_mail_for( (int) $attempt['notification_id'] );
		if ( ! is_array( $message ) ) {
			axismundi_ntf_settle_attempt( (int) $attempt['id'], 'failed', 'notification is gone' );
			++$tally['failed'];
			continue;
		}
		$sent = wp_mail( (string) $mailbox['address'], $message['subject'], $message['body'] );
		if ( $sent ) {
			axismundi_ntf_settle_attempt( (int) $attempt['id'], 'sent' );
			++$tally['sent'];
			continue;
		}
		// A refused send may be a mail server having a bad minute, so it is worth repeating -- but not
		// forever, and the count is what stops a permanently broken address being retried indefinitely.
		$final = ( (int) $attempt['attempts'] + 1 ) >= AXISMUNDI_NTF_ATTEMPT_MAX_TRIES;
		axismundi_ntf_settle_attempt( (int) $attempt['id'], $final ? 'failed' : 'retryable', 'wp_mail refused' );
		++$tally[ $final ? 'failed' : 'retryable' ];
	}
	return $tally;
}

/**
 * Whether push is a thing this site can do to somebody.
 *
 * Asked of the PWA plugin rather than answered here. Whether a browser can be reached is about
 * devices, keys and a service worker, none of which is this plugin's business -- and asking means a
 * push preference cannot be offered on a site that has no way to honour it.
 *
 * @return bool
 */
function axismundi_ntf_push_available() : bool {
	return function_exists( 'axismundi_pwa_can_deliver_push' ) && axismundi_pwa_can_deliver_push();
}

/**
 * Wake somebody's devices about one delivery.
 *
 * The last two of the four things re-asked before a push goes out: the delivery is still unread and
 * the person still reads for that Actor were settled by the caller; here it is whether this site can
 * still send at all, and whether they have a device left to send to.
 *
 * A device that is gone is the PWA plugin's to forget, and it does -- a push service answering `410`
 * revokes it there. Nothing about that is a failure of this attempt: the notification is still
 * theirs, and it is still in their inbox.
 *
 * @param array<string,mixed> $attempt Attempt row joined to its delivery and event.
 * @return string Tally key: sent, skipped, failed or retryable.
 */
function axismundi_ntf_attempt_push( array $attempt ) : string {
	if ( ! axismundi_ntf_push_available() ) {
		axismundi_ntf_settle_attempt( (int) $attempt['id'], 'failed', 'push unavailable' );
		return 'failed';
	}
	$devices = axismundi_pwa_subscriptions_for( (int) $attempt['local_user_id'] );
	if ( array() === $devices ) {
		// Every device unsubscribed or expired. Not a retry: a device is not something a repeat makes.
		axismundi_ntf_settle_attempt( (int) $attempt['id'], 'skipped', 'no device' );
		return 'skipped';
	}
	/*
	 * The id and the category, which is all a push message may carry. What it was about stays behind
	 * the authenticated fetch the app makes when somebody opens it -- and that fetch asks the manager
	 * question again, because a notification opened on a phone is read by whoever is holding it.
	 */
	$payload   = array( 'delivery' => (int) $attempt['notification_id'], 'category' => (string) $attempt['category'] );
	$reached   = false;
	$retryable = false;
	foreach ( $devices as $device ) {
		$result = axismundi_pwa_push( (int) $device['id'], $payload );
		if ( $result['sent'] ) {
			$reached = true;
			continue;
		}
		// A push service having a bad minute is worth repeating; a device that is finished is not, and
		// has already been forgotten by the plugin that owns devices.
		$retryable = $retryable || ( ! $result['expired'] && (int) $result['status'] >= 500 );
	}
	if ( $reached ) {
		axismundi_ntf_settle_attempt( (int) $attempt['id'], 'sent' );
		return 'sent';
	}
	$final = ! $retryable || ( (int) $attempt['attempts'] + 1 ) >= AXISMUNDI_NTF_ATTEMPT_MAX_TRIES;
	axismundi_ntf_settle_attempt( (int) $attempt['id'], $final ? 'failed' : 'retryable', 'no device accepted it' );
	return $final ? 'failed' : 'retryable';
}

/**
 * What one notification says in an email.
 *
 * Built from the snapshot, so a message about an Event since renamed or deleted still reads. The
 * body says what happened and points at the site; it deliberately does not carry a guest list, a
 * comment body or anything else somebody might not want sitting in a mailbox.
 *
 * @param int $notification_id Event id.
 * @return array{subject:string,body:string}|null
 */
function axismundi_ntf_mail_for( int $notification_id ) : ?array {
	global $wpdb;
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- primary-key lookup in this plugin's own table.
	$event = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . axismundi_ntf_events_table() . ' WHERE id = %d', $notification_id ), ARRAY_A );
	if ( ! is_array( $event ) ) {
		return null;
	}
	$snapshot = json_decode( (string) $event['snapshot'], true );
	$title    = is_array( $snapshot ) ? trim( (string) ( $snapshot['title'] ?? '' ) ) : '';
	$subject  = '' !== $title
		/* translators: 1: site name, 2: what the notification is about. */
		? sprintf( __( '[%1$s] %2$s', 'axismundi-notifications' ), wp_specialchars_decode( (string) get_bloginfo( 'name' ), ENT_QUOTES ), $title )
		/* translators: %s: site name. */
		: sprintf( __( '[%s] You have a notification', 'axismundi-notifications' ), wp_specialchars_decode( (string) get_bloginfo( 'name' ), ENT_QUOTES ) );

	$lines = array(
		(string) $event['kind'],
		'',
		/* translators: %s: notifications screen URL. */
		sprintf( __( 'Read it here: %s', 'axismundi-notifications' ), axismundi_ntf_admin_url() ),
	);
	return array( 'subject' => $subject, 'body' => implode( "\n", $lines ) );
}

/** @return void */
function axismundi_ntf_schedule_worker() : void {
	if ( ! wp_next_scheduled( 'axismundi_ntf_transport_queue' ) ) {
		wp_schedule_event( time() + MINUTE_IN_SECONDS, 'hourly', 'axismundi_ntf_transport_queue' );
	}
}
add_action( 'init', 'axismundi_ntf_schedule_worker' );
add_action( 'axismundi_ntf_transport_queue', 'axismundi_ntf_process_transport_queue' );
