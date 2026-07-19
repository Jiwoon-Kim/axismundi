<?php
/**
 * Phase 3a/3b - first-party Media Library attachment projection (dev-only).
 *
 * @package AxismundiObjectProjections
 */

defined( 'ABSPATH' ) || exit( 1 );

require_once dirname( __DIR__ ) . '/includes/registry.php';
require_once dirname( __DIR__ ) . '/includes/renderer.php';
require_once dirname( __DIR__ ) . '/includes/post-article.php';
require_once dirname( __DIR__ ) . '/includes/integrations/media-library.php';

$ax_media_projection_results = array();
$ax_media_projection_posts   = array();
$ax_media_projection_subjects = array();
$ax_media_projection_mode    = get_option( AXISMUNDI_MEDIA_MODE_OPTION, AXISMUNDI_MEDIA_MODE_DEFAULT );
$ax_media_projection_actor_id = 0;

/** @param array<bool> $results Results. @param string $label Label. @param bool $condition Condition. */
function ax_media_projection_assert( array &$results, string $label, bool $condition ) : void {
	$results[] = $condition;
	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI test output.
	printf( "[%s] %s\n", $condition ? 'PASS' : 'FAIL', $label );
}

try {
	$admins    = get_users( array( 'role' => 'administrator', 'number' => 1, 'fields' => 'ids' ) );
	$author_id = isset( $admins[0] ) ? (int) $admins[0] : 0;
	$image_id  = wp_insert_attachment(
		array(
			'post_author'    => $author_id,
			'post_mime_type' => 'image/jpeg',
			'post_status'    => 'inherit',
			'post_title'     => 'Projected image',
			'post_excerpt'   => 'Original caption',
			'guid'           => home_url( '/wp-content/uploads/projected-image.jpg' ),
		),
		'2026/07/projected-image.jpg'
	);
	// Synthesize a real subsize ladder so the rendition policy is actually exercised: one
	// entry above the embedded 1024 cap and two below it.
	wp_update_attachment_metadata(
		$image_id,
		array(
			'file'   => '2026/07/projected-image.jpg',
			'width'  => 4000,
			'height' => 3000,
			'sizes'  => array(
				'thumbnail'    => array( 'file' => 'projected-image-150x150.jpg', 'width' => 150, 'height' => 150, 'mime-type' => 'image/jpeg', 'filesize' => 6000 ),
				'medium'       => array( 'file' => 'projected-image-300x225.jpg', 'width' => 300, 'height' => 225, 'mime-type' => 'image/jpeg', 'filesize' => 18000 ),
				'large'        => array( 'file' => 'projected-image-1024x768.jpg', 'width' => 1024, 'height' => 768, 'mime-type' => 'image/jpeg', 'filesize' => 140000 ),
			),
		)
	);
	$video_id = wp_insert_attachment(
		array(
			'post_author'    => $author_id,
			'post_mime_type' => 'video/mp4',
			'post_status'    => 'inherit',
			'post_title'     => 'Projected video',
			'guid'           => home_url( '/wp-content/uploads/projected-video.mp4' ),
		)
	);
	$ax_media_projection_posts = array_filter( array( $image_id, $video_id ), 'is_int' );
	$image = get_post( $image_id );
	$video = get_post( $video_id );

	update_option( AXISMUNDI_MEDIA_MODE_OPTION, 'core' );
	ax_media_projection_assert( $ax_media_projection_results, 'the first-party adapter is inert in Core mode', $image instanceof WP_Post && ! axismundi_op_media_attachment_supports( $image ) );

	update_option( AXISMUNDI_MEDIA_MODE_OPTION, 'independent' );
	add_filter( 'axismundi_op_media_attachment_actor_uri', static fn() : string => 'https://example.com/actors/media-owner' );
	$article_actor = axismundi_actors_create_local( array( 'actor_type' => 'Person', 'actor_scope' => 'user', 'preferred_username' => 'media-article-' . strtolower( wp_generate_password( 8, false, false ) ) ) );
	if ( $article_actor instanceof Axismundi_Actor ) {
		$ax_media_projection_actor_id = $article_actor->get_identity_id();
		axismundi_actors_set_status( $ax_media_projection_actor_id, 'public' );
		$article_actor = axismundi_actors_get_by_identity( $ax_media_projection_actor_id );
	}
	$article_actor_uri = $article_actor instanceof Axismundi_Actor ? $article_actor->get_uri() : '';
	add_filter( 'axismundi_op_post_actor_uri', static fn() : string => $article_actor_uri );
	$GLOBALS['axismundi_op_loaded']              = false;
	$GLOBALS['axismundi_op_object_transformers'] = array();
	$GLOBALS['axismundi_op_sequence']            = 0;

	$image_object = axismundi_op_transform_object( $image );
	$video_object = axismundi_op_transform_object( $video );
	ax_media_projection_assert(
		$ax_media_projection_results,
		'Independent-mode image and video attachments resolve to their ActivityStreams types',
		is_array( $image_object ) && 'Image' === $image_object['type']
			&& is_array( $video_object ) && 'Video' === $video_object['type']
	);

	$article_id = wp_insert_post(
		array(
			'post_type'    => 'post',
			'post_status'  => 'publish',
			'post_author'  => $author_id,
			'post_title'   => 'Article using media',
			'post_content' => '<!-- wp:image {"id":' . $image_id . '} --><figure class="wp-block-image"><img src="' . esc_url( wp_get_attachment_url( $image_id ) ) . '" /></figure><!-- /wp:image --><!-- wp:video {"id":' . $video_id . '} --><figure class="wp-block-video"><video controls src="' . esc_url( wp_get_attachment_url( $video_id ) ) . '"></video></figure><!-- /wp:video -->',
			'post_excerpt' => 'Media Article preview.',
		)
	);
	$private_post_id = wp_insert_post(
		array(
			'post_type'    => 'post',
			'post_status'  => 'private',
			'post_author'  => $author_id,
			'post_title'   => 'Private usage',
			'post_content' => '<!-- wp:image {"id":' . $image_id . '} --><figure><img /></figure><!-- /wp:image -->',
		)
	);
	$ax_media_projection_subjects = array_filter( array( $article_id, $private_post_id ), 'is_int' );
	update_post_meta( $article_id, '_thumbnail_id', $image_id );
	axismundi_media_relations_reindex_post( $article_id );
	axismundi_media_relations_reindex_post( $private_post_id );

	$article = axismundi_op_transform_object( get_post( $article_id ) );
	$attached_ids = array();
	foreach ( is_array( $article ) ? (array) ( $article['attachment'] ?? array() ) : array() as $descriptor ) {
		$attached_ids[] = (string) ( $descriptor['id'] ?? '' );
	}
	ax_media_projection_assert(
		$ax_media_projection_results,
		'Article image and attachment members are supplied by the Media Library relation index',
		is_array( $article )
			&& axismundi_op_media_attachment_uri( $image ) === ( $article['image']['id'] ?? '' )
			&& ( $article['image'] ?? null ) === ( $article['preview']['attachment'] ?? null )
			&& in_array( axismundi_op_media_attachment_uri( $image ), $attached_ids, true )
			&& in_array( axismundi_op_media_attachment_uri( $video ), $attached_ids, true )
	);

	// Role-dependent name: embedded media carries alt text, never the title (MEDIA-RENDITIONS §5).
	update_post_meta( $image_id, '_wp_attachment_image_alt', 'A described test image' );
	$article_alt   = axismundi_op_transform_object( get_post( $article_id ) );
	$embedded_alt  = is_array( $article_alt ) ? (array) ( $article_alt['image'] ?? array() ) : array();
	delete_post_meta( $image_id, '_wp_attachment_image_alt' );
	$article_noalt = axismundi_op_transform_object( get_post( $article_id ) );
	$embedded_noalt = is_array( $article_noalt ) ? (array) ( $article_noalt['image'] ?? array() ) : array();
	ax_media_projection_assert(
		$ax_media_projection_results,
		'embedded media names itself with alt text and omits name entirely when alt is empty',
		'A described test image' === ( $embedded_alt['name'] ?? '' )
			&& ! isset( $embedded_noalt['name'] )
			&& get_post( $image_id )->post_title !== ( $embedded_noalt['name'] ?? null )
	);
	ax_media_projection_assert(
		$ax_media_projection_results,
		'Article attachment and preview.attachment descriptors stay identical',
		( $article_alt['image'] ?? null ) === ( $article_alt['preview']['attachment'] ?? null )
	);

	$image_object = axismundi_op_transform_object( $image );
	// Identity never drifts by role; only `name` and the rendition policy do.
	$core_keys = array( 'id', 'type', 'mediaType' );
	ax_media_projection_assert(
		$ax_media_projection_results,
		'standalone and embedded descriptors share one identity/type/mediaType core',
		is_array( $image_object )
			&& array_intersect_key( $image_object, array_flip( $core_keys ) ) === array_intersect_key( $embedded_noalt, array_flip( $core_keys ) )
	);
	// Wider-fediverse consumers do not select between versions. Embedded media therefore
	// carries one fetchable URL string, while the standalone object keeps its Link ladder.
	ax_media_projection_assert(
		$ax_media_projection_results,
		'embedded media advertises one interoperable URL string while the standalone keeps its FEP-1311 Link ladder',
		is_string( $embedded_noalt['url'] ?? null )
			&& false !== strpos( (string) $embedded_noalt['url'], 'projected-image-1024x768.jpg' )
			&& is_array( $image_object['url'] ?? null )
			&& count( $image_object['url'] ) > 1
	);

	$used_in      = axismundi_op_transform_collection( new Axismundi_OP_Media_Used_In( $image ) );
	ax_media_projection_assert(
		$ax_media_projection_results,
		'a media object advertises a usedIn collection that lists distinct public Articles only',
		is_array( $image_object ) && axismundi_op_media_used_in_url( $image ) === ( $image_object['usedIn'] ?? '' )
			&& is_array( $used_in ) && 1 === $used_in['totalItems']
			&& array( axismundi_op_post_object_uri( get_post( $article_id ) ) ) === $used_in['orderedItems']
	);
	ax_media_projection_assert(
		$ax_media_projection_results,
		'the attachment object keeps a stable query id and Actor attribution, and names itself with its own title',
		is_array( $image_object )
			&& add_query_arg( 'attachment_id', $image_id, home_url( '/' ) ) === $image_object['id']
			&& 'https://example.com/actors/media-owner' === $image_object['attributedTo']
			&& get_post( $image_id )->post_title === $image_object['name']
			&& ! isset( $image_object['attachment'] )
	);
	$image_links = is_array( $image_object ) && is_array( $image_object['url'] ?? null ) ? $image_object['url'] : array();
	$last_link   = $image_links ? end( $image_links ) : array();
	ax_media_projection_assert(
		$ax_media_projection_results,
		'url is an ordered Link array whose last entry is the human HTML page',
		! empty( $image_links )
			&& 'text/html' === ( $last_link['mediaType'] ?? '' )
			&& get_attachment_link( $image_id ) === ( $last_link['href'] ?? '' )
			&& axismundi_op_object_html_url( $image_object ) === get_attachment_link( $image_id )
	);
	ax_media_projection_assert(
		$ax_media_projection_results,
		'the original file URL never appears anywhere in the projected payload',
		! empty( $image_links )
			&& false === strpos( (string) wp_json_encode( $image_object ), (string) wp_get_attachment_url( $image_id ) )
	);

	update_post_meta( $image_id, AXISMUNDI_MEDIA_SENSITIVE_STATE_META, 'self_marked' );
	update_post_meta( $image_id, '_ax_media_content_warning', 'Sensitive test warning' );
	update_post_meta( $image_id, '_ax_media_license', 'cc-by' );
	$image_object = axismundi_op_transform_object( $image );
	$context      = is_array( $image_object ) ? (array) $image_object['@context'] : array();
	ax_media_projection_assert(
		$ax_media_projection_results,
		'sensitive state, content warning, and canonical CC license are represented through public Media services',
		is_array( $image_object ) && true === $image_object['sensitive']
			&& 'Sensitive test warning' === $image_object['summary']
			&& 'https://creativecommons.org/licenses/by/4.0/' === $image_object['license']
			&& count( $context ) > 1
	);

	update_post_meta( $image_id, '_ax_media_visibility', 'unlisted' );
	ax_media_projection_assert( $ax_media_projection_results, 'unlisted direct objects remain projectable', is_array( axismundi_op_transform_object( $image ) ) );
	update_post_meta( $image_id, '_ax_media_visibility', 'private' );
	$private = axismundi_op_transform_object( $image );
	ax_media_projection_assert( $ax_media_projection_results, 'private objects fail closed without owner/editor bypass', is_wp_error( $private ) && 'ax_op_not_public' === $private->get_error_code() );

	update_post_meta( $image_id, '_ax_media_visibility', 'public' );
	remove_all_filters( 'axismundi_op_media_attachment_actor_uri' );
	add_filter( 'axismundi_op_media_attachment_actor_uri', static fn() : string => '' );
	$without_actor = axismundi_op_transform_object( $image );
	ax_media_projection_assert( $ax_media_projection_results, 'an attachment without a public Actor fails closed', is_wp_error( $without_actor ) && 'ax_op_not_public' === $without_actor->get_error_code() );
} finally {
	remove_all_filters( 'axismundi_op_media_attachment_actor_uri' );
	remove_all_filters( 'axismundi_op_post_actor_uri' );
	update_option( AXISMUNDI_MEDIA_MODE_OPTION, $ax_media_projection_mode );
	foreach ( $ax_media_projection_subjects as $subject_id ) {
		wp_delete_post( (int) $subject_id, true );
	}
	foreach ( $ax_media_projection_posts as $attachment_id ) {
		wp_delete_attachment( (int) $attachment_id, true );
	}
	if ( $ax_media_projection_actor_id > 0 ) {
		global $wpdb;
		$wpdb->delete( axismundi_actors_actors_table(), array( 'identity_id' => $ax_media_projection_actor_id ), array( '%d' ) );
		$wpdb->delete( axismundi_actors_identities_table(), array( 'id' => $ax_media_projection_actor_id ), array( '%d' ) );
	}
}

$ax_media_projection_failures = count( array_filter( $ax_media_projection_results, static fn( bool $result ) : bool => ! $result ) );
// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI test output.
printf( "\n== %d checks, %d failed ==\n", count( $ax_media_projection_results ), $ax_media_projection_failures );

if ( class_exists( 'WP_CLI' ) ) {
	WP_CLI::halt( $ax_media_projection_failures > 0 ? 1 : 0 );
}
exit( $ax_media_projection_failures > 0 ? 1 : 0 );
