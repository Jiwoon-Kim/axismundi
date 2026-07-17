<?php
/**
 * Rewrite installation regression (dev-only; dist-excluded).
 *
 * Locks the class of failure that reached staging in Object Projections 0.0.18: rules
 * registered in memory, never persisted to the table, and a version counter that had
 * already burned itself so nothing ever retried. Assertions are about the stored
 * `rewrite_rules` option, which is what WordPress actually routes from.
 *
 * Deliberately not over HTTP: wp-env's Apache ignores .htaccess, so no pretty URL is
 * fetchable locally. The rewrite table is testable, and it is where the bug lives.
 *
 * @package AxismundiActors
 */

defined( 'ABSPATH' ) || exit( 1 );

require_once dirname( __DIR__ ) . '/includes/routing.php';

$ax_results        = array();
$ax_prev_permalink = (string) get_option( 'permalink_structure', '' );

/**
 * @param array  $results   Accumulator.
 * @param string $label     Assertion label.
 * @param bool   $condition Result.
 */
function ax_rewrite_install_assert( array &$results, string $label, bool $condition ) : void {
	$results[] = $condition;
	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI test output.
	printf( "[%s] %s\n", $condition ? 'PASS' : 'FAIL', $label );
}

/** Drop this plugin's rules from the stored table, as the staging failure did. */
function ax_rewrite_install_break_table() : void {
	$stored = (array) get_option( 'rewrite_rules' );
	foreach ( array_keys( axismundi_actors_rewrite_rules() ) as $regex ) {
		unset( $stored[ $regex ] );
	}
	update_option( 'rewrite_rules', $stored );
}

/** Re-run the init-time installer the way a fresh request would. */
function ax_rewrite_install_run() : void {
	delete_transient( 'ax_actors_rewrite_retry' );
	axismundi_actors_maybe_upgrade_rewrite_rules();
}

try {
	global $wp_rewrite;

	update_option( 'permalink_structure', '/%postname%/' );
	$wp_rewrite->init();
	axismundi_actors_register_rewrite_rules();
	flush_rewrite_rules( false );

	ax_rewrite_install_assert(
		$ax_results,
		'a flush puts every actor rule into the stored rewrite table',
		axismundi_actors_rewrite_rules_installed()
	);

	$stored = (array) get_option( 'rewrite_rules' );
	ax_rewrite_install_assert(
		$ax_results,
		'the identity and handle routes are stored against their query vars',
		'index.php?ax_actor=$matches[1]' === ( $stored['^actors/([0-9a-fA-F-]{36})/?$'] ?? '' )
			&& 'index.php?ax_actor_handle=$matches[1]' === ( $stored['^@([^/]+)/?$'] ?? '' )
	);

	ax_rewrite_install_break_table();
	ax_rewrite_install_assert(
		$ax_results,
		'a table missing the rules is reported as not installed',
		! axismundi_actors_rewrite_rules_installed()
	);

	ax_rewrite_install_run();
	ax_rewrite_install_assert(
		$ax_results,
		'the installer heals a table whose rules went missing, with no manual permalink save',
		axismundi_actors_rewrite_rules_installed()
	);

	// The exact staging shape: rules gone while a counter claims the work is done.
	update_option( 'ax_actors_rewrite_version', 99, false );
	ax_rewrite_install_break_table();
	ax_rewrite_install_run();
	ax_rewrite_install_assert(
		$ax_results,
		'a burned version counter can no longer suppress the repair',
		axismundi_actors_rewrite_rules_installed()
	);
	delete_option( 'ax_actors_rewrite_version' );

	// A new rule must take effect without anyone bumping a counter — the reason the old
	// counter had already reached 3.
	$stored = (array) get_option( 'rewrite_rules' );
	unset( $stored['^nodeinfo/2\.1/?$'] );
	update_option( 'rewrite_rules', $stored );
	ax_rewrite_install_run();
	ax_rewrite_install_assert(
		$ax_results,
		'a single missing rule is enough to trigger reinstallation',
		axismundi_actors_rewrite_rules_installed()
	);

	// An unpersistable rule must degrade to one flush an hour, not one per request.
	ax_rewrite_install_break_table();
	$broken = (array) get_option( 'rewrite_rules' );
	delete_transient( 'ax_actors_rewrite_retry' );
	axismundi_actors_maybe_upgrade_rewrite_rules();   // takes the retry slot
	update_option( 'rewrite_rules', $broken );        // pretend the write was discarded
	axismundi_actors_maybe_upgrade_rewrite_rules();   // must not flush again
	ax_rewrite_install_assert(
		$ax_results,
		'a repeatedly failing install is rate-limited rather than flushing every request',
		! axismundi_actors_rewrite_rules_installed()
			&& false !== get_transient( 'ax_actors_rewrite_retry' )
	);
	delete_transient( 'ax_actors_rewrite_retry' );

	// Plain permalinks have no table to install into.
	update_option( 'permalink_structure', '' );
	$wp_rewrite->init();
	update_option( 'rewrite_rules', array() );
	delete_transient( 'ax_actors_rewrite_retry' );
	axismundi_actors_maybe_upgrade_rewrite_rules();
	ax_rewrite_install_assert(
		$ax_results,
		'plain permalinks are left alone instead of triggering an endless flush',
		false === get_transient( 'ax_actors_rewrite_retry' )
	);
} finally {
	delete_transient( 'ax_actors_rewrite_retry' );
	delete_option( 'ax_actors_rewrite_version' );
	update_option( 'permalink_structure', $ax_prev_permalink );
	global $wp_rewrite;
	$wp_rewrite->init();
	flush_rewrite_rules( false );
}

$ax_failed = count( array_filter( $ax_results, static fn( $r ) => ! $r ) );
// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI test output.
printf( "\n== %d checks, %d failed ==\n", count( $ax_results ), $ax_failed );
exit( $ax_failed > 0 ? 1 : 0 );
