<?php
/**
 * The release train's schemas, on a new site and on an old one (dev-only).
 *
 * Deploying is two different events wearing one name. A new site runs every installer once against
 * an empty database; an existing site runs whatever migration steps it has not run yet, against
 * tables that already hold somebody's data. Only the second one can lose anything, and only the
 * first one is what a developer usually tests.
 *
 * So this asks both, of every plugin in the train that owns tables: is the schema at the version
 * this code expects, and does the upgrade path put it back there after being told it is behind?
 * Running it in the `tests` environment answers the new-site question, because that database has
 * never seen these plugins; running it in the development environment answers the other, because
 * that one has months of fixtures in it.
 *
 * It never drops a table. A migration audit that starts by deleting the data is not auditing
 * migration -- it is auditing installation twice.
 *
 * @package Axismundi
 */

defined( 'ABSPATH' ) || exit( 1 );

$ax_rt_results = array();

/** @param bool[] $results Results. */
function ax_rt_assert( array &$results, string $label, bool $condition ) : void {
	$results[] = $condition;
	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI fixture output.
	printf( "[%s] %s\n", $condition ? 'PASS' : 'FAIL', $label );
}

/*
 * Each plugin in the train that owns storage: the version its code expects, the option holding the
 * version the database is at, the installer, and one table that must exist for the plugin to be
 * anything at all.
 */
$ax_rt_plugins = array(
	'Axismundi Actors'             => array( 'AXISMUNDI_ACTORS_DB_VERSION', 'ax_actors_db_version', 'axismundi_actors_maybe_upgrade', 'ax_identities' ),
	'Axismundi Activities'         => array( 'AXISMUNDI_ACT_DB_VERSION', 'AXISMUNDI_ACT_DB_VERSION_OPTION', 'axismundi_act_maybe_upgrade', 'ax_activities' ),
	'Axismundi Object Projections' => array( 'AXISMUNDI_OP_DB_VERSION', 'AXISMUNDI_OP_DB_VERSION_OPTION', 'axismundi_op_maybe_upgrade', 'ax_remote_objects' ),
	'Axismundi Note'               => array( 'AXISMUNDI_NOTE_DB_VERSION', 'AXISMUNDI_NOTE_DB_VERSION_OPTION', 'axismundi_note_maybe_install', 'ax_notes' ),
	'Axismundi Calendar'           => array( 'AXISMUNDI_CAL_DB_VERSION', 'AXISMUNDI_CAL_DB_VERSION_OPTION', 'axismundi_cal_maybe_upgrade', 'ax_cal_calendars' ),
	'Axismundi Notifications'      => array( 'AXISMUNDI_NTF_DB_VERSION', 'AXISMUNDI_NTF_DB_VERSION_OPTION', 'axismundi_ntf_maybe_upgrade', 'ax_ntf_events' ),
	'Axismundi PWA'                => array( 'AXISMUNDI_PWA_DB_VERSION', 'AXISMUNDI_PWA_DB_VERSION_OPTION', 'axismundi_pwa_maybe_upgrade', 'ax_pwa_push_subscriptions' ),
);

global $wpdb;

foreach ( $ax_rt_plugins as $ax_rt_name => $ax_rt_spec ) {
	list( $ax_rt_version_constant, $ax_rt_option, $ax_rt_upgrade, $ax_rt_table ) = $ax_rt_spec;
	if ( ! defined( $ax_rt_version_constant ) || ! function_exists( $ax_rt_upgrade ) ) {
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI fixture output.
		printf( "[SKIP] %s is not active here\n", $ax_rt_name );
		continue;
	}
	// The option name is itself a constant in the newer plugins and a literal in the older ones.
	$ax_rt_option_name = defined( $ax_rt_option ) ? (string) constant( $ax_rt_option ) : $ax_rt_option;
	$ax_rt_expected    = (string) constant( $ax_rt_version_constant );
	$ax_rt_full_table  = $wpdb->prefix . $ax_rt_table;

	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- schema check.
	$ax_rt_exists = $ax_rt_full_table === $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $ax_rt_full_table ) );
	ax_rt_assert(
		$ax_rt_results,
		sprintf( '%s has its storage at the version this code expects (%s)', $ax_rt_name, $ax_rt_expected ),
		$ax_rt_exists && $ax_rt_expected === (string) get_option( $ax_rt_option_name, '' )
	);

	/*
	 * The upgrade path, told the site is behind, through the entry point a real deployment runs rather
	 * than through the installer underneath it -- a plugin whose migration is only wired to activation
	 * would pass the second check and still ship a site that never migrates. `dbDelta` is idempotent,
	 * so running it against tables holding data is safe; whether the recorded version comes back is
	 * the question.
	 */
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- schema check.
	$ax_rt_rows_before = $ax_rt_exists ? (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . $ax_rt_full_table ) : 0;
	$ax_rt_before      = get_option( $ax_rt_option_name, '' );
	update_option( $ax_rt_option_name, '0', false );
	call_user_func( $ax_rt_upgrade );
	$ax_rt_after = (string) get_option( $ax_rt_option_name, '' );
	if ( $ax_rt_expected !== $ax_rt_after ) {
		update_option( $ax_rt_option_name, $ax_rt_before, false );
	}
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- schema check.
	$ax_rt_rows_after = $ax_rt_exists ? (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . $ax_rt_full_table ) : 0;
	ax_rt_assert(
		$ax_rt_results,
		sprintf( '%s migrates a database that is behind back up to it, and the rows that were there still are', $ax_rt_name ),
		$ax_rt_expected === $ax_rt_after && $ax_rt_rows_before === $ax_rt_rows_after
	);
}

$ax_rt_failures = count( array_filter( $ax_rt_results, static fn( bool $result ) : bool => ! $result ) );
// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI fixture output.
printf( "\n== %d checks, %d failed ==\n", count( $ax_rt_results ), $ax_rt_failures );
if ( class_exists( 'WP_CLI' ) ) {
	WP_CLI::halt( $ax_rt_failures > 0 ? 1 : 0 );
}
exit( $ax_rt_failures > 0 ? 1 : 0 );
