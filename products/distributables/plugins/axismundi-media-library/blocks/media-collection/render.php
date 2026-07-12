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
$axismundi_media_collection_columns = max( 1, min( 6, (int) ( $attributes['columns'] ?? 3 ) ) );
$axismundi_media_collection_per_page = max( 1, min( 48, (int) ( $attributes['perPage'] ?? 12 ) ) );
$axismundi_media_collection_page = max( 1, (int) get_query_var( 'ax_media_page', 1 ) );
$axismundi_media_collection_size = sanitize_key( (string) ( $attributes['imageSize'] ?? 'medium_large' ) );
$axismundi_media_collection_show_dates = ! isset( $attributes['showDates'] ) || (bool) $attributes['showDates'];
$axismundi_media_collection_show_counts = ! isset( $attributes['showCounts'] ) || (bool) $attributes['showCounts'];
$axismundi_media_collection_show_up = ! isset( $attributes['showUp'] ) || (bool) $attributes['showUp'];
$axismundi_media_collection_tiles = array();

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
	$axismundi_media_collection_tiles[] = sprintf(
		'<li class="ax-media-collection__item is-up"><a href="%1$s"><span class="ax-media-collection__symbol" aria-hidden="true">←</span><span>%2$s</span></a></li>',
		esc_url( $axismundi_media_collection_up_url ),
		esc_html__( 'Up one level', 'axismundi-media-library' )
	);
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
			if ( ( $axismundi_media_collection_child_rank > $axismundi_media_collection_allowed_rank && ! axismundi_media_can_manage_folder( $axismundi_media_collection_child_id ) ) || $axismundi_media_collection_child_gate > 0 ) {
				continue;
			}
			$axismundi_media_collection_count = axismundi_media_folder_visible_count( $axismundi_media_collection_child_id, $axismundi_media_collection_allowed_rank );
			$axismundi_media_collection_tiles[] = sprintf(
				'<li class="ax-media-collection__item is-folder"><a href="%1$s"><span class="ax-media-collection__folder-art" aria-hidden="true"></span><span class="ax-media-collection__title">%2$s</span>%3$s</a></li>',
				esc_url( axismundi_media_folder_url( $axismundi_media_collection_owner, $axismundi_media_collection_child_id ) ),
				esc_html( $axismundi_media_collection_child->name ),
				$axismundi_media_collection_show_counts ? '<span class="ax-media-collection__count">' . esc_html( (string) $axismundi_media_collection_count ) . '</span>' : ''
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
foreach ( $axismundi_media_collection_query->posts as $axismundi_media_collection_attachment ) {
	$axismundi_media_collection_attachment_id = (int) $axismundi_media_collection_attachment->ID;
	$axismundi_media_collection_image = wp_get_attachment_image( $axismundi_media_collection_attachment_id, $axismundi_media_collection_size, true, array( 'loading' => 'lazy' ) );
	if ( ! $axismundi_media_collection_image ) {
		$axismundi_media_collection_image = '<span class="ax-media-collection__file-type">' . esc_html( strtoupper( axismundi_media_object_type( $axismundi_media_collection_attachment_id ) ) ) . '</span>';
	}
	$axismundi_media_collection_date = $axismundi_media_collection_show_dates
		? '<time datetime="' . esc_attr( get_the_date( DATE_W3C, $axismundi_media_collection_attachment_id ) ) . '">' . esc_html( get_the_date( '', $axismundi_media_collection_attachment_id ) ) . '</time>'
		: '';
	$axismundi_media_collection_tiles[] = sprintf(
		'<li class="ax-media-collection__item is-attachment"><article><a class="ax-media-collection__preview" href="%1$s">%2$s</a><h2><a href="%1$s">%3$s</a></h2>%4$s</article></li>',
		esc_url( axismundi_media_object_url( $axismundi_media_collection_attachment_id ) ),
		$axismundi_media_collection_image, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Core image markup or escaped fallback above.
		esc_html( get_the_title( $axismundi_media_collection_attachment_id ) ),
		$axismundi_media_collection_date // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Constructed from escaped date values.
	);
}

$axismundi_media_collection_wrapper = get_block_wrapper_attributes(
	array(
		'class' => 'ax-media-collection',
		'style' => '--ax-media-collection-columns:' . $axismundi_media_collection_columns,
	)
);
?>
<div <?php echo $axismundi_media_collection_wrapper; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Core-generated wrapper attributes. ?>>
	<?php if ( ! empty( $axismundi_media_collection_tiles ) ) : ?>
		<ul class="ax-media-collection__grid"><?php echo implode( '', $axismundi_media_collection_tiles ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Every tile component is escaped above. ?></ul>
	<?php else : ?>
		<p><?php esc_html_e( 'No media is available in this collection.', 'axismundi-media-library' ); ?></p>
	<?php endif; ?>
	<?php if ( $axismundi_media_collection_query->max_num_pages > 1 ) : ?>
		<nav class="ax-media-collection__pagination" aria-label="<?php esc_attr_e( 'Media pagination', 'axismundi-media-library' ); ?>">
			<?php
			echo wp_kses_post(
				paginate_links(
					array(
						'base'      => esc_url_raw( add_query_arg( 'ax_media_page', '%#%' ) ),
						'format'    => '',
						'current'   => $axismundi_media_collection_page,
						'total'     => (int) $axismundi_media_collection_query->max_num_pages,
						'prev_text' => __( 'Previous', 'axismundi-media-library' ),
						'next_text' => __( 'Next', 'axismundi-media-library' ),
					)
				)
			);
			?>
		</nav>
	<?php endif; ?>
</div>
<?php wp_reset_postdata(); ?>
