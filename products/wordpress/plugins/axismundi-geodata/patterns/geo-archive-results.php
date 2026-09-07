<?php
/**
 * Title: Geo archive results
 * Slug: axismundi-geodata/geo-archive-results
 * Description: A geo taxonomy archive (geotag / geo area): the tonal hero, a Query Map View of the current term, then a card-grid query feed beside a geo-specific sidebar.
 * Categories: query
 * Inserter: no
 *
 * A tonal hero (breadcrumbs + archive title + term description), an Axismundi Map
 * block (source "current": a geo_area archive follows the geotags on the current
 * query page, a geotag archive draws that single place — enhanced pagination swaps
 * the overlay without remounting the map), then a three-column card grid of the
 * inherited feed beside a geo-specific sidebar (geo_area categories, geotag cloud,
 * date archives). The map needs the Axismundi Map plugin and a front-end map
 * provider (Settings > Geodata); without them it degrades to a short notice and
 * the feed still renders.
 *
 * @package AxismundiGeodata
 */

defined( 'ABSPATH' ) || exit;

?>
<!-- wp:group {"tagName":"main","metadata":{"name":"Geo archive results"},"align":"full","style":{"spacing":{"padding":{"right":"var:preset|spacing|200","bottom":"var:preset|spacing|800","left":"var:preset|spacing|200"}}},"layout":{"type":"constrained"}} -->
<main class="wp-block-group alignfull" style="padding-right:var(--wp--preset--spacing--200);padding-bottom:var(--wp--preset--spacing--800);padding-left:var(--wp--preset--spacing--200)"><!-- wp:group {"align":"full","style":{"border":{"top":{"color":"var:preset|color|outline-variant","width":"1px"},"right":[],"bottom":{"color":"var:preset|color|outline-variant","width":"1px"},"left":[]}},"backgroundColor":"surface-container-low","layout":{"type":"constrained"}} -->
<div class="wp-block-group alignfull has-surface-container-low-background-color has-background" style="border-top-color:var(--wp--preset--color--outline-variant);border-top-width:1px;border-bottom-color:var(--wp--preset--color--outline-variant);border-bottom-width:1px"><!-- wp:group {"align":"wide","style":{"spacing":{"padding":{"top":"var:preset|spacing|700","bottom":"var:preset|spacing|700"},"blockGap":"var:preset|spacing|200"}},"layout":{"type":"constrained"}} -->
<div class="wp-block-group alignwide" style="padding-top:var(--wp--preset--spacing--700);padding-bottom:var(--wp--preset--spacing--700)"><!-- wp:breadcrumbs {"align":"wide","style":{"spacing":{"padding":{"right":"var:preset|spacing|0","left":"var:preset|spacing|0","top":"var:preset|spacing|0","bottom":"var:preset|spacing|0"}}}} /-->

<!-- wp:query-title {"type":"archive","align":"wide","fontSize":"display-medium"} /-->

<!-- wp:term-description {"align":"wide","className":"has-on-surface-variant-color has-text-color","style":{"spacing":{"margin":{"top":"var:preset|spacing|200"}}},"textColor":"on-surface-variant","fontSize":"body-large"} /--></div>
<!-- /wp:group --></div>
<!-- /wp:group -->

<!-- wp:axismundi/map {"source":"current","height":420,"align":"wide","style":{"spacing":{"margin":{"top":"var:preset|spacing|500"}}}} /-->

<!-- wp:columns {"align":"wide","style":{"spacing":{"blockGap":{"left":"var:preset|spacing|600"},"margin":{"top":"var:preset|spacing|500"}}}} -->
<div class="wp-block-columns alignwide" style="margin-top:var(--wp--preset--spacing--500)"><!-- wp:column -->
<div class="wp-block-column"><!-- wp:query {"queryId":32,"query":{"perPage":10,"pages":0,"offset":0,"postType":"post","order":"desc","orderBy":"date","author":"","search":"","exclude":[],"sticky":"","inherit":true,"taxQuery":null,"parents":[],"format":[]},"enhancedPagination":true,"align":"full","layout":{"type":"default"}} -->
<div class="wp-block-query alignfull"><!-- wp:query-total /-->

<!-- wp:post-template {"align":"wide","style":{"spacing":{"blockGap":"var:preset|spacing|100"}},"layout":{"type":"grid","columnCount":3}} -->
<!-- wp:group {"className":"is-style-card-elevated","style":{"border":{"radius":{"topLeft":"24px","topRight":"24px","bottomLeft":"24px","bottomRight":"24px"}},"spacing":{"blockGap":"var:preset|spacing|0"},"elements":{"link":{"color":{"text":"var:preset|color|on-surface"}}}},"textColor":"on-surface","layout":{"type":"default"}} -->
<div class="wp-block-group is-style-card-elevated has-on-surface-color has-text-color has-link-color" style="border-top-left-radius:24px;border-top-right-radius:24px;border-bottom-left-radius:24px;border-bottom-right-radius:24px"><!-- wp:post-featured-image {"aspectRatio":"2/1","style":{"border":{"radius":{"topLeft":"24px","topRight":"24px","bottomLeft":"24px","bottomRight":"24px"}}}} /-->

<!-- wp:group {"style":{"spacing":{"padding":{"right":"var:preset|spacing|300","left":"var:preset|spacing|300","top":"var:preset|spacing|300","bottom":"var:preset|spacing|300"},"blockGap":"var:preset|spacing|100"}},"layout":{"type":"default"}} -->
<div class="wp-block-group" style="padding-top:var(--wp--preset--spacing--300);padding-right:var(--wp--preset--spacing--300);padding-bottom:var(--wp--preset--spacing--300);padding-left:var(--wp--preset--spacing--300)"><!-- wp:post-title {"isLink":true,"fontSize":"headline-small"} /-->

<!-- wp:post-excerpt {"showMoreOnNewLine":false,"excerptLength":10,"fontSize":"body-medium"} /-->

<!-- wp:post-terms {"term":"axismundi_geotag","className":"is-style-tags","style":{"elements":{"link":{"color":{"text":"var:preset|color|secondary"}}},"typography":{"textAlign":"right"}},"textColor":"secondary"} /--></div>
<!-- /wp:group --></div>
<!-- /wp:group -->
<!-- /wp:post-template -->

<!-- wp:group {"style":{"spacing":{"margin":{"top":"var:preset|spacing|500"}}},"layout":{"type":"constrained"}} -->
<div class="wp-block-group" style="margin-top:var(--wp--preset--spacing--500)"><!-- wp:query-pagination {"paginationArrow":"arrow","showLabel":false,"layout":{"type":"flex","justifyContent":"space-between"}} -->
<!-- wp:query-pagination-previous /-->

<!-- wp:query-pagination-numbers /-->

<!-- wp:query-pagination-next /-->
<!-- /wp:query-pagination --></div>
<!-- /wp:group -->

<!-- wp:query-no-results -->
<!-- wp:group {"style":{"spacing":{"padding":{"top":"var:preset|spacing|600","bottom":"var:preset|spacing|600"}}},"layout":{"type":"constrained","contentSize":"480px"}} -->
<div class="wp-block-group" style="padding-top:var(--wp--preset--spacing--600);padding-bottom:var(--wp--preset--spacing--600)"><!-- wp:heading {"style":{"typography":{"textAlign":"center"}},"fontSize":"headline-medium"} -->
<h2 class="wp-block-heading has-text-align-center has-headline-medium-font-size"><?php echo esc_html_x( 'Nothing here yet', 'Geo archive empty-state heading.', 'axismundi-geodata' ); ?></h2>
<!-- /wp:heading -->

<!-- wp:paragraph {"className":"has-on-surface-variant-color has-text-color","style":{"typography":{"textAlign":"center"}},"textColor":"on-surface-variant","fontSize":"body-large"} -->
<p class="has-text-align-center has-on-surface-variant-color has-text-color has-body-large-font-size"><?php echo esc_html_x( 'There are no posts for this place yet. Try a nearby place or return home.', 'Geo archive empty-state guidance.', 'axismundi-geodata' ); ?></p>
<!-- /wp:paragraph --></div>
<!-- /wp:group -->
<!-- /wp:query-no-results --></div>
<!-- /wp:query --></div>
<!-- /wp:column -->

<!-- wp:column {"verticalAlignment":"stretch","width":"280px"} -->
<div class="wp-block-column is-vertically-aligned-stretch" style="flex-basis:280px"><!-- wp:group {"className":"is-position-sticky","style":{"position":{"type":"sticky","top":"0px"}},"layout":{"type":"default"}} -->
<div class="wp-block-group is-position-sticky"><!-- wp:group {"metadata":{"name":"Geo archive sidebar"},"style":{"spacing":{"blockGap":"var:preset|spacing|300","padding":{"top":"var:preset|spacing|300","bottom":"var:preset|spacing|300"}}},"layout":{"type":"default"}} -->
<div class="wp-block-group" style="padding-top:var(--wp--preset--spacing--300);padding-bottom:var(--wp--preset--spacing--300)"><!-- wp:group {"style":{"color":{"background":"var:preset|color|surface-container-low"},"border":{"radius":"12px"},"spacing":{"padding":"var:preset|spacing|200","blockGap":"var:preset|spacing|200"}},"layout":{"type":"default"}} -->
<div class="wp-block-group has-background" style="border-radius:12px;background-color:var(--wp--preset--color--surface-container-low);padding:var(--wp--preset--spacing--200)"><!-- wp:heading {"fontSize":"title-medium"} -->
<h2 class="wp-block-heading has-title-medium-font-size"><?php echo esc_html_x( 'Geo areas', 'Geo archive sidebar heading.', 'axismundi-geodata' ); ?></h2>
<!-- /wp:heading -->

<!-- wp:categories {"taxonomy":"axismundi_geo_area","showHierarchy":true,"showPostCounts":true,"showEmpty":true,"className":"is-style-row"} /--></div>
<!-- /wp:group -->

<!-- wp:group {"style":{"color":{"background":"var:preset|color|surface-container-low"},"border":{"radius":"12px"},"spacing":{"padding":"var:preset|spacing|200","blockGap":"var:preset|spacing|200"}},"layout":{"type":"default"}} -->
<div class="wp-block-group has-background" style="border-radius:12px;background-color:var(--wp--preset--color--surface-container-low);padding:var(--wp--preset--spacing--200)"><!-- wp:heading {"fontSize":"title-medium"} -->
<h2 class="wp-block-heading has-title-medium-font-size"><?php echo esc_html_x( 'Geotags', 'Geo archive sidebar heading.', 'axismundi-geodata' ); ?></h2>
<!-- /wp:heading -->

<!-- wp:tag-cloud {"taxonomy":"axismundi_geotag","showTagCounts":true,"className":"is-style-tags"} /--></div>
<!-- /wp:group -->

<!-- wp:group {"style":{"color":{"background":"var:preset|color|surface-container-low"},"border":{"radius":"12px"},"spacing":{"padding":"var:preset|spacing|200","blockGap":"var:preset|spacing|200"}},"layout":{"type":"default"}} -->
<div class="wp-block-group has-background" style="border-radius:12px;background-color:var(--wp--preset--color--surface-container-low);padding:var(--wp--preset--spacing--200)"><!-- wp:heading {"fontSize":"title-medium"} -->
<h2 class="wp-block-heading has-title-medium-font-size"><?php echo esc_html_x( 'Archives', 'Geo archive sidebar heading.', 'axismundi-geodata' ); ?></h2>
<!-- /wp:heading -->

<!-- wp:archives {"showPostCounts":true,"className":"is-style-row"} /--></div>
<!-- /wp:group --></div>
<!-- /wp:group --></div>
<!-- /wp:group --></div>
<!-- /wp:column --></div>
<!-- /wp:columns --></main>
<!-- /wp:group -->
