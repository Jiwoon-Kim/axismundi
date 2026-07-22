<?php
/**
 * axismundi/dialog — server render.
 *
 * An open button plus a native <dialog>. A dialog is always modal: showModal()
 * supplies the top layer, ::backdrop scrim, focus containment, Escape handling,
 * and focus restoration. It shares the axismundi/dialog Interactivity store,
 * open-button, surface, and dialog-title / dialog-close blocks with the Sheet.
 *
 * Two variants: `basic` (centred, 280–560px, 28px radius) and `full-screen`
 * (fills the viewport). The content is a Dialog template part; the title is
 * delivered to axismundi/dialog-title through a scoped render_block_context
 * filter, exactly as the Sheet does.
 *
 * @package AxismundiDialogs
 */

defined( 'ABSPATH' ) || exit;

$axismundi_dialogs_id       = wp_unique_id( 'ax-dialog-' );
$axismundi_dialogs_variant  = in_array( ( $attributes['variant'] ?? 'basic' ), array( 'basic', 'fullscreen' ), true ) ? $attributes['variant'] : 'basic';
$axismundi_dialogs_width    = in_array( ( $attributes['width'] ?? 'medium' ), array( 'narrow', 'medium', 'wide' ), true ) ? $attributes['width'] : 'medium';
$axismundi_dialogs_icon     = preg_replace( '/[^a-z0-9_]/', '', strtolower( (string) ( $attributes['triggerIcon'] ?? 'open_in_new' ) ) );
$axismundi_dialogs_icon     = '' !== $axismundi_dialogs_icon ? $axismundi_dialogs_icon : 'open_in_new';
$axismundi_dialogs_trigger_html = wp_kses_post( (string) ( $attributes['triggerLabel'] ?? '' ) );
$axismundi_dialogs_trigger_raw  = trim( wp_strip_all_tags( $axismundi_dialogs_trigger_html ) );
$axismundi_dialogs_trigger      = '' !== $axismundi_dialogs_trigger_raw ? $axismundi_dialogs_trigger_raw : __( 'Open', 'axismundi-dialogs' );
$axismundi_dialogs_label    = (string) ( $attributes['label'] ?? '' );
$axismundi_dialogs_label    = '' !== trim( $axismundi_dialogs_label ) ? $axismundi_dialogs_label : $axismundi_dialogs_trigger;
$axismundi_dialogs_backdrop = ! isset( $attributes['closeOnBackdrop'] ) || (bool) $attributes['closeOnBackdrop'];

// Full-screen class uses the M3 hyphenated modifier.
$axismundi_dialogs_variant_class = 'fullscreen' === $axismundi_dialogs_variant ? 'full-screen' : 'basic';

// Resolve the referenced Dialog template part + its title fallback.
$axismundi_dialogs_body       = '';
$axismundi_dialogs_part_title = '';
	$axismundi_dialogs_part       = (string) ( $attributes['templatePart'] ?? '' );
$axismundi_dialogs_template   = null;
if ( '' !== $axismundi_dialogs_part ) {
	$axismundi_dialogs_part_id = str_contains( $axismundi_dialogs_part, '//' )
		? $axismundi_dialogs_part
		: get_stylesheet() . '//' . $axismundi_dialogs_part;
	$axismundi_dialogs_template = get_block_template( $axismundi_dialogs_part_id, 'wp_template_part' );
	if ( $axismundi_dialogs_template instanceof WP_Block_Template ) {
		$axismundi_dialogs_part_title = trim( (string) ( $axismundi_dialogs_template->title ?? '' ) );
	}
}

$axismundi_dialogs_title = trim( (string) ( $attributes['title'] ?? '' ) );
if ( '' === $axismundi_dialogs_title ) {
	$axismundi_dialogs_title = $axismundi_dialogs_part_title;
}
if ( '' === $axismundi_dialogs_title ) {
	$axismundi_dialogs_title = $axismundi_dialogs_trigger_raw;
}
if ( '' === $axismundi_dialogs_title ) {
	$axismundi_dialogs_title = __( 'Dialog', 'axismundi-dialogs' );
}

if ( $axismundi_dialogs_template instanceof WP_Block_Template && ! empty( $axismundi_dialogs_template->content ) ) {
	$axismundi_dialogs_inject_title = static function ( $context, $parsed_block ) use ( $axismundi_dialogs_title ) {
		if ( 'axismundi/dialog-title' === ( $parsed_block['blockName'] ?? '' ) ) {
			$context['axismundi/dialogTitle'] = $axismundi_dialogs_title;
		}
		return $context;
	};
	add_filter( 'render_block_context', $axismundi_dialogs_inject_title, 10, 2 );
	$axismundi_dialogs_body = do_blocks( $axismundi_dialogs_template->content );
	remove_filter( 'render_block_context', $axismundi_dialogs_inject_title, 10 );
}

$axismundi_dialogs_wrapper = get_block_wrapper_attributes(
	array(
		'class'               => 'ax-dialog-host',
		'data-wp-interactive' => 'axismundi/dialog',
	)
);
?>
<div
	<?php echo $axismundi_dialogs_wrapper; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
	<?php echo wp_interactivity_data_wp_context( array( 'isOpen' => false ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
>
	<button
		type="button"
		class="ax-dialog__open-button ax-icon-button has-state-layer"
		aria-haspopup="dialog"
		aria-controls="<?php echo esc_attr( $axismundi_dialogs_id ); ?>"
		aria-expanded="false"
		data-wp-bind--aria-expanded="context.isOpen"
		data-wp-on--click="actions.open"
	>
		<span class="material-symbols-outlined notranslate" translate="no" aria-hidden="true" draggable="false"><?php echo esc_html( $axismundi_dialogs_icon ); ?></span>
		<?php if ( '' !== trim( $axismundi_dialogs_trigger_html ) ) : ?>
			<span class="ax-dialog__open-button-label"><?php echo $axismundi_dialogs_trigger_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Sanitized with wp_kses_post(). ?></span>
		<?php else : ?>
			<span class="screen-reader-text"><?php echo esc_html( $axismundi_dialogs_trigger ); ?></span>
		<?php endif; ?>
	</button>

	<dialog
		id="<?php echo esc_attr( $axismundi_dialogs_id ); ?>"
		class="ax-dialog ax-dialog--<?php echo esc_attr( $axismundi_dialogs_variant_class ); ?> is-width-<?php echo esc_attr( $axismundi_dialogs_width ); ?>"
		aria-label="<?php echo esc_attr( $axismundi_dialogs_label ); ?>"
		data-ax-modal="true"
		data-ax-close-on-backdrop="<?php echo $axismundi_dialogs_backdrop ? 'true' : 'false'; ?>"
		data-wp-on--click="actions.onBackdropClick"
		data-wp-on--cancel="actions.onCancel"
		data-wp-on--close="actions.onDialogClose"
	>
		<div class="ax-dialog__surface">
			<?php
			echo $axismundi_dialogs_body; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- do_blocks() output.
			?>
		</div>
	</dialog>
</div>
