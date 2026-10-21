<?php
/** Run with wp eval-file after WordPress has loaded. */

$checks = 0;
$failures = array();
$check = static function ( bool $ok, string $label ) use ( &$checks, &$failures ) : void {
	++$checks;
	if ( ! $ok ) {
		$failures[] = $label;
	}
};
$old_enabled = get_option( 'axismundi_stylebook_enabled', null );
$flushes = 0;
$count_flush = static function () use ( &$flushes ) : void { ++$flushes; };
add_action( 'generate_rewrite_rules', $count_flush );

try {
	update_option( 'axismundi_stylebook_enabled', false );
	axismundi_capstone_upgrade_rewrite_rules();
	$flushes = 0;
	axismundi_capstone_upgrade_rewrite_rules();
	axismundi_capstone_upgrade_rewrite_rules();
	$check( 0 === $flushes, 'unchanged requests do not flush' );
	$check( array() === axismundi_capstone_resolve_public_path( 'stylebook/components/buttons' ), 'disabled Stylebook is not resolved' );
	$check( 'home' === ( axismundi_capstone_resolve_public_path( 'social/' )['name'] ?? '' ), 'Social home remains' );
	$check( 'object' === ( axismundi_capstone_resolve_public_path( 'social/objects/example' )['name'] ?? '' ), 'Social object remains' );
	update_option( 'axismundi_stylebook_enabled', true );
	axismundi_capstone_add_rewrite_rule();
	axismundi_capstone_upgrade_rewrite_rules();
	$check( 1 === $flushes, 'enabling causes exactly one soft flush' );
	axismundi_capstone_upgrade_rewrite_rules();
	$check( 1 === $flushes, 'repeat enabled request does not flush' );
	foreach ( array( '', '/styles', '/styles/motion', '/layout/feed', '/layout/list-detail', '/layout/supporting_pane', '/layout/topology' ) as $suffix ) {
		$check( 'stylebook' === ( axismundi_capstone_resolve_public_path( 'stylebook' . $suffix )['name'] ?? '' ), 'Stylebook route ' . $suffix );
	}
	foreach ( array( 'buttons', 'icon-buttons', 'button-groups', 'split-buttons', 'cards', 'carousels', 'lists', 'tabs', 'badges', 'dividers', 'navigations', 'app-bars', 'surfaces', 'menus', 'fabs', 'tooltips', 'loading-indicators' ) as $component ) {
		$route = axismundi_capstone_resolve_public_path( 'stylebook/components/' . $component );
		$check( $component === ( $route['component'] ?? '' ), 'component dispatch ' . $component );
	}
	foreach ( array( 'social/stylebook', 'social/stylebook/components/buttons', 'objects/example', 'stylebook/components/unknown', 'stylebook/layout/unknown', 'stylebook/styles/unknown', 'stylebook/components/buttons/extra' ) as $path ) {
		$check( array() === axismundi_capstone_resolve_public_path( $path ), 'reject ' . $path );
	}
	update_option( 'axismundi_stylebook_enabled', false );
	axismundi_capstone_upgrade_rewrite_rules();
	$check( 2 === $flushes, 'disabling causes exactly one soft flush' );
	axismundi_capstone_upgrade_rewrite_rules();
	$check( 2 === $flushes, 'repeat disabled request does not flush' );
} finally {
	remove_action( 'generate_rewrite_rules', $count_flush );
	if ( null === $old_enabled ) {
		delete_option( 'axismundi_stylebook_enabled' );
	} else {
		update_option( 'axismundi_stylebook_enabled', $old_enabled );
	}
	axismundi_capstone_upgrade_rewrite_rules();
}
echo sprintf( "public-routes: %d checks, %d failed\n", $checks, count( $failures ) );
foreach ( $failures as $failure ) {
	echo "FAIL: $failure\n";
}
if ( $failures ) {
	WP_CLI::halt( 1 );
}
