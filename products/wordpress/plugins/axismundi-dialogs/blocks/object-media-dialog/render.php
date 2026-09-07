<?php
/**
 * axismundi/object-media-dialog — server render (singleton hub).
 *
 * Renders ONE fixed-id native <dialog> per page. It ships empty: any Object Attachments
 * block on the page opens it and the runtime fills the carousel and side panel from that
 * block's own DOM. One hub rather than one dialog per Object is the same rule the post
 * quick view follows — a feed of twenty posts must not emit twenty dialogs.
 *
 * Nothing here is Object-specific, because the hub is chrome. Object Projections owns
 * the media and the panel markup; this block owns the surface that presents them.
 *
 * @package AxismundiDialogs
 */

defined( 'ABSPATH' ) || exit;

/*
 * Same vocabulary as `axismundi/dialog`: one `variant` choosing between a contained
 * dialog and a full-screen one.
 *
 * Full-screen reuses that block's `ax-dialog--full-screen` class outright, so the two
 * dialogs fill the viewport identically and share the M3 tokens for surface, radius, and
 * elevation.
 *
 * The contained variant deliberately does NOT take `ax-dialog--basic`. An M3 basic dialog
 * caps at 560px, which is a width for a sentence and a pair of buttons — a media viewer
 * has to hold an image beside the post it came from, and at 560px that split collapses.
 * It therefore keeps its own width while drawing on the same tokens, rather than
 * inheriting a size meant for a different kind of content.
 */
$axismundi_dialogs_omd_variant = in_array( ( $attributes['variant'] ?? 'basic' ), array( 'basic', 'fullscreen' ), true )
	? $attributes['variant']
	: 'basic';
$axismundi_dialogs_omd_classes = 'ax-dialog ax-object-media-dialog is-variant-' . $axismundi_dialogs_omd_variant
	. ( 'fullscreen' === $axismundi_dialogs_omd_variant ? ' ax-dialog--full-screen' : '' );

$axismundi_dialogs_omd_wrapper = get_block_wrapper_attributes(
	array(
		'class'               => 'ax-object-media-dialog-host',
	)
);
?>
<div
	<?php echo $axismundi_dialogs_omd_wrapper; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
>
	<dialog
		id="ax-object-media-dialog"
		class="<?php echo esc_attr( $axismundi_dialogs_omd_classes ); ?>"
		aria-label="<?php esc_attr_e( 'Attached media', 'axismundi-dialogs' ); ?>"
		data-ax-modal="true"
		data-ax-close-on-backdrop="true"
	>
		<div class="ax-dialog__surface ax-object-media-dialog__surface">
			<button
				type="button"
				class="ax-object-media-dialog__close"
				aria-label="<?php esc_attr_e( 'Close', 'axismundi-dialogs' ); ?>"
				data-ax-media-dialog-close
			>
				<span class="material-symbols-outlined notranslate" translate="no" aria-hidden="true">close</span>
			</button>

			<div class="ax-object-media-dialog__layout">
				<!--
					The media region reuses the Object Attachments carousel classes so the
					engine that drives the inline carousel drives this one unchanged.
				-->
				<div class="ax-object-media-dialog__media">
					<div class="axismundi-object__carousel" data-ax-carousel>
						<div class="axismundi-object__carousel-viewport">
							<div class="axismundi-object__carousel-track"></div>
						</div>
					</div>
				</div>

				<aside class="ax-object-media-dialog__panel">
					<div class="ax-object-media-dialog__content"></div>
				</aside>
			</div>
		</div>
	</dialog>
</div>
