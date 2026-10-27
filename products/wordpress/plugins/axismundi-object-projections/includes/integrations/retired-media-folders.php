<?php
/** Remove obsolete AP rewrites without changing taxonomy archives or Atom/MRSS. */
defined( 'ABSPATH' ) || exit;

function axismundi_op_retire_media_folder_rewrites() : void {
	if ( '1' === get_option( 'ax_op_media_folder_retired' ) ) { return; }
	$rules = get_option( 'rewrite_rules' );
	if ( is_array( $rules ) ) {
		unset( $rules['^media/folder/([0-9a-fA-F-]{36})/page/([0-9]+)/?$'], $rules['^media/folder/([0-9a-fA-F-]{36})/?$'] );
		update_option( 'rewrite_rules', $rules );
	}
	delete_option( 'ax_op_media_folder_rewrite_version' );
	delete_transient( 'ax_op_media_folder_rewrite_retry' );
	update_option( 'ax_op_media_folder_retired', '1', false );
}
add_action( 'init', 'axismundi_op_retire_media_folder_rewrites', 12 );
