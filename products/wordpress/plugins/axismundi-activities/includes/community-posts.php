<?php
/** Community root submission and review. No transport authentication, network, or Forum storage. */
defined( 'ABSPATH' ) || exit;

/** A directly received Person Create is evidence; cache presence alone never submits a post. */
function axismundi_act_admit_community_root( Axismundi_Activity $create, Axismundi_Actor $group ) {
	global $wpdb;
	if ( ! axismundi_actors_is_community( $group ) || 'public' !== $group->get_status() ) {
		return new WP_Error( 'ax_community_profile', 'Only local Community Groups accept submissions.', array( 'status' => 403 ) );
	}
	$person = axismundi_actors_get_by_uri( $create->get_actor_uri() );
	$object = $create->get_payload()['object'] ?? null;
	if ( 'Create' !== $create->get_type() || 'inbound' !== $create->get_direction() || ! $create->is_effective()
		|| ! $person || $person->is_local() || 'Person' !== $person->get_type() || 'public' !== $person->get_status()
		|| ! is_array( $object ) || ! in_array( $object['type'] ?? '', array( 'Page', 'Article' ), true ) || ! empty( $object['inReplyTo'] )
		|| $person->get_uri() !== axismundi_act_member_uri( $object['attributedTo'] ?? '' )
		|| ! axismundi_act_is_publicly_renderable( $create ) ) {
		return new WP_Error( 'ax_community_root', 'An effective public remote Person Create(Page or Article) is required.', array( 'status' => 422 ) );
	}
	$origin = static fn( $uri ) => array( wp_parse_url( $uri, PHP_URL_SCHEME ), wp_parse_url( $uri, PHP_URL_HOST ), wp_parse_url( $uri, PHP_URL_PORT ) );
	if ( $origin( $create->get_object_uri() ) !== $origin( $person->get_uri() ) ) {
		return new WP_Error( 'ax_community_origin', 'Cross-origin Object authorship needs evidence beyond this direct submission.', array( 'status' => 403 ) );
	}
	$addressed = false;
	foreach ( array( $create->get_payload(), $object ) as $payload ) {
		foreach ( array( 'to', 'cc', 'audience' ) as $field ) {
			if ( in_array( $group->get_uri(), axismundi_op_application_recipient_uris( $payload[$field] ?? null ), true ) ) { $addressed = true; }
		}
	}
	$received = $wpdb->get_var( $wpdb->prepare( 'SELECT activity_id FROM %i WHERE activity_id=%d AND actor_identity_id=%d', axismundi_act_inbox_table(), $create->get_id(), $group->get_identity_id() ) );
	if ( ! $addressed || ! $received ) { return new WP_Error( 'ax_community_receipt', 'An explicit Group address and actual Inbox receipt are required.', array( 'status' => 403 ) ); }
	$follow = axismundi_act_get_relation( 'follow', $person->get_uri(), $group->get_uri() );
	if ( ! is_array( $follow ) || 'accepted' !== $follow['state'] ) { return new WP_Error( 'ax_community_membership', 'An accepted Follow is required to submit a root post.', array( 'status' => 403 ) ); }
	$stored = axismundi_op_get_remote_object( $create->get_object_uri(), false );
	if ( ! $stored || ! axismundi_op_remote_object_is_publicly_listable( $stored ) || $stored['attributed_to_uri'] !== $person->get_uri() ) {
		return new WP_Error( 'ax_community_unresolved', 'The source Object is not yet a confirmed public observation.', array( 'status' => 409 ) );
	}
	return axismundi_actors_admit_community_post( $group, $create->get_object_uri(), $create->get_uri(), $person->get_uri() );
}

/** Authenticate the operator as a Person, then authorize review through current moderator membership. */
function axismundi_act_review_community_post( int $user, Axismundi_Actor $moderator, Axismundi_Actor $group, string $uri, bool $approve ) {
	$permission = axismundi_act_outbox_authorize( $user, $moderator );
	if ( is_wp_error( $permission ) ) { return $permission; }
	$row = axismundi_actors_get_community_post( $group->get_identity_id(), $uri );
	$create = $row ? axismundi_act_get( $row['create_uri'] ) : null;
	if ( ! $row || ! $create ) { return new WP_Error( 'ax_community_missing', 'The original submission was not found.', array( 'status' => 404 ) ); }
	return axismundi_act_submission_lock( array( 'source_owner' => 'axismundi-activities', 'source_kind' => 'community-review', 'source_ref' => $row['id'] ), static function () use ( $user, $moderator, $group, $uri, $approve, $row, $create ) {
		global $wpdb;
		$decide = static function () use ( $user, $moderator, $group, $uri, $approve ) {
			$permission = axismundi_act_outbox_authorize( $user, $moderator );
			$fresh = axismundi_actors_get_by_identity( $group->get_identity_id() );
			return is_wp_error( $permission ) ? $permission : ( $fresh ? axismundi_actors_decide_community_post( $fresh, $moderator, $uri, $approve ) : new WP_Error( 'ax_community_missing', 'The Group is unavailable.' ) );
		};
		if ( ! $approve ) {
			if ( false === $wpdb->query( 'START TRANSACTION' ) ) { return new WP_Error( 'ax_community_write', 'Review transaction failed.' ); }
			$committed = false;
			try {
				$result = $decide();
				if ( is_wp_error( $result ) ) { return $result; }
				$committed = false !== $wpdb->query( 'COMMIT' );
				return $committed ? true : new WP_Error( 'ax_community_write', 'Review commit failed.' );
			} finally { if ( ! $committed ) { $wpdb->query( 'ROLLBACK' ); } }
		}
		// Revalidate the immutable submission and current source; review never repairs or refetches it.
		$eligible = ! empty( $create->get_payload()['object']['inReplyTo'] ) ? axismundi_act_admit_community_reply( $create, $group ) : axismundi_act_admit_community_root( $create, $group );
		if ( is_wp_error( $eligible ) ) { return $eligible; }
		if ( ! $group->is_handle_locked() ) { return new WP_Error( 'ax_community_group', 'The Group has no federatable handle.' ); }
		return axismundi_act_record_source_activity(
			array( 'type' => 'Announce', 'actor' => $group->get_uri(), 'object' => $create->get_payload(), 'to' => array( 'https://www.w3.org/ns/activitystreams#Public' ), 'cc' => array( axismundi_op_actor_followers_url( $group ) ) ),
			'outbound', 'community-post:' . $row['id'], array(), $decide
		);
	} );
}
