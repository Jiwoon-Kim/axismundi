<?php
/**
 * The structured REST field the authoring panel reads and writes.
 *
 * One field, `axismundi_cal_envelope`, over the same single writer the importer and any future
 * caller use. The panel is an editing surface and nothing more: every rule that decides whether an
 * Event is well-formed -- the IANA timezone, `endTime` after `startTime`, the URL an `external`
 * join mode requires -- stays in `axismundi_cal_event_save()`, so a client cannot author its way around
 * one and a second authoring path cannot drift from the first.
 *
 * The envelope is a custom table rather than post meta, so this deliberately is not
 * `register_post_meta`: meta would give the editor a writer that bypasses the validating one.
 *
 * @package AxismundiCalendar
 */

defined( 'ABSPATH' ) || exit;

/**
 * The envelope as the panel sees it.
 *
 * A curated shape rather than the stored row. The GMT columns are derived from the local time and
 * the zone, and echoing them would invite a client to send them back as if they were authorable.
 * `previousStartsAtGmt` is readable because a rescheduled Event should be able to say so, but it is
 * written only by `save()` noticing the start moved.
 *
 * @param int $post_id Event post ID.
 * @return array<string,mixed>
 */
function axismundi_cal_rest_envelope( int $post_id ) : array {
	$envelope = axismundi_cal_event_get( $post_id );
	if ( ! is_array( $envelope ) ) {
		// An Event that has not been given its times yet. Reported as an empty envelope rather
		// than null so the panel has one shape to render in both states.
		return array(
			'startsAt'                 => '',
			'endsAt'                   => '',
			'timezone'                 => '',
			'displayEndTime'           => true,
			'eventStatus'              => 'EventScheduled',
			'joinMode'                 => 'free',
			'externalParticipationUrl' => '',
			'maximumAttendeeCapacity'  => null,
			'previousStartsAtGmt'      => '',
			'complete'                 => false,
		);
	}
	return array(
		'startsAt'                 => (string) $envelope['starts_at'],
		'endsAt'                   => (string) $envelope['ends_at'],
		'timezone'                 => (string) $envelope['timezone'],
		'displayEndTime'           => (bool) $envelope['display_end_time'],
		'eventStatus'              => (string) $envelope['event_status'],
		'joinMode'                 => (string) $envelope['join_mode'],
		'externalParticipationUrl' => (string) $envelope['external_participation_url'],
		'maximumAttendeeCapacity'  => null === $envelope['maximum_attendee_capacity'] ? null : (int) $envelope['maximum_attendee_capacity'],
		'previousStartsAtGmt'      => (string) ( $envelope['previous_starts_at_gmt'] ?? '' ),
		'complete'                 => true,
	);
}

/**
 * Whether an incoming envelope carries enough to be stored at all.
 *
 * The table cannot hold half an Event: both times and the zone are columns with no meaningful
 * empty value, and `save()` refuses anything less. A draft being filled in one field at a time
 * would therefore fail on every keystroke-triggered autosave before the author reached the last
 * field, so an incomplete envelope for an Event that has none yet is left unwritten instead of
 * rejected. Publishing is where incompleteness becomes an error, because that is where it starts
 * to matter -- see `axismundi_cal_guard_publish()`.
 *
 * @param array<string,mixed> $value Incoming envelope.
 * @return bool
 */
function axismundi_cal_rest_envelope_is_writable( array $value ) : bool {
	return '' !== trim( (string) ( $value['startsAt'] ?? '' ) )
		&& '' !== trim( (string) ( $value['endsAt'] ?? '' ) )
		&& '' !== trim( (string) ( $value['timezone'] ?? '' ) );
}

/**
 * Map the panel's shape onto the writer's field names.
 *
 * @param array<string,mixed> $value Incoming envelope.
 * @return array<string,mixed>
 */
function axismundi_cal_rest_to_fields( array $value ) : array {
	$map = array(
		'startsAt'                 => 'starts_at',
		'endsAt'                   => 'ends_at',
		'timezone'                 => 'timezone',
		'displayEndTime'           => 'display_end_time',
		'eventStatus'              => 'event_status',
		'joinMode'                 => 'join_mode',
		'externalParticipationUrl' => 'external_participation_url',
		'maximumAttendeeCapacity'  => 'maximum_attendee_capacity',
	);
	$fields = array();
	foreach ( $map as $from => $to ) {
		if ( array_key_exists( $from, $value ) ) {
			$fields[ $to ] = $value[ $from ];
		}
	}
	return $fields;
}

/**
 * Register the envelope field on the Event REST resource.
 *
 * @return void
 */
function axismundi_cal_register_rest_field() : void {
	register_rest_field(
		AXISMUNDI_CAL_EVENT_POST_TYPE,
		'axismundi_cal_envelope',
		array(
			'get_callback'    => static function ( array $post ) : array {
				return axismundi_cal_rest_envelope( (int) $post['id'] );
			},
			'update_callback' => static function ( $value, WP_Post $post ) {
				// Dormant until the panel sends it, so any other write path is untouched.
				if ( ! is_array( $value ) ) {
					return true;
				}
				if ( ! current_user_can( 'edit_post', $post->ID ) ) {
					return new WP_Error( 'ax_event_forbidden', __( 'You cannot edit this Event.', 'axismundi-calendar' ), array( 'status' => 403 ) );
				}
				if ( null === axismundi_cal_event_get( (int) $post->ID ) && ! axismundi_cal_rest_envelope_is_writable( $value ) ) {
					return true;
				}
				$result = axismundi_cal_event_save( (int) $post->ID, axismundi_cal_rest_to_fields( $value ) );
				return is_wp_error( $result ) ? $result : true;
			},
			'schema'          => array(
				'type'       => 'object',
				'context'    => array( 'edit' ),
				'properties' => array(
					'startsAt'                 => array( 'type' => 'string' ),
					'endsAt'                   => array( 'type' => 'string' ),
					'timezone'                 => array( 'type' => 'string' ),
					'displayEndTime'           => array( 'type' => 'boolean' ),
					'eventStatus'              => array( 'type' => 'string', 'enum' => axismundi_cal_event_statuses() ),
					'joinMode'                 => array( 'type' => 'string', 'enum' => axismundi_cal_event_join_modes() ),
					'externalParticipationUrl' => array( 'type' => 'string' ),
					'maximumAttendeeCapacity'  => array( 'type' => array( 'integer', 'null' ), 'minimum' => 1 ),
					'previousStartsAtGmt'      => array( 'type' => 'string', 'readonly' => true ),
					'complete'                 => array( 'type' => 'boolean', 'readonly' => true ),
				),
			),
		)
	);
}
add_action( 'rest_api_init', 'axismundi_cal_register_rest_field' );
