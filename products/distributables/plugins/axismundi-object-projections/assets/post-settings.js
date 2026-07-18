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
		const quotePolicy = meta._ax_op_quote_policy || '';
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
			} ),
			el( wp.components.SelectControl, {
				label: wp.i18n.__( 'Who can quote this post?', 'axismundi-object-projections' ),
				value: quotePolicy,
				options: [
					{ label: wp.i18n.__( 'Not specified', 'axismundi-object-projections' ), value: '' },
					{ label: wp.i18n.__( 'Anyone', 'axismundi-object-projections' ), value: 'anyone' },
					{ label: wp.i18n.__( 'Followers only', 'axismundi-object-projections' ), value: 'followers' },
					{ label: wp.i18n.__( 'Just me', 'axismundi-object-projections' ), value: 'me' },
				],
				onChange: ( value ) => update( '_ax_op_quote_policy', value ),
			} )
		);
	}

	wp.plugins.registerPlugin( 'axismundi-post-settings', { render: FederationSettings } );
}( window.wp ) );
