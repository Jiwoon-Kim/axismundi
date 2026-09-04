/**
 * Theme Switcher — editor registration (no build / vanilla).
 *
 * The editor preview writes the same axismundi_theme cookie as the front-end
 * Interactivity store. The editor canvas bridge copies that cookie onto the
 * iframe <html data-theme>, so the preview can be used to check light/dark/auto
 * without adding a second persistence channel. save() returns null — this is a
 * dynamic block rendered by render.php on the front end.
 */
( function ( blocks, blockEditor, element, components, compose ) {
	var el = element.createElement;
	var useState = element.useState;
	var useEffect = element.useEffect;
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

	/*
	 * One scheme, however many switchers are on the page.
	 *
	 * Each edit() is its own React component, so per-block state made every
	 * instance believe the cookie still said what it said when that instance
	 * mounted: clicking Light in a group left a cycle button beside it painted
	 * as unselected. The scheme is document state, not block state, so every
	 * instance subscribes to the same change event the front-end store and the
	 * editor bridge already broadcast. Nothing here writes it -- applyMode does,
	 * and the event comes straight back to all of us, the clicked block
	 * included.
	 */
	function useScheme() {
		var state = useState( readCookie );
		var scheme = state[ 0 ];
		var setScheme = state[ 1 ];

		useEffect( function () {
			function onChange( event ) {
				setScheme( normalize( event.detail && event.detail.mode ) );
			}

			// A cookie written by another tab, or before this block mounted.
			setScheme( readCookie() );
			window.addEventListener( 'axismundi-theme-scheme-change', onChange );
			return function () {
				window.removeEventListener( 'axismundi-theme-scheme-change', onChange );
			};
		}, [] );

		return scheme;
	}

	blocks.registerBlockType( 'axismundi/theme-switcher', {
		// Tint the inserter/toolbar icon with the brand primary so this
		// theme-owned control reads distinctly from generic core blocks. (Icon
		// shape unchanged — block.json's admin-appearance dashicon, just coloured.
		// A literal hex is required here: the editor chrome has no theme tokens.)
		icon: { src: 'admin-appearance', foreground: '#6750A4' },
		edit: function ( props ) {
			var current = useScheme();
			/*
			 * The panel's options menu opens beside the sidebar rather than over it,
			 * except on a narrow screen where there is nowhere to put it. Core
			 * computes this in useToolsPanelDropdownMenuProps(), which is private to
			 * the block library; the offset is that function's own arithmetic --
			 * sidebar 248 - button 24 - border 1 + padding 16 + spacing 20.
			 */
			var isNarrow = compose.useViewportMatch( 'medium', '<' );
			var dropdownMenuProps = isNarrow
				? {}
				: { popoverProps: { placement: 'left-start', offset: 259 } };
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
			var hasCycle = 'off' !== visibility;
			var SIZES = [ 'xsmall', 'small', 'medium', 'large', 'xlarge' ];
			var size = ( props.attributes && props.attributes.size ) || 'small';
			if ( SIZES.indexOf( size ) === -1 ) {
				size = 'small';
			}
			// Group segments keep their label text when it is hidden; it becomes
			// screen-reader text so it is still each button's accessible name.
			var showLabels = ! props.attributes || undefined === props.attributes.showLabels
				? true
				: !! props.attributes.showLabels;
			/*
			 * A plain tooltip only where a control shows no text of its own:
			 * the cycle button, always, and segments whose labels are off. A
			 * group showing its labels needs none, so the setting is not
			 * offered when that is the whole block.
			 */
			var showTooltips = ! props.attributes || undefined === props.attributes.showTooltips
				? true
				: !! props.attributes.showTooltips;
			var segmentTooltip = showTooltips && ! showLabels;
			var canTooltip = hasCycle || ( hasGroup && ! showLabels );
			// Material's Standard colour set, which exists for the Icon button and
			// not for the Button -- so it is a setting on the cycle button, not a
			// fourth block style that would colour the group too. See style.css.
			var standard = !! ( props.attributes && props.attributes.cycleButtonStandard );
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
			if ( hasCycle && standard ) {
				wrapperAttrs[ 'data-cycle-standard' ] = 'true';
			}
			/*
			 * The layout classes, written here rather than received.
			 *
			 * `layout` support reaches the editor through the inner-blocks
			 * wrapper -- it is built for container blocks, and the class names it
			 * computes are handed to `useInnerBlocksProps`. This block has no
			 * inner blocks, so nothing collected them and the canvas ignored
			 * Justification while the front end honoured it:
			 * `get_block_wrapper_attributes()` adds them there regardless.
			 *
			 * So the same names are put on directly. They are the ones the
			 * server emits, minus its per-container hash, and style.css maps the
			 * justification class to a value the way core/buttons' own
			 * stylesheet does -- which is also why the two agree now without
			 * depending on generated CSS that only exists on one side.
			 */
			var layout = ( props.attributes && props.attributes.layout ) || {};
			wrapperAttrs.className = [
				'is-layout-flex',
				'wp-block-axismundi-theme-switcher-is-layout-flex',
				layout.justifyContent
					? 'is-content-justification-' + layout.justifyContent
					: '',
			].filter( Boolean ).join( ' ' );

			var blockProps = useBlockProps( wrapperAttrs );

			/*
			 * A ToolsPanel, not a PanelBody: every control here is optional and has a
			 * default, which is what the panel's options menu and Reset all are for.
			 * Someone who has changed two things can put them back without having to
			 * remember what they were, and someone who never touches a control can
			 * hide it. core/navigation's Display panel is the same panel under the
			 * same name.
			 *
			 * The visibility control inside it is a ToggleGroupControl, mirroring the
			 * one core/navigation gives `overlayMenu` -- same three answers to the
			 * same question about how far a control compresses, so the same control.
			 *
			 * Size comes first. It applies whatever the visibility is, while
			 * visibility decides which surfaces a reader ever sees -- the narrower
			 * question belongs after the one that always applies. It also puts Size's
			 * help line, which names the surfaces currently sized, directly above the
			 * control that determines them.
			 */
			var ToolsPanel = components.__experimentalToolsPanel;
			var ToolsPanelItem = components.__experimentalToolsPanelItem;
			var ToggleGroupControl = components.__experimentalToggleGroupControl;
			var ToggleGroupControlOption = components.__experimentalToggleGroupControlOption;

			var inspector = el(
				blockEditor.InspectorControls,
				{ key: 'inspector' },
				el(
					ToolsPanel,
					{
						label: 'Display',
						panelId: props.clientId,
						dropdownMenuProps: dropdownMenuProps,
						resetAll: function () {
							props.setAttributes( {
								size: undefined,
								cycleButtonVisibility: 'off',
								showLabels: true,
								showTooltips: true,
								cycleButtonStandard: false,
							} );
						},
					},
					el(
						ToolsPanelItem,
						{
							label: 'Size',
							panelId: props.clientId,
							isShownByDefault: true,
							hasValue: function () {
								return size !== 'small';
							},
							// The attribute has a real default, so clearing it is the
							// reset -- nothing needs writing back.
							onDeselect: function () {
								props.setAttributes( { size: undefined } );
							},
						},
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
							// below.
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
						} )
					),
					el(
						ToolsPanelItem,
						{
							label: 'Cycle button visibility',
							panelId: props.clientId,
							isShownByDefault: true,
							hasValue: function () {
								return visibility !== 'off';
							},
							/*
							 * Written, not cleared. Clearing the attribute lets the
							 * pre-0.1.7 `is-style-theme-cycle` class resolve again, so
							 * content saved back then would reset to `always` -- the
							 * value being reset away from. `off` is the default, and it
							 * is what the control was showing.
							 */
							onDeselect: function () {
								props.setAttributes( { cycleButtonVisibility: 'off' } );
							},
						},
						el(
							ToggleGroupControl,
							{
								label: 'Cycle button visibility',
								'aria-label': 'Configure cycle button visibility',
								value: visibility,
								help: {
									off: 'The button group at every viewport.',
									mobile: 'One cycling button on small screens, the group above them.',
									always: 'One button that cycles through Auto, Light and Dark.',
								}[ visibility ],
								onChange: function ( value ) {
									props.setAttributes( { cycleButtonVisibility: value } );
								},
								isBlock: true,
								__next40pxDefaultSize: true,
								__nextHasNoMarginBottom: true,
							},
							el( ToggleGroupControlOption, { value: 'off', label: 'Off' } ),
							el( ToggleGroupControlOption, { value: 'mobile', label: 'Mobile' } ),
							el( ToggleGroupControlOption, { value: 'always', label: 'Always' } )
						)
					),
					// The treatment it replaces is the cycle button's, so the item
					// appears only where that button does -- `mobile` and `always`.
					hasCycle &&
						el(
							ToolsPanelItem,
							{
								label: 'Standard icon button',
								panelId: props.clientId,
								isShownByDefault: true,
								hasValue: function () {
									return standard;
								},
								onDeselect: function () {
									props.setAttributes( { cycleButtonStandard: false } );
								},
							},
							el( components.ToggleControl, {
								label: 'Standard icon button',
								checked: standard,
								help: standard
									? 'No container. The symbol alone carries the scheme.'
									: 'The cycle button takes the block\'s colour treatment.',
								onChange: function ( value ) {
									props.setAttributes( { cycleButtonStandard: !! value } );
								},
								__nextHasNoMarginBottom: true,
							} )
						),
					// Only the group has visible labels to turn off; the cycle button
					// never shows one, so with `always` the item is not offered at all.
					hasGroup &&
						el(
							ToolsPanelItem,
							{
								label: 'Show labels',
								panelId: props.clientId,
								isShownByDefault: true,
								hasValue: function () {
									return ! showLabels;
								},
								onDeselect: function () {
									props.setAttributes( { showLabels: true } );
								},
							},
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
						),
					// Last, because whether it has anywhere to appear depends on the
					// two settings above.
					canTooltip &&
						el(
							ToolsPanelItem,
							{
								label: 'Show tooltips',
								panelId: props.clientId,
								isShownByDefault: true,
								hasValue: function () {
									return ! showTooltips;
								},
								onDeselect: function () {
									props.setAttributes( { showTooltips: true } );
								},
							},
							el( components.ToggleControl, {
								label: 'Show tooltips',
								checked: showTooltips,
								help: showTooltips
									? 'Buttons with no visible name show it on hover.'
									: 'No tooltips. Screen readers still read the name.',
								onChange: function ( value ) {
									props.setAttributes( { showTooltips: !! value } );
								},
								__nextHasNoMarginBottom: true,
							} )
						)
				)
			);

			function applyMode( nextMode, event ) {
				var next = normalize( nextMode );
				writeCookie( next );
				event.currentTarget.ownerDocument.documentElement.dataset.theme = next;
				// Dispatched, not assigned: useScheme above listens, so this
				// block and every other one re-render from the same signal.
				window.dispatchEvent(
					new CustomEvent( 'axismundi-theme-scheme-change', {
						detail: { mode: next },
					} )
				);
			}

			/*
			 * Both surfaces, the way render.php builds them, and for the same
			 * reason: at `mobile` the plugin prints a media query from the theme's
			 * settings.viewport that shows one and hides the other, and it prints it
			 * into the editor as well (enqueue_block_assets). Rendering only the
			 * group here made the block vanish under 7.1's Mobile device preview --
			 * the canvas is an iframe that really is that width, so the query fired,
			 * hid the group, and there was no cycle button for it to show. The canvas
			 * does have a viewport to respond to; it just had nothing to respond
			 * with. Now the preview shows what a reader at that width sees.
			 */
			var cycleButton = el(
				'button',
				{
					type: 'button',
					className: 'axismundi-theme-switcher__button axismundi-theme-switcher__cycle',
					'data-theme-cycle': 'true',
					'data-ax-ts-tooltip': showTooltips ? 'true' : undefined,
					// See style.css: `auto` follows the system and reads as
					// unselected, an explicit light or dark as selected.
					'data-theme-scheme': normalize( current ),
					'aria-label': 'Color scheme: ' + currentMode.label + '. Activate to cycle.',
					onClick: function ( event ) {
						var index = MODES.map( function ( m ) { return m.mode; } ).indexOf( normalize( current ) );
						applyMode( MODES[ ( index + 1 ) % MODES.length ].mode, event );
					},
				},
				el( 'span', { className: 'material-symbols-outlined', 'aria-hidden': 'true' }, currentMode.icon ),
				el( 'span', { className: 'screen-reader-text' }, currentMode.label )
			);

			// The segments live in their own element, which is what the connected
			// rules are scoped to.
			var buttonGroup = el(
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
							'data-ax-ts-tooltip': segmentTooltip ? 'true' : undefined,
							'aria-pressed': m.mode === current ? 'true' : 'false',
							onClick: function ( event ) {
								applyMode( m.mode, event );
							},
						},
						el( 'span', { className: 'material-symbols-outlined', 'aria-hidden': 'true' }, m.icon ),
						el( 'span', { className: labelClass }, m.label )
					);
				} )
			);

			return el(
				element.Fragment,
				null,
				inspector,
				el(
					'div',
					blockProps,
					hasCycle && cycleButton,
					hasGroup && buttonGroup
				)
			);
		},
		save: function () {
			return null;
		},
	} );
} )( window.wp.blocks, window.wp.blockEditor, window.wp.element, window.wp.components, window.wp.compose );
