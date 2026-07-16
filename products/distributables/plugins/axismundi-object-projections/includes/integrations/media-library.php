<?php
/**
 * First-party Axismundi Media Library projection adapter.
 *
 * The Media Library owns attachment/folder state and access policy. This adapter owns
 * ActivityStreams semantics and reads only its public service functions. No SQL or
 * private meta-schema knowledge is used for visibility, sensitivity, or licensing.
 *
 * @package AxismundiObjectProjections
 */

defined( 'ABSPATH' ) || exit;

/** Read-only source for one Attachment's public reverse-usage collection. */
final class Axismundi_OP_Media_Used_In {
	public function __construct( private WP_Post $attachment ) {}
	public function get_attachment() : WP_Post { return $this->attachment; }
}

/**
 * Whether the first-party Media Library integration can run this request.
 *
 * @return bool
 */
function axismundi_op_media_library_available() : bool {
	return function_exists( 'axismundi_media_is_independent' )
		&& axismundi_media_is_independent()
		&& function_exists( 'axismundi_media_effective_visibility' )
		&& function_exists( 'axismundi_media_locked_gate_for_attachment' )
		&& function_exists( 'axismundi_media_is_sensitive' )
		&& function_exists( 'axismundi_media_content_warning_text' )
		&& function_exists( 'axismundi_media_license_record' )
		&& function_exists( 'axismundi_media_feed_rendition' );
}

/**
 * Extend the renderer-owned context for Media Library members.
 *
 * @param array<int,mixed>         $context Existing contexts.
 * @param array<string,mixed>|null $object  Object being finalized.
 * @return array<int,mixed>
 */
function axismundi_op_media_jsonld_context( array $context, ?array $object = null ) : array {
	if ( ! is_array( $object ) || ! in_array( $object['type'] ?? '', array( 'Image', 'Video', 'Audio', 'Document' ), true ) ) {
		return $context;
	}
	$context[] = array(
		'schema'          => 'https://schema.org/',
		'license'         => array(
			'@id'   => 'schema:license',
			'@type' => '@id',
		),
	);
	if ( isset( $object['usedIn'] ) ) {
		$context[] = array(
			'usedIn' => array(
				'@id'   => 'https://github.com/Jiwoon-Kim/axismundi/ns#usedIn',
				'@type' => '@id',
			),
		);
	}
	return $context;
}

/**
 * Whether this adapter owns a source.
 *
 * @param mixed $source Candidate source.
 * @return bool
 */
function axismundi_op_media_attachment_supports( $source ) : bool {
	return axismundi_op_media_library_available()
		&& $source instanceof WP_Post
		&& 'attachment' === $source->post_type;
}

/**
 * Stable attachment object URI.
 *
 * @param WP_Post $attachment Attachment.
 * @return string
 */
function axismundi_op_media_attachment_uri( WP_Post $attachment ) : string {
	return add_query_arg( 'attachment_id', (int) $attachment->ID, home_url( '/' ) );
}

/**
 * Public Actor URI for an attachment owner.
 *
 * @param WP_Post $attachment Attachment.
 * @return string
 */
function axismundi_op_media_attachment_actor_uri( WP_Post $attachment ) : string {
	$uri = axismundi_op_local_author_actor_uri( (int) $attachment->post_author );
	/**
	 * Filter the Actor URI attributed to one Media Library attachment.
	 *
	 * @since 0.0.4
	 * @param string  $uri        Actor URI or empty string.
	 * @param WP_Post $attachment Attachment.
	 */
	return (string) apply_filters( 'axismundi_op_media_attachment_actor_uri', $uri, $attachment );
}

/**
 * Anonymous/cache-safe attachment visibility.
 *
 * Do not call axismundi_media_can_view_single(): it intentionally grants an owner/editor
 * bypass, while a negotiated public representation must never vary by logged-in viewer.
 *
 * @param WP_Post $attachment Attachment.
 * @return bool
 */
function axismundi_op_media_attachment_visible( WP_Post $attachment ) : bool {
	$tier = axismundi_media_effective_visibility( (int) $attachment->ID );
	$gate = (int) axismundi_media_locked_gate_for_attachment( (int) $attachment->ID );
	return in_array( $tier, array( 'public', 'unlisted' ), true )
		&& 0 === $gate
		&& '' !== axismundi_op_media_attachment_actor_uri( $attachment );
}

/**
 * ActivityStreams type for one attachment MIME family.
 *
 * @param string $mime MIME type.
 * @return string
 */
function axismundi_op_media_object_type( string $mime ) : string {
	if ( str_starts_with( $mime, 'image/' ) ) {
		return 'Image';
	}
	if ( str_starts_with( $mime, 'video/' ) ) {
		return 'Video';
	}
	if ( str_starts_with( $mime, 'audio/' ) ) {
		return 'Audio';
	}
	return 'Document';
}

/**
 * Pick the media resource represented by this object.
 *
 * Images use the Media Library's bounded feed rendition helper (never the original by
 * default). Other MIME families use their attachment file because core has no general
 * derivative contract for them.
 *
 * @param int    $attachment_id Attachment id.
 * @param string $mime          MIME type.
 * @return array<string,mixed>|null
 */
function axismundi_op_media_url_links( WP_Post $attachment, array $policy = array() ) : array {
	$id    = (int) $attachment->ID;
	$mime  = (string) get_post_mime_type( $attachment );
	$links = array();

	if ( str_starts_with( $mime, 'image/' ) ) {
		// Images advertise only already-generated derivatives. Media Library owns the
		// selection and structurally excludes the original, so there is deliberately no
		// wp_get_attachment_url() fallback here: no derivative means no media Link.
		foreach ( axismundi_media_federation_renditions( $id, $policy ) as $rendition ) {
			$links[] = array(
				'type'      => 'Link',
				'href'      => (string) $rendition['url'],
				'mediaType' => (string) $rendition['mediaType'],
				'width'     => (int) $rendition['width'],
				'height'    => (int) $rendition['height'],
				'size'      => (int) $rendition['size'],
			);
		}
	} else {
		// Video / audio / documents keep their existing single-file policy: without a
		// transcoding substrate no versions are invented (MEDIA-RENDITIONS.md §6), and
		// whether the file is downloadable stays a separate policy question.
		$file = (string) wp_get_attachment_url( $id );
		if ( '' !== $file ) {
			$links[] = array( 'type' => 'Link', 'href' => $file, 'mediaType' => $mime );
		}
	}

	// The human page is always last so a naive url[0] consumer reads media, not HTML.
	$page = get_attachment_link( $id );
	if ( $page ) {
		$links[] = array( 'type' => 'Link', 'href' => (string) $page, 'mediaType' => 'text/html' );
	}
	return $links;
}

/**
 * Rendition policy for media embedded in another object.
 *
 * Nothing in the wider fediverse selects between multiple media versions today, so an
 * embedded image advertises exactly one Link. Multiple versions exist for the standalone
 * object, whose consumer is an Axismundi peer picking a size it can afford
 * (MEDIA-RENDITIONS.md §1). Capping at 1024 also lands on WordPress's own `large` default,
 * which is what themes already render in content.
 *
 * @return array<string,mixed>
 */
function axismundi_op_media_embedded_rendition_policy() : array {
	return array( 'max' => 1, 'max_dimension' => 1024 );
}

/**
 * The descriptor core: identity, type, and MIME are shared by every projection role; the
 * ordered `url[]` is built by the same rules but under a role-dependent rendition policy.
 * Role-dependent descriptive members such as `name` are added by the caller
 * (MEDIA-RENDITIONS.md §5), never here.
 *
 * @param WP_Post             $attachment Attachment.
 * @param array<string,mixed> $policy     Rendition policy; may narrow, never widen.
 * @return array<string,mixed>|null
 */
function axismundi_op_media_descriptor_core( WP_Post $attachment, array $policy = array() ) : ?array {
	if ( ! axismundi_op_media_attachment_visible( $attachment ) ) {
		return null;
	}
	$links = axismundi_op_media_url_links( $attachment, $policy );
	if ( empty( $links ) ) {
		return null;
	}
	return array(
		'id'        => axismundi_op_media_attachment_uri( $attachment ),
		'type'      => axismundi_op_media_object_type( (string) get_post_mime_type( $attachment ) ),
		'mediaType' => (string) get_post_mime_type( $attachment ),
		'url'       => $links,
	);
}

/**
 * Embedded media descriptor for an Article `attachment[]` or `preview.attachment`.
 *
 * FEP-1311 scopes `name` on an embedded media attachment to the alternative text, which is
 * what Mastodon renders as alt. An empty alt omits `name`: a filename-like title in that
 * slot is worse than none for a screen reader.
 *
 * @param WP_Post $attachment Attachment.
 * @return array<string,mixed>|null
 */
function axismundi_op_media_attachment_descriptor( WP_Post $attachment ) : ?array {
	$descriptor = axismundi_op_media_descriptor_core( $attachment, axismundi_op_media_embedded_rendition_policy() );
	if ( null === $descriptor ) {
		return null;
	}
	$alt = trim( (string) get_post_meta( (int) $attachment->ID, '_wp_attachment_image_alt', true ) );
	if ( '' !== $alt ) {
		$descriptor['name'] = sanitize_text_field( wp_strip_all_tags( $alt ) );
	}
	return $descriptor;
}

/** Stable reverse-usage collection URI for one Attachment. */
function axismundi_op_media_used_in_url( WP_Post $attachment ) : string {
	return rest_url( 'axismundi/v1/media/' . (int) $attachment->ID . '/used-in' );
}

/** Add indexed Article media using only the Media Library's public relation API. */
function axismundi_op_media_enrich_article( array $article, WP_Post $post ) : array {
	if ( ! function_exists( 'axismundi_media_relations_for_subject' ) ) {
		return $article;
	}
	$attachments = array();
	foreach ( axismundi_media_relations_for_subject( (int) $post->ID ) as $relation ) {
		if ( 'usage' !== ( $relation['relation_kind'] ?? '' ) || 'active' !== ( $relation['status'] ?? '' ) ) {
			continue;
		}
		$attachment_id = (int) ( $relation['object_attachment_id'] ?? 0 );
		$attachment    = $attachment_id > 0 ? get_post( $attachment_id ) : null;
		if ( ! $attachment instanceof WP_Post || 'attachment' !== $attachment->post_type ) {
			continue;
		}
		$descriptor = axismundi_op_media_attachment_descriptor( $attachment );
		if ( null === $descriptor ) {
			continue;
		}
		if ( 'as:image' === ( $relation['predicate'] ?? '' ) && 'featured' === ( $relation['role'] ?? '' ) ) {
			$article['image'] = $descriptor;
			continue;
		}
		if ( 'as:attachment' === ( $relation['predicate'] ?? '' ) ) {
			$attachments[ $attachment_id ] = $descriptor;
		}
	}
	if ( $attachments ) {
		$article['attachment'] = array_values( $attachments );
	}
	if ( isset( $article['preview'], $article['image'] ) && is_array( $article['preview'] ) ) {
		$article['preview']['attachment'] = $article['image'];
	}
	return $article;
}

/**
 * Transform one Media Library attachment.
 *
 * @param WP_Post $attachment Attachment.
 * @return array<string,mixed>|WP_Error
 */
function axismundi_op_media_attachment_transform( WP_Post $attachment ) {
	$id            = (int) $attachment->ID;
	$mime          = (string) get_post_mime_type( $attachment );
	$attributed_to = axismundi_op_media_attachment_actor_uri( $attachment );
	$core          = axismundi_op_media_descriptor_core( $attachment );
	if ( null === $core || '' === $attributed_to ) {
		return new WP_Error( 'ax_op_media_identity', __( 'The attachment has no public object, page, or Actor URI.', 'axismundi-object-projections' ) );
	}

	// The standalone Attachment is a first-class object with its own page, likes, and
	// usedIn collection, so its `name` is the attachment's own title rather than alt text.
	$object = array_merge(
		$core,
		array(
			'attributedTo' => $attributed_to,
			'name'         => '' !== trim( (string) $attachment->post_title ) ? $attachment->post_title : __( '(untitled media)', 'axismundi-object-projections' ),
			'published'    => get_post_time( DATE_W3C, true, $attachment ),
			'updated'      => get_post_modified_time( DATE_W3C, true, $attachment ),
		)
	);

	$description = trim( (string) $attachment->post_content );
	$caption     = trim( (string) $attachment->post_excerpt );
	if ( '' !== $description ) {
		$object['content'] = wpautop( $description );
	}
	if ( '' !== $caption ) {
		$object['summary'] = $caption;
	}

	$sensitive = axismundi_media_is_sensitive( $id );
	$object['sensitive'] = $sensitive;
	if ( $sensitive ) {
		$object['summary'] = axismundi_media_content_warning_text( $id );
	}

	$license = axismundi_media_license_record( $id );
	if ( ! empty( $license['url'] ) ) {
		$object['license'] = (string) $license['url'];
	}
	if ( function_exists( 'axismundi_media_relations_used_in' ) ) {
		$object['usedIn'] = axismundi_op_media_used_in_url( $attachment );
	}

	/**
	 * Filter the Media Library attachment projection before renderer validation.
	 *
	 * @since 0.0.4
	 * @param array<string,mixed> $object     Projection.
	 * @param WP_Post            $attachment Source attachment.
	 */
	return apply_filters( 'axismundi_op_media_attachment', $object, $attachment );
}

/**
 * Detect the optional first-party plugin and register its transformer/context.
 *
 * @return void
 */
function axismundi_op_register_media_library_integration() : void {
	if ( ! axismundi_op_media_library_available() ) {
		return;
	}
	axismundi_op_register_object_transformer(
		'axismundi-media-attachment',
		array(
			'supports'   => 'axismundi_op_media_attachment_supports',
			'object_uri' => 'axismundi_op_media_attachment_uri',
			'transform'  => 'axismundi_op_media_attachment_transform',
			'visible'    => 'axismundi_op_media_attachment_visible',
			'priority'   => 10,
		)
	);
	add_filter( 'axismundi_op_jsonld_context', 'axismundi_op_media_jsonld_context', 10, 2 );
	add_filter( 'axismundi_op_post_article', 'axismundi_op_media_enrich_article', 8, 2 );
	if ( function_exists( 'axismundi_media_relations_used_in' ) ) {
		axismundi_op_register_collection_transformer(
			'axismundi-media-used-in',
			array(
				'supports'       => static fn( $source ) : bool => $source instanceof Axismundi_OP_Media_Used_In,
				'collection_uri' => static fn( Axismundi_OP_Media_Used_In $source ) : string => axismundi_op_media_used_in_url( $source->get_attachment() ),
				'transform'      => 'axismundi_op_media_used_in_transform',
				'visible'        => 'axismundi_op_media_used_in_visible',
				'priority'       => 5,
			)
		);
	}
}
add_action( 'axismundi_op_register_transformers', 'axismundi_op_register_media_library_integration' );

/** Public visibility gate for one Attachment reverse-usage collection. */
function axismundi_op_media_used_in_visible( Axismundi_OP_Media_Used_In $source ) : bool {
	return axismundi_op_media_attachment_visible( $source->get_attachment() );
}

/** Project the distinct public Articles that currently reference an Attachment. */
function axismundi_op_media_used_in_transform( Axismundi_OP_Media_Used_In $source ) : array {
	$attachment = $source->get_attachment();
	$items      = array();
	foreach ( axismundi_media_relations_used_in( (int) $attachment->ID, 0 ) as $relation ) {
		$post_id = (int) ( $relation['subject_post_id'] ?? 0 );
		$post    = $post_id > 0 ? get_post( $post_id ) : null;
		if ( ! $post instanceof WP_Post || 'post' !== $post->post_type ) {
			continue;
		}
		$projected = axismundi_op_transform_object( $post );
		if ( is_array( $projected ) && 'Article' === ( $projected['type'] ?? '' ) ) {
			$items[ (string) $projected['id'] ] = get_post_time( 'U', true, $post );
		}
	}
	arsort( $items, SORT_NUMERIC );
	return array(
		'id'           => axismundi_op_media_used_in_url( $attachment ),
		'type'         => 'OrderedCollection',
		'attributedTo' => axismundi_op_media_attachment_actor_uri( $attachment ),
		'url'          => get_attachment_link( (int) $attachment->ID ),
		'totalItems'   => count( $items ),
		'orderedItems' => array_keys( $items ),
	);
}

/** Register the public Attachment reverse-usage route. */
function axismundi_op_register_media_used_in_route() : void {
	if ( ! axismundi_op_media_library_available() || ! function_exists( 'axismundi_media_relations_used_in' ) ) {
		return;
	}
	register_rest_route(
		'axismundi/v1',
		'/media/(?P<id>\d+)/used-in',
		array(
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => 'axismundi_op_get_media_used_in',
			'permission_callback' => '__return_true',
			'args'                => array( 'id' => array( 'required' => true, 'type' => 'integer', 'minimum' => 1 ) ),
		)
	);
}
add_action( 'rest_api_init', 'axismundi_op_register_media_used_in_route' );

/** Serve one public Attachment reverse-usage collection. */
function axismundi_op_get_media_used_in( WP_REST_Request $request ) {
	$attachment = get_post( (int) $request['id'] );
	if ( ! $attachment instanceof WP_Post || 'attachment' !== $attachment->post_type || ! axismundi_op_media_attachment_visible( $attachment ) ) {
		return new WP_Error( 'ax_op_used_in_not_found', __( 'The media usage collection was not found.', 'axismundi-object-projections' ), array( 'status' => 404 ) );
	}
	$collection = axismundi_op_transform_collection( new Axismundi_OP_Media_Used_In( $attachment ) );
	if ( is_wp_error( $collection ) ) {
		return $collection;
	}
	$response = rest_ensure_response( $collection );
	$response->header( 'Content-Type', 'application/activity+json; charset=' . get_option( 'blog_charset' ) );
	$response->header( 'Cache-Control', 'public, max-age=60' );
	return $response;
}
