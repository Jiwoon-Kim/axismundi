<?php
/**
 * Phase 2 - read-only Activity and relation log.
 *
 * @package AxismundiActivities
 */

defined( 'ABSPATH' ) || exit;

/** Activity Log administrator URL. */
function axismundi_act_admin_url( string $activity_uri = '' ) : string {
	$url = add_query_arg( 'page', 'axismundi-activity-log', admin_url( 'tools.php' ) );
	return '' === $activity_uri ? $url : add_query_arg( 'activity_uri', $activity_uri, $url );
}

/** Register the read-only log. */
function axismundi_act_register_admin_page() : void {
	add_management_page(
		__( 'Activity Log', 'axismundi-activities' ),
		__( 'Activity Log', 'axismundi-activities' ),
		'manage_options',
		'axismundi-activity-log',
		'axismundi_act_render_admin_page'
	);
}
add_action( 'admin_menu', 'axismundi_act_register_admin_page' );

/** Actor reference linked to the appropriate Actors-owned surface. */
function axismundi_act_admin_actor_link( string $actor_uri ) : string {
	$actor = axismundi_actors_get_by_uri( $actor_uri );
	$href  = $actor_uri;
	if ( $actor instanceof Axismundi_Actor ) {
		if ( $actor->is_local() && '' !== $actor->get_profile_url() ) {
			$href = $actor->get_profile_url();
		} elseif ( function_exists( 'axismundi_actors_remote_admin_url' ) ) {
			$href = add_query_arg( 'actor_id', $actor->get_identity_id(), axismundi_actors_remote_admin_url() );
		}
	}
	return '<a href="' . esc_url( $href ) . '"><code>' . esc_html( $actor_uri ) . '</code></a>';
}

/** Render one selected immutable payload. */
function axismundi_act_render_admin_detail( Axismundi_Activity $activity ) : void {
	$json = wp_json_encode( $activity->get_payload(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
	?>
	<hr>
	<h2><?php echo esc_html( $activity->get_type() ); ?></h2>
	<table class="widefat striped" style="max-width:1000px"><tbody>
		<tr><th scope="row"><?php esc_html_e( 'Activity URI', 'axismundi-activities' ); ?></th><td><code><?php echo esc_html( $activity->get_uri() ); ?></code></td></tr>
		<tr><th scope="row"><?php esc_html_e( 'Actor', 'axismundi-activities' ); ?></th><td><?php echo axismundi_act_admin_actor_link( $activity->get_actor_uri() ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- helper escapes complete anchor. ?></td></tr>
		<tr><th scope="row"><?php esc_html_e( 'Object', 'axismundi-activities' ); ?></th><td><code><?php echo esc_html( $activity->get_object_uri() ?? '—' ); ?></code></td></tr>
		<tr><th scope="row"><?php esc_html_e( 'Target', 'axismundi-activities' ); ?></th><td><code><?php echo esc_html( $activity->get_target_uri() ?? '—' ); ?></code></td></tr>
		<tr><th scope="row"><?php esc_html_e( 'Direction / effective status', 'axismundi-activities' ); ?></th><td><?php echo esc_html( $activity->get_direction() . ' / ' . $activity->get_effective_status() ); ?></td></tr>
	</tbody></table>
	<h3><?php esc_html_e( 'Immutable payload', 'axismundi-activities' ); ?></h3>
	<pre style="max-width:1000px;max-height:600px;overflow:auto;white-space:pre-wrap;overflow-wrap:anywhere"><?php echo esc_html( is_string( $json ) ? $json : '' ); ?></pre>
	<?php
}

/** Render recent ledger rows and materialized relation state. */
function axismundi_act_render_admin_page() : void {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'You cannot inspect the Activity ledger.', 'axismundi-activities' ), '', array( 'response' => 403 ) );
	}
	$selected_uri = isset( $_GET['activity_uri'] ) ? esc_url_raw( wp_unslash( $_GET['activity_uri'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only selection.
	$selected     = '' !== $selected_uri ? axismundi_act_get( $selected_uri ) : null;
	$activities   = axismundi_act_get_recent( 100 );
	$relations    = axismundi_act_get_recent_relations( 100 );
	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'Activity Log', 'axismundi-activities' ); ?></h1>
		<p><?php esc_html_e( 'Read-only local ledger. Network delivery and notification state are intentionally not shown here.', 'axismundi-activities' ); ?></p>
		<?php if ( $selected instanceof Axismundi_Activity ) : ?>
			<?php axismundi_act_render_admin_detail( $selected ); ?>
		<?php endif; ?>

		<hr>
		<h2><?php esc_html_e( 'Recent activities', 'axismundi-activities' ); ?></h2>
		<table class="widefat striped"><thead><tr><th><?php esc_html_e( 'Activity', 'axismundi-activities' ); ?></th><th><?php esc_html_e( 'Actor', 'axismundi-activities' ); ?></th><th><?php esc_html_e( 'Object', 'axismundi-activities' ); ?></th><th><?php esc_html_e( 'Direction', 'axismundi-activities' ); ?></th><th><?php esc_html_e( 'Status', 'axismundi-activities' ); ?></th></tr></thead><tbody>
		<?php if ( empty( $activities ) ) : ?>
			<tr><td colspan="5"><?php esc_html_e( 'No activities recorded.', 'axismundi-activities' ); ?></td></tr>
		<?php else : foreach ( $activities as $activity ) : ?>
			<tr>
				<td><a href="<?php echo esc_url( axismundi_act_admin_url( $activity->get_uri() ) ); ?>"><?php echo esc_html( $activity->get_type() ); ?></a><br><code><?php echo esc_html( $activity->get_uri() ); ?></code></td>
				<td><?php echo axismundi_act_admin_actor_link( $activity->get_actor_uri() ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- helper escapes complete anchor. ?></td>
				<td><code><?php echo esc_html( $activity->get_object_uri() ?? '—' ); ?></code></td>
				<td><?php echo esc_html( $activity->get_direction() ); ?></td>
				<td><?php echo esc_html( $activity->get_effective_status() ); ?></td>
			</tr>
		<?php endforeach; endif; ?>
		</tbody></table>

		<h2><?php esc_html_e( 'Social relations', 'axismundi-activities' ); ?></h2>
		<table class="widefat striped"><thead><tr><th><?php esc_html_e( 'Type', 'axismundi-activities' ); ?></th><th><?php esc_html_e( 'Subject', 'axismundi-activities' ); ?></th><th><?php esc_html_e( 'Object', 'axismundi-activities' ); ?></th><th><?php esc_html_e( 'Direction', 'axismundi-activities' ); ?></th><th><?php esc_html_e( 'State', 'axismundi-activities' ); ?></th></tr></thead><tbody>
		<?php if ( empty( $relations ) ) : ?>
			<tr><td colspan="5"><?php esc_html_e( 'No social relations derived.', 'axismundi-activities' ); ?></td></tr>
		<?php else : foreach ( $relations as $relation ) : ?>
			<tr><td><?php echo esc_html( (string) $relation['relation_type'] ); ?></td><td><?php echo axismundi_act_admin_actor_link( (string) $relation['subject_actor_uri'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- helper escapes complete anchor. ?></td><td><?php echo axismundi_act_admin_actor_link( (string) $relation['object_actor_uri'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- helper escapes complete anchor. ?></td><td><?php echo esc_html( (string) $relation['direction'] ); ?></td><td><?php echo esc_html( (string) $relation['state'] ); ?></td></tr>
		<?php endforeach; endif; ?>
		</tbody></table>
	</div>
	<?php
}
