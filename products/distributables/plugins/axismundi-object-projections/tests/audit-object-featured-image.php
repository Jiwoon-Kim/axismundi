<?php
/**
 * Object Featured Image block regression (dev-only).
 *
 * The block answers two questions at once -- Core's Featured Image ("which image
 * represents this subject") and Cover's ("how is it framed") -- for either the
 * Object being rendered or the Actor in context. These checks pin the behaviour
 * an author sees, not the internals that produce it.
 *
 * @package AxismundiObjectProjections
 */

defined( 'ABSPATH' ) || exit( 1 );

$ax_fi_results = array();
$ax_fi_remote  = array();

/** @param bool[] $results Results. */
function ax_fi_assert( array &$results, string $label, bool $condition ) : void {
	$results[] = $condition;
	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI test output.
	printf( "[%s] %s\n", $condition ? 'PASS' : 'FAIL', $label );
}

/**
 * Push one cached remote Object as the Object being rendered.
 *
 * @param array<string,mixed> $payload Remote AS2 payload.
 * @param string[]            $tracked Collected URIs for fixture cleanup.
 * @return void
 */
function ax_fi_activate( array $payload, array &$tracked ) : void {
	axismundi_op_remote_object_store( $payload );
	$tracked[] = (string) $payload['id'];
	$source    = axismundi_op_resolve_source_by_uri( (string) $payload['id'] );
	axismundi_op_set_current_object_view_model( null === $source ? null : axismundi_op_object_view_model( $source ) );
}

try {
	axismundi_op_install();
	$ax_fi_public = 'https://www.w3.org/ns/activitystreams#Public';
	$ax_fi_actor  = 'https://example.com/users/fi-' . strtolower( wp_generate_password( 8, false, false ) );
	$ax_fi_lead   = array( 'type' => 'Image', 'mediaType' => 'image/jpeg', 'name' => 'Lead alt', 'width' => 1200, 'height' => 630, 'url' => 'https://example.com/fi-lead.jpg' );
	$ax_fi_base   = static fn( array $extra ) : array => array_merge(
		array( 'id' => 'https://example.com/notes/' . wp_generate_uuid4(), 'type' => 'Note', 'attributedTo' => $ax_fi_actor, 'to' => array( $ax_fi_public ), 'content' => '<p>Body.</p>' ),
		$extra
	);

	// Like Core's Featured Image, nothing to show means nothing rendered: the block
	// must not leave an empty box in the flow.
	axismundi_op_set_current_object_view_model( null );
	ax_fi_assert( $ax_fi_results, 'no subject and no placeholder renders nothing at all', '' === trim( do_blocks( '<!-- wp:axismundi/object-featured-image /-->' ) ) );

	// A banner slot is the exception, because most Actors have no header image and
	// collapsing the slot would move every profile's layout.
	$ax_fi_empty = do_blocks( '<!-- wp:axismundi/object-featured-image {"showPlaceholder":true} /-->' );
	ax_fi_assert(
		$ax_fi_results,
		'an opted-in banner slot keeps its space with a placeholder and never invents an image',
		false !== strpos( $ax_fi_empty, 'axismundi-object__featured-image-media is-empty' )
			&& false === strpos( $ax_fi_empty, '<img' )
			&& false === strpos( $ax_fi_empty, 'background-image' )
	);

	ax_fi_activate( $ax_fi_base( array( 'image' => $ax_fi_lead ) ), $ax_fi_remote );
	$ax_fi_plain = do_blocks( '<!-- wp:axismundi/object-featured-image /-->' );
	ax_fi_assert(
		$ax_fi_results,
		'an Object lead image renders as an image with its alternative text and intrinsic size',
		false !== strpos( $ax_fi_plain, 'src="https://example.com/fi-lead.jpg"' )
			&& false !== strpos( $ax_fi_plain, 'alt="Lead alt"' )
			&& false !== strpos( $ax_fi_plain, 'width="1200"' )
			&& false !== strpos( $ax_fi_plain, 'height="630"' )
			&& false !== strpos( $ax_fi_plain, 'object-fit:cover' )
	);

	// A featured image is the author's declared representative image, never an
	// attachment promoted to one. A titled article carrying only attachments has no
	// `image`, so the block renders nothing rather than lifting its first attachment
	// into a hero the author never chose.
	ax_fi_activate(
		$ax_fi_base(
			array(
				'type'       => 'Article',
				'name'       => 'Attachment only',
				'content'    => str_repeat( '<p>Long body.</p>', 90 ),
				'attachment' => array( array( 'type' => 'Image', 'mediaType' => 'image/jpeg', 'name' => 'Just related', 'url' => 'https://example.com/fi-attach.jpg' ) ),
			)
		),
		$ax_fi_remote
	);
	ax_fi_assert(
		$ax_fi_results,
		'an article with attachments but no declared image renders no featured image at all',
		'' === trim( do_blocks( '<!-- wp:axismundi/object-featured-image /-->' ) )
	);

	ax_fi_activate( $ax_fi_base( array( 'image' => $ax_fi_lead ) ), $ax_fi_remote );

	// Focal point and overlay are Cover's questions, asked of a featured image.
	$ax_fi_framed = do_blocks( '<!-- wp:axismundi/object-featured-image {"scale":"contain","focalPoint":{"x":0.2,"y":0.8},"dimRatio":40,"overlayColor":"primary"} /-->' );
	ax_fi_assert(
		$ax_fi_results,
		'framing controls reach the rendered image and the overlay is decorative',
		false !== strpos( $ax_fi_framed, 'object-position:20% 80%' )
			&& false !== strpos( $ax_fi_framed, 'object-fit:contain' )
			&& false !== strpos( $ax_fi_framed, 'has-primary-background-color' )
			&& false !== strpos( $ax_fi_framed, 'opacity:0.4' )
			&& false !== strpos( $ax_fi_framed, 'aria-hidden="true"' )
	);

	// Sizing, border, and shadow are real block supports rather than private
	// attributes, so Core writes the wrapper CSS and an author finds the controls
	// in the Styles tab where every other block keeps them. Wearing Core's own
	// `wp-block-post-featured-image` class would additionally inherit whatever a
	// theme styles on that block, which is why the wrapper does not carry it.
	$ax_fi_sized = do_blocks( '<!-- wp:axismundi/object-featured-image {"style":{"dimensions":{"minHeight":"320px"},"border":{"radius":"12px"},"shadow":"var:preset|shadow|natural"}} /-->' );
	$ax_fi_ratio = do_blocks( '<!-- wp:axismundi/object-featured-image {"style":{"dimensions":{"aspectRatio":"16/9"}}} /-->' );
	ax_fi_assert(
		$ax_fi_results,
		'sizing, border, and shadow come from block supports and no Core block class is borrowed',
		false !== strpos( $ax_fi_sized, 'min-height:320px' )
			&& false !== strpos( $ax_fi_sized, 'border-radius:12px' )
			&& false !== strpos( $ax_fi_sized, 'box-shadow:var(--wp--preset--shadow--natural)' )
			&& false !== strpos( $ax_fi_ratio, 'aspect-ratio:16/9' )
			&& false === strpos( $ax_fi_sized, 'wp-block-post-featured-image' )
	);

	// Opacity is a dimming control: dragging it before choosing a colour has to
	// dim the image rather than do nothing, so the overlay carries a colour of its
	// own instead of inheriting whatever `currentColor` happens to be.
	$ax_fi_bare = do_blocks( '<!-- wp:axismundi/object-featured-image {"dimRatio":60} /-->' );
	ax_fi_assert(
		$ax_fi_results,
		'overlay opacity alone dims the image without a colour having been chosen',
		false !== strpos( $ax_fi_bare, 'axismundi-object__featured-image-overlay' )
			&& false !== strpos( $ax_fi_bare, 'opacity:0.6' )
	);

	// A fixed or tiled image is painted, so it cannot be a replaced element. It has
	// to keep an accessible name instead of silently losing its alternative text.
	$ax_fi_fixed = do_blocks( '<!-- wp:axismundi/object-featured-image {"hasParallax":true,"isRepeated":true} /-->' );
	ax_fi_assert(
		$ax_fi_results,
		'a fixed or tiled lead image becomes a painted background that keeps its accessible name',
		false === strpos( $ax_fi_fixed, '<img' )
			&& false !== strpos( $ax_fi_fixed, 'role="img"' )
			&& false !== strpos( $ax_fi_fixed, 'aria-label="Lead alt"' )
			&& false !== strpos( $ax_fi_fixed, 'has-parallax' )
			&& false !== strpos( $ax_fi_fixed, 'is-repeated' )
			&& false !== strpos( $ax_fi_fixed, 'background-image:url(https://example.com/fi-lead.jpg)' )
	);

	// The lead image inherits the Object's flag rather than answering sensitivity a
	// second time, and reuses the Media Library's reveal so one page never shows two
	// different content-warning treatments.
	ax_fi_activate( $ax_fi_base( array( 'image' => $ax_fi_lead, 'sensitive' => true, 'summary' => 'Field spoiler' ) ), $ax_fi_remote );
	$ax_fi_sensitive = do_blocks( '<!-- wp:axismundi/object-featured-image /-->' );
	ax_fi_assert(
		$ax_fi_results,
		'a sensitive Object gates its lead image behind the shared reveal, using the Object own warning',
		false !== strpos( $ax_fi_sensitive, 'ax-media-sensitive is-hidden' )
			&& false !== strpos( $ax_fi_sensitive, 'Field spoiler' )
			&& false !== strpos( $ax_fi_sensitive, 'ax-media-sensitive__reveal' )
	);

	// A non-sensitive Object must not inherit a gate from the previous one.
	ax_fi_activate( $ax_fi_base( array( 'image' => $ax_fi_lead ) ), $ax_fi_remote );
	ax_fi_assert(
		$ax_fi_results,
		'a non-sensitive Object shows its lead image ungated',
		false === strpos( do_blocks( '<!-- wp:axismundi/object-featured-image /-->' ), 'ax-media-sensitive' )
	);

	// An Object with no human page has nothing to link to, so the request for a link
	// is declined rather than answered with a dead anchor.
	ax_fi_assert(
		$ax_fi_results,
		'a lead image is left unlinked when the Object has no human page',
		false === strpos( do_blocks( '<!-- wp:axismundi/object-featured-image {"isLink":true} /-->' ), '<a ' )
	);

	ax_fi_activate( $ax_fi_base( array( 'image' => $ax_fi_lead, 'url' => 'https://example.com/@fi/lead-note' ) ), $ax_fi_remote );
	$ax_fi_model  = (array) axismundi_op_current_object_view_model();
	$ax_fi_human  = (string) ( $ax_fi_model['human_url'] ?? '' );
	$ax_fi_linked = do_blocks( '<!-- wp:axismundi/object-featured-image {"isLink":true,"linkTarget":"_blank"} /-->' );
	ax_fi_assert(
		$ax_fi_results,
		'a linked lead image points at the Object human page and a new tab is opened safely',
		'' !== $ax_fi_human
			&& false !== strpos( $ax_fi_linked, 'href="' . esc_url( $ax_fi_human ) . '"' )
			&& false !== strpos( $ax_fi_linked, 'rel="noreferrer noopener"' )
	);
} finally {
	axismundi_op_set_current_object_view_model( null );
	foreach ( $ax_fi_remote as $ax_fi_uri ) {
		axismundi_op_remote_object_delete( $ax_fi_uri );
	}
}

$ax_fi_failures = count( array_filter( $ax_fi_results, static fn( bool $result ) : bool => ! $result ) );
// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI test output.
printf( "\n== %d checks, %d failed ==\n", count( $ax_fi_results ), $ax_fi_failures );
if ( class_exists( 'WP_CLI' ) ) {
	WP_CLI::halt( $ax_fi_failures > 0 ? 1 : 0 );
}
exit( $ax_fi_failures > 0 ? 1 : 0 );
