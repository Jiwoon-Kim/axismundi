<?php
/**
 * Render an Actor feed Activity's object through the neutral view model.
 *
 * Activities owns which ledger entries appear and their verb framing; Object
 * Projections owns turning the object URI into a card. This default handler on
 * the Activities-owned filter resolves any local or cached-remote object, so a
 * boosted (Announce) object renders exactly like an authored (Create) one. A
 * Create additionally requires the object's author to equal the acting Actor, so
 * a Create can never advertise another Actor's object as the profile owner's.
 *
 * @package AxismundiObjectProjections
 */

defined( 'ABSPATH' ) || exit;

/**
 * Resolve one public Activity feed item's object into compact object HTML.
 *
 * @param string              $html          Existing product renderer output.
 * @param array<string,mixed> $item          Public-safe Activity feed item.
 * @param string              $card_template Card the feed wants repeated; empty means the bundled one.
 */
function axismundi_op_actor_feed_object_html( string $html, array $item, string $card_template = '' ) : string {
	if ( '' !== $html || ! function_exists( 'axismundi_op_render_object_by_uri' ) ) {
		return $html;
	}
	$object_uri = (string) ( $item['object_uri'] ?? '' );
	if ( '' === $object_uri ) {
		return $html;
	}
	// Actor timelines are viewer-specific action surfaces. The nested dynamic
	// buttons set the no-cache policy when they bind a logged-in Actor's state;
	// hashtag archives remain read-only through their separate renderer options.
	/*
	 * The feed owns clicks for the cards inside it. Cards here are appended, replaced, and
	 * filtered continuously, and DOM added after load is never hydrated, so a per-card
	 * interactive block would arrive dead the moment it was appended. Controls therefore render
	 * as presentation and the feed region dispatches their actions.
	 */
	$options = array( 'headingTag' => 'h3', 'interactions' => true, 'viewerScoped' => true, 'interactionOwner' => 'feed', 'cardTemplate' => $card_template );
	if ( 'Create' === (string) ( $item['type'] ?? '' ) ) {
		$options['expected_author'] = (string) ( $item['actor_uri'] ?? '' );
	}
	return axismundi_op_render_object_by_uri( $object_uri, $options );
}
add_filter( 'axismundi_act_actor_feed_object_html', 'axismundi_op_actor_feed_object_html', 20, 3 );

/**
 * Render a public Activity's uncached Object as an outbound reference.
 *
 * An inbound Announce usually carries an Object URI rather than an embedded
 * snapshot. Rendering must never fetch that URI during a profile request, but
 * hiding the public Announce entirely loses the Actor's activity history. This
 * fallback applies only when no local or cached source exists. Known remote
 * tombstones and non-public cached Objects remain hidden through the normal
 * renderer above.
 *
 * @param string              $html Existing fallback HTML.
 * @param array<string,mixed> $item Public-safe Activity feed item.
 */
function axismundi_op_actor_feed_missing_object_html( string $html, array $item ) : string {
	if ( '' !== $html || ! function_exists( 'axismundi_op_resolve_source_by_uri' ) ) {
		return $html;
	}
	// Only an Announce is evidence of a deliberately shared Object whose body
	// has not reached this site yet. A missing Create is a broken or stale
	// ledger reference; rendering it as an external card turns local drafts,
	// fixtures, and deleted posts into noisy timeline rows.
	if ( 'Announce' !== (string) ( $item['type'] ?? '' ) ) {
		return '';
	}
	$object_uri = isset( $item['object_uri'] ) && is_string( $item['object_uri'] ) ? trim( $item['object_uri'] ) : '';
	$parts      = wp_parse_url( $object_uri );
	if ( '' === $object_uri || ! is_array( $parts ) || empty( $parts['host'] ) || null !== axismundi_op_resolve_source_by_uri( $object_uri ) ) {
		return '';
	}
	// Older ledger rows predate inbound observation. Let their first profile view
	// self-heal through the same deferred path without fetching during render.
	if ( function_exists( 'axismundi_op_schedule_announced_object_fetch' ) ) {
		axismundi_op_schedule_announced_object_fetch( $object_uri );
	}
	$host = (string) $parts['host'];
	return '<article class="axismundi-object-card axismundi-object-card--external-reference">'
		. '<p class="axismundi-object-card__eyebrow">' . esc_html__( 'External object', 'axismundi-object-projections' ) . '</p>'
		. '<a class="axismundi-object-card__external-link" href="' . esc_url( $object_uri ) . '" rel="nofollow noopener noreferrer" target="_blank">'
		. '<span class="material-symbols-outlined" aria-hidden="true">open_in_new</span>'
		. esc_html( $host )
		. '<span class="screen-reader-text"> ' . esc_html__( 'Open original object', 'axismundi-object-projections' ) . '</span>'
		. '</a></article>';
}
add_filter( 'axismundi_act_actor_feed_missing_object_html', 'axismundi_op_actor_feed_missing_object_html', 20, 2 );

/**
 * Add public cache-only Objects as observed fallback rows for an Actor profile.
 *
 * A direct fetch, such as an uncached remote inReplyTo parent, is not evidence
 * that we received a Create Activity. It remains an Object observation and gets
 * a normal card without an Activity verb frame.
 *
 * @param array<int,array<string,mixed>> $items Existing observed feed items.
 * @param string[]                       $activity_object_uris URIs already framed by active Activity rows.
 * @return array<int,array<string,mixed>>
 */
function axismundi_op_actor_feed_observed_items( array $items, Axismundi_Actor $actor, array $activity_object_uris, int $limit ) : array {
	return array_merge( $items, axismundi_op_get_observed_actor_objects( $actor->get_uri(), $activity_object_uris, $limit ) );
}
add_filter( 'axismundi_act_actor_feed_observed_items', 'axismundi_op_actor_feed_observed_items', 20, 4 );

/**
 * Answer whether an Object the Activity only names is a reply.
 *
 * Object Projections owns the thread graph, so it can answer for an inbound Create that carried
 * a bare URI — the case the ledger payload cannot settle on its own. A cached remote payload is
 * consulted second: an object we hold a copy of states its own `inReplyTo`, and trusting that is
 * the same thing the card rendering already does.
 *
 * @param bool   $is_reply   Whether the entry is a reply.
 * @param string $object_uri Canonical object URI.
 * @return bool
 */
function axismundi_op_actor_feed_item_is_reply( bool $is_reply, string $object_uri ) : bool {
	if ( $is_reply || '' === $object_uri ) {
		return $is_reply;
	}
	if ( function_exists( 'axismundi_op_get_thread_parent_uri' ) && '' !== axismundi_op_get_thread_parent_uri( $object_uri ) ) {
		return true;
	}
	if ( function_exists( 'axismundi_op_remote_object_get' ) ) {
		$remote  = axismundi_op_remote_object_get( $object_uri, false );
		$payload = is_array( $remote ) ? (array) ( $remote['payload'] ?? array() ) : array();
		if ( ! empty( $payload['inReplyTo'] ) ) {
			return true;
		}
	}
	return false;
}
add_filter( 'axismundi_act_actor_feed_item_is_reply', 'axismundi_op_actor_feed_item_is_reply', 10, 2 );
