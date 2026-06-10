<?php
/**
 * Axismundi — attachment media block.
 *
 * A theme-internal dynamic block, `axismundi/attachment-media`, used in
 * templates/attachment.html to render the attachment file itself. WordPress
 * core has no block that outputs "the current attachment" — core/post-content
 * renders only the description — so an attachment page would otherwise show
 * metadata with no media. This block switches on the attachment's MIME type and
 * emits a native, responsive media element: an image, an audio player, a video
 * player (with sibling WebVTT caption tracks), or a download link as a fallback.
 *
 * It carries no presentational CSS — the elements inherit the theme's global
 * responsive media baseline in style.css (max-width:100%). The block is
 * registered server-side only and is deliberately absent from the inserter: it
 * is a template primitive, not an authoring block.
 *
 * @package Axismundi
 */

defined( 'ABSPATH' ) || exit;

/**
 * Register the attachment-media block.
 */
function axismundi_register_attachment_media_block() : void {
	register_block_type(
		'axismundi/attachment-media',
		array(
			'render_callback' => 'axismundi_render_attachment_media',
			'uses_context'    => array( 'postId' ),
		)
	);
}
add_action( 'init', 'axismundi_register_attachment_media_block' );

/**
 * Render the attachment file for the current attachment.
 *
 * @param array         $attributes Block attributes (unused).
 * @param string        $content    Inner content (unused).
 * @param WP_Block|null $block      Block instance, carrying post context.
 * @return string Media HTML, or '' when there is nothing to render.
 */
function axismundi_render_attachment_media( $attributes = array(), $content = '', $block = null ) : string {
	$post_id = ( $block && ! empty( $block->context['postId'] ) )
		? (int) $block->context['postId']
		: (int) get_the_ID();

	if ( ! $post_id || 'attachment' !== get_post_type( $post_id ) ) {
		return '';
	}

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

	return sprintf(
		'<div %s>%s</div>',
		get_block_wrapper_attributes(),
		$media
	);
}

/**
 * Find sibling WebVTT caption tracks for a video attachment.
 *
 * Uses a filename convention: a video stored as "<base>.<ext>" pairs with any
 * caption uploaded as "<base>.<lang>.vtt" in the same uploads directory (e.g.
 * gwangan-720p.webm <- gwangan-720p.en.vtt / gwangan-720p.ko.vtt). The first
 * track discovered is marked default.
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
