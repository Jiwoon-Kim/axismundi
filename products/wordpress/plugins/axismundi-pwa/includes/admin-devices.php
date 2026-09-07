<?php
/**
 * Turning push on, where somebody would look for it.
 *
 * Inside their own notification settings rather than in a site-wide PWA screen, because a push
 * subscription is a relationship between one signed-in person and one browser -- not a property of
 * the site and not of an Actor. Somebody with three Actors and two laptops turns it on twice, once
 * per browser, and it applies to everything they read.
 *
 * The permission prompt happens on the button and nowhere else. A prompt fired on page load is the
 * behaviour browsers are actively penalising, and the one that teaches people to click Block before
 * reading the sentence.
 *
 * This plugin renders the section; Notifications owns the page and says where it goes. Neither
 * reaches into the other: the page fires an action, and this answers it.
 *
 * @package AxismundiPwa
 */

defined( 'ABSPATH' ) || exit;

/**
 * The device section on somebody's notification settings.
 *
 * @return void
 */
function axismundi_pwa_render_device_settings() : void {
	$capability = axismundi_pwa_capability();
	$user_id    = get_current_user_id();
	if ( $user_id <= 0 ) {
		return;
	}
	echo '<h3>' . esc_html__( 'This browser', 'axismundi-pwa' ) . '</h3>';
	if ( ! $capability['subscribe'] ) {
		/*
		 * Said, rather than a disabled button. Which of the reasons it is matters to whoever has to
		 * fix it: a missing service worker plugin is an install, and missing keys are a deployment
		 * setting.
		 */
		$reasons = array(
			'provider_missing' => __( 'The service worker plugin this needs is not active.', 'axismundi-pwa' ),
			'not_installed'    => __( 'Push storage is not installed on this site.', 'axismundi-pwa' ),
			'no_keys'          => __( 'This site has no push keys configured, so browsers cannot be registered.', 'axismundi-pwa' ),
		);
		echo '<p>' . esc_html( $reasons[ $capability['reason'] ] ?? __( 'Push is not available on this site.', 'axismundi-pwa' ) ) . '</p>';
		return;
	}

	wp_enqueue_script(
		'axismundi-pwa-push',
		plugins_url( 'assets/push.js', dirname( __DIR__ ) . '/axismundi-pwa.php' ),
		array( 'wp-api-fetch' ),
		AXISMUNDI_PWA_VERSION,
		true
	);
	wp_localize_script(
		'axismundi-pwa-push',
		'axismundiPwaPush',
		array(
			// The worker with scope over the whole site, which is the one carrying our handlers. An
			// admin page registering the admin worker would subscribe against a worker that has none.
			'serviceWorkerUrl'     => function_exists( 'wp_get_service_worker_url' ) ? wp_get_service_worker_url() : home_url( '/wp.serviceworker' ),
			'applicationServerKey' => axismundi_pwa_application_server_key(),
			'restUrl'              => rest_url( 'axismundi/v1/pwa/subscriptions' ),
			'nonce'                => wp_create_nonce( 'wp_rest' ),
			'canDeliver'           => (bool) $capability['deliver'],
			'strings'              => array(
				'unsupported' => __( 'This browser cannot receive push notifications.', 'axismundi-pwa' ),
				'denied'      => __( 'You have blocked notifications for this site. Your browser settings are the only place that can be changed.', 'axismundi-pwa' ),
				'off'         => __( 'This browser is not registered.', 'axismundi-pwa' ),
				'on'          => __( 'This browser is registered and can be woken.', 'axismundi-pwa' ),
				'enable'      => __( 'Turn on push for this browser', 'axismundi-pwa' ),
				'disable'     => __( 'Stop using this browser', 'axismundi-pwa' ),
				'working'     => __( 'Working…', 'axismundi-pwa' ),
				'failed'      => __( 'That did not work. Nothing has been registered.', 'axismundi-pwa' ),
			),
		)
	);
	?>
	<div id="axismundi-pwa-push" data-state="unknown">
		<p class="axismundi-pwa-push-status"><?php esc_html_e( 'Checking this browser…', 'axismundi-pwa' ); ?></p>
		<p><button type="button" class="button axismundi-pwa-push-toggle" hidden></button></p>
	</div>
	<?php
	if ( ! $capability['deliver'] ) {
		// Registering is still worth doing -- the device is remembered for when sending works -- but
		// saying so beats letting somebody register and then wonder why nothing ever arrives.
		echo '<p class="description">' . esc_html__( 'This site cannot send push notifications yet. A browser registered now will be used once it can.', 'axismundi-pwa' ) . '</p>';
	}
	axismundi_pwa_render_device_list( $user_id );
}
add_action( 'axismundi_notification_device_settings', 'axismundi_pwa_render_device_settings' );

/**
 * The browsers somebody has registered.
 *
 * Server-rendered, because a person recognising their own devices is the point and only the site
 * knows what it has stored. Each can be given up from here, which matters for the browser somebody
 * no longer has in front of them.
 *
 * @param int $user_id Owner.
 * @return void
 */
function axismundi_pwa_render_device_list( int $user_id ) : void {
	$devices = axismundi_pwa_subscriptions_for( $user_id );
	if ( array() === $devices ) {
		return;
	}
	?>
	<table class="widefat striped" style="max-width:48em;margin-top:1em">
		<thead>
			<tr>
				<th scope="col"><?php esc_html_e( 'Browser', 'axismundi-pwa' ); ?></th>
				<th scope="col"><?php esc_html_e( 'Registered', 'axismundi-pwa' ); ?></th>
				<th scope="col"><?php esc_html_e( 'Stop', 'axismundi-pwa' ); ?></th>
			</tr>
		</thead>
		<tbody>
		<?php foreach ( $devices as $device ) : ?>
			<tr>
				<td><?php echo esc_html( '' !== (string) $device['user_agent'] ? (string) $device['user_agent'] : __( 'Unnamed browser', 'axismundi-pwa' ) ); ?></td>
				<td><?php echo esc_html( mysql2date( get_option( 'date_format' ), (string) $device['created_at'] ) ); ?></td>
				<td>
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
						<?php wp_nonce_field( 'ax_pwa_forget_' . (int) $device['id'] ); ?>
						<input type="hidden" name="action" value="ax_pwa_forget_device">
						<input type="hidden" name="device" value="<?php echo esc_attr( (string) $device['id'] ); ?>">
						<button type="submit" class="button button-small"><?php esc_html_e( 'Forget', 'axismundi-pwa' ); ?></button>
					</form>
				</td>
			</tr>
		<?php endforeach; ?>
		</tbody>
	</table>
	<?php
}

/**
 * Give up one device from the list.
 *
 * By row id rather than by endpoint, so the page never has to carry the endpoint -- which is the
 * credential for waking that browser and has no business in a form field or a browser history.
 *
 * @return void
 */
function axismundi_pwa_handle_forget_device() : void {
	$id = isset( $_POST['device'] ) ? (int) $_POST['device'] : 0;
	check_admin_referer( 'ax_pwa_forget_' . $id );
	$device = axismundi_pwa_subscription( $id );
	// Scoped to the owner: a row id is guessable in a way an endpoint is not, so the check is the
	// relation and not the number.
	if ( is_array( $device ) && (int) $device['local_user_id'] === get_current_user_id() ) {
		axismundi_pwa_revoke( (string) $device['endpoint'], get_current_user_id() );
	}
	wp_safe_redirect( wp_get_referer() ? wp_get_referer() : admin_url() );
	exit;
}
add_action( 'admin_post_ax_pwa_forget_device', 'axismundi_pwa_handle_forget_device' );
