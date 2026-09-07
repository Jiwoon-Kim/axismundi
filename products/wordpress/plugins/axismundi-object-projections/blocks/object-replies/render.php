<?php
/**
 * Object Replies server render.
 *
 * @package AxismundiObjectProjections
 */

defined( 'ABSPATH' ) || exit;

// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- The renderer escapes its own body and wrapper.
echo axismundi_op_render_object_replies_block( $attributes ?? array() );
