/**
 * Shared Note block-editor helpers.
 *
 * No build step: plain runtime globals only (wp.*). One responsibility per file.
 */
( function ( wp ) {
	'use strict';

	window.axismundiNote = window.axismundiNote || {};

	/** Resolve PluginDocumentSettingPanel across WordPress versions. */
	window.axismundiNote.documentPanel = function () {
		if ( wp.editor && wp.editor.PluginDocumentSettingPanel ) {
			return wp.editor.PluginDocumentSettingPanel;
		}
		if ( wp.editPost && wp.editPost.PluginDocumentSettingPanel ) {
			return wp.editPost.PluginDocumentSettingPanel;
		}
		return null;
	};

	/** One trimmed non-empty entry per line. */
	window.axismundiNote.linesToList = function ( text ) {
		return String( text || '' )
			.split( /[\r\n]+/ )
			.map( function ( line ) {
				return line.trim();
			} )
			.filter( Boolean );
	};
} )( window.wp );
