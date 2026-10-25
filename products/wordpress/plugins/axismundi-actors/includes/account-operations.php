<?php
/**
 * Review-gated account creation and protocol-neutral workspace preferences.
 * No HTTP routes or UI are registered here.
 *
 * @package AxismundiActors
 */

defined( 'ABSPATH' ) || exit;

/**
 * Create an internal, handle-less Account for the authenticated user only.
 * Creation stays opt-in until multi-account lifecycle and transport are reviewed.
 *
 * @param array $args actor_type, display_name, personal (optional).
 * @return Axismundi_Actor|WP_Error
 */
function axismundi_actors_create_account( array $args ) {
	$user_id = get_current_user_id();
	if ( $user_id <= 0 || ! user_can( $user_id, 'edit_posts' ) ) {
		return new WP_Error( 'ax_actors_account_forbidden', __( 'You cannot create an account.', 'axismundi-actors' ) );
	}
	if ( ! axismundi_actors_account_access_ready() ) {
		return new WP_Error( 'ax_actors_account_not_ready', __( 'Account access is not ready.', 'axismundi-actors' ) );
	}
	if ( array() !== array_diff( array_keys( $args ), array( 'actor_type', 'display_name', 'personal' ) ) ) {
		return new WP_Error( 'ax_actors_account_arguments', __( 'Unsupported account creation arguments.', 'axismundi-actors' ) );
	}
	$type = $args['actor_type'] ?? 'Person';
	$name = $args['display_name'] ?? '';
	$personal = array_key_exists( 'personal', $args ) ? $args['personal'] : ( 'Person' === $type );
	if ( ! is_string( $type ) || ! in_array( $type, axismundi_actors_kinds(), true )
		|| ! is_string( $name ) || ! is_bool( $personal ) ) {
		return new WP_Error( 'ax_actors_account_arguments', __( 'Invalid account type, name or personal ownership.', 'axismundi-actors' ) );
	}
	$name = sanitize_text_field( $name );
	if ( '' === $name || strlen( $name ) > 191 ) {
		return new WP_Error( 'ax_actors_account_arguments', __( 'An account needs a non-empty name of at most 191 bytes.', 'axismundi-actors' ) );
	}
	/** Enable explicitly in development after reviewing lifecycle restrictions. */
	if ( true !== apply_filters( 'axismundi_actors_account_creation_enabled', false, $user_id ) ) {
		return new WP_Error( 'ax_actors_account_creation_disabled', __( 'Additional account creation is awaiting review.', 'axismundi-actors' ) );
	}
	return axismundi_actors_create_local( array(
		'actor_type' => $type,
		'actor_scope' => $personal ? 'user' : 'managed',
		'personal_owner_user_id' => $personal ? $user_id : null,
		'initial_owner_user_id' => $user_id,
		'display_name' => $name,
		'status' => 'internal',
	) );
}

/** Use session selection during rollout; retain the old preference only while disabled. */
function axismundi_actors_set_workspace( int $user_id, int $identity_id ) {
	if ( $user_id <= 0 || $user_id !== get_current_user_id() || ! get_userdata( $user_id ) ) {
		return new WP_Error( 'ax_actors_workspace_user', __( 'You may change only your own workspace.', 'axismundi-actors' ) );
	}
	$actor = $identity_id > 0 ? axismundi_actors_resolve_workspace( $identity_id, $user_id ) : null;
	if ( $identity_id < 0 || ( $identity_id > 0 && ! $actor instanceof Axismundi_Actor ) ) {
		return new WP_Error( 'ax_actors_workspace_forbidden', __( 'That workspace is unavailable.', 'axismundi-actors' ) );
	}
	if ( axismundi_actors_account_selection_enabled() ) {
		$session = axismundi_actors_browser_session( $user_id );
		if ( is_wp_error( $session ) ) {
			return $session;
		}
		$result = 0 === $identity_id
			? axismundi_actors_clear_account_selection()
			: axismundi_actors_set_account_selection( $identity_id );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		if ( ! $result ) {
			return new WP_Error( 'ax_actors_workspace_save', __( 'The workspace cookie could not be saved.', 'axismundi-actors' ) );
		}
		do_action( 'axismundi_actors_workspace_changed', $actor, $user_id );
		return true;
	}
	if ( 0 === $identity_id ) {
		delete_user_meta( $user_id, AXISMUNDI_ACTORS_ACTING_META );
		if ( metadata_exists( 'user', $user_id, AXISMUNDI_ACTORS_ACTING_META ) ) {
			return new WP_Error( 'ax_actors_workspace_save', __( 'The workspace could not be cleared.', 'axismundi-actors' ) );
		}
	} else {
		update_user_meta( $user_id, AXISMUNDI_ACTORS_ACTING_META, $identity_id );
		if ( $identity_id !== (int) get_user_meta( $user_id, AXISMUNDI_ACTORS_ACTING_META, true ) ) {
			return new WP_Error( 'ax_actors_workspace_save', __( 'The workspace could not be saved.', 'axismundi-actors' ) );
		}
	}
	do_action( 'axismundi_actors_workspace_changed', $actor, $user_id );
	return true;
}

/** A default's purpose is a local domain key, not a protocol capability URI. */
function axismundi_actors_account_default_key( string $purpose ) : string {
	return preg_match( '/\A[a-z][a-z0-9_-]{0,63}\z/D', $purpose ) ? 'ax_account_default_' . $purpose : '';
}

/** User preference only: does not assert that a feature is implemented or enabled. */
function axismundi_actors_set_default_account( int $user_id, string $purpose, int $identity_id ) {
	$key = axismundi_actors_account_default_key( $purpose );
	if ( '' === $key || $user_id <= 0 || $user_id !== get_current_user_id() || ! get_userdata( $user_id ) ) {
		return new WP_Error( 'ax_actors_default_arguments', __( 'Invalid account default or user.', 'axismundi-actors' ) );
	}
	if ( $identity_id < 0 || ( $identity_id > 0 && null === axismundi_actors_resolve_workspace( $identity_id, $user_id ) ) ) {
		return new WP_Error( 'ax_actors_default_forbidden', __( 'That default account is unavailable.', 'axismundi-actors' ) );
	}
	if ( 0 === $identity_id ) {
		delete_user_meta( $user_id, $key );
		if ( metadata_exists( 'user', $user_id, $key ) ) {
			return new WP_Error( 'ax_actors_default_save', __( 'The default could not be cleared.', 'axismundi-actors' ) );
		}
	} else {
		update_user_meta( $user_id, $key, $identity_id );
		if ( $identity_id !== (int) get_user_meta( $user_id, $key, true ) ) {
			return new WP_Error( 'ax_actors_default_save', __( 'The default could not be saved.', 'axismundi-actors' ) );
		}
	}
	do_action( 'axismundi_actors_account_default_changed', $user_id, $purpose, $identity_id );
	return true;
}

/** Resolve a stored default without rewriting a stale preference or guessing a replacement. */
function axismundi_actors_default_account( int $user_id, string $purpose ) : ?Axismundi_Actor {
	$key = axismundi_actors_account_default_key( $purpose );
	if ( $user_id <= 0 || '' === $key ) {
		return null;
	}
	return axismundi_actors_resolve_workspace( (int) get_user_meta( $user_id, $key, true ), $user_id );
}

/** Disabled-rollout compatibility only; never used after session selection fails. */
function axismundi_actors_legacy_current_workspace( int $user_id ) : ?Axismundi_Actor {
	if ( $user_id <= 0 ) {
		return null;
	}
	$selected = axismundi_actors_resolve_workspace( (int) get_user_meta( $user_id, AXISMUNDI_ACTORS_ACTING_META, true ), $user_id );
	if ( $selected instanceof Axismundi_Actor ) {
		return $selected;
	}
	$default = axismundi_actors_default_account( $user_id, 'workspace' );
	if ( $default instanceof Axismundi_Actor ) {
		return $default;
	}
	$legacy = axismundi_actors_get_for_user( $user_id );
	return $legacy instanceof Axismundi_Actor && axismundi_actors_can_access_workspace( $legacy, $user_id ) ? $legacy : null;
}

/** Preserve missing, invalid and revoked selection errors for workspace consumers. */
function axismundi_actors_current_workspace_result( ?int $user_id = null ) {
	$user_id = $user_id ?? get_current_user_id();
	if ( axismundi_actors_account_selection_enabled() ) {
		return axismundi_actors_resolve_account_selection( $user_id, null, '', false, true );
	}
	$actor = axismundi_actors_legacy_current_workspace( $user_id );
	return $actor instanceof Axismundi_Actor
		? array( 'actor' => $actor, 'source' => 'legacy' )
		: new WP_Error( 'ax_selection_missing', __( 'No accessible account is selected.', 'axismundi-actors' ) );
}

/** Typed compatibility surface; an invalid selection never triggers another lookup. */
function axismundi_actors_current_workspace( ?int $user_id = null ) : ?Axismundi_Actor {
	$result = axismundi_actors_current_workspace_result( $user_id );
	return is_wp_error( $result ) ? null : $result['actor'];
}

/** Explicit publication bridge for new callers; never silently publish as another workspace. */
function axismundi_actors_workspace_publisher( ?int $user_id = null ) : ?Axismundi_Actor {
	$user_id = $user_id ?? get_current_user_id();
	$actor = axismundi_actors_current_workspace( $user_id );
	return $actor instanceof Axismundi_Actor && axismundi_actors_can_act_as( $actor, $user_id ) ? $actor : null;
}
