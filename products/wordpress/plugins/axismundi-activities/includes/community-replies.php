<?php
/** Community reply policy for OP local Objects, independent of Note CPT and Forum tables. */
defined( 'ABSPATH' ) || exit;

/** Local acceptance is an Actors decision or an effective Group Announce, not a parent's claim. */
function axismundi_act_community_parent_accepted( string $uri, Axismundi_Actor $group ) : bool {
	$row = axismundi_actors_get_community_post( $group->get_identity_id(), $uri );
	if ( $row && 'approved' !== $row['admission_state'] ) { return false; }
	foreach ( axismundi_act_get_by_object( $uri, 50 ) as $create ) {
		if ( 'Create' !== $create->get_type() || ! $create->is_effective() ) { continue; }
		foreach ( axismundi_act_get_by_object( $create->get_uri(), 50 ) as $announce ) {
			if ( 'Announce' === $announce->get_type() && $announce->is_effective() && $announce->get_actor_uri() === $group->get_uri() ) { return true; }
		}
	}
	return false;
}

/** Read existing public parent state only; ordinary replies and missing parents stay ordinary. */
function axismundi_act_community_reply_context( string $parent_uri ) {
	$parent = axismundi_op_read_object_jsonld( $parent_uri );
	if ( is_wp_error( $parent ) || ! in_array( $parent['type'] ?? '', array( 'Page', 'Article', 'Note' ), true ) ) { return null; }
	$groups = array();
	foreach ( array( 'audience', 'to', 'cc' ) as $field ) {
		foreach ( axismundi_op_application_recipient_uris( $parent[$field] ?? null ) as $uri ) {
			$group = axismundi_actors_get_by_uri( $uri );
			if ( $group && 'Group' === $group->get_type() && 'public' === $group->get_status() ) { $groups[$uri] = $group; }
		}
	}
	if ( ! $groups ) { return null; }
	if ( 1 !== count( $groups ) ) { return new WP_Error( 'ax_community_ambiguous', 'The parent names multiple Groups; no Community is inferred.', array( 'status' => 409 ) ); }
	$group = reset( $groups );
	if ( $group->is_local() && ( ! axismundi_actors_is_community( $group ) || ! axismundi_act_community_parent_accepted( $parent_uri, $group ) ) ) {
		return new WP_Error( 'ax_community_parent', 'The local Community has not accepted this parent.', array( 'status' => 403 ) );
	}
	return array( 'group' => $group, 'parent' => $parent );
}

/** Apply the established outbound contract only to explicitly public Person replies. */
function axismundi_act_prepare_community_reply( array $note, Axismundi_Actor $actor, ?Axismundi_Actor $expected_group = null ) {
	$parent_uri = axismundi_act_member_uri( $note['inReplyTo'] ?? '' );
	if ( $expected_group && '' === $parent_uri ) {
		return new WP_Error( 'ax_community_parent', __( 'Choose a valid parent Object in the selected Community.', 'axismundi-activities' ), array( 'status' => 422 ) );
	}
	if ( '' === $parent_uri || ! axismundi_act_audience_is_public( axismundi_act_audience( $note ) ) ) { return $note; }
	$context = axismundi_act_community_reply_context( $parent_uri );
	if ( is_wp_error( $context ) ) { return $context; }
	if ( $expected_group && ( ! $context || $expected_group->get_uri() !== $context['group']->get_uri() ) ) {
		return new WP_Error( 'ax_community_parent', __( 'The parent is not in the selected Community.', 'axismundi-activities' ), array( 'status' => 409 ) );
	}
	if ( null === $context ) { return $note; }
	if ( ! empty( $note['bto'] ) || ! empty( $note['bcc'] ) ) {
		return new WP_Error( 'ax_community_blind_address', __( 'Community submissions do not support bto or bcc recipients.', 'axismundi-activities' ), array( 'status' => 422 ) );
	}
	if ( 'Person' !== $actor->get_type() ) { return new WP_Error( 'ax_community_author', 'Community replies require a Person author.', array( 'status' => 403 ) ); }
	$group = $context['group'];
	$blocked = axismundi_act_actor_blocks( $actor->get_uri(), $group->get_uri() );
	if ( is_wp_error( $blocked ) || $blocked ) { return new WP_Error( 'ax_community_blocked', 'The selected Person blocks this Group.', array( 'status' => 403 ) ); }
	if ( $group->is_local() && ! $expected_group ) {
		$follow = axismundi_act_get_relation( 'follow', $actor->get_uri(), $group->get_uri() );
		if ( ! is_array( $follow ) || 'accepted' !== $follow['state'] ) { return new WP_Error( 'ax_community_membership', 'An accepted Follow is required for local Community replies.', array( 'status' => 403 ) ); }
	}
	$cc = array_merge( array( 'https://www.w3.org/ns/activitystreams#Public' ), axismundi_op_application_recipient_uris( $context['parent']['attributedTo'] ?? null ), axismundi_op_application_recipient_uris( $note['to'] ?? null ), axismundi_op_application_recipient_uris( $note['cc'] ?? null ) );
	$note['to'] = array( $group->get_uri() );
	$note['cc'] = array_values( array_diff( array_unique( $cc ), array( $group->get_uri() ) ) );
	$note['audience'] = $group->get_uri();
	unset( $note['bto'], $note['bcc'] );
	return $note;
}

/** Submit a local C2S Note to its Community; retries repair receipt/admission only. */
function axismundi_act_receive_community_submission( Axismundi_Activity $create ) {
	$object = $create->get_payload()['object'] ?? null;
	if ( 'Create' !== $create->get_type() || ! is_array( $object ) || 'Note' !== ( $object['type'] ?? '' ) ) { return $create; }
	$group = axismundi_actors_get_by_uri( axismundi_act_member_uri( $object['audience'] ?? '' ) );
	if ( ! $group || 'Group' !== $group->get_type() || ! $group->is_local() ) { return $create; }
	$receipt = apply_filters( 'axismundi_act_community_reply_receipt', new WP_Error( 'ax_community_transport', 'The local Community delivery policy is unavailable.', array( 'status' => 503 ) ), $create, $group );
	if ( is_wp_error( $receipt ) ) { return $receipt; }
	$entry = empty( $object['inReplyTo'] ) ? axismundi_act_admit_community_root( $create, $group ) : axismundi_act_admit_community_reply( $create, $group );
	return is_wp_error( $entry ) ? $entry : $create;
}

/** Local and directly verified remote Note submissions share the Community review queue. */
function axismundi_act_admit_community_reply( Axismundi_Activity $create, Axismundi_Actor $group ) {
	global $wpdb;
	$object = $create->get_payload()['object'] ?? null;
	$person = axismundi_actors_get_by_uri( $create->get_actor_uri() );
	if ( ! axismundi_actors_is_community( $group ) || 'public' !== $group->get_status() || 'Create' !== $create->get_type() || ! $create->is_effective()
		|| ! $person || 'Person' !== $person->get_type() || 'public' !== $person->get_status() || ! is_array( $object ) || 'Note' !== ( $object['type'] ?? '' )
		|| $person->get_uri() !== axismundi_act_object_publisher_uri( $object ) || ! axismundi_act_is_publicly_renderable( $create ) ) { return new WP_Error( 'ax_community_reply', 'An eligible Person Note reply is required.', array( 'status' => 403 ) ); }
	$parent_uri = axismundi_act_member_uri( $object['inReplyTo'] ?? '' );
	if ( '' === $parent_uri || ! axismundi_act_community_parent_accepted( $parent_uri, $group ) ) { return new WP_Error( 'ax_community_parent', 'The Community has not accepted the reply parent.', array( 'status' => 409 ) ); }
	$addresses = array();
	foreach ( array( $create->get_payload(), $object ) as $document ) {
		foreach ( array( 'to', 'cc', 'audience' ) as $field ) { $addresses = array_merge( $addresses, axismundi_op_application_recipient_uris( $document[$field] ?? null ) ); }
	}
	if ( ! in_array( $group->get_uri(), $addresses, true ) || ! $wpdb->get_var( $wpdb->prepare( 'SELECT id FROM %i WHERE activity_id=%d AND actor_identity_id=%d', axismundi_act_inbox_table(), $create->get_id(), $group->get_identity_id() ) ) ) { return new WP_Error( 'ax_community_receipt', 'A Group address and Inbox receipt are required.', array( 'status' => 403 ) ); }
	if ( $person->is_local() ) {
		$row = axismundi_op_get_local_object( $create->get_object_uri() );
		$valid = 'outbound' === $create->get_direction() && $row && 'active' === $row['object_status'] && ! empty( $row['publicly_readable'] ) && $row['attributed_to_uri'] === $person->get_uri();
	} else {
		$row = axismundi_op_get_remote_object( $create->get_object_uri(), false );
		$valid = 'inbound' === $create->get_direction() && $row && axismundi_op_remote_object_is_publicly_listable( $row ) && $row['attributed_to_uri'] === $person->get_uri();
	}
	if ( ! $valid ) { return new WP_Error( 'ax_community_unresolved', 'The reply source is unavailable.', array( 'status' => 409 ) ); }
	return axismundi_actors_admit_community_post( $group, $create->get_object_uri(), $create->get_uri(), $person->get_uri() );
}

/** Group routing is not a personal profile publication, even when Public is in cc. */
add_filter( 'axismundi_act_public_outbox_payload', static function ( $payload, Axismundi_Activity $activity ) {
	$object = $activity->get_payload()['object'] ?? null;
	if ( 'outbound' === $activity->get_direction() && 'Create' === $activity->get_type() && is_array( $object ) && 'Note' === ( $object['type'] ?? '' ) ) {
		$group = axismundi_actors_get_by_uri( axismundi_act_member_uri( $object['audience'] ?? '' ) );
		if ( $group && 'Group' === $group->get_type() ) { return null; }
	}
	return $payload;
}, 30, 2 );
