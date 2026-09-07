<?php
/**
 * Protected media challenge form.
 *
 * @package AxismundiMediaLibrary
 */

defined( 'ABSPATH' ) || exit;

global $wp_query;
$axismundi_media_gate_id = $wp_query instanceof WP_Query ? (int) $wp_query->get( 'ax_media_gate_required' ) : 0;
if ( $axismundi_media_gate_id <= 0 ) {
	return;
}
$axismundi_media_gate_term = get_term( $axismundi_media_gate_id, AXISMUNDI_MEDIA_FOLDER_TAX );
$axismundi_media_gate_title = $axismundi_media_gate_term instanceof WP_Term ? $axismundi_media_gate_term->name : __( 'Protected media', 'axismundi-media-library' );
$axismundi_media_gate_redirect = is_attachment()
	? axismundi_media_object_url( (int) get_queried_object_id() )
	: axismundi_media_folder_url( axismundi_media_folder_owner( $axismundi_media_gate_id ), (int) get_query_var( 'ax_media_folder' ) );
?>
<section <?php echo get_block_wrapper_attributes(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Core-generated block wrapper attributes. ?>>
	<h1><?php echo esc_html( $axismundi_media_gate_title ); ?></h1>
	<p><?php esc_html_e( 'This media collection is password protected.', 'axismundi-media-library' ); ?></p>
	<?php if ( isset( $_GET['ax_media_gate_error'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Display-only redirect flag. ?>
		<p class="ax-media-gate-error" role="alert"><?php esc_html_e( 'The password is incorrect.', 'axismundi-media-library' ); ?></p>
	<?php endif; ?>
	<form method="post" action="<?php echo esc_url( $axismundi_media_gate_redirect ); ?>">
		<input type="hidden" name="ax_media_gate_action" value="unlock">
		<input type="hidden" name="folder_id" value="<?php echo esc_attr( (string) $axismundi_media_gate_id ); ?>">
		<input type="hidden" name="redirect_to" value="<?php echo esc_attr( $axismundi_media_gate_redirect ); ?>">
		<?php wp_nonce_field( 'ax_media_unlock_' . $axismundi_media_gate_id ); ?>
		<label for="ax-media-folder-password"><?php esc_html_e( 'Password', 'axismundi-media-library' ); ?></label>
		<input id="ax-media-folder-password" type="password" name="folder_password" required autocomplete="current-password">
		<button type="submit" class="wp-element-button"><?php esc_html_e( 'Unlock', 'axismundi-media-library' ); ?></button>
	</form>
</section>
