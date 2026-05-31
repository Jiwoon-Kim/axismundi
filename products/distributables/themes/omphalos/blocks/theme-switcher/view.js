/**
 * omphalos/theme-switcher — front-end Interactivity store.
 *
 * Clicking a mode button writes <html data-theme="…"> (tokens.sys.dark.css +
 * foundation.css respond immediately: colours, color-scheme, dark shadows) and
 * persists the choice to the omphalos_theme cookie. The head script
 * (inc/theme-switcher.php) applies the persisted value before paint; this store
 * only handles user toggles and the live active state.
 *
 * `currentScheme` is REACTIVE state (a signal). The active binding reads it (not
 * the raw DOM dataset, which is not reactive), so aria-pressed updates the moment
 * a button is clicked. It is seeded from the head-script-set data-theme, which
 * also corrects the cache-stale SSR aria-pressed on hydration.
 *
 * `auto` is an explicit value (not "remove data-theme") — kept in both the DOM
 * and the cookie, matching the head script + token selectors.
 */
import { getContext, store } from '@wordpress/interactivity';

const VALID = [ 'auto', 'light', 'dark' ];
const COOKIE = 'omphalos_theme';

const normalize = ( value ) => ( VALID.includes( value ) ? value : 'auto' );

const writeCookie = ( mode ) => {
	document.cookie = `${ COOKIE }=${ mode }; path=/; max-age=31536000; SameSite=Lax`;
};

const { state } = store( 'omphalos/theme-switcher', {
	state: {
		currentScheme: normalize( document.documentElement.dataset.theme ),
		get isActive() {
			const { mode } = getContext();
			return normalize( mode ) === state.currentScheme;
		},
	},
	actions: {
		setScheme() {
			const next = normalize( getContext().mode );
			document.documentElement.dataset.theme = next;
			state.currentScheme = next;
			writeCookie( next );
		},
	},
} );
