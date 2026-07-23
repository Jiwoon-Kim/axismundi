<?php
/**
 * Object Actions server render.
 *
 * Renamed from `object-interactions`: "actions" is what a viewer may do now,
 * which pairs with a future read-only `object-stats` for what has already
 * happened. The deprecated name stays registered so saved templates keep
 * rendering.
 *
 * @package AxismundiObjectProjections
 */

defined( 'ABSPATH' ) || exit;

// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Nested blocks render their own escaped output.
echo axismundi_op_render_object_interactions_block( $attributes, $content );
