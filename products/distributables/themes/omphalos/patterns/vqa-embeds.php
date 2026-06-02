<?php
/**
 * Title: VQA Embeds Blocks
 * Slug: omphalos/vqa-embeds
 * Categories: omphalos
 * Inserter: true
 * Description: WordPress core "Embeds" category blocks for Phase 8 VQA — a CURATED
 *              (not exhaustive) set of providers, one per surface type, to observe
 *              how core/embed renders BEFORE any embed chrome is contracted. core/
 *              embed is effectively static save(): the <figure class="wp-block-embed
 *              is-type-* is-provider-*"> + bare URL is what saves; the front-end
 *              `the_content` autoembed (oEmbed) replaces the URL with the provider
 *              HTML at render. So the DOM shape is provider-dependent and falls into
 *              three buckets — (1) IFRAME (youtube/ted/videopress/spotify): theme
 *              owns the outer frame only; (2) BLOCKQUOTE + provider <script>
 *              (reddit/bluesky/mastodon): the script upgrades the quote client-side,
 *              theme styles only the pre-script fallback; (3) LINK fallback when the
 *              provider is unsupported/unreachable: theme owns a fallback card. The
 *              theme must NEVER style inside the provider iframe/script UI.
 *
 *              oEmbed is an external fetch: the first render of this page populates
 *              the per-post _oembed_* cache. Fragile providers (X/Threads/Instagram)
 *              need auth/script policies that usually fail in wp-env — they are kept
 *              in an explicit "expected fallback" section, NOT treated as test
 *              failures. URLs are public WordPress-ecosystem references; no personal
 *              accounts. Provider/type classes here are authored to the documented
 *              oEmbed result and re-verified against the rendered DOM in the route doc.
 *
 * @package Omphalos
 */
?>
<!-- wp:heading {"level":1} -->
<h1 class="wp-block-heading">Core Embeds VQA</h1>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>선별 specimen. provider별로 iframe / blockquote+script / fallback 중 무엇으로 렌더되는지 먼저 관찰하고, 테마는 outer shell(figure 간격·미디어 radius·caption·fallback)만 최소 계약한다. iframe/script 내부는 건드리지 않는다.</p>
<!-- /wp:paragraph -->

<!-- wp:heading -->
<h2 class="wp-block-heading">1. Article / WordPress ecosystem</h2>
<!-- /wp:heading -->

<!-- wp:embed {"url":"https://ma.tt/2026/04/theopensource/","type":"wp-embed","providerNameSlug":"ma-tt"} -->
<figure class="wp-block-embed is-type-wp-embed is-provider-ma-tt wp-block-embed-ma-tt"><div class="wp-block-embed__wrapper">
https://ma.tt/2026/04/theopensource/
</div></figure>
<!-- /wp:embed -->

<!-- wp:embed {"url":"https://wordpress.com/blog/2026/05/28/reader-social-bluesky-mastodon-fediverse/","type":"wp-embed","providerNameSlug":"wordpress-com"} -->
<figure class="wp-block-embed is-type-wp-embed is-provider-wordpress-com wp-block-embed-wordpress-com"><div class="wp-block-embed__wrapper">
https://wordpress.com/blog/2026/05/28/reader-social-bluesky-mastodon-fediverse/
</div></figure>
<!-- /wp:embed -->

<!-- wp:embed {"url":"https://gutenbergtimes.com/wordpress-7-0-source-of-truth/","type":"wp-embed","providerNameSlug":"gutenberg-times"} -->
<figure class="wp-block-embed is-type-wp-embed is-provider-gutenberg-times wp-block-embed-gutenberg-times"><div class="wp-block-embed__wrapper">
https://gutenbergtimes.com/wordpress-7-0-source-of-truth/
</div></figure>
<!-- /wp:embed -->

<!-- wp:heading -->
<h2 class="wp-block-heading">2. Video</h2>
<!-- /wp:heading -->

<!-- wp:embed {"url":"https://wordpress.tv/2025/07/14/wceu-2025-after-movie/","type":"video","providerNameSlug":"videopress","responsive":true,"className":"wp-embed-aspect-16-9 wp-has-aspect-ratio"} -->
<figure class="wp-block-embed is-type-video is-provider-videopress wp-block-embed-videopress wp-embed-aspect-16-9 wp-has-aspect-ratio"><div class="wp-block-embed__wrapper">
https://wordpress.tv/2025/07/14/wceu-2025-after-movie/
</div></figure>
<!-- /wp:embed -->

<!-- wp:embed {"url":"https://videopress.com/v/Ygmx4akX","type":"video","providerNameSlug":"videopress","responsive":true,"className":"wp-embed-aspect-16-9 wp-has-aspect-ratio"} -->
<figure class="wp-block-embed is-type-video is-provider-videopress wp-block-embed-videopress wp-embed-aspect-16-9 wp-has-aspect-ratio"><div class="wp-block-embed__wrapper">
https://videopress.com/v/Ygmx4akX
</div></figure>
<!-- /wp:embed -->

<!-- wp:embed {"url":"https://www.youtube.com/watch?v=_LhXqP9wrBM","type":"video","providerNameSlug":"youtube","responsive":true,"className":"wp-embed-aspect-16-9 wp-has-aspect-ratio"} -->
<figure class="wp-block-embed is-type-video is-provider-youtube wp-block-embed-youtube wp-embed-aspect-16-9 wp-has-aspect-ratio"><div class="wp-block-embed__wrapper">
https://www.youtube.com/watch?v=_LhXqP9wrBM
</div></figure>
<!-- /wp:embed -->

<!-- wp:embed {"url":"https://www.ted.com/talks/matt_mullenweg_why_working_from_home_is_good_for_business","type":"video","providerNameSlug":"ted","responsive":true,"className":"wp-embed-aspect-16-9 wp-has-aspect-ratio"} -->
<figure class="wp-block-embed is-type-video is-provider-ted wp-block-embed-ted wp-embed-aspect-16-9 wp-has-aspect-ratio"><div class="wp-block-embed__wrapper">
https://www.ted.com/talks/matt_mullenweg_why_working_from_home_is_good_for_business
</div></figure>
<!-- /wp:embed -->

<!-- wp:heading -->
<h2 class="wp-block-heading">3. Social / Fediverse</h2>
<!-- /wp:heading -->

<!-- wp:embed {"url":"https://bsky.app/profile/wordpress.org/post/3mmcnxsbl7323","type":"rich","providerNameSlug":"bluesky"} -->
<figure class="wp-block-embed is-type-rich is-provider-bluesky wp-block-embed-bluesky"><div class="wp-block-embed__wrapper">
https://bsky.app/profile/wordpress.org/post/3mmcnxsbl7323
</div></figure>
<!-- /wp:embed -->

<!-- wp:embed {"url":"https://mastodon.world/@WordPress/116608604100921521","type":"rich","providerNameSlug":"mastodon"} -->
<figure class="wp-block-embed is-type-rich is-provider-mastodon wp-block-embed-mastodon"><div class="wp-block-embed__wrapper">
https://mastodon.world/@WordPress/116608604100921521
</div></figure>
<!-- /wp:embed -->

<!-- wp:embed {"url":"https://www.reddit.com/r/Wordpress/comments/1cqlvod/start_here_essential_resources_faqs/","type":"rich","providerNameSlug":"reddit"} -->
<figure class="wp-block-embed is-type-rich is-provider-reddit wp-block-embed-reddit"><div class="wp-block-embed__wrapper">
https://www.reddit.com/r/Wordpress/comments/1cqlvod/start_here_essential_resources_faqs/
</div></figure>
<!-- /wp:embed -->

<!-- wp:heading -->
<h2 class="wp-block-heading">4. Audio</h2>
<!-- /wp:heading -->

<!-- wp:embed {"url":"https://open.spotify.com/playlist/41QodfvqbsJwlqTuA2jciP","type":"rich","providerNameSlug":"spotify"} -->
<figure class="wp-block-embed is-type-rich is-provider-spotify wp-block-embed-spotify"><div class="wp-block-embed__wrapper">
https://open.spotify.com/playlist/41QodfvqbsJwlqTuA2jciP
</div></figure>
<!-- /wp:embed -->

<!-- wp:embed {"url":"https://soundcloud.com/taalexer/cold-glass","type":"rich","providerNameSlug":"soundcloud"} -->
<figure class="wp-block-embed is-type-rich is-provider-soundcloud wp-block-embed-soundcloud"><div class="wp-block-embed__wrapper">
https://soundcloud.com/taalexer/cold-glass
</div></figure>
<!-- /wp:embed -->

<!-- wp:heading -->
<h2 class="wp-block-heading">5. Image / Pin</h2>
<!-- /wp:heading -->

<!-- wp:embed {"url":"https://kr.pinterest.com/pin/1086212003911511156/","type":"rich","providerNameSlug":"pinterest"} -->
<figure class="wp-block-embed is-type-rich is-provider-pinterest wp-block-embed-pinterest"><div class="wp-block-embed__wrapper">
https://kr.pinterest.com/pin/1086212003911511156/
</div></figure>
<!-- /wp:embed -->

<!-- wp:heading -->
<h2 class="wp-block-heading">6. Fragile providers (expected fallback)</h2>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>auth/script 정책 때문에 wp-env/oEmbed에서 자주 실패한다. fallback(링크)로 떨어지는 것이 정상이며 테스트 실패로 보지 않는다.</p>
<!-- /wp:paragraph -->

<!-- wp:embed {"url":"https://x.com/WordPress/status/1868689630931059186","type":"rich","providerNameSlug":"twitter"} -->
<figure class="wp-block-embed is-type-rich is-provider-twitter wp-block-embed-twitter"><div class="wp-block-embed__wrapper">
https://x.com/WordPress/status/1868689630931059186
</div></figure>
<!-- /wp:embed -->

<!-- wp:embed {"url":"https://www.threads.com/@wordpress/post/DYkpR-EGLkF","type":"rich","providerNameSlug":"threads"} -->
<figure class="wp-block-embed is-type-rich is-provider-threads wp-block-embed-threads"><div class="wp-block-embed__wrapper">
https://www.threads.com/@wordpress/post/DYkpR-EGLkF
</div></figure>
<!-- /wp:embed -->

<!-- wp:embed {"url":"https://www.instagram.com/p/DYkuHnHGCXE/","type":"rich","providerNameSlug":"instagram"} -->
<figure class="wp-block-embed is-type-rich is-provider-instagram wp-block-embed-instagram"><div class="wp-block-embed__wrapper">
https://www.instagram.com/p/DYkuHnHGCXE/
</div></figure>
<!-- /wp:embed -->

<!-- wp:embed {"url":"https://wordpress.tumblr.com/post/817161664557383680/wordpress-70-armstrong-is-here-this-major","type":"rich","providerNameSlug":"tumblr"} -->
<figure class="wp-block-embed is-type-rich is-provider-tumblr wp-block-embed-tumblr"><div class="wp-block-embed__wrapper">
https://wordpress.tumblr.com/post/817161664557383680/wordpress-70-armstrong-is-here-this-major
</div></figure>
<!-- /wp:embed -->

<!-- wp:heading -->
<h2 class="wp-block-heading">7. Fallback / unsupported URL</h2>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>등록된 oEmbed provider가 아닌 URL. 핸들러가 없어 링크로 떨어진다 — 테마 fallback card 계약 대상.</p>
<!-- /wp:paragraph -->

<!-- wp:embed {"url":"https://example.com/not-an-embeddable-resource","type":"rich","providerNameSlug":"embed-handler"} -->
<figure class="wp-block-embed is-type-rich is-provider-embed-handler wp-block-embed-embed-handler"><div class="wp-block-embed__wrapper">
https://example.com/not-an-embeddable-resource
</div></figure>
<!-- /wp:embed -->
