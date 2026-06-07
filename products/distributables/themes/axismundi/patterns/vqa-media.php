<?php
/**
 * Title: VQA Media Blocks
 * Slug: axismundi/vqa-media
 * Categories: axismundi
 * Inserter: false
 * Description: WordPress core MEDIA-family blocks for the Axismundi baseline VQA —
 *   image, gallery, cover, media-text, audio, video, file. Block-rooted
 *   (wp:* / .wp-block-*), unstyled, for the blank/core render snapshot before M3
 *   binding. Images use picsum.photos; audio/video use small sample sources.
 *   Dev-only specimen — excluded from the distributable ZIP via .distignore.
 *
 * @package Axismundi
 */
?>
<!-- wp:heading {"level":1} -->
<h1 class="wp-block-heading">VQA Media — core media-family blocks</h1>
<!-- /wp:heading -->

<!-- wp:heading -->
<h2 class="wp-block-heading">1. Image (with caption)</h2>
<!-- /wp:heading -->

<!-- wp:image {"sizeSlug":"large"} -->
<figure class="wp-block-image size-large"><img src="https://picsum.photos/id/1020/600/360" alt="Picsum image"/><figcaption class="wp-element-caption">Image caption — figcaption typography + alignment.</figcaption></figure>
<!-- /wp:image -->

<!-- wp:heading -->
<h2 class="wp-block-heading">2. Gallery (3 columns)</h2>
<!-- /wp:heading -->

<!-- wp:gallery {"columns":3,"linkTo":"none"} -->
<figure class="wp-block-gallery has-nested-images columns-3 is-cropped"><!-- wp:image -->
<figure class="wp-block-image"><img src="https://picsum.photos/id/1024/400/300" alt=""/></figure>
<!-- /wp:image -->

<!-- wp:image -->
<figure class="wp-block-image"><img src="https://picsum.photos/id/1025/400/300" alt=""/></figure>
<!-- /wp:image -->

<!-- wp:image -->
<figure class="wp-block-image"><img src="https://picsum.photos/id/1027/400/300" alt=""/></figure>
<!-- /wp:image --></figure>
<!-- /wp:gallery -->

<!-- wp:heading -->
<h2 class="wp-block-heading">3. Cover (overlay text)</h2>
<!-- /wp:heading -->

<!-- wp:cover {"url":"https://picsum.photos/id/1018/1200/400","dimRatio":50,"minHeight":320} -->
<div class="wp-block-cover" style="min-height:320px"><span aria-hidden="true" class="wp-block-cover__background has-background-dim-50 has-background-dim"></span><img class="wp-block-cover__image-background" src="https://picsum.photos/id/1018/1200/400" alt=""/><div class="wp-block-cover__inner-container"><!-- wp:heading {"textAlign":"center","textColor":"white"} -->
<h2 class="wp-block-heading has-text-align-center has-white-color has-text-color">Cover overlay heading</h2>
<!-- /wp:heading --></div></div>
<!-- /wp:cover -->

<!-- wp:heading -->
<h2 class="wp-block-heading">4. Media &amp; Text</h2>
<!-- /wp:heading -->

<!-- wp:media-text {"mediaType":"image"} -->
<div class="wp-block-media-text is-stacked-on-mobile"><figure class="wp-block-media-text__media"><img src="https://picsum.photos/id/1033/600/400" alt=""/></figure><div class="wp-block-media-text__content"><!-- wp:paragraph -->
<p>Media on the left, text on the right — stacks on mobile. The quick brown fox jumps over the lazy dog.</p>
<!-- /wp:paragraph --></div></div>
<!-- /wp:media-text -->

<!-- wp:heading -->
<h2 class="wp-block-heading">5. Audio</h2>
<!-- /wp:heading -->

<!-- wp:audio -->
<figure class="wp-block-audio"><audio controls src="https://www.w3schools.com/html/horse.ogg"></audio><figcaption class="wp-element-caption">Audio caption.</figcaption></figure>
<!-- /wp:audio -->

<!-- wp:heading -->
<h2 class="wp-block-heading">6. Video</h2>
<!-- /wp:heading -->

<!-- wp:video -->
<figure class="wp-block-video"><video controls poster="https://picsum.photos/id/1018/640/360" src="https://www.w3schools.com/html/mov_bbb.mp4"></video><figcaption class="wp-element-caption">Video caption.</figcaption></figure>
<!-- /wp:video -->

<!-- wp:heading -->
<h2 class="wp-block-heading">7. File (download)</h2>
<!-- /wp:heading -->

<!-- wp:file {"href":"https://www.w3.org/WAI/ER/tests/xhtml/testfiles/resources/pdf/dummy.pdf"} -->
<div class="wp-block-file"><a href="https://www.w3.org/WAI/ER/tests/xhtml/testfiles/resources/pdf/dummy.pdf">dummy.pdf</a><a href="https://www.w3.org/WAI/ER/tests/xhtml/testfiles/resources/pdf/dummy.pdf" class="wp-block-file__button wp-element-button" download>Download</a></div>
<!-- /wp:file -->
