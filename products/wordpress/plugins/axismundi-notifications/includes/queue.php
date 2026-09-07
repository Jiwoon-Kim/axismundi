<?php
/**
 * When an Activity becomes a notification.
 *
 * Not when it is recorded. The ledger commits an Activity and announces it immediately, and at that
 * moment the domain transition it describes has usually not happened yet -- Calendar records the
 * `Invite` and *then* writes the participation row, deliberately, because a row with no Activity
 * behind it is worse than an Activity whose row is one line away. A resolver run there would compute
 * its audience from the state before the act it is describing.
 *
 * So the ledger hook only remembers, and resolution happens when the domain says it has finished:
 *
 *   axismundi_act_activity_recorded   enqueue
 *   the domain finishes its command   axismundi_notification_flush()
 *   end of request                    flush whatever nobody flushed
 *
 * The explicit flush is the contract and the shutdown pass is a safety net. They are not
 * interchangeable: a fatal error, an `exit` in a redirect handler or a request killed by a timeout
 * all end without shutdown running the way anybody hoped, and what survives is the Activity with no
 * notification beside it. A domain command that returns to its caller having recorded an Activity
 * and changed its state, without flushing, is a bug in that command.
 *
 * Resolution is synchronous and never deferred to cron, because the audience is a snapshot -- "the
 * Actors who still had this Event on their calendar when it was called off". Recomputing that
 * tomorrow would send a past cancellation to somebody removed since, and would quietly drop somebody
 * who left after being told.
 *
 * @package AxismundiNotifications
 */

defined( 'ABSPATH' ) || exit;

/**
 * Remember an Activity until the transition it describes is complete.
 *
 * @param Axismundi_Activity $activity Newly committed Activity.
 * @return void
 */
function axismundi_ntf_enqueue_activity( Axismundi_Activity $activity ) : void {
	if ( ! axismundi_ntf_ready() ) {
		return;
	}
	$uri = (string) $activity->get_uri();
	if ( '' === $uri ) {
		return;
	}
	if ( ! isset( $GLOBALS['axismundi_ntf_pending'] ) || ! is_array( $GLOBALS['axismundi_ntf_pending'] ) ) {
		$GLOBALS['axismundi_ntf_pending'] = array();
	}
	// Keyed by URI, so the same Activity arriving twice in one request waits once.
	$GLOBALS['axismundi_ntf_pending'][ $uri ] = true;
}
add_action( 'axismundi_act_activity_recorded', 'axismundi_ntf_enqueue_activity' );

/** @return string[] Activity URIs waiting to be resolved. */
function axismundi_ntf_pending() : array {
	return array_keys( (array) ( $GLOBALS['axismundi_ntf_pending'] ?? array() ) );
}

/**
 * Ask the domains what the waiting Activities meant, and store the answers.
 *
 * Called by a domain command once its state transition is complete and before it returns. Safe to
 * call when nothing is waiting, and safe to call twice: the queue is emptied first, and the dedupe
 * constraint holds anything that slips through.
 *
 * @return int Events recorded.
 */
function axismundi_notification_flush() : int {
	if ( ! axismundi_ntf_ready() ) {
		return 0;
	}
	$pending = axismundi_ntf_pending();
	// Emptied before resolving, not after. A resolver that records an Activity of its own would
	// otherwise re-enter this with the old queue still in place and resolve everything twice.
	$GLOBALS['axismundi_ntf_pending'] = array();

	$recorded = 0;
	foreach ( $pending as $uri ) {
		$activity = axismundi_act_get( (string) $uri );
		if ( ! $activity instanceof Axismundi_Activity ) {
			continue;
		}
		/**
		 * Filter the notification intents one Activity produces.
		 *
		 * Answered by the domain that owns the transition, because only it knows what the Activity
		 * changed and who that concerns -- this plugin deliberately cannot read Calendar's tables or
		 * Forum's, and does not learn federation: an `Invite` that arrived from another server
		 * resolves exactly like one written here.
		 *
		 * Each intent is an array of `kind`, `recipient_actor_uri`, and optionally `actor_uri`,
		 * `object_uri`, `grouping_key`, `occurred_at` and `snapshot`. The snapshot is what the
		 * resolver saw, and it is stored, because nothing here will ever ask again.
		 *
		 * @param array<int,array<string,mixed>> $intents  Intents so far.
		 * @param Axismundi_Activity             $activity The committed Activity.
		 */
		$intents = (array) apply_filters( 'axismundi_notification_intents', array(), $activity );
		foreach ( $intents as $intent ) {
			if ( ! is_array( $intent ) ) {
				continue;
			}
			$event = axismundi_ntf_record_event( $intent, $activity );
			if ( ! is_wp_error( $event ) && $event > 0 ) {
				/*
				 * Handed out in the same breath, to the managers as they stand now. This is the snapshot:
				 * somebody made a manager tomorrow will be able to read this, and will not be handed it
				 * as something unread from before they arrived.
				 *
				 * Held-for-review notices are not handed out. They exist, they can be looked through, and
				 * nobody's badge counts them until somebody says they were wanted.
				 */
				axismundi_ntf_fan_out( (int) $event );
				++$recorded;
			}
		}
	}
	return $recorded;
}

/**
 * The safety net, and only that.
 *
 * Anything still waiting here is a domain command that did not flush, which is a bug in that
 * command rather than a supported path -- but losing the notification as well as having the bug
 * helps nobody, so it is resolved rather than dropped.
 *
 * @return void
 */
function axismundi_ntf_flush_on_shutdown() : void {
	if ( array() !== axismundi_ntf_pending() ) {
		axismundi_notification_flush();
	}
}
add_action( 'shutdown', 'axismundi_ntf_flush_on_shutdown', 5 );
