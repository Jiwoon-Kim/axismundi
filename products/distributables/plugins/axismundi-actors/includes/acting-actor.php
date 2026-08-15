<?php
/**
 * The acting Actor: the local identity a signed-in user has chosen to publish as
 * (ROUTING §0.05).
 *
 * This is the third of the three Actors a request can have, and the only one a user
 * picks. It is not `profile_actor()` -- the Actor a request is *about* -- and nothing
 * here resolves one from the other. Reading an Organization's profile page must never
 * be what decides who a post is published by; that substitution is a privilege bug
 * that reads as correct code, which is why the two live in different files under
 * different names and neither falls back to the other.
 *
 * Switching the acting Actor is not switching WordPress user. Capabilities, the login
 * session, and the editor recorded in `post_author` all stay with the account; only
 * the identity a domain plugin attributes new work to changes.
 *
 * The stored choice is a **preference, not a capability**. Manager roles are revocable,
 * so eligibility is re-checked on read and must be re-checked again by every caller at
 * the moment it mutates something -- a selection made last week is not authority today.
 * A choice that has since become ineligible falls back to the user's own Person rather
 * than raising: losing a role should stop you publishing as that Organization, not lock
 * you out of writing as yourself.
 *
 * @package AxismundiActors
 */

defined( 'ABSPATH' ) || exit;

/** User meta holding the chosen identity. A preference, never consulted as authority. */
const AXISMUNDI_ACTORS_ACTING_META = 'ax_acting_actor_identity';

/**
 * May this user publish as this Actor right now?
 *
 * The one predicate every part of this slice asks -- the switcher when it lists,
 * the setter when it stores, the resolver when it reads back, and (importantly)
 * domain plugins at each mutation. Managed actors defer entirely to the manager
 * relation, with no site-administrator override: an administrator who has not been
 * appointed may claim the actor first, visibly, rather than silently publishing as it.
 *
 * Publishing needs an address, so an unactivated Person is not yet a choice. Being
 * public is deliberately *not* required: whether a profile is published has no bearing
 * on who authored something.
 *
 * @param Axismundi_Actor $actor   Candidate identity.
 * @param int             $user_id WP user doing the acting.
 * @return bool
 */
function axismundi_actors_can_act_as( Axismundi_Actor $actor, int $user_id ) : bool {
	if ( $user_id <= 0 || ! $actor->is_local() || ! $actor->is_handle_locked() ) {
		return false;
	}
	if ( in_array( $actor->get_status(), array( 'disabled', 'tombstone' ), true ) ) {
		return false;
	}
	if ( $actor->is_managed() ) {
		return axismundi_actors_managed_actor_can_manage( $actor->get_identity_id(), $user_id );
	}
	// A Person is acted as by exactly the account it belongs to. The site actor is nobody's to speak for.
	return 'user' === $actor->get_scope() && (int) $actor->get_local_user_id() === $user_id;
}

/**
 * The Actor a user acts as when they have chosen nothing: their own Person.
 *
 * Deliberately never the site actor and never a managed actor they happen to run --
 * an unchosen default that speaks for an organisation would put words in its mouth.
 * Returns null for an account with no activated Person, which is a real state and not
 * an error; callers decide what to do without an identity to publish under.
 *
 * @param int $user_id WP user.
 * @return Axismundi_Actor|null
 */
function axismundi_actors_default_acting_actor( int $user_id ) : ?Axismundi_Actor {
	if ( $user_id <= 0 ) {
		return null;
	}
	$actor = axismundi_actors_get_for_user( $user_id );
	return $actor instanceof Axismundi_Actor && axismundi_actors_can_act_as( $actor, $user_id ) ? $actor : null;
}

/**
 * Every identity one user may publish as, their own Person first.
 *
 * @param int|null $user_id WP user; defaults to the current one.
 * @return Axismundi_Actor[]
 */
function axismundi_actors_acting_actor_options( ?int $user_id = null ) : array {
	$user_id = null === $user_id ? get_current_user_id() : $user_id;
	if ( $user_id <= 0 ) {
		return array();
	}
	$options = array();
	$person  = axismundi_actors_default_acting_actor( $user_id );
	if ( $person instanceof Axismundi_Actor ) {
		$options[] = $person;
	}
	foreach ( axismundi_actors_list_manageable_actors( $user_id ) as $managed ) {
		if ( axismundi_actors_can_act_as( $managed, $user_id ) ) {
			$options[] = $managed;
		}
	}
	return $options;
}

/**
 * The Actor a user is currently publishing as.
 *
 * Reads the stored preference and re-checks it, so a revoked manager stops acting as
 * that Organization on the next request without anything having to remember to clear
 * the meta. An ineligible or vanished selection falls back to the user's own Person.
 *
 * The stale row is left where it is: this is a read, and a read that writes turns every
 * page view into a race. It is cleared when the user next chooses, or by the switcher.
 *
 * @param int|null $user_id WP user; defaults to the current one.
 * @return Axismundi_Actor|null Null when the account has no identity it may publish as.
 */
function axismundi_actors_acting_actor( ?int $user_id = null ) : ?Axismundi_Actor {
	$user_id = null === $user_id ? get_current_user_id() : $user_id;
	if ( $user_id <= 0 ) {
		return null;
	}
	$chosen = (int) get_user_meta( $user_id, AXISMUNDI_ACTORS_ACTING_META, true );
	if ( $chosen > 0 ) {
		$actor = axismundi_actors_get_by_identity( $chosen );
		if ( $actor instanceof Axismundi_Actor && axismundi_actors_can_act_as( $actor, $user_id ) ) {
			return $actor;
		}
	}
	return axismundi_actors_default_acting_actor( $user_id );
}

/**
 * Store one user's choice of acting Actor.
 *
 * Storing is gated by the same predicate every mutation re-runs, so an ineligible
 * choice never becomes a stored one. Passing 0 clears the choice back to the default.
 *
 * @param int $user_id     WP user.
 * @param int $identity_id Chosen identity, or 0 to return to the default.
 * @return true|WP_Error
 */
function axismundi_actors_set_acting_actor( int $user_id, int $identity_id ) {
	if ( $user_id <= 0 ) {
		return new WP_Error( 'ax_actors_acting_user', __( 'Only a signed-in user can choose an Actor to publish as.', 'axismundi-actors' ) );
	}
	if ( $identity_id <= 0 ) {
		delete_user_meta( $user_id, AXISMUNDI_ACTORS_ACTING_META );
		return true;
	}
	$actor = axismundi_actors_get_by_identity( $identity_id );
	if ( ! $actor instanceof Axismundi_Actor || ! axismundi_actors_can_act_as( $actor, $user_id ) ) {
		return new WP_Error( 'ax_actors_acting_denied', __( 'You cannot publish as that Actor.', 'axismundi-actors' ) );
	}
	update_user_meta( $user_id, AXISMUNDI_ACTORS_ACTING_META, $identity_id );
	/**
	 * Fires after a user changes the identity they publish as. Nothing already
	 * published moves: this affects what is authored next, and nothing before it.
	 *
	 * @param Axismundi_Actor $actor   Newly acting Actor.
	 * @param int             $user_id WP user who chose it.
	 */
	do_action( 'axismundi_actors_acting_actor_changed', $actor, $user_id );
	return true;
}

/** @return string Label for one option in the switcher. */
function axismundi_actors_acting_actor_label( Axismundi_Actor $actor ) : string {
	$name = $actor->get_display_name();
	return '' !== $name ? $name : '@' . $actor->get_preferred_username();
}

/* -------------------------------------------------------------------------- *
 * The switcher (admin bar; front end and admin alike).
 * -------------------------------------------------------------------------- */

/**
 * Offer the identities a user may publish as, and say which one is in force.
 *
 * It hangs under the account menu rather than sitting on the bar as a menu of its own.
 * Switching is not switching user -- capabilities, session and `post_author` are
 * untouched -- but "who am I publishing as" is the same question the account menu
 * already answers, and putting it anywhere else invites reading it as a second login.
 * It is its own group inside that menu, so it never looks like part of Edit Profile
 * or Log Out.
 *
 * "View profile" and "Act as" are separate commands on purpose. Reading a page as an
 * Organization and speaking as one are different acts, and a menu that made looking
 * into switching would be the same substitution `profile_actor()` was renamed to
 * prevent -- only this time performed by the user's own click.
 *
 * Each switch is a nonce'd POST rather than a link: changing who you publish as is a
 * state change, and must not be something a crafted URL can do to you.
 *
 * @param WP_Admin_Bar $bar Admin bar.
 * @return void
 */
function axismundi_actors_acting_actor_admin_bar( WP_Admin_Bar $bar ) : void {
	$user_id = get_current_user_id();
	if ( $user_id <= 0 ) {
		return;
	}
	$options = axismundi_actors_acting_actor_options( $user_id );
	// One identity is not a choice; a menu offering it would only be furniture.
	if ( count( $options ) < 2 ) {
		return;
	}
	$acting = axismundi_actors_acting_actor( $user_id );
	if ( ! $acting instanceof Axismundi_Actor ) {
		return;
	}

	$bar->add_group(
		array(
			'id'     => 'ax-acting-actor',
			'parent' => 'my-account',
			'meta'   => array( 'class' => 'ab-sub-secondary' ),
		)
	);
	$bar->add_node(
		array(
			'id'     => 'ax-acting-actor-current',
			'parent' => 'ax-acting-actor',
			/* translators: %s: name of the Actor the user is currently publishing as. */
			'title'  => sprintf( esc_html__( 'Posting as %s', 'axismundi-actors' ), esc_html( axismundi_actors_acting_actor_label( $acting ) ) ),
			'href'   => axismundi_actors_is_public_profile( $acting ) ? $acting->get_profile_url() : false,
			'meta'   => array( 'title' => __( 'The Actor new work is published as', 'axismundi-actors' ) ),
		)
	);

	foreach ( $options as $option ) {
		$identity_id = $option->get_identity_id();
		if ( $identity_id === $acting->get_identity_id() ) {
			continue;
		}
		$label = axismundi_actors_acting_actor_label( $option );
		ob_start();
		?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline">
			<input type="hidden" name="action" value="axismundi_actors_set_acting_actor">
			<input type="hidden" name="identity_id" value="<?php echo esc_attr( (string) $identity_id ); ?>">
			<input type="hidden" name="redirect_to" value="<?php echo esc_attr( axismundi_actors_acting_actor_return_url() ); ?>">
			<?php wp_nonce_field( 'ax_actors_acting_actor_' . $identity_id ); ?>
			<button type="submit" class="ab-item button-link">
				<?php
				/* translators: %s: Actor name. */
				printf( esc_html__( 'Act as %s', 'axismundi-actors' ), esc_html( $label ) );
				?>
			</button>
		</form>
		<?php
		$bar->add_node(
			array(
				'id'     => 'ax-acting-actor-' . $identity_id,
				'parent' => 'ax-acting-actor',
				'title'  => (string) ob_get_clean(),
				'href'   => false,
			)
		);
	}
}
add_action( 'admin_bar_menu', 'axismundi_actors_acting_actor_admin_bar', 80 );

/** @return string The page to come back to after switching: where the user already was. */
function axismundi_actors_acting_actor_return_url() : string {
	$path = isset( $_SERVER['REQUEST_URI'] ) ? esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '';
	return '' === $path ? home_url( '/' ) : home_url( $path );
}

/**
 * Apply a switch. Nonce and eligibility are both checked here, because the menu that
 * offered the option was rendered on an earlier request and a role can be revoked in
 * between.
 *
 * @return void
 */
function axismundi_actors_handle_set_acting_actor() : void {
	$identity_id = isset( $_POST['identity_id'] ) ? absint( $_POST['identity_id'] ) : 0;
	check_admin_referer( 'ax_actors_acting_actor_' . $identity_id );
	$result = axismundi_actors_set_acting_actor( get_current_user_id(), $identity_id );
	if ( is_wp_error( $result ) ) {
		wp_die( esc_html( $result->get_error_message() ), '', array( 'response' => 403 ) );
	}
	$redirect = isset( $_POST['redirect_to'] ) ? esc_url_raw( wp_unslash( $_POST['redirect_to'] ) ) : '';
	wp_safe_redirect( '' !== $redirect ? $redirect : admin_url() );
	exit;
}
add_action( 'admin_post_axismundi_actors_set_acting_actor', 'axismundi_actors_handle_set_acting_actor' );
