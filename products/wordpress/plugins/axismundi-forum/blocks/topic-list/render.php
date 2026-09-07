<?php
defined( 'ABSPATH' ) || exit;

// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- renderer escapes its own output.
echo axismundi_forum_render_topic_list_block( $attributes ?? array(), $content ?? '' );
