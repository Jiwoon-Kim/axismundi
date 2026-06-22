<?php
/**
 * Title: VQA — Taxonomy & widget block specimens
 * Slug: axismundi/vqa-taxonomy
 * Inserter: no
 *
 * Renders the taxonomy / widget blocks that DO get real data on an ordinary page, so
 * their DOM, classes, and computed styles can be extracted in one pass before the
 * base / variation / selector contracts are written. Archive-context blocks
 * (query-title type:archive, term-name, term-count, term-description) are intentionally
 * NOT here — they need a real term archive and are inspected on a live ?cat= page.
 * Dev-only harness — excluded from the distributable ZIP via .distignore.
 *
 * @package Axismundi
 */

?>
<!-- wp:group {"metadata":{"patternName":"axismundi/vqa-taxonomy","name":"VQA — Taxonomy & widget block specimens"},"align":"wide","style":{"spacing":{"blockGap":"var:preset|spacing|700"}},"layout":{"type":"constrained"}} -->
<div class="wp-block-group alignwide"><!-- wp:heading {"fontSize":"headline-medium"} -->
<h2 class="wp-block-heading has-headline-medium-font-size">Taxonomy &amp; widget block specimens</h2>
<!-- /wp:heading -->

<!-- wp:paragraph {"textColor":"on-surface-variant","fontSize":"body-small"} -->
<p class="has-on-surface-variant-color has-text-color has-body-small-font-size">DOM / class / computed-style extraction harness. Each block rendered with real data. Archive-identity blocks live on a real term archive, not here.</p>
<!-- /wp:paragraph -->

<!-- wp:heading {"fontSize":"label-large"} -->
<h2 class="wp-block-heading has-label-large-font-size">1 · Categories — list (hierarchy + counts)</h2>
<!-- /wp:heading -->

<!-- wp:categories {"showHierarchy":true,"showPostCounts":true,"showEmpty":true} /-->

<!-- wp:heading {"fontSize":"label-large"} -->
<h2 class="wp-block-heading has-label-large-font-size">2 · Categories — dropdown</h2>
<!-- /wp:heading -->

<!-- wp:categories {"displayAsDropdown":true,"showHierarchy":true,"showPostCounts":true,"showEmpty":true} /-->

<!-- wp:heading {"fontSize":"label-large"} -->
<h2 class="wp-block-heading has-label-large-font-size">3 · Tags (categories taxonomy:post_tag) — list + dropdown</h2>
<!-- /wp:heading -->

<!-- wp:categories {"taxonomy":"post_tag","showPostCounts":true,"showEmpty":true} /-->

<!-- wp:categories {"taxonomy":"post_tag","displayAsDropdown":true,"showPostCounts":true,"showEmpty":true} /-->

<!-- wp:heading {"fontSize":"label-large"} -->
<h2 class="wp-block-heading has-label-large-font-size">4 · Archives — list</h2>
<!-- /wp:heading -->

<!-- wp:archives {"showPostCounts":true,"type":"daily"} /-->

<!-- wp:heading {"fontSize":"label-large"} -->
<h2 class="wp-block-heading has-label-large-font-size">5 · Archives — dropdown</h2>
<!-- /wp:heading -->

<!-- wp:archives {"displayAsDropdown":true,"showPostCounts":true} /-->

<!-- wp:heading {"fontSize":"label-large"} -->
<h2 class="wp-block-heading has-label-large-font-size">6 · Tag Cloud — default</h2>
<!-- /wp:heading -->

<!-- wp:tag-cloud {"showTagCounts":true} /-->

<!-- wp:heading {"fontSize":"label-large"} -->
<h2 class="wp-block-heading has-label-large-font-size">7 · Tag Cloud — outline variation</h2>
<!-- /wp:heading -->

<!-- wp:tag-cloud {"taxonomy":"category","showTagCounts":true,"className":"is-style-outline"} /-->

<!-- wp:heading {"fontSize":"label-large"} -->
<h2 class="wp-block-heading has-label-large-font-size">8 · Calendar</h2>
<!-- /wp:heading -->

<!-- wp:calendar /-->

<!-- wp:heading {"fontSize":"label-large"} -->
<h2 class="wp-block-heading has-label-large-font-size">9 · Page List (widget context, outside navigation)</h2>
<!-- /wp:heading -->

<!-- wp:page-list {"axismundiPageListIcons":false} /-->

<!-- wp:page-list /-->

<!-- wp:heading {"fontSize":"label-large"} -->
<h2 class="wp-block-heading has-label-large-font-size">10 · Terms Query — list (name + count)</h2>
<!-- /wp:heading -->

<!-- wp:terms-query -->
<div class="wp-block-terms-query"><!-- wp:term-template {"layout":{"type":"default","columnCount":3}} -->
<!-- wp:group {"style":{"spacing":{"blockGap":"var:preset|spacing|50"}},"layout":{"type":"flex","flexWrap":"nowrap"}} -->
<div class="wp-block-group"><!-- wp:term-name {"isLink":true} /-->

<!-- wp:term-count /--></div>
<!-- /wp:group -->
<!-- /wp:term-template --></div>
<!-- /wp:terms-query -->

<!-- wp:heading {"fontSize":"label-large"} -->
<h2 class="wp-block-heading has-label-large-font-size">11 · Terms Query — grid (columnCount 3)</h2>
<!-- /wp:heading -->

<!-- wp:terms-query {"termQuery":{"perPage":0,"taxonomy":"category","order":"asc","orderBy":"name","include":[],"hideEmpty":false,"showNested":true,"inherit":false}} -->
<div class="wp-block-terms-query"><!-- wp:term-template {"layout":{"type":"grid","columnCount":3}} -->
<!-- wp:group {"style":{"spacing":{"blockGap":"var:preset|spacing|50"}},"layout":{"type":"flex","flexWrap":"nowrap"}} -->
<div class="wp-block-group"><!-- wp:term-name {"isLink":true} /-->

<!-- wp:term-count /--></div>
<!-- /wp:group -->
<!-- /wp:term-template --></div>
<!-- /wp:terms-query -->

<!-- wp:heading {"fontSize":"label-large"} -->
<h2 class="wp-block-heading has-label-large-font-size">12 · Post Terms inside a Query Loop (category + tag)</h2>
<!-- /wp:heading -->

<!-- wp:query {"queryId":50,"query":{"perPage":2,"pages":0,"offset":0,"postType":"post","order":"desc","orderBy":"date","author":"","search":"","exclude":[],"sticky":"","inherit":false,"taxQuery":null,"parents":[]},"layout":{"type":"default"}} -->
<div class="wp-block-query"><!-- wp:post-template {"style":{"spacing":{"blockGap":"var:preset|spacing|200"}},"layout":{"type":"default"}} -->
<!-- wp:post-title {"isLink":true,"fontSize":"title-large"} /-->

<!-- wp:post-terms {"term":"category","className":"is-style-badge"} /-->

<!-- wp:post-terms {"term":"post_tag","className":"is-style-tags"} /-->
<!-- /wp:post-template --></div>
<!-- /wp:query --></div>
<!-- /wp:group -->
