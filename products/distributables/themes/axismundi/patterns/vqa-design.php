<?php
/**
 * Title: VQA Design Blocks
 * Slug: axismundi/vqa-design
 * Categories: axismundi
 * Inserter: false
 * Description: WordPress core DESIGN-family blocks for the Axismundi baseline VQA —
 *   buttons (fill / outline), columns, group (constrained / row / stack), separator,
 *   spacer, more, page-break. Block-rooted (wp:* / .wp-block-*), unstyled, so the
 *   blank/core render can be snapshotted before M3 binding. Dev-only specimen —
 *   excluded from the distributable ZIP via .distignore.
 *
 * @package Axismundi
 */
?>
<!-- wp:heading {"level":1} -->
<h1 class="wp-block-heading">VQA Design — core design-family blocks</h1>
<!-- /wp:heading -->

<!-- wp:heading -->
<h2 class="wp-block-heading">1. Buttons (fill / outline)</h2>
<!-- /wp:heading -->

<!-- wp:buttons -->
<div class="wp-block-buttons"><!-- wp:button {"url":"#"} -->
<div class="wp-block-button"><a class="wp-block-button__link wp-element-button" href="#">Filled button</a></div>
<!-- /wp:button -->

<!-- wp:button {"url":"#","className":"is-style-outline"} -->
<div class="wp-block-button is-style-outline"><a class="wp-block-button__link wp-element-button" href="#">Outline button</a></div>
<!-- /wp:button -->

<!-- wp:button {"url":"#","width":50} -->
<div class="wp-block-button has-custom-width wp-block-button__width-50"><a class="wp-block-button__link wp-element-button" href="#">50% width</a></div>
<!-- /wp:button --></div>
<!-- /wp:buttons -->

<!-- wp:buttons -->
<div class="wp-block-buttons"><!-- wp:button {"url":"#","className":"is-style-tonal"} -->
<div class="wp-block-button is-style-tonal"><a class="wp-block-button__link wp-element-button" href="#">Tonal button</a></div>
<!-- /wp:button -->

<!-- wp:button {"url":"#","className":"is-style-elevated"} -->
<div class="wp-block-button is-style-elevated"><a class="wp-block-button__link wp-element-button" href="#">Elevated button</a></div>
<!-- /wp:button -->

<!-- wp:button {"url":"#","className":"is-style-text"} -->
<div class="wp-block-button is-style-text"><a class="wp-block-button__link wp-element-button" href="#">Text button</a></div>
<!-- /wp:button --></div>
<!-- /wp:buttons -->

<!-- wp:heading -->
<h2 class="wp-block-heading">2. Columns</h2>
<!-- /wp:heading -->

<!-- wp:columns -->
<div class="wp-block-columns"><!-- wp:column -->
<div class="wp-block-column"><!-- wp:paragraph -->
<p>Column one — equal width by default. The quick brown fox.</p>
<!-- /wp:paragraph --></div>
<!-- /wp:column -->

<!-- wp:column -->
<div class="wp-block-column"><!-- wp:paragraph -->
<p>Column two — observe inter-column gap and stacking on narrow viewports.</p>
<!-- /wp:paragraph --></div>
<!-- /wp:column -->

<!-- wp:column -->
<div class="wp-block-column"><!-- wp:paragraph -->
<p>Column three.</p>
<!-- /wp:paragraph --></div>
<!-- /wp:column --></div>
<!-- /wp:columns -->

<!-- wp:heading -->
<h2 class="wp-block-heading">3. Group — constrained</h2>
<!-- /wp:heading -->

<!-- wp:group {"layout":{"type":"constrained"}} -->
<div class="wp-block-group"><!-- wp:heading {"level":3} -->
<h3 class="wp-block-heading">Group heading</h3>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>A constrained group — a bare layout container with no chrome on the blank theme.</p>
<!-- /wp:paragraph --></div>
<!-- /wp:group -->

<!-- wp:heading -->
<h2 class="wp-block-heading">4. Row (flex, horizontal)</h2>
<!-- /wp:heading -->

<!-- wp:group {"layout":{"type":"flex","flexWrap":"nowrap"}} -->
<div class="wp-block-group"><!-- wp:paragraph -->
<p>Row item A</p>
<!-- /wp:paragraph -->

<!-- wp:paragraph -->
<p>Row item B</p>
<!-- /wp:paragraph -->

<!-- wp:paragraph -->
<p>Row item C</p>
<!-- /wp:paragraph --></div>
<!-- /wp:group -->

<!-- wp:heading -->
<h2 class="wp-block-heading">5. Stack (flex, vertical)</h2>
<!-- /wp:heading -->

<!-- wp:group {"layout":{"type":"flex","orientation":"vertical"}} -->
<div class="wp-block-group"><!-- wp:paragraph -->
<p>Stack item 1</p>
<!-- /wp:paragraph -->

<!-- wp:paragraph -->
<p>Stack item 2</p>
<!-- /wp:paragraph --></div>
<!-- /wp:group -->

<!-- wp:heading -->
<h2 class="wp-block-heading">6. Separator &amp; Spacer</h2>
<!-- /wp:heading -->

<!-- wp:separator -->
<hr class="wp-block-separator has-alpha-channel-opacity"/>
<!-- /wp:separator -->

<!-- wp:paragraph -->
<p>Above: separator. Below: a 40px spacer, then a paragraph.</p>
<!-- /wp:paragraph -->

<!-- wp:spacer {"height":"40px"} -->
<div style="height:40px" aria-hidden="true" class="wp-block-spacer"></div>
<!-- /wp:spacer -->

<!-- wp:paragraph -->
<p>Paragraph after the spacer.</p>
<!-- /wp:paragraph -->

<!-- wp:heading -->
<h2 class="wp-block-heading">7. More &amp; Page Break</h2>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>Content before the More tag.</p>
<!-- /wp:paragraph -->

<!-- wp:more -->
<!--more-->
<!-- /wp:more -->

<!-- wp:paragraph -->
<p>Content after More. A Page Break (nextpage) follows — it paginates the post on the front end.</p>
<!-- /wp:paragraph -->

<!-- wp:nextpage -->
<!--nextpage-->
<!-- /wp:nextpage -->

<!-- wp:paragraph -->
<p>Content on the second page.</p>
<!-- /wp:paragraph -->
