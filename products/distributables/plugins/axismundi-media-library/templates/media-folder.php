<?php
defined( 'ABSPATH' ) || exit;
?>
<!-- wp:template-part {"slug":"header","area":"header","tagName":"header"} /-->

<!-- wp:group {"tagName":"main","align":"full","layout":{"type":"constrained"}} -->
<main class="wp-block-group alignfull">
	<!-- wp:group {"align":"wide","style":{"spacing":{"padding":{"top":"var:preset|spacing|300","bottom":"var:preset|spacing|300"}}},"layout":{"type":"default"}} -->
	<div class="wp-block-group alignwide" style="padding-top:var(--wp--preset--spacing--300);padding-bottom:var(--wp--preset--spacing--300)">
		<!-- wp:breadcrumbs {"align":"wide","style":{"spacing":{"padding":{"right":"var:preset|spacing|0","left":"var:preset|spacing|0","top":"var:preset|spacing|0","bottom":"var:preset|spacing|0"}}}} /-->
		<!-- wp:axismundi/media-archive-title {"fontSize":"display-small"} /-->
		<!-- wp:axismundi/media-collection {"source":"current"} /-->
	</div>
	<!-- /wp:group -->
</main>
<!-- /wp:group -->

<!-- wp:template-part {"slug":"footer","area":"footer","tagName":"footer"} /-->
