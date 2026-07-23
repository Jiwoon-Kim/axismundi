<?php
/**
 * Actor Profile Fields server render.
 *
 * @package AxismundiActors
 */

defined( 'ABSPATH' ) || exit;

$axismundi_actor_fields_actor = axismundi_actors_resolve_block_actor( (string) ( $block->context['axismundi/actorId'] ?? '' ) );
if ( ! $axismundi_actor_fields_actor || ! $axismundi_actor_fields_actor->is_local() ) {
	return;
}
$axismundi_actor_fields = axismundi_actors_get_profile_fields( $axismundi_actor_fields_actor->get_identity_id() );
if ( empty( $axismundi_actor_fields ) ) {
	return;
}
$axismundi_actor_fields_wrapper = get_block_wrapper_attributes( array( 'class' => 'ax-actor-profile-fields-block' ) );
?>
<ul <?php echo $axismundi_actor_fields_wrapper; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Core-generated block wrapper attributes. ?>>
	<?php foreach ( $axismundi_actor_fields as $axismundi_actor_field ) : ?>
		<li class="ax-actor-profile-fields-block__item">
			<span class="ax-actor-profile-fields-block__name"><?php echo esc_html( $axismundi_actor_field['name'] ); ?></span>
			<a href="<?php echo esc_url( $axismundi_actor_field['url'] ); ?>" rel="me nofollow noopener noreferrer" target="_blank">
				<?php echo esc_html( preg_replace( '#^https?://#', '', untrailingslashit( $axismundi_actor_field['url'] ) ) ); ?>
			</a>
			<?php if ( 'verified' === $axismundi_actor_field['verification_status'] ) : ?>
				<span class="ax-actor-profile-fields-block__verified" aria-label="<?php esc_attr_e( 'Verified reciprocal link', 'axismundi-actors' ); ?>" title="<?php esc_attr_e( 'Verified reciprocal link', 'axismundi-actors' ); ?>">&#10003;</span>
			<?php endif; ?>
		</li>
	<?php endforeach; ?>
</ul>
