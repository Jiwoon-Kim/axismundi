( function ( wp ) {
	'use strict';

	const { InspectorControls } = wp.blockEditor;
	const { CheckboxControl, PanelBody } = wp.components;
	const { createHigherOrderComponent } = wp.compose;
	const { useSelect } = wp.data;
	const { createElement: el, Fragment } = wp.element;
	const { addFilter } = wp.hooks;
	const { __ } = wp.i18n;

	function SensitiveImageEdit( props ) {
		const attachmentId = Number( props.attributes.id || 0 );
		const attachment = useSelect(
			( select ) => attachmentId > 0 ? select( 'core' ).getMedia( attachmentId ) : null,
			[ attachmentId ]
		);
		const attachmentSensitive = Boolean( attachment && attachment.axismundiSensitive );
		const sensitive = attachmentSensitive || Boolean( props.attributes.axismundiSensitive );
		const help = attachmentSensitive
			? __( 'This image is marked sensitive in Media Library and cannot be cleared here.', 'axismundi-media-library' )
			: __( 'Applies a sensitive-content warning only where this image appears in this post.', 'axismundi-media-library' );

		return el(
			Fragment,
			null,
			el( props.BlockEdit, props ),
			el(
				InspectorControls,
				{ group: 'settings' },
				el(
					PanelBody,
					{ title: __( 'Content warning', 'axismundi-media-library' ), initialOpen: true },
					el( CheckboxControl, {
						label: __( 'Sensitive image', 'axismundi-media-library' ),
						checked: sensitive,
						disabled: attachmentSensitive,
						help,
						onChange: ( value ) => props.setAttributes( { axismundiSensitive: Boolean( value ) } ),
					} )
				)
			)
		);
	}

	const withSensitiveImageControls = createHigherOrderComponent(
		( BlockEdit ) => ( props ) => props.name === 'core/image'
			? el( SensitiveImageEdit, { ...props, BlockEdit } )
			: el( BlockEdit, props ),
		'withSensitiveImageControls'
	);

	addFilter( 'editor.BlockEdit', 'axismundi-media-library/sensitive-image-controls', withSensitiveImageControls );

}( window.wp ) );
