<?php
/**
 * A Followers or Following list for the Actor in context.
 *
 * For a local Actor the ledger is the collection: every accepted Follow edge is here,
 * so the count of rows and the size of the collection are the same number.
 *
 * For a remote Actor they are not, and the difference is the whole point of this page.
 * The rows are edges this server happened to observe; the collection belongs to the
 * other server and is usually far larger. Both numbers are shown, because showing only
 * the fragment is how Misskey ends up presenting 4 of pfefferle's 4,870 followers as
 * though that were the account's reach.
 *
 * @package AxismundiActors
 */

defined( 'ABSPATH' ) || exit;

$axismundi_follow_actor = axismundi_actors_resolve_block_actor( (string) ( $block->context['axismundi/actorId'] ?? '' ) );
$axismundi_follow_kind  = (string) get_query_var( 'ax_actor_collection' );
$axismundi_follow_kind  = in_array( $axismundi_follow_kind, array( 'followers', 'following' ), true )
	? $axismundi_follow_kind
	: ( 'following' === (string) ( $attributes['collection'] ?? '' ) ? 'following' : 'followers' );

if ( ! $axismundi_follow_actor instanceof Axismundi_Actor
	|| ! axismundi_actors_follow_collection_page_is_available( $axismundi_follow_actor )
	|| ! function_exists( 'axismundi_act_get_follow_collection_page' ) ) {
	return;
}

$axismundi_follow_page = max( 1, absint( get_query_var( 'page' ) ) );
$axismundi_follow_data = axismundi_act_get_follow_collection_page(
	'followers' === $axismundi_follow_kind ? 'object' : 'subject',
	$axismundi_follow_actor->get_uri(),
	$axismundi_follow_page,
	20
);

$axismundi_follow_is_remote = ! $axismundi_follow_actor->is_local();
// Null when the other server has not told us, which is rendered as no claim at all
// rather than as a total that happens to equal what we know.
$axismundi_follow_total = $axismundi_follow_is_remote
	? $axismundi_follow_actor->get_remote_follow_total( $axismundi_follow_kind )
	: null;

// The Core-generated `wp-block-axismundi-*` wrapper is the stable hook; a hand-written alias
// beside it named the same element twice and was styled by nothing.
$axismundi_follow_wrapper = get_block_wrapper_attributes();
?>
<section <?php echo $axismundi_follow_wrapper; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Core-generated block wrapper attributes. ?>>
	<h1><?php echo esc_html( 'followers' === $axismundi_follow_kind ? __( 'Followers', 'axismundi-actors' ) : __( 'Following', 'axismundi-actors' ) ); ?></h1>

	<?php if ( $axismundi_follow_is_remote ) : ?>
		<p class="ax-actor-follow-list__scope">
			<?php
			if ( null !== $axismundi_follow_total ) {
				printf(
					/* translators: 1: total reported by the remote server, 2: number this server knows. */
					esc_html__( '%1$s in total, %2$s known to this server.', 'axismundi-actors' ),
					'<strong>' . esc_html( number_format_i18n( $axismundi_follow_total ) ) . '</strong>',
					'<strong>' . esc_html( number_format_i18n( (int) $axismundi_follow_data['total'] ) ) . '</strong>'
				);
			} else {
				printf(
					/* translators: %s: number of accounts this server knows. */
					esc_html__( '%s known to this server. The full list lives on the original server.', 'axismundi-actors' ),
					'<strong>' . esc_html( number_format_i18n( (int) $axismundi_follow_data['total'] ) ) . '</strong>'
				);
			}
			$axismundi_follow_origin = axismundi_actors_remote_follow_collection_url( $axismundi_follow_actor );
			if ( '' !== $axismundi_follow_origin ) :
				?>
				<a class="ax-actor-follow-list__origin" href="<?php echo esc_url( $axismundi_follow_origin ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'View the full list on the original server', 'axismundi-actors' ); ?></a>
			<?php endif; ?>
		</p>
	<?php endif; ?>

	<?php if ( empty( $axismundi_follow_data['items'] ) ) : ?>
		<p><?php esc_html_e( 'No public accounts to show yet.', 'axismundi-actors' ); ?></p>
	<?php else : ?>
		<ul>
			<?php
			foreach ( $axismundi_follow_data['items'] as $axismundi_follow_uri ) :
				$axismundi_follow_related = axismundi_actors_get_by_uri( $axismundi_follow_uri );
				?>
				<li>
					<?php if ( $axismundi_follow_related instanceof Axismundi_Actor ) : ?>
						<?php
						/*
						 * The handle must carry its domain. A list of federated accounts is
						 * exactly where a bare `@thaumiel999` stops identifying anyone: the
						 * misskey.io and mastodon.social accounts of that name are different
						 * people and would otherwise render identically.
						 */
						$axismundi_follow_handle = function_exists( 'axismundi_actors_federated_mention_name' )
							? axismundi_actors_federated_mention_name( $axismundi_follow_related )
							: '@' . $axismundi_follow_related->get_preferred_username();
						$axismundi_follow_name = $axismundi_follow_related->get_display_name();
						?>
						<a href="<?php echo esc_url( axismundi_actors_profile_hub_url( $axismundi_follow_related ) ); ?>">
							<?php echo axismundi_actors_avatar_html( $axismundi_follow_related, 48 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Repository-built, already-escaped avatar markup. ?>
							<span>
								<strong><?php echo esc_html( '' !== $axismundi_follow_name ? $axismundi_follow_name : $axismundi_follow_related->get_preferred_username() ); ?></strong>
								<small><?php echo esc_html( $axismundi_follow_handle ); ?></small>
							</span>
						</a>
					<?php else : ?>
						<?php
						/*
						 * An edge whose Actor we never cached. The bare URI is an ActivityPub id,
						 * not a page a reader can use, so this shows the origin host and leaves
						 * the link unfollowed rather than dressing JSON up as a profile.
						 */
						?>
						<a href="<?php echo esc_url( $axismundi_follow_uri ); ?>" rel="nofollow noopener noreferrer">
							<span><strong><?php echo esc_html( wp_parse_url( $axismundi_follow_uri, PHP_URL_HOST ) ?: $axismundi_follow_uri ); ?></strong></span>
						</a>
					<?php endif; ?>
				</li>
			<?php endforeach; ?>
		</ul>
	<?php endif; ?>

	<?php if ( $axismundi_follow_page > 1 || ! empty( $axismundi_follow_data['has_more'] ) ) : ?>
		<nav class="ax-actor-follow-list__pagination" aria-label="<?php esc_attr_e( 'Follow list pages', 'axismundi-actors' ); ?>">
			<?php if ( $axismundi_follow_page > 1 ) : ?>
				<a href="<?php echo esc_url( axismundi_actors_follow_collection_url( $axismundi_follow_actor, $axismundi_follow_kind, $axismundi_follow_page - 1 ) ); ?>"><?php esc_html_e( 'Previous', 'axismundi-actors' ); ?></a>
			<?php endif; ?>
			<?php if ( ! empty( $axismundi_follow_data['has_more'] ) ) : ?>
				<a href="<?php echo esc_url( axismundi_actors_follow_collection_url( $axismundi_follow_actor, $axismundi_follow_kind, $axismundi_follow_page + 1 ) ); ?>"><?php esc_html_e( 'Next', 'axismundi-actors' ); ?></a>
			<?php endif; ?>
		</nav>
	<?php endif; ?>
</section>
