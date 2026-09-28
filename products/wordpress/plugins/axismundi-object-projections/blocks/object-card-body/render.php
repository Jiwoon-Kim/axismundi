<?php
/** Object Card Body server render. */

defined( 'ABSPATH' ) || exit;

echo wp_kses( axismundi_op_render_object_card_body_block(), axismundi_op_allowed_block_html() );