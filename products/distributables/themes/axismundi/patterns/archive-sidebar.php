<?php
/**
 * Title: Archive sidebar
 * Slug: axismundi/archive-sidebar
 * Description: Reusable archive aside — Categories, Tags, and date Archives as M3 sheet cards.
 * Categories: query
 *
 * Three "explore other content" cards for any archive: each is a filled sheet
 * (surface-container-low, 12px radius, 16px padding) with a title-medium heading
 * and a finished widget block. Categories and Archives use the M3 List row style
 * (divided rows, name + count); Tags use the Tags chip style. Kept as a standalone
 * file pattern so category.html / tag.html / date.html can reuse or reorder it.
 * The cards are global lists (all terms), not term-context siblings.
 *
 * @package Axismundi
 */

?>
<!-- wp:group {"metadata":{"name":"Archive sidebar"},"style":{"spacing":{"blockGap":"var:preset|spacing|400"}},"layout":{"type":"default"}} -->
<div class="wp-block-group"><!-- wp:group {"style":{"color":{"background":"var:preset|color|surface-container-low"},"border":{"radius":"12px"},"spacing":{"padding":"var:preset|spacing|200","blockGap":"var:preset|spacing|150"}},"layout":{"type":"constrained"}} -->
<div class="wp-block-group has-background" style="border-radius:12px;background-color:var(--wp--preset--color--surface-container-low);padding:var(--wp--preset--spacing--200)"><!-- wp:heading {"level":2,"fontSize":"title-medium"} -->
<h2 class="wp-block-heading has-title-medium-font-size"><?php echo esc_html_x( 'Categories', 'Archive sidebar card heading.', 'axismundi' ); ?></h2>
<!-- /wp:heading -->

<!-- wp:categories {"showPostCounts":true,"showHierarchy":true,"className":"is-style-row"} /--></div>
<!-- /wp:group -->

<!-- wp:group {"style":{"color":{"background":"var:preset|color|surface-container-low"},"border":{"radius":"12px"},"spacing":{"padding":"var:preset|spacing|200","blockGap":"var:preset|spacing|150"}},"layout":{"type":"constrained"}} -->
<div class="wp-block-group has-background" style="border-radius:12px;background-color:var(--wp--preset--color--surface-container-low);padding:var(--wp--preset--spacing--200)"><!-- wp:heading {"level":2,"fontSize":"title-medium"} -->
<h2 class="wp-block-heading has-title-medium-font-size"><?php echo esc_html_x( 'Tags', 'Archive sidebar card heading.', 'axismundi' ); ?></h2>
<!-- /wp:heading -->

<!-- wp:tag-cloud {"showTagCounts":true,"className":"is-style-tags"} /--></div>
<!-- /wp:group -->

<!-- wp:group {"style":{"color":{"background":"var:preset|color|surface-container-low"},"border":{"radius":"12px"},"spacing":{"padding":"var:preset|spacing|200","blockGap":"var:preset|spacing|150"}},"layout":{"type":"constrained"}} -->
<div class="wp-block-group has-background" style="border-radius:12px;background-color:var(--wp--preset--color--surface-container-low);padding:var(--wp--preset--spacing--200)"><!-- wp:heading {"level":2,"fontSize":"title-medium"} -->
<h2 class="wp-block-heading has-title-medium-font-size"><?php echo esc_html_x( 'Archives', 'Archive sidebar card heading.', 'axismundi' ); ?></h2>
<!-- /wp:heading -->

<!-- wp:archives {"showPostCounts":true,"className":"is-style-row"} /--></div>
<!-- /wp:group --></div>
<!-- /wp:group -->
