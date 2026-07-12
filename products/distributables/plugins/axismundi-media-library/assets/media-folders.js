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

	function row( id, name, depth, hasChildren, isProtected, count ) {
		var li = el( 'li', 'ax-media-folder-tree__item' );
		li.style.setProperty( '--ax-media-folder-depth', depth );
		var line = el( 'div', 'ax-media-folder-tree__line' );

		if ( hasChildren ) {
			var toggle = el( 'button', 'ax-media-folder-toggle' );
			toggle.type = 'button';
			toggle.setAttribute( 'aria-expanded', 'true' );
			toggle.setAttribute( 'aria-label', name );
			toggle.appendChild( icon( 'dashicons-arrow-down-alt2' ) );
			line.appendChild( toggle );
		} else {
			var spacer = el( 'span', 'ax-media-folder-toggle-spacer' );
			spacer.setAttribute( 'aria-hidden', 'true' );
			line.appendChild( spacer );
		}

		var sel = isList ? el( 'a', 'ax-media-folder-select' ) : el( 'button', 'ax-media-folder-select' );
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
		if ( 'number' === typeof count ) {
			sel.appendChild( el( 'span', 'ax-media-folder-tree__count', String( count ) ) );
		}

		line.appendChild( sel );
		li.appendChild( line );
		return li;
	}

	function branch( folder, byParent, depth ) {
		var children = byParent[ folder.id ] || [];
		var li = row(
			parseInt( folder.id, 10 ),
			folder.name,
			depth,
			children.length > 0,
			!! folder.protected,
			parseInt( folder.recursive_count, 10 ) || 0
		);
		if ( children.length ) {
			var ul = el( 'ul', 'ax-media-folder-tree__children' );
			children.forEach( function ( child ) {
				ul.appendChild( branch( child, byParent, depth + 1 ) );
			} );
			li.appendChild( ul );
		}
		return li;
	}

	function currentSelection() {
		if ( isList ) {
			var m = window.location.search.match( /[?&]ax_media_folder=(\d+)/ );
			return m ? parseInt( m[ 1 ], 10 ) : -1;
		}
		var dropdown = document.getElementById( 'ax-media-folder-filter' );
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

	function updateActive() {
		var selected = currentSelection();
		document.querySelectorAll( '.ax-media-folder-tree .ax-media-folder-select' ).forEach( function ( button ) {
			var active = parseInt( button.getAttribute( 'data-folder' ), 10 ) === selected;
			button.classList.toggle( 'is-active', active );
			if ( active ) {
				button.setAttribute( 'aria-current', 'true' );
			} else {
				button.removeAttribute( 'aria-current' );
			}
		} );
	}

	function selectGrid( id ) {
		var dropdown = document.getElementById( 'ax-media-folder-filter' );
		if ( ! dropdown ) {
			return;
		}
		var value = -1 === id ? 'all' : ( 0 === id ? 'unfiled' : 'folder-' + id );
		// The toolbar filter is a Backbone view bound with jQuery events; a native
		// change event does not reach its handler, so trigger through jQuery.
		if ( $ ) {
			$( dropdown ).val( value ).trigger( 'change' );
		} else {
			dropdown.value = value;
			dropdown.dispatchEvent( new Event( 'change', { bubbles: true } ) );
		}
		syncSelection();
	}

	function syncSelection() {
		updateActive();
		var nav = document.getElementById( 'ax-media-breadcrumb' );
		if ( nav ) {
			renderBreadcrumb( nav );
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
		var manage = el( 'a', 'button button-small', config.manage );
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
		list.appendChild( row( -1, config.all, 0, false, false, null ) );
		list.appendChild( row( 0, config.unfiled, 0, false, false, null ) );
		( byParent[ 0 ] || [] ).forEach( function ( folder ) {
			list.appendChild( branch( folder, byParent, 0 ) );
		} );
		aside.appendChild( list );
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
		browserEl.appendChild( createTreeAside( null, true ) );
		updateActive();
	}

	document.addEventListener( 'click', function ( event ) {
		var target = event.target instanceof Element ? event.target : null;

		// Breadcrumb crumb (grid mode uses buttons; list mode uses real links).
		var breadcrumb = target && target.closest( '.ax-media-breadcrumb__link' );
		if ( breadcrumb && ! isList ) {
			event.preventDefault();
			var crumbId = parseInt( breadcrumb.getAttribute( 'data-folder' ), 10 );
			selectGrid( isNaN( crumbId ) ? -1 : crumbId );
			return;
		}

		var command = target && target.closest(
			'.ax-media-folder-tree .ax-media-folder-select, .ax-media-folder-tree .ax-media-folder-toggle'
		);
		if ( ! command ) {
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
			return;
		}
		// Folder select. List-mode rows are real links — let the browser navigate.
		if ( isList ) {
			return;
		}
		event.preventDefault();
		var id = parseInt( command.getAttribute( 'data-folder' ), 10 );
		selectGrid( isNaN( id ) ? -1 : id );
	}, true );

	function init() {
		buildSidebar();
		buildBreadcrumb();
	}

	if ( 'loading' === document.readyState ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}

} )( window.wp, window.jQuery, window.axMediaFolders );
