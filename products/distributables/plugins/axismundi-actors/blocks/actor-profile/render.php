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
<article <?php echo $axismundi_actor_profile_wrapper; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Core-generated block wrapper attributes. ?>>
	<header class="ax-actor-profile__header">
		<?php if ( '' !== $axismundi_actor_profile_data['avatar'] ) : ?>
			<img class="ax-actor-profile__avatar" src="<?php echo esc_url( $axismundi_actor_profile_data['avatar'] ); ?>" alt="" width="96" height="96" />
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
