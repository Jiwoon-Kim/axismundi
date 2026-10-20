<?php
/**
 * Mixed local and remote Object archive for one shared hashtag.
 *
 * @package AxismundiObjectProjections
 */

defined( 'ABSPATH' ) || exit;

/** Whether a taxonomy term is one public Axismundi hashtag archive. */
function axismundi_op_is_hashtag_archive_term( $source ) : bool {
	return $source instanceof WP_Term && AXISMUNDI_OP_HASHTAG_TAXONOMY === $source->taxonomy;
}

/** Stable canonical URI for one hashtag archive collection. */
function axismundi_op_hashtag_archive_uri( WP_Term $term ) : string {
	return axismundi_op_is_hashtag_archive_term( $term ) ? add_query_arg( 'tag_id', $term->term_id, home_url( '/' ) ) : '';
}

/** Claim only an unambiguous numeric hashtag query; leave Core tags untouched. */
function axismundi_op_numeric_hashtag_term( array $query ) : ?WP_Term {
	$id = $query['tag_id'] ?? null;
	if ( ( ! is_int( $id ) && ! is_string( $id ) ) || ! preg_match( '/^[1-9][0-9]*$/D', (string) $id ) || false === filter_var( $id, FILTER_VALIDATE_INT, array( 'options' => array( 'min_range' => 1 ) ) ) ) {
		return null;
	}
	foreach ( array( 'p', 'page_id', 'attachment_id', 'name', 'pagename', 'attachment', 'cat', 'category_name', 'tag', 'taxonomy', 'term', 'post_type', 'ax_note', 'ax_object', 'ax_remote_object' ) as $key ) {
		if ( ! empty( $query[ $key ] ) ) {
			return null;
		}
	}
	// Legacy shared terms can inhabit two taxonomies. Never steal Core's tag route.
	if ( get_term( (int) $id, 'post_tag' ) instanceof WP_Term ) {
		return null;
	}
	$term = get_term( (int) $id, AXISMUNDI_OP_HASHTAG_TAXONOMY );
	return $term instanceof WP_Term ? $term : null;
}

/** Resolve the term before WP_Query hardcodes tag_id as a post_tag query. */
function axismundi_op_route_numeric_hashtag( array $query ) : array {
	// Core's tag_id is a private WP_Query variable, not a public URL variable.
	// Claim only our own terms instead of exposing it globally for every taxonomy.
	$root = wp_parse_url( home_url( '/' ), PHP_URL_PATH );
	$path = wp_parse_url( wp_unslash( $_SERVER['REQUEST_URI'] ?? (string) $root ), PHP_URL_PATH );
	if ( isset( $_GET['tag_id'] ) && untrailingslashit( (string) $path ) !== untrailingslashit( (string) $root ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only namespace gate.
		return $query;
	}
	$requested = array_merge( $query, wp_unslash( $_GET ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Public, read-only identity lookup.
	$term = axismundi_op_numeric_hashtag_term( $requested );
	if ( ! $term instanceof WP_Term ) {
		return $query;
	}
	unset( $query['tag_id'] );
	$query['taxonomy'] = AXISMUNDI_OP_HASHTAG_TAXONOMY;
	$query['term'] = $term->slug;
	return $query;
}
add_filter( 'request', 'axismundi_op_route_numeric_hashtag', 20 );

/** The numeric identifier is not an alias to be canonicalized to a mutable slug. */
function axismundi_op_numeric_hashtag_canonical( $redirect, $requested ) {
	$path = wp_parse_url( (string) $requested, PHP_URL_PATH );
	$root = wp_parse_url( home_url( '/' ), PHP_URL_PATH );
	if ( untrailingslashit( (string) $path ) !== untrailingslashit( (string) $root ) ) {
		return $redirect;
	}
	$query = wp_parse_url( (string) $requested, PHP_URL_QUERY );
	parse_str( (string) $query, $vars );
	return axismundi_op_numeric_hashtag_term( $vars ) instanceof WP_Term ? false : $redirect;
}
add_filter( 'redirect_canonical', 'axismundi_op_numeric_hashtag_canonical', 20, 2 );

/** Redirect only legacy human paths, after the negotiated router has answered. */
function axismundi_op_redirect_legacy_hashtag() : void {
	$term = get_queried_object();
	if ( ! axismundi_op_is_hashtag_archive_term( $term ) ) {
		return;
	}
	$url = get_term_link( $term );
	if ( is_wp_error( $url ) ) {
		return;
	}
	$path = wp_parse_url( wp_unslash( $_SERVER['REQUEST_URI'] ?? '' ), PHP_URL_PATH );
	$legacy = str_replace( '/hashtags/', '/hashtag/', wp_parse_url( $url, PHP_URL_PATH ) );
	if ( $path === $legacy ) {
		wp_safe_redirect( add_query_arg( wp_unslash( $_GET ), $url ), 301 ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Preserve read-only query arguments.
		exit;
	}
}
add_action( 'template_redirect', 'axismundi_op_redirect_legacy_hashtag', 9 );

/**
 * Project a hashtag archive identity under the project's collection policy.
 *
 * The archive is a locally indexed discovery surface, not an Actor-owned
 * timeline. This slice exposes identity only; membership paging will move to
 * the unified term index, and no exact visible total is claimed here.
 */
function axismundi_op_hashtag_archive_transform( WP_Term $term ) : array {
	$document = array(
		'id'   => axismundi_op_hashtag_archive_uri( $term ),
		'type' => 'OrderedCollection',
		'name' => '#' . $term->name,
	);
	$summary = wp_kses_post( $term->description );
	if ( '' !== trim( $summary ) ) {
		$document['summary'] = $summary;
	}
	$url = get_term_link( $term );
	if ( ! is_wp_error( $url ) ) {
		$document['url'] = $url;
	}
	return $document;
}

/** Register the negotiated ActivityStreams representation for hashtag archives. */
function axismundi_op_register_hashtag_archive_transformer() : void {
	axismundi_op_register_collection_transformer(
		'axismundi-hashtag-archive',
		array(
			'supports'       => 'axismundi_op_is_hashtag_archive_term',
			'collection_uri' => 'axismundi_op_hashtag_archive_uri',
			'transform'      => 'axismundi_op_hashtag_archive_transform',
			'priority'       => 5,
		)
	);
}
add_action( 'axismundi_op_register_transformers', 'axismundi_op_register_hashtag_archive_transformer' );

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
	$page = axismundi_op_query_term_members( $term, array( 'type' => $type, 'limit' => $limit ) );
	return is_wp_error( $page ) ? array() : $page['items'];
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
		$query = 'all' === $type ? array() : array( 'type' => $type );
		if ( isset( $_GET['actor'] ) && is_string( $_GET['actor'] ) && '' !== $_GET['actor'] ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Public narrowing filter.
			$query['actor'] = wp_unslash( $_GET['actor'] );
		}
		$url = $query ? $base . '?' . http_build_query( $query, '', '&', PHP_QUERY_RFC3986 ) : $base;
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
	$type = isset( $_GET['type'] ) && is_string( $_GET['type'] ) ? axismundi_op_hashtag_archive_type( sanitize_key( wp_unslash( $_GET['type'] ) ) ) : 'all'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Public read filter.
	$limit = isset( $attributes['perPage'] ) ? (int) $attributes['perPage'] : 50;
	$actor = isset( $_GET['actor'] ) && is_string( $_GET['actor'] ) ? wp_unslash( $_GET['actor'] ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Public narrowing filter.
	$cursor = isset( $_GET['cursor'] ) && is_string( $_GET['cursor'] ) ? wp_unslash( $_GET['cursor'] ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Public traversal state.
	$page = axismundi_op_query_term_members( $term, array( 'type' => $type, 'limit' => $limit, 'actor' => $actor, 'cursor' => $cursor ) );
	$cards = array();
	foreach ( is_wp_error( $page ) ? array() : $page['items'] as $item ) {
		$card = axismundi_op_render_object_by_uri( (string) $item['object_uri'], array( 'headingTag' => 'h2', 'interactions' => false ) );
		if ( '' !== $card ) {
			$cards[] = '<li class="axismundi-hashtag-archive__item">' . $card . '</li>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Object renderer owns escaped card HTML.
		}
	}
	$body = empty( $cards )
		? '<p class="axismundi-hashtag-archive__empty">' . esc_html__( 'No public objects use this hashtag yet.', 'axismundi-object-projections' ) . '</p>'
		: '<ol class="axismundi-hashtag-archive__list">' . implode( '', $cards ) . '</ol>';
	if ( ! is_wp_error( $page ) && null !== $page['next_cursor'] ) {
		$base = get_term_link( $term );
		if ( ! is_wp_error( $base ) ) {
			$query = http_build_query( array( 'type' => $type, 'actor' => $actor, 'cursor' => $page['next_cursor'] ), '', '&', PHP_QUERY_RFC3986 );
			$body .= '<a rel="next" href="' . esc_url( $base . '?' . $query ) . '">' . esc_html__( 'Next', 'axismundi-object-projections' ) . '</a>';
		}
	}
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
