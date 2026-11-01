<?php
/**
 * Focused Person Follow/Block regression audit. Development WP-CLI only.
 *
 * Run with wp eval-file, or define AX_PERSON_BLOCK_AUDIT_NO_AUTORUN=true,
 * require this file, and call
 * axismundi_act_audit_person_block() from a mutation harness.
 * No schema installation, shared Actors, HTTP, or transport queue execution.
 *
 * @package AxismundiActivities
 */

defined( 'ABSPATH' ) || exit( 1 );

/** Run an isolated fixture; assertion failures have exactly the named label as message. */
function axismundi_act_audit_person_block() : int {
	global $wpdb;
	require_once ABSPATH . 'wp-admin/includes/user.php';
	$users = $identities = $uris = array();
	$old_user = get_current_user_id();
	$old_get = $_GET;
	$old_cookies = $_COOKIE;
	$checks = $http_calls = 0;
	$suffix = strtolower( str_replace( '-', '', wp_generate_uuid4() ) );
	$queue = 'axismundi_activitypub_bridge_queue_outbound';
	$priority = has_action( 'axismundi_act_activity_recorded', $queue );
	$assert = static function ( string $label, bool $ok ) use ( &$checks ) : void {
		if ( ! $ok ) { throw new RuntimeException( $label ); }
		++$checks;
		printf( "[PASS] %s\n", $label );
	};
	$http = static function () use ( &$http_calls ) {
		++$http_calls;
		return new WP_Error( 'ax_person_block_audit_http', 'Fixture forbids HTTP.' );
	};
	$person = static function () use ( &$users, &$identities, &$uris, $suffix, $assert ) : Axismundi_Actor {
		$login = 'axpb_' . substr( $suffix, 0, 16 ) . '_' . count( $users );
		$id = wp_create_user( $login, wp_generate_password( 32 ), $login . '@example.test' );
		$assert( 'fixture.user', ! is_wp_error( $id ) );
		$users[] = (int) $id;
		( new WP_User( $id ) )->set_role( 'contributor' );
		$actor = axismundi_actors_ensure_for_user( $id );
		$assert( 'fixture.person', $actor instanceof Axismundi_Actor );
		// Track immediately, including failures during handle activation.
		$identities[] = $actor->get_identity_id();
		$uris[] = $actor->get_uri();
		$assert( 'fixture.handle', ! is_wp_error( axismundi_actors_register_handle( $actor->get_identity_id(), $login ) ) );
		$assert( 'fixture.public', (bool) axismundi_actors_set_status( $actor->get_identity_id(), 'public' ) );
		$actor = axismundi_actors_get_for_user( $id );
		$assert( 'fixture.activated-person', $actor instanceof Axismundi_Actor && 'Person' === $actor->get_type() && $actor->is_local() );
		$uris[] = $actor->get_uri();
		return $actor;
	};
	$policy = static function ( Axismundi_Actor $actor, bool $manual ) use ( $assert ) : Axismundi_Actor {
		$assert( 'fixture.policy', true === axismundi_actors_set_local_policy( $actor, 'manually_approves_followers', $manual, (int) $actor->get_local_user_id() ) );
		return axismundi_actors_get_by_uri( $actor->get_uri() );
	};
	$relation = static fn( $a, $b ) => axismundi_act_get_relation( 'follow', $a->get_uri(), $b->get_uri() );
	$record = static function ( string $type, Axismundi_Actor $actor, string $object, string $direction = 'outbound' ) use ( $assert, $suffix ) : Axismundi_Activity {
		$row = axismundi_act_record_activity( array(
			'id' => 'inbound' === $direction ? 'https://example.test/axpb/' . $suffix . '/' . wp_generate_uuid4() : axismundi_act_local_uri( wp_generate_uuid4() ),
			'type' => $type, 'actor' => $actor->get_uri(), 'object' => $object,
		), $direction );
		$assert( 'fixture.record.' . $type, $row instanceof Axismundi_Activity );
		return $row;
	};
	$count_rows = static function () use ( &$uris, $wpdb ) : array {
		$counts = array( 0, 0, 0 );
		foreach ( array_unique( $uris ) as $uri ) {
			$counts[0] += (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE actor_uri = %s', axismundi_act_activities_table(), $uri ) );
			$counts[1] += (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE subject_actor_uri = %s', axismundi_act_relations_table(), $uri ) );
		}
		foreach ( array_unique( $uris ) as $uri ) {
			$a = axismundi_actors_get_by_uri( $uri );
			if ( $a instanceof Axismundi_Actor ) {
				$counts[2] += (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE actor_identity_id = %d', axismundi_act_inbox_table(), $a->get_identity_id() ) );
			}
		}
		return $counts;
	};
	$snapshot = static function () use ( $wpdb ) : array {
		$tables = array( axismundi_act_activities_table(), axismundi_act_relations_table(), axismundi_act_inbox_table(), axismundi_actors_identities_table(), axismundi_actors_actors_table(), axismundi_actors_managers_table(), $wpdb->users, $wpdb->usermeta );
		$result = array();
		foreach ( $tables as $table ) {
			$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM %i', $table ), ARRAY_A );
			if ( '' !== $wpdb->last_error ) { throw new RuntimeException( 'Snapshot read failed' ); }
			$rows = array_map( static fn( $row ) => wp_json_encode( $row ), $rows );
			sort( $rows, SORT_STRING );
			$result[ $table ] = hash( 'sha256', implode( "\n", $rows ) );
		}
		return $result;
	};
	$baseline = $snapshot();
	try {
		add_filter( 'pre_http_request', $http, PHP_INT_MAX, 3 );
		if ( false !== $priority ) { remove_action( 'axismundi_act_activity_recorded', $queue, $priority ); }
		$_GET = array();
		$_COOKIE = array();
		$assert( 'fixture.repositories-ready', axismundi_act_relations_ready() && function_exists( 'axismundi_activitypub_bridge_receive_local_activity' ) );
		// Explicit Actor policies avoid modifying any existing site option.
		$a = $policy( $person(), false );
		$b = $policy( $person(), true );
		wp_set_current_user( (int) $a->get_local_user_id() );
		$first = axismundi_act_follow_actor( $a, $b );
		$assert( 'refollow.initial-pending', is_array( $first ) && 'pending' === $first['state'] );
		$stored = axismundi_act_transaction_row( $first['initiating_activity_uri'] );
		$wpdb->update( axismundi_act_activities_table(), array( 'direction' => 'local' ), array( 'id' => $stored['id'] ) );
		$wpdb->update( axismundi_act_relations_table(), array( 'direction' => 'local' ), array( 'id' => $first['id'] ) );
		$assert( 'direction.legacy-metadata-migration', axismundi_act_migrate_legacy_directions() && 'outbound' === axismundi_act_get( $first['initiating_activity_uri'] )->get_direction() && 'outbound' === $relation( $a, $b )['direction'] );
		$after_migration = axismundi_act_transaction_row( $first['initiating_activity_uri'] );
		$assert( 'direction.migration-preserves-identity-payload', $stored['activity_uri'] === $after_migration['activity_uri'] && $stored['payload_json'] === $after_migration['payload_json'] && $stored['payload_hash'] === $after_migration['payload_hash'] );
		wp_set_current_user( (int) $b->get_local_user_id() );
		$rejected = axismundi_act_respond_to_local_follow( $b, $first['initiating_activity_uri'], 'reject' );
		$assert( 'refollow.rejected', is_array( $rejected ) && 'rejected' === $rejected['state'] );
		wp_set_current_user( (int) $a->get_local_user_id() );
		$second = axismundi_act_follow_actor( $a, $b );
		$assert( 'refollow.rejected-to-fresh-pending', is_array( $second ) && 'pending' === $second['state'] && $first['initiating_activity_uri'] !== $second['initiating_activity_uri'] );
		$b = $policy( $b, false );
		$assert( 'approval.no-retroactive-accept', 'pending' === $relation( $a, $b )['state'] );
		wp_set_current_user( (int) $b->get_local_user_id() );
		$requests = axismundi_act_get_pending_follow_requests( $b->get_uri() );
		$assert( 'approval.pending-request-reader', 1 === count( $requests ) && $second['initiating_activity_uri'] === $requests[0]['initiating_activity_uri'] );
		// Supply a fixture-only browser selection without emitting response cookies.
		if ( axismundi_actors_account_selection_enabled() ) {
			$expires = time() + 600;
			$user_id = (int) $b->get_local_user_id();
			$token = WP_Session_Tokens::get_instance( $user_id )->create( $expires );
			$_COOKIE[ LOGGED_IN_COOKIE ] = wp_generate_auth_cookie( $user_id, $expires, 'logged_in', $token );
			$session = axismundi_actors_browser_session( $user_id );
			$assert( 'fixture.browser-session', is_array( $session ) );
			$unsigned = 'v1|' . $b->get_identity_id() . '|' . $user_id . '|' . $session['expiration'];
			$_COOKIE[ axismundi_actors_selection_cookie_name() ] = $unsigned . '|' . hash_hmac( 'sha256', $unsigned . '|' . $session['binding'], wp_salt( 'auth' ) );
		}
		$level = ob_get_level();
		try { ob_start(); axismundi_act_render_follows_page(); $html = (string) ob_get_contents(); }
		finally { while ( ob_get_level() > $level ) { ob_end_clean(); } }
		$assert( 'approval.pending-request-ui', str_contains( $html, 'Follow requests' ) && str_contains( $html, $a->get_preferred_username() ) && str_contains( $html, 'Accept' ) && str_contains( $html, '_wpnonce' ) );
		$rejected = axismundi_act_respond_to_local_follow( $b, $second['initiating_activity_uri'], 'reject' );
		$assert( 'refollow.second-rejected', is_array( $rejected ) && 'rejected' === $rejected['state'] );
		wp_set_current_user( (int) $a->get_local_user_id() );
		$third = axismundi_act_follow_actor( $a, $b );
		$assert( 'refollow.rejected-to-fresh-accepted', is_array( $third ) && 'accepted' === $third['state'] && $third['initiating_activity_uri'] !== $second['initiating_activity_uri'] );

		foreach ( array( false, true ) as $manual_a ) {
			foreach ( array( false, true ) as $manual_b ) {
				$tag = ( $manual_a ? 'pending' : 'accepted' ) . '-' . ( $manual_b ? 'pending' : 'accepted' );
				$a = $policy( $person(), $manual_a );
				$b = $policy( $person(), $manual_b );
				wp_set_current_user( (int) $a->get_local_user_id() );
				$ab = axismundi_act_follow_actor( $a, $b );
				wp_set_current_user( (int) $b->get_local_user_id() );
				$ba = axismundi_act_follow_actor( $b, $a );
				$assert( 'block.setup.' . $tag, is_array( $ab ) && is_array( $ba ) && ( $manual_b ? 'pending' : 'accepted' ) === $ab['state'] && ( $manual_a ? 'pending' : 'accepted' ) === $ba['state'] );
				if ( ! $manual_a ) {
					$announcement = axismundi_act_record_activity( array( 'type' => 'Announce', 'actor' => $a->get_uri(), 'object' => 'https://example.com/axpb/announced', 'to' => array( axismundi_op_actor_followers_url( $a ) ) ), 'outbound' );
					$assert( 'fixture.collection-address', $announcement instanceof Axismundi_Activity );
					axismundi_activitypub_bridge_activity_inboxes( $announcement, true );
					$receipt = $wpdb->get_var( $wpdb->prepare( 'SELECT id FROM %i WHERE activity_id=%d AND actor_identity_id=%d', axismundi_act_inbox_table(), $announcement->get_id(), $b->get_identity_id() ) );
					$assert( 'transport.local-expanded-follower.' . $tag, (int) $receipt > 0 );
				}
				wp_set_current_user( (int) $a->get_local_user_id() );
				$block = axismundi_act_block_actor( $a, $b );
				$assert( 'block.active.' . $tag, is_array( $block ) && 'active' === $block['state'] );
				$assert( 'block.closes-both-directions.' . $tag, 'undone' === $relation( $a, $b )['state'] && 'rejected' === $relation( $b, $a )['state'] && array() === axismundi_act_get_following( $a->get_uri() ) && array() === axismundi_act_get_followers( $a->get_uri() ) && array() === axismundi_act_get_pending_follow_requests( $a->get_uri() ) && array() === axismundi_act_get_pending_follow_requests( $b->get_uri() ) );
				$cleanup_undo = axismundi_act_get( $relation( $a, $b )['state_activity_uri'] );
				$cleanup_reject = axismundi_act_get( $relation( $b, $a )['state_activity_uri'] );
				$assert( 'block.cleanup-authored-only-by-blocker.' . $tag, $cleanup_undo instanceof Axismundi_Activity && 'Undo' === $cleanup_undo->get_type() && $a->get_uri() === $cleanup_undo->get_actor_uri() && $cleanup_reject instanceof Axismundi_Activity && 'Reject' === $cleanup_reject->get_type() && $a->get_uri() === $cleanup_reject->get_actor_uri() );
				$assert( 'block.pair-gate-both-directions.' . $tag, true === axismundi_act_follow_pair_blocked( $a->get_uri(), $b->get_uri() ) && true === axismundi_act_follow_pair_blocked( $b->get_uri(), $a->get_uri() ) );
				foreach ( array( array( $a, $b ), array( $b, $a ) ) as $index => [ $sender, $target ] ) {
					wp_set_current_user( (int) $sender->get_local_user_id() );
					$before = $count_rows();
					$denied = axismundi_act_follow_actor( $sender, $target );
					$assert( 'block.follow-denied.' . $tag . '.' . $index, is_wp_error( $denied ) && 'ax_act_follow_blocked' === $denied->get_error_code() && $before === $count_rows() );
					$payload = array( 'id' => axismundi_act_local_uri( wp_generate_uuid4() ), 'type' => 'Follow', 'actor' => $sender->get_uri(), 'object' => $target->get_uri() );
					$denied = axismundi_act_record_activity( $payload, 'outbound' );
					$assert( 'block.ledger-follow-denied.' . $tag . '.' . $index, is_wp_error( $denied ) && 'ax_act_follow_blocked' === $denied->get_error_code() && null === axismundi_act_get( $payload['id'] ) && $before === $count_rows() );
				}
				$record( 'Accept', $b, $ab['initiating_activity_uri'] );
				$record( 'Accept', $a, $ba['initiating_activity_uri'] );
				$assert( 'block.late-accept-before-unblock.' . $tag, 'undone' === $relation( $a, $b )['state'] && 'rejected' === $relation( $b, $a )['state'] );
				wp_set_current_user( (int) $b->get_local_user_id() );
				$before = $count_rows();
				$denied = axismundi_act_unblock_actor( $a, $b );
				$assert( 'block.unauthorized-unblock.' . $tag, is_wp_error( $denied ) && 'ax_act_block_forbidden' === $denied->get_error_code() && true === axismundi_act_actor_blocks( $a->get_uri(), $b->get_uri() ) && $before === $count_rows() );
				wp_set_current_user( (int) $a->get_local_user_id() );
				$before = $count_rows();
				$duplicate = axismundi_act_block_actor( $a, $b );
				$assert( 'block.duplicate-no-rows.' . $tag, $duplicate === $block && $before === $count_rows() );
				$activity = axismundi_act_get( $block['initiating_activity_uri'] );
				$assert( 'block.local-outbound.' . $tag, $activity instanceof Axismundi_Activity && 'outbound' === $activity->get_direction() );
				$before = $count_rows();
				$replay = axismundi_act_record_activity( $activity->get_payload(), 'outbound' );
				$assert( 'block.ledger-replay-no-rows.' . $tag, $replay instanceof Axismundi_Activity && $activity->get_id() === $replay->get_id() && $before === $count_rows() );
				$assert( 'block.private-audience.' . $tag, array( $b->get_uri() ) === (array) $activity->get_audience()['to'] && ! str_contains( wp_json_encode( $activity->get_audience() ), 'https://www.w3.org/ns/activitystreams#Public' ) );
				$before = $count_rows();
				$received = axismundi_activitypub_bridge_receive_local_activity( $activity->get_uri(), $b->get_identity_id() );
				$assert( 'block.local-receipt-shared-row.' . $tag, $received instanceof Axismundi_Activity && $activity->get_id() === $received->get_id() && 'outbound' === axismundi_act_get( $activity->get_uri() )->get_direction() && $before[0] === $count_rows()[0] );
				$members = $wpdb->get_col( $wpdb->prepare( 'SELECT actor_identity_id FROM %i WHERE activity_id = %d', axismundi_act_inbox_table(), $activity->get_id() ) );
				$assert( 'block.local-receipt-membership.' . $tag, array( $b->get_identity_id() ) === array_map( 'intval', $members ) );
				$before = $count_rows();
				$received = axismundi_activitypub_bridge_receive_local_activity( $activity->get_uri(), $b->get_identity_id() );
				$assert( 'block.receipt-replay-no-rows.' . $tag, $received instanceof Axismundi_Activity && $activity->get_id() === $received->get_id() && $before === $count_rows() );
				$unblocked = axismundi_act_unblock_actor( $a, $b );
				$assert( 'unblock.no-restoration.' . $tag, is_array( $unblocked ) && 'undone' === $unblocked['state'] && false === axismundi_act_follow_pair_blocked( $a->get_uri(), $b->get_uri() ) && 'undone' === $relation( $a, $b )['state'] && 'rejected' === $relation( $b, $a )['state'] );
				$undo = $wpdb->get_row( $wpdb->prepare( "SELECT activity_uri FROM %i WHERE actor_uri = %s AND object_uri = %s AND activity_type = 'Undo'", axismundi_act_activities_table(), $a->get_uri(), $activity->get_uri() ), ARRAY_A );
				$assert( 'unblock.undo-recorded.' . $tag, is_array( $undo ) );
				$assert( 'unblock.actual-undo-provenance.' . $tag, $undo['activity_uri'] === $unblocked['state_activity_uri'] );
				$undo_activity = axismundi_act_get( $undo['activity_uri'] );
				$assert( 'unblock.private-audience.' . $tag, array( $b->get_uri() ) === (array) $undo_activity->get_audience()['to'] && ! str_contains( wp_json_encode( $undo_activity->get_audience() ), 'https://www.w3.org/ns/activitystreams#Public' ) );
				$received = axismundi_activitypub_bridge_receive_local_activity( $undo['activity_uri'], $b->get_identity_id() );
				$assert( 'unblock.local-receipt-shared-row.' . $tag, $received instanceof Axismundi_Activity && $undo_activity->get_id() === $received->get_id() );
				$before = $count_rows();
				$replay = axismundi_act_record_activity( $undo_activity->get_payload(), 'outbound' );
				$received = axismundi_activitypub_bridge_receive_local_activity( $undo['activity_uri'], $b->get_identity_id() );
				$assert( 'unblock.ledger-and-receipt-replay-no-rows.' . $tag, $replay instanceof Axismundi_Activity && $received instanceof Axismundi_Activity && $undo_activity->get_id() === $replay->get_id() && $undo_activity->get_id() === $received->get_id() && $before === $count_rows() );
				$before = $count_rows();
				$duplicate = axismundi_act_unblock_actor( $a, $b );
				$assert( 'unblock.duplicate-no-rows.' . $tag, $duplicate === $unblocked && $before === $count_rows() );
				$record( 'Accept', $b, $ab['initiating_activity_uri'] );
				$record( 'Accept', $a, $ba['initiating_activity_uri'] );
				$assert( 'block.late-accept-after-unblock.' . $tag, 'undone' === $relation( $a, $b )['state'] && 'rejected' === $relation( $b, $a )['state'] );
				$new_ab = axismundi_act_follow_actor( $a, $b );
				wp_set_current_user( (int) $b->get_local_user_id() );
				$new_ba = axismundi_act_follow_actor( $b, $a );
				$assert( 'unblock.new-follow-both-directions.' . $tag, is_array( $new_ab ) && is_array( $new_ba ) && $new_ab['initiating_activity_uri'] !== $ab['initiating_activity_uri'] && $new_ba['initiating_activity_uri'] !== $ba['initiating_activity_uri'] && ( $manual_b ? 'pending' : 'accepted' ) === $new_ab['state'] && ( $manual_a ? 'pending' : 'accepted' ) === $new_ba['state'] );
				$record( 'Accept', $b, $ab['initiating_activity_uri'] );
				$record( 'Accept', $a, $ba['initiating_activity_uri'] );
				$assert( 'block.old-cycle-accept-cannot-replace-newer.' . $tag, $new_ab === $relation( $a, $b ) && $new_ba === $relation( $b, $a ) );
			}
		}

		$a = $policy( $person(), true );
		$remote_uri = 'https://example.com/axpb/remote/' . $suffix;
		$r = axismundi_actors_upsert_remote( array( 'uri' => $remote_uri, 'actor_type' => 'Person', 'preferred_username' => 'axpb_' . $suffix, 'payload' => array( 'id' => $remote_uri, 'type' => 'Person' ), 'endpoints' => array( 'inbox' => $remote_uri . '/inbox', 'outbox' => $remote_uri . '/outbox' ) ) );
		$assert( 'fixture.remote-cache', $r instanceof Axismundi_Actor );
		$identities[] = $r->get_identity_id();
		$uris[] = $r->get_uri();
		wp_set_current_user( (int) $a->get_local_user_id() );
		$out = axismundi_act_follow_actor( $a, $r );
		$assert( 'remote.outbound-pending', is_array( $out ) && 'pending' === $out['state'] );
		$record( 'Accept', $r, $out['initiating_activity_uri'], 'inbound' );
		$in = $record( 'Follow', $r, $a->get_uri(), 'inbound' );
		$assert( 'remote.setup-accepted-pending', 'accepted' === $relation( $a, $r )['state'] && 'pending' === $relation( $r, $a )['state'] );
		$block = $record( 'Block', $r, $a->get_uri(), 'inbound' );
		$assert( 'remote.block-closes-both-flows', 'rejected' === $relation( $a, $r )['state'] && 'undone' === $relation( $r, $a )['state'] && $block->get_uri() === $relation( $a, $r )['state_activity_uri'] && $block->get_uri() === $relation( $r, $a )['state_activity_uri'] );
		$expected_out = $relation( $a, $r );
		$expected_in = $relation( $r, $a );
		$payload_before = axismundi_act_transaction_row( $block->get_uri() )['payload_json'];
		$wpdb->update( axismundi_act_relations_table(), array( 'state' => 'accepted', 'termination_activity_uri' => null ), array( 'id' => $expected_out['id'] ) );
		$wpdb->update( axismundi_act_relations_table(), array( 'state' => 'pending', 'termination_activity_uri' => null ), array( 'id' => $expected_in['id'] ) );
		$assert( 'upgrade.active-person-block-backfill', axismundi_act_backfill_active_person_blocks() && 'rejected' === $relation( $a, $r )['state'] && 'undone' === $relation( $r, $a )['state'] );
		foreach ( array( 'state', 'state_activity_uri', 'termination_activity_uri' ) as $field ) {
			$assert( 'upgrade.matches-live-transition.' . $field, $expected_out[ $field ] === $relation( $a, $r )[ $field ] && $expected_in[ $field ] === $relation( $r, $a )[ $field ] );
		}
		$upgraded = array( $relation( $a, $r ), $relation( $r, $a ) );
		$assert( 'upgrade.idempotent-payload-unchanged', axismundi_act_backfill_active_person_blocks() && $upgraded === array( $relation( $a, $r ), $relation( $r, $a ) ) && $payload_before === axismundi_act_transaction_row( $block->get_uri() )['payload_json'] );
		$wpdb->update( axismundi_act_relations_table(), array( 'termination_activity_uri' => null ), array( 'id' => $expected_in['id'] ) );
		$assert( 'upgrade.terminal-cycle-marked-without-state-change', axismundi_act_backfill_active_person_blocks() && 'undone' === $relation( $r, $a )['state'] && $block->get_uri() === $relation( $r, $a )['termination_activity_uri'] );
		$forged = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM %i WHERE actor_uri = %s AND activity_type IN ('Undo','Reject')", axismundi_act_activities_table(), $r->get_uri() ) );
		$assert( 'remote.no-forged-undo-reject', 0 === $forged );
		$record( 'Accept', $r, $out['initiating_activity_uri'], 'inbound' );
		$assert( 'remote.late-accept-blocked', 'rejected' === $relation( $a, $r )['state'] );
		$record( 'Undo', $r, $block->get_uri(), 'inbound' );
		$record( 'Accept', $r, $out['initiating_activity_uri'], 'inbound' );
		$assert( 'remote.unblock-does-not-revive', 'rejected' === $relation( $a, $r )['state'] && 'undone' === $relation( $r, $a )['state'] );
		$scoped = axismundi_act_record_activity( array( 'id' => $remote_uri . '/scoped-ban', 'type' => 'Block', 'actor' => $r->get_uri(), 'object' => $a->get_uri(), 'target' => 'https://example.com/community/not-person-block' ), 'inbound' );
		$assert( 'remote.scoped-ban-not-person-block', $scoped instanceof Axismundi_Activity && false === axismundi_act_actor_blocks( $r->get_uri(), $a->get_uri() ) );
		$b1 = $record( 'Block', $r, $a->get_uri(), 'inbound' );
		$b2 = $record( 'Block', $r, $a->get_uri(), 'inbound' );
		$record( 'Undo', $r, $b1->get_uri(), 'inbound' );
		$assert( 'remote.stale-block-undo-does-not-unblock-newer', true === axismundi_act_actor_blocks( $r->get_uri(), $a->get_uri() ) && $b2->get_uri() === axismundi_act_get_relation( 'block', $r->get_uri(), $a->get_uri() )['initiating_activity_uri'] );
		$record( 'Undo', $r, $b2->get_uri(), 'inbound' );
		$fresh = axismundi_act_follow_actor( $a, $r );
		$record( 'Accept', $r, $fresh['initiating_activity_uri'], 'inbound' );
		$future_block = $remote_uri . '/undo-before-block';
		$early_undo = $record( 'Undo', $r, $future_block, 'inbound' );
		$delayed = axismundi_act_record_activity( array( 'id' => $future_block, 'type' => 'Block', 'actor' => $r->get_uri(), 'object' => $a->get_uri() ), 'inbound' );
		$assert( 'remote.undo-before-block-still-ends-old-follow', $delayed instanceof Axismundi_Activity && 'rejected' === $relation( $a, $r )['state'] && false === axismundi_act_actor_blocks( $r->get_uri(), $a->get_uri() ) );
		$u1 = $record( 'Undo', $r, $delayed->get_uri(), 'inbound' );
		$u2 = $record( 'Undo', $r, $delayed->get_uri(), 'inbound' );
		$fresh = axismundi_act_follow_actor( $a, $r );
		$record( 'Accept', $r, $fresh['initiating_activity_uri'], 'inbound' );
		$record( 'Undo', $r, $u1->get_uri(), 'inbound' );
		$reconciled = axismundi_act_reconcile_follow( axismundi_act_transaction_row( $fresh['initiating_activity_uri'] ) );
		$assert( 'remote.partial-undo-branch-not-reactivation', false === axismundi_act_actor_blocks( $r->get_uri(), $a->get_uri() ) && 'accepted' === $reconciled['state'] );
		$record( 'Undo', $r, $early_undo->get_uri(), 'inbound' );
		$record( 'Undo', $r, $u2->get_uri(), 'inbound' );
		$assert( 'remote.actual-block-reactivation-ends-new-cycle', true === axismundi_act_actor_blocks( $r->get_uri(), $a->get_uri() ) && 'rejected' === $relation( $a, $r )['state'] );
		$record( 'Undo', $r, $delayed->get_uri(), 'inbound' );
		$record( 'Reject', $r, $fresh['initiating_activity_uri'], 'inbound' );
		$record( 'Accept', $r, $fresh['initiating_activity_uri'], 'inbound' );
		$assert( 'remote.reactivation-cause-survives-response-uri', 'rejected' === $relation( $a, $r )['state'] && $delayed->get_uri() === $relation( $a, $r )['termination_activity_uri'] );
		foreach ( array( 'Undo', 'Reject' ) as $terminal ) {
			$prior = axismundi_act_follow_actor( $a, $r );
			$record( 'Accept', $r, $prior['initiating_activity_uri'], 'inbound' );
			$end = 'Undo' === $terminal ? $record( 'Undo', $a, $prior['initiating_activity_uri'] ) : $record( 'Reject', $r, $prior['initiating_activity_uri'], 'inbound' );
			$barrier = $record( 'Block', $r, $a->get_uri(), 'inbound' );
			$assert( 'remote.terminal-cycle-gets-block-cause.' . $terminal, $barrier->get_uri() === $relation( $a, $r )['termination_activity_uri'] );
			$record( 'Undo', $r, $barrier->get_uri(), 'inbound' );
			$record( 'Undo', 'Undo' === $terminal ? $a : $r, $end->get_uri(), 'Undo' === $terminal ? 'outbound' : 'inbound' );
			$assert( 'remote.undo-terminal-cannot-revive-blocked-cycle.' . $terminal, 'rejected' === $relation( $a, $r )['state'] );
		}
		$own = axismundi_act_block_actor( $a, $r );
		$assert( 'remote.outbound-block-created', is_array( $own ) );
		$wire_block = axismundi_act_get( $own['initiating_activity_uri'] );
		axismundi_activitypub_bridge_queue_outbound( $wire_block );
		$job_id = axismundi_activitypub_bridge_find_delivery( $wire_block->get_uri() );
		$job = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM %i WHERE id=%d', axismundi_activitypub_bridge_delivery_table(), $job_id ), ARRAY_A );
		$assert( 'remote.block-queued-only-to-target', is_array( $job ) && array( $remote_uri . '/inbox' ) === json_decode( $job['inboxes_json'], true ) );
		$own_undo = axismundi_act_unblock_actor( $a, $r );
		$wire_undo = $wpdb->get_var( $wpdb->prepare( "SELECT activity_uri FROM %i WHERE actor_uri=%s AND object_uri=%s AND activity_type='Undo'", axismundi_act_activities_table(), $a->get_uri(), $wire_block->get_uri() ) );
		axismundi_activitypub_bridge_queue_outbound( axismundi_act_get( $wire_undo ) );
		$assert( 'remote.unblock-queued-to-target', is_array( $own_undo ) && axismundi_activitypub_bridge_find_delivery( $wire_undo ) > 0 );
		$wpdb->update( axismundi_act_relations_table(), array( 'state' => 'accepted', 'termination_activity_uri' => null ), array( 'id' => $relation( $a, $r )['id'] ) );
		$assert( 'upgrade.inactive-blocks-not-retroactive', axismundi_act_backfill_active_person_blocks() && 'accepted' === $relation( $a, $r )['state'] && null === $relation( $a, $r )['termination_activity_uri'] );
		$wpdb->update( axismundi_act_relations_table(), array( 'state' => 'active', 'initiating_activity_uri' => $scoped->get_uri() ), array( 'id' => axismundi_act_get_relation( 'block', $r->get_uri(), $a->get_uri() )['id'] ) );
		$assert( 'upgrade.scoped-bans-excluded', axismundi_act_backfill_active_person_blocks() && 'accepted' === $relation( $a, $r )['state'] && null === $relation( $a, $r )['termination_activity_uri'] );
		$assert( 'transport.no-http-attempts', 0 === $http_calls );
	} finally {
		// Remove only fixture-owned rows, including hook-generated cleanup Activities.
		try {
			foreach ( array_unique( $uris ) as $uri ) {
				$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT id, activity_uri FROM %i WHERE actor_uri = %s', axismundi_act_activities_table(), $uri ), ARRAY_A );
				foreach ( $rows as $row ) {
					$wpdb->delete( axismundi_act_inbox_table(), array( 'activity_id' => $row['id'] ) );
					if ( function_exists( 'axismundi_activitypub_bridge_find_delivery' ) ) {
						$delivery = axismundi_activitypub_bridge_find_delivery( $row['activity_uri'] );
						if ( $delivery ) {
							for ( $attempt = 0; $attempt <= 20; ++$attempt ) { wp_clear_scheduled_hook( AXISMUNDI_ACTIVITYPUB_BRIDGE_DELIVERY_HOOK, array( $delivery, $attempt ) ); }
							$wpdb->delete( axismundi_activitypub_bridge_delivery_table(), array( 'id' => $delivery ) );
						}
					}
				}
				$wpdb->delete( axismundi_act_relations_table(), array( 'subject_actor_uri' => $uri ) );
				$wpdb->delete( axismundi_act_relations_table(), array( 'object_actor_uri' => $uri ) );
				$wpdb->delete( axismundi_act_activities_table(), array( 'actor_uri' => $uri ) );
				$wpdb->delete( axismundi_actors_identity_relations_table(), array( 'target_uri' => $uri ) );
			}
			foreach ( array_unique( $identities ) as $id ) {
				$wpdb->delete( axismundi_act_inbox_table(), array( 'actor_identity_id' => $id ) );
				foreach ( array( 'endpoints', 'keys', 'signing_keys', 'fetch_state', 'asset_cache', 'addresses', 'texts', 'profile_fields', 'alternate_names', 'managers' ) as $kind ) {
					$table = call_user_func( 'axismundi_actors_' . $kind . '_table' );
					$wpdb->delete( $table, array( 'identity_id' => $id ) );
				}
				$wpdb->delete( axismundi_actors_identity_relations_table(), array( 'identity_id' => $id ) );
				$wpdb->delete( axismundi_actors_meta_table(), array( 'actor_id' => $id ) );
					$wpdb->delete( axismundi_actors_actors_table(), array( 'identity_id' => $id ) );
				$wpdb->delete( axismundi_actors_identities_table(), array( 'id' => $id ) );
			}
			foreach ( $users as $id ) { wp_delete_user( $id ); }
		} finally {
			remove_filter( 'pre_http_request', $http, PHP_INT_MAX );
			if ( false !== $priority ) { add_action( 'axismundi_act_activity_recorded', $queue, $priority ); }
			wp_set_current_user( $old_user );
			$_GET = $old_get;
			$_COOKIE = $old_cookies;
		}
	}
	$assert( 'fixture.existing-rows-preserved', $baseline === $snapshot() );
	printf( "\nPASS: %d checks\n", $checks );
	return $checks;
}

if ( ! defined( 'AX_PERSON_BLOCK_AUDIT_NO_AUTORUN' ) || ! AX_PERSON_BLOCK_AUDIT_NO_AUTORUN ) {
	axismundi_act_audit_person_block();
}
