<?php
/** Public, cached Group directory. @package AxismundiActors */

defined( 'ABSPATH' ) || exit;

$ax_group_page  = max( 1, absint( get_query_var( 'page' ) ) );
$ax_group_limit = 30;
$ax_group_total = function_exists( 'axismundi_actors_count_public_groups' ) ? axismundi_actors_count_public_groups() : 0;
$ax_group_items = function_exists( 'axismundi_actors_get_public_groups' ) ? axismundi_actors_get_public_groups( $ax_group_limit, ( $ax_group_page - 1 ) * $ax_group_limit ) : array();
$ax_group_wrap  = get_block_wrapper_attributes( array( 'class' => 'ax-group-directory' ) );
?>
<section <?php echo $ax_group_wrap; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Core-generated attributes. ?>>
	<h1><?php esc_html_e( 'Groups', 'axismundi-actors' ); ?></h1>
	<?php if ( empty( $ax_group_items ) ) : ?>
		<p><?php esc_html_e( 'No public Groups are known to this server yet.', 'axismundi-actors' ); ?></p>
	<?php else : ?>
		<ul>
			<?php foreach ( $ax_group_items as $ax_group ) : ?>
				<?php $ax_group_handle = function_exists( 'axismundi_actors_federated_mention_name' ) ? axismundi_actors_federated_mention_name( $ax_group ) : '@' . $ax_group->get_preferred_username(); ?>
				<li><a href="<?php echo esc_url( axismundi_actors_profile_hub_url( $ax_group ) ); ?>">
					<?php echo axismundi_actors_avatar_html( $ax_group, 48 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- repository-built avatar markup. ?>
					<span><strong><?php echo esc_html( $ax_group->get_display_name() ?: $ax_group->get_preferred_username() ); ?></strong><small><?php echo esc_html( $ax_group_handle ); ?></small></span>
				</a></li>
			<?php endforeach; ?>
		</ul>
	<?php endif; ?>
	<?php if ( $ax_group_total > $ax_group_page * $ax_group_limit ) : ?>
		<nav class="ax-group-directory__pagination" aria-label="<?php esc_attr_e( 'Group directory pages', 'axismundi-actors' ); ?>"><a href="<?php echo esc_url( add_query_arg( 'page', $ax_group_page + 1, home_url( '/groups/' ) ) ); ?>"><?php esc_html_e( 'Next', 'axismundi-actors' ); ?></a></nav>
	<?php endif; ?>
</section>
