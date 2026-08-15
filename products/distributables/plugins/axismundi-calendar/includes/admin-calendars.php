<?php
/**
 * The Calendars management screen.
 *
 * Ownership is recorded now, while calendars are public and every logged-in manager can see all of
 * them, because it is the answer to "whose calendar is this?" -- and that question becomes
 * unanswerable retroactively. Private calendars, sharing and subscription secrets all need it, and
 * reconstructing ownership after the fact means guessing from post authorship or from who happened
 * to edit last.
 *
 * Authority is recorded on the Calendar itself; ACL and CalendarList entries are separate relations
 * for access and personal UI state. It is chosen at creation because ownership transfer needs an
 * explicit federation policy that does not exist yet.
 *
 * @package AxismundiCalendar
 */

defined( 'ABSPATH' ) || exit;

/**
 * Whether the current user may reach the Calendars screen at all.
 *
 * Publishing is the floor: a calendar is a public, subscribable surface, so making one is closer to
 * publishing than to drafting.
 *
 * @return bool
 */
function axismundi_cal_can_manage_calendars() : bool {
	return current_user_can( 'publish_posts' );
}

/**
 * Whether the current user may change one particular Calendar.
 *
 * Two ways in: moderation capability over other people's content, or being the Actor the calendar
 * belongs to. The second is why ownership is recorded from the start -- without it the only
 * available rule would be "anyone who can publish may edit anyone's calendar".
 *
 * @param array<string,mixed>|null $calendar Calendar row, or null for a new one.
 * @return bool
 */
function axismundi_cal_can_manage_calendar( ?array $calendar ) : bool {
	if ( ! axismundi_cal_can_manage_calendars() ) {
		return false;
	}
	if ( null === $calendar || current_user_can( 'edit_others_posts' ) ) {
		return true;
	}
	$calendar_id = (int) ( $calendar['id'] ?? 0 );
	if ( 'remote' === (string) ( $calendar['kind'] ?? '' ) && is_array( axismundi_cal_list_entry( $calendar_id, axismundi_cal_current_actor_uri() ) ) ) {
		// This only permits removing the caller's personal CalendarList entry. The source remains
		// read-only and no local authority or write rule is inferred from subscribing to it.
		return true;
	}
	/*
	 * The ACL is the source, so the admin screen, the REST API and any sharing UI answer the same
	 * question the same way. It also covers the case a per-Calendar column never could: a Calendar
	 * belonging to a managed Group, administered by that Group's managers.
	 */
	if ( axismundi_cal_can_write( $calendar_id, axismundi_cal_current_actor_uri(), get_current_user_id() ) ) {
		return true;
	}
	$owner = axismundi_cal_calendar_owner( $calendar_id );
	if ( '' === $owner ) {
		// An unowned calendar predates ownership being recorded, or was made by a route that did not
		// set it. Treated as moderator-only rather than as everyone's, since the safe reading of "no
		// owner" is not "any owner".
		return false;
	}
	return $owner === axismundi_cal_current_actor_uri();
}

/**
 * The Actor URI of the current user, when this site has Actors at all.
 *
 * Optional by design: the calendar engine works without Object Projections or Actors installed, so
 * ownership degrades to unset rather than blocking the screen.
 *
 * @return string
 */
function axismundi_cal_current_actor_uri() : string {
	$user_id = get_current_user_id();
	// Through the shared gate rather than its own `function_exists`, so ownership and the projection
	// cannot disagree about whether this site has identity at all.
	if ( $user_id <= 0 || ! axismundi_cal_federation_ready() ) {
		return '';
	}
	return (string) axismundi_op_local_author_actor_uri( $user_id );
}

/**
 * Human-friendly identity for a stored Actor URI.
 *
 * @param string $actor_uri Actor URI.
 * @return string
 */
function axismundi_cal_admin_actor_label( string $actor_uri ) : string {
	$actor_uri = trim( $actor_uri );
	if ( '' === $actor_uri || ! function_exists( 'axismundi_actors_get_by_uri' ) ) {
		return '';
	}
	$actor = axismundi_actors_get_by_uri( $actor_uri );
	if ( ! $actor instanceof Axismundi_Actor ) {
		return $actor_uri;
	}
	$handle = trim( (string) $actor->get_preferred_username() );
	return trim( (string) $actor->get_display_name() . ( '' !== $handle ? ' (@' . $handle . ')' : '' ) );
}

/**
 * Local Actors the current user may choose while creating a Calendar.
 *
 * A Person may create a Calendar for themselves. A managed Group is offered only to its managers,
 * which reuses Actors' own authority rule rather than making Calendar infer Group membership. An
 * existing Calendar never accepts this input: ownership transfer remains deliberately unavailable.
 *
 * @return array<string,string> Actor URI => label.
 */
function axismundi_cal_admin_creatable_authorities() : array {
	if ( ! axismundi_cal_has_actors() ) {
		return array();
	}
	$choices = array();
	$user_id = get_current_user_id();
	if ( $user_id > 0 && function_exists( 'axismundi_actors_get_for_user' ) ) {
		$actor = axismundi_actors_get_for_user( $user_id );
		if ( $actor instanceof Axismundi_Actor && $actor->is_local() ) {
			$choices[ $actor->get_uri() ] = axismundi_cal_admin_actor_label( $actor->get_uri() );
		}
	}
	if ( $user_id > 0 && function_exists( 'axismundi_actors_list_manageable_actors' ) ) {
		foreach ( axismundi_actors_list_manageable_actors( $user_id, 'manager' ) as $actor ) {
			if ( $actor instanceof Axismundi_Actor && $actor->is_local() ) {
				$choices[ $actor->get_uri() ] = axismundi_cal_admin_actor_label( $actor->get_uri() );
			}
		}
	}
	return $choices;
}

/**
 * Whether this user may see every Calendar on the instance, not only their own.
 *
 * A separate question from managing one Calendar. The per-Actor list is what somebody works in; this
 * is the inventory a site administrator needs to find a Calendar nobody is looking after -- an
 * orphan from an upgrade, a subscription whose source is failing, a Calendar whose owner has left.
 * Without it those are unreachable, because scoping the list to the caller's own Actor is exactly
 * what hides them.
 *
 * @return bool
 */
function axismundi_cal_can_manage_all_calendars() : bool {
	return current_user_can( 'edit_others_posts' );
}

/**
 * Every Calendar on the instance, for the administrator inventory.
 *
 * @return array<int,array<string,mixed>>
 */
function axismundi_cal_all_calendar_rows() : array {
	global $wpdb;
	if ( ! axismundi_cal_can_manage_all_calendars() || ! axismundi_cal_ready() ) {
		return array();
	}
	$table = axismundi_cal_calendars_table();
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- administrator inventory over this plugin's own table.
	return (array) $wpdb->get_results( "SELECT * FROM {$table} ORDER BY kind ASC, name ASC", ARRAY_A );
}

/**
 * Calendars this Actor can operate from the management screen.
 *
 * Sources are cached once per instance, while CalendarList membership is personal. A remote
 * Calendar therefore appears only when this Actor added it; local Calendars appear through a write
 * rule or an authority the user manages. This is intentionally narrower than moderator lookup.
 *
 * @return array<int,array<string,mixed>>
 */
function axismundi_cal_admin_calendar_rows() : array {
	$actor_uri = axismundi_cal_current_actor_uri();
	if ( '' === $actor_uri ) {
		return array();
	}
	$ids  = array_values( array_unique( array_merge( axismundi_cal_actor_calendar_ids( $actor_uri ), axismundi_cal_user_authority_calendar_ids( get_current_user_id() ) ) ) );
	$rows = array();
	foreach ( $ids as $calendar_id ) {
		$calendar = axismundi_cal_calendar_get( $calendar_id );
		if ( ! is_array( $calendar ) ) {
			continue;
		}
		if ( 'remote' === (string) $calendar['kind'] ) {
			if ( ! is_array( axismundi_cal_list_entry( $calendar_id, $actor_uri ) ) ) {
				continue;
			}
		} elseif ( ! axismundi_cal_can_write( $calendar_id, $actor_uri, get_current_user_id() ) ) {
			continue;
		}
		$rows[] = $calendar;
	}
	usort( $rows, static fn( array $left, array $right ) : int => strcasecmp( axismundi_cal_calendar_display_name( $left ), axismundi_cal_calendar_display_name( $right ) ) );
	return $rows;
}

/**
 * Register the Calendars screen beneath Events.
 *
 * @return void
 */
function axismundi_cal_admin_menu() : void {
	add_submenu_page(
		'edit.php?post_type=' . AXISMUNDI_CAL_EVENT_POST_TYPE,
		__( 'Calendars', 'axismundi-calendar' ),
		__( 'Calendars', 'axismundi-calendar' ),
		'publish_posts',
		'ax-calendars',
		'axismundi_cal_render_calendars_page'
	);
}
add_action( 'admin_menu', 'axismundi_cal_admin_menu' );

/**
 * Handle a submitted Calendar form.
 *
 * Runs on `admin_post` rather than inside the render, so a successful save redirects and cannot be
 * repeated by a page refresh.
 *
 * @return void
 */
function axismundi_cal_handle_calendar_form() : void {
	$base = admin_url( 'edit.php?post_type=' . AXISMUNDI_CAL_EVENT_POST_TYPE . '&page=ax-calendars' );
	if ( ! axismundi_cal_can_manage_calendars() ) {
		wp_die( esc_html__( 'You are not allowed to manage calendars.', 'axismundi-calendar' ), 403 );
	}

	$id       = isset( $_POST['calendar_id'] ) ? absint( wp_unslash( $_POST['calendar_id'] ) ) : 0;
	$existing = $id > 0 ? axismundi_cal_calendar_get( $id ) : null;
	// Bound to the row being changed, so a nonce for one calendar cannot be replayed against another.
	check_admin_referer( 'ax_cal_save_' . $id );
	if ( $id > 0 && ! is_array( $existing ) ) {
		wp_safe_redirect( add_query_arg( 'ax_cal_error', 'missing', $base ) );
		exit;
	}
	if ( ! axismundi_cal_can_manage_calendar( $existing ) ) {
		wp_die( esc_html__( 'You are not allowed to manage that calendar.', 'axismundi-calendar' ), 403 );
	}

	$action = isset( $_POST['ax_cal_action'] ) ? sanitize_key( wp_unslash( (string) $_POST['ax_cal_action'] ) ) : 'save';
	if ( 'delete' === $action && $id > 0 ) {
		if ( 'remote' === (string) $existing['kind'] ) {
			// Through the same decision the subscriptions screen makes, so "unsubscribe" cannot mean
			// two different things depending on which screen it was pressed from.
			$source = axismundi_cal_source_for_calendar( $id );
			if ( is_array( $source ) ) {
				axismundi_cal_release_subscription( (int) $source['id'], axismundi_cal_current_actor_uri() );
			} else {
				axismundi_cal_list_remove( $id, axismundi_cal_current_actor_uri() );
			}
		} elseif ( ! empty( $existing['is_primary'] ) ) {
			/*
			 * Somebody's default Calendar. Deleting it would leave their next Event with nowhere to be
			 * filed, and the writer would simply make another -- so the refusal is the honest answer
			 * rather than a delete that quietly undoes itself.
			 */
			wp_safe_redirect( add_query_arg( array( 'ax_cal_error' => 'ax_cal_primary', 'ax_cal_edit' => $id ), $base ) );
			exit;
		} elseif ( ! axismundi_cal_calendar_delete( $id ) ) {
			wp_safe_redirect( add_query_arg( array( 'ax_cal_error' => 'ax_cal_delete', 'ax_cal_edit' => $id ), $base ) );
			exit;
		}
		wp_safe_redirect( add_query_arg( 'ax_cal_notice', 'deleted', $base ) );
		exit;
	}
	if ( is_array( $existing ) && 'remote' === (string) $existing['kind'] ) {
		/*
		 * A subscribed Calendar is published elsewhere, so its name, description and timezone are its
		 * publisher's to change. What is editable here is what somebody calls it in their own list --
		 * which is a `CalendarListEntry` alias and changes nothing for anyone else following the same
		 * feed. Google draws the line in the same place, and for the same reason.
		 */
		$alias   = isset( $_POST['name'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['name'] ) ) : '';
		$actor   = axismundi_cal_current_actor_uri();
		$renamed = '' !== $actor
			? axismundi_cal_list_set( $id, $actor, 'reader', array( 'summary_override' => $alias === (string) $existing['name'] ? '' : $alias ) )
			: new WP_Error( 'ax_cal_no_actor', '' );
		if ( is_wp_error( $renamed ) ) {
			wp_safe_redirect( add_query_arg( array( 'ax_cal_error' => 'readonly', 'ax_cal_edit' => $id ), $base ) );
			exit;
		}
		wp_safe_redirect( add_query_arg( array( 'ax_cal_notice' => 'renamed', 'ax_cal_edit' => $id ), $base ) );
		exit;
	}

	$fields = array(
		'name'        => isset( $_POST['name'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['name'] ) ) : '',
		'slug'        => isset( $_POST['slug'] ) ? sanitize_title( wp_unslash( (string) $_POST['slug'] ) ) : '',
		'description' => isset( $_POST['description'] ) ? sanitize_textarea_field( wp_unslash( (string) $_POST['description'] ) ) : '',
	);
	$fields['timezone'] = isset( $_POST['timezone'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['timezone'] ) ) : '';
	/*
	 * An authority is settable while creating, and on a Calendar an upgrade left without one. The
	 * second is not a transfer -- there is nothing to move -- and `record_owner()` still refuses to
	 * change an authority that is already set, so this cannot become one.
	 */
	$unassigned = is_array( $existing ) && 'local' === (string) $existing['kind'] && '' === (string) $existing['authority_actor_uri'];
	if ( null === $existing || $unassigned ) {
		$choices = axismundi_cal_admin_creatable_authorities();
		$chosen  = isset( $_POST['owner_actor_uri'] ) ? esc_url_raw( wp_unslash( (string) $_POST['owner_actor_uri'] ) ) : '';
		// Never trust a submitted URI as an ownership grant. It must be the current Person Actor or a
		// managed Group the current user may operate.
		$fields['owner_actor_uri'] = isset( $choices[ $chosen ] ) ? $chosen : axismundi_cal_current_actor_uri();
	}

	$saved = axismundi_cal_calendar_save( $fields, $id );
	if ( is_wp_error( $saved ) ) {
		wp_safe_redirect( add_query_arg( array( 'ax_cal_error' => rawurlencode( $saved->get_error_code() ), 'ax_cal_edit' => $id ), $base ) );
		exit;
	}
	wp_safe_redirect( add_query_arg( 'ax_cal_notice', $id > 0 ? 'updated' : 'created', $base ) );
	exit;
}
add_action( 'admin_post_ax_cal_save_calendar', 'axismundi_cal_handle_calendar_form' );

/**
 * Subscribe this instance to one public ICS Calendar.
 *
 * @return void
 */
function axismundi_cal_handle_calendar_subscription() : void {
	$base = admin_url( 'edit.php?post_type=' . AXISMUNDI_CAL_EVENT_POST_TYPE . '&page=ax-calendars' );
	if ( ! axismundi_cal_can_manage_calendars() ) {
		wp_die( esc_html__( 'You are not allowed to manage calendars.', 'axismundi-calendar' ), 403 );
	}
	check_admin_referer( 'ax_cal_subscribe' );
	$url    = isset( $_POST['source_url'] ) ? esc_url_raw( wp_unslash( (string) $_POST['source_url'] ) ) : '';
	$source = axismundi_cal_subscribe_url( $url );
	if ( is_wp_error( $source ) ) {
		wp_safe_redirect( add_query_arg( 'ax_cal_error', rawurlencode( $source->get_error_code() ), $base ) );
		exit;
	}
	$actor_uri = axismundi_cal_current_actor_uri();
	if ( '' === $actor_uri || is_wp_error( axismundi_cal_list_set( (int) $source, $actor_uri ) ) ) {
		wp_safe_redirect( add_query_arg( 'ax_cal_error', 'ax_cal_authority', $base ) );
		exit;
	}
	wp_safe_redirect( add_query_arg( array( 'ax_cal_notice' => 'subscribed', 'ax_cal_edit' => (int) $source ), $base ) );
	exit;
}
add_action( 'admin_post_ax_cal_subscribe_calendar', 'axismundi_cal_handle_calendar_subscription' );

/**
 * The message for one error code.
 *
 * The writer's refusals are already author-facing sentences, so this only maps the codes the screen
 * itself can produce.
 *
 * @param string $code Error code.
 * @return string
 */
function axismundi_cal_admin_error_message( string $code ) : string {
	switch ( $code ) {
		case 'ax_cal_slug_taken':
			return __( 'Another calendar already uses that slug. Subscribers follow the slug, so it has to stay unique.', 'axismundi-calendar' );
		case 'ax_cal_name':
			return __( 'A calendar needs a name.', 'axismundi-calendar' );
		case 'ax_cal_slug':
			return __( 'A calendar needs a slug that can appear in a URL.', 'axismundi-calendar' );
		case 'ax_cal_timezone':
			return __( 'A calendar needs a named IANA timezone such as Asia/Seoul. Fixed offsets cannot follow daylight-saving rules.', 'axismundi-calendar' );
		case 'readonly':
			return __( 'A subscribed calendar is read-only. Change it at its source.', 'axismundi-calendar' );
		case 'ax_cal_source_url':
		case 'ax_cal_source_private':
		case 'ax_cal_source_write':
			return __( 'That public iCalendar address could not be added.', 'axismundi-calendar' );
		case 'missing':
		case 'ax_cal_missing':
			return __( 'That calendar no longer exists.', 'axismundi-calendar' );
		case 'ax_cal_last_owner':
			return __( 'A calendar cannot be left without an owner. Give somebody else full access first.', 'axismundi-calendar' );
		case 'ax_cal_acl_role':
			return __( 'That is not a level of access this calendar can grant.', 'axismundi-calendar' );
		case 'ax_cal_acl_principal':
			return __( 'Sharing with a person or group needs their Actor address.', 'axismundi-calendar' );
		case 'ax_cal_acl_public_role':
			return __( 'Anyone can be allowed to read or to see free/busy time, but not to write.', 'axismundi-calendar' );
		case 'ax_cal_authority':
			return __( 'A local calendar needs an Actor to belong to.', 'axismundi-calendar' );
		case 'ax_cal_authority_locked':
			return __( 'Ownership transfer is not available yet.', 'axismundi-calendar' );
		case 'ax_cal_primary':
			return __( 'This is the default calendar for its Actor, so it cannot be deleted. Make another calendar the default first, or empty this one.', 'axismundi-calendar' );
		case 'ax_cal_delete':
			return __( 'The calendar could not be deleted.', 'axismundi-calendar' );
		case 'ax_cal_source_missing':
			return __( 'That subscription no longer exists.', 'axismundi-calendar' );
		case 'ax_cal_source_fetch':
		case 'ax_cal_source_parse':
			return __( 'The calendar could not be fetched from that address.', 'axismundi-calendar' );
		default:
			return __( 'The calendar could not be saved.', 'axismundi-calendar' );
	}
}

/**
 * Render the Calendars screen.
 *
 * @return void
 */
function axismundi_cal_render_calendars_page() : void {
	global $wpdb;
	if ( ! axismundi_cal_can_manage_calendars() ) {
		wp_die( esc_html__( 'You are not allowed to manage calendars.', 'axismundi-calendar' ), 403 );
	}
	$base = admin_url( 'edit.php?post_type=' . AXISMUNDI_CAL_EVENT_POST_TYPE . '&page=ax-calendars' );

	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only screen selection.
	$editing  = isset( $_GET['ax_cal_edit'] ) ? absint( wp_unslash( $_GET['ax_cal_edit'] ) ) : 0;
	$calendar = $editing > 0 ? axismundi_cal_calendar_get( $editing ) : null;
	if ( $editing > 0 && ! axismundi_cal_can_manage_calendar( $calendar ) ) {
		wp_die( esc_html__( 'You are not allowed to manage that calendar.', 'axismundi-calendar' ), 403 );
	}

	$rows = axismundi_cal_admin_calendar_rows();

	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only notice.
	$notice = isset( $_GET['ax_cal_notice'] ) ? sanitize_key( wp_unslash( (string) $_GET['ax_cal_notice'] ) ) : '';
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only notice.
	$error = isset( $_GET['ax_cal_error'] ) ? sanitize_key( wp_unslash( (string) $_GET['ax_cal_error'] ) ) : '';
	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'Calendars', 'axismundi-calendar' ); ?></h1>
		<p class="description">
			<?php esc_html_e( 'Create a Calendar for local Events, or subscribe this site to a public iCalendar address.', 'axismundi-calendar' ); ?>
		</p>

		<?php if ( '' !== $notice ) : ?>
			<div class="notice notice-success is-dismissible"><p><?php echo esc_html__( 'Calendar saved.', 'axismundi-calendar' ); ?></p></div>
		<?php endif; ?>
		<?php if ( '' !== $error ) : ?>
			<div class="notice notice-error"><p><?php echo esc_html( axismundi_cal_admin_error_message( $error ) ); ?></p></div>
		<?php endif; ?>

		<?php
		/*
		 * Calendars an upgrade could not attribute to anybody. Shown only to people who can act on
		 * them, and worded as what it costs rather than as a warning with no consequence: these
		 * Calendars keep working locally, and only their federation is stopped, which is not
		 * something an administrator would otherwise notice.
		 */
		$orphans = axismundi_cal_can_manage_all_calendars() ? axismundi_cal_orphan_calendars() : array();
		?>
		<?php if ( array() !== $orphans ) : ?>
			<div class="notice notice-warning">
				<p>
					<?php
					echo esc_html(
						sprintf(
							/* translators: %d: number of calendars. */
							_n(
								'%d calendar has no Actor, so its Events are withheld from other servers.',
								'%d calendars have no Actor, so their Events are withheld from other servers.',
								count( $orphans ),
								'axismundi-calendar'
							),
							count( $orphans )
						)
					);
					?>
					<?php esc_html_e( 'They remain readable on this site. Assign an Actor to each, or delete it along with the Events it holds.', 'axismundi-calendar' ); ?>
				</p>
				<ul>
					<?php foreach ( $orphans as $orphan ) : ?>
						<li>
							<a href="<?php echo esc_url( add_query_arg( 'ax_cal_edit', (int) $orphan['id'], $base ) ); ?>"><?php echo esc_html( axismundi_cal_calendar_display_name( $orphan ) ); ?></a>
							<?php echo esc_html( sprintf( /* translators: %d: number of events. */ _n( '%d event', '%d events', count( axismundi_cal_calendar_event_ids( (int) $orphan['id'] ) ), 'axismundi-calendar' ), count( axismundi_cal_calendar_event_ids( (int) $orphan['id'] ) ) ) ); ?>
						</li>
					<?php endforeach; ?>
				</ul>
			</div>
		<?php endif; ?>

		<?php
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only screen selection.
		$show_all = isset( $_GET['ax_cal_scope'] ) && 'all' === sanitize_key( wp_unslash( (string) $_GET['ax_cal_scope'] ) );
		?>
		<?php if ( axismundi_cal_can_manage_all_calendars() ) : ?>
			<p>
				<?php if ( $show_all ) : ?>
					<a href="<?php echo esc_url( $base ); ?>"><?php esc_html_e( '&larr; Back to my calendars', 'axismundi-calendar' ); ?></a>
				<?php else : ?>
					<a href="<?php echo esc_url( add_query_arg( 'ax_cal_scope', 'all', $base ) ); ?>"><?php esc_html_e( 'View every calendar on this site', 'axismundi-calendar' ); ?></a>
				<?php endif; ?>
			</p>
		<?php endif; ?>

		<?php if ( $show_all && axismundi_cal_can_manage_all_calendars() ) : ?>
			<h2><?php esc_html_e( 'Every calendar on this site', 'axismundi-calendar' ); ?></h2>
			<p class="description"><?php esc_html_e( 'The administrator inventory. This is not a calendar list: appearing here is not a relation to any of these calendars, and nothing here is added to your own.', 'axismundi-calendar' ); ?></p>
			<table class="wp-list-table widefat fixed striped">
				<thead>
					<tr>
						<th scope="col"><?php esc_html_e( 'Name', 'axismundi-calendar' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Slug', 'axismundi-calendar' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Type', 'axismundi-calendar' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Owner', 'axismundi-calendar' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Events', 'axismundi-calendar' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( axismundi_cal_all_calendar_rows() as $row ) : ?>
						<?php $row_authority = (string) $row['authority_actor_uri']; ?>
						<tr>
							<td><strong><a href="<?php echo esc_url( add_query_arg( 'ax_cal_edit', (int) $row['id'], $base ) ); ?>"><?php echo esc_html( axismundi_cal_calendar_display_name( $row ) ); ?></a></strong></td>
							<td><code><?php echo esc_html( (string) $row['slug'] ); ?></code></td>
							<td><?php echo esc_html( 'remote' === (string) $row['kind'] ? __( 'Subscribed', 'axismundi-calendar' ) : __( 'Local', 'axismundi-calendar' ) ); ?></td>
							<td>
								<?php if ( '' !== $row_authority ) : ?>
									<?php echo esc_html( axismundi_cal_admin_actor_label( $row_authority ) ); ?>
								<?php elseif ( 'remote' === (string) $row['kind'] ) : ?>
									<?php esc_html_e( 'Published elsewhere', 'axismundi-calendar' ); ?>
								<?php else : ?>
									<strong><?php esc_html_e( 'No Actor &mdash; not federated', 'axismundi-calendar' ); ?></strong>
								<?php endif; ?>
							</td>
							<td><?php echo esc_html( number_format_i18n( count( axismundi_cal_calendar_event_ids( (int) $row['id'] ) ) ) ); ?></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		<?php endif; ?>

		<h2><?php esc_html_e( 'Subscribe to a calendar', 'axismundi-calendar' ); ?></h2>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="ax_cal_subscribe_calendar">
			<?php wp_nonce_field( 'ax_cal_subscribe' ); ?>
			<label class="screen-reader-text" for="ax-cal-source-url"><?php esc_html_e( 'Public iCalendar address', 'axismundi-calendar' ); ?></label>
			<input name="source_url" id="ax-cal-source-url" type="url" class="regular-text" required placeholder="https://example.com/calendar.ics">
			<button type="submit" class="button button-secondary"><?php esc_html_e( 'Subscribe', 'axismundi-calendar' ); ?></button>
			<p class="description"><?php esc_html_e( 'Use a public .ics address. Private subscription addresses are credentials and need a separate secure-storage flow.', 'axismundi-calendar' ); ?></p>
		</form>

		<h2><?php esc_html_e( 'My calendars and subscriptions', 'axismundi-calendar' ); ?></h2>
		<table class="wp-list-table widefat fixed striped">
			<thead>
				<tr>
					<th scope="col"><?php esc_html_e( 'Name', 'axismundi-calendar' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Slug', 'axismundi-calendar' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Timezone', 'axismundi-calendar' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Type', 'axismundi-calendar' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Owner', 'axismundi-calendar' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Events', 'axismundi-calendar' ); ?></th>
					<th scope="col"><?php esc_html_e( 'View', 'axismundi-calendar' ); ?></th>
					<th scope="col"><?php esc_html_e( 'iCalendar', 'axismundi-calendar' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php if ( empty( $rows ) ) : ?>
					<tr><td colspan="8"><?php esc_html_e( 'No calendars yet.', 'axismundi-calendar' ); ?></td></tr>
				<?php endif; ?>
				<?php foreach ( $rows as $row ) : ?>
					<?php $can_edit = axismundi_cal_can_manage_calendar( $row ); ?>
					<tr>
						<td>
							<strong>
								<?php if ( $can_edit ) : ?>
									<a href="<?php echo esc_url( add_query_arg( 'ax_cal_edit', (int) $row['id'], $base ) ); ?>"><?php echo esc_html( axismundi_cal_calendar_display_name( $row ) ); ?></a>
								<?php else : ?>
									<?php echo esc_html( axismundi_cal_calendar_display_name( $row ) ); ?>
								<?php endif; ?>
							</strong>
						</td>
						<td><code><?php echo esc_html( $row['slug'] ); ?></code></td>
						<td><?php echo esc_html( (string) $row['timezone'] ); ?></td>
						<td><?php echo esc_html( 'remote' === (string) $row['kind'] ? __( 'Subscribed', 'axismundi-calendar' ) : __( 'Local', 'axismundi-calendar' ) ); ?></td>
						<?php $row_owner = axismundi_cal_calendar_authority( (int) $row['id'] ); ?>
						<td><?php echo esc_html( '' !== $row_owner ? axismundi_cal_admin_actor_label( $row_owner ) : __( 'Unassigned', 'axismundi-calendar' ) ); ?></td>
						<td><?php echo esc_html( number_format_i18n( count( axismundi_cal_calendar_event_ids( (int) $row['id'] ) ) ) ); ?></td>
						<td><a href="<?php echo esc_url( axismundi_cal_calendar_url( $row ) ); ?>"><?php esc_html_e( 'View', 'axismundi-calendar' ); ?></a></td>
						<td>
							<?php if ( '' !== axismundi_cal_calendar_ics_url( $row ) ) : ?>
								<a href="<?php echo esc_url( axismundi_cal_calendar_ics_url( $row ) ); ?>"><code>.ics</code></a>
							<?php else : ?>
								<?php $source = axismundi_cal_source_for_calendar( (int) $row['id'] ); ?>
								<?php if ( is_array( $source ) ) : ?>
									<a href="<?php echo esc_url( (string) $source['source_url'] ); ?>"><?php esc_html_e( 'Source', 'axismundi-calendar' ); ?></a>
								<?php endif; ?>
							<?php endif; ?>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>

		<?php if ( is_array( $calendar ) && 'remote' === (string) $calendar['kind'] ) : ?>
			<?php $source = axismundi_cal_source_for_calendar( (int) $calendar['id'] ); ?>
			<h2><?php esc_html_e( 'Subscribed calendar', 'axismundi-calendar' ); ?></h2>
			<p><a href="<?php echo esc_url( axismundi_cal_calendar_url( $calendar ) ); ?>"><?php esc_html_e( 'View calendar', 'axismundi-calendar' ); ?></a></p>
			<?php if ( is_array( $source ) ) : ?>
				<p><code><?php echo esc_html( (string) $source['source_url'] ); ?></code></p>
			<?php endif; ?>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="ax_cal_save_calendar">
				<input type="hidden" name="calendar_id" value="<?php echo esc_attr( (string) $calendar['id'] ); ?>">
				<?php wp_nonce_field( 'ax_cal_save_' . (int) $calendar['id'] ); ?>
				<button type="submit" class="button button-link-delete" name="ax_cal_action" value="delete" onclick="return confirm( '<?php echo esc_js( __( 'Unsubscribe from this calendar and remove its cached events?', 'axismundi-calendar' ) ); ?>' );"><?php esc_html_e( 'Unsubscribe', 'axismundi-calendar' ); ?></button>
				<a class="button button-secondary" href="<?php echo esc_url( $base ); ?>"><?php esc_html_e( 'Back', 'axismundi-calendar' ); ?></a>
			</form>
	</div>
		<?php return; endif; ?>

		<?php if ( is_array( $calendar ) ) : ?>
			<h2><?php esc_html_e( 'Integration', 'axismundi-calendar' ); ?></h2>
			<table class="form-table" role="presentation">
				<tr><th scope="row"><?php esc_html_e( 'Calendar ID', 'axismundi-calendar' ); ?></th><td><code><?php echo esc_html( (string) $calendar['uuid'] ); ?></code></td></tr>
				<tr><th scope="row"><?php esc_html_e( 'Calendar API', 'axismundi-calendar' ); ?></th><td><a href="<?php echo esc_url( rest_url( 'axismundi/v1/calendars/' . $calendar['uuid'] ) ); ?>"><code><?php echo esc_html( rest_url( 'axismundi/v1/calendars/' . $calendar['uuid'] ) ); ?></code></a></td></tr>
				<?php if ( axismundi_cal_is_publicly_readable( (int) $calendar['id'] ) ) : ?>
					<tr><th scope="row"><?php esc_html_e( 'Public calendar address', 'axismundi-calendar' ); ?></th><td><a href="<?php echo esc_url( axismundi_cal_calendar_url( $calendar ) ); ?>"><code><?php echo esc_html( axismundi_cal_calendar_url( $calendar ) ); ?></code></a></td></tr>
					<tr><th scope="row"><?php esc_html_e( 'Public iCalendar address', 'axismundi-calendar' ); ?></th><td><a href="<?php echo esc_url( axismundi_cal_calendar_ics_url( $calendar ) ); ?>"><code><?php echo esc_html( axismundi_cal_calendar_ics_url( $calendar ) ); ?></code></a></td></tr>
				<?php else : ?>
					<tr><th scope="row"><?php esc_html_e( 'Public addresses', 'axismundi-calendar' ); ?></th><td><?php esc_html_e( 'Unavailable until this calendar is shared publicly.', 'axismundi-calendar' ); ?></td></tr>
				<?php endif; ?>
			</table>
		<?php endif; ?>

		<h2><?php echo esc_html( is_array( $calendar ) ? __( 'Details', 'axismundi-calendar' ) : __( 'New calendar', 'axismundi-calendar' ) ); ?></h2>
		<?php if ( is_array( $calendar ) && 'remote' === (string) $calendar['kind'] ) : ?>
			<p class="description">
				<?php esc_html_e( 'This calendar is published elsewhere. Its description and timezone belong to whoever publishes it; the name here is what it is called in your own list, and changes nothing for anyone else following the same feed.', 'axismundi-calendar' ); ?>
			</p>
		<?php endif; ?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="ax_cal_save_calendar">
			<input type="hidden" name="calendar_id" value="<?php echo esc_attr( (string) ( $calendar['id'] ?? 0 ) ); ?>">
			<?php wp_nonce_field( 'ax_cal_save_' . (int) ( $calendar['id'] ?? 0 ) ); ?>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><label for="ax-cal-name"><?php esc_html_e( 'Name', 'axismundi-calendar' ); ?></label></th>
					<td><input name="name" id="ax-cal-name" type="text" class="regular-text" <?php echo '' === (string) ( $calendar['managed_key'] ?? '' ) ? 'required' : 'placeholder="' . esc_attr( axismundi_cal_calendar_display_name( (array) $calendar ) ) . '"'; ?> value="<?php echo esc_attr( (string) ( $calendar['name'] ?? '' ) ); ?>"></td>
				</tr>
				<tr>
					<th scope="row"><label for="ax-cal-slug"><?php esc_html_e( 'Slug', 'axismundi-calendar' ); ?></label></th>
					<td>
						<input name="slug" id="ax-cal-slug" type="text" class="regular-text" value="<?php echo esc_attr( (string) ( $calendar['slug'] ?? '' ) ); ?>">
						<p class="description"><?php esc_html_e( 'Used in the subscription address. Changing it breaks calendars people have already subscribed to.', 'axismundi-calendar' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="ax-cal-timezone"><?php esc_html_e( 'Timezone', 'axismundi-calendar' ); ?></label></th>
					<td>
						<select name="timezone" id="ax-cal-timezone">
							<?php
							/*
							 * Core's picker rather than a list of our own: it localizes the city names,
							 * groups them by region and stays current with the tz database. It also offers
							 * manual offsets, which are refused on save -- a calendar stores a place, and
							 * an offset is not one.
							 */
							require_once ABSPATH . 'wp-admin/includes/template.php';
							// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- core builds this option list.
							echo wp_timezone_choice( (string) ( $calendar['timezone'] ?? '' ), get_user_locale() );
							?>
						</select>
						<p class="description">
							<?php esc_html_e( 'New Events begin in this timezone. Readers still see timed Events in their own timezone; all-day Events remain dates.', 'axismundi-calendar' ); ?>
						</p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="ax-cal-description"><?php esc_html_e( 'Description', 'axismundi-calendar' ); ?></label></th>
					<td><textarea name="description" id="ax-cal-description" rows="3" class="large-text"><?php echo esc_textarea( (string) ( $calendar['description'] ?? '' ) ); ?></textarea></td>
				</tr>
				<tr>
					<th scope="row"><label for="ax-cal-owner"><?php esc_html_e( 'Owner', 'axismundi-calendar' ); ?></label></th>
					<td>
						<?php if ( ! is_array( $calendar ) ) : ?>
							<?php $authorities = axismundi_cal_admin_creatable_authorities(); $current_authority = axismundi_cal_current_actor_uri(); ?>
							<select name="owner_actor_uri" id="ax-cal-owner">
								<?php foreach ( $authorities as $uri => $label ) : ?>
									<option value="<?php echo esc_attr( $uri ); ?>" <?php selected( $uri, $current_authority ); ?>><?php echo esc_html( $label ); ?></option>
								<?php endforeach; ?>
							</select>
							<p class="description"><?php esc_html_e( 'Choose your Person Actor or a Group you manage. Ownership transfer is not available yet.', 'axismundi-calendar' ); ?></p>
						<?php else : ?>
							<?php $authority = axismundi_cal_calendar_authority( (int) $calendar['id'] ); ?>
							<?php if ( '' === $authority && 'local' === (string) $calendar['kind'] ) : ?>
								<?php $authorities = axismundi_cal_admin_creatable_authorities(); ?>
								<select name="owner_actor_uri" id="ax-cal-owner">
									<?php foreach ( $authorities as $uri => $label ) : ?>
										<option value="<?php echo esc_attr( $uri ); ?>" <?php selected( $uri, axismundi_cal_current_actor_uri() ); ?>><?php echo esc_html( $label ); ?></option>
									<?php endforeach; ?>
								</select>
								<p class="description"><?php esc_html_e( 'This calendar was left without an Actor by an upgrade. Assigning one is not a transfer, and can only be done once.', 'axismundi-calendar' ); ?></p>
							<?php else : ?>
								<strong><?php echo esc_html( '' !== $authority ? axismundi_cal_admin_actor_label( $authority ) : __( 'Unassigned', 'axismundi-calendar' ) ); ?></strong>
								<?php if ( '' !== $authority ) : ?><p><code><?php echo esc_html( $authority ); ?></code></p><?php endif; ?>
								<p class="description"><?php esc_html_e( 'Ownership transfer is not available yet.', 'axismundi-calendar' ); ?></p>
							<?php endif; ?>
						<?php endif; ?>
					</td>
				</tr>
			</table>
			<p class="submit">
				<button type="submit" class="button button-primary" name="ax_cal_action" value="save">
					<?php echo esc_html( is_array( $calendar ) ? __( 'Save calendar', 'axismundi-calendar' ) : __( 'Add calendar', 'axismundi-calendar' ) ); ?>
				</button>
				<?php if ( is_array( $calendar ) ) : ?>
					<a class="button button-secondary" href="<?php echo esc_url( $base ); ?>"><?php esc_html_e( 'Cancel', 'axismundi-calendar' ); ?></a>
				<?php endif; ?>
			</p>
		</form>

		<?php if ( is_array( $calendar ) ) : ?>
			<?php
			$event_count  = count( axismundi_cal_calendar_event_ids( (int) $calendar['id'] ) );
			$capabilities = axismundi_cal_calendar_capabilities( $calendar );
			$is_remote    = 'ics' === axismundi_cal_calendar_source_type( $calendar );
			// Undeletable for two unrelated reasons -- being somebody's default, or not being ours to
			// delete at all -- which is exactly why the screen asks the capability and not the type.
			$is_primary   = ! $is_remote && ! $capabilities['delete'];
			/*
			 * Stated in numbers rather than as "every Event it owns". A person deciding whether to
			 * delete a calendar is deciding about a specific quantity of their own work, and a
			 * confirmation that does not say how much is a confirmation they cannot answer.
			 */
			$confirm = $is_remote
				? __( 'Remove this subscription from your calendars?', 'axismundi-calendar' )
				: sprintf(
					/* translators: %d: number of events. */
					_n(
						'Delete this calendar and permanently delete the %d event on it?',
						'Delete this calendar and permanently delete the %d events on it?',
						$event_count,
						'axismundi-calendar'
					),
					$event_count
				);
			?>
			<h2><?php echo esc_html( $is_remote ? __( 'Subscription', 'axismundi-calendar' ) : __( 'Delete', 'axismundi-calendar' ) ); ?></h2>
			<?php if ( $is_primary ) : ?>
				<p class="description">
					<?php esc_html_e( 'This is the default calendar for its Actor. Events written without naming a calendar are filed here, so it cannot be deleted; empty it instead.', 'axismundi-calendar' ); ?>
				</p>
			<?php elseif ( $capabilities['delete'] || $capabilities['unsubscribe'] ) : ?>
				<p class="description">
					<?php
					echo esc_html(
						$is_remote
							? __( 'Removing this takes it out of your own list. The cached copy is kept while anyone else here still follows it.', 'axismundi-calendar' )
							: sprintf(
								/* translators: %d: number of events. */
								_n( 'This calendar holds %d event, which is deleted with it.', 'This calendar holds %d events, which are deleted with it.', $event_count, 'axismundi-calendar' ),
								$event_count
							)
					);
					?>
				</p>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<input type="hidden" name="action" value="ax_cal_save_calendar">
					<input type="hidden" name="calendar_id" value="<?php echo esc_attr( (string) $calendar['id'] ); ?>">
					<?php wp_nonce_field( 'ax_cal_save_' . (int) $calendar['id'] ); ?>
					<p>
						<button type="submit" class="button button-link-delete" name="ax_cal_action" value="delete"
							onclick="return confirm( '<?php echo esc_js( $confirm ); ?>' );">
							<?php echo esc_html( $is_remote ? __( 'Unsubscribe', 'axismundi-calendar' ) : __( 'Delete calendar', 'axismundi-calendar' ) ); ?>
						</button>
					</p>
				</form>
			<?php endif; ?>
		<?php endif; ?>

		<?php
		if ( is_array( $calendar ) ) {
			// Its own forms, below the one that edits the Calendar. Sharing is a different act from
			// renaming, and one save button for both would make "I fixed a typo" and "I gave three
			// people access" the same click.
			axismundi_cal_render_sharing( $calendar );
		}
		?>
	</div>
	<?php
}
