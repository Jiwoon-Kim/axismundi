<?php
/** Unordered attribution: publisher authority, complete recipients and collection passthrough. */
defined( 'ABSPATH' ) || exit( 1 );
// Exercise installed-but-inactive Forum services without activating the plugin or retired Note runtime.
foreach ( array( 'repository', 'topics', 'memberships', 'outbound-topics', 'distribution', 'votes' ) as $module ) { require_once WP_PLUGIN_DIR . '/axismundi-forum/includes/' . $module . '.php'; }
axismundi_forum_install();
global $wpdb;
$checks = 0;
$assert = static function ( $ok, string $label ) use ( &$checks ) : void {
	if ( ! $ok ) { throw new RuntimeException( $label ); }
	++$checks; echo "PASS $label\n";
};
$actors = array(); $user = 0;
foreach ( $wpdb->get_results( 'SELECT DISTINCT identity_id,principal_id FROM ' . axismundi_actors_managers_table() . " WHERE principal_kind='wp_user'", ARRAY_A ) as $manager ) {
	$actor = axismundi_actors_get_by_identity( (int) $manager['identity_id'] );
	if ( $actor && 'Person' === $actor->get_type() && true === axismundi_act_outbox_authorize( (int) $manager['principal_id'], $actor ) ) {
		if ( ! $actors ) { $user = (int) $manager['principal_id']; }
		$actors[$actor->get_uri()] = $actor;
		if ( 3 === count( $actors ) ) { break; }
	}
}
$assert( 3 === count( $actors ), 'Three existing operable local Persons are available without changing their grants' );
list( $publisher, $collaborator, $reactor ) = array_values( $actors );
$initial_user = get_current_user_id();
$bridge_priority = has_action( 'axismundi_act_activity_recorded', 'axismundi_activitypub_bridge_queue_outbound' );
if ( false !== $bridge_priority ) { remove_action( 'axismundi_act_activity_recorded', 'axismundi_activitypub_bridge_queue_outbound', $bridge_priority ); }
$recorded = array(); $posts = array(); $object_uris = array(); $group = null;
$observe = static function ( $activity ) use ( &$recorded ) { $recorded[$activity->get_uri()] = $activity; };
$owner = static fn() => 'joint-attribution-audit';
add_action( 'axismundi_act_activity_recorded', $observe, 999 );
add_filter( 'axismundi_op_post_lifecycle_owner', $owner, PHP_INT_MAX );
try {
	wp_set_current_user( $user );
	$post_id = wp_insert_post( array( 'post_type' => 'post', 'post_status' => 'publish', 'post_author' => $user, 'post_title' => 'Joint attribution audit', 'post_content' => 'Joint source' ) );
	$posts[] = $post_id; $post = get_post( $post_id );
	$assert( true === axismundi_op_store_post_attribution( $post, $publisher->get_uri(), array( $collaborator->get_uri() ) ), 'Explicit publisher and collaborator are stored independently' );
	$object = axismundi_op_transform_object( $post );
	$assert( is_array( $object ) && is_array( $object['attributedTo'] ) && 2 === count( $object['attributedTo'] ), 'Post projection emits both attributions' );
	$uri = $object['id']; $object_uris[] = $uri;
	$reversed = $object; $reversed['attributedTo'] = array( $collaborator->get_uri(), $publisher->get_uri() );
	$assert( $publisher->get_uri() === axismundi_act_object_publisher_uri( $reversed ), 'The second wire member remains publisher when registry authority says so' );
	$assert( axismundi_act_lifecycle_fingerprint( $object ) === axismundi_act_lifecycle_fingerprint( $reversed ), 'Reordering the attribution set does not create a lifecycle change' );
	$forged = $object; $forged['attributedTo'] = $collaborator->get_uri();
	$assert( '' === axismundi_act_object_publisher_uri( $forged ), 'A scalar claim cannot replace an established local publisher' );
	$remote = $reversed; $remote['id'] = 'https://example.test/unknown-joint/' . wp_generate_uuid4();
	$assert( '' === axismundi_act_object_publisher_uri( $remote ), 'Unknown remote multi-author authority is unresolved, not first-member selected' );
	$remote['attributedTo'] = array_reverse( $remote['attributedTo'] );
	$assert( '' === axismundi_act_object_publisher_uri( $remote ), 'Unknown remote authority stays unresolved after array reordering' );
	$assert( array( $collaborator->get_uri(), $publisher->get_uri() ) === axismundi_act_attribution_uris( array( array( 'id' => $collaborator->get_uri() ), $publisher->get_uri(), $collaborator->get_uri() ) ), 'Recipient normalization retains every distinct descriptor and URI' );
	$invalid_recipients = axismundi_act_reaction_recipients( array( $publisher->get_uri(), 'not-an-actor-uri' ) );
	$assert( is_wp_error( $invalid_recipients ) && 'ax_act_reaction_recipient' === $invalid_recipients->get_error_code(), 'A malformed second recipient cannot be silently dropped' );
	$question = $reversed; $question['type'] = 'Question'; $question['oneOf'] = array( array( 'type' => 'Note', 'name' => 'One' ), array( 'type' => 'Note', 'name' => 'Two' ) );
	$question_data = axismundi_act_question_object( $question );
	$assert( is_array( $question_data ) && $question_data['author_uri'] === $publisher->get_uri(), 'Question author resolution uses publisher rather than its first attribution' );
	$question['id'] = $remote['id'];
	$unresolved_question = axismundi_act_question_object( $question );
	$assert( is_wp_error( $unresolved_question ) && 'ax_act_question_author' === $unresolved_question->get_error_code(), 'Unknown multi-author Question fails with the author-specific error' );
	$quote_target = 'https://example.test/quoted/' . wp_generate_uuid4();
	$quote_row = array( 'actor_uri' => $publisher->get_uri(), 'object_uri' => $quote_target, 'payload' => array( 'instrument' => $reversed ) );
	$assert( true === axismundi_act_validate_quote_request_instrument( Axismundi_Activity::from_row( $quote_row ) ), 'An inline quote accepts registry publisher in the second position' );
	$quote_row['actor_uri'] = $collaborator->get_uri();
	$bad_quote = axismundi_act_validate_quote_request_instrument( Axismundi_Activity::from_row( $quote_row ) );
	$assert( is_wp_error( $bad_quote ) && 'ax_act_quote_instrument_actor' === $bad_quote->get_error_code(), 'First-position collaborator cannot impersonate the quote publisher' );
	$quote_row['actor_uri'] = $publisher->get_uri(); $quote_row['payload']['instrument'] = $remote;
	$assert( is_wp_error( axismundi_act_validate_quote_request_instrument( Axismundi_Activity::from_row( $quote_row ) ) ), 'Unknown remote multi-author quote authority is not accepted as omitted attribution' );
	$bad_outbound = axismundi_act_record_outbound_quote_request( array( 'instrument' => $reversed, 'quoted_object_uri' => $quote_target, 'author_actor_uri' => $collaborator->get_uri(), 'target_author_uri' => $reactor->get_uri() ) );
	$assert( is_wp_error( $bad_outbound ) && 'ax_act_outbound_quote_attribution' === $bad_outbound->get_error_code(), 'Outbound quote rejects collaborator-as-publisher before writing' );
	$sentiment = axismundi_act_resolve_sentiment_target( $uri );
	$announce = axismundi_act_resolve_announce_target( $uri );
	foreach ( array( 'sentiment' => $sentiment, 'announce' => $announce ) as $kind => $target ) {
		$assert( is_array( $target ) && 2 === count( $target['recipient_uris'] ) && in_array( $publisher->get_uri(), $target['recipient_uris'], true ) && in_array( $collaborator->get_uri(), $target['recipient_uris'], true ), 'Resolver retains all joint recipients: ' . $kind );
	}
	foreach ( array( 'Like', 'Dislike', 'EmojiReact', 'Announce' ) as $kind ) {
		$recipients = array( $collaborator->get_uri(), $publisher->get_uri() );
		if ( 'Like' === $kind ) { $activity = axismundi_act_like_object( $reactor, $uri, $recipients ); }
		elseif ( 'Dislike' === $kind ) { $activity = axismundi_act_dislike_object( $reactor, $uri, $recipients ); }
		elseif ( 'EmojiReact' === $kind ) { $activity = axismundi_act_react_to_object( $reactor, $uri, "\xF0\x9F\x91\x8D", $recipients ); }
		else { $activity = axismundi_act_announce_object( $reactor, $uri, $recipients ); }
		$audience = $activity instanceof Axismundi_Activity ? $activity->get_audience() : array();
		$addressed = array_merge( $audience['to'] ?? array(), $audience['cc'] ?? array() );
		$assert( $activity instanceof Axismundi_Activity && in_array( $publisher->get_uri(), $addressed, true ) && in_array( $collaborator->get_uri(), $addressed, true ), 'Recorded interaction retains both recipients: ' . $kind );
	}
	$collections = array(
		'likes' => axismundi_op_object_likes_transform( new Axismundi_OP_Object_Likes( $uri, $post ) ),
		'shares' => axismundi_op_object_shares_transform( new Axismundi_OP_Object_Shares( $uri, $post ) ),
		'emoji' => axismundi_op_object_emoji_reactions_transform( new Axismundi_OP_Object_Emoji_Reactions( $uri, $post ) ),
		'replies-root' => axismundi_op_object_replies_transform( new Axismundi_OP_Object_Replies( $uri, $post ) ),
		'replies-page' => axismundi_op_object_replies_transform( new Axismundi_OP_Object_Replies( $uri, $post, 1 ) ),
	);
	foreach ( $collections as $kind => $collection ) { $assert( is_array( $collection ) && $object['attributedTo'] === $collection['attributedTo'], 'Collection passes the attribution array through unchanged: ' . $kind ); }
	$first_create = axismundi_act_record_object_commit( $object, $publisher->get_uri() );
	$assert( $first_create instanceof Axismundi_Activity, 'A joint Object lifecycle can be committed with an explicit publisher' );
	$old_create = $first_create->get_payload();
	$changed = axismundi_op_set_post_attribution( $post_id, $publisher->get_uri(), $user, array( $collaborator->get_uri(), $reactor->get_uri() ) );
	$latest = axismundi_act_get_object_lifecycle( $uri );
	$assert( true === $changed && $latest && 'Update' === $latest->get_type() && $publisher->get_uri() === $latest->get_actor_uri() && in_array( $reactor->get_uri(), $latest->get_payload()['object']['attributedTo'], true ), 'Changing collaborators emits Update under the unchanged publisher Account' );
	$assert( $old_create === axismundi_act_get( $first_create->get_uri() )->get_payload(), 'Joint attribution edits do not rewrite the original Create' );
	$without_member = $latest->get_payload()['object'];
	$without_member['attributedTo'] = array( $publisher->get_uri(), $reactor->get_uri() );
	$direct_removal = axismundi_act_record_object_commit( $without_member, $publisher->get_uri() );
	$assert( is_wp_error( $direct_removal ) && 'ax_act_object_attribution' === $direct_removal->get_error_code()
		&& $latest->get_uri() === axismundi_act_get_object_lifecycle( $uri )->get_uri(), 'Direct snapshot Update cannot remove a published collaborator' );
	$removal = axismundi_op_set_post_attribution( $post_id, $publisher->get_uri(), $user, array( $collaborator->get_uri() ) );
	$assert( is_wp_error( $removal ) && 'ax_op_attribution_removal' === $removal->get_error_code(), 'Removing a published collaborator is rejected' );
	if ( class_exists( 'Axismundi_Note_Source' ) && function_exists( 'axismundi_forum_note_reply_parent_actor_uris' ) ) {
		$reply_source = new Axismundi_Note_Source( array( 'in_reply_to_uri' => $uri ), null );
		$assert( 3 === count( axismundi_forum_note_reply_parent_actor_uris( $reply_source ) ), 'Retired Note reply adapter retains every parent author as a recipient' );
	}
	if ( function_exists( 'axismundi_forum_submitted_object_community_group' ) ) {
		$group_uri = 'https://example.com/groups/joint-' . wp_generate_uuid4();
		$group = axismundi_actors_upsert_remote( array( 'uri' => $group_uri, 'actor_type' => 'Group', 'preferred_username' => 'joint-audit', 'display_name' => 'Joint audit', 'profile_url' => $group_uri, 'endpoints' => array( 'inbox' => $group_uri . '/inbox', 'outbox' => $group_uri . '/outbox', 'followers' => $group_uri . '/followers' ), 'payload' => array( 'id' => $group_uri, 'type' => 'Group', 'inbox' => $group_uri . '/inbox', 'outbox' => $group_uri . '/outbox', 'followers' => $group_uri . '/followers' ) ) );
		if ( is_wp_error( $group ) ) { echo 'GROUP FIXTURE ERROR: ' . $group->get_error_message() . "\n"; }
		$assert( $group instanceof Axismundi_Actor, 'Remote Group fixture is available without a network request' );
		$submission_object = $reversed; $submission_object['audience'] = $group_uri;
		$submission = array( 'type' => 'Create', 'actor' => $collaborator->get_uri(), 'object' => $submission_object, 'to' => array( $group_uri ) );
		$forged_submission = axismundi_act_record_source_activity( $submission, 'outbound', 'joint-forged:' . wp_generate_uuid4() );
		$assert( $forged_submission instanceof Axismundi_Activity && null === axismundi_forum_submitted_object_community_group( $uri, false ), 'First-position collaborator cannot submit the publisher\'s Object to a Group' );
		$submission['actor'] = $publisher->get_uri();
		$good_submission = axismundi_act_record_source_activity( $submission, 'outbound', 'joint-valid:' . wp_generate_uuid4() );
		$resolved_group = axismundi_forum_submitted_object_community_group( $uri, false );
		$assert( $good_submission instanceof Axismundi_Activity && $resolved_group && $group_uri === $resolved_group->get_uri(), 'Second-position registry publisher can submit the same joint Object to the Group' );
	}
	echo "PASS $checks joint attribution checks\n";
} finally {
	foreach ( $posts as $post_id ) { wp_delete_post( $post_id, true ); }
	foreach ( $recorded as $activity ) {
		$wpdb->delete( axismundi_act_inbox_table(), array( 'activity_id' => $activity->get_id() ) );
		if ( function_exists( 'axismundi_activitypub_bridge_delivery_table' ) ) { $wpdb->delete( axismundi_activitypub_bridge_delivery_table(), array( 'activity_uri' => $activity->get_uri() ) ); }
		$wpdb->delete( axismundi_act_activities_table(), array( 'activity_uri' => $activity->get_uri() ) );
	}
	foreach ( $object_uris as $uri ) {
		axismundi_op_delete_object_listing_projection( $uri );
		$wpdb->delete( axismundi_op_objects_table(), array( 'canonical_uri' => $uri ) );
	}
	if ( $group instanceof Axismundi_Actor ) {
		foreach ( array( axismundi_actors_texts_table(), axismundi_actors_addresses_table(), axismundi_actors_endpoints_table(), axismundi_actors_asset_cache_table(), axismundi_actors_keys_table(), axismundi_actors_fetch_state_table(), axismundi_actors_actors_table() ) as $table ) { $wpdb->delete( $table, array( 'identity_id' => $group->get_identity_id() ) ); }
		$wpdb->delete( axismundi_actors_identities_table(), array( 'id' => $group->get_identity_id() ) );
	}
	remove_action( 'axismundi_act_activity_recorded', $observe, 999 );
	remove_filter( 'axismundi_op_post_lifecycle_owner', $owner, PHP_INT_MAX );
	if ( false !== $bridge_priority ) { add_action( 'axismundi_act_activity_recorded', 'axismundi_activitypub_bridge_queue_outbound', $bridge_priority ); }
	wp_set_current_user( $initial_user );
}
