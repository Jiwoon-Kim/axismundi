<?php
/** Collection contract registry audit (dev-only; dist-excluded). */

defined( 'ABSPATH' ) || exit( 1 );

require_once dirname( __DIR__ ) . '/includes/collection-registry.php';

$ax_contract_results = array();
function ax_contract_assert( array &$results, string $label, bool $holds ) : void {
	$results[] = $holds;
	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI audit output.
	printf( "[%s] %s\n", $holds ? 'PASS' : 'FAIL', $label );
}

$GLOBALS['axismundi_op_collection_contracts'] = array();
$GLOBALS['axismundi_op_collection_contract_sequence'] = 0;
$GLOBALS['axismundi_op_collection_contracts_loaded'] = true;

$reader = static fn( string $value ) : string => 'read:' . $value;
$base = array(
	'member_models' => array( 'Activity' ),
	'presentation' => 'paged-members',
	'ordering' => 'immutable-cursor(feed_sort_at,id)',
	'total_capability' => 'unavailable',
	'total_reason' => 'read-time-filter',
	'total_disclosure' => array( 'public' => 'never', 'owner' => 'always' ),
	'reader' => $reader,
	'owner' => 'activities',
);

$registered = axismundi_op_register_collection_contract( 'ap-outbox', $base );
$contract = axismundi_op_collection_contract( 'ap-outbox' );
ax_contract_assert( $ax_contract_results, 'a complete contract registers and has a read-back API', true === $registered && is_array( $contract ) && 'read-time-filter' === $contract['total_reason'] && 'activities' === $contract['owner'] );
ax_contract_assert( $ax_contract_results, 'the read API invokes the registered owner reader', 'read:cursor' === axismundi_op_read_collection_contract( 'ap-outbox', 'cursor' ) );

$duplicate = axismundi_op_register_collection_contract( 'ap-outbox', $base );
ax_contract_assert( $ax_contract_results, 'a later contract cannot silently replace an existing protocol claim', is_wp_error( $duplicate ) && 'ax_op_collection_contract_duplicate' === $duplicate->get_error_code() && 'read-time-filter' === axismundi_op_collection_contract( 'ap-outbox' )['total_reason'] );

$missing_reason = $base;
unset( $missing_reason['total_reason'] );
$bad_reason = axismundi_op_register_collection_contract( 'bad-reason', $missing_reason );
$bad_exact = $base;
$bad_exact['total_capability'] = 'exact';
$bad_exact['total_reason'] = 'read-time-filter';
$exact_reason = axismundi_op_register_collection_contract( 'exact-reason', $bad_exact );
ax_contract_assert( $ax_contract_results, 'unavailable totals require a reason and exact totals reject one', is_wp_error( $bad_reason ) && is_wp_error( $exact_reason ) );

$bad_disclosure = $base;
$bad_disclosure['total_disclosure'] = array( 'public' => 'never' );
$disclosure = axismundi_op_register_collection_contract( 'bad-disclosure', $bad_disclosure );
$bad_reader = $base;
$bad_reader['reader'] = 'not-a-reader';
$reader_error = axismundi_op_register_collection_contract( 'bad-reader', $bad_reader );
$bad_owner = $base;
unset( $bad_owner['owner'] );
$owner_error = axismundi_op_register_collection_contract( 'bad-owner', $bad_owner );
ax_contract_assert( $ax_contract_results, 'both requester disclosure levels, an owning reader, and an owner are mandatory', is_wp_error( $disclosure ) && is_wp_error( $reader_error ) && is_wp_error( $owner_error ) );

$missing = axismundi_op_read_collection_contract( 'missing' );
ax_contract_assert( $ax_contract_results, 'an absent contract is an explicit error rather than an empty collection', is_wp_error( $missing ) && 'ax_op_collection_contract_not_found' === $missing->get_error_code() );

$failures = count( array_filter( $ax_contract_results, static fn( bool $holds ) : bool => ! $holds ) );
// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI audit output.
printf( "\n== %d checks, %d failed ==\n", count( $ax_contract_results ), $failures );
if ( class_exists( 'WP_CLI' ) ) {
	WP_CLI::halt( $failures > 0 ? 1 : 0 );
}
exit( $failures > 0 ? 1 : 0 );
