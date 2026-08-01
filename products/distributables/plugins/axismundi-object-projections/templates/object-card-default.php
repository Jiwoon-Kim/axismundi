<?php
/**
 * Feed card for a Note, Question, or any Object that is not an Article.
 *
 * These Objects are the post: the body is the thing, and a stream shows it in full.
 * There is no separate page holding "the rest", so nothing here defers to a link.
 *
 * @package AxismundiObjectProjections
 */

defined( 'ABSPATH' ) || exit;
?>
<!-- wp:group {"className":"axismundi-object-thread-item","layout":{"type":"constrained"}} -->
<div class="wp-block-group axismundi-object-thread-item">
	<!-- wp:axismundi/reply-context /-->
	<!-- wp:group {"tagName":"article","className":"axismundi-object-card","layout":{"type":"constrained"}} -->
	<article class="wp-block-group axismundi-object-card">
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
