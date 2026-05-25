<?php
/**
 * Title: 404
 * Slug: axismundi-pilot/hidden-404
 * Inserter: false
 */
?>
<!-- wp:group {"style":{"spacing":{"padding":{"right":"0","left":"0"}}},"layout":{"type":"constrained"}} -->
<div class="wp-block-group" style="padding-right:0;padding-left:0">
	<!-- wp:columns {"align":"wide","style":{"spacing":{"blockGap":{"top":"var:preset|spacing|50","left":"var:preset|spacing|50"}}}} -->
	<div class="wp-block-columns alignwide">
		<!-- wp:column -->
		<div class="wp-block-column">
			<!-- wp:cover {"overlayColor":"surface-variant","isUserOverlayColor":true,"minHeight":420,"minHeightUnit":"px","style":{"border":{"radius":"20px"}},"layout":{"type":"constrained"}} -->
			<div class="wp-block-cover" style="border-radius:20px;min-height:420px"><span aria-hidden="true" class="wp-block-cover__background has-surface-variant-background-color has-background-dim-100 has-background-dim"></span><div class="wp-block-cover__inner-container"></div></div>
			<!-- /wp:cover -->
		</div>
		<!-- /wp:column -->
		<!-- wp:column {"verticalAlignment":"bottom"} -->
		<div class="wp-block-column is-vertically-aligned-bottom">
			<!-- wp:group {"layout":{"type":"default"}} -->
			<div class="wp-block-group">
				<!-- wp:heading {"level":1} -->
				<h1 class="wp-block-heading">
					<?php echo esc_html_x( 'Page not found', '404 error message', 'axismundi-pilot' ); ?>
				</h1>
				<!-- /wp:heading -->
				<!-- wp:paragraph -->
				<p><?php echo esc_html_x( 'The page you are looking for doesn\'t exist, or it has been moved. Please try searching using the form below.', '404 error message', 'axismundi-pilot' ); ?></p>
				<!-- /wp:paragraph -->
				<!-- wp:pattern {"slug":"axismundi-pilot/hidden-search"} /-->
			</div>
			<!-- /wp:group -->
		</div>
		<!-- /wp:column -->
	</div>
	<!-- /wp:columns -->
</div>
<!-- /wp:group -->
