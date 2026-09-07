<?php
/**
 * Server render bridge for the quote-context block.
 *
 * @package AxismundiObjectProjections
 */

defined( 'ABSPATH' ) || exit;

// Block metadata render templates are included inside WordPress' output buffer.
// Echoing, rather than returning, is what supplies the dynamic block markup.
// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Renderer escapes its own fragments.
echo axismundi_op_render_quote_context_block( $attributes );
