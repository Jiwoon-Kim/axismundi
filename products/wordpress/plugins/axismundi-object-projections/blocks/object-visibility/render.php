<?php
/**
 * Object Visibility server render.
 *
 * @package AxismundiObjectProjections
 */

defined( 'ABSPATH' ) || exit;

// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Renderer escapes the glyph, label, and wrapper attributes.
echo axismundi_op_kses_block_html( axismundi_op_render_object_visibility_block( $attributes ) );