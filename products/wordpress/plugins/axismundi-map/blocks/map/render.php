<?php
/**
 * axismundi/map render template. The logic lives in axismundi_map_render_block()
 * so its variables are function-scoped; this template just forwards the block
 * attributes WordPress provides.
 *
 * @package AxismundiMap
 *
 * @var array $attributes Block attributes.
 */

defined( 'ABSPATH' ) || exit;

axismundi_map_render_block( is_array( $attributes ) ? $attributes : array() );
