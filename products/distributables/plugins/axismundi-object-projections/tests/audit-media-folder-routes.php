<?php
/**
 * Folder route installation regression (dev-only; dist-excluded).
 *
 * Locks the failure that reached staging: the folder rewrite rules shipped in 0.0.18,
 * the version counter that was supposed to install them burned itself without the rules
 * ever reaching the table, and every /media/folder/{uuid} 404'd until permalinks were
 * saved by hand. Registration is not persistence — these assertions are about the stored
 * `rewrite_rules` option, which is what WordPress actually routes from.
 *
 * Deliberately does not use HTTP: wp-env's Apache ignores .htaccess, so no pretty URL can
 * be fetched locally. The rewrite table is testable, and it is where the bug lived.
 *
 * @package AxismundiObjectProjections
 */

defined( 'ABSPATH' ) || exit( 1 );

$ax_results        = array();
$ax_prev_permalink = (string) get_option( 'permalink_structure', '' );

/**
 * @param array  $results   Accumulator.
 * @param string $label     Assertion label.
 * @param bool   $condition Result.
 */
function ax_folder_routes_assert( array &$results, string $label, bool $condition ) : void {
	$results[] = $condition;
	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI test output.
	printf( "[%s] %s\n", $condition ? 'PASS' : 'FAIL', $label );
}

/** Re-run the init-time installer the way a fresh request would. */
function ax_folder_routes_run_installer() : void {
	delete_transient( 'ax_op_media_folder_rewrite_retry' );
	axismundi_op_maybe_upgrade_media_folder_routes();
}

try {
	global $wp_rewrite;

	// Plain permalinks keep no rewrite table; the staging condition is a pretty one.
	update_option( 'permalink_structure', '/%postname%/' );
	$wp_rewrite->init();
	axismundi_op_register_media_folder_routes();
	flush_rewrite_rules( false );

	ax_folder_routes_assert(
		$ax_results,
		'a flush puts every folder rule into the stored rewrite table',
		axismundi_op_media_folder_routes_installed()
	);

	$stored = (array) get_option( 'rewrite_rules' );
	ax_folder_routes_assert(
		$ax_results,
		'the stored rules route to the folder query vars, not to a page lookup',
		'index.php?ax_op_media_folder=$matches[1]' === ( $stored['^media/folder/([0-9a-fA-F-]{36})/?$'] ?? '' )
			&& 'index.php?ax_op_media_folder=$matches[1]&ax_op_media_folder_page=$matches[2]' === ( $stored['^media/folder/([0-9a-fA-F-]{36})/page/([0-9]+)/?$'] ?? '' )
	);

	// Exactly the staging state: the rules are gone from the table while the plugin still
	// believes it has already installed them.
	$broken = $stored;
	foreach ( array_keys( axismundi_op_media_folder_rewrite_rules() ) as $regex ) {
		unset( $broken[ $regex ] );
	}
	update_option( 'rewrite_rules', $broken );
	ax_folder_routes_assert(
		$ax_results,
		'a table missing the rules is reported as not installed',
		! axismundi_op_media_folder_routes_installed()
	);

	ax_folder_routes_run_installer();
	ax_folder_routes_assert(
		$ax_results,
		'the installer heals a table whose rules went missing, with no version bump and no manual permalink save',
		axismundi_op_media_folder_routes_installed()
	);

	// A stale counter from 0.0.18 must not be able to suppress the repair.
	update_option( 'ax_op_media_folder_rewrite_version', 99, false );
	$broken = (array) get_option( 'rewrite_rules' );
	foreach ( array_keys( axismundi_op_media_folder_rewrite_rules() ) as $regex ) {
		unset( $broken[ $regex ] );
	}
	update_option( 'rewrite_rules', $broken );
	ax_folder_routes_run_installer();
	ax_folder_routes_assert(
		$ax_results,
		'a burned 0.0.18 version counter no longer blocks installation',
		axismundi_op_media_folder_routes_installed()
	);
	delete_option( 'ax_op_media_folder_rewrite_version' );

	// An unpersistable rule must degrade to one flush an hour, not one per request.
	$broken = (array) get_option( 'rewrite_rules' );
	foreach ( array_keys( axismundi_op_media_folder_rewrite_rules() ) as $regex ) {
		unset( $broken[ $regex ] );
	}
	update_option( 'rewrite_rules', $broken );
	delete_transient( 'ax_op_media_folder_rewrite_retry' );
	axismundi_op_maybe_upgrade_media_folder_routes();      // takes the retry slot
	update_option( 'rewrite_rules', $broken );            // pretend the write was discarded
	axismundi_op_maybe_upgrade_media_folder_routes();      // must not flush again
	ax_folder_routes_assert(
		$ax_results,
		'a repeatedly failing install is rate-limited rather than flushing every request',
		! axismundi_op_media_folder_routes_installed()
			&& false !== get_transient( 'ax_op_media_folder_rewrite_retry' )
	);
	delete_transient( 'ax_op_media_folder_rewrite_retry' );

	// Plain permalinks have no table to install into; flushing on every request would be
	// a self-inflicted performance bug.
	update_option( 'permalink_structure', '' );
	$wp_rewrite->init();
	update_option( 'rewrite_rules', array() );
	delete_transient( 'ax_op_media_folder_rewrite_retry' );
	axismundi_op_maybe_upgrade_media_folder_routes();
	ax_folder_routes_assert(
		$ax_results,
		'plain permalinks are left alone instead of triggering an endless flush',
		false === get_transient( 'ax_op_media_folder_rewrite_retry' )
	);
} finally {
	delete_transient( 'ax_op_media_folder_rewrite_retry' );
	delete_option( 'ax_op_media_folder_rewrite_version' );
	update_option( 'permalink_structure', $ax_prev_permalink );
	global $wp_rewrite;
	$wp_rewrite->init();
	flush_rewrite_rules( false );
}

$ax_failed = count( array_filter( $ax_results, static fn( $r ) => ! $r ) );
// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI test output.
printf( "\n== %d checks, %d failed ==\n", count( $ax_results ), $ax_failed );
exit( $ax_failed > 0 ? 1 : 0 );
