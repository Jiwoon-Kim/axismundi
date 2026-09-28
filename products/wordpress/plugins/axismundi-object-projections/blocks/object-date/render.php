<?php
/**
 * Object Date server render.
 *
 * @package AxismundiObjectProjections
 */

defined( 'ABSPATH' ) || exit;

echo wp_kses( axismundi_op_render_object_date_block( $attributes ), axismundi_op_allowed_block_html() );