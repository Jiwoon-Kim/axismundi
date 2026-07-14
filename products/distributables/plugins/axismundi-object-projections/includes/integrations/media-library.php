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
		'sensitive'       => 'as:sensitive',
		'schema'          => 'https://schema.org/',
		'license'         => array(
			'@id'   => 'schema:license',
			'@type' => '@id',
		),
	);
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
function axismundi_op_media_resource( int $attachment_id, string $mime ) : ?array {
	$rendition = null;
	if ( str_starts_with( $mime, 'image/' ) ) {
		$rendition = axismundi_media_feed_rendition( $attachment_id, array( 'medium_large', 'large', 'medium' ) );
	}

	$url = is_array( $rendition ) ? (string) $rendition['url'] : (string) wp_get_attachment_url( $attachment_id );
	if ( '' === $url ) {
		return null;
	}
	$link = array(
		'type'      => 'Link',
		'href'      => $url,
		'mediaType' => is_array( $rendition ) ? (string) $rendition['mime'] : $mime,
	);
	if ( is_array( $rendition ) ) {
		$link['width']  = (int) $rendition['width'];
		$link['height'] = (int) $rendition['height'];
	}
	return $link;
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
	$url           = get_attachment_link( $id );
	if ( ! $url || '' === $attributed_to ) {
		return new WP_Error( 'ax_op_media_identity', __( 'The attachment has no public object, page, or Actor URI.', 'axismundi-object-projections' ) );
	}

	$object = array(
		'id'           => axismundi_op_media_attachment_uri( $attachment ),
		'type'         => axismundi_op_media_object_type( $mime ),
		'attributedTo' => $attributed_to,
		'url'          => $url,
		'name'         => '' !== trim( (string) $attachment->post_title ) ? $attachment->post_title : __( '(untitled media)', 'axismundi-object-projections' ),
		'mediaType'    => $mime,
		'published'    => get_post_time( DATE_W3C, true, $attachment ),
		'updated'      => get_post_modified_time( DATE_W3C, true, $attachment ),
	);

	$description = trim( (string) $attachment->post_content );
	$caption     = trim( (string) $attachment->post_excerpt );
	if ( '' !== $description ) {
		$object['content'] = wpautop( $description );
	}
	if ( '' !== $caption ) {
		$object['summary'] = $caption;
	}

	$resource = axismundi_op_media_resource( $id, $mime );
	if ( null !== $resource ) {
		$object['attachment'] = $resource;
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
}
add_action( 'axismundi_op_register_transformers', 'axismundi_op_register_media_library_integration' );
