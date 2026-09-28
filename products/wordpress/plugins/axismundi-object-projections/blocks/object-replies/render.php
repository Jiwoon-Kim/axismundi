<?php
/**
 * Object Replies server render.
 *
 * @package AxismundiObjectProjections
 */

defined( 'ABSPATH' ) || exit;

echo wp_kses( axismundi_op_render_object_replies_block( $attributes ?? array() ), axismundi_op_allowed_block_html() );