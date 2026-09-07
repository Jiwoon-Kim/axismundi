<?php
/**
 * Server render bridge for the Question block.
 *
 * @package AxismundiObjectProjections
 */

defined( 'ABSPATH' ) || exit;

// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Renderer escapes its own fragments.
echo axismundi_op_render_question_block();
