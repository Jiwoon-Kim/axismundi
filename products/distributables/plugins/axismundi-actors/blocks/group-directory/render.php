<?php
/** Public, cached Group directory. @package AxismundiActors */

defined( 'ABSPATH' ) || exit;

$axismundi_actor_group_page  = max( 1, absint( get_query_var( 'page' ) ) );
$axismundi_actor_group_limit = 30;
$axismundi_actor_group_total = function_exists( 'axismundi_actors_count_public_groups' ) ? axismundi_actors_count_public_groups() : 0;
$axismundi_actor_group_items = function_exists( 'axismundi_actors_get_public_managed_actors' ) ? axismundi_actors_get_public_managed_actors( $axismundi_actor_group_limit, ( $axismundi_actor_group_page - 1 ) * $axismundi_actor_group_limit ) : array();
// The Core-generated `wp-block-axismundi-*` wrapper is the stable hook; a hand-written alias
// beside it named the same element twice and was styled by nothing.
$axismundi_actor_group_wrapper = get_block_wrapper_attributes();
?>
<section <?php echo $axismundi_actor_group_wrapper; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Core-generated attributes. ?>>
	<h1><?php esc_html_e( 'Groups', 'axismundi-actors' ); ?></h1>
	<?php if ( empty( $axismundi_actor_group_items ) ) : ?>
		<p><?php esc_html_e( 'No public Groups are known to this server yet.', 'axismundi-actors' ); ?></p>
	<?php else : ?>
		<ul>
			<?php foreach ( $axismundi_actor_group_items as $axismundi_actor_group ) : ?>
				<?php $axismundi_actor_group_handle = function_exists( 'axismundi_actors_federated_mention_name' ) ? axismundi_actors_federated_mention_name( $axismundi_actor_group ) : '@' . $axismundi_actor_group->get_preferred_username(); ?>
				<li><a href="<?php echo esc_url( axismundi_actors_profile_hub_url( $axismundi_actor_group ) ); ?>">
					<?php echo axismundi_actors_avatar_html( $axismundi_actor_group, 48 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- repository-built avatar markup. ?>
					<span><strong><?php echo esc_html( $axismundi_actor_group->get_display_name() ?: $axismundi_actor_group->get_preferred_username() ); ?></strong><small><?php echo esc_html( $axismundi_actor_group_handle ); ?></small></span>
				</a></li>
			<?php endforeach; ?>
		</ul>
	<?php endif; ?>
	<?php if ( $axismundi_actor_group_total > $axismundi_actor_group_page * $axismundi_actor_group_limit ) : ?>
		<nav class="ax-group-directory__pagination" aria-label="<?php esc_attr_e( 'Group directory pages', 'axismundi-actors' ); ?>"><a href="<?php echo esc_url( add_query_arg( 'page', $axismundi_actor_group_page + 1, home_url( '/groups/' ) ) ); ?>"><?php esc_html_e( 'Next', 'axismundi-actors' ); ?></a></nav>
	<?php endif; ?>
</section>
