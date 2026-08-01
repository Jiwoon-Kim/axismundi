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
// The Core-generated `wp-block-axismundi-*` wrapper is the sole root contract; the hand-written
// alias named the same element twice.
$axismundi_actor_identity_wrapper     = get_block_wrapper_attributes( array( 'class' => 'is-' . $axismundi_actor_identity_variant ) );

/**
 * The display name as HTML, after escaping.
 *
 * A federated display name can carry custom-emoji shortcodes — `:mastodon: 김지운` is a
 * real one this site receives — and turning those into images is a decoration of the
 * visual surface only. It happens here, on the escaped string, rather than in
 * `axismundi_actors_profile_data()`, because the stored name must stay plain text: the
 * document title, OpenGraph, feeds, and the admin all publish it as-is, and Mastodon
 * does the same with its own profiles.
 *
 * @param string              $name_html Escaped display name.
 * @param array<string,mixed> $subject   Resolved block subject, including the Actor when known.
 */
$axismundi_actor_identity_name_display = (string) apply_filters(
	'axismundi_actors_display_name_html',
	esc_html( $axismundi_actor_identity_name ),
	$axismundi_actor_identity_subject
);
?>
<div <?php echo $axismundi_actor_identity_wrapper; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Core-generated block wrapper attributes. ?>>
	<?php if ( 'compact' === $axismundi_actor_identity_variant ) : ?>
		<?php $axismundi_actor_identity_name_html = '<span class="ax-actor-identity__name">' . $axismundi_actor_identity_name_display . '</span>'; ?>
		<?php echo '' !== $axismundi_actor_identity_url ? '<a href="' . esc_url( $axismundi_actor_identity_url ) . '" rel="author">' . $axismundi_actor_identity_name_html . '</a>' : $axismundi_actor_identity_name_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Values escaped above. ?>
		<?php if ( $axismundi_actor_identity_show_user && '' !== $axismundi_actor_identity_username ) : ?><span class="ax-actor-identity__preferred-username">@<?php echo esc_html( $axismundi_actor_identity_username ); ?></span><?php endif; ?>
		<?php if ( $axismundi_actor_identity_show_handle && '' !== $axismundi_actor_identity_handle && '@' . $axismundi_actor_identity_username !== $axismundi_actor_identity_handle ) : ?><span class="ax-actor-identity__handle"><?php echo esc_html( $axismundi_actor_identity_handle ); ?></span><?php endif; ?>
	<?php else : ?>
		<h1 class="wp-block-heading ax-actor-identity__name"><?php echo $axismundi_actor_identity_name_display; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped above, then decorated with emoji markup. ?></h1>
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
