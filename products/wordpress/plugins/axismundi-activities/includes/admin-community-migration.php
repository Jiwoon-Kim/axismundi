<?php
/** Explicit browser adapter for legacy Community adoption; no CLI dependency. */
defined( 'ABSPATH' ) || exit;

function axismundi_act_community_migration_nonce( string $group, string $person ) : string {
	return 'ax_community_migrate_' . hash( 'sha256', $group . "\n" . $person );
}

function axismundi_act_community_migration_request( array $input ) {
	foreach ( array( 'group_uri', 'person_uri', '_wpnonce', 'confirm_migration' ) as $key ) {
		if ( ! isset( $input[$key] ) || ! is_string( $input[$key] ) ) { return new WP_Error( 'ax_migration_input', 'Explicit Group, Person and confirmation are required.', array( 'status' => 400 ) ); }
	}
	if ( '1' !== $input['confirm_migration'] || ! wp_verify_nonce( sanitize_text_field( $input['_wpnonce'] ), axismundi_act_community_migration_nonce( $input['group_uri'], $input['person_uri'] ) ) ) {
		return new WP_Error( 'ax_migration_nonce', 'Confirm this Group and Person with a current migration form.', array( 'status' => 403 ) );
	}
	$group = axismundi_actors_get_by_uri( $input['group_uri'] );
	$person = axismundi_actors_get_by_uri( $input['person_uri'] );
	if ( ! $group || ! $person ) { return new WP_Error( 'ax_migration_input', 'Both Actors must already exist locally.', array( 'status' => 400 ) ); }
	return axismundi_act_migrate_community_group( get_current_user_id(), $group, $person );
}

function axismundi_act_handle_community_migration() : void {
	if ( 'POST' !== ( $_SERVER['REQUEST_METHOD'] ?? '' ) ) { wp_die( 'A POST request is required.', '', array( 'response' => 405 ) ); }
	$result = axismundi_act_community_migration_request( wp_unslash( $_POST ) );
	if ( is_wp_error( $result ) ) { wp_die( esc_html( $result->get_error_message() ), '', array( 'response' => (int) ( $result->get_error_data()['status'] ?? 400 ) ) ); }
	$group = axismundi_actors_moderators_group_for_uri( $result->get_target_uri() );
	wp_safe_redirect( add_query_arg( array( 'page' => 'axismundi-community-review', 'group_id' => $group ? $group->get_identity_id() : 0, 'migrated' => '1' ), admin_url( 'tools.php' ) ), 303 );
	exit;
}
add_action( 'admin_post_axismundi_community_migrate', 'axismundi_act_handle_community_migration' );

/** Return only explicit legacy candidates or unfinished announcements, never infer moderators. */
function axismundi_act_community_migration_candidates( Axismundi_Actor $person ) : array {
	$user = get_current_user_id();
	if ( ! current_user_can( 'manage_options' ) || 'Person' !== $person->get_type() || true !== axismundi_act_outbox_authorize( $user, $person ) ) { return array(); }
	$candidates = array();
	foreach ( axismundi_actors_list_local_actors( 'public' ) as $group ) {
		if ( 'Group' !== $group->get_type() || true !== axismundi_act_outbox_authorize( $user, $group ) ) { continue; }
		if ( null === axismundi_actors_group_profile( $group ) ) { $candidates[] = array( 'group' => $group, 'retry' => false ); continue; }
		$control = axismundi_act_community_migration_control( $group );
		if ( axismundi_actors_is_community( $group ) && $control && $control->get_actor_uri() === $person->get_uri() && ! axismundi_act_community_migration_announced( $group, $control ) ) {
			$candidates[] = array( 'group' => $group, 'retry' => true );
		}
	}
	return $candidates;
}

function axismundi_act_render_community_migrations( Axismundi_Actor $person ) : void {
	$candidates = axismundi_act_community_migration_candidates( $person );
	if ( ! $candidates ) { return; }
	echo '<details><summary>' . esc_html__( 'Legacy Community migration', 'axismundi-activities' ) . '</summary><table class="widefat striped"><thead><tr><th>' . esc_html__( 'Group', 'axismundi-activities' ) . '</th><th>' . esc_html__( 'First moderator', 'axismundi-activities' ) . '</th><th>' . esc_html__( 'Migration', 'axismundi-activities' ) . '</th></tr></thead><tbody>';
	foreach ( $candidates as $candidate ) {
		$group = $candidate['group'];
		echo '<tr><td style="overflow-wrap:anywhere">' . esc_html( $group->get_display_name() ?: '@' . $group->get_preferred_username() ) . '<br><code>' . esc_html( $group->get_uri() ) . '</code></td><td style="overflow-wrap:anywhere"><code>' . esc_html( $person->get_uri() ) . '</code></td><td><form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		foreach ( array( 'action' => 'axismundi_community_migrate', 'group_uri' => $group->get_uri(), 'person_uri' => $person->get_uri() ) as $key => $value ) { echo '<input type="hidden" name="' . esc_attr( $key ) . '" value="' . esc_attr( $value ) . '">'; }
		wp_nonce_field( axismundi_act_community_migration_nonce( $group->get_uri(), $person->get_uri() ) );
		echo '<p><label><input type="checkbox" name="confirm_migration" value="1" required> ' . esc_html__( 'Confirm Community profile and this Person as first moderator.', 'axismundi-activities' ) . '</label></p>';
		echo '<button class="button button-primary" type="submit">' . esc_html( $candidate['retry'] ? __( 'Finish announcement', 'axismundi-activities' ) : __( 'Migrate Community', 'axismundi-activities' ) ) . '</button></form></td></tr>';
	}
	echo '</tbody></table></details>';
}
