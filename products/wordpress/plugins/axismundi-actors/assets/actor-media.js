/**
 * Actor avatar / header picker (Phase 4b). Opens the core Media modal, writes the
 * chosen attachment id into the field's hidden input, and previews it. Remove only
 * clears the reference (the attachment is never deleted).
 */
( function ( $ ) {
	'use strict';

	$( document ).on( 'click', '.ax-actor-media-select', function ( event ) {
		event.preventDefault();
		var field = $( this ).closest( '.ax-actor-media-field' );
		var frame = wp.media( {
			title: field.data( 'role' ) === 'header' ? 'Select header image' : 'Select avatar',
			library: { type: 'image' },
			button: { text: 'Use this image' },
			multiple: false,
		} );
		frame.on( 'select', function () {
			var attachment = frame.state().get( 'selection' ).first().toJSON();
			var url = ( attachment.sizes && attachment.sizes.thumbnail ) ? attachment.sizes.thumbnail.url : attachment.url;
			field.find( 'input[type=hidden]' ).val( attachment.id );
			field.find( '.ax-actor-media-preview' ).html(
				$( '<img>', { src: url, alt: '', css: { maxWidth: '150px', height: 'auto' } } )
			);
		} );
		frame.open();
	} );

	$( document ).on( 'click', '.ax-actor-media-remove', function ( event ) {
		event.preventDefault();
		var field = $( this ).closest( '.ax-actor-media-field' );
		field.find( 'input[type=hidden]' ).val( '0' );
		field.find( '.ax-actor-media-preview' ).empty();
	} );
}( jQuery ) );
