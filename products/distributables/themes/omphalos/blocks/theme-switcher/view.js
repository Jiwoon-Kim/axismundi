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

const MODES = {
	auto: { icon: 'contrast', label: 'Auto' },
	light: { icon: 'light_mode', label: 'Light' },
	dark: { icon: 'dark_mode', label: 'Dark' },
};
const VALID = Object.keys( MODES );
const COOKIE = 'omphalos_theme';

const normalize = ( value ) => ( VALID.includes( value ) ? value : 'auto' );

const writeCookie = ( mode ) => {
	document.cookie = `${ COOKIE }=${ mode }; path=/; max-age=31536000; SameSite=Lax`;
};

const applyScheme = ( mode ) => {
	const next = normalize( mode );
	document.documentElement.dataset.theme = next;
	state.currentScheme = next;
	writeCookie( next );
};

const { state } = store( 'omphalos/theme-switcher', {
	state: {
		currentScheme: normalize( document.documentElement.dataset.theme ),
		get isActive() {
			const { mode } = getContext();
			return normalize( mode ) === state.currentScheme;
		},
		get currentIcon() {
			return MODES[ state.currentScheme ].icon;
		},
		get currentLabel() {
			return MODES[ state.currentScheme ].label;
		},
		get cycleAriaLabel() {
			return `Color scheme: ${ state.currentLabel }. Activate to cycle.`;
		},
	},
	actions: {
		setScheme() {
			applyScheme( getContext().mode );
		},
		cycleScheme() {
			const index = VALID.indexOf( state.currentScheme );
			applyScheme( VALID[ ( index + 1 ) % VALID.length ] );
		},
	},
} );
