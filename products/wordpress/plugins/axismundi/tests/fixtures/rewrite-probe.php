<?php
/** Temporary mu-plugin for the HTTP rewrite audit; never load in production. */
defined( 'ABSPATH' ) || exit;
add_action( 'generate_rewrite_rules', static function () : void {
	update_option( '_axismundi_route_audit_flushes', (int) get_option( '_axismundi_route_audit_flushes', 0 ) + 1, false );
} );
