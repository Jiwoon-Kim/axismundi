<?php
/** Exercise the admin POST adapter against the existing transactional review contract. */
defined( 'ABSPATH' ) || exit( 1 );
$admin_checks = 0;
$admin_request = $community_admin_request ?? 'axismundi_act_admin_review_request';
$register_pages = $community_admin_register ?? 'axismundi_act_register_workflow_pages';
$page_checked = false;
$community_review = static function ( $user, $moderator, $group, $uri, $approve, $reason = '' ) use ( &$admin_checks, $admin_request, $register_pages, &$page_checked ) {
	$previous = get_current_user_id();
	wp_set_current_user( $user );
	try {
		$input = array( 'moderator_id' => (string) $moderator->get_identity_id(), 'group_id' => (string) $group->get_identity_id(), 'object_uri' => $uri, 'decision' => $approve ? 'approve' : 'reject', 'reason' => $reason );
		$input['_wpnonce'] = wp_create_nonce( axismundi_act_admin_review_nonce_action( $moderator->get_identity_id(), $group->get_identity_id(), $uri ) );
		$row = axismundi_actors_get_community_post( $group->get_identity_id(), $uri );
		if ( axismundi_act_admin_review_allowed( $moderator, $group ) && 'pending' === $row['admission_state'] ) {
			require_once ABSPATH . 'wp-admin/includes/template.php';
			$old_get = $_GET;
			$_GET = array( 'group_id' => (string) $group->get_identity_id() );
			ob_start();
			try { axismundi_act_render_community_review(); }
			finally { $html = ob_get_clean(); $_GET = $old_get; }
			if ( ! str_contains( $html, 'axismundi_community_review' ) || ! str_contains( $html, esc_attr( $uri ) ) || ! str_contains( $html, 'value="reject"' ) ) { throw new RuntimeException( 'Pending screen must expose the nonce-protected review form' ); }
			++$admin_checks;
		}
		$bad = $input; $bad['_wpnonce'] = 'invalid';
		if ( ! is_wp_error( $admin_request( $bad ) ) ) { throw new RuntimeException( 'Invalid nonce accepted' ); }
		++$admin_checks;
		$bad = $input; $bad['decision'] = array( 'approve' );
		if ( ! is_wp_error( $admin_request( $bad ) ) ) { throw new RuntimeException( 'Non-scalar decision accepted' ); }
		++$admin_checks;
		$allowed = axismundi_act_admin_review_allowed( $moderator, $group );
		if ( ! $page_checked && ! $allowed ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
			require_once ABSPATH . 'wp-admin/includes/template.php';
			$globals = array();
			foreach ( array( 'menu', 'submenu', 'admin_page_hooks', '_registered_pages', '_parent_pages' ) as $key ) {
				$globals[$key] = $GLOBALS[$key] ?? null;
				$GLOBALS[$key] = array();
			}
			try {
				$register_pages();
				$slugs = array_column( $GLOBALS['submenu']['tools.php'] ?? array(), 2 );
				if ( ! in_array( 'axismundi-community-review', $slugs, true ) ) { throw new RuntimeException( 'Moderation page must register without memberships' ); }
				++$admin_checks;
			} finally {
				foreach ( $globals as $key => $value ) { if ( null === $value ) { unset( $GLOBALS[$key] ); } else { $GLOBALS[$key] = $value; } }
			}
			$old_get = $_GET; $_GET = array( 'group_id' => (string) $group->get_identity_id() );
			ob_start();
			try { axismundi_act_render_community_review(); }
			finally { $empty = ob_get_clean(); $_GET = $old_get; }
			if ( ! str_contains( $empty, 'You do not moderate any Communities as this Person.' ) ) { throw new RuntimeException( 'A Person without memberships gets an empty workspace, not a permission failure' ); }
			if ( str_contains( $empty, $uri ) || str_contains( $empty, 'value="approve"' ) ) { throw new RuntimeException( 'An unauthorized Group must disclose no submission or decision button' ); }
			$admin_checks += 2;
			if ( ! function_exists( 'axismundi_actors_render_managed_actors_page' ) ) { require_once WP_PLUGIN_DIR . '/axismundi-actors/includes/admin.php'; }
			$reflection = new ReflectionFunction( 'axismundi_actors_render_managed_actors_page' );
			$source = implode( '', array_slice( file( $reflection->getFileName() ), $reflection->getStartLine() - 1, $reflection->getEndLine() - $reflection->getStartLine() + 1 ) );
			if ( str_contains( $source, 'axismundi_forum_' ) || str_contains( $source, 'Groups I moderate' ) ) { throw new RuntimeException( 'Managed accounts must not retain Forum moderation' ); }
			++$admin_checks;
			$page_checked = true;
		}
		if ( $allowed && ! $approve ) {
			$bad = $input; $bad['reason'] = " \n\t";
			$denied = $admin_request( $bad );
			if ( ! is_wp_error( $denied ) || 'ax_review_reason' !== $denied->get_error_code() ) { throw new RuntimeException( 'Handler must require a rejection reason before calling the service' ); }
			++$admin_checks;
		}
		$old_selection = get_user_meta( $user, AXISMUNDI_ACTORS_ACTING_META, true );
		// Simulate another tab switching to the Group after a Person form was rendered.
		update_user_meta( $user, AXISMUNDI_ACTORS_ACTING_META, $group->get_identity_id() );
		try { $result = $admin_request( $input ); }
		finally {
			if ( '' === $old_selection ) { delete_user_meta( $user, AXISMUNDI_ACTORS_ACTING_META ); }
			else { update_user_meta( $user, AXISMUNDI_ACTORS_ACTING_META, $old_selection ); }
		}
		if ( $allowed && 'pending' === $row['admission_state'] && $approve && ! is_wp_error( $result ) ) {
			if ( ! $result instanceof Axismundi_Activity || $result->get_actor_uri() !== $group->get_uri() ) { throw new RuntimeException( 'Rendered Person must survive a later Account switch' ); }
			++$admin_checks;
		}
		if ( $allowed && ! $approve && ! is_wp_error( $result ) ) {
			if ( $reason !== axismundi_actors_get_community_post( $group->get_identity_id(), $uri )['review_reason'] ) { throw new RuntimeException( 'Rejection reason must commit with the decision' ); }
			++$admin_checks;
		}
		if ( ! axismundi_act_admin_review_allowed( $moderator, $group ) ) {
			if ( ! is_wp_error( $result ) || 'ax_review_forbidden' !== $result->get_error_code() ) { throw new RuntimeException( 'Handler must reject unauthorized review before calling the service' ); }
			if ( axismundi_act_admin_pending_posts( $moderator, $group ) ) { throw new RuntimeException( 'Unauthorized pending rows disclosed' ); }
			$admin_checks += 2;
		}
		return $result;
	} finally { wp_set_current_user( $previous ); }
};
ob_start();
try { require __DIR__ . '/audit-community-posts.php'; }
finally { $summary = ob_get_clean(); }
if ( ! preg_match( '/== (\d+) checks, 0 failed ==/', $summary, $match ) ) { throw new RuntimeException( 'Review baseline did not complete: ' . $summary ); }
printf( "== %d checks, 0 failed ==\n", (int) $match[1] + $admin_checks );
