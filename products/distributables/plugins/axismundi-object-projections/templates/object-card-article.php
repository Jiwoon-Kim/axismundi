<?php
/**
 * Feed card for an Article.
 *
 * An Article in a stream is a lead-in, not the piece: image, title, summary, and a way
 * through to the full text. There is deliberately no content block — the body lives on
 * the Article's own page, which is what "Read more" is for.
 *
 * That absence is what keeps the rules from piling up. With no body in the card there is
 * nothing for a post-level content warning to fold, so this card carries no
 * `object-content-warning` wrapper and needs no `hideInFeed` switch: a sensitive Article
 * is protected by `object-summary`, which obscures the summary in place and leaves the
 * "Read more" link reachable outside the cover.
 *
 * @package AxismundiObjectProjections
 */

defined( 'ABSPATH' ) || exit;
?>
<!-- wp:group {"className":"axismundi-object-thread-item","layout":{"type":"constrained"}} -->
<div class="wp-block-group axismundi-object-thread-item">
	<!-- wp:axismundi/reply-context /-->
	<!-- wp:group {"tagName":"article","className":"axismundi-object-card axismundi-object-card--article","layout":{"type":"constrained"}} -->
	<article class="wp-block-group axismundi-object-card axismundi-object-card--article">
		<?php require __DIR__ . '/parts/object-card-header.php'; ?>
		<?php
		/*
		 * A fixed height, because this image is a lead-in rather than the piece. A stream
		 * is a list of posts, and letting each Article's image take its intrinsic height
		 * makes the card as tall as the picture happens to be, pushing the next post off
		 * screen. The Article's own page shows the image at its real size.
		 */
		?>
		<!-- wp:axismundi/object-featured-image {"style":{"dimensions":{"height":"200px"}}} /-->
		<!-- wp:axismundi/object-title /-->
		<!-- wp:axismundi/object-summary {"moreText":"Read more"} /-->
		<!-- wp:axismundi/object-hashtags {"className":"is-style-tags"} /-->
		<!-- wp:axismundi/reaction-bar /-->
		<!-- wp:axismundi/interactions -->
			<!-- wp:axismundi/interaction {"type":"reply"} /-->
			<!-- wp:axismundi/interaction {"type":"like"} /-->
			<!-- wp:axismundi/interaction {"type":"announce","announceMenu":true} /-->
			<!-- wp:axismundi/interaction {"type":"reaction"} /-->
		<!-- /wp:axismundi/interactions -->
	</article>
	<!-- /wp:group -->
</div>
<!-- /wp:group -->
