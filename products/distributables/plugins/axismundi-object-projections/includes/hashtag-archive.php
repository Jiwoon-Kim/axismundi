<?php
/**
 * Mixed local and remote Object archive for one shared hashtag.
 *
 * @package AxismundiObjectProjections
 */

defined( 'ABSPATH' ) || exit;

/** Normalize an allowed hashtag archive object-type filter. */
function axismundi_op_hashtag_archive_type( $value ) : string {
	$value = sanitize_key( (string) $value );
	return in_array( $value, array( 'all', 'post', 'note', 'media', 'remote' ), true ) ? $value : 'all';
}

/** Local WordPress post types included by one hashtag archive mode. */
function axismundi_op_hashtag_archive_local_types( string $type ) : array {
	return match ( $type ) {
		'post'  => array( 'post' ),
		'note'  => array( 'ax_note' ),
		'media' => array( 'attachment' ),
		'remote' => array(),
		default => array_values( array_filter( array( 'post', 'ax_note' ), 'post_type_exists' ) ),
	};
}

/** Add one resolved, publicly visible source to a URI-keyed archive result map. */
function axismundi_op_hashtag_archive_add_source( array &$items, $source ) : void {
	if ( ! function_exists( 'axismundi_op_object_card_publicly_renderable' )
		|| ! axismundi_op_object_card_publicly_renderable( $source )
	) {
		return;
	}
	$model = axismundi_op_object_view_model( $source );
	$uri   = is_array( $model ) ? (string) ( $model['id'] ?? $model['object_uri'] ?? '' ) : '';
	if ( '' === $uri || ! is_array( $model ) || 'tombstone' === (string) ( $model['status'] ?? '' ) ) {
		return;
	}
	$published = (string) ( $model['published'] ?? '' );
	$items[ $uri ] = array(
		'object_uri' => $uri,
		'published'  => false === strtotime( $published ) ? '' : $published,
		'origin'     => $source instanceof WP_Post ? 'local' : 'remote',
		'type'       => (string) ( $model['type'] ?? 'Object' ),
	);
}

/** Descending Object chronology with canonical URI tie-breaker. */
function axismundi_op_hashtag_archive_compare( array $left, array $right ) : int {
	$left_time  = '' !== (string) $left['published'] ? (int) strtotime( (string) $left['published'] ) : 0;
	$right_time = '' !== (string) $right['published'] ? (int) strtotime( (string) $right['published'] ) : 0;
	return $left_time === $right_time
		? strcmp( (string) $right['object_uri'], (string) $left['object_uri'] )
		: $right_time <=> $left_time;
}

/** Return one mixed public Object page for a hashtag term. */
function axismundi_op_get_hashtag_archive_items( WP_Term $term, string $type = 'all', int $limit = 50 ) : array {
	global $wpdb;
	if ( AXISMUNDI_OP_HASHTAG_TAXONOMY !== $term->taxonomy ) {
		return array();
	}
	$type  = axismundi_op_hashtag_archive_type( $type );
	$limit = max( 1, min( 100, $limit ) );
	$items = array();
	$types = axismundi_op_hashtag_archive_local_types( $type );
	if ( ! empty( $types ) ) {
		$statuses = in_array( 'attachment', $types, true ) ? array( 'publish', 'inherit' ) : array( 'publish' );
		$posts    = get_posts(
			array(
				'post_type'      => $types,
				'post_status'    => $statuses,
				'posts_per_page' => $limit,
				'orderby'        => 'date',
				'order'          => 'DESC',
				'tax_query'      => array(
					array( 'taxonomy' => AXISMUNDI_OP_HASHTAG_TAXONOMY, 'field' => 'term_id', 'terms' => array( $term->term_id ) ),
				),
			)
		);
		foreach ( $posts as $post ) {
			axismundi_op_hashtag_archive_add_source( $items, $post );
		}
	}
	if ( 'post' !== $type && 'note' !== $type && 'media' !== $type ) {
		$index   = axismundi_op_remote_object_hashtags_table();
		$objects = axismundi_op_remote_objects_table();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fixed projection tables and prepared term key.
		$rows = (array) $wpdb->get_results( $wpdb->prepare( "SELECT o.* FROM {$objects} o INNER JOIN {$index} h ON h.remote_object_id = o.id WHERE h.term_taxonomy_id = %d AND o.object_status = 'active' ORDER BY COALESCE(o.published_at, o.remote_updated_at, o.created_at) DESC, o.id DESC LIMIT %d", (int) $term->term_taxonomy_id, $limit ), ARRAY_A );
		foreach ( $rows as $row ) {
			$row['payload'] = json_decode( (string) ( $row['payload_json'] ?? '' ), true );
			$row['payload'] = is_array( $row['payload'] ) ? $row['payload'] : array();
			if ( ! axismundi_op_remote_object_is_publicly_listable( $row ) ) {
				continue;
			}
			$source = axismundi_op_resolve_source_by_uri( (string) $row['object_uri'] );
			if ( null !== $source ) {
				axismundi_op_hashtag_archive_add_source( $items, $source );
			}
		}
	}
	usort( $items, 'axismundi_op_hashtag_archive_compare' );
	return array_slice( $items, 0, $limit );
}

/** Render type filters without making type membership a taxonomy concern. */
function axismundi_op_render_hashtag_archive_filters( WP_Term $term, string $current ) : string {
	$labels = array(
		'all'    => __( 'All', 'axismundi-object-projections' ),
		'post'   => __( 'Posts', 'axismundi-object-projections' ),
		'note'   => __( 'Notes', 'axismundi-object-projections' ),
		'media'  => __( 'Media', 'axismundi-object-projections' ),
		'remote' => __( 'Remote', 'axismundi-object-projections' ),
	);
	$base = get_term_link( $term );
	if ( is_wp_error( $base ) ) {
		return '';
	}
	$links = array();
	foreach ( $labels as $type => $label ) {
		$url = 'all' === $type ? $base : add_query_arg( 'type', $type, $base );
		$links[] = '<a class="axismundi-hashtag-archive__filter' . ( $type === $current ? ' is-current' : '' ) . '" href="' . esc_url( $url ) . '"' . ( $type === $current ? ' aria-current="page"' : '' ) . '>' . esc_html( $label ) . '</a>';
	}
	return '<nav class="axismundi-hashtag-archive__filters" aria-label="' . esc_attr__( 'Object type', 'axismundi-object-projections' ) . '">' . implode( '', $links ) . '</nav>';
}

/**
 * Render the mixed public Object archive for the queried hashtag term.
 *
 * The taxonomy main query keeps the term context and the normal template
 * hierarchy intact; it deliberately does not drive this list. A cached remote
 * observation is not a `WP_Post`, so the mixed local/remote result set and its
 * paging belong to this block rather than to a Core Query Loop.
 */
function axismundi_op_render_hashtag_archive_block( array $attributes = array(), string $content = '' ) : string {
	unset( $content );
	$term = get_queried_object();
	if ( ! $term instanceof WP_Term || AXISMUNDI_OP_HASHTAG_TAXONOMY !== $term->taxonomy ) {
		return '';
	}
	$type  = axismundi_op_hashtag_archive_type( sanitize_key( wp_unslash( $_GET['type'] ?? 'all' ) ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- public read filter.
	$limit = isset( $attributes['perPage'] ) ? (int) $attributes['perPage'] : 50;
	$cards = array();
	foreach ( axismundi_op_get_hashtag_archive_items( $term, $type, $limit ) as $item ) {
		$card = axismundi_op_render_object_by_uri( (string) $item['object_uri'], array( 'headingTag' => 'h2', 'interactions' => false ) );
		if ( '' !== $card ) {
			$cards[] = '<li class="axismundi-hashtag-archive__item">' . $card . '</li>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Object renderer owns escaped card HTML.
		}
	}
	$body = empty( $cards )
		? '<p class="axismundi-hashtag-archive__empty">' . esc_html__( 'No public objects use this hashtag yet.', 'axismundi-object-projections' ) . '</p>'
		: '<ol class="axismundi-hashtag-archive__list">' . implode( '', $cards ) . '</ol>';
	return '<div ' . get_block_wrapper_attributes( array( 'class' => 'axismundi-hashtag-archive' ) ) . '>'
		. axismundi_op_render_hashtag_archive_filters( $term, $type )
		. $body
		. '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Filters and cards are escaped by their renderers.
}

/** Read the hashtag archive block template bundled with OP. */
function axismundi_op_hashtag_archive_template_content() : string {
	$path = dirname( __DIR__ ) . '/templates/taxonomy-ax_hashtag.php';
	if ( ! is_readable( $path ) ) {
		return '';
	}
	ob_start();
	include $path;
	return (string) ob_get_clean();
}

/** Show the hashtag archive title with its ActivityStreams display prefix. */
function axismundi_op_hashtag_archive_title( $title ) {
	$term = get_queried_object();
	return is_tax( AXISMUNDI_OP_HASHTAG_TAXONOMY ) && $term instanceof WP_Term ? '#' . $term->name : $title;
}
add_filter( 'get_the_archive_title', 'axismundi_op_hashtag_archive_title' );

/**
 * Register the archive block and OP's default `taxonomy-ax_hashtag` template.
 *
 * The standard hierarchy slug lets a theme file or a Site Editor customization
 * take precedence over this bundled default, which a `template_redirect` render
 * could never allow.
 */
function axismundi_op_register_hashtag_archive() : void {
	register_block_type(
		'axismundi/hashtag-archive',
		array(
			'api_version'     => 3,
			'title'           => __( 'Axismundi Hashtag Archive', 'axismundi-object-projections' ),
			'description'     => __( 'Public local and cached remote Objects that share the queried hashtag.', 'axismundi-object-projections' ),
			'category'        => 'theme',
			// The archive block sits in the bundled taxonomy template, so it needs a
			// client-side registration too: editing that template without one shows
			// the block as unsupported.
			'editor_script'   => 'axismundi-op-object-blocks',
			'style'           => 'axismundi-op-object-view',
			'editor_style'    => 'axismundi-op-object-view',
			'render_callback' => 'axismundi_op_render_hashtag_archive_block',
			'attributes'      => array( 'perPage' => array( 'type' => 'number', 'default' => 50 ) ),
			'supports'        => array( 'html' => false, 'align' => array( 'wide', 'full' ) ),
		)
	);
	if ( function_exists( 'register_block_template' ) ) {
		register_block_template(
			'axismundi-object-projections//taxonomy-ax_hashtag',
			array(
				'title'       => __( 'Axismundi Hashtag Archive', 'axismundi-object-projections' ),
				'description' => __( 'The mixed local and cached remote Object archive for one shared hashtag.', 'axismundi-object-projections' ),
				'content'     => axismundi_op_hashtag_archive_template_content(),
			)
		);
	}
}
add_action( 'init', 'axismundi_op_register_hashtag_archive', 20 );
