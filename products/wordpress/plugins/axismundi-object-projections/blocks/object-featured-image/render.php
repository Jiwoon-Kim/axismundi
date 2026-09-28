<?php
/**
 * Object Featured Image server render.
 *
 * @package AxismundiObjectProjections
 */

defined( 'ABSPATH' ) || exit;

echo wp_kses( axismundi_op_render_object_featured_image_block( $attributes, $content, $block ), axismundi_op_allowed_block_html() );