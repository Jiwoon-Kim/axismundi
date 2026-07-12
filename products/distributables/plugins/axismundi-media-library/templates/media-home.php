<?php
defined( 'ABSPATH' ) || exit;
?>
<!-- wp:template-part {"slug":"header","area":"header","tagName":"header"} /-->

<!-- wp:group {"tagName":"main","align":"full","layout":{"type":"constrained"}} -->
<main class="wp-block-group alignfull">
	<!-- wp:group {"align":"wide","style":{"spacing":{"padding":{"top":"var:preset|spacing|300","bottom":"var:preset|spacing|300"}}},"layout":{"type":"default"}} -->
	<div class="wp-block-group alignwide" style="padding-top:var(--wp--preset--spacing--300);padding-bottom:var(--wp--preset--spacing--300)">
		<!-- wp:heading {"level":1,"fontSize":"display-small"} -->
		<h1 class="wp-block-heading has-display-small-font-size"><?php esc_html_e( 'Media', 'axismundi-media-library' ); ?></h1>
		<!-- /wp:heading -->

		<!-- wp:query {"query":{"inherit":true},"layout":{"type":"default"}} -->
		<div class="wp-block-query">
			<!-- wp:post-template {"style":{"spacing":{"blockGap":"var:preset|spacing|300"}},"layout":{"type":"grid","columnCount":3}} -->
			<!-- wp:group {"tagName":"article","style":{"spacing":{"blockGap":"var:preset|spacing|100"}},"layout":{"type":"default"}} -->
			<article class="wp-block-group">
				<!-- wp:axismundi/media-preview /-->
				<!-- wp:post-title {"isLink":true,"fontSize":"title-medium"} /-->
				<!-- wp:post-date {"fontSize":"body-small"} /-->
			</article>
			<!-- /wp:group -->
			<!-- /wp:post-template -->

			<!-- wp:query-pagination {"paginationArrow":"arrow","layout":{"type":"flex","justifyContent":"space-between"},"style":{"spacing":{"margin":{"top":"var:preset|spacing|400"}}}} -->
			<!-- wp:query-pagination-previous /-->
			<!-- wp:query-pagination-numbers /-->
			<!-- wp:query-pagination-next /-->
			<!-- /wp:query-pagination -->

			<!-- wp:query-no-results -->
			<!-- wp:paragraph -->
			<p><?php esc_html_e( 'No public media found.', 'axismundi-media-library' ); ?></p>
			<!-- /wp:paragraph -->
			<!-- /wp:query-no-results -->
		</div>
		<!-- /wp:query -->
	</div>
	<!-- /wp:group -->
</main>
<!-- /wp:group -->

<!-- wp:template-part {"slug":"footer","area":"footer","tagName":"footer"} /-->
