/**
 * Theme Switcher — editor registration + static preview (no build / vanilla).
 *
 * Phase 2: the editor canvas shows the segmented control with "auto" active and
 * NO behaviour (the front-end Interactivity view module lands in Phase 3). save()
 * returns null — this is a dynamic block rendered by render.php on the front end.
 */
( function ( blocks, blockEditor, element ) {
	var el = element.createElement;
	var useBlockProps = blockEditor.useBlockProps;

	var MODES = [
		{ mode: 'auto', icon: 'brightness_auto', label: 'Auto' },
		{ mode: 'light', icon: 'light_mode', label: 'Light' },
		{ mode: 'dark', icon: 'dark_mode', label: 'Dark' },
	];

	blocks.registerBlockType( 'omphalos/theme-switcher', {
		edit: function () {
			var blockProps = useBlockProps( {
				role: 'group',
				'aria-label': 'Color scheme',
			} );
			return el(
				'div',
				blockProps,
				MODES.map( function ( m ) {
					return el(
						'button',
						{
							key: m.mode,
							type: 'button',
							className: 'omphalos-theme-switcher__button',
							'data-theme-mode': m.mode,
							'aria-pressed': m.mode === 'auto' ? 'true' : 'false',
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
