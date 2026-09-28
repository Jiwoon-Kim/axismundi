<?php
defined( 'ABSPATH' ) || exit;

// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Renderer escapes all dynamic values.
echo axismundi_op_kses_block_html( axismundi_op_render_object_title_block( $attributes ) );