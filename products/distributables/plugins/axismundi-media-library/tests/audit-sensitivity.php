<?php
/**
 * Phase 4a — sensitive authority regression (dev-only; dist-excluded).
 *
 * Self-contained; `finally` cleanup; exit 0/1. Locks: owner self-mark + self-clear;
 * moderator lock the owner cannot clear (the Phase 2c gap, now resolved); automated
 * flag the owner may not self-clear but a moderator can; confirmed lock; owner cannot
 * set a moderator-only state; the derived boolean tracks state; capability mapping;
 * legacy fallback.
 *
 * @package AxismundiMediaLibrary
 */

defined( 'ABSPATH' ) || exit( 1 );
require_once ABSPATH . 'wp-admin/includes/user.php';

$ax_results = array();

/**
 * @param array  $results Accumulator.
 * @param string $label   Contract.
 * @param bool   $cond    Holds.
 * @return void
 */
function ax_sens_assert( array &$results, string $label, bool $cond ) : void {
	$results[] = $cond;
	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI test output, not HTML.
	printf( "[%s] %s\n", $cond ? 'PASS' : 'FAIL', $label );
}

$ax_created = array( 'users' => array(), 'atts' => array() );

try {
	$ax_admins = get_users( array( 'role' => 'administrator', 'number' => 1, 'fields' => 'ids' ) );
	$mod       = $ax_admins ? (int) $ax_admins[0] : 1; // edit_others_posts -> moderator

	$existing = get_user_by( 'login', 'ax-sens-owner' );
	if ( $existing ) {
		wp_delete_user( $existing->ID, $mod );
	}
	$owner = (int) wp_insert_user( array( 'user_login' => 'ax-sens-owner', 'user_pass' => wp_generate_password(), 'role' => 'author' ) );
	$ax_created['users'] = array( $owner );

	$att  = (int) wp_insert_attachment( array( 'post_title' => 'ax-sens 1', 'post_status' => 'inherit', 'post_mime_type' => 'image/jpeg', 'post_author' => $owner ) );
	$att2 = (int) wp_insert_attachment( array( 'post_title' => 'ax-sens 2', 'post_status' => 'inherit', 'post_mime_type' => 'image/jpeg', 'post_author' => $owner ) );
	$ax_created['atts'] = array( $att, $att2 );

	// Capabilities
	ax_sens_assert( $ax_results, 'moderator (edit_others) has moderate_media_sensitivity', user_can( $mod, 'moderate_media_sensitivity' ) );
	ax_sens_assert( $ax_results, 'owner lacks moderate_media_sensitivity', ! user_can( $owner, 'moderate_media_sensitivity' ) );
	ax_sens_assert( $ax_results, 'owner (upload_files) has mark_own_media_sensitive', user_can( $owner, 'mark_own_media_sensitive' ) );

	// Owner self-mark + effective boolean + clear
	ax_sens_assert( $ax_results, 'owner self-marks', true === axismundi_media_set_sensitive_state( $att, 'self_marked', $owner ) );
	ax_sens_assert( $ax_results, 'effective sensitive + derived boolean 1, not locked', axismundi_media_is_sensitive( $att ) && '1' === get_post_meta( $att, '_ax_media_sensitive', true ) && ! axismundi_media_sensitive_locked( $att ) );
	ax_sens_assert( $ax_results, 'owner clears own self-mark', true === axismundi_media_set_sensitive_state( $att, 'none', $owner ) && ! axismundi_media_is_sensitive( $att ) && '0' === get_post_meta( $att, '_ax_media_sensitive', true ) );

	// Owner cannot set a moderator-only state
	ax_sens_assert( $ax_results, 'owner cannot set moderator_marked', is_wp_error( axismundi_media_set_sensitive_state( $att2, 'moderator_marked', $owner ) ) && ! axismundi_media_is_sensitive( $att2 ) );

	// Moderator lock — the Phase 2c gap, now resolved
	axismundi_media_set_sensitive_state( $att, 'moderator_marked', $mod );
	ax_sens_assert( $ax_results, 'moderator mark locks the item', axismundi_media_sensitive_locked( $att ) && axismundi_media_is_sensitive( $att ) );
	ax_sens_assert( $ax_results, 'owner CANNOT clear a moderator lock (Phase 2c gap resolved)', is_wp_error( axismundi_media_set_sensitive_state( $att, 'none', $owner ) ) && 'moderator_marked' === axismundi_media_sensitive_state( $att ) );

	// Automated flag: owner appeal-only, moderator can clear
	axismundi_media_set_sensitive_state( $att, 'automated_flagged', $mod );
	ax_sens_assert( $ax_results, 'owner cannot self-clear an automated flag', is_wp_error( axismundi_media_set_sensitive_state( $att, 'none', $owner ) ) && 'automated_flagged' === axismundi_media_sensitive_state( $att ) );
	ax_sens_assert( $ax_results, 'moderator clears an automated flag', true === axismundi_media_set_sensitive_state( $att, 'none', $mod ) && ! axismundi_media_is_sensitive( $att ) );

	// Confirmed lock
	axismundi_media_set_sensitive_state( $att, 'confirmed', $mod );
	ax_sens_assert( $ax_results, 'owner cannot clear a confirmed lock', is_wp_error( axismundi_media_set_sensitive_state( $att, 'none', $owner ) ) && 'confirmed' === axismundi_media_sensitive_state( $att ) );

	// Legacy fallback: bare _ax_media_sensitive=1 with no state reads as self_marked
	update_post_meta( $att2, '_ax_media_sensitive', '1' );
	delete_post_meta( $att2, '_ax_media_sensitive_state' );
	ax_sens_assert( $ax_results, 'legacy boolean reads as self_marked', 'self_marked' === axismundi_media_sensitive_state( $att2 ) && axismundi_media_is_sensitive( $att2 ) );

} finally {
	foreach ( $ax_created['atts'] as $ax_a ) {
		if ( $ax_a ) {
			wp_delete_attachment( (int) $ax_a, true );
		}
	}
	foreach ( $ax_created['users'] as $ax_u ) {
		if ( $ax_u ) {
			wp_delete_user( (int) $ax_u, $mod );
		}
	}
}

$ax_fail = 0;
foreach ( $ax_results as $ax_r ) {
	if ( ! $ax_r ) {
		++$ax_fail;
	}
}
// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI test output, not HTML.
printf( "\n== %d checks, %d failed ==\n", count( $ax_results ), $ax_fail );

if ( class_exists( 'WP_CLI' ) ) {
	WP_CLI::halt( $ax_fail > 0 ? 1 : 0 );
}
exit( $ax_fail > 0 ? 1 : 0 );
