/**
 * axismundi/announce-button editor registration (no build step).
 *
 * The preview borrows Core's Button markup and the theme's Text button style so
 * an author sees the control they will actually get. The count is rendered, not
 * edited: it comes from the Activity ledger at render time.
 */
( function ( blocks, blockEditor, components, element, i18n ) {
	'use strict';
	var el = element.createElement;
	var __ = i18n.__;
	blocks.registerBlockType( 'axismundi/announce-button', {
		edit: function ( props ) {
			var attributes = props.attributes || {};
			var setAttributes = props.setAttributes;
			var children = [ el( 'span', { className: 'material-symbols-outlined', 'aria-hidden': 'true', key: 'icon' }, 'sync' ) ];
			if ( attributes.showLabel ) {
				children.push( el( 'span', { className: 'axismundi-announce-button__label', key: 'label' }, __( 'Repost', 'axismundi-activities' ) ) );
			}
			if ( false !== attributes.showCount ) {
				children.push( el( 'span', { className: 'axismundi-announce-button__count', key: 'count' }, '0' ) );
			}
			return el(
				element.Fragment,
				{},
				el(
					blockEditor.InspectorControls,
					{},
					el(
						components.PanelBody,
						{ title: __( 'Repost button', 'axismundi-activities' ) },
						el( components.ToggleControl, {
							label: __( 'Show text label', 'axismundi-activities' ),
							help: __( 'The icon already names the action; the label is optional.', 'axismundi-activities' ),
							checked: !! attributes.showLabel,
							onChange: function ( value ) { setAttributes( { showLabel: value } ); },
							__nextHasNoMarginBottom: true
						} ),
						el( components.ToggleControl, {
							label: __( 'Show count', 'axismundi-activities' ),
							checked: false !== attributes.showCount,
							onChange: function ( value ) { setAttributes( { showCount: value } ); },
							__nextHasNoMarginBottom: true
						} )
					)
				),
				el(
					'div',
					blockEditor.useBlockProps( { className: 'wp-block-button is-style-text' } ),
					el(
						'span',
						{ className: 'wp-block-button__link wp-element-button axismundi-announce-button__button axismundi-interaction-button__editor-preview', 'aria-label': __( 'Repost or quote', 'axismundi-activities' ) },
						children
					)
				)
			);
		},
		save: function () { return null; }
	} );
} )( window.wp.blocks, window.wp.blockEditor, window.wp.components, window.wp.element, window.wp.i18n );
