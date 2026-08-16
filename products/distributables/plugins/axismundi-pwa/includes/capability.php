<?php
/**
 * What this site can actually do, asked before anybody offers it.
 *
 * Two capabilities and not one, because they fail separately and a product offering push has to
 * know which:
 *
 *   subscribe  a browser can register a device here, and the site can hold it
 *   deliver    something on this site can encrypt and post a message to a push service
 *
 * The second is not built. Web Push delivery needs VAPID signing and payload encryption, and until
 * that exists and is proven to reach a device, `deliver` reports false -- so no settings screen
 * anywhere offers a switch that would silently do nothing. That failure has a name in this
 * codebase: a preference that exists is not a delivery that happens.
 *
 * `pwa` is a hard dependency, declared in the plugin header and checked again at runtime. A site
 * may have exactly one service worker, and that plugin exists to let several products compose one
 * rather than fight over it -- so registering a second one here as a fallback would recreate the
 * exact problem it solves. With it absent, this reports false and offers nothing.
 *
 * @package AxismundiPwa
 */

defined( 'ABSPATH' ) || exit;

/** The provider release whose service worker API this was written against. */
const AXISMUNDI_PWA_PROVIDER_MINIMUM = '0.8.2';

/**
 * Whether the service worker provider is present and speaks the API this uses.
 *
 * Checked by the function that is actually called as well as by the version, because a constant
 * proves a file loaded while the function proves the seam exists.
 *
 * @return bool
 */
function axismundi_pwa_has_provider() : bool {
	return defined( 'PWA_VERSION' )
		&& version_compare( (string) PWA_VERSION, AXISMUNDI_PWA_PROVIDER_MINIMUM, '>=' )
		&& function_exists( 'wp_register_service_worker_script' );
}

/**
 * What this site can do about push, and why not when it cannot.
 *
 * Returned as a shape rather than a boolean so a caller can say something true to a person: "your
 * browser can be registered but this site cannot send yet" is a different sentence from "the
 * service worker plugin is missing", and both are better than a switch that does nothing.
 *
 * @return array{subscribe:bool,deliver:bool,reason:string}
 */
function axismundi_pwa_capability() : array {
	if ( ! axismundi_pwa_has_provider() ) {
		return array(
			'subscribe' => false,
			'deliver'   => false,
			'reason'    => 'provider_missing',
		);
	}
	if ( ! axismundi_pwa_ready() ) {
		return array(
			'subscribe' => false,
			'deliver'   => false,
			'reason'    => 'not_installed',
		);
	}
	if ( ! axismundi_pwa_has_keys() ) {
		// A browser will not subscribe without an application server key to bind the subscription to.
		return array(
			'subscribe' => false,
			'deliver'   => false,
			'reason'    => 'no_keys',
		);
	}
	return array(
		'subscribe' => true,
		// Deliberately false, and the whole point of reporting it. Nothing here encrypts and posts a
		// Web Push message yet, so nothing should be advertising push as a way of being reached.
		'deliver'   => false,
		'reason'    => 'no_sender',
	);
}

/**
 * Whether push is a thing this site can actually do to somebody.
 *
 * The one predicate other products should ask. Notifications uses it to decide whether push is
 * offered at all, which is what keeps a preference from existing before the thing it prefers.
 *
 * @return bool
 */
function axismundi_pwa_can_deliver_push() : bool {
	$capability = axismundi_pwa_capability();
	return $capability['subscribe'] && $capability['deliver'];
}
