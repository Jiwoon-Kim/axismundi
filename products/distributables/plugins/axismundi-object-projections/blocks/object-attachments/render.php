<?php
/**
 * Object Attachments server render — the Object sibling of Core's Gallery.
 *
 * @package AxismundiObjectProjections
 */

defined( 'ABSPATH' ) || exit;

// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- The renderer escapes every part it builds.
echo axismundi_op_render_object_attachments_block( $attributes );
