<?php
/**
 * Server render bridge for the Question block.
 *
 * @package AxismundiObjectProjections
 */

defined( 'ABSPATH' ) || exit;

echo wp_kses( axismundi_op_render_question_block(), axismundi_op_allowed_block_html() );