<?php
/**
 * FEP-044f outbound QuoteRequest emission and decision reconciliation.
 *
 * A local Note that quotes another Object records an outbound (or, for a local
 * target, `local`) QuoteRequest carrying the finalized quoting Note inline, so a
 * private (followers/mentioned) Note can be reviewed without a signed dereference.
 * The Activities ledger is the durable authority for the decision; the
 * `axismundi_act_outbound_quote_decided` action is only an immediate wake-up
 * signal, and `axismundi_act_outbound_quote_decision()` re-reads the same ledger
 * so a consumer can reconcile after a mid-write failure.
 *
 * @package AxismundiActivities
 */

defined( 'ABSPATH' ) || exit;

/**
 * Record one QuoteRequest, idempotent per quoting Object, quoted Object, target, and generation.
 *
 * The generation lets an author explicitly re-request after a terminal decision or
 * a target change without colliding with the immutable first request. Slice-3 (Note)
 * owns when a new generation is minted; this primitive only keys on it.
 *
 * @param array<string,mixed> $args instrument (inline finalized object),
 *                                  quoted_object_uri, author_actor_uri,
 *                                  target_author_uri, [generation], [direction].
 * @return Axismundi_Activity|WP_Error
 */
function axismundi_act_record_outbound_quote_request( array $args ) {
	$instrument = $args['instrument'] ?? null;
	$quoted     = axismundi_act_uri( (string) ( $args['quoted_object_uri'] ?? '' ) );
	$author     = axismundi_act_uri( (string) ( $args['author_actor_uri'] ?? '' ) );
	$target     = axismundi_act_uri( (string) ( $args['target_author_uri'] ?? '' ) );
	$generation = max( 1, (int) ( $args['generation'] ?? 1 ) );
	$direction  = in_array( ( $args['direction'] ?? '' ), array( 'outbound', 'local' ), true ) ? (string) $args['direction'] : 'outbound';
	// The quoting Note must be inlined as a finalized object so a private, held Note
	// can be reviewed without a signed dereference; a bare URI is refused here so the
	// contract cannot be broken by a caller mistake.
	if ( ! is_array( $instrument ) || array_is_list( $instrument ) ) {
		return new WP_Error( 'ax_act_outbound_quote_instrument', __( 'An outbound QuoteRequest must inline the finalized quoting object.', 'axismundi-activities' ) );
	}
	$quoting    = axismundi_act_member_uri( $instrument['id'] ?? '' );
	$attributed = axismundi_act_member_uri( $instrument['attributedTo'] ?? '' );
	if ( '' === $quoting || '' === $quoted || '' === $author || '' === $target || hash_equals( $quoting, $quoted ) ) {
		return new WP_Error( 'ax_act_outbound_quote_args', __( 'An outbound QuoteRequest requires distinct quoting and quoted Objects, an author, and a target.', 'axismundi-activities' ) );
	}
	if ( '' !== $attributed && ! hash_equals( $author, $attributed ) ) {
		return new WP_Error( 'ax_act_outbound_quote_attribution', __( 'The inlined quoting object is attributed to a different Actor.', 'axismundi-activities' ) );
	}
	return axismundi_act_record_source_activity(
		array(
			'type'       => 'QuoteRequest',
			'actor'      => $author,
			'object'     => $quoted,
			'instrument' => $instrument,
			'to'         => array( $target ),
		),
		$direction,
		'outbound-quote-request:' . hash( 'sha256', $quoting . '|' . $quoted . '|' . $target . '|' . $generation )
	);
}

/**
 * Find the latest request for one exact quoting/quoted/author/target tuple.
 *
 * The target lives in the audience rather than an indexed identity column, so
 * candidates are cursor-paged by the other exact URI references and verified
 * against the lossless payload. No fixed result window may hide a prior request.
 */
function axismundi_act_get_outbound_quote_request( string $quoting_uri, string $quoted_uri, string $author_uri, string $target_uri ) : ?Axismundi_Activity {
	global $wpdb;
	$quoting = axismundi_act_uri( $quoting_uri );
	$quoted  = axismundi_act_uri( $quoted_uri );
	$author  = axismundi_act_uri( $author_uri );
	$target  = axismundi_act_uri( $target_uri );
	if ( '' === $quoting || '' === $quoted || '' === $author || '' === $target ) {
		return null;
	}
	$table  = axismundi_act_activities_table();
	$page   = 200;
	$cursor = PHP_INT_MAX;
	do {
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fixed table and prepared values.
		$rows = (array) $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE id < %d AND activity_type = 'QuoteRequest' AND direction IN ('outbound','local') AND actor_uri_hash = %s AND actor_uri = %s AND object_uri_hash = %s AND object_uri = %s AND instrument_uri_hash = %s AND instrument_uri = %s ORDER BY id DESC LIMIT {$page}",
				$cursor,
				hash( 'sha256', $author ),
				$author,
				hash( 'sha256', $quoted ),
				$quoted,
				hash( 'sha256', $quoting ),
				$quoting
			),
			ARRAY_A
		);
		foreach ( $rows as $row ) {
			$cursor   = min( $cursor, (int) $row['id'] );
			$request  = axismundi_act_hydrate( $row );
			$audience = $request->get_audience();
			foreach ( (array) ( $audience['to'] ?? array() ) as $recipient ) {
				if ( is_string( $recipient ) && hash_equals( $target, $recipient ) ) {
					return $request;
				}
			}
		}
	} while ( count( $rows ) === $page );
	return null;
}

/**
 * Whether one Accept/Reject's embedded QuoteRequest matches the stored request.
 *
 * A URI-only reference is allowed. An inlined object must match every field it
 * provides exactly, so a decider cannot re-bind our request to different members.
 */
function axismundi_act_outbound_quote_object_matches( Axismundi_Activity $decision, Axismundi_Activity $request ) : bool {
	$object = $decision->get_payload()['object'] ?? null;
	if ( ! is_array( $object ) || array_is_list( $object ) ) {
		return true;
	}
	$expected = array(
		'type'       => 'QuoteRequest',
		'actor'      => $request->get_actor_uri(),
		'object'     => (string) $request->get_object_uri(),
		'instrument' => (string) $request->get_instrument_uri(),
	);
	foreach ( $expected as $field => $value ) {
		if ( ! array_key_exists( $field, $object ) ) {
			continue;
		}
		$actual = 'type' === $field ? (string) $object[ $field ] : axismundi_act_member_uri( $object[ $field ] );
		if ( ! hash_equals( (string) $value, (string) $actual ) ) {
			return false;
		}
	}
	return true;
}

/**
 * Reconcile the immutable first decision for one of our outbound QuoteRequests.
 *
 * The first valid Accept or Reject wins and never flips: a later Reject after an
 * Accept (or the reverse) is ignored, so a fresh attempt must be a new generation.
 * Depends only on committed Activities, so a consumer re-reads it after a crash.
 *
 * @return array{request:Axismundi_Activity,decision:string,authorization_uri:string,deciding_activity_uri:string}|null
 */
function axismundi_act_outbound_quote_decision( string $request_uri ) : ?array {
	$request_uri = axismundi_act_uri( $request_uri );
	if ( '' === $request_uri ) {
		return null;
	}
	$request = axismundi_act_get( $request_uri );
	if ( ! $request instanceof Axismundi_Activity
		|| 'QuoteRequest' !== $request->get_type()
		|| ! in_array( $request->get_direction(), array( 'outbound', 'local' ), true )
	) {
		return null;
	}
	$targets = array_values( array_filter( (array) ( $request->get_audience()['to'] ?? array() ), 'is_string' ) );
	if ( empty( $targets ) ) {
		return null;
	}
	global $wpdb;
	$table        = axismundi_act_activities_table();
	$hashes       = array_map( static fn( string $t ) : string => hash( 'sha256', $t ), $targets );
	$placeholders = implode( ',', array_fill( 0, count( $hashes ), '%s' ) );
	$page         = 200;
	$cursor       = 0;
	// Page the whole ledger with an id cursor scoped to this request's object and its
	// addressed target Actors, so no bounded pile of earlier invalid responses can
	// hide the immutable first valid decision behind a single fetch window.
	do {
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fixed table + %s/%d placeholders; values prepared.
		$rows = (array) $wpdb->get_results(
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $placeholders is only %s tokens.
				"SELECT * FROM {$table} WHERE id > %d AND object_uri_hash = %s AND object_uri = %s AND activity_type IN ('Accept','Reject') AND actor_uri_hash IN ({$placeholders}) ORDER BY id ASC LIMIT {$page}",
				array_merge( array( $cursor, hash( 'sha256', $request_uri ), $request_uri ), $hashes )
			),
			ARRAY_A
		);
		foreach ( $rows as $row ) {
			$cursor   = max( $cursor, (int) $row['id'] );
			$activity = axismundi_act_hydrate( $row );
			if ( ! in_array( $activity->get_direction(), array( 'inbound', 'local' ), true ) ) {
				continue;
			}
			$decider   = $activity->get_actor_uri();
			$is_target = false;
			foreach ( $targets as $target ) {
				if ( hash_equals( $target, $decider ) ) {
					$is_target = true;
					break;
				}
			}
			if ( ! $is_target || ! axismundi_act_outbound_quote_object_matches( $activity, $request ) ) {
				continue;
			}
			if ( 'Accept' === $activity->get_type() && '' === axismundi_act_member_uri( $activity->get_payload()['result'] ?? '' ) ) {
				// An Accept without a QuoteAuthorization result is not a completed decision.
				continue;
			}
			// First valid decision in ledger order is immutable.
			return array(
				'request'               => $request,
				'decision'              => 'Accept' === $activity->get_type() ? 'accepted' : 'rejected',
				'authorization_uri'     => 'Accept' === $activity->get_type() ? axismundi_act_member_uri( $activity->get_payload()['result'] ?? '' ) : '',
				'deciding_activity_uri' => $activity->get_uri(),
			);
		}
	} while ( count( $rows ) === $page );
	return null;
}

/**
 * Fire the outbound-quote decision signal only for the first valid decision.
 *
 * The heavy validation lives in the reconcile query, so the event and the durable
 * reconciliation never disagree; and the signal fires exactly once — when the
 * committing Activity is the immutable first decision, never on a later duplicate.
 */
function axismundi_act_maybe_decide_outbound_quote( Axismundi_Activity $activity ) : void {
	if ( ! in_array( $activity->get_type(), array( 'Accept', 'Reject' ), true )
		|| ! in_array( $activity->get_direction(), array( 'inbound', 'local' ), true )
	) {
		return;
	}
	$request_uri = (string) $activity->get_object_uri();
	if ( '' === $request_uri ) {
		return;
	}
	$decision = axismundi_act_outbound_quote_decision( $request_uri );
	if ( null === $decision || ! hash_equals( (string) $decision['deciding_activity_uri'], $activity->get_uri() ) ) {
		return;
	}
	/**
	 * Fires when one of our outbound QuoteRequests reaches its first valid decision.
	 *
	 * A wake-up signal only. The ledger is authoritative; a consumer must call an
	 * idempotent ensure-Create and reconcile from `axismundi_act_outbound_quote_decision()`.
	 *
	 * @param Axismundi_Activity $request           The outbound QuoteRequest.
	 * @param string             $decision          'accepted' or 'rejected'.
	 * @param string             $authorization_uri QuoteAuthorization URI, or '' on reject.
	 */
	do_action( 'axismundi_act_outbound_quote_decided', $decision['request'], $decision['decision'], $decision['authorization_uri'] );
}
add_action( 'axismundi_act_activity_recorded', 'axismundi_act_maybe_decide_outbound_quote', 15 );
