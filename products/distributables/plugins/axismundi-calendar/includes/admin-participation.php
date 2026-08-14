<?php
/**
 * Who has said what about one Event, and the two answers an organizer can give.
 *
 * Every tab here is a projection of participation state. None of them is a status of its own: an
 * Event has replies in the states the ledger can produce, and a screen inventing a category to sort
 * them into would be a fact that exists only while somebody is looking at it. `Participants` is the
 * accepted replies, `Requests` is the pending ones, and neither is stored as a list.
 *
 * `Invitations` is absent rather than empty. A tab announcing a feature that cannot be used is worse
 * than no tab: it reads as broken to somebody who does not know it is unbuilt, and it invites the
 * question every time. It arrives with the `Invite` that fills it.
 *
 * The buttons post to WordPress rather than to the REST routes. This is a server-rendered screen and
 * a form is what it has; the routes exist for surfaces that are not this one. Both call the same
 * model, so neither is the place any rule lives -- the handler below re-establishes the mode, the
 * state and the authority exactly as the endpoint does, because a screen that drew the wrong button
 * must not be able to produce a state the model would refuse.
 *
 * @package AxismundiCalendar
 */

defined( 'ABSPATH' ) || exit;

/** @return string The participation screen's own admin URL, without an Event. */
function axismundi_cal_participation_admin_url() : string {
	return admin_url( 'edit.php?post_type=' . AXISMUNDI_CAL_EVENT_POST_TYPE . '&page=ax-participation' );
}

/**
 * Register the screen.
 *
 * A real menu title, and left in the menu. Registering it with an empty one and then removing it is
 * the usual trick for a page reached only by link, and it breaks the admin header: the title is read
 * back out of the submenu that was just taken away, so WordPress renders every page load with a null
 * title. Giving it a name and a landing state costs one small index and nothing goes missing.
 *
 * @return void
 */
function axismundi_cal_participation_menu() : void {
	add_submenu_page(
		'edit.php?post_type=' . AXISMUNDI_CAL_EVENT_POST_TYPE,
		__( 'Event participation', 'axismundi-calendar' ),
		__( 'Participation', 'axismundi-calendar' ),
		'edit_posts',
		'ax-participation',
		'axismundi_cal_render_participation_page'
	);
}
add_action( 'admin_menu', 'axismundi_cal_participation_menu', 11 );

/**
 * The way in, from the Events list.
 *
 * Only where there is something to manage. An Event taking no replies has no participation to show,
 * and a link to an empty screen is a promise the Event has explicitly not made.
 *
 * @param array<string,string> $actions Row actions.
 * @param WP_Post              $post    Event post.
 * @return array<string,string>
 */
function axismundi_cal_participation_row_action( array $actions, WP_Post $post ) : array {
	if ( AXISMUNDI_CAL_EVENT_POST_TYPE !== $post->post_type || ! axismundi_cal_can_manage_participation( (int) $post->ID ) ) {
		return $actions;
	}
	$envelope = axismundi_cal_event_get( (int) $post->ID );
	if ( ! is_array( $envelope ) || ! in_array( (string) $envelope['join_mode'], AXISMUNDI_CAL_JOINABLE_MODES, true ) ) {
		return $actions;
	}
	$pending = count( axismundi_cal_event_participations( (int) $post->ID, array( 'pending' ) ) );
	$label   = $pending > 0
		/* translators: %d: number of requests awaiting an answer. */
		? sprintf( _n( 'Participation (%d waiting)', 'Participation (%d waiting)', $pending, 'axismundi-calendar' ), $pending )
		: __( 'Participation', 'axismundi-calendar' );
	$actions['ax_participation'] = sprintf(
		'<a href="%s">%s</a>',
		esc_url( add_query_arg( 'event', (int) $post->ID, axismundi_cal_participation_admin_url() ) ),
		esc_html( $label )
	);
	return $actions;
}
add_filter( 'post_row_actions', 'axismundi_cal_participation_row_action', 10, 2 );

/**
 * How one reply reads on screen.
 *
 * The automatic acceptances are worth separating from the decided ones. Both are an `Accept` in the
 * ledger and the state is the same, but an organizer scanning a list wants to know which of these
 * they personally let in -- and on an open Event the honest answer is none of them.
 *
 * @param array<string,mixed> $row      Participation row.
 * @param string              $mode     The Event's join mode.
 * @return string
 */
function axismundi_cal_participation_state_label( array $row, string $mode ) : string {
	$state = (string) $row['state'];
	if ( 'accepted' === $state ) {
		return 'free' === $mode
			? __( 'Accepted automatically', 'axismundi-calendar' )
			: __( 'Accepted', 'axismundi-calendar' );
	}
	$labels = array(
		'pending'            => __( 'Waiting for an answer', 'axismundi-calendar' ),
		'tentative'          => __( 'Probably coming', 'axismundi-calendar' ),
		'tentative_rejected' => __( 'Probably not coming', 'axismundi-calendar' ),
		'rejected'           => __( 'Turned down', 'axismundi-calendar' ),
		'withdrawn'          => __( 'Request taken back', 'axismundi-calendar' ),
	);
	return $labels[ $state ] ?? $state;
}

/**
 * One person, named as well as this site can name them.
 *
 * The URI when there is nothing better. A remote Actor this site has never cached has no display
 * name here, and inventing one from the URI would put a guess where a fact belongs.
 *
 * @param array<string,mixed> $row Participation row.
 * @return string
 */
function axismundi_cal_participation_actor_label( array $row ) : string {
	$uri   = (string) $row['actor_uri'];
	$actor = function_exists( 'axismundi_actors_get_by_uri' ) ? axismundi_actors_get_by_uri( $uri ) : null;
	if ( ! $actor instanceof Axismundi_Actor ) {
		return $uri;
	}
	$name   = (string) $actor->get_display_name();
	$handle = (string) $actor->get_preferred_username();
	if ( '' === $name ) {
		return '' !== $handle ? '@' . $handle : $uri;
	}
	return '' !== $handle ? $name . ' (@' . $handle . ')' : $name;
}

/**
 * One table of replies.
 *
 * @param array<int,array<string,mixed>> $rows    Participation rows.
 * @param string                         $mode    The Event's join mode.
 * @param int                            $post_id Event post ID.
 * @param bool                           $answerable Whether to offer the two answers.
 * @param string                         $empty   What to say when there are none.
 * @return void
 */
function axismundi_cal_render_participation_table( array $rows, string $mode, int $post_id, bool $answerable, string $empty ) : void {
	if ( array() === $rows ) {
		echo '<p>' . esc_html( $empty ) . '</p>';
		return;
	}
	?>
	<table class="widefat striped">
		<thead>
			<tr>
				<th scope="col"><?php esc_html_e( 'Who', 'axismundi-calendar' ); ?></th>
				<th scope="col"><?php esc_html_e( 'State', 'axismundi-calendar' ); ?></th>
				<th scope="col"><?php esc_html_e( 'Asked', 'axismundi-calendar' ); ?></th>
				<?php if ( $answerable ) : ?>
					<th scope="col"><?php esc_html_e( 'Answer', 'axismundi-calendar' ); ?></th>
				<?php endif; ?>
			</tr>
		</thead>
		<tbody>
		<?php foreach ( $rows as $row ) : ?>
			<tr>
				<td><?php echo esc_html( axismundi_cal_participation_actor_label( $row ) ); ?></td>
				<td><?php echo esc_html( axismundi_cal_participation_state_label( $row, $mode ) ); ?></td>
				<td><?php echo esc_html( mysql2date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), (string) $row['created_at'] ) ); ?></td>
				<?php if ( $answerable ) : ?>
					<td>
						<?php foreach ( array( 'accept' => __( 'Accept', 'axismundi-calendar' ), 'reject' => __( 'Reject', 'axismundi-calendar' ) ) as $decision => $label ) : ?>
							<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline">
								<?php wp_nonce_field( 'ax_cal_participation_' . (int) $row['id'] ); ?>
								<input type="hidden" name="action" value="ax_cal_answer_request">
								<input type="hidden" name="event" value="<?php echo esc_attr( (string) $post_id ); ?>">
								<input type="hidden" name="participation" value="<?php echo esc_attr( (string) $row['id'] ); ?>">
								<input type="hidden" name="decision" value="<?php echo esc_attr( $decision ); ?>">
								<button type="submit" class="button<?php echo 'accept' === $decision ? ' button-primary' : ''; ?>"><?php echo esc_html( $label ); ?></button>
							</form>
						<?php endforeach; ?>
					</td>
				<?php endif; ?>
			</tr>
		<?php endforeach; ?>
		</tbody>
	</table>
	<?php
}

/**
 * The screen.
 *
 * @return void
 */
function axismundi_cal_render_participation_page() : void {
	$post_id = isset( $_GET['event'] ) ? (int) $_GET['event'] : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- naming which Event to read.
	if ( $post_id <= 0 ) {
		axismundi_cal_render_participation_index();
		return;
	}
	$post = get_post( $post_id );
	if ( ! $post instanceof WP_Post || AXISMUNDI_CAL_EVENT_POST_TYPE !== $post->post_type ) {
		wp_die( esc_html__( 'That event does not exist.', 'axismundi-calendar' ), 404 );
	}
	if ( ! axismundi_cal_can_manage_participation( $post_id ) ) {
		wp_die( esc_html__( 'Replies to this event are not yours to read.', 'axismundi-calendar' ), 403 );
	}
	$envelope   = axismundi_cal_event_get( $post_id );
	$mode       = is_array( $envelope ) ? (string) $envelope['join_mode'] : 'none';
	$capacity   = is_array( $envelope ) && null !== $envelope['maximum_attendee_capacity'] ? (int) $envelope['maximum_attendee_capacity'] : null;
	$accepted   = axismundi_cal_event_participations( $post_id, array( 'accepted' ) );
	$pending    = axismundi_cal_event_participations( $post_id, array( 'pending' ) );
	$remaining  = axismundi_cal_event_remaining_capacity( $post_id );
	$modes      = array(
		'free'       => __( 'Admitted immediately', 'axismundi-calendar' ),
		'restricted' => __( 'Admitted after approval', 'axismundi-calendar' ),
		'invite'     => __( 'Invitation only', 'axismundi-calendar' ),
		'external'   => __( 'Handled elsewhere', 'axismundi-calendar' ),
		'none'       => __( 'Taking no replies', 'axismundi-calendar' ),
	);
	?>
	<div class="wrap">
		<h1><?php echo esc_html( get_the_title( $post ) ); ?></h1>
		<p><a href="<?php echo esc_url( (string) get_edit_post_link( $post_id ) ); ?>"><?php esc_html_e( '&larr; Back to the event', 'axismundi-calendar' ); ?></a></p>

		<?php axismundi_cal_participation_notice(); ?>

		<h2><?php esc_html_e( 'Overview', 'axismundi-calendar' ); ?></h2>
		<table class="widefat striped" style="max-width:40em">
			<tbody>
				<tr>
					<th scope="row"><?php esc_html_e( 'Participation', 'axismundi-calendar' ); ?></th>
					<td><?php echo esc_html( $modes[ $mode ] ?? $mode ); ?></td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Coming', 'axismundi-calendar' ); ?></th>
					<td><?php echo esc_html( (string) count( $accepted ) ); ?></td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Waiting for an answer', 'axismundi-calendar' ); ?></th>
					<td><?php echo esc_html( (string) count( $pending ) ); ?></td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Capacity', 'axismundi-calendar' ); ?></th>
					<td>
						<?php
						/*
						 * No limit is not a limit of nought, and the two must not read the same. An
						 * organizer glancing at "0 left" on an Event that never had a capacity would
						 * believe it was full.
						 */
						echo null === $capacity
							? esc_html__( 'No limit', 'axismundi-calendar' )
							/* translators: 1: places remaining, 2: total places. */
							: esc_html( sprintf( __( '%1$d of %2$d places left', 'axismundi-calendar' ), (int) $remaining, $capacity ) );
						?>
					</td>
				</tr>
			</tbody>
		</table>

		<h2><?php esc_html_e( 'Participants', 'axismundi-calendar' ); ?></h2>
		<?php
		/*
		 * The accepted replies alone. Capacity and the FEP `attendees` collection both count this one
		 * state, so a list that showed anything else beside them would be a headcount that disagrees
		 * with the number above it.
		 */
		axismundi_cal_render_participation_table(
			$accepted,
			$mode,
			$post_id,
			false,
			__( 'Nobody is coming yet.', 'axismundi-calendar' )
		);
		?>

		<?php if ( 'restricted' === $mode ) : ?>
			<h2><?php esc_html_e( 'Requests', 'axismundi-calendar' ); ?></h2>
			<?php
			/*
			 * Only where requests are actually held. An Event admitting people on arrival has nothing
			 * waiting, and a section offering to approve them would be a decision that was already made.
			 */
			axismundi_cal_render_participation_table(
				$pending,
				$mode,
				$post_id,
				true,
				__( 'No requests are waiting.', 'axismundi-calendar' )
			);
			?>
		<?php endif; ?>
	</div>
	<?php
}

/**
 * Which Events have participation to manage.
 *
 * What the menu item lands on, since participation is about one Event and there is no sensible one to
 * default to. Only the Events that take replies and only the ones this user runs: an index listing
 * Events they cannot open would be a list of doors that do not turn.
 *
 * @return void
 */
function axismundi_cal_render_participation_index() : void {
	$events = get_posts(
		array(
			'post_type'      => AXISMUNDI_CAL_EVENT_POST_TYPE,
			'post_status'    => array( 'publish', 'draft', 'pending', 'future', 'private' ),
			'posts_per_page' => 100,
			'orderby'        => 'date',
			'order'          => 'DESC',
		)
	);
	$rows = array();
	foreach ( $events as $event ) {
		$envelope = axismundi_cal_event_get( (int) $event->ID );
		if ( ! is_array( $envelope )
			|| ! in_array( (string) $envelope['join_mode'], AXISMUNDI_CAL_JOINABLE_MODES, true )
			|| ! axismundi_cal_can_manage_participation( (int) $event->ID )
		) {
			continue;
		}
		$rows[] = array(
			'post'     => $event,
			'accepted' => count( axismundi_cal_event_participations( (int) $event->ID, array( 'accepted' ) ) ),
			'pending'  => count( axismundi_cal_event_participations( (int) $event->ID, array( 'pending' ) ) ),
		);
	}
	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'Event participation', 'axismundi-calendar' ); ?></h1>
		<?php if ( array() === $rows ) : ?>
			<p><?php esc_html_e( 'None of your events are taking replies yet. Turn participation on from an event to manage who is coming.', 'axismundi-calendar' ); ?></p>
		<?php else : ?>
			<table class="widefat striped">
				<thead>
					<tr>
						<th scope="col"><?php esc_html_e( 'Event', 'axismundi-calendar' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Coming', 'axismundi-calendar' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Waiting for an answer', 'axismundi-calendar' ); ?></th>
					</tr>
				</thead>
				<tbody>
				<?php foreach ( $rows as $row ) : ?>
					<tr>
						<td>
							<a href="<?php echo esc_url( add_query_arg( 'event', (int) $row['post']->ID, axismundi_cal_participation_admin_url() ) ); ?>">
								<?php echo esc_html( get_the_title( $row['post'] ) ); ?>
							</a>
						</td>
						<td><?php echo esc_html( (string) $row['accepted'] ); ?></td>
						<td><?php echo esc_html( (string) $row['pending'] ); ?></td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
		<?php endif; ?>
	</div>
	<?php
}

/**
 * Answer one request.
 *
 * Every condition is established here and not read from the form. The screen only draws these buttons
 * on a moderated Event with a pending request, but a form can be submitted by anything -- so the
 * authority, the mode and the state are all checked again, and the model is asked rather than
 * imitated.
 *
 * @return void
 */
function axismundi_cal_handle_participation_answer() : void {
	$post_id  = isset( $_POST['event'] ) ? (int) $_POST['event'] : 0;
	$row_id   = isset( $_POST['participation'] ) ? (int) $_POST['participation'] : 0;
	$decision = isset( $_POST['decision'] ) ? sanitize_key( wp_unslash( (string) $_POST['decision'] ) ) : '';
	check_admin_referer( 'ax_cal_participation_' . $row_id );
	$base = add_query_arg( 'event', $post_id, axismundi_cal_participation_admin_url() );

	if ( ! axismundi_cal_can_manage_participation( $post_id ) ) {
		wp_die( esc_html__( 'Answering requests for this event is not yours to do.', 'axismundi-calendar' ), 403 );
	}
	$envelope = axismundi_cal_event_get( $post_id );
	if ( ! is_array( $envelope ) || 'restricted' !== (string) $envelope['join_mode'] ) {
		wp_safe_redirect( add_query_arg( 'ax_cal_error', 'ax_cal_not_moderated', $base ) );
		exit;
	}
	$participation = axismundi_cal_participation_by_id( $post_id, $row_id );
	if ( ! is_array( $participation ) ) {
		wp_safe_redirect( add_query_arg( 'ax_cal_error', 'ax_cal_participation_missing', $base ) );
		exit;
	}
	$state = axismundi_cal_event_respond_to_join( $post_id, (string) $participation['actor_uri'], $decision );
	if ( is_wp_error( $state ) ) {
		wp_safe_redirect( add_query_arg( 'ax_cal_error', rawurlencode( $state->get_error_code() ), $base ) );
		exit;
	}
	wp_safe_redirect( add_query_arg( 'ax_cal_notice', $state, $base ) );
	exit;
}
add_action( 'admin_post_ax_cal_answer_request', 'axismundi_cal_handle_participation_answer' );

/**
 * Say how the last answer went.
 *
 * @return void
 */
function axismundi_cal_participation_notice() : void {
	// phpcs:disable WordPress.Security.NonceVerification.Recommended -- reading back a redirect this file wrote.
	$notice = isset( $_GET['ax_cal_notice'] ) ? sanitize_key( wp_unslash( (string) $_GET['ax_cal_notice'] ) ) : '';
	$error  = isset( $_GET['ax_cal_error'] ) ? sanitize_key( wp_unslash( (string) $_GET['ax_cal_error'] ) ) : '';
	// phpcs:enable WordPress.Security.NonceVerification.Recommended
	$notices = array(
		'accepted' => __( 'They are coming.', 'axismundi-calendar' ),
		'rejected' => __( 'That request was turned down.', 'axismundi-calendar' ),
	);
	$errors  = array(
		'ax_event_accept_full'          => __( 'This event is full, so nobody else can be accepted. Raise the capacity or make room first.', 'axismundi-calendar' ),
		'ax_event_respond_answered'     => __( 'That request had already been answered.', 'axismundi-calendar' ),
		'ax_event_respond_no_host'      => __( 'This event has no host actor to answer with.', 'axismundi-calendar' ),
		'ax_cal_not_moderated'          => __( 'This event does not hold requests for approval.', 'axismundi-calendar' ),
		'ax_cal_participation_missing'  => __( 'That request is not one of this event\'s.', 'axismundi-calendar' ),
	);
	if ( isset( $notices[ $notice ] ) ) {
		printf( '<div class="notice notice-success is-dismissible"><p>%s</p></div>', esc_html( $notices[ $notice ] ) );
	}
	if ( '' !== $error ) {
		printf(
			'<div class="notice notice-error is-dismissible"><p>%s</p></div>',
			esc_html( $errors[ $error ] ?? __( 'That request could not be answered.', 'axismundi-calendar' ) )
		);
	}
}
