<?php
/**
 * Seed the VQA Theme specimen.
 *
 * Theme/template blocks (post-title, post-terms, post-featured-image, comments,
 * etc.) only render against a real post context, so this specimen is a POST — not
 * a page like the other VQA specimens — with a category, tags, and a featured
 * image assigned so the post-identity and post-meta sections actually render.
 * Archive/term-label blocks (query-title, term-name/description/count) still stay
 * blank here: they need an archive/term template, which a singular post cannot
 * provide.
 *
 * Run from the theme dir:
 *   npx wp-env run cli wp eval-file wp-content/themes/axismundi/scripts/seed-vqa-theme.php
 *
 * Dev-only — excluded from the distributable ZIP via .distignore.
 *
 * @package Axismundi
 */

ob_start();
include get_theme_file_path( 'patterns/vqa-theme.php' );
$content = ob_get_clean();

// Local VQA avatar context. WordPress admin email changes normally require a
// confirmation email; the seed owns this demo database, so update the user
// directly to make the Avatar block deterministic.
wp_update_user(
	array(
		'ID'         => 1,
		'user_email' => 'kimjiwoon75@gmail.com',
	)
);

$existing = get_page_by_path( 'vqa-theme', OBJECT, 'post' );
$postarr  = array(
	'post_type'    => 'post',
	'post_status'  => 'publish',
	'post_title'   => 'VQA Theme',
	'post_name'    => 'vqa-theme',
	'post_content' => $content,
);
if ( $existing ) {
	$postarr['ID'] = $existing->ID;
	$id            = wp_update_post( $postarr, true );
} else {
	$id = wp_insert_post( $postarr, true );
}
if ( is_wp_error( $id ) ) {
	echo 'ERROR: ' . $id->get_error_message() . "\n";
	return;
}

// Terms so post-terms (category + tags) renders in the specimen. Categories are
// hierarchical, so wp_set_post_categories needs the term ID, not the name.
$cat = get_term_by( 'name', 'Design Systems', 'category' );
if ( $cat ) {
	wp_set_post_categories( $id, array( $cat->term_id ) );
}
wp_set_post_tags( $id, array( 'm3', 'theme-blocks' ) );

// Featured image so post-featured-image renders (prefer the mogu placeholder).
$img = get_posts(
	array(
		'post_type'      => 'attachment',
		'post_mime_type' => 'image',
		'numberposts'    => 1,
		's'              => 'mogu',
	)
);
if ( ! $img ) {
	$img = get_posts(
		array(
			'post_type'      => 'attachment',
			'post_mime_type' => 'image',
			'numberposts'    => 1,
		)
	);
}
if ( $img ) {
	set_post_thumbnail( $id, $img[0]->ID );
}

echo 'VQA Theme post id=' . $id . ' url=' . get_permalink( $id ) . "\n";
