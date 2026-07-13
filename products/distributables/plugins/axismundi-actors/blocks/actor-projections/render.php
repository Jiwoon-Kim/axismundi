<?php
/**
 * Actor Projections server render.
 *
 * @package AxismundiActors
 */

defined( 'ABSPATH' ) || exit;

$axismundi_actor_projections_actor = axismundi_actors_current_actor();
if ( ! $axismundi_actor_projections_actor ) {
	return;
}
$axismundi_actor_projections_items = axismundi_actors_get_projections( $axismundi_actor_projections_actor );
if ( empty( $axismundi_actor_projections_items ) ) {
	return;
}
$axismundi_actor_projections_wrapper = get_block_wrapper_attributes( array( 'class' => 'ax-actor-projections' ) );
?>
<nav <?php echo $axismundi_actor_projections_wrapper; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Core-generated block wrapper attributes. ?> aria-label="<?php esc_attr_e( 'Actor profiles', 'axismundi-actors' ); ?>">
	<ul class="ax-actor-projections__list">
		<?php foreach ( $axismundi_actor_projections_items as $axismundi_actor_projection_item ) : ?>
			<li class="ax-actor-projections__item ax-actor-projections__item--<?php echo esc_attr( $axismundi_actor_projection_item['id'] ); ?>">
				<a href="<?php echo esc_url( $axismundi_actor_projection_item['url'] ); ?>">
					<span><?php echo esc_html( $axismundi_actor_projection_item['label'] ); ?></span>
					<?php if ( null !== $axismundi_actor_projection_item['count'] ) : ?>
						<?php /* translators: %d: number of readable items in this projection. */ ?>
						<span class="ax-actor-projections__count" aria-label="<?php echo esc_attr( sprintf( __( '%d items', 'axismundi-actors' ), $axismundi_actor_projection_item['count'] ) ); ?>"><?php echo esc_html( number_format_i18n( $axismundi_actor_projection_item['count'] ) ); ?></span>
					<?php endif; ?>
				</a>
			</li>
		<?php endforeach; ?>
	</ul>
</nav>
