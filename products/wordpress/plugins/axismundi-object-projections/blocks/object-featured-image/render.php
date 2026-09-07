<?php
/**
 * Object Featured Image server render.
 *
 * @package AxismundiObjectProjections
 */

defined( 'ABSPATH' ) || exit;

// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- The renderer escapes every part it builds.
echo axismundi_op_render_object_featured_image_block( $attributes, $content, $block );
