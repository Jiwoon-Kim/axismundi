/**
 * Axismundi Pilot — color-scheme.js (pre-built)
 *
 * Hand-crafted script module output. Equivalent to the webpack --experimental-modules
 * build of resources/js/color-scheme.js.
 *
 * @wordpress/interactivity is an external module resolved by WordPress's importmap
 * (registered since WP 6.5). No bundling of that package is needed.
 *
 * To rebuild from source after editing resources/js/color-scheme.js:
 *   cd products/reference-implementations/axismundi-pilot
 *   npm install
 *   npm run build
 */

import { store, getContext } from "@wordpress/interactivity";

const prefersDark = window.matchMedia( "(prefers-color-scheme: dark)" );

function resolveTheme( colorScheme ) {
	return colorScheme;
}

function persist( mode, state ) {
	const name   = state.cookieName;
	const path   = state.cookiePath   || "/";
	const domain = state.cookieDomain ? "; domain=" + state.cookieDomain : "";

	document.cookie = name + "=" + mode + "; path=" + path + domain;

	if ( state.userId > 0 && typeof wp !== "undefined" && wp.apiFetch ) {
		wp.apiFetch( {
			path:   "/wp/v2/users/" + state.userId,
			method: "POST",
			data:   { meta: { [ name ]: mode } },
		} );
	}
}

const { state } = store( "axismundi-pilot/color-scheme", {
	state: {
		get isActive() {
			const { mode } = getContext();
			return state.colorScheme === mode;
		},
	},

	actions: {
		setScheme() {
			const { mode } = getContext();
			state.colorScheme = mode;
			persist( mode, state );
		},
	},

	callbacks: {
		updateTheme() {
			document.documentElement.setAttribute(
				"data-theme",
				resolveTheme( state.colorScheme )
			);
		},
	},
} );

prefersDark.addEventListener( "change", () => {
	if ( state.colorScheme === "auto" ) {
		// eslint-disable-next-line no-self-assign
		state.colorScheme = state.colorScheme;
	}
} );
