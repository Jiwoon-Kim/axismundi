<?php
/** Dependency proof: wp --skip-plugins --skip-themes eval-file this-file. */
defined( 'ABSPATH' ) || exit( 1 );
if ( function_exists( 'axismundi_ntf_ready' ) || function_exists( 'axismundi_pwa_capability' ) || function_exists( 'axismundi_contacts_cards_table' ) ) {
	throw new RuntimeException( 'Run with --skip-plugins --skip-themes.' );
}
require_once WP_PLUGIN_DIR . '/axismundi-actors/axismundi-actors.php';
require_once dirname( __DIR__ ) . '/axismundi-jmap.php';
require __DIR__ . '/audit-todo-poc.php';
