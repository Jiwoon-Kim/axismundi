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
	if ( 'top' === $which && $screen && in_array( $screen->id, array( 'edit-post', 'upload' ), true ) ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only filters.
		axismundi_op_admin_publishing_controls( axismundi_op_admin_publishing_selection( wp_unslash( $_GET ) ) );
	}
}
add_action( 'restrict_manage_posts', 'axismundi_op_admin_publishing_list_controls', 10, 2 );

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
