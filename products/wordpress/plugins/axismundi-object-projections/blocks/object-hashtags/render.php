<?php
/**
 * Object Hashtags server render.
 *
 * @package AxismundiObjectProjections
 */

defined( 'ABSPATH' ) || exit;

// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- The renderer escapes every chip and its wrapper.
echo axismundi_op_render_object_hashtags_block( $attributes );
