<?php
/**
 * Collection contract registry.
 *
 * A transformer owns a representation for one source. A collection contract
 * records the independent facts that let a caller reason about membership,
 * counting, ordering, and disclosure before it chooses that representation.
 * Domain plugins register the reader that owns those facts; OP only validates
 * and exposes the contract.
 *
 * @package AxismundiObjectProjections
 */

defined( 'ABSPATH' ) || exit;

/** @var array<string,array<string,mixed>> Collection contracts by stable id. */
$GLOBALS['axismundi_op_collection_contracts'] = array();

/** @var int Stable request-local registration sequence. */
$GLOBALS['axismundi_op_collection_contract_sequence'] = 0;

/** @var bool Whether collection-contract registrations have run. */
$GLOBALS['axismundi_op_collection_contracts_loaded'] = false;

/**
 * Normalize and validate one collection contract.
 *
 * @param string              $id   Stable contract id.
 * @param array<string,mixed> $args Contract fields.
 * @return array<string,mixed>|WP_Error
 */
function axismundi_op_normalize_collection_contract( string $id, array $args ) {
	$id = sanitize_key( $id );
	if ( '' === $id || $id !== (string) ( $args['id'] ?? $id ) ) {
		return new WP_Error( 'ax_op_collection_contract_id', __( 'Collection contract IDs must be non-empty lowercase slugs.', 'axismundi-object-projections' ) );
	}
	$member_models = array_values( array_unique( array_map( 'strval', (array) ( $args['member_models'] ?? array() ) ) ) );
	if ( empty( $member_models ) || array_diff( $member_models, array( 'Activity', 'Object', 'Actor', 'mixed' ) ) ) {
		return new WP_Error( 'ax_op_collection_contract_members', __( 'A collection contract requires supported member models.', 'axismundi-object-projections' ) );
	}
	$presentation = (string) ( $args['presentation'] ?? '' );
	if ( ! in_array( $presentation, array( 'metadata-only', 'count-only', 'bounded-members', 'paged-members' ), true ) ) {
		return new WP_Error( 'ax_op_collection_contract_presentation', __( 'A collection contract requires a supported presentation.', 'axismundi-object-projections' ) );
	}
	$ordering = (string) ( $args['ordering'] ?? '' );
	if ( ! in_array( $ordering, array( 'none', 'source-defined', 'immutable-cursor(feed_sort_at,id)' ), true ) ) {
		return new WP_Error( 'ax_op_collection_contract_ordering', __( 'A collection contract requires a supported ordering.', 'axismundi-object-projections' ) );
	}
	$total_capability = (string) ( $args['total_capability'] ?? '' );
	if ( ! in_array( $total_capability, array( 'exact', 'unavailable' ), true ) ) {
		return new WP_Error( 'ax_op_collection_contract_total', __( 'A collection contract must state whether an exact total exists.', 'axismundi-object-projections' ) );
	}
	$total_reason = isset( $args['total_reason'] ) ? (string) $args['total_reason'] : '';
	$valid_reasons = array( 'read-time-filter', 'bounded-scan', 'membership-not-stored', 'member-level-visibility' );
	if ( 'unavailable' === $total_capability && ! in_array( $total_reason, $valid_reasons, true ) ) {
		return new WP_Error( 'ax_op_collection_contract_total_reason', __( 'An unavailable total requires a measured reason.', 'axismundi-object-projections' ) );
	}
	if ( 'exact' === $total_capability && '' !== $total_reason ) {
		return new WP_Error( 'ax_op_collection_contract_total_reason', __( 'An exact total must not carry an unavailable-total reason.', 'axismundi-object-projections' ) );
	}
	$disclosure = (array) ( $args['total_disclosure'] ?? array() );
	$levels     = array( 'public', 'owner' );
	if ( array_diff( $levels, array_keys( $disclosure ) ) || array_diff( array_keys( $disclosure ), $levels ) || array_diff( array_values( $disclosure ), array( 'never', 'policy-gated', 'always' ) ) ) {
		return new WP_Error( 'ax_op_collection_contract_disclosure', __( 'A collection contract requires public and owner total-disclosure policies.', 'axismundi-object-projections' ) );
	}
	if ( ! is_callable( $args['reader'] ?? null ) ) {
		return new WP_Error( 'ax_op_collection_contract_reader', __( 'A collection contract requires an owning reader callback.', 'axismundi-object-projections' ) );
	}
	$owner = sanitize_key( (string) ( $args['owner'] ?? '' ) );
	if ( '' === $owner ) {
		return new WP_Error( 'ax_op_collection_contract_owner', __( 'A collection contract requires its owning plugin.', 'axismundi-object-projections' ) );
	}
	return array(
		'id'               => $id,
		'member_models'    => $member_models,
		'presentation'     => $presentation,
		'ordering'         => $ordering,
		'total_capability' => $total_capability,
		'total_reason'     => $total_reason,
		'total_disclosure' => array( 'public' => (string) $disclosure['public'], 'owner' => (string) $disclosure['owner'] ),
		'reader'           => $args['reader'],
		'owner'            => $owner,
		'priority'         => isset( $args['priority'] ) ? (int) $args['priority'] : 10,
	);
}

/**
 * Register one collection contract. Later definitions never replace an
 * earlier one: a descriptor is a protocol claim, not a filterable preference.
 *
 * @param string              $id   Stable contract id.
 * @param array<string,mixed> $args Contract fields.
 * @return true|WP_Error
 */
function axismundi_op_register_collection_contract( string $id, array $args ) {
	$args['id'] = $id;
	$contract   = axismundi_op_normalize_collection_contract( $id, $args );
	if ( is_wp_error( $contract ) ) {
		return $contract;
	}
	if ( isset( $GLOBALS['axismundi_op_collection_contracts'][ $contract['id'] ] ) ) {
		return new WP_Error( 'ax_op_collection_contract_duplicate', __( 'A collection contract may be registered only once.', 'axismundi-object-projections' ) );
	}
	++$GLOBALS['axismundi_op_collection_contract_sequence'];
	$contract['order'] = (int) $GLOBALS['axismundi_op_collection_contract_sequence'];
	$GLOBALS['axismundi_op_collection_contracts'][ $contract['id'] ] = $contract;
	return true;
}

/** Load domain-owned contract registrations once per request. */
function axismundi_op_load_collection_contracts() : void {
	if ( $GLOBALS['axismundi_op_collection_contracts_loaded'] ) {
		return;
	}
	$GLOBALS['axismundi_op_collection_contracts_loaded'] = true;
	do_action( 'axismundi_op_register_collection_contracts' );
}

/** @return array<int,array<string,mixed>> Registered contracts in stable order. */
function axismundi_op_collection_contracts() : array {
	axismundi_op_load_collection_contracts();
	$contracts = array_values( $GLOBALS['axismundi_op_collection_contracts'] );
	usort( $contracts, static fn( array $a, array $b ) : int => array( $a['priority'], $a['order'] ) <=> array( $b['priority'], $b['order'] ) );
	return $contracts;
}

/** @return array<string,mixed>|null One contract by stable id. */
function axismundi_op_collection_contract( string $id ) : ?array {
	axismundi_op_load_collection_contracts();
	$id = sanitize_key( $id );
	return $GLOBALS['axismundi_op_collection_contracts'][ $id ] ?? null;
}

/** Invoke the owning reader for one registered contract. */
function axismundi_op_read_collection_contract( string $id, ...$args ) {
	$contract = axismundi_op_collection_contract( $id );
	if ( ! is_array( $contract ) ) {
		return new WP_Error( 'ax_op_collection_contract_not_found', __( 'The collection contract is not registered.', 'axismundi-object-projections' ) );
	}
	return call_user_func_array( $contract['reader'], $args );
}
