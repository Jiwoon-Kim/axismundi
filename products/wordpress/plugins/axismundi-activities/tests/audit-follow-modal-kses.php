<?php
/** Exercise the production anonymous rendering branch and its local output boundary. */
defined( 'ABSPATH' ) || exit( 1 );
if ( ! function_exists( 'axismundi_dialogs_render_interaction_dialog' ) ) { throw new RuntimeException( 'Dialogs is required for this audit.' ); }
$checks = 0;
$assert = static function ( bool $ok, string $label ) use ( &$checks ) { ++$checks; if ( ! $ok ) { throw new RuntimeException( $label ); } echo "PASS {$label}\n"; };
$namespace = 'AxModal' . str_replace( '-', '', wp_generate_uuid4() );
$reflection = new ReflectionFunction( 'axismundi_act_render_follow_button' );
$source = implode( '', array_slice( file( $reflection->getFileName() ), $reflection->getStartLine() - 1, $reflection->getEndLine() - $reflection->getStartLine() + 1 ) );
eval( 'namespace ' . $namespace . '; use \Axismundi_Actor; use \WP_Block; ' . <<<'PHP'
function axismundi_act_follow_button_target( $attributes, $block ) { return $GLOBALS[__NAMESPACE__]['actor']; }
function axismundi_act_current_local_actor() { return null; }
function axismundi_act_follow_modal_allowed_html() { return $GLOBALS[__NAMESPACE__]['allowed']; }
function axismundi_act_render_remote_follow_modal( $actor, $id, $url ) {
 $modal = \axismundi_act_render_remote_follow_modal( $actor, $id, $url );
 $GLOBALS[__NAMESPACE__]['raw'] = $modal;
 return $modal . $GLOBALS[__NAMESPACE__]['attack'];
}
PHP
. $source );
$GLOBALS[$namespace] = array(
	'actor' => Axismundi_Actor::from_row( array( 'id' => -1, 'uuid' => wp_generate_uuid4(), 'canonical_uri' => 'https://example.test/actors/test', 'origin' => 'remote', 'status' => 'public', 'actor_type' => 'Person', 'preferred_username' => 'test', 'display_name' => 'Name <img src=x onerror="alert(1)"> & "quotes"', 'profile_url' => 'https://example.test/@test', 'local_user_id' => null ) ),
	'allowed' => axismundi_act_follow_modal_allowed_html(), 'attack' => '', 'raw' => '',
);
$render = $namespace . '\axismundi_act_render_follow_button';
$user = get_current_user_id();
$post_policy = wp_kses_allowed_html( 'post' );
try {
	wp_set_current_user( 0 );
	$block = new WP_Block( array( 'blockName' => 'axismundi/follow-button', 'attrs' => array() ) );
	$html = $render( array(), '', $block );
	$raw = $GLOBALS[$namespace]['raw'];
	$assert( '' !== $raw && str_contains( $html, '<dialog ' ) && str_contains( $html, '<input ' ) && str_contains( $html, '<form ' ), 'Anonymous production branch renders the native follow form' );
	$assert( wp_kses( $raw, $GLOBALS[$namespace]['allowed'] ) === $raw && str_contains( $html, $raw ), 'Legitimate modal survives the output boundary byte for byte' );
	$assert( ! str_contains( $html, 'onerror=' ), 'Remote display-name markup is not executable' );
	foreach ( array( 'input', 'form', 'input-data', 'span-translate' ) as $mutation ) {
		$allowed = axismundi_act_follow_modal_allowed_html();
		if ( 'input-data' === $mutation ) { unset( $allowed['input']['data-*'] ); }
		elseif ( 'span-translate' === $mutation ) { unset( $allowed['span']['translate'] ); }
		else { unset( $allowed[$mutation] ); }
		$GLOBALS[$namespace]['allowed'] = $allowed;
		$mutant = $render( array(), '', $block );
		$assert( ! str_contains( $mutant, $GLOBALS[$namespace]['raw'] ), 'CAUGHT allowlist mutation: ' . $mutation );
	}
	$GLOBALS[$namespace]['allowed'] = axismundi_act_follow_modal_allowed_html();
	$GLOBALS[$namespace]['attack'] = '<script>alert(1)</script><input onfocus="alert(2)" formaction="https://evil.test"><form action="https://evil.test" onsubmit="alert(3)"></form><a href="javascript:alert(4)">bad</a>';
	$guarded = $render( array(), '', $block );
	$assert( ! preg_match( '/<script\b|\sonfocus=|\sonsubmit=|\sformaction=|\saction=|javascript:/i', $guarded ), 'Output boundary strips scripts, event handlers, form destinations and unsafe protocols' );
	$assert( $post_policy === wp_kses_allowed_html( 'post' ), 'Post-content policy is unchanged' );
} finally { wp_set_current_user( $user ); unset( $GLOBALS[$namespace] ); }
echo "== {$checks} checks, 0 failed ==\n";
