<?php
/** Community root submission and review. No transport authentication, network, or Forum storage. */
defined( 'ABSPATH' ) || exit;

/** The explicit picker and the C2S command use the same Actor-scoped policy. */
function axismundi_act_can_select_community( Axismundi_Actor $person, Axismundi_Actor $group, int $user = 0 ) : bool {
	if ( ! $person->is_local() || 'Person' !== $person->get_type() || 'public' !== $person->get_status()
		|| 'Group' !== $group->get_type() || 'public' !== $group->get_status() ) { return false; }
	$follow = axismundi_act_get_relation( 'follow', $person->get_uri(), $group->get_uri() );
	return ( ( is_array( $follow ) && 'accepted' === $follow['state'] )
			|| ( $group->is_local() && $user > 0 && axismundi_actors_can_act_as( $group, $user ) ) )
		&& false === axismundi_act_actor_blocks( $person->get_uri(), $group->get_uri() )
		&& function_exists( 'axismundi_actors_has_community_submission_profile' )
		&& axismundi_actors_has_community_submission_profile( $group );
}

/** Normalize explicit Group submissions without broadening their visibility. */
function axismundi_act_prepare_community_submission( array $note, Axismundi_Actor $person, int $user = 0 ) {
	$members = axismundi_as2_normalize_members( $note['audience'] ?? null );
	if ( ! $members ) { return axismundi_act_prepare_community_reply( $note, $person ); }
	$group = 1 === count( $members ) && ! empty( $members[0]['resolvable'] ) ? axismundi_actors_get_by_uri( $members[0]['uri'] ) : null;
	if ( ! $group || ! axismundi_act_can_select_community( $person, $group, $user ) ) {
		return new WP_Error( 'ax_community_audience', __( 'Choose a Community you manage or this Person follows.', 'axismundi-activities' ), array( 'status' => 403 ) );
	}
	$audience = axismundi_act_audience( $note );
	if ( $audience['bto'] || $audience['bcc'] ) {
		return new WP_Error( 'ax_community_blind_address', __( 'Community submissions do not support bto or bcc recipients.', 'axismundi-activities' ), array( 'status' => 422 ) );
	}
	if ( ! axismundi_act_audience_is_public( $audience ) ) {
		return new WP_Error( 'ax_community_visibility', __( 'Community submissions require Public or Quiet public visibility.', 'axismundi-activities' ), array( 'status' => 422 ) );
	}
	$public = axismundi_act_public_audience_uri();
	$public_values = array( $public, 'as:Public' );
	$public_to = (bool) array_intersect( $public_values, $audience['to'] );
	if ( ! empty( $note['inReplyTo'] ) ) {
		$prepared = axismundi_act_prepare_community_reply( $note, $person, $group );
		if ( is_wp_error( $prepared ) ) { return $prepared; }
		if ( $public_to ) {
			$prepared['to'][] = $public;
			$prepared['cc'] = array_values( array_diff( $prepared['cc'], $public_values ) );
		}
		return $prepared;
	}
	$note['audience'] = $group->get_uri();
	$note['to'] = $public_to ? array( $group->get_uri(), $public ) : array( $group->get_uri() );
	$cc = array_diff( array_merge( $audience['to'], $audience['cc'] ), array_merge( array( $group->get_uri() ), $public_values ) );
	$note['cc'] = array_values( array_unique( array_merge( $public_to ? array() : array( $public ), $cc ) ) );
	unset( $note['bto'], $note['bcc'] );
	return $note;
}

/** A directly received Person Create is evidence; cache presence alone never submits a post. */
function axismundi_act_admit_community_root( Axismundi_Activity $create, Axismundi_Actor $group ) {
	global $wpdb;
	if ( ! axismundi_actors_is_community( $group ) || 'public' !== $group->get_status() ) {
		return new WP_Error( 'ax_community_profile', 'Only local Community Groups accept submissions.', array( 'status' => 403 ) );
	}
	$person = axismundi_actors_get_by_uri( $create->get_actor_uri() );
	$object = $create->get_payload()['object'] ?? null;
	$local = $person && $person->is_local();
	if ( 'Create' !== $create->get_type() || ( $local ? 'outbound' : 'inbound' ) !== $create->get_direction() || ! $create->is_effective()
		|| ! $person || 'Person' !== $person->get_type() || 'public' !== $person->get_status()
		|| ! is_array( $object ) || ! in_array( $object['type'] ?? '', $local ? array( 'Note' ) : array( 'Page', 'Article' ), true ) || ! empty( $object['inReplyTo'] )
		|| $person->get_uri() !== axismundi_act_object_publisher_uri( $object )
		|| ! axismundi_act_is_publicly_renderable( $create ) ) {
		return new WP_Error( 'ax_community_root', 'An effective public Person Create is required: a local Note or a remote Page or Article.', array( 'status' => 422 ) );
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
	// Local submissions were authorized by the outbox command before commit.
	if ( ! $local && ( ! is_array( $follow ) || 'accepted' !== $follow['state'] ) ) { return new WP_Error( 'ax_community_membership', 'An accepted Follow is required to submit a root post.', array( 'status' => 403 ) ); }
	$stored = $local ? axismundi_op_get_local_object( $create->get_object_uri() ) : axismundi_op_get_remote_object( $create->get_object_uri(), false );
	$visible = $stored && ( $local ? ( 'active' === $stored['object_status'] && ! empty( $stored['publicly_readable'] ) ) : axismundi_op_remote_object_is_publicly_listable( $stored ) );
	if ( ! $visible || $stored['attributed_to_uri'] !== $person->get_uri() ) {
		return new WP_Error( 'ax_community_unresolved', 'The source Object is not yet a confirmed public observation.', array( 'status' => 409 ) );
	}
	return axismundi_actors_admit_community_post( $group, $create->get_object_uri(), $create->get_uri(), $person->get_uri() );
}

/** Authenticate the operator as a Person, then authorize review through current moderator membership. */
function axismundi_act_review_community_post( int $user, Axismundi_Actor $moderator, Axismundi_Actor $group, string $uri, bool $approve, string $reason = '' ) {
	$permission = axismundi_act_outbox_authorize( $user, $moderator );
	if ( is_wp_error( $permission ) ) { return $permission; }
	$row = axismundi_actors_get_community_post( $group->get_identity_id(), $uri );
	$create = $row ? axismundi_act_get( $row['create_uri'] ) : null;
	if ( ! $row || ! $create ) { return new WP_Error( 'ax_community_missing', 'The original submission was not found.', array( 'status' => 404 ) ); }
	return axismundi_act_submission_lock( array( 'source_owner' => 'axismundi-activities', 'source_kind' => 'community-review', 'source_ref' => $row['id'] ), static function () use ( $user, $moderator, $group, $uri, $approve, $reason, $row, $create ) {
		global $wpdb;
		$decide = static function () use ( $user, $moderator, $group, $uri, $approve, $reason ) {
			$permission = axismundi_act_outbox_authorize( $user, $moderator );
			$fresh = axismundi_actors_get_by_identity( $group->get_identity_id() );
			return is_wp_error( $permission ) ? $permission : ( $fresh ? axismundi_actors_decide_community_post( $fresh, $moderator, $uri, $approve, $reason ) : new WP_Error( 'ax_community_missing', 'The Group is unavailable.' ) );
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
