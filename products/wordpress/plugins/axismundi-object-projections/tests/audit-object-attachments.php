<?php
/**
 * Object Attachments block + received-sensitivity contract regression.
 *
 * Locks the receive side of ActivityStreams sensitivity and the Core Gallery sibling
 * markup:
 *   - effective sensitivity is `attachment.sensitive ?? object.sensitive`: an explicit
 *     per-attachment claim wins, while an omitted field inherits the Object flag;
 *   - warning text prefers the attachment's own summary, then the Object's;
 *   - items render as Core's nested-image Gallery structure so Core's stylesheet lays
 *     them out, with the sensitive figure decorated in place (never wrapped), which is
 *     what keeps a gated item inside the gallery grid;
 *   - remote media is hot-linked without a referrer and never rewritten to a local URL;
 *   - non-visual downloads and Tombstones produce nothing.
 *
 * @package AxismundiObjectProjections
 */

defined( 'ABSPATH' ) || exit( 1 );

$ax_att_results = array();

// `get_block_wrapper_attributes()` reads the block currently being rendered. Calling the
// renderer directly (rather than through do_blocks) leaves that unset, so the harness
// supplies it; without this the assertions still hold but Core emits a null-offset notice.
WP_Block_Supports::$block_to_render = array( 'blockName' => 'axismundi/object-attachments', 'attrs' => array() );

/** @param bool[] $results Results. */
function ax_att_assert( array &$results, string $label, bool $condition ) : void {
	$results[] = $condition;
	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI fixture output.
	printf( "[%s] %s\n", $condition ? 'PASS' : 'FAIL', $label );
}

/** One image attachment descriptor in the shape the view model normalizes to. */
function ax_att_image( string $href, ?bool $sensitive = null, string $summary = '', string $name = '' ) : array {
	$descriptor = array(
		'type'      => 'Document',
		'name'      => $name,
		'mediaType' => 'image/webp',
		'url'       => array( array( 'type' => 'Link', 'href' => $href, 'mediaType' => 'image/webp' ) ),
		'summary'   => $summary,
	);
	if ( null !== $sensitive ) {
		$descriptor['sensitive'] = $sensitive;
	}
	return $descriptor;
}

/** Build a model carrying the given attachments as visual, pre-classified items. */
function ax_att_model( array $attachments, bool $object_sensitive = false, string $warning = '' ) : array {
	$model                           = axismundi_op_object_view_model_defaults();
	$model['status']                 = 'active';
	$model['sensitive']              = $object_sensitive;
	$model['content_warning']        = $warning;
	$model['attachments']            = $attachments;
	$model['media']['before_content'] = $attachments;
	return $model;
}

/** Render the block against one model. */
function ax_att_render( array $model, array $attributes = array() ) : string {
	axismundi_op_set_current_object_view_model( $model );
	$html = axismundi_op_render_object_attachments_block( $attributes );
	axismundi_op_set_current_object_view_model( null );
	return $html;
}

$ax_att_a = 'https://media.example.test/one.webp';
$ax_att_b = 'https://media.example.test/two.webp';

// --- per-attachment override / Object inheritance -------------------------------
$ax_att_neither = ax_att_render( ax_att_model( array( ax_att_image( $ax_att_a, false ) ) ) );
ax_att_assert(
	$ax_att_results,
	'neither the Object nor the attachment sensitive renders ungated media',
	false === strpos( $ax_att_neither, 'ax-media-sensitive' ) && false !== strpos( $ax_att_neither, $ax_att_a )
);

$ax_att_only_attachment = ax_att_render( ax_att_model( array( ax_att_image( $ax_att_a, true ) ) ) );
ax_att_assert(
	$ax_att_results,
	'a sensitive attachment inside a non-sensitive Object is gated',
	1 === substr_count( $ax_att_only_attachment, 'ax-media-sensitive is-hidden' )
);

// The Object flag is an aggregate, so an explicit per-attachment `false` wins over it.
$ax_att_only_object = ax_att_render( ax_att_model( array( ax_att_image( $ax_att_a, false ) ), true ) );
ax_att_assert(
	$ax_att_results,
	'an attachment declaring itself not sensitive is not gated by the aggregate Object flag',
	false === strpos( $ax_att_only_object, 'ax-media-sensitive' )
);

// The real Misskey shape: Object sensitive (aggregate), one file false and one true.
// Exactly one item may be gated, or one flagged file would gate its siblings.
$ax_att_mixed = ax_att_render(
	ax_att_model( array( ax_att_image( $ax_att_a, false ), ax_att_image( $ax_att_b, true ) ), true )
);
ax_att_assert(
	$ax_att_results,
	'a Misskey-shaped Note gates only the file flagged sensitive, not its siblings',
	1 === substr_count( $ax_att_mixed, 'ax-media-sensitive is-hidden' )
		&& false !== strpos( $ax_att_mixed, 'src="' . $ax_att_a . '"' )
);

// Mastodon has no per-attachment field: undeclared attachments inherit the Object.
$ax_att_inherited = ax_att_render(
	ax_att_model( array( ax_att_image( $ax_att_a ), ax_att_image( $ax_att_b ) ), true )
);
ax_att_assert(
	$ax_att_results,
	'attachments that declare nothing inherit the Object flag (the Mastodon shape)',
	2 === substr_count( $ax_att_inherited, 'ax-media-sensitive is-hidden' )
);

// An attachment with no `sensitive` key under a non-sensitive Object stays open.
$ax_att_absent = ax_att_render( ax_att_model( array( ax_att_image( $ax_att_a ) ) ) );
ax_att_assert(
	$ax_att_results,
	'an undeclared attachment under a non-sensitive Object is not gated',
	false === strpos( $ax_att_absent, 'ax-media-sensitive' )
);

// The tri-state has to survive normalization, or "undeclared" collapses into "false"
// and the Mastodon inheritance case above silently stops working.
$ax_att_normalized = axismundi_op_remote_view_attachments(
	array(
		'attachment' => array(
			array( 'type' => 'Document', 'mediaType' => 'image/webp', 'url' => $ax_att_a ),
			array( 'type' => 'Document', 'mediaType' => 'image/webp', 'url' => $ax_att_b, 'sensitive' => false ),
		),
	)
);
ax_att_assert(
	$ax_att_results,
	'normalization keeps `sensitive` tri-state: undeclared stays null, explicit false stays false',
	array_key_exists( 'sensitive', $ax_att_normalized[0] ?? array() )
		&& null === $ax_att_normalized[0]['sensitive']
		&& false === ( $ax_att_normalized[1]['sensitive'] ?? 'missing' )
);

// --- warning text precedence ----------------------------------------------------
ax_att_assert(
	$ax_att_results,
	'the attachment summary is the warning when it has one',
	'nudity' === axismundi_op_attachment_warning_text(
		ax_att_image( $ax_att_a, true, 'nudity' ),
		ax_att_model( array(), true, 'object level' )
	)
);
ax_att_assert(
	$ax_att_results,
	'the Object warning applies when the attachment states none',
	'object level' === axismundi_op_attachment_warning_text(
		ax_att_image( $ax_att_a, true ),
		ax_att_model( array(), true, 'object level' )
	)
);

// --- Core Gallery sibling markup ------------------------------------------------
ax_att_assert(
	$ax_att_results,
	'items render as Core nested-image Gallery markup so Core styles the grid',
	false !== strpos( $ax_att_mixed, 'wp-block-gallery' )
		&& false !== strpos( $ax_att_mixed, 'has-nested-images' )
		&& 2 === substr_count( $ax_att_mixed, '<figure class="wp-block-image' )
);
ax_att_assert(
	$ax_att_results,
	'a gated item keeps its figure as the direct gallery child instead of gaining a wrapper',
	false !== strpos( $ax_att_mixed, '<figure class="wp-block-image ax-media-sensitive is-hidden"' )
		// The legacy wrapping form (a gating div around the figure) is what breaks Core
		// Gallery's direct-child geometry. The overlay div inside the figure is expected.
		&& false === strpos( $ax_att_mixed, '<div class="ax-media-sensitive is-hidden"' )
);
ax_att_assert(
	$ax_att_results,
	'the gallery gap variable is emitted so Core column math matches the real gap',
	false !== strpos( $ax_att_mixed, '--wp--style--unstable-gallery-gap:' )
		&& false !== strpos( $ax_att_mixed, ';gap:' )
);
$ax_att_columns = ax_att_render(
	ax_att_model( array( ax_att_image( $ax_att_a ), ax_att_image( $ax_att_b ) ) ),
	array( 'columns' => 3, 'imageCrop' => false )
);
ax_att_assert(
	$ax_att_results,
	'columns and crop map onto Core gallery class names',
	false !== strpos( $ax_att_columns, 'columns-3' ) && false === strpos( $ax_att_columns, 'is-cropped' )
);
$ax_att_ratio = ax_att_render(
	ax_att_model( array( ax_att_image( $ax_att_a ) ) ),
	array( 'aspectRatio' => '1/1', 'imageCrop' => false )
);
ax_att_assert(
	$ax_att_results,
	'aspect ratio preserves the source image inside its selected frame when cropping is off',
	false !== strpos( $ax_att_ratio, 'aspect-ratio:1/1;object-fit:contain' )
);

// --- gallery / carousel display modes --------------------------------------------
// One block, two renderings of the same attachment set — not two blocks. Gallery view
// borrows Core's Gallery contract; carousel view is this block's own presentation and
// must not inherit the grid classes, or Core would size items a track already places.
$ax_att_carousel = ax_att_render(
	ax_att_model( array( ax_att_image( $ax_att_a ), ax_att_image( $ax_att_b, true ) ) ),
	array( 'displayMode' => 'carousel' )
);
ax_att_assert(
	$ax_att_results,
	'carousel mode emits a track of slides and drops Core gallery grid classes',
	false !== strpos( $ax_att_carousel, 'data-ax-carousel' )
		&& 2 === substr_count( $ax_att_carousel, 'axismundi-object__carousel-slide' )
		&& false === strpos( $ax_att_carousel, 'wp-block-gallery' )
		&& false === strpos( $ax_att_carousel, 'has-nested-images' )
);
ax_att_assert(
	$ax_att_results,
	'carousel mode ships previous/next and one dot per slide',
	1 === substr_count( $ax_att_carousel, 'carousel-nav--prev' )
		&& 1 === substr_count( $ax_att_carousel, 'carousel-nav--next' )
		&& 2 === substr_count( $ax_att_carousel, 'aria-label="Go to slide' )
);
ax_att_assert(
	$ax_att_results,
	'sensitivity is enforced identically in carousel mode',
	1 === substr_count( $ax_att_carousel, 'ax-media-sensitive is-hidden' )
		&& 1 === substr_count( $ax_att_carousel, 'ax-media-sensitive__reveal' )
);
// Every slide is emitted server-side and in order, so the media stays reachable with
// the view script absent -- and no slide is addressed by a per-image id or URL hash,
// which is what keeps Back out of the slide sequence.
ax_att_assert(
	$ax_att_results,
	'carousel slides are server-rendered in order and carry no per-image id or hash anchor',
	strpos( $ax_att_carousel, $ax_att_a ) < strpos( $ax_att_carousel, $ax_att_b )
		&& false === strpos( $ax_att_carousel, 'id="' )
		&& false === strpos( $ax_att_carousel, 'href="#' )
);
$ax_att_single = ax_att_render(
	ax_att_model( array( ax_att_image( $ax_att_a ) ) ),
	array( 'displayMode' => 'carousel' )
);
ax_att_assert(
	$ax_att_results,
	'a single attachment gets no navigation controls',
	false === strpos( $ax_att_single, 'carousel-nav' ) && false === strpos( $ax_att_single, 'carousel-dot' )
);

// --- what belongs in the media sequence ------------------------------------------
// A carousel is a way to look through things. Video qualifies; audio does not, so it is
// excluded from the sequence — but still rendered, because dropping it would lose media
// a peer actually sent.
$ax_att_audio = array(
	'type'      => 'Document',
	'name'      => '',
	'mediaType' => 'audio/mpeg',
	'url'       => array( array( 'type' => 'Link', 'href' => 'https://media.example.test/track.mp3' ) ),
	'summary'   => '',
);
$ax_att_video = array(
	'type'      => 'Document',
	'name'      => '',
	'mediaType' => 'video/mp4',
	'url'       => array( array( 'type' => 'Link', 'href' => 'https://media.example.test/clip.mp4' ) ),
	'summary'   => '',
);
$ax_att_mixed_media = ax_att_render(
	ax_att_model( array( ax_att_image( $ax_att_a ), $ax_att_video, $ax_att_audio ) ),
	array( 'displayMode' => 'carousel' )
);
ax_att_assert(
	$ax_att_results,
	'the carousel sequence holds images and video but not audio',
	2 === substr_count( $ax_att_mixed_media, 'axismundi-object__carousel-slide' )
		&& false !== strpos( $ax_att_mixed_media, 'clip.mp4' )
);
ax_att_assert(
	$ax_att_results,
	'audio still renders, outside the sequence rather than dropped',
	false !== strpos( $ax_att_mixed_media, 'track.mp3' )
		&& false !== strpos( $ax_att_mixed_media, 'axismundi-object__attachments-aside' )
		&& false !== strpos( $ax_att_mixed_media, 'data-ax-media-aside' )
);
// Sequence membership is marked by data-ax-media-index, which is also the filter the
// dialog clones by — so audio cannot leak into the dialog's carousel either.
ax_att_assert(
	$ax_att_results,
	'only sequence members carry the index the dialog clones by',
	2 === substr_count( $ax_att_mixed_media, 'data-ax-media-index' )
		&& 1 === substr_count( $ax_att_mixed_media, 'data-ax-media-aside' )
);
// An Object carrying only audio has no sequence at all, and must not emit an empty one.
$ax_att_audio_only = ax_att_render(
	ax_att_model( array( $ax_att_audio ) ),
	array( 'displayMode' => 'carousel' )
);
ax_att_assert(
	$ax_att_results,
	'an audio-only Object renders no empty carousel',
	false === strpos( $ax_att_audio_only, 'data-ax-carousel' )
		&& false !== strpos( $ax_att_audio_only, 'track.mp3' )
);

// --- preview limit ----------------------------------------------------------------
// The overflow must stay in the DOM: the dialog builds its carousel by cloning these
// figures, so hiding beyond the limit is presentation, never truncation.
$ax_att_six = array();
for ( $ax_att_i = 0; $ax_att_i < 6; $ax_att_i++ ) {
	$ax_att_six[] = ax_att_image( 'https://media.example.test/' . $ax_att_i . '.webp' );
}
$ax_att_limited = ax_att_render( ax_att_model( $ax_att_six ), array( 'previewCount' => 4 ) );
ax_att_assert(
	$ax_att_results,
	'a preview limit hides the overflow without removing it from the dialog source',
	6 === substr_count( $ax_att_limited, 'data-ax-media-index' )
		&& 2 === substr_count( $ax_att_limited, 'is-preview-overflow' )
);
ax_att_assert(
	$ax_att_results,
	'the remaining count is badged on the last visible tile',
	false !== strpos( $ax_att_limited, '>+2<' )
		&& strpos( $ax_att_limited, 'attachment-more' ) > strpos( $ax_att_limited, '3.webp' )
		&& strpos( $ax_att_limited, 'attachment-more' ) < strpos( $ax_att_limited, '4.webp' )
);
$ax_att_default = ax_att_render( ax_att_model( $ax_att_six ) );
ax_att_assert(
	$ax_att_results,
	'the default gallery is two columns, five-by-three, and previews four items without removing dialog sources',
	6 === substr_count( $ax_att_default, 'data-ax-media-index' )
		&& 2 === substr_count( $ax_att_default, 'is-preview-overflow' )
		&& false !== strpos( $ax_att_default, '>+2<' )
		&& false !== strpos( $ax_att_default, 'columns-2' )
		&& false !== strpos( $ax_att_default, 'aspect-ratio:5/3;object-fit:cover' )
);
$ax_att_unlimited = ax_att_render( ax_att_model( $ax_att_six ), array( 'previewCount' => 0 ) );
ax_att_assert(
	$ax_att_results,
	'an explicit zero preview count shows every attachment and no badge',
	false === strpos( $ax_att_unlimited, 'is-preview-overflow' )
		&& false === strpos( $ax_att_unlimited, 'attachment-more' )
);
// A limit at or above the count is not a limit.
$ax_att_exact = ax_att_render( ax_att_model( $ax_att_six ), array( 'previewCount' => 6 ) );
ax_att_assert(
	$ax_att_results,
	'a limit equal to the attachment count adds no badge',
	false === strpos( $ax_att_exact, 'attachment-more' ) && false === strpos( $ax_att_exact, 'is-preview-overflow' )
);

// --- what the block hands the media dialog ---------------------------------------
// The dialog surface belongs to Axismundi Dialogs; this block publishes the data and
// the open affordances, and must render identically with no dialog on the page.
ax_att_assert(
	$ax_att_results,
	'the block publishes its Object and one opener per viewable image for the dialog',
	false !== strpos( $ax_att_mixed, 'data-ax-object-media' )
		&& 2 === substr_count( $ax_att_mixed, 'data-ax-media-index' )
		&& 2 === substr_count( $ax_att_mixed, 'data-ax-media-open' )
);
// The opener must precede the warning overlay: the overlay paints last, covers the
// figure, and swallows the click, so gated media cannot be opened before it is revealed.
ax_att_assert(
	$ax_att_results,
	'a gated item places its opener before the warning overlay so the overlay wins the click',
	strpos( $ax_att_mixed, 'data-ax-media-open="1"' ) < strpos( $ax_att_mixed, 'ax-media-sensitive__overlay' )
);
// Linking to the media file is an alternative destination, so no dialog opener is added.
$ax_att_linked = ax_att_render(
	ax_att_model( array( ax_att_image( $ax_att_a ) ) ),
	array( 'linkTo' => 'media' )
);
ax_att_assert(
	$ax_att_results,
	'linking an item to its media file suppresses the dialog opener',
	false === strpos( $ax_att_linked, 'data-ax-media-open' ) && false !== strpos( $ax_att_linked, '<a href=' )
);

// The side panel is a projection of the Object, shipped inert inside a <template>.
$ax_att_panel_model            = ax_att_model( array( ax_att_image( $ax_att_a ) ) );
$ax_att_panel_model['content_html'] = '<p>Body of the post.</p>';
$ax_att_panel_model['human_url']    = 'https://remote.test/notes/1';
$ax_att_panel_model['reply_context'] = array(
	'available' => true,
	'uri'       => 'https://remote.test/notes/parent',
	'url'       => 'https://remote.test/notes/parent',
	'excerpt'   => 'The parent post.',
	'author'    => array( 'name' => 'Alice', 'handle' => '@alice@remote.test' ),
);
$ax_att_with_panel = ax_att_render( $ax_att_panel_model );
ax_att_assert(
	$ax_att_results,
	'the side panel ships inside a template carrying the body, permalink, and one reply ancestor',
	false !== strpos( $ax_att_with_panel, '<template class="axismundi-object__media-panel-data">' )
		&& false !== strpos( $ax_att_with_panel, 'Body of the post.' )
		&& false !== strpos( $ax_att_with_panel, 'axismundi-object__reply-context' )
		&& false !== strpos( $ax_att_with_panel, 'The parent post.' )
);
// A sensitive Object's body stays behind its warning in the dialog too.
$ax_att_panel_model['sensitive']       = true;
$ax_att_panel_model['content_warning'] = 'eye contact';
$ax_att_gated_panel                    = ax_att_render( $ax_att_panel_model );
ax_att_assert(
	$ax_att_results,
	'a sensitive Object keeps its body behind the warning in the dialog panel',
	false !== strpos( $ax_att_gated_panel, 'axismundi-object__sensitive' )
		&& false !== strpos( $ax_att_gated_panel, 'eye contact' )
);

// --- hot-linking, not caching ---------------------------------------------------
ax_att_assert(
	$ax_att_results,
	'remote media is hot-linked unrewritten and sent without a referrer',
	false !== strpos( $ax_att_mixed, 'src="' . $ax_att_a . '"' )
		&& 2 === substr_count( $ax_att_mixed, 'referrerpolicy="no-referrer"' )
		&& false === strpos( $ax_att_mixed, 'wp-content/uploads' )
);

// --- exclusions -----------------------------------------------------------------
$ax_att_download_model                     = axismundi_op_object_view_model_defaults();
$ax_att_download_model['status']           = 'active';
$ax_att_download_model['media']['downloads'] = array(
	array( 'type' => 'Document', 'mediaType' => 'application/pdf', 'url' => array( array( 'href' => 'https://x.test/a.pdf' ) ) ),
);
ax_att_assert(
	$ax_att_results,
	'a non-visual download is not a gallery item',
	'' === ax_att_render( $ax_att_download_model )
);

$ax_att_tomb              = ax_att_model( array( ax_att_image( $ax_att_a ) ) );
$ax_att_tomb['status']    = 'tombstone';
ax_att_assert(
	$ax_att_results,
	'a Tombstone renders no attachments',
	'' === ax_att_render( $ax_att_tomb )
);

// --- editor parity ---------------------------------------------------------------
$ax_att_editor_script = (string) file_get_contents( dirname( __DIR__ ) . '/blocks/object-attachments/edit.js' );
$ax_att_editor_style  = (string) file_get_contents( dirname( __DIR__ ) . '/blocks/object-attachments/editor.css' );
$ax_att_front_style   = (string) file_get_contents( dirname( __DIR__ ) . '/assets/object-view.css' );
$ax_att_metadata      = json_decode( (string) file_get_contents( dirname( __DIR__ ) . '/blocks/object-attachments/block.json' ), true );
ax_att_assert(
	$ax_att_results,
	'carousel editor preview follows the server crop policy and leaves auto aspect ratios natural',
	false !== strpos( $ax_att_editor_script, "objectFit: crop ? 'cover' : 'contain'" )
		&& false !== strpos( $ax_att_editor_script, "if ( 'auto' !== aspectRatio )" )
		&& false === strpos( $ax_att_editor_script, 'galleryPreviewViewportWidth' )
);
ax_att_assert(
	$ax_att_results,
	'BlockPreview-only gallery height handling stays out of the front-end stylesheet',
	is_array( $ax_att_metadata )
		&& in_array( 'file:./editor.css', (array) ( $ax_att_metadata['editorStyle'] ?? array() ), true )
		&& false !== strpos( $ax_att_editor_style, '.block-editor-block-preview__content' )
		&& false === strpos( $ax_att_front_style, '.block-editor-block-preview__content' )
);

$ax_att_failed = count( array_filter( $ax_att_results, static fn( $r ) => ! $r ) );
// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI fixture output.
printf( "\n%d/%d passed\n", count( $ax_att_results ) - $ax_att_failed, count( $ax_att_results ) );
exit( $ax_att_failed > 0 ? 1 : 0 );
