<?php
/**
 * Posting to a push service, without one (dev-only; dist-excluded).
 *
 * The cryptography is the library's and is not re-tested here. What is ours is everything around
 * it: that a request is actually made, that it goes to the endpoint the device registered, that
 * nothing but an id and a category travels in it, and that a push service saying an endpoint is
 * finished stops us reaching for it again.
 *
 * The transport is injected, so this proves the send happened without waking anybody's phone -- and
 * proves it against a `410` too, which is the answer that matters most and the hardest to arrange
 * on purpose against a real service.
 *
 * @package AxismundiPwa
 */

defined( 'ABSPATH' ) || exit( 1 );

$ax_sn_results = array();
$ax_sn_users   = array();

/** @param bool[] $results Results. */
function ax_sn_assert( array &$results, string $label, bool $condition ) : void {
	$results[] = $condition;
	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI fixture output.
	printf( "[%s] %s\n", $condition ? 'PASS' : 'FAIL', $label );
}

if ( ! axismundi_pwa_has_library() ) {
	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI fixture output.
	printf( "[FAIL] the Web Push library is not installed; run composer install in the plugin\n" );
	exit( 1 );
}

/**
 * A push service that answers however the fixture says, and remembers what it was asked.
 *
 * PSR-18, which is what the library takes -- so everything up to the request is the real code path:
 * the VAPID token is really signed and the payload is really encrypted, and only the socket is not.
 */
final class Ax_Sn_Push_Service implements \Psr\Http\Client\ClientInterface {

	/** @var array<int,\Psr\Http\Message\RequestInterface> */
	public array $requests = array();

	public int $status = 201;

	public function sendRequest( \Psr\Http\Message\RequestInterface $request ) : \Psr\Http\Message\ResponseInterface {
		$this->requests[] = $request;
		return new \Nyholm\Psr7\Response( $this->status );
	}
}

/** One account. */
function ax_sn_user( array &$users ) : int {
	$login = 'axsn' . strtolower( wp_generate_password( 8, false, false ) );
	$id    = (int) wp_insert_user(
		array( 'user_login' => $login, 'user_email' => $login . '@example.test', 'user_pass' => wp_generate_password(), 'role' => 'editor' )
	);
	$users[] = $id;
	return $id;
}

try {
	/*
	 * Real keys, because the signing is real: a fixture key of the wrong shape would fail inside the
	 * library rather than proving anything about this plugin.
	 */
	$ax_sn_keys = axismundi_pwa_generate_keys();
	if ( is_wp_error( $ax_sn_keys ) ) {
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI fixture output.
		printf( "[FAIL] could not make a key pair: %s\n", $ax_sn_keys->get_error_message() );
		exit( 1 );
	}
	define( 'AXISMUNDI_PWA_VAPID_PUBLIC_KEY', $ax_sn_keys['public'] );
	define( 'AXISMUNDI_PWA_VAPID_PRIVATE_KEY', $ax_sn_keys['private'] );

	ax_sn_assert(
		$ax_sn_results,
		'with keys configured and the library present, this site says it can send',
		axismundi_pwa_can_deliver_push()
			&& true === axismundi_pwa_capability()['deliver']
	);

	$ax_sn_owner    = ax_sn_user( $ax_sn_users );
	$ax_sn_endpoint = 'https://push.example.test/' . wp_generate_uuid4();
	/*
	 * A real subscription key pair, because the payload is really encrypted to it. A made-up p256dh
	 * is not a point on the curve and the library refuses it -- correctly, and uninformatively for a
	 * fixture trying to prove something else.
	 */
	$ax_sn_device = openssl_pkey_new( array( 'curve_name' => 'prime256v1', 'private_key_type' => OPENSSL_KEYTYPE_EC ) );
	$ax_sn_detail = openssl_pkey_get_details( $ax_sn_device );
	$ax_sn_public = "\x04" . str_pad( (string) $ax_sn_detail['ec']['x'], 32, "\0", STR_PAD_LEFT ) . str_pad( (string) $ax_sn_detail['ec']['y'], 32, "\0", STR_PAD_LEFT );
	$ax_sn_id     = axismundi_pwa_subscribe(
		$ax_sn_owner,
		array(
			'endpoint' => $ax_sn_endpoint,
			'keys'     => array(
				'p256dh' => axismundi_pwa_base64url( $ax_sn_public ),
				'auth'   => axismundi_pwa_base64url( random_bytes( 16 ) ),
			),
		),
		'Test/1.0'
	);

	// -- a message actually goes out ---------------------------------------------------------------------

	$ax_sn_service = new Ax_Sn_Push_Service();
	$ax_sn_sent    = axismundi_pwa_push( (int) $ax_sn_id, array( 'delivery' => 42, 'category' => 'calendar' ), $ax_sn_service );
	ax_sn_assert(
		$ax_sn_results,
		'a push is signed, encrypted and posted to the endpoint that device registered',
		true === $ax_sn_sent['sent']
			&& 1 === count( $ax_sn_service->requests )
			&& $ax_sn_endpoint === (string) $ax_sn_service->requests[0]->getUri()
	);
	$ax_sn_request = $ax_sn_service->requests[0];
	ax_sn_assert(
		$ax_sn_results,
		'carrying a VAPID token and an encrypted body, which is why the library does this and not us',
		str_starts_with( (string) $ax_sn_request->getHeaderLine( 'Authorization' ), 'vapid' )
			&& 'aes128gcm' === (string) $ax_sn_request->getHeaderLine( 'Content-Encoding' )
			&& $ax_sn_request->getBody()->getSize() > 0
	);
	/*
	 * And the body is opaque. This is the check that would fail the day somebody adds a title "just
	 * for the notification text": a push message crosses a service that is not ours and can be read
	 * off a locked screen.
	 */
	ax_sn_assert(
		$ax_sn_results,
		'and nothing readable travels in it, the contents staying behind a signed-in fetch',
		! str_contains( (string) $ax_sn_request->getBody(), 'calendar' )
			&& ! str_contains( (string) $ax_sn_request->getBody(), '42' )
	);
	// What may be said at all is a fixed shape, so a field added later cannot ride along unnoticed.
	ax_sn_assert(
		$ax_sn_results,
		'what may be said is allowlisted rather than filtered, so a new field cannot ride along',
		array( 'delivery' => 7, 'category' => 'social' ) === axismundi_pwa_payload(
			array( 'delivery' => 7, 'category' => 'social', 'title' => 'Dinner at eight', 'body' => 'with the usual people' )
		)
	);

	// -- an endpoint that is finished --------------------------------------------------------------------

	/*
	 * `410` is a push service saying that browser is gone: uninstalled, expired, site data cleared.
	 * The only correct response is to stop reaching for it -- retrying is how a dead endpoint becomes
	 * a permanent queue, and how a sender spends every run failing at the same device.
	 */
	$ax_sn_gone         = new Ax_Sn_Push_Service();
	$ax_sn_gone->status = 410;
	$ax_sn_result       = axismundi_pwa_push( (int) $ax_sn_id, array( 'delivery' => 43, 'category' => 'calendar' ), $ax_sn_gone );
	ax_sn_assert(
		$ax_sn_results,
		'a push service saying the endpoint is gone revokes the device rather than reporting a failure',
		false === $ax_sn_result['sent']
			&& true === $ax_sn_result['expired']
			&& 410 === (int) $ax_sn_result['status']
			&& array() === axismundi_pwa_subscriptions_for( $ax_sn_owner )
	);
	// And nothing tries it again.
	$ax_sn_after = axismundi_pwa_push( (int) $ax_sn_id, array( 'delivery' => 44, 'category' => 'calendar' ), $ax_sn_gone );
	ax_sn_assert(
		$ax_sn_results,
		'and nothing reaches for it afterwards, which is the whole reason to act on the answer',
		false === $ax_sn_after['sent']
			&& 'no_subscription' === (string) $ax_sn_after['error']
			&& 1 === count( $ax_sn_gone->requests )
	);
	/*
	 * A refusal that is not about the device -- a service having a bad minute -- leaves it alone. The
	 * difference between "stop" and "try later" is the whole of what this layer decides.
	 */
	$ax_sn_second = axismundi_pwa_subscribe(
		$ax_sn_owner,
		array(
			'endpoint' => 'https://push.example.test/' . wp_generate_uuid4(),
			'keys'     => array( 'p256dh' => axismundi_pwa_base64url( $ax_sn_public ), 'auth' => axismundi_pwa_base64url( random_bytes( 16 ) ) ),
		),
		'Test/2.0'
	);
	$ax_sn_busy         = new Ax_Sn_Push_Service();
	$ax_sn_busy->status = 500;
	$ax_sn_failed       = axismundi_pwa_push( (int) $ax_sn_second, array( 'delivery' => 45, 'category' => 'calendar' ), $ax_sn_busy );
	ax_sn_assert(
		$ax_sn_results,
		'while a service having a bad minute leaves the device alone, that not being about the device',
		false === $ax_sn_failed['sent']
			&& false === $ax_sn_failed['expired']
			&& 1 === count( axismundi_pwa_subscriptions_for( $ax_sn_owner ) )
	);
} finally {
	global $wpdb;
	foreach ( array_unique( $ax_sn_users ) as $ax_sn_user_id ) {
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fixture cleanup.
		$wpdb->delete( axismundi_pwa_subscriptions_table(), array( 'local_user_id' => (int) $ax_sn_user_id ), array( '%d' ) );
		wp_delete_user( (int) $ax_sn_user_id );
	}
}

$ax_sn_failures = count( array_filter( $ax_sn_results, static fn( bool $result ) : bool => ! $result ) );
// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI fixture output.
printf( "\n== %d checks, %d failed ==\n", count( $ax_sn_results ), $ax_sn_failures );
if ( class_exists( 'WP_CLI' ) ) {
	WP_CLI::halt( $ax_sn_failures > 0 ? 1 : 0 );
}
exit( $ax_sn_failures > 0 ? 1 : 0 );
