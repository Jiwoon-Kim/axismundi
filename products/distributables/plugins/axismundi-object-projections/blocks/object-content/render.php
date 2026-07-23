<?php
/**
 * Object Content server render.
 *
 * The first Object block migrated to a `block.json` directory. Declaring
 * metadata in one file lets WordPress bootstrap identical block definitions to
 * the editor, which is what makes Core-style supports safe here: the previous
 * inline registration had to hand-maintain a parallel editor copy.
 *
 * @package AxismundiObjectProjections
 */

defined( 'ABSPATH' ) || exit;

// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- The renderer escapes its own body and wrapper.
echo axismundi_op_render_object_content_block( $attributes );
