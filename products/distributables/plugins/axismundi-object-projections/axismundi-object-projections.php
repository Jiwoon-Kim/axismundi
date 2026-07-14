<?php
/**
 * Plugin Name:       Axismundi Object Projections
 * Plugin URI:        https://github.com/Jiwoon-Kim/axismundi/tree/main/products/distributables/plugins/axismundi-object-projections
 * Description:       Projects WordPress objects (posts, media, collections) into ActivityStreams JSON-LD through a transformer registry and a single renderer. Representation only — it owns no Activity store, inbox/outbox, signatures, or delivery.
 * Version:           0.0.3
 * Requires at least: 6.7
 * Requires PHP:      8.1
 * Author:            KIM JIWOON
 * Author URI:        https://designbusan.ai.kr
 * License:           GPL-3.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-3.0.html
 * Text Domain:       axismundi-object-projections
 *
 * @package AxismundiObjectProjections
 *
 * Phases 0–2 are implemented: contract, registry/renderer, standalone content
 * negotiation, and the Core Post → Article transformer. There is deliberately no
 * custom rewrite, table, REST route, Activity store, inbox/outbox, or delivery.
 */

defined( 'ABSPATH' ) || exit;

const AXISMUNDI_OP_VERSION = '0.0.3';

require_once __DIR__ . '/includes/registry.php';
require_once __DIR__ . '/includes/renderer.php';
require_once __DIR__ . '/includes/post-article.php';
require_once __DIR__ . '/includes/router.php';
