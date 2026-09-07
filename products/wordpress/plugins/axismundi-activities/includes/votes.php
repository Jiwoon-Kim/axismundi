<?php
/**
 * Like and Dislike as one ledger shape.
 *
 * These two are the same Activity with a different verb: one Actor, one object, undone by
 * referring to the Activity rather than the object. Writing them twice would mean every later
 * fix — the Undo-convergence branch, the cycle key, the `reaction_key IS NULL` guard that keeps
 * emoji reactions out of Like counts — had to be found and applied twice, and this codebase has
 * already produced several defects of exactly that kind.
 *
 * So the queries are written once against a verb, and `Like` keeps its existing function names
 * as delegates. Nothing that already calls the Like API changes.
 *
 * What is deliberately *not* here: any rule that a Dislike cancels a Like. ActivityStreams does
 * not say they are opposites, and "one vote per person" is a community policy, not a property of
 * the ledger. A product that wants Lemmy-style exclusive voting undoes the other verb itself.
 *
 * @package AxismundiActivities
 */

defined( 'ABSPATH' ) || exit;

/**
 * The vote verbs this ledger records as a plain Actor-to-object judgement.
 *
 * @return string[]
 */
function axismundi_act_vote_types() : array {
	return array( 'Like', 'Dislike' );
}

/** Whether a verb is one of the plain vote types. */
function axismundi_act_is_vote_type( string $type ) : bool {
	return in_array( $type, axismundi_act_vote_types(), true );
}

/**
 * Effective votes of one verb for one exact object, newest first and unique by Actor.
 *
 * @param string $type       Vote verb.
 * @param string $object_uri Canonical object URI.
 * @param int    $limit      Bounded row count.
 * @return Axismundi_Activity[]
 */
function axismundi_act_get_effective_votes( string $type, string $object_uri, int $limit = 100 ) : array {
	global $wpdb;
	$uri = axismundi_act_uri( $object_uri );
	if ( ! axismundi_act_is_vote_type( $type ) || '' === $uri || AXISMUNDI_ACT_DB_VERSION !== (string) get_option( AXISMUNDI_ACT_DB_VERSION_OPTION, '' ) ) {
		return array();
	}
	$table = axismundi_act_activities_table();
	$limit = max( 1, min( 200, $limit ) );
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- exact URI vote query; the verb is allowlisted above and prepared below.
	$rows = (array) $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} WHERE activity_type = %s AND reaction_key IS NULL AND object_uri_hash = %s AND object_uri = %s AND effective_status = 'active' ORDER BY COALESCE(published_at, received_at, created_at) DESC, id DESC LIMIT %d", $type, hash( 'sha256', $uri ), $uri, $limit ), ARRAY_A );
	$seen = array();
	$out  = array();
	foreach ( $rows as $row ) {
		$actor_hash = (string) $row['actor_uri_hash'];
		if ( isset( $seen[ $actor_hash ] ) ) {
			continue;
		}
		$seen[ $actor_hash ] = true;
		$out[] = axismundi_act_hydrate( $row );
	}
	return $out;
}

/**
 * Latest effective vote Activity for each Actor across one or more vote verbs.
 *
 * The ledger does not make the verbs mutually exclusive. Consumers that do — such as a Forum
 * score — need one deterministic Activity per Actor without changing the underlying facts. This
 * intentionally has no display-page limit: a tally must not silently stop changing after an
 * arbitrary number of voters.
 *
 * @param string[] $types      Allowed vote verbs.
 * @param string   $object_uri Canonical object URI.
 * @return array<string,Axismundi_Activity> Actor URI keyed current vote Activities.
 */
function axismundi_act_get_latest_effective_votes( array $types, string $object_uri ) : array {
	global $wpdb;
	$types = array_values( array_unique( array_filter( $types, 'axismundi_act_is_vote_type' ) ) );
	$uri   = axismundi_act_uri( $object_uri );
	if ( empty( $types ) || '' === $uri || AXISMUNDI_ACT_DB_VERSION !== (string) get_option( AXISMUNDI_ACT_DB_VERSION_OPTION, '' ) ) {
		return array();
	}
	$table        = axismundi_act_activities_table();
	$placeholders = implode( ', ', array_fill( 0, count( $types ), '%s' ) );
	$args         = array_merge( $types, array( hash( 'sha256', $uri ), $uri ) );
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- vote verbs are allowlisted and all values are prepared; a complete ledger read is required for an exact tally.
	$rows = (array) $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} WHERE activity_type IN ({$placeholders}) AND reaction_key IS NULL AND object_uri_hash = %s AND object_uri = %s AND effective_status = 'active'", ...$args ), ARRAY_A );
	$out  = array();
	foreach ( $rows as $row ) {
		$activity = axismundi_act_hydrate( $row );
		$actor_uri = $activity->get_actor_uri();
		$existing  = $out[ $actor_uri ] ?? null;
		if ( ! $existing instanceof Axismundi_Activity || axismundi_act_vote_activity_is_newer( $activity, $existing ) ) {
			$out[ $actor_uri ] = $activity;
		}
	}
	return $out;
}

/** Whether the first vote Activity wins a latest-state tie over the second. */
function axismundi_act_vote_activity_is_newer( Axismundi_Activity $left, Axismundi_Activity $right ) : bool {
	$left_time  = (int) strtotime( (string) ( $left->get_published_at() ?? '' ) );
	$right_time = (int) strtotime( (string) ( $right->get_published_at() ?? '' ) );
	if ( $left_time !== $right_time ) {
		return $left_time > $right_time;
	}
	return strcmp( $left->get_uri(), $right->get_uri() ) > 0;
}

/** Count distinct Actors with an effective vote of one verb for one object URI. */
function axismundi_act_get_vote_count( string $type, string $object_uri ) : int {
	global $wpdb;
	$uri = axismundi_act_uri( $object_uri );
	if ( ! axismundi_act_is_vote_type( $type ) || '' === $uri || AXISMUNDI_ACT_DB_VERSION !== (string) get_option( AXISMUNDI_ACT_DB_VERSION_OPTION, '' ) ) {
		return 0;
	}
	$table = axismundi_act_activities_table();
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- indexed aggregate derived from the authoritative ledger.
	return (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(DISTINCT actor_uri_hash) FROM {$table} WHERE activity_type = %s AND reaction_key IS NULL AND object_uri_hash = %s AND object_uri = %s AND effective_status = 'active'", $type, hash( 'sha256', $uri ), $uri ) );
}

/** Latest vote of one verb by one Actor for one object, optionally requiring effective state. */
function axismundi_act_get_actor_vote( string $type, string $actor_uri, string $object_uri, bool $effective_only = true ) : ?Axismundi_Activity {
	global $wpdb;
	$actor  = axismundi_act_uri( $actor_uri );
	$object = axismundi_act_uri( $object_uri );
	if ( ! axismundi_act_is_vote_type( $type ) || '' === $actor || '' === $object || AXISMUNDI_ACT_DB_VERSION !== (string) get_option( AXISMUNDI_ACT_DB_VERSION_OPTION, '' ) ) {
		return null;
	}
	$table  = axismundi_act_activities_table();
	$active = $effective_only ? "AND effective_status = 'active'" : '';
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- allowlisted fixed status clause and exact URI lookup.
	$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE activity_type = %s AND reaction_key IS NULL AND actor_uri_hash = %s AND actor_uri = %s AND object_uri_hash = %s AND object_uri = %s {$active} ORDER BY id DESC LIMIT 1", $type, hash( 'sha256', $actor ), $actor, hash( 'sha256', $object ), $object ), ARRAY_A );
	return is_array( $row ) ? axismundi_act_hydrate( $row ) : null;
}

/** Whether one Actor currently holds a vote of one verb on an object. */
function axismundi_act_get_vote_state( string $type, string $actor_uri, string $object_uri ) : bool {
	return axismundi_act_get_actor_vote( $type, $actor_uri, $object_uri, true ) instanceof Axismundi_Activity;
}

/** @return string[] Distinct object URIs one Actor currently holds a vote of one verb on. */
function axismundi_act_get_voted_object_uris( string $type, string $actor_uri, int $limit = 100 ) : array {
	global $wpdb;
	$actor = axismundi_act_uri( $actor_uri );
	if ( ! axismundi_act_is_vote_type( $type ) || '' === $actor || AXISMUNDI_ACT_DB_VERSION !== (string) get_option( AXISMUNDI_ACT_DB_VERSION_OPTION, '' ) ) {
		return array();
	}
	$table = axismundi_act_activities_table();
	$limit = max( 1, min( 200, $limit ) );
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- actor's effective vote projection query.
	$rows = (array) $wpdb->get_col( $wpdb->prepare( "SELECT object_uri FROM {$table} WHERE activity_type = %s AND reaction_key IS NULL AND actor_uri_hash = %s AND actor_uri = %s AND effective_status = 'active' AND object_uri IS NOT NULL ORDER BY id DESC LIMIT %d", $type, hash( 'sha256', $actor ), $actor, $limit ) );
	return array_values( array_unique( array_map( 'strval', $rows ) ) );
}

/** Number of historical vote cycles of one verb for an Actor/object pair. */
function axismundi_act_vote_cycle_count( string $type, string $actor_uri, string $object_uri ) : int {
	global $wpdb;
	if ( ! axismundi_act_is_vote_type( $type ) ) {
		return 0;
	}
	$table = axismundi_act_activities_table();
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- exact pair count used for a stable source-event key.
	return (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE activity_type = %s AND reaction_key IS NULL AND actor_uri_hash = %s AND actor_uri = %s AND object_uri_hash = %s AND object_uri = %s", $type, hash( 'sha256', $actor_uri ), $actor_uri, hash( 'sha256', $object_uri ), $object_uri ) );
}

/**
 * Record or return the effective vote of one verb for one object.
 *
 * @param string          $type                Vote verb.
 * @param Axismundi_Actor $actor               Local voting Actor.
 * @param string          $object_uri          Canonical object URI.
 * @param string          $recipient_actor_uri Optional object owner to address.
 * @return Axismundi_Activity|WP_Error
 */
function axismundi_act_vote_on_object( string $type, Axismundi_Actor $actor, string $object_uri, string $recipient_actor_uri = '' ) {
	if ( ! axismundi_act_is_vote_type( $type ) ) {
		return new WP_Error( 'ax_act_vote_type', __( 'That is not a supported vote activity.', 'axismundi-activities' ) );
	}
	$valid  = axismundi_act_validate_reaction_actor( $actor );
	$object = axismundi_act_uri( $object_uri );
	if ( is_wp_error( $valid ) ) {
		return $valid;
	}
	if ( '' === $object ) {
		return new WP_Error( 'ax_act_reaction_object', __( 'A vote requires a canonical object URI.', 'axismundi-activities' ) );
	}
	$existing = axismundi_act_get_actor_vote( $type, $actor->get_uri(), $object, true );
	if ( $existing instanceof Axismundi_Activity ) {
		return $existing;
	}
	$recipient = '' !== $recipient_actor_uri ? axismundi_actors_get_by_uri( $recipient_actor_uri ) : null;
	if ( '' !== $recipient_actor_uri && ! $recipient instanceof Axismundi_Actor ) {
		return new WP_Error( 'ax_act_reaction_recipient', __( 'The object owner Actor is unavailable.', 'axismundi-activities' ) );
	}
	$payload = array( 'type' => $type, 'actor' => $actor->get_uri(), 'object' => $object );
	if ( $recipient instanceof Axismundi_Actor && $recipient->get_uri() !== $actor->get_uri() ) {
		$payload['to'] = array( $recipient->get_uri() );
	}
	$direction = $recipient instanceof Axismundi_Actor && ! $recipient->is_local() ? 'outbound' : 'local';
	if ( $recipient instanceof Axismundi_Actor && $recipient->get_uri() === $actor->get_uri() ) {
		/*
		 * A self-vote still changes the public representation cached by the Actor's remote
		 * followers. Addressing only the object owner would address ourselves and leave those
		 * peers at their old count, so send this narrow self-case to the followers audience
		 * just like a public authored Activity.
		 */
		$followers = (string) apply_filters( 'axismundi_act_actor_followers_uri', '', $actor );
		if ( '' !== $followers ) {
			$payload['cc'] = array( $followers );
			$direction     = 'outbound';
		}
	}
	$cycle  = axismundi_act_vote_cycle_count( $type, $actor->get_uri(), $object ) + 1;
	$source = strtolower( $type ) . ':' . hash( 'sha256', $actor->get_uri() ) . ':' . hash( 'sha256', $object ) . ':' . $cycle;
	return axismundi_act_record_source_activity( $payload, $direction, $source );
}

/**
 * Undo the current vote of one verb by referring to its Activity URI, never the object URI.
 *
 * @param string          $type       Vote verb.
 * @param Axismundi_Actor $actor      Local voting Actor.
 * @param string          $object_uri Canonical object URI.
 * @return Axismundi_Activity|WP_Error
 */
function axismundi_act_undo_vote_on_object( string $type, Axismundi_Actor $actor, string $object_uri ) {
	if ( ! axismundi_act_is_vote_type( $type ) ) {
		return new WP_Error( 'ax_act_vote_type', __( 'That is not a supported vote activity.', 'axismundi-activities' ) );
	}
	$valid  = axismundi_act_validate_reaction_actor( $actor );
	$object = axismundi_act_uri( $object_uri );
	if ( is_wp_error( $valid ) ) {
		return $valid;
	}
	$vote = axismundi_act_get_actor_vote( $type, $actor->get_uri(), $object, true );
	if ( ! $vote instanceof Axismundi_Activity ) {
		// A repeated undo converges on the Undo that already happened rather than erroring,
		// so a double click and a retried request settle at the same state.
		$latest = axismundi_act_get_actor_vote( $type, $actor->get_uri(), $object, false );
		if ( $latest instanceof Axismundi_Activity && ! $latest->is_effective() ) {
			$undos = array_filter( axismundi_act_get_by_object( $latest->get_uri(), 20 ), static fn( Axismundi_Activity $item ) : bool => 'Undo' === $item->get_type() && $item->is_effective() && $item->get_actor_uri() === $actor->get_uri() );
			return ! empty( $undos ) ? reset( $undos ) : new WP_Error( 'ax_act_vote_missing', __( 'There is no active vote to undo.', 'axismundi-activities' ) );
		}
		return new WP_Error( 'ax_act_vote_missing', __( 'There is no active vote to undo.', 'axismundi-activities' ) );
	}
	$payload = array( 'type' => 'Undo', 'actor' => $actor->get_uri(), 'object' => $vote->get_uri() );
	$to      = (array) ( $vote->get_audience()['to'] ?? array() );
	$cc      = (array) ( $vote->get_audience()['cc'] ?? array() );
	if ( ! empty( $to ) ) {
		$payload['to'] = $to;
	}
	if ( ! empty( $cc ) ) {
		$payload['cc'] = $cc;
	}
	return axismundi_act_record_source_activity( $payload, $vote->get_direction(), 'un' . strtolower( $type ) . ':' . $vote->get_uri() );
}

/** @return Axismundi_Activity[] Effective Dislikes for one object. */
function axismundi_act_get_effective_dislikes( string $object_uri, int $limit = 100 ) : array {
	return axismundi_act_get_effective_votes( 'Dislike', $object_uri, $limit );
}

/** Count distinct Actors with an effective Dislike for one object URI. */
function axismundi_act_get_dislike_count( string $object_uri ) : int {
	return axismundi_act_get_vote_count( 'Dislike', $object_uri );
}

/** Latest Dislike by one Actor for one object. */
function axismundi_act_get_actor_dislike( string $actor_uri, string $object_uri, bool $effective_only = true ) : ?Axismundi_Activity {
	return axismundi_act_get_actor_vote( 'Dislike', $actor_uri, $object_uri, $effective_only );
}

/** Whether one Actor currently dislikes an object. */
function axismundi_act_get_dislike_state( string $actor_uri, string $object_uri ) : bool {
	return axismundi_act_get_vote_state( 'Dislike', $actor_uri, $object_uri );
}

/** Record or return the effective Dislike for one object. */
function axismundi_act_dislike_object( Axismundi_Actor $actor, string $object_uri, string $recipient_actor_uri = '' ) {
	return axismundi_act_vote_on_object( 'Dislike', $actor, $object_uri, $recipient_actor_uri );
}

/** Undo the current Dislike by referring to the Dislike Activity URI. */
function axismundi_act_undislike_object( Axismundi_Actor $actor, string $object_uri ) {
	return axismundi_act_undo_vote_on_object( 'Dislike', $actor, $object_uri );
}
