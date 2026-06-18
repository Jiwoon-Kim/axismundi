<?php
/**
 * Table of Contents block — server render.
 *
 * The list is built from the current post's headings via the shared
 * axismundi_toc_process() walk, so the anchors match the ids the post-content
 * filter injects. Emits the lab TOC vocabulary (toc-list / toc-h{2,3,4} /
 * is-current) so the theme's M3 skin and this plugin's scroll-spy couple to the
 * same hooks.
 *
 * Two placements share that list:
 *   - rail: a <nav> aside (the host wrapper owns sticky).
 *   - disclosure: a sticky native <details> for above-the-content placement; its
 *     <summary> can reflect the current section (view.js + summaryMode).
 *
 * @package AxismundiTableOfContents
 *
 * @var array<string,mixed> $attributes Block attributes.
 */

defined( 'ABSPATH' ) || exit;

$axismundi_toc_post = get_post();
if ( ! $axismundi_toc_post instanceof WP_Post ) {
	return;
}

$axismundi_toc_min = isset( $attributes['minLevel'] ) ? (int) $attributes['minLevel'] : 2;
$axismundi_toc_max = isset( $attributes['maxLevel'] ) ? (int) $attributes['maxLevel'] : 4;
if ( $axismundi_toc_min > $axismundi_toc_max ) {
	$axismundi_toc_swap = $axismundi_toc_min;
	$axismundi_toc_min  = $axismundi_toc_max;
	$axismundi_toc_max  = $axismundi_toc_swap;
}

$axismundi_toc_processed = axismundi_toc_process( $axismundi_toc_post->post_content );
$axismundi_toc_items     = array_filter(
	$axismundi_toc_processed['headings'],
	static function ( array $h ) use ( $axismundi_toc_min, $axismundi_toc_max ) : bool {
		return $h['level'] >= $axismundi_toc_min && $h['level'] <= $axismundi_toc_max;
	}
);

if ( empty( $axismundi_toc_items ) ) {
	return; // No headings in range — render nothing rather than an empty shell.
}

$axismundi_toc_ordered = ! empty( $attributes['ordered'] );
$axismundi_toc_show    = ! isset( $attributes['showTitle'] ) || ! empty( $attributes['showTitle'] );
$axismundi_toc_title   = ( isset( $attributes['title'] ) && '' !== $attributes['title'] )
	? (string) $attributes['title']
	: __( 'On this page', 'axismundi-table-of-contents' );
$axismundi_toc_variant = ( isset( $attributes['variant'] ) && 'disclosure' === $attributes['variant'] ) ? 'disclosure' : 'rail';
$axismundi_toc_open    = ! empty( $attributes['openByDefault'] );
$axismundi_toc_summary = isset( $attributes['summaryMode'] ) ? (string) $attributes['summaryMode'] : 'current';
if ( ! in_array( $axismundi_toc_summary, array( 'title', 'current', 'title-current' ), true ) ) {
	$axismundi_toc_summary = 'current';
}
$axismundi_toc_list = $axismundi_toc_ordered ? 'ol' : 'ul';

// Shared outline list. Built once; every value is escaped per field, so the
// assembled markup is echoed raw below.
ob_start();
?>
<<?php echo esc_html( $axismundi_toc_list ); ?> class="ax-toc__list toc-list">
	<?php foreach ( $axismundi_toc_items as $axismundi_toc_h ) : ?>
		<li class="ax-toc__item toc-h<?php echo (int) $axismundi_toc_h['level']; ?>">
			<a class="ax-toc__link" href="#<?php echo esc_attr( $axismundi_toc_h['id'] ); ?>"><?php echo esc_html( $axismundi_toc_h['text'] ); ?></a>
		</li>
	<?php endforeach; ?>
</<?php echo esc_html( $axismundi_toc_list ); ?>>
<?php
$axismundi_toc_list_html = ob_get_clean();

if ( 'disclosure' === $axismundi_toc_variant ) :
	$axismundi_toc_wrapper = get_block_wrapper_attributes(
		array(
			'class'             => 'ax-toc ax-toc--disclosure',
			'data-summary-mode' => $axismundi_toc_summary,
		)
	);
	?>
	<details <?php echo $axismundi_toc_wrapper; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><?php echo $axismundi_toc_open ? ' open' : ''; ?>>
		<summary class="ax-toc__summary">
			<span class="ax-toc__summary-label"><?php echo esc_html( $axismundi_toc_title ); ?></span>
			<span class="ax-toc__summary-current"></span>
		</summary>
		<nav class="ax-toc__panel" aria-label="<?php echo esc_attr( $axismundi_toc_title ); ?>">
			<?php echo $axismundi_toc_list_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
		</nav>
	</details>
	<?php
else :
	$axismundi_toc_wrapper = get_block_wrapper_attributes(
		array(
			'class'      => 'ax-toc ax-toc--rail',
			'aria-label' => $axismundi_toc_title,
		)
	);
	?>
	<nav <?php echo $axismundi_toc_wrapper; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
		<?php if ( $axismundi_toc_show ) : ?>
			<div class="ax-toc__title"><?php echo esc_html( $axismundi_toc_title ); ?></div>
		<?php endif; ?>
		<?php echo $axismundi_toc_list_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
	</nav>
	<?php
endif;
