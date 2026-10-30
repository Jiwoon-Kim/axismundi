<?php
/** Separate Account inventory, Actor inventory and local Actor creation surfaces. */
defined( 'ABSPATH' ) || exit;

function axismundi_actors_render_accounts_landing() : void {
	if ( current_user_can( 'manage_options' ) ) { axismundi_actors_render_all_accounts(); }
	else { axismundi_actors_render_admin_page(); }
}

function axismundi_actors_render_all_accounts() : void {
	if ( ! current_user_can( 'manage_options' ) ) { wp_die( 'Forbidden', '', array( 'response' => 403 ) ); }
	echo '<div class="wrap"><h1>All Accounts</h1>';
	if ( ! function_exists( '\\Axismundi\\Jmap\\admin_account_inventory' ) ) {
		echo '<p>' . esc_html__( 'JMAP is inactive. Account inventory is unavailable.', 'axismundi-actors' ) . '</p></div>';
		return;
	}
	$page = max( 1, absint( $_GET['paged'] ?? 1 ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	$inventory = \Axismundi\Jmap\admin_account_inventory( $page, 50 );
	echo '<table class="widefat striped"><thead><tr><th>Account ID</th><th>Name</th><th>Status</th><th>Revision</th><th>Updated</th></tr></thead><tbody>';
	foreach ( $inventory['items'] as $row ) {
		$actor = axismundi_actors_get_by_identity( (int) $row['actor_id'] );
		if ( ! $actor || ! $actor->is_local() ) { continue; }
		echo '<tr><td>' . esc_html( 'a' . $row['actor_id'] ) . '</td><td>' . esc_html( $actor->get_display_name() ) . '</td><td>' . esc_html( axismundi_actors_status_label( $actor ) ) . '</td><td>' . esc_html( $row['revision'] ) . '</td><td>' . esc_html( $row['updated_at'] ) . '</td></tr>';
	}
	echo '</tbody></table>';
	axismundi_actors_directory_pagination( $page, (int) $inventory['total'], 'axismundi-all-accounts' );
	echo '</div>';
}

function axismundi_actors_directory_pagination( int $page, int $total, string $slug ) : void {
	echo '<div class="tablenav"><div class="tablenav-pages">';
	echo wp_kses_post( paginate_links( array( 'base' => axismundi_actors_account_admin_url( $slug ) . '&paged=%#%', 'current' => $page, 'total' => max( 1, (int) ceil( $total / 50 ) ) ) ) );
	echo '</div></div>';
}

function axismundi_actors_render_local_actors() : void {
	if ( ! current_user_can( 'manage_options' ) ) { wp_die( 'Forbidden', '', array( 'response' => 403 ) ); }
	$actors = array_values( axismundi_actors_list_local_actors() );
	$page = max( 1, absint( $_GET['paged'] ?? 1 ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	echo '<div class="wrap"><h1>Local Actors</h1><table class="widefat striped"><thead><tr><th>ID</th><th>Name</th><th>Handle</th><th>Type</th><th>Status</th><th>URI</th></tr></thead><tbody>';
	foreach ( array_slice( $actors, ( $page - 1 ) * 50, 50 ) as $actor ) {
		$link = axismundi_actors_account_admin_url( 'axismundi-actor-profile', array( 'identity_id' => $actor->get_identity_id() ) );
		echo '<tr><td>' . esc_html( $actor->get_identity_id() ) . '</td><td><a href="' . esc_url( $link ) . '">' . esc_html( $actor->get_display_name() ) . '</a></td><td>' . esc_html( '@' . $actor->get_preferred_username() ) . '</td><td>' . esc_html( $actor->get_type() ) . '</td><td>' . esc_html( axismundi_actors_status_label( $actor ) ) . '</td><td>' . esc_html( $actor->get_uri() ) . '</td></tr>';
	}
	echo '</tbody></table>';
	axismundi_actors_directory_pagination( $page, count( $actors ), 'axismundi-local-actors' );
	echo '</div>';
}

function axismundi_actors_render_add_local_actor() : void {
	if ( ! current_user_can( 'edit_posts' ) ) { wp_die( 'Forbidden', '', array( 'response' => 403 ) ); }
	echo '<div class="wrap"><h1>Add Local Actor</h1>';
	axismundi_actors_admin_notice();
	echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="axismundi_actors_add_local_actor">';
	wp_nonce_field( 'ax_actors_add_local_actor' );
	echo '<table class="form-table"><tr><th><label for="ax-new-type">Type</label></th><td><select id="ax-new-type" name="actor_type">';
	foreach ( axismundi_actors_kinds() as $type ) { echo '<option>' . esc_html( $type ) . '</option>'; }
	echo '</select></td></tr>';
	foreach ( array( 'handle' => 'Handle', 'name' => 'Name' ) as $key => $label ) {
		echo '<tr><th><label for="ax-new-' . esc_attr( $key ) . '">' . esc_html( $label ) . '</label></th><td><input class="regular-text" id="ax-new-' . esc_attr( $key ) . '" name="' . esc_attr( $key ) . '" required></td></tr>';
	}
	echo '<tr><th><label for="ax-new-summary">Summary</label></th><td><textarea class="large-text" id="ax-new-summary" name="summary" rows="4"></textarea></td></tr><tr><th><label for="ax-new-status">Status</label></th><td><select id="ax-new-status" name="status"><option value="public">Public</option><option value="disabled">Disabled</option></select></td></tr></table>';
	submit_button( __( 'Create Actor', 'axismundi-actors' ) );
	echo '</form></div>';
}

function axismundi_actors_handle_add_local_actor() : void {
	if ( ! current_user_can( 'edit_posts' ) ) { wp_die( 'Forbidden', '', array( 'response' => 403 ) ); }
	check_admin_referer( 'ax_actors_add_local_actor' );
	$type = sanitize_text_field( wp_unslash( $_POST['actor_type'] ?? '' ) );
	$handle = sanitize_text_field( wp_unslash( $_POST['handle'] ?? '' ) );
	$name = sanitize_text_field( wp_unslash( $_POST['name'] ?? '' ) );
	$back = axismundi_actors_account_admin_url( 'axismundi-add-local-actor' );
	if ( ! in_array( $type, axismundi_actors_kinds(), true ) || '' === trim( $name ) || '' === trim( $handle ) ) {
		axismundi_actors_redirect_result( $back, new WP_Error( 'ax_actor_input', 'Type, handle and name are required.' ) );
	}
	$actor = axismundi_actors_create_principal_account( array( 'actor_type' => $type, 'preferred_username' => $handle, 'status' => 'disabled' === ( $_POST['status'] ?? '' ) ? 'disabled' : 'public' ), 'wp_user', get_current_user_id() );
	if ( is_wp_error( $actor ) ) { axismundi_actors_redirect_result( $back, $actor ); }
	$language = axismundi_actors_site_language();
	foreach ( array( 'name' => $name, 'summary' => wp_kses_post( wp_unslash( $_POST['summary'] ?? '' ) ) ) as $property => $value ) {
		$result = axismundi_actors_set_text( $actor->get_identity_id(), $property, $language, $value );
		if ( is_wp_error( $result ) ) { axismundi_actors_redirect_result( $back, $result ); }
	}
	axismundi_actors_profile_updated( $actor->get_identity_id() );
	axismundi_actors_redirect_result( axismundi_actors_account_admin_url( 'axismundi-actor-profile', array( 'identity_id' => $actor->get_identity_id() ) ), true );
}
add_action( 'admin_post_axismundi_actors_add_local_actor', 'axismundi_actors_handle_add_local_actor' );
