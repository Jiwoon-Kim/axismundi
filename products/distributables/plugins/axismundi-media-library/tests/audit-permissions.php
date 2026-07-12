<?php
/**
 * Phase 2c — permission & IDOR regression audit (dev-only; excluded from the
 * distributed ZIP by scripts/build-zip.ps1).
 *
 * Self-contained: creates its own users / attachments / folders (no dependency on any
 * existing site ID) and cleans them up in `finally` on both success and failure.
 * Locks the folder ownership + IDOR contracts that are easiest to regress once
 * shared folders and the capability model land (Phase 5). The sensitive-authority
 * gap is reported as a KNOWN GAP (Phase 4), not a failure.
 *
 *   npx wp-env run cli wp eval-file \
 *     wp-content/plugins/axismundi-media-library/tests/audit-permissions.php
 *
 * Exit code: 0 = all contracts hold, 1 = a contract regressed.
 *
 * @package AxismundiMediaLibrary
 */

defined( 'ABSPATH' ) || exit( 1 );
require_once ABSPATH . 'wp-admin/includes/user.php'; // wp_delete_user

$ax_results = array();

/**
 * Record a contract assertion.
 *
 * @param array $results Results accumulator (by reference).
 * @param string $label  Contract description.
 * @param bool   $cond   Whether it holds.
 * @return void
 */
function ax_audit_assert( array &$results, string $label, bool $cond ) : void {
	$results[] = array( 'pass' => $cond, 'gap' => false );
	printf( "[%s] %s\n", $cond ? 'PASS' : 'FAIL', $label );
}

/**
 * Record a known, intentional gap (informational; never fails the run).
 *
 * @param array  $results Results accumulator (by reference).
 * @param string $label   Gap description.
 * @param string $detail  Where it is resolved.
 * @return void
 */
function ax_audit_gap( array &$results, string $label, string $detail ) : void {
	$results[] = array( 'pass' => true, 'gap' => true );
	printf( "[KNOWN GAP] %s — %s\n", $label, $detail );
}

$ax_prev_mode = get_option( 'ax_media_relationship_mode', 'core' );
$ax_admins    = get_users( array( 'role' => 'administrator', 'number' => 1, 'fields' => 'ids' ) );
$ax_admin     = $ax_admins ? (int) $ax_admins[0] : 1;
$ax_created   = array( 'users' => array(), 'atts' => array(), 'folders' => array() );

try {
	update_option( 'ax_media_relationship_mode', 'independent' );

	// Fresh ephemeral users — drop any leftovers from an aborted run first.
	foreach ( array( 'ax-test-alice', 'ax-test-bob' ) as $ax_login ) {
		$ax_existing = get_user_by( 'login', $ax_login );
		if ( $ax_existing ) {
			wp_delete_user( $ax_existing->ID, $ax_admin );
		}
	}
	$ax_alice = (int) wp_insert_user( array( 'user_login' => 'ax-test-alice', 'user_pass' => wp_generate_password(), 'role' => 'author' ) );
	$ax_bob   = (int) wp_insert_user( array( 'user_login' => 'ax-test-bob', 'user_pass' => wp_generate_password(), 'role' => 'author' ) );
	$ax_created['users'] = array( $ax_alice, $ax_bob );

	$ax_att_a = (int) wp_insert_attachment( array( 'post_title' => 'ax-test alice', 'post_status' => 'inherit', 'post_mime_type' => 'image/jpeg', 'post_author' => $ax_alice ) );
	$ax_att_b = (int) wp_insert_attachment( array( 'post_title' => 'ax-test bob', 'post_status' => 'inherit', 'post_mime_type' => 'image/jpeg', 'post_author' => $ax_bob ) );
	$ax_created['atts'] = array( $ax_att_a, $ax_att_b );

	wp_set_current_user( $ax_alice );
	$ax_f_a = axismundi_media_create_folder( 'AX Test Alice' );
	wp_set_current_user( $ax_bob );
	$ax_f_b = axismundi_media_create_folder( 'AX Test Bob' );
	foreach ( array( $ax_f_a, $ax_f_b ) as $ax_f ) {
		if ( ! is_wp_error( $ax_f ) ) {
			$ax_created['folders'][] = (int) $ax_f;
		}
	}
	wp_set_current_user( $ax_alice );

	ax_audit_assert( $ax_results, 'Fixtures created (users, attachments, folders)', ! is_wp_error( $ax_f_a ) && ! is_wp_error( $ax_f_b ) && $ax_alice > 0 && $ax_bob > 0 );

	if ( ! is_wp_error( $ax_f_a ) && ! is_wp_error( $ax_f_b ) ) {
		$ax_f_a = (int) $ax_f_a;
		$ax_f_b = (int) $ax_f_b;

		$r = axismundi_media_move_attachments( array( $ax_att_a ), $ax_f_a, $ax_alice );
		ax_audit_assert( $ax_results, 'Alice moves her own attachment', ! is_wp_error( $r ) && 1 === count( $r['moved'] ) );

		$r = axismundi_media_move_attachments( array( $ax_att_b ), $ax_f_a, $ax_alice );
		ax_audit_assert( $ax_results, "Alice cannot move Bob's attachment (attachment IDOR)", ! is_wp_error( $r ) && 0 === count( $r['moved'] ) && 1 === count( $r['denied'] ) );

		$r = axismundi_media_move_attachments( array( $ax_att_a ), $ax_f_b, $ax_alice );
		ax_audit_assert( $ax_results, "Alice cannot move into Bob's folder (folder IDOR)", is_wp_error( $r ) );

		ax_audit_assert( $ax_results, "Alice cannot manage Bob's folder", ! axismundi_media_can_manage_folder( $ax_f_b, $ax_alice ) );

		$ax_af = array_map( 'intval', wp_list_pluck( axismundi_media_user_folders( $ax_alice ), 'id' ) );
		ax_audit_assert( $ax_results, 'Alice folder list is owner-isolated', in_array( $ax_f_a, $ax_af, true ) && ! in_array( $ax_f_b, $ax_af, true ) );

		ax_audit_assert( $ax_results, 'Admin (edit_others_posts) can audit/manage any folder', axismundi_media_can_manage_folder( $ax_f_b, $ax_admin ) );
		$r = axismundi_media_move_attachments( array( $ax_att_b ), $ax_f_b, $ax_admin );
		ax_audit_assert( $ax_results, "Admin can move another user's attachment", ! is_wp_error( $r ) && 1 === count( $r['moved'] ) );

		// Admin marks Alice's media sensitive; today's boolean model lets the owner
		// clear it (owner has edit_post). Recorded as the Phase 4 authority gap.
		update_post_meta( $ax_att_a, '_ax_media_sensitive', '1' );
		if ( user_can( $ax_alice, 'edit_post', $ax_att_a ) ) {
			ax_audit_gap( $ax_results, 'Owner can clear an admin-set sensitive flag', 'boolean model; Phase 4 sensitive authority (state+set_by+locked) — SECURITY.md §2.4' );
		} else {
			ax_audit_assert( $ax_results, 'Owner cannot clear an admin-set sensitive flag', true );
		}
	}
} finally {
	wp_set_current_user( $ax_admin > 0 ? $ax_admin : 1 );
	foreach ( $ax_created['folders'] as $ax_fid ) {
		axismundi_media_delete_folder( (int) $ax_fid, $ax_admin > 0 ? $ax_admin : 1 );
	}
	foreach ( $ax_created['atts'] as $ax_aid ) {
		wp_delete_attachment( (int) $ax_aid, true );
	}
	foreach ( $ax_created['users'] as $ax_uid ) {
		wp_delete_user( (int) $ax_uid, $ax_admin > 0 ? $ax_admin : 1 );
	}
	update_option( 'ax_media_relationship_mode', $ax_prev_mode );
}

$ax_fail = 0;
$ax_gaps = 0;
foreach ( $ax_results as $ax_r ) {
	if ( ! $ax_r['pass'] ) {
		++$ax_fail;
	}
	if ( $ax_r['gap'] ) {
		++$ax_gaps;
	}
}
printf( "\n== %d checks, %d failed, %d known gap(s) ==\n", count( $ax_results ), $ax_fail, $ax_gaps );

if ( class_exists( 'WP_CLI' ) ) {
	WP_CLI::halt( $ax_fail > 0 ? 1 : 0 );
}
exit( $ax_fail > 0 ? 1 : 0 );
