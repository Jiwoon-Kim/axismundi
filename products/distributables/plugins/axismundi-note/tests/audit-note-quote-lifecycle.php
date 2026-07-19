<?php
/**
 * Outbound Quote lifecycle branching and reconciliation regression (dev-only).
 *
 * @package AxismundiNote
 */

defined( 'ABSPATH' ) || exit( 1 );

global $wpdb;
$ax_nql_results        = array();
$ax_nql_post_ids       = array();
$ax_nql_user_ids       = array();
$ax_nql_actor_ids      = array();
$ax_nql_remote_objects = array();
$GLOBALS['ax_nql_errors'] = array();

/** @param bool[] $results Results. */
function ax_nql_assert( array &$results, string $label, bool $condition ) : void {
	$results[] = $condition;
	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI test output.
	printf( "[%s] %s\n", $condition ? 'PASS' : 'FAIL', $label );
}

/** Capture lifecycle errors for fixture diagnostics and assertions. */
function ax_nql_capture_error( WP_Error $error ) : void {
	$GLOBALS['ax_nql_errors'][] = $error->get_error_code();
}

/** Whether a finalized Object declares the required FEP-044f terms. */
function ax_nql_has_quote_context( array $object, bool $authorization = false ) : bool {
	$context = serialize( $object['@context'] ?? null );
	return is_string( $context )
		&& false !== strpos( $context, 'https://w3id.org/fep/044f#quote' )
		&& ( ! $authorization || false !== strpos( $context, 'https://w3id.org/fep/044f#quoteAuthorization' ) );
}

/** Create one public local author. */
function ax_nql_local_author( array &$user_ids, array &$actor_ids ) : ?WP_User {
	$login = 'ax_nql_' . strtolower( wp_generate_password( 8, false, false ) );
	$uid   = (int) wp_insert_user( array( 'user_login' => $login, 'user_pass' => wp_generate_password(), 'role' => 'author' ) );
	if ( $uid <= 0 ) {
		return null;
	}
	$user_ids[] = $uid;
	$actor = axismundi_actors_ensure_for_user( $uid );
	if ( $actor instanceof Axismundi_Actor ) {
		$actor_ids[] = $actor->get_identity_id();
		axismundi_actors_register_handle( $actor->get_identity_id(), $login );
		axismundi_actors_set_status( $actor->get_identity_id(), 'public' );
	}
	return get_userdata( $uid ) ?: null;
}

/** Create one deliverable cached remote Actor. */
function ax_nql_remote_actor( array &$actor_ids, string $slug ) : ?Axismundi_Actor {
	$uri     = 'https://example.com/actors/' . $slug;
	$payload = array(
		'id'                => $uri,
		'type'              => 'Person',
		'preferredUsername' => $slug,
		'inbox'             => $uri . '/inbox',
		'outbox'            => $uri . '/outbox',
	);
	$record = axismundi_actors_normalize_remote_actor_payload( $payload, $uri );
	$actor  = is_array( $record ) ? axismundi_actors_upsert_remote( $record ) : null;
	if ( $actor instanceof Axismundi_Actor ) {
		$actor_ids[] = $actor->get_identity_id();
		return $actor;
	}
	return null;
}

/** Create a draft Note and optionally author a quote target. */
function ax_nql_note( array &$post_ids, int $author_id, string $content, string $quote_target = '' ) : array {
	$post_id = (int) wp_insert_post(
		array(
			'post_type'    => AXISMUNDI_NOTE_POST_TYPE,
			'post_status'  => 'draft',
			'post_author'  => $author_id,
			'post_content' => $content,
		)
	);
	$post_ids[] = $post_id;
	if ( '' !== $quote_target ) {
		axismundi_note_save( $post_id, array( 'quote_target_uri' => $quote_target ) );
	}
	$envelope = axismundi_note_get( $post_id );
	return array(
		'post_id' => $post_id,
		'uri'     => is_array( $envelope ) ? axismundi_note_object_uri( (string) $envelope['local_uuid'] ) : '',
	);
}

/** Publish one fixture Note. */
function ax_nql_publish( int $post_id ) : ?WP_Post {
	wp_update_post( array( 'ID' => $post_id, 'post_status' => 'publish' ) );
	$post = get_post( $post_id );
	return $post instanceof WP_Post ? $post : null;
}

/** Store one remote Note target. */
function ax_nql_remote_target( array &$remote_objects, Axismundi_Actor $actor, string $slug ) : string {
	$uri = 'https://remote.example/notes/' . $slug;
	$stored = axismundi_op_remote_object_store(
		array(
			'id'           => $uri,
			'type'         => 'Note',
			'attributedTo' => $actor->get_uri(),
			'content'      => 'Remote quote target.',
		)
	);
	if ( is_array( $stored ) ) {
		$remote_objects[] = $uri;
		return $uri;
	}
	return '';
}

/** Cache one exact remote QuoteAuthorization document. */
function ax_nql_remote_authorization( array &$remote_objects, string $uri, Axismundi_Activity $request, string $target_actor_uri, string $interaction_target = '' ) : bool {
	$stored = axismundi_op_remote_object_store(
		array(
			'id'                => $uri,
			'type'              => 'QuoteAuthorization',
			'attributedTo'      => $target_actor_uri,
			'interactingObject' => (string) $request->get_instrument_uri(),
			'interactionTarget' => '' !== $interaction_target ? $interaction_target : (string) $request->get_object_uri(),
		)
	);
	if ( is_array( $stored ) ) {
		$remote_objects[] = $uri;
		return true;
	}
	return false;
}

try {
	add_action( 'axismundi_note_lifecycle_failed', 'ax_nql_capture_error', 10, 1 );

	$author = ax_nql_local_author( $ax_nql_user_ids, $ax_nql_actor_ids );
	$other  = ax_nql_local_author( $ax_nql_user_ids, $ax_nql_actor_ids );
	$actor  = $author instanceof WP_User ? axismundi_actors_get_for_user( $author->ID ) : null;
	$other_actor = $other instanceof WP_User ? axismundi_actors_get_for_user( $other->ID ) : null;
	$actor_uri       = $actor instanceof Axismundi_Actor ? $actor->get_uri() : '';
	$other_actor_uri = $other_actor instanceof Axismundi_Actor ? $other_actor->get_uri() : '';
	ax_nql_assert( $ax_nql_results, 'the fixture created two public local authors', '' !== $actor_uri && '' !== $other_actor_uri );

	$self_target = ax_nql_note( $ax_nql_post_ids, (int) $author->ID, 'Self target.' );
	ax_nql_publish( $self_target['post_id'] );
	$self_quote = ax_nql_note( $ax_nql_post_ids, (int) $author->ID, 'Self quote.', $self_target['uri'] );
	ax_nql_publish( $self_quote['post_id'] );
	$self_lifecycle = axismundi_act_get_object_lifecycle( $self_quote['uri'] );
	$self_object    = $self_lifecycle instanceof Axismundi_Activity ? $self_lifecycle->get_payload()['object'] ?? array() : array();
	$self_request   = axismundi_act_get_outbound_quote_request( $self_quote['uri'], $self_target['uri'], $actor_uri, $actor_uri );
	$self_source = new Axismundi_Note_Source( axismundi_note_get( $self_quote['post_id'] ), get_post( $self_quote['post_id'] ) );
	$self_projection = axismundi_op_transform_object( $self_source );
	ax_nql_assert( $ax_nql_results, 'a self-quote records Create and remains publicly re-projectable without fabricated authorization', $self_lifecycle instanceof Axismundi_Activity && 'Create' === $self_lifecycle->get_type() && $self_target['uri'] === ( $self_object['quote'] ?? '' ) && ! isset( $self_object['quoteAuthorization'] ) && ax_nql_has_quote_context( $self_object ) && null === $self_request && is_array( $self_projection ) && $self_target['uri'] === ( $self_projection['quote'] ?? '' ) && ! isset( $self_projection['quoteAuthorization'] ) );

	$local_target = ax_nql_note( $ax_nql_post_ids, (int) $other->ID, 'Local target.' );
	axismundi_note_save( $local_target['post_id'], array( 'quote_policy' => 'anyone' ) );
	ax_nql_publish( $local_target['post_id'] );
	$local_quote = ax_nql_note( $ax_nql_post_ids, (int) $author->ID, 'Local approved quote.', $local_target['uri'] );
	ax_nql_publish( $local_quote['post_id'] );
	$local_request = axismundi_act_get_outbound_quote_request( $local_quote['uri'], $local_target['uri'], $actor_uri, $other_actor_uri );
	$local_decision = $local_request instanceof Axismundi_Activity ? axismundi_act_outbound_quote_decision( $local_request->get_uri() ) : null;
	$local_lifecycle = axismundi_act_get_object_lifecycle( $local_quote['uri'] );
	$local_object = $local_lifecycle instanceof Axismundi_Activity ? $local_lifecycle->get_payload()['object'] ?? array() : array();
	ax_nql_assert( $ax_nql_results, 'a local-other quote runs the local state machine and commits only with mapped authorization evidence', $local_request instanceof Axismundi_Activity && 'local' === $local_request->get_direction() && is_array( $local_decision ) && 'accepted' === $local_decision['decision'] && $local_lifecycle instanceof Axismundi_Activity && $local_decision['authorization_uri'] === ( $local_object['quoteAuthorization'] ?? '' ) && ax_nql_has_quote_context( $local_object, true ) );

	$remote_actor = ax_nql_remote_actor( $ax_nql_actor_ids, 'quote-' . strtolower( wp_generate_password( 6, false, false ) ) );
	$remote_target = $remote_actor instanceof Axismundi_Actor ? ax_nql_remote_target( $ax_nql_remote_objects, $remote_actor, strtolower( wp_generate_password( 6, false, false ) ) ) : '';
	$remote_quote = ax_nql_note( $ax_nql_post_ids, (int) $author->ID, 'Remote held quote.', $remote_target );
	ax_nql_publish( $remote_quote['post_id'] );
	$remote_request = $remote_actor instanceof Axismundi_Actor ? axismundi_act_get_outbound_quote_request( $remote_quote['uri'], $remote_target, $actor_uri, $remote_actor->get_uri() ) : null;
	$remote_instrument = $remote_request instanceof Axismundi_Activity ? $remote_request->get_payload()['instrument'] ?? array() : array();
	$remote_pending_source = new Axismundi_Note_Source( axismundi_note_get( $remote_quote['post_id'] ), get_post( $remote_quote['post_id'] ) );
	$remote_pending_status = axismundi_note_quote_status( get_post( $remote_quote['post_id'] ) );
	ax_nql_assert( $ax_nql_results, 'a remote quote records one request and stays hidden while its finalized inline instrument awaits approval', $remote_request instanceof Axismundi_Activity && 'outbound' === $remote_request->get_direction() && is_array( $remote_instrument ) && ax_nql_has_quote_context( $remote_instrument ) && null === axismundi_act_get_object_lifecycle( $remote_quote['uri'] ) && 'pending' === $remote_pending_status['state'] && ! axismundi_note_source_visible( $remote_pending_source ) );

	$remote_auth_uri = 'https://remote.example/authorizations/' . strtolower( wp_generate_password( 8, false, false ) );
	$auth_cached = $remote_request instanceof Axismundi_Activity && $remote_actor instanceof Axismundi_Actor
		? ax_nql_remote_authorization( $ax_nql_remote_objects, $remote_auth_uri, $remote_request, $remote_actor->get_uri() )
		: false;
	$accept = $remote_request instanceof Axismundi_Activity && $remote_actor instanceof Axismundi_Actor
		? axismundi_act_record_activity(
			array(
				'id'     => 'https://example.com/activities/' . wp_generate_uuid4(),
				'type'   => 'Accept',
				'actor'  => $remote_actor->get_uri(),
				'object' => $remote_request->get_uri(),
				'result' => $remote_auth_uri,
				'to'     => array( $actor_uri ),
			),
			'inbound'
		)
		: null;
	$remote_lifecycle = axismundi_act_get_object_lifecycle( $remote_quote['uri'] );
	$remote_object = $remote_lifecycle instanceof Axismundi_Activity ? $remote_lifecycle->get_payload()['object'] ?? array() : array();
	$remote_accepted_source = new Axismundi_Note_Source( axismundi_note_get( $remote_quote['post_id'] ), get_post( $remote_quote['post_id'] ) );
	$remote_projection = axismundi_op_transform_object( $remote_accepted_source );
	$remote_accepted_status = axismundi_note_quote_status( get_post( $remote_quote['post_id'] ) );
	if ( ! $remote_lifecycle instanceof Axismundi_Activity ) {
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI fixture diagnostic.
		printf( "[INFO] remote lifecycle errors: %s\n", implode( ',', $GLOBALS['ax_nql_errors'] ) );
	}
	ax_nql_assert( $ax_nql_results, 'a signed remote Accept wakes one Create and the route re-projects the same verified evidence', $auth_cached && $accept instanceof Axismundi_Activity && $remote_lifecycle instanceof Axismundi_Activity && 'Create' === $remote_lifecycle->get_type() && $remote_auth_uri === ( $remote_object['quoteAuthorization'] ?? '' ) && ax_nql_has_quote_context( $remote_object, true ) && 'accepted' === $remote_accepted_status['state'] && is_array( $remote_projection ) && $remote_auth_uri === ( $remote_projection['quoteAuthorization'] ?? '' ) && axismundi_note_source_visible( $remote_accepted_source ) );

	$rejected_target = $remote_actor instanceof Axismundi_Actor ? ax_nql_remote_target( $ax_nql_remote_objects, $remote_actor, 'rejected-' . strtolower( wp_generate_password( 6, false, false ) ) ) : '';
	$rejected_quote  = ax_nql_note( $ax_nql_post_ids, (int) $author->ID, 'Rejected quote.', $rejected_target );
	ax_nql_publish( $rejected_quote['post_id'] );
	$rejected_request = $remote_actor instanceof Axismundi_Actor ? axismundi_act_get_outbound_quote_request( $rejected_quote['uri'], $rejected_target, $actor_uri, $remote_actor->get_uri() ) : null;
	$reject = $rejected_request instanceof Axismundi_Activity && $remote_actor instanceof Axismundi_Actor
		? axismundi_act_record_activity(
			array(
				'id'     => 'https://example.com/activities/' . wp_generate_uuid4(),
				'type'   => 'Reject',
				'actor'  => $remote_actor->get_uri(),
				'object' => $rejected_request->get_uri(),
				'to'     => array( $actor_uri ),
			),
			'inbound'
		)
		: null;
	$rejected_decision = $rejected_request instanceof Axismundi_Activity ? axismundi_act_outbound_quote_decision( $rejected_request->get_uri() ) : null;
	$rejected_source = new Axismundi_Note_Source( axismundi_note_get( $rejected_quote['post_id'] ), get_post( $rejected_quote['post_id'] ) );
	$rejected_status = axismundi_note_quote_status( get_post( $rejected_quote['post_id'] ) );
	ax_nql_assert( $ax_nql_results, 'a remote Reject is durable and keeps both Create and the public route closed', $reject instanceof Axismundi_Activity && is_array( $rejected_decision ) && 'rejected' === $rejected_decision['decision'] && null === axismundi_act_get_object_lifecycle( $rejected_quote['uri'] ) && 'rejected' === $rejected_status['state'] && ! axismundi_note_source_visible( $rejected_source ) );

	// Removing and re-adding the same target is an explicit retry. It must mint a
	// new request generation rather than resolving forever to the immutable Reject.
	axismundi_note_save( $rejected_quote['post_id'], array( 'quote_target_uri' => '' ) );
	$retried_envelope = axismundi_note_save( $rejected_quote['post_id'], array( 'quote_target_uri' => $rejected_target ) );
	$retry_result     = axismundi_note_reconcile_quote( $rejected_quote['post_id'] );
	$retried_request  = $remote_actor instanceof Axismundi_Actor ? axismundi_act_get_outbound_quote_request( $rejected_quote['uri'], $rejected_target, $actor_uri, $remote_actor->get_uri(), (int) ( $retried_envelope['quote_generation'] ?? 0 ) ) : null;
	$retried_status   = axismundi_note_quote_status( get_post( $rejected_quote['post_id'] ) );
	ax_nql_assert( $ax_nql_results, 'removing and re-adding a rejected target records a fresh pending QuoteRequest generation', is_array( $retried_envelope ) && (int) $retried_envelope['quote_generation'] > 1 && is_wp_error( $retry_result ) && 'ax_note_quote_pending' === $retry_result->get_error_code() && $retried_request instanceof Axismundi_Activity && ! hash_equals( $rejected_request->get_uri(), $retried_request->get_uri() ) && null === axismundi_act_outbound_quote_decision( $retried_request->get_uri() ) && 'pending' === $retried_status['state'] );

	$bad_auth_target = $remote_actor instanceof Axismundi_Actor ? ax_nql_remote_target( $ax_nql_remote_objects, $remote_actor, 'bad-auth-' . strtolower( wp_generate_password( 6, false, false ) ) ) : '';
	$bad_auth_quote  = ax_nql_note( $ax_nql_post_ids, (int) $author->ID, 'Mismatched authorization quote.', $bad_auth_target );
	ax_nql_publish( $bad_auth_quote['post_id'] );
	$bad_auth_request = $remote_actor instanceof Axismundi_Actor ? axismundi_act_get_outbound_quote_request( $bad_auth_quote['uri'], $bad_auth_target, $actor_uri, $remote_actor->get_uri() ) : null;
	$bad_auth_uri = 'https://remote.example/authorizations/' . strtolower( wp_generate_password( 8, false, false ) );
	$bad_auth_cached = $bad_auth_request instanceof Axismundi_Activity && $remote_actor instanceof Axismundi_Actor
		? ax_nql_remote_authorization( $ax_nql_remote_objects, $bad_auth_uri, $bad_auth_request, $remote_actor->get_uri(), 'https://remote.example/notes/wrong-target' )
		: false;
	$GLOBALS['ax_nql_errors'] = array();
	$bad_accept = $bad_auth_request instanceof Axismundi_Activity && $remote_actor instanceof Axismundi_Actor
		? axismundi_act_record_activity(
			array(
				'id'     => 'https://example.com/activities/' . wp_generate_uuid4(),
				'type'   => 'Accept',
				'actor'  => $remote_actor->get_uri(),
				'object' => $bad_auth_request->get_uri(),
				'result' => $bad_auth_uri,
				'to'     => array( $actor_uri ),
			),
			'inbound'
		)
		: null;
	ax_nql_assert( $ax_nql_results, 'an Accept whose authorization names a different target cannot open Create', $bad_auth_cached && $bad_accept instanceof Axismundi_Activity && null === axismundi_act_get_object_lifecycle( $bad_auth_quote['uri'] ) && in_array( 'ax_note_quote_authorization', $GLOBALS['ax_nql_errors'], true ) );

	$recovery_target = $remote_actor instanceof Axismundi_Actor ? ax_nql_remote_target( $ax_nql_remote_objects, $remote_actor, 'recovery-' . strtolower( wp_generate_password( 6, false, false ) ) ) : '';
	$recovery_quote  = ax_nql_note( $ax_nql_post_ids, (int) $author->ID, 'Recovery quote.', $recovery_target );
	ax_nql_publish( $recovery_quote['post_id'] );
	$recovery_request = $remote_actor instanceof Axismundi_Actor ? axismundi_act_get_outbound_quote_request( $recovery_quote['uri'], $recovery_target, $actor_uri, $remote_actor->get_uri() ) : null;
	$recovery_auth_uri = 'https://remote.example/authorizations/' . strtolower( wp_generate_password( 8, false, false ) );
	$recovery_cached = $recovery_request instanceof Axismundi_Activity && $remote_actor instanceof Axismundi_Actor
		? ax_nql_remote_authorization( $ax_nql_remote_objects, $recovery_auth_uri, $recovery_request, $remote_actor->get_uri() )
		: false;
	remove_action( 'axismundi_act_outbound_quote_decided', 'axismundi_note_quote_decided', 20 );
	$recovery_accept = $recovery_request instanceof Axismundi_Activity && $remote_actor instanceof Axismundi_Actor
		? axismundi_act_record_activity(
			array(
				'id'     => 'https://example.com/activities/' . wp_generate_uuid4(),
				'type'   => 'Accept',
				'actor'  => $remote_actor->get_uri(),
				'object' => $recovery_request->get_uri(),
				'result' => $recovery_auth_uri,
				'to'     => array( $actor_uri ),
			),
			'inbound'
		)
		: null;
	$before_reconcile = axismundi_act_get_object_lifecycle( $recovery_quote['uri'] );
	add_action( 'axismundi_act_outbound_quote_decided', 'axismundi_note_quote_decided', 20, 3 );
	$reconciled = axismundi_note_reconcile_quote( $recovery_quote['post_id'] );
	$after_reconcile = axismundi_act_get_object_lifecycle( $recovery_quote['uri'] );
	ax_nql_assert( $ax_nql_results, 'an accepted request survives a lost wake-up and reconcile records exactly one Create', $recovery_cached && $recovery_accept instanceof Axismundi_Activity && null === $before_reconcile && $reconciled instanceof Axismundi_Activity && $after_reconcile instanceof Axismundi_Activity && $reconciled->get_id() === $after_reconcile->get_id() );

	$draft_target = ax_nql_note( $ax_nql_post_ids, (int) $author->ID, 'Unpublished target.' );
	$bad_quote    = ax_nql_note( $ax_nql_post_ids, (int) $author->ID, 'Must remain held.', $draft_target['uri'] );
	ax_nql_publish( $bad_quote['post_id'] );
	ax_nql_assert( $ax_nql_results, 'a self-owned draft target cannot use the self exception to open Create', null === axismundi_act_get_object_lifecycle( $bad_quote['uri'] ) );
} finally {
	remove_action( 'axismundi_note_lifecycle_failed', 'ax_nql_capture_error', 10 );
	add_action( 'axismundi_act_outbound_quote_decided', 'axismundi_note_quote_decided', 20, 3 );
	foreach ( array_unique( $ax_nql_remote_objects ) as $uri ) {
		axismundi_op_remote_object_delete( $uri );
	}
	foreach ( array_unique( $ax_nql_post_ids ) as $post_id ) {
		$wpdb->delete( axismundi_note_table(), array( 'post_id' => (int) $post_id ), array( '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		if ( get_post( (int) $post_id ) instanceof WP_Post ) {
			wp_delete_post( (int) $post_id, true );
		}
	}
	$actor_hashes = array();
	foreach ( array_unique( $ax_nql_actor_ids ) as $identity_id ) {
		$identity = $wpdb->get_row( $wpdb->prepare( 'SELECT canonical_uri FROM ' . axismundi_actors_identities_table() . ' WHERE id = %d', $identity_id ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery
		if ( is_array( $identity ) ) {
			$actor_hashes[] = hash( 'sha256', (string) $identity['canonical_uri'] );
		}
	}
	foreach ( $actor_hashes as $hash ) {
		$wpdb->delete( axismundi_act_activities_table(), array( 'actor_uri_hash' => $hash ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->delete( axismundi_act_quote_authorizations_table(), array( 'requester_actor_uri_hash' => $hash ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->delete( axismundi_act_quote_authorizations_table(), array( 'author_actor_uri_hash' => $hash ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	}
	foreach ( array_unique( $ax_nql_actor_ids ) as $identity_id ) {
		foreach ( array( axismundi_actors_texts_table(), axismundi_actors_addresses_table(), axismundi_actors_endpoints_table(), axismundi_actors_asset_cache_table(), axismundi_actors_keys_table(), axismundi_actors_fetch_state_table() ) as $actor_table ) {
			$wpdb->delete( $actor_table, array( 'identity_id' => (int) $identity_id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		}
		$wpdb->delete( axismundi_actors_actors_table(), array( 'identity_id' => (int) $identity_id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->delete( axismundi_actors_identities_table(), array( 'id' => (int) $identity_id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	}
	if ( ! empty( $ax_nql_user_ids ) ) {
		require_once ABSPATH . 'wp-admin/includes/user.php';
		foreach ( array_unique( $ax_nql_user_ids ) as $user_id ) {
			if ( get_userdata( (int) $user_id ) ) {
				wp_delete_user( (int) $user_id );
			}
		}
	}
}

$ax_nql_failures = count( array_filter( $ax_nql_results, static fn( bool $result ) : bool => ! $result ) );
// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI test output.
printf( "\n== %d checks, %d failed ==\n", count( $ax_nql_results ), $ax_nql_failures );
if ( class_exists( 'WP_CLI' ) ) {
	WP_CLI::halt( $ax_nql_failures > 0 ? 1 : 0 );
}
exit( $ax_nql_failures > 0 ? 1 : 0 );
