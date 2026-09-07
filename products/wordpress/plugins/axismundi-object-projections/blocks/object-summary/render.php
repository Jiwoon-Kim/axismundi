<?php
defined( 'ABSPATH' ) || exit;

// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Renderer escapes the summary and wrapper attributes.
echo axismundi_op_render_object_summary_block( $attributes );
