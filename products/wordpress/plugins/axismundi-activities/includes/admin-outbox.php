<?php
/** Private, read-only activity history for the selected local Actor. */
defined( 'ABSPATH' ) || exit;

add_action( 'admin_menu', 'axismundi_act_register_admin_outbox', 11 );

function axismundi_act_register_admin_outbox() : void {
	add_submenu_page( axismundi_actors_accounts_menu_slug(), __( 'Outbox', 'axismundi-activities' ), __( 'Outbox', 'axismundi-activities' ), 'edit_posts', 'axismundi-outbox', 'axismundi_act_render_admin_outbox' );
}

function axismundi_act_admin_outbox_date( $value ) : string {
	if ( ! is_string( $value ) || ! preg_match( '/\A([0-9]{4})-([0-9]{2})-([0-9]{2})\z/', $value, $parts ) ) {
		return '';
	}
	return checkdate( (int) $parts[2], (int) $parts[3], (int) $parts[1] ) && (int) $parts[1] >= 1000 ? $value : '';
}

function axismundi_act_admin_outbox_rows( Axismundi_Actor $actor, string $type, string $target, int $before, string $from = '', string $through = '' ) {
	global $wpdb;
	$allowed = axismundi_act_outbox_authorize( get_current_user_id(), $actor );
	if ( is_wp_error( $allowed ) ) {
		return $allowed;
	}
	$uri = $actor->get_uri();
	// Empty filters are wildcards; the Actor scope is always mandatory.
	$sql = "SELECT id, activity_type, object_uri, activity_uri, feed_sort_at, effective_status
		FROM %i WHERE actor_uri_hash = %s AND actor_uri = %s
		AND direction = 'outbound'
		AND (%s = '' OR activity_type = %s)
		AND (%s = '' OR (object_uri_hash = %s AND object_uri = %s))
		AND (%s = '' OR feed_sort_at >= %s)
		AND (%s = '' OR feed_sort_at <= %s)
		AND (%d = 0 OR id < %d) ORDER BY id DESC LIMIT 26";
	// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Fixed query with bound arguments; private history is read fresh.
	$rows = $wpdb->get_results( $wpdb->prepare( $sql, axismundi_act_activities_table(), hash( 'sha256', $uri ), $uri, $type, $type, $target, hash( 'sha256', $target ), $target, $from, ( $from ?: '1000-01-01' ) . ' 00:00:00', $through, ( $through ?: '9999-12-31' ) . ' 23:59:59', $before, $before ), ARRAY_A );
	return $wpdb->last_error ? new WP_Error( 'ax_outbox_read_failed', __( 'Activity history is unavailable.', 'axismundi-activities' ) ) : $rows;
}

function axismundi_act_render_admin_outbox() : void {
	if ( ! current_user_can( 'edit_posts' ) ) {
		wp_die( esc_html__( 'You cannot access this page.', 'axismundi-activities' ) );
	}
	$actor = axismundi_act_admin_publisher();
	echo '<div class="wrap"><h1>' . esc_html__( 'Outbox', 'axismundi-activities' ) . '</h1>';
	axismundi_act_admin_account_switcher();
	if ( is_wp_error( $actor ) ) {
		echo '<p>' . esc_html( $actor->get_error_message() ) . '</p></div>';
		return;
	}
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only, Actor-authorized filters.
	$input = wp_unslash( $_GET );
	$admin_url = axismundi_actors_account_admin_url( 'axismundi-outbox' );
	$history = 'history' === ( $input['view'] ?? '' ) || isset( $input['activity_type'], $input['target'] );
	echo '<nav class="nav-tab-wrapper">';
	foreach ( array( 'collection' => __( 'Collection', 'axismundi-activities' ), 'history' => __( 'Activity history', 'axismundi-activities' ) ) as $view => $label ) {
		$current = $history === ( 'history' === $view );
		echo '<a class="nav-tab' . ( $current ? ' nav-tab-active" aria-current="page' : '' ) . '" href="' . esc_url( add_query_arg( 'view', $view, $admin_url ) ) . '">' . esc_html( $label ) . '</a>';
	}
	echo '</nav>';
	if ( ! $history ) {
		$cursor = is_string( $input['cursor'] ?? null ) ? sanitize_text_field( $input['cursor'] ) : '';
		$page = '1' === ( $input['collection_page'] ?? '' );
		if ( (string) $actor->get_identity_id() !== ( $input['actor'] ?? '' ) ) {
			$cursor = '';
			$page = false;
		}
		$request = new WP_REST_Request( 'GET' );
		$request->set_param( 'uuid', $actor->get_uuid() );
		$request->set_param( 'page', $page ? 1 : 0 );
		$request->set_param( 'cursor', $cursor );
		$response = axismundi_op_get_actor_outbox( $request );
		if ( is_wp_error( $response ) ) {
			echo '<p>' . esc_html( $response->get_error_message() ) . '</p></div>';
			return;
		}
		$collection = $response->get_data();
		echo '<p><a href="' . esc_url( axismundi_op_actor_outbox_url( $actor ) ) . '">' . esc_html__( 'Open public Outbox JSON', 'axismundi-activities' ) . '</a></p>';
		$link = $page ? ( $collection['next'] ?? '' ) : ( $collection['first'] ?? '' );
		if ( is_string( $link ) && '' !== $link ) {
			wp_parse_str( (string) wp_parse_url( $link, PHP_URL_QUERY ), $query );
			$next = add_query_arg( array( 'collection_page' => '1', 'actor' => $actor->get_identity_id(), 'cursor' => rawurlencode( (string) ( $query['cursor'] ?? '' ) ) ), $admin_url );
			echo '<p><a class="button" href="' . esc_url( $next ) . '">' . esc_html( $page ? __( 'Next page', 'axismundi-activities' ) : __( 'First page', 'axismundi-activities' ) ) . '</a></p>';
		}
		echo '<pre style="white-space:pre-wrap;overflow-wrap:anywhere">' . esc_html( wp_json_encode( $collection, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) ) . '</pre></div>';
		return;
	}
	$type = sanitize_text_field( is_string( $input['activity_type'] ?? null ) ? $input['activity_type'] : '' );
	$target = sanitize_text_field( is_string( $input['target'] ?? null ) ? $input['target'] : '' );
	$before = isset( $input['actor'] ) && (string) $actor->get_identity_id() === $input['actor'] && is_string( $input['before'] ?? null ) ? max( 0, (int) $input['before'] ) : 0;
	$from = axismundi_act_admin_outbox_date( $input['from'] ?? '' );
	$through = axismundi_act_admin_outbox_date( $input['through'] ?? '' );
	$invalid_dates = ( $input['from'] ?? '' ) !== $from || ( $input['through'] ?? '' ) !== $through
		|| ( '' !== $from && '' !== $through && $from > $through );
	$rows = $invalid_dates
		? new WP_Error( 'ax_outbox_dates', __( 'Enter a valid date range.', 'axismundi-activities' ) )
		: axismundi_act_admin_outbox_rows( $actor, $type, $target, $before, $from, $through );
	echo '<p><strong>' . esc_html( axismundi_actors_acting_actor_label( $actor ) ) . '</strong></p>';
	?>
	<form method="get" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>">
		<input type="hidden" name="page" value="axismundi-outbox">
		<input type="hidden" name="view" value="history">
		<label><?php esc_html_e( 'Activity type', 'axismundi-activities' ); ?> <input name="activity_type" list="ax-outbox-types" value="<?php echo esc_attr( $type ); ?>"></label>
		<datalist id="ax-outbox-types">
			<?php foreach ( array( 'Like', 'Create', 'Update', 'Delete', 'Follow', 'Announce', 'Undo', 'Block', 'Add', 'Remove' ) as $suggestion ) : ?>
				<option value="<?php echo esc_attr( $suggestion ); ?>"></option>
			<?php endforeach; ?>
		</datalist>
		<label><?php esc_html_e( 'Object URI', 'axismundi-activities' ); ?> <input name="target" value="<?php echo esc_attr( $target ); ?>"></label>
		<label><?php esc_html_e( 'From (UTC)', 'axismundi-activities' ); ?> <input type="date" name="from" value="<?php echo esc_attr( $from ); ?>"></label>
		<label><?php esc_html_e( 'Through (UTC)', 'axismundi-activities' ); ?> <input type="date" name="through" value="<?php echo esc_attr( $through ); ?>"></label>
		<?php submit_button( __( 'Filter', 'axismundi-activities' ), 'secondary', '', false ); ?>
	</form>
	<?php
	if ( is_wp_error( $rows ) || ! $rows ) {
		echo '<p>' . esc_html( is_wp_error( $rows ) ? $rows->get_error_message() : __( 'No matching activities.', 'axismundi-activities' ) ) . '</p></div>';
		return;
	}
	$page = array_slice( $rows, 0, 25 );
	?>
	<table class="widefat striped" style="margin-top:1em;overflow-wrap:anywhere">
		<thead><tr>
			<th scope="col"><?php esc_html_e( 'Type', 'axismundi-activities' ); ?></th>
			<th scope="col"><?php esc_html_e( 'Object', 'axismundi-activities' ); ?></th>
			<th scope="col"><?php esc_html_e( 'Activity', 'axismundi-activities' ); ?></th>
			<th scope="col"><?php esc_html_e( 'Time (UTC)', 'axismundi-activities' ); ?></th>
			<th scope="col"><?php esc_html_e( 'Activity state', 'axismundi-activities' ); ?></th>
		</tr></thead>
		<tbody>
		<?php foreach ( $page as $row ) : ?>
			<tr>
			<?php foreach ( array( 'activity_type', 'object_uri', 'activity_uri', 'feed_sort_at', 'effective_status' ) as $field ) : ?>
				<td><?php echo esc_html( $row[ $field ] ?? '' ); ?></td>
			<?php endforeach; ?>
			</tr>
		<?php endforeach; ?>
		</tbody>
	</table>
	<?php
	if ( count( $rows ) > 25 ) {
		$last = end( $page );
		$url = add_query_arg( array( 'page' => 'axismundi-outbox', 'actor' => $actor->get_identity_id(), 'before' => $last['id'], 'activity_type' => rawurlencode( $type ), 'target' => rawurlencode( $target ), 'from' => $from, 'through' => $through ), admin_url( 'admin.php' ) );
		echo '<p><a class="button" href="' . esc_url( $url ) . '">' . esc_html__( 'Older activities', 'axismundi-activities' ) . '</a></p>';
	}
	echo '</div>';
}
