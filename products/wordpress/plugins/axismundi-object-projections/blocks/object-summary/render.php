<?php
defined( 'ABSPATH' ) || exit;

echo wp_kses( axismundi_op_render_object_summary_block( $attributes ), axismundi_op_allowed_block_html() );