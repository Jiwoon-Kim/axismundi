<?php
/**
 * axismundi/dialog-close — server render.
 *
 * A dismiss button placed inside a Sheet template part. It carries the
 * Interactivity directive that calls the enclosing axismundi/sheet store's close
 * action; the button resolves the dialog from its own DOM ancestry, so it needs
 * no sheet id. Outside a sheet it is an inert button (the action never resolves).
 *
 * @package AxismundiDialogs
 */

defined( 'ABSPATH' ) || exit;

$axismundi_dialogs_close_label = (string) ( $attributes['label'] ?? '' );
$axismundi_dialogs_close_label = '' !== trim( $axismundi_dialogs_close_label ) ? $axismundi_dialogs_close_label : __( 'Close', 'axismundi-dialogs' );
$axismundi_dialogs_close_icon  = preg_replace( '/[^a-z0-9_]/', '', strtolower( (string) ( $attributes['icon'] ?? 'close' ) ) );
$axismundi_dialogs_close_icon  = '' !== $axismundi_dialogs_close_icon ? $axismundi_dialogs_close_icon : 'close';

$axismundi_dialogs_close_wrapper = get_block_wrapper_attributes(
	array(
		'class'             => 'ax-dialog-close',
		'type'              => 'button',
		'aria-label'        => $axismundi_dialogs_close_label,
		'data-wp-on--click' => 'actions.close',
	)
);
?>
<button <?php echo $axismundi_dialogs_close_wrapper; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
	<span class="material-symbols-outlined notranslate" translate="no" aria-hidden="true" draggable="false"><?php echo esc_html( $axismundi_dialogs_close_icon ); ?></span>
</button>
