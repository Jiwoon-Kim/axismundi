<?php
/**
 * What can be done with one Calendar, answered once.
 *
 * Screens kept asking the same two questions in different words -- is this remote, is this the
 * default -- and deriving from them, separately, what to show. That answers the wrong question. What
 * a screen needs is not the Calendar's type but which operations it permits, and those do not line
 * up one-to-one with type: a subscribed Calendar and somebody's default are both undeletable for
 * entirely different reasons, and a reader and a subscriber both cannot write for different ones
 * again.
 *
 * Stated as capabilities, the matrix reads:
 *
 *   default local    edit yes   share yes   delete NO    unsubscribe no    write yes
 *   ordinary local   edit yes   share yes   delete yes   unsubscribe no    write yes
 *   subscribed       edit NO    share NO    delete NO    unsubscribe yes   write NO
 *
 * The value of writing it down is that a fourth kind -- an imported calendar, a curated holiday
 * feed, a remote ActivityPub collection -- is a row in this table rather than another `if` in every
 * screen that renders a Calendar.
 *
 * Nothing here is a security boundary on its own. Each capability is computed from the ACL and the
 * Calendar's own state, and the writers enforce the same rules independently -- a screen that
 * offered a button anyway would still be refused.
 *
 * @package AxismundiCalendar
 */

defined( 'ABSPATH' ) || exit;

/**
 * Where a Calendar's contents come from.
 *
 * Derived rather than stored, because every fact it is derived from is already recorded and a column
 * would be a second copy able to disagree with them. That changes when contents can arrive by a
 * route that leaves no other trace -- an imported file, an operator typing holidays in by hand --
 * since "was imported once" is not visible anywhere else. This returns `native` or `ics` today and
 * is the seam those will be added to.
 *
 * @param array<string,mixed> $calendar Calendar row.
 * @return string `native` for a Calendar authored here, `ics` for one mirrored from a feed.
 */
function axismundi_cal_calendar_source_type( array $calendar ) : string {
	return 'remote' === (string) ( $calendar['kind'] ?? '' ) ? 'ics' : 'native';
}

/**
 * Every operation the current request may perform on one Calendar.
 *
 * @param array<string,mixed>|null $calendar  Calendar row.
 * @param string                   $actor_uri Asking Actor, or '' to resolve the current request.
 * @param int                      $user_id   Asking WP user, or 0 to resolve the current request.
 * @return array<string,bool>
 */
function axismundi_cal_calendar_capabilities( ?array $calendar, string $actor_uri = '', int $user_id = 0 ) : array {
	$none = array(
		// The Calendar itself: its name, what it is for, and when its Events are assumed to happen.
		'edit_details'    => false,
		'change_timezone' => false,
		// One Actor's own view of it, which is theirs whatever else they may not do.
		'rename_locally'  => false,
		// Who else may see it, and whether anyone at all may.
		'share'           => false,
		'publish'         => false,
		// The two ways a Calendar leaves a screen, which are not the same operation.
		'delete'          => false,
		'unsubscribe'     => false,
		// What goes on it, and what comes off it.
		'write_events'    => false,
		'export'          => false,
	);
	if ( ! is_array( $calendar ) || empty( $calendar['id'] ) ) {
		return $none;
	}

	$calendar_id = (int) $calendar['id'];
	$actor_uri   = '' !== $actor_uri ? $actor_uri : axismundi_cal_current_actor_uri();
	$user_id     = $user_id > 0 ? $user_id : get_current_user_id();
	$role        = axismundi_cal_effective_role( $calendar_id, $actor_uri, $user_id );
	$rank        = axismundi_cal_acl_rank( $role );
	$reader      = $rank >= axismundi_cal_acl_rank( 'reader' );
	$writer      = $rank >= axismundi_cal_acl_rank( 'writer' );
	$owner       = $rank >= axismundi_cal_acl_rank( 'owner' );
	$subscribed  = '' !== $actor_uri && is_array( axismundi_cal_list_entry( $calendar_id, $actor_uri ) );

	if ( 'ics' === axismundi_cal_calendar_source_type( $calendar ) ) {
		/*
		 * A mirror of somebody else's Calendar. Its name, description and timezone are its
		 * publisher's, and no role granted here can change that -- which is why `owner` on a
		 * subscribed Calendar still cannot edit or share it. What a subscriber owns is their own
		 * relation to it: what they call it, and whether they keep following it.
		 */
		return array_merge(
			$none,
			array(
				'rename_locally' => $subscribed,
				'unsubscribe'    => $subscribed,
				'export'         => $reader,
			)
		);
	}

	return array(
		'edit_details'    => $writer,
		'change_timezone' => $writer,
		'rename_locally'  => $subscribed,
		// Deciding who else may see a Calendar is administering it, which a writer was never granted.
		'share'           => $owner,
		'publish'         => $owner,
		/*
		 * A default Calendar is where its Actor's Events go when they name none. Deleting it would
		 * leave the next one with nowhere to be filed, and the writer would simply make another.
		 */
		'delete'          => $owner && empty( $calendar['is_primary'] ),
		// There is no subscription to leave; a local Calendar is left by giving up the role.
		'unsubscribe'     => false,
		'write_events'    => $writer,
		'export'          => $reader,
	);
}

/**
 * One capability, for the places that need a single answer.
 *
 * @param array<string,mixed>|null $calendar   Calendar row.
 * @param string                   $capability Capability name.
 * @return bool
 */
function axismundi_cal_calendar_can( ?array $calendar, string $capability ) : bool {
	$capabilities = axismundi_cal_calendar_capabilities( $calendar );
	return ! empty( $capabilities[ $capability ] );
}
