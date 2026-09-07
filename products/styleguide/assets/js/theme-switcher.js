/**
 * Theme switcher — static adapter for the axismundi-theme-switcher block.
 *
 * The plugin's behaviour is a WordPress Interactivity API store with a
 * server-rendered first paint. None of that transfers, and none of it needs to:
 * what the two implementations share is a contract, not code.
 *
 *   html[data-theme]   "auto" | "light" | "dark", always present, never removed
 *   persistence        the choice survives a reload
 *   bootstrap          applied before first paint, so nothing flashes
 *   announcement       window "axismundi-theme-scheme-change", detail.mode
 *   cycle order        auto -> light -> dark -> auto
 *
 * `auto` is a written value rather than the absence of the attribute. The
 * theme's dark CSS treats a missing attribute and `auto` identically, so the
 * rendering is the same either way - but removing it loses the difference
 * between "chose auto" and "never chose", which is what persistence is about.
 *
 * Storage is localStorage here where the plugin uses a cookie. That is not a
 * shortcut: the plugin's comment says the bootstrap is deliberately client-side
 * so a full-page cache cannot bake one visitor's colour mode into shared HTML.
 * A static site is that problem in its strongest form, and localStorage answers
 * it without sending anything to a server that has no use for it.
 *
 * One controller, two surfaces. The cycle button advances through three values,
 * so it carries no aria-pressed - the attribute is binary and would have to lie
 * about one of the three. Its accessible name announces the current mode
 * instead, and is rewritten on every change. The group's segments are a genuine
 * set of three toggles, so they use aria-pressed as intended. Both surfaces
 * listen to the same event, so a page showing both keeps them in step.
 */
(function () {
	'use strict';

	var STORAGE_KEY = 'axismundi_theme';
	var EVENT = 'axismundi-theme-scheme-change';

	var MODES = {
		auto: { icon: 'contrast', label: 'Auto' },
		light: { icon: 'light_mode', label: 'Light' },
		dark: { icon: 'dark_mode', label: 'Dark' },
	};
	var ORDER = ['auto', 'light', 'dark'];

	function normalize(value) {
		return ORDER.indexOf(value) === -1 ? 'auto' : value;
	}

	/**
	 * The state. Knows nothing about buttons.
	 */
	var ThemeController = {
		modes: MODES,
		order: ORDER,

		current: function () {
			return normalize(document.documentElement.dataset.theme);
		},

		/**
		 * Write the mode, persist it, and say so. Storage can throw - private
		 * windows, disabled site data - and that must not stop the mode being
		 * applied for this page.
		 */
		set: function (mode) {
			var next = normalize(mode);
			document.documentElement.dataset.theme = next;
			try {
				window.localStorage.setItem(STORAGE_KEY, next);
			} catch (e) {
				/* the choice still applies, it just will not survive a reload */
			}
			window.dispatchEvent(
				new CustomEvent(EVENT, { detail: { mode: next } })
			);
			return next;
		},

		cycle: function () {
			var index = ORDER.indexOf(this.current());
			return this.set(ORDER[(index + 1) % ORDER.length]);
		},

		onChange: function (handler) {
			window.addEventListener(EVENT, function (event) {
				handler(event.detail.mode);
			});
		},
	};

	/**
	 * Cycle surface: one icon button, three destinations.
	 */
	function bindCycle(root) {
		var button = root.querySelector('[data-ax-ts-cycle]');
		if (!button) {
			return;
		}
		var icon = button.querySelector('.material-symbols-outlined');
		var label = button.querySelector('[data-ax-ts-label]');

		function render(mode) {
			var m = MODES[mode];
			button.dataset.themeScheme = mode;
			button.setAttribute(
				'aria-label',
				'Color scheme: ' + m.label + '. Activate to cycle.'
			);
			if (icon) {
				icon.textContent = m.icon;
			}
			if (label) {
				label.textContent = m.label;
			}
		}

		button.addEventListener('click', function () {
			ThemeController.cycle();
		});
		ThemeController.onChange(render);
		render(ThemeController.current());
	}

	/**
	 * Group surface: three segments, one pressed.
	 */
	function bindGroup(root) {
		var buttons = root.querySelectorAll('[data-ax-ts-mode]');
		if (!buttons.length) {
			return;
		}

		function render(mode) {
			Array.prototype.forEach.call(buttons, function (button) {
				button.setAttribute(
					'aria-pressed',
					button.dataset.axTsMode === mode ? 'true' : 'false'
				);
			});
		}

		Array.prototype.forEach.call(buttons, function (button) {
			button.addEventListener('click', function () {
				ThemeController.set(button.dataset.axTsMode);
			});
		});
		ThemeController.onChange(render);
		render(ThemeController.current());
	}

	function init() {
		Array.prototype.forEach.call(
			document.querySelectorAll('[data-ax-ts]'),
			function (root) {
				bindCycle(root);
				bindGroup(root);
			}
		);
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', init);
	} else {
		init();
	}

	window.AxismundiThemeController = ThemeController;
})();
