<?php
/**
 * axismundi/sheet — server render.
 *
 * Emits a trigger button plus a native <dialog>. The dialog is the modal host:
 * showModal() supplies the top layer, ::backdrop scrim, focus containment, Escape
 * handling, and focus restoration to the trigger. The Interactivity store only
 * opens/closes it, mirrors aria-expanded, locks document scroll, and enforces the
 * single-open-sheet policy.
 *
 * The sheet content is a Sheet template part (theme//slug); it is rendered here so
 * its blocks are present (and crawlable) before the dialog is ever opened.
 *
 * @package AxismundiDialogs
 */

defined( 'ABSPATH' ) || exit;

$axismundi_dialogs_id       = wp_unique_id( 'ax-dialog-' );
$axismundi_dialogs_variant  = in_array( ( $attributes['variant'] ?? 'side' ), array( 'side', 'bottom' ), true ) ? $attributes['variant'] : 'side';
$axismundi_dialogs_attachment = in_array( ( $attributes['attachment'] ?? 'docked' ), array( 'docked', 'detached' ), true ) ? $attributes['attachment'] : 'docked';
$axismundi_dialogs_edge     = in_array( ( $attributes['edge'] ?? 'end' ), array( 'start', 'end' ), true ) ? $attributes['edge'] : 'end';
$axismundi_dialogs_width    = in_array( ( $attributes['width'] ?? 'medium' ), array( 'narrow', 'medium', 'wide' ), true ) ? $attributes['width'] : 'medium';
$axismundi_dialogs_icon     = preg_replace( '/[^a-z0-9_]/', '', strtolower( (string) ( $attributes['triggerIcon'] ?? 'menu' ) ) );
$axismundi_dialogs_icon     = '' !== $axismundi_dialogs_icon ? $axismundi_dialogs_icon : 'menu';
$axismundi_dialogs_trigger_html = wp_kses_post( (string) ( $attributes['triggerLabel'] ?? '' ) );
$axismundi_dialogs_trigger_raw  = trim( wp_strip_all_tags( $axismundi_dialogs_trigger_html ) );
$axismundi_dialogs_trigger      = '' !== $axismundi_dialogs_trigger_raw ? $axismundi_dialogs_trigger_raw : __( 'Open', 'axismundi-dialogs' );
$axismundi_dialogs_label    = (string) ( $attributes['label'] ?? '' );
$axismundi_dialogs_label    = '' !== trim( $axismundi_dialogs_label ) ? $axismundi_dialogs_label : $axismundi_dialogs_trigger;
$axismundi_dialogs_backdrop = ! isset( $attributes['closeOnBackdrop'] ) || (bool) $attributes['closeOnBackdrop'];
$axismundi_dialogs_handle   = 'bottom' === $axismundi_dialogs_variant && ! empty( $attributes['showDragHandle'] );
$axismundi_dialogs_modal    = ! isset( $attributes['modal'] ) || (bool) $attributes['modal'];
$axismundi_dialogs_scroll   = 'sheet' === ( $attributes['scrollMode'] ?? 'body' ) ? 'sheet' : 'body';

// Detached is a modal Side Sheet geometry. Standard and Bottom Sheets remain
// docked so presentation and geometry cannot form unsupported combinations.
if ( 'side' !== $axismundi_dialogs_variant || ! $axismundi_dialogs_modal ) {
	$axismundi_dialogs_attachment = 'docked';
}
$axismundi_dialogs_push = 'side' === $axismundi_dialogs_variant && ! $axismundi_dialogs_modal && 'docked' === $axismundi_dialogs_attachment;
// A standard (non-modal) sheet only closes on a backdrop click while it still has
// a scrim, i.e. when modal; otherwise there is no ::backdrop to click.
$axismundi_dialogs_backdrop = $axismundi_dialogs_modal && $axismundi_dialogs_backdrop;

// Resolve the referenced Sheet template part to rendered block markup, capturing
// its title as a fallback for the dynamic axismundi/dialog-title block.
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

// Title resolution: explicit -> template-part title -> trigger label -> generic.
$axismundi_dialogs_title = trim( (string) ( $attributes['title'] ?? '' ) );
if ( '' === $axismundi_dialogs_title ) {
	$axismundi_dialogs_title = $axismundi_dialogs_part_title;
}
if ( '' === $axismundi_dialogs_title ) {
	$axismundi_dialogs_title = $axismundi_dialogs_trigger_raw;
}
if ( '' === $axismundi_dialogs_title ) {
	$axismundi_dialogs_title = __( 'Sheet', 'axismundi-dialogs' );
}

// Render the part. The part is rendered here (not as a child of this block), so
// the current title is delivered to axismundi/dialog-title through a scoped
// render_block_context filter rather than block-tree context.
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
		class="ax-dialog ax-dialog--sheet-<?php echo esc_attr( $axismundi_dialogs_variant ); ?> ax-dialog--edge-<?php echo esc_attr( $axismundi_dialogs_edge ); ?> is-<?php echo esc_attr( $axismundi_dialogs_attachment ); ?> is-width-<?php echo esc_attr( $axismundi_dialogs_width ); ?><?php echo 'sheet' === $axismundi_dialogs_scroll ? ' is-scroll-sheet' : ''; ?><?php echo $axismundi_dialogs_modal ? '' : ' is-standard'; ?>"
		aria-label="<?php echo esc_attr( $axismundi_dialogs_label ); ?>"
		data-ax-modal="<?php echo $axismundi_dialogs_modal ? 'true' : 'false'; ?>"
		data-ax-attachment="<?php echo esc_attr( $axismundi_dialogs_attachment ); ?>"
		data-ax-edge="<?php echo esc_attr( $axismundi_dialogs_edge ); ?>"
		data-ax-push="<?php echo $axismundi_dialogs_push ? 'true' : 'false'; ?>"
		data-ax-close-on-backdrop="<?php echo $axismundi_dialogs_backdrop ? 'true' : 'false'; ?>"
		data-wp-on--click="actions.onBackdropClick"
		data-wp-on--cancel="actions.onCancel"
		data-wp-on--close="actions.onDialogClose"
	>
		<div class="ax-dialog__surface">
			<?php if ( $axismundi_dialogs_handle ) : ?>
				<div class="ax-dialog__drag-handle" aria-hidden="true"></div>
			<?php endif; ?>
			<?php
			echo $axismundi_dialogs_body; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- do_blocks() output.
			?>
		</div>
	</dialog>
</div>
