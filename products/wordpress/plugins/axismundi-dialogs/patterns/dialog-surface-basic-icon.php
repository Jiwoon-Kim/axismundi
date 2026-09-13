<?php
/**
 * Dialog Surface: Basic dialog with icon.
 *
 * The Material 3 Design Kit's basic dialog with its optional hero icon: the
 * icon centred over a centred headline 16 below it, the supporting text
 * start-aligned under them. Inside the Content slot, a Headline group - a
 * centred vertical stack whose gap carries the 16 - holds icon and headline;
 * the rest is the Basic dialog pattern.
 *
 * @package AxismundiDialogs
 */

defined( 'ABSPATH' ) || exit;

return array(
	'title'       => _x( 'Basic dialog with icon', 'Block pattern title', 'axismundi-dialogs' ),
	'description' => __( 'A modal dialog with a centred icon above its headline, supporting text, and a dismissing and a confirming action.', 'axismundi-dialogs' ),
	'blockTypes'  => array( 'core/template-part/dialog-surface' ),
	'categories'  => array( 'dialog-surface' ),
	'content'     => '<!-- wp:axismundi/dialog ' . wp_json_encode( array( 'presentation' => 'dialog-basic' ) ) . ' -->
<!-- wp:group {"metadata":{"name":"Content"},"style":{"spacing":{"padding":{"top":"24px","right":"24px","bottom":"0","left":"24px"},"blockGap":"16px"}},"layout":{"type":"constrained"}} -->
<div class="wp-block-group" style="padding-top:24px;padding-right:24px;padding-bottom:0;padding-left:24px"><!-- wp:group {"metadata":{"name":"Headline"},"style":{"spacing":{"blockGap":"16px"}},"layout":{"type":"flex","orientation":"vertical","justifyContent":"center"}} -->
<div class="wp-block-group"><!-- wp:axismundi/dialog-icon ' . wp_json_encode(
		array(
			'iconSource' => 'font',
			'icon'       => 'delete',
			'iconClass'  => 'material-symbols-outlined',
			'tagName'    => 'span',
			'textColor'  => 'secondary',
			'style'      => array(
				'typography' => array(
					'fontVariationSettings' => array( array( 'FILL' => '0' ), array( 'wght' => '400' ), array( 'GRAD' => '0' ), array( 'opsz' => '24' ) ),
				),
			),
		)
	) . ' /-->

<!-- wp:heading {"textAlign":"center"} -->
<h2 class="wp-block-heading has-text-align-center">' . esc_html__( 'Discard draft?', 'axismundi-dialogs' ) . '</h2>
<!-- /wp:heading --></div>
<!-- /wp:group -->

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
