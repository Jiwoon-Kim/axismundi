<?php
/**
 * Playground CLI check, appended only to the --dev blueprint.
 *
 * Writes what the setup step produced to /vqa-report/result.json, a directory
 * the CLI mounts from the host: which parts resolve, and what the VQA page
 * renders - its triggers and the surfaces queued for the end of the page.
 *
 * @package AxismundiDialogs
 */

require_once '/wordpress/wp-load.php';

$slugs  = array( 'dialog-basic', 'dialog-basic-icon', 'dialog-list', 'dialog-dismiss-none', 'dialog-full-screen', 'sheets-bottom', 'sheets-side', 'sheets-side-start', 'sheets-side-standard', 'sheets-side-standard-move' );
$report = array(
	'theme'   => wp_get_theme()->get_stylesheet() . ' ' . wp_get_theme()->get( 'Version' ),
	'plugins' => array(),
	'parts'   => array(),
);

foreach ( array( 'axismundi-dialogs/axismundi-dialogs.php', 'axismundi-theme-switcher/axismundi-theme-switcher.php' ) as $file ) {
	$report['plugins'][ $file ] = is_plugin_active( $file );
}

foreach ( $slugs as $slug ) {
	$template                 = get_block_template( get_stylesheet() . '//' . $slug, 'wp_template_part' );
	$report['parts'][ $slug ] = $template ? $template->area : null;
}

$page = get_page_by_path( 'dialogs-vqa' );
if ( $page ) {
	$html     = do_blocks( $page->post_content );
	$surfaces = function_exists( 'axismundi_dialogs_queue_dialog_surface' ) ? axismundi_dialogs_queue_dialog_surface() : array();
	$footer   = implode( '', $surfaces );

	$report['page'] = array(
		'id'                => $page->ID,
		'rendered_bytes'    => strlen( $html ),
		'controls'          => preg_match_all( '#class="[^"]*wp-block-button__link#', $html ),
		'commandfor'        => preg_match_all( '#commandfor="dialog-surface-[^"]+"#', $html ),
		'aria_pressed'      => preg_match_all( '#\saria-pressed="(?:true|false)"#', $html ),
		'surfaces_queued'   => count( $surfaces ),
		'dialogs_in_footer' => preg_match_all( '#<dialog\b#', $footer ),
		'dialog_ids'        => preg_match_all( '#<dialog[^>]*\bid="(dialog-surface-[^"]+)"#', $footer, $m ) ? $m[1] : array(),
	);
}

wp_mkdir_p( '/vqa-report' );
file_put_contents( '/vqa-report/result.json', wp_json_encode( $report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) );
