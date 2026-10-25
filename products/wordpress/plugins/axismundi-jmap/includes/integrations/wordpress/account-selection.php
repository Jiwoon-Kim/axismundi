<?php
/** Browser selection adapter over JMAP Account projection, not a JMAP method. @package AxismundiJmap */
namespace Axismundi\Jmap;

defined( 'ABSPATH' ) || exit;

add_filter( 'axismundi_actors_account_selection_adapter_available', '__return_true' );

/** Opt in only for review until write consumers have migrated to the selection kernel. */
function account_selection_ui_enabled() : bool {
	return function_exists( 'axismundi_actors_account_selection_enabled' )
		&& axismundi_actors_account_selection_enabled();
}

function account_selection_page_url() : string {
	return admin_url( 'profile.php?page=axismundi-jmap-accounts' );
}

/** Use one Account projection; do not invent a parallel User-to-Actor list. */
function account_selection_view() {
	$user = get_current_user_id();
	if ( $user <= 0 || ! current_user_can( 'read' ) ) {
		return new \WP_Error( 'ax_jmap_selection_user', __( 'You must sign in to select an Account.', 'axismundi-jmap' ) );
	}
	$session = axismundi_actors_browser_session( $user );
	if ( is_wp_error( $session ) ) {
		return $session;
	}
	try {
		$projection = ( new SessionProjector( core_registry() ) )->accounts( $user );
	} catch ( \Throwable $error ) {
		return new \WP_Error( 'ax_jmap_selection_unavailable', __( 'Account selection is unavailable.', 'axismundi-jmap' ) );
	}
	$current = axismundi_actors_resolve_account_selection( $user, null, '', false, true );
	return array(
		'accounts' => $projection->accounts,
		'primaryAccounts' => $projection->primaryAccounts,
		'current' => is_wp_error( $current ) ? '' : 'a' . $current['actor']->get_identity_id(),
		'error' => is_wp_error( $current ) && 'ax_selection_missing' !== $current->get_error_code() ? $current : null,
	);
}

/** Current authenticated browser only. Default preferences are not modified here. */
function apply_account_selection( string $operation, string $account_id, string $nonce ) {
	if ( ! account_selection_ui_enabled() ) {
		return new \WP_Error( 'ax_jmap_selection_disabled', __( 'Account switching is not enabled.', 'axismundi-jmap' ) );
	}
	$user = get_current_user_id();
	if ( $user <= 0 || ! current_user_can( 'read' ) ) {
		return new \WP_Error( 'ax_jmap_selection_user', __( 'You must sign in to select an Account.', 'axismundi-jmap' ) );
	}
	$session = axismundi_actors_browser_session( $user );
	if ( is_wp_error( $session ) ) {
		return $session;
	}
	if ( ! wp_verify_nonce( $nonce, 'ax_jmap_account_selection' ) ) {
		return new \WP_Error( 'ax_jmap_selection_nonce', __( 'The Account selection request has expired. Reload the page.', 'axismundi-jmap' ) );
	}
	if ( 'clear' === $operation ) {
		return axismundi_actors_clear_account_selection() ? true
			: new \WP_Error( 'ax_selection_headers', __( 'The Account selection cookie could not be cleared.', 'axismundi-jmap' ) );
	}
	if ( 'select' !== $operation ) {
		return new \WP_Error( 'ax_jmap_selection_operation', __( 'Invalid Account selection operation.', 'axismundi-jmap' ) );
	}
	$actor = resolve_account_actor( $account_id, $user );
	if ( ! $actor instanceof \Axismundi_Actor ) {
		return new \WP_Error( 'ax_jmap_selection_account', __( 'The selected Account is unavailable or access was revoked.', 'axismundi-jmap' ) );
	}
	return axismundi_actors_set_account_selection( $actor->get_identity_id() );
}

/** A POST-only WordPress adapter; never accepts a principal or redirect from the request. */
function handle_account_selection_post() : void {
	if ( 'POST' !== strtoupper( (string) ( $_SERVER['REQUEST_METHOD'] ?? '' ) ) ) {
		wp_die( esc_html__( 'A POST request is required.', 'axismundi-jmap' ), '', array( 'response' => 405 ) );
	}
	$values = array();
	foreach ( array( 'operation', 'account_id', '_wpnonce' ) as $key ) {
		if ( isset( $_POST[ $key ] ) && ! is_string( $_POST[ $key ] ) ) {
			wp_die( esc_html__( 'Invalid Account selection request.', 'axismundi-jmap' ), '', array( 'response' => 400 ) );
		}
		$values[ $key ] = isset( $_POST[ $key ] ) ? wp_unslash( $_POST[ $key ] ) : '';
	}
	$result = apply_account_selection( $values['operation'], $values['account_id'], $values['_wpnonce'] );
	if ( is_wp_error( $result ) ) {
		wp_die( esc_html( $result->get_error_message() ), '', array( 'response' => 'ax_selection_headers' === $result->get_error_code() ? 500 : 403 ) );
	}
	nocache_headers();
	wp_safe_redirect( account_selection_page_url(), 303 );
	exit;
}

/** Native WordPress controls; account data comes from the JMAP projector. */
function render_account_selection_page() : void {
	if ( ! account_selection_ui_enabled() ) {
		wp_die( esc_html__( 'Account switching is not enabled.', 'axismundi-jmap' ), '', array( 'response' => 403 ) );
	}
	$view = account_selection_view();
	echo '<div class="wrap"><h1>' . esc_html__( 'Accounts', 'axismundi-jmap' ) . '</h1>';
	if ( is_wp_error( $view ) ) {
		echo '<div class="notice notice-error"><p>' . esc_html( $view->get_error_message() ) . '</p></div></div>';
		return;
	}
	if ( is_wp_error( $view['error'] ) ) {
		echo '<div class="notice notice-warning"><p>' . esc_html( $view['error']->get_error_message() ) . '</p></div>';
	}
	if ( ! get_object_vars( $view['accounts'] ) ) {
		echo '<p>' . esc_html__( 'No accessible Accounts.', 'axismundi-jmap' ) . '</p></div>';
		return;
	}
	?>
	<form action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="post">
		<input type="hidden" name="action" value="axismundi_jmap_select_account">
		<?php wp_nonce_field( 'ax_jmap_account_selection' ); ?>
		<table class="form-table" role="presentation"><tr>
			<th scope="row"><label for="ax-jmap-account"><?php esc_html_e( 'Current Account', 'axismundi-jmap' ); ?></label></th>
			<td><select id="ax-jmap-account" name="account_id">
				<option value=""><?php esc_html_e( 'No Account selected', 'axismundi-jmap' ); ?></option>
				<?php foreach ( $view['accounts'] as $id => $account ) : ?>
					<option value="<?php echo esc_attr( $id ); ?>" <?php selected( $view['current'], $id ); ?>><?php echo esc_html( '' !== $account->name ? $account->name : $id ); ?></option>
				<?php endforeach; ?>
			</select></td>
		</tr></table>
		<p class="submit">
			<button class="button button-primary" type="submit" name="operation" value="select"><?php esc_html_e( 'Select Account', 'axismundi-jmap' ); ?></button>
			<button class="button" type="submit" name="operation" value="clear"><?php esc_html_e( 'Clear selection', 'axismundi-jmap' ); ?></button>
		</p>
	</form></div>
	<?php
}

/** Never expose old and new switchers together when the review gate is enabled. */
function register_account_selection_ui() : void {
	if ( ! account_selection_ui_enabled() ) {
		return;
	}
	remove_action( 'admin_bar_menu', 'axismundi_actors_acting_actor_admin_bar', 80 );
	remove_action( 'admin_post_axismundi_actors_set_acting_actor', 'axismundi_actors_handle_set_acting_actor' );
	add_action( 'admin_menu', static function () : void {
		add_submenu_page( 'profile.php', __( 'Accounts', 'axismundi-jmap' ), __( 'Accounts', 'axismundi-jmap' ), 'read', 'axismundi-jmap-accounts', __NAMESPACE__ . '\\render_account_selection_page' );
	} );
	add_action( 'admin_bar_menu', static function ( \WP_Admin_Bar $bar ) : void {
		if ( get_current_user_id() > 0 && current_user_can( 'read' ) ) {
			$bar->add_node( array( 'id' => 'ax-jmap-accounts', 'parent' => 'my-account', 'title' => esc_html__( 'Accounts', 'axismundi-jmap' ), 'href' => account_selection_page_url() ) );
		}
	}, 80 );
}
add_action( 'plugins_loaded', __NAMESPACE__ . '\\register_account_selection_ui', 30 );
add_action( 'admin_post_axismundi_jmap_select_account', __NAMESPACE__ . '\\handle_account_selection_post' );
