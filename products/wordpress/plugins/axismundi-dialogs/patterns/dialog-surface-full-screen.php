<?php
/**
 * Dialog Surface: Full-screen dialog.
 *
 * M3 full-screen dialog anatomy: a 56dp header with the close icon button - the
 * only navigation it carries - the headline filling the space between, and a
 * text button that names what happens next; the task below it. The header is a
 * Row group and the headline is set to fill it, so the layout is the group's
 * and stays editable. The Content group uses the theme's content width, so on a
 * wide window the task reads at a line length rather than across the screen.
 *
 * @package AxismundiDialogs
 */

defined( 'ABSPATH' ) || exit;

return array(
	'title'       => _x( 'Full-screen dialog', 'Block pattern title', 'axismundi-dialogs' ),
	'description' => __( 'A dialog that fills a compact window for a task with several steps: a header with close, headline and save, and the task below.', 'axismundi-dialogs' ),
	'blockTypes'  => array( 'core/template-part/dialog-surface' ),
	'categories'  => array( 'dialog-surface' ),
	'content'     => '<!-- wp:axismundi/dialog ' . wp_json_encode( array( 'presentation' => 'dialog-full-screen' ) ) . ' -->
<!-- wp:group {"tagName":"header","metadata":{"name":"Header"},"style":{"dimensions":{"minHeight":"56px"},"spacing":{"padding":{"top":"0","right":"24px","bottom":"0","left":"8px"},"blockGap":"8px"}},"layout":{"type":"flex","flexWrap":"nowrap","verticalAlignment":"center"}} -->
<header class="wp-block-group" style="min-height:56px;padding-top:0;padding-right:24px;padding-bottom:0;padding-left:8px"><!-- wp:axismundi/dialog-button-group {"buttonType":"icon"} -->
<div class="wp-block-axismundi-dialog-button-group wp-block-buttons"><!-- wp:axismundi/dialog-icon-button ' . wp_json_encode( array( 'text' => __( 'Close', 'axismundi-dialogs' ), 'icon' => 'close', 'action' => 'dialog-surface-close', 'className' => 'is-style-standard', 'lock' => array( 'remove' => true ) ) ) . ' /--></div>
<!-- /wp:axismundi/dialog-button-group -->

<!-- wp:heading {"style":{"layout":{"selfStretch":"fill","flexSize":null}}} -->
<h2 class="wp-block-heading">' . esc_html__( 'New event', 'axismundi-dialogs' ) . '</h2>
<!-- /wp:heading -->

<!-- wp:axismundi/dialog-button-group -->
<div class="wp-block-axismundi-dialog-button-group wp-block-buttons"><!-- wp:axismundi/dialog-button ' . wp_json_encode( array( 'text' => __( 'Save', 'axismundi-dialogs' ), 'className' => 'is-style-text' ) ) . ' /--></div>
<!-- /wp:axismundi/dialog-button-group --></header>
<!-- /wp:group -->

<!-- wp:group {"metadata":{"name":"Content"},"style":{"spacing":{"padding":{"top":"24px","right":"24px","bottom":"24px","left":"24px"}}},"layout":{"type":"constrained"}} -->
<div class="wp-block-group" style="padding-top:24px;padding-right:24px;padding-bottom:24px;padding-left:24px"><!-- wp:paragraph -->
<p>' . esc_html__( 'Title, date, place and time go here - a task with several steps that does not save as you type.', 'axismundi-dialogs' ) . '</p>
<!-- /wp:paragraph --></div>
<!-- /wp:group -->
<!-- /wp:axismundi/dialog -->',
);
