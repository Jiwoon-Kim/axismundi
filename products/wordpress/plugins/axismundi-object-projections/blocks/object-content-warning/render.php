<?php
/**
 * Object Content Warning server render.
 *
 * @package AxismundiObjectProjections
 */

defined( 'ABSPATH' ) || exit;

echo wp_kses( axismundi_op_render_object_content_warning_block( $attributes, $content ), axismundi_op_allowed_block_html() );