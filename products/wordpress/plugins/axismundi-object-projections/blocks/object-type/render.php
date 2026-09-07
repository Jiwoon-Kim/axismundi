<?php
/**
 * Object Type server render.
 *
 * @package AxismundiObjectProjections
 */

defined( 'ABSPATH' ) || exit;

// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Renderer escapes the type label and wrapper attributes.
echo axismundi_op_render_object_type_block( $attributes );
