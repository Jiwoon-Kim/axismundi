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
			return {
				postType: editor.getCurrentPostType(),
				envelope: editor.getEditedPostAttribute( 'axismundi_note_envelope' ) || {}
			};
		}, [] );

		var editPost = useDispatch( 'core/editor' ).editPost;

		if ( ! Panel || POST_TYPE !== state.postType ) {
			return null;
		}

		var envelope = state.envelope;
		function update( changes ) {
			var next = Object.assign( {}, envelope, changes );
			editPost( { axismundi_note_envelope: next } );
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
			} )
		);
	}

	registerPlugin( 'axismundi-note-envelope', { render: EnvelopePanel } );
} )( window.wp );
