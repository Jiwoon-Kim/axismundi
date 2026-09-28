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

// Block context, not a global flag: inner blocks are rendered before their parent's
// callback runs, so anything the wrapper set at render time would arrive too late here.
$axismundi_op_content_delegated = ! empty( $block->context['axismundi/objectDisclosure'] );

echo wp_kses( axismundi_op_render_object_content_block( $attributes, $axismundi_op_content_delegated ), axismundi_op_allowed_block_html() );