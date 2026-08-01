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
		<!-- wp:axismundi/object-card-body /-->
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
