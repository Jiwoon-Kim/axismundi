<?php
/**
 * A Calendar as an ActivityStreams collection.
 *
 * This is the address a peer subscribes to and the one FEP-400e names as `target` when it asks to
 * put something on a calendar. Three things about it are load-bearing:
 *
 * `attributedTo` is the Calendar's authority -- the Actor whose collection this is -- and never the
 * Actor who wrote any particular Event. Each Event keeps its own `attributedTo`, which is whoever
 * published it, and the two answer different questions: whose collection, and whose work. Collapsing
 * them here would undo the separation the Event slice exists for, and on the wire it would make the
 * calendar's owner the party a reply is addressed to.
 *
 * The items are what this calendar *publishes*, which is narrower than what it shows. An Event that
 * appears on somebody's calendar because they were invited to it is filed on the host's calendar and
 * belongs in the host's collection; putting it here as well would publish another Actor's work under
 * this collection and, worse, tell a subscriber it lives at two addresses.
 *
 * And every item passes both gates. A calendar being public says a stranger may read the collection;
 * it does not say every Event on it is public, and `event_listable()` is the one place that answers
 * both halves, so this asks it rather than re-deriving a second opinion.
 *
 * @package AxismundiCalendar
 */

defined( 'ABSPATH' ) || exit;

/** Items per page. Small enough that a busy calendar pages rather than answering with everything. */
const AXISMUNDI_CAL_COLLECTION_PAGE_SIZE = 50;

/**
 * One Calendar, wrapped so the projection registry can recognise it.
 *
 * A plain row would be an array, and an array is what every other source already is.
 */
final class Axismundi_Cal_Collection {

	/** @var array<string,mixed> */
	public array $calendar;

	/** @var int */
	public int $page;

	/**
	 * @param array<string,mixed> $calendar Calendar row.
	 * @param int                 $page     1-based page.
	 */
	public function __construct( array $calendar, int $page = 1 ) {
		$this->calendar = $calendar;
		$this->page     = max( 1, $page );
	}
}

/**
 * The canonical collection address of one Calendar.
 *
 * An Actor's own calendar is addressed by the handle its Actor already promises; anything else gets
 * an opaque UUID, because a Calendar somebody made can be renamed, re-shared and handed to a
 * different owner, and none of those may move the address a subscriber holds.
 *
 * @param array<string,mixed> $calendar Calendar row.
 * @return string
 */
function axismundi_cal_collection_uri_for( array $calendar ) : string {
	if ( 'local' !== (string) ( $calendar['kind'] ?? '' ) ) {
		// A subscribed mirror is somebody else's collection. We do not republish it under our own id.
		return '';
	}
	$slug = (string) ( $calendar['slug'] ?? '' );
	if ( '' !== axismundi_cal_calendar_slug_handle( $slug ) ) {
		return home_url( '/calendar/' . $slug );
	}
	$uuid = (string) ( $calendar['uuid'] ?? '' );
	return '' === $uuid ? '' : home_url( '/calendar/c/' . $uuid );
}

/** @param mixed $source Source. @return string */
function axismundi_cal_collection_uri( $source ) : string {
	return $source instanceof Axismundi_Cal_Collection ? axismundi_cal_collection_uri_for( $source->calendar ) : '';
}

/**
 * Whether this collection may be served to anybody at all.
 *
 * Public means public: an anonymous reader. A calendar shared with three people is not published, and
 * serving it here because the person asking happens to hold access would make the collection's
 * meaning depend on who fetched it -- which is not a thing a cache or a peer can reason about.
 *
 * @param mixed $source Source.
 * @return bool
 */
function axismundi_cal_collection_visible( $source ) : bool {
	if ( ! $source instanceof Axismundi_Cal_Collection ) {
		return false;
	}
	$id = (int) ( $source->calendar['id'] ?? 0 );
	return '' !== axismundi_cal_collection_uri_for( $source->calendar )
		&& '' !== axismundi_cal_calendar_authority( $id )
		&& axismundi_cal_calendar_is_listable( $id );
}

/**
 * The Events one Calendar publishes, in a fixed order.
 *
 * Ordered by when they start and then by their own URI. The second half is not decoration: two
 * Events starting at the same instant would otherwise come back in whatever order the database felt
 * like, and a collection whose page boundaries move between two identical requests drops items for
 * anybody reading it a page at a time.
 *
 * @param int $calendar_id Calendar id.
 * @return array<int,array{uri:string,start:string}>
 */
function axismundi_cal_collection_items( int $calendar_id ) : array {
	global $wpdb;
	if ( $calendar_id <= 0 || ! axismundi_cal_ready() ) {
		return array();
	}
	$schedules = axismundi_cal_schedules_table();
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- indexed lookup in this plugin's own table.
	$rows = (array) $wpdb->get_results(
		$wpdb->prepare( "SELECT event_post_id, dtstart_local, timezone FROM {$schedules} WHERE calendar_id = %d", $calendar_id ),
		ARRAY_A
	);

	$items = array();
	foreach ( $rows as $row ) {
		$post = get_post( (int) $row['event_post_id'] );
		if ( ! $post instanceof WP_Post || ! axismundi_cal_event_listable( $post ) ) {
			continue;
		}
		$uri = axismundi_cal_event_object_uri( $post );
		if ( '' === $uri ) {
			continue;
		}
		$items[] = array(
			'uri'   => $uri,
			'start' => axismundi_cal_to_utc( (string) $row['dtstart_local'], (string) $row['timezone'] ),
		);
	}
	usort(
		$items,
		static function ( array $a, array $b ) : int {
			$by_start = strcmp( (string) $a['start'], (string) $b['start'] );
			return 0 !== $by_start ? $by_start : strcmp( (string) $a['uri'], (string) $b['uri'] );
		}
	);
	return $items;
}

/**
 * The collection document.
 *
 * @param mixed $source Source.
 * @return array<string,mixed>|WP_Error
 */
function axismundi_cal_collection_transform( $source ) {
	if ( ! $source instanceof Axismundi_Cal_Collection ) {
		return new WP_Error( 'ax_cal_collection_source', __( 'That is not a calendar collection.', 'axismundi-calendar' ) );
	}
	$calendar = $source->calendar;
	$id       = axismundi_cal_collection_uri_for( $calendar );
	if ( '' === $id ) {
		return new WP_Error( 'ax_cal_collection_uri', __( 'That calendar has no collection address.', 'axismundi-calendar' ) );
	}
	$items = axismundi_cal_collection_items( (int) $calendar['id'] );
	$total = count( $items );
	$pages = max( 1, (int) ceil( $total / AXISMUNDI_CAL_COLLECTION_PAGE_SIZE ) );
	$page  = min( max( 1, $source->page ), $pages );
	$slice = array_slice( $items, ( $page - 1 ) * AXISMUNDI_CAL_COLLECTION_PAGE_SIZE, AXISMUNDI_CAL_COLLECTION_PAGE_SIZE );

	$document = array(
		'@context'     => 'https://www.w3.org/ns/activitystreams',
		'id'           => 1 === $page ? $id : $id . '/page/' . $page,
		'type'         => 'OrderedCollection',
		'name'         => axismundi_cal_calendar_display_name( $calendar ),
		/*
		 * Whose collection this is. FEP-400e checks this against the `target` of anything asking to be
		 * added, so it has to be the Actor that may answer for the calendar -- not whoever wrote the
		 * Event that happens to be first in it.
		 */
		'attributedTo' => axismundi_cal_calendar_authority( (int) $calendar['id'] ),
		'totalItems'   => $total,
		'startIndex'   => ( $page - 1 ) * AXISMUNDI_CAL_COLLECTION_PAGE_SIZE,
		'orderedItems' => array_map( static fn( array $item ) : string => (string) $item['uri'], $slice ),
	);
	if ( '' !== (string) ( $calendar['description'] ?? '' ) ) {
		$document['summary'] = wp_strip_all_tags( (string) $calendar['description'] );
	}
	if ( $page > 1 ) {
		$document['prev'] = 2 === $page ? $id : $id . '/page/' . ( $page - 1 );
	}
	if ( $page < $pages ) {
		$document['next'] = $id . '/page/' . ( $page + 1 );
	}
	return $document;
}

/** Register the calendar collection transformer. */
function axismundi_cal_register_collection_transformer() : void {
	if ( ! function_exists( 'axismundi_op_register_collection_transformer' ) ) {
		return;
	}
	axismundi_op_register_collection_transformer(
		'axismundi-calendar',
		array(
			'supports'       => static fn( $source ) : bool => $source instanceof Axismundi_Cal_Collection,
			'collection_uri' => 'axismundi_cal_collection_uri',
			'transform'      => 'axismundi_cal_collection_transform',
			'visible'        => 'axismundi_cal_collection_visible',
			'priority'       => 5,
		)
	);
}
add_action( 'axismundi_op_register_transformers', 'axismundi_cal_register_collection_transformer' );

/**
 * The opaque collection address, for calendars that are nobody's own.
 *
 * @return array<string,string>
 */
function axismundi_cal_collection_rewrite_rules() : array {
	return array(
		'^calendar/c/([0-9a-fA-F-]{36})/page/([0-9]+)/?$' => 'index.php?ax_cal_uuid=$matches[1]&ax_cal_page_no=$matches[2]',
		'^calendar/c/([0-9a-fA-F-]{36})/?$'               => 'index.php?ax_cal_uuid=$matches[1]',
		'^calendar/(@[^/]+)/page/([0-9]+)/?$'             => 'index.php?ax_cal_slug=$matches[1]&ax_cal_page_no=$matches[2]',
	);
}

/** @return void */
function axismundi_cal_register_collection_routes() : void {
	foreach ( axismundi_cal_collection_rewrite_rules() as $regex => $query ) {
		add_rewrite_rule( $regex, $query, 'top' );
	}
}
add_action( 'init', 'axismundi_cal_register_collection_routes', 7 );

/**
 * @param string[] $vars Query vars.
 * @return string[]
 */
function axismundi_cal_collection_query_vars( array $vars ) : array {
	$vars[] = 'ax_cal_uuid';
	$vars[] = 'ax_cal_page_no';
	return $vars;
}
add_filter( 'query_vars', 'axismundi_cal_collection_query_vars' );

/**
 * Hand the projection router a Calendar when the request names one.
 *
 * Only ever adds a source, never replaces one somebody else resolved: the router's contract is that
 * a resolver may claim its own namespace and must otherwise leave the answer alone.
 *
 * @param mixed $source Already-resolved source.
 * @return mixed
 */
function axismundi_cal_resolve_collection_source( $source ) {
	if ( null !== $source && ! ( $source instanceof WP_Post && AXISMUNDI_CAL_EVENT_POST_TYPE !== $source->post_type ) ) {
		return $source;
	}
	$uuid = (string) get_query_var( 'ax_cal_uuid' );
	$slug = (string) get_query_var( 'ax_cal_slug' );
	if ( '' === $uuid && '' === $slug ) {
		return $source;
	}
	$calendar = '' !== $uuid ? axismundi_cal_calendar_by_uuid( $uuid ) : axismundi_cal_calendar_by_slug( $slug );
	if ( ! is_array( $calendar ) ) {
		return $source;
	}
	$page = (int) get_query_var( 'ax_cal_page_no' );
	return new Axismundi_Cal_Collection( $calendar, $page > 0 ? $page : 1 );
}
add_filter( 'axismundi_op_current_source', 'axismundi_cal_resolve_collection_source' );
