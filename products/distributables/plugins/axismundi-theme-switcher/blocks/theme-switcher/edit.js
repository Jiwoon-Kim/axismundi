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
			// See render.php. `cycleButtonVisibility` says how far the control
			// compresses -- off, mobile, always -- rather than which component to
			// use, the same question core/navigation asks with `overlayMenu`. An
			// unrecognised value falls back to the pre-0.1.7 class.
			var className = ( props.attributes && props.attributes.className ) || '';
			var visibility = ( props.attributes && props.attributes.cycleButtonVisibility ) || '';
			if ( [ 'off', 'mobile', 'always' ].indexOf( visibility ) === -1 ) {
				visibility = ( ' ' + className + ' ' ).indexOf( ' is-style-theme-cycle ' ) !== -1 ? 'always' : 'off';
			}
			var hasGroup = 'always' !== visibility;
			var SIZES = [ 'xsmall', 'small', 'medium', 'large', 'xlarge' ];
			var size = ( props.attributes && props.attributes.size ) || 'small';
			if ( SIZES.indexOf( size ) === -1 ) {
				size = 'small';
			}
			// The canvas has no viewport to respond to, so `mobile` previews the
			// group -- what a desktop reader sees.
			var isCycle = ! hasGroup;
			// Group segments keep their label text when it is hidden; it becomes
			// screen-reader text so it is still each button's accessible name.
			var showLabels = ! props.attributes || undefined === props.attributes.showLabels
				? true
				: !! props.attributes.showLabels;
			var labelClass = showLabels
				? 'axismundi-theme-switcher__label'
				: 'axismundi-theme-switcher__label screen-reader-text';
			var currentMode = modeData( current );
			// Only the group has a label to show or hide, so only the group says so.
			var wrapperAttrs = {
				role: 'group',
				'aria-label': 'Color scheme',
				'data-cycle-visibility': visibility,
				'data-size': size,
			};
			if ( hasGroup ) {
				wrapperAttrs[ 'data-labels' ] = showLabels ? 'visible' : 'hidden';
			}
			var blockProps = useBlockProps( wrapperAttrs );

			var inspector = el(
				blockEditor.InspectorControls,
				{ key: 'inspector' },
				el(
					components.PanelBody,
					{ title: 'Settings' },
					el( components.SelectControl, {
						label: 'Cycle button visibility',
						value: visibility,
						options: [
							{ label: 'Off', value: 'off' },
							{ label: 'Mobile', value: 'mobile' },
							{ label: 'Always', value: 'always' },
						],
						help: {
							off: 'The button group at every viewport.',
							mobile: 'One cycling button on small screens, the group above them.',
							always: 'One button that cycles through Auto, Light and Dark.',
						}[ visibility ],
						onChange: function ( value ) {
							props.setAttributes( { cycleButtonVisibility: value } );
						},
						__next40pxDefaultSize: true,
						__nextHasNoMarginBottom: true,
					} ),
					el( components.SelectControl, {
						label: 'Size',
						value: size,
						options: [
							{ label: 'Extra small', value: 'xsmall' },
							{ label: 'Small', value: 'small' },
							{ label: 'Medium', value: 'medium' },
							{ label: 'Large', value: 'large' },
							{ label: 'Extra large', value: 'xlarge' },
						],
						// The label stays put and the help says what it currently
						// sizes, since which surfaces exist depends on the setting
						// above.
						help: {
							off: 'Sets the height of the button group.',
							mobile: 'Sets the height of the group and the cycle button.',
							always: 'Sets the height of the cycle button.',
						}[ visibility ],
						onChange: function ( value ) {
							props.setAttributes( { size: value } );
						},
						__next40pxDefaultSize: true,
						__nextHasNoMarginBottom: true,
					} ),
					// Only the group has visible labels to turn off; the cycle
					// button never shows one.
					hasGroup &&
						el( components.ToggleControl, {
							label: 'Show labels',
							checked: showLabels,
							help: showLabels
								? 'Each mode shows its name beside the icon.'
								: 'Icon only. The name is still read by screen readers.',
							onChange: function ( value ) {
								props.setAttributes( { showLabels: !! value } );
							},
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
					// Same structure render.php builds: the segments live in their
					// own element, which is what the connected rules are scoped to.
					el(
						'div',
						{ className: 'axismundi-theme-switcher__group' },
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
								el( 'span', { className: labelClass }, m.label )
							);
						} )
					)
				)
			);
		},
		save: function () {
			return null;
		},
	} );
} )( window.wp.blocks, window.wp.blockEditor, window.wp.element, window.wp.components );
