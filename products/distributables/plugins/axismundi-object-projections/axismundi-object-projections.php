<?php
/**
 * Plugin Name:       Axismundi Object Projections
 * Plugin URI:        https://github.com/Jiwoon-Kim/axismundi/tree/main/products/distributables/plugins/axismundi-object-projections
 * Description:       Projects WordPress objects (posts, media, collections) into ActivityStreams JSON-LD through a transformer registry and a single renderer. Representation only — it owns no Activity store, inbox/outbox, signatures, or delivery.
 * Version:           0.0.1
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
 * Phase 0 (contract + scaffold) and Phase 1 (transformer registry + renderer) are
 * implemented. There is deliberately **no HTTP routing, no rewrite, no table, and no
 * REST route** yet: content negotiation on the existing WordPress URL is Phase 2, and
 * the Core Post → Article transformer is Phase 2/3 (docs/PHASES.md). This package owns
 * only the projection contract and the JSON-LD serialization of one object/collection.
 */

defined( 'ABSPATH' ) || exit;

const AXISMUNDI_OP_VERSION = '0.0.1';

require_once __DIR__ . '/includes/registry.php';
require_once __DIR__ . '/includes/renderer.php';
