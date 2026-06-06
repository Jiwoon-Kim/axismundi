<?php
/**
 * Title: VQA — Prose primitives
 * Slug: axismundi/vqa-prose
 * Inserter: false
 * Description: Phase 1 baseline harness. Every core text/prose primitive block,
 *   unstyled, so the blank/core render can be snapshotted before any M3 binding.
 *   Dev-only specimen — excluded from the distributable ZIP via .distignore.
 *
 * @package Axismundi
 */
?>
<!-- wp:heading {"level":1} -->
<h1 class="wp-block-heading">Prose VQA — H1 Display heading</h1>
<!-- /wp:heading -->

<!-- wp:heading {"level":2} -->
<h2 class="wp-block-heading">H2 section heading</h2>
<!-- /wp:heading -->

<!-- wp:heading {"level":3} -->
<h3 class="wp-block-heading">H3 subsection heading</h3>
<!-- /wp:heading -->

<!-- wp:heading {"level":4} -->
<h4 class="wp-block-heading">H4 heading</h4>
<!-- /wp:heading -->

<!-- wp:heading {"level":5} -->
<h5 class="wp-block-heading">H5 heading</h5>
<!-- /wp:heading -->

<!-- wp:heading {"level":6} -->
<h6 class="wp-block-heading">H6 heading</h6>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>Body paragraph. The quick brown fox jumps over the lazy dog. This sentence is intentionally long enough to wrap across multiple lines so that line-height, measure (line length), and paragraph rhythm are observable in the baseline snapshot before any Material Design 3 typography is bound. It also contains an <a href="#">inline link</a>, <strong>bold</strong>, <em>italic</em>, and <code>inline code</code>.</p>
<!-- /wp:paragraph -->

<!-- wp:paragraph {"dropCap":true} -->
<p class="has-drop-cap">Drop-cap paragraph. The first letter renders as a drop cap when the block supports it, which is a useful tell for how the theme treats first-letter typography.</p>
<!-- /wp:paragraph -->

<!-- wp:list -->
<ul class="wp-block-list"><!-- wp:list-item --><li>Unordered list item one</li><!-- /wp:list-item --><!-- wp:list-item --><li>Unordered list item two<!-- wp:list --><ul class="wp-block-list"><!-- wp:list-item --><li>Nested item</li><!-- /wp:list-item --></ul><!-- /wp:list --></li><!-- /wp:list-item --><!-- wp:list-item --><li>Unordered list item three</li><!-- /wp:list-item --></ul>
<!-- /wp:list -->

<!-- wp:list {"ordered":true} -->
<ol class="wp-block-list"><!-- wp:list-item --><li>Ordered item one</li><!-- /wp:list-item --><!-- wp:list-item --><li>Ordered item two</li><!-- /wp:list-item --><!-- wp:list-item --><li>Ordered item three</li><!-- /wp:list-item --></ol>
<!-- /wp:list -->

<!-- wp:quote -->
<blockquote class="wp-block-quote"><!-- wp:paragraph --><p>A block quote. Default core quote rendering — note the left rule (if any), indentation, and citation typography.</p><!-- /wp:paragraph --><cite>Citation author</cite></blockquote>
<!-- /wp:quote -->

<!-- wp:pullquote -->
<figure class="wp-block-pullquote"><blockquote><p>A pullquote — larger, centered emphasis text.</p><cite>Pullquote source</cite></blockquote></figure>
<!-- /wp:pullquote -->

<!-- wp:table -->
<figure class="wp-block-table"><table><thead><tr><th>Header A</th><th>Header B</th><th>Header C</th></tr></thead><tbody><tr><td>Cell 1</td><td>Cell 2</td><td>Cell 3</td></tr><tr><td>Cell 4</td><td>Cell 5</td><td>Cell 6</td></tr></tbody></table><figcaption class="wp-element-caption">Table caption</figcaption></figure>
<!-- /wp:table -->

<!-- wp:code -->
<pre class="wp-block-code"><code>function axismundi() {
  return 'code block';
}</code></pre>
<!-- /wp:code -->

<!-- wp:preformatted -->
<pre class="wp-block-preformatted">Preformatted text
  preserves    whitespace
and line breaks.</pre>
<!-- /wp:preformatted -->

<!-- wp:verse -->
<pre class="wp-block-verse">A verse block
holds poetry and
soft line breaks.</pre>
<!-- /wp:verse -->

<!-- wp:separator -->
<hr class="wp-block-separator has-alpha-channel-opacity"/>
<!-- /wp:separator -->

<!-- wp:paragraph -->
<p>Closing paragraph after the separator, to observe vertical rhythm reset.</p>
<!-- /wp:paragraph -->
