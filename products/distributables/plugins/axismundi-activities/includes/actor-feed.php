<?php
/**
 * Human-facing timeline for an Actor profile.
 *
 * The immutable Activity ledger remains authoritative. This is deliberately a
 * presentation adapter over its public-safe payloads, not a second object feed
 * or an archive of WordPress posts.
 *
 * @package AxismundiActivities
 */

defined( 'ABSPATH' ) || exit;

/**
 * Build one public-safe feed item descriptor from a Create or Announce row.
 *
 * The card content is not read here: Activities owns selection and verb framing,
 * and hands the object URI to whichever product renders it. The public audience
 * gate stays here so followers-only and mentioned-only rows never surface.
 */
function axismundi_act_actor_feed_item( Axismundi_Activity $activity ) : ?array {
	if ( ! axismundi_act_is_publicly_renderable( $activity ) ) {
		return null;
	}
	$payload = $activity->get_payload();
	unset( $payload['bto'], $payload['bcc'] );
	$type = $activity->get_type();
	if ( 'Create' !== $type && 'Announce' !== $type ) {
		return null;
	}
	$object_uri = axismundi_act_member_uri( $payload['object'] ?? null );
	if ( '' === $object_uri ) {
		$object_uri = (string) ( $activity->get_object_uri() ?? '' );
	}
	if ( '' === $object_uri ) {
		return null;
	}
	$published = $activity->get_published_at();

	return array(
		'id'         => $activity->get_uri(),
		'kind'       => 'activity',
		'type'       => $type,
		'actor_uri'  => $activity->get_actor_uri(),
		'object_uri' => $object_uri,
		'published'  => is_string( $published ) ? $published : '',
	);
}

/** Normalize one third-party observed Object fallback row. */
function axismundi_act_actor_feed_observed_item( $item, Axismundi_Actor $actor ) : ?array {
	if ( ! is_array( $item ) || 'observed_object' !== (string) ( $item['kind'] ?? '' ) || ! hash_equals( $actor->get_uri(), (string) ( $item['actor_uri'] ?? '' ) ) ) {
		return null;
	}
	$object_uri = axismundi_act_uri( $item['object_uri'] ?? '' );
	if ( '' === $object_uri ) {
		return null;
	}
	$published = is_scalar( $item['published'] ?? null ) ? (string) $item['published'] : '';
	return array(
		'id'         => 'observed:' . hash( 'sha256', $object_uri ),
		'kind'       => 'observed_object',
		'type'       => 'Object',
		'actor_uri'  => $actor->get_uri(),
		'object_uri' => $object_uri,
		'published'  => false === strtotime( $published ) ? '' : $published,
	);
}

/** Descending feed chronology with a deterministic identity tie-breaker. */
function axismundi_act_actor_feed_compare( array $left, array $right ) : int {
	$left_time  = '' !== (string) ( $left['published'] ?? '' ) ? (int) strtotime( (string) $left['published'] ) : 0;
	$right_time = '' !== (string) ( $right['published'] ?? '' ) ? (int) strtotime( (string) $right['published'] ) : 0;
	if ( $left_time !== $right_time ) {
		return $right_time <=> $left_time;
	}
	return strcmp( (string) ( $right['id'] ?? '' ), (string) ( $left['id'] ?? '' ) );
}

/** Public Activity feed items for one local or cached remote public Actor. */
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
	if ( ! function_exists( 'axismundi_actors_is_public_profile' )
		|| ! axismundi_actors_is_public_profile( $actor )
	) {
		return array();
	}
	$items = array();
	foreach ( axismundi_act_get_actor_feed( $actor->get_uri(), max( 1, min( 50, $limit ) ) ) as $activity ) {
		if ( ! $activity instanceof Axismundi_Activity ) {
			continue;
		}
		$item = axismundi_act_actor_feed_item( $activity );
		if ( is_array( $item ) ) {
			$items[] = $item;
		}
	}
	$activity_object_uris = array_values(
		array_unique(
			array_filter(
				array_map(
					static fn( array $item ) : string => (string) ( $item['object_uri'] ?? '' ),
					$items
				)
			)
		)
	);
	/**
	 * Allow Object Projections to include directly observed public Objects that
	 * have no Activity anchor, such as an uncached remote inReplyTo parent.
	 *
	 * @param array<int,array<string,mixed>> $observed Existing observed rows.
	 * @param string[]                       $activity_object_uris Object URIs already framed by an Activity.
	 */
	$observed = (array) apply_filters( 'axismundi_act_actor_feed_observed_items', array(), $actor, $activity_object_uris, $limit );
	foreach ( $observed as $item ) {
		$normalized = axismundi_act_actor_feed_observed_item( $item, $actor );
		if ( is_array( $normalized ) ) {
			$items[] = $normalized;
		}
	}
	usort( $items, 'axismundi_act_actor_feed_compare' );
	return array_slice( $items, 0, $limit );
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
		 * its own view model. Activities deliberately owns only ledger selection
		 * and verb framing, so it never reaches into Note or Object Projections
		 * directly. Object Projections registers the default handler.
		 *
		 * @param string               $html Empty by default.
		 * @param array<string,mixed>  $item Public-safe Activity feed item.
		 */
		$object_html = (string) apply_filters( 'axismundi_act_actor_feed_object_html', '', $item );
		if ( '' === $object_html ) {
			// A deleted, tombstoned, or otherwise unrenderable object hides its row.
			continue;
		}
		$frame = '';
		if ( 'Announce' === $item['type'] ) {
			$frame = '<p class="axismundi-activity-feed__boost"><span class="material-symbols-outlined" aria-hidden="true">sync</span> '
				. esc_html__( 'Boosted', 'axismundi-activities' ) . '</p>';
		}
		$cards[] = '<li class="axismundi-activity-feed__item axismundi-activity-feed__item--object axismundi-activity-feed__item--' . esc_attr( strtolower( $item['type'] ) ) . '">'
			. $frame
			. $object_html
			. '</li>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Frame is escaped above; the owning product owns and escapes its renderer output.
	}
	if ( empty( $cards ) ) {
		return '';
	}
	return '<section class="axismundi-activity-feed" aria-labelledby="axismundi-activity-feed-heading">'
		. '<h2 id="axismundi-activity-feed-heading" class="axismundi-activity-feed__heading">' . esc_html__( 'Timeline', 'axismundi-activities' ) . '</h2>'
		. '<ol class="axismundi-activity-feed__list">' . implode( '', $cards ) . '</ol>'
		. '</section>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Cards are escaped above.
}

/** Register the server-rendered Actor Activity feed block. */
function axismundi_act_register_actor_activity_feed_block() : void {
	register_block_type( dirname( __DIR__ ) . '/blocks/actor-activity-feed', array( 'render_callback' => 'axismundi_act_render_actor_activity_feed' ) );
}
add_action( 'init', 'axismundi_act_register_actor_activity_feed_block' );
