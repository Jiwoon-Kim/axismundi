/**
 * Theme Switcher — editor registration (no build / vanilla).
 *
 * The editor preview writes the same axismundi_theme cookie as the front-end
 * Interactivity store. The editor canvas bridge copies that cookie onto the
 * iframe <html data-theme>, so the preview can be used to check light/dark/auto
 * without adding a second persistence channel. save() returns null — this is a
 * dynamic block rendered by render.php on the front end.
 */
( function ( blocks, blockEditor, element, components ) {
	var el = element.createElement;
	var useState = element.useState;
	var useBlockProps = blockEditor.useBlockProps;
	var COOKIE = 'axismundi_theme';

	var MODES = [
		{ mode: 'auto', icon: 'contrast', label: 'Auto' },
		{ mode: 'light', icon: 'light_mode', label: 'Light' },
		{ mode: 'dark', icon: 'dark_mode', label: 'Dark' },
	];

	function normalize( value ) {
		return MODES.some( function ( m ) { return m.mode === value; } ) ? value : 'auto';
	}

	function readCookie() {
		var match = document.cookie.match( new RegExp( '(?:^|;\\s*)' + COOKIE + '=(auto|light|dark)' ) );
		return normalize( match && match[ 1 ] );
	}

	function writeCookie( mode ) {
		document.cookie = COOKIE + '=' + normalize( mode ) + '; path=/; max-age=31536000; SameSite=Lax';
	}

	function modeData( mode ) {
		return MODES.filter( function ( m ) { return m.mode === normalize( mode ); } )[ 0 ] || MODES[ 0 ];
	}

	blocks.registerBlockType( 'axismundi/theme-switcher', {
		// Tint the inserter/toolbar icon with the brand primary so this
		// theme-owned control reads distinctly from generic core blocks. (Icon
		// shape unchanged — block.json's admin-appearance dashicon, just coloured.
		// A literal hex is required here: the editor chrome has no theme tokens.)
		icon: { src: 'admin-appearance', foreground: '#6750A4' },
		edit: function ( props ) {
			var currentState = useState( readCookie );
			var current = currentState[ 0 ];
			var setCurrent = currentState[ 1 ];
			// See render.php: `component` is an attribute because icon and group are
			// different controls, not two looks. An unset value falls back to the
			// pre-0.1.7 class so existing content keeps rendering as it did.
			var className = ( props.attributes && props.attributes.className ) || '';
			var component = ( props.attributes && props.attributes.component ) || '';
			if ( ! component ) {
				component = ( ' ' + className + ' ' ).indexOf( ' is-style-theme-cycle ' ) !== -1 ? 'icon' : 'group';
			}
			var isCycle = 'icon' === component;
			var currentMode = modeData( current );
			var blockProps = useBlockProps( {
				role: 'group',
				'aria-label': 'Color scheme',
				'data-component': component,
			} );

			var inspector = el(
				blockEditor.InspectorControls,
				{ key: 'inspector' },
				el(
					components.PanelBody,
					{ title: 'Settings' },
					el( components.SelectControl, {
						label: 'Component',
						value: component,
						options: [
							{ label: 'Connected button group', value: 'group' },
							{ label: 'Icon button', value: 'icon' },
						],
						help: isCycle
							? 'One button that cycles through Auto, Light and Dark.'
							: 'Three buttons, one per mode.',
						onChange: function ( value ) {
							props.setAttributes( { component: value } );
						},
						__next40pxDefaultSize: true,
						__nextHasNoMarginBottom: true,
					} )
				)
			);

			function applyMode( nextMode, event ) {
				var next = normalize( nextMode );
				writeCookie( next );
				setCurrent( next );
				event.currentTarget.ownerDocument.documentElement.dataset.theme = next;
				window.dispatchEvent(
					new CustomEvent( 'axismundi-theme-scheme-change', {
						detail: { mode: next },
					} )
				);
			}

			if ( isCycle ) {
				return el(
					element.Fragment,
					null,
					inspector,
					el(
						'div',
						blockProps,
						el(
							'button',
							{
								type: 'button',
								className: 'axismundi-theme-switcher__button axismundi-theme-switcher__cycle',
								'data-theme-cycle': 'true',
								'aria-label': 'Color scheme: ' + currentMode.label + '. Activate to cycle.',
								onClick: function ( event ) {
									var index = MODES.map( function ( m ) { return m.mode; } ).indexOf( normalize( current ) );
									applyMode( MODES[ ( index + 1 ) % MODES.length ].mode, event );
								},
							},
							el( 'span', { className: 'material-symbols-outlined', 'aria-hidden': 'true' }, currentMode.icon ),
							el( 'span', { className: 'screen-reader-text' }, currentMode.label )
						)
					)
				);
			}

			return el(
				element.Fragment,
				null,
				inspector,
				el(
					'div',
					blockProps,
					MODES.map( function ( m ) {
						return el(
							'button',
							{
								key: m.mode,
								type: 'button',
								className: 'axismundi-theme-switcher__button wp-element-button',
								'data-theme-mode': m.mode,
								'aria-pressed': m.mode === current ? 'true' : 'false',
								onClick: function ( event ) {
									applyMode( m.mode, event );
								},
							},
							el( 'span', { className: 'material-symbols-outlined', 'aria-hidden': 'true' }, m.icon ),
							el( 'span', { className: 'axismundi-theme-switcher__label' }, m.label )
						);
					} )
				)
			);
		},
		save: function () {
			return null;
		},
	} );
} )( window.wp.blocks, window.wp.blockEditor, window.wp.element, window.wp.components );
