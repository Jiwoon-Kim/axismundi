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

/** Run the explicit read-only dry scan for this request only. */
function axismundi_activitypub_bridge_requested_legacy_report() : ?array {
	if ( ! isset( $_POST['ax_bridge_legacy_scan'] ) ) {
		return null;
	}
	check_admin_referer( 'ax_bridge_legacy_scan' );
	return axismundi_activitypub_bridge_scan_legacy_data();
}

/** Run the explicit import after an exact typed confirmation. */
function axismundi_activitypub_bridge_requested_legacy_import() : ?array {
	if ( ! isset( $_POST['ax_bridge_legacy_import'] ) ) {
		return null;
	}
	check_admin_referer( 'ax_bridge_legacy_import' );
	$confirmation = isset( $_POST['ax_bridge_legacy_confirmation'] ) ? sanitize_text_field( wp_unslash( $_POST['ax_bridge_legacy_confirmation'] ) ) : '';
	if ( 'IMPORT' !== $confirmation ) {
		return array(
			'generated_at'     => current_time( 'mysql', true ),
			'official_version' => defined( 'ACTIVITYPUB_PLUGIN_VERSION' ) ? ACTIVITYPUB_PLUGIN_VERSION : '',
			'summary'          => array( 'preflight' => array( 'failed' => 1 ) ),
			'rows'             => array( array( 'source' => 'preflight', 'source_id' => 'confirmation', 'identity' => '', 'status' => 'failed', 'detail' => __( 'Type IMPORT exactly to run the migration.', 'axismundi-activitypub-bridge' ) ) ),
			'writes'           => 0,
			'deletes'          => 0,
			'network_requests' => 0,
			'complete'         => false,
		);
	}
	return axismundi_activitypub_bridge_import_legacy_data();
}

/** Compact classification counts for one report axis. */
function axismundi_activitypub_bridge_report_counts( array $counts ) : string {
	ksort( $counts );
	$parts = array();
	foreach ( $counts as $status => $count ) {
		$parts[] = sanitize_key( (string) $status ) . ': ' . (int) $count;
	}
	return empty( $parts ) ? '—' : implode( ', ', $parts );
}

/** Render the migration dry-run controls and optional ephemeral report. */
function axismundi_activitypub_bridge_render_legacy_scan( ?array $report, ?array $import_result = null ) : void {
	?>
	<h2><?php esc_html_e( 'Legacy ActivityPub migration', 'axismundi-activitypub-bridge' ); ?></h2>
	<p><?php esc_html_e( 'Scan official ActivityPub storage without changing any row, then explicitly import supported Actor, Object, and Inbox records through Axismundi repositories. Import never deletes official rows or performs network requests. Purge remains disabled.', 'axismundi-activitypub-bridge' ); ?></p>
	<form method="post">
		<?php wp_nonce_field( 'ax_bridge_legacy_scan' ); ?>
		<?php submit_button( __( 'Run migration dry scan', 'axismundi-activitypub-bridge' ), 'secondary', 'ax_bridge_legacy_scan', false ); ?>
	</form>
	<form method="post" style="margin-top: 1em;">
		<?php wp_nonce_field( 'ax_bridge_legacy_import' ); ?>
		<label for="ax-bridge-legacy-confirmation"><?php esc_html_e( 'Type IMPORT to import and verify supported rows:', 'axismundi-activitypub-bridge' ); ?></label>
		<input id="ax-bridge-legacy-confirmation" name="ax_bridge_legacy_confirmation" type="text" autocomplete="off" value="">
		<?php submit_button( __( 'Import and verify', 'axismundi-activitypub-bridge' ), 'primary', 'ax_bridge_legacy_import', false ); ?>
	</form>
	<?php if ( is_array( $import_result ) ) : ?>
		<h3><?php esc_html_e( 'Import result', 'axismundi-activitypub-bridge' ); ?></h3>
		<p>
			<strong><?php echo esc_html( ! empty( $import_result['complete'] ) ? __( 'Complete', 'axismundi-activitypub-bridge' ) : __( 'Incomplete', 'axismundi-activitypub-bridge' ) ); ?></strong>
			<?php
			echo esc_html(
				sprintf(
					/* translators: 1: writes, 2: deletes, 3: network requests. */
					__( 'Repository writes: %1$d. Source deletes: %2$d. Network requests: %3$d.', 'axismundi-activitypub-bridge' ),
					(int) $import_result['writes'],
					(int) $import_result['deletes'],
					(int) $import_result['network_requests']
				)
			);
			?>
		</p>
		<table class="widefat striped">
			<thead><tr><th><?php esc_html_e( 'Source', 'axismundi-activitypub-bridge' ); ?></th><th><?php esc_html_e( 'Result', 'axismundi-activitypub-bridge' ); ?></th></tr></thead>
			<tbody>
			<?php foreach ( $import_result['summary'] as $source => $counts ) : ?>
				<tr><td><code><?php echo esc_html( (string) $source ); ?></code></td><td><?php echo esc_html( axismundi_activitypub_bridge_report_counts( (array) $counts ) ); ?></td></tr>
			<?php endforeach; ?>
			</tbody>
		</table>
		<table class="widefat striped" style="margin-top: 1em;">
			<thead><tr><th><?php esc_html_e( 'Source row', 'axismundi-activitypub-bridge' ); ?></th><th><?php esc_html_e( 'Canonical identity', 'axismundi-activitypub-bridge' ); ?></th><th><?php esc_html_e( 'Status', 'axismundi-activitypub-bridge' ); ?></th><th><?php esc_html_e( 'Verification', 'axismundi-activitypub-bridge' ); ?></th></tr></thead>
			<tbody>
			<?php foreach ( $import_result['rows'] as $row ) : ?>
				<tr>
					<td><code><?php echo esc_html( $row['source'] . ':' . $row['source_id'] ); ?></code></td>
					<td><code><?php echo esc_html( '' !== $row['identity'] ? $row['identity'] : '—' ); ?></code></td>
					<td><code><?php echo esc_html( $row['status'] ); ?></code></td>
					<td><?php echo esc_html( $row['detail'] ); ?></td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
	<?php endif; ?>
	<?php if ( ! is_array( $report ) ) : return; endif; ?>
	<p><strong><?php esc_html_e( 'Result:', 'axismundi-activitypub-bridge' ); ?></strong>
		<?php
		echo esc_html(
			sprintf(
				/* translators: 1: official plugin version, 2: UTC scan time. */
				__( 'ActivityPub %1$s, scanned at %2$s UTC. Writes: 0. Network requests: 0.', 'axismundi-activitypub-bridge' ),
				(string) $report['official_version'],
				(string) $report['generated_at']
			)
		);
		?>
	</p>
	<?php if ( ! empty( $report['truncated'] ) ) : ?>
		<div class="notice notice-warning inline"><p><?php esc_html_e( 'The bounded scan limit was reached. Increase the scan limit only after reviewing site size.', 'axismundi-activitypub-bridge' ); ?></p></div>
	<?php endif; ?>
	<table class="widefat striped">
		<thead><tr><th><?php esc_html_e( 'Source', 'axismundi-activitypub-bridge' ); ?></th><th><?php esc_html_e( 'Scanned / available', 'axismundi-activitypub-bridge' ); ?></th><th><?php esc_html_e( 'Import decision', 'axismundi-activitypub-bridge' ); ?></th><th><?php esc_html_e( 'Purge decision', 'axismundi-activitypub-bridge' ); ?></th></tr></thead>
		<tbody>
		<?php foreach ( $report['summary'] as $source => $summary ) : ?>
			<tr>
				<td><code><?php echo esc_html( (string) $source ); ?></code></td>
				<td><?php echo esc_html( (int) $summary['scanned'] . ' / ' . (int) $summary['available'] ); ?></td>
				<td><?php echo esc_html( axismundi_activitypub_bridge_report_counts( (array) $summary['import'] ) ); ?></td>
				<td><?php echo esc_html( axismundi_activitypub_bridge_report_counts( (array) $summary['purge'] ) ); ?></td>
			</tr>
		<?php endforeach; ?>
		</tbody>
	</table>
	<h3><?php esc_html_e( 'Classified sample', 'axismundi-activitypub-bridge' ); ?></h3>
	<table class="widefat striped">
		<thead><tr><th><?php esc_html_e( 'Source row', 'axismundi-activitypub-bridge' ); ?></th><th><?php esc_html_e( 'Canonical identity', 'axismundi-activitypub-bridge' ); ?></th><th><?php esc_html_e( 'Import', 'axismundi-activitypub-bridge' ); ?></th><th><?php esc_html_e( 'Purge', 'axismundi-activitypub-bridge' ); ?></th><th><?php esc_html_e( 'Reason', 'axismundi-activitypub-bridge' ); ?></th></tr></thead>
		<tbody>
		<?php foreach ( $report['rows'] as $row ) : ?>
			<tr>
				<td><code><?php echo esc_html( $row['source'] . ':' . $row['source_id'] ); ?></code></td>
				<td><code><?php echo esc_html( '' !== $row['identity'] ? $row['identity'] : '—' ); ?></code></td>
				<td><code><?php echo esc_html( $row['import'] ); ?></code></td>
				<td><code><?php echo esc_html( $row['purge'] ); ?></code></td>
				<td><?php echo esc_html( $row['detail'] ); ?></td>
			</tr>
		<?php endforeach; ?>
		</tbody>
	</table>
	<?php
}

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
	$queue = axismundi_activitypub_bridge_delivery_jobs( 100 );
	$legacy_report = axismundi_activitypub_bridge_requested_legacy_report();
	$legacy_import = axismundi_activitypub_bridge_requested_legacy_import();
	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'ActivityPub Bridge', 'axismundi-activitypub-bridge' ); ?></h1>
		<p><?php esc_html_e( 'Axismundi owns Actor representations and Activity state. The Bridge owns this outbound queue and reuses the official ActivityPub plugin only for HTTP signature generation and Inbox verification.', 'axismundi-activitypub-bridge' ); ?></p>

		<?php axismundi_activitypub_bridge_render_legacy_scan( $legacy_report, $legacy_import ); ?>

		<h2><?php esc_html_e( 'Endpoints', 'axismundi-activitypub-bridge' ); ?></h2>
		<p><strong><?php esc_html_e( 'Shared Inbox', 'axismundi-activitypub-bridge' ); ?>:</strong> <code><?php echo esc_html( axismundi_activitypub_bridge_shared_inbox_url() ); ?></code></p>
		<table class="widefat striped"><thead><tr><th><?php esc_html_e( 'Actor', 'axismundi-activitypub-bridge' ); ?></th><th><?php esc_html_e( 'Inbox', 'axismundi-activitypub-bridge' ); ?></th><th><?php esc_html_e( 'Outbox', 'axismundi-activitypub-bridge' ); ?></th><th><?php esc_html_e( 'Signing key', 'axismundi-activitypub-bridge' ); ?></th></tr></thead><tbody>
		<?php foreach ( axismundi_activitypub_bridge_public_actors() as $actor ) : $sender = axismundi_activitypub_bridge_sender( $actor ); $outbox = function_exists( 'axismundi_op_actor_outbox_url' ) ? axismundi_op_actor_outbox_url( $actor ) : ''; ?>
			<tr>
				<td><a href="<?php echo esc_url( $actor->get_profile_url() ); ?>"><code><?php echo esc_html( $actor->get_uri() ); ?></code></a></td>
				<td><code><?php echo esc_html( axismundi_activitypub_bridge_inbox_url( $actor ) ); ?></code></td>
				<td><?php if ( '' !== $outbox ) : ?><a href="<?php echo esc_url( $outbox ); ?>"><code><?php echo esc_html( $outbox ); ?></code></a><?php else : ?>—<?php endif; ?></td>
				<td><code><?php echo esc_html( $sender['key_id'] ); ?></code></td>
			</tr>
		<?php endforeach; ?>
		</tbody></table>

		<h2><?php esc_html_e( 'Inbox ledger', 'axismundi-activitypub-bridge' ); ?></h2>
		<table class="widefat striped"><thead><tr><th><?php esc_html_e( 'Activity', 'axismundi-activitypub-bridge' ); ?></th><th><?php esc_html_e( 'Actor', 'axismundi-activitypub-bridge' ); ?></th><th><?php esc_html_e( 'Object', 'axismundi-activitypub-bridge' ); ?></th><th><?php esc_html_e( 'Status', 'axismundi-activitypub-bridge' ); ?></th></tr></thead><tbody><?php axismundi_activitypub_bridge_render_activity_rows( 'inbound' ); ?></tbody></table>

		<h2><?php esc_html_e( 'Inbox diagnostics', 'axismundi-activitypub-bridge' ); ?></h2>
		<p><?php esc_html_e( 'Recent verified Inbox outcomes. Payload content and recipient data are never copied into this diagnostic buffer.', 'axismundi-activitypub-bridge' ); ?></p>
		<table class="widefat striped"><thead><tr><th><?php esc_html_e( 'UTC time', 'axismundi-activitypub-bridge' ); ?></th><th><?php esc_html_e( 'Route', 'axismundi-activitypub-bridge' ); ?></th><th><?php esc_html_e( 'Type', 'axismundi-activitypub-bridge' ); ?></th><th><?php esc_html_e( 'Activity hash', 'axismundi-activitypub-bridge' ); ?></th><th><?php esc_html_e( 'Outcome', 'axismundi-activitypub-bridge' ); ?></th></tr></thead><tbody>
		<?php $diagnostics = axismundi_activitypub_bridge_inbox_diagnostics(); ?>
		<?php if ( empty( $diagnostics ) ) : ?><tr><td colspan="5"><?php esc_html_e( 'No Inbox diagnostics recorded yet.', 'axismundi-activitypub-bridge' ); ?></td></tr><?php endif; ?>
		<?php foreach ( $diagnostics as $entry ) : ?>
			<tr>
				<td><?php echo esc_html( (string) ( $entry['time'] ?? '' ) ); ?></td>
				<td><code><?php echo esc_html( (string) ( $entry['route'] ?? '' ) ); ?></code></td>
				<td><?php echo esc_html( (string) ( $entry['activity_type'] ?? '' ) ); ?></td>
				<td><code><?php echo esc_html( substr( (string) ( $entry['activity_id_hash'] ?? '' ), 0, 16 ) ); ?></code></td>
				<td><strong><?php echo esc_html( (string) ( $entry['outcome'] ?? '' ) ); ?></strong><?php if ( ! empty( $entry['code'] ) ) : ?> — <code><?php echo esc_html( (string) $entry['code'] ); ?></code><?php endif; ?></td>
			</tr>
		<?php endforeach; ?>
		</tbody></table>

		<h2><?php esc_html_e( 'Outbox ledger', 'axismundi-activitypub-bridge' ); ?></h2>
		<table class="widefat striped"><thead><tr><th><?php esc_html_e( 'Activity', 'axismundi-activitypub-bridge' ); ?></th><th><?php esc_html_e( 'Actor', 'axismundi-activitypub-bridge' ); ?></th><th><?php esc_html_e( 'Object', 'axismundi-activitypub-bridge' ); ?></th><th><?php esc_html_e( 'Status', 'axismundi-activitypub-bridge' ); ?></th></tr></thead><tbody><?php axismundi_activitypub_bridge_render_activity_rows( 'outbound' ); ?></tbody></table>

		<h2><?php esc_html_e( 'Transport queue', 'axismundi-activitypub-bridge' ); ?></h2>
		<table class="widefat striped"><thead><tr><th><?php esc_html_e( 'Activity', 'axismundi-activitypub-bridge' ); ?></th><th><?php esc_html_e( 'Sender', 'axismundi-activitypub-bridge' ); ?></th><th><?php esc_html_e( 'Status', 'axismundi-activitypub-bridge' ); ?></th><th><?php esc_html_e( 'Attempt', 'axismundi-activitypub-bridge' ); ?></th><th><?php esc_html_e( 'Last error', 'axismundi-activitypub-bridge' ); ?></th></tr></thead><tbody>
		<?php if ( empty( $queue ) ) : ?><tr><td colspan="5"><?php esc_html_e( 'No transport jobs.', 'axismundi-activitypub-bridge' ); ?></td></tr><?php endif; ?>
		<?php foreach ( $queue as $job ) : ?>
			<tr>
				<td><code><?php echo esc_html( (string) $job->activity_uri ); ?></code></td>
				<td><code><?php echo esc_html( (string) $job->actor_uri ); ?></code></td>
				<td><?php echo esc_html( (string) $job->status ); ?></td>
				<td><?php echo esc_html( (string) $job->attempt ); ?></td>
				<td><?php echo esc_html( (string) $job->last_error ); ?></td>
			</tr>
		<?php endforeach; ?>
		</tbody></table>
	</div>
	<?php
}
