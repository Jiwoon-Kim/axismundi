<?php
/** A system Account identity, separate from the retired experimental site Actor. */
defined( 'ABSPATH' ) || exit;

/** A configured role, never inferred from username or the legacy instance binding. */
function axismundi_actors_is_system_actor( Axismundi_Actor $actor ) : bool {
	return $actor->is_local() && $actor->get_identity_id() === (int) get_option( 'ax_actors_system_actor_id', 0 );
}

/** Read only: a missing, disabled or deleted system binding is never recreated here. */
function axismundi_actors_get_system_actor() : ?Axismundi_Actor {
	$id = (int) get_option( 'ax_actors_system_actor_id', 0 );
	$actor = $id > 0 ? axismundi_actors_get_by_identity( $id ) : null;
	return $actor instanceof Axismundi_Actor && $actor->is_local() && 'Application' === $actor->get_type() ? $actor : null;
}

/** Provision once; handles are aliases, never authority to adopt an existing Actor. */
function axismundi_actors_ensure_system_actor() {
	if ( false !== get_option( 'ax_actors_system_actor_id', false ) ) {
		return axismundi_actors_get_system_actor() ?? new WP_Error( 'ax_system_actor_binding', 'The configured system Actor is unavailable; explicit recovery is required.' );
	}
	if ( '1' !== get_option( 'ax_actors_body_version' ) || AXISMUNDI_ACTORS_DB_VERSION !== get_option( 'ax_actors_db_version' ) ) {
		return new WP_Error( 'ax_system_actor_schema', 'Actor storage is not ready.' );
	}
	global $wpdb;
	$lock = 'ax-system-' . substr( hash( 'sha256', DB_NAME . '|' . $wpdb->prefix ), 0, 40 );
	if ( '1' !== (string) $wpdb->get_var( $wpdb->prepare( 'SELECT GET_LOCK(%s,0)', $lock ) ) ) {
		return new WP_Error( 'ax_system_actor_busy', 'System Actor provisioning is already in progress.' );
	}
	try {
		// Another request may have bound the identity while this request was starting.
		wp_cache_delete( 'ax_actors_system_actor_id', 'options' );
		wp_cache_delete( 'notoptions', 'options' );
		if ( false !== get_option( 'ax_actors_system_actor_id', false ) ) {
			return axismundi_actors_get_system_actor() ?? new WP_Error( 'ax_system_actor_binding', 'The configured system Actor is unavailable; explicit recovery is required.' );
		}
		$username = get_option( 'ax_actors_system_actor_username', 'wordpress' );
		if ( ! is_string( $username ) || ! axismundi_actors_is_valid_handle( $username )
			|| strtolower( $username ) !== $username || axismundi_actors_is_reserved_handle( $username ) ) {
			return new WP_Error( 'ax_system_actor_username', 'The system Actor needs a valid unreserved lowercase handle.' );
		}
		if ( axismundi_actors_get_by_handle( $username ) || axismundi_actors_handle_owner( $username ) ) {
			return new WP_Error( 'ax_system_actor_handle_taken', 'The system Actor handle is already in use. Configure an unused system Actor username before provisioning.' );
		}
		$actor = axismundi_actors_create_local( array(
			'actor_type' => 'Application', 'preferred_username' => $username,
			'display_name' => 'WordPress', 'status' => 'public',
		) );
		if ( is_wp_error( $actor ) ) { return $actor; }
		if ( ! $actor instanceof Axismundi_Actor || $username !== $actor->get_preferred_username() ) {
			return new WP_Error( 'ax_system_actor_handle_race', 'The requested handle changed during creation; explicit recovery is required.' );
		}
		if ( ! add_option( 'ax_actors_system_actor_id', $actor->get_identity_id(), '', false ) ) {
			return new WP_Error( 'ax_system_actor_binding_save', 'The system Actor was created but its binding could not be saved; explicit recovery is required.' );
		}
		return $actor;
	} finally {
		$wpdb->get_var( $wpdb->prepare( 'SELECT RELEASE_LOCK(%s)', $lock ) );
	}
}

/** Upgrade existing installations after normal local-Actor consumers have registered. */
function axismundi_actors_maybe_seed_system_actor() : void {
	if ( false !== get_option( 'ax_actors_system_actor_id', false ) ) { return; }
	$result = axismundi_actors_ensure_system_actor();
	if ( is_wp_error( $result ) ) { $GLOBALS['axismundi_actors_system_actor_error'] = $result; }
}
add_action( 'init', 'axismundi_actors_maybe_seed_system_actor', 40 );

/** Retain the old URI as a Tombstone, not as a second system Account or a handle claim. */
function axismundi_actors_retire_legacy_site_actor() : bool {
	if ( false !== get_option( 'ax_actors_legacy_site_retired', false ) ) { return true; }
	$system = axismundi_actors_get_system_actor();
	if ( ! $system || '1' !== get_option( 'ax_actors_body_version' ) ) { return false; }
	global $wpdb;
	$id = (int) get_option( 'ax_actors_instance_identity_id', 0 );
	if ( $id <= 0 ) {
		$ids = $wpdb->get_col( $wpdb->prepare( "SELECT id FROM %i WHERE origin='local' AND actor_scope='site' LIMIT 2", axismundi_actors_actors_table() ) );
		if ( $wpdb->last_error || count( $ids ) > 1 ) { return false; }
		$id = (int) ( $ids[0] ?? 0 );
	}
	if ( $id === $system->get_identity_id() ) { return false; }
	$old = $id > 0 ? axismundi_actors_get_by_identity( $id ) : null;
	if ( $old && ( ! $old->is_local() || ! axismundi_actors_set_status( $id, 'tombstone' ) ) ) { return false; }
	foreach ( array( 'ax_actors_instance_identity_id', 'ax_actors_site_actor_disabled', 'ax_actors_site_actor_type', 'ax_actors_site_owner_user_id' ) as $option ) {
		delete_option( $option );
		if ( false !== get_option( $option, false ) ) { return false; }
	}
	// Preserve a migration receipt only after settings cleanup has succeeded.
	return add_option( 'ax_actors_legacy_site_retired', array( 'ids' => $old ? array( $id ) : array() ), '', false );
}
add_action( 'init', 'axismundi_actors_retire_legacy_site_actor', 45 );

add_action( 'admin_notices', static function () : void {
	$error = $GLOBALS['axismundi_actors_system_actor_error'] ?? null;
	if ( current_user_can( 'manage_options' ) && is_wp_error( $error ) ) {
		echo '<div class="notice notice-error"><p>' . esc_html( 'Axismundi system Actor: ' . $error->get_error_message() ) . '</p></div>';
	}
} );
