<?php
/** Native dynamic interaction-dialog regression fixture (dev-only). */

defined( 'ABSPATH' ) || exit( 1 );

require_once dirname( __DIR__ ) . '/includes/interaction-dialog.php';

$ax_interaction_dialog_results = array();

function ax_interaction_dialog_assert( array &$results, string $label, bool $condition ) : void {
	$results[] = $condition;
	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI fixture output.
	printf( "[%s] %s\n", $condition ? 'PASS' : 'FAIL', $label );
}

$html = axismundi_dialogs_render_interaction_dialog(
	array(
		'id'              => 'remote-follow-test',
		'title'           => 'Follow Example',
		'body'            => '<p>Trusted body.</p>',
		'close_action'    => 'actions.closeRemoteFollowDialog',
		'cancel_action'   => 'actions.onRemoteFollowDialogCancel',
		'backdrop_action' => 'actions.onRemoteFollowDialogBackdrop',
	)
);
ax_interaction_dialog_assert( $ax_interaction_dialog_results, 'dynamic interaction dialog renders the shared native dialog shape', str_contains( $html, '<dialog id="remote-follow-test"' ) && str_contains( $html, 'class="ax-dialog ax-dialog--basic is-width-medium ax-interaction-dialog"' ) && str_contains( $html, 'aria-labelledby="remote-follow-test-title"' ) );
ax_interaction_dialog_assert( $ax_interaction_dialog_results, 'dynamic interaction dialog keeps caller actions constrained to Interactivity paths', str_contains( $html, 'data-wp-on--click="actions.onRemoteFollowDialogBackdrop"' ) && str_contains( $html, 'data-wp-on--cancel="actions.onRemoteFollowDialogCancel"' ) && str_contains( $html, 'data-wp-on--click="actions.closeRemoteFollowDialog"' ) );
$fallback = axismundi_dialogs_render_interaction_dialog( array( 'id' => 'test', 'body' => '<p>Body</p>', 'close_action' => 'javascript:alert(1)' ) );
ax_interaction_dialog_assert( $ax_interaction_dialog_results, 'arbitrary action strings fail closed to the standard close action', str_contains( $fallback, 'data-wp-on--click="actions.closeInteractionDialog"' ) && ! str_contains( $fallback, 'javascript:' ) );

$ax_interaction_dialog_failures = count( array_filter( $ax_interaction_dialog_results, static fn( bool $passed ) : bool => ! $passed ) );
// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI fixture output.
printf( "\n== %d checks, %d failed ==\n", count( $ax_interaction_dialog_results ), $ax_interaction_dialog_failures );
exit( $ax_interaction_dialog_failures > 0 ? 1 : 0 );
