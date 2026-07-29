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
		<!-- wp:axismundi/object-featured-image /-->
		<!-- wp:axismundi/object-title /-->
		<!-- wp:axismundi/object-summary /-->
		<?php
		/*
		 * An authored content warning covers the whole post, so the body, the quote
		 * preview, the poll, and the attachments share one disclosure — matching how
		 * Mastodon and Misskey present a warned post. `reply-context`, the header,
		 * hashtags, and actions stay outside: they are the surrounding conversation,
		 * not the warned material. Without an authored warning this wrapper renders
		 * its children untouched.
		 */
		?>
		<!-- wp:axismundi/object-content-warning -->
			<!-- wp:axismundi/object-content /-->
			<!-- wp:axismundi/quote-context /-->
			<!-- wp:axismundi/question /-->
			<!-- wp:axismundi/object-attachments /-->
		<!-- /wp:axismundi/object-content-warning -->
		<!-- wp:axismundi/object-hashtags {"className":"is-style-tags"} /-->
		<!-- wp:axismundi/reaction-bar /-->
		<!-- wp:axismundi/object-actions -->
			<!-- wp:axismundi/reply-button /-->
			<!-- wp:axismundi/like-button /-->
			<!-- wp:axismundi/announce-button /-->
			<!-- wp:axismundi/reaction-button /-->
		<!-- /wp:axismundi/object-actions -->
	</article>
	<!-- /wp:group -->
</div>
<!-- /wp:group -->
