<?php
/**
 * Supply the Axismundi product's software identity to the local NodeInfo document.
 *
 * Axismundi Actors owns the NodeInfo endpoints, the discovery document, the live
 * local-actor count, and the per-host remote NodeInfo cache. That ownership is
 * recorded in `axismundi-activitypub-bridge/docs/OWNERSHIP.md` for one reason --
 * "Avoid competing identity and discovery URLs" -- so nothing here publishes a
 * route or reads an Actors table. This file subscribes to a filter Actors already
 * declares for it.
 *
 * What Core owns is narrower than the document: the version of the deployed
 * Axismundi product. `nodeinfo.php` in Actors falls back to its own plugin version
 * with a comment saying a later plugin should set the real one, and until this file
 * existed nothing did -- so the site advertised the Actors plugin version to the
 * fediverse as its software version.
 *
 * `software.name` is restated rather than inherited so the filtered document does
 * not depend on the fallback staying spelled the same. Everything else in the
 * NodeInfo document stays Actors', and the capability facts stay with the plugins
 * that know them: the ActivityPub Bridge contributes `activitypub` to `protocols`
 * only once its transport is actually available, which is the pattern this follows.
 *
 * @package Axismundi
 */

defined( 'ABSPATH' ) || exit;

/**
 * Name the deployed Axismundi product in NodeInfo's `software` block.
 *
 * @param array<string,mixed> $software name / version / repository / homepage.
 * @return array<string,mixed>
 */
function axismundi_capstone_nodeinfo_software( $software ) : array {
	$software = is_array( $software ) ? $software : array();

	$software['name']       = 'axismundi';
	$software['version']    = AXISMUNDI_CAPSTONE_VERSION;
	$software['repository'] = 'https://github.com/Jiwoon-Kim/axismundi';

	return $software;
}
add_filter( 'axismundi_actors_nodeinfo_software', 'axismundi_capstone_nodeinfo_software' );
