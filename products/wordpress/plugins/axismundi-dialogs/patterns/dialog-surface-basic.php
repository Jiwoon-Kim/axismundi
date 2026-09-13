<?php
/**
 * Dialog Surface: Basic dialog.
 *
 * Built from the dialog-surface slots - Header, Content, Actions - using the
 * ones this presentation needs, as the Material 3 Design Kit layers it: the
 * container carries no padding, a Content group holds the headline and the
 * supporting text and carries the spacing, and an Actions group holds the
 * button group, dismissing action first, confirming action nearest the
 * trailing edge.
 *
 * Spacing, from the published measurements (products/styleguide/_data/surface.yml):
 * 24 around, 16 headline to body (the Content group's Block spacing), 24 body to
 * actions, 8 between buttons. Content uses the theme's content width, as every
 * dialog-surface Content slot starts out.
 *
 * @package AxismundiDialogs
 */

defined( 'ABSPATH' ) || exit;

return array(
	'title'       => _x( 'Basic dialog', 'Block pattern title', 'axismundi-dialogs' ),
	'description' => __( 'A modal dialog that asks for a decision: a headline, supporting text, and a dismissing and a confirming action.', 'axismundi-dialogs' ),
	'blockTypes'  => array( 'core/template-part/dialog-surface' ),
	'categories'  => array( 'dialog-surface' ),
	'content'     => '<!-- wp:axismundi/dialog ' . wp_json_encode( array( 'presentation' => 'dialog-basic' ) ) . ' -->
<!-- wp:group {"metadata":{"name":"Content"},"style":{"spacing":{"padding":{"top":"24px","right":"24px","bottom":"0","left":"24px"},"blockGap":"16px"}},"layout":{"type":"constrained"}} -->
<div class="wp-block-group" style="padding-top:24px;padding-right:24px;padding-bottom:0;padding-left:24px"><!-- wp:heading -->
<h2 class="wp-block-heading">' . esc_html__( 'Discard draft?', 'axismundi-dialogs' ) . '</h2>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>' . esc_html__( 'The draft and its changes will be removed. This cannot be undone.', 'axismundi-dialogs' ) . '</p>
<!-- /wp:paragraph --></div>
<!-- /wp:group -->

<!-- wp:group {"tagName":"footer","metadata":{"name":"Actions"},"style":{"spacing":{"padding":{"top":"24px","right":"24px","bottom":"24px","left":"24px"}}}} -->
<footer class="wp-block-group" style="padding-top:24px;padding-right:24px;padding-bottom:24px;padding-left:24px"><!-- wp:axismundi/dialog-button-group {"style":{"spacing":{"blockGap":"8px"}},"layout":{"type":"flex","justifyContent":"right"}} -->
<div class="wp-block-axismundi-dialog-button-group wp-block-buttons"><!-- wp:axismundi/dialog-button ' . wp_json_encode( array( 'text' => __( 'Cancel', 'axismundi-dialogs' ), 'action' => 'dialog-surface-close', 'className' => 'is-style-text' ) ) . ' /-->

<!-- wp:axismundi/dialog-button ' . wp_json_encode( array( 'text' => __( 'Discard', 'axismundi-dialogs' ), 'className' => 'is-style-text' ) ) . ' /--></div>
<!-- /wp:axismundi/dialog-button-group --></footer>
<!-- /wp:group -->
<!-- /wp:axismundi/dialog -->',
);
