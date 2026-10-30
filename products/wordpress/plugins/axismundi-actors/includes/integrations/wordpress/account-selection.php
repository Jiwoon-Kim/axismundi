<?php
/** Browser Actor selection, independent of JMAP transport and capabilities. */
namespace Axismundi\Actors\Selection;

defined( 'ABSPATH' ) || exit;

add_filter( 'axismundi_actors_account_selection_adapter_available', '__return_true' );

/** Opt in only for review until write consumers have migrated to the selection kernel. */
function account_selection_ui_enabled() : bool {
	return function_exists( 'axismundi_actors_account_selection_enabled' )
		&& axismundi_actors_account_selection_enabled();
}

/**
 * One Accounts submenu URL regardless of WordPress User-list privileges.
 * The historical page identifier is retained; only its navigation parent changed.
 */
function account_selection_page_url() : string {
	return \axismundi_actors_account_admin_url( 'axismundi-jmap-accounts' );
}

/** Operating principal access belongs to Actors, not a protocol Session. */
function account_selection_view() {
	$user = get_current_user_id();
	if ( $user <= 0 || ! current_user_can( 'read' ) ) {
		return new \WP_Error( 'ax_jmap_selection_user', __( 'You must sign in to select an Account.', 'axismundi-jmap' ) );
	}
	$session = axismundi_actors_browser_session( $user );
	if ( is_wp_error( $session ) ) {
		return $session;
	}
	$accounts = new \stdClass();
	foreach ( axismundi_actors_workspace_options( $user ) as $actor ) {
		$accounts->{'a' . $actor->get_identity_id()} = (object) array( 'name' => $actor->get_display_name() );
	}
	$current = axismundi_actors_resolve_account_selection( $user, null, '', false, true );
	return array(
		'accounts' => $accounts,
		'primaryAccounts' => new \stdClass(),
		'current' => is_wp_error( $current ) ? '' : 'a' . $current['actor']->get_identity_id(),
		'loginDefault' => ( $default = axismundi_actors_default_account( $user, 'login' ) ) instanceof \Axismundi_Actor ? 'a' . $default->get_identity_id() : '',
		'error' => is_wp_error( $current ) && 'ax_selection_missing' !== $current->get_error_code() ? $current : null,
	);
}

function resolve_account_actor( string $account_id, int $user ) : ?\Axismundi_Actor {
	if ( ! preg_match( '/^a([1-9][0-9]*)$/D', $account_id, $match ) || (string) (int) $match[1] !== $match[1] ) {
		return null;
	}
	return axismundi_actors_resolve_workspace( (int) $match[1], $user );
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
	if ( 'clear_login_default' === $operation ) {
		return axismundi_actors_set_default_account( $user, 'login', 0 );
	}
	if ( ! in_array( $operation, array( 'select', 'login_default' ), true ) ) {
		return new \WP_Error( 'ax_jmap_selection_operation', __( 'Invalid Account selection operation.', 'axismundi-jmap' ) );
	}
	$actor = resolve_account_actor( $account_id, $user );
	if ( ! $actor instanceof \Axismundi_Actor ) {
		return new \WP_Error( 'ax_jmap_selection_account', __( 'The selected Account is unavailable or access was revoked.', 'axismundi-jmap' ) );
	}
	return 'login_default' === $operation
		? axismundi_actors_set_default_account( $user, 'login', $actor->get_identity_id() )
		: axismundi_actors_set_account_selection( $actor->get_identity_id() );
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
		</p>
	</form>
	<h2><?php esc_html_e( 'Login Account', 'axismundi-jmap' ); ?></h2>
	<form action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="post">
		<input type="hidden" name="action" value="axismundi_jmap_select_account">
		<?php wp_nonce_field( 'ax_jmap_account_selection' ); ?>
		<?php foreach ( $view['accounts'] as $id => $account ) : ?>
			<p><label><input type="radio" name="account_id" value="<?php echo esc_attr( $id ); ?>" <?php checked( $view['loginDefault'], $id ); ?>> <?php echo account_selection_avatar( $id ) . esc_html( $account->name ); ?></label></p>
		<?php endforeach; ?>
		<p><button class="button button-primary" name="operation" value="login_default"><?php esc_html_e( 'Save login Account', 'axismundi-jmap' ); ?></button>
		<button class="button" name="operation" value="clear_login_default"><?php esc_html_e( 'Require selection at login', 'axismundi-jmap' ); ?></button></p>
	</form></div>
	<?php
}

/** Actor presentation only; never add avatar fields to the JMAP Account object. */
function account_selection_avatar( string $account_id ) : string {
	$actor = resolve_account_actor( $account_id, get_current_user_id() );
	$id = $actor instanceof \Axismundi_Actor ? $actor->get_avatar_attachment_id() : 0;
	$image = $id > 0 ? wp_get_attachment_image( $id, array( 28, 28 ), false, array( 'class' => 'ax-account-avatar', 'alt' => '' ) ) : '';
	return $image ?: '<span class="ax-account-avatar dashicons dashicons-admin-users" aria-hidden="true"></span>';
}

/** Never expose old and new switchers together when the review gate is enabled. */
function register_account_selection_ui() : void {
	if ( ! account_selection_ui_enabled() ) {
		return;
	}
	remove_action( 'admin_bar_menu', 'axismundi_actors_acting_actor_admin_bar', 80 );
	remove_action( 'admin_post_axismundi_actors_set_acting_actor', 'axismundi_actors_handle_set_acting_actor' );
	add_action( 'admin_menu', static function () : void {
		$hook = add_submenu_page( \axismundi_actors_accounts_menu_slug(), __( 'Accounts', 'axismundi-jmap' ), __( 'Switch Account', 'axismundi-actors' ), 'read', 'axismundi-jmap-accounts', __NAMESPACE__ . '\\render_account_selection_page' );
		if ( ! is_string( $hook ) ) {
			return;
		}
		/*
		 * State the screen title for every entry URL, not just the canonical one.
		 *
		 * `get_admin_page_title()` finds the registered submenu entry only when `$pagenow`
		 * equals the parent WordPress stored it under. A stale `profile.php?page=` link --
		 * a bookmark, or one shared before this screen moved -- still renders the screen
		 * through the same page hook with `$pagenow` disagreeing, so core matches nothing
		 * and leaves `$title` null for `admin-header.php` to strip_tags(). This hook runs
		 * before that header, so the title is right whichever URL was used.
		 */
		add_action( 'load-' . $hook, static function () : void {
			$GLOBALS['title'] = __( 'Accounts', 'axismundi-jmap' );
		} );
	} );
	add_action( 'admin_bar_menu', static function ( \WP_Admin_Bar $bar ) : void {
		if ( get_current_user_id() > 0 && current_user_can( 'read' ) ) {
			$view = account_selection_view();
			if ( is_wp_error( $view ) ) { return; }
			$current = $view['current'];
			$name = isset( $view['accounts']->{$current} ) ? $view['accounts']->{$current}->name : __( 'Choose Account', 'axismundi-jmap' );
			$bar->add_node( array( 'id' => 'ax-jmap-accounts', 'parent' => 'top-secondary', 'title' => account_selection_avatar( $current ) . '<span class="ax-account-name">' . esc_html( $name ) . '</span>', 'href' => account_selection_page_url() ) );
			foreach ( $view['accounts'] as $id => $account ) {
				$form = '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="axismundi_jmap_select_account"><input type="hidden" name="operation" value="select"><input type="hidden" name="account_id" value="' . esc_attr( $id ) . '">'
					. wp_nonce_field( 'ax_jmap_account_selection', '_wpnonce', false, false )
					. '<button type="submit" class="ab-item ax-account-switch"' . ( $id === $current ? ' aria-current="true"' : '' ) . '>' . account_selection_avatar( $id ) . '<span class="ax-account-name">' . esc_html( $account->name ) . '</span>'
					. ( $id === $current ? '<span class="dashicons dashicons-yes" aria-hidden="true"></span>' : '' ) . '</button></form>';
				$bar->add_node( array( 'id' => 'ax-jmap-account-' . $id, 'parent' => 'ax-jmap-accounts', 'title' => $form, 'href' => false ) );
			}
			$bar->add_node( array( 'id' => 'ax-jmap-account-settings', 'parent' => 'ax-jmap-accounts', 'title' => esc_html__( 'Account settings', 'axismundi-jmap' ), 'href' => account_selection_page_url() ) );
		}
	}, 80 );
}

/** Keep fixed-size avatars and long Account labels usable in the native toolbar. */
function account_selection_styles() : void {
	if ( ! account_selection_ui_enabled() || ! is_user_logged_in() ) { return; }
	$plugin = dirname( __DIR__, 3 ) . '/axismundi-actors.php';
	wp_enqueue_style( 'axismundi-account-selection', plugins_url( 'assets/account-selection.css', $plugin ), array( 'dashicons' ), (string) filemtime( dirname( __DIR__, 3 ) . '/assets/account-selection.css' ) );
}
add_action( 'admin_enqueue_scripts', __NAMESPACE__ . '\\account_selection_styles' );
add_action( 'wp_enqueue_scripts', __NAMESPACE__ . '\\account_selection_styles' );
add_action( 'plugins_loaded', __NAMESPACE__ . '\\register_account_selection_ui', 30 );
add_action( 'admin_post_axismundi_jmap_select_account', __NAMESPACE__ . '\\handle_account_selection_post' );
