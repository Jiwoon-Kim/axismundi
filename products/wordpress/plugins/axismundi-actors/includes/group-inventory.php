<?php
/**
 * Read-only survey of local Group Actors, for deciding what to migrate and what to retire.
 *
 * This writes nothing, installs no schema and repairs nothing. Every lookup that depends on a
 * table or plugin which may be absent reports `unsupported` rather than a number: a missing
 * Forum or an older schema must not read as "no evidence", because that is the one mistake that
 * turns a survey into a reason to delete something.
 *
 * Follower counts are evidence of being known, never proof of not being known. A remote can hold
 * a Group URI from a Follow that was later undone, from an Announce it received, from an object
 * whose audience named the Group, or from a plain fetch. Read a zero here as "no current
 * followers", nothing more.
 *
 * Actor scope and AS2 Actor type are different axes. A `site` scope Group is not thereby wrong.
 *
 * @package AxismundiActors
 */

defined( 'ABSPATH' ) || exit;

const AXISMUNDI_ACTORS_INVENTORY_UNSUPPORTED = 'unsupported';

/** @return bool Whether a table exists and can be read. */
function axismundi_actors_inventory_table_ready( string $table ) : bool {
	global $wpdb;
	if ( '' === $table ) {
		return false;
	}
	$found = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- read-only survey.
	return is_string( $found ) && $found === $table;
}

/**
 * Resolve a table accessor only when both the function and its table are present.
 *
 * @return string Table name, or '' when the survey cannot read it.
 */
function axismundi_actors_inventory_table( string $accessor ) : string {
	if ( ! function_exists( $accessor ) ) {
		return '';
	}
	$table = (string) call_user_func( $accessor );
	return axismundi_actors_inventory_table_ready( $table ) ? $table : '';
}

/**
 * Follow evidence for one Group: current accepted inbound follows, and any relation row ever.
 *
 * The second number is the one that matters for retirement: a row in any state means some remote
 * once asked for this Group by URI.
 *
 * @return array{current:int|string,ever:int|string}
 */
function axismundi_actors_inventory_follow_evidence( string $uri ) : array {
	global $wpdb;
	$table = axismundi_actors_inventory_table( 'axismundi_act_relations_table' );
	if ( '' === $table ) {
		return array( 'current' => AXISMUNDI_ACTORS_INVENTORY_UNSUPPORTED, 'ever' => AXISMUNDI_ACTORS_INVENTORY_UNSUPPORTED );
	}
	$hash = hash( 'sha256', $uri );
	$current = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE relation_type='follow' AND object_actor_uri_hash=%s AND state='accepted'", $hash ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- read-only survey.
	$ever = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE subject_actor_uri_hash=%s OR object_actor_uri_hash=%s", $hash, $hash ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- read-only survey.
	if ( '' !== $wpdb->last_error ) {
		return array( 'current' => AXISMUNDI_ACTORS_INVENTORY_UNSUPPORTED, 'ever' => AXISMUNDI_ACTORS_INVENTORY_UNSUPPORTED );
	}
	return array( 'current' => (int) $current, 'ever' => (int) $ever );
}

/** @return int|string Activities this Group authored, or `unsupported`. */
function axismundi_actors_inventory_activity_count( string $uri ) {
	global $wpdb;
	$table = axismundi_actors_inventory_table( 'axismundi_act_activities_table' );
	if ( '' === $table ) {
		return AXISMUNDI_ACTORS_INVENTORY_UNSUPPORTED;
	}
	$count = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE actor_uri_hash=%s", hash( 'sha256', $uri ) ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- read-only survey.
	return '' === $wpdb->last_error ? (int) $count : AXISMUNDI_ACTORS_INVENTORY_UNSUPPORTED;
}

/** @return int|string Principal grants over this Group, or `unsupported`. Read only; never promoted. */
function axismundi_actors_inventory_principal_count( int $identity_id ) {
	global $wpdb;
	$table = axismundi_actors_inventory_table( 'axismundi_actors_managers_table' );
	if ( '' === $table ) {
		return AXISMUNDI_ACTORS_INVENTORY_UNSUPPORTED;
	}
	$count = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE identity_id=%d", $identity_id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- read-only survey.
	return '' === $wpdb->last_error ? (int) $count : AXISMUNDI_ACTORS_INVENTORY_UNSUPPORTED;
}

/** @return int|string Moderator rows for this Group, or `unsupported`. Read only; never promoted. */
function axismundi_actors_inventory_moderator_count( int $identity_id ) {
	global $wpdb;
	$table = axismundi_actors_inventory_table( 'axismundi_actors_moderators_table' );
	if ( '' === $table ) {
		return AXISMUNDI_ACTORS_INVENTORY_UNSUPPORTED;
	}
	$count = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE group_id=%d", $identity_id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- read-only survey.
	return '' === $wpdb->last_error ? (int) $count : AXISMUNDI_ACTORS_INVENTORY_UNSUPPORTED;
}

/**
 * Forum binding and membership counts.
 *
 * Forum may be deactivated, which is the normal state while its federation path is being moved
 * out. That reads as `unsupported`, not as an absence of bindings.
 *
 * @return array{entries:int|string,memberships:int|string}
 */
function axismundi_actors_inventory_forum_evidence( int $identity_id ) : array {
	global $wpdb;
	$out = array();
	foreach ( array( 'entries' => 'axismundi_forum_entries_table', 'memberships' => 'axismundi_forum_memberships_table' ) as $label => $accessor ) {
		$table = axismundi_actors_inventory_table( $accessor );
		if ( '' === $table ) {
			$out[ $label ] = AXISMUNDI_ACTORS_INVENTORY_UNSUPPORTED;
			continue;
		}
		$columns = (array) $wpdb->get_col( "SHOW COLUMNS FROM {$table}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- read-only survey.
		// The column that names the Group differs between the two tables and between schema versions.
		$column = '';
		foreach ( array( 'group_identity_id', 'identity_id', 'group_id' ) as $candidate ) {
			if ( in_array( $candidate, $columns, true ) ) {
				$column = $candidate;
				break;
			}
		}
		if ( '' === $column ) {
			$out[ $label ] = AXISMUNDI_ACTORS_INVENTORY_UNSUPPORTED;
			continue;
		}
		$count = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE {$column}=%d", $identity_id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- read-only survey.
		$out[ $label ] = '' === $wpdb->last_error ? (int) $count : AXISMUNDI_ACTORS_INVENTORY_UNSUPPORTED;
	}
	return $out;
}

/**
 * One row per local Group Actor.
 *
 * @return array<int,array<string,mixed>>
 */
function axismundi_actors_group_inventory() : array {
	global $wpdb;
	$actors = axismundi_actors_actors_table();
	$rows = $wpdb->get_results( "SELECT identity_id, actor_scope, preferred_username, status, followers_total, created_at FROM {$actors} WHERE actor_type='Group' AND origin='local' ORDER BY identity_id", ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- read-only survey.
	$out = array();
	foreach ( (array) $rows as $row ) {
		$identity_id = (int) $row['identity_id'];
		$actor = axismundi_actors_get_by_identity( $identity_id );
		$uri = $actor instanceof Axismundi_Actor ? $actor->get_uri() : '';
		$profile = $actor instanceof Axismundi_Actor && function_exists( 'axismundi_actors_group_profile' )
			? ( (string) axismundi_actors_group_profile( $actor ) ?: 'unset' )
			: AXISMUNDI_ACTORS_INVENTORY_UNSUPPORTED;
		$follows = '' === $uri
			? array( 'current' => AXISMUNDI_ACTORS_INVENTORY_UNSUPPORTED, 'ever' => AXISMUNDI_ACTORS_INVENTORY_UNSUPPORTED )
			: axismundi_actors_inventory_follow_evidence( $uri );
		$forum = axismundi_actors_inventory_forum_evidence( $identity_id );
		$out[] = array(
			'identity_id'     => $identity_id,
			'uri'             => '' === $uri ? AXISMUNDI_ACTORS_INVENTORY_UNSUPPORTED : $uri,
			'handle'          => (string) $row['preferred_username'],
			'profile'         => $profile,
			'status'          => (string) $row['status'],
			'scope'           => (string) $row['actor_scope'],
			'created_at'      => (string) $row['created_at'],
			'followers_total' => (int) $row['followers_total'],
			'follows_current' => $follows['current'],
			'follows_ever'    => $follows['ever'],
			'activities'      => '' === $uri ? AXISMUNDI_ACTORS_INVENTORY_UNSUPPORTED : axismundi_actors_inventory_activity_count( $uri ),
			'forum_entries'   => $forum['entries'],
			'forum_members'   => $forum['memberships'],
			'principals'      => axismundi_actors_inventory_principal_count( $identity_id ),
			'moderators'      => axismundi_actors_inventory_moderator_count( $identity_id ),
		);
	}
	return $out;
}

if ( defined( 'WP_CLI' ) && WP_CLI ) {
	/**
	 * Survey local Group Actors. Writes nothing.
	 *
	 * ## OPTIONS
	 *
	 * [--format=<format>]
	 * : table, csv or json. Default table.
	 */
	WP_CLI::add_command( 'axismundi group inventory', static function ( array $args, array $assoc ) : void {
		$rows = axismundi_actors_group_inventory();
		if ( ! $rows ) {
			WP_CLI::log( 'No local Group Actors.' );
			return;
		}
		$fields = array( 'identity_id', 'handle', 'profile', 'status', 'scope', 'followers_total', 'follows_current', 'follows_ever', 'activities', 'forum_entries', 'forum_members', 'principals', 'moderators', 'created_at', 'uri' );
		WP_CLI\Utils\format_items( $assoc['format'] ?? 'table', $rows, $fields );
		WP_CLI::log( sprintf( '%d local Group Actors. "%s" means this survey could not read that source, which is not the same as zero.', count( $rows ), AXISMUNDI_ACTORS_INVENTORY_UNSUPPORTED ) );
	} );
}
