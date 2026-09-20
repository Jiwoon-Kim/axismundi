<?php
/**
 * Baseline media members of a core post (dev-only).
 *
 * FEP-b2b8 asks that media embedded in an Article's content also be listed in `attachment`,
 * so a reader can fetch it without parsing markup. This plugin answers that from what the
 * author placed -- the featured image and the attachment ids the editor stores on its media
 * blocks -- and from nothing else.
 *
 * The rule these checks defend: only media this site holds is published. A hotlinked image
 * has no attachment id, so its type and dimensions cannot be stated truthfully, and a
 * descriptor missing those is read as broken media by Mastodon and ignored by Misskey.
 *
 * @package AxismundiObjectProjections
 */

defined( 'ABSPATH' ) || exit( 1 );

$ax_mb_results = array();
$ax_mb_posts   = array();

/** @param bool[] $results Results. */
function ax_mb_assert( array &$results, string $label, bool $condition ) : void {
	$results[] = $condition;
	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI test output.
	printf( "[%s] %s\n", $condition ? 'PASS' : 'FAIL', $label );
}

try {
	$ax_mb_media = get_posts( array( 'post_type' => 'attachment', 'numberposts' => 3, 'post_status' => 'inherit' ) );
	if ( count( $ax_mb_media ) < 2 ) {
		ax_mb_assert( $ax_mb_results, 'the site holds at least two attachments to publish', false );
		printf( "\n%d/%d passed\n", 0, 1 );
		return;
	}
	$ax_mb_ids  = wp_list_pluck( $ax_mb_media, 'ID' );
	$ax_mb_user = get_users( array( 'role' => 'administrator', 'number' => 1 ) );
	$ax_mb_user = $ax_mb_user ? (int) $ax_mb_user[0]->ID : 0;

	$ax_mb_content = '<!-- wp:image {"id":' . (int) $ax_mb_ids[0] . '} --><figure><img src="local" /></figure><!-- /wp:image -->'
		. "\n" . '<!-- wp:paragraph --><p>text <img src="https://example.com/hotlink.jpg" /> more</p><!-- /wp:paragraph -->';
	$ax_mb_post_id = wp_insert_post(
		array(
			'post_title'   => 'Baseline media fixture',
			'post_content' => $ax_mb_content,
			'post_status'  => 'publish',
			'post_author'  => $ax_mb_user,
		)
	);
	$ax_mb_posts[] = $ax_mb_post_id;
	set_post_thumbnail( $ax_mb_post_id, (int) $ax_mb_ids[1] );
	$ax_mb_post = get_post( $ax_mb_post_id );

	$ax_mb_collected = axismundi_op_post_media_ids( $ax_mb_post );
	ax_mb_assert(
		$ax_mb_results,
		'the featured image leads, and the media block follows in reading order',
		array( (int) $ax_mb_ids[1], (int) $ax_mb_ids[0] ) === $ax_mb_collected
	);

	$ax_mb_members = axismundi_op_post_media_members( $ax_mb_post );
	ax_mb_assert(
		$ax_mb_results,
		'a hotlinked image is not published as an attachment',
		count( $ax_mb_members['attachment'] ) === count( $ax_mb_collected )
	);
	$ax_mb_first = $ax_mb_members['attachment'][0] ?? array();
	ax_mb_assert(
		$ax_mb_results,
		'each descriptor states a fetchable file, its media type and its dimensions',
		! empty( $ax_mb_first['url'] ) && ! empty( $ax_mb_first['mediaType'] )
			&& ( (int) ( $ax_mb_first['width'] ?? 0 ) > 0 || ! str_starts_with( (string) $ax_mb_first['mediaType'], 'image/' ) )
	);
	ax_mb_assert(
		$ax_mb_results,
		'an embedded descriptor is anonymous, because it belongs to the document carrying it',
		! isset( $ax_mb_first['id'] )
	);
	ax_mb_assert(
		$ax_mb_results,
		'the featured image is also published as the representative image',
		is_array( $ax_mb_members['image'] ) && ! empty( $ax_mb_members['image']['url'] )
	);

	// A long article is a long article. Publishing fewer media than it holds would understate
	// the document; how many to show or pre-fetch is the receiving server's own policy.
	$ax_mb_many = array_fill( 0, 40, (int) $ax_mb_ids[0] );
	$ax_mb_many[0] = (int) $ax_mb_ids[1];
	$ax_mb_filter  = static fn() : array => $ax_mb_many;
	add_filter( 'axismundi_op_post_media_ids', $ax_mb_filter );
	$ax_mb_unique = axismundi_op_post_media_ids( $ax_mb_post );
	remove_filter( 'axismundi_op_post_media_ids', $ax_mb_filter );
	ax_mb_assert(
		$ax_mb_results,
		'the list is not capped, and repeats collapse to one entry each',
		array( (int) $ax_mb_ids[1], (int) $ax_mb_ids[0] ) === $ax_mb_unique
	);

	$ax_mb_trim = static fn( array $ids ) : array => array_slice( $ids, 0, 1 );
	add_filter( 'axismundi_op_post_media_ids', $ax_mb_trim );
	$ax_mb_trimmed = axismundi_op_post_media_ids( $ax_mb_post );
	remove_filter( 'axismundi_op_post_media_ids', $ax_mb_trim );
	ax_mb_assert(
		$ax_mb_results,
		'a product that records what a post uses can replace the list',
		array( (int) $ax_mb_ids[1] ) === $ax_mb_trimmed
	);
} finally {
	foreach ( $ax_mb_posts as $ax_mb_post_id ) {
		if ( $ax_mb_post_id && ! is_wp_error( $ax_mb_post_id ) ) {
			wp_delete_post( (int) $ax_mb_post_id, true );
		}
	}
}

printf( "\n%d/%d passed\n", count( array_filter( $ax_mb_results ) ), count( $ax_mb_results ) );
