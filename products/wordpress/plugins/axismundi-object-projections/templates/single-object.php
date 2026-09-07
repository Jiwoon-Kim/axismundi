<?php
/**
 * The canonical page for one Object: the Object itself, not a card standing in for it.
 *
 * This composes the document directly instead of echoing the shared card pattern. A card is a row
 * in a list — it exists to be one of many, and it carries the chrome, the identity row and the
 * ordering that being one of many requires. This page is the thing the card points at, and there is
 * only ever one of it here, so it states the Object's own parts in their own order.
 *
 * Note, Question and quote-post are one template on purpose. They are not three kinds of page:
 * each of `object-content`, `question` and `quote-context` renders only when the active Object
 * actually carries that part and renders nothing otherwise, so one composition covers all three
 * without a type switch and without three files to keep in step.
 *
 * `object-content-warning` must keep wrapping the body, the quote, the poll and the attachments. It
 * provides the `axismundi/objectDisclosure` context those blocks read, so an authored warning folds
 * all of them under one disclosure. Lifting any of them out of the wrapper would leave that part of
 * a sensitive Object visible with no cover.
 *
 * Articles never reach this template: `axismundi_op_object_template_slug()` routes them to
 * `single-object-article`, which is their full-text page.
 *
 * @package AxismundiObjectProjections
 */

defined( 'ABSPATH' ) || exit;
?>
<!-- wp:template-part {"slug":"header","area":"header","tagName":"header"} /-->

<!-- wp:group {"tagName":"main","align":"full","layout":{"type":"constrained"}} -->
<main class="wp-block-group alignfull">
	<!-- wp:group {"align":"wide","style":{"spacing":{"padding":{"top":"var:preset|spacing|500","bottom":"var:preset|spacing|500"},"blockGap":"var:preset|spacing|300"}},"layout":{"type":"constrained"}} -->
	<div class="wp-block-group alignwide" style="padding-top:var(--wp--preset--spacing--500);padding-bottom:var(--wp--preset--spacing--500)">
		<!-- wp:group {"tagName":"article","className":"axismundi-object-document","style":{"spacing":{"blockGap":"var:preset|spacing|200"}},"layout":{"type":"constrained"}} -->
		<article class="wp-block-group axismundi-object-document">
			<!-- wp:axismundi/reply-context /-->
			<!-- wp:axismundi/object-status /-->
			<?php
			/*
			 * A byline, not the card's identity row.
			 *
			 * `object-card-header` exists so every card shares one arrangement and the densities cannot
			 * drift; a template that saves a copy of that structure is exactly what it was built to stop.
			 * This page is the deliberate exception. It is the document, not a row in a list, so its
			 * attribution is set at reading size and is meant to differ from the card -- and a copy that
			 * is supposed to differ is not drift. Card changes will not reach here, which is the point.
			 */
			?>
			<!-- wp:group {"metadata":{"name":"Byline"},"style":{"spacing":{"margin":{"top":"var:preset|spacing|300","bottom":"var:preset|spacing|400"},"blockGap":"var:preset|spacing|125"}},"textColor":"on-surface-variant","fontSize":"body-small","layout":{"type":"flex","flexWrap":"wrap","justifyContent":"space-between"}} -->
			<div class="wp-block-group has-on-surface-variant-color has-text-color has-body-small-font-size" style="margin-top:var(--wp--preset--spacing--300);margin-bottom:var(--wp--preset--spacing--400)">
				<!-- wp:group {"style":{"spacing":{"blockGap":"var:preset|spacing|175"}},"layout":{"type":"flex","flexWrap":"nowrap"}} -->
				<div class="wp-block-group">
					<!-- wp:axismundi/actor-avatar {"size":48,"style":{"border":{"radius":{"topLeft":"24px","topRight":"24px","bottomLeft":"24px","bottomRight":"24px"}}}} /-->
					<!-- wp:group {"style":{"spacing":{"blockGap":"var:preset|spacing|0"},"layout":{"selfStretch":"fill","flexSize":null}},"layout":{"type":"flex","orientation":"vertical","flexWrap":"nowrap"}} -->
					<div class="wp-block-group">
						<!-- wp:axismundi/actor-name {"style":{"typography":{"fontStyle":"normal","fontWeight":"500"},"elements":{"link":{"color":{"text":"var:preset|color|on-surface"}}}},"textColor":"on-surface","fontSize":"body-large"} /-->
						<!-- wp:axismundi/actor-handle {"style":{"elements":{"link":{"color":{"text":"var:preset|color|on-surface-variant"}}}},"textColor":"on-surface-variant","fontSize":"body-small"} /-->
					</div>
					<!-- /wp:group -->
				</div>
				<!-- /wp:group -->
				<!-- wp:group {"style":{"spacing":{"blockGap":"var:preset|spacing|0"}},"layout":{"type":"flex","orientation":"vertical","justifyContent":"right"}} -->
				<div class="wp-block-group">
					<!-- wp:axismundi/object-type /-->
					<!-- wp:axismundi/object-date /-->
				</div>
				<!-- /wp:group -->
			</div>
			<!-- /wp:group -->
			<!-- wp:axismundi/object-featured-image /-->
			<!-- wp:axismundi/object-title /-->
			<!-- wp:axismundi/object-content-warning -->
				<!-- wp:axismundi/object-content /-->
				<!-- wp:axismundi/quote-context /-->
				<!-- wp:axismundi/question /-->
				<!-- wp:axismundi/object-attachments /-->
			<!-- /wp:axismundi/object-content-warning -->
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

		<!-- wp:axismundi/replies /-->
	</div>
	<!-- /wp:group -->
</main>
<!-- /wp:group -->

<?php
/*
 * One media dialog for the whole page, owned by Axismundi Dialogs. Object Projections
 * composes it here the same way the Actor profile composes the Activities feed: the
 * template places another plugin's surface without owning it. If Dialogs is inactive the
 * block is unregistered and renders nothing, and the attachments stay inline-only.
 */
?>
<!-- wp:axismundi/object-media-dialog /-->

<!-- wp:template-part {"slug":"footer","area":"footer","tagName":"footer"} /-->
