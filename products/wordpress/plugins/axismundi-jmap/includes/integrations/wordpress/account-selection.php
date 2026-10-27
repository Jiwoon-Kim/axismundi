<?php
/** Compatibility PHP entry points. Browser UI and hooks are owned by Actors. */
namespace Axismundi\Jmap;

defined( 'ABSPATH' ) || exit;

function account_selection_ui_enabled() : bool { return \Axismundi\Actors\Selection\account_selection_ui_enabled(); }
function account_selection_page_url() : string { return \Axismundi\Actors\Selection\account_selection_page_url(); }
function account_selection_view() { return \Axismundi\Actors\Selection\account_selection_view(); }
function apply_account_selection( string $operation, string $account_id, string $nonce ) { return \Axismundi\Actors\Selection\apply_account_selection( $operation, $account_id, $nonce ); }
function handle_account_selection_post() : void { \Axismundi\Actors\Selection\handle_account_selection_post(); }
function render_account_selection_page() : void { \Axismundi\Actors\Selection\render_account_selection_page(); }
function account_selection_avatar( string $account_id ) : string { return \Axismundi\Actors\Selection\account_selection_avatar( $account_id ); }
function register_account_selection_ui() : void { \Axismundi\Actors\Selection\register_account_selection_ui(); }
function account_selection_styles() : void { \Axismundi\Actors\Selection\account_selection_styles(); }
