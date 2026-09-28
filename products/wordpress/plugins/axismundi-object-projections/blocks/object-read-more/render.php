<?php
/** Object Read More server render. */

defined( 'ABSPATH' ) || exit;

echo wp_kses( axismundi_op_render_object_read_more_block( $attributes ?? array() ), axismundi_op_allowed_block_html() );