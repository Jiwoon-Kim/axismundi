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

/** The recipient index does not exist yet, so an Inbox reader must fail loudly. */
function axismundi_act_read_actor_inbox_unavailable( string $actor_uri ) {
	return new WP_Error( 'ax_act_inbox_membership_not_stored', __( 'Inbox recipients are not materialized in the Activity ledger.', 'axismundi-activities' ) );
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
			'presentation'     => 'metadata-only',
			'ordering'         => 'none',
			'total_capability' => 'unavailable',
			'total_reason'     => 'membership-not-stored',
			'total_disclosure' => array( 'public' => 'never', 'owner' => 'never' ),
			'reader'           => 'axismundi_act_read_actor_inbox_unavailable',
		),
		'ap-followers' => array(
			'member_models'    => array( 'Actor' ),
			'presentation'     => 'paged-members',
			'ordering'         => 'source-defined',
			'total_capability' => 'exact',
			'total_disclosure' => array( 'public' => 'never', 'owner' => 'never' ),
			'reader'           => 'axismundi_act_get_follow_collection_page',
		),
		'ap-following' => array(
			'member_models'    => array( 'Actor' ),
			'presentation'     => 'paged-members',
			'ordering'         => 'source-defined',
			'total_capability' => 'exact',
			'total_disclosure' => array( 'public' => 'policy-gated', 'owner' => 'always' ),
			'reader'           => 'axismundi_act_get_follow_collection_page',
		),
		'ap-liked' => array(
			'member_models'    => array( 'Object' ),
			'presentation'     => 'bounded-members',
			'ordering'         => 'source-defined',
			'total_capability' => 'unavailable',
			'total_reason'     => 'bounded-scan',
			'total_disclosure' => array( 'public' => 'policy-gated', 'owner' => 'always' ),
			'reader'           => 'axismundi_act_get_liked_object_uris',
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
		$contract['owner'] = 'activities';
		axismundi_op_register_collection_contract( $id, $contract );
	}
}
add_action( 'axismundi_op_register_collection_contracts', 'axismundi_act_register_collection_contracts' );
