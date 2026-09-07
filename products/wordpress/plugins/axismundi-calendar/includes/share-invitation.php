<?php
/**
 * Being asked to take a Calendar into your own list.
 *
 * Access and acceptance are two different acts, and Google keeps them apart for a reason worth
 * copying: granting somebody `writer` on a calendar makes them able to write to it immediately, but
 * it does not put it on their screen. That is theirs to decide, and having decided, theirs to undo --
 * which is why the mail says you can hide or remove the calendar at any time. If acceptance granted
 * the access instead, removing it from your list would be giving up the permission.
 *
 *   Calendar ACL          what you may do -- granted at once, the single source of truth
 *   share invitation      that you were asked -- pending, accepted or declined
 *   CalendarList entry    that you took it -- created by accepting, removable without losing access
 *
 * Declining does not revoke anything either. It records that you were asked and said no; the owner
 * still decides who has access, and can ask again.
 *
 * What this file deliberately does not do is deliver. A remote Actor can be resolved and given a
 * rule, but nothing yet carries the invitation to their server or serves them a private calendar
 * they could read -- so a remote invitation is recorded as undeliverable rather than left looking
 * like it arrived. Telling somebody their calendar is shared when the other side has no way to
 * discover or accept it is the one outcome worth writing extra code to avoid.
 *
 * @package AxismundiCalendar
 */

defined( 'ABSPATH' ) || exit;

/** @return string Share invitation table name. */
function axismundi_cal_share_invitations_table() : string {
	global $wpdb;
	return $wpdb->prefix . 'ax_cal_share_invitations';
}

/** Lifecycle of one invitation. `revoked` is not among them: withdrawing access is an ACL act. */
const AXISMUNDI_CAL_INVITATION_STATES = array( 'pending', 'accepted', 'declined' );

/**
 * Whether an Actor is one this site can actually deliver an invitation to.
 *
 * Local only, for now. This is the honest half of "remote Actors are supported": discovery works and
 * a rule can be stored, but neither the invitation nor an authenticated read of a private calendar
 * has anywhere to go yet.
 *
 * @param string $actor_uri Actor URI.
 * @return bool
 */
function axismundi_cal_invitation_deliverable( string $actor_uri ) : bool {
	if ( ! function_exists( 'axismundi_actors_get_by_uri' ) ) {
		return false;
	}
	$actor = axismundi_actors_get_by_uri( trim( $actor_uri ) );
	return $actor instanceof Axismundi_Actor && $actor->is_local();
}

/**
 * Record that one Actor was invited to take a Calendar into their list.
 *
 * Idempotent on `(calendar, recipient)`: re-sharing at a different role updates the standing
 * invitation rather than stacking a second one, and an invitation somebody already accepted is left
 * alone -- they have the calendar, and asking again should not quietly put it back to pending.
 *
 * @param int    $calendar_id  Calendar id.
 * @param string $recipient    Actor invited.
 * @param string $role         Role granted at the time of asking.
 * @param string $invited_by   Actor doing the sharing.
 * @return int|WP_Error Invitation id.
 */
function axismundi_cal_share_invite( int $calendar_id, string $recipient, string $role, string $invited_by = '' ) {
	global $wpdb;
	$recipient = trim( $recipient );
	if ( $calendar_id <= 0 || '' === $recipient ) {
		return new WP_Error( 'ax_cal_invite_target', __( 'An invitation needs a calendar and an Actor.', 'axismundi-calendar' ), array( 'status' => 400 ) );
	}
	if ( ! axismundi_cal_ready() ) {
		return new WP_Error( 'ax_cal_store', __( 'The calendar store is unavailable.', 'axismundi-calendar' ) );
	}
	$existing = axismundi_cal_share_invitation( $calendar_id, $recipient );
	$now      = current_time( 'mysql', true );
	$table    = axismundi_cal_share_invitations_table();
	if ( is_array( $existing ) ) {
		if ( 'accepted' === (string) $existing['state'] ) {
			// Already theirs. The role they hold is the ACL's answer, and it has just been updated.
			return (int) $existing['id'];
		}
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- this plugin's own table.
		$wpdb->update(
			$table,
			array( 'role_at_sent' => $role, 'state' => 'pending', 'responded_at' => null, 'updated_at' => $now ),
			array( 'id' => (int) $existing['id'] )
		);
		return (int) $existing['id'];
	}
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- this plugin's own table.
	$ok = $wpdb->insert(
		$table,
		array(
			'calendar_id'         => $calendar_id,
			'recipient_actor_uri' => $recipient,
			'recipient_uri_hash'  => hash( 'sha256', $recipient ),
			'invited_by_actor_uri' => trim( $invited_by ),
			'role_at_sent'        => $role,
			'state'               => 'pending',
			'created_at'          => $now,
			'updated_at'          => $now,
		)
	);
	return false === $ok
		? new WP_Error( 'ax_cal_invite_write', __( 'The invitation could not be recorded.', 'axismundi-calendar' ) )
		: (int) $wpdb->insert_id;
}

/**
 * One Actor's standing invitation to one Calendar.
 *
 * @param int    $calendar_id Calendar id.
 * @param string $actor_uri   Actor.
 * @return array<string,mixed>|null
 */
function axismundi_cal_share_invitation( int $calendar_id, string $actor_uri ) : ?array {
	global $wpdb;
	if ( $calendar_id <= 0 || '' === trim( $actor_uri ) || ! axismundi_cal_ready() ) {
		return null;
	}
	$table = axismundi_cal_share_invitations_table();
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- keyed lookup in this plugin's own table.
	$row = $wpdb->get_row(
		$wpdb->prepare( "SELECT * FROM {$table} WHERE calendar_id = %d AND recipient_uri_hash = %s", $calendar_id, hash( 'sha256', trim( $actor_uri ) ) ),
		ARRAY_A
	);
	return is_array( $row ) ? $row : null;
}

/**
 * Invitations one Actor has not answered.
 *
 * @param string $actor_uri Actor.
 * @return array<int,array<string,mixed>>
 */
function axismundi_cal_pending_share_invitations( string $actor_uri ) : array {
	global $wpdb;
	if ( '' === trim( $actor_uri ) || ! axismundi_cal_ready() ) {
		return array();
	}
	$table = axismundi_cal_share_invitations_table();
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- indexed lookup in this plugin's own table.
	return (array) $wpdb->get_results(
		$wpdb->prepare( "SELECT * FROM {$table} WHERE recipient_uri_hash = %s AND state = 'pending' ORDER BY created_at ASC", hash( 'sha256', trim( $actor_uri ) ) ),
		ARRAY_A
	);
}

/**
 * Answer an invitation.
 *
 * Accepting is what puts the Calendar in the Actor's own list; declining records the answer and
 * leaves the ACL exactly as it was, because who may read a calendar is the owner's decision and not
 * something a recipient revokes by saying no. Either way the access itself is untouched.
 *
 * The access is checked at the moment of answering rather than trusted from the invitation: it was
 * recorded when the invitation was sent, and an owner may have changed their mind since.
 *
 * @param int    $calendar_id Calendar id.
 * @param string $actor_uri   Answering Actor.
 * @param string $answer      accept|decline.
 * @return true|WP_Error
 */
function axismundi_cal_answer_share_invitation( int $calendar_id, string $actor_uri, string $answer ) {
	global $wpdb;
	$actor_uri  = trim( $actor_uri );
	$invitation = axismundi_cal_share_invitation( $calendar_id, $actor_uri );
	if ( ! is_array( $invitation ) ) {
		return new WP_Error( 'ax_cal_invite_missing', __( 'There is no invitation to that calendar.', 'axismundi-calendar' ), array( 'status' => 404 ) );
	}
	if ( ! in_array( $answer, array( 'accept', 'decline' ), true ) ) {
		return new WP_Error( 'ax_cal_invite_answer', __( 'An invitation is either accepted or declined.', 'axismundi-calendar' ), array( 'status' => 400 ) );
	}
	$now   = current_time( 'mysql', true );
	$table = axismundi_cal_share_invitations_table();

	if ( 'decline' === $answer ) {
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- this plugin's own table.
		$wpdb->update( $table, array( 'state' => 'declined', 'responded_at' => $now, 'updated_at' => $now ), array( 'id' => (int) $invitation['id'] ) );
		return true;
	}

	if ( ! axismundi_cal_can_read( $calendar_id, $actor_uri ) ) {
		return new WP_Error( 'ax_cal_invite_access', __( 'That calendar is no longer shared with you.', 'axismundi-calendar' ), array( 'status' => 403 ) );
	}
	$entry = axismundi_cal_list_set( $calendar_id, $actor_uri );
	if ( is_wp_error( $entry ) ) {
		return $entry;
	}
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- this plugin's own table.
	$wpdb->update( $table, array( 'state' => 'accepted', 'responded_at' => $now, 'updated_at' => $now ), array( 'id' => (int) $invitation['id'] ) );
	return true;
}

/**
 * Forget every invitation to one Calendar.
 *
 * @param int $calendar_id Calendar id.
 * @return void
 */
function axismundi_cal_forget_share_invitations( int $calendar_id ) : void {
	global $wpdb;
	if ( $calendar_id <= 0 || ! axismundi_cal_ready() ) {
		return;
	}
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- this plugin's own table.
	$wpdb->delete( axismundi_cal_share_invitations_table(), array( 'calendar_id' => $calendar_id ), array( '%d' ) );
}

/**
 * Invitations waiting for the person reading the screen.
 *
 * Rendered wherever calendars are listed rather than behind a menu of its own: an invitation nobody
 * finds is one nobody answers, and the answer is what decides whether the calendar shows up at all.
 *
 * @return void
 */
function axismundi_cal_render_share_invitations() : void {
	$actor_uri = axismundi_cal_authoring_actor_uri();
	if ( '' === $actor_uri ) {
		return;
	}
	$pending = axismundi_cal_pending_share_invitations( $actor_uri );
	if ( array() === $pending ) {
		return;
	}
	$roles = axismundi_cal_share_role_labels();
	/*
	 * "Calendar invitations", not "Shared with you". This table holds only what has not been answered,
	 * and an accepted calendar leaves it for the list below -- so a heading naming the relationship
	 * would keep claiming to show everything shared with you while showing the opposite.
	 */
	?>
	<h2><?php esc_html_e( 'Calendar invitations', 'axismundi-calendar' ); ?></h2>
	<p class="description">
		<?php esc_html_e( 'You already have access to these. Accepting adds one to your list; you can hide or remove it afterwards without losing access, and declining leaves the access in place.', 'axismundi-calendar' ); ?>
	</p>
	<table class="wp-list-table widefat fixed striped">
		<thead>
			<tr>
				<th scope="col"><?php esc_html_e( 'Calendar', 'axismundi-calendar' ); ?></th>
				<th scope="col"><?php esc_html_e( 'Shared by', 'axismundi-calendar' ); ?></th>
				<th scope="col"><?php esc_html_e( 'Access', 'axismundi-calendar' ); ?></th>
				<th scope="col"><?php esc_html_e( 'Answer', 'axismundi-calendar' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php foreach ( $pending as $invitation ) : ?>
				<?php
				$calendar = axismundi_cal_calendar_get( (int) $invitation['calendar_id'] );
				if ( ! is_array( $calendar ) ) {
					continue;
				}
				?>
				<tr>
					<td><strong><?php echo esc_html( axismundi_cal_calendar_display_name( $calendar ) ); ?></strong></td>
					<td><?php echo esc_html( axismundi_cal_admin_actor_label( (string) $invitation['invited_by_actor_uri'] ) ); ?></td>
					<td><?php echo esc_html( $roles[ (string) $invitation['role_at_sent'] ] ?? (string) $invitation['role_at_sent'] ); ?></td>
					<td>
						<?php foreach ( array( 'accept' => __( 'Accept', 'axismundi-calendar' ), 'decline' => __( 'Decline', 'axismundi-calendar' ) ) as $ax_answer => $ax_label ) : ?>
							<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline">
								<input type="hidden" name="action" value="ax_cal_answer_invitation">
								<input type="hidden" name="calendar_id" value="<?php echo esc_attr( (string) $invitation['calendar_id'] ); ?>">
								<input type="hidden" name="answer" value="<?php echo esc_attr( $ax_answer ); ?>">
								<?php wp_nonce_field( 'ax_cal_answer_invitation_' . (int) $invitation['calendar_id'] ); ?>
								<button type="submit" class="button button-secondary"><?php echo esc_html( $ax_label ); ?></button>
							</form>
						<?php endforeach; ?>
					</td>
				</tr>
			<?php endforeach; ?>
		</tbody>
	</table>
	<?php
}

/**
 * Answer an invitation from the screen.
 *
 * The answering Actor is the one this user is acting as, never one named in the request: a form field
 * saying who is replying would let anybody accept on somebody else's behalf.
 *
 * @return void
 */
function axismundi_cal_handle_answer_invitation() : void {
	$calendar_id = isset( $_POST['calendar_id'] ) ? absint( wp_unslash( $_POST['calendar_id'] ) ) : 0;
	check_admin_referer( 'ax_cal_answer_invitation_' . $calendar_id );
	$answer = isset( $_POST['answer'] ) && 'accept' === $_POST['answer'] ? 'accept' : 'decline';
	$result = axismundi_cal_answer_share_invitation( $calendar_id, axismundi_cal_authoring_actor_uri(), $answer );
	$base   = admin_url( 'edit.php?post_type=' . AXISMUNDI_CAL_EVENT_POST_TYPE . '&page=ax-calendars' );
	wp_safe_redirect(
		is_wp_error( $result )
			? add_query_arg( 'ax_cal_error', rawurlencode( $result->get_error_code() ), $base )
			: add_query_arg( 'ax_cal_notice', 'accept' === $answer ? 'invitation_accepted' : 'invitation_declined', $base )
	);
	exit;
}
add_action( 'admin_post_ax_cal_answer_invitation', 'axismundi_cal_handle_answer_invitation' );

/**
 * Which section of somebody's calendar list one Calendar belongs in.
 *
 * The question is not how much access somebody has but whether the calendar is theirs to be in:
 * `mine` is a calendar they run or were given access to directly, `subscribed` is one they went and
 * added -- a public collection, or a feed from somewhere else entirely. A `writer` on somebody
 * else's calendar is still a recipient rather than an authority, and a calendar found by its address
 * is a subscription no matter how much of it can be read.
 *
 * @param int    $calendar_id Calendar id.
 * @param string $actor_uri   Viewing Actor.
 * @return string `mine` | `subscribed`
 */
function axismundi_cal_calendar_list_section( int $calendar_id, string $actor_uri ) : string {
	$calendar = axismundi_cal_calendar_get( $calendar_id );
	if ( is_array( $calendar ) && 'remote' === (string) $calendar['kind'] ) {
		// A mirror of somebody else's feed. Nobody here has a relation to it beyond having added it.
		return 'subscribed';
	}
	if ( axismundi_cal_calendar_authority( $calendar_id ) === trim( $actor_uri ) && '' !== trim( $actor_uri ) ) {
		return 'mine';
	}
	return is_array( axismundi_cal_acl_rule( $calendar_id, trim( $actor_uri ), 'actor' ) ) ? 'mine' : 'subscribed';
}
