<?php
/**
 * Forum membership projection for inbound Follows to local managed Groups.
 *
 * Activities remains authoritative for Follow, Accept, Reject, and Undo. Forum
 * projects those transitions only when the followed local Group is bound to one
 * Forum, then uses the projection for Forum-specific admission decisions.
 *
 * @package AxismundiForum
 */

defined( 'ABSPATH' ) || exit;

/** @return array<string,string> Stable membership-policy labels. */
function axismundi_forum_membership_policies() : array {
	return array(
		'open'     => __( 'Accept new followers automatically', 'axismundi-forum' ),
		'approval' => __( 'Require Group manager approval', 'axismundi-forum' ),
	);
}

/**
 * Join the creator's public Person to a newly published local community.
 *
 * This is deliberately tied to publishing the Group, not to a manager assignment. The latter
 * is local login delegation; this is a Person-to-Group Follow that Forum will project under its
 * membership policy. An inactive profile leaves no fake edge.
 */
function axismundi_forum_auto_join_published_community( Axismundi_Actor $group, int $user_id ) : void {
	$person = function_exists( 'axismundi_actors_get_for_user' ) ? axismundi_actors_get_for_user( $user_id ) : null;
	if ( ! $person instanceof Axismundi_Actor || ! $person->is_local() || 'Person' !== $person->get_type()
		|| 'public' !== $person->get_status() || ! $person->is_handle_locked()
		|| ! function_exists( 'axismundi_act_follow_actor' ) ) {
		return;
	}
	axismundi_act_follow_actor( $person, $group );
}
add_action( 'axismundi_forum_public_community_initialized', 'axismundi_forum_auto_join_published_community', 10, 2 );

/**
 * Replay pre-existing local and inbound Follow relations when Forum first observes a public
 * Group as a community.
 *
 * A managed Group can exist before Forum is installed. Those earlier Follows were correctly
 * left pending because no product owned admission yet; once Forum observes the Group they must pass
 * through the same live admission path as a newly received Follow, including an outward Accept
 * where applicable. Rebuilding the projection alone is insufficient because it cannot send the
 * Accept which changes the ledger from pending to accepted.
 */
function axismundi_forum_admit_existing_follows_after_community_initialization( Axismundi_Actor $group, int $user_id ) : void {
	unset( $user_id );
	if ( ! function_exists( 'axismundi_act_get_relations_for_object' ) ) {
		return;
	}
	$after_id = 0;
	while ( true ) {
		$relations = (array) axismundi_act_get_relations_for_object( $group->get_uri(), array( 'follow' ), 200, $after_id );
		foreach ( $relations as $relation ) {
			$after_id = max( $after_id, (int) ( $relation['id'] ?? 0 ) );
			axismundi_forum_sync_membership_from_follow( $relation );
		}
		if ( count( $relations ) < 200 ) {
			break;
		}
	}
}
add_action( 'axismundi_forum_public_community_initialized', 'axismundi_forum_admit_existing_follows_after_community_initialization', 20, 2 );

/** Read one Forum's membership policy; public Forums default to automatic join. */
function axismundi_forum_get_membership_policy( int $group_identity_id ) : string {
	$community = axismundi_forum_get_community( $group_identity_id );
	$policy = is_array( $community ) ? (string) $community['membership_policy'] : '';
	return array_key_exists( $policy, axismundi_forum_membership_policies() ) ? $policy : 'open';
}

/**
 * Persist one Forum membership policy through the bound Group manager authority.
 *
 * Tightening to `approval` is not retroactive: members already admitted stay admitted, because
 * the policy governs who may be admitted next, not a re-audit of decisions already taken and
 * already federated. Loosening to `open` is retroactive, and deliberately so — see
 * axismundi_forum_admit_pending_memberships().
 */
function axismundi_forum_set_membership_policy( int $group_identity_id, int $user_id, string $policy ) {
	if ( ! array_key_exists( $policy, axismundi_forum_membership_policies() ) ) {
		return new WP_Error( 'ax_forum_membership_policy', __( 'The Forum membership policy is invalid.', 'axismundi-forum' ) );
	}
	if ( $policy === axismundi_forum_get_membership_policy( $group_identity_id ) ) {
		return true;
	}
	$result = axismundi_forum_update_community_policy( $group_identity_id, $user_id, 'membership_policy', $policy );
	if ( is_wp_error( $result ) ) {
		return $result;
	}
	if ( 'open' === $policy ) {
		axismundi_forum_admit_pending_memberships( $group_identity_id );
	}
	return true;
}

/**
 * Admit everyone already waiting when a Forum opens.
 *
 * Opening a Forum is a decision about who may join, not about who asked before the switch was
 * flipped. Leaving the existing queue pending would make the same request mean two different
 * things depending on its timestamp, and nobody would ever clear the queue: an open Forum has
 * no approval screen to clear it from.
 *
 * This is a manager's act, so unlike axismundi_forum_rebuild_memberships() it does federate.
 * A remote follower whose request is being granted has to be told, and the Accept is the only
 * thing that tells them; re-projecting silently would admit them here and leave them pending
 * on their own server forever.
 *
 * The queue is drained in pages rather than in one bounded read. A single `LIMIT 200` looked
 * harmless until you ask what the 201st person experiences: an open Forum that never admits
 * them and no screen anywhere that shows they are waiting. Each admission removes its own row
 * from the pending set, so the same query walks the queue forward; a page that admits nobody
 * is a page of rows that cannot be admitted at all, and that ends the loop instead of spinning
 * on it. Whatever remains is reported, never silently dropped.
 *
 * @param int $group_identity_id Forum that just became open.
 * @return array{admitted:int,remaining:int} Members admitted, and requests still waiting.
 */
function axismundi_forum_admit_pending_memberships( int $group_identity_id ) : array {
	$group    = axismundi_forum_get_community_group( $group_identity_id );
	$admitted = 0;
	while ( true ) {
		$batch = axismundi_forum_pending_memberships( $group_identity_id, 100 );
		if ( array() === $batch ) {
			break;
		}
		$progress = 0;
		foreach ( $batch as $membership ) {
			$evidence = (string) ( $membership['membership_evidence_activity_uri'] ?? '' );
			$member   = function_exists( 'axismundi_actors_get_by_identity' ) ? axismundi_actors_get_by_identity( (int) $membership['actor_identity_id'] ) : null;
			if ( '' === $evidence || ! $member instanceof Axismundi_Actor || ! $group instanceof Axismundi_Actor
				|| ! function_exists( 'axismundi_act_respond_to_local_follow' ) ) {
				continue;
			}
			// The Accept comes first for the same reason it does in the live hook: a member is
			// admitted only once the ledger says so, so a failed Accept leaves them queued
			// rather than admitted-here-and-pending-there.
			if ( is_wp_error( axismundi_act_respond_to_local_follow( $group, $evidence, 'accept' ) )
				|| true !== axismundi_forum_write_membership( $group_identity_id, (int) $membership['actor_identity_id'], 'accepted', $evidence ) ) {
				continue;
			}
			++$progress;
		}
		$admitted += $progress;
		if ( 0 === $progress ) {
			break;
		}
	}
	$remaining = count( axismundi_forum_pending_memberships( $group_identity_id, 200 ) );
	/**
	 * Fires after an open-policy admission sweep, including one that could not finish.
	 *
	 * @param int $group_identity_id Forum swept.
	 * @param int $admitted      Members admitted.
	 * @param int $remaining     Requests still pending, capped at the reporting page size.
	 */
	do_action( 'axismundi_forum_memberships_admitted', $group_identity_id, $admitted, $remaining );
	return array( 'admitted' => $admitted, 'remaining' => $remaining );
}

/** @return array<string,mixed>|null One Forum membership projection row. */
function axismundi_forum_get_membership( int $group_identity_id, int $actor_identity_id ) : ?array {
	if ( $group_identity_id <= 0 || $actor_identity_id <= 0 ) {
		return null;
	}
	global $wpdb;
	$table = axismundi_forum_memberships_table();
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- composite-primary-key lookup on a Forum-owned custom table.
	$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE group_identity_id = %d AND actor_identity_id = %d", $group_identity_id, $actor_identity_id ), ARRAY_A );
	return is_array( $row ) ? $row : null;
}

/**
 * Local communities this user's public Person has joined.
 *
 * This is a Forum projection query, not a managed-group lookup: being delegated to operate
 * a Group and having accepted membership in someone else's community are separate facts.
 * Managed communities are omitted so a Topic picker can show each destination once.
 *
 * @return array<int,Axismundi_Actor> Community Groups keyed by identity id.
 */
function axismundi_forum_joined_local_communities_for_user( int $user_id ) : array {
	$member = function_exists( 'axismundi_actors_get_for_user' ) ? axismundi_actors_get_for_user( $user_id ) : null;
	if ( ! $member instanceof Axismundi_Actor || ! $member->is_local() || 'Person' !== $member->get_type() ) {
		return array();
	}
	global $wpdb;
	$table = axismundi_forum_memberships_table();
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- accepted membership projection lookup using its actor_state index.
	$group_ids = (array) $wpdb->get_col( $wpdb->prepare( "SELECT group_identity_id FROM {$table} WHERE actor_identity_id = %d AND membership_state = 'accepted' ORDER BY group_identity_id ASC", $member->get_identity_id() ) );
	$managed = axismundi_forum_manageable_communities( $user_id );
	$groups  = array();
	foreach ( $group_ids as $group_id ) {
		$group_id = (int) $group_id;
		if ( $group_id <= 0 || isset( $managed[ $group_id ] ) || ! axismundi_forum_is_community( $group_id ) ) {
			continue;
		}
		$group = function_exists( 'axismundi_actors_get_by_identity' ) ? axismundi_actors_get_by_identity( $group_id ) : null;
		if ( $group instanceof Axismundi_Actor && $group->is_local() && $group->is_managed() && 'Group' === $group->get_type() ) {
			$groups[ $group_id ] = $group;
		}
	}
	return $groups;
}

/** @return array<int,array<string,mixed>> Pending membership requests for one Forum. */
function axismundi_forum_pending_memberships( int $group_identity_id, int $limit = 100 ) : array {
	if ( $group_identity_id <= 0 ) {
		return array();
	}
	global $wpdb;
	$table = axismundi_forum_memberships_table();
	$limit = max( 1, min( 200, $limit ) );
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- indexed Forum membership-request list.
	return (array) $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} WHERE group_identity_id = %d AND membership_state = 'pending' ORDER BY created_at ASC LIMIT %d", $group_identity_id, $limit ), ARRAY_A );
}

/**
 * One paged membership-projection view for a Group manager.
 *
 * Membership is Forum policy projected from the Follow ledger, so this is intentionally
 * separate from Actors' generic follower list: it answers who is admitted to this Community.
 *
 * @return array{items:array<int,array<string,mixed>>,total:int,has_more:bool}
 */
function axismundi_forum_get_membership_page( int $group_identity_id, string $state = 'accepted', int $page = 1, int $per_page = 50 ) : array {
	if ( $group_identity_id <= 0 || ! in_array( $state, array( 'pending', 'accepted', 'rejected', 'undone' ), true ) ) {
		return array( 'items' => array(), 'total' => 0, 'has_more' => false );
	}
	$page     = max( 1, $page );
	$per_page = max( 1, min( 100, $per_page ) );
	$offset   = ( $page - 1 ) * $per_page;
	global $wpdb;
	$table = axismundi_forum_memberships_table();
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Group/state is indexed on Forum's membership projection.
	$total = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE group_identity_id = %d AND membership_state = %s", $group_identity_id, $state ) );
	// Fetch one extra row so the UI can paginate without lying about a full page.
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- manager-only paged projection view.
	$rows = (array) $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} WHERE group_identity_id = %d AND membership_state = %s ORDER BY created_at ASC, actor_identity_id ASC LIMIT %d OFFSET %d", $group_identity_id, $state, $per_page + 1, $offset ), ARRAY_A );
	return array(
		'items'    => array_slice( $rows, 0, $per_page ),
		'total'    => $total,
		'has_more' => count( $rows ) > $per_page,
	);
}

/** @return int Count Forum membership projections for lifecycle guards. */
function axismundi_forum_count_memberships( int $group_identity_id ) : int {
	global $wpdb;
	$table = axismundi_forum_memberships_table();
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- indexed Forum membership count.
	return (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE group_identity_id = %d", $group_identity_id ) );
}

/** Delete contextual membership projections when a Forum itself is permanently deleted. */
function axismundi_forum_delete_memberships_for_forum( int $group_identity_id ) : void {
	global $wpdb;
	$wpdb->delete( axismundi_forum_memberships_table(), array( 'group_identity_id' => $group_identity_id ), array( '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Forum deletion removes only its local admission projection.
}

/** Upsert one state transition projected from the Activities Follow ledger. */
function axismundi_forum_write_membership( int $group_identity_id, int $actor_identity_id, string $state, string $membership_evidence_activity_uri ) {
	if ( ! in_array( $state, array( 'pending', 'accepted', 'rejected', 'undone' ), true ) ) {
		return new WP_Error( 'ax_forum_membership_state', __( 'The Forum membership state is invalid.', 'axismundi-forum' ) );
	}
	$existing = axismundi_forum_get_membership( $group_identity_id, $actor_identity_id );
	$now      = current_time( 'mysql', true );
	global $wpdb;
	$table = axismundi_forum_memberships_table();
	if ( is_array( $existing ) ) {
		$updated = $wpdb->update(
			$table,
			array(
				'membership_evidence_activity_uri' => $membership_evidence_activity_uri,
				'membership_state'                 => $state,
				'updated_at'                       => $now,
			),
			array( 'group_identity_id' => $group_identity_id, 'actor_identity_id' => $actor_identity_id ),
			array( '%s', '%s', '%s' ),
			array( '%d', '%d' )
		); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- exact update of a Forum-owned projection.
		return false === $updated ? new WP_Error( 'ax_forum_membership_write', __( 'The Forum membership could not be updated.', 'axismundi-forum' ) ) : true;
	}
	$inserted = $wpdb->insert(
		$table,
		array(
			'group_identity_id'                    => $group_identity_id,
			'actor_identity_id'                => $actor_identity_id,
			'membership_evidence_activity_uri' => $membership_evidence_activity_uri,
			'membership_state'                 => $state,
			'created_at'                       => $now,
			'updated_at'                       => $now,
		),
		array( '%d', '%d', '%s', '%s', '%s', '%s' )
	); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- insert of a Forum-owned projection.
	return false === $inserted ? new WP_Error( 'ax_forum_membership_write', __( 'The Forum membership could not be created.', 'axismundi-forum' ) ) : true;
}

/**
 * The membership state one relation projects to under one policy.
 *
 * The single place this rule exists, because it has two callers that must never
 * disagree: the live transition hook and the rebuild. If the hook applied the policy
 * and the rebuild replayed raw relation states, a Forum rebuilt from the ledger would
 * silently differ from the same Forum that was never rebuilt.
 *
 * `open` resolves a pending request to `accepted` rather than waiting for the Accept to
 * round-trip. That is not optimism: on an open Forum the site has already decided, so the
 * projection states the decision and the Accept carries it to the peer. It also keeps the
 * rule a pure function of (relation state, policy), which is what makes a rebuild possible.
 *
 * @param string $relation_state Activities relation state.
 * @param string $policy         Forum membership policy.
 * @return string Projected membership state, or '' when the relation projects to nothing.
 */
function axismundi_forum_project_membership_state( string $relation_state, string $policy ) : string {
	if ( ! in_array( $relation_state, array( 'pending', 'accepted', 'rejected', 'undone' ), true ) ) {
		return '';
	}
	return 'pending' === $relation_state && 'open' === $policy ? 'accepted' : $relation_state;
}

/**
 * Whether one relation is membership evidence for a Forum.
 *
 * `Follow` and `Join` both mean "I want in". Lemmy has no Join at all — an accepted
 * `Follow(Group)` *is* the membership — while Mobilizon sends `Join`. Neither one on its own
 * means admitted: under `approval` both are requests awaiting a manager.
 *
 * Only `follow` actually arrives today. Activities recognises `Follow` and `Block` and nothing
 * else, so a `join` relation cannot exist in the ledger yet and this list does not make one
 * possible — supporting Join means giving it an Activity, a relation type, and Accept/Reject
 * semantics in Activities, as one piece of work. What this does is keep the projection rule
 * from being the thing that has to change on that day.
 *
 * Adding a type here is not enough to make it admit anyone either: the projected state must be
 * one a Follow can be in, and there must be an evidence Activity. A Block relation, whose
 * states are `active` and `undone`, projects to nothing on the first count.
 *
 * @param string $relation_type Activities relation type.
 * @return bool
 */
function axismundi_forum_is_membership_evidence( string $relation_type ) : bool {
	/** Filter which relation types count as Forum membership evidence. @param string[] $types Defaults. */
	return in_array( $relation_type, (array) apply_filters( 'axismundi_forum_membership_evidence_types', array( 'follow', 'join' ) ), true );
}

/**
 * Project one membership relation transition onto its bound Forum.
 *
 * Local and remote members are projected alike. Excluding local Actors — which this did,
 * twice over, by requiring `inbound` and refusing `is_local()` members — meant a site's own
 * users could never be members of its own Forum, so an `approval` Forum could admit remote
 * people and nobody else. Manager authority is not a substitute: that is the operator
 * relation, not membership.
 *
 * The gates that matter are kept exactly as they were: the followed Actor must be a local
 * managed Group, it must be bound to a Forum, and there must be an evidence Activity.
 */
function axismundi_forum_sync_membership_from_follow( array $relation ) : void {
	if ( ! axismundi_forum_is_membership_evidence( (string) ( $relation['relation_type'] ?? '' ) )
		|| ! in_array( (string) ( $relation['direction'] ?? '' ), array( 'inbound', 'local' ), true ) ) {
		return;
	}
	$group = function_exists( 'axismundi_actors_get_by_uri' ) ? axismundi_actors_get_by_uri( (string) ( $relation['object_actor_uri'] ?? '' ) ) : null;
	$member = function_exists( 'axismundi_actors_get_by_uri' ) ? axismundi_actors_get_by_uri( (string) ( $relation['subject_actor_uri'] ?? '' ) ) : null;
	if ( ! $group instanceof Axismundi_Actor || ! $member instanceof Axismundi_Actor || ! $group->is_local() || ! $group->is_managed() || 'Group' !== $group->get_type() ) {
		return;
	}
	$group_identity_id = $group->get_identity_id();
	if ( ! axismundi_forum_is_community( $group_identity_id ) ) {
		return;
	}
	$evidence_uri   = (string) ( $relation['initiating_activity_uri'] ?? '' );
	$policy         = axismundi_forum_get_membership_policy( $group_identity_id );
	$relation_state = (string) ( $relation['state'] ?? '' );
	$state          = axismundi_forum_project_membership_state( $relation_state, $policy );
	if ( '' === $state || '' === $evidence_uri ) {
		return;
	}

	/*
	 * The Accept happens before the projection, not after it. Activities does not auto-accept a
	 * Follow aimed at a Group — admission is this Forum's decision — so the Accept is what moves
	 * the ledger, and writing `accepted` first meant a failed Accept left the Forum saying
	 * admitted while the Follow it was derived from still said pending. Ordering it this way
	 * makes the ledger the thing that can fail, and the projection follow it: if the Accept does
	 * not go through, the member is recorded as still waiting, which is exactly what they are,
	 * and the next relation change or rebuild admits them.
	 *
	 * Delivery is not decided here. axismundi_act_respond_to_local_follow() reads the relation's
	 * own direction and only sends the Accept outward when the follower is remote.
	 */
	if ( 'accepted' === $state && 'pending' === $relation_state && function_exists( 'axismundi_act_respond_to_local_follow' )
		&& is_wp_error( axismundi_act_respond_to_local_follow( $group, $evidence_uri, 'accept' ) ) ) {
		$state = 'pending';
	}
	axismundi_forum_write_membership( $group_identity_id, $member->get_identity_id(), $state, $evidence_uri );
}

/**
 * Rebuild one Forum's membership projection from the Activities ledger.
 *
 * A projection that cannot be rebuilt is not a projection; it is a second copy that drifts.
 * Forum membership is derived from relations Activities owns, so deleting and rebinding a
 * Forum, changing its policy, or upgrading this schema must all be recoverable — and none
 * of them are, if the only way a row is ever written is a live transition that has already
 * happened.
 *
 * Read and project only. This never sends an Accept, a Reject, or anything else outward:
 * rebuilding a local view must not re-deliver Activities that peers already received, and a
 * rebuild that federated would turn an administrative repair into a broadcast.
 *
 * It replaces rather than upserts. Writing only the rows the ledger still yields would leave
 * behind every row it no longer yields — a member whose Actor cache was evicted, a row written
 * under an evidence rule that has since changed — so the projection would accumulate exactly
 * the entries a rebuild is supposed to clear. The whole Forum's projection is deleted and
 * rewritten inside one transaction, so a reader never sees a half-built membership list and a
 * failure part-way through leaves the previous one intact.
 *
 * @param int $group_identity_id Forum to rebuild.
 * @return array{members:int,relations:int}|WP_Error Rows written and relations read.
 */
function axismundi_forum_rebuild_memberships( int $group_identity_id ) {
	$group = axismundi_forum_get_community_group( $group_identity_id );
	if ( ! $group instanceof Axismundi_Actor || ! function_exists( 'axismundi_act_get_relations_for_object' ) ) {
		return new WP_Error( 'ax_forum_rebuild_unbound', __( 'That Forum is not bound to a managed Group.', 'axismundi-forum' ) );
	}
	global $wpdb;
	$policy    = axismundi_forum_get_membership_policy( $group_identity_id );
	$evidence  = (array) apply_filters( 'axismundi_forum_membership_evidence_types', array( 'follow', 'join' ) );
	$page      = 200;
	$after_id  = 0;
	$members   = 0;
	$relations = 0;
	$wpdb->query( 'START TRANSACTION' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- the delete and the rewrite are one replacement.
	$wpdb->delete( axismundi_forum_memberships_table(), array( 'group_identity_id' => $group_identity_id ), array( '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- replaced in full below.
	while ( true ) {
		// The evidence types are pushed into the query rather than filtered out afterwards: a
		// Block aimed at this Group is a relation too, and letting those rows occupy the page
		// would make the page size mean something other than "members read so far".
		$rows = (array) axismundi_act_get_relations_for_object( $group->get_uri(), $evidence, $page, $after_id );
		foreach ( $rows as $relation ) {
			$after_id = max( $after_id, (int) $relation['id'] );
			++$relations;
			$member = function_exists( 'axismundi_actors_get_by_uri' ) ? axismundi_actors_get_by_uri( (string) ( $relation['subject_actor_uri'] ?? '' ) ) : null;
			$state  = axismundi_forum_project_membership_state( (string) ( $relation['state'] ?? '' ), $policy );
			$uri    = (string) ( $relation['initiating_activity_uri'] ?? '' );
			// An evidence Activity is required, which excludes the legacy follower snapshots an
			// official-ActivityPub import writes with no initiating Activity. That is the right
			// answer for a projection built on evidence and the wrong answer for a Group that
			// arrives with followers already attached; importing those is its own decision.
			if ( ! $member instanceof Axismundi_Actor || '' === $state || '' === $uri ) {
				continue;
			}
			if ( true !== axismundi_forum_write_membership( $group_identity_id, $member->get_identity_id(), $state, $uri ) ) {
				$wpdb->query( 'ROLLBACK' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- a partial rebuild is worse than none.
				return new WP_Error( 'ax_forum_rebuild_write', __( 'The Forum membership projection could not be rebuilt.', 'axismundi-forum' ) );
			}
			++$members;
		}
		if ( count( $rows ) < $page ) {
			break;
		}
	}
	$wpdb->query( 'COMMIT' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- replacement complete.
	return array( 'members' => $members, 'relations' => $relations );
}
add_action( 'axismundi_act_relation_changed', 'axismundi_forum_sync_membership_from_follow', 20 );

/** Accept or reject a pending Forum membership request as a manager of its Group. */
function axismundi_forum_respond_to_membership_request( int $group_identity_id, int $actor_identity_id, int $user_id, string $decision ) {
	if ( ! in_array( $decision, array( 'accept', 'reject' ), true ) ) {
		return new WP_Error( 'ax_forum_membership_decision', __( 'The membership decision is invalid.', 'axismundi-forum' ) );
	}
	if ( ! function_exists( 'axismundi_forum_user_can_manage' ) || ! axismundi_forum_user_can_manage( $group_identity_id, $user_id ) ) {
		return new WP_Error( 'ax_forum_forbidden', __( 'You do not manage this Forum Group.', 'axismundi-forum' ) );
	}
	$membership = axismundi_forum_get_membership( $group_identity_id, $actor_identity_id );
	$group      = axismundi_forum_get_community_group( $group_identity_id );
	if ( ! is_array( $membership ) || 'pending' !== (string) $membership['membership_state'] || ! $group instanceof Axismundi_Actor || ! function_exists( 'axismundi_act_respond_to_local_follow' ) ) {
		return new WP_Error( 'ax_forum_membership_request', __( 'That membership request is no longer pending.', 'axismundi-forum' ) );
	}
	return axismundi_act_respond_to_local_follow( $group, (string) $membership['membership_evidence_activity_uri'], $decision );
}
