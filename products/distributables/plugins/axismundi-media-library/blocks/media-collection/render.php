<?php
/**
 * Navigable media collection render.
 *
 * @package AxismundiMediaLibrary
 */

defined( 'ABSPATH' ) || exit;

$axismundi_media_collection_source = in_array( (string) ( $attributes['source'] ?? 'current' ), array( 'current', 'owner', 'folder' ), true ) ? (string) ( $attributes['source'] ?? 'current' ) : 'current';
$axismundi_media_collection_owner  = max( 0, (int) ( $attributes['ownerId'] ?? 0 ) );
$axismundi_media_collection_folder = max( 0, (int) ( $attributes['folderId'] ?? 0 ) );
$axismundi_media_collection_route  = (string) get_query_var( 'ax_media_archive' );

if ( 'current' === $axismundi_media_collection_source ) {
	if ( 'folder' === $axismundi_media_collection_route ) {
		$axismundi_media_collection_folder = (int) get_query_var( 'ax_media_folder' );
		$axismundi_media_collection_owner  = axismundi_media_folder_owner( $axismundi_media_collection_folder );
	} elseif ( 'owner' === $axismundi_media_collection_route ) {
		$axismundi_media_collection_raw_owner = (string) get_query_var( 'ax_media_owner' );
		$axismundi_media_collection_user = ctype_digit( $axismundi_media_collection_raw_owner )
			? get_user_by( 'id', (int) $axismundi_media_collection_raw_owner )
			: get_user_by( 'slug', $axismundi_media_collection_raw_owner );
		$axismundi_media_collection_owner = $axismundi_media_collection_user ? (int) $axismundi_media_collection_user->ID : 0;
	} elseif ( ! in_array( $axismundi_media_collection_route, array( 'landing', 'owner', 'folder' ), true ) && is_admin() ) {
		$axismundi_media_collection_source = 'owner';
		$axismundi_media_collection_owner  = get_current_user_id();
	}
}

if ( 'folder' === $axismundi_media_collection_source && $axismundi_media_collection_folder > 0 ) {
	$axismundi_media_collection_owner = axismundi_media_folder_owner( $axismundi_media_collection_folder );
}

$axismundi_media_collection_scope = $axismundi_media_collection_folder > 0 ? 'folder' : ( $axismundi_media_collection_owner > 0 ? 'owner' : 'landing' );
$axismundi_media_collection_columns = max( 1, min( 6, (int) ( $attributes['columns'] ?? 4 ) ) );
$axismundi_media_collection_per_page = max( 1, min( 48, (int) ( $attributes['perPage'] ?? 12 ) ) );
$axismundi_media_collection_page = max( 1, (int) get_query_var( 'ax_media_page', 1 ) );
$axismundi_media_collection_size = sanitize_key( (string) ( $attributes['imageSize'] ?? 'medium_large' ) );
$axismundi_media_collection_show_dates = ! isset( $attributes['showDates'] ) || (bool) $attributes['showDates'];
$axismundi_media_collection_show_counts = ! empty( $attributes['showCounts'] );
$axismundi_media_collection_show_up = ! empty( $attributes['showUp'] );
$axismundi_media_collection_default_structure =
	'<!-- wp:axismundi/media-folders ' . wp_json_encode( array( 'showCounts' => $axismundi_media_collection_show_counts, 'showUp' => $axismundi_media_collection_show_up ) ) . ' /-->' .
	'<!-- wp:axismundi/media-post-template -->' .
	'<!-- wp:axismundi/media-preview ' . wp_json_encode( array( 'sizeSlug' => $axismundi_media_collection_size ) ) . ' /-->' .
	'<!-- wp:post-title {"level":3,"isLink":true} /-->' .
	( $axismundi_media_collection_show_dates ? '<!-- wp:post-date /-->' : '' ) .
	'<!-- /wp:axismundi/media-post-template -->' .
	'<!-- wp:axismundi/media-no-results --><!-- wp:paragraph --><p>' . esc_html__( 'No media is available in this collection.', 'axismundi-media-library' ) . '</p><!-- /wp:paragraph --><!-- /wp:axismundi/media-no-results -->' .
	'<!-- wp:axismundi/media-pagination /-->';
$axismundi_media_collection_structure = array_values(
	array_filter(
		! empty( $block->parsed_block['innerBlocks'] ) ? $block->parsed_block['innerBlocks'] : parse_blocks( $axismundi_media_collection_default_structure ),
		static fn( array $inner_block ) : bool => ! empty( $inner_block['blockName'] )
	)
);
$axismundi_media_collection_find_region = static function ( string $name ) use ( $axismundi_media_collection_structure ) : ?array {
	foreach ( $axismundi_media_collection_structure as $region ) {
		if ( $name === $region['blockName'] ) {
			return $region;
		}
	}
	return null;
};
$axismundi_media_collection_folders_region = $axismundi_media_collection_find_region( 'axismundi/media-folders' );
$axismundi_media_collection_template_region = $axismundi_media_collection_find_region( 'axismundi/media-post-template' );
$axismundi_media_collection_no_results_region = $axismundi_media_collection_find_region( 'axismundi/media-no-results' );
$axismundi_media_collection_pagination_region = $axismundi_media_collection_find_region( 'axismundi/media-pagination' );
if ( $axismundi_media_collection_folders_region ) {
	$axismundi_media_collection_show_counts = ! empty( $axismundi_media_collection_folders_region['attrs']['showCounts'] );
	$axismundi_media_collection_show_up = ! empty( $axismundi_media_collection_folders_region['attrs']['showUp'] );
}
$axismundi_media_collection_folders_heading = trim( (string) ( $axismundi_media_collection_folders_region['attrs']['heading'] ?? __( 'Folders', 'axismundi-media-library' ) ) );
$axismundi_media_collection_folder_tiles = array();
$axismundi_media_collection_attachment_tiles = array();
$axismundi_media_collection_up_url = '';

// Navigation tiles are not paged: folders remain stable while attachments page.
$axismundi_media_collection_parent = 0;
$axismundi_media_collection_children_parent = 0;
if ( 'folder' === $axismundi_media_collection_scope ) {
	$axismundi_media_collection_term = get_term( $axismundi_media_collection_folder, AXISMUNDI_MEDIA_FOLDER_TAX );
	if ( $axismundi_media_collection_term instanceof WP_Term ) {
		$axismundi_media_collection_parent = (int) $axismundi_media_collection_term->parent;
		$axismundi_media_collection_children_parent = $axismundi_media_collection_folder;
	}
} elseif ( 'owner' === $axismundi_media_collection_scope ) {
	$axismundi_media_collection_children_parent = axismundi_media_user_root( $axismundi_media_collection_owner, false );
}

if ( 'folder' === $axismundi_media_collection_scope && $axismundi_media_collection_show_up ) {
	$axismundi_media_collection_parent_term = get_term( $axismundi_media_collection_parent, AXISMUNDI_MEDIA_FOLDER_TAX );
	$axismundi_media_collection_up_url = $axismundi_media_collection_parent_term instanceof WP_Term && ! axismundi_media_is_root_term( (int) $axismundi_media_collection_parent_term->term_id )
		? axismundi_media_folder_url( $axismundi_media_collection_owner, (int) $axismundi_media_collection_parent_term->term_id )
		: axismundi_media_author_url( $axismundi_media_collection_owner );
}

if ( in_array( $axismundi_media_collection_scope, array( 'owner', 'folder' ), true ) && $axismundi_media_collection_children_parent > 0 ) {
	$axismundi_media_collection_children = get_terms(
		array(
			'taxonomy'   => AXISMUNDI_MEDIA_FOLDER_TAX,
			'hide_empty' => false,
			'parent'     => $axismundi_media_collection_children_parent,
		)
	);
	if ( ! is_wp_error( $axismundi_media_collection_children ) ) {
		foreach ( $axismundi_media_collection_children as $axismundi_media_collection_child ) {
			$axismundi_media_collection_child_id = (int) $axismundi_media_collection_child->term_id;
			$axismundi_media_collection_child_rank = axismundi_media_folder_effective_tier_rank( $axismundi_media_collection_child_id );
			$axismundi_media_collection_child_gate = axismundi_media_locked_folder_gate( $axismundi_media_collection_child_id );
			$axismundi_media_collection_allowed_rank = 'folder' === $axismundi_media_collection_scope ? min( 1, axismundi_media_folder_effective_tier_rank( $axismundi_media_collection_folder ) ) : 0;
			if ( $axismundi_media_collection_child_rank > $axismundi_media_collection_allowed_rank && ! axismundi_media_can_manage_folder( $axismundi_media_collection_child_id ) ) {
				continue;
			}
			$axismundi_media_collection_count = $axismundi_media_collection_child_gate > 0
				? null
				: axismundi_media_folder_visible_count( $axismundi_media_collection_child_id, $axismundi_media_collection_allowed_rank );
			$axismundi_media_collection_folder_tiles[] = sprintf(
				'<li class="ax-media-collection__item is-folder%4$s"><a href="%1$s"><span class="material-symbols-outlined ax-media-collection__folder-icon" aria-hidden="true">folder</span><span class="ax-media-collection__title">%2$s</span>%3$s%5$s</a></li>',
				esc_url( axismundi_media_folder_url( $axismundi_media_collection_owner, $axismundi_media_collection_child_id ) ),
				esc_html( $axismundi_media_collection_child->name ),
				$axismundi_media_collection_show_counts && null !== $axismundi_media_collection_count ? '<span class="ax-media-collection__count">' . esc_html( (string) $axismundi_media_collection_count ) . '</span>' : '',
				$axismundi_media_collection_child_gate > 0 ? ' is-protected' : '',
				$axismundi_media_collection_child_gate > 0 ? '<span class="material-symbols-outlined ax-media-collection__lock" aria-label="' . esc_attr__( 'Password protected', 'axismundi-media-library' ) . '">lock</span>' : ''
			);
		}
	}
}

$axismundi_media_collection_query_args = array(
	'post_type'                  => 'attachment',
	'post_status'                => 'inherit',
	'posts_per_page'             => $axismundi_media_collection_per_page,
	'paged'                      => $axismundi_media_collection_page,
	'orderby'                    => 'date',
	'order'                      => 'DESC',
	'ax_media_visibility_filter' => true,
);
if ( $axismundi_media_collection_owner > 0 ) {
	$axismundi_media_collection_query_args['author'] = $axismundi_media_collection_owner;
}
if ( 'folder' === $axismundi_media_collection_scope ) {
	$axismundi_media_collection_query_args['tax_query'] = array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query -- Explicit collection scope.
		array(
			'taxonomy'         => AXISMUNDI_MEDIA_FOLDER_TAX,
			'field'            => 'term_id',
			'terms'            => array( $axismundi_media_collection_folder ),
			'include_children' => false,
		),
	);
	$axismundi_media_collection_rank = axismundi_media_folder_effective_tier_rank( $axismundi_media_collection_folder );
	$axismundi_media_collection_query_args['ax_media_visibility_max_rank'] = min( 1, $axismundi_media_collection_rank );
	if ( axismundi_media_folder_effective_gate( $axismundi_media_collection_folder ) && 0 === axismundi_media_locked_folder_gate( $axismundi_media_collection_folder ) ) {
		$axismundi_media_collection_query_args['ax_media_allow_gated'] = true;
	}
} elseif ( 'owner' === $axismundi_media_collection_scope ) {
	$axismundi_media_collection_query_args['tax_query'] = array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query -- Root collection requires unfiled objects.
		array(
			'taxonomy' => AXISMUNDI_MEDIA_FOLDER_TAX,
			'operator' => 'NOT EXISTS',
		),
	);
}

$axismundi_media_collection_query = new WP_Query( $axismundi_media_collection_query_args );
$axismundi_media_collection_render_template = static function ( array $template_blocks, int $attachment_id ) : string {
	$original_post = $GLOBALS['post'] ?? null;
	$attachment_post = $attachment_id > 0 ? get_post( $attachment_id ) : null;
	if ( $attachment_post instanceof WP_Post ) {
		$GLOBALS['post'] = $attachment_post;
		setup_postdata( $attachment_post );
	}
	$html = '';
	$context = array(
		'postId'   => $attachment_id,
		'postType' => 'attachment',
	);
	foreach ( $template_blocks as $template_block ) {
		if ( empty( $template_block['blockName'] ) ) {
			continue;
		}
		$html .= ( new WP_Block( $template_block, $context ) )->render();
	}
	if ( $original_post instanceof WP_Post ) {
		$GLOBALS['post'] = $original_post;
		setup_postdata( $original_post );
	} elseif ( $attachment_post instanceof WP_Post ) {
		unset( $GLOBALS['post'] );
	}
	return $html;
};
$axismundi_media_collection_item_template = $axismundi_media_collection_template_region['innerBlocks'] ?? array();
foreach ( $axismundi_media_collection_query->posts as $axismundi_media_collection_attachment ) {
	$axismundi_media_collection_attachment_id = (int) $axismundi_media_collection_attachment->ID;
	$axismundi_media_collection_attachment_tiles[] = sprintf(
		'<li class="ax-media-collection__item is-attachment"><article>%s</article></li>',
		$axismundi_media_collection_render_template( $axismundi_media_collection_item_template, $axismundi_media_collection_attachment_id ) // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Rendered registered blocks.
	);
}

$axismundi_media_collection_no_results = '';
if ( $axismundi_media_collection_no_results_region ) {
	$axismundi_media_collection_no_results = $axismundi_media_collection_render_template(
		$axismundi_media_collection_no_results_region['innerBlocks'] ?? array(),
		0
	);
}

$axismundi_media_collection_wrapper = get_block_wrapper_attributes(
	array(
		'class' => 'ax-media-collection',
		'style' => '--ax-media-collection-columns:' . $axismundi_media_collection_columns,
	)
);
$axismundi_media_collection_instance = ! empty( $attributes['anchor'] )
	? sanitize_html_class( (string) $attributes['anchor'] )
	: wp_unique_id( 'ax-media-collection-' );
$axismundi_media_collection_folders_id = $axismundi_media_collection_instance . '-folders';
$axismundi_media_collection_media_id = $axismundi_media_collection_instance . '-media';
?>
<div <?php echo $axismundi_media_collection_wrapper; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Core-generated wrapper attributes. ?>>
	<?php if ( $axismundi_media_collection_folders_region && ( ! empty( $axismundi_media_collection_folder_tiles ) || $axismundi_media_collection_up_url ) ) : ?>
		<section class="ax-media-collection__folders" aria-labelledby="<?php echo esc_attr( $axismundi_media_collection_folders_id ); ?>">
			<header class="ax-media-collection__section-header">
				<h2 id="<?php echo esc_attr( $axismundi_media_collection_folders_id ); ?>"><?php echo esc_html( $axismundi_media_collection_folders_heading ?: __( 'Folders', 'axismundi-media-library' ) ); ?></h2>
				<?php if ( $axismundi_media_collection_up_url ) : ?>
					<a class="ax-media-collection__up" href="<?php echo esc_url( $axismundi_media_collection_up_url ); ?>"><span class="material-symbols-outlined" aria-hidden="true">folder_open</span> <?php esc_html_e( 'Up one level', 'axismundi-media-library' ); ?></a>
				<?php endif; ?>
			</header>
			<?php if ( ! empty( $axismundi_media_collection_folder_tiles ) ) : ?>
				<ul class="ax-media-collection__grid ax-media-collection__folder-grid"><?php echo implode( '', $axismundi_media_collection_folder_tiles ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Every tile component is escaped above. ?></ul>
			<?php endif; ?>
		</section>
	<?php endif; ?>
	<section class="ax-media-collection__media" aria-labelledby="<?php echo esc_attr( $axismundi_media_collection_media_id ); ?>">
		<h2 id="<?php echo esc_attr( $axismundi_media_collection_media_id ); ?>"><?php esc_html_e( 'Media', 'axismundi-media-library' ); ?></h2>
		<?php if ( $axismundi_media_collection_template_region && ! empty( $axismundi_media_collection_query->posts ) ) : ?>
			<ul class="ax-media-collection__grid ax-media-collection__media-grid"><?php echo implode( '', $axismundi_media_collection_attachment_tiles ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Every tile component is escaped above. ?></ul>
		<?php elseif ( empty( $axismundi_media_collection_query->posts ) && $axismundi_media_collection_no_results_region ) : ?>
			<div class="ax-media-collection__no-results"><?php echo $axismundi_media_collection_no_results; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Rendered registered blocks. ?></div>
		<?php endif; ?>
		<?php if ( $axismundi_media_collection_pagination_region && $axismundi_media_collection_query->max_num_pages > 1 ) : ?>
		<nav class="wp-block-query-pagination ax-media-collection__pagination" aria-label="<?php esc_attr_e( 'Media pagination', 'axismundi-media-library' ); ?>">
			<?php
			$axismundi_media_collection_pagination_attrs = $axismundi_media_collection_pagination_region['attrs'] ?? array();
			$axismundi_media_collection_pagination_links = paginate_links(
				array(
					'base'      => esc_url_raw( add_query_arg( 'ax_media_page', '%#%' ) ),
					'format'    => '',
					'current'   => $axismundi_media_collection_page,
					'total'     => (int) $axismundi_media_collection_query->max_num_pages,
					'prev_text' => __( 'Previous', 'axismundi-media-library' ),
					'next_text' => __( 'Next', 'axismundi-media-library' ),
					'type'      => 'array',
				)
			);
			$axismundi_media_collection_pagination_links = array_filter(
				(array) $axismundi_media_collection_pagination_links,
				static function ( string $link ) use ( $axismundi_media_collection_pagination_attrs ) : bool {
					if ( str_contains( $link, 'class="prev ' ) ) {
						return ! isset( $axismundi_media_collection_pagination_attrs['showPrevious'] ) || (bool) $axismundi_media_collection_pagination_attrs['showPrevious'];
					}
					if ( str_contains( $link, 'class="next ' ) ) {
						return ! isset( $axismundi_media_collection_pagination_attrs['showNext'] ) || (bool) $axismundi_media_collection_pagination_attrs['showNext'];
					}
					return ! isset( $axismundi_media_collection_pagination_attrs['showNumbers'] ) || (bool) $axismundi_media_collection_pagination_attrs['showNumbers'];
				}
			);
			echo wp_kses_post( implode( '', $axismundi_media_collection_pagination_links ) );
			?>
		</nav>
		<?php endif; ?>
	</section>
</div>
<?php wp_reset_postdata(); ?>
