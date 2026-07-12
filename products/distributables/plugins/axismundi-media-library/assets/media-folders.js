/**
 * Minimal media-modal folder filter. Uses the core AttachmentFilters model so
 * folder selection participates in the normal query lifecycle.
 */
( function ( wp, $, config ) {
	'use strict';

	if ( ! wp || ! wp.media || ! wp.media.view || ! config ) {
		return;
	}

	var Browser = wp.media.view.AttachmentsBrowser;
	var originalCreateToolbar = Browser && Browser.prototype.createToolbar;
	if ( ! originalCreateToolbar || originalCreateToolbar.axMediaFolders ) {
		return;
	}

	var FolderFilter = wp.media.view.AttachmentFilters.extend( {
		id: 'ax-media-folder-filter',

		createFilters: function () {
			var filters = {
				all: {
					text: config.all,
					props: { ax_media_folder: -1 },
					priority: 10
				},
				unfiled: {
					text: config.unfiled,
					props: { ax_media_folder: 0 },
					priority: 20
				}
			};
			( config.folders || [] ).forEach( function ( folder, index ) {
				filters[ 'folder-' + folder.id ] = {
					text: folder.label,
					props: { ax_media_folder: parseInt( folder.id, 10 ) },
					priority: 30 + index
				};
			} );
			this.filters = filters;
		},

		change: function () {
			wp.media.view.AttachmentFilters.prototype.change.apply( this, arguments );
		}
	} );

	Browser.prototype.createToolbar = function () {
		originalCreateToolbar.apply( this, arguments );
		this.toolbar.set( 'axMediaFolderLabel', new wp.media.view.Label( {
			value: config.label,
			attributes: { 'for': 'ax-media-folder-filter' },
			priority: -70
		} ).render() );
		this.toolbar.set( 'axMediaFolder', new FolderFilter( {
			controller: this.controller,
			model: this.collection.props,
			priority: -70
		} ).render() );
	};
	Browser.prototype.createToolbar.axMediaFolders = true;

} )( window.wp, window.jQuery, window.axMediaFolders );
