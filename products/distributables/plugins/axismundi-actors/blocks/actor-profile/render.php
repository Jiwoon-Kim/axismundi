<?php
/**
 * Actor Profile server render.
 *
 * @package AxismundiActors
 */

defined( 'ABSPATH' ) || exit;

$axismundi_actor_profile_actor = axismundi_actors_current_actor();
if ( ! $axismundi_actor_profile_actor ) {
	return;
}
$axismundi_actor_profile_data    = axismundi_actors_profile_data( $axismundi_actor_profile_actor );
$axismundi_actor_profile_wrapper = get_block_wrapper_attributes( array( 'class' => 'ax-actor-profile' ) );
?>
<?php
$axismundi_actor_profile_header = axismundi_actors_header_html( $axismundi_actor_profile_actor );
$axismundi_actor_profile_avatar = axismundi_actors_avatar_html( $axismundi_actor_profile_actor, 96 );
$axismundi_actor_allowed_image  = array(
	'img' => array( 'src' => true, 'srcset' => true, 'sizes' => true, 'class' => true, 'alt' => true, 'width' => true, 'height' => true, 'loading' => true, 'decoding' => true ),
);
?>
<article <?php echo $axismundi_actor_profile_wrapper; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Core-generated block wrapper attributes. ?>>
	<?php if ( '' !== $axismundi_actor_profile_header ) : ?>
		<div class="ax-actor-profile__cover"><?php echo wp_kses( $axismundi_actor_profile_header, $axismundi_actor_allowed_image ); ?></div>
	<?php endif; ?>
	<header class="ax-actor-profile__header">
		<?php if ( '' !== $axismundi_actor_profile_avatar ) : ?>
			<?php echo wp_kses( $axismundi_actor_profile_avatar, $axismundi_actor_allowed_image ); ?>
		<?php endif; ?>
		<div class="ax-actor-profile__identity">
			<h1 class="wp-block-heading ax-actor-profile__name"><?php echo esc_html( $axismundi_actor_profile_data['name'] ); ?></h1>
			<p class="ax-actor-profile__meta">
				<span class="ax-actor-profile__handle"><?php echo esc_html( '@' . $axismundi_actor_profile_actor->get_preferred_username() ); ?></span>
				<span class="ax-actor-profile__type"><?php echo esc_html( $axismundi_actor_profile_actor->get_type() ); ?></span>
			</p>
		</div>
	</header>
	<?php if ( '' !== $axismundi_actor_profile_data['summary'] ) : ?>
		<div class="ax-actor-profile__summary"><?php echo wp_kses_post( wpautop( $axismundi_actor_profile_data['summary'] ) ); ?></div>
	<?php endif; ?>
	<?php if ( '' !== $axismundi_actor_profile_data['url'] ) : ?>
		<p class="ax-actor-profile__website"><a href="<?php echo esc_url( $axismundi_actor_profile_data['url'] ); ?>" rel="me"><?php echo esc_html( preg_replace( '#^https?://#', '', untrailingslashit( $axismundi_actor_profile_data['url'] ) ) ); ?></a></p>
	<?php endif; ?>
	<?php if ( 'public' !== $axismundi_actor_profile_actor->get_status() ) : ?>
		<p class="ax-actor-profile__preview"><?php esc_html_e( 'Private preview. This actor profile is not public.', 'axismundi-actors' ); ?></p>
	<?php endif; ?>
</article>
