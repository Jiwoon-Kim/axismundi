<?php
/** Regression checks for the plugin review input and output boundaries. */
defined( 'ABSPATH' ) || exit( 1 );
$checks = 0;
$assert = static function ( $ok, $label ) use ( &$checks ) {
	++$checks;
	if ( ! $ok ) { throw new RuntimeException( $label ); }
	echo "PASS {$label}\n";
};
$context = array( 'label' => 'Quotes " and \' <script> & text', 'nested' => array( 'enabled' => true ) );
$wrapper = get_block_wrapper_attributes( array( 'data-wp-context' => wp_json_encode( $context, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP ) ) );
$tag = new WP_HTML_Tag_Processor( '<div ' . $wrapper . '></div>' );
$tag->next_tag();
$assert( json_decode( $tag->get_attribute( 'data-wp-context' ), true ) === $context, 'Wrapper context survives attribute encoding without double escaping' );
$rendered = wp_interactivity_process_directives( '<div data-wp-interactive="axismundi/review-test" ' . $wrapper . '><span data-wp-text="context.label"></span></div>' );
$assert( str_contains( $rendered, esc_html( $context['label'] ) ), 'Interactivity runtime resolves the wrapper context' );
$allowed = axismundi_act_social_control_allowed_html();
$form = '<form method="post" action="https://example.test/wp-admin/admin-post.php" style="display:inline"><input type="hidden" name="_wpnonce" value="abc" id="_wpnonce"><input type="hidden" name="_wp_http_referer" value="/test"><button class="button" name="decision" value="reject">Remove</button></form>';
$assert( wp_kses( $form, $allowed ) === $form, 'Control allowlist preserves form action, nonce, referer and submit decision' );
$attack = wp_kses( '<form action="javascript:alert(1)" onsubmit="alert(2)"><input onfocus="alert(3)"><script>x</script></form>', $allowed );
$assert( ! preg_match( '/javascript:|onsubmit|onfocus|<script/i', $attack ), 'Control output rejects unsafe protocols, handlers and script tags' );
$namespace = 'AxReview' . str_replace( '-', '', wp_generate_uuid4() );
eval( 'namespace ' . $namespace . '; ' . <<<'PHP'
function axismundi_act_community_migration_request( $input ) { $GLOBALS[__NAMESPACE__] = $input; return new \WP_Error( 'test', 'stop' ); }
function axismundi_act_admin_review_request( $input ) { $GLOBALS[__NAMESPACE__] = $input; return new \WP_Error( 'test', 'stop' ); }
function wp_die( ...$args ) { throw new \RuntimeException( 'test-stop' ); }
PHP
);
foreach ( array( 'axismundi_act_handle_community_migration', 'axismundi_act_handle_admin_review' ) as $function ) {
	$reflection = new ReflectionFunction( $function );
	$source = implode( '', array_slice( file( $reflection->getFileName() ), $reflection->getStartLine() - 1, $reflection->getEndLine() - $reflection->getStartLine() + 1 ) );
	eval( 'namespace ' . $namespace . '; ' . $source );
}
$post = $_POST;
$method = $_SERVER['REQUEST_METHOD'] ?? null;
try {
	$_SERVER['REQUEST_METHOD'] = 'POST';
	$_POST = wp_slash( array( 'group_uri' => 'javascript:alert(1)', 'person_uri' => 'https://example.test/person', '_wpnonce' => '<b>nonce</b>', 'confirm_migration' => '1', 'ignored' => 'secret' ) );
	try { ( $namespace . '\axismundi_act_handle_community_migration' )(); } catch ( RuntimeException $e ) { if ( 'test-stop' !== $e->getMessage() ) { throw $e; } }
	$input = $GLOBALS[$namespace];
	$assert( 4 === count( $input ) && '' === $input['group_uri'] && 'nonce' === $input['_wpnonce'], 'Migration boundary selects named fields and sanitizes URI and nonce before lookup' );
	$_POST = wp_slash( array( 'moderator_id' => '12', 'group_id' => '34', 'object_uri' => 'javascript:alert(1)', 'decision' => 'APPROVE', '_wpnonce' => '<b>nonce</b>', 'reason' => '<b>reason</b>', 'ignored' => 'secret' ) );
	try { ( $namespace . '\axismundi_act_handle_admin_review' )(); } catch ( RuntimeException $e ) { if ( 'test-stop' !== $e->getMessage() ) { throw $e; } }
	$input = $GLOBALS[$namespace];
	$assert( 6 === count( $input ) && '' === $input['object_uri'] && 'approve' === $input['decision'] && 'reason' === $input['reason'], 'Review boundary selects and sanitizes named fields before service dispatch' );
	foreach ( array( 'axismundi_act_handle_community_migration' => 'group_uri', 'axismundi_act_handle_admin_review' => 'reason' ) as $handler => $field ) {
		unset( $GLOBALS[$namespace] );
		$_POST = array( $field => array( 'invalid' ) );
		try { ( $namespace . '\\' . $handler )(); } catch ( RuntimeException $e ) { if ( 'test-stop' !== $e->getMessage() ) { throw $e; } }
		$assert( ! isset( $GLOBALS[$namespace] ), 'Array input is rejected before dispatch: ' . $field );
	}
} finally {
	$_POST = $post;
	if ( null === $method ) { unset( $_SERVER['REQUEST_METHOD'] ); } else { $_SERVER['REQUEST_METHOD'] = $method; }
	unset( $GLOBALS[$namespace] );
}
echo "== {$checks} checks, 0 failed ==\n";
