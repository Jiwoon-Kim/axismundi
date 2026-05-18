<?php
/**
 * Title: Axismundi card list
 * Slug: axismundi-pilot/card-list
 * Categories: axismundi-composition
 * Description: Card surfaces and segmented List styling composed from core blocks.
 */
?>
<!-- wp:columns -->
<div class="wp-block-columns">
	<!-- wp:column -->
	<div class="wp-block-column">
		<!-- wp:group {"className":"is-style-card-elevated","layout":{"type":"constrained"}} -->
		<div class="wp-block-group is-style-card-elevated">
			<!-- wp:heading {"level":2} -->
			<h2>Component mapping</h2>
			<!-- /wp:heading -->

			<!-- wp:paragraph -->
			<p>Wave 1 components become block styles, patterns, and theme surfaces without custom blocks.</p>
			<!-- /wp:paragraph -->

			<!-- wp:list {"className":"is-style-list-segmented"} -->
			<ul class="wp-block-list is-style-list-segmented">
				<li>Button styles use core/button variants.</li>
				<li>Card surfaces use core/group styles.</li>
				<li>List specimens use core/list styles.</li>
			</ul>
			<!-- /wp:list -->
		</div>
		<!-- /wp:group -->
	</div>
	<!-- /wp:column -->

	<!-- wp:column -->
	<div class="wp-block-column">
		<!-- wp:group {"className":"is-style-card-outlined","layout":{"type":"constrained"}} -->
		<div class="wp-block-group is-style-card-outlined">
			<!-- wp:heading {"level":2} -->
			<h2>Pilot constraints</h2>
			<!-- /wp:heading -->

			<!-- wp:paragraph -->
			<p>The Pilot consumes existing ontology assets and avoids Carousel, custom block registration, and plugin-only behavior.</p>
			<!-- /wp:paragraph -->

			<!-- wp:separator {"className":"is-style-divider-inset"} -->
			<hr class="wp-block-separator has-alpha-channel-opacity is-style-divider-inset"/>
			<!-- /wp:separator -->

			<!-- wp:buttons -->
			<div class="wp-block-buttons">
				<!-- wp:button {"className":"is-style-tonal"} -->
				<div class="wp-block-button is-style-tonal"><a class="wp-block-button__link wp-element-button">Review scope</a></div>
				<!-- /wp:button -->
			</div>
			<!-- /wp:buttons -->
		</div>
		<!-- /wp:group -->
	</div>
	<!-- /wp:column -->
</div>
<!-- /wp:columns -->
