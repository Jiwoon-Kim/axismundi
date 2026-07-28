<?php
/**
 * Actor Biography server render.
 *
 * @package AxismundiActors
 */

defined( 'ABSPATH' ) || exit;

$axismundi_actor_bio_actor = axismundi_actors_resolve_block_actor( (string) ( $block->context['axismundi/actorId'] ?? '' ) );
if ( ! $axismundi_actor_bio_actor ) {
	return;
}
$axismundi_actor_bio_data     = axismundi_actors_profile_data( $axismundi_actor_bio_actor );
$axismundi_actor_bio_is_local = $axismundi_actor_bio_actor->is_local();
$axismundi_actor_bio_is_public = 'public' === $axismundi_actor_bio_actor->get_status();
$axismundi_actor_bio_has_body = '' !== $axismundi_actor_bio_data['summary'];
if ( ! $axismundi_actor_bio_has_body && $axismundi_actor_bio_is_local && $axismundi_actor_bio_is_public ) {
	return;
}
$axismundi_actor_bio_wrapper = get_block_wrapper_attributes( array( 'class' => 'ax-actor-biography' ) );
/**
 * The rendered summary, after sanitizing.
 *
 * Sanitizing first is the only safe order: a product decorating this string adds markup
 * that must survive, and a remote summary is untrusted until `wp_kses_post()` has run.
 * The same seam as `axismundi_actors_display_name_html`, one layer down — a remote Actor
 * declares its custom emoji once, and both its name and its biography are entitled to it.
 *
 * @param string          $summary_html Sanitized summary HTML.
 * @param Axismundi_Actor $actor        Actor the summary belongs to.
 */
$axismundi_actor_bio_summary = '' === $axismundi_actor_bio_data['summary']
	? ''
	: (string) apply_filters(
		'axismundi_actors_summary_html',
		wp_kses_post( wpautop( $axismundi_actor_bio_data['summary'] ) ),
		$axismundi_actor_bio_actor
	);
?>
<div <?php echo $axismundi_actor_bio_wrapper; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Core-generated block wrapper attributes. ?>>
	<?php if ( '' !== $axismundi_actor_bio_summary ) : ?>
		<div class="ax-actor-biography__summary"><?php echo $axismundi_actor_bio_summary; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Sanitized above, then decorated with emoji markup. ?></div>
	<?php endif; ?>
	<?php if ( $axismundi_actor_bio_is_local && ! $axismundi_actor_bio_is_public ) : ?>
		<p class="ax-actor-biography__preview"><?php esc_html_e( 'Private preview. This actor profile is not public.', 'axismundi-actors' ); ?></p>
	<?php endif; ?>
</div>
