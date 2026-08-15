<?php
/**
 * The Event projection: an `ax_event` post as an ActivityStreams Event.
 *
 * Registered with Object Projections' transformer registry, which is what makes an Event an Object
 * everywhere else — the thread graph, interactions, the listing index and the canonical document
 * route all reach it through the same seam a Note or a Topic does, without any of them learning
 * what an event is.
 *
 * The wire format follows FEP-8a8e. That is not a preference: `Event` alone tells a peer almost
 * nothing, and the properties that make an event usable — when, where, whether you can join — are
 * the FEP's. `event-bridge-for-activitypub` emits the same shape for eight other event plugins, so
 * matching it is what lets a Mobilizon or Gancio instance read this site's events at all.
 *
 * @package AxismundiCalendar
 */

defined( 'ABSPATH' ) || exit;

/**
 * Whether this source is an Event this plugin projects.
 *
 * An Event needs its envelope: a post of this type with no times is a draft of an Event rather than
 * one, and projecting it would publish an Event with no `startTime`, which FEP-8a8e requires.
 *
 * @param mixed $source Candidate source.
 * @return bool
 */
function axismundi_cal_event_transformer_supports( $source ) : bool {
	return $source instanceof WP_Post
		&& AXISMUNDI_CAL_EVENT_POST_TYPE === $source->post_type
		&& null !== axismundi_cal_event_get( (int) $source->ID );
}

/**
 * The canonical Object URI for one Event.
 *
 * The stable post URI rather than the permalink, for the reason every local Object here uses it: a
 * permalink changes when a slug is edited, and an Object identity that changes is a different
 * Object to every peer holding the old one. The readable page travels as `url`.
 *
 * @param mixed $source Event post.
 * @return string
 */
function axismundi_cal_event_object_uri( $source ) : string {
	if ( ! $source instanceof WP_Post || ! function_exists( 'axismundi_op_post_object_uri' ) ) {
		return '';
	}
	return axismundi_op_post_object_uri( $source );
}

/**
 * Whether this Event may be represented publicly.
 *
 * @param mixed $source Event post.
 * @return bool
 */
function axismundi_cal_event_visible( $source ) : bool {
	if ( ! $source instanceof WP_Post
		|| 'publish' !== $source->post_status
		|| '' !== (string) $source->post_password
		|| ! is_post_publicly_viewable( $source ) ) {
		return false;
	}
	/*
	 * An Event on a Calendar nobody may read is not this site's to publish. Federation is the widest
	 * surface there is, so it asks the same question the grid does rather than trusting post status.
	 */
	$schedule = axismundi_cal_schedule_for_event( (int) $source->ID );
	if ( ! is_array( $schedule ) || '' === axismundi_cal_calendar_authority( (int) $schedule['calendar_id'] ) || ! axismundi_cal_is_publicly_readable( (int) $schedule['calendar_id'] ) ) {
		return false;
	}

	/*
	 * A recurring Event is held back from federation until occurrences are projected individually.
	 *
	 * FEP-8a8e has no recurrence: an `Event` carries one `startTime`. Publishing a weekly series as
	 * a single Event would tell every peer it happens once, on the first date -- and it would look
	 * entirely correct on the receiving end, which is what makes it worse than publishing nothing.
	 * Peers would then hold a wrong record that no later correction reaches, because they have no
	 * reason to re-fetch something they believe they already have.
	 *
	 * Withheld rather than approximated. The panel says so where the rule is authored, so this is a
	 * stated limitation rather than a silent omission.
	 */
	return ! axismundi_cal_schedule_is_recurring( $schedule );
}

/**
 * Project one Event post into an ActivityStreams Event.
 *
 * @param mixed $source Event post.
 * @return array<string,mixed>
 */
function axismundi_cal_event_transform( $source ) : array {
	if ( ! $source instanceof WP_Post ) {
		return array();
	}
	$envelope = axismundi_cal_event_get( (int) $source->ID );
	if ( ! is_array( $envelope ) ) {
		return array();
	}
	$uri      = axismundi_cal_event_object_uri( $source );
	$timezone = (string) $envelope['timezone'];

	$event = array(
		'id'           => $uri,
		'type'         => 'Event',
		/*
		 * Plain text, per FEP-8a8e. A title carrying markup is a title every consumer renders
		 * differently, and several render it as escaped source.
		 */
		'name'         => wp_strip_all_tags( get_the_title( $source ) ),
		'startTime'    => axismundi_cal_iso8601( (string) $envelope['starts_at'], $timezone ),
		'endTime'      => axismundi_cal_iso8601( (string) $envelope['ends_at'], $timezone ),
		'timezone'     => $timezone,
		'url'          => get_permalink( $source ),
		'published'    => get_post_time( DATE_W3C, true, $source ),
		'updated'      => get_post_modified_time( DATE_W3C, true, $source ),
		'eventStatus'  => (string) $envelope['event_status'],
		/*
		 * Local Join and Invite handling does not exist yet. Advertising `free`, `restricted` or
		 * `invite` would invite a remote actor to send an Activity this instance cannot honour.
		 * `external` remains meaningful because it names a working off-site participation URL.
		 */
		'joinMode'     => 'external' === (string) $envelope['join_mode'] ? 'external' : 'none',
	);

	/*
	 * Published by the Actor it was published by, which is not the same as the Actor whose Calendar it
	 * sits on. Somebody with write access to a shared Calendar adds an Event to it and remains its
	 * author; attributing it to the Calendar's owner would hand their work to somebody else and, on
	 * the wire, make the owner the party a reply is addressed to.
	 *
	 * The Calendar's authority is still the answer to a different question -- who owns the collection
	 * this is filed in -- and appears as FEP-400e `target.attributedTo` when the Calendar is projected
	 * as a Collection. It is deliberately not asserted here, because nothing yet publishes that
	 * Collection and naming a target no consumer can fetch would be an invitation to fetch it.
	 */
	$acting = axismundi_cal_event_acting_actor_uri( (int) $source->ID );
	if ( '' !== $acting ) {
		$event['attributedTo'] = $acting;
		/*
		 * `organizers` is required by FEP-8a8e and is not the same claim as `attributedTo`: one
		 * says who published the record, the other who is running the event. The FEP requires a
		 * Collection, not an array; an inline Collection is honest until organizer paging exists.
		 */
		$event['organizers'] = array(
			'type'       => 'Collection',
			'totalItems' => 1,
			'items'      => array( $acting ),
		);
	}

	$content = apply_filters( 'the_content', $source->post_content );
	if ( '' !== trim( wp_strip_all_tags( (string) $content ) ) ) {
		$event['content']   = (string) $content;
		$event['mediaType'] = 'text/html';
	}

	if ( empty( $envelope['display_end_time'] ) ) {
		$event['displayEndTime'] = false;
	}
	if ( ! empty( $envelope['previous_starts_at_gmt'] ) ) {
		$event['previousStartTime'] = axismundi_cal_iso8601( (string) $envelope['previous_starts_at_gmt'], 'UTC' );
	}
	if ( 'external' === (string) $event['joinMode'] && '' !== (string) $envelope['external_participation_url'] ) {
		$event['externalParticipationUrl'] = (string) $envelope['external_participation_url'];
	}
	if ( null !== $envelope['maximum_attendee_capacity'] ) {
		$event['maximumAttendeeCapacity'] = (int) $envelope['maximum_attendee_capacity'];
		// Worked out from the accepted replies rather than counted separately. A peer reads this to
		// decide whether to offer joining at all, so a stale number is an invitation to be turned away.
		$event['remainingAttendeeCapacity'] = (int) axismundi_cal_event_remaining_capacity( (int) $source->ID );
	}

	/**
	 * Filter the Event projection before the renderer validates it.
	 *
	 * Location and participation arrive through here rather than being wired in: `location` is
	 * Geodata's to answer, and `attendees` is a projection of the Activity ledger. Neither belongs
	 * to the post type.
	 *
	 * @param array<string,mixed> $event    Projected Event.
	 * @param WP_Post             $source   Event post.
	 * @param array<string,mixed> $envelope Event envelope.
	 */
	return (array) apply_filters( 'axismundi_cal_event_object', $event, $source, $envelope );
}

/**
 * Format one stored datetime as ISO 8601 with its offset.
 *
 * The offset is carried because a bare local time is ambiguous to every reader, and the IANA name
 * travels separately in `timezone` so a consumer can still show the event's own wall time.
 *
 * @param string $value    Stored datetime.
 * @param string $timezone IANA timezone name.
 * @return string
 */
function axismundi_cal_iso8601( string $value, string $timezone ) : string {
	try {
		$zone = new DateTimeZone( '' !== $timezone ? $timezone : 'UTC' );
		return ( new DateTimeImmutable( $value, $zone ) )->format( DATE_W3C );
	} catch ( Exception $error ) {
		return '';
	}
}

/**
 * Resolve an Event source from its canonical Object URI.
 *
 * The transformer says how to project a source; this says how to find one again from the URI alone,
 * which is what everything holding only an identity needs — the thread graph resolving a parent, the
 * listing projection deciding whether a row still has a source, the cached-object route.
 *
 * Fallback-only by contract: a product returns its own source when it recognizes the exact URI and
 * returns `$source` untouched otherwise, so two products can never both claim one Object.
 *
 * The URI is compared to the one this plugin would mint rather than trusting the `p` argument,
 * because any post can be addressed as `?p=<id>` and only ours should answer here.
 *
 * @param mixed  $source Existing resolution, or null.
 * @param string $uri    Canonical object URI.
 * @return mixed|null
 */
function axismundi_cal_event_resolve_source_by_uri( $source, string $uri ) {
	if ( null !== $source ) {
		return $source;
	}
	$parts = wp_parse_url( $uri );
	if ( ! is_array( $parts ) || empty( $parts['query'] ) ) {
		return null;
	}
	parse_str( (string) $parts['query'], $args );
	$post = isset( $args['p'] ) ? get_post( absint( $args['p'] ) ) : null;
	if ( ! $post instanceof WP_Post || ! axismundi_cal_event_transformer_supports( $post ) ) {
		return null;
	}
	return hash_equals( $uri, axismundi_cal_event_object_uri( $post ) ) ? $post : null;
}
add_filter( 'axismundi_op_resolve_source_by_uri', 'axismundi_cal_event_resolve_source_by_uri', 9, 2 );

/**
 * Register the Event transformer.
 *
 * @return void
 */
function axismundi_cal_register_event_transformer() : void {
	/*
	 * One gate for every Actor-dependent surface. Registering a transformer without Actors would
	 * publish Events attributed to nobody, which the renderer refuses anyway -- but it would fail
	 * per Event at render time rather than saying plainly that a plugin is missing.
	 */
	if ( ! axismundi_cal_federation_ready() ) {
		return;
	}
	axismundi_op_register_object_transformer(
		'axismundi-calendar',
		array(
			'supports'   => 'axismundi_cal_event_transformer_supports',
			'object_uri' => 'axismundi_cal_event_object_uri',
			'transform'  => 'axismundi_cal_event_transform',
			'visible'    => 'axismundi_cal_event_visible',
			// Ahead of the Core Post transformer, which would otherwise claim this post type and
			// publish an Event as an ordinary Article.
			'priority'   => 5,
		)
	);
}
add_action( 'axismundi_op_register_transformers', 'axismundi_cal_register_event_transformer' );
