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
	if ( empty( $member_models ) || array_diff( $member_models, array( 'Activity', 'Object', 'mixed' ) ) ) {
		return new WP_Error( 'ax_op_collection_contract_members', __( 'A collection contract requires supported member models.', 'axismundi-object-projections' ) );
	}
	$presentation = (string) ( $args['presentation'] ?? '' );
	if ( ! in_array( $presentation, array( 'metadata-only', 'count-only', 'bounded-members', 'paged-members' ), true ) ) {
		return new WP_Error( 'ax_op_collection_contract_presentation', __( 'A collection contract requires a supported presentation.', 'axismundi-object-projections' ) );
	}
	$ordering = (string) ( $args['ordering'] ?? '' );
	$collection_type = (string) ( $args['collection_type'] ?? '' );
	if ( ! in_array( $collection_type, array( 'Collection', 'OrderedCollection' ), true ) || ( 'immutable-cursor(feed_sort_at,id)' === $ordering && 'OrderedCollection' !== $collection_type ) ) {
		return new WP_Error( 'ax_op_collection_contract_type', 'A collection requires a root type consistent with its ordering.' );
	}
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
	$membership_source = (string) ( $args['membership_source'] ?? '' );
	$authority = (string) ( $args['membership_authority'] ?? '' );
	$append_policy = (string) ( $args['append_policy'] ?? '' );
	if ( '' === $membership_source || ! in_array( $authority, array( 'ledger-index', 'relation-row', 'membership-row' ), true ) || ! in_array( $append_policy, array( 'closed', 'owner-only', 'publicly-appendable' ), true ) ) {
		return new WP_Error( 'ax_op_collection_membership_contract', 'A collection must declare its membership source, authority, and append policy.' );
	}
	return array(
		'id'               => $id,
		'member_models'    => $member_models,
		'member_types'     => array_values( (array) ( $args['member_types'] ?? array() ) ),
		'membership_source' => $membership_source,
		'membership_authority' => $authority,
		'append_policy'    => $append_policy,
		'presentation'     => $presentation,
		'ordering'         => $ordering,
		'collection_type'  => $collection_type,
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

/**
 * Assemble a collection from representation metadata and owner-read membership.
 *
 * Requester classification and policy approval are supplied only by the server
 * route after authorization, never from client query parameters.
 *
 * @return array<string,mixed>|WP_Error
 */
function axismundi_op_assemble_collection( string $id, array $metadata, array $membership, string $requester = 'public', bool $total_allowed = false ) {
	$contract = axismundi_op_collection_contract( $id );
	if ( null === $contract ) {
		return new WP_Error( 'ax_op_collection_contract_not_found', 'The collection contract is not registered.' );
	}
	$root_type = $contract['collection_type'];
	$allowed_types = array( $root_type );
	if ( 'paged-members' === $contract['presentation'] ) {
		$allowed_types[] = $root_type . 'Page';
	}
	if ( ! in_array( $metadata['type'] ?? '', $allowed_types, true ) ) {
		return new WP_Error( 'ax_op_collection_type', 'The collection representation type contradicts its contract.' );
	}
	if ( ! isset( $contract['total_disclosure'][ $requester ] ) ) {
		return new WP_Error( 'ax_op_collection_requester', 'Unknown collection requester class.' );
	}
	foreach ( array( 'totalItems', 'items', 'orderedItems' ) as $key ) {
		if ( array_key_exists( $key, $metadata ) ) {
			return new WP_Error( 'ax_op_collection_metadata', 'Membership claims must come from the owning reader.' );
		}
	}
	$policy = $contract['total_disclosure'][ $requester ];
	$emit_total = 'exact' === $contract['total_capability'] && ( 'always' === $policy || ( 'policy-gated' === $policy && $total_allowed ) );
	if ( $emit_total ) {
		if ( ! isset( $membership['total'] ) || ! is_int( $membership['total'] ) || $membership['total'] < 0 ) {
			return new WP_Error( 'ax_op_collection_total', 'An exact disclosed total requires a nonnegative integer from the reader.' );
		}
		$metadata['totalItems'] = $membership['total'];
	}
	if ( array_key_exists( 'items', $membership ) ) {
		if ( ! in_array( $contract['presentation'], array( 'bounded-members', 'paged-members' ), true ) || ! is_array( $membership['items'] ) ) {
			return new WP_Error( 'ax_op_collection_members', 'This contract does not permit member enumeration.' );
		}
		$key = in_array( $metadata['type'] ?? '', array( 'OrderedCollection', 'OrderedCollectionPage' ), true ) ? 'orderedItems' : 'items';
		$metadata[ $key ] = array_values( $membership['items'] );
	}
	return $metadata;
}
