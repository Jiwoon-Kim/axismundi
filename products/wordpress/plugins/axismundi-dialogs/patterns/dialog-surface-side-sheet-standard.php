<?php
/**
 * Dialog Surface: Standard side sheet.
 *
 * M3 standard side sheet: supplementary content that stays open beside the
 * page on medium and expanded windows, with no scrim. Header and Content groups
 * carry the published spacing: 24 at the start, 12 between top elements. The
 * close button is locked against removal - M3 requires a close affordance in a
 * side sheet.
 *
 * @package AxismundiDialogs
 */

defined( 'ABSPATH' ) || exit;

return array(
	'title'       => _x( 'Standard side sheet', 'Block pattern title', 'axismundi-dialogs' ),
	'description' => __( 'A sheet on the end edge with no scrim, for details people use while they keep working on the page.', 'axismundi-dialogs' ),
	'blockTypes'  => array( 'core/template-part/dialog-surface' ),
	'categories'  => array( 'dialog-surface' ),
	'content'     => '<!-- wp:axismundi/dialog ' . wp_json_encode( array( 'presentation' => 'sheet-side', 'modality' => 'standard' ) ) . ' -->
<!-- wp:group {"tagName":"header","metadata":{"name":"Header"},"style":{"spacing":{"padding":{"top":"12px","right":"12px","bottom":"12px","left":"24px"},"blockGap":"12px"}},"layout":{"type":"flex","flexWrap":"nowrap","justifyContent":"space-between","verticalAlignment":"center"}} -->
<header class="wp-block-group" style="padding-top:12px;padding-right:12px;padding-bottom:12px;padding-left:24px"><!-- wp:heading -->
<h2 class="wp-block-heading">' . esc_html__( 'Photo details', 'axismundi-dialogs' ) . '</h2>
<!-- /wp:heading -->

<!-- wp:axismundi/dialog-button-group {"buttonType":"icon"} -->
<div class="wp-block-axismundi-dialog-button-group wp-block-buttons"><!-- wp:axismundi/dialog-icon-button ' . wp_json_encode( array( 'text' => __( 'Close', 'axismundi-dialogs' ), 'icon' => 'close', 'action' => 'dialog-surface-close', 'className' => 'is-style-standard', 'lock' => array( 'remove' => true ) ) ) . ' /--></div>
<!-- /wp:axismundi/dialog-button-group --></header>
<!-- /wp:group -->

<!-- wp:group {"metadata":{"name":"Content"},"style":{"spacing":{"padding":{"top":"0","right":"24px","bottom":"24px","left":"24px"}}},"layout":{"type":"constrained"}} -->
<div class="wp-block-group" style="padding-top:0;padding-right:24px;padding-bottom:24px;padding-left:24px"><!-- wp:paragraph -->
<p>' . esc_html__( 'Date taken, camera, location.', 'axismundi-dialogs' ) . '</p>
<!-- /wp:paragraph --></div>
<!-- /wp:group -->
<!-- /wp:axismundi/dialog -->',
);
