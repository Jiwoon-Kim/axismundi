<?php
/** Thin authenticated clients for Community review and the C2S outbox. */
defined( 'ABSPATH' ) || exit;

/** Current selection is context, never authorization. */
function axismundi_act_admin_publisher() {
	$selection = axismundi_actors_current_workspace_result();
	if ( is_wp_error( $selection ) ) { return $selection; }
	$actor = $selection['actor'];
	$allowed = axismundi_act_outbox_authorize( get_current_user_id(), $actor );
	return is_wp_error( $allowed ) ? $allowed : $actor;
}

/** Reuse Actors' selection adapters; do not store another composer preference. */
function axismundi_act_admin_account_switcher() : void {
	if ( axismundi_actors_account_selection_enabled() ) {
		echo '<p><a href="' . esc_url( axismundi_actors_account_admin_url( 'axismundi-jmap-accounts' ) ) . '">' . esc_html__( 'Switch Account', 'axismundi-activities' ) . '</a></p>';
		return;
	}
	echo '<details><summary>' . esc_html__( 'Switch Account', 'axismundi-activities' ) . '</summary>';
	foreach ( axismundi_actors_acting_actor_options() as $option ) {
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="axismundi_actors_set_acting_actor"><input type="hidden" name="identity_id" value="' . esc_attr( $option->get_identity_id() ) . '"><input type="hidden" name="redirect_to" value="' . esc_url( axismundi_actors_acting_actor_return_url() ) . '">';
		wp_nonce_field( 'ax_actors_acting_actor_' . $option->get_identity_id() );
		echo '<p><button class="button" type="submit">' . esc_html( axismundi_actors_acting_actor_label( $option ) ) . '</button></p></form>';
	}
	echo '</details>';
}

/** Review membership is distinct from permission to edit the moderator list. */
function axismundi_act_admin_review_allowed( $moderator, $group ) : bool {
	if ( ! $moderator instanceof Axismundi_Actor || ! $group instanceof Axismundi_Actor
		|| 'Person' !== $moderator->get_type() || ! axismundi_actors_is_community( $group )
		|| 'public' !== $group->get_status()
		|| true !== axismundi_act_outbox_authorize( get_current_user_id(), $moderator ) ) { return false; }
	$members = axismundi_actors_read_moderators( $group );
	return ! is_wp_error( $members ) && in_array( $moderator->get_uri(), $members['items'], true );
}

function axismundi_act_admin_review_groups( $moderator ) : array {
	if ( ! $moderator instanceof Axismundi_Actor || true !== axismundi_act_outbox_authorize( get_current_user_id(), $moderator ) ) { return array(); }
	return array_values( array_filter( axismundi_actors_local_communities_moderated_by( $moderator ),
		static fn( $group ) => axismundi_act_admin_review_allowed( $moderator, $group ) ) );
}

/** Bind CSRF protection to the visible principal and exact submission. */
function axismundi_act_admin_review_nonce_action( int $moderator, int $group, string $uri ) : string {
	return 'ax_community_review_' . $moderator . '_' . $group . '_' . hash( 'sha256', $uri );
}

/** Testable POST adapter. All publication and transaction logic stays in the service. */
function axismundi_act_admin_review_request( array $input ) {
	foreach ( array( 'moderator_id', 'group_id', 'object_uri', 'decision', '_wpnonce' ) as $key ) {
		if ( ! isset( $input[$key] ) || ! is_string( $input[$key] ) ) {
			return new WP_Error( 'ax_review_input', 'Invalid review request.', array( 'status' => 400 ) );
		}
	}
	$moderator_id = absint( $input['moderator_id'] );
	$group_id = absint( $input['group_id'] );
	$uri = $input['object_uri'];
	if ( ! wp_verify_nonce( sanitize_text_field( $input['_wpnonce'] ), axismundi_act_admin_review_nonce_action( $moderator_id, $group_id, $uri ) ) ) {
		return new WP_Error( 'ax_review_nonce', 'The review request has expired.', array( 'status' => 403 ) );
	}
	$moderator = axismundi_actors_get_by_identity( $moderator_id );
	$group = axismundi_actors_get_by_identity( $group_id );
	if ( ! axismundi_act_admin_review_allowed( $moderator, $group ) ) {
		return new WP_Error( 'ax_review_forbidden', 'You cannot review this Community.', array( 'status' => 403 ) );
	}
	if ( ! in_array( $input['decision'], array( 'approve', 'reject' ), true ) ) {
		return new WP_Error( 'ax_review_decision', 'Invalid decision.', array( 'status' => 400 ) );
	}
	if ( isset( $input['reason'] ) && ! is_string( $input['reason'] ) ) { return new WP_Error( 'ax_review_reason', 'Invalid reason.', array( 'status' => 400 ) ); }
	$reason = trim( sanitize_textarea_field( $input['reason'] ?? '' ) );
	if ( strlen( $reason ) > 4096 || ( 'reject' === $input['decision'] && '' === $reason ) ) {
		return new WP_Error( 'ax_review_reason', 'A rejection reason is required (maximum 4096 bytes).', array( 'status' => 400 ) );
	}
	return axismundi_act_review_community_post( get_current_user_id(), $moderator, $group, $uri, 'approve' === $input['decision'], $reason );
}

function axismundi_act_handle_admin_review() : void {
	if ( 'POST' !== ( $_SERVER['REQUEST_METHOD'] ?? '' ) ) { wp_die( 'A POST request is required.', '', array( 'response' => 405 ) ); }
	$result = axismundi_act_admin_review_request( wp_unslash( $_POST ) );
	if ( is_wp_error( $result ) ) {
		wp_die( esc_html( $result->get_error_message() ), '', array( 'response' => (int) ( $result->get_error_data()['status'] ?? 400 ) ) );
	}
	wp_safe_redirect( add_query_arg( array( 'page' => 'axismundi-community-review', 'group_id' => absint( $_POST['group_id'] ), 'reviewed' => '1' ), admin_url( 'tools.php' ) ), 303 );
	exit;
}
add_action( 'admin_post_axismundi_community_review', 'axismundi_act_handle_admin_review' );

/** Register even with no selected Person or memberships; an empty workspace is not a 403. */
function axismundi_act_register_workflow_pages() : void {
	add_management_page( __( 'C2S Composer', 'axismundi-activities' ), __( 'C2S Composer', 'axismundi-activities' ), 'read', 'axismundi-c2s-composer', 'axismundi_act_render_composer' );
	// Keep existing bookmarks while replacing the old conditional Review surface.
	add_management_page( __( 'Community Moderation', 'axismundi-activities' ), __( 'Community Moderation', 'axismundi-activities' ), 'read', 'axismundi-community-review', 'axismundi_act_render_community_review' );
}
add_action( 'admin_menu', 'axismundi_act_register_workflow_pages' );

/** Bounded pending rows; only called after checking the selected moderator. */
function axismundi_act_admin_pending_posts( Axismundi_Actor $moderator, Axismundi_Actor $group, int $before = PHP_INT_MAX ) : array {
	global $wpdb;
	if ( ! axismundi_act_admin_review_allowed( $moderator, $group ) ) { return array(); }
	return $wpdb->get_results( $wpdb->prepare( "SELECT * FROM %i WHERE group_id=%d AND admission_state='pending' AND id<%d ORDER BY id DESC LIMIT 21", axismundi_actors_community_posts_table(), $group->get_identity_id(), $before ), ARRAY_A ) ?: array();
}

function axismundi_act_render_community_review() : void {
	if ( ! current_user_can( 'read' ) ) { wp_die( 'Sign in to moderate Communities.', '', array( 'response' => 403 ) ); }
	$moderator = axismundi_act_admin_publisher();
	$groups = axismundi_act_admin_review_groups( $moderator );
	echo '<div class="wrap"><h1>' . esc_html__( 'Community Moderation', 'axismundi-activities' ) . '</h1>';
	axismundi_act_admin_account_switcher();
	if ( is_wp_error( $moderator ) ) {
		echo '<div class="notice notice-warning"><p>' . esc_html( $moderator->get_error_message() ) . '</p></div></div>'; return;
	}
	echo '<p><strong>' . esc_html( $moderator->get_display_name() ) . '</strong> <code style="overflow-wrap:anywhere">' . esc_html( $moderator->get_uri() ) . '</code></p>';
	if ( 'Person' !== $moderator->get_type() ) {
		echo '<div class="notice notice-info"><p>' . esc_html__( 'Select a Person Account for Community moderation.', 'axismundi-activities' ) . '</p></div></div>'; return;
	}
	if ( isset( $_GET['migrated'] ) && '1' === $_GET['migrated'] ) { echo '<div class="notice notice-success"><p>' . esc_html__( 'Community migration completed.', 'axismundi-activities' ) . '</p></div>'; }
	axismundi_act_render_community_migrations( $moderator );
	if ( ! $groups ) { echo '<p>' . esc_html__( 'You do not moderate any Communities as this Person.', 'axismundi-activities' ) . '</p></div>'; return; }
	$selected = isset( $_GET['group_id'] ) && is_string( $_GET['group_id'] ) ? absint( $_GET['group_id'] ) : $groups[0]->get_identity_id();
	$group = axismundi_actors_get_by_identity( $selected );
	if ( ! axismundi_act_admin_review_allowed( $moderator, $group ) ) {
		echo '<div class="notice notice-error"><p>' . esc_html__( 'You cannot moderate the selected Community.', 'axismundi-activities' ) . '</p></div></div>'; return;
	}
	$before = isset( $_GET['before'] ) && is_string( $_GET['before'] ) ? absint( $_GET['before'] ) : PHP_INT_MAX;
	$rows = axismundi_act_admin_pending_posts( $moderator, $group, $before );
	$more = count( $rows ) > 20;
	$rows = array_slice( $rows, 0, 20 );
	if ( isset( $_GET['reviewed'] ) && '1' === $_GET['reviewed'] ) { echo '<div class="notice notice-success"><p>' . esc_html__( 'Decision recorded.', 'axismundi-activities' ) . '</p></div>'; }
	echo '<form method="get"><input type="hidden" name="page" value="axismundi-community-review"><label for="ax-review-group">' . esc_html__( 'Community', 'axismundi-activities' ) . '</label> <select id="ax-review-group" name="group_id">';
	foreach ( $groups as $option ) { echo '<option value="' . esc_attr( $option->get_identity_id() ) . '" ' . selected( $selected, $option->get_identity_id(), false ) . '>' . esc_html( $option->get_display_name() ) . '</option>'; }
	echo '</select> '; submit_button( __( 'Filter', 'axismundi-activities' ), 'secondary', '', false ); echo '</form>';
	echo '<h2>' . esc_html__( 'Pending submissions', 'axismundi-activities' ) . '</h2>';
	echo '<table class="widefat striped"><thead><tr><th>' . esc_html__( 'Submission', 'axismundi-activities' ) . '</th><th>' . esc_html__( 'Author', 'axismundi-activities' ) . '</th><th>' . esc_html__( 'Received (UTC)', 'axismundi-activities' ) . '</th><th>' . esc_html__( 'Decision', 'axismundi-activities' ) . '</th></tr></thead><tbody>';
	foreach ( $rows as $row ) {
		$create = axismundi_act_get( $row['create_uri'] );
		$object = $create ? ( $create->get_payload()['object'] ?? null ) : null;
		$title = is_array( $object ) && is_string( $object['name'] ?? null ) ? $object['name'] : $row['object_uri'];
		echo '<tr><td style="overflow-wrap:anywhere"><a href="' . esc_url( $row['object_uri'] ) . '">' . esc_html( $title ) . '</a>';
		if ( is_array( $object ) ) {
			echo '<p>' . esc_html( is_string( $object['type'] ?? null ) ? $object['type'] : '' ) . '</p><details><summary>' . esc_html__( 'Content', 'axismundi-activities' ) . '</summary><pre style="white-space:pre-wrap;overflow-wrap:anywhere">' . esc_html( is_string( $object['content'] ?? null ) ? $object['content'] : '' ) . '</pre></details>';
		}
		echo '</td><td style="overflow-wrap:anywhere"><code>' . esc_html( $row['author_uri'] ) . '</code></td><td>' . esc_html( $row['created_at'] ) . '</td><td><form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		foreach ( array( 'action' => 'axismundi_community_review', 'moderator_id' => $moderator->get_identity_id(), 'group_id' => $selected, 'object_uri' => $row['object_uri'] ) as $key => $value ) { echo '<input type="hidden" name="' . esc_attr( $key ) . '" value="' . esc_attr( $value ) . '">'; }
		wp_nonce_field( axismundi_act_admin_review_nonce_action( $moderator->get_identity_id(), $selected, $row['object_uri'] ) );
		echo '<p><label for="ax-review-reason-' . esc_attr( $row['id'] ) . '">' . esc_html__( 'Rejection reason', 'axismundi-activities' ) . '</label><textarea class="large-text" id="ax-review-reason-' . esc_attr( $row['id'] ) . '" name="reason" rows="2" maxlength="1024" required></textarea></p>';
		echo '<button class="button button-primary" name="decision" value="approve" formnovalidate>' . esc_html__( 'Approve', 'axismundi-activities' ) . '</button> <button class="button" name="decision" value="reject">' . esc_html__( 'Reject', 'axismundi-activities' ) . '</button></form></td></tr>';
	}
	if ( ! $rows ) { echo '<tr><td colspan="4">' . esc_html__( 'No pending submissions.', 'axismundi-activities' ) . '</td></tr>'; }
	echo '</tbody></table>';
	if ( $more ) { echo '<p><a class="button" href="' . esc_url( add_query_arg( array( 'page' => 'axismundi-community-review', 'group_id' => $selected, 'before' => end( $rows )['id'] ), admin_url( 'tools.php' ) ) ) . '">' . esc_html__( 'Next', 'axismundi-activities' ) . '</a></p>'; }
	echo '</div>';
}

function axismundi_act_render_composer() : void {
	if ( ! current_user_can( 'read' ) ) { wp_die( 'Sign in to compose.', '', array( 'response' => 403 ) ); }
	$actor = axismundi_act_admin_publisher();
	echo '<div class="wrap"><h1>' . esc_html__( 'C2S Composer', 'axismundi-activities' ) . '</h1>';
	axismundi_act_admin_account_switcher();
	if ( is_wp_error( $actor ) || 'Person' !== $actor->get_type() ) {
		echo '<div class="notice notice-warning"><p>' . esc_html( is_wp_error( $actor ) ? $actor->get_error_message() : __( 'Select an active Person Account.', 'axismundi-activities' ) ) . '</p></div></div>'; return;
	}
	echo '<p><strong>' . esc_html( $actor->get_display_name() ) . '</strong> <code>' . esc_html( $actor->get_uri() ) . '</code></p>';
	echo '<form id="ax-c2s-composer"><table class="form-table"><tr><th><label for="ax-c2s-parent">' . esc_html__( 'Parent Object URI', 'axismundi-activities' ) . '</label></th><td><input class="large-text" type="url" id="ax-c2s-parent" required></td></tr><tr><th><label for="ax-c2s-content">' . esc_html__( 'Reply', 'axismundi-activities' ) . '</label></th><td><textarea class="large-text" id="ax-c2s-content" rows="8" required></textarea></td></tr></table>';
	echo '<p>' . esc_html__( 'Public', 'axismundi-activities' ) . '</p>';
	submit_button( __( 'Publish reply', 'axismundi-activities' ) );
	echo '</form><div id="ax-c2s-result" role="status" aria-live="polite"></div></div>';
	wp_enqueue_script( 'axismundi-c2s-composer', plugins_url( 'assets/c2s-composer.js', dirname( __DIR__ ) . '/axismundi-activities.php' ), array(), AXISMUNDI_ACTIVITIES_VERSION, true );
	wp_add_inline_script( 'axismundi-c2s-composer', 'window.axismundiC2SComposer = ' . wp_json_encode( array( 'outbox' => axismundi_op_actor_outbox_url( $actor ), 'nonce' => wp_create_nonce( 'wp_rest' ), 'success' => __( 'Reply published.', 'axismundi-activities' ), 'failure' => __( 'Submission failed. Retry without changing the reply.', 'axismundi-activities' ), 'busy' => __( 'Publishing...', 'axismundi-activities' ) ) ) . ';', 'before' );
}
