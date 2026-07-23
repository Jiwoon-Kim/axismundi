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
// A profile can hold at most eight links (axismundi_actors_save_profile_fields()),
// so "Number of items" is bounded the same way Latest Posts bounds itself by its
// own query -- it can narrow a full profile for a compact placement, never invent
// rows the actor never authored.
$axismundi_actor_fields_limit  = max( 1, min( 8, (int) ( $attributes['itemsToShow'] ?? 8 ) ) );
$axismundi_actor_fields        = array_slice( $axismundi_actor_fields, 0, $axismundi_actor_fields_limit );
$axismundi_actor_fields_display = isset( $attributes['display'] ) && 'grid' === $attributes['display'] ? 'grid' : 'list';
$axismundi_actor_fields_columns = max( 2, min( 4, (int) ( $attributes['columns'] ?? 2 ) ) );
$axismundi_actor_fields_class   = 'ax-actor-profile-fields-block is-display-' . $axismundi_actor_fields_display;
if ( 'grid' === $axismundi_actor_fields_display ) {
	$axismundi_actor_fields_class .= ' columns-' . $axismundi_actor_fields_columns;
}
$axismundi_actor_fields_wrapper = get_block_wrapper_attributes( array( 'class' => $axismundi_actor_fields_class ) );
?>
<ul <?php echo $axismundi_actor_fields_wrapper; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Core-generated block wrapper attributes. ?>>
	<?php foreach ( $axismundi_actor_fields as $axismundi_actor_field ) : ?>
		<li class="ax-actor-profile-fields-block__item">
			<span class="ax-actor-profile-fields-block__name"><?php echo esc_html( $axismundi_actor_field['name'] ); ?></span>
			<a class="ax-actor-profile-fields-block__url" href="<?php echo esc_url( $axismundi_actor_field['url'] ); ?>" rel="me nofollow noopener noreferrer" target="_blank">
				<?php echo esc_html( preg_replace( '#^https?://#', '', untrailingslashit( $axismundi_actor_field['url'] ) ) ); ?>
			</a>
			<?php if ( 'verified' === $axismundi_actor_field['verification_status'] ) : ?>
				<span class="ax-actor-profile-fields-block__verified material-symbols-outlined" aria-label="<?php esc_attr_e( 'Verified reciprocal link', 'axismundi-actors' ); ?>" title="<?php esc_attr_e( 'Verified reciprocal link', 'axismundi-actors' ); ?>">verified</span>
			<?php endif; ?>
		</li>
	<?php endforeach; ?>
</ul>
