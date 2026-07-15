<?php
/**
 * Read-only ActivityPub Bridge transport inspector.
 *
 * @package AxismundiActivityPubBridge
 */

defined( 'ABSPATH' ) || exit;

/** Register the transport inspector. */
function axismundi_activitypub_bridge_register_admin_page() : void {
	add_management_page(
		__( 'ActivityPub Bridge', 'axismundi-activitypub-bridge' ),
		__( 'ActivityPub Bridge', 'axismundi-activitypub-bridge' ),
		'manage_options',
		'axismundi-activitypub-bridge',
		'axismundi_activitypub_bridge_render_admin_page'
	);
}
add_action( 'admin_menu', 'axismundi_activitypub_bridge_register_admin_page' );

/** Every currently public local Actor, without creating identities while reading. */
function axismundi_activitypub_bridge_public_actors() : array {
	$actors = array();
	$site   = axismundi_actors_get_site_actor();
	if ( $site instanceof Axismundi_Actor && 'public' === $site->get_status() ) {
		$actors[ $site->get_uri() ] = $site;
	}
	foreach ( get_users( array( 'fields' => 'ids' ) ) as $user_id ) {
		$actor = axismundi_actors_get_for_user( (int) $user_id );
		if ( $actor instanceof Axismundi_Actor && 'public' === $actor->get_status() ) {
			$actors[ $actor->get_uri() ] = $actor;
		}
	}
	return array_values( $actors );
}

/** Render one direction of the authoritative Axismundi Activity ledger. */
function axismundi_activitypub_bridge_render_activity_rows( string $direction ) : void {
	$shown = 0;
	foreach ( axismundi_act_get_recent( 100 ) as $activity ) {
		if ( ! $activity instanceof Axismundi_Activity || $direction !== $activity->get_direction() ) {
			continue;
		}
		++$shown;
		?>
		<tr>
			<td><?php echo esc_html( $activity->get_type() ); ?><br><code><?php echo esc_html( $activity->get_uri() ); ?></code></td>
			<td><code><?php echo esc_html( $activity->get_actor_uri() ); ?></code></td>
			<td><code><?php echo esc_html( $activity->get_object_uri() ?? '—' ); ?></code></td>
			<td><?php echo esc_html( $activity->get_effective_status() ); ?></td>
		</tr>
		<?php
	}
	if ( 0 === $shown ) {
		?><tr><td colspan="4"><?php esc_html_e( 'No matching activities.', 'axismundi-activitypub-bridge' ); ?></td></tr><?php
	}
}

/** Render read-only endpoint, ledger, and transport queue state. */
function axismundi_activitypub_bridge_render_admin_page() : void {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'You cannot inspect federation transport.', 'axismundi-activitypub-bridge' ), '', array( 'response' => 403 ) );
	}
	$queue = get_posts(
		array(
			'post_type'      => 'ap_outbox',
			'post_status'    => array( 'pending', 'publish' ),
			'posts_per_page' => 100,
			'meta_key'       => '_activitypub_external_delivery', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
			'meta_value'     => 1, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
		)
	);
	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'ActivityPub Bridge', 'axismundi-activitypub-bridge' ); ?></h1>
		<p><?php esc_html_e( 'Axismundi owns Actor representations and Activity state. The official ActivityPub plugin verifies Inbox signatures and operates this outbound transport queue.', 'axismundi-activitypub-bridge' ); ?></p>

		<h2><?php esc_html_e( 'Endpoints', 'axismundi-activitypub-bridge' ); ?></h2>
		<p><strong><?php esc_html_e( 'Shared Inbox', 'axismundi-activitypub-bridge' ); ?>:</strong> <code><?php echo esc_html( axismundi_activitypub_bridge_shared_inbox_url() ); ?></code></p>
		<table class="widefat striped"><thead><tr><th><?php esc_html_e( 'Actor', 'axismundi-activitypub-bridge' ); ?></th><th><?php esc_html_e( 'Inbox', 'axismundi-activitypub-bridge' ); ?></th><th><?php esc_html_e( 'Outbox', 'axismundi-activitypub-bridge' ); ?></th><th><?php esc_html_e( 'Signing key', 'axismundi-activitypub-bridge' ); ?></th></tr></thead><tbody>
		<?php foreach ( axismundi_activitypub_bridge_public_actors() as $actor ) : $sender = axismundi_activitypub_bridge_sender( $actor ); ?>
			<tr>
				<td><a href="<?php echo esc_url( $actor->get_profile_url() ); ?>"><code><?php echo esc_html( $actor->get_uri() ); ?></code></a></td>
				<td><code><?php echo esc_html( axismundi_activitypub_bridge_inbox_url( $actor ) ); ?></code></td>
				<td><a href="<?php echo esc_url( axismundi_activitypub_bridge_outbox_url( $actor ) ); ?>"><code><?php echo esc_html( axismundi_activitypub_bridge_outbox_url( $actor ) ); ?></code></a></td>
				<td><code><?php echo esc_html( $sender['key_id'] ); ?></code></td>
			</tr>
		<?php endforeach; ?>
		</tbody></table>

		<h2><?php esc_html_e( 'Inbox ledger', 'axismundi-activitypub-bridge' ); ?></h2>
		<table class="widefat striped"><thead><tr><th><?php esc_html_e( 'Activity', 'axismundi-activitypub-bridge' ); ?></th><th><?php esc_html_e( 'Actor', 'axismundi-activitypub-bridge' ); ?></th><th><?php esc_html_e( 'Object', 'axismundi-activitypub-bridge' ); ?></th><th><?php esc_html_e( 'Status', 'axismundi-activitypub-bridge' ); ?></th></tr></thead><tbody><?php axismundi_activitypub_bridge_render_activity_rows( 'inbound' ); ?></tbody></table>

		<h2><?php esc_html_e( 'Outbox ledger', 'axismundi-activitypub-bridge' ); ?></h2>
		<table class="widefat striped"><thead><tr><th><?php esc_html_e( 'Activity', 'axismundi-activitypub-bridge' ); ?></th><th><?php esc_html_e( 'Actor', 'axismundi-activitypub-bridge' ); ?></th><th><?php esc_html_e( 'Object', 'axismundi-activitypub-bridge' ); ?></th><th><?php esc_html_e( 'Status', 'axismundi-activitypub-bridge' ); ?></th></tr></thead><tbody><?php axismundi_activitypub_bridge_render_activity_rows( 'outbound' ); ?></tbody></table>

		<h2><?php esc_html_e( 'Transport queue', 'axismundi-activitypub-bridge' ); ?></h2>
		<table class="widefat striped"><thead><tr><th><?php esc_html_e( 'Activity', 'axismundi-activitypub-bridge' ); ?></th><th><?php esc_html_e( 'Sender', 'axismundi-activitypub-bridge' ); ?></th><th><?php esc_html_e( 'Status', 'axismundi-activitypub-bridge' ); ?></th><th><?php esc_html_e( 'Attempt', 'axismundi-activitypub-bridge' ); ?></th><th><?php esc_html_e( 'Last error', 'axismundi-activitypub-bridge' ); ?></th></tr></thead><tbody>
		<?php if ( empty( $queue ) ) : ?><tr><td colspan="5"><?php esc_html_e( 'No transport jobs.', 'axismundi-activitypub-bridge' ); ?></td></tr><?php endif; ?>
		<?php foreach ( $queue as $job ) : ?>
			<tr>
				<td><code><?php echo esc_html( (string) get_post_meta( $job->ID, '_activitypub_external_activity_uri', true ) ); ?></code></td>
				<td><code><?php echo esc_html( (string) get_post_meta( $job->ID, '_activitypub_external_actor_uri', true ) ); ?></code></td>
				<td><?php echo esc_html( (string) get_post_meta( $job->ID, '_activitypub_external_status', true ) ); ?></td>
				<td><?php echo esc_html( (string) get_post_meta( $job->ID, '_activitypub_external_attempt', true ) ); ?></td>
				<td><?php echo esc_html( (string) get_post_meta( $job->ID, '_activitypub_external_last_error', true ) ); ?></td>
			</tr>
		<?php endforeach; ?>
		</tbody></table>
	</div>
	<?php
}
