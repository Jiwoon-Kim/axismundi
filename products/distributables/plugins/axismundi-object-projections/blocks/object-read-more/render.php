<?php
/** Object Read More server render. */

defined( 'ABSPATH' ) || exit;

// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- renderer escapes its own output.
echo axismundi_op_render_object_read_more_block( $attributes ?? array() );
