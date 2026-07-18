<?php
/** FEP-044f QuoteAuthorization representation regression (dev-only). */

defined( 'ABSPATH' ) || exit( 1 );

global $wpdb;
$ax_op_qa_results    = array();
$ax_op_qa_users      = array();
$ax_op_qa_identities = array();
$ax_op_qa_old_get    = $_GET;
$ax_op_qa_old_uri    = $_SERVER['REQUEST_URI'] ?? null;
$ax_op_qa_suffix     = strtolower( wp_generate_password( 8, false, false ) );

function ax_op_qa_assert( array &$results, string $label, bool $condition ) : void {
	$results[] = $condition;
	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI fixture output.
	printf( "[%s] %s\n", $condition ? 'PASS' : 'FAIL', $label );
}

try {
	$user_id = wp_create_user( 'axopqa_' . $ax_op_qa_suffix, wp_generate_password( 24 ), 'axopqa_' . $ax_op_qa_suffix . '@example.test' );
	if ( ! is_wp_error( $user_id ) ) {
		$ax_op_qa_users[] = (int) $user_id;
	}
	$author = ! is_wp_error( $user_id ) ? axismundi_actors_ensure_for_user( (int) $user_id ) : null;
	if ( $author instanceof Axismundi_Actor ) {
		axismundi_actors_register_handle( $author->get_identity_id(), 'axopqa_' . $ax_op_qa_suffix );
		axismundi_actors_set_status( $author->get_identity_id(), 'public' );
		$author = axismundi_actors_get_for_user( (int) $user_id );
		$ax_op_qa_identities[] = $author->get_identity_id();
	}
	$uuid = wp_generate_uuid4();
	$authorization = array(
		'uuid'                => $uuid,
		'authorization_uri'   => axismundi_act_quote_authorization_uri( $uuid ),
		'author_actor_uri'    => $author instanceof Axismundi_Actor ? $author->get_uri() : '',
		'quoting_object_uri'  => 'https://remote.example/users/bob/statuses/' . $ax_op_qa_suffix,
		'quoted_object_uri'   => add_query_arg( 'p', wp_rand( 1000, 9999 ), home_url( '/' ) ),
		'status'              => 'active',
		'revoked_at'          => null,
	);
	$document = axismundi_op_quote_authorization_document( $authorization );
	$context  = is_array( $document ) ? (array) $document['@context'] : array();
	ax_op_qa_assert(
		$ax_op_qa_results,
		'an active stamp emits the exact FEP members as URI references with renderer-owned context',
		is_array( $document )
			&& 'QuoteAuthorization' === $document['type']
			&& $authorization['authorization_uri'] === $document['id']
			&& $authorization['author_actor_uri'] === $document['attributedTo']
			&& $authorization['quoting_object_uri'] === $document['interactingObject']
			&& $authorization['quoted_object_uri'] === $document['interactionTarget']
			&& in_array( 'https://www.w3.org/ns/activitystreams', $context, true )
			&& false !== array_search( 'https://w3id.org/fep/044f#QuoteAuthorization', array_column( array_filter( $context, 'is_array' ), 'QuoteAuthorization' ), true )
			&& is_string( $document['interactingObject'] ) && is_string( $document['interactionTarget'] )
	);

	$revoked = array_merge( $authorization, array( 'status' => 'revoked', 'revoked_at' => '2026-07-18 00:00:00' ) );
	$tombstone = axismundi_op_quote_authorization_tombstone( $revoked );
	ax_op_qa_assert(
		$ax_op_qa_results,
		'a revoked stamp becomes a 410-ready Tombstone without leaking either referenced Object',
		'Tombstone' === $tombstone['type'] && 'QuoteAuthorization' === $tombstone['formerType']
			&& isset( $tombstone['deleted'] )
			&& ! isset( $tombstone['attributedTo'], $tombstone['interactingObject'], $tombstone['interactionTarget'] )
	);

	$home_path = (string) wp_parse_url( home_url( '/' ), PHP_URL_PATH );
	$_GET = array( 'ax_quote_authorization' => $uuid );
	$_SERVER['REQUEST_URI'] = trailingslashit( $home_path ) . '?ax_quote_authorization=' . rawurlencode( $uuid );
	$canonical = axismundi_op_quote_authorization_request_uuid();
	$_GET['extra'] = '1';
	$extra = axismundi_op_quote_authorization_request_uuid();
	$_GET = array( 'ax_quote_authorization' => $uuid );
	$_SERVER['REQUEST_URI'] = trailingslashit( $home_path ) . 'not-canonical/?ax_quote_authorization=' . rawurlencode( $uuid );
	$wrong_path = axismundi_op_quote_authorization_request_uuid();
	ax_op_qa_assert( $ax_op_qa_results, 'the public route claims only the exact canonical path and single UUID query argument', $uuid === $canonical && null === $extra && null === $wrong_path );
} finally {
	$_GET = $ax_op_qa_old_get;
	if ( null === $ax_op_qa_old_uri ) {
		unset( $_SERVER['REQUEST_URI'] );
	} else {
		$_SERVER['REQUEST_URI'] = $ax_op_qa_old_uri;
	}
	foreach ( $ax_op_qa_identities as $identity_id ) {
		foreach ( array( axismundi_actors_endpoints_table(), axismundi_actors_keys_table(), axismundi_actors_fetch_state_table(), axismundi_actors_identity_relations_table(), axismundi_actors_asset_cache_table(), axismundi_actors_addresses_table(), axismundi_actors_texts_table() ) as $child_table ) {
			$wpdb->delete( $child_table, array( 'identity_id' => $identity_id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fixture cleanup.
		}
		$wpdb->delete( axismundi_actors_actors_table(), array( 'identity_id' => $identity_id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fixture cleanup.
		$wpdb->delete( axismundi_actors_identities_table(), array( 'id' => $identity_id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fixture cleanup.
	}
	foreach ( $ax_op_qa_users as $user_id ) {
		wp_delete_user( $user_id );
	}
}

$ax_op_qa_failed = count( array_filter( $ax_op_qa_results, static fn( bool $result ) : bool => ! $result ) );
// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI fixture output.
printf( "\n== %d checks, %d failed ==\n", count( $ax_op_qa_results ), $ax_op_qa_failed );
if ( class_exists( 'WP_CLI' ) ) {
	WP_CLI::halt( $ax_op_qa_failed > 0 ? 1 : 0 );
}
exit( $ax_op_qa_failed > 0 ? 1 : 0 );
