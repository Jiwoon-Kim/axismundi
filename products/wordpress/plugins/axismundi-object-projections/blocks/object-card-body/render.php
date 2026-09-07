<?php
/** Object Card Body server render. */

defined( 'ABSPATH' ) || exit;

// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- nested blocks render their own escaped output.
echo axismundi_op_render_object_card_body_block();
