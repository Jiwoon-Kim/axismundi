<?php
/**
 * Block registration.
 *
 * The style handle is registered before the block so that `block.json` can name it. Versioned by
 * `filemtime` rather than by the plugin version: a fixed version string becomes the `ver=` query,
 * and an edited stylesheet then keeps serving from cache with nothing to say it is stale.
 *
 * @package AxismundiCalendar
 */

defined( 'ABSPATH' ) || exit;

/**
 * Register the calendar block and its stylesheet.
 *
 * @return void
 */
function axismundi_cal_register_blocks() : void {
	$dir   = dirname( __DIR__ ) . '/blocks/calendar';
	$style = $dir . '/style.css';
	if ( ! file_exists( $dir . '/block.json' ) ) {
		return;
	}

	if ( file_exists( $style ) ) {
		wp_register_style(
			'axismundi-calendar-grid',
			plugins_url( 'blocks/calendar/style.css', dirname( __DIR__ ) . '/axismundi-calendar.php' ),
			array(),
			(string) filemtime( $style )
		);
	}

	register_block_type( $dir );
}
add_action( 'init', 'axismundi_cal_register_blocks' );
