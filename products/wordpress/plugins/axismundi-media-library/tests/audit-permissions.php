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
	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI test output, not HTML.
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
	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI test output, not HTML.
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
	$ax_att_c = (int) wp_insert_attachment( array( 'post_title' => 'ax-test alice bulk', 'post_status' => 'inherit', 'post_mime_type' => 'image/jpeg', 'post_author' => $ax_alice ) );
	$ax_att_b = (int) wp_insert_attachment( array( 'post_title' => 'ax-test bob', 'post_status' => 'inherit', 'post_mime_type' => 'image/jpeg', 'post_author' => $ax_bob ) );
	$ax_created['atts'] = array( $ax_att_a, $ax_att_b, $ax_att_c );

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
		$ax_f_protected = axismundi_media_create_folder( 'AX Test protected parent', 0, $ax_alice );
		$ax_f_inherited = ! is_wp_error( $ax_f_protected ) ? axismundi_media_create_folder( 'AX Test inherited child', (int) $ax_f_protected, $ax_alice ) : $ax_f_protected;
		if ( ! is_wp_error( $ax_f_protected ) ) {
			$ax_f_protected = (int) $ax_f_protected;
			$ax_created['folders'][] = $ax_f_protected;
			axismundi_media_set_folder_tier( $ax_f_protected, 'private', $ax_alice );
			axismundi_media_set_folder_access( $ax_f_protected, 'password', 'audit-parent-password', $ax_alice );
		}
		if ( ! is_wp_error( $ax_f_inherited ) ) {
			$ax_f_inherited = (int) $ax_f_inherited;
			$ax_created['folders'][] = $ax_f_inherited;
		}
		$ax_policy_block = ! is_wp_error( $ax_f_inherited ) ? axismundi_media_move_folder( $ax_f_inherited, 0, $ax_alice ) : new WP_Error();
		$ax_policy_confirmed = ! is_wp_error( $ax_f_inherited ) ? axismundi_media_move_folder( $ax_f_inherited, 0, $ax_alice, true ) : new WP_Error();
		ax_audit_assert( $ax_results, 'Moving an inherited password/private folder to top level requires explicit confirmation', is_wp_error( $ax_policy_block ) && 'ax_media_folder_policy_confirmation' === $ax_policy_block->get_error_code() && 409 === (int) $ax_policy_block->get_error_data()['status'] && ! is_wp_error( $ax_policy_confirmed ) && ! axismundi_media_folder_effective_gate( $ax_f_inherited ) && 0 === axismundi_media_folder_effective_tier_rank( $ax_f_inherited ) );
		$ax_f_rest_protected = axismundi_media_create_folder( 'AX Test REST protected parent', 0, $ax_alice );
		$ax_f_rest_inherited = ! is_wp_error( $ax_f_rest_protected ) ? axismundi_media_create_folder( 'AX Test REST inherited child', (int) $ax_f_rest_protected, $ax_alice ) : $ax_f_rest_protected;
		if ( ! is_wp_error( $ax_f_rest_protected ) ) {
			$ax_f_rest_protected = (int) $ax_f_rest_protected;
			$ax_created['folders'][] = $ax_f_rest_protected;
			axismundi_media_set_folder_access( $ax_f_rest_protected, 'password', 'audit-rest-parent-password', $ax_alice );
		}
		if ( ! is_wp_error( $ax_f_rest_inherited ) ) {
			$ax_f_rest_inherited = (int) $ax_f_rest_inherited;
			$ax_created['folders'][] = $ax_f_rest_inherited;
			$ax_rest_policy_request = new WP_REST_Request( 'POST', '/' . AXISMUNDI_MEDIA_REST_NS . '/folders/' . $ax_f_rest_inherited );
			$ax_rest_policy_request->set_param( 'parent', 0 );
			$ax_rest_policy_response = rest_do_request( $ax_rest_policy_request );
			$ax_rest_policy_confirm = new WP_REST_Request( 'POST', '/' . AXISMUNDI_MEDIA_REST_NS . '/folders/' . $ax_f_rest_inherited );
			$ax_rest_policy_confirm->set_param( 'parent', 0 );
			$ax_rest_policy_confirm->set_param( 'confirm_policy_change', true );
			$ax_rest_policy_confirm_response = rest_do_request( $ax_rest_policy_confirm );
			ax_audit_assert( $ax_results, 'Folder REST requires and honors explicit confirmation before weakening inherited protection', 409 === $ax_rest_policy_response->get_status() && 'ax_media_folder_policy_confirmation' === ( $ax_rest_policy_response->get_data()['code'] ?? '' ) && 200 === $ax_rest_policy_confirm_response->get_status() && ! axismundi_media_folder_effective_gate( $ax_f_rest_inherited ) );
		}
		$ax_request_folder_present = array_key_exists( 'ax_media_folder', $_REQUEST );
		$ax_request_folder         = $ax_request_folder_present ? $_REQUEST['ax_media_folder'] : null;
		$_REQUEST['ax_media_folder'] = (string) $ax_f_a;
		$ax_att_upload = (int) wp_insert_attachment( array( 'post_title' => 'ax-test selected-folder upload', 'post_status' => 'inherit', 'post_mime_type' => 'image/jpeg', 'post_author' => $ax_alice ) );
		$ax_created['atts'][] = $ax_att_upload;
		if ( $ax_request_folder_present ) {
			$_REQUEST['ax_media_folder'] = $ax_request_folder;
		} else {
			unset( $_REQUEST['ax_media_folder'] );
		}
		ax_audit_assert( $ax_results, 'An upload request with a selected folder is assigned to that folder', $ax_f_a === axismundi_media_attachment_folder( $ax_att_upload ) );
		$folder_counts_request  = new WP_REST_Request( 'GET', '/' . AXISMUNDI_MEDIA_REST_NS . '/folders/counts' );
		$folder_counts_response = rest_do_request( $folder_counts_request );
		$folder_counts_data     = $folder_counts_response->get_data();
		ax_audit_assert( $ax_results, 'Folder counts REST reports the selected-folder upload immediately', 200 === $folder_counts_response->get_status() && 1 === ( $folder_counts_data['folders'][ $ax_f_a ] ?? null ) );
		$folder_create_request = new WP_REST_Request( 'POST', '/' . AXISMUNDI_MEDIA_REST_NS . '/folders' );
		$folder_create_request->set_param( 'name', 'AX Test REST child' );
		$folder_create_request->set_param( 'parent', $ax_f_a );
		$folder_create_response = rest_do_request( $folder_create_request );
		$folder_create_data     = $folder_create_response->get_data();
		$ax_f_rest_child        = (int) ( $folder_create_data['id'] ?? 0 );
		if ( $ax_f_rest_child > 0 ) {
			$ax_created['folders'][] = $ax_f_rest_child;
		}
		$folder_create_tree = array_column( (array) ( $folder_create_data['folders'] ?? array() ), null, 'id' );
		ax_audit_assert( $ax_results, 'Folder REST create returns the browser tree for a new nested folder', 201 === $folder_create_response->get_status() && $ax_f_a === (int) get_term( $ax_f_rest_child, AXISMUNDI_MEDIA_FOLDER_TAX )->parent && isset( $folder_create_tree[ $ax_f_rest_child ] ) && false === (bool) $folder_create_tree[ $ax_f_rest_child ]['protected'] );
		$folder_rename_request = new WP_REST_Request( 'POST', '/' . AXISMUNDI_MEDIA_REST_NS . '/folders/' . $ax_f_rest_child );
		$folder_rename_request->set_param( 'name', 'AX Test REST renamed' );
		$folder_rename_response = rest_do_request( $folder_rename_request );
		$folder_rename_data     = $folder_rename_response->get_data();
		$folder_rename_tree     = array_column( (array) ( $folder_rename_data['folders'] ?? array() ), null, 'id' );
		ax_audit_assert( $ax_results, 'Folder REST rename returns the renamed browser-tree row', 200 === $folder_rename_response->get_status() && 'AX Test REST renamed' === ( $folder_rename_tree[ $ax_f_rest_child ]['name'] ?? null ) );

		$r = axismundi_media_move_attachments( array( $ax_att_a ), $ax_f_a, $ax_alice );
		ax_audit_assert( $ax_results, 'Alice moves her own attachment', ! is_wp_error( $r ) && 1 === count( $r['moved'] ) );

		wp_set_current_user( $ax_alice );
		$saved = axismundi_media_attachment_save(
			array( 'ID' => $ax_att_a ),
			array( 'ax_media_folder' => '0' )
		);
		ax_audit_assert( $ax_results, 'Attachment Details saves Location changes', $ax_att_a === (int) $saved['ID'] && 0 === axismundi_media_attachment_folder( $ax_att_a ) );
		ax_audit_assert( $ax_results, 'Grid Location service saves the selected folder', true === axismundi_media_save_attachment_location( $ax_att_a, $ax_f_a, $ax_alice ) && $ax_f_a === axismundi_media_attachment_folder( $ax_att_a ) );
		axismundi_media_set_attachment_folder( $ax_att_a, 0 );
		$drag_counts_before = axismundi_media_user_folder_browser_counts( $ax_alice );
		$drag_request = new WP_REST_Request( 'POST', '/' . AXISMUNDI_MEDIA_REST_NS . '/folders/move' );
		$drag_request->set_param( 'attachments', array( $ax_att_a, $ax_att_c ) );
		$drag_request->set_param( 'folder', $ax_f_a );
		$drag_response = rest_do_request( $drag_request );
		$drag_data     = $drag_response->get_data();
		$drag_moved    = array_map( 'intval', (array) ( $drag_data['moved'] ?? array() ) );
		sort( $drag_moved );
		$drag_expected = array( $ax_att_a, $ax_att_c );
		sort( $drag_expected );
		ax_audit_assert( $ax_results, 'Bulk drag-and-drop returns both moved attachments and refreshed source/target counts', 200 === $drag_response->get_status() && $drag_expected === $drag_moved && $ax_f_a === axismundi_media_attachment_folder( $ax_att_a ) && $ax_f_a === axismundi_media_attachment_folder( $ax_att_c ) && ( $drag_counts_before['unfiled'] - 2 ) === ( $drag_data['counts']['unfiled'] ?? null ) && ( $drag_counts_before['folders'][ $ax_f_a ] + 2 ) === ( $drag_data['counts']['folders'][ $ax_f_a ] ?? null ) );
		$ax_f_child = axismundi_media_create_folder( 'AX Test Alice child', $ax_f_a, $ax_alice );
		if ( ! is_wp_error( $ax_f_child ) ) {
			$ax_f_child = (int) $ax_f_child;
			$ax_created['folders'][] = $ax_f_child;
			axismundi_media_set_folder_access( $ax_f_child, 'password', 'audit-password', $ax_alice );
			axismundi_media_move_attachments( array( $ax_att_c ), $ax_f_child, $ax_alice );
			$direct_counts = axismundi_media_user_folder_browser_counts( $ax_alice );
			ax_audit_assert( $ax_results, 'Folder sidebar shows All Media and direct folder counts, not descendant totals', 3 === $direct_counts['all'] && 0 === $direct_counts['unfiled'] && 2 === ( $direct_counts['folders'][ $ax_f_a ] ?? null ) && 1 === ( $direct_counts['folders'][ $ax_f_child ] ?? null ) && 3 === axismundi_media_folder_recursive_count( $ax_f_a ) );
			$ax_request_query_present = array_key_exists( 'query', $_REQUEST );
			$ax_request_query         = $ax_request_query_present ? $_REQUEST['query'] : null;
			$_REQUEST['query'] = array( 'ax_media_folder' => 'folder-' . $ax_f_a );
			$direct_folder_ids = get_posts(
				axismundi_media_modal_folder_query(
					array(
						'post_type'      => 'attachment',
						'post_status'    => 'inherit',
						'fields'         => 'ids',
						'posts_per_page' => -1,
					)
				)
			);
			if ( $ax_request_query_present ) {
				$_REQUEST['query'] = $ax_request_query;
			} else {
				unset( $_REQUEST['query'] );
			}
			ax_audit_assert( $ax_results, 'A parent folder query excludes attachments assigned only to its child folder', in_array( $ax_att_a, $direct_folder_ids, true ) && ! in_array( $ax_att_c, $direct_folder_ids, true ) );
			$folder_move_request = new WP_REST_Request( 'POST', '/' . AXISMUNDI_MEDIA_REST_NS . '/folders/' . $ax_f_child );
			$folder_move_request->set_param( 'parent', 0 );
			$folder_move_response = rest_do_request( $folder_move_request );
			$folder_move_data     = $folder_move_response->get_data();
			$folder_move_tree     = array_column( (array) ( $folder_move_data['folders'] ?? array() ), null, 'id' );
			$alice_root           = axismundi_media_user_root( $ax_alice, false );
			ax_audit_assert( $ax_results, 'Folder REST move pulls a nested folder back to its owner top level, refreshes the tree, and keeps its password gate visible', 200 === $folder_move_response->get_status() && $alice_root === (int) get_term( $ax_f_child, AXISMUNDI_MEDIA_FOLDER_TAX )->parent && ! empty( $folder_move_tree[ $ax_f_child ]['protected'] ) && 'password' === axismundi_media_folder_access( $ax_f_child ) && axismundi_media_folder_effective_gate( $ax_f_child ) );
			axismundi_media_move_folder( $ax_f_child, $ax_f_a, $ax_alice );
			ax_audit_assert( $ax_results, 'Folder move rejects nesting a folder inside its own descendant', is_wp_error( axismundi_media_move_folder( $ax_f_a, $ax_f_child, $ax_alice ) ) );
		} else {
			ax_audit_assert( $ax_results, 'Folder sidebar shows All Media and direct folder counts, not descendant totals', false );
		}
		axismundi_media_move_attachments( array( $ax_att_a ), $ax_f_a, $ax_alice );

		$r = axismundi_media_move_attachments( array( $ax_att_b ), $ax_f_a, $ax_alice );
		ax_audit_assert( $ax_results, "Alice cannot move Bob's attachment (attachment IDOR)", ! is_wp_error( $r ) && 0 === count( $r['moved'] ) && 1 === count( $r['denied'] ) );

		$r = axismundi_media_move_attachments( array( $ax_att_b ), $ax_f_a, $ax_admin );
		ax_audit_assert( $ax_results, 'Admin cannot create a cross-owner folder relation', ! is_wp_error( $r ) && 0 === count( $r['moved'] ) && 1 === count( $r['denied'] ) );

		$r = axismundi_media_move_attachments( array( $ax_att_a ), $ax_f_b, $ax_alice );
		ax_audit_assert( $ax_results, "Alice cannot move into Bob's folder (folder IDOR)", is_wp_error( $r ) );
		ax_audit_assert( $ax_results, 'An administrator cannot merge different owners\' folder trees', is_wp_error( axismundi_media_move_folder( $ax_f_a, $ax_f_b, $ax_admin ) ) );

		ax_audit_assert( $ax_results, "Alice cannot manage Bob's folder", ! axismundi_media_can_manage_folder( $ax_f_b, $ax_alice ) );

		$ax_af = array_map( 'intval', wp_list_pluck( axismundi_media_user_folders( $ax_alice ), 'id' ) );
		ax_audit_assert( $ax_results, 'Alice folder list is owner-isolated', in_array( $ax_f_a, $ax_af, true ) && ! in_array( $ax_f_b, $ax_af, true ) );

		$_REQUEST['query'] = array( 'ax_media_folder' => 'unfiled' );
		$unfiled_args      = axismundi_media_modal_folder_query( array() );
		ax_audit_assert( $ax_results, 'Unfiled media-modal query is scoped to the current uploader', $ax_alice === (int) ( $unfiled_args['author'] ?? 0 ) );
		unset( $_REQUEST['query'] );

		ax_audit_assert( $ax_results, 'Admin (edit_others_posts) can audit/manage any folder', axismundi_media_can_manage_folder( $ax_f_b, $ax_admin ) );
		$r = axismundi_media_move_attachments( array( $ax_att_b ), $ax_f_b, $ax_admin );
		ax_audit_assert( $ax_results, "Admin can move another user's attachment", ! is_wp_error( $r ) && 1 === count( $r['moved'] ) );

		wp_set_object_terms( $ax_att_b, array( $ax_f_a ), AXISMUNDI_MEDIA_FOLDER_TAX, false );
		delete_option( 'ax_media_folder_owner_repair_version' );
		axismundi_media_repair_folder_owner_relations();
		ax_audit_assert( $ax_results, 'Repair normalizes a legacy cross-owner relation to Unfiled', 0 === axismundi_media_attachment_folder( $ax_att_b ) );

		// Phase 4a resolved the earlier gap: a moderator (admin) sensitivity lock cannot
		// be cleared by the owner.
		axismundi_media_set_sensitive_state( $ax_att_a, 'moderator_marked', $ax_admin );
		ax_audit_assert( $ax_results, 'Owner cannot clear a moderator-set sensitive flag (Phase 4a)', is_wp_error( axismundi_media_set_sensitive_state( $ax_att_a, 'none', $ax_alice ) ) && axismundi_media_is_sensitive( $ax_att_a ) );
		axismundi_media_set_sensitive_state( $ax_att_a, 'none', $ax_admin );
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
// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI test output, not HTML.
printf( "\n== %d checks, %d failed, %d known gap(s) ==\n", count( $ax_results ), $ax_fail, $ax_gaps );

if ( class_exists( 'WP_CLI' ) ) {
	WP_CLI::halt( $ax_fail > 0 ? 1 : 0 );
}
exit( $ax_fail > 0 ? 1 : 0 );
