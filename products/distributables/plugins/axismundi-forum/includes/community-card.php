<?php
/**
 * Community card: the Group a Topic belongs to, shown beside the Topic itself.
 *
 * A Topic page is not the community page — the Group Actor profile is — but a reader who
 * arrives at a single Topic still has to be able to see which community they are in, follow
 * it, and leave for it. Lemmy solves this with a community panel next to the post, and this
 * is that panel.
 *
 * Every value is read from Actors at render time. Forum stores the Group identity and nothing
 * about the Group: no cached name, avatar, or summary. That boundary is the whole reason the
 * Group Actor is the community rather than a record Forum keeps beside one, and a card that
 * copied those fields would quietly become a second source of truth for them.
 *
 * @package AxismundiForum
 */

defined( 'ABSPATH' ) || exit;

/**
 * The community Group this request is about, if any.
 *
 * A Topic reaches its Group two ways: an admitted local Topic through its Forum entry, and an
 * outbound Topic through the remote Group it was submitted to. Both are answered here so the
 * card works on a Topic that was written for someone else's community.
 *
 * @return Axismundi_Actor|null
 */
function axismundi_forum_card_group() : ?Axismundi_Actor {
	$group_identity_id = axismundi_forum_context_group_id();
	if ( $group_identity_id > 0 ) {
		return axismundi_forum_get_community_group( $group_identity_id );
	}
	if ( ! is_singular( AXISMUNDI_FORUM_TOPIC_POST_TYPE ) ) {
		return null;
	}
	$topic = get_queried_object();
	if ( ! $topic instanceof WP_Post ) {
		return null;
	}
	$entry = axismundi_forum_get_topic_entry( $topic->ID );
	if ( is_array( $entry ) ) {
		return axismundi_forum_get_community_group( (int) $entry['group_identity_id'] );
	}
	return axismundi_forum_get_remote_topic_group( $topic );
}

/**
 * Render the community card for the Group this page's Topic belongs to.
 *
 * @param array $attributes Block attributes.
 * @return string
 */
function axismundi_forum_render_community_card_block( array $attributes = array() ) : string {
	$group = axismundi_forum_card_group();
	if ( ! $group instanceof Axismundi_Actor || 'tombstone' === $group->get_status() ) {
		return '';
	}
	$name    = $group->get_display_name();
	$name    = '' !== $name ? $name : $group->get_preferred_username();
	$handle  = function_exists( 'axismundi_actors_mention_handle' ) ? axismundi_actors_mention_handle( $group ) : '@' . $group->get_preferred_username();
	$profile = $group->get_profile_url();
	$avatar  = function_exists( 'axismundi_actors_avatar_url' ) ? (string) axismundi_actors_avatar_url( $group ) : '';
	// Actors owns the localized text resolution, including its language fallbacks.
	$summary = function_exists( 'axismundi_actors_resolve_text' ) ? (string) axismundi_actors_resolve_text( $group, 'summary' ) : '';

	$parts = array();
	if ( '' !== $avatar ) {
		$parts[] = '<img class="axismundi-forum-community-card__avatar" src="' . esc_url( $avatar ) . '" alt="" width="64" height="64" loading="lazy" decoding="async" />';
	}
	$title = '' !== $profile
		? '<a href="' . esc_url( $profile ) . '">' . esc_html( $name ) . '</a>'
		: esc_html( $name );
	$parts[] = '<p class="axismundi-forum-community-card__name">' . $title . '</p>';
	$parts[] = '<p class="axismundi-forum-community-card__handle">' . esc_html( $handle ) . '</p>';
	if ( '' !== $summary ) {
		$parts[] = '<div class="axismundi-forum-community-card__summary">' . wp_kses_post( $summary ) . '</div>';
	}
	if ( ! empty( $attributes['showMemberCount'] ) && $group->is_local() ) {
		$parts[] = '<p class="axismundi-forum-community-card__members">'
			. esc_html( sprintf( /* translators: %d: member count. */ _n( '%d member', '%d members', axismundi_forum_count_memberships( $group->get_identity_id() ), 'axismundi-forum' ), axismundi_forum_count_memberships( $group->get_identity_id() ) ) )
			. '</p>';
	}
	/*
	 * The Follow control is Activities' block, rendered through the same seam a profile uses.
	 * Forum does not reimplement Follow state here; a second implementation would be a second
	 * place for "are you a member" to be wrong.
	 */
	$follow = do_blocks( '<!-- wp:axismundi/follow-button {"actorUri":"' . esc_attr( $group->get_uri() ) . '"} /-->' );
	if ( '' !== trim( wp_strip_all_tags( $follow ) ) || false !== strpos( $follow, '<button' ) ) {
		$parts[] = '<div class="axismundi-forum-community-card__follow">' . $follow . '</div>';
	}

	// Callable outside a block render stack, where get_block_wrapper_attributes() would warn.
	$wrapper = null === WP_Block_Supports::$block_to_render
		? 'class="axismundi-forum-community-card"'
		: get_block_wrapper_attributes( array( 'class' => 'axismundi-forum-community-card' ) );
	return '<aside ' . $wrapper . '>'
		. implode( '', $parts )
		. '</aside>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Parts escaped above; Follow is a rendered block.
}

/** Register the Community Card block from its server-owned metadata. */
function axismundi_forum_register_community_card_block() : void {
	register_block_type( dirname( __DIR__ ) . '/blocks/community-card' );
}
add_action( 'init', 'axismundi_forum_register_community_card_block', 20 );
