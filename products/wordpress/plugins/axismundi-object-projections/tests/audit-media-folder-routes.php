<?php
/** Retired AP folder rewrites do not affect unrelated routes. Dev-only. */
defined( 'ABSPATH' ) || exit( 1 );
$rules = get_option( 'rewrite_rules' );
$marker = get_option( 'ax_op_media_folder_retired', false );
try {
	delete_option( 'ax_op_media_folder_retired' );
	update_option( 'rewrite_rules', array( '^media/folder/([0-9a-fA-F-]{36})/?$' => 'index.php?ax_op_media_folder=$matches[1]', '^media/folder/([0-9a-fA-F-]{36})/page/([0-9]+)/?$' => 'index.php?ax_op_media_folder=$matches[1]&ax_op_media_folder_page=$matches[2]', 'unrelated' => 'index.php?fixture=1' ) );
	axismundi_op_retire_media_folder_rewrites();
	if ( array( 'unrelated' => 'index.php?fixture=1' ) !== get_option( 'rewrite_rules' ) ) { throw new RuntimeException( 'Only obsolete AP rules may be removed.' ); }
	axismundi_op_retire_media_folder_rewrites();
	if ( array( 'unrelated' => 'index.php?fixture=1' ) !== get_option( 'rewrite_rules' ) ) { throw new RuntimeException( 'Replay must preserve unrelated rules.' ); }
	if ( class_exists( 'Axismundi_OP_Media_Folder_Collection' ) || function_exists( 'axismundi_op_media_folder_collection_transform' ) ) { throw new RuntimeException( 'Folder AP transformer must be absent.' ); }
	$vars = apply_filters( 'query_vars', array() );
	if ( in_array( 'ax_op_media_folder', $vars, true ) || in_array( 'ax_op_media_folder_page', $vars, true ) ) { throw new RuntimeException( 'Retired query vars must not be accepted.' ); }
} finally {
	update_option( 'rewrite_rules', $rules );
	if ( false === $marker ) { delete_option( 'ax_op_media_folder_retired' ); } else { update_option( 'ax_op_media_folder_retired', $marker ); }
}
echo "PASS: 4 retired route checks\n";
