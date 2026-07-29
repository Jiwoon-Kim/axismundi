( function ( blocks, blockEditor, components, element, i18n ) {
	'use strict';
	var el = element.createElement;
	var __ = i18n.__;
	var InspectorControls = blockEditor.InspectorControls;
	var PanelBody = components.PanelBody;
	var RangeControl = components.RangeControl;

	blocks.registerBlockType( 'axismundi/forum-topic-list', {
		edit: function ( props ) {
			return el( element.Fragment, {},
				el( InspectorControls, {}, el( PanelBody, { title: __( 'Topics', 'axismundi-forum' ) },
					el( RangeControl, { label: __( 'Topics to show', 'axismundi-forum' ), value: props.attributes.perPage || 20, min: 1, max: 100, onChange: function ( value ) { props.setAttributes( { perPage: value } ); } } )
				) ),
				el( 'section', blockEditor.useBlockProps( { className: 'axismundi-forum-topic-list is-editor-preview' } ),
					el( 'h2', {}, __( 'Topics', 'axismundi-forum' ) ),
					el( 'ol', { className: 'axismundi-forum-topic-list__items' },
						el( 'li', {}, __( 'A Forum Topic appears here', 'axismundi-forum' ) ),
						el( 'li', {}, __( 'Topic context stays separate from activity', 'axismundi-forum' ) )
					)
				)
			);
		},
		save: function () { return null; }
	} );
} )( window.wp.blocks, window.wp.blockEditor, window.wp.components, window.wp.element, window.wp.i18n );
