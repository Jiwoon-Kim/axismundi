/**
 * Media Library folder sidebar.
 *
 * Layout: the sidebar is injected as a flex sibling of #wpbody-content (FileBird's
 * approach) so it works in BOTH upload.php modes. Filtering differs by mode:
 *   - grid: drive the hidden toolbar folder dropdown, which re-queries wp.media.
 *   - list: the folder rows are real `?mode=list&ax_media_folder=ID` links; the
 *     server filters the list table (no wp.media on that screen).
 * The media picker modal keeps the toolbar dropdown (no #wpbody sidebar there).
 */
( function ( wp, $, config ) {
	'use strict';

	if ( ! config ) {
		return;
	}

	var modalFolder = 'all';
	var suppressFolderClickUntil = 0;
	var folderEditor = null;
	var folderContextMenu = null;
	var gridUploadFolder = 0;

	/* ---------------------------------------------------------------- *
	 * Toolbar folder dropdown — the grid/modal query mechanism.
	 * ---------------------------------------------------------------- */
	if ( wp && wp.media && wp.media.view && wp.media.view.AttachmentsBrowser
		&& ! wp.media.view.AttachmentsBrowser.prototype.createToolbar.axMediaFolders ) {
		var Browser = wp.media.view.AttachmentsBrowser;
		var originalCreateToolbar = Browser.prototype.createToolbar;
		var FolderFilter = wp.media.view.AttachmentFilters.extend( {
			id: 'ax-media-folder-filter',
			createFilters: function () {
				var filters = {
					all: { text: config.all, props: { ax_media_folder: 'all' }, priority: 10 },
					unfiled: { text: config.unfiled, props: { ax_media_folder: 'unfiled' }, priority: 20 }
				};
				( config.folders || [] ).forEach( function ( folder, index ) {
					filters[ 'folder-' + folder.id ] = {
						text: folder.label,
						props: { ax_media_folder: 'folder-' + folder.id },
						priority: 30 + index
					};
				} );
				this.filters = filters;
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

		var originalRender = Browser.prototype.render;
		if ( originalRender && ! originalRender.axMediaFolderTree ) {
			Browser.prototype.render = function () {
				var result = originalRender.apply( this, arguments );
				rememberAttachmentBrowser( this );
				// Inject the tree into the media-picker browser. Gated to non-upload
				// screens (the modal): upload.php renders its own #wpbody sidebar, and
				// the browser can be detached at render time so a .media-modal DOM
				// check is unreliable.
				if ( ! isUploadPage() ) {
					mountModalTree( this.el );
				}
				return result;
			};
			Browser.prototype.render.axMediaFolderTree = true;
		}

		var AttachmentLibrary = wp.media.view.Attachment.Library;
		if ( AttachmentLibrary && ! AttachmentLibrary.prototype.initialize.axMediaFolderDrag ) {
			var originalAttachmentInitialize = AttachmentLibrary.prototype.initialize;
			AttachmentLibrary.prototype.initialize = function () {
				originalAttachmentInitialize.apply( this, arguments );
				this.on( 'ready', function () {
					registerAttachmentDraggable( this.$el, browserForAttachment( this.$el ) );
				}, this );
			};
			AttachmentLibrary.prototype.initialize.axMediaFolderDrag = true;
		}
	}

	/* ---------------------------------------------------------------- *
	 * Page-level sidebar (upload.php grid + list only).
	 * ---------------------------------------------------------------- */
	var isList = 'list' === config.mode;

	function isUploadPage() {
		return document.body && document.body.classList.contains( 'upload-php' );
	}

	function el( tag, cls, text ) {
		var e = document.createElement( tag );
		if ( cls ) { e.className = cls; }
		if ( null != text ) { e.textContent = text; }
		return e;
	}

	function icon( name ) {
		var s = el( 'span', 'dashicons ' + name );
		s.setAttribute( 'aria-hidden', 'true' );
		return s;
	}

	function folderUrl( id ) {
		var u = new URL( config.listBaseUrl, window.location.origin );
		u.searchParams.set( 'mode', 'list' );
		if ( id > 0 ) {
			u.searchParams.set( 'ax_media_folder', String( id ) );
		} else if ( 0 === id ) {
			u.searchParams.set( 'ax_media_folder', '0' );
		} else {
			u.searchParams.delete( 'ax_media_folder' );
		}
		return u.toString();
	}

	/* ---------------------------------------------------------------- *
	 * Folder-tree disclosure state — a browser preference, not folder data.
	 * ---------------------------------------------------------------- */
	var collapsedFolderIds = null;

	function collapsedFolderStorageKey() {
		return 'axismundi-media-folders.collapsed.' + String( config.userId || 0 );
	}

	function collapsedFolders() {
		if ( collapsedFolderIds ) {
			return collapsedFolderIds;
		}
		try {
			var saved = JSON.parse( window.localStorage.getItem( collapsedFolderStorageKey() ) || '[]' );
			collapsedFolderIds = Array.isArray( saved ) ? saved.map( function ( id ) {
				return parseInt( id, 10 );
			} ).filter( function ( id ) {
				return ! isNaN( id ) && id > 0;
			} ) : [];
		} catch ( error ) {
			collapsedFolderIds = [];
		}
		return collapsedFolderIds;
	}

	function folderIsCollapsed( id ) {
		return -1 !== collapsedFolders().indexOf( id );
	}

	function setFolderCollapsed( id, collapsed ) {
		var folders = collapsedFolders();
		var index = folders.indexOf( id );
		if ( collapsed && -1 === index ) {
			folders.push( id );
		} else if ( ! collapsed && -1 !== index ) {
			folders.splice( index, 1 );
		}
		try {
			window.localStorage.setItem( collapsedFolderStorageKey(), JSON.stringify( folders ) );
		} catch ( error ) {
			// Storage is optional; this session still has the expanded state.
		}
	}

	function row( id, name, depth, hasChildren, isProtected, count, collapsed ) {
		var li = el( 'li', 'ax-media-folder-tree__item' );
		if ( id > 0 ) {
			li.setAttribute( 'data-folder', id );
		}
		li.style.setProperty( '--ax-media-folder-depth', depth );
		var line = el( 'div', 'ax-media-folder-tree__line' );

		if ( hasChildren ) {
			var toggle = el( 'button', 'ax-media-folder-toggle' );
			toggle.type = 'button';
			toggle.setAttribute( 'data-folder', id );
			toggle.setAttribute( 'aria-expanded', collapsed ? 'false' : 'true' );
			toggle.setAttribute( 'aria-label', name );
			toggle.appendChild( icon( collapsed ? 'dashicons-arrow-right-alt2' : 'dashicons-arrow-down-alt2' ) );
			line.appendChild( toggle );
		} else {
			var spacer = el( 'span', 'ax-media-folder-toggle-spacer' );
			spacer.setAttribute( 'aria-hidden', 'true' );
			line.appendChild( spacer );
		}
		var sel = isList ? el( 'a', 'ax-media-folder-select' ) : el( 'button', 'ax-media-folder-select' );
		// The row itself is the folder drag source in grid, list, and media-frame
		// trees. Disable the browser's native drag in every variant so jQuery UI
		// owns the gesture consistently.
		sel.setAttribute( 'draggable', 'false' );
		if ( isList ) {
			sel.href = folderUrl( id );
		} else {
			sel.type = 'button';
		}
		sel.setAttribute( 'data-folder', id );
		sel.appendChild( icon( -1 === id ? 'dashicons-images-alt2' : ( 0 === id ? 'dashicons-portfolio' : 'dashicons-category' ) ) );
		sel.appendChild( el( 'span', 'ax-media-folder-tree__name', name ) );
		if ( isProtected ) {
			sel.appendChild( icon( 'dashicons-lock' ) );
		}
		count = folderCount( count );
		if ( null !== count ) {
			sel.appendChild( el( 'span', 'ax-media-folder-tree__count', String( count ) ) );
		}

		line.appendChild( sel );
		if ( id > 0 ) {
			var actions = el( 'button', 'ax-media-folder-actions' );
			actions.type = 'button';
			actions.setAttribute( 'aria-label', config.folderActions + ': ' + name );
			actions.setAttribute( 'aria-haspopup', 'true' );
			actions.setAttribute( 'aria-expanded', 'false' );
			actions.appendChild( icon( 'dashicons-ellipsis' ) );
			line.appendChild( actions );
		}
		li.appendChild( line );
		return li;
	}

	// wp_localize_script() converts top-level scalar values to strings while
	// nested JSON values stay numeric. Treat both forms as the same count.
	function folderCount( value ) {
		var count = parseInt( value, 10 );
		return ! isNaN( count ) && count >= 0 ? count : null;
	}

	function setFolderCount( button, count ) {
		var label = button.querySelector( '.ax-media-folder-tree__count' );
		if ( ! label ) {
			label = el( 'span', 'ax-media-folder-tree__count' );
			button.appendChild( label );
		}
		label.textContent = String( count );
	}

	function updateFolderCounts( counts ) {
		if ( ! counts ) {
			return;
		}
		var unfiled = folderCount( counts.unfiled );
		var all = folderCount( counts.all );
		if ( null !== unfiled ) {
			config.unfiledCount = unfiled;
		}
		if ( null !== all ) {
			config.allCount = all;
		}
		if ( counts.folders ) {
			( config.folders || [] ).forEach( function ( folder ) {
				var count = counts.folders[ String( folder.id ) ];
				count = folderCount( count );
				if ( null !== count ) {
					folder.count = count;
				}
			} );
		}
		document.querySelectorAll( '.ax-media-folder-tree .ax-media-folder-select' ).forEach( function ( button ) {
			var id = parseInt( button.getAttribute( 'data-folder' ), 10 );
			if ( -1 === id && null !== all ) {
				setFolderCount( button, all );
			} else if ( 0 === id && null !== unfiled ) {
				setFolderCount( button, unfiled );
			} else if ( id > 0 && counts.folders && null !== folderCount( counts.folders[ String( id ) ] ) ) {
				setFolderCount( button, folderCount( counts.folders[ String( id ) ] ) );
			}
		} );
	}

	function branch( folder, byParent, depth ) {
		var children = byParent[ folder.id ] || [];
		var collapsed = children.length > 0 && folderIsCollapsed( parseInt( folder.id, 10 ) );
		var li = row(
			parseInt( folder.id, 10 ),
			folder.name,
			depth,
			children.length > 0,
			!! folder.protected,
			parseInt( folder.count, 10 ) || 0,
			collapsed
		);
		if ( children.length ) {
			var ul = el( 'ul', 'ax-media-folder-tree__children' );
			children.forEach( function ( child ) {
				ul.appendChild( branch( child, byParent, depth + 1 ) );
			} );
			ul.hidden = collapsed;
			li.appendChild( ul );
		}
		return li;
	}

	function mediaFrameFor( node ) {
		return node && node.closest ? node.closest( '.media-frame' ) : null;
	}

	function folderDropdown( node ) {
		var frame = mediaFrameFor( node );
		// Closed wp.media frames remain in the DOM, so an ID lookup must stay
		// within the frame that owns the clicked folder tree.
		return ( frame || document ).querySelector( '#ax-media-folder-filter' );
	}

	function folderTreeFor( node ) {
		return node && node.closest ? node.closest( '.ax-media-folder-tree' ) : null;
	}

	function currentSelection( node ) {
		if ( isList ) {
			var m = window.location.search.match( /[?&]ax_media_folder=(\d+)/ );
			return m ? parseInt( m[ 1 ], 10 ) : -1;
		}
		var dropdown = folderDropdown( node );
		if ( dropdown && dropdown.value ) {
			if ( 'unfiled' === dropdown.value ) {
				return 0;
			}
			var mm = dropdown.value.match( /^folder-(\d+)$/ );
			if ( mm ) {
				return parseInt( mm[ 1 ], 10 );
			}
		}
		return -1;
	}

	function updateActive( node ) {
		var selected = currentSelection( node );
		var root = folderTreeFor( node ) || node || document;
		root.querySelectorAll( '.ax-media-folder-tree .ax-media-folder-select' ).forEach( function ( button ) {
			var active = parseInt( button.getAttribute( 'data-folder' ), 10 ) === selected;
			button.classList.toggle( 'is-active', active );
			if ( active ) {
				button.setAttribute( 'aria-current', 'true' );
			} else {
				button.removeAttribute( 'aria-current' );
			}
		} );
	}

	function selectGrid( id, node ) {
		var dropdown = folderDropdown( node );
		if ( ! dropdown ) {
			return;
		}
		var value = -1 === id ? 'all' : ( 0 === id ? 'unfiled' : 'folder-' + id );
		modalFolder = value;
		// The toolbar filter is a Backbone view bound with jQuery events; a native
		// change event does not reach its handler, so trigger through jQuery.
		if ( $ ) {
			$( dropdown ).val( value ).trigger( 'change' );
		} else {
			dropdown.value = value;
			dropdown.dispatchEvent( new Event( 'change', { bubbles: true } ) );
		}
		setGridUploadFolder( id );
		syncSelection( node );
	}

	function setGridUploadFolder( folderId, browser ) {
		if ( ! isUploadPage() || isList ) {
			return;
		}
		gridUploadFolder = folderId > 0 ? folderId : 0;
		browser = browser || ( window.wp && window.wp.media && window.wp.media.frames && window.wp.media.frames.browse && window.wp.media.frames.browse.browserView );
		var uploader = gridCoreUploader( browser );
		if ( uploader && uploader.param ) {
			uploader.param( 'ax_media_folder', String( gridUploadFolder ) );
		}
		bindGridUploadCompletion( browser );
	}

	function gridCoreUploader( browser ) {
		// AttachmentsBrowser.uploader is the inline view. The wp.Uploader instance
		// normally belongs to the frame controller, but accept both Core paths.
		if ( browser && browser.controller && browser.controller.uploader && browser.controller.uploader.param ) {
			return browser.controller.uploader;
		}
		if ( browser && browser.uploader && browser.uploader.uploader && browser.uploader.uploader.param ) {
			return browser.uploader.uploader;
		}
		return null;
	}

	function refreshFolderCounts() {
		if ( ! config.countsUrl || ! config.restNonce ) {
			return;
		}
		window.fetch( config.countsUrl, {
			credentials: 'same-origin',
			headers: { 'X-WP-Nonce': config.restNonce }
		} ).then( function ( response ) {
			return response.ok ? response.json() : null;
		} ).then( function ( counts ) {
			updateFolderCounts( counts );
		} );
	}

	function bindGridUploadCompletion( browser ) {
		if ( ! browser ) {
			return;
		}
		var uploader = gridCoreUploader( browser );
		if ( ! uploader ) {
			// UploaderInline creates wp.Uploader asynchronously. Its controller emits
			// this Core event once the real Plupload instance is ready.
			if ( browser.controller && ! browser.axMediaFolderUploaderReadyBound ) {
				browser.axMediaFolderUploaderReadyBound = true;
				browser.controller.on( 'uploader:ready', function () {
					setGridUploadFolder( gridUploadFolder, browser );
				} );
			}
			return;
		}
		if ( uploader.axMediaFolderUploadBound || ! uploader.uploader || ! uploader.uploader.bind ) {
			return;
		}
		uploader.axMediaFolderUploadBound = true;
		uploader.uploader.bind( 'FileUploaded', function ( up, file ) {
			// Core updates file.attachment in its own FileUploaded callback. Defer one
			// turn so the model has the server ID and the folder assignment is complete.
			window.setTimeout( function () {
				var selected = currentSelection( browser.el );
				if ( file.attachment && ( -1 === selected || selected === gridUploadFolder ) ) {
					browser.collection.add( file.attachment, { merge: true } );
				}
				refreshFolderCounts();
			}, 0 );
		} );
	}

	function syncSelection( node ) {
		updateActive( node );
		var nav = document.getElementById( 'ax-media-breadcrumb' );
		if ( nav ) {
			renderBreadcrumb( nav );
		}
	}

	/* ---------------------------------------------------------------- *
	 * Attachment drag-and-drop — jQuery UI, matching WordPress media views.
	 * ---------------------------------------------------------------- */
	function attachmentIdsForDrag( attachment ) {
		var $attachment = $( attachment );
		if ( isList ) {
			var $checked = $( '#the-list input[name="media[]"]:checked' );
			var $row = $attachment.closest( 'tr[id^="post-"]' );
			var $rows = $row.find( 'input[name="media[]"]:checked' ).length ? $checked.closest( 'tr[id^="post-"]' ) : $row;
			return $rows.map( function () {
				return parseInt( String( this.id || '' ).replace( /^post-/, '' ), 10 );
			} ).get().filter( function ( id ) {
				return ! isNaN( id ) && id > 0;
			} );
		}
		var $selected = $attachment.closest( '.attachments' ).find( '.attachment.selected:not(.selection,:hidden)' );
		var $sources = $selected.filter( $attachment ).length ? $selected : $attachment;
		return $sources.map( function () {
			return parseInt( $( this ).attr( 'data-id' ), 10 );
		} ).get().filter( function ( id ) {
			return ! isNaN( id ) && id > 0;
		} );
	}

	function browserForAttachment( attachment ) {
		var $browser = $( attachment ).closest( '.attachments-browser' );
		var browserNode;
		if ( ! $browser.length ) {
			$browser = $( '.attachments-browser' ).first();
		}
		browserNode = $browser.get( 0 );
		return ( browserNode && browserNode.axMediaFolderBrowser ) || $browser.data( 'axMediaFolderBrowser' ) || null;
	}

	function rememberAttachmentBrowser( browser ) {
		if ( ! browser || ! browser.el ) {
			return;
		}
		browser.el.axMediaFolderBrowser = browser;
		$( browser.el ).data( 'axMediaFolderBrowser', browser );
		setGridUploadFolder( currentSelection( browser.el ), browser );
	}

	function bindExistingAttachmentBrowser() {
		var media = window.wp && window.wp.media;
		var frame = media && media.frame;
		var browser = media && media.frames && media.frames.browse ? media.frames.browse.browserView : null;
		if ( ! browser && frame && frame.content && frame.content.get ) {
			browser = frame.content.get();
		}
		// upload.php creates its AttachmentsBrowser before this plugin's footer
		// script runs. Core exposes that instance as media.frames.browse.browserView;
		// future/modal views pass through Browser.prototype.render above.
		if ( browser && browser.collection && browser.el ) {
			rememberAttachmentBrowser( browser );
		}
	}

	function folderDropId( target ) {
		var id = parseInt( target && target.getAttribute( 'data-folder' ), 10 );
		return ! isNaN( id ) && id >= 0 ? id : null;
	}

	function dragStatus( target ) {
		var tree = folderTreeFor( target );
		return tree ? tree.querySelector( '.ax-media-folder-tree__drop-status' ) : null;
	}

	function setDragStatus( target, message ) {
		var status = dragStatus( target );
		if ( status ) {
			status.textContent = message;
		}
	}

	function showMoveNotice( message, isError, origin ) {
		var existing = document.getElementById( 'ax-media-folder-move-notice' );
		if ( existing ) {
			existing.remove();
		}
		var notice = el( 'div', 'notice ' + ( isError ? 'notice-error' : 'notice-success' ) );
		notice.id = 'ax-media-folder-move-notice';
		notice.appendChild( el( 'p', null, message ) );
		var modal = origin && origin.closest ? origin.closest( '.media-frame' ) : null;
		var host = modal ? modal.querySelector( '.media-frame-content' ) : document.querySelector( '#wpbody-content .wrap' );
		if ( host ) {
			host.insertBefore( notice, host.firstChild );
		}
		if ( wp && wp.a11y && wp.a11y.speak ) {
			wp.a11y.speak( message, isError ? 'assertive' : 'polite' );
		}
	}

	function removeMovedAttachmentsFromCurrentFolder( browser, sourceFolder, folderId, attachmentIds ) {
		var models;
		if ( isList ) {
			removeMovedListRows( sourceFolder, folderId, attachmentIds );
			return;
		}
		if ( ! browser || ! browser.collection ) {
			return;
		}
		// "All media" still contains the moved files. Every specific source view
		// must remove them immediately, just as FileBird does after a successful drop.
		if ( -1 === sourceFolder || sourceFolder === folderId ) {
			return;
		}
		models = attachmentIds.map( function ( id ) {
			return browser.collection.get( id );
		} ).filter( function ( model ) {
			return !! model;
		} );
		if ( models.length ) {
			browser.collection.remove( models );
		}
	}

	function removeMovedListRows( sourceFolder, folderId, attachmentIds ) {
		if ( -1 === sourceFolder || sourceFolder === folderId ) {
			return;
		}
		attachmentIds.forEach( function ( id ) {
			var row = document.getElementById( 'post-' + id );
			if ( row ) {
				row.remove();
			}
		} );
		var list = document.getElementById( 'the-list' );
		if ( list && ! list.querySelector( 'tr' ) ) {
			var columns = document.querySelectorAll( '.wp-list-table thead th' ).length || 1;
			var empty = el( 'tr', 'no-items' );
			var cell = el( 'td', 'colspanchange', config.noMedia );
			cell.colSpan = columns;
			empty.appendChild( cell );
			list.appendChild( empty );
		}
	}

	function clearBrowserSelection( browser ) {
		if ( ! browser || ! browser.controller || ! browser.controller.state ) {
			return;
		}
		var selection = browser.controller.state().get( 'selection' );
		if ( selection && selection.reset ) {
			selection.reset();
			browser.controller.trigger( 'selection:toggle' );
		}
	}

	async function moveDraggedAttachments( target, sourceFolder, folderId, attachmentIds, browser ) {
		if ( ! attachmentIds.length || ! config.moveUrl || ! config.restNonce ) {
			return;
		}
		target.classList.add( 'is-drop-pending' );
		setDragStatus( target, config.moving );
		try {
			var response = await window.fetch( config.moveUrl, {
				method: 'POST',
				credentials: 'same-origin',
				headers: {
					'Content-Type': 'application/json',
					'X-WP-Nonce': config.restNonce
				},
				body: JSON.stringify( { attachments: attachmentIds, folder: folderId } )
			} );
			var result = await response.json();
			if ( ! response.ok || ! result || ! Array.isArray( result.moved ) || ! result.moved.length ) {
				throw new Error( result && result.message ? result.message : config.moveError );
			}
			updateFolderCounts( result.counts );
			removeMovedAttachmentsFromCurrentFolder( browser, sourceFolder, folderId, result.moved );
			var denied = Array.isArray( result.denied ) ? result.denied : [];
			var message = denied.length ? config.partiallyMoved.replace( '%1$d', result.moved.length ).replace( '%2$d', denied.length ) : config.moved;
			setDragStatus( target, message );
			showMoveNotice( message, denied.length > 0, target );
		} catch ( error ) {
			var message = error.message || config.moveError;
			setDragStatus( target, message );
			showMoveNotice( message, true, target );
		} finally {
			target.classList.remove( 'is-drop-pending' );
		}
	}

	function registerAttachmentDraggable( attachment, browser ) {
		var $attachment = $( attachment );
		if ( ! $attachment.length || $attachment.hasClass( 'ui-draggable' ) ) {
			return;
		}
		var options = {
			appendTo: 'body',
			cursor: 'move',
			// Keep the drag helper's origin on the pointer. Without this, jQuery UI
			// preserves the grab offset and the apparent drop position drifts.
			cursorAt: { top: 0, left: 0 },
			helper: function () {
				var ids = attachmentIdsForDrag( this );
				return $( '<span>', {
					'class': 'ax-media-folder-tree__drag-helper',
					'data-attachment-ids': ids.join( ',' ),
					text: ids.length + ' ' + config.items
				} );
			},
			start: function () {
				$attachment.addClass( 'is-dragging' );
			},
			stop: function () {
				$attachment.removeClass( 'is-dragging' );
			}
		};
		if ( isList ) {
			// Match FileBird's list affordance: dragging begins only from the
			// checkbox column, so ordinary row links and controls keep their behavior.
			options.handle = '.check-column';
		}
		$attachment.draggable( options );
		if ( browser ) {
			$attachment.data( 'axMediaFolderBrowser', browser );
		}
	}

	function registerFolderDropTarget( target ) {
		var $target = $( target );
		if ( $target.hasClass( 'ui-droppable' ) || null === folderDropId( target ) ) {
			return;
		}
		$target.droppable( {
			greedy: true,
			accept: function ( draggable ) {
				if ( folderEditor && draggable.is( '.ax-media-folder-tree__item[data-folder]' ) ) {
					return false;
				}
				return draggable.is( '.ax-media-folder-tree__item[data-folder]' )
					|| draggable.is( isList ? 'table.wp-list-table tbody tr[id^="post-"]' : '.attachments .attachment[data-id]' );
			},
			tolerance: 'pointer',
			hoverClass: 'is-drop-target',
			drop: function ( event, ui ) {
				if ( ui.draggable.is( '.ax-media-folder-tree__item[data-folder]' ) ) {
					moveDraggedFolder( this, parseInt( ui.draggable.attr( 'data-folder' ), 10 ), folderDropId( this ) );
					return;
				}
				var folderId = folderDropId( this );
				var sourceFolder = currentSelection( this );
				var attachmentIds = String( ui.helper.attr( 'data-attachment-ids' ) || '' ).split( ',' ).map( function ( id ) {
					return parseInt( id, 10 );
				} ).filter( function ( id ) {
					return ! isNaN( id ) && id > 0;
				} );
				// Attachment.Library.controller is the media frame, not the
				// AttachmentsBrowser that owns the visible collection. Resolve from the
				// dragged tile first so collection.remove() updates this actual grid.
				var browser = browserForAttachment( ui.draggable ) || ui.draggable.data( 'axMediaFolderBrowser' );
				if ( null === folderId || ! attachmentIds.length || sourceFolder === folderId ) {
					return;
				}
				// jQuery UI's drop ends with a click on the folder button. It must not
				// become navigation to the target folder: the source grid needs to stay
				// visible long enough for the moved tiles to be removed from it.
				$( this ).data( 'axMediaFolderSuppressClickUntil', Date.now() + 500 );
				clearBrowserSelection( browser );
				moveDraggedAttachments( this, sourceFolder, folderId, attachmentIds, browser );
			}
		} );
	}

	function registerFolderDraggable( item ) {
		var $item = $( item );
		if ( ! $item.length || $item.hasClass( 'ui-draggable' ) ) {
			return;
		}
		$item.draggable( {
			appendTo: 'body',
			cursor: 'move',
			cursorAt: { top: 0, left: 0 },
			// Folder names are the drag affordance. The small threshold preserves
		// ordinary click navigation in both the grid and list sidebars.
		handle: '.ax-media-folder-select',
		// jQuery UI cancels drags that start from any button by default. Grid-mode
		// folder rows are buttons, so only the disclosure toggle is excluded.
		cancel: '.ax-media-folder-toggle',
		distance: 8,
			helper: function () {
				// Match Core's Menu structure cue: drag a compact copy of the
				// actual folder row, not a detached label or a browser link ghost.
				var $line = $( this ).children( '.ax-media-folder-tree__line' ).clone( false );
				$line.addClass( 'ax-media-folder-tree__folder-helper' );
				$line.css( 'width', $( this ).children( '.ax-media-folder-tree__line' ).outerWidth() );
				$line.find( 'a' ).removeAttr( 'href' ).attr( 'draggable', 'false' );
				return $line;
			},
			start: function () {
				if ( folderEditor ) {
					return false;
				}
				$item.addClass( 'is-dragging' );
				document.querySelectorAll( '.ax-media-folder-tree__root-drop' ).forEach( function ( zone ) {
					zone.hidden = false;
				} );
			},
			stop: function () {
				$item.removeClass( 'is-dragging' );
				document.querySelectorAll( '.ax-media-folder-tree__root-drop' ).forEach( function ( zone ) {
					zone.hidden = true;
				} );
				suppressFolderClickUntil = Date.now() + 500;
			}
		} );
	}

	function registerRootFolderDropTarget( target ) {
		var $target = $( target );
		if ( $target.hasClass( 'ui-droppable' ) ) {
			return;
		}
		$target.droppable( {
			greedy: true,
			accept: function ( draggable ) {
				return ! folderEditor && draggable.is( '.ax-media-folder-tree__item[data-folder]' );
			},
			tolerance: 'pointer',
			hoverClass: 'is-drop-target',
			drop: function ( event, ui ) {
				moveDraggedFolder( this, parseInt( ui.draggable.attr( 'data-folder' ), 10 ), 0 );
			}
		} );
	}

	function replaceFolderTrees() {
		document.querySelectorAll( '.ax-media-folder-tree' ).forEach( function ( tree ) {
			var replacement = createTreeAside( tree.id || null, !! tree.closest( '.media-modal' ) );
			$( tree ).replaceWith( replacement );
		} );
		initAttachmentDragAndDrop();
		initFolderDragAndDrop();
		updateActive();
	}

	function closeFolderContextMenu( restoreFocus ) {
		if ( folderContextMenu ) {
			var trigger = folderContextMenu.axMediaFolderTrigger;
			if ( trigger && trigger.setAttribute ) {
				trigger.setAttribute( 'aria-expanded', 'false' );
			}
			folderContextMenu.remove();
			folderContextMenu = null;
			if ( restoreFocus && trigger && trigger.focus ) {
				trigger.focus();
			}
		}
	}

	function applyFolderTreeResponse( result ) {
		if ( ! result || ! Array.isArray( result.folders ) ) {
			throw new Error( result && result.message ? result.message : config.folderSaveError );
		}
		config.folders = result.folders;
		updateFolderCounts( result.counts );
		replaceFolderTrees();
	}

	function createFolderEditor( mode, folderId, parentId, name ) {
		var form = el( 'form', 'ax-media-folder-editor' );
		form.setAttribute( 'data-mode', mode );
		var input = el( 'input', 'ax-media-folder-editor__input' );
		input.type = 'text';
		input.required = true;
		input.value = name || '';
		input.setAttribute( 'aria-label', config.folderName );
		form.appendChild( input );
		var actions = el( 'div', 'ax-media-folder-editor__actions' );
		var cancel = el( 'button', 'button ax-media-folder-editor__cancel', config.cancel );
		cancel.type = 'button';
		var save = el( 'button', 'button button-primary ax-media-folder-editor__save', config.save );
		save.type = 'submit';
		actions.appendChild( cancel );
		actions.appendChild( save );
		form.appendChild( actions );

		cancel.addEventListener( 'click', function () {
			folderEditor = null;
			replaceFolderTrees();
		} );
		form.addEventListener( 'submit', async function ( event ) {
			event.preventDefault();
			var value = input.value.trim();
			if ( ! value ) {
				input.focus();
				return;
			}
			input.disabled = true;
			cancel.disabled = true;
			save.disabled = true;
			try {
				var url = 'new' === mode ? config.folderRestUrl : config.folderRestUrl + String( folderId );
				var body = 'new' === mode ? { name: value, parent: parentId } : { name: value };
				var response = await window.fetch( url, {
					method: 'POST',
					credentials: 'same-origin',
					headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': config.restNonce },
					body: JSON.stringify( body )
				} );
				var result = await response.json();
				if ( ! response.ok ) {
					throw new Error( result && result.message ? result.message : config.folderSaveError );
				}
				folderEditor = null;
				applyFolderTreeResponse( result );
				showMoveNotice( config.folderSaved, false );
			} catch ( error ) {
				showMoveNotice( error.message || config.folderSaveError, true );
				input.disabled = false;
				cancel.disabled = false;
				save.disabled = false;
				input.focus();
			}
		} );
		window.setTimeout( function () { input.focus(); input.select(); }, 0 );
		return form;
	}

	function renderFolderEditor( aside ) {
		if ( ! folderEditor || 'ax-media-folder-sidebar' !== aside.id ) {
			return;
		}
		var state = folderEditor;
		if ( 'rename' === state.mode ) {
			var item = aside.querySelector( '.ax-media-folder-tree__item[data-folder="' + state.folderId + '"]' );
			var select = item && item.querySelector( ':scope > .ax-media-folder-tree__line > .ax-media-folder-select' );
			if ( ! select ) {
				folderEditor = null;
				return;
			}
			var editLine = el( 'div', 'ax-media-folder-tree__edit-line' );
			var folderIcon = select.querySelector( '.dashicons' );
			if ( folderIcon ) {
				editLine.appendChild( folderIcon.cloneNode( true ) );
			}
			editLine.appendChild( createFolderEditor( 'rename', state.folderId, 0, state.name ) );
			select.replaceWith( editLine );
			return;
		}
		var editorItem = el( 'li', 'ax-media-folder-tree__item ax-media-folder-tree__item--editor' );
		editorItem.style.setProperty( '--ax-media-folder-depth', state.parentId > 0 ? state.depth + 1 : 0 );
		var newLine = el( 'div', 'ax-media-folder-tree__line ax-media-folder-tree__edit-line' );
		newLine.appendChild( el( 'span', 'ax-media-folder-toggle-spacer' ) );
		newLine.appendChild( icon( 'dashicons-category' ) );
		newLine.appendChild( createFolderEditor( 'new', 0, state.parentId, '' ) );
		editorItem.appendChild( newLine );
		if ( state.parentId > 0 ) {
			var parent = aside.querySelector( '.ax-media-folder-tree__item[data-folder="' + state.parentId + '"]' );
			var children = parent && parent.querySelector( ':scope > .ax-media-folder-tree__children' );
			if ( parent && ! children ) {
				children = el( 'ul', 'ax-media-folder-tree__children' );
				parent.appendChild( children );
				var parentLine = parent.querySelector( ':scope > .ax-media-folder-tree__line' );
				var spacer = parentLine && parentLine.querySelector( ':scope > .ax-media-folder-toggle-spacer' );
				if ( spacer ) {
					var toggle = el( 'button', 'ax-media-folder-toggle' );
					toggle.type = 'button';
					toggle.setAttribute( 'data-folder', state.parentId );
					toggle.setAttribute( 'aria-expanded', 'true' );
					toggle.setAttribute( 'aria-label', state.name );
					toggle.appendChild( icon( 'dashicons-arrow-down-alt2' ) );
					spacer.replaceWith( toggle );
				}
			}
			if ( children ) {
				children.hidden = false;
				children.appendChild( editorItem );
				return;
			}
		}
		aside.querySelector( '.ax-media-folder-tree__list' ).appendChild( editorItem );
	}

	function openFolderEditor( mode, folderId, tree ) {
		var item = folderId > 0 && tree.querySelector( '.ax-media-folder-tree__item[data-folder="' + folderId + '"]' );
		var name = item && item.querySelector( '.ax-media-folder-tree__name' );
		folderEditor = {
			mode: mode,
			folderId: folderId,
			parentId: 'new' === mode ? folderId : 0,
			name: name ? name.textContent : '',
			depth: item ? parseInt( item.style.getPropertyValue( '--ax-media-folder-depth' ), 10 ) || 0 : 0
		};
		closeFolderContextMenu();
		replaceFolderTrees();
	}

	function isFolderDescendant( folderId, candidateId ) {
		var candidate = folderById( candidateId );
		var guard = 0;
		while ( candidate && guard < 50 ) {
			if ( parseInt( candidate.parent, 10 ) === folderId ) {
				return true;
			}
			candidate = folderById( parseInt( candidate.parent, 10 ) || 0 );
			guard++;
		}
		return false;
	}

	function openFolderContextMenu( event, folderId, tree, trigger ) {
		if ( folderId <= 0 || 'ax-media-folder-sidebar' !== tree.id ) {
			return;
		}
		event.preventDefault();
		closeFolderContextMenu();
		var menu = el( 'div', 'ax-media-folder-context-menu' );
		menu.setAttribute( 'aria-label', config.folderActions );
		var addItem = function ( label, glyph, handler ) {
			var button = el( 'button', 'ax-media-folder-context-menu__item', label );
			button.type = 'button';
			button.prepend( icon( glyph ) );
			button.addEventListener( 'click', handler );
			menu.appendChild( button );
		};
		addItem( config.newFolder, 'dashicons-category-add', function () { openFolderEditor( 'new', folderId, tree ); } );
		addItem( config.renameFolder, 'dashicons-edit', function () { openFolderEditor( 'rename', folderId, tree ); } );
		var moveLabel = el( 'label', 'screen-reader-text', config.moveTo );
		var moveSelect = el( 'select', 'ax-media-folder-context-menu__move' );
		moveLabel.htmlFor = 'ax-media-folder-move-' + folderId;
		moveSelect.id = moveLabel.htmlFor;
		moveSelect.appendChild( new Option( config.moveTo + '…', '' ) );
		moveSelect.appendChild( new Option( config.topLevel, '0' ) );
		( config.folders || [] ).forEach( function ( folder ) {
			var candidateId = parseInt( folder.id, 10 );
			if ( candidateId !== folderId && ! isFolderDescendant( folderId, candidateId ) ) {
				moveSelect.appendChild( new Option( folder.name, String( candidateId ) ) );
			}
		} );
		moveSelect.addEventListener( 'change', function () {
			var parentId = parseInt( moveSelect.value, 10 );
			if ( ! isNaN( parentId ) ) {
				closeFolderContextMenu();
				moveDraggedFolder( tree, folderId, parentId );
			}
		} );
		menu.appendChild( moveLabel );
		menu.appendChild( moveSelect );
		menu.style.left = event.clientX + 'px';
		menu.style.top = event.clientY + 'px';
		document.body.appendChild( menu );
		folderContextMenu = menu;
		folderContextMenu.axMediaFolderTrigger = trigger || null;
		if ( trigger && trigger.setAttribute ) {
			trigger.setAttribute( 'aria-expanded', 'true' );
		}
		var bounds = menu.getBoundingClientRect();
		if ( bounds.right > window.innerWidth ) { menu.style.left = Math.max( 8, event.clientX - bounds.width ) + 'px'; }
		if ( bounds.bottom > window.innerHeight ) { menu.style.top = Math.max( 8, event.clientY - bounds.height ) + 'px'; }
		var first = menu.querySelector( 'button, select' );
		if ( first ) {
			first.focus();
		}
	}

	async function moveDraggedFolder( target, folderId, parentId ) {
		if ( folderEditor || ! folderId || folderId === parentId || ! config.folderRestUrl || ! config.restNonce ) {
			return;
		}
		target.classList.add( 'is-drop-pending' );
		setDragStatus( target, config.movingFolder );
		try {
			var confirmed = false;
			var response;
			var result;
			while ( true ) {
				response = await window.fetch( config.folderRestUrl + String( folderId ), {
				method: 'POST',
				credentials: 'same-origin',
				headers: {
					'Content-Type': 'application/json',
					'X-WP-Nonce': config.restNonce
				},
					body: JSON.stringify( { parent: parentId, confirm_policy_change: confirmed } )
				} );
				result = await response.json();
				if ( 409 === response.status && result && 'ax_media_folder_policy_confirmation' === result.code && ! confirmed && window.confirm( config.confirmPolicyLoosening ) ) {
					confirmed = true;
					continue;
				}
				break;
			}
			if ( ! response.ok || ! result || ! Array.isArray( result.folders ) ) {
				throw new Error( result && result.message ? result.message : config.folderMoveError );
			}
			applyFolderTreeResponse( result );
			setDragStatus( target, config.folderMoved );
			showMoveNotice( config.folderMoved, false, target );
		} catch ( error ) {
			var message = error.message || config.folderMoveError;
			setDragStatus( target, message );
			showMoveNotice( message, true, target );
		} finally {
			target.classList.remove( 'is-drop-pending' );
		}
	}

	function initAttachmentDragAndDrop() {
		if ( ! $ || ! $.fn.draggable || ! $.fn.droppable ) {
			return;
		}
		if ( isList ) {
			$( 'table.wp-list-table tbody tr[id^="post-"]' ).each( function () {
				registerAttachmentDraggable( this, null );
			} );
		} else {
			$( '.attachments .attachment[data-id]' ).each( function () {
				registerAttachmentDraggable( this, browserForAttachment( this ) );
			} );
		}
		$( '.ax-media-folder-tree .ax-media-folder-select' ).each( function () {
			registerFolderDropTarget( this );
		} );
	}

	function initFolderDragAndDrop() {
		if ( ! $ || ! $.fn.draggable || ! $.fn.droppable ) {
			return;
		}
		$( '.ax-media-folder-tree__item[data-folder]' ).each( function () {
			registerFolderDraggable( this );
		} );
		$( '.ax-media-folder-tree__root-drop' ).each( function () {
			registerRootFolderDropTarget( this );
		} );
	}

	/* ---------------------------------------------------------------- *
	 * Attachment Details Location — deterministic save with status.
	 * ---------------------------------------------------------------- */
	function locationSelect( target ) {
		if ( ! target || 'SELECT' !== target.tagName ) {
			return null;
		}
		return /attachments\[\d+\]\[ax_media_folder\]$/.test( target.name || '' ) ? target : null;
	}

	function locationStatus( select ) {
		var status = select.parentNode && select.parentNode.querySelector( '.ax-media-location-status' );
		if ( ! status && select.parentNode ) {
			status = el( 'span', 'ax-media-location-status' );
			status.setAttribute( 'role', 'status' );
			status.setAttribute( 'aria-live', 'polite' );
			select.parentNode.appendChild( status );
		}
		return status;
	}

	async function saveLocation( select, event ) {
		// Core's compat form also auto-saves on change, but exposes no completion
		// state. Own this field so reload/close timing cannot silently lose it.
		event.preventDefault();
		event.stopImmediatePropagation();

		var match = ( select.name || '' ).match( /attachments\[(\d+)\]\[ax_media_folder\]$/ );
		if ( ! match ) {
			return;
		}
		var previous = select.getAttribute( 'data-ax-previous-folder' ) || '0';
		var status = locationStatus( select );
		select.disabled = true;
		if ( status ) {
			status.textContent = config.saving;
		}

		try {
			var body = new URLSearchParams( {
				action: 'axismundi_media_save_attachment_location',
				nonce: config.locationNonce,
				attachment_id: match[ 1 ],
				folder: select.value
			} );
			var response = await window.fetch( config.ajaxUrl, {
				method: 'POST',
				credentials: 'same-origin',
				headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
				body: body.toString()
			} );
			var result = await response.json();
			if ( ! response.ok || ! result.success ) {
				throw new Error( result.data && result.data.message ? result.data.message : config.saveError );
			}
			select.setAttribute( 'data-ax-previous-folder', select.value );
			if ( status ) {
				status.textContent = config.saved;
			}
		} catch ( error ) {
			select.value = previous;
			if ( status ) {
				status.textContent = error.message || config.saveError;
			}
		} finally {
			select.disabled = false;
		}
	}

	/* ---------------------------------------------------------------- *
	 * Breadcrumb — the ancestry path of the current folder.
	 * ---------------------------------------------------------------- */
	function folderById( id ) {
		return ( config.folders || [] ).find( function ( folder ) {
			return parseInt( folder.id, 10 ) === id;
		} );
	}

	function folderPath( id ) {
		var path = [];
		var folder = folderById( id );
		var guard = 0;
		while ( folder && guard < 50 ) {
			path.unshift( { id: parseInt( folder.id, 10 ), name: folder.name } );
			folder = folderById( parseInt( folder.parent, 10 ) || 0 );
			guard++;
		}
		return path;
	}

	function crumb( id, name, isCurrent ) {
		if ( isCurrent ) {
			var span = el( 'span', 'ax-media-breadcrumb__current', name );
			span.setAttribute( 'aria-current', 'true' );
			return span;
		}
		var node;
		if ( isList ) {
			node = el( 'a', 'ax-media-breadcrumb__link', name );
			node.href = folderUrl( id );
		} else {
			node = el( 'button', 'ax-media-breadcrumb__link', name );
			node.type = 'button';
		}
		node.setAttribute( 'data-folder', id );
		return node;
	}

	function renderBreadcrumb( nav ) {
		nav.textContent = '';
		var selected = currentSelection();
		var items;
		if ( 0 === selected ) {
			items = [ { id: -1, name: config.all }, { id: 0, name: config.unfiled, current: true } ];
		} else if ( -1 === selected ) {
			items = [ { id: -1, name: config.all, current: true } ];
		} else {
			items = [ { id: -1, name: config.all } ];
			var path = folderPath( selected );
			path.forEach( function ( entry, index ) {
				items.push( { id: entry.id, name: entry.name, current: index === path.length - 1 } );
			} );
		}
		items.forEach( function ( item, index ) {
			if ( index > 0 ) {
				var sep = el( 'span', 'ax-media-breadcrumb__sep', '›' );
				sep.setAttribute( 'aria-hidden', 'true' );
				nav.appendChild( sep );
			}
			nav.appendChild( crumb( item.id, item.name, !! item.current ) );
		} );
	}

	function buildBreadcrumb() {
		if ( ! isUploadPage() ) {
			return;
		}
		var wrap = document.querySelector( '#wpbody-content .wrap' );
		if ( ! wrap || document.getElementById( 'ax-media-breadcrumb' ) ) {
			return;
		}
		var nav = el( 'nav', 'ax-media-breadcrumb' );
		nav.id = 'ax-media-breadcrumb';
		nav.setAttribute( 'aria-label', config.breadcrumbLabel || config.treeTitle );
		var anchor = wrap.querySelector( '.wp-header-end' ) || wrap.querySelector( 'h1' );
		if ( anchor && anchor.parentNode ) {
			anchor.parentNode.insertBefore( nav, anchor.nextSibling );
		} else {
			wrap.insertBefore( nav, wrap.firstChild );
		}
		renderBreadcrumb( nav );
	}

	function createTreeAside( domId, manageNewTab ) {
		var aside = el( 'aside', 'ax-media-folder-tree' );
		if ( domId ) {
			aside.id = domId;
		}
		aside.setAttribute( 'aria-label', config.treeTitle );

		var header = el( 'div', 'ax-media-folder-tree__header' );
		header.appendChild( el( 'h2', null, config.treeTitle ) );
		if ( 'ax-media-folder-sidebar' === domId ) {
			var add = el( 'button', 'button button-small ax-media-folder-tree__new-folder', config.newFolder );
			add.type = 'button';
			add.prepend( icon( 'dashicons-category-add' ) );
			header.appendChild( add );
		}
		var manage = el( 'a', 'button button-small ax-media-folder-tree__manage', config.manage );
		manage.href = config.manageUrl;
		if ( manageNewTab ) {
			// In the modal a same-tab link would abandon the post being edited.
			manage.target = '_blank';
			manage.rel = 'noopener';
		}
		header.appendChild( manage );
		aside.appendChild( header );

		var byParent = {};
		( config.folders || [] ).forEach( function ( folder ) {
			var parent = parseInt( folder.parent, 10 ) || 0;
			( byParent[ parent ] = byParent[ parent ] || [] ).push( folder );
		} );

		var list = el( 'ul', 'ax-media-folder-tree__list' );
		list.appendChild( row( -1, config.all, 0, false, false, config.allCount, false ) );
		list.appendChild( row( 0, config.unfiled, 0, false, false, config.unfiledCount, false ) );
		( byParent[ 0 ] || [] ).forEach( function ( folder ) {
			list.appendChild( branch( folder, byParent, 0 ) );
		} );
		aside.appendChild( list );
		var rootDrop = el( 'div', 'ax-media-folder-tree__root-drop', config.topLevel );
		rootDrop.hidden = true;
		aside.appendChild( rootDrop );
		var status = el( 'span', 'ax-media-folder-tree__drop-status' );
		status.setAttribute( 'role', 'status' );
		status.setAttribute( 'aria-live', 'polite' );
		aside.appendChild( status );
		renderFolderEditor( aside );
		return aside;
	}

	// Page sidebar (upload.php grid + list): a flex sibling of #wpbody-content.
	function buildSidebar() {
		if ( ! isUploadPage() ) {
			return;
		}
		var wpbody = document.getElementById( 'wpbody' );
		var content = document.getElementById( 'wpbody-content' );
		if ( ! wpbody || ! content || document.getElementById( 'ax-media-folder-sidebar' ) ) {
			return;
		}
		wpbody.insertBefore( createTreeAside( 'ax-media-folder-sidebar', false ), content );
		wpbody.classList.add( 'has-ax-media-folder-tree' );
		updateActive();
	}

	// Media-picker modal: the tree lives inside the attachments browser (the page
	// uses the #wpbody sidebar instead). Called from the AttachmentsBrowser render.
	function mountModalTree( browserEl ) {
		if ( ! browserEl || browserEl.querySelector( ':scope > .ax-media-folder-tree' ) ) {
			return;
		}
		browserEl.classList.add( 'has-ax-media-folder-tree' );
		var tree = createTreeAside( null, true );
		browserEl.appendChild( tree );
		if ( $ && $.fn.droppable ) {
			$( tree ).find( '.ax-media-folder-select' ).each( function () {
				registerFolderDropTarget( this );
			} );
			initFolderDragAndDrop();
		}
		updateActive( browserEl );
	}

	document.addEventListener( 'click', function ( event ) {
		var target = event.target instanceof Element ? event.target : null;
		if ( folderContextMenu && ( ! target || ! target.closest( '.ax-media-folder-context-menu, .ax-media-folder-actions' ) ) ) {
			closeFolderContextMenu( false );
		}
		var newFolder = target && target.closest( '.ax-media-folder-tree__new-folder' );
		if ( newFolder ) {
			event.preventDefault();
			openFolderEditor( 'new', 0, document.getElementById( 'ax-media-folder-sidebar' ) );
			return;
		}
		var actions = target && target.closest( '.ax-media-folder-tree .ax-media-folder-actions' );
		if ( actions ) {
			event.preventDefault();
			var actionItem = actions.closest( '.ax-media-folder-tree__item[data-folder]' );
			var actionTree = actionItem && folderTreeFor( actionItem );
			var actionFolderId = actionItem ? parseInt( actionItem.getAttribute( 'data-folder' ), 10 ) : 0;
			if ( actionTree && actionFolderId > 0 ) {
				var rect = actions.getBoundingClientRect();
				openFolderContextMenu( { clientX: rect.left, clientY: rect.bottom, preventDefault: function () {} }, actionFolderId, actionTree, actions );
			}
			return;
		}

		// Breadcrumb crumb (grid mode uses buttons; list mode uses real links).
		var breadcrumb = target && target.closest( '.ax-media-breadcrumb__link' );
		if ( breadcrumb && ! isList ) {
			event.preventDefault();
			var crumbId = parseInt( breadcrumb.getAttribute( 'data-folder' ), 10 );
			selectGrid( isNaN( crumbId ) ? -1 : crumbId, breadcrumb );
			return;
		}

		var command = target && target.closest(
			'.ax-media-folder-tree .ax-media-folder-select, .ax-media-folder-tree .ax-media-folder-toggle'
		);
		if ( ! command ) {
			return;
		}
		if ( Date.now() < suppressFolderClickUntil ) {
			event.preventDefault();
			event.stopImmediatePropagation();
			return;
		}
		if ( command.classList.contains( 'ax-media-folder-toggle' ) ) {
			event.preventDefault();
			var item = command.closest( '.ax-media-folder-tree__item' );
			var children = item && item.querySelector( '.ax-media-folder-tree__children' );
			var expanded = 'true' === command.getAttribute( 'aria-expanded' );
			command.setAttribute( 'aria-expanded', expanded ? 'false' : 'true' );
			var glyph = command.querySelector( '.dashicons' );
			if ( glyph ) {
				glyph.classList.toggle( 'dashicons-arrow-down-alt2', ! expanded );
				glyph.classList.toggle( 'dashicons-arrow-right-alt2', expanded );
			}
			if ( children ) {
				children.hidden = expanded;
			}
			setFolderCollapsed( parseInt( command.getAttribute( 'data-folder' ), 10 ), expanded );
			return;
		}
		// Folder select. List-mode rows are real links — let the browser navigate.
		if ( $ && Date.now() < ( $( command ).data( 'axMediaFolderSuppressClickUntil' ) || 0 ) ) {
			event.preventDefault();
			event.stopImmediatePropagation();
			return;
		}
		if ( isList ) {
			return;
		}
		event.preventDefault();
		var id = parseInt( command.getAttribute( 'data-folder' ), 10 );
		selectGrid( isNaN( id ) ? -1 : id, command );
	}, true );

	document.addEventListener( 'contextmenu', function ( event ) {
		var target = event.target instanceof Element ? event.target : null;
		var select = target && target.closest( '.ax-media-folder-tree .ax-media-folder-select' );
		var tree = select && folderTreeFor( select );
		var folderId = select ? parseInt( select.getAttribute( 'data-folder' ), 10 ) : 0;
		if ( tree && ! isNaN( folderId ) ) {
			openFolderContextMenu( event, folderId, tree, select );
		}
	} );

	document.addEventListener( 'keydown', function ( event ) {
		if ( 'Escape' === event.key && folderContextMenu ) {
			event.preventDefault();
			closeFolderContextMenu( true );
			return;
		}
		var select = event.target instanceof Element ? event.target.closest( '.ax-media-folder-tree .ax-media-folder-select' ) : null;
		if ( ! select || ( 'ContextMenu' !== event.key && !( event.shiftKey && 'F10' === event.key ) ) ) {
			return;
		}
		var tree = folderTreeFor( select );
		var folderId = parseInt( select.getAttribute( 'data-folder' ), 10 );
		if ( tree && folderId > 0 ) {
			event.preventDefault();
			var rect = select.getBoundingClientRect();
			openFolderContextMenu( { clientX: rect.left, clientY: rect.bottom, preventDefault: function () {} }, folderId, tree, select );
		}
	} );

	document.addEventListener( 'focusin', function ( event ) {
		var select = locationSelect( event.target );
		if ( select ) {
			select.setAttribute( 'data-ax-previous-folder', select.value );
		}
	}, true );

	document.addEventListener( 'change', function ( event ) {
		var select = locationSelect( event.target );
		if ( select ) {
			saveLocation( select, event );
		}
	}, true );

	function init() {
		bindExistingAttachmentBrowser();
		buildSidebar();
		buildBreadcrumb();
		initAttachmentDragAndDrop();
		initFolderDragAndDrop();
	}

	if ( 'loading' === document.readyState ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}

} )( window.wp, window.jQuery, window.axMediaFolders );
