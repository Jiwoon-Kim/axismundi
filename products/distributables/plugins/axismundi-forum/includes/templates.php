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
 * Read a plugin block template that composes another plugin's pattern at read time.
 *
 * An Object page has no post behind it, so its markup is assembled from Object Projections'
 * card pattern rather than written out here. That makes the template a PHP file: it is executed
 * once to produce the block markup that is then registered and stays editable.
 *
 * @param string $slug Template slug (file basename without extension).
 * @return string
 */
function axismundi_forum_php_template_content( string $slug ) : string {
	$path = __DIR__ . '/../templates/' . $slug . '.php';
	if ( ! is_readable( $path ) || ! function_exists( 'axismundi_op_object_card_pattern_content' ) ) {
		return '';
	}
	ob_start();
	include $path;
	return (string) ob_get_clean();
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
	$community_object = axismundi_forum_php_template_content( 'single-object-community' );
	if ( '' !== $community_object ) {
		register_block_template(
			'axismundi-forum//single-object-community',
			array(
				'title'       => __( 'Single Community Object', 'axismundi-forum' ),
				'description' => __( 'One Object that belongs to a community, with its community card and vote.', 'axismundi-forum' ),
				'content'     => $community_object,
			)
		);
	}
}
add_action( 'init', 'axismundi_forum_register_templates', 20 );

/**
 * Route an Object bound to a community Group through the community template.
 *
 * Object Projections decides between a root post and a reply from the Object alone; whether that
 * Object belongs to a community is Forum's fact to supply, so it is answered here rather than by
 * teaching the Object layer what a Group is. A Topic keeps `single-ax_topic` — it is a real post
 * type and never reaches this route.
 *
 * @param string              $slug   Slug Object Projections chose.
 * @param array<string,mixed> $model  Object view model.
 * @param int                 $status HTTP status the route resolved to.
 * @return string
 */
function axismundi_forum_community_object_template_slug( string $slug, array $model, int $status ) : string {
	// A Tombstone stays minimal: naming the community of a deleted post would say more about
	// it than the deletion left standing.
	if ( 200 !== $status ) {
		return $slug;
	}
	$object_uri = trim( (string) ( $model['id'] ?? '' ) );
	if ( '' === $object_uri || ! function_exists( 'axismundi_forum_object_community_group' ) ) {
		return $slug;
	}
	return axismundi_forum_object_community_group( $object_uri ) instanceof Axismundi_Actor
		? 'single-object-community'
		: $slug;
}
add_filter( 'axismundi_op_object_template_slug', 'axismundi_forum_community_object_template_slug', 10, 3 );
