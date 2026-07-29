<?php
/**
 * Plugin-owned Forum templates (docs/AXISMUNDI-FORUM-ARCHITECTURE.md §5, §6-F0).
 *
 * Forum owns its archive and single templates; the theme styles them but does not own
 * the CPT/template identity. F0 ships minimal block templates (a Forum index and a
 * single-Forum body); the Group header, topic list, and New Topic action arrive with
 * F1's Forum-specific blocks. Registering via `register_block_template` lets the block
 * template hierarchy resolve them for `ax_forum` while remaining fully theme-styleable.
 *
 * @package AxismundiForum
 */

defined( 'ABSPATH' ) || exit;

/**
 * Read a plugin block-template file's markup.
 *
 * @param string $slug Template slug (file basename without extension).
 * @return string
 */
function axismundi_forum_template_content( string $slug ) : string {
	$path = __DIR__ . '/../templates/' . $slug . '.html';
	return is_readable( $path ) ? (string) file_get_contents( $path ) : ''; // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- local bundled template read.
}

/**
 * Register the Forum archive/single block templates so the block-template hierarchy
 * uses them for `ax_forum` when the active theme provides none.
 *
 * @return void
 */
function axismundi_forum_register_templates() : void {
	if ( ! function_exists( 'register_block_template' ) ) {
		return;
	}
	register_block_template(
		'axismundi-forum//archive-ax_forum',
		array(
			'title'       => __( 'Forum Archive', 'axismundi-forum' ),
			'description' => __( 'The list of Forums.', 'axismundi-forum' ),
			'content'     => axismundi_forum_template_content( 'archive-ax_forum' ),
			'post_types'  => array( 'ax_forum' ),
		)
	);
	register_block_template(
		'axismundi-forum//single-ax_forum',
		array(
			'title'       => __( 'Single Forum', 'axismundi-forum' ),
			'description' => __( 'A single Forum board.', 'axismundi-forum' ),
			'content'     => axismundi_forum_template_content( 'single-ax_forum' ),
			'post_types'  => array( 'ax_forum' ),
		)
	);
	register_block_template(
		'axismundi-forum//single-ax_topic',
		array(
			'title'       => __( 'Single Forum Topic', 'axismundi-forum' ),
			'description' => __( 'A single local Forum Topic.', 'axismundi-forum' ),
			'content'     => axismundi_forum_template_content( 'single-ax_topic' ),
			'post_types'  => array( AXISMUNDI_FORUM_TOPIC_POST_TYPE ),
		)
	);
}
add_action( 'init', 'axismundi_forum_register_templates', 20 );
