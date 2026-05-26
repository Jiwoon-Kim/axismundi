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

/** Mirrors the OS preference — used only when colorScheme is 'auto'. */
const prefersDark = window.matchMedia( '(prefers-color-scheme: dark)' );

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
function persist( mode, state ) {
	const name   = state.cookieName;
	const path   = state.cookiePath   || '/';
	const domain = state.cookieDomain ? `; domain=${ state.cookieDomain }` : '';

	document.cookie = `${ name }=${ mode }; path=${ path }${ domain }`;

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

/**
 * When the user is in 'auto' mode, keep aria-checked in sync if the OS
 * preference changes (e.g. dusk/dawn system automatic switching).
 * The CSS media query already handles visual colour; this only updates ARIA.
 */
prefersDark.addEventListener( 'change', () => {
	if ( state.colorScheme === 'auto' ) {
		// Trigger a reactive update without changing the stored scheme.
		// Reassigning to itself nudges the Interactivity API to re-evaluate
		// derived state for any watchers.
		// eslint-disable-next-line no-self-assign
		state.colorScheme = state.colorScheme;
	}
} );
