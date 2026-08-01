<?php
/**
 * The canonical page for an Object that belongs to a community.
 *
 * Object Projections renders the post itself; this template only supplies the frame a community
 * post needs and an ordinary one does not — which community it is in, and the vote it carries.
 * The card comes from OP's own pattern rather than being reproduced here, so a change to how an
 * Object looks reaches this page too instead of quietly diverging from every other surface.
 *
 * This is the Object route's counterpart to `single-ax_topic`. A Topic is a real post type and
 * uses post blocks; an Object document has no post behind it, so the same two-column frame is
 * built around the view model instead.
 */

defined( 'ABSPATH' ) || exit;
?>
<!-- wp:template-part {"slug":"header","area":"header","tagName":"header"} /-->

<!-- wp:group {"tagName":"main","align":"full","layout":{"type":"constrained"}} -->
<main class="wp-block-group alignfull">
	<!-- wp:columns {"align":"wide","style":{"spacing":{"padding":{"top":"var:preset|spacing|500","bottom":"var:preset|spacing|500"},"blockGap":{"top":"var:preset|spacing|400","left":"var:preset|spacing|400"}}}} -->
	<div class="wp-block-columns alignwide" style="padding-top:var(--wp--preset--spacing--500);padding-bottom:var(--wp--preset--spacing--500)">
		<!-- wp:column {"width":"66.66%"} -->
		<div class="wp-block-column" style="flex-basis:66.66%">
			<?php echo axismundi_op_object_card_pattern_content(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Trusted bundled block pattern from Object Projections. ?>
			<!-- wp:axismundi/interaction {"type":"vote"} /-->

			<!-- wp:axismundi/replies /-->

			<!-- wp:axismundi/object-replies /-->
		</div>
		<!-- /wp:column -->

		<!-- wp:column {"width":"33.33%"} -->
		<div class="wp-block-column" style="flex-basis:33.33%">
			<!-- wp:axismundi/community-card /-->
		</div>
		<!-- /wp:column -->
	</div>
	<!-- /wp:columns -->
</main>
<!-- /wp:group -->

<!-- wp:axismundi/object-media-dialog /-->

<!-- wp:template-part {"slug":"footer","area":"footer","tagName":"footer"} /-->
