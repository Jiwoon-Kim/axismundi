<?php
/**
 * FileBird CSV importer regression fixture (dev-only; dist-excluded).
 *
 * Self-contained; `finally` cleanup; exit 0/1. Locks parsing, hierarchy,
 * one-folder preservation, resumable batches, and idempotent reruns.
 *
 * @package AxismundiMediaLibrary
 */

defined( 'ABSPATH' ) || exit( 1 );

$ax_fbi_results = array();

/**
 * @param array  $results Accumulator.
 * @param string $label Contract.
 * @param bool   $condition Holds.
 * @return void
 */
function ax_fbi_assert( array &$results, string $label, bool $condition ) : void {
	$results[] = $condition;
	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI test output, not HTML.
	printf( "[%s] %s\n", $condition ? 'PASS' : 'FAIL', $label );
}

/**
 * @param string $contents CSV contents.
 * @return string Temporary path.
 */
function ax_fbi_csv( string $contents ) : string {
	$path = wp_tempnam( 'ax-filebird.csv' );
	file_put_contents( $path, $contents ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- Dev-only fixture temp file.
	return $path;
}

$ax_fbi_created = array(
	'attachments' => array(),
	'terms'       => array(),
	'users'       => array(),
	'files'       => array(),
);

try {
	$admin = (int) wp_insert_user(
		array(
			'user_login' => 'ax_fbi_admin_' . wp_rand( 1000, 9999 ),
			'user_pass'  => wp_generate_password(),
			'role'       => 'administrator',
		)
	);
	$ax_fbi_created['users'][] = $admin;
	wp_set_current_user( $admin );

	foreach ( array( 'Root item', 'Child item', 'Existing item' ) as $title ) {
		$attachment_id = (int) wp_insert_attachment(
			array(
				'post_title'     => $title,
				'post_status'    => 'inherit',
				'post_mime_type' => 'image/jpeg',
				'post_author'    => $admin,
			)
		);
		$ax_fbi_created['attachments'][] = $attachment_id;
	}
	list( $root_item, $child_item, $existing_item ) = $ax_fbi_created['attachments'];

	$existing_folder = axismundi_media_create_folder( 'Existing Axismundi', 0, $admin );
	if ( ! is_wp_error( $existing_folder ) ) {
		$existing_folder                  = (int) $existing_folder;
		$ax_fbi_created['terms'][]         = $existing_folder;
		axismundi_media_set_attachment_folder( $existing_item, $existing_folder );
	}

	$missing_id = 99999991;
	$csv        = "id,name,parent,type,ord,created_by,attachment_ids\n";
	$csv       .= "10,Imported Root,0,0,0,123,{$root_item}|{$existing_item}|{$missing_id}\n";
	$csv       .= "11,Imported Child,10,0,0,123,{$child_item}\n";
	$path       = ax_fbi_csv( $csv );
	$ax_fbi_created['files'][] = $path;
	$parsed = axismundi_media_parse_filebird_csv( $path );

	ax_fbi_assert( $ax_fbi_results, 'valid FileBird CSV parses two folders and four assignments', ! is_wp_error( $parsed ) && 2 === count( $parsed['folders'] ) && 4 === count( $parsed['assignments'] ) );
	ax_fbi_assert( $ax_fbi_results, 'parent folder is ordered before its child', ! is_wp_error( $parsed ) && array( 10, 11 ) === $parsed['folder_order'] );

	$job = axismundi_media_filebird_create_job( $parsed, $admin );
	$guard = 0;
	while ( 'done' !== $job['status'] && $guard < 20 ) {
		axismundi_media_filebird_process_job( $job, 1, 1 );
		++$guard;
	}
	$root_term  = (int) ( $job['folder_map'][10] ?? 0 );
	$child_term = (int) ( $job['folder_map'][11] ?? 0 );
	$ax_fbi_created['terms'][] = $child_term;
	$ax_fbi_created['terms'][] = $root_term;

	ax_fbi_assert( $ax_fbi_results, 'small batches resume until the job completes', 'done' === $job['status'] && $guard > 2 );
	$child = get_term( $child_term, AXISMUNDI_MEDIA_FOLDER_TAX );
	ax_fbi_assert( $ax_fbi_results, 'FileBird hierarchy is recreated under the imported parent', $child instanceof WP_Term && $root_term === (int) $child->parent );
	ax_fbi_assert( $ax_fbi_results, 'unfiled media is assigned to its imported folders', $root_term === axismundi_media_attachment_folder( $root_item ) && $child_term === axismundi_media_attachment_folder( $child_item ) );
	ax_fbi_assert( $ax_fbi_results, 'an existing Axismundi assignment is preserved', $existing_folder === axismundi_media_attachment_folder( $existing_item ) && 1 === $job['stats']['attachments_preserved'] );
	ax_fbi_assert( $ax_fbi_results, 'missing attachments are reported without stopping the import', 1 === $job['stats']['attachments_missing'] && 2 === $job['stats']['attachments_assigned'] );

	$rerun = axismundi_media_filebird_create_job( $parsed, $admin );
	while ( 'done' !== $rerun['status'] ) {
		axismundi_media_filebird_process_job( $rerun, 50, 250 );
	}
	ax_fbi_assert( $ax_fbi_results, 'rerunning the same CSV reuses source-tagged folders', 2 === $rerun['stats']['folders_reused'] && 0 === $rerun['stats']['folders_created'] );
	ax_fbi_assert( $ax_fbi_results, 'rerun recognizes target assignments and still preserves unrelated ones', 2 === $rerun['stats']['attachments_already'] && 1 === $rerun['stats']['attachments_preserved'] );

	$duplicate_path = ax_fbi_csv(
		"id,name,parent,attachment_ids\n20,One,0,{$root_item}\n21,Two,0,{$root_item}\n"
	);
	$ax_fbi_created['files'][] = $duplicate_path;
	$duplicate = axismundi_media_parse_filebird_csv( $duplicate_path );
	ax_fbi_assert( $ax_fbi_results, 'cross-folder duplicate attachment IDs are reported and first assignment wins', ! is_wp_error( $duplicate ) && 1 === $duplicate['duplicate_assignments'] && 1 === count( $duplicate['assignments'] ) );

	$cycle_path = ax_fbi_csv( "id,name,parent,attachment_ids\n30,A,31,\n31,B,30,\n" );
	$ax_fbi_created['files'][] = $cycle_path;
	$cycle = axismundi_media_parse_filebird_csv( $cycle_path );
	ax_fbi_assert( $ax_fbi_results, 'cyclic folder hierarchies are rejected before mutation', is_wp_error( $cycle ) && 'ax_media_filebird_cycle' === $cycle->get_error_code() );
} finally {
	foreach ( $ax_fbi_created['attachments'] as $attachment_id ) {
		wp_delete_attachment( (int) $attachment_id, true );
	}
	foreach ( array_unique( array_filter( $ax_fbi_created['terms'] ) ) as $term_id ) {
		wp_delete_term( (int) $term_id, AXISMUNDI_MEDIA_FOLDER_TAX );
	}
	foreach ( $ax_fbi_created['users'] as $user_id ) {
		$root = axismundi_media_user_root( (int) $user_id, false );
		if ( $root > 0 ) {
			wp_delete_term( $root, AXISMUNDI_MEDIA_FOLDER_TAX );
		}
		wp_delete_user( (int) $user_id );
	}
	foreach ( $ax_fbi_created['files'] as $path ) {
		if ( file_exists( $path ) ) {
			unlink( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink -- Dev-only fixture temp file cleanup.
		}
	}
}

$ax_fbi_failed = count( array_filter( $ax_fbi_results, static fn( $result ) => ! $result ) );
// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI test output, not HTML.
printf( "\n== %d checks, %d failed ==\n", count( $ax_fbi_results ), $ax_fbi_failed );

if ( class_exists( 'WP_CLI' ) ) {
	WP_CLI::halt( $ax_fbi_failed > 0 ? 1 : 0 );
}
exit( $ax_fbi_failed > 0 ? 1 : 0 );
