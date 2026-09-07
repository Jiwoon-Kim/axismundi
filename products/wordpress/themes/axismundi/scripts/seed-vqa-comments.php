<?php
/**
 * Seed the "VQA Theme Comments" specimen post for the comments lane VQA.
 *
 * Creates (or refreshes) a published post with comment_status open and a fixed
 * set of threaded comments (nested replies to depth 3, multiple threads) so the
 * core/comments → core/comment-template render — list, nesting, author/date/
 * content, reply/edit links, and the comment form — can be verified on the
 * single template before the M3 skin is layered on.
 *
 * Run: wp eval-file scripts/seed-vqa-comments.php   (idempotent)
 *
 * @package Axismundi
 */

$slug  = 'vqa-theme-comments';
$title = 'VQA Theme Comments';

$content = <<<HTML
<!-- wp:paragraph -->
<p>This specimen exercises the comments lane: the comments area, the repeating comment template, threaded replies, and the comment form. The body is intentionally short — the point is everything below the article.</p>
<!-- /wp:paragraph -->

<!-- wp:heading -->
<h2 class="wp-block-heading">A short section</h2>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>Comment rendering should map cleanly onto Material 3: each comment reads as a list item with a leading avatar, the reply and edit links as text buttons, and the form as an outlined text field with a button.</p>
<!-- /wp:paragraph -->
HTML;

// Find or create the post.
$existing = get_page_by_path( $slug, OBJECT, 'post' );
$postarr  = array(
	'post_title'     => $title,
	'post_name'      => $slug,
	'post_content'   => $content,
	'post_status'    => 'publish',
	'post_type'      => 'post',
	'comment_status' => 'open',
);
if ( $existing ) {
	$postarr['ID'] = $existing->ID;
	$post_id       = wp_update_post( $postarr );
} else {
	$post_id = wp_insert_post( $postarr );
}
if ( is_wp_error( $post_id ) || ! $post_id ) {
	echo "FAILED to create post\n";
	return;
}
echo "post_id={$post_id} (/?p={$post_id})\n";

// Clear existing comments so re-runs don't pile up.
foreach ( get_comments( array( 'post_id' => $post_id, 'status' => 'any' ) ) as $c ) {
	wp_delete_comment( $c->comment_ID, true );
}

// Insert threaded comments. Each row: [author, email, content, parent-key].
$now    = current_time( 'timestamp' );
$rows   = array(
	'c1' => array( 'Jiwoon Kim', 'jiwoon@example.com', 'First — this is a top-level comment to check the baseline row: avatar, name, date, and a single paragraph of content.', null ),
	'c2' => array( 'Mina Park', 'mina@example.com', 'A reply to the first comment. Nesting should indent this one row under its parent.', 'c1' ),
	'c3' => array( 'Jiwoon Kim', 'jiwoon@example.com', 'And a reply to the reply — depth three. This is where nested inset and the threading line matter.', 'c2' ),
	'c4' => array( 'Alex Rivera', 'alex@example.com', "A second top-level thread, with a slightly longer body so wrapping and line-height read correctly. Vestibulum id ligula porta felis euismod semper. Cras mattis consectetur purus sit amet fermentum.", null ),
	'c5' => array( 'Sam Lee', 'sam@example.com', 'Short reply.', 'c4' ),
	'c6' => array( 'Dana Wu', 'dana@example.com', 'A third standalone top-level comment to give the list some length.', null ),
);

$ids = array();
$i   = 0;
foreach ( $rows as $key => $row ) {
	list( $author, $email, $text, $parent_key ) = $row;
	$cid = wp_insert_comment(
		array(
			'comment_post_ID'      => $post_id,
			'comment_author'       => $author,
			'comment_author_email' => $email,
			'comment_content'      => $text,
			'comment_parent'       => $parent_key ? ( $ids[ $parent_key ] ?? 0 ) : 0,
			'comment_approved'     => 1,
			'comment_date'         => date( 'Y-m-d H:i:s', $now - ( ( count( $rows ) - $i ) * 3600 ) ),
		)
	);
	$ids[ $key ] = $cid;
	$i++;
}
echo 'inserted ' . count( $ids ) . " comments (nested to depth 3)\n";
