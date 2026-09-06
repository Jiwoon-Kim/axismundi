<?php
/**
 * Omphalos — an Axismundi child theme used as a layout laboratory.
 *
 * Intentionally near-empty. The parent registers the fonts, the token layers,
 * the core block styles and the block style variations, and it addresses all of
 * them through get_template_directory(), so they keep resolving to the parent
 * while this child is active. Nothing here needs to re-enqueue any of it.
 *
 * WordPress loads this file before the parent's functions.php, so a hook added
 * here fires against a parent that has not registered anything yet. Anything
 * that needs to see the parent's registrations belongs on `after_setup_theme`
 * or later, not at the top level of this file.
 *
 * @package Omphalos
 */

defined( 'ABSPATH' ) || exit;
