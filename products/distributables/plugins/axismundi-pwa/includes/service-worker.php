<?php
/**
 * The site's one service worker, with our handlers composed into it.
 *
 * Registered through the provider rather than served separately, because a site has exactly one
 * service worker and the provider exists so several products can share it. A second one registered
 * here would recreate the fight it was written to prevent.
 *
 * What the handlers do is deliberately small, and the reason is the payload. A push message crosses
 * a push service and can land on a lock screen, and the endpoint that carries it is effectively a
 * capability URL -- so nothing goes in it but a delivery id and a category. A private invitation's
 * title, a guest list, a comment body: none of that travels. The notification says something has
 * arrived, and the opened, authenticated app fetches what it actually was.
 *
 * @package AxismundiPwa
 */

defined( 'ABSPATH' ) || exit;

/**
 * Compose our push handlers into the front-end service worker.
 *
 * @return void
 */
function axismundi_pwa_register_service_worker_script() : void {
	if ( ! axismundi_pwa_has_provider() ) {
		return;
	}
	wp_register_service_worker_script(
		'axismundi-push',
		array(
			'src' => 'axismundi_pwa_service_worker_script',
		)
	);
}
add_action( 'wp_front_service_worker', 'axismundi_pwa_register_service_worker_script' );

/**
 * The JavaScript that goes into the composed worker.
 *
 * Written as a callback rather than a file so the routes it needs are the site's real ones rather
 * than a guess made in a static asset.
 *
 * @return string
 */
function axismundi_pwa_service_worker_script() : string {
	$inbox = wp_json_encode( admin_url( 'index.php?page=axismundi-notifications' ) );
	$fetch = wp_json_encode( rest_url( 'axismundi/v1/notifications/' ) );

	return <<<JS
/* Axismundi push handlers. */

self.addEventListener( 'push', function ( event ) {
	/*
	 * The message carries an id and a category and nothing else, so this cannot say what happened --
	 * which is the point. What it can do is say that something has, and let the app answer the rest
	 * once somebody has opened it and is authenticated.
	 */
	var payload = {};
	try {
		payload = event.data ? event.data.json() : {};
	} catch ( error ) {
		payload = {};
	}
	var title = payload.category ? 'New ' + payload.category + ' notification' : 'New notification';
	event.waitUntil(
		self.registration.showNotification( title, {
			body: 'Open to read it.',
			tag: payload.delivery ? 'axismundi-' + payload.delivery : 'axismundi',
			data: { delivery: payload.delivery || 0, inbox: {$inbox}, fetch: {$fetch} },
			renotify: false
		} )
	);
} );

self.addEventListener( 'notificationclick', function ( event ) {
	event.notification.close();
	var target = ( event.notification.data && event.notification.data.inbox ) || '/';
	/*
	 * Focus a window that is already open rather than stacking another one, and never act on the
	 * notification itself: accepting an invitation from here would be a command taken without the
	 * server re-checking who is asking, which is exactly what it must not become.
	 */
	event.waitUntil(
		self.clients.matchAll( { type: 'window', includeUncontrolled: true } ).then( function ( windows ) {
			for ( var i = 0; i < windows.length; i++ ) {
				if ( windows[ i ].url.indexOf( target ) !== -1 && 'focus' in windows[ i ] ) {
					return windows[ i ].focus();
				}
			}
			return self.clients.openWindow ? self.clients.openWindow( target ) : undefined;
		} )
	);
} );

self.addEventListener( 'pushsubscriptionchange', function ( event ) {
	/*
	 * A push service rotating an endpoint. Nothing is re-registered from in here: the page does that
	 * when somebody next opens it, with a session behind it. Re-subscribing from a worker would mean
	 * a device registering itself with no one signed in to say whose it is.
	 */
	event.waitUntil( Promise.resolve() );
} );
JS;
}
