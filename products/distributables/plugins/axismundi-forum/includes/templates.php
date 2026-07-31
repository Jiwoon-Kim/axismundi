<?php
/**
 * Plugin-owned Topic template.
 *
 * A Topic is Forum's own post type, so Forum ships its single template rather than leaving
 * it to whichever theme is active. Without this the block-template hierarchy falls through
 * to the theme's generic `single.html`, which renders a Topic as an ordinary post: Core
 * comments instead of federated replies, and no sign of the community it belongs to.
 *
 * The theme still styles it. Registering through `register_block_template` keeps the markup
 * fully overridable in the Site Editor, so owning the template is not the same as owning its
 * appearance.
 *
 * This file previously also registered `archive-ax_forum` and `single-ax_forum`. Those went
 * away with the CPT they described; the Topic template outlived them and was left
 * unregistered, which is why Topics silently rendered through the theme's post template.
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
 * Register the single-Topic block template for `ax_topic`.
 *
 * @return void
 */
function axismundi_forum_register_templates() : void {
	if ( ! function_exists( 'register_block_template' ) ) {
		return;
	}
	register_block_template(
		'axismundi-forum//single-ax_topic',
		array(
			'title'       => __( 'Single Forum Topic', 'axismundi-forum' ),
			'description' => __( 'One community Topic with its federated replies.', 'axismundi-forum' ),
			'content'     => axismundi_forum_template_content( 'single-ax_topic' ),
			'post_types'  => array( AXISMUNDI_FORUM_TOPIC_POST_TYPE ),
		)
	);
}
add_action( 'init', 'axismundi_forum_register_templates', 20 );
