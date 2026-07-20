<?php
/**
 * Human-facing Activity ledger feed for an Actor profile.
 *
 * The immutable Activity ledger remains authoritative. This is deliberately a
 * presentation adapter over its public-safe payloads, not a second object feed
 * or an archive of WordPress posts.
 *
 * @package AxismundiActivities
 */

defined( 'ABSPATH' ) || exit;

/** Build one public-safe, human-facing feed item from a ledger row. */
function axismundi_act_actor_feed_item( Axismundi_Activity $activity ) : ?array {
	$payload = axismundi_act_public_payload( $activity );
	if ( ! is_array( $payload ) ) {
		return null;
	}
	$type       = $activity->get_type();
	$object     = $payload['object'] ?? null;
	$object_uri = axismundi_act_member_uri( $object );
	$object_map = is_array( $object ) && ! array_is_list( $object ) ? $object : array();
	$content    = isset( $object_map['content'] ) ? wp_kses_post( (string) $object_map['content'] ) : '';
	$title      = isset( $object_map['name'] ) ? trim( wp_strip_all_tags( (string) $object_map['name'] ) ) : '';
	$url        = axismundi_act_member_uri( $object_map['url'] ?? $object_uri );
	$published  = $activity->get_published_at();

	$verbs = array(
		'Create'   => __( 'Published a post', 'axismundi-activities' ),
		'Update'   => __( 'Updated a post', 'axismundi-activities' ),
		'Delete'   => __( 'Deleted a post', 'axismundi-activities' ),
		'Announce' => __( 'Boosted a post', 'axismundi-activities' ),
		'Like'     => __( 'Liked a post', 'axismundi-activities' ),
		'Undo'     => __( 'Undid an activity', 'axismundi-activities' ),
	);

	return array(
		'id'          => $activity->get_uri(),
		'type'        => $type,
		'actor_uri'   => $activity->get_actor_uri(),
		'label'       => $verbs[ $type ] ?? sprintf(
			/* translators: %s: ActivityStreams activity type. */
			__( 'Performed %s', 'axismundi-activities' ),
			$type
		),
		'object_uri'  => $object_uri,
		'url'         => $url,
		'title'       => $title,
		'content_html' => $content,
		'published'   => is_string( $published ) ? $published : '',
	);
}

/** Public Activity feed items for one local public Actor. */
function axismundi_act_actor_feed_items( Axismundi_Actor $actor, int $limit = 20 ) : array {
	// A profile may be rendered from a long-lived object in an admin preview.
	// Re-resolve the identity before applying the public boundary so a status
	// change cannot leave a stale Actor object advertising a public feed.
	if ( function_exists( 'axismundi_actors_get_by_uri' ) ) {
		$current = axismundi_actors_get_by_uri( $actor->get_uri() );
		if ( ! $current instanceof Axismundi_Actor ) {
			return array();
		}
		$actor = $current;
	}
	if ( ! $actor->is_local()
		|| ! function_exists( 'axismundi_actors_is_public_profile' )
		|| ! axismundi_actors_is_public_profile( $actor )
	) {
		return array();
	}
	$items = array();
	foreach ( axismundi_act_get_by_actor( $actor->get_uri(), max( 1, min( 50, $limit ) ) ) as $activity ) {
		if ( ! $activity instanceof Axismundi_Activity ) {
			continue;
		}
		$item = axismundi_act_actor_feed_item( $activity );
		if ( is_array( $item ) ) {
			$items[] = $item;
		}
	}
	return $items;
}

/** Render the current Actor's public Activity feed. */
function axismundi_act_render_actor_activity_feed() : string {
	if ( ! function_exists( 'axismundi_actors_current_actor' ) ) {
		return '';
	}
	$actor = axismundi_actors_current_actor();
	if ( ! $actor instanceof Axismundi_Actor ) {
		return '';
	}
	$items = axismundi_act_actor_feed_items( $actor );
	if ( empty( $items ) ) {
		return '';
	}
	$cards = array();
	foreach ( $items as $item ) {
		/**
		 * Let an object-owning product render a public activity's object through
		 * its own view model. Activities deliberately owns only ledger selection,
		 * so it never reaches into Note or Object Projections directly.
		 *
		 * @param string               $html Empty by default.
		 * @param array<string,mixed>  $item Public-safe Activity feed item.
		 */
		$object_html = (string) apply_filters( 'axismundi_act_actor_feed_object_html', '', $item );
		if ( '' !== $object_html ) {
			$cards[] = '<li class="axismundi-activity-feed__item axismundi-activity-feed__item--object axismundi-activity-feed__item--' . esc_attr( strtolower( $item['type'] ) ) . '">'
				. $object_html
				. '</li>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- The owning product owns and escapes its renderer output.
			continue;
		}
		$meta = '';
		if ( '' !== $item['published'] ) {
			$timestamp = strtotime( $item['published'] );
			if ( false !== $timestamp ) {
				$meta = '<time datetime="' . esc_attr( gmdate( 'c', $timestamp ) ) . '">' . esc_html( wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $timestamp ) ) . '</time>';
			}
		}
		$target = '' !== $item['url'] ? '<a class="axismundi-activity-feed__target" href="' . esc_url( $item['url'] ) . '">' . esc_html__( 'View post', 'axismundi-activities' ) . '</a>' : '';
		$title  = '' !== $item['title'] ? '<h3 class="axismundi-activity-feed__title">' . esc_html( $item['title'] ) . '</h3>' : '';
		$body   = '' !== $item['content_html'] ? '<div class="axismundi-activity-feed__content">' . wp_kses_post( $item['content_html'] ) . '</div>' : '';
		$cards[] = '<li class="axismundi-activity-feed__item axismundi-activity-feed__item--' . esc_attr( strtolower( $item['type'] ) ) . '">'
			. '<header class="axismundi-activity-feed__header"><span class="axismundi-activity-feed__verb">' . esc_html( $item['label'] ) . '</span>' . $meta . '</header>'
			. $title . $body . $target
			. '</li>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Every interpolated component is escaped above.
	}
	return '<section class="axismundi-activity-feed" aria-labelledby="axismundi-activity-feed-heading">'
		. '<h2 id="axismundi-activity-feed-heading" class="axismundi-activity-feed__heading">' . esc_html__( 'Activity', 'axismundi-activities' ) . '</h2>'
		. '<ol class="axismundi-activity-feed__list">' . implode( '', $cards ) . '</ol>'
		. '</section>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Cards are escaped above.
}

/** Register the server-rendered Actor Activity feed block. */
function axismundi_act_register_actor_activity_feed_block() : void {
	register_block_type( dirname( __DIR__ ) . '/blocks/actor-activity-feed', array( 'render_callback' => 'axismundi_act_render_actor_activity_feed' ) );
}
add_action( 'init', 'axismundi_act_register_actor_activity_feed_block' );
