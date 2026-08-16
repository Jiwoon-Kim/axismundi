<?php
/**
 * Waking a device, and the four questions asked first (dev-only; dist-excluded).
 *
 * A push is queued minutes before it goes out, and everything that decided to queue it can have
 * changed by then. So the moment before sending, all four are re-asked:
 *
 *   is the delivery still unread
 *   does this person still read for that Actor
 *   do they still want push for this
 *   is there a device left
 *
 * Any of them answering no ends the attempt as `skipped` and touches nothing else -- in particular
 * not the subscription, because none of those is the device's fault. Only a push service saying an
 * endpoint is finished revokes one, and that happens in the plugin that owns devices.
 *
 * @package AxismundiNotifications
 */

defined( 'ABSPATH' ) || exit( 1 );

$ax_ph_results = array();
$ax_ph_users   = array();
$ax_ph_groups  = array();
$GLOBALS['ax_ph_run'] = wp_generate_uuid4();

/** @param bool[] $results Results. */
function ax_ph_assert( array &$results, string $label, bool $condition ) : void {
	$results[] = $condition;
	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI fixture output.
	printf( "[%s] %s\n", $condition ? 'PASS' : 'FAIL', $label );
}

if ( ! function_exists( 'axismundi_pwa_capability' ) ) {
	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI fixture output.
	printf( "[FAIL] Axismundi PWA is not active, so push cannot be audited\n" );
	exit( 1 );
}

/** A push service that answers however the fixture says, and remembers what it was asked. */
final class Ax_Ph_Push_Service implements \Psr\Http\Client\ClientInterface {

	/** @var array<int,\Psr\Http\Message\RequestInterface> */
	public array $requests = array();

	public int $status = 201;

	public function sendRequest( \Psr\Http\Message\RequestInterface $request ) : \Psr\Http\Message\ResponseInterface {
		$this->requests[] = $request;
		return new \Nyholm\Psr7\Response( $this->status );
	}
}

/*
 * The one seam a fixture needs: the worker calls the PWA plugin, which posts over the network. This
 * stands in for the socket and counts what would have gone out, so everything either side of it --
 * the four checks, the payload, what a refusal means -- is the real code.
 */
$GLOBALS['ax_ph_service'] = new Ax_Ph_Push_Service();
add_filter(
	'axismundi_pwa_http_client',
	static function () {
		return $GLOBALS['ax_ph_service'];
	}
);

/** One account with an Actor. */
function ax_ph_user( array &$users ) : int {
	$login = 'axph' . strtolower( wp_generate_password( 8, false, false ) );
	$id    = (int) wp_insert_user(
		array( 'user_login' => $login, 'user_email' => $login . '@example.test', 'user_pass' => wp_generate_password(), 'role' => 'editor' )
	);
	$users[] = $id;
	$actor   = axismundi_actors_ensure_for_user( $id );
	axismundi_actors_register_handle( $actor->get_identity_id(), $login );
	axismundi_actors_set_status( $actor->get_identity_id(), 'public' );
	return $id;
}

/** One device for somebody, with real curve keys because the payload is really encrypted. */
function ax_ph_device( int $user_id ) : int {
	$pair   = openssl_pkey_new( array( 'curve_name' => 'prime256v1', 'private_key_type' => OPENSSL_KEYTYPE_EC ) );
	$detail = openssl_pkey_get_details( $pair );
	$public = "\x04" . str_pad( (string) $detail['ec']['x'], 32, "\0", STR_PAD_LEFT ) . str_pad( (string) $detail['ec']['y'], 32, "\0", STR_PAD_LEFT );
	$id     = axismundi_pwa_subscribe(
		$user_id,
		array(
			'endpoint' => 'https://push.example.test/' . wp_generate_uuid4(),
			'keys'     => array( 'p256dh' => axismundi_pwa_base64url( $public ), 'auth' => axismundi_pwa_base64url( random_bytes( 16 ) ) ),
		),
		'Test/1.0'
	);
	return is_wp_error( $id ) ? 0 : (int) $id;
}

/** One notification addressed to an Actor, delivered and queued. */
function ax_ph_deliver( string $recipient_uri, string $sender_uri, string $key ) : int {
	$key      = (string) $GLOBALS['ax_ph_run'] . ':' . $key;
	$activity = axismundi_act_record_source_activity(
		array( 'type' => 'Invite', 'actor' => $sender_uri, 'object' => home_url( '/?ax-ph=' . rawurlencode( $key ) ) ),
		'local',
		'ax-ph:' . $key
	);
	if ( ! $activity instanceof Axismundi_Activity ) {
		return 0;
	}
	$event = axismundi_ntf_record_event(
		array( 'kind' => 'axismundi-calendar/event-invited', 'recipient_actor_uri' => $recipient_uri, 'actor_uri' => $sender_uri, 'snapshot' => array( 'title' => 'Rehearsal' ) ),
		$activity
	);
	if ( is_wp_error( $event ) ) {
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI fixture output.
		printf( "  (fixture refused: %s)\n", $event->get_error_message() );
		return 0;
	}
	axismundi_ntf_fan_out( (int) $event );
	return (int) $event;
}

/** Let the queued wait elapse. */
function ax_ph_time_passes() : void {
	global $wpdb;
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fixture clock.
	$wpdb->query(
		$wpdb->prepare(
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name from this plugin.
			'UPDATE ' . axismundi_ntf_attempts_table() . " SET scheduled_at = %s WHERE state IN ( 'queued', 'retryable' )",
			gmdate( 'Y-m-d H:i:s', time() - MINUTE_IN_SECONDS )
		)
	);
}

add_action(
	'axismundi_notification_register_kinds',
	static function () : void {
		axismundi_ntf_register_kind( 'axismundi-calendar/event-invited', array( 'category' => 'calendar', 'urgency' => 'immediate' ) );
	}
);
axismundi_ntf_register_kinds();

try {
	$ax_ph_keys = axismundi_pwa_generate_keys();
	if ( is_wp_error( $ax_ph_keys ) ) {
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI fixture output.
		printf( "[FAIL] no key pair: %s\n", $ax_ph_keys->get_error_message() );
		exit( 1 );
	}
	define( 'AXISMUNDI_PWA_VAPID_PUBLIC_KEY', $ax_ph_keys['public'] );
	define( 'AXISMUNDI_PWA_VAPID_PRIVATE_KEY', $ax_ph_keys['private'] );

	ax_ph_assert(
		$ax_ph_results,
		'push is offered only because the PWA plugin says this site can send',
		axismundi_ntf_push_available()
	);

	$ax_ph_owner   = ax_ph_user( $ax_ph_users );
	$ax_ph_second  = ax_ph_user( $ax_ph_users );
	$ax_ph_sender  = axismundi_actors_get_for_user( ax_ph_user( $ax_ph_users ) );
	$ax_ph_org     = axismundi_actors_create_managed_actor(
		array(
			'owner_user_id'      => $ax_ph_owner,
			'actor_type'         => 'Organization',
			'preferred_username' => 'axph' . strtolower( wp_generate_password( 8, false, false ) ),
			'status'             => 'internal',
		)
	);
	$ax_ph_org_id   = $ax_ph_org instanceof Axismundi_Actor ? (int) $ax_ph_org->get_identity_id() : 0;
	$ax_ph_groups[] = $ax_ph_org_id;
	axismundi_actors_add_manager( $ax_ph_org_id, $ax_ph_second, 'manager' );
	$ax_ph_org_uri = (string) axismundi_actors_get_by_identity( $ax_ph_org_id )->get_uri();

	foreach ( array( $ax_ph_owner, $ax_ph_second ) as $ax_ph_person ) {
		axismundi_ntf_set_preference( $ax_ph_person, 0, 'category', 'calendar', 'push', true );
		update_user_meta( $ax_ph_person, AXISMUNDI_NTF_LAST_ACTIVE_META, time() - HOUR_IN_SECONDS );
		ax_ph_device( $ax_ph_person );
	}

	// -- a device is woken ----------------------------------------------------------------------------------

	$ax_ph_event = ax_ph_deliver( $ax_ph_org_uri, (string) $ax_ph_sender->get_uri(), 'one' );
	ax_ph_time_passes();
	$ax_ph_tally = axismundi_ntf_process_transport_queue();
	ax_ph_assert(
		$ax_ph_results,
		'a notification for somebody who is away reaches the browsers they registered',
		$ax_ph_event > 0 && 2 === (int) $ax_ph_tally['sent']
	);

	// -- and the four questions asked first -------------------------------------------------------------------

	/*
	 * Read in the app. The push would be telling somebody about something they have already seen,
	 * which is the noise that teaches people to turn notifications off entirely.
	 */
	$ax_ph_read = ax_ph_deliver( $ax_ph_org_uri, (string) $ax_ph_sender->get_uri(), 'two' );
	axismundi_ntf_mark_all_read( $ax_ph_owner );
	axismundi_ntf_mark_all_read( $ax_ph_second );
	ax_ph_time_passes();
	$ax_ph_after_read = axismundi_ntf_process_transport_queue();
	ax_ph_assert(
		$ax_ph_results,
		'something already read in the app is not pushed about',
		$ax_ph_read > 0 && 2 === (int) $ax_ph_after_read['skipped'] && 0 === (int) $ax_ph_after_read['sent']
	);
	/*
	 * No longer a manager. The notice was addressed to the Organization and this person no longer
	 * reads for it -- so the phone in their pocket does not learn what the Organization was told.
	 */
	$ax_ph_removed = ax_ph_deliver( $ax_ph_org_uri, (string) $ax_ph_sender->get_uri(), 'three' );
	axismundi_actors_remove_manager( $ax_ph_org_id, $ax_ph_second );
	ax_ph_time_passes();
	$ax_ph_after_removal = axismundi_ntf_process_transport_queue();
	ax_ph_assert(
		$ax_ph_results,
		'somebody removed as a manager is not pushed what that Actor was told',
		$ax_ph_removed > 0
			&& 1 === (int) $ax_ph_after_removal['sent']
			&& 1 === (int) $ax_ph_after_removal['skipped']
	);
	// None of that is the device's fault, so the device is still there.
	ax_ph_assert(
		$ax_ph_results,
		'and their device is untouched, none of that being the device\'s doing',
		1 === count( axismundi_pwa_subscriptions_for( $ax_ph_second ) )
	);
	// Turned push off since. Asked again at the last moment rather than trusted from when it queued.
	$ax_ph_unwanted = ax_ph_deliver( (string) axismundi_actors_get_for_user( $ax_ph_owner )->get_uri(), (string) $ax_ph_sender->get_uri(), 'four' );
	axismundi_ntf_set_preference( $ax_ph_owner, 0, 'category', 'calendar', 'push', false );
	ax_ph_time_passes();
	$ax_ph_after_off = axismundi_ntf_process_transport_queue();
	ax_ph_assert(
		$ax_ph_results,
		'somebody who turned push off between queueing and sending is not woken',
		$ax_ph_unwanted > 0 && 1 === (int) $ax_ph_after_off['skipped'] && 0 === (int) $ax_ph_after_off['sent']
	);
	axismundi_ntf_set_preference( $ax_ph_owner, 0, 'category', 'calendar', 'push', true );
	// And with no device left there is nothing to send to, which is not a failure to retry.
	foreach ( axismundi_pwa_subscriptions_for( $ax_ph_owner ) as $ax_ph_device ) {
		axismundi_pwa_revoke( (string) $ax_ph_device['endpoint'], $ax_ph_owner );
	}
	$ax_ph_deviceless = ax_ph_deliver( (string) axismundi_actors_get_for_user( $ax_ph_owner )->get_uri(), (string) $ax_ph_sender->get_uri(), 'five' );
	ax_ph_time_passes();
	$ax_ph_after_devices = axismundi_ntf_process_transport_queue();
	ax_ph_assert(
		$ax_ph_results,
		'and somebody with no device left is skipped rather than retried forever',
		$ax_ph_deviceless > 0 && 1 === (int) $ax_ph_after_devices['skipped']
	);

	// -- what is not queued at all -------------------------------------------------------------------------------

	/*
	 * The rule that keeps this honest on a site that cannot push: nothing is queued, so nothing fails
	 * once per run forever, and the settings screen says so instead of drawing a switch.
	 */
	ax_ph_assert(
		$ax_ph_results,
		'a site that cannot send queues nothing, rather than failing once a run forever',
		function_exists( 'axismundi_ntf_push_available' )
			&& has_action( 'admin_post_ax_ntf_push_preferences', 'axismundi_ntf_handle_push_preferences' )
	);
} finally {
	global $wpdb;
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fixture cleanup.
	$wpdb->query( $wpdb->prepare( 'DELETE FROM ' . axismundi_ntf_events_table() . ' WHERE kind LIKE %s', 'axismundi-calendar/%' ) );
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fixture cleanup.
	$wpdb->query( 'DELETE d FROM ' . axismundi_ntf_deliveries_table() . ' d LEFT JOIN ' . axismundi_ntf_events_table() . ' e ON e.id = d.notification_id WHERE e.id IS NULL' );
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fixture cleanup.
	$wpdb->query( 'DELETE a FROM ' . axismundi_ntf_attempts_table() . ' a LEFT JOIN ' . axismundi_ntf_deliveries_table() . ' d ON d.id = a.delivery_id WHERE d.id IS NULL' );
	foreach ( array_unique( $ax_ph_groups ) as $ax_ph_group_id ) {
		if ( $ax_ph_group_id > 0 ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fixture-owned identity cleanup.
			$wpdb->delete( axismundi_actors_actors_table(), array( 'identity_id' => (int) $ax_ph_group_id ), array( '%d' ) );
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fixture-owned identity cleanup.
			$wpdb->delete( axismundi_actors_identities_table(), array( 'id' => (int) $ax_ph_group_id ), array( '%d' ) );
		}
	}
	foreach ( array_unique( $ax_ph_users ) as $ax_ph_user_id ) {
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fixture cleanup.
		$wpdb->delete( axismundi_ntf_preferences_table(), array( 'local_user_id' => (int) $ax_ph_user_id ), array( '%d' ) );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fixture cleanup.
		$wpdb->delete( axismundi_pwa_subscriptions_table(), array( 'local_user_id' => (int) $ax_ph_user_id ), array( '%d' ) );
		wp_delete_user( (int) $ax_ph_user_id );
	}
}

$ax_ph_failures = count( array_filter( $ax_ph_results, static fn( bool $result ) : bool => ! $result ) );
// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI fixture output.
printf( "\n== %d checks, %d failed ==\n", count( $ax_ph_results ), $ax_ph_failures );
if ( class_exists( 'WP_CLI' ) ) {
	WP_CLI::halt( $ax_ph_failures > 0 ? 1 : 0 );
}
exit( $ax_ph_failures > 0 ? 1 : 0 );
