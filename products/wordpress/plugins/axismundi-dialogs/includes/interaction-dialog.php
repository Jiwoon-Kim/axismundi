<?php
/**
 * Native dialog surface for dynamic Axismundi interactions.
 *
 * Blocks such as Follow own their data and Interactivity store, while Dialogs
 * owns the accessible native <dialog> chrome and its visual contract. This
 * keeps a dynamic interaction from needing a template part solely to render
 * contextual server data.
 *
 * @package AxismundiDialogs
 */

defined( 'ABSPATH' ) || exit;

/** Enqueue the shared native-dialog surface for a dynamic interaction. */
function axismundi_dialogs_enqueue_interaction_dialog_assets() : void {
	$shared_handle = 'axismundi-dialogs-interaction-shared';
	$basic_handle  = 'axismundi-dialogs-interaction-basic';
	$modal_handle  = 'axismundi-dialogs-interaction-modal';

	if ( ! wp_style_is( $shared_handle, 'registered' ) ) {
		wp_register_style( $shared_handle, plugins_url( 'assets/shared.css', dirname( __DIR__ ) . '/axismundi-dialogs.php' ), array(), (string) filemtime( dirname( __DIR__ ) . '/assets/shared.css' ) );
	}
	if ( ! wp_style_is( $basic_handle, 'registered' ) ) {
		wp_register_style( $basic_handle, plugins_url( 'blocks/dialog/style.css', dirname( __DIR__ ) . '/axismundi-dialogs.php' ), array( $shared_handle ), (string) filemtime( dirname( __DIR__ ) . '/blocks/dialog/style.css' ) );
	}
	if ( ! wp_style_is( $modal_handle, 'registered' ) ) {
		wp_register_style( $modal_handle, plugins_url( 'assets/interaction-dialog.css', dirname( __DIR__ ) . '/axismundi-dialogs.php' ), array( $basic_handle ), (string) filemtime( dirname( __DIR__ ) . '/assets/interaction-dialog.css' ) );
	}

	wp_enqueue_style( $shared_handle );
	wp_enqueue_style( $basic_handle );
	wp_enqueue_style( $modal_handle );
}

/** Only accept an Interactivity action path, never arbitrary markup. */
function axismundi_dialogs_interaction_action( string $action, string $fallback ) : string {
	return preg_match( '/^actions\.[A-Za-z0-9_]+$/', $action ) ? $action : $fallback;
}

/**
 * Render one basic native dialog within the caller's Interactivity context.
 *
 * @param array<string,mixed> $args Dialog properties and trusted rendered body.
 */
function axismundi_dialogs_render_interaction_dialog( array $args ) : string {
	$id = sanitize_html_class( (string) ( $args['id'] ?? '' ) );
	if ( '' === $id ) {
		return '';
	}

	axismundi_dialogs_enqueue_interaction_dialog_assets();
	$title            = trim( wp_strip_all_tags( (string) ( $args['title'] ?? '' ) ) );
	$label            = '' !== $title ? $title : __( 'Dialog', 'axismundi-dialogs' );
	$body             = (string) ( $args['body'] ?? '' );
	$close_action     = axismundi_dialogs_interaction_action( (string) ( $args['close_action'] ?? '' ), 'actions.closeInteractionDialog' );
	$cancel_action    = axismundi_dialogs_interaction_action( (string) ( $args['cancel_action'] ?? '' ), 'actions.onInteractionDialogCancel' );
	$backdrop_action  = axismundi_dialogs_interaction_action( (string) ( $args['backdrop_action'] ?? '' ), 'actions.onInteractionDialogBackdrop' );

	ob_start();
	?>
	<dialog id="<?php echo esc_attr( $id ); ?>" class="ax-dialog ax-dialog--basic is-width-medium ax-interaction-dialog" aria-labelledby="<?php echo esc_attr( $id . '-title' ); ?>" data-ax-modal="true" data-ax-close-on-backdrop="true" data-wp-on--click="<?php echo esc_attr( $backdrop_action ); ?>" data-wp-on--cancel="<?php echo esc_attr( $cancel_action ); ?>">
		<div class="ax-dialog__surface">
			<section class="ax-interaction-dialog__section">
				<header class="ax-interaction-dialog__header">
					<h2 id="<?php echo esc_attr( $id . '-title' ); ?>" class="ax-interaction-dialog__title"><?php echo esc_html( $label ); ?></h2>
					<button type="button" class="ax-dialog-close ax-interaction-dialog__close" aria-label="<?php esc_attr_e( 'Close dialog', 'axismundi-dialogs' ); ?>" data-wp-on--click="<?php echo esc_attr( $close_action ); ?>"><span class="material-symbols-outlined notranslate" translate="no" aria-hidden="true">close</span></button>
				</header>
				<div class="ax-interaction-dialog__body"><?php echo $body; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Caller owns escaped dynamic body. ?></div>
			</section>
		</div>
	</dialog>
	<?php
	return (string) ob_get_clean();
}
