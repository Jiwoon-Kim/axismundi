<?php
/**
 * Title: VQA Media Blocks
 * Slug: omphalos/vqa-media
 * Categories: omphalos
 * Inserter: false
 * Description: Core media block specimens for Phase 8 Media-group VQA. This is a
 *              SEED-BOUND template, not a directly insertable pattern: the
 *              __*_ID__ / __*_URL__ placeholders below are substituted with the
 *              installation's real attachment IDs/URLs by scripts/seed.ps1 when
 *              it builds the /vqa-media/ page. Inserting this pattern as-is would
 *              drop literal placeholders and broken media, so Inserter is false.
 *              The /vqa-media/ page produced by the seed is the canonical output.
 *
 *              Media blocks (image, gallery, audio, video, cover, media-text,
 *              file) are dynamic and attachment-aware — they render env-specific
 *              IDs/URLs (wp-image-N, /uploads/YYYY/MM/...), so hard-coding IDs
 *              would break on reseed or another install. Markup mirrors the
 *              editor's canonical save output (captions use wp-element-caption).
 *
 * @package Omphalos
 */
?>
<!-- wp:heading {"level":1} -->
<h1 class="wp-block-heading">Core Media VQA</h1>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>Core media blocks baseline. Asset IDs/URLs are seed-bound to this install. Verify caption position, image radius, native controls, video tracks, gallery layout, and file download button.</p>
<!-- /wp:paragraph -->

<!-- wp:heading -->
<h2 class="wp-block-heading">Image — default + caption</h2>
<!-- /wp:heading -->

<!-- wp:image {"id":__IMAGE_ID__,"sizeSlug":"large","linkDestination":"none"} -->
<figure class="wp-block-image size-large"><img src="__IMAGE_URL__" alt="Mogu placeholder" class="wp-image-__IMAGE_ID__"/><figcaption class="wp-element-caption">Image caption — body-small, centered, on-surface-variant.</figcaption></figure>
<!-- /wp:image -->

<!-- wp:heading -->
<h2 class="wp-block-heading">Image — rounded style</h2>
<!-- /wp:heading -->

<!-- wp:image {"id":__IMAGE_ID__,"sizeSlug":"large","linkDestination":"none","className":"is-style-rounded"} -->
<figure class="wp-block-image size-large is-style-rounded"><img src="__IMAGE_URL__" alt="Mogu placeholder rounded" class="wp-image-__IMAGE_ID__"/></figure>
<!-- /wp:image -->

<!-- wp:heading -->
<h2 class="wp-block-heading">Gallery — 2 columns</h2>
<!-- /wp:heading -->

<!-- wp:gallery {"columns":2,"linkTo":"none"} -->
<figure class="wp-block-gallery has-nested-images columns-2 is-cropped"><!-- wp:image {"id":__IMAGE_ID__,"sizeSlug":"large","linkDestination":"none"} -->
<figure class="wp-block-image size-large"><img src="__IMAGE_URL__" alt="" class="wp-image-__IMAGE_ID__"/></figure>
<!-- /wp:image -->

<!-- wp:image {"id":__COVER_ID__,"sizeSlug":"large","linkDestination":"none"} -->
<figure class="wp-block-image size-large"><img src="__COVER_URL__" alt="" class="wp-image-__COVER_ID__"/></figure>
<!-- /wp:image --></figure>
<!-- /wp:gallery -->

<!-- wp:heading -->
<h2 class="wp-block-heading">Audio — native controls + caption</h2>
<!-- /wp:heading -->

<!-- wp:audio {"id":__AUDIO_ID__} -->
<figure class="wp-block-audio"><audio controls src="__AUDIO_URL__"></audio><figcaption class="wp-element-caption">Audio caption — Opus-in-Ogg placeholder.</figcaption></figure>
<!-- /wp:audio -->

<!-- wp:heading -->
<h2 class="wp-block-heading">Video — tracks (en/ko) + caption</h2>
<!-- /wp:heading -->

<!-- wp:video {"id":__VIDEO_ID__} -->
<figure class="wp-block-video"><video controls src="__VIDEO_URL__"><track src="__VTT_EN_URL__" kind="subtitles" srclang="en" label="English"><track src="__VTT_KO_URL__" kind="subtitles" srclang="ko" label="Korean"></video><figcaption class="wp-element-caption">Video caption — WebM with English/Korean subtitle tracks.</figcaption></figure>
<!-- /wp:video -->

<!-- wp:heading -->
<h2 class="wp-block-heading">Cover — image background</h2>
<!-- /wp:heading -->

<!-- wp:cover {"url":"__IMAGE_URL__","id":__IMAGE_ID__,"dimRatio":50,"isUserOverlayColor":true,"minHeight":280,"contentPosition":"center center"} -->
<div class="wp-block-cover" style="min-height:280px"><span aria-hidden="true" class="wp-block-cover__background has-background-dim"></span><img class="wp-block-cover__image-background wp-image-__IMAGE_ID__" alt="" src="__IMAGE_URL__" data-object-fit="cover"/><div class="wp-block-cover__inner-container"><!-- wp:paragraph {"align":"center","fontSize":"x-large"} -->
<p class="has-text-align-center has-x-large-font-size">Cover heading over image</p>
<!-- /wp:paragraph --></div></div>
<!-- /wp:cover -->

<!-- wp:heading -->
<h2 class="wp-block-heading">Media &amp; Text — image left</h2>
<!-- /wp:heading -->

<!-- wp:media-text {"mediaId":__IMAGE_ID__,"mediaType":"image"} -->
<div class="wp-block-media-text is-stacked-on-mobile"><figure class="wp-block-media-text__media"><img src="__IMAGE_URL__" alt="" class="wp-image-__IMAGE_ID__ size-full"/></figure><div class="wp-block-media-text__content"><!-- wp:paragraph -->
<p>Media &amp; Text content column — stacks on mobile. The image sits in the media column and text flows beside it.</p>
<!-- /wp:paragraph --></div></div>
<!-- /wp:media-text -->

<!-- wp:heading -->
<h2 class="wp-block-heading">File — download button</h2>
<!-- /wp:heading -->

<!-- wp:file {"id":__AUDIO_ID__,"href":"__AUDIO_URL__"} -->
<div class="wp-block-file"><a id="wp-block-file--media-__AUDIO_ID__" href="__AUDIO_URL__">audio-placeholder-jazzy-lofi.ogg</a><a href="__AUDIO_URL__" class="wp-block-file__button wp-element-button" download>Download</a></div>
<!-- /wp:file -->
