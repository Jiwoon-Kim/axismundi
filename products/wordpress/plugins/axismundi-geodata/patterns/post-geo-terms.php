<?php
/**
 * Title: Post geo terms
 * Slug: axismundi-geodata/post-geo-terms
 * Description: Displays the current post's geographic area and place terms.
 * Categories: text
 * Block Types: core/post-content
 *
 * @package AxismundiGeodata
 */

defined( 'ABSPATH' ) || exit;

?>
<!-- wp:group {"style":{"spacing":{"margin":{"top":"var:preset|spacing|200"},"blockGap":"var:preset|spacing|200"}},"layout":{"type":"flex","flexWrap":"wrap"}} -->
<div class="wp-block-group" style="margin-top:var(--wp--preset--spacing--200)"><!-- wp:post-terms {"term":"axismundi_geotag","className":"is-style-tags"} /-->

<!-- wp:post-terms {"term":"axismundi_geo_area","className":"is-style-tags"} /--></div>
<!-- /wp:group -->
