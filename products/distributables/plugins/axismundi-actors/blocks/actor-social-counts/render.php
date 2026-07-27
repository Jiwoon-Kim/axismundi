<?php
/**
 * Follower and following counts for the Actor in context.
 *
 * Two sources, never mixed. A local Actor's numbers come from our own accepted-Follow
 * ledger and link to the lists we host. A remote Actor's come from the count its own
 * server published, cached in the background ({@see includes/follow-counts.php}), and
 * link to the remote profile page rather than to a list here -- the accounts we happen
 * to know are not that account's followers, they are our fragment of them.
 *
 * A missing remote count renders as a link with no number. That is the honest shape:
 * null means we have not been told, and printing 0 would claim the account has nobody.
 *
 * @package AxismundiActors
 */

defined( 'ABSPATH' ) || exit;

$axismundi_counts_actor = axismundi_actors_resolve_block_actor( (string) ( $block->context['axismundi/actorId'] ?? '' ) );
if ( ! $axismundi_counts_actor instanceof Axismundi_Actor ) {
	return;
}

$axismundi_counts_items = array();
$axismundi_counts_labels = array(
	'followers' => __( 'Followers', 'axismundi-actors' ),
	'following' => __( 'Following', 'axismundi-actors' ),
);

if ( $axismundi_counts_actor->is_local() ) {
	// This asks about counts, not lists, because `count-only` keeps the numbers and
	// drops the links; only `private` removes the block entirely.
	if ( axismundi_actors_follow_counts_are_public( $axismundi_counts_actor )
		&& function_exists( 'axismundi_act_get_follower_count' )
		&& function_exists( 'axismundi_act_get_following_count' ) ) {
		$axismundi_counts_linked = axismundi_actors_follow_collections_are_public( $axismundi_counts_actor );
		$axismundi_counts_items  = array(
			'followers' => array(
				'count' => axismundi_act_get_follower_count( $axismundi_counts_actor->get_uri() ),
				'url'   => $axismundi_counts_linked ? axismundi_actors_follow_collection_url( $axismundi_counts_actor, 'followers' ) : '',
			),
			'following' => array(
				'count' => axismundi_act_get_following_count( $axismundi_counts_actor->get_uri() ),
				'url'   => $axismundi_counts_linked ? axismundi_actors_follow_collection_url( $axismundi_counts_actor, 'following' ) : '',
			),
		);
	}
} else {
	// The number is the remote server's; the page behind it is ours, and it is the only
	// place the distinction between that total and our own fragment is spelled out. The
	// link out to the origin lives there rather than here, so a reader meets the caveat
	// before they meet the partial list.
	$axismundi_counts_hosted = axismundi_actors_follow_collection_page_is_available( $axismundi_counts_actor );
	$axismundi_counts_origin = axismundi_actors_remote_follow_collection_url( $axismundi_counts_actor );
	foreach ( array_keys( $axismundi_counts_labels ) as $axismundi_counts_kind ) {
		$axismundi_counts_total = $axismundi_counts_actor->get_remote_follow_total( $axismundi_counts_kind );
		$axismundi_counts_url   = $axismundi_counts_hosted
			? axismundi_actors_follow_collection_url( $axismundi_counts_actor, $axismundi_counts_kind )
			: $axismundi_counts_origin;
		if ( null === $axismundi_counts_total && '' === $axismundi_counts_url ) {
			continue;
		}
		$axismundi_counts_items[ $axismundi_counts_kind ] = array(
			'count'    => $axismundi_counts_total,
			'url'      => $axismundi_counts_url,
			'external' => ! $axismundi_counts_hosted,
		);
	}
}

if ( empty( $axismundi_counts_items ) ) {
	return;
}

$axismundi_counts_wrapper = get_block_wrapper_attributes( array( 'class' => 'ax-actor-social-counts' ) );
?>
<ul <?php echo $axismundi_counts_wrapper; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Core-generated block wrapper attributes. ?>>
	<?php foreach ( $axismundi_counts_items as $axismundi_counts_kind => $axismundi_counts_item ) : ?>
		<li class="ax-actor-social-counts__item">
			<?php if ( '' !== (string) $axismundi_counts_item['url'] ) : ?>
				<a href="<?php echo esc_url( (string) $axismundi_counts_item['url'] ); ?>"<?php echo ! empty( $axismundi_counts_item['external'] ) ? ' target="_blank" rel="noopener noreferrer"' : ''; ?>>
					<span><?php echo esc_html( $axismundi_counts_labels[ $axismundi_counts_kind ] ); ?></span>
					<?php if ( null !== $axismundi_counts_item['count'] ) : ?><strong><?php echo esc_html( number_format_i18n( (int) $axismundi_counts_item['count'] ) ); ?></strong><?php endif; ?>
				</a>
			<?php else : ?>
				<span><?php echo esc_html( $axismundi_counts_labels[ $axismundi_counts_kind ] ); ?></span>
				<?php if ( null !== $axismundi_counts_item['count'] ) : ?><strong><?php echo esc_html( number_format_i18n( (int) $axismundi_counts_item['count'] ) ); ?></strong><?php endif; ?>
			<?php endif; ?>
		</li>
	<?php endforeach; ?>
</ul>
