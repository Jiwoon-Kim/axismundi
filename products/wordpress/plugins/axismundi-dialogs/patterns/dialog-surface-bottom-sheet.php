<?php
/**
 * Dialog Surface: Bottom sheet.
 *
 * The Material 3 Design Kit layers a bottom sheet as Header > Drag handle, then
 * a Content slot, on a container with no padding. The Header is the Dialog
 * block's own - its drag handle switch, on by default, renders it, and turning
 * the switch off removes the Header - so the pattern is the Content: one group
 * that owns the sheet's spacing, 24 on every side whether or not a handle sits
 * above it, and scrolls. M3 publishes no content padding for a bottom sheet;
 * 24 is this project's default (surface.yml, sheet-bottom content_padding).
 *
 * @package AxismundiDialogs
 */

defined( 'ABSPATH' ) || exit;

return array(
	'title'       => _x( 'Bottom sheet', 'Block pattern title', 'axismundi-dialogs' ),
	'description' => __( 'A modal sheet anchored to the bottom of a small window, for supplementary content and actions.', 'axismundi-dialogs' ),
	'blockTypes'  => array( 'core/template-part/dialog-surface' ),
	'categories'  => array( 'dialog-surface' ),
	'content'     => '<!-- wp:axismundi/dialog ' . wp_json_encode( array( 'presentation' => 'sheet-bottom' ) ) . ' -->
<!-- wp:group {"metadata":{"name":"Content"},"style":{"spacing":{"padding":{"top":"24px","right":"24px","bottom":"24px","left":"24px"}}},"layout":{"type":"constrained"}} -->
<div class="wp-block-group" style="padding-top:24px;padding-right:24px;padding-bottom:24px;padding-left:24px"><!-- wp:heading -->
<h2 class="wp-block-heading">' . esc_html__( 'Share', 'axismundi-dialogs' ) . '</h2>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>' . esc_html__( 'Copy the link, or choose who can see this post.', 'axismundi-dialogs' ) . '</p>
<!-- /wp:paragraph --></div>
<!-- /wp:group -->
<!-- /wp:axismundi/dialog -->',
);
