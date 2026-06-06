<?php
/**
 * Title: VQA Media Blocks
 * Slug: omphalos/vqa-media
 * Categories: omphalos
 * Inserter: false
 * Description: Core media block specimens for Phase 8 Media-group VQA. This is a
 *              SEED-BOUND template, not a directly insertable pattern: the
 *              __*_ID__ / __*_URL__ / __*_PERMALINK__ placeholders below are
 *              substituted with the installation's real attachment values by
 *              scripts/seed-vqa-media.php (run from scripts/seed.ps1) when it
 *              builds the /vqa-media/ page. Inserting this pattern as-is would
 *              drop literal placeholders and broken media, so Inserter is false.
 *              The /vqa-media/ page produced by the seed is the canonical output.
 *
 *              Placeholders:
 *                __IMAGE_ID__ / __IMAGE_URL__          main raster image (mogu)
 *                __AUDIO_IMAGE_ID__ / __AUDIO_IMAGE_URL__
 *                                                      cover art embedded in the
 *                                                      audio file (a DISTINCT 2nd
 *                                                      gallery tile, so the WP 7.0
 *                                                      lightbox carousel visibly
 *                                                      advances between two
 *                                                      different images)
 *                __AUDIO_ID__ / __AUDIO_URL__ / __AUDIO_PERMALINK__
 *                __VIDEO_ID__ / __VIDEO_URL__ / __VTT_KO_URL__
 *
 *              Media blocks (image, gallery, audio, video, cover, media-text,
 *              file) are dynamic and attachment-aware — they render env-specific
 *              IDs/URLs (wp-image-N, /uploads/YYYY/MM/...), so hard-coding IDs
 *              would break on reseed or another install. Markup mirrors the
 *              editor's canonical save output (captions use wp-element-caption;
 *              cover img precedes the dim span; video carries a tracks attr;
 *              core/file's textLink href/target are sourced from markup, not the
 *              block comment).
 *
 *              core/icon (WP-category "media") is also here but is NOT
 *              attachment-bound: its `icon` attribute is a library reference
 *              (core/<name>, e.g. core/star-filled) and the block is dynamic
 *              (render emits inline SVG, fill: currentColor), so its markup has no
 *              seed placeholders. Distinct from the .material-symbols-outlined
 *              font utility (ligature glyph) — core/icon is inline SVG.
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

<!-- wp:image {"lightbox":{"enabled":false},"id":__IMAGE_ID__,"sizeSlug":"large","linkDestination":"none"} -->
<figure class="wp-block-image size-large"><img src="__IMAGE_URL__" alt="Mogu placeholder" class="wp-image-__IMAGE_ID__"/><figcaption class="wp-element-caption">Image caption — body-small, centered, on-surface-variant.</figcaption></figure>
<!-- /wp:image -->

<!-- wp:group {"layout":{"type":"flex","flexWrap":"nowrap"}} -->
<div class="wp-block-group"><!-- wp:group {"layout":{"type":"constrained"}} -->
<div class="wp-block-group"><!-- wp:heading -->
<h2 class="wp-block-heading">Image — rounded style</h2>
<!-- /wp:heading -->

<!-- wp:image {"id":__IMAGE_ID__,"sizeSlug":"large","linkDestination":"none","className":"is-style-rounded"} -->
<figure class="wp-block-image size-large is-style-rounded"><img src="__IMAGE_URL__" alt="Mogu placeholder rounded" class="wp-image-__IMAGE_ID__"/></figure>
<!-- /wp:image --></div>
<!-- /wp:group -->

<!-- wp:group {"layout":{"type":"constrained"}} -->
<div class="wp-block-group"><!-- wp:heading -->
<h2 class="wp-block-heading">Image — Global Styles radius</h2>
<!-- /wp:heading -->

<!-- wp:image {"id":__IMAGE_ID__,"sizeSlug":"large","linkDestination":"none"} -->
<figure class="wp-block-image size-large"><img src="__IMAGE_URL__" alt="Mogu placeholder default radius" class="wp-image-__IMAGE_ID__"/></figure>
<!-- /wp:image --></div>
<!-- /wp:group --></div>
<!-- /wp:group -->

<!-- wp:heading -->
<h2 class="wp-block-heading">Gallery — 2 columns</h2>
<!-- /wp:heading -->

<!-- wp:gallery {"columns":2,"linkTo":"none"} -->
<figure class="wp-block-gallery has-nested-images columns-2 is-cropped"><!-- wp:image {"id":__IMAGE_ID__,"sizeSlug":"large","linkDestination":"none"} -->
<figure class="wp-block-image size-large"><img src="__IMAGE_URL__" alt="" class="wp-image-__IMAGE_ID__"/></figure>
<!-- /wp:image -->

<!-- wp:image {"id":__AUDIO_IMAGE_ID__,"sizeSlug":"full","linkDestination":"none"} -->
<figure class="wp-block-image size-full"><img src="__AUDIO_IMAGE_URL__" alt="" class="wp-image-__AUDIO_IMAGE_ID__"/></figure>
<!-- /wp:image --></figure>
<!-- /wp:gallery -->

<!-- wp:heading -->
<h2 class="wp-block-heading">Audio — native controls + caption</h2>
<!-- /wp:heading -->

<!-- wp:audio {"id":__AUDIO_ID__} -->
<figure class="wp-block-audio"><audio controls src="__AUDIO_URL__"></audio><figcaption class="wp-element-caption">Audio caption — Opus-in-Ogg placeholder.</figcaption></figure>
<!-- /wp:audio -->

<!-- wp:heading -->
<h2 class="wp-block-heading">Video — subtitle track (ko) + caption</h2>
<!-- /wp:heading -->

<!-- wp:video {"id":__VIDEO_ID__,"tracks":[{"src":"__VTT_KO_URL__","label":"한국어","srcLang":"ko","kind":"subtitles","default":true}]} -->
<figure class="wp-block-video"><video controls src="__VIDEO_URL__"><track src="__VTT_KO_URL__" label="한국어" srclang="ko" kind="subtitles" default/></video><figcaption class="wp-element-caption">Video caption — WebM with a Korean subtitle track. WP core supports one VTT track per video; multi-track is plugin/child-render territory.</figcaption></figure>
<!-- /wp:video -->

<!-- wp:heading -->
<h2 class="wp-block-heading">Cover — image background</h2>
<!-- /wp:heading -->

<!-- wp:cover {"url":"__IMAGE_URL__","id":__IMAGE_ID__,"dimRatio":50,"isUserOverlayColor":true,"minHeight":280,"contentPosition":"center center","layout":{"type":"default"}} -->
<div class="wp-block-cover" style="min-height:280px"><img class="wp-block-cover__image-background wp-image-__IMAGE_ID__" alt="" src="__IMAGE_URL__" data-object-fit="cover"/><span aria-hidden="true" class="wp-block-cover__background has-background-dim"></span><div class="wp-block-cover__inner-container"><!-- wp:paragraph {"style":{"typography":{"textAlign":"center"}}} -->
<p class="has-text-align-center">core/cover</p>
<!-- /wp:paragraph -->

<!-- wp:paragraph {"style":{"typography":{"textAlign":"center"}},"fontSize":"x-large"} -->
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

<!-- wp:file {"id":__AUDIO_ID__,"href":"__AUDIO_URL__","displayPreview":false} -->
<div class="wp-block-file"><a id="wp-block-file--media-__AUDIO_ID__" href="__AUDIO_PERMALINK__" target="_blank" rel="noreferrer noopener">audio-placeholder-jazzy-lofi.ogg</a><a href="__AUDIO_URL__" class="wp-block-file__button wp-element-button" download aria-describedby="wp-block-file--media-__AUDIO_ID__">Download</a></div>
<!-- /wp:file -->

<!-- wp:heading -->
<h2 class="wp-block-heading">Icon — core/icon (SVG symbol)</h2>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>core/icon은 라이브러리 SVG 심볼 블록입니다(<code>icon</code>=<code>core/&lt;name&gt;</code>, render가 inline SVG 출력, <code>fill: currentColor</code>). 폰트 글리프인 <code>.material-symbols-outlined</code> 유틸과는 별개 표면 — 이건 inline SVG, 저건 ligature 폰트. 코어 기본(24px, currentColor)이 합리적이라 theme가 크기를 강제하지 않습니다. <code>textColor</code>로 색, <code>dimensions.width</code>로 크기를 줍니다.</p>
<!-- /wp:paragraph -->

<!-- wp:icon {"icon":"core/star-filled"} /-->

<!-- wp:icon {"icon":"core/at-symbol","textColor":"primary"} /-->

<!-- wp:icon {"icon":"core/download","style":{"dimensions":{"width":"48px"}}} /-->

<!-- wp:group {"layout":{"type":"flex","flexWrap":"nowrap"}} -->
<div class="wp-block-group"><!-- wp:icon {"icon":"core/star-empty"} /-->

<!-- wp:icon {"icon":"core/star-filled"} /-->

<!-- wp:icon {"icon":"core/star-half"} /--></div>
<!-- /wp:group -->
