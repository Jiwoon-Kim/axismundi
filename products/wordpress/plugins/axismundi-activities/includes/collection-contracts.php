<?php
/**
 * Activities-owned collection contracts.
 *
 * These descriptors declare ledger and relation facts for Object Projections.
 * They do not register routes or serialize JSON-LD.
 *
 * @package AxismundiActivities
 */

defined( 'ABSPATH' ) || exit;

/** Compatibility entry point; Inbox reads now require Account authorization. */
function axismundi_act_read_actor_inbox_unavailable( string $actor_uri ) {
	return axismundi_act_get_actor_inbox_page( $actor_uri );
}

/** Compatibility entry point for the policy-gated cursor reader. */
function axismundi_act_read_liked_collection_unavailable( string $actor_uri ) {
	return axismundi_act_get_liked_collection_page( $actor_uri );
}

/** Register Activity-ledger and relation-backed collection contracts. */
function axismundi_act_register_collection_contracts() : void {
	if ( ! function_exists( 'axismundi_op_register_collection_contract' ) ) {
		return;
	}
	$contracts = array(
		'ap-outbox' => array(
			'member_models'    => array( 'Activity' ),
			'presentation'     => 'paged-members',
			'ordering'         => 'immutable-cursor(feed_sort_at,id)',
			'total_capability' => 'unavailable',
			'total_reason'     => 'read-time-filter',
			'total_disclosure' => array( 'public' => 'never', 'owner' => 'always' ),
			'reader'           => 'axismundi_act_get_public_outbox_page',
		),
		'ap-inbox' => array(
			'member_models'    => array( 'Activity' ),
			'presentation'     => 'paged-members',
			'ordering'         => 'source-defined',
			'total_capability' => 'unavailable',
			'total_reason'     => 'member-level-visibility',
			'total_disclosure' => array( 'public' => 'never', 'owner' => 'never' ),
			'reader'           => 'axismundi_act_get_actor_inbox_page',
		),
		'ap-followers' => array(
			'member_models'    => array( 'Object' ),
			'member_types'     => axismundi_actors_kinds(),
			'presentation'     => 'paged-members',
			'ordering'         => 'source-defined',
			'total_capability' => 'exact',
			'total_disclosure' => array( 'public' => 'policy-gated', 'owner' => 'always' ),
			'reader'           => 'axismundi_act_get_follow_collection_page',
		),
		'ap-following' => array(
			'member_models'    => array( 'Object' ),
			'presentation'     => 'paged-members',
			'ordering'         => 'source-defined',
			'total_capability' => 'exact',
			'total_disclosure' => array( 'public' => 'policy-gated', 'owner' => 'always' ),
			'reader'           => 'axismundi_act_get_follow_collection_page',
		),
		'ap-liked' => array(
			'member_models'    => array( 'Object' ),
			'presentation'     => 'paged-members',
			'ordering'         => 'source-defined',
			'total_capability' => 'unavailable',
			'total_reason'     => 'membership-not-stored',
			'total_disclosure' => array( 'public' => 'policy-gated', 'owner' => 'always' ),
			'reader'           => 'axismundi_act_get_liked_collection_page',
		),
		'ap-likes' => array(
			'member_models'    => array( 'Activity' ),
			'presentation'     => 'count-only',
			'ordering'         => 'none',
			'total_capability' => 'exact',
			'total_disclosure' => array( 'public' => 'policy-gated', 'owner' => 'always' ),
			'reader'           => 'axismundi_act_get_like_count',
		),
		'ap-shares' => array(
			'member_models'    => array( 'Activity' ),
			'presentation'     => 'count-only',
			'ordering'         => 'none',
			'total_capability' => 'exact',
			'total_disclosure' => array( 'public' => 'policy-gated', 'owner' => 'always' ),
			'reader'           => 'axismundi_act_get_announce_count',
		),
	);
	foreach ( $contracts as $id => $contract ) {
		$contract['collection_type'] = 'OrderedCollection';
		$contract['owner'] = 'activities';
		$source = array( 'ap-outbox' => 'publication', 'ap-inbox' => 'receipt', 'ap-followers' => 'Follow+Accept', 'ap-following' => 'Follow+Accept', 'ap-liked' => 'Like', 'ap-likes' => 'Like', 'ap-shares' => 'Announce' );
		$contract['membership_source'] = 'side-effect:' . $source[ $id ];
		$contract['membership_authority'] = in_array( $id, array( 'ap-followers', 'ap-following' ), true ) ? 'relation-row' : 'ledger-index';
		$contract['append_policy'] = 'closed';
		axismundi_op_register_collection_contract( $id, $contract );
	}
}
add_action( 'axismundi_op_register_collection_contracts', 'axismundi_act_register_collection_contracts' );
