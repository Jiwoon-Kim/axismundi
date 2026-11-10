<?php
/** Write current WP source attribution, retaining immutable Activity history. */
defined( 'ABSPATH' ) || exit;

function axismundi_act_attribution_post_source( string $uri ) : ?WP_Post {
	$parts = wp_parse_url( $uri );
	$query = array();
	parse_str( $parts['query'] ?? '', $query );
	$id = $query['p'] ?? $query['attachment_id'] ?? null;
	if ( ! is_scalar( $id ) || ! ctype_digit( (string) $id ) || (int) $id <= 0 ) { return null; }
	$post = get_post( (int) $id );
	if ( ! $post instanceof WP_Post || ! in_array( $post->post_type, array( 'post', 'attachment' ), true ) || ! function_exists( 'axismundi_op_post_object_uri' ) ) { return null; }
	$expected = 'attachment' === $post->post_type ? axismundi_op_core_attachment_uri( $post ) : axismundi_op_post_object_uri( $post );
	return $uri === $expected ? $post : null;
}

/** Post-commit sink runs before OP updates the shadow index. Replayed older rows cannot win. */
function axismundi_act_store_recorded_post_attribution( Axismundi_Activity $activity ) : void {
	if ( 'outbound' !== $activity->get_direction() || ! in_array( $activity->get_type(), array( 'Create', 'Update' ), true ) || ! function_exists( 'axismundi_op_store_post_attribution' ) ) { return; }
	$post = axismundi_act_attribution_post_source( $activity->get_object_uri() );
	$latest = $post ? axismundi_act_get_object_lifecycle( $activity->get_object_uri() ) : null;
	if ( ! $post || ! $latest || $latest->get_uri() !== $activity->get_uri() ) { return; }
	$object = $activity->get_payload()['object'] ?? null;
	$members = is_array( $object ) && isset( $object['attributedTo'] ) ? axismundi_op_normalize_attribution_uris( $object['attributedTo'] ) : array();
	if ( is_wp_error( $members ) ) { do_action( 'axismundi_act_post_attribution_write_failed', $members, $activity ); return; }
	if ( 'post' === $post->post_type ) {
		// Updates project the attribution already stored by the source mutation, not the other way around.
		if ( 'Create' !== $activity->get_type() || axismundi_act_outbound_create_actor( $activity->get_object_uri() ) !== $activity->get_actor_uri() ) {
			return;
		}
		$result = axismundi_op_store_post_attribution( $post, $activity->get_actor_uri(), $members ?: null );
		if ( is_wp_error( $result ) ) { do_action( 'axismundi_act_post_attribution_write_failed', $result, $activity ); }
		return;
	}
	$uri = axismundi_op_stored_post_attribution( $post->ID );
	if ( ! in_array( $uri, $members, true ) ) { $uri = ''; }
	if ( '' === $uri && in_array( $activity->get_actor_uri(), $members, true ) ) { $uri = $activity->get_actor_uri(); }
	if ( '' === $uri && 1 === count( $members ) ) { $uri = $members[0]; }
	if ( '' === $uri && 'Create' === $activity->get_type() ) { $uri = $activity->get_actor_uri(); }
	// An Update's actor is not evidence of Object attribution.
	if ( '' === $uri ) { return; }
	if ( $members && ! in_array( $uri, $members, true ) ) {
		do_action( 'axismundi_act_post_attribution_write_failed', new WP_Error( 'ax_act_attribution_ambiguous', 'Joint attribution has no explicit local publisher.' ), $activity ); return;
	}
	$result = axismundi_op_store_post_attribution( $post, $uri, $members ? array_values( array_diff( $members, array( $uri ) ) ) : null );
	if ( is_wp_error( $result ) ) { do_action( 'axismundi_act_post_attribution_write_failed', $result, $activity ); }
}
add_action( 'axismundi_act_activity_recorded', 'axismundi_act_store_recorded_post_attribution', 5 );

/** Authorized attribution changes emit Update without rewriting the original Create. */
function axismundi_act_publish_post_attribution_change( $result, WP_Post $post, string $old, string $uri, int $user_id, array $old_others = array(), array $new_others = array(), bool $unpublished_only = false, bool $allow_federated_update = true ) {
	if ( is_wp_error( $result ) ) { return $result; }
	$object_uri = 'attachment' === $post->post_type ? axismundi_op_core_attachment_uri( $post ) : axismundi_op_post_object_uri( $post );
	if ( $unpublished_only ) {
		if ( false !== axismundi_act_has_outbound_create( $object_uri ) ) {
			return new WP_Error( 'ax_act_attribution_published', __( 'Publication changed during bulk assignment.', 'axismundi-activities' ), array( 'status' => 409 ) );
		}
		$GLOBALS['axismundi_act_attribution_only_saves'][$post->ID] = true;
		return $result;
	}
	$latest = axismundi_act_get_object_lifecycle( $object_uri );
	if ( ! $latest || 'Delete' === $latest->get_type() ) {
		// Editing only the Account field must not turn a subsequent WP save into first publication.
		$GLOBALS['axismundi_act_attribution_only_saves'][$post->ID] = true;
		return $result;
	}
	if ( ! $allow_federated_update ) {
		return new WP_Error( 'ax_act_attribution_confirm_update', __( 'Reload the actor picker and confirm the federated Update before changing attribution.', 'axismundi-activities' ), array( 'status' => 409 ) );
	}
	if ( '' === $uri ) { return new WP_Error( 'ax_act_attribution_empty', 'Choose a publishing Account for an already announced Object.', array( 'status' => 409 ) ); }
	$actor = axismundi_actors_get_by_uri( $uri );
	if ( ! user_can( $user_id, 'edit_post', $post->ID ) || ! $actor || ! axismundi_actors_can_act_as( $actor, $user_id ) ) {
		return new WP_Error( 'ax_act_attribution_permission', 'Publishing Account authority changed.', array( 'status' => 403 ) );
	}
	$object = axismundi_op_transform_object( $post );
	if ( ! is_array( $object ) || ( $object['id'] ?? '' ) !== $object_uri || axismundi_op_local_object_publisher_uri( $object ) !== $uri ) {
		return new WP_Error( 'ax_act_attribution_projection', 'The changed Object cannot be projected.', array( 'status' => 409 ) );
	}
	$recorded = axismundi_act_record_object_commit( $object, $uri );
	return is_wp_error( $recorded ) ? $recorded : $result;
}
add_filter( 'axismundi_op_post_attribution_changed', 'axismundi_act_publish_post_attribution_change', 10, 9 );

/** Explicit bounded migration: default is read-only; ambiguous history is never guessed. */
function axismundi_act_migrate_post_attribution( bool $apply = false, int $after = 0, int $limit = 100 ) : array {
	global $wpdb;
	$ids = $wpdb->get_col( $wpdb->prepare( "SELECT ID FROM %i WHERE post_type IN ('post','attachment') AND ID > %d ORDER BY ID LIMIT %d", $wpdb->posts, $after, max( 1, min( 500, $limit ) ) ) );
	$report = array( 'apply' => $apply, 'after' => $after, 'next' => $after, 'checked' => 0, 'candidates' => array(), 'conflicts' => array(), 'missing' => 0, 'unchanged' => 0, 'written' => 0, 'errors' => array() );
	foreach ( $ids as $id ) {
		$post = get_post( (int) $id );
		$refresh = false;
		$report['next'] = (int) $id;
		++$report['checked'];
		$uri = 'attachment' === $post->post_type ? axismundi_op_core_attachment_uri( $post ) : axismundi_op_post_object_uri( $post );
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT activity_type, actor_uri, payload_json FROM %i WHERE object_uri_hash=%s AND object_uri=%s AND direction='outbound' AND activity_type IN ('Create','Update') AND effective_status='active'", axismundi_act_activities_table(), hash( 'sha256', $uri ), $uri ), ARRAY_A );
		$claims = array();
		$ambiguous_members = false;
		foreach ( $rows as $row ) {
			$payload = json_decode( $row['payload_json'], true );
			$object = $payload['object'] ?? null;
			$members = is_array( $object ) && isset( $object['attributedTo'] ) ? axismundi_op_normalize_attribution_uris( $object['attributedTo'] ) : array();
			if ( is_wp_error( $members ) || count( $members ) > 1 ) { $ambiguous_members = true; break; }
			$claim = is_array( $object ) ? axismundi_act_member_uri( $object['attributedTo'] ?? '' ) : '';
			if ( '' === $claim && 'Create' === $row['activity_type'] ) { $claim = $row['actor_uri']; }
			if ( '' !== $claim ) { $claims[$claim] = true; }
		}
		if ( $ambiguous_members ) { $report['conflicts'][] = (int) $id; continue; }
		if ( ! $claims ) { ++$report['missing']; }
		elseif ( 1 !== count( $claims ) ) { $report['conflicts'][] = (int) $id; }
		else {
			$claim = array_key_first( $claims );
			$stored = axismundi_op_stored_post_attribution( (int) $id );
			if ( metadata_exists( 'post', (int) $id, AXISMUNDI_OP_ATTRIBUTED_TO_META ) ) {
				if ( $stored === $claim ) { ++$report['unchanged']; $refresh = true; } else { $report['conflicts'][] = (int) $id; }
			} else {
				$actor = axismundi_actors_get_by_uri( $claim );
				if ( ! $actor || ! $actor->is_local() ) { $report['conflicts'][] = (int) $id; }
				else {
					$report['candidates'][(int) $id] = $claim;
					if ( $apply ) {
						if ( ! add_post_meta( (int) $id, AXISMUNDI_OP_ATTRIBUTED_TO_META, wp_slash( $claim ), true ) ) { $report['errors'][] = (int) $id; }
						else { ++$report['written']; $refresh = true; }
					}
				}
			}
		}
		// Register existing attachments only when the operator explicitly applies this batch.
		if ( $apply && ! in_array( (int) $id, $report['conflicts'], true ) && ( $refresh || 'attachment' === $post->post_type ) ) {
			$registered = axismundi_op_register_local_post_object( $post );
			if ( is_wp_error( $registered ) ) { $report['errors'][] = (int) $id; }
			else { axismundi_op_refresh_object_listing_projection( $uri ); }
		}
	}
	return $report;
}

if ( defined( 'WP_CLI' ) && WP_CLI ) {
	WP_CLI::add_command( 'axismundi attribution', static function ( array $args, array $options ) : void {
		$report = axismundi_act_migrate_post_attribution( isset( $options['apply'] ), absint( $options['after'] ?? 0 ), absint( $options['limit'] ?? 100 ) );
		WP_CLI::line( wp_json_encode( $report, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) );
	} );
}
