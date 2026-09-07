<?php
/**
 * A short, display-only preview of one Object, for surfaces that list rather than render.
 *
 * An archive of contributions shows what a post is about; it does not show the post. That needs a
 * sentence or two, and a Topic often has none — `summary` is optional in ActivityStreams and a
 * forum thread is written as a body, not as an abstract.
 *
 * The temptation is to fill that gap by generating a `summary` on the object itself. That would be
 * wrong: `summary` is the author's own words, it federates, and a peer that receives one has no
 * way to tell an authored abstract from a machine-cut first paragraph. So the protocol object is
 * left exactly as the author made it, and the trimming lives here, where it is understood to be a
 * local display decision that never leaves this site.
 *
 * Sensitive Objects get no preview at all. An excerpt of something behind a content warning is the
 * warning defeated: the reader sees the first forty words of the thing they asked not to be shown
 * until they chose to. Those rows show their warning instead, which is what every other surface
 * already does.
 *
 * @package AxismundiObjectProjections
 */

defined( 'ABSPATH' ) || exit;

/** How many words a listed Object may show, by the kind of thing it is. */
function axismundi_op_list_preview_length( string $type ) : int {
	/*
	 * A reply is read in the context of what it answers, so it needs less: the parent is on the
	 * same row. A Topic or Article is the thing itself and gets the fuller lead-in.
	 */
	$words = 'Note' === $type ? 25 : 40;
	/**
	 * Adjust the preview length for one Object type.
	 *
	 * @param int    $words Word count.
	 * @param string $type  Object type.
	 */
	return max( 5, (int) apply_filters( 'axismundi_op_list_preview_length', $words, $type ) );
}

/**
 * Build the display-only preview for one Object view model.
 *
 * @param array<string,mixed> $model Object view model.
 * @return array{title:string,excerpt:string,sensitive:bool,content_warning:string,url:string,type:string,published:string}
 */
function axismundi_op_object_list_preview( array $model ) : array {
	$type      = (string) ( $model['type'] ?? 'Note' );
	$sensitive = ! empty( $model['sensitive'] );
	$warning   = trim( (string) ( $model['content_warning'] ?? '' ) );
	$excerpt   = '';
	if ( ! $sensitive ) {
		// The author's own summary always wins. Only when there is none is the body cut, and the
		// cut is made from stripped text so no half-open tag can escape into the list.
		$excerpt = trim( (string) ( $model['summary'] ?? '' ) );
		if ( '' === $excerpt ) {
			$excerpt = wp_trim_words(
				wp_strip_all_tags( (string) ( $model['content_html'] ?? '' ) ),
				axismundi_op_list_preview_length( $type )
			);
		}
	}
	return array(
		'title'           => trim( (string) ( $model['title'] ?? $model['name'] ?? '' ) ),
		'excerpt'         => $excerpt,
		'sensitive'       => $sensitive,
		'content_warning' => $warning,
		'url'             => (string) ( $model['human_url'] ?? $model['cached_view_url'] ?? '' ),
		'type'            => $type,
		'published'       => (string) ( $model['published'] ?? '' ),
	);
}

/**
 * Resolve one Object URI straight to its list preview.
 *
 * Returns null for anything a reader may not see, so a caller can drop the row without having to
 * repeat the visibility rules the card renderer already enforces.
 *
 * @param string $uri Canonical object URI.
 * @return array<string,mixed>|null
 */
function axismundi_op_list_preview_by_uri( string $uri ) : ?array {
	$uri = trim( $uri );
	if ( '' === $uri || ! function_exists( 'axismundi_op_resolve_source_by_uri' ) ) {
		return null;
	}
	$source = axismundi_op_resolve_source_by_uri( $uri );
	if ( null === $source
		|| ! function_exists( 'axismundi_op_object_card_publicly_renderable' )
		|| ! axismundi_op_object_card_publicly_renderable( $source )
	) {
		return null;
	}
	$model = axismundi_op_object_view_model( $source );
	if ( ! is_array( $model ) || 'tombstone' === (string) ( $model['status'] ?? '' ) ) {
		return null;
	}
	return axismundi_op_object_list_preview( $model );
}
