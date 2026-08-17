<?php
/**
 * Plugin Name:       Axismundi Table of Contents
 * Plugin URI:        https://github.com/Jiwoon-Kim/axismundi/tree/main/products/distributables/plugins/axismundi-table-of-contents
 * Description:       On-page table of contents block that builds from a post's headings and keeps the heading ids in sync.
 * Version:           0.1.2
 * Requires at least: 6.7
 * Requires PHP:      8.1
 * Author:            KIM JIWOON
 * Author URI:        https://designbusan.ai.kr
 * License:           GPL-3.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-3.0.html
 * Text Domain:       axismundi-table-of-contents
 *
 * @package AxismundiTableOfContents
 */

defined( 'ABSPATH' ) || exit;

/**
 * Register the axismundi/toc block from its block.json.
 *
 * @return void
 */
function axismundi_toc_register_block() : void {
	$dir = __DIR__ . '/blocks/toc';
	if ( file_exists( $dir . '/block.json' ) ) {
		register_block_type( $dir );
	}
}
add_action( 'init', 'axismundi_toc_register_block' );

/**
 * Register the "above content" disclosure placement as a block pattern.
 *
 * The default theme single template keeps the rail aside; this pattern lets a user
 * drop the collapsible disclosure variant just before core/post-content. Patterns
 * are not auto-loaded for plugins (that is theme-only), so register it explicitly.
 *
 * @return void
 */
function axismundi_toc_register_patterns() : void {
	if ( ! function_exists( 'register_block_pattern' ) ) {
		return;
	}
	register_block_pattern(
		'axismundi/toc-before-content',
		array(
			'title'       => __( 'Table of contents (above content)', 'axismundi-table-of-contents' ),
			'description' => __( 'A collapsible table of contents to place above the post content.', 'axismundi-table-of-contents' ),
			'categories'  => array( 'text' ),
			'content'     => '<!-- wp:axismundi/toc {"variant":"disclosure","openByDefault":false,"summaryMode":"current"} /-->',
		)
	);
}
add_action( 'init', 'axismundi_toc_register_patterns' );

/**
 * Walk h2-h6 in an HTML fragment, returning the heading list AND a copy of the
 * fragment with deterministic ids injected into id-less headings.
 *
 * This is the single source of truth for heading ids. The TOC block (which reads
 * the post's raw content) and the post-content id-injection filter (which reads
 * the rendered content) both call this with the same slug algorithm, so the TOC
 * anchors always match the heading ids — regardless of which block renders first
 * and regardless of where the TOC block is placed (template aside or content).
 *
 * The walk always covers h2-h6 so the dedup counter is identical on both sides;
 * callers narrow the *displayed* level range themselves.
 *
 * @param string $html HTML fragment to scan.
 * @return array{headings: array<int,array{level:int,text:string,id:string}>, html: string}
 */
function axismundi_toc_process( string $html ) : array {
	$headings = array();
	$seen     = array();

	$callback = static function ( array $m ) use ( &$headings, &$seen ) : string {
		$level = (int) $m[1];
		$attrs = $m[2];
		$inner = $m[3];
		$full  = $m[0];

		$text = trim( wp_strip_all_tags( $inner ) );
		if ( '' === $text ) {
			return $full;
		}

		$has_id = (bool) preg_match( '/\sid\s*=\s*("|\')(.*?)\1/i', $attrs, $idm );

		if ( $has_id ) {
			// Respect an author-provided id (the editor's HTML anchor field).
			$id = $idm[2];
		} else {
			$base = sanitize_title( $text );
			if ( '' === $base ) {
				$base = 'section';
			}
			$id = $base;
			$n  = 2;
			while ( isset( $seen[ $id ] ) ) {
				$id = $base . '-' . $n;
				++$n;
			}
		}
		$seen[ $id ] = true;

		$headings[] = array(
			'level' => $level,
			'text'  => $text,
			'id'    => $id,
		);

		if ( ! $has_id ) {
			$full = '<h' . $level . ' id="' . esc_attr( $id ) . '"' . $attrs . '>' . $inner . '</h' . $level . '>';
		}

		return $full;
	};

	$result = preg_replace_callback( '/<h([2-6])\b([^>]*)>(.*?)<\/h\1>/is', $callback, $html );

	return array(
		'headings' => $headings,
		'html'     => is_string( $result ) ? $result : $html,
	);
}

/**
 * Inject heading ids into the rendered post-content on singular views.
 *
 * Adding an id to an id-less article heading is benign (it only enables
 * deep-linking) and guarantees the TOC anchors resolve whether the TOC block is
 * placed in the template or inside the content. Author-provided anchors are kept.
 *
 * @param string              $block_content Rendered block HTML.
 * @param array<string,mixed> $block         Parsed block (unused).
 * @return string
 */
function axismundi_toc_inject_post_content_ids( string $block_content, array $block ) : string {
	unset( $block );
	if ( is_admin() || ! is_singular() ) {
		return $block_content;
	}
	$processed = axismundi_toc_process( $block_content );
	return $processed['html'];
}
add_filter( 'render_block_core/post-content', 'axismundi_toc_inject_post_content_ids', 10, 2 );
