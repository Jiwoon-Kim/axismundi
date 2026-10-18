<?php
/** Registered collection-contract audit (dev-only; dist-excluded). */

defined( 'ABSPATH' ) || exit( 1 );

$ax_contracts_results = array();
function ax_contracts_assert( array &$results, string $label, bool $holds ) : void {
	$results[] = $holds;
	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI audit output.
	printf( "[%s] %s\n", $holds ? 'PASS' : 'FAIL', $label );
}

$contracts = array();
foreach ( axismundi_op_collection_contracts() as $contract ) {
	$contracts[ $contract['id'] ] = $contract;
}
$expected = array( 'ap-outbox', 'ap-inbox', 'ap-followers', 'ap-following', 'ap-liked', 'ap-likes', 'ap-shares', 'replies', 'used-in' );
if ( function_exists( 'axismundi_forum_read_moderator_collection' ) ) {
	$expected[] = 'ap-moderators';
}
ax_contracts_assert( $ax_contracts_results, 'every current implicit collection has one registered contract and folders are not modeled as collections', array() === array_diff( $expected, array_keys( $contracts ) ) && array() === array_diff( array_keys( $contracts ), $expected ) );

foreach ( array( 'ap-outbox', 'ap-inbox', 'ap-liked', 'replies' ) as $id ) {
	$contract = $contracts[ $id ] ?? array();
	ax_contracts_assert( $ax_contracts_results, $id . ' declares why it cannot publish an exact total', 'unavailable' === ( $contract['total_capability'] ?? '' ) && '' !== ( $contract['total_reason'] ?? '' ) );
}
foreach ( array( 'ap-followers', 'ap-following', 'ap-likes', 'ap-shares', 'used-in' ) as $id ) {
	$contract = $contracts[ $id ] ?? array();
	ax_contracts_assert( $ax_contracts_results, $id . ' declares an exact total backed by its owning reader', 'exact' === ( $contract['total_capability'] ?? '' ) && '' === ( $contract['total_reason'] ?? '' ) && is_callable( $contract['reader'] ?? null ) );
}

$outbox = $contracts['ap-outbox'] ?? array();
foreach ( array( 'ap-outbox', 'ap-inbox' ) as $id ) {
	ax_contracts_assert( $ax_contracts_results, '[spec AP 5.1/5.2] ' . $id . ' requires OrderedCollection', 'OrderedCollection' === ( $contracts[ $id ]['collection_type'] ?? '' ) );
}
ax_contracts_assert( $ax_contracts_results, 'the Outbox keeps its immutable ledger cursor and Activities-owned reader binding', 'immutable-cursor(feed_sort_at,id)' === ( $outbox['ordering'] ?? '' ) && 'activities' === ( $outbox['owner'] ?? '' ) && 'axismundi_act_get_public_outbox_page' === ( $outbox['reader'] ?? '' ) );

$inbox = axismundi_op_read_collection_contract( 'ap-inbox', 'https://example.test/actors/owner' );
ax_contracts_assert( $ax_contracts_results, 'the Inbox reader reports missing recipient membership instead of returning an invented empty collection', is_wp_error( $inbox ) && 'ax_act_inbox_membership_not_stored' === $inbox->get_error_code() );

$failures = count( array_filter( $ax_contracts_results, static fn( bool $holds ) : bool => ! $holds ) );
// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI audit output.
printf( "\n== %d checks, %d failed ==\n", count( $ax_contracts_results ), $failures );
if ( class_exists( 'WP_CLI' ) ) {
	WP_CLI::halt( $failures > 0 ? 1 : 0 );
}
exit( $failures > 0 ? 1 : 0 );
