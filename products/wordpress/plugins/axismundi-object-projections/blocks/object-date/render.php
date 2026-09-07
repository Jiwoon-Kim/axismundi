<?php
/**
 * Object Date server render.
 *
 * @package AxismundiObjectProjections
 */

defined( 'ABSPATH' ) || exit;

// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Renderer escapes the date, link, and wrapper attributes.
echo axismundi_op_render_object_date_block( $attributes );
