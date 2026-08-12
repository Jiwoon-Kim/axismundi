<?php
/**
 * The read API: what one principal may see of the Calendars on this site.
 *
 * Two judgements, never one. `axismundi_cal_is_publicly_readable()` answers what an anonymous
 * request may have and governs the HTML, ICS and ActivityPub surfaces; the effective ACL role
 * answers what a particular principal may have and governs these routes. Collapsing them would
 * either publish shared Calendars or make a reader unable to read their own.
 *
 * `freeBusyReader` is deliberately not enough for anything here. That role exists to say a time is
 * occupied without saying what occupies it, so every endpoint in this file -- all of which return
 * titles, descriptions or both -- requires `reader`. The free/busy surface it is for does not exist
 * yet, and when it does it is a separate route that returns intervals.
 *
 * Refusals distinguish the two askers. An anonymous request for a Calendar it may not read is
 * answered `404`, exactly as a Calendar that does not exist would be, because `403` would confirm
 * there is something there. A request from a signed-in user is answered `403`, since they already
 * know the URL resolves to something and the useful answer is that they lack the role.
 *
 * @package AxismundiCalendar
 */

defined( 'ABSPATH' ) || exit;

/**
 * The widest window one range request may ask for.
 *
 * A year and a day. Wide enough for any calendar view somebody actually renders, narrow enough that
 * an unbounded `start`/`end` cannot turn a recurring rule into an expansion of millions of rows.
 */
const AXISMUNDI_CAL_REST_MAX_WINDOW = 367 * DAY_IN_SECONDS;

/**
 * The strongest role the current request holds on a Calendar.
 *
 * The request's principal is its Actor when it has one and the public otherwise, and the WP user is
 * carried along so a Calendar belonging to a managed Group resolves through that Group's managers.
 * This is the authenticated counterpart to `axismundi_cal_is_publicly_readable()`, and the only
 * thing the routes below ask.
 *
 * @param int $calendar_id Calendar id.
 * @return string Role, or '' for no access at all.
 */
function axismundi_cal_request_role( int $calendar_id ) : string {
	return axismundi_cal_effective_role( $calendar_id, axismundi_cal_current_actor_uri(), get_current_user_id() );
}

/**
 * Whether the current request may read a Calendar's detail.
 *
 * @param int $calendar_id Calendar id.
 * @return bool
 */
function axismundi_cal_request_can_read( int $calendar_id ) : bool {
	/*
	 * Readable by everyone means everyone, including through this API. A calendar the site publishes
	 * is public by policy rather than by a granted rule, so asking the ACL alone answered no for the
	 * one kind of calendar that exists to be read -- and refused it from every REST surface at once.
	 */
	if ( axismundi_cal_is_publicly_readable( $calendar_id ) ) {
		return true;
	}
	return axismundi_cal_acl_rank( axismundi_cal_request_role( $calendar_id ) ) >= axismundi_cal_acl_rank( 'reader' );
}

/**
 * The refusal appropriate to who is asking.
 *
 * @return WP_Error
 */
function axismundi_cal_rest_refuse() : WP_Error {
	if ( is_user_logged_in() ) {
		return new WP_Error(
			'ax_cal_forbidden',
			__( 'You do not have access to that calendar.', 'axismundi-calendar' ),
			array( 'status' => 403 )
		);
	}
	return new WP_Error(
		'ax_cal_not_found',
		__( 'No calendar was found.', 'axismundi-calendar' ),
		array( 'status' => 404 )
	);
}

/**
 * Resolve a Calendar by uuid, or the refusal that hides whether it exists.
 *
 * An unknown uuid and an unreadable one return the same thing to an anonymous caller on purpose:
 * enumeration is exactly what distinguishable answers would allow.
 *
 * @param WP_REST_Request $request Request.
 * @return array<string,mixed>|WP_Error
 */
function axismundi_cal_rest_readable_calendar( WP_REST_Request $request ) {
	$calendar = axismundi_cal_calendar_by_uuid( (string) $request['uuid'] );
	if ( ! is_array( $calendar ) ) {
		// Not found, for everybody. There is nothing here to be forbidden from.
		return new WP_Error( 'ax_cal_not_found', __( 'No calendar was found.', 'axismundi-calendar' ), array( 'status' => 404 ) );
	}
	if ( ! axismundi_cal_request_can_read( (int) $calendar['id'] ) ) {
		return axismundi_cal_rest_refuse();
	}
	return $calendar;
}

/**
 * One Calendar as this request sees it.
 *
 * `accessRole` is computed from the ACL rather than read from a list entry. The entry holds a copy
 * for display, and a copy is what a revoked rule leaves behind -- reporting it would tell a client
 * it may still write to something it may no longer read.
 *
 * The subscription URL appears only for a publicly readable Calendar, because that is the only case
 * where it resolves: handing a private Calendar's `.ics` address to its reader would be handing them
 * a URL that answers `404`.
 *
 * @param array<string,mixed> $calendar Calendar row.
 * @param array<string,mixed> $entry    The asking Actor's list entry, if any.
 * @return array<string,mixed>
 */
function axismundi_cal_rest_calendar( array $calendar, array $entry = array() ) : array {
	$calendar_id = (int) $calendar['id'];
	$public      = axismundi_cal_is_publicly_readable( $calendar_id );

	$out = array(
		'id'          => (string) $calendar['uuid'],
		'summary'     => (string) $calendar['name'],
		'description' => (string) $calendar['description'],
		'timezone'    => axismundi_cal_calendar_timezone( $calendar ),
		'kind'        => (string) $calendar['kind'],
		'accessRole'  => axismundi_cal_request_role( $calendar_id ),
		'public'      => $public,
		'revision'    => (int) $calendar['revision'],
	);
	if ( $public ) {
		$out['url']    = axismundi_cal_calendar_url( $calendar );
		$out['icsUrl'] = axismundi_cal_calendar_ics_url( $calendar );
	}
	if ( array() !== $entry ) {
		/*
		 * The asking Actor's own view of it, which is theirs and not the Calendar's: an alias and a
		 * colour one person chose must not travel to everyone else in the Calendar.
		 */
		$out['selected']        = (bool) $entry['selected'];
		$out['hidden']          = (bool) $entry['hidden'];
		$out['summaryOverride'] = (string) $entry['summary_override'];
		$out['color']           = (string) $entry['color'];
	}
	return $out;
}

/**
 * One occurrence as the API reports it.
 *
 * Both the UTC instant and the Calendar's own local wall time, because neither alone is enough: UTC
 * is what sorts and compares, and the local time is what the event actually claims to happen at.
 * Rendering in the reader's zone is the client's to do, from the instant.
 *
 * @param array<string,mixed> $occurrence Occurrence row.
 * @return array<string,mixed>
 */
function axismundi_cal_rest_occurrence( array $occurrence ) : array {
	return array(
		'eventId'    => (int) $occurrence['post_id'],
		'summary'    => (string) $occurrence['title'],
		'url'        => (string) $occurrence['permalink'],
		'startUtc'   => (string) $occurrence['start_utc'],
		'endUtc'     => (string) $occurrence['end_utc'],
		'startLocal' => (string) ( $occurrence['start_local'] ?? '' ),
		'endLocal'   => (string) ( $occurrence['end_local'] ?? '' ),
		'allDay'     => (bool) $occurrence['all_day'],
		'recurring'  => (bool) $occurrence['recurring'],
	);
}

/**
 * `GET /axismundi/v1/actors/me/calendarList`.
 *
 * The Calendars the signed-in user's Actor stands in some relation to. A user with no Actor stands
 * in no relation to any of them and gets an empty list rather than an error: that is the true
 * answer, and this route is not the place to complain that identity is not installed.
 *
 * Entries whose rule has since been revoked are dropped rather than reported with a stale role.
 *
 * @param WP_REST_Request $request Request.
 * @return WP_REST_Response
 */
function axismundi_cal_rest_calendar_list( WP_REST_Request $request ) : WP_REST_Response {
	return new WP_REST_Response( array( 'items' => axismundi_cal_calendar_list_items() ), 200 );
}

/**
 * The calendar list itself, without a response around it.
 *
 * Its own function because two endpoints answer with it, and a second copy assembled slightly
 * differently is how a sidebar and the screen beside it start disagreeing about which Calendars
 * somebody has.
 *
 * @return array<int,array<string,mixed>>
 */
function axismundi_cal_calendar_list_items() : array {
	$actor_uri = axismundi_cal_current_actor_uri();
	$items     = array();
	if ( '' !== $actor_uri ) {
		/*
		 * Their own relations, plus the Calendars of any Group they manage. The second is why this is
		 * a union and not one query: a Group's calendar belongs to the Group, and its managers reach
		 * it through the Group rather than through a rule naming them personally.
		 */
		$ids = array_values(
			array_unique(
				array_merge(
					axismundi_cal_actor_calendar_ids( $actor_uri ),
					axismundi_cal_user_authority_calendar_ids( get_current_user_id() )
				)
			)
		);
		foreach ( $ids as $calendar_id ) {
			$calendar = axismundi_cal_calendar_get( $calendar_id );
			if ( ! is_array( $calendar ) || ! axismundi_cal_request_can_read( $calendar_id ) ) {
				continue;
			}
			$entry   = axismundi_cal_list_entry( $calendar_id, $actor_uri );
			$items[] = axismundi_cal_rest_calendar( $calendar, is_array( $entry ) ? $entry : array() );
		}
	}
	return $items;
}

/**
 * `GET /axismundi/v1/calendars/{uuid}`.
 *
 * @param WP_REST_Request $request Request.
 * @return WP_REST_Response|WP_Error
 */
function axismundi_cal_rest_calendar_detail( WP_REST_Request $request ) {
	$calendar = axismundi_cal_rest_readable_calendar( $request );
	if ( is_wp_error( $calendar ) ) {
		return $calendar;
	}
	$actor_uri = axismundi_cal_current_actor_uri();
	$entry     = '' === $actor_uri ? null : axismundi_cal_list_entry( (int) $calendar['id'], $actor_uri );
	return new WP_REST_Response( axismundi_cal_rest_calendar( $calendar, is_array( $entry ) ? $entry : array() ), 200 );
}

/**
 * `GET /axismundi/v1/calendars/{uuid}/events`.
 *
 * Access is decided here and the occurrences are fetched through the serializer that performs no
 * check of its own, which is what lets an authorized reader of a private Calendar be served without
 * anything having to bypass the public gate.
 *
 * @param WP_REST_Request $request Request.
 * @return WP_REST_Response|WP_Error
 */
function axismundi_cal_rest_calendar_events( WP_REST_Request $request ) {
	$calendar = axismundi_cal_rest_readable_calendar( $request );
	if ( is_wp_error( $calendar ) ) {
		return $calendar;
	}

	$start = (int) strtotime( (string) $request['start'] );
	$end   = (int) strtotime( (string) $request['end'] );
	if ( $start <= 0 || $end <= 0 || $end <= $start ) {
		return new WP_Error( 'ax_cal_range', __( 'A range needs a start and a later end.', 'axismundi-calendar' ), array( 'status' => 400 ) );
	}
	if ( $end - $start > AXISMUNDI_CAL_REST_MAX_WINDOW ) {
		// Refused rather than silently clamped: a client asking for ten years and receiving one would
		// believe the missing nine were empty.
		return new WP_Error(
			'ax_cal_range_too_wide',
			__( 'That range is wider than this API will expand at once.', 'axismundi-calendar' ),
			array( 'status' => 400 )
		);
	}

	$occurrences = axismundi_cal_calendar_occurrences(
		(int) $calendar['id'],
		gmdate( 'Y-m-d H:i:s', $start ),
		gmdate( 'Y-m-d H:i:s', $end ),
		(int) $request['limit']
	);
	return new WP_REST_Response(
		array(
			'calendar' => (string) $calendar['uuid'],
			'start'    => gmdate( 'c', $start ),
			'end'      => gmdate( 'c', $end ),
			'items'    => array_map( 'axismundi_cal_rest_occurrence', $occurrences ),
		),
		200
	);
}

/**
 * `GET /axismundi/v1/actors/me/calendarView`.
 *
 * One range across several Calendars at once, which is what a calendar screen actually asks. Doing
 * it per Calendar would mean a month of eight calendars is eight round trips whose results the
 * client then has to merge, sort and truncate consistently -- and a client that truncates
 * per-calendar shows eight partial months rather than one complete one.
 *
 * Every named Calendar is checked separately, and one the caller may not read is dropped rather than
 * refused. A view is a set of things somebody ticked, and a single stale tick would otherwise empty
 * the whole screen; that a Calendar is missing from the answer is the honest report of what they may
 * see. Asking for none is not an error either, since unticking everything is a thing people do.
 *
 * Subscribed Calendars contribute their cached entries. They are read-only and marked as such, so
 * the screen can show a public-holiday feed beside somebody's own Events without offering to edit
 * one of them.
 *
 * @param WP_REST_Request $request Request.
 * @return WP_REST_Response|WP_Error
 */
function axismundi_cal_rest_calendar_view( WP_REST_Request $request ) {
	return axismundi_cal_view_payload(
		(array) $request['calendars'],
		(string) $request['start'],
		(string) $request['end'],
		(int) $request['limit'],
		/*
		 * What the client would rather read, resolved here rather than there. A browser choosing its
		 * own label would give a different answer from the ICS feed and the calendar page for the same
		 * day, and the point of one dataset is that it reads the same everywhere.
		 */
		(array) $request['languages']
	);
}

/**
 * The merged range, or the refusal, without a route around it.
 *
 * @param array<int,string> $uuids  Calendar uuids.
 * @param string            $start  Range start.
 * @param string            $end    Range end.
 * @param int               $limit  Maximum occurrences.
 * @return WP_REST_Response|WP_Error
 */
function axismundi_cal_view_payload( array $uuids, string $start_arg, string $end_arg, int $limit, array $accepted = array() ) {
	$start = (int) strtotime( $start_arg );
	$end   = (int) strtotime( $end_arg );
	if ( $start <= 0 || $end <= 0 || $end <= $start ) {
		return new WP_Error( 'ax_cal_range', __( 'A range needs a start and a later end.', 'axismundi-calendar' ), array( 'status' => 400 ) );
	}
	if ( $end - $start > AXISMUNDI_CAL_REST_MAX_WINDOW ) {
		return new WP_Error(
			'ax_cal_range_too_wide',
			__( 'That range is wider than this API will expand at once.', 'axismundi-calendar' ),
			array( 'status' => 400 )
		);
	}

	$from = gmdate( 'Y-m-d H:i:s', $start );
	$to   = gmdate( 'Y-m-d H:i:s', $end );
	$seen = array();
	$out  = array();
	// Days already answered by a sibling calendar of the same dataset.
	$linked = array();

	foreach ( array_slice( $uuids, 0, 50 ) as $uuid ) {
		$calendar = axismundi_cal_calendar_by_uuid( (string) $uuid );
		if ( ! is_array( $calendar ) || isset( $seen[ (int) $calendar['id'] ] ) ) {
			continue;
		}
		$seen[ (int) $calendar['id'] ] = true;
		if ( ! axismundi_cal_request_can_read( (int) $calendar['id'] ) ) {
			continue;
		}
		$calendar_uuid = (string) $calendar['uuid'];

		if ( 'system' === (string) $calendar['kind'] ) {
			/*
			 * A maintained dataset, and the one source where two selected calendars can be the same
			 * thing. 대한민국의 휴일 and Holidays in South Korea are one dataset in two languages, so a
			 * day appears once however many of its siblings are ticked -- `$linked` is what remembers a
			 * day already answered, and it is keyed on the occurrence rather than the row.
			 *
			 * A row not yet linked to a day is its own entry. It is a holiday nobody has related to
			 * anything, which is a state to show rather than one to hide.
			 */
			foreach ( axismundi_cal_system_items_in_range( (int) $calendar['id'], substr( $from, 0, 10 ), substr( $to, 0, 10 ) ) as $entry ) {
				$occurrence_id = (int) $entry['holiday_occurrence_id'];
				if ( $occurrence_id > 0 ) {
					if ( isset( $linked[ $occurrence_id ] ) ) {
						continue;
					}
					$linked[ $occurrence_id ] = true;
				}
				$label = $occurrence_id > 0 ? axismundi_cal_resolve_occurrence_label( $occurrence_id, $accepted ) : null;
				$out[] = array(
					'calendar'   => $calendar_uuid,
					'eventId'    => 0,
					'summary'    => is_array( $label ) ? $label['title'] : (string) $entry['title'],
					// Which language this turned out to be, so a screen can say it is showing English
					// because there is no Korean rather than looking finished.
					'locale'     => is_array( $label ) ? $label['locale'] : '',
					'url'        => '',
					'startUtc'   => (string) $entry['start_date'] . ' 00:00:00',
					'endUtc'     => (string) $entry['end_date'] . ' 00:00:00',
					'startLocal' => (string) $entry['start_date'] . ' 00:00:00',
					'endLocal'   => (string) $entry['end_date'] . ' 00:00:00',
					// Always. A holiday is a whole day everywhere, and the moment one becomes an
					// instant it moves a day for somebody.
					'allDay'     => true,
					'recurring'  => false,
					'readOnly'   => true,
				);
			}
			continue;
		}

		if ( 'remote' === (string) $calendar['kind'] ) {
			foreach ( axismundi_cal_subscribed_entries( (int) $calendar['id'], $from, $to ) as $entry ) {
				$out[] = array(
					'calendar'   => $calendar_uuid,
					'eventId'    => 0,
					'summary'    => (string) $entry['summary'],
					'url'        => (string) $entry['url'],
					'startUtc'   => (string) $entry['start_utc'],
					'endUtc'     => (string) $entry['end_utc'],
					'startLocal' => (string) $entry['start_local'],
					'endLocal'   => (string) $entry['end_local'],
					'allDay'     => (bool) $entry['all_day'],
					'recurring'  => '' !== (string) $entry['rrule'],
					'readOnly'   => true,
				);
			}
			continue;
		}

		foreach ( axismundi_cal_calendar_occurrences( (int) $calendar['id'], $from, $to ) as $occurrence ) {
			$out[] = array_merge(
				axismundi_cal_rest_occurrence( $occurrence ),
				array( 'calendar' => $calendar_uuid, 'readOnly' => false )
			);
		}
	}

	usort( $out, static fn( array $left, array $right ) : int => strcmp( (string) $left['startUtc'], (string) $right['startUtc'] ) );
	/*
	 * Truncated after the merge, never per Calendar. A cap applied to each one separately would
	 * silently drop the end of every busy calendar and call the result a month.
	 */
	$truncated = count( $out ) > $limit;

	return new WP_REST_Response(
		array(
			'start'     => gmdate( 'c', $start ),
			'end'       => gmdate( 'c', $end ),
			'truncated' => $truncated,
			'items'     => array_slice( $out, 0, $limit ),
		),
		200
	);
}

/**
 * `GET /axismundi/v1/actors/me/calendarWorkspace`.
 *
 * The first request the calendar screen makes, answering both halves of it at once.
 *
 * Asked separately, the two are a waterfall: the screen cannot know which Calendars to fetch a month
 * for until the list has come back and told it which ones are ticked. Two round trips is two
 * WordPress bootstraps, and the second cannot start until the first has finished -- so the grid
 * appears distinctly after the sidebar even when both are fast.
 *
 * Neither half is reimplemented here. This calls the same two functions the separate endpoints call,
 * so a client that fetches them individually gets the same answer, and the sidebar cannot start
 * disagreeing with the screen beside it.
 *
 * Which Calendars are in the range is decided here rather than by the client: `selected` and
 * `hidden` are the caller's own view state, already known while assembling the list, and a round
 * trip to ask them back would be the waterfall again in miniature.
 *
 * @param WP_REST_Request $request Request.
 * @return WP_REST_Response|WP_Error
 */
function axismundi_cal_rest_calendar_workspace( WP_REST_Request $request ) {
	$calendars = axismundi_cal_calendar_list_items();
	$ticked    = array();
	foreach ( $calendars as $calendar ) {
		// An entry-less Calendar has no view state yet; it counts as shown, since somebody with access
		// to a Calendar they have never opened should still find it on their screen.
		$selected = ! array_key_exists( 'selected', $calendar ) || $calendar['selected'];
		$hidden   = array_key_exists( 'hidden', $calendar ) && $calendar['hidden'];
		if ( $selected && ! $hidden ) {
			$ticked[] = (string) $calendar['id'];
		}
	}

	$view = axismundi_cal_view_payload( $ticked, (string) $request['start'], (string) $request['end'], (int) $request['limit'] );
	if ( is_wp_error( $view ) ) {
		return $view;
	}
	return new WP_REST_Response(
		array(
			'calendars' => $calendars,
			'view'      => $view->get_data(),
		),
		200
	);
}

/**
 * Register the read routes.
 *
 * Read-only by design: `WP_REST_Server::READABLE` and nothing else. Writing a calendar list entry,
 * changing an ACL and responding to an invitation are each their own slice with their own rules, and
 * none of them belongs behind a route whose permission callback only knows how to say `reader`.
 *
 * @return void
 */
function axismundi_cal_register_read_routes() : void {
	register_rest_route(
		'axismundi/v1',
		'/actors/me/calendarList',
		array(
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => 'axismundi_cal_rest_calendar_list',
			'permission_callback' => static function () {
				if ( is_user_logged_in() ) {
					return true;
				}
				// `me` with nobody signed in is not a forbidden calendar, it is no principal at all.
				return new WP_Error( 'ax_cal_unauthenticated', __( 'You must be signed in.', 'axismundi-calendar' ), array( 'status' => 401 ) );
			},
		)
	);

	$range_args = array(
		'start' => array( 'type' => 'string', 'default' => gmdate( 'Y-m-d\TH:i:s\Z' ) ),
		'end'   => array( 'type' => 'string', 'default' => gmdate( 'Y-m-d\TH:i:s\Z', time() + ( 90 * DAY_IN_SECONDS ) ) ),
		'limit' => array( 'type' => 'integer', 'default' => AXISMUNDI_CAL_RANGE_MAX, 'minimum' => 1, 'maximum' => AXISMUNDI_CAL_RANGE_MAX ),
	);

	register_rest_route(
		'axismundi/v1',
		'/actors/me/calendarWorkspace',
		array(
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => 'axismundi_cal_rest_calendar_workspace',
			'permission_callback' => static function () {
				if ( is_user_logged_in() ) {
					return true;
				}
				return new WP_Error( 'ax_cal_unauthenticated', __( 'You must be signed in.', 'axismundi-calendar' ), array( 'status' => 401 ) );
			},
			'args'                => $range_args,
		)
	);

	register_rest_route(
		'axismundi/v1',
		'/actors/me/calendarView',
		array(
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => 'axismundi_cal_rest_calendar_view',
			// No capability of its own: the range is assembled from Calendars this principal may read,
			// and an anonymous caller may read the public ones exactly as they can elsewhere.
			'permission_callback' => '__return_true',
			'args'                => array(
				'calendars' => array(
					'type'              => 'array',
					'required'          => true,
					'items'             => array( 'type' => 'string' ),
					'sanitize_callback' => static function ( $value ) : array {
						// Accepts a comma-separated list as well, because that is what a URL built by
						// hand looks like and refusing it teaches nothing.
						$list = is_array( $value ) ? $value : explode( ',', (string) $value );
						return array_values( array_filter( array_map( 'sanitize_text_field', $list ) ) );
					},
				),
				'start'     => array( 'type' => 'string', 'default' => gmdate( 'Y-m-d\TH:i:s\Z' ) ),
				'end'       => array( 'type' => 'string', 'default' => gmdate( 'Y-m-d\TH:i:s\Z', time() + ( 90 * DAY_IN_SECONDS ) ) ),
				'limit'     => array( 'type' => 'integer', 'default' => AXISMUNDI_CAL_RANGE_MAX, 'minimum' => 1, 'maximum' => AXISMUNDI_CAL_RANGE_MAX ),
				'languages' => array(
					'type'              => 'array',
					'default'           => array(),
					'items'             => array( 'type' => 'string' ),
					'sanitize_callback' => static function ( $value ) : array {
						$list = is_array( $value ) ? $value : explode( ',', (string) $value );
						return array_slice( array_values( array_filter( array_map( 'sanitize_text_field', $list ) ) ), 0, 10 );
					},
				),
			),
		)
	);

	$uuid = array(
		'uuid' => array(
			'type'              => 'string',
			'required'          => true,
			'validate_callback' => static fn( $value ) : bool => 1 === preg_match( '/^[0-9a-f-]{36}$/i', (string) $value ),
		),
	);

	register_rest_route(
		'axismundi/v1',
		'/calendars/(?P<uuid>[0-9a-fA-F-]{36})',
		array(
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => 'axismundi_cal_rest_calendar_detail',
			// Every refusal here depends on which Calendar was asked for, so it is decided in the
			// handler where the row is already resolved rather than resolved twice.
			'permission_callback' => '__return_true',
			'args'                => $uuid,
		)
	);

	register_rest_route(
		'axismundi/v1',
		'/calendars/(?P<uuid>[0-9a-fA-F-]{36})/events',
		array(
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => 'axismundi_cal_rest_calendar_events',
			'permission_callback' => '__return_true',
			'args'                => array_merge(
				$uuid,
				array(
					'start' => array(
						'type'    => 'string',
						'default' => gmdate( 'Y-m-d\TH:i:s\Z' ),
					),
					'end'   => array(
						'type'    => 'string',
						'default' => gmdate( 'Y-m-d\TH:i:s\Z', time() + ( 90 * DAY_IN_SECONDS ) ),
					),
					'limit' => array(
						'type'    => 'integer',
						'default' => AXISMUNDI_CAL_RANGE_MAX,
						'minimum' => 1,
						'maximum' => AXISMUNDI_CAL_RANGE_MAX,
					),
				)
			),
		)
	);
}
add_action( 'rest_api_init', 'axismundi_cal_register_read_routes' );
