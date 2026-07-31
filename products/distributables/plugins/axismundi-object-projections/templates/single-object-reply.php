<?php
/**
 * The canonical page for an Object that is a reply.
 *
 * It carries the same Object card as the root template, because a reply is not a lesser kind of
 * post. What differs is the frame: the ancestor is stated above the post rather than left for the
 * reader to infer, and the replies below are read as a continuation rather than as an opening.
 *
 * Keeping this separate from `single-object` is the point — the two can now be styled and
 * rearranged independently in the Site Editor without either growing conditionals for the other.
 */

defined( 'ABSPATH' ) || exit;
?>
<!-- wp:template-part {"slug":"header","area":"header","tagName":"header"} /-->

<!-- wp:group {"tagName":"main","align":"full","layout":{"type":"constrained"}} -->
<main class="wp-block-group alignfull">
	<!-- wp:group {"align":"wide","style":{"spacing":{"padding":{"top":"var:preset|spacing|500","bottom":"var:preset|spacing|500"}}},"layout":{"type":"default"}} -->
	<div class="wp-block-group alignwide" style="padding-top:var(--wp--preset--spacing--500);padding-bottom:var(--wp--preset--spacing--500)">
		<?php echo axismundi_op_object_card_pattern_content(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Trusted bundled block pattern. ?>
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
