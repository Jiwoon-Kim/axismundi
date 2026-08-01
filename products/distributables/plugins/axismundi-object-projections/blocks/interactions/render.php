<?php
/**
 * Interactions server render.
 *
 * Plural because it holds `axismundi/interaction` blocks, the way Core's Buttons holds Button:
 * the container is the row, the child is the control. It was `object-actions` before that child
 * existed, and `object-interactions` before that — the earlier rename was about pairing "actions"
 * with a future read-only `object-stats`, and this name leaves that pairing intact.
 *
 * The row stays in this plugin rather than moving beside the control it holds. It is layout, it
 * works with whatever a template puts inside it, and an Object card has to keep its shape on a
 * site where the product owning those controls is not installed.
 *
 * @package AxismundiObjectProjections
 */

defined( 'ABSPATH' ) || exit;

// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Nested blocks render their own escaped output.
echo axismundi_op_render_object_interactions_block( $attributes, $content );
