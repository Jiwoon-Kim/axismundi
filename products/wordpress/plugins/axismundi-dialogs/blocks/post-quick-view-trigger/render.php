<?php
/**
 * axismundi/post-quick-view-trigger — server render.
 *
 * A microblog-style comments action: a Material Symbols icon (comment when
 * comments are open, comments_disabled when closed) plus the comment count.
 * v0.1 links to the post's comments anchor; a later phase swaps the link for a
 * shared quick-view dialog trigger. Reads the post from Query Loop context
 * (postId) so it works per-row inside a Post Template, falling back to the
 * current loop post on single templates.
 *
 * The glyph relies on the theme's .material-symbols-outlined contract (icons.css);
 * without an Axismundi-family theme the ligature degrades to plain text.
 *
 * @package AxismundiDialogs
 */

defined( 'ABSPATH' ) || exit;

$axismundi_dialogs_qv_post_id = isset( $block->context['postId'] ) ? (int) $block->context['postId'] : 0;
if ( ! $axismundi_dialogs_qv_post_id ) {
	$axismundi_dialogs_qv_post_id = (int) get_the_ID();
}
if ( ! $axismundi_dialogs_qv_post_id ) {
	return;
}

$axismundi_dialogs_qv_display = ( 'count-and-label' === ( $attributes['displayMode'] ?? 'count' ) ) ? 'count-and-label' : 'count';
$axismundi_dialogs_qv_closed  = in_array( ( $attributes['closedBehavior'] ?? 'disabled' ), array( 'disabled', 'link', 'hidden' ), true )
	? $attributes['closedBehavior']
	: 'disabled';

$axismundi_dialogs_qv_open  = comments_open( $axismundi_dialogs_qv_post_id );
$axismundi_dialogs_qv_count = (int) get_comments_number( $axismundi_dialogs_qv_post_id );

if ( ! $axismundi_dialogs_qv_open && 'hidden' === $axismundi_dialogs_qv_closed ) {
	return;
}

$axismundi_dialogs_qv_icon = $axismundi_dialogs_qv_open ? 'comment' : 'comments_disabled';

$axismundi_dialogs_qv_count_text = number_format_i18n( $axismundi_dialogs_qv_count );
$axismundi_dialogs_qv_aria       = $axismundi_dialogs_qv_open
	/* translators: %s: number of comments. */
	? sprintf( _n( '%s comment', '%s comments', $axismundi_dialogs_qv_count, 'axismundi-dialogs' ), $axismundi_dialogs_qv_count_text )
	: __( 'Comments closed', 'axismundi-dialogs' );
$axismundi_dialogs_qv_label = ( 'count-and-label' === $axismundi_dialogs_qv_display )
	/* translators: %s: number of comments. */
	? sprintf( _n( '%s comment', '%s comments', $axismundi_dialogs_qv_count, 'axismundi-dialogs' ), $axismundi_dialogs_qv_count_text )
	: $axismundi_dialogs_qv_count_text;

$axismundi_dialogs_qv_inner = sprintf(
	'<span class="ax-comment-action__icon material-symbols-outlined notranslate" translate="no" aria-hidden="true">%1$s</span><span class="ax-comment-action__count">%2$s</span>',
	esc_html( $axismundi_dialogs_qv_icon ),
	esc_html( $axismundi_dialogs_qv_label )
);

$axismundi_dialogs_qv_as_link = $axismundi_dialogs_qv_open || 'link' === $axismundi_dialogs_qv_closed;

if ( $axismundi_dialogs_qv_as_link ) {
	$axismundi_dialogs_qv_href = get_comments_link( $axismundi_dialogs_qv_post_id );

	// Progressive enhancement: point at the singleton hub and hand it the post.
	// When the hub (and its runtime) is absent the action bails without
	// preventDefault(), so the anchor navigates to #comments as in v0.1.
	$axismundi_dialogs_qv_wrapper = get_block_wrapper_attributes(
		array(
			'class'               => 'ax-comment-action ax-post-quick-view-trigger' . ( $axismundi_dialogs_qv_open ? '' : ' is-closed' ),
			'href'                => esc_url( $axismundi_dialogs_qv_href ),
			'aria-label'          => $axismundi_dialogs_qv_aria,
			'aria-haspopup'       => 'dialog',
			'aria-controls'       => 'ax-post-quick-view',
			'data-wp-interactive' => 'axismundi/dialog',
			'data-wp-on--click'   => 'actions.openPostQuickView',
		)
	);
	$axismundi_dialogs_qv_context = wp_interactivity_data_wp_context(
		array(
			'postId'    => $axismundi_dialogs_qv_post_id,
			'href'      => $axismundi_dialogs_qv_href,
			'fetchUrl'  => rest_url( 'axismundi-dialogs/v1/post-quick-view/' . $axismundi_dialogs_qv_post_id ),
			'permalink' => get_permalink( $axismundi_dialogs_qv_post_id ),
		)
	);
	printf( '<a %1$s %2$s>%3$s</a>', $axismundi_dialogs_qv_wrapper, $axismundi_dialogs_qv_context, $axismundi_dialogs_qv_inner ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	return;
}

$axismundi_dialogs_qv_wrapper = get_block_wrapper_attributes(
	array(
		'class'      => 'ax-comment-action is-closed is-disabled',
		'aria-label' => $axismundi_dialogs_qv_aria,
	)
);
printf( '<span %1$s>%2$s</span>', $axismundi_dialogs_qv_wrapper, $axismundi_dialogs_qv_inner ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
