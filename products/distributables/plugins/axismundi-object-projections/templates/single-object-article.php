<?php
/**
 * Canonical page for a cached remote Article.
 *
 * Reaching this page is the reader answering "Read more", so the piece is shown: full
 * content, no lead-in, and no cover over it. The summary block is absent rather than
 * un-obscured — on the page that holds the text, a teaser for that same text is noise,
 * and leaving it out means the spoiler rule never has to learn about surfaces.
 *
 * There is no content-warning wrapper here, deliberately. `object-content-warning` is the
 * Note and Question disclosure, and `axismundi_op_object_folds_behind_warning()` returns
 * false for every Article — so the wrapper would render its children untouched on every
 * page it ever appeared on. A block that can only ever be transparent is not a boundary,
 * it is scenery, and it would suggest a fold this template never performs. An Article's
 * protection is the summary spoiler, which lives on the cards that carry a summary.
 *
 * @package AxismundiObjectProjections
 */

defined( 'ABSPATH' ) || exit;
?>
<!-- wp:template-part {"slug":"header","area":"header","tagName":"header"} /-->

<!-- wp:group {"tagName":"main","align":"full","layout":{"type":"constrained"}} -->
<main class="wp-block-group alignfull">
	<!-- wp:group {"align":"wide","style":{"spacing":{"padding":{"top":"var:preset|spacing|500","bottom":"var:preset|spacing|500"}}},"layout":{"type":"default"}} -->
	<div class="wp-block-group alignwide" style="padding-top:var(--wp--preset--spacing--500);padding-bottom:var(--wp--preset--spacing--500)">
		<!-- wp:group {"tagName":"article","className":"axismundi-object-card axismundi-object-card--article-full","layout":{"type":"constrained"}} -->
		<article class="wp-block-group axismundi-object-card axismundi-object-card--article-full">
			<?php require __DIR__ . '/parts/object-card-header.php'; ?>
			<!-- wp:axismundi/object-featured-image {"style":{"dimensions":{"height":"300px"}}} /-->
			<!-- wp:axismundi/object-title /-->
			<!-- wp:axismundi/object-content /-->
			<!-- wp:axismundi/quote-context /-->
			<!-- wp:axismundi/question /-->
			<!-- wp:axismundi/object-attachments /-->
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
		<!-- wp:axismundi/replies /-->
	</div>
	<!-- /wp:group -->
</main>
<!-- /wp:group -->

<!-- wp:template-part {"slug":"footer","area":"footer","tagName":"footer"} /-->
