<?php
/**
 * Send one real push to a real browser (dev-only; dist-excluded).
 *
 * Not an audit -- the audits prove the rules with the socket faked. This is the one thing they
 * cannot: that a browser somebody actually subscribed in shows a notification, and that clicking it
 * lands on a fetch the server answers.
 *
 * Run it, then look at the screen:
 *
 *   wp eval-file .../e2e-push.php            send one
 *   wp eval-file .../e2e-push.php cleanup    remove what it made
 *
 * The subscription and the VAPID configuration are left alone either way, so the next run needs no
 * setting up.
 *
 * @package AxismundiNotifications
 */

defined( 'ABSPATH' ) || exit( 1 );

const AX_E2E_KIND   = 'axismundi-notifications/e2e-test';
const AX_E2E_OPTION = 'ax_ntf_e2e_push';

add_action(
	'axismundi_notification_register_kinds',
	static function () : void {
		// Its own kind, so cleaning up afterwards cannot take a real notification with it.
		axismundi_ntf_register_kind( AX_E2E_KIND, array( 'category' => 'calendar', 'urgency' => 'immediate' ) );
	}
);
axismundi_ntf_register_kinds();

global $wpdb, $args;
$mode      = isset( $args[0] ) ? (string) $args[0] : 'send';
$recipient = 1;

if ( 'cleanup' === $mode ) {
	$made = (array) get_option( AX_E2E_OPTION, array() );
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fixture cleanup.
	$wpdb->query( $wpdb->prepare( 'DELETE FROM ' . axismundi_ntf_events_table() . ' WHERE kind = %s', AX_E2E_KIND ) );
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fixture cleanup.
	$wpdb->query( 'DELETE d FROM ' . axismundi_ntf_deliveries_table() . ' d LEFT JOIN ' . axismundi_ntf_events_table() . ' e ON e.id = d.notification_id WHERE e.id IS NULL' );
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fixture cleanup.
	$wpdb->query( 'DELETE a FROM ' . axismundi_ntf_attempts_table() . ' a LEFT JOIN ' . axismundi_ntf_deliveries_table() . ' d ON d.id = a.delivery_id WHERE d.id IS NULL' );
	if ( ! empty( $made['sender_user'] ) ) {
		require_once ABSPATH . 'wp-admin/includes/user.php';
		wp_delete_user( (int) $made['sender_user'] );
	}
	delete_option( AX_E2E_OPTION );
	// Deliberately untouched: the browser subscription and the VAPID keys, which are what makes the
	// next run one command instead of a setup.
	echo "cleaned; subscription and keys left in place\n";
	return;
}

$capability = axismundi_pwa_capability();
if ( ! axismundi_ntf_push_available() ) {
	echo 'cannot send: ', wp_json_encode( $capability ), "\n";
	return;
}
$devices = axismundi_pwa_subscriptions_for( $recipient );
if ( array() === $devices ) {
	echo "no device registered for user {$recipient}; turn push on in that browser first\n";
	return;
}

// Somebody other than the recipient, because nobody is told about their own act.
$sender_user = (int) wp_insert_user(
	array(
		'user_login' => 'e2epush' . strtolower( wp_generate_password( 6, false, false ) ),
		'user_email' => 'e2epush' . strtolower( wp_generate_password( 6, false, false ) ) . '@example.test',
		'user_pass'  => wp_generate_password(),
		'role'       => 'editor',
	)
);
$sender = axismundi_actors_ensure_for_user( $sender_user );
axismundi_actors_register_handle( $sender->get_identity_id(), 'e2epush' . strtolower( wp_generate_password( 8, false, false ) ) );
axismundi_actors_set_status( $sender->get_identity_id(), 'public' );

$me = axismundi_actors_ensure_for_user( $recipient );
axismundi_ntf_set_preference( $recipient, 0, 'category', 'calendar', 'push', true );

$key      = 'ax-e2e-push:' . wp_generate_uuid4();
$activity = axismundi_act_record_source_activity(
	array( 'type' => 'Invite', 'actor' => (string) $sender->get_uri(), 'object' => home_url( '/?ax-e2e=' . rawurlencode( $key ) ) ),
	'local',
	$key
);
$event = axismundi_ntf_record_event(
	array(
		'kind'                => AX_E2E_KIND,
		'recipient_actor_uri' => (string) $me->get_uri(),
		'actor_uri'           => (string) $sender->get_uri(),
		'snapshot'            => array( 'title' => 'Push test ' . gmdate( 'H:i:s' ) ),
	),
	$activity
);
if ( is_wp_error( $event ) ) {
	echo 'refused: ', $event->get_error_message(), "\n";
	return;
}
axismundi_ntf_fan_out( (int) $event );
update_option( AX_E2E_OPTION, array( 'notification' => (int) $event, 'sender_user' => $sender_user ), false );

/*
 * Away, and the wait elapsed. Both are real conditions the worker checks -- somebody at the keyboard
 * is left waiting on purpose, which is the rule this fixture would otherwise trip over.
 */
update_user_meta( $recipient, AXISMUNDI_NTF_LAST_ACTIVE_META, time() - HOUR_IN_SECONDS );
// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fixture clock.
$wpdb->query( $wpdb->prepare( 'UPDATE ' . axismundi_ntf_attempts_table() . " SET scheduled_at = %s WHERE state = 'queued'", gmdate( 'Y-m-d H:i:s', time() - MINUTE_IN_SECONDS ) ) );

$tally = axismundi_ntf_process_transport_queue();

// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fixture report.
$attempts = (array) $wpdb->get_results(
	$wpdb->prepare(
		'SELECT a.transport, a.state, a.last_error FROM ' . axismundi_ntf_attempts_table() . ' a'
		. ' INNER JOIN ' . axismundi_ntf_deliveries_table() . ' d ON d.id = a.delivery_id'
		. ' WHERE d.notification_id = %d',
		(int) $event
	),
	ARRAY_A
);

echo 'notification=', (int) $event, "\n";
echo 'devices=', count( $devices ), "\n";
echo 'worker=', wp_json_encode( $tally ), "\n";
foreach ( $attempts as $attempt ) {
	echo 'attempt ', $attempt['transport'], '=', $attempt['state'], '' !== (string) $attempt['last_error'] ? ' (' . $attempt['last_error'] . ')' : '', "\n";
}
echo 'fetch: ', rest_url( 'axismundi/v1/notifications/' . (int) $event ), "\n";
