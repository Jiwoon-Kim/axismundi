<?php
/** Local Group federation contracts; no wire projection or platform detection. */
defined( 'ABSPATH' ) || exit;

/** Null preserves historical Groups without assigning a new contract. */
function axismundi_actors_group_profile( Axismundi_Actor $actor ) : ?string {
	if ( ! $actor->is_local() || 'Group' !== $actor->get_type() ) { return null; }
	return axismundi_actors_get_meta( $actor->get_identity_id(), 'policy:federation_profile' );
}

/** Validate explicit creation input; legacy callers may omit the field. */
function axismundi_actors_validate_group_profile( string $type, array $args ) {
	if ( ! array_key_exists( 'federation_profile', $args ) ) { return true; }
	if ( 'Group' !== $type ) {
		return new WP_Error( 'ax_actors_profile_type', 'Only Group Actors may have a federation profile.' );
	}
	if ( 'community' !== $args['federation_profile'] ) {
		return new WP_Error( 'ax_actors_profile_unavailable', 'Only the Community federation profile is available.', array( 'status' => 400 ) );
	}
	return true;
}

/** Ordinary edits cannot assign contracts; explicit legacy migration is a separate command. */
function axismundi_actors_check_group_profile_update( array $args ) {
	return array_key_exists( 'federation_profile', $args )
		? new WP_Error( 'ax_actors_profile_immutable', 'Federation profiles cannot be changed after Actor creation.', array( 'status' => 400 ) )
		: true;
}

/** Reject mutation on Actor REST surfaces without adding an update API. */
function axismundi_actors_guard_group_profile_rest( $result, $server, WP_REST_Request $request ) {
	if ( null !== $result || ! preg_match( '#^/axismundi/(?:v1|activitypub)/actors(?:/|$)#', $request->get_route() )
		|| in_array( $request->get_method(), array( 'GET', 'HEAD', 'OPTIONS' ), true ) ) { return $result; }
	$check = axismundi_actors_check_group_profile_update( $request->get_params() );
	return is_wp_error( $check ) ? $check : $result;
}
add_filter( 'rest_pre_dispatch', 'axismundi_actors_guard_group_profile_rest', 10, 3 );

/** Only creation forms may supply the profile in an Actor admin request. */
function axismundi_actors_guard_group_profile_admin() : void {
	$action = sanitize_key( wp_unslash( $_POST['action'] ?? '' ) );
	if ( 0 !== strpos( $action, 'axismundi_actors_' ) || in_array( $action, array( 'axismundi_actors_add_local_actor', 'axismundi_actors_create_managed_actor' ), true ) ) { return; }
	$check = axismundi_actors_check_group_profile_update( $_POST );
	if ( is_wp_error( $check ) ) { wp_die( esc_html( $check->get_error_message() ), '', array( 'response' => 400 ) ); }
}
add_action( 'admin_init', 'axismundi_actors_guard_group_profile_admin' );

/** Conditional creation field shared by local Actor forms. */
function axismundi_actors_render_group_profile_field() : void {
	?>
	<tr data-ax-group-profile hidden>
		<th><?php esc_html_e( 'Federation profile', 'axismundi-actors' ); ?></th>
		<td>
			<fieldset>
				<label><input type="radio" name="federation_profile" value="community" checked disabled> <?php esc_html_e( 'Community', 'axismundi-actors' ); ?></label>
				<p class="description"><?php esc_html_e( 'Uses FEP-1b12 for community federation. Implementation is in progress.', 'axismundi-actors' ); ?></p>
				<label><input type="radio" value="channel" disabled> <?php esc_html_e( 'Channel (not implemented)', 'axismundi-actors' ); ?></label>
				<p class="description"><?php esc_html_e( 'The Channel profile does not use FEP-1b12.', 'axismundi-actors' ); ?></p>
				<p class="description"><?php esc_html_e( 'The federation profile cannot be changed after creation.', 'axismundi-actors' ); ?></p>
			</fieldset>
		</td>
	</tr>
	<script>
	document.addEventListener('DOMContentLoaded', function () {
		document.querySelectorAll('[data-ax-group-profile]').forEach(function (row) {
			const form = row.closest('form');
			const update = function () {
				const type = form.querySelector('select[name="actor_type"], input[name="actor_type"]:checked');
				const group = type && type.value === 'Group';
				row.hidden = !group;
				row.querySelector('[name="federation_profile"]').disabled = !group;
			};
			form.querySelectorAll('[name="actor_type"]').forEach(function (input) { input.addEventListener('change', update); });
			update();
		});
	});
	</script>
	<?php
}
