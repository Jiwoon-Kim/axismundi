<?php
/**
 * Object View Model shared-contract regression (dev-only).
 *
 * Three adapters (local Post, local Note, cached remote) feed one contract, so
 * these checks pin the shared normalization and enrichment rather than any one
 * product's fields.
 *
 * @package AxismundiObjectProjections
 */

defined( 'ABSPATH' ) || exit( 1 );

global $wpdb;
$ax_vmc_results = array();
$ax_vmc_remote  = array();
$ax_vmc_posts   = array();
$ax_vmc_atts    = array();
$ax_vmc_terms   = array();
$ax_vmc_user    = get_current_user_id();

/** @param bool[] $results Results. */
function ax_vmc_assert( array &$results, string $label, bool $condition ) : void {
	$results[] = $condition;
	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI test output.
	printf( "[%s] %s\n", $condition ? 'PASS' : 'FAIL', $label );
}

/**
 * Store one cached remote Object and return its normalized view model.
 *
 * @param array<string,mixed> $payload Remote AS2 payload.
 * @param string[]            $tracked Collected URIs for fixture cleanup.
 * @return array<string,mixed>
 */
function ax_vmc_remote_model( array $payload, array &$tracked ) : array {
	axismundi_op_remote_object_store( $payload );
	$tracked[] = (string) $payload['id'];
	$source    = axismundi_op_resolve_source_by_uri( (string) $payload['id'] );
	$model     = null === $source ? null : axismundi_op_object_view_model( $source );
	return is_array( $model ) ? $model : array();
}

try {
	axismundi_op_install();
	$ax_vmc_public = 'https://www.w3.org/ns/activitystreams#Public';
	$ax_vmc_suffix = strtolower( wp_generate_password( 8, false, false ) );
	$ax_vmc_actor  = 'https://example.com/users/vmc-' . $ax_vmc_suffix;
	$ax_vmc_media  = static fn( int $n ) : string => 'https://example.com/vmc-' . $n . '.jpg';
	$ax_vmc_image  = static fn( int $n ) : array => array( 'type' => 'Image', 'mediaType' => 'image/jpeg', 'url' => 'https://example.com/vmc-' . $n . '.jpg' );

	// A non-sensitive AS2 `summary` is an excerpt. Adapters map it onto the
	// warning slot, so the contract has to reclaim it or every plain summary
	// would render as a content warning.
	$plain = ax_vmc_remote_model(
		array( 'id' => 'https://example.com/notes/' . wp_generate_uuid4(), 'type' => 'Note', 'attributedTo' => $ax_vmc_actor, 'to' => array( $ax_vmc_public ), 'summary' => 'A plain summary', 'content' => '<p>Body.</p>' ),
		$ax_vmc_remote
	);
	ax_vmc_assert( $ax_vmc_results, 'a non-sensitive summary becomes an excerpt and clears the content warning', 'A plain summary' === (string) ( $plain['summary'] ?? '' ) && '' === (string) ( $plain['content_warning'] ?? '' ) );

	$warned = ax_vmc_remote_model(
		array( 'id' => 'https://example.com/notes/' . wp_generate_uuid4(), 'type' => 'Note', 'attributedTo' => $ax_vmc_actor, 'to' => array( $ax_vmc_public ), 'summary' => 'Spoiler', 'sensitive' => true, 'content' => '<p>Body.</p>' ),
		$ax_vmc_remote
	);
	ax_vmc_assert( $ax_vmc_results, 'a sensitive summary stays a content warning and never becomes an excerpt', 'Spoiler' === (string) ( $warned['content_warning'] ?? '' ) && '' === (string) ( $warned['summary'] ?? '' ) );

	$map_only = ax_vmc_remote_model(
		array(
			'id'           => 'https://example.com/articles/' . wp_generate_uuid4(),
			'type'         => 'Article',
			'attributedTo' => $ax_vmc_actor,
			'to'           => array( $ax_vmc_public ),
			'nameMap'      => array( 'ko-KR' => 'Map-only title' ),
			'summaryMap'   => array( 'ko-KR' => '<p>Map-only excerpt.</p>' ),
			'contentMap'   => array( 'ko-KR' => '<p>Map-only body.</p>' ),
		),
		$ax_vmc_remote
	);
	ax_vmc_assert( $ax_vmc_results, 'a map-only remote Object is normalized and rendered from its maps without scalar name, summary, or content', 'Map-only title' === (string) ( $map_only['title'] ?? '' ) && false !== strpos( (string) ( $map_only['summary'] ?? '' ), 'Map-only excerpt.' ) && false !== strpos( (string) ( $map_only['content_html'] ?? '' ), 'Map-only body.' ) && 'ko-KR' === (string) ( $map_only['language'] ?? '' ) );

	$legacy_map_row = array(
		'object_uri'       => 'https://example.com/articles/legacy-map-only-' . wp_generate_uuid4(),
		'object_type'      => 'Article',
		'object_status'    => 'active',
		'attributed_to_uri' => $ax_vmc_actor,
		'name'             => null,
		'summary'          => null,
		'content'          => null,
		'content_language' => null,
		'payload'          => array(
			'nameMap'    => array( 'en' => 'Legacy map title' ),
			'summaryMap' => array( 'en' => '<p>Legacy map excerpt.</p>' ),
			'contentMap' => array( 'en' => '<p>Legacy map body.</p>' ),
		),
	);
	$legacy_map_model = axismundi_op_remote_source_view_model( new Axismundi_Op_Remote_Source( $legacy_map_row ) );
	ax_vmc_assert( $ax_vmc_results, 'a pre-map-only-cache row still renders from its retained raw payload instead of requiring a refetch', is_array( $legacy_map_model ) && 'Legacy map title' === (string) ( $legacy_map_model['title'] ?? '' ) && false !== strpos( (string) ( $legacy_map_model['content_html'] ?? '' ), 'Legacy map body.' ) && false !== strpos( (string) ( $legacy_map_model['content_warning'] ?? '' ), 'Legacy map excerpt.' ) && 'en' === (string) ( $legacy_map_model['language'] ?? '' ) );

	// Mastodon exposes a CW as `summary` plus Object-level `sensitive`, but does
	// not declare sensitivity on each attachment. The omitted attachment claim must
	// inherit the Object warning rather than being normalized to explicit false.
	$mastodon_cw = ax_vmc_remote_model(
		array(
			'id'           => 'https://mastodon.example/users/alice/statuses/' . wp_generate_uuid4(),
			'type'         => 'Question',
			'attributedTo' => $ax_vmc_actor,
			'to'           => array( $ax_vmc_public ),
			'summary'      => 'cw',
			'sensitive'    => true,
			'content'      => '<p>Mastodon CW body.</p>',
			'attachment'   => array( array( 'type' => 'Document', 'mediaType' => 'image/png', 'url' => $ax_vmc_media( 101 ) ) ),
		),
		$ax_vmc_remote
	);
	ax_vmc_assert(
		$ax_vmc_results,
		'a Mastodon CW Question keeps summary as its body warning and lets an undeclared attachment inherit it',
		'cw' === (string) ( $mastodon_cw['content_warning'] ?? '' )
			&& '' === (string) ( $mastodon_cw['summary'] ?? '' )
			&& array_key_exists( 'sensitive', (array) ( $mastodon_cw['attachments'][0] ?? array() ) )
			&& null === $mastodon_cw['attachments'][0]['sensitive']
			&& axismundi_op_attachment_is_sensitive( (array) ( $mastodon_cw['attachments'][0] ?? array() ), $mastodon_cw )
	);
	axismundi_op_set_current_object_view_model( $mastodon_cw );
	$mastodon_cw_content = do_blocks( '<!-- wp:axismundi/object-content /-->' );
	axismundi_op_set_current_object_view_model( null );
	ax_vmc_assert(
		$ax_vmc_results,
		'a Mastodon CW Question renders its received warning as a closed content gate',
		false !== strpos( $mastodon_cw_content, '<details' )
			&& false !== strpos( $mastodon_cw_content, '>cw</summary>' )
			&& false !== strpos( $mastodon_cw_content, 'Mastodon CW body.' )
	);

	// Misskey sends the same Object-level CW but can explicitly mark an individual
	// file non-sensitive. That precise file stays visible; otherwise one sensitive
	// sibling would accidentally hide every attachment in the Note.
	$misskey_cw = ax_vmc_remote_model(
		array(
			'id'           => 'https://misskey.example/notes/' . wp_generate_uuid4(),
			'type'         => 'Note',
			'attributedTo' => $ax_vmc_actor,
			'to'           => array( $ax_vmc_public ),
			'summary'      => 'sdf',
			'sensitive'    => true,
			'content'      => '<p>Misskey CW body.</p>',
			'attachment'   => array( array( 'type' => 'Document', 'mediaType' => 'image/jpeg', 'url' => $ax_vmc_media( 102 ), 'sensitive' => false ) ),
		),
		$ax_vmc_remote
	);
	ax_vmc_assert(
		$ax_vmc_results,
		'a Misskey CW Note keeps its summary as a body warning but honors an explicit non-sensitive file',
		'sdf' === (string) ( $misskey_cw['content_warning'] ?? '' )
			&& '' === (string) ( $misskey_cw['summary'] ?? '' )
			&& false === ( $misskey_cw['attachments'][0]['sensitive'] ?? true )
			&& ! axismundi_op_attachment_is_sensitive( (array) ( $misskey_cw['attachments'][0] ?? array() ), $misskey_cw )
	);
	axismundi_op_set_current_object_view_model( $misskey_cw );
	$misskey_cw_content = do_blocks( '<!-- wp:axismundi/object-content /-->' );
	axismundi_op_set_current_object_view_model( null );
	ax_vmc_assert(
		$ax_vmc_results,
		'a Misskey CW Note renders the Object body behind its received warning even when its file stays visible',
		false !== strpos( $misskey_cw_content, '<details' )
			&& false !== strpos( $misskey_cw_content, '>sdf</summary>' )
			&& false !== strpos( $misskey_cw_content, 'Misskey CW body.' )
	);

	$ax_vmc_sensitive_summary = array_merge(
		axismundi_op_object_view_model_defaults(),
		array(
			'id'              => 'https://example.com/articles/' . wp_generate_uuid4(),
			'object_uri'      => 'https://example.com/articles/' . wp_generate_uuid4(),
			'type'            => 'Article',
			'title'           => 'A title that must not outrank the warning',
			'summary'         => 'The sensitive Article lead.',
			'sensitive'       => true,
			'content_warning' => 'Citizen Kane',
		)
	);
	axismundi_op_set_current_object_view_model( $ax_vmc_sensitive_summary );
	$ax_vmc_sensitive_summary_html = do_blocks( '<!-- wp:axismundi/object-summary /-->' );
	axismundi_op_set_current_object_view_model( null );
	ax_vmc_assert(
		$ax_vmc_results,
		'a sensitive Article covers its stream summary and labels it with dcterms subject before title or summary',
		false !== strpos( $ax_vmc_sensitive_summary_html, 'axismundi-object__summary-gate' )
			// An Article obscures its summary in place rather than collapsing it, so the
			// label is the spoiler's own line and not a `<details>` summary element.
			&& false !== strpos( $ax_vmc_sensitive_summary_html, 'Content warning: Citizen Kane' )
			&& false === strpos( $ax_vmc_sensitive_summary_html, 'A title that must not outrank the warning' )
			&& false !== strpos( $ax_vmc_sensitive_summary_html, 'is-obscured' )
			&& false !== strpos( $ax_vmc_sensitive_summary_html, 'The sensitive Article lead.' )
	);

	$ax_vmc_excerpt_model = array_merge(
		axismundi_op_object_view_model_defaults(),
		array(
			'id'         => 'https://example.com/articles/' . wp_generate_uuid4(),
			'object_uri' => 'https://example.com/articles/' . wp_generate_uuid4(),
			'summary'    => 'One two three four five.',
			'human_url'  => 'https://example.com/articles/full-text',
		)
	);
	axismundi_op_set_current_object_view_model( $ax_vmc_excerpt_model );
	$ax_vmc_excerpt_newline = do_blocks( '<!-- wp:axismundi/object-summary {"excerptLength":3,"moreText":"Read article","showMoreOnNewLine":true} /-->' );
	$ax_vmc_excerpt_inline  = do_blocks( '<!-- wp:axismundi/object-summary {"excerptLength":3,"moreText":"Read article","showMoreOnNewLine":false} /-->' );
	axismundi_op_set_current_object_view_model( null );
	ax_vmc_assert(
		$ax_vmc_results,
		'Object Summary follows Core Post Excerpt controls for maximum words and a new-line Read more link',
		false !== strpos( $ax_vmc_excerpt_newline, 'One two three…' )
			&& false === strpos( $ax_vmc_excerpt_newline, 'four five' )
			&& false !== strpos( $ax_vmc_excerpt_newline, '<p class="wp-block-post-excerpt__more-text">' )
			&& false !== strpos( $ax_vmc_excerpt_newline, 'href="https://example.com/articles/full-text"' )
	);
	ax_vmc_assert(
		$ax_vmc_results,
		'Object Summary can keep its Read more link inline with the excerpt',
		false !== strpos( $ax_vmc_excerpt_inline, '<p class="wp-block-post-excerpt__excerpt is-inline">' )
			&& false !== strpos( $ax_vmc_excerpt_inline, '</p> <a class="wp-block-post-excerpt__more-link"' )
	);

	$ax_vmc_metadata_model = array_merge(
		axismundi_op_object_view_model_defaults(),
		array(
			'id'         => 'https://example.com/articles/' . wp_generate_uuid4(),
			'object_uri' => 'https://example.com/articles/' . wp_generate_uuid4(),
			'type'       => 'Article',
			'published'  => '2026-07-20T10:00:00+00:00',
			'updated'    => '2026-07-21T11:30:00+00:00',
			'human_url'  => 'https://example.com/articles/metadata',
		)
	);
	axismundi_op_set_current_object_view_model( $ax_vmc_metadata_model );
	$ax_vmc_metadata_html = do_blocks( '<!-- wp:axismundi/object-type /--><!-- wp:axismundi/object-date /--><!-- wp:axismundi/object-date {"field":"updated","format":"Y-m-d H:i","isLink":true} /-->' );
	axismundi_op_set_current_object_view_model( null );
	ax_vmc_assert(
		$ax_vmc_results,
		'Object Type and Object Date split the legacy meta surface into dynamic Core siblings for type, publication, and modification time',
		false !== strpos( $ax_vmc_metadata_html, 'wp-block-query-title' )
			&& false !== strpos( $ax_vmc_metadata_html, '>Article</span>' )
			&& false !== strpos( $ax_vmc_metadata_html, 'datetime="2026-07-20T10:00:00+00:00"' )
			&& false !== strpos( $ax_vmc_metadata_html, 'wp-block-post-date__modified-date' )
			&& false !== strpos( $ax_vmc_metadata_html, '2026-07-21 11:30' )
			&& false !== strpos( $ax_vmc_metadata_html, 'href="https://example.com/articles/metadata"' )
	);

	ax_vmc_assert( $ax_vmc_results, 'a cached remote Object reports public visibility and its human URL slot', 'public' === (string) ( $plain['visibility']['level'] ?? '' ) && array_key_exists( 'human_url', $plain ) );

	$limited = ax_vmc_remote_model(
		array( 'id' => 'https://example.com/notes/' . wp_generate_uuid4(), 'type' => 'Note', 'attributedTo' => $ax_vmc_actor, 'to' => array( $ax_vmc_actor . '/followers' ), 'content' => '<p>SECRET-CONTRACT-BODY</p>' ),
		$ax_vmc_remote
	);
	ax_vmc_assert( $ax_vmc_results, 'a followers-only remote Object reports limited visibility', 'limited' === (string) ( $limited['visibility']['level'] ?? '' ) );

	// A public Announce or quote must never widen the audience of what it points
	// at, so a limited remote Object is not renderable as a public card.
	$limited_uri  = (string) ( $limited['object_uri'] ?? '' );
	$limited_card = '' !== $limited_uri ? axismundi_op_render_object_by_uri( $limited_uri, array( 'headingTag' => 'h3', 'interactions' => false ) ) : 'unrendered';
	$public_uri   = (string) ( $plain['object_uri'] ?? '' );
	$public_card  = '' !== $public_uri ? axismundi_op_render_object_by_uri( $public_uri, array( 'headingTag' => 'h3', 'interactions' => false ) ) : '';
	ax_vmc_assert(
		$ax_vmc_results,
		'a limited remote Object never renders as a public card while a public one still does',
		'' === $limited_card && false === strpos( $limited_card, 'SECRET-CONTRACT-BODY' ) && false !== strpos( $public_card, 'Body.' )
	);

	// `attachment` states relatedness, not placement. Media already referenced by
	// the body belongs to the body; a titled long object leads with one image and
	// files stay downloads.
	$article = ax_vmc_remote_model(
		array(
			'id'           => 'https://example.com/notes/' . wp_generate_uuid4(),
			'type'         => 'Article',
			'attributedTo' => $ax_vmc_actor,
			'to'           => array( $ax_vmc_public ),
			'name'         => 'Long form',
			'content'      => '<p>Body</p><img src="' . $ax_vmc_media( 1 ) . '" />',
			'attachment'   => array( $ax_vmc_image( 1 ), $ax_vmc_image( 2 ), array( 'type' => 'Document', 'mediaType' => 'application/pdf', 'url' => 'https://example.com/vmc.pdf' ) ),
		),
		$ax_vmc_remote
	);
	ax_vmc_assert(
		$ax_vmc_results,
		'body-referenced media is not repeated, one image leads an article, and files stay downloads',
		'article' === (string) ( $article['presentation']['profile'] ?? '' )
			&& 1 === count( (array) ( $article['media']['inline_refs'] ?? array() ) )
			&& is_array( $article['media']['featured'] ?? null )
			&& 1 === count( (array) ( $article['media']['downloads'] ?? array() ) )
			&& array() === (array) ( $article['media']['before_content'] ?? array() )
	);

	// The derived lead/thumbnail candidate and the author's declared image are kept
	// apart: this article declared no `image`, so `media.featured` was inferred from
	// an attachment for a compact card, while `media.image` stays null. Blurring them
	// would let a receiver invent an author-chosen hero the sender never sent.
	ax_vmc_assert(
		$ax_vmc_results,
		'an attachment can seed the derived thumbnail candidate but never the declared image',
		is_array( $article['media']['featured'] ?? null )
			&& array_key_exists( 'image', (array) $article['media'] )
			&& null === $article['media']['image']
	);

	$gallery = ax_vmc_remote_model(
		array( 'id' => 'https://example.com/notes/' . wp_generate_uuid4(), 'type' => 'Note', 'attributedTo' => $ax_vmc_actor, 'to' => array( $ax_vmc_public ), 'content' => '<p>Caption</p>', 'attachment' => array( $ax_vmc_image( 3 ), $ax_vmc_image( 4 ), $ax_vmc_image( 5 ), $ax_vmc_image( 6 ), $ax_vmc_image( 7 ) ) ),
		$ax_vmc_remote
	);
	ax_vmc_assert(
		$ax_vmc_results,
		'a short object carrying unreferenced visuals leads with them as a carousel',
		'media-first' === (string) ( $gallery['presentation']['profile'] ?? '' )
			&& 5 === count( (array) ( $gallery['media']['before_content'] ?? array() ) )
			&& 'carousel' === (string) ( $gallery['media']['layout'] ?? '' )
	);

	ax_vmc_assert( $ax_vmc_results, 'a cached remote Object is never editable', false === (bool) ( $plain['capabilities']['can_edit'] ?? true ) );

	// `media.featured` is the single lead image a card renders. A declared AS2 `image`
	// is the author's own choice of lead, so it outranks anything inferred from the
	// attachment list -- and it does not consume that attachment, which stays related
	// media in its own right.
	$declared = ax_vmc_remote_model(
		array(
			'id'           => 'https://example.com/notes/' . wp_generate_uuid4(),
			'type'         => 'Article',
			'attributedTo' => $ax_vmc_actor,
			'to'           => array( $ax_vmc_public ),
			'name'         => 'Declared lead',
			'content'      => str_repeat( '<p>Long body.</p>', 90 ),
			'image'        => array( 'type' => 'Image', 'mediaType' => 'image/jpeg', 'name' => 'Hero alt', 'width' => 1200, 'height' => 630, 'url' => 'https://example.com/vmc-hero.jpg' ),
			'attachment'   => array( $ax_vmc_image( 8 ) ),
		),
		$ax_vmc_remote
	);
	$declared_featured = (array) ( $declared['media']['featured'] ?? array() );
	ax_vmc_assert(
		$ax_vmc_results,
		'a declared image leads the card without consuming the attachment it was declared beside',
		'https://example.com/vmc-hero.jpg' === (string) ( $declared_featured['url'] ?? '' )
			&& 'Hero alt' === (string) ( $declared_featured['alt'] ?? '' )
			&& 1200 === (int) ( $declared_featured['width'] ?? 0 )
			&& 1 === count( (array) ( $declared['media']['after_content'] ?? array() ) )
	);

	// A peer may state `image` as a bare URL or as a list. Both are the same claim, so
	// both have to reach a block in the one shape it knows how to render.
	$string_form = ax_vmc_remote_model(
		array( 'id' => 'https://example.com/notes/' . wp_generate_uuid4(), 'type' => 'Note', 'attributedTo' => $ax_vmc_actor, 'to' => array( $ax_vmc_public ), 'content' => '<p>Body.</p>', 'image' => 'https://example.com/vmc-string.jpg' ),
		$ax_vmc_remote
	);
	$list_form = ax_vmc_remote_model(
		array( 'id' => 'https://example.com/notes/' . wp_generate_uuid4(), 'type' => 'Note', 'attributedTo' => $ax_vmc_actor, 'to' => array( $ax_vmc_public ), 'content' => '<p>Body.</p>', 'image' => array( $ax_vmc_image( 9 ), $ax_vmc_image( 10 ) ) ),
		$ax_vmc_remote
	);
	// A peer may also state the image once and its renditions as Link objects, which is
	// where the dimensions then live. The lead slot still has to end up with one URL.
	$link_form = ax_vmc_remote_model(
		array(
			'id'           => 'https://example.com/notes/' . wp_generate_uuid4(),
			'type'         => 'Note',
			'attributedTo' => $ax_vmc_actor,
			'to'           => array( $ax_vmc_public ),
			'content'      => '<p>Body.</p>',
			'image'        => array( 'type' => 'Image', 'name' => 'Link alt', 'url' => array( array( 'type' => 'Link', 'href' => 'https://example.com/vmc-link.jpg', 'mediaType' => 'image/jpeg', 'width' => 640, 'height' => 480 ) ) ),
		),
		$ax_vmc_remote
	);
	ax_vmc_assert(
		$ax_vmc_results,
		'an image stating its rendition as a Link still resolves to one URL and keeps that Link dimensions',
		'https://example.com/vmc-link.jpg' === (string) ( $link_form['media']['featured']['url'] ?? '' )
			&& 640 === (int) ( $link_form['media']['featured']['width'] ?? 0 )
			&& 480 === (int) ( $link_form['media']['featured']['height'] ?? 0 )
			&& 'image/jpeg' === (string) ( $link_form['media']['featured']['mediaType'] ?? '' )
	);

	$shape = array( 'url', 'alt', 'width', 'height', 'mediaType' );
	ax_vmc_assert(
		$ax_vmc_results,
		'a bare-URL image, a list image, and an attachment-derived lead all normalize to one descriptor shape',
		$shape === array_keys( (array) ( $string_form['media']['featured'] ?? array() ) )
			&& $shape === array_keys( (array) ( $list_form['media']['featured'] ?? array() ) )
			&& $shape === array_keys( (array) ( $article['media']['featured'] ?? array() ) )
			&& $shape === array_keys( $declared_featured )
			&& 'https://example.com/vmc-string.jpg' === (string) ( $string_form['media']['featured']['url'] ?? '' )
			&& $ax_vmc_media( 9 ) === (string) ( $list_form['media']['featured']['url'] ?? '' )
	);

	// Sensitivity is a property of the Object, not of its lead image: one flag drives
	// both the body warning and the image blur, so the descriptor must not carry a
	// second, separately-answerable one.
	ax_vmc_assert(
		$ax_vmc_results,
		'a featured descriptor carries no sensitivity of its own and inherits the Object flag',
		! array_key_exists( 'sensitive', $declared_featured ) && ! array_key_exists( 'summary', $declared_featured )
	);

	// Local Post adapter: editing capability and index-backed enrichment.
	wp_set_current_user( 1 );
	$ax_vmc_post = (int) wp_insert_post( array( 'post_type' => 'post', 'post_status' => 'publish', 'post_author' => 1, 'post_title' => 'Contract local post', 'post_excerpt' => 'Editorial excerpt', 'post_content' => '<p>Lead body.</p><!-- wp:more --><!--more--><!-- /wp:more --><p>Local body.</p>' ), true );
	if ( $ax_vmc_post > 0 ) {
		$ax_vmc_posts[] = $ax_vmc_post;
	}
	$ax_vmc_term = axismundi_op_ensure_hashtag_term( 'VmcTag' . $ax_vmc_suffix );
	if ( $ax_vmc_term instanceof WP_Term ) {
		$ax_vmc_terms[] = (int) $ax_vmc_term->term_id;
		wp_set_object_terms( $ax_vmc_post, array( (int) $ax_vmc_term->term_id ), AXISMUNDI_OP_HASHTAG_TAXONOMY );
	}
	$local_source = axismundi_op_resolve_source_by_uri( axismundi_op_post_object_uri( get_post( $ax_vmc_post ) ) );
	$local        = null === $local_source ? array() : (array) axismundi_op_object_view_model( $local_source );
	ax_vmc_assert( $ax_vmc_results, 'a local Post an editor may edit reports can_edit and exposes the More lead without promoting its separate editorial excerpt to Article summary', true === (bool) ( $local['capabilities']['can_edit'] ?? false ) && 'Lead body.' === (string) ( $local['summary'] ?? '' ) && 'Editorial excerpt' !== (string) ( $local['summary'] ?? '' ) );
	ax_vmc_assert( $ax_vmc_results, 'a local Post exposes a human URL distinct from its canonical id slot', '' !== (string) ( $local['human_url'] ?? '' ) );

	// Relation lookups are index-backed, so a reply tree resolving a model per
	// node must not pay for them. They resolve when an Object becomes the one
	// being rendered as a full card.
	$before_enrichment = (array) ( $local['hashtags'] ?? array() );
	axismundi_op_set_current_object_view_model( $local );
	$enriched = (array) axismundi_op_current_object_view_model();
	axismundi_op_set_current_object_view_model( null );
	ax_vmc_assert(
		$ax_vmc_results,
		'relation enrichment is deferred to the full-card boundary rather than every resolved model',
		array() === $before_enrichment
			&& 1 === count( (array) ( $enriched['hashtags'] ?? array() ) )
			&& true === (bool) ( $enriched['_enriched'] ?? false )
			&& array_key_exists( 'in_reply_to', $enriched )
			&& array_key_exists( 'mentions', $enriched )
	);

	// A local Post says "lead image" with a featured image, and the Media Library
	// indexes that as an `as:image` relation. The card reads the same `media.featured`
	// slot a remote object fills, so the two paths have to converge here. The fixture
	// image is large enough to generate a derivative on purpose: the media policy
	// advertises renditions and structurally excludes the original, so a thumbnail-sized
	// source would legitimately produce no image at all.
	require_once ABSPATH . 'wp-admin/includes/image.php';
	$ax_vmc_uploads = wp_upload_dir();
	$ax_vmc_file    = $ax_vmc_uploads['path'] . '/vmc-featured-' . $ax_vmc_suffix . '.png';
	$ax_vmc_canvas  = imagecreatetruecolor( 400, 300 );
	imagefill( $ax_vmc_canvas, 0, 0, imagecolorallocate( $ax_vmc_canvas, 10, 120, 200 ) );
	imagepng( $ax_vmc_canvas, $ax_vmc_file );
	imagedestroy( $ax_vmc_canvas );
	$ax_vmc_fi_post = (int) wp_insert_post( array( 'post_type' => 'post', 'post_status' => 'publish', 'post_author' => 1, 'post_title' => 'Contract featured image', 'post_content' => '<p>Body.</p>' ), true );
	$ax_vmc_posts[] = $ax_vmc_fi_post;
	$ax_vmc_att     = (int) wp_insert_attachment( array( 'post_mime_type' => 'image/png', 'post_title' => 'Contract lead image', 'post_status' => 'inherit', 'post_author' => 1 ), $ax_vmc_file, $ax_vmc_fi_post, true );
	$ax_vmc_atts[]  = $ax_vmc_att;
	wp_update_attachment_metadata( $ax_vmc_att, wp_generate_attachment_metadata( $ax_vmc_att, $ax_vmc_file ) );
	update_post_meta( $ax_vmc_att, '_wp_attachment_image_alt', 'Local lead alt' );
	set_post_thumbnail( $ax_vmc_fi_post, $ax_vmc_att );
	$ax_vmc_fi_src   = axismundi_op_resolve_source_by_uri( axismundi_op_post_object_uri( get_post( $ax_vmc_fi_post ) ) );
	$ax_vmc_fi_model = null === $ax_vmc_fi_src ? array() : (array) axismundi_op_object_view_model( $ax_vmc_fi_src );
	$ax_vmc_fi       = (array) ( $ax_vmc_fi_model['media']['featured'] ?? array() );
	ax_vmc_assert(
		$ax_vmc_results,
		'a local Post featured image fills the same lead slot, in the same shape, with its alt and dimensions',
		$shape === array_keys( $ax_vmc_fi )
			&& 'Local lead alt' === (string) ( $ax_vmc_fi['alt'] ?? '' )
			&& (int) ( $ax_vmc_fi['width'] ?? 0 ) > 0
			&& (int) ( $ax_vmc_fi['height'] ?? 0 ) > 0
			&& 'image/png' === (string) ( $ax_vmc_fi['mediaType'] ?? '' )
			&& false !== strpos( (string) ( $ax_vmc_fi['url'] ?? '' ), 'vmc-featured-' . $ax_vmc_suffix )
	);
} finally {
	wp_set_current_user( $ax_vmc_user );
	foreach ( $ax_vmc_remote as $ax_vmc_uri ) {
		axismundi_op_remote_object_delete( $ax_vmc_uri );
	}
	// Attachments outlive their parent post, so the file and its derivatives have to be
	// removed explicitly or a fixture leaves uploads behind on every run.
	foreach ( $ax_vmc_atts as $ax_vmc_att_id ) {
		wp_delete_attachment( (int) $ax_vmc_att_id, true );
	}
	foreach ( $ax_vmc_posts as $ax_vmc_post_id ) {
		wp_delete_post( (int) $ax_vmc_post_id, true );
	}
	// `ax_hashtag` is site-wide vocabulary; a fixture term must never survive.
	foreach ( $ax_vmc_terms as $ax_vmc_term_id ) {
		wp_delete_term( (int) $ax_vmc_term_id, AXISMUNDI_OP_HASHTAG_TAXONOMY );
	}
}

$ax_vmc_failures = count( array_filter( $ax_vmc_results, static fn( bool $result ) : bool => ! $result ) );
// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI test output.
printf( "\n== %d checks, %d failed ==\n", count( $ax_vmc_results ), $ax_vmc_failures );
if ( class_exists( 'WP_CLI' ) ) {
	WP_CLI::halt( $ax_vmc_failures > 0 ? 1 : 0 );
}
exit( $ax_vmc_failures > 0 ? 1 : 0 );
