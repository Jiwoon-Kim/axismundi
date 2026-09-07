<?php
/**
 * Rewrite installation regression (dev-only; dist-excluded).
 *
 * Locks the class of failure that reached staging in Object Projections 0.0.18 (rules
 * registered but never persisted, with a burned counter so nothing retried) plus the
 * mode-specific defect this plugin carried: the counter was consumed even on the branch
 * that deliberately skipped the flush in Core mode, so the record claimed rules were
 * installed that had never been written.
 *
 * Assertions are about the stored `rewrite_rules` option, which is what WordPress routes
 * from — the precise unit under test. Pretty URLs *are* fetchable locally (.wp-env.json
 * maps a real .htaccess and turns on pretty permalinks), so an end-to-end check is a
 * separate, complementary thing rather than something this file works around.
 *
 * @package AxismundiMediaLibrary
 */

defined( 'ABSPATH' ) || exit( 1 );

$ax_results        = array();
$ax_prev_permalink = (string) get_option( 'permalink_structure', '' );
$ax_prev_mode      = get_option( AXISMUNDI_MEDIA_MODE_OPTION, AXISMUNDI_MEDIA_MODE_DEFAULT );

/**
 * @param array  $results   Accumulator.
 * @param string $label     Assertion label.
 * @param bool   $condition Result.
 */
function ax_media_rewrite_assert( array &$results, string $label, bool $condition ) : void {
	$results[] = $condition;
	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI test output.
	printf( "[%s] %s\n", $condition ? 'PASS' : 'FAIL', $label );
}

/** Drop this plugin's rules from the stored table, as the staging failure did. */
function ax_media_rewrite_break_table() : void {
	$stored = (array) get_option( 'rewrite_rules' );
	foreach ( array_keys( axismundi_media_rewrite_rules() ) as $regex ) {
		unset( $stored[ $regex ] );
	}
	update_option( 'rewrite_rules', $stored );
}

/** Re-run the init-time installer the way a fresh request would. */
function ax_media_rewrite_run() : void {
	delete_transient( 'ax_media_rewrite_retry' );
	axismundi_media_maybe_upgrade_rewrite_rules();
}

try {
	global $wp_rewrite;

	update_option( 'permalink_structure', '/%postname%/' );
	update_option( AXISMUNDI_MEDIA_MODE_OPTION, 'independent' );
	$wp_rewrite->init();
	axismundi_media_register_rewrite_rules();
	flush_rewrite_rules( false );

	ax_media_rewrite_assert(
		$ax_results,
		'a flush puts every archive rule into the stored rewrite table',
		axismundi_media_rewrite_rules_installed()
	);

	$stored = (array) get_option( 'rewrite_rules' );
	ax_media_rewrite_assert(
		$ax_results,
		'the feed rules ride along on the same flush, so the archive rules are a valid canary',
		isset( $stored['^media/author/([^/]+)/feed/atom/?$'] )
	);

	ax_media_rewrite_break_table();
	ax_media_rewrite_assert(
		$ax_results,
		'a table missing the rules is reported as not installed',
		! axismundi_media_rewrite_rules_installed()
	);

	ax_media_rewrite_run();
	ax_media_rewrite_assert(
		$ax_results,
		'the installer heals a table whose rules went missing, with no manual permalink save',
		axismundi_media_rewrite_rules_installed()
	);

	// The exact staging shape: rules gone while a counter claims the work is done.
	update_option( 'ax_media_rewrite_version', 99, false );
	ax_media_rewrite_break_table();
	ax_media_rewrite_run();
	ax_media_rewrite_assert(
		$ax_results,
		'a burned version counter can no longer suppress the repair',
		axismundi_media_rewrite_rules_installed()
	);
	delete_option( 'ax_media_rewrite_version' );

	// The mode-specific defect: Core mode used to burn the counter without ever flushing,
	// so a later switch to Independent inherited a table with no rules and a record that
	// said the work was done.
	update_option( AXISMUNDI_MEDIA_MODE_OPTION, 'core' );
	ax_media_rewrite_break_table();
	ax_media_rewrite_run();
	ax_media_rewrite_assert(
		$ax_results,
		'Core mode installs nothing and leaves no record claiming it did',
		! axismundi_media_rewrite_rules_installed()
			&& '' === (string) get_option( 'ax_media_rewrite_version', '' )
	);

	update_option( AXISMUNDI_MEDIA_MODE_OPTION, 'independent' );
	ax_media_rewrite_run();
	ax_media_rewrite_assert(
		$ax_results,
		'switching to Independent installs the rules a Core-mode site never had',
		axismundi_media_rewrite_rules_installed()
	);

	// An unpersistable rule must degrade to one flush an hour, not one per request.
	ax_media_rewrite_break_table();
	$broken = (array) get_option( 'rewrite_rules' );
	delete_transient( 'ax_media_rewrite_retry' );
	axismundi_media_maybe_upgrade_rewrite_rules();   // takes the retry slot
	update_option( 'rewrite_rules', $broken );       // pretend the write was discarded
	axismundi_media_maybe_upgrade_rewrite_rules();   // must not flush again
	ax_media_rewrite_assert(
		$ax_results,
		'a repeatedly failing install is rate-limited rather than flushing every request',
		! axismundi_media_rewrite_rules_installed()
			&& false !== get_transient( 'ax_media_rewrite_retry' )
	);
	delete_transient( 'ax_media_rewrite_retry' );

	// Plain permalinks have no table to install into.
	update_option( 'permalink_structure', '' );
	$wp_rewrite->init();
	update_option( 'rewrite_rules', array() );
	delete_transient( 'ax_media_rewrite_retry' );
	axismundi_media_maybe_upgrade_rewrite_rules();
	ax_media_rewrite_assert(
		$ax_results,
		'plain permalinks are left alone instead of triggering an endless flush',
		false === get_transient( 'ax_media_rewrite_retry' )
	);
} finally {
	delete_transient( 'ax_media_rewrite_retry' );
	delete_option( 'ax_media_rewrite_version' );
	update_option( AXISMUNDI_MEDIA_MODE_OPTION, $ax_prev_mode );
	update_option( 'permalink_structure', $ax_prev_permalink );
	global $wp_rewrite;
	$wp_rewrite->init();
	flush_rewrite_rules( false );
}

$ax_failed = count( array_filter( $ax_results, static fn( $r ) => ! $r ) );
// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI test output.
printf( "\n== %d checks, %d failed ==\n", count( $ax_results ), $ax_failed );
exit( $ax_failed > 0 ? 1 : 0 );
