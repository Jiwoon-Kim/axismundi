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
 * Where a Calendar's contents can come from.
 *
 * Recorded on the Calendar because origin is decided once and cannot be reconstructed afterwards.
 */
const AXISMUNDI_CAL_SOURCE_TYPES = array( 'native', 'subscription', 'import', 'manual' );

/**
 * Where a Calendar's contents come from.
 *
 *   native        authored here, one Event at a time
 *   subscription  a read-only mirror of a feed somebody else publishes
 *   import        read from a file once, and this site's from then on
 *   manual        a dataset this site maintains by hand -- holidays, observances
 *
 * Stored rather than derived. It was derivable while there were two of these, because a mirror is
 * exactly a Calendar with a source row -- but "was read from a file once, and is now ours" leaves no
 * trace anywhere else, and neither does "an operator maintains this by hand". A Calendar's origin has
 * to be recorded at the moment it is decided or it cannot be recovered later.
 *
 * The distinction that matters most is `import` against `subscription`. Both begin as somebody
 * else's iCalendar, and they diverge immediately: an import is ours to edit and to delete, a
 * subscription is neither, and its publisher goes on being the authority. Sharing one field between
 * them would give an imported holiday calendar the refusals meant for a mirror.
 *
 * @param array<string,mixed> $calendar Calendar row.
 * @return string
 */
function axismundi_cal_calendar_source_type( array $calendar ) : string {
	$source = (string) ( $calendar['source'] ?? '' );
	if ( in_array( $source, AXISMUNDI_CAL_SOURCE_TYPES, true ) ) {
		return $source;
	}
	// A row written before the column existed. `kind` is what the answer was derived from then.
	return 'remote' === (string) ( $calendar['kind'] ?? '' ) ? 'subscription' : 'native';
}

/**
 * Whether a Calendar's contents are a dataset rather than authored Events.
 *
 * Holidays, observances, moon phases. Nobody writes an Event onto one of these, whatever role they
 * hold: the entries are maintained as data, so the Event editor has no business offering to file
 * something there. That is a property of the Calendar, not of the person looking at it, which is why
 * it is not expressible as an ACL role.
 *
 * @param array<string,mixed> $calendar Calendar row.
 * @return bool
 */
function axismundi_cal_calendar_is_dataset( array $calendar ) : bool {
	return in_array( axismundi_cal_calendar_source_type( $calendar ), array( 'manual', 'import' ), true );
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
		'manage_items'    => false,
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

	if ( 'system' === (string) ( $calendar['kind'] ?? '' ) ) {
		/*
		 * A dataset this site publishes. It answers to a capability rather than to an owner, which is
		 * the whole reason it has no authority Actor -- so `share` and `publish` are not refusals of
		 * a role somebody lacks, they are operations that mean nothing here. It is public by policy,
		 * and there is nobody to grant that or take it away.
		 *
		 * Whoever may maintain the site's data maintains these. Deliberately `edit_others_posts`
		 * rather than an ACL role: an ACL would imply the Calendar could be shared, which is exactly
		 * what it cannot be.
		 */
		$manager = axismundi_cal_can_manage_all_calendars();
		return array_merge(
			$none,
			array(
				'edit_details'    => $manager,
				'change_timezone' => $manager,
				'rename_locally'  => $subscribed,
				'delete'          => $manager,
				'manage_items'    => $manager,
				// A system feed is not implemented yet. Calling this true would advertise an endpoint that
				// does not serialize system items.
				'export'          => false,
			)
		);
	}

	if ( 'subscription' === axismundi_cal_calendar_source_type( $calendar ) ) {
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
		/*
		 * A dataset Calendar holds entries that are maintained rather than authored, so no role makes
		 * it somewhere to file an Event. Its contents are edited on the screen that owns the dataset.
		 */
		'write_events'    => $writer && ! axismundi_cal_calendar_is_dataset( $calendar ),
		/*
		 * The other half of that answer. A maintained Calendar refuses Events and would otherwise have
		 * no way to be maintained at all -- its entries are added, classified and reviewed on the
		 * screen that owns the dataset, by whoever may write to it.
		 */
		'manage_items'    => $writer && axismundi_cal_calendar_is_dataset( $calendar ),
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
