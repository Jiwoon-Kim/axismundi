/**
 * Consent-state preview for axismundi/quote-context.
 *
 * The selected variant belongs only to the Site Editor preview. The dynamic
 * renderer always derives availability from the live quote relation.
 */
( function ( blocks, blockEditor, components, element, i18n ) {
	'use strict';
	var el = element.createElement;
	var __ = i18n.__;
	var InspectorControls = blockEditor.InspectorControls;
	var PanelBody = components.PanelBody;
	var SelectControl = components.SelectControl;

	function Author() {
		return el( 'div', { className: 'axismundi-object__quote-author' },
			el( 'span', { className: 'axismundi-object__quote-avatar', 'aria-hidden': true }, 'B' ),
			el( 'span', {},
				el( 'strong', {}, __( 'Bora Kim', 'axismundi-object-projections' ) ),
				el( 'span', { className: 'axismundi-object__quote-handle' }, '@bora@example.com' )
			)
		);
	}

	function Embed( variant ) {
		var article = 'article' === variant;
		return el( 'blockquote', { className: 'axismundi-object__quote axismundi-object__quote--embed' + ( article ? ' is-article' : '' ) },
			Author(),
			article
				? el( element.Fragment, {},
					el( 'h3', { className: 'axismundi-object__quote-title' }, __( 'A walkable city starts with its streets', 'axismundi-object-projections' ) ),
					el( 'p', { className: 'axismundi-object__quote-excerpt' }, __( 'A compact Article preview keeps its title, summary, and origin distinct from a Note.', 'axismundi-object-projections' ) ),
					el( 'a', { href: '#', onClick: function ( event ) { event.preventDefault(); } }, __( 'Read article', 'axismundi-object-projections' ) )
				)
				: el( 'p', { className: 'axismundi-object__quote-excerpt' }, __( 'A direct quoted Note uses the compact embedded-content surface.', 'axismundi-object-projections' ) )
		);
	}

	function Placeholder( unavailable ) {
		return el( 'aside', { className: 'axismundi-object__quote axismundi-object__quote--placeholder' },
			el( 'span', { className: 'material-symbols-outlined', 'aria-hidden': true }, unavailable ? 'link_off' : 'schedule' ),
			el( 'div', {},
				el( 'strong', {}, unavailable ? __( 'Quoted object unavailable', 'axismundi-object-projections' ) : __( 'Quote approval pending', 'axismundi-object-projections' ) ),
				el( 'p', {}, unavailable ? __( 'The original object cannot be previewed here.', 'axismundi-object-projections' ) : __( 'The quoted preview will appear once approval is available.', 'axismundi-object-projections' ) )
			)
		);
	}

	function Reference() {
		return el( 'aside', { className: 'axismundi-object__quote axismundi-object__quote--reference' },
			el( 'span', { className: 'material-symbols-outlined', 'aria-hidden': true }, 'format_quote' ),
			el( 'a', { href: '#', onClick: function ( event ) { event.preventDefault(); } }, '“' + __( 'Earlier quoted Article', 'axismundi-object-projections' ) + '” — @carol@example.org' )
		);
	}

	blocks.registerBlockType( 'axismundi/quote-context', {
		edit: function ( props ) {
			var variant = props.attributes.previewVariant || 'note';
			var preview = 'article' === variant ? Embed( 'article' )
				: 'pending' === variant ? Placeholder( false )
				: 'unavailable' === variant ? Placeholder( true )
				: 'reference' === variant ? Reference()
				: Embed( 'note' );
			return el( element.Fragment, {},
				el( InspectorControls, {}, el( PanelBody, { title: __( 'Preview', 'axismundi-object-projections' ) },
					el( SelectControl, {
						label: __( 'Quote preview', 'axismundi-object-projections' ),
						value: variant,
						options: [
							{ label: __( 'Embedded Note', 'axismundi-object-projections' ), value: 'note' },
							{ label: __( 'Embedded Article', 'axismundi-object-projections' ), value: 'article' },
							{ label: __( 'Approval pending', 'axismundi-object-projections' ), value: 'pending' },
							{ label: __( 'Unavailable', 'axismundi-object-projections' ), value: 'unavailable' },
							{ label: __( 'Nested reference', 'axismundi-object-projections' ), value: 'reference' }
						],
						onChange: function ( value ) { props.setAttributes( { previewVariant: value } ); }
					})
				)),
				el( 'div', blockEditor.useBlockProps( { className: 'axismundi-object__quote-context is-editor-preview' } ), preview )
			);
		},
		save: function () { return null; }
	} );
} )( window.wp.blocks, window.wp.blockEditor, window.wp.components, window.wp.element, window.wp.i18n );
