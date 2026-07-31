<?php
/**
 * Community Card server render.
 *
 * @package AxismundiForum
 */

defined( 'ABSPATH' ) || exit;

// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- The renderer escapes its own output.
echo axismundi_forum_render_community_card_block( $attributes ?? array() );
