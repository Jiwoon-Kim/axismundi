/**
 * Axismundi Pilot — color-scheme.js
 *
 * Interactivity API store for the 3-state (light / dark / auto) theme switcher.
 *
 * State is initialised server-side via wp_interactivity_state() and stored
 * client-side as a cookie (and user meta for logged-in users).
 *
 * Namespace: axismundi-pilot/color-scheme
 */

import { store, getContext } from '@wordpress/interactivity';

/**
 * Resolve the effective data-theme value for a given colorScheme.
 *
 * 'auto' defers to the OS preference and returns 'auto' (CSS media query
 * in tokens.sys.dark.css handles the actual colour change).
 *
 * @param {string} colorScheme  'light' | 'dark' | 'auto'
 * @returns {string}
 */
function resolveTheme( colorScheme ) {
	return colorScheme; // data-theme="light|dark|auto" — tokens.sys.dark.css handles auto
}

/**
 * Persist the chosen scheme to a cookie and, if logged in, to user meta.
 *
 * @param {string} mode   'light' | 'dark' | 'auto'
 * @param {object} state  Interactivity API shared state
 */
/** One year, in seconds — keeps the anonymous visitor's choice across sessions. */
const COOKIE_MAX_AGE = 31536000;

function persist( mode, state ) {
	const name   = state.cookieName;
	const path   = state.cookiePath   || '/';
	const domain = state.cookieDomain ? `; domain=${ state.cookieDomain }` : '';

	// max-age makes this a persistent (not session) cookie so a logged-out
	// visitor's choice survives a browser restart and SSR can read it back.
	// SameSite=Lax matches WordPress's own cookie defaults.
	document.cookie = `${ name }=${ mode }; path=${ path }${ domain }; max-age=${ COOKIE_MAX_AGE }; SameSite=Lax`;

	if ( state.userId > 0 && typeof wp !== 'undefined' && wp.apiFetch ) {
		wp.apiFetch( {
			path:   `/wp/v2/users/${ state.userId }`,
			method: 'POST',
			data:   { meta: { [ name ]: mode } },
		} );
	}
}

const { state } = store( 'axismundi-pilot/color-scheme', {
	state: {
		/**
		 * Whether this button's mode matches the active scheme.
		 * Uses data-wp-context={"mode":"light|dark|auto"} per button.
		 */
		get isActive() {
			const { mode } = getContext();
			return state.colorScheme === mode;
		},
	},

	actions: {
		/** Called by each button via data-wp-on--click. */
		setScheme() {
			const { mode } = getContext();
			state.colorScheme = mode;
			persist( mode, state );
		},
	},

	callbacks: {
		/**
		 * Fires once on init and whenever colorScheme changes (data-wp-watch).
		 * Writes data-theme to <html> so the MD3 token cascade takes effect.
		 */
		updateTheme() {
			document.documentElement.setAttribute(
				'data-theme',
				resolveTheme( state.colorScheme )
			);
		},
	},
} );

// Note: when colorScheme is 'auto' the OS preference is resolved entirely in
// CSS via the `@media (prefers-color-scheme: dark)` block in tokens.sys.dark.css,
// and aria-checked (state.isActive) does not depend on the OS preference, so no
// JS listener on `prefersDark` change is required here.
