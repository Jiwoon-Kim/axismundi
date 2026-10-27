<?php
/** Retire copied federation anchors, not User-owned folders or their feeds. */
defined( 'ABSPATH' ) || exit;

function axismundi_media_retire_folder_identity_meta() : bool {
	global $wpdb;
	$ids = $wpdb->get_col( $wpdb->prepare( "SELECT DISTINCT term_id FROM %i WHERE meta_key = %s", $wpdb->termmeta, '_ax_media_folder_uuid' ) );
	if ( $wpdb->last_error ) { return false; }
	foreach ( $ids as $id ) {
		if ( ! delete_term_meta( (int) $id, '_ax_media_folder_uuid' ) ) { return false; }
	}
	update_option( 'ax_media_folder_identity_retired', '1', false );
	return true;
}

function axismundi_media_maybe_retire_folder_identity_meta() : void {
	if ( '1' !== get_option( 'ax_media_folder_identity_retired' ) ) {
		axismundi_media_retire_folder_identity_meta();
	}
}
add_action( 'init', 'axismundi_media_maybe_retire_folder_identity_meta', 20 );
