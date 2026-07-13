<?php
/**
 * Phase 4b — folder default license stamp + invariants (dev-only; dist-excluded).
 *
 * Self-contained; `finally` cleanup; exit 0/1. Locks the stamp contract:
 * stamp copies the folder default onto a new upload; nearest ancestor wins; no
 * default anywhere reads as all-rights-reserved; an attachment-set license is never
 * overwritten; and moving a stamped attachment never changes its license.
 *
 * @package AxismundiMediaLibrary
 */

defined( 'ABSPATH' ) || exit( 1 );

$ax_results = array();

/**
 * @param array  $results Accumulator.
 * @param string $label   Contract.
 * @param bool   $cond    Holds.
 * @return void
 */
function ax_folic_assert( array &$results, string $label, bool $cond ) : void {
	$results[] = $cond;
	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI test output, not HTML.
	printf( "[%s] %s\n", $cond ? 'PASS' : 'FAIL', $label );
}

/**
 * @return int New attachment ID.
 */
function ax_folic_att() : int {
	return (int) wp_insert_attachment( array( 'post_title' => 'Upload', 'post_status' => 'inherit', 'post_mime_type' => 'image/jpeg' ) );
}

$ax_created = array( 'atts' => array(), 'terms' => array(), 'users' => array() );

try {
	$admin = (int) wp_insert_user( array( 'user_login' => 'ax_folic_admin', 'user_pass' => wp_generate_password(), 'role' => 'administrator' ) );
	$ax_created['users'][] = $admin;
	wp_set_current_user( $admin );

	$parent = axismundi_media_create_folder( 'Rights Parent', 0, $admin );
	$child  = axismundi_media_create_folder( 'Rights Child', is_wp_error( $parent ) ? 0 : (int) $parent, $admin );
	$bare   = axismundi_media_create_folder( 'No Default', 0, $admin );
	foreach ( array( $parent, $child, $bare ) as $t ) {
		if ( ! is_wp_error( $t ) ) {
			$ax_created['terms'][] = (int) $t;
		}
	}
	$parent = (int) $parent;
	$child  = (int) $child;
	$bare   = (int) $bare;

	// Setter: valid code stores; invalid rejects; '' clears.
	$set_ok  = axismundi_media_set_folder_default_license( $parent, 'cc-by-sa', $admin );
	$set_bad = axismundi_media_set_folder_default_license( $parent, 'not-a-license', $admin );
	ax_folic_assert( $ax_results, 'setter stores a valid code and rejects an invalid one', true === $set_ok && is_wp_error( $set_bad ) && 'cc-by-sa' === (string) get_term_meta( $parent, AXISMUNDI_MEDIA_FOLDER_DEFAULT_LICENSE_META, true ) );

	// Nearest ancestor wins: child has no default -> resolves to parent's.
	ax_folic_assert( $ax_results, 'child with no default resolves to parent default', 'cc-by-sa' === axismundi_media_folder_default_license( $child ) );

	$a1 = ax_folic_att();
	$ax_created['atts'][] = $a1;
	axismundi_media_stamp_folder_default_license( $a1, $child );
	ax_folic_assert( $ax_results, 'upload into child is stamped with the ancestor default', 'cc-by-sa' === axismundi_media_license_code( $a1 ) );

	// Child's own default overrides the ancestor for its own uploads.
	axismundi_media_set_folder_default_license( $child, 'cc-by', $admin );
	$a2 = ax_folic_att();
	$ax_created['atts'][] = $a2;
	axismundi_media_stamp_folder_default_license( $a2, $child );
	ax_folic_assert( $ax_results, "child's own default overrides the ancestor", 'cc-by' === axismundi_media_license_code( $a2 ) );

	// No default anywhere -> nothing written -> reads as all-rights-reserved.
	$a3 = ax_folic_att();
	$ax_created['atts'][] = $a3;
	axismundi_media_stamp_folder_default_license( $a3, $bare );
	ax_folic_assert( $ax_results, 'no chain default writes nothing (reads all-rights-reserved)', '' === (string) get_post_meta( $a3, '_ax_media_license', true ) && 'all-rights-reserved' === axismundi_media_license_code( $a3 ) );

	// Contract 7: an attachment-set license is never overwritten.
	$a4 = ax_folic_att();
	$ax_created['atts'][] = $a4;
	update_post_meta( $a4, '_ax_media_license', 'cc0' );
	axismundi_media_stamp_folder_default_license( $a4, $child );
	ax_folic_assert( $ax_results, 'stamp never overwrites an attachment-set license', 'cc0' === axismundi_media_license_code( $a4 ) );

	// Contract 6: moving a stamped attachment never changes its license.
	axismundi_media_set_attachment_folder( $a1, $bare );
	ax_folic_assert( $ax_results, 'moving a stamped attachment keeps its license', 'cc-by-sa' === axismundi_media_license_code( $a1 ) );

	// Clearing the default removes the meta.
	axismundi_media_set_folder_default_license( $parent, '', $admin );
	ax_folic_assert( $ax_results, "clearing removes the folder default", '' === (string) get_term_meta( $parent, AXISMUNDI_MEDIA_FOLDER_DEFAULT_LICENSE_META, true ) );

} finally {
	foreach ( $ax_created['atts'] as $ax_a ) {
		if ( $ax_a ) {
			wp_delete_attachment( (int) $ax_a, true );
		}
	}
	foreach ( $ax_created['terms'] as $ax_t ) {
		if ( $ax_t ) {
			wp_delete_term( (int) $ax_t, AXISMUNDI_MEDIA_FOLDER_TAX );
		}
	}
	foreach ( $ax_created['users'] as $ax_u ) {
		if ( $ax_u ) {
			wp_delete_user( (int) $ax_u );
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
