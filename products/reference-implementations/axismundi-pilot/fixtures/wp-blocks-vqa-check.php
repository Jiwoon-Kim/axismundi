<?php
/**
 * WP-CLI helper for the Axismundi Pilot VQA wall.
 *
 * Usage:
 *   wp eval-file wp-content/themes/axismundi-pilot/fixtures/wp-blocks-vqa-check.php
 */

$post = get_page_by_path( 'wp-blocks-vqa-wall', OBJECT, 'post' );

if ( ! $post ) {
	fwrite( STDERR, "missing post\n" );
	exit( 1 );
}

$names = array();

$walk = function ( array $blocks ) use ( &$walk, &$names ) : void {
	foreach ( $blocks as $block ) {
		$name = $block['blockName'] ?? null;

		if ( $name ) {
			$names[ $name ] = ( $names[ $name ] ?? 0 ) + 1;
		}

		if ( ! empty( $block['innerBlocks'] ) ) {
			$walk( $block['innerBlocks'] );
		}
	}
};

$walk( parse_blocks( $post->post_content ) );
ksort( $names );

echo 'block_count=' . array_sum( $names ) . PHP_EOL;
echo 'block_types=' . count( $names ) . PHP_EOL;

foreach ( $names as $name => $count ) {
	echo $name . ' ' . $count . PHP_EOL;
}

$rendered = apply_filters( 'the_content', $post->post_content );
echo 'render_len=' . strlen( $rendered ) . PHP_EOL;
echo 'has_recovery=' . ( false !== strpos( $rendered, 'block has encountered' ) ? 'yes' : 'no' ) . PHP_EOL;
