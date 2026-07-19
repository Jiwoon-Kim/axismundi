/**
 * Note federation envelope document panel.
 *
 * A PluginDocumentSettingPanel over the single structured REST field
 * `axismundi_note_envelope`. The panel is only an editing surface: all
 * validation and authority stay server-side in axismundi_note_save_envelope().
 * No JSX, no build — plain wp.element.createElement.
 */
( function ( wp ) {
	'use strict';

	var el = wp.element.createElement;
	var __ = wp.i18n.__;
	var C = wp.components;
	var registerPlugin = wp.plugins.registerPlugin;
	var useSelect = wp.data.useSelect;
	var useDispatch = wp.data.useDispatch;
	var POST_TYPE = 'ax_note';

	wp.domReady( function () {
		if ( POST_TYPE !== wp.data.select( 'core/editor' ).getCurrentPostType() ) {
			return;
		}
		[ 'core/editor', 'core/edit-post' ].forEach( function ( store ) {
			var actions = wp.data.dispatch( store );
			if ( actions && 'function' === typeof actions.removeEditorPanel ) {
				actions.removeEditorPanel( 'taxonomy-panel-category' );
			}
		} );
	} );

	var VISIBILITY = [
		{ label: __( 'Public', 'axismundi-note' ), value: 'public' },
		{ label: __( 'Quiet public', 'axismundi-note' ), value: 'unlisted' },
		{ label: __( 'Followers', 'axismundi-note' ), value: 'followers' },
		{ label: __( 'Mentioned only', 'axismundi-note' ), value: 'mentioned' }
	];

	function EnvelopePanel() {
		var Panel = window.axismundiNote.documentPanel();

		var state = useSelect( function ( select ) {
			var editor = select( 'core/editor' );
			var envelope = editor.getEditedPostAttribute( 'axismundi_note_envelope' ) || {};
			var ids = envelope.attachments || [];
			return {
				postType: editor.getCurrentPostType(),
				envelope: envelope,
				media: ids.map( function ( id ) { return select( 'core' ).getMedia( id ); } )
			};
		}, [] );

		var editPost = useDispatch( 'core/editor' ).editPost;

		if ( ! Panel || POST_TYPE !== state.postType ) {
			return null;
		}

		var envelope = state.envelope;
		function update( changes ) {
			var next = Object.assign( {}, envelope, changes );
			if ( ! window.axismundiNoteEditor || ! window.axismundiNoteEditor.attachmentsEnabled ) {
				delete next.attachments;
			}
			editPost( { axismundi_note_envelope: next } );
		}

		function openAttachmentPicker() {
			if ( ! wp.media ) {
				return;
			}
			var frame = wp.media( {
				title: __( 'Select Note attachments', 'axismundi-note' ),
				button: { text: __( 'Use selected media', 'axismundi-note' ) },
				multiple: true
			} );
			frame.on( 'open', function () {
				var selection = frame.state().get( 'selection' );
				( envelope.attachments || [] ).forEach( function ( id ) {
					selection.add( wp.media.attachment( id ) );
				} );
			} );
			frame.on( 'select', function () {
				update( { attachments: frame.state().get( 'selection' ).map( function ( item ) { return item.id; } ) } );
			} );
			frame.open();
		}

		function moveAttachment( index, direction ) {
			var ids = ( envelope.attachments || [] ).slice();
			var target = index + direction;
			if ( target < 0 || target >= ids.length ) {
				return;
			}
			var moved = ids[ index ];
			ids[ index ] = ids[ target ];
			ids[ target ] = moved;
			update( { attachments: ids } );
		}

		function removeAttachment( id ) {
			update( { attachments: ( envelope.attachments || [] ).filter( function ( candidate ) { return candidate !== id; } ) } );
		}

		var attachmentControls = null;
		if ( window.axismundiNoteEditor && window.axismundiNoteEditor.attachmentsEnabled ) {
			attachmentControls = el(
				'div',
				{ className: 'axismundi-note-attachments' },
				el( 'h3', {}, __( 'Attachments', 'axismundi-note' ) ),
				( envelope.attachments || [] ).length
					? el( 'ul', { className: 'axismundi-note-attachments__list' }, ( envelope.attachments || [] ).map( function ( id, index ) {
						var item = state.media[ index ];
						var label = item && item.title && item.title.rendered ? item.title.rendered : __( 'Media item', 'axismundi-note' ) + ' #' + id;
						return el(
							'li',
							{ key: id },
							el( 'span', { className: 'axismundi-note-attachments__label' }, label ),
							el( C.Button, { icon: 'arrow-up-alt2', label: __( 'Move up', 'axismundi-note' ), disabled: 0 === index, onClick: function () { moveAttachment( index, -1 ); } } ),
							el( C.Button, { icon: 'arrow-down-alt2', label: __( 'Move down', 'axismundi-note' ), disabled: index === envelope.attachments.length - 1, onClick: function () { moveAttachment( index, 1 ); } } ),
							el( C.Button, { icon: 'remove', label: __( 'Remove attachment', 'axismundi-note' ), isDestructive: true, onClick: function () { removeAttachment( id ); } } )
						);
					} ) )
					: el( 'p', {}, __( 'No media selected.', 'axismundi-note' ) ),
				el( C.Button, { icon: 'format-image', variant: 'secondary', onClick: openAttachmentPicker }, __( 'Select media', 'axismundi-note' ) )
			);
		}

		return el(
			Panel,
			{ name: 'axismundi-note-envelope', title: __( 'Federation', 'axismundi-note' ) },
			el( C.SelectControl, {
				label: __( 'Audience', 'axismundi-note' ),
				value: envelope.visibility || 'public',
				options: VISIBILITY,
				__next40pxDefaultSize: true,
				onChange: function ( value ) { update( { visibility: value } ); }
			} ),
			el( C.TextControl, {
				label: __( 'Language (BCP-47)', 'axismundi-note' ),
				value: envelope.language || '',
				__next40pxDefaultSize: true,
				onChange: function ( value ) { update( { language: value } ); }
			} ),
			el( C.TextControl, {
				label: __( 'In reply to (URI)', 'axismundi-note' ),
				type: 'url',
				value: envelope.inReplyTo || '',
				__next40pxDefaultSize: true,
				onChange: function ( value ) { update( { inReplyTo: value } ); }
			} ),
			el( C.TextControl, {
				label: __( 'Context (URI)', 'axismundi-note' ),
				type: 'url',
				value: envelope.context || '',
				__next40pxDefaultSize: true,
				onChange: function ( value ) { update( { context: value } ); }
			} ),
			el( C.ToggleControl, {
				label: __( 'Sensitive content', 'axismundi-note' ),
				checked: !! envelope.sensitive,
				onChange: function ( value ) { update( { sensitive: value } ); }
			} ),
			el( C.TextControl, {
				label: __( 'Content warning', 'axismundi-note' ),
				value: envelope.contentWarning || '',
				__next40pxDefaultSize: true,
				onChange: function ( value ) { update( { contentWarning: value } ); }
			} ),
			el( C.TextareaControl, {
				label: __( 'Mentioned Actor URIs', 'axismundi-note' ),
				help: __( 'One Actor URI per line. Body @-mention anchors merge automatically.', 'axismundi-note' ),
				value: ( envelope.mentions || [] ).join( '\n' ),
				onChange: function ( value ) { update( { mentions: window.axismundiNote.linesToList( value ) } ); }
			} ),
			attachmentControls
		);
	}

	registerPlugin( 'axismundi-note-envelope', { render: EnvelopePanel } );
} )( window.wp );
