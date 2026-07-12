<?php
/**
 * Folder archive navigation render.
 *
 * @package AxismundiMediaLibrary
 */

defined( 'ABSPATH' ) || exit;

$axismundi_media_folder_id = (int) get_query_var( 'ax_media_folder' );
$axismundi_media_folder    = get_term( $axismundi_media_folder_id, AXISMUNDI_MEDIA_FOLDER_TAX );
if ( ! $axismundi_media_folder instanceof WP_Term ) {
	return;
}
$axismundi_media_owner_id = axismundi_media_folder_owner( $axismundi_media_folder_id );
$axismundi_media_children = get_terms(
	array(
		'taxonomy'   => AXISMUNDI_MEDIA_FOLDER_TAX,
		'hide_empty' => false,
		'parent'     => $axismundi_media_folder_id,
	)
);
$axismundi_media_links = array();
$axismundi_media_parent = get_term( (int) $axismundi_media_folder->parent, AXISMUNDI_MEDIA_FOLDER_TAX );
if ( $axismundi_media_parent instanceof WP_Term && ! axismundi_media_is_root_term( (int) $axismundi_media_parent->term_id ) ) {
	$axismundi_media_links[] = sprintf(
		'<li class="is-parent"><a href="%1$s">%2$s</a></li>',
		esc_url( axismundi_media_folder_url( $axismundi_media_owner_id, (int) $axismundi_media_parent->term_id ) ),
		esc_html__( 'Up one folder', 'axismundi-media-library' )
	);
} else {
	$axismundi_media_links[] = sprintf(
		'<li class="is-parent"><a href="%1$s">%2$s</a></li>',
		esc_url( axismundi_media_author_url( $axismundi_media_owner_id ) ),
		esc_html__( 'All media by this author', 'axismundi-media-library' )
	);
}
if ( ! is_wp_error( $axismundi_media_children ) ) {
	foreach ( $axismundi_media_children as $axismundi_media_child ) {
		$axismundi_media_rank = axismundi_media_folder_effective_tier_rank( (int) $axismundi_media_child->term_id );
		if ( 2 === $axismundi_media_rank && ! axismundi_media_can_manage_folder( (int) $axismundi_media_child->term_id ) ) {
			continue;
		}
		$axismundi_media_links[] = sprintf(
			'<li><a href="%1$s"><span>%2$s</span><span class="count">%3$d</span></a></li>',
			esc_url( axismundi_media_folder_url( $axismundi_media_owner_id, (int) $axismundi_media_child->term_id ) ),
			esc_html( $axismundi_media_child->name ),
			(int) axismundi_media_folder_recursive_count( (int) $axismundi_media_child->term_id )
		);
	}
}

printf(
	'<nav %1$s aria-label="%2$s"><ul>%3$s</ul></nav>',
	get_block_wrapper_attributes(), // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Core-generated block wrapper attributes.
	esc_attr__( 'Media folders', 'axismundi-media-library' ),
	implode( '', $axismundi_media_links ) // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Every link component is escaped above.
);
