<?php
/**
 * Actor Identity server render.
 *
 * Display name, username, and federated handle stay one block: they read as
 * a single unit and a viewer never needs to reposition them independently of
 * one another. Only the handle and the type badge are worth toggling.
 *
 * @package AxismundiActors
 */

defined( 'ABSPATH' ) || exit;

$axismundi_actor_identity_subject = axismundi_actors_resolve_block_subject( (string) ( $block->context['axismundi/actorId'] ?? '' ) );
if ( ! is_array( $axismundi_actor_identity_subject ) ) {
	return;
}
$axismundi_actor_identity_variant     = 'compact' === (string) ( $attributes['variant'] ?? '' ) ? 'compact' : 'profile';
$axismundi_actor_identity_name        = trim( (string) $axismundi_actor_identity_subject['name'] );
$axismundi_actor_identity_username    = ltrim( trim( (string) $axismundi_actor_identity_subject['preferred_username'] ), '@' );
$axismundi_actor_identity_handle      = trim( (string) $axismundi_actor_identity_subject['handle'] );
$axismundi_actor_identity_url         = esc_url( (string) $axismundi_actor_identity_subject['url'] );
$axismundi_actor_identity_show_handle = ! isset( $attributes['showHandle'] ) || (bool) $attributes['showHandle'];
$axismundi_actor_identity_show_user   = 'compact' === $axismundi_actor_identity_variant || ! empty( $attributes['showUsername'] );
$axismundi_actor_identity_show_type   = isset( $attributes['showTypeBadge'] ) && (bool) $attributes['showTypeBadge'];
$axismundi_actor_identity_wrapper     = get_block_wrapper_attributes( array( 'class' => 'ax-actor-identity is-' . $axismundi_actor_identity_variant ) );
?>
<div <?php echo $axismundi_actor_identity_wrapper; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Core-generated block wrapper attributes. ?>>
	<?php if ( 'compact' === $axismundi_actor_identity_variant ) : ?>
		<?php $axismundi_actor_identity_name_html = '<span class="ax-actor-identity__name">' . esc_html( $axismundi_actor_identity_name ) . '</span>'; ?>
		<?php echo '' !== $axismundi_actor_identity_url ? '<a href="' . esc_url( $axismundi_actor_identity_url ) . '" rel="author">' . $axismundi_actor_identity_name_html . '</a>' : $axismundi_actor_identity_name_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Values escaped above. ?>
		<?php if ( $axismundi_actor_identity_show_user && '' !== $axismundi_actor_identity_username ) : ?><span class="ax-actor-identity__preferred-username">@<?php echo esc_html( $axismundi_actor_identity_username ); ?></span><?php endif; ?>
		<?php if ( $axismundi_actor_identity_show_handle && '' !== $axismundi_actor_identity_handle && '@' . $axismundi_actor_identity_username !== $axismundi_actor_identity_handle ) : ?><span class="ax-actor-identity__handle"><?php echo esc_html( $axismundi_actor_identity_handle ); ?></span><?php endif; ?>
	<?php else : ?>
		<h1 class="wp-block-heading ax-actor-identity__name"><?php echo esc_html( $axismundi_actor_identity_name ); ?></h1>
	<?php endif; ?>
	<?php if ( 'profile' === $axismundi_actor_identity_variant && ( $axismundi_actor_identity_show_handle || $axismundi_actor_identity_show_type ) ) : ?>
		<p class="ax-actor-identity__meta">
			<?php if ( $axismundi_actor_identity_show_handle ) : ?>
				<span class="ax-actor-identity__handle"><?php echo esc_html( $axismundi_actor_identity_handle ); ?></span>
			<?php endif; ?>
			<?php if ( $axismundi_actor_identity_show_type ) : ?>
				<span class="ax-actor-identity__type"><?php echo esc_html( (string) $axismundi_actor_identity_subject['type'] ); ?></span>
			<?php endif; ?>
		</p>
	<?php endif; ?>
</div>
