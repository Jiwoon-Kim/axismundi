/**
 * Theme Switcher — editor registration (no build / vanilla).
 *
 * The editor preview writes the same omphalos_theme cookie as the front-end
 * Interactivity store. The editor canvas bridge copies that cookie onto the
 * iframe <html data-theme>, so the preview can be used to check light/dark/auto
 * without adding a second persistence channel. save() returns null — this is a
 * dynamic block rendered by render.php on the front end.
 */
( function ( blocks, blockEditor, element ) {
	var el = element.createElement;
	var useState = element.useState;
	var useBlockProps = blockEditor.useBlockProps;
	var COOKIE = 'omphalos_theme';

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

	blocks.registerBlockType( 'omphalos/theme-switcher', {
		// Tint the inserter/toolbar icon with the brand primary so this
		// theme-owned control reads distinctly from generic core blocks. (Icon
		// shape unchanged — block.json's admin-appearance dashicon, just coloured.
		// A literal hex is required here: the editor chrome has no theme tokens.)
		icon: { src: 'admin-appearance', foreground: '#6750A4' },
		edit: function ( props ) {
			var currentState = useState( readCookie );
			var current = currentState[ 0 ];
			var setCurrent = currentState[ 1 ];
			var className = ( props.attributes && props.attributes.className ) || '';
			var isCycle = ( ' ' + className + ' ' ).indexOf( ' is-style-theme-cycle ' ) !== -1;
			var currentMode = modeData( current );
			var blockProps = useBlockProps( {
				role: 'group',
				'aria-label': 'Color scheme',
			} );

			function applyMode( nextMode, event ) {
				var next = normalize( nextMode );
				writeCookie( next );
				setCurrent( next );
				event.currentTarget.ownerDocument.documentElement.dataset.theme = next;
				window.dispatchEvent(
					new CustomEvent( 'omphalos-theme-scheme-change', {
						detail: { mode: next },
					} )
				);
			}

			if ( isCycle ) {
				return el(
					'div',
					blockProps,
					el(
						'button',
						{
							type: 'button',
							className: 'omphalos-theme-switcher__button omphalos-theme-switcher__cycle ax-icon-button is-standard has-state-layer t-theme-cycle',
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
				);
			}

			return el(
				'div',
				blockProps,
				MODES.map( function ( m ) {
					return el(
						'button',
						{
							key: m.mode,
							type: 'button',
							className: 'omphalos-theme-switcher__button wp-element-button',
							'data-theme-mode': m.mode,
							'aria-pressed': m.mode === current ? 'true' : 'false',
							onClick: function ( event ) {
								applyMode( m.mode, event );
							},
						},
						el( 'span', { className: 'material-symbols-outlined', 'aria-hidden': 'true' }, m.icon ),
						el( 'span', { className: 'omphalos-theme-switcher__label' }, m.label )
					);
				} )
			);
		},
		save: function () {
			return null;
		},
	} );
} )( window.wp.blocks, window.wp.blockEditor, window.wp.element );
