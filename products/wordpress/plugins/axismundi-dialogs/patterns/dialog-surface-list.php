<?php
/**
 * Dialog Surface: List dialog.
 *
 * The Material 3 Design Kit's List dialog and Scrollable list dialog are one
 * structure, laid out in the dialog-surface slots: the kit's Title &
 * description frame as a pinned Header with 24 padding all round, the list as
 * the Content slot with no padding so it runs to the dialog's edges and scrolls
 * when it is longer than the room left, and Actions.
 *
 * @package AxismundiDialogs
 */

defined( 'ABSPATH' ) || exit;

return array(
	'title'       => _x( 'List dialog', 'Block pattern title', 'axismundi-dialogs' ),
	'description' => __( 'A modal dialog with a list that runs to its edges and scrolls between a pinned headline and pinned actions.', 'axismundi-dialogs' ),
	'blockTypes'  => array( 'core/template-part/dialog-surface' ),
	'categories'  => array( 'dialog-surface' ),
	'content'     => '<!-- wp:axismundi/dialog ' . wp_json_encode( array( 'presentation' => 'dialog-basic' ) ) . ' -->
<!-- wp:group {"tagName":"header","metadata":{"name":"Header"},"style":{"spacing":{"padding":{"top":"24px","right":"24px","bottom":"24px","left":"24px"},"blockGap":"16px"}},"layout":{"type":"flex","orientation":"vertical","justifyContent":"stretch"}} -->
<header class="wp-block-group" style="padding-top:24px;padding-right:24px;padding-bottom:24px;padding-left:24px"><!-- wp:heading -->
<h2 class="wp-block-heading">' . esc_html__( 'Choose a folder', 'axismundi-dialogs' ) . '</h2>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>' . esc_html__( 'The post is moved into the folder you choose.', 'axismundi-dialogs' ) . '</p>
<!-- /wp:paragraph --></header>
<!-- /wp:group -->

<!-- wp:group {"metadata":{"name":"Content"},"style":{"spacing":{"padding":{"top":"0","right":"0","bottom":"0","left":"0"}}},"layout":{"type":"constrained"}} -->
<div class="wp-block-group" style="padding-top:0;padding-right:0;padding-bottom:0;padding-left:0"><!-- wp:list -->
<ul class="wp-block-list"><!-- wp:list-item -->
<li>' . esc_html__( 'Drafts', 'axismundi-dialogs' ) . '</li>
<!-- /wp:list-item -->

<!-- wp:list-item -->
<li>' . esc_html__( 'Published', 'axismundi-dialogs' ) . '</li>
<!-- /wp:list-item -->

<!-- wp:list-item -->
<li>' . esc_html__( 'Archive', 'axismundi-dialogs' ) . '</li>
<!-- /wp:list-item --></ul>
<!-- /wp:list --></div>
<!-- /wp:group -->

<!-- wp:group {"tagName":"footer","metadata":{"name":"Actions"},"style":{"spacing":{"padding":{"top":"24px","right":"24px","bottom":"24px","left":"24px"}}}} -->
<footer class="wp-block-group" style="padding-top:24px;padding-right:24px;padding-bottom:24px;padding-left:24px"><!-- wp:axismundi/dialog-button-group {"style":{"spacing":{"blockGap":"8px"}},"layout":{"type":"flex","justifyContent":"right"}} -->
<div class="wp-block-axismundi-dialog-button-group wp-block-buttons"><!-- wp:axismundi/dialog-button ' . wp_json_encode( array( 'text' => __( 'Cancel', 'axismundi-dialogs' ), 'action' => 'dialog-surface-close', 'className' => 'is-style-text' ) ) . ' /-->

<!-- wp:axismundi/dialog-button ' . wp_json_encode( array( 'text' => __( 'Move', 'axismundi-dialogs' ), 'className' => 'is-style-text' ) ) . ' /--></div>
<!-- /wp:axismundi/dialog-button-group --></footer>
<!-- /wp:group -->
<!-- /wp:axismundi/dialog -->',
);
