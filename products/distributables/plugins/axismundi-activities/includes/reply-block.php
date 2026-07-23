<?php
/**
 * Reply authoring entry point for one Object view.
 *
 * The first version deliberately delegates composition to the Note editor. A
 * future front-end editor can replace only the compose URL filter while the
 * Object URI, public-reply count, and block contract remain stable.
 *
 * @package AxismundiActivities
 */

defined( 'ABSPATH' ) || exit;

/** Resolve the current Object URI and its bounded visible-thread count. */
function axismundi_act_reply_button_data( array $attributes, WP_Block $block ) : array {
	$uri   = axismundi_act_like_block_object_uri( $attributes, $block );
	$count = array( 'count' => 0, 'truncated' => false );
	if ( '' !== $uri && function_exists( 'axismundi_op_get_display_reply_tree_count' ) ) {
		$count = axismundi_op_get_display_reply_tree_count( $uri );
	} elseif ( '' !== $uri && function_exists( 'axismundi_op_get_public_reply_collection_count' ) ) {
		$count = axismundi_op_get_public_reply_collection_count( $uri );
	}
	return array(
		'object_uri' => $uri,
		'count'      => max( 0, (int) ( $count['count'] ?? 0 ) ),
		'truncated'  => ! empty( $count['truncated'] ),
	);
}

/** Render a text-button entry point into the local Note reply authoring flow. */
function axismundi_act_render_reply_button( array $attributes, string $content, WP_Block $block ) : string {
	$data = axismundi_act_reply_button_data( $attributes, $block );
	if ( '' === $data['object_uri'] ) {
		return '';
	}
	$actor       = axismundi_act_current_local_actor();
	$compose_url = $actor instanceof Axismundi_Actor ? (string) apply_filters( 'axismundi_act_reply_compose_url', '', $data['object_uri'] ) : '';
	$count       = number_format_i18n( $data['count'] ) . ( $data['truncated'] ? '+' : '' );
	$reply_label = sprintf(
		/* translators: %s: number of visible replies. */
		_n( 'Reply (%s reply)', 'Reply (%s replies)', $data['count'], 'axismundi-activities' ),
		$count
	);
	$label       = $actor instanceof Axismundi_Actor && '' !== $compose_url
		? $reply_label
		: ( is_user_logged_in() ? __( 'Activate a public Actor profile to reply.', 'axismundi-activities' ) : __( 'Log in to reply.', 'axismundi-activities' ) );
	// The icon carries the action, so the text label is opt-in while the count is
	// on by default. The accessible name stays on the control either way.
	$inner = '<span class="material-symbols-outlined" aria-hidden="true">reply</span>';
	if ( ! empty( $attributes['showLabel'] ) ) {
		$inner .= '<span class="axismundi-reply-button__label" aria-hidden="true">' . esc_html__( 'Reply', 'axismundi-activities' ) . '</span>';
	}
	if ( ! isset( $attributes['showCount'] ) || (bool) $attributes['showCount'] ) {
		$inner .= '<span class="axismundi-reply-button__count" aria-hidden="true">' . esc_html( $count ) . '</span>';
	}
	ob_start();
	?>
	<div <?php echo get_block_wrapper_attributes(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
		<?php if ( '' !== $compose_url ) : ?>
			<a class="axismundi-reply-button__button" href="<?php echo esc_url( $compose_url ); ?>" aria-label="<?php echo esc_attr( $label ); ?>" title="<?php echo esc_attr( $label ); ?>"><?php echo $inner; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Parts escaped above. ?></a>
		<?php else : ?>
			<button type="button" class="axismundi-reply-button__button" aria-label="<?php echo esc_attr( $label ); ?>" title="<?php echo esc_attr( $label ); ?>" disabled><?php echo $inner; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Parts escaped above. ?></button>
		<?php endif; ?>
	</div>
	<?php
	return (string) ob_get_clean();
}

/** Register the reusable Reply entry-point block. */
function axismundi_act_register_reply_button_block() : void {
	register_block_type( dirname( __DIR__ ) . '/blocks/reply-button', array( 'render_callback' => 'axismundi_act_render_reply_button' ) );
}
add_action( 'init', 'axismundi_act_register_reply_button_block' );
