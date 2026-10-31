<?php
/** Authenticated Todo proof-of-concept admin surface. */
namespace Axismundi\Jmap\TodoPoc\Admin;
defined( 'ABSPATH' ) || exit;

function permission( \WP_REST_Request $request ) {
	if ( ! current_user_can( 'read' ) || ! wp_verify_nonce( $request->get_header( 'X-WP-Nonce' ), 'wp_rest' ) ) {
		return new \WP_Error( 'todo_forbidden', 'Authentication required.', array( 'status' => 403 ) );
	}
	return true;
}

function server_failure( \Throwable $error ) : \WP_Error {
	error_log( 'Todo REST server failure: ' . get_class( $error ) );
	return new \WP_Error( 'todo_server_failed', 'Server failed.', array( 'status' => 500 ) );
}

// Cover successful responses and permission/error responses on all Todo routes.
add_filter( 'rest_post_dispatch', function ( $response, $server, $request ) {
	if ( preg_match( '#^/axismundi-jmap/v1/todo(?:/|$)#', $request->get_route() ) ) {
		$response = rest_ensure_response( $response );
		$response->header( 'Cache-Control', 'private, no-store' );
	}
	return $response;
}, 10, 3 );

add_action( 'rest_api_init', function () {
	register_rest_route( 'axismundi-jmap/v1', '/todo/context', array(
		'methods' => 'GET', 'permission_callback' => __NAMESPACE__ . '\\permission',
		'callback' => function () {
			try { return rest_ensure_response( array(
			'accounts' => \Axismundi\Jmap\TodoPoc\account_options(),
			'directoryAccountId' => \Axismundi\Jmap\TodoPoc\directory_account(),
			'capabilities' => array( 'urn:ietf:params:jmap:core', \Axismundi\Jmap\TodoPoc\CAPABILITY, \Axismundi\Jmap\TodoPoc\PRINCIPALS ),
			) ); }
			catch ( \Throwable $error ) { return server_failure( $error ); }
		},
	) );
	register_rest_route( 'axismundi-jmap/v1', '/todo', array(
		// Deprecated development URL: preserve it as a narrow generic API alias.
		'methods' => 'POST', 'permission_callback' => '\\Axismundi\\Jmap\\Http\\permission',
		'callback' => '\\Axismundi\\Jmap\\Http\\dispatch',
	) );
	register_rest_route( 'axismundi-jmap/v1', '/todo/enable', array(
		'methods' => 'POST',
		'permission_callback' => function ( $request ) {
			$allowed = permission( $request );
			return true === $allowed && current_user_can( 'manage_options' ) ? true : new \WP_Error( 'todo_forbidden', 'Administrator access required.', array( 'status' => 403 ) );
		},
		'callback' => function ( \WP_REST_Request $request ) {
			$id = $request->get_param( 'accountId' );
			if ( ! is_string( $id ) || '' === $id ) { return new \WP_Error( 'todo_account', 'Account ID required.', array( 'status' => 400 ) ); }
			try {
				$result = \Axismundi\Jmap\TodoPoc\enable_account( $id );
				return is_wp_error( $result ) ? $result : rest_ensure_response( array( 'enabled' => $result ) );
			} catch ( \Throwable $error ) { return server_failure( $error ); }
		},
	) );
} );

add_action( 'admin_menu', function () {
	$hook = add_menu_page( 'Todo', 'Todo', 'read', 'axismundi-jmap-todo', function () {
		echo '<div class="wrap" id="ax-jmap-todo"><h1>Todo</h1><p role="status" aria-live="polite" id="todo-status"></p><div id="todo-content"></div></div>';
	}, 'dashicons-list-view' );
	add_action( 'admin_enqueue_scripts', function ( $screen ) use ( $hook ) {
		if ( $screen !== $hook ) { return; }
		$root = dirname( __DIR__, 3 );
		$url = plugins_url( 'assets/', $root . '/axismundi-jmap.php' );
		wp_enqueue_style( 'ax-jmap-todo', $url . 'todo.css', array(), filemtime( $root . '/assets/todo.css' ) );
		wp_enqueue_script( 'ax-jmap-todo-sync', $url . 'todo-sync.js', array(), filemtime( $root . '/assets/todo-sync.js' ), true );
		wp_enqueue_script( 'ax-jmap-todo', $url . 'todo.js', array( 'ax-jmap-todo-sync' ), filemtime( $root . '/assets/todo.js' ), true );
		wp_add_inline_script( 'ax-jmap-todo', 'window.axTodoConfig=' . wp_json_encode( array( 'url' => rest_url( 'axismundi-jmap/v1/api' ), 'contextUrl' => rest_url( 'axismundi-jmap/v1/todo' ), 'nonce' => wp_create_nonce( 'wp_rest' ), 'canEnable' => current_user_can( 'manage_options' ) ) ) . ';', 'before' );
	} );
} );
