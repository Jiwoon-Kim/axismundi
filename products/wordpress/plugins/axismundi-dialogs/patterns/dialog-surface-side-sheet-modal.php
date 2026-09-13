<?php
/**
 * Dialog Surface: Modal side sheet.
 *
 * M3 modal side sheet anatomy, as Header, Content and Actions groups that carry
 * the published spacing: 24 at the start, 12 between top elements, bottom
 * actions 16 above and 24 below, aligned to the start. The close button is
 * locked against removal - M3 requires a close affordance in a side sheet. The
 * divider M3 draws above the actions is optional and left out; a Separator
 * block adds it.
 *
 * @package AxismundiDialogs
 */

defined( 'ABSPATH' ) || exit;

return array(
	'title'       => _x( 'Modal side sheet', 'Block pattern title', 'axismundi-dialogs' ),
	'description' => __( 'A sheet on the end edge above a scrim, for filters or details that must be dismissed to return to the page.', 'axismundi-dialogs' ),
	'blockTypes'  => array( 'core/template-part/dialog-surface' ),
	'categories'  => array( 'dialog-surface' ),
	'content'     => '<!-- wp:axismundi/dialog ' . wp_json_encode( array( 'presentation' => 'sheet-side' ) ) . ' -->
<!-- wp:group {"tagName":"header","metadata":{"name":"Header"},"style":{"spacing":{"padding":{"top":"12px","right":"12px","bottom":"12px","left":"24px"},"blockGap":"12px"}},"layout":{"type":"flex","flexWrap":"nowrap","justifyContent":"space-between","verticalAlignment":"center"}} -->
<header class="wp-block-group" style="padding-top:12px;padding-right:12px;padding-bottom:12px;padding-left:24px"><!-- wp:heading -->
<h2 class="wp-block-heading">' . esc_html__( 'Filters', 'axismundi-dialogs' ) . '</h2>
<!-- /wp:heading -->

<!-- wp:axismundi/dialog-button-group {"buttonType":"icon"} -->
<div class="wp-block-axismundi-dialog-button-group wp-block-buttons"><!-- wp:axismundi/dialog-icon-button ' . wp_json_encode( array( 'text' => __( 'Close', 'axismundi-dialogs' ), 'icon' => 'close', 'action' => 'dialog-surface-close', 'className' => 'is-style-standard', 'lock' => array( 'remove' => true ) ) ) . ' /--></div>
<!-- /wp:axismundi/dialog-button-group --></header>
<!-- /wp:group -->

<!-- wp:group {"metadata":{"name":"Content"},"style":{"spacing":{"padding":{"top":"0","right":"24px","bottom":"0","left":"24px"}}},"layout":{"type":"constrained"}} -->
<div class="wp-block-group" style="padding-top:0;padding-right:24px;padding-bottom:0;padding-left:24px"><!-- wp:paragraph -->
<p>' . esc_html__( 'Include drafts, author, date range.', 'axismundi-dialogs' ) . '</p>
<!-- /wp:paragraph --></div>
<!-- /wp:group -->

<!-- wp:group {"tagName":"footer","metadata":{"name":"Actions"},"style":{"spacing":{"padding":{"top":"16px","right":"24px","bottom":"24px","left":"24px"}}}} -->
<footer class="wp-block-group" style="padding-top:16px;padding-right:24px;padding-bottom:24px;padding-left:24px"><!-- wp:axismundi/dialog-button-group {"style":{"spacing":{"blockGap":"8px"}},"layout":{"type":"flex","justifyContent":"left"}} -->
<div class="wp-block-axismundi-dialog-button-group wp-block-buttons"><!-- wp:axismundi/dialog-button ' . wp_json_encode( array( 'text' => __( 'Apply', 'axismundi-dialogs' ) ) ) . ' /-->

<!-- wp:axismundi/dialog-button ' . wp_json_encode( array( 'text' => __( 'Cancel', 'axismundi-dialogs' ), 'action' => 'dialog-surface-close', 'className' => 'is-style-text' ) ) . ' /--></div>
<!-- /wp:axismundi/dialog-button-group --></footer>
<!-- /wp:group -->
<!-- /wp:axismundi/dialog -->',
);
