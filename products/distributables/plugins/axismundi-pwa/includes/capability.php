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
 * They fail for different reasons and are worth different sentences. A site with no keys configured
 * cannot do either; a site with keys but no library can take subscriptions and reach none of them.
 * Reporting one boolean would leave a settings screen offering a switch that silently does nothing,
 * which has a name in this codebase: a preference that exists is not a delivery that happens.
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
		/*
		 * A browser will not subscribe without an application server key to bind the subscription to,
		 * and the signing half lives in the deployment's secrets rather than here -- so a site that has
		 * not configured them says so instead of generating a pair to make the message go away.
		 */
		return array(
			'subscribe' => false,
			'deliver'   => false,
			'reason'    => 'no_keys',
		);
	}
	if ( ! axismundi_pwa_has_library() ) {
		// Subscriptions can still be taken; nothing can be sent to them until the cryptography is here.
		return array(
			'subscribe' => true,
			'deliver'   => false,
			'reason'    => 'no_sender',
		);
	}
	return array(
		'subscribe' => true,
		'deliver'   => true,
		'reason'    => '',
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
