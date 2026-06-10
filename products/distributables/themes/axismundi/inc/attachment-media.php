<?php
/**
 * Axismundi — attachment media rendering.
 *
 * WordPress core has no block that outputs "the current attachment" —
 * core/post-content renders only the description — so an attachment page would
 * otherwise show metadata with no media. This module prepends the attachment
 * file to the Post Content block on attachment pages, switching on the
 * attachment's MIME type to emit a native, responsive element: an image, an
 * audio player, a video player (with caption tracks paired by a non-standard,
 * theme-specific filename convention — see axismundi_attachment_caption_tracks),
 * or a download link as a fallback. It renders only the file itself and invents
 * no associations beyond that caption pairing.
 *
 * Wiring note: this filters render_block_core/post-content rather than
 * registering a block. register_block_type() is plugin territory and is
 * disallowed in themes by the WordPress.org Theme Check, so a server-side
 * filter is the theme-legal way to inject the media. The elements carry no
 * presentational CSS — they ride the theme's global responsive media baseline
 * in style.css (max-width:100%).
 *
 * @package Axismundi
 */

defined( 'ABSPATH' ) || exit;

// Axismundi renders attachment media itself; drop core's the_content prepend so
// the file is never injected twice on an attachment page.
remove_filter( 'the_content', 'prepend_attachment' );

/**
 * Prepend the attachment file to the Post Content block on attachment pages.
 *
 * @param string   $block_content Rendered Post Content block markup.
 * @param array    $block         Parsed block.
 * @param WP_Block $instance      Block instance, carrying post context.
 * @return string Filtered block markup.
 */
function axismundi_prepend_attachment_media( $block_content, $block, $instance ) {
	$post_id = isset( $instance->context['postId'] ) ? (int) $instance->context['postId'] : 0;

	if ( ! $post_id || ! is_attachment( $post_id ) ) {
		return $block_content;
	}

	return axismundi_attachment_media_html( $post_id ) . $block_content;
}
add_filter( 'render_block_core/post-content', 'axismundi_prepend_attachment_media', 10, 3 );

/**
 * Build the media element for an attachment, by MIME type.
 *
 * @param int $post_id Attachment ID.
 * @return string Media HTML, or '' when there is nothing to render.
 */
function axismundi_attachment_media_html( int $post_id ) : string {
	$url = wp_get_attachment_url( $post_id );
	if ( ! $url ) {
		return '';
	}

	$type  = strtok( (string) get_post_mime_type( $post_id ), '/' );
	$media = '';

	switch ( $type ) {
		case 'image':
			$media = wp_get_attachment_image(
				$post_id,
				'full',
				false,
				array(
					'decoding' => 'async',
					'loading'  => 'eager',
				)
			);
			break;

		case 'audio':
			$media = sprintf(
				'<audio controls preload="metadata" src="%s"></audio>',
				esc_url( $url )
			);
			break;

		case 'video':
			// Caption tracks via a non-standard, theme-specific filename
			// convention (see axismundi_attachment_caption_tracks); WordPress
			// has no native video-attachment -> caption association.
			$tracks = '';
			foreach ( axismundi_attachment_caption_tracks( $post_id ) as $track ) {
				$tracks .= sprintf(
					'<track kind="captions" src="%s" srclang="%s" label="%s"%s>',
					esc_url( $track['src'] ),
					esc_attr( $track['srclang'] ),
					esc_attr( $track['label'] ),
					$track['default'] ? ' default' : ''
				);
			}
			$media = sprintf(
				'<video controls playsinline preload="metadata" src="%s">%s</video>',
				esc_url( $url ),
				$tracks
			);
			break;

		default:
			// PDFs, plain text, WebVTT, archives, etc. — offer the file itself.
			$media = sprintf(
				'<a href="%s" download>%s</a>',
				esc_url( $url ),
				esc_html( wp_basename( (string) get_attached_file( $post_id ) ) )
			);
	}

	if ( '' === $media ) {
		return '';
	}

	return sprintf( '<figure class="axismundi-attachment-media">%s</figure>', $media );
}

/**
 * Find sibling WebVTT caption tracks for a video attachment.
 *
 * NON-STANDARD, Axismundi-specific convention. WordPress has no native
 * association between a raw video attachment and caption files — captions
 * normally live on the core/video block (its track UI stores them in post
 * content), and nothing in the standard upload flow attaches a track to an
 * attachment *page*. This is a theme convenience for attachment pages only: a
 * video stored as "<base>.<ext>" is paired with any caption uploaded as
 * "<base>.<lang>.vtt" in the same uploads directory (e.g. gwangan-720p.webm <-
 * gwangan-720p.en.vtt / gwangan-720p.ko.vtt). The track matching the site
 * locale (else the first found) is marked default. Because the pairing is by
 * filename, an unrelated VTT that happens to share the base name will match.
 *
 * @param int $video_id Video attachment ID.
 * @return array<int,array{src:string,srclang:string,label:string,default:bool}>
 */
function axismundi_attachment_caption_tracks( int $video_id ) : array {
	$video_file = get_attached_file( $video_id );
	if ( ! $video_file ) {
		return array();
	}

	$dir  = trailingslashit( dirname( $video_file ) );
	$base = pathinfo( $video_file, PATHINFO_FILENAME );

	$vtt_ids = get_posts(
		array(
			'post_type'      => 'attachment',
			'post_mime_type' => 'text/vtt',
			'post_status'    => 'inherit',
			'numberposts'    => -1,
			'orderby'        => 'ID',
			'order'          => 'ASC',
			'fields'         => 'ids',
		)
	);

	$tracks = array();
	foreach ( $vtt_ids as $vtt_id ) {
		$vtt_file = get_attached_file( $vtt_id );
		if ( ! $vtt_file || trailingslashit( dirname( $vtt_file ) ) !== $dir ) {
			continue;
		}

		$name = wp_basename( $vtt_file );
		if ( 0 !== strpos( $name, $base . '.' ) ) {
			continue;
		}

		// Language code = the segment between "<base>." and ".vtt". WordPress
		// sanitises a double extension like "clip.en.vtt" to "clip.en_.vtt", so
		// trim the trailing underscore the sanitiser leaves behind.
		$lang = preg_replace( '/^' . preg_quote( $base . '.', '/' ) . '(.+)\.vtt$/i', '$1', $name );
		$lang = ( $lang === $name ) ? '' : trim( $lang, '_-. ' );

		$tracks[] = array(
			'src'     => wp_get_attachment_url( $vtt_id ),
			'srclang' => $lang,
			'label'   => axismundi_language_label( $lang ),
			'default' => false,
		);
	}

	// Default to the track matching the site language, else the first found.
	if ( $tracks ) {
		$site_lang   = strtolower( (string) strtok( get_locale(), '_-' ) );
		$default_idx = 0;
		foreach ( $tracks as $i => $track ) {
			if ( strtolower( $track['srclang'] ) === $site_lang ) {
				$default_idx = $i;
				break;
			}
		}
		$tracks[ $default_idx ]['default'] = true;
	}

	return $tracks;
}

/**
 * Human-readable label for a caption language code.
 *
 * @param string $lang Language code (e.g. "en", "ko").
 * @return string Display label.
 */
function axismundi_language_label( string $lang ) : string {
	if ( '' === $lang ) {
		return __( 'Captions', 'axismundi' );
	}

	$map = array(
		'en' => 'English',
		'ko' => '한국어',
		'ja' => '日本語',
	);

	return $map[ strtolower( $lang ) ] ?? strtoupper( $lang );
}
