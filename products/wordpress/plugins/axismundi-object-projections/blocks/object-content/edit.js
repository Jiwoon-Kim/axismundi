/**
 * axismundi/object-content editor registration (no build step).
 *
 * Only `edit`/`save` are declared here. Attributes, supports, and the rest of the
 * metadata come from block.json, which WordPress bootstraps into the editor for
 * us; re-declaring them in JavaScript would override that server definition.
 */
( function ( blocks, blockEditor, components, element, i18n ) {
	var el = element.createElement;
	var __ = i18n.__;
	var InspectorControls = blockEditor.InspectorControls;
	var PanelBody = components.PanelBody;
	var ToggleControl = components.ToggleControl;

	blocks.registerBlockType( 'axismundi/object-content', {
		edit: function ( props ) {
			var attributes = props.attributes || {};
			var hideInFeed = Boolean( attributes.hideInFeed );

			var inspector = el(
				InspectorControls,
				null,
				el(
					PanelBody,
					{ title: __( 'Settings', 'axismundi-object-projections' ) },
					el( ToggleControl, {
						__nextHasNoMarginBottom: true,
						label: __( 'Hide in timelines', 'axismundi-object-projections' ),
						checked: hideInFeed,
						help: __(
							'Timelines show the summary instead, and the body stays on the Object page. Ignored when an Object has no summary — a Note is only its body, so a card is never left empty.',
							'axismundi-object-projections'
						),
						onChange: function ( value ) { props.setAttributes( { hideInFeed: Boolean( value ) } ); }
					} )
				)
			);

			var body = el(
				'div',
				blockEditor.useBlockProps( { className: 'wp-block-post-content axismundi-object__content' } ),
				el( 'p', null, __( 'This is the Object Content block. It displays the body of the ActivityStreams Object currently being rendered.', 'axismundi-object-projections' ) ),
				el( 'p', null, __( 'That object may be a short Note, a long-form Article, a Question, or another federated Object with paragraphs, media, quotations, polls, and other structured content.', 'axismundi-object-projections' ) ),
				el( 'p', null, __( 'The authored sensitive-content gate is preserved when this block renders an Object on the front end.', 'axismundi-object-projections' ) ),
				hideInFeed
					? el(
						'p',
						null,
						el( 'em', null, __( 'Hidden in timelines: only Objects that carry a summary hide their body there.', 'axismundi-object-projections' ) )
					)
					: null
			);

			return el( element.Fragment, null, inspector, body );
		},
		save: function () { return null; },
	} );
} )( window.wp.blocks, window.wp.blockEditor, window.wp.components, window.wp.element, window.wp.i18n );
