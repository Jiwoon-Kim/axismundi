<?php
/**
 * Title: VQA Theme Archive
 * Slug: omphalos/vqa-theme-archive
 * Categories: omphalos
 * Inserter: false
 * Description: Phase 3 Theme VQA specimen for archive/query-title and term blocks.
 *
 * @package Omphalos
 */
?>
<!-- wp:heading {"level":1} -->
<h1 class="wp-block-heading">Core Theme VQA — Archive / Terms</h1>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>Archive/query-title blocks are primarily template-context chrome. This page keeps the in-content surface to term blocks and links to the live archive contexts seeded by <code>seed-vqa-theme-archive.php</code>.</p>
<!-- /wp:paragraph -->

<!-- wp:heading -->
<h2 class="wp-block-heading">1. Live Archive Contexts</h2>
<!-- /wp:heading -->

<!-- wp:list -->
<ul>
	<!-- wp:list-item -->
	<li><a href="/?cat=3">Category archive: VQA Theme Topic</a></li>
	<!-- /wp:list-item -->

	<!-- wp:list-item -->
	<li><a href="/?tag=vqa-theme-tag">Tag archive: VQA Theme Tag</a></li>
	<!-- /wp:list-item -->

	<!-- wp:list-item -->
	<li><a href="/?s=Query+Loop">Search results: Query Loop</a></li>
	<!-- /wp:list-item -->
</ul>
<!-- /wp:list -->

<!-- wp:heading -->
<h2 class="wp-block-heading">2. Category Terms Query</h2>
<!-- /wp:heading -->

<!-- wp:terms-query {"termQuery":{"taxonomy":"category","perPage":8,"orderBy":"name","order":"asc","hideEmpty":false}} -->
<!-- wp:term-template -->
<!-- wp:term-name {"level":3,"isLink":true} /-->
<!-- wp:term-description /-->
<!-- wp:term-count /-->
<!-- /wp:term-template -->
<!-- /wp:terms-query -->

<!-- wp:heading -->
<h2 class="wp-block-heading">3. Tag Terms Query</h2>
<!-- /wp:heading -->

<!-- wp:terms-query {"termQuery":{"taxonomy":"post_tag","perPage":8,"orderBy":"name","order":"asc","hideEmpty":false}} -->
<!-- wp:term-template -->
<!-- wp:term-name {"level":3,"isLink":true} /-->
<!-- wp:term-description /-->
<!-- wp:term-count /-->
<!-- /wp:term-template -->
<!-- /wp:terms-query -->
