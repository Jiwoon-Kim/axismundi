<?php
/**
 * Where an Event happens, when that is more than one place.
 *
 * FEP-8a8e takes `location` as a list, and a hybrid Event is the ordinary reason for it: a room, a
 * meeting link, and often a stream of the same thing. A single text column holds the first of those
 * and loses the rest, so this is a list -- and "hybrid" is read off what is in it rather than stored
 * beside it, because a flag and a list answering the same question is how the two come to disagree.
 *
 * A `Place` is the geodata plugin's object, with its own identity and its own validity rules. The
 * column for it exists here so that a physical location can point at one when that plugin owns a
 * contract to check it against; until then a physical location is a label and an address, and an
 * Event is writable before anybody has registered the venue.
 *
 * @package AxismundiCalendar
 */

defined( 'ABSPATH' ) || exit;

/** Whether a location is somewhere to go or somewhere to connect. */
const AXISMUNDI_CAL_LOCATION_KINDS = array( 'physical', 'virtual' );

/**
 * What a virtual location offers, as RFC 7986 names them.
 *
 * One vocabulary rather than a kind of our own. `CONFERENCE` covers a broadcast as well as a meeting
 * -- that is what `FEED` is for -- so a stream and a video call are the same kind of thing carrying
 * different features, and splitting them into separate models here would invent a distinction
 * neither iCalendar nor FEP-8a8e makes.
 */
const AXISMUNDI_CAL_LOCATION_FEATURES = array( 'AUDIO', 'CHAT', 'FEED', 'MODERATOR', 'PHONE', 'SCREEN', 'VIDEO' );

/**
 * Who a location is told to.
 *
 * A public event with a private joining link is the ordinary case, not an edge one: the address is
 * announced and the meeting URL is for the people coming. Published in the open feed, that URL is
 * handed to everybody who has the calendar -- so this is stored per location rather than inherited
 * from the Event, which is public precisely because its existence should be.
 */
const AXISMUNDI_CAL_LOCATION_ACCESS = array( 'public', 'attendees' );

/** @return string Event location table name. */
function axismundi_cal_event_locations_table() : string {
	global $wpdb;
	return $wpdb->prefix . 'ax_cal_event_locations';
}

/**
 * The places one Event happens, in the order somebody arranged them.
 *
 * Order is a fact rather than a detail: the first physical one is what the iCalendar document names,
 * because `LOCATION` is a single property and cannot hold the others.
 *
 * @param int $post_id Event post ID.
 * @return array<int,array<string,mixed>>
 */
function axismundi_cal_event_locations( int $post_id ) : array {
	global $wpdb;
	if ( $post_id <= 0 || ! axismundi_cal_ready() ) {
		return array();
	}
	$table = axismundi_cal_event_locations_table();
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- keyed lookup in this plugin's own table.
	return (array) $wpdb->get_results(
		$wpdb->prepare( "SELECT * FROM {$table} WHERE event_post_id = %d ORDER BY position ASC, id ASC", $post_id ),
		ARRAY_A
	);
}

/**
 * How somebody attends, worked out from where it is.
 *
 * Derived rather than stored. An Event with a room and a meeting link is hybrid because of what it
 * has, and a stored flag would go on saying so after the last link was removed.
 *
 * @param int $post_id Event post ID.
 * @return string `physical`, `virtual`, `hybrid`, or '' when nowhere is named.
 */
function axismundi_cal_event_attendance_mode( int $post_id ) : string {
	$kinds = array_column( axismundi_cal_event_locations( $post_id ), 'kind' );
	$here  = in_array( 'physical', $kinds, true );
	$away  = in_array( 'virtual', $kinds, true );
	if ( $here && $away ) {
		return 'hybrid';
	}
	if ( $here ) {
		return 'physical';
	}
	return $away ? 'virtual' : '';
}

/**
 * Replace the places one Event happens.
 *
 * Written whole rather than one row at a time. The list has an order and the order means something,
 * so a partial update would have to say where each row went -- and the caller that knows is the one
 * that just rearranged them.
 *
 * @param int                            $post_id   Event post ID.
 * @param array<int,array<string,mixed>> $locations Submitted locations.
 * @return true|WP_Error
 */
function axismundi_cal_event_locations_save( int $post_id, array $locations ) {
	global $wpdb;
	if ( ! axismundi_cal_ready() ) {
		return new WP_Error( 'ax_cal_store', __( 'The calendar store is unavailable.', 'axismundi-calendar' ) );
	}

	$rows = array();
	foreach ( array_values( $locations ) as $position => $location ) {
		$location = (array) $location;
		$kind     = (string) ( $location['kind'] ?? 'physical' );
		if ( ! in_array( $kind, AXISMUNDI_CAL_LOCATION_KINDS, true ) ) {
			return new WP_Error( 'ax_event_location_kind', __( 'A location is either somewhere to go or somewhere to connect.', 'axismundi-calendar' ), array( 'status' => 400 ) );
		}
		$url = esc_url_raw( trim( (string) ( $location['url'] ?? '' ) ) );
		/*
		 * A virtual location is a URL. Without one there is nothing to attend, and a row saying only
		 * "online" would make an Event look reachable while telling nobody how.
		 */
		if ( 'virtual' === $kind && '' === $url ) {
			return new WP_Error( 'ax_event_location_url', __( 'An online location needs the address people join at.', 'axismundi-calendar' ), array( 'status' => 400 ) );
		}
		$features = array_values( array_unique( array_map(
			static fn( $feature ) : string => strtoupper( trim( (string) $feature ) ),
			(array) ( $location['features'] ?? array() )
		) ) );
		foreach ( $features as $feature ) {
			if ( ! in_array( $feature, AXISMUNDI_CAL_LOCATION_FEATURES, true ) ) {
				return new WP_Error( 'ax_event_location_feature', __( 'That is not something RFC 7986 says a link can offer.', 'axismundi-calendar' ), array( 'status' => 400 ) );
			}
		}
		$access = (string) ( $location['access'] ?? 'public' );
		if ( ! in_array( $access, AXISMUNDI_CAL_LOCATION_ACCESS, true ) ) {
			return new WP_Error( 'ax_event_location_access', __( 'A location is either announced or kept for the people attending.', 'axismundi-calendar' ), array( 'status' => 400 ) );
		}

		$rows[] = array(
			'event_post_id' => $post_id,
			'position'      => (int) $position,
			'kind'          => $kind,
			// Ordered by the vocabulary rather than by how somebody ticked them, so the stored value does
			// not depend on the order of a form.
			'features'      => 'virtual' === $kind
				? implode( ',', array_values( array_intersect( AXISMUNDI_CAL_LOCATION_FEATURES, $features ) ) )
				: '',
			'access'        => $access,
			'label'         => sanitize_text_field( (string) ( $location['label'] ?? '' ) ),
			'url'           => $url,
			'address_text'  => 'physical' === $kind ? sanitize_textarea_field( (string) ( $location['address_text'] ?? '' ) ) : '',
			/*
			 * Kept but never validated here. The geodata plugin owns `Place`, and taking a bare id as
			 * proof of one would leave this plugin half-owning a model it cannot check.
			 */
			'place_id'      => isset( $location['place_id'] ) && (int) $location['place_id'] > 0 ? (int) $location['place_id'] : null,
		);
	}

	$table = axismundi_cal_event_locations_table();
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- this plugin's own table.
	$wpdb->delete( $table, array( 'event_post_id' => $post_id ) );
	foreach ( $rows as $row ) {
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- this plugin's own table.
		$wpdb->insert( $table, $row );
	}
	return true;
}

/**
 * The first physical place a given document may name.
 *
 * Filtered before it is chosen, not after. Choosing the first physical row and then dropping it for
 * being attendees-only leaves `LOCATION` empty on an Event that has a public venue further down the
 * list -- the address is announced everywhere except the one place somebody subscribes to.
 *
 * @param int  $post_id     Event post ID.
 * @param bool $public_only Whether the caller may only show what was announced.
 * @return array<string,mixed>|null
 */
function axismundi_cal_event_primary_place( int $post_id, bool $public_only = true ) : ?array {
	foreach ( axismundi_cal_event_locations( $post_id ) as $location ) {
		if ( 'physical' !== (string) $location['kind'] ) {
			continue;
		}
		if ( $public_only && 'public' !== (string) $location['access'] ) {
			continue;
		}
		return $location;
	}
	return null;
}

/**
 * The locations one viewer may be shown.
 *
 * `attendees` means the people coming: whoever maintains the Event, and whoever it accepted. Being
 * accepted and then not told where to go would make acceptance mean nothing, so the two arrive
 * together -- and an invitation is one more line in that predicate rather than a rethink of it.
 *
 * A logged-in reader is not an attendee. Opening it to any account would publish a private joining
 * link to everybody on the site and call that restricted.
 *
 * @param int $post_id Event post ID.
 * @return array<int,array<string,mixed>>
 */
function axismundi_cal_event_visible_locations( int $post_id, string $actor_uri = '' ) : array {
	$actor_uri = '' !== $actor_uri ? $actor_uri : (string) axismundi_cal_current_actor_uri();
	$attending = axismundi_cal_can_view_attendee_location( $actor_uri, $post_id );

	return array_values( array_filter(
		axismundi_cal_event_locations( $post_id ),
		static fn( array $location ) : bool => 'public' === (string) $location['access'] || $attending
	) );
}

/**
 * One physical place as a line of text.
 *
 * @param array<string,mixed> $location Location row.
 * @return string
 */
function axismundi_cal_event_place_text( array $location ) : string {
	$label   = trim( (string) ( $location['label'] ?? '' ) );
	$address = trim( (string) ( $location['address_text'] ?? '' ) );
	if ( '' === $label ) {
		return $address;
	}
	return '' === $address ? $label : $label . ', ' . $address;
}

/**
 * Drop an Event's locations with the Event.
 *
 * @param int $post_id Event post ID.
 * @return void
 */
function axismundi_cal_event_locations_forget( int $post_id ) : void {
	global $wpdb;
	if ( ! axismundi_cal_ready() ) {
		return;
	}
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- this plugin's own table.
	$wpdb->delete( axismundi_cal_event_locations_table(), array( 'event_post_id' => $post_id ) );
}
