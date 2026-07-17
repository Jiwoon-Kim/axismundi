<?php
/**
 * Phase 1 Activity repository regression fixture (dev-only).
 *
 * @package AxismundiActivities
 */

defined( 'ABSPATH' ) || exit( 1 );

require_once dirname( __DIR__ ) . '/includes/repository.php';

$ax_act_results       = array();
$ax_act_uris          = array();
$ax_act_remote_id     = 0;
$ax_act_suffix        = strtolower( wp_generate_password( 8, false, false ) );
// Declared up front so `finally` can always restore the prefix and drop the shadow tables,
// even if the migration block throws while $wpdb->prefix is redirected.
$ax_act_real_prefix   = $GLOBALS['wpdb']->prefix;
$ax_act_real_version  = get_option( AXISMUNDI_ACT_DB_VERSION_OPTION );
$ax_act_shadow_prefix = '';
$ax_act_remote_uri    = 'https://example.com/users/ax_activity_' . $ax_act_suffix;
$GLOBALS['ax_act_hook_uris'] = array();
$GLOBALS['ax_act_http_requests'] = 0;

/** Record one fixture result. */
function ax_act_assert( array &$results, string $label, bool $condition ) : void {
	$results[] = $condition;
	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI test output.
	printf( "[%s] %s\n", $condition ? 'PASS' : 'FAIL', $label );
}

/** Observe post-commit records. */
function ax_act_observe_record( Axismundi_Activity $activity ) : void {
	$GLOBALS['ax_act_hook_uris'][] = $activity->get_uri();
}

/** Prove repository operations do not perform HTTP. */
function ax_act_observe_http( $preempt ) {
	++$GLOBALS['ax_act_http_requests'];
	return $preempt;
}

try {
	$installed = axismundi_act_install();
	global $wpdb;
	$table = axismundi_act_activities_table();
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fixture verifies its custom table.
	$columns = (array) $wpdb->get_col( "SHOW COLUMNS FROM {$table}" );
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fixture verifies its custom index.
	$index = (array) $wpdb->get_results( "SHOW INDEX FROM {$table} WHERE Key_name = 'activity_uri_hash'", ARRAY_A );
	ax_act_assert( $ax_act_results, 'the Activity table retains verified unique URI identity and no blog_id tenancy column after upgrade', $installed && AXISMUNDI_ACT_DB_VERSION === (string) get_option( AXISMUNDI_ACT_DB_VERSION_OPTION ) && ! empty( $index ) && 0 === (int) $index[0]['Non_unique'] && ! in_array( 'blog_id', $columns, true ) );

	$site_actor = axismundi_actors_get_site_actor();
	$remote     = axismundi_actors_upsert_remote(
		array(
			'uri'                => $ax_act_remote_uri,
			'actor_type'         => 'Person',
			'preferred_username' => 'ax_activity_' . $ax_act_suffix,
			'display_name'       => 'Activity fixture remote Actor',
			'profile_url'        => 'https://example.com/@ax_activity_' . $ax_act_suffix,
			'payload'            => array( 'id' => $ax_act_remote_uri, 'type' => 'Person' ),
			'endpoints'          => array(
				'inbox'  => $ax_act_remote_uri . '/inbox',
				'outbox' => $ax_act_remote_uri . '/outbox',
			),
		)
	);
	$ax_act_remote_id = $remote instanceof Axismundi_Actor ? $remote->get_identity_id() : 0;
	ax_act_assert( $ax_act_results, 'fixture has local and remote Actors from the required Actors repository', $site_actor instanceof Axismundi_Actor && $site_actor->is_local() && $remote instanceof Axismundi_Actor && ! $remote->is_local() );

	add_action( 'axismundi_act_activity_recorded', 'ax_act_observe_record' );
	add_filter( 'pre_http_request', 'ax_act_observe_http' );

	$object_uri = 'https://remote.example/objects/' . $ax_act_suffix;
	$like       = axismundi_act_record_activity(
		array(
			'type'   => 'Like',
			'actor'  => $site_actor->get_uri(),
			'object' => $object_uri,
			'to'     => array( 'https://www.w3.org/ns/activitystreams#Public' ),
			'cc'     => array( $ax_act_remote_uri . '/followers' ),
		),
		'outbound'
	);
	if ( $like instanceof Axismundi_Activity ) {
		$ax_act_uris[] = $like->get_uri();
	}
	ax_act_assert(
		$ax_act_results,
		'local recording mints a UUID URI, embeds it in the immutable payload, and normalizes audience',
		$like instanceof Axismundi_Activity
			&& null !== $like->get_local_uuid()
			&& axismundi_act_local_uri( (string) $like->get_local_uuid() ) === $like->get_uri()
			&& $like->get_uri() === (string) $like->get_payload()['id']
			&& array( 'https://www.w3.org/ns/activitystreams#Public' ) === $like->get_audience()['to']
	);

	$replay = $like instanceof Axismundi_Activity ? axismundi_act_record_activity( $like->get_payload(), 'outbound' ) : null;
	ax_act_assert( $ax_act_results, 'an identical URI/payload replay is idempotent and does not emit a second recorded hook', $replay instanceof Axismundi_Activity && $replay->get_id() === $like->get_id() && 1 === count( $GLOBALS['ax_act_hook_uris'] ) );

	$conflicting_payload            = $like instanceof Axismundi_Activity ? $like->get_payload() : array();
	$conflicting_payload['summary'] = 'different immutable content';
	$conflict                       = axismundi_act_record_activity( $conflicting_payload, 'outbound' );
	ax_act_assert( $ax_act_results, 'the same Activity URI with a different payload is rejected without mutation', is_wp_error( $conflict ) && 'ax_act_identity_conflict' === $conflict->get_error_code() && axismundi_act_get( $like->get_uri() )->get_payload() === $like->get_payload() );

	$remote_activity_uri = 'https://example.com/activities/' . $ax_act_suffix;
	$remote_activity     = axismundi_act_record_activity(
		array(
			'id'        => $remote_activity_uri,
			'type'      => 'Announce',
			'actor'     => $ax_act_remote_uri,
			'object'    => $object_uri,
			'published' => '2026-07-14T12:00:00Z',
		),
		'inbound'
	);
	if ( $remote_activity instanceof Axismundi_Activity ) {
		$ax_act_uris[] = $remote_activity->get_uri();
	}
	ax_act_assert( $ax_act_results, 'an inbound Activity preserves its remote id and has no local UUID', $remote_activity instanceof Axismundi_Activity && $remote_activity_uri === $remote_activity->get_uri() && null === $remote_activity->get_local_uuid() && 'inbound' === $remote_activity->get_direction() );

	$wrong_direction = axismundi_act_record_activity( array( 'id' => 'https://example.com/activities/wrong-' . $ax_act_suffix, 'type' => 'Like', 'actor' => $site_actor->get_uri(), 'object' => $object_uri ), 'inbound' );
	$unknown_actor   = axismundi_act_record_activity( array( 'type' => 'Like', 'actor' => 'https://unknown.example/users/nope', 'object' => $object_uri ), 'outbound' );
	ax_act_assert( $ax_act_results, 'Actor dependency fails closed for unknown Actors and direction/origin conflicts', is_wp_error( $wrong_direction ) && 'ax_act_direction' === $wrong_direction->get_error_code() && is_wp_error( $unknown_actor ) && 'ax_act_actor' === $unknown_actor->get_error_code() );
	$missing_object = axismundi_act_record_activity( array( 'type' => 'Like', 'actor' => $site_actor->get_uri() ), 'local' );
	$missing_target = axismundi_act_record_activity( array( 'type' => 'Add', 'actor' => $site_actor->get_uri(), 'object' => $object_uri ), 'local' );
	ax_act_assert( $ax_act_results, 'Activity-specific minimum references reject empty objects and collection targets', is_wp_error( $missing_object ) && 'ax_act_object' === $missing_object->get_error_code() && is_wp_error( $missing_target ) && 'ax_act_target' === $missing_target->get_error_code() );

	$by_actor  = axismundi_act_get_by_actor( $site_actor->get_uri() );
	$by_object = axismundi_act_get_by_object( $object_uri );
	ax_act_assert( $ax_act_results, 'Actor and Object reverse lookups use exact URI references', 1 <= count( array_filter( $by_actor, static fn( Axismundi_Activity $item ) : bool => $item->get_uri() === $like->get_uri() ) ) && 2 <= count( $by_object ) );

	$oversize = axismundi_act_record_activity( array( 'type' => 'Create', 'actor' => $site_actor->get_uri(), 'object' => $object_uri, 'content' => str_repeat( 'x', AXISMUNDI_ACT_PAYLOAD_MAX + 1 ) ), 'local' );
	ax_act_assert( $ax_act_results, 'oversized payloads are rejected before database mutation', is_wp_error( $oversize ) && 'ax_act_payload_size' === $oversize->get_error_code() );

	$undo = axismundi_act_record_activity( array( 'type' => 'Undo', 'actor' => $site_actor->get_uri(), 'object' => $like->get_uri() ), 'outbound' );
	if ( $undo instanceof Axismundi_Activity ) {
		$ax_act_uris[] = $undo->get_uri();
	}
	ax_act_assert( $ax_act_results, 'same-Actor Undo preserves the original payload and marks its effective state undone', $undo instanceof Axismundi_Activity && 'undone' === axismundi_act_get( $like->get_uri() )->get_effective_status() && 'Like' === axismundi_act_get( $like->get_uri() )->get_type() );

	$mismatched_undo = axismundi_act_record_activity( array( 'id' => 'https://example.com/activities/mismatch-' . $ax_act_suffix, 'type' => 'Undo', 'actor' => $ax_act_remote_uri, 'object' => $like->get_uri() ), 'inbound' );
	ax_act_assert( $ax_act_results, 'Undo authored by another Actor is rejected and cannot alter effective state', is_wp_error( $mismatched_undo ) && 'ax_act_undo_actor' === $mismatched_undo->get_error_code() && 'undone' === axismundi_act_get( $like->get_uri() )->get_effective_status() );

	$undo_undo = axismundi_act_record_activity( array( 'type' => 'Undo', 'actor' => $site_actor->get_uri(), 'object' => $undo->get_uri() ), 'outbound' );
	if ( $undo_undo instanceof Axismundi_Activity ) {
		$ax_act_uris[] = $undo_undo->get_uri();
	}
	ax_act_assert( $ax_act_results, 'Undo of Undo neutralizes the first Undo and restores the original Activity', $undo_undo instanceof Axismundi_Activity && 'undone' === axismundi_act_get( $undo->get_uri() )->get_effective_status() && 'active' === axismundi_act_get( $like->get_uri() )->get_effective_status() );

	$future_uuid = wp_generate_uuid4();
	$future_uri  = axismundi_act_local_uri( $future_uuid );
	$early_undo  = axismundi_act_record_activity( array( 'type' => 'Undo', 'actor' => $site_actor->get_uri(), 'object' => $future_uri ), 'local' );
	if ( $early_undo instanceof Axismundi_Activity ) {
		$ax_act_uris[] = $early_undo->get_uri();
	}
	$future_like = axismundi_act_record_activity( array( 'id' => $future_uri, 'type' => 'Like', 'actor' => $site_actor->get_uri(), 'object' => 'https://remote.example/objects/future-' . $ax_act_suffix ), 'local' );
	if ( $future_like instanceof Axismundi_Activity ) {
		$ax_act_uris[] = $future_like->get_uri();
	}
	ax_act_assert( $ax_act_results, 'an out-of-order Undo is reconciled when its same-Actor target arrives', $early_undo instanceof Axismundi_Activity && $future_like instanceof Axismundi_Activity && 'undone' === $future_like->get_effective_status() );

	ax_act_assert( $ax_act_results, 'recorded hooks observe committed rows exactly once and repository operations perform no HTTP', 6 === count( $GLOBALS['ax_act_hook_uris'] ) && 0 === $GLOBALS['ax_act_http_requests'] && count( $GLOBALS['ax_act_hook_uris'] ) === count( array_filter( $GLOBALS['ax_act_hook_uris'], static fn( string $uri ) : bool => axismundi_act_get( $uri ) instanceof Axismundi_Activity ) ) );

	// v5 `instrument`. Deliberately after the hook-count assertion above, which counts every
	// Activity this fixture records.
	$ax_act_instrument_uri = 'https://remote.example/users/bob/statuses/' . $ax_act_suffix;
	$ax_act_quoted_uri     = 'https://remote.example/users/alice/statuses/' . $ax_act_suffix;
	// FEP-044f sends the quoting Object embedded, not as a bare URI.
	$ax_act_embedded = axismundi_act_normalize(
		array(
			'type'       => 'Like',
			'actor'      => $site_actor->get_uri(),
			'object'     => $ax_act_quoted_uri,
			'instrument' => array( 'type' => 'Note', 'id' => $ax_act_instrument_uri, 'quote' => $ax_act_quoted_uri ),
		),
		'outbound'
	);
	ax_act_assert(
		$ax_act_results,
		'an embedded instrument is reduced to its id and hashed, distinct from the object it accompanies',
		is_array( $ax_act_instrument = $ax_act_embedded )
			&& $ax_act_instrument_uri === ( $ax_act_embedded['instrument_uri'] ?? '' )
			&& hash( 'sha256', $ax_act_instrument_uri ) === ( $ax_act_embedded['instrument_uri_hash'] ?? '' )
			&& $ax_act_quoted_uri === ( $ax_act_embedded['object_uri'] ?? '' )
	);

	$ax_act_no_instrument = axismundi_act_normalize( array( 'type' => 'Like', 'actor' => $site_actor->get_uri(), 'object' => $ax_act_quoted_uri ), 'outbound' );
	ax_act_assert(
		$ax_act_results,
		'an Activity without an instrument stores null rather than an empty string',
		is_array( $ax_act_no_instrument )
			&& null === $ax_act_no_instrument['instrument_uri']
			&& null === $ax_act_no_instrument['instrument_uri_hash']
	);

	$ax_act_with_instrument = axismundi_act_record_activity(
		array(
			'type'       => 'Like',
			'actor'      => $site_actor->get_uri(),
			'object'     => $ax_act_quoted_uri,
			'instrument' => array( 'type' => 'Note', 'id' => $ax_act_instrument_uri ),
		),
		'outbound'
	);
	if ( $ax_act_with_instrument instanceof Axismundi_Activity ) {
		$ax_act_uris[] = $ax_act_with_instrument->get_uri();
	}
	$ax_act_reread = $ax_act_with_instrument instanceof Axismundi_Activity ? axismundi_act_get( $ax_act_with_instrument->get_uri() ) : null;
	ax_act_assert(
		$ax_act_results,
		'instrument survives the write and rehydration, and the payload keeps the embedded original',
		$ax_act_reread instanceof Axismundi_Activity
			&& $ax_act_instrument_uri === $ax_act_reread->get_instrument_uri()
			&& $ax_act_instrument_uri === ( $ax_act_reread->get_payload()['instrument']['id'] ?? '' )
	);

	// A source event that resolves to a different instrument is a different Activity.
	$ax_act_source = 'fixture-instrument:' . $ax_act_suffix;
	$ax_act_first  = axismundi_act_record_source_activity(
		array( 'type' => 'Like', 'actor' => $site_actor->get_uri(), 'object' => $ax_act_quoted_uri, 'instrument' => $ax_act_instrument_uri ),
		'outbound',
		$ax_act_source
	);
	if ( $ax_act_first instanceof Axismundi_Activity ) {
		$ax_act_uris[] = $ax_act_first->get_uri();
	}
	$ax_act_conflict = axismundi_act_record_source_activity(
		array( 'type' => 'Like', 'actor' => $site_actor->get_uri(), 'object' => $ax_act_quoted_uri, 'instrument' => $ax_act_instrument_uri . '-other' ),
		'outbound',
		$ax_act_source
	);
	$ax_act_replay = axismundi_act_record_source_activity(
		array( 'type' => 'Like', 'actor' => $site_actor->get_uri(), 'object' => $ax_act_quoted_uri, 'instrument' => $ax_act_instrument_uri ),
		'outbound',
		$ax_act_source
	);
	ax_act_assert(
		$ax_act_results,
		'one source event cannot silently change its instrument, while an identical replay stays idempotent',
		is_wp_error( $ax_act_conflict )
			&& 'ax_act_source_conflict' === $ax_act_conflict->get_error_code()
			&& $ax_act_replay instanceof Axismundi_Activity
			&& $ax_act_first instanceof Axismundi_Activity
			&& $ax_act_replay->get_uri() === $ax_act_first->get_uri()
	);

	// The real v4 -> v5 upgrade runs once per site, so reproduce it — but never on the shared
	// ledger. Dropping the column there is unrecoverable in practice: nothing backfills
	// instrument_uri from payload_json, so every pre-existing row would keep its payload and
	// lose the normalized column and index forever. The installer is prefix-driven, so point
	// it at a throwaway prefix instead and leave the real tables untouched.
	$ax_act_shadow_prefix = $ax_act_real_prefix . 'axfix' . $ax_act_suffix . '_';
	$wpdb->prefix         = $ax_act_shadow_prefix;
	$ax_act_shadow        = axismundi_act_activities_table();
	$ax_act_shadow_built  = axismundi_act_install();

	// Take the shadow down to the v4 shape and seed a row that predates the column.
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange -- throwaway fixture table.
	$wpdb->query( "ALTER TABLE {$ax_act_shadow} DROP INDEX instrument_uri_hash, DROP COLUMN instrument_uri, DROP COLUMN instrument_uri_hash" );
	$ax_act_seed_uri = 'https://remote.example/activities/v4-row-' . $ax_act_suffix;
	$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- throwaway fixture table.
		$ax_act_shadow,
		array(
			'activity_uri' => $ax_act_seed_uri,
			'activity_uri_hash' => hash( 'sha256', $ax_act_seed_uri ),
			'activity_type' => 'Like',
			'actor_uri' => $ax_act_remote_uri,
			'actor_uri_hash' => hash( 'sha256', $ax_act_remote_uri ),
			'object_uri' => $ax_act_quoted_uri,
			'object_uri_hash' => hash( 'sha256', $ax_act_quoted_uri ),
			'direction' => 'inbound',
			'effective_status' => 'active',
			'audience_json' => '{}',
			'payload_json' => '{}',
			'payload_hash' => hash( 'sha256', '{}' ),
			'created_at' => current_time( 'mysql', true ),
			'updated_at' => current_time( 'mysql', true ),
		)
	);
	delete_option( AXISMUNDI_ACT_DB_VERSION_OPTION );
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- throwaway fixture table.
	$ax_act_v4_columns = (array) $wpdb->get_col( "SHOW COLUMNS FROM {$ax_act_shadow}" );

	$ax_act_migrated = axismundi_act_install();

	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- throwaway fixture table.
	$ax_act_v5_columns = (array) $wpdb->get_col( "SHOW COLUMNS FROM {$ax_act_shadow}" );
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- throwaway fixture table.
	$ax_act_v5_index = (array) $wpdb->get_results( "SHOW INDEX FROM {$ax_act_shadow} WHERE Key_name = 'instrument_uri_hash'", ARRAY_A );
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- throwaway fixture table.
	$ax_act_seed_row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$ax_act_shadow} WHERE activity_uri_hash = %s", hash( 'sha256', $ax_act_seed_uri ) ), ARRAY_A );

	// Restored here so the assertions below read the real site; `finally` repeats it because
	// a throw above must not leave the prefix redirected.
	$wpdb->prefix = $ax_act_real_prefix;
	update_option( AXISMUNDI_ACT_DB_VERSION_OPTION, $ax_act_real_version, false );

	ax_act_assert(
		$ax_act_results,
		'a populated v4 ledger upgrades in place: the column and index appear, an existing row survives with a null instrument, and the version is recorded only afterwards',
		$ax_act_shadow_built
			&& ! in_array( 'instrument_uri', $ax_act_v4_columns, true )
			&& $ax_act_migrated
			&& in_array( 'instrument_uri', $ax_act_v5_columns, true )
			&& in_array( 'instrument_uri_hash', $ax_act_v5_columns, true )
			&& ! empty( $ax_act_v5_index )
			&& is_array( $ax_act_seed_row )
			&& null === $ax_act_seed_row['instrument_uri']
			&& null === $ax_act_seed_row['instrument_uri_hash']
	);

	$ax_act_real_table = axismundi_act_activities_table();
	// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fixed custom table name; real-table safety check.
	$ax_act_real_columns = (array) $wpdb->get_col( "SHOW COLUMNS FROM {$ax_act_real_table}" );
	ax_act_assert(
		$ax_act_results,
		'the migration test leaves the real ledger and its recorded version untouched',
		$wpdb->prefix === $ax_act_real_prefix
			&& AXISMUNDI_ACT_DB_VERSION === (string) get_option( AXISMUNDI_ACT_DB_VERSION_OPTION )
			&& in_array( 'instrument_uri', $ax_act_real_columns, true )
	);
} finally {
	remove_action( 'axismundi_act_activity_recorded', 'ax_act_observe_record' );
	remove_filter( 'pre_http_request', 'ax_act_observe_http' );
	global $wpdb;
	// First, before any cleanup below resolves a table name: a throw inside the migration
	// block would otherwise leave the prefix redirected and point every delete at the shadow.
	$wpdb->prefix = $ax_act_real_prefix;
	update_option( AXISMUNDI_ACT_DB_VERSION_OPTION, $ax_act_real_version, false );
	if ( '' !== $ax_act_shadow_prefix ) {
		foreach ( array( $ax_act_shadow_prefix . 'ax_activities', $ax_act_shadow_prefix . 'ax_activity_relations' ) as $ax_act_shadow_table ) {
			$wpdb->query( "DROP TABLE IF EXISTS {$ax_act_shadow_table}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange -- fixture-owned throwaway table.
		}
	}
	foreach ( array_unique( $ax_act_uris ) as $ax_act_uri ) {
		$wpdb->delete( axismundi_act_activities_table(), array( 'activity_uri_hash' => hash( 'sha256', $ax_act_uri ) ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fixture cleanup.
	}
	if ( $ax_act_remote_id > 0 ) {
		foreach ( array( axismundi_actors_endpoints_table(), axismundi_actors_keys_table(), axismundi_actors_fetch_state_table(), axismundi_actors_identity_relations_table(), axismundi_actors_asset_cache_table(), axismundi_actors_addresses_table(), axismundi_actors_texts_table() ) as $ax_act_child_table ) {
			$wpdb->delete( $ax_act_child_table, array( 'identity_id' => $ax_act_remote_id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fixture-owned Actor cleanup.
		}
		$wpdb->delete( axismundi_actors_actors_table(), array( 'identity_id' => $ax_act_remote_id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fixture-owned Actor cleanup.
		$wpdb->delete( axismundi_actors_identities_table(), array( 'id' => $ax_act_remote_id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fixture-owned Actor cleanup.
	}
}

$ax_act_failures = count( array_filter( $ax_act_results, static fn( bool $result ) : bool => ! $result ) );
// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI test output.
printf( "\n== %d checks, %d failed ==\n", count( $ax_act_results ), $ax_act_failures );

if ( class_exists( 'WP_CLI' ) ) {
	WP_CLI::halt( $ax_act_failures > 0 ? 1 : 0 );
}
exit( $ax_act_failures > 0 ? 1 : 0 );
