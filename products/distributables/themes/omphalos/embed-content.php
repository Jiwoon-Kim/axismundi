<?php
/**
 * Omphalos embed content template part (the /embed/ Object Card markup).
 *
 * Theme-OWNED copy of core's wp-includes/theme-compat/embed-content.php so we can
 * attach the theme's typescale UTILITY classes (t-title-medium / t-body-medium /
 * t-body-small) directly on the markup instead of inferring them with selector
 * equivalents. The utilities are defined self-contained (with WP-core fallbacks) in
 * assets/styles/embed.css — no token CSS is enqueued into the iframe (route §10.3).
 *
 * Phase 2a = article/post variant only; the structure, hooks, and the
 * rectangular/square featured-image branch are kept identical to core so WP-to-WP /
 * web embed compatibility is preserved. attachment / activity variants are a later
 * phase (EMBED-TEMPLATE-ROUTE §10.1, §8 Phase 2b/2c).
 *
 * @package Omphalos
 */
?>
	<div <?php post_class( 'wp-embed' ); ?>>
		<?php
		$thumbnail_id = 0;

		if ( has_post_thumbnail() ) {
			$thumbnail_id = get_post_thumbnail_id();
		}

		if ( 'attachment' === get_post_type() && wp_attachment_is_image() ) {
			$thumbnail_id = get_the_ID();
		}

		/** This filter is documented in wp-includes/theme-compat/embed-content.php */
		$thumbnail_id = apply_filters( 'embed_thumbnail_id', $thumbnail_id );

		if ( $thumbnail_id ) {
			$aspect_ratio = 1;
			$measurements = array( 1, 1 );
			$image_size   = 'full'; // Fallback.

			$meta = wp_get_attachment_metadata( $thumbnail_id );
			if ( ! empty( $meta['sizes'] ) ) {
				foreach ( $meta['sizes'] as $size => $data ) {
					if ( $data['height'] > 0 && $data['width'] / $data['height'] > $aspect_ratio ) {
						$aspect_ratio = $data['width'] / $data['height'];
						$measurements = array( $data['width'], $data['height'] );
						$image_size   = $size;
					}
				}
			}

			/** This filter is documented in wp-includes/theme-compat/embed-content.php */
			$image_size = apply_filters( 'embed_thumbnail_image_size', $image_size, $thumbnail_id );

			$shape = $measurements[0] / $measurements[1] >= 1.75 ? 'rectangular' : 'square';

			/** This filter is documented in wp-includes/theme-compat/embed-content.php */
			$shape = apply_filters( 'embed_thumbnail_image_shape', $shape, $thumbnail_id );
		}

		if ( $thumbnail_id && 'rectangular' === $shape ) :
			?>
			<div class="wp-embed-featured-image rectangular">
				<a href="<?php the_permalink(); ?>" target="_top">
					<?php echo wp_get_attachment_image( $thumbnail_id, $image_size ); ?>
				</a>
			</div>
		<?php endif; ?>

		<p class="wp-embed-heading t-title-medium">
			<a href="<?php the_permalink(); ?>" target="_top">
				<?php the_title(); ?>
			</a>
		</p>

		<?php if ( $thumbnail_id && 'square' === $shape ) : ?>
			<div class="wp-embed-featured-image square">
				<a href="<?php the_permalink(); ?>" target="_top">
					<?php echo wp_get_attachment_image( $thumbnail_id, $image_size ); ?>
				</a>
			</div>
		<?php endif; ?>

		<div class="wp-embed-excerpt t-body-medium"><?php the_excerpt_embed(); ?></div>

		<?php
		/** This action is documented in wp-includes/theme-compat/embed-content.php */
		do_action( 'embed_content' );
		?>

		<div class="wp-embed-footer t-body-small">
			<?php the_embed_site_title(); ?>

			<div class="wp-embed-meta">
				<?php
				// Omphalos meta policy (Phase 2a-2): a POST is temporal, so it gets a
				// published date; a PAGE is a static object, so it does not. Everything
				// else (site title, comments, share via the core hook) stays shared.
				if ( 'post' === get_post_type() ) :
					?>
					<time class="wp-embed-meta-date" datetime="<?php echo esc_attr( get_the_date( DATE_W3C ) ); ?>"><?php echo esc_html( get_the_date() ); ?></time>
					<?php
				endif;
				/** This action is documented in wp-includes/theme-compat/embed-content.php */
				do_action( 'embed_content_meta' );
				?>
			</div>
		</div>
	</div>
<?php
