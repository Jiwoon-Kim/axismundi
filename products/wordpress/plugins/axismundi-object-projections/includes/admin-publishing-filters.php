<?php
/** Publishing filters for the native Posts and Media Library screens. */
defined( 'ABSPATH' ) || exit;

function axismundi_op_admin_publishing_actors() : array {
	return function_exists( 'axismundi_actors_acting_actor_options' ) ? array_filter(
		axismundi_actors_acting_actor_options(),
		static fn( $actor ) => $actor->is_local() && 'public' === $actor->get_status()
	) : array();
}

function axismundi_op_admin_publishing_selection( array $input ) : array {
	return array(
		'actor' => is_string( $input['ax_publishing_actor'] ?? null ) ? $input['ax_publishing_actor'] : '',
		'unpublished' => '1' === ( $input['ax_not_federated'] ?? '' ),
	);
}

function axismundi_op_admin_publishing_controls( array $selection ) : void {
	?>
	<label class="screen-reader-text" for="ax-publishing-actor"><?php esc_html_e( 'Publishing Actor', 'axismundi-object-projections' ); ?></label>
	<select id="ax-publishing-actor" name="ax_publishing_actor">
		<option value=""><?php esc_html_e( 'All publishing actors', 'axismundi-object-projections' ); ?></option>
		<option value="unassigned" <?php selected( $selection['actor'], 'unassigned' ); ?>><?php esc_html_e( 'Unassigned', 'axismundi-object-projections' ); ?></option>
		<?php foreach ( axismundi_op_admin_publishing_actors() as $actor ) : ?>
			<option value="<?php echo esc_attr( $actor->get_identity_id() ); ?>" <?php selected( $selection['actor'], (string) $actor->get_identity_id() ); ?>><?php echo esc_html( axismundi_actors_acting_actor_label( $actor ) ); ?></option>
		<?php endforeach; ?>
	</select>
	<?php if ( function_exists( 'axismundi_act_has_outbound_create' ) ) : ?>
		<label><input type="checkbox" name="ax_not_federated" value="1" <?php checked( $selection['unpublished'] ); ?>> <?php esc_html_e( 'Not federated', 'axismundi-object-projections' ); ?></label>
	<?php endif;
}

function axismundi_op_admin_publishing_list_controls( $post_type, string $which ) : void {
	$screen = get_current_screen();
	$positions = array( 'edit-post' => 'top', 'upload' => 'bar' );
	if ( $screen && $which === ( $positions[ $screen->id ] ?? null ) ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only filters.
		axismundi_op_admin_publishing_controls( axismundi_op_admin_publishing_selection( wp_unslash( $_GET ) ) );
	}
}
add_action( 'restrict_manage_posts', 'axismundi_op_admin_publishing_list_controls', 10, 2 );

function axismundi_op_admin_media_columns( array $columns ) : array {
	$attribution = array( 'ax_attributed_to' => __( 'Attributed to', 'axismundi-object-projections' ) );
	$parent = array_search( 'parent', array_keys( $columns ), true );
	if ( false === $parent ) {
		return $columns + $attribution;
	}

	return array_slice( $columns, 0, $parent + 1, true ) + $attribution + array_slice( $columns, $parent + 1, null, true );
}
add_filter( 'manage_media_columns', 'axismundi_op_admin_media_columns' );

function axismundi_op_admin_media_attribution_column( string $column, int $post_id ) : void {
	if ( 'ax_attributed_to' !== $column ) {
		return;
	}

	$attachment = get_post( $post_id );
	if ( ! $attachment || 'attachment' !== $attachment->post_type ) {
		return;
	}

	axismundi_op_admin_render_actor_assignment( $attachment, axismundi_op_attachment_actor_uri( $attachment ) );
}
add_action( 'manage_media_custom_column', 'axismundi_op_admin_media_attribution_column', 10, 2 );

function axismundi_op_admin_render_actor_assignment( WP_Post $post, string $uri, bool $assignable = true ) : void {
	$is_post = 'post' === $post->post_type;
	$uris = $is_post ? axismundi_op_post_attribution_uris( $post, $uri ) : ( '' !== $uri ? array( $uri ) : array() );
	$labels = array();
	foreach ( $uris as $member ) {
		$actor = function_exists( 'axismundi_actors_get_by_uri' ) ? axismundi_actors_get_by_uri( $member ) : null;
		$label = '';
		if ( $actor instanceof Axismundi_Actor ) {
			$label = $is_post ? axismundi_actors_mention_handle( $actor ) : axismundi_actors_acting_actor_label( $actor );
		}
		$labels[] = '' !== $label ? $label : $member;
	}
	printf(
		'<span data-assigned-actor title="%s" style="overflow-wrap: anywhere">%s</span>',
		esc_attr( implode( ', ', $uris ) ),
		esc_html( $labels ? implode( ', ', $labels ) : __( 'Unassigned', 'axismundi-object-projections' ) )
	);

	if ( $assignable && 'trash' !== $post->post_status && current_user_can( 'edit_post', $post->ID ) && function_exists( 'axismundi_actors_search_assignable' ) ) {
		printf(
			'<br><button type="button" class="button-link ax-assign-list-actor" aria-haspopup="dialog" aria-controls="find-actors" data-post-id="%d" data-post-type="%s" data-current-actor="%s">%s</button>',
			$post->ID,
			esc_attr( $post->post_type ),
			esc_attr( $uri ),
			esc_html__( 'Assign', 'axismundi-object-projections' )
		);
	}
}
add_action( 'axismundi_op_post_federation_actions', static function ( WP_Post $post ) : void {
	$creator = function_exists( 'axismundi_act_outbound_create_actor' ) ? axismundi_act_outbound_create_actor( axismundi_op_post_object_uri( $post ) ) : '';
	$uri = $creator ?: axismundi_op_stored_post_attribution( $post->ID );
	echo '<div class="ax-post-actor-assignment">' . esc_html__( 'Attributed to', 'axismundi-object-projections' ) . '<br>';
	axismundi_op_admin_render_actor_assignment( $post, $uri, '' === $creator );
	echo '</div>';
}, 5 );

function axismundi_op_admin_actor_assignment_source( int $post_id, int $user_id ) {
	$post = $post_id > 0 ? get_post( $post_id ) : null;
	if ( ! $post || ! in_array( $post->post_type, array( 'post', 'attachment' ), true ) || 'trash' === $post->post_status || ! user_can( $user_id, 'edit_post', $post_id ) ) {
		return new WP_Error( 'ax_op_actor_assignment', __( 'You cannot edit this item.', 'axismundi-object-projections' ), array( 'status' => 403 ) );
	}
	return $post;
}

function axismundi_op_admin_list_actor_options( int $post_id, int $user_id, string $search ) {
	$post = axismundi_op_admin_actor_assignment_source( $post_id, $user_id );
	if ( is_wp_error( $post ) ) { return $post; }
	if ( ! function_exists( 'axismundi_actors_search_assignable' ) ) {
		return array();
	}
	$search = trim( ltrim( trim( $search ), '@' ) );
	$allow_other_local = 'attachment' === $post->post_type && '' !== $search && user_can( $user_id, 'manage_options' ) && function_exists( 'axismundi_act_has_outbound_create' )
		&& false === axismundi_act_has_outbound_create( axismundi_op_core_attachment_uri( $post ) );
	return array_map(
		static fn( $actor ) => array(
			'uri' => $actor->get_uri(),
			'label' => axismundi_actors_acting_actor_label( $actor ),
			'handle' => '' !== $actor->get_display_name() ? axismundi_actors_mention_handle( $actor ) : '',
			'type' => $actor->get_type(),
			'adminAssignment' => $allow_other_local && ! axismundi_actors_can_act_as( $actor, $user_id ),
		),
		axismundi_actors_search_assignable( $search, $user_id, $allow_other_local )
	);
}

function axismundi_op_admin_actor_picker_screen() : bool {
	$screen = get_current_screen();
	return $screen && function_exists( 'axismundi_actors_search_assignable' )
		&& ( 'edit-post' === $screen->id || ( 'upload' === $screen->id && 'list' === ( $GLOBALS['mode'] ?? '' ) ) );
}

function axismundi_op_admin_list_actor_dialog() : void {
	if ( ! axismundi_op_admin_actor_picker_screen() ) {
		return;
	}
	?>
	<dialog id="find-actors" aria-labelledby="find-actors-head">
		<form id="find-actors-form">
			<header>
				<h2 id="find-actors-head"><?php esc_html_e( 'Assign to a local actor', 'axismundi-object-projections' ); ?></h2>
				<button type="button" class="button-link" data-close aria-label="<?php esc_attr_e( 'Close', 'axismundi-object-projections' ); ?>"><span class="dashicons dashicons-no-alt" aria-hidden="true"></span></button>
			</header>
			<div class="ax-find-actors-results">
				<label class="screen-reader-text" for="find-actors-input"><?php esc_html_e( 'Search actors', 'axismundi-object-projections' ); ?></label>
				<input id="find-actors-input" type="search" placeholder="<?php esc_attr_e( 'Search actors', 'axismundi-object-projections' ); ?>" autofocus>
				<table class="widefat striped" hidden>
					<thead><tr><th scope="col"><?php esc_html_e( 'Actor', 'axismundi-object-projections' ); ?></th><th scope="col"><?php esc_html_e( 'Type', 'axismundi-object-projections' ); ?></th></tr></thead>
					<tbody></tbody>
				</table>
				<p data-loading role="status" hidden><?php esc_html_e( 'Loading actors...', 'axismundi-object-projections' ); ?></p>
				<p data-empty hidden><?php esc_html_e( 'No matching actors.', 'axismundi-object-projections' ); ?></p>
				<label data-update-warning hidden><input type="checkbox" name="confirm_update" value="1"> <?php esc_html_e( 'Changing the assigned actor will publish a federated Update.', 'axismundi-object-projections' ); ?></label>
				<p data-error role="alert" hidden></p>
			</div>
			<footer>
				<?php wp_nonce_field( 'ax_op_assign_list_actor', 'nonce', false ); ?>
				<button type="submit" class="button button-primary" disabled><?php esc_html_e( 'Select', 'axismundi-object-projections' ); ?></button>
			</footer>
		</form>
	</dialog>
	<?php
}
add_action( 'admin_footer-upload.php', 'axismundi_op_admin_list_actor_dialog' );
add_action( 'admin_footer-edit.php', 'axismundi_op_admin_list_actor_dialog' );

add_action( 'admin_enqueue_scripts', static function () : void {
	if ( ! axismundi_op_admin_actor_picker_screen() ) {
		return;
	}
	$plugin = dirname( __DIR__ ) . '/axismundi-object-projections.php';
	wp_enqueue_script( 'axismundi-op-media-actor', plugins_url( 'assets/media-actor.js', $plugin ), array( 'wp-i18n' ), AXISMUNDI_OP_VERSION, true );
	wp_set_script_translations( 'axismundi-op-media-actor', 'axismundi-object-projections' );
	wp_enqueue_style( 'axismundi-op-media-actor', plugins_url( 'assets/media-actor.css', $plugin ), array(), AXISMUNDI_OP_VERSION );
} );

add_action( 'wp_ajax_ax_op_search_list_actors', static function () : void {
	if ( ! check_ajax_referer( 'ax_op_assign_list_actor', 'nonce', false ) ) {
		wp_send_json_error( __( 'The session expired. Reload the page and try again.', 'axismundi-object-projections' ), 403 );
	}
	$post_id = is_string( $_POST['post_id'] ?? null ) ? absint( $_POST['post_id'] ) : 0;
	$search = is_string( $_POST['search'] ?? null ) ? sanitize_text_field( wp_unslash( $_POST['search'] ) ) : '';
	$result = axismundi_op_admin_list_actor_options( $post_id, get_current_user_id(), $search );
	if ( is_wp_error( $result ) ) {
		wp_send_json_error( $result->get_error_message(), $result->get_error_data()['status'] ?? 500 );
	}
	$post = get_post( $post_id );
	$lifecycle = 'post' === $post->post_type && function_exists( 'axismundi_act_get_object_lifecycle' )
		? axismundi_act_get_object_lifecycle( axismundi_op_post_object_uri( $post ) ) : null;
	wp_send_json_success( array( 'actors' => $result, 'warnUpdate' => $lifecycle && 'Delete' !== $lifecycle->get_type() ) );
} );

function axismundi_op_admin_assign_list_actor( int $post_id, string $uri, int $user_id, bool $confirm_update = false ) {
	$post = axismundi_op_admin_actor_assignment_source( $post_id, $user_id );
	if ( is_wp_error( $post ) ) { return $post; }
	if ( '' === $uri ) {
		return new WP_Error( 'ax_op_actor_assignment', __( 'Choose an actor.', 'axismundi-object-projections' ), array( 'status' => 400 ) );
	}
	$media = 'attachment' === $post->post_type;
	return axismundi_op_set_post_attribution( $post_id, $uri, $user_id, null, false, $media, $media || $confirm_update );
}

add_action( 'wp_ajax_ax_op_assign_list_actor', static function () : void {
	if ( ! check_ajax_referer( 'ax_op_assign_list_actor', 'nonce', false ) ) {
		wp_send_json_error( __( 'The session expired. Reload the page and try again.', 'axismundi-object-projections' ), 403 );
	}
	$post_id = is_string( $_POST['post_id'] ?? null ) ? absint( $_POST['post_id'] ) : 0;
	$uri = is_string( $_POST['actor'] ?? null ) ? wp_unslash( $_POST['actor'] ) : '';
	$result = axismundi_op_admin_assign_list_actor( $post_id, $uri, get_current_user_id(), '1' === ( $_POST['confirm_update'] ?? '' ) );
	if ( is_wp_error( $result ) ) {
		wp_send_json_error( $result->get_error_message(), $result->get_error_data()['status'] ?? 500 );
	}
	wp_send_json_success();
} );

/** The grid passes its URL query variables to the core attachment AJAX request. */
function axismundi_op_admin_publishing_grid_controls() : void {
	$screen = get_current_screen();
	if ( ! $screen || 'upload' !== $screen->id || 'grid' !== ( $GLOBALS['mode'] ?? '' ) ) {
		return;
	}
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only filters.
	$input = wp_unslash( $_GET );
	?>
	<form method="get" action="<?php echo esc_url( admin_url( 'upload.php' ) ); ?>">
		<input type="hidden" name="mode" value="grid">
		<?php axismundi_op_admin_publishing_controls( axismundi_op_admin_publishing_selection( $input ) ); ?>
		<?php submit_button( __( 'Filter' ), 'secondary', '', false ); ?>
	</form>
	<?php
}
add_action( 'admin_notices', 'axismundi_op_admin_publishing_grid_controls' );

function axismundi_op_admin_publishing_list_query( WP_Query $query ) : void {
	if ( ! is_admin() || ! $query->is_main_query() ) {
		return;
	}
	$page = $GLOBALS['pagenow'] ?? '';
	$type = $query->get( 'post_type' ) ?: 'post';
	if ( ( 'edit.php' === $page && 'post' === $type ) || ( 'upload.php' === $page && 'attachment' === $type ) ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only filters.
		$query->set( 'ax_op_publishing_selection', axismundi_op_admin_publishing_selection( wp_unslash( $_GET ) ) );
	}
}
add_action( 'pre_get_posts', 'axismundi_op_admin_publishing_list_query' );

function axismundi_op_admin_publishing_grid_query( array $args ) : array {
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Core attachment query is read-only and requires upload_files.
	$input = isset( $_REQUEST['query'] ) && is_array( $_REQUEST['query'] ) ? wp_unslash( $_REQUEST['query'] ) : array();
	$args['ax_op_publishing_selection'] = axismundi_op_admin_publishing_selection( $input );
	return $args;
}
add_filter( 'ajax_query_attachments_args', 'axismundi_op_admin_publishing_grid_query' );

function axismundi_op_admin_publishing_where( string $where, WP_Query $query ) : string {
	global $wpdb;
	$selection = $query->get( 'ax_op_publishing_selection' );
	$type = $query->get( 'post_type' ) ?: 'post';
	if ( ! is_array( $selection ) || ! in_array( $type, array( 'post', 'attachment' ), true ) ) {
		return $where;
	}
	$actor = $selection['actor'];
	if ( '' === $actor && ! $selection['unpublished'] ) {
		return $where;
	}
	// EXISTS preserves one row per WP post even if the registry has more than one source binding.
	$source = $wpdb->prepare( "FROM %i o WHERE o.source_owner = 'wordpress-core' AND o.source_kind = %s AND o.source_ref = {$wpdb->posts}.ID AND o.origin = 'local'", axismundi_op_objects_table(), $type );
	if ( 'unassigned' === $actor ) {
		$where .= " AND NOT EXISTS (SELECT 1 {$source} AND COALESCE(o.attributed_to_uri, '') <> '')";
		$where .= $wpdb->prepare( " AND NOT EXISTS (SELECT 1 FROM %i m WHERE m.post_id = {$wpdb->posts}.ID AND m.meta_key = %s AND m.meta_value <> '')", $wpdb->postmeta, AXISMUNDI_OP_ATTRIBUTED_TO_META );
	} elseif ( '' !== $actor ) {
		$identity = ctype_digit( $actor ) ? axismundi_actors_get_by_identity( (int) $actor ) : null;
		if ( ! $identity instanceof Axismundi_Actor || ! $identity->is_local() || 'public' !== $identity->get_status()
			|| ! axismundi_actors_can_act_as( $identity, get_current_user_id() ) ) {
			return $where . ' AND 1 = 0';
		}
		$where .= " AND EXISTS (SELECT 1 {$source}" . $wpdb->prepare( ' AND o.attributed_identity_id = %d)', $identity->get_identity_id() );
	}
	if ( ! $selection['unpublished'] ) {
		return $where;
	}
	if ( ! function_exists( 'axismundi_act_has_outbound_create' ) || AXISMUNDI_ACT_DB_VERSION !== (string) get_option( AXISMUNDI_ACT_DB_VERSION_OPTION, '' ) ) {
		return $where . ' AND 1 = 0';
	}
	// A missing or zero projection is not proof of non-publication. Check the ledger by URI.
	$where .= ' AND NOT EXISTS (' . $wpdb->prepare(
		"SELECT 1 {$source} AND EXISTS (SELECT 1 FROM %i i WHERE i.object_uri_hash = o.canonical_uri_hash AND i.source = 'local' AND i.federated = 1))",
		axismundi_op_object_index_table()
	);
	$where .= ' AND NOT EXISTS (' . $wpdb->prepare(
		"SELECT 1 {$source} AND EXISTS (SELECT 1 FROM %i a WHERE a.object_uri_hash = o.canonical_uri_hash AND a.object_uri = o.canonical_uri AND a.direction = 'outbound' AND a.activity_type = 'Create'))",
		axismundi_act_activities_table()
	);
	// Legacy rows may predate the registry. Only synthesize the default URI when it is authoritative.
	if ( 'post' === $type && has_filter( 'axismundi_op_post_object_uri' ) ) {
		return $where . " AND EXISTS (SELECT 1 {$source})";
	}
	$prefix = add_query_arg( 'attachment' === $type ? 'attachment_id' : 'p', '', home_url( '/' ) );
	$prefix = rtrim( $prefix, '=' ) . '=';
	$where .= " AND (EXISTS (SELECT 1 {$source}) OR NOT EXISTS (" . $wpdb->prepare(
		"SELECT 1 FROM %i a WHERE a.object_uri_hash = SHA2(CONCAT(%s, {$wpdb->posts}.ID), 256) AND a.direction = 'outbound' AND a.activity_type = 'Create'))",
		axismundi_act_activities_table(),
		$prefix
	);
	return $where;
}
add_filter( 'posts_where', 'axismundi_op_admin_publishing_where', 10, 2 );

function axismundi_op_admin_publishing_bulk_actions( array $actions ) : array {
	if ( function_exists( 'axismundi_act_has_outbound_create' ) ) {
		foreach ( axismundi_op_admin_publishing_actors() as $actor ) {
			/* translators: %s: Publishing Actor's name. */
			$actions[ 'ax_assign_publisher_' . $actor->get_identity_id() ] = sprintf( __( 'Assign unpublished to %s', 'axismundi-object-projections' ), axismundi_actors_acting_actor_label( $actor ) );
		}
	}
	return $actions;
}

function axismundi_op_admin_publishing_bulk_assign( string $url, string $action, array $ids ) : string {
	if ( ! preg_match( '/^ax_assign_publisher_([1-9][0-9]*)$/D', $action, $matches ) ) {
		return $url;
	}
	// Core validates the bulk-posts/bulk-media nonce before calling this handler.
	$actor = axismundi_actors_get_by_identity( (int) $matches[1] );
	$type = 'handle_bulk_actions-upload' === current_filter() ? 'attachment' : 'post';
	$assigned = 0;
	foreach ( $ids as $id ) {
		$post = get_post( $id );
		if ( $actor instanceof Axismundi_Actor && $post instanceof WP_Post && $type === $post->post_type
			&& true === axismundi_op_set_post_attribution( $post->ID, $actor->get_uri(), get_current_user_id(), null, true ) ) {
			++$assigned;
		}
	}
	return add_query_arg( array( 'ax_publisher_assigned' => $assigned, 'ax_publisher_skipped' => count( $ids ) - $assigned ), $url );
}

foreach ( array( 'edit-post', 'upload' ) as $axismundi_op_publishing_screen ) {
	add_filter( 'bulk_actions-' . $axismundi_op_publishing_screen, 'axismundi_op_admin_publishing_bulk_actions' );
	add_filter( 'handle_bulk_actions-' . $axismundi_op_publishing_screen, 'axismundi_op_admin_publishing_bulk_assign', 10, 3 );
}
unset( $axismundi_op_publishing_screen );

function axismundi_op_admin_publishing_bulk_notice() : void {
	$screen = get_current_screen();
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Counts from the completed action's redirect.
	if ( ! $screen || ! in_array( $screen->id, array( 'edit-post', 'upload' ), true ) || ! isset( $_GET['ax_publisher_assigned'], $_GET['ax_publisher_skipped'] ) ) {
		return;
	}
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Counts only.
	$assigned = is_scalar( $_GET['ax_publisher_assigned'] ) ? absint( $_GET['ax_publisher_assigned'] ) : 0;
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Counts only.
	$skipped = is_scalar( $_GET['ax_publisher_skipped'] ) ? absint( $_GET['ax_publisher_skipped'] ) : 0;
	/* translators: 1: Assigned objects. 2: Skipped objects. */
	wp_admin_notice( esc_html( sprintf( __( 'Publishing Actor assigned: %1$s. Skipped: %2$s.', 'axismundi-object-projections' ), number_format_i18n( $assigned ), number_format_i18n( $skipped ) ) ), array( 'type' => $skipped ? 'warning' : 'success', 'dismissible' => true ) );
}
add_action( 'admin_notices', 'axismundi_op_admin_publishing_bulk_notice' );
