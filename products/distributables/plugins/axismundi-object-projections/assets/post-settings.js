( function ( wp ) {
	'use strict';

	const el = wp.element.createElement;
	const Panel = wp.editPost && wp.editPost.PluginDocumentSettingPanel;
	if ( ! Panel ) {
		return;
	}

	function FederationSettings() {
		const meta = wp.data.useSelect(
			( select ) => select( 'core/editor' ).getEditedPostAttribute( 'meta' ) || {},
			[]
		);
		const { editPost } = wp.data.useDispatch( 'core/editor' );
		const sensitive = Boolean( meta._ax_op_sensitive );
		const warning = meta._ax_op_content_warning || '';
		const update = ( key, value ) => editPost( { meta: { ...meta, [ key ]: value } } );

		return el(
			Panel,
			{ name: 'axismundi-federation', title: wp.i18n.__( 'Federation', 'axismundi-object-projections' ) },
			el( wp.components.CheckboxControl, {
				label: wp.i18n.__( 'Sensitive content', 'axismundi-object-projections' ),
				checked: sensitive,
				onChange: ( value ) => update( '_ax_op_sensitive', value ),
			} ),
			el( wp.components.TextareaControl, {
				label: wp.i18n.__( 'Content warning', 'axismundi-object-projections' ),
				value: warning,
				maxLength: 500,
				onChange: ( value ) => update( '_ax_op_content_warning', value ),
			} )
		);
	}

	wp.plugins.registerPlugin( 'axismundi-post-settings', { render: FederationSettings } );
}( window.wp ) );
