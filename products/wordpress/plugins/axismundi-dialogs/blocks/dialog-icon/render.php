<?php
/**
 * axismundi/dialog-icon — server render.
 *
 * A decorative leading icon for a basic dialog header (M3 optional hero icon).
 *
 * @package AxismundiDialogs
 */

defined( 'ABSPATH' ) || exit;

$axismundi_dialogs_icon_name = preg_replace( '/[^a-z0-9_]/', '', strtolower( (string) ( $attributes['icon'] ?? 'info' ) ) );
$axismundi_dialogs_icon_name = '' !== $axismundi_dialogs_icon_name ? $axismundi_dialogs_icon_name : 'info';

$axismundi_dialogs_icon_wrapper = get_block_wrapper_attributes(
	array(
		'class'            => 'ax-dialog-icon material-symbols-outlined notranslate',
		'translate'        => 'no',
		'aria-hidden'      => 'true',
		'data-ax-dialog-icon' => 'true',
	)
);
?>
<span <?php echo $axismundi_dialogs_icon_wrapper; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>><?php echo esc_html( $axismundi_dialogs_icon_name ); ?></span>
