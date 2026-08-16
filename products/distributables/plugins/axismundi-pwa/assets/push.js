/**
 * Turning push on for this browser.
 *
 * The permission prompt happens inside the click handler and nowhere else. A prompt fired on load
 * is what browsers penalise and what teaches people to press Block before reading the sentence --
 * and a person who has just pressed a button asking for notifications knows what the dialog is.
 *
 * Nothing here decides what gets sent. It registers a device and tells the site about it; whether
 * anything is worth waking that device for is a preference on the same page, and it is deliberately
 * not switched on from in here.
 */
( function () {
	var config = window.axismundiPwaPush;
	if ( ! config ) {
		return;
	}
	var root = document.getElementById( 'axismundi-pwa-push' );
	if ( ! root ) {
		return;
	}
	var status = root.querySelector( '.axismundi-pwa-push-status' );
	var button = root.querySelector( '.axismundi-pwa-push-toggle' );

	function show( state, message, action ) {
		root.setAttribute( 'data-state', state );
		status.textContent = message;
		if ( action ) {
			button.textContent = action;
			button.hidden = false;
			button.disabled = false;
		} else {
			button.hidden = true;
		}
	}

	function supported() {
		return 'serviceWorker' in navigator && 'PushManager' in window && 'Notification' in window;
	}

	/** base64url to the byte array the Push API wants for an application server key. */
	function decodeKey( key ) {
		var padded = ( key + '='.repeat( ( 4 - ( key.length % 4 ) ) % 4 ) ).replace( /-/g, '+' ).replace( /_/g, '/' );
		var raw = window.atob( padded );
		var bytes = new Uint8Array( raw.length );
		for ( var i = 0; i < raw.length; i++ ) {
			bytes[ i ] = raw.charCodeAt( i );
		}
		return bytes;
	}

	function send( method, body ) {
		return window.fetch( config.restUrl, {
			method: method,
			credentials: 'same-origin',
			headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': config.nonce },
			body: JSON.stringify( body )
		} ).then( function ( response ) {
			if ( ! response.ok ) {
				throw new Error( 'rest' );
			}
			return response.json();
		} );
	}

	/*
	 * The worker with scope over the whole site, registered explicitly rather than assumed. An admin
	 * page that let the browser pick would get the admin worker, which carries no push handlers --
	 * and the subscription would be perfectly valid and never show anybody anything.
	 */
	function worker() {
		return navigator.serviceWorker.register( config.serviceWorkerUrl, { scope: '/' } ).then( function () {
			return navigator.serviceWorker.ready;
		} );
	}

	function refresh() {
		if ( ! supported() ) {
			show( 'unsupported', config.strings.unsupported );
			return;
		}
		if ( 'denied' === Notification.permission ) {
			// Nothing this page can do: a blocked site cannot re-ask, and pretending otherwise with a
			// button that silently fails is worse than saying where the setting lives.
			show( 'denied', config.strings.denied );
			return;
		}
		navigator.serviceWorker.getRegistration( '/' ).then( function ( registration ) {
			if ( ! registration ) {
				show( 'off', config.strings.off, config.strings.enable );
				return;
			}
			registration.pushManager.getSubscription().then( function ( subscription ) {
				if ( subscription ) {
					show( 'on', config.strings.on, config.strings.disable );
				} else {
					show( 'off', config.strings.off, config.strings.enable );
				}
			} );
		} );
	}

	function enable() {
		// Inside the click, which is the whole rule: this is the only place the prompt may appear.
		return Notification.requestPermission().then( function ( permission ) {
			if ( 'granted' !== permission ) {
				refresh();
				return;
			}
			return worker().then( function ( registration ) {
				return registration.pushManager.subscribe( {
					userVisibleOnly: true,
					applicationServerKey: decodeKey( config.applicationServerKey )
				} );
			} ).then( function ( subscription ) {
				var json = subscription.toJSON();
				return send( 'POST', { endpoint: json.endpoint, keys: json.keys } );
			} ).then( function () {
				show( 'on', config.strings.on, config.strings.disable );
			} );
		} );
	}

	function disable() {
		return navigator.serviceWorker.getRegistration( '/' ).then( function ( registration ) {
			if ( ! registration ) {
				return null;
			}
			return registration.pushManager.getSubscription();
		} ).then( function ( subscription ) {
			if ( ! subscription ) {
				return null;
			}
			var endpoint = subscription.endpoint;
			/*
			 * The browser first, then the site. If the site forgot it and the browser kept it, a device
			 * would go on holding a live subscription nothing here knows about.
			 */
			return subscription.unsubscribe().then( function () {
				return send( 'DELETE', { endpoint: endpoint } );
			} );
		} ).then( function () {
			show( 'off', config.strings.off, config.strings.enable );
		} );
	}

	button.addEventListener( 'click', function () {
		var turningOn = 'on' !== root.getAttribute( 'data-state' );
		button.disabled = true;
		status.textContent = config.strings.working;
		var work = turningOn ? enable() : disable();
		work.catch( function () {
			show( 'off', config.strings.failed, config.strings.enable );
		} );
	} );

	refresh();
} )();
