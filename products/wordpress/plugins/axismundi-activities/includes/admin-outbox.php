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

const AXISMUNDI_ACT_ADMIN_OUTBOX_PAGE_SIZE = 25;

function axismundi_act_admin_outbox_rows( Axismundi_Actor $actor, string $type, string $target, int $page, string $from = '', string $through = '' ) {
	global $wpdb;
	$allowed = axismundi_act_outbox_authorize( get_current_user_id(), $actor );
	if ( is_wp_error( $allowed ) ) {
		return $allowed;
	}
	$uri = $actor->get_uri();
	$where = "FROM %i WHERE actor_uri_hash = %s AND actor_uri = %s AND direction = 'outbound'";
	$args  = array( axismundi_act_activities_table(), hash( 'sha256', $uri ), $uri );
	if ( '' !== $type ) {
		$where .= ' AND activity_type = %s';
		$args[] = $type;
	}
	if ( '' !== $target ) {
		$where .= ' AND object_uri_hash = %s AND object_uri = %s';
		array_push( $args, hash( 'sha256', $target ), $target );
	}
	if ( '' !== $from ) {
		$where .= ' AND feed_sort_at >= %s';
		$args[] = $from . ' 00:00:00';
	}
	if ( '' !== $through ) {
		$where .= ' AND feed_sort_at <= %s';
		$args[] = $through . ' 23:59:59';
	}
	$error = new WP_Error( 'ax_outbox_read_failed', __( 'Activity history is unavailable.', 'axismundi-activities' ) );
	// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Shared predicate with bound arguments.
	$total = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) {$where}", $args ) );
	if ( $wpdb->last_error ) {
		return $error;
	}
	$size  = AXISMUNDI_ACT_ADMIN_OUTBOX_PAGE_SIZE;
	$pages = (int) ceil( $total / $size );
	$page  = max( 1, $page );
	$rows  = array();
	if ( $page <= $pages ) {
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Same predicate as the count.
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT id, activity_type, object_uri, activity_uri, feed_sort_at, effective_status {$where} ORDER BY feed_sort_at DESC, id DESC LIMIT %d OFFSET %d", array_merge( $args, array( $size, ( $page - 1 ) * $size ) ) ), ARRAY_A );
		if ( $wpdb->last_error ) {
			return $error;
		}
	}
	return array( 'rows' => $rows, 'total' => $total, 'pages' => $pages );
}

function axismundi_act_admin_outbox_pagination( int $page, int $pages, int $total, string $url ) : void {
	echo '<nav class="tablenav bottom" aria-label="' . esc_attr__( 'Activity history pages', 'axismundi-activities' ) . '"><div class="tablenav-pages"><span class="displaying-num">';
	/* translators: %s: Number of activities. */
	echo esc_html( sprintf( _n( '%s activity', '%s activities', $total, 'axismundi-activities' ), number_format_i18n( $total ) ) );
	echo '</span>';
	if ( $pages > 1 ) {
		$link = static function ( int $number, string $label, string $accessible ) use ( $url ) : void {
			echo '<a class="button" href="' . esc_url( add_query_arg( 'paged', $number, $url ) ) . '" aria-label="' . esc_attr( $accessible ) . '">' . esc_html( $label ) . '</a> ';
		};
		echo '<span class="pagination-links">';
		if ( $page > 1 ) {
			$link( 1, "\u{00AB}", __( 'First page', 'axismundi-activities' ) );
		}
		if ( $page > 1 && $page <= $pages ) {
			$link( $page - 1, "\u{2039}", __( 'Previous page', 'axismundi-activities' ) );
		}
		$numbers = array_unique( array_merge( array( 1 ), range( max( 1, min( $page, $pages ) - 2 ), min( $pages, min( $page, $pages ) + 2 ) ), array( $pages ) ) );
		$previous = 0;
		foreach ( $numbers as $number ) {
			if ( $number > $previous + 1 ) {
				echo '<span aria-hidden="true">... </span>';
			}
			if ( $number === $page ) {
				echo '<span class="button disabled" aria-current="page">' . esc_html( number_format_i18n( $number ) ) . '</span> ';
			} else {
				/* translators: %s: Page number. */
				$link( $number, number_format_i18n( $number ), sprintf( __( 'Page %s', 'axismundi-activities' ), number_format_i18n( $number ) ) );
			}
			$previous = $number;
		}
		if ( $page < $pages ) {
			$link( $page + 1, "\u{203A}", __( 'Next page', 'axismundi-activities' ) );
		}
		if ( $page !== $pages ) {
			$link( $pages, "\u{00BB}", __( 'Last page', 'axismundi-activities' ) );
		}
		echo '</span>';
	} elseif ( 1 === $pages && $page > 1 ) {
		echo '<a class="button" href="' . esc_url( $url ) . '">' . esc_html__( 'First page', 'axismundi-activities' ) . '</a>';
	}
	echo '</div></nav>';
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
	$history = 'collection' !== ( $input['view'] ?? ( isset( $input['collection_page'] ) ? 'collection' : 'history' ) );
	echo '<nav class="nav-tab-wrapper">';
	foreach ( array( 'history' => __( 'Activity history', 'axismundi-activities' ), 'collection' => __( 'Collection', 'axismundi-activities' ) ) as $view => $label ) {
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
			$next = add_query_arg( array( 'view' => 'collection', 'collection_page' => '1', 'actor' => $actor->get_identity_id(), 'cursor' => rawurlencode( (string) ( $query['cursor'] ?? '' ) ) ), $admin_url );
			echo '<p><a class="button" href="' . esc_url( $next ) . '">' . esc_html( $page ? __( 'Next page', 'axismundi-activities' ) : __( 'First page', 'axismundi-activities' ) ) . '</a></p>';
		}
		echo '<pre style="white-space:pre-wrap;overflow-wrap:anywhere">' . esc_html( wp_json_encode( $collection, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) ) . '</pre></div>';
		return;
	}
	$type = sanitize_text_field( is_string( $input['activity_type'] ?? null ) ? $input['activity_type'] : '' );
	$target = sanitize_text_field( is_string( $input['target'] ?? null ) ? $input['target'] : '' );
	$page = (string) $actor->get_identity_id() === ( $input['actor'] ?? '' ) && is_string( $input['paged'] ?? null ) ? max( 1, (int) $input['paged'] ) : 1;
	$from = axismundi_act_admin_outbox_date( $input['from'] ?? '' );
	$through = axismundi_act_admin_outbox_date( $input['through'] ?? '' );
	$invalid_dates = ( $input['from'] ?? '' ) !== $from || ( $input['through'] ?? '' ) !== $through
		|| ( '' !== $from && '' !== $through && $from > $through );
	$result = $invalid_dates
		? new WP_Error( 'ax_outbox_dates', __( 'Enter a valid date range.', 'axismundi-activities' ) )
		: axismundi_act_admin_outbox_rows( $actor, $type, $target, $page, $from, $through );
	echo '<p><strong>' . esc_html( axismundi_actors_acting_actor_label( $actor ) ) . '</strong></p>';
	?>
	<form method="get" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>">
		<input type="hidden" name="page" value="axismundi-outbox">
		<input type="hidden" name="view" value="history">
		<input type="hidden" name="actor" value="<?php echo esc_attr( $actor->get_identity_id() ); ?>">
		<div class="tablenav top" style="height:auto"><div class="alignleft actions">
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
		</div><br class="clear"></div>
	</form>
	<?php
	if ( is_wp_error( $result ) ) {
		echo '<p>' . esc_html( $result->get_error_message() ) . '</p></div>';
		return;
	}
	?>
	<div style="overflow-x:auto">
	<table class="widefat fixed striped" style="min-width:38em;overflow-wrap:anywhere">
		<thead><tr>
			<th scope="col"><?php esc_html_e( 'Type', 'axismundi-activities' ); ?></th>
			<th scope="col"><?php esc_html_e( 'Object', 'axismundi-activities' ); ?></th>
			<th scope="col"><?php esc_html_e( 'Activity', 'axismundi-activities' ); ?></th>
			<th scope="col"><?php esc_html_e( 'Time (UTC)', 'axismundi-activities' ); ?></th>
			<th scope="col"><?php esc_html_e( 'Activity state', 'axismundi-activities' ); ?></th>
		</tr></thead>
		<tbody>
		<?php if ( ! $result['rows'] ) : ?>
			<tr class="no-items"><td colspan="5"><?php esc_html_e( 'No matching activities.', 'axismundi-activities' ); ?></td></tr>
		<?php endif; ?>
		<?php foreach ( $result['rows'] as $row ) : ?>
			<tr>
			<?php foreach ( array( 'activity_type', 'object_uri', 'activity_uri', 'feed_sort_at', 'effective_status' ) as $field ) : ?>
				<td><?php echo esc_html( $row[ $field ] ?? '' ); ?></td>
			<?php endforeach; ?>
			</tr>
		<?php endforeach; ?>
		</tbody>
	</table>
	</div>
	<?php
	$url = add_query_arg( array( 'view' => 'history', 'actor' => $actor->get_identity_id(), 'activity_type' => rawurlencode( $type ), 'target' => rawurlencode( $target ), 'from' => $from, 'through' => $through ), $admin_url );
	axismundi_act_admin_outbox_pagination( $page, $result['pages'], $result['total'], $url );
	echo '</div>';
}
