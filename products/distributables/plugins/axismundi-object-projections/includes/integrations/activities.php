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
 * @param string              $html Existing product renderer output.
 * @param array<string,mixed> $item Public-safe Activity feed item.
 */
function axismundi_op_actor_feed_object_html( string $html, array $item ) : string {
	if ( '' !== $html || ! function_exists( 'axismundi_op_render_object_by_uri' ) ) {
		return $html;
	}
	$object_uri = (string) ( $item['object_uri'] ?? '' );
	if ( '' === $object_uri ) {
		return $html;
	}
	$options = array( 'headingTag' => 'h3', 'interactions' => false );
	if ( 'Create' === (string) ( $item['type'] ?? '' ) ) {
		$options['expected_author'] = (string) ( $item['actor_uri'] ?? '' );
	}
	return axismundi_op_render_object_by_uri( $object_uri, $options );
}
add_filter( 'axismundi_act_actor_feed_object_html', 'axismundi_op_actor_feed_object_html', 20, 2 );

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
