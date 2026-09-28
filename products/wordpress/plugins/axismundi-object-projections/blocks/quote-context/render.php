<?php
/**
 * Server render bridge for the quote-context block.
 *
 * @package AxismundiObjectProjections
 */

defined( 'ABSPATH' ) || exit;

// Block metadata render templates are included inside WordPress' output buffer.
// Echoing, rather than returning, is what supplies the dynamic block markup.
echo wp_kses( axismundi_op_render_quote_context_block( $attributes ), axismundi_op_allowed_block_html() );