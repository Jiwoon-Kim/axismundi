<?php
/**
 * The feed card for every Object, whatever kind it is.
 *
 * There were two of these and they had come to differ by a single class. Everything that once
 * made an Article's card a different document — no body, no content-warning wrapper, a lead image
 * pinned to a fixed height, a route out to the full piece — moved into `object-card-body`, which
 * is the one region that depends on what it is holding. What is left is the part that never
 * depended on it: who posted, what it is in reply to, the tags, the reactions, and what a reader
 * can do about it.
 *
 * The variant only names the card. `--article` carries no styles today, but the canonical Article
 * page emits it too, and a card that stopped emitting it would leave the two surfaces disagreeing
 * about what a theme can hook.
 *
 * @package AxismundiObjectProjections
 */

defined( 'ABSPATH' ) || exit;

$axismundi_op_card_variant = isset( $axismundi_op_card_variant ) && 'article' === $axismundi_op_card_variant ? 'article' : 'default';
$axismundi_op_card_class   = 'article' === $axismundi_op_card_variant
	? 'axismundi-object-card axismundi-object-card--article'
	: 'axismundi-object-card';
?>
<!-- wp:group {"className":"axismundi-object-thread-item","layout":{"type":"constrained"}} -->
<div class="wp-block-group axismundi-object-thread-item">
	<!-- wp:axismundi/reply-context /-->
	<!-- wp:group {"tagName":"article","className":"<?php echo esc_attr( $axismundi_op_card_class ); ?>","layout":{"type":"constrained"}} -->
	<article class="wp-block-group <?php echo esc_attr( $axismundi_op_card_class ); ?>">
		<?php require __DIR__ . '/parts/object-card-header.php'; ?>
		<!-- wp:axismundi/object-card-body /-->
		<!-- wp:axismundi/object-hashtags {"className":"is-style-tags"} /-->
		<!-- wp:axismundi/reaction-bar /-->
		<!-- wp:axismundi/interactions -->
			<!-- wp:axismundi/interaction {"type":"reply"} /-->
			<!-- wp:axismundi/interaction {"type":"like"} /-->

			<!-- wp:axismundi/interaction {"type":"dislike"} /-->
			<!-- wp:axismundi/interaction {"type":"announce","announceMenu":true} /-->
			<!-- wp:axismundi/interaction {"type":"reaction"} /-->
		<!-- /wp:axismundi/interactions -->
	</article>
	<!-- /wp:group -->
</div>
<!-- /wp:group -->
