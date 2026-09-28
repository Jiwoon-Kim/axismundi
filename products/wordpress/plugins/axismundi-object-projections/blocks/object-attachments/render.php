<?php
/**
 * Object Attachments server render — the Object sibling of Core's Gallery.
 *
 * @package AxismundiObjectProjections
 */

defined( 'ABSPATH' ) || exit;

echo wp_kses( axismundi_op_render_object_attachments_block( $attributes ), axismundi_op_allowed_block_html() );