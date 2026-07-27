<?php
/**
 * Object Content Warning server render.
 *
 * @package AxismundiObjectProjections
 */

defined( 'ABSPATH' ) || exit;

// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- The renderer escapes the label; inner blocks are already rendered by Core.
echo axismundi_op_render_object_content_warning_block( $attributes, $content );
