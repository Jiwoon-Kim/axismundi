<?php
/**
 * Posting a message to a push service.
 *
 * The cryptography is not ours. VAPID signing, ECDH, HKDF and `aes128gcm` payload encryption are
 * exactly the kind of code that looks right, passes the tests somebody wrote for it, and is subtly
 * wrong in a way no audit here would catch -- so `minishlink/web-push` does it, and this file is
 * the part that is actually ours: which device, what may be in the payload, and what a refusal
 * means.
 *
 * What a refusal means is the half worth having. A push service answering `404` or `410` is saying
 * that endpoint is finished -- the browser was uninstalled, the subscription expired, somebody
 * cleared their site data -- and the only correct response is to stop reaching for it. Retrying is
 * how a dead endpoint becomes a permanent queue.
 *
 * The payload carries a delivery id and a category. Nothing else goes through a push service and on
 * to a lock screen; the opened, authenticated app fetches what it actually was.
 *
 * @package AxismundiPwa
 */

defined( 'ABSPATH' ) || exit;

/** @return bool Whether the library that does the cryptography is present. */
function axismundi_pwa_has_library() : bool {
	if ( ! class_exists( '\\Minishlink\\WebPush\\WebPush' ) && is_readable( __DIR__ . '/../vendor/autoload.php' ) ) {
		require_once __DIR__ . '/../vendor/autoload.php';
	}
	return class_exists( '\\Minishlink\\WebPush\\WebPush' );
}

/**
 * Wake one device.
 *
 * @param int                                $subscription_id Subscription row id.
 * @param array<string,mixed>                $payload         What to say: `delivery` and `category`.
 * @param \Psr\Http\Client\ClientInterface|null $client        Injected transport, for audits.
 * @return array{sent:bool,status:int,expired:bool,error:string}
 */
function axismundi_pwa_push( int $subscription_id, array $payload, $client = null ) : array {
	$failure = array( 'sent' => false, 'status' => 0, 'expired' => false, 'error' => '' );
	if ( ! axismundi_pwa_has_library() ) {
		$failure['error'] = 'library_missing';
		return $failure;
	}
	if ( ! axismundi_pwa_has_keys() ) {
		$failure['error'] = 'no_keys';
		return $failure;
	}
	$row = axismundi_pwa_subscription( $subscription_id );
	if ( ! is_array( $row ) || null !== $row['revoked_at'] ) {
		$failure['error'] = 'no_subscription';
		return $failure;
	}

	/**
	 * Filter the HTTP client used to reach a push service.
	 *
	 * Injected rather than only constructed, because the interesting answers -- a `410`, a service
	 * having a bad minute -- are the ones that cannot be arranged against a real push service on
	 * purpose. A site behind a proxy has the same need for a different reason.
	 *
	 * @param \Psr\Http\Client\ClientInterface|null $client Client, or null to let the library find one.
	 */
	$client = apply_filters( 'axismundi_pwa_http_client', $client );

	try {
		$push = new \Minishlink\WebPush\WebPush(
			array(
				'VAPID' => array(
					'subject'    => axismundi_pwa_vapid_subject(),
					'publicKey'  => axismundi_pwa_application_server_key(),
					'privateKey' => axismundi_pwa_signing_key(),
				),
			),
			array( 'TTL' => HOUR_IN_SECONDS ),
			$client
		);
		$report = $push->sendOneNotification(
			\Minishlink\WebPush\Subscription::create(
				array(
					'endpoint'        => (string) $row['endpoint'],
					'publicKey'       => (string) $row['p256dh_key'],
					'authToken'       => (string) $row['auth_key'],
					'contentEncoding' => 'aes128gcm',
				)
			),
			(string) wp_json_encode( axismundi_pwa_payload( $payload ) )
		);
	} catch ( Throwable $error ) {
		$failure['error'] = $error->getMessage();
		return $failure;
	}

	$status = 0;
	$response = $report->getResponse();
	if ( null !== $response ) {
		$status = (int) $response->getStatusCode();
	}
	/*
	 * `404` and `410` are the push service saying this endpoint is over. Revoked here rather than
	 * reported upward and forgotten, because the caller deciding whether to retry should not also
	 * have to know which HTTP codes mean a device is gone -- and one that did not know would keep a
	 * dead endpoint in the queue forever.
	 */
	$expired = $report->isSubscriptionExpired() || in_array( $status, array( 404, 410 ), true );
	if ( $expired ) {
		axismundi_pwa_revoke( (string) $row['endpoint'] );
	}
	return array(
		'sent'    => $report->isSuccess(),
		'status'  => $status,
		'expired' => $expired,
		'error'   => $report->isSuccess() ? '' : (string) $report->getReason(),
	);
}

/**
 * What may travel in a push message.
 *
 * Allowlisted rather than filtered, because the risk is not a field somebody thought about and
 * decided to strip -- it is the one added later without anybody thinking about it at all. An
 * endpoint is effectively a capability URL and a notification can be read off a locked screen, so
 * what goes through is an id to fetch and a word for what kind of thing it is.
 *
 * @param array<string,mixed> $payload Proposed payload.
 * @return array{delivery:int,category:string}
 */
function axismundi_pwa_payload( array $payload ) : array {
	return array(
		'delivery' => (int) ( $payload['delivery'] ?? 0 ),
		'category' => sanitize_key( (string) ( $payload['category'] ?? '' ) ),
	);
}

/**
 * One subscription row.
 *
 * @param int $subscription_id Row id.
 * @return array<string,mixed>|null
 */
function axismundi_pwa_subscription( int $subscription_id ) : ?array {
	global $wpdb;
	if ( $subscription_id <= 0 || ! axismundi_pwa_ready() ) {
		return null;
	}
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- primary-key lookup in this plugin's own table.
	$row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . axismundi_pwa_subscriptions_table() . ' WHERE id = %d', $subscription_id ), ARRAY_A );
	return is_array( $row ) ? $row : null;
}
