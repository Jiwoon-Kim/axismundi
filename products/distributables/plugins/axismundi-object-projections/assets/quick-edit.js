( function ( $, inlineEditPost ) {
	'use strict';

	const originalEdit = inlineEditPost.edit;
	inlineEditPost.edit = function ( id ) {
		originalEdit.apply( this, arguments );
		const postId = typeof id === 'object' ? parseInt( this.getId( id ), 10 ) : parseInt( id, 10 );
		const row = $( '#post-' + postId );
		const state = row.find( '.axismundi-op-federation-state' );
		const editRow = $( '#edit-' + postId );
		editRow.find( '[name="axismundi_op_sensitive"]' ).prop( 'checked', state.data( 'sensitive' ) === 1 );
		editRow.find( '[name="axismundi_op_content_warning"]' ).val( state.attr( 'data-warning' ) || '' );
		editRow.find( '[name="axismundi_op_quote_policy"]' ).val( state.attr( 'data-quote-policy' ) || '' );
	};
}( jQuery, inlineEditPost ) );
