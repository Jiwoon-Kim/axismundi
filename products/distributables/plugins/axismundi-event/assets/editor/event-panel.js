/**
 * Event envelope document panel.
 *
 * A PluginDocumentSettingPanel over the single structured REST field
 * `axismundi_event_envelope`. Editing surface only: every rule about what a
 * well-formed Event is lives in axismundi_event_save(), and its refusals
 * surface natively as the block editor's REST error.
 *
 * No JSX, no build -- plain wp.element.createElement.
 */
( function ( wp ) {
	'use strict';

	var el = wp.element.createElement;
	var __ = wp.i18n.__;
	var C = wp.components;
	var registerPlugin = wp.plugins.registerPlugin;
	var useSelect = wp.data.useSelect;
	var useDispatch = wp.data.useDispatch;
	var POST_TYPE = 'ax_event';

	/** Resolve PluginDocumentSettingPanel across WordPress versions. */
	function documentPanel() {
		if ( wp.editor && wp.editor.PluginDocumentSettingPanel ) {
			return wp.editor.PluginDocumentSettingPanel;
		}
		if ( wp.editPost && wp.editPost.PluginDocumentSettingPanel ) {
			return wp.editPost.PluginDocumentSettingPanel;
		}
		return null;
	}

	var STATUS = [
		{ label: __( 'Scheduled', 'axismundi-event' ), value: 'EventScheduled' },
		{ label: __( 'Cancelled', 'axismundi-event' ), value: 'EventCancelled' },
		{ label: __( 'Postponed', 'axismundi-event' ), value: 'EventPostponed' },
		{ label: __( 'Rescheduled', 'axismundi-event' ), value: 'EventRescheduled' },
		{ label: __( 'Tentative', 'axismundi-event' ), value: 'EventTentative' },
		{ label: __( 'Moved online', 'axismundi-event' ), value: 'EventMovedOnline' }
	];

	var JOIN_MODE = [
		{ label: __( 'Anyone can join', 'axismundi-event' ), value: 'free' },
		{ label: __( 'Approval required', 'axismundi-event' ), value: 'restricted' },
		{ label: __( 'Join elsewhere', 'axismundi-event' ), value: 'external' },
		{ label: __( 'Invitation only', 'axismundi-event' ), value: 'invite' },
		{ label: __( 'No participation', 'axismundi-event' ), value: 'none' }
	];

	/**
	 * Timezone choices, with no preselected value.
	 *
	 * The empty first entry is deliberate and is not a default: an Event happens in a
	 * particular place, and inheriting the site's zone would stamp a confident offset on
	 * an event whose author never said where it is. The server refuses an empty zone, so
	 * this placeholder is a question the author has to answer rather than a silent choice.
	 */
	function timezoneOptions() {
		var config = window.axismundiEventEditor || {};
		var list = Array.isArray( config.timezones ) ? config.timezones : [];
		var groups = [];
		var byGroup = {};
		list.forEach( function ( zone ) {
			if ( ! byGroup[ zone.group ] ) {
				byGroup[ zone.group ] = [];
				groups.push( zone.group );
			}
			byGroup[ zone.group ].push( zone );
		} );
		return { groups: groups, byGroup: byGroup };
	}

	/** 'Y-m-d H:i:s' as the value a datetime-local control wants. */
	function toInput( stored ) {
		var value = String( stored || '' ).trim();
		if ( ! value ) {
			return '';
		}
		return value.replace( ' ', 'T' ).slice( 0, 16 );
	}

	/** A datetime-local value as the 'Y-m-d H:i:s' the writer stores. */
	function toStored( input ) {
		var value = String( input || '' ).trim();
		if ( ! value ) {
			return '';
		}
		value = value.replace( 'T', ' ' );
		return 10 === value.length ? value + ' 00:00:00' : ( 16 === value.length ? value + ':00' : value );
	}

	function EventPanel() {
		var Panel = documentPanel();

		var state = useSelect( function ( select ) {
			var editor = select( 'core/editor' );
			return {
				postType: editor.getCurrentPostType(),
				envelope: editor.getEditedPostAttribute( 'axismundi_event_envelope' ) || {}
			};
		}, [] );

		var editPost = useDispatch( 'core/editor' ).editPost;

		if ( ! Panel || POST_TYPE !== state.postType ) {
			return null;
		}

		var envelope = state.envelope;
		var zones = timezoneOptions();

		function update( changes ) {
			editPost( { axismundi_event_envelope: Object.assign( {}, envelope, changes ) } );
		}

		var missing = [];
		if ( ! String( envelope.startsAt || '' ).trim() ) {
			missing.push( __( 'a start', 'axismundi-event' ) );
		}
		if ( ! String( envelope.endsAt || '' ).trim() ) {
			missing.push( __( 'an end', 'axismundi-event' ) );
		}
		if ( ! String( envelope.timezone || '' ).trim() ) {
			missing.push( __( 'a timezone', 'axismundi-event' ) );
		}

		var children = [];

		if ( missing.length ) {
			children.push(
				el(
					C.Notice,
					{ key: 'incomplete', status: 'warning', isDismissible: false },
					// Said before publishing is attempted, because an Event without these
					// projects to nothing and the page would otherwise look finished.
					__( 'This Event still needs ', 'axismundi-event' ) + missing.join( ', ' ) + __ ( '. It cannot be published until then.', 'axismundi-event' )
				)
			);
		}

		children.push(
			el( C.TextControl, {
				key: 'startsAt',
				type: 'datetime-local',
				label: __( 'Starts', 'axismundi-event' ),
				help: __( 'The local time where the event happens.', 'axismundi-event' ),
				value: toInput( envelope.startsAt ),
				onChange: function ( value ) { update( { startsAt: toStored( value ) } ); }
			} )
		);

		children.push(
			el( C.TextControl, {
				key: 'endsAt',
				type: 'datetime-local',
				label: __( 'Ends', 'axismundi-event' ),
				value: toInput( envelope.endsAt ),
				onChange: function ( value ) { update( { endsAt: toStored( value ) } ); }
			} )
		);

		children.push(
			el(
				C.BaseControl,
				{ key: 'timezone', id: 'ax-event-timezone', label: __( 'Timezone', 'axismundi-event' ), help: __( 'Where the event happens, not where you are. This travels with the start time.', 'axismundi-event' ) },
				el(
					'select',
					{
						id: 'ax-event-timezone',
						className: 'components-select-control__input',
						style: { width: '100%' },
						value: envelope.timezone || '',
						onChange: function ( event ) { update( { timezone: event.target.value } ); }
					},
					[ el( 'option', { key: '', value: '' }, __( 'Select a timezone', 'axismundi-event' ) ) ].concat(
						zones.groups.map( function ( group ) {
							return el(
								'optgroup',
								{ key: group, label: group },
								zones.byGroup[ group ].map( function ( zone ) {
									return el( 'option', { key: zone.value, value: zone.value }, zone.label );
								} )
							);
						} )
					)
				)
			)
		);

		children.push(
			el( C.ToggleControl, {
				key: 'displayEndTime',
				label: __( 'Show the end time', 'axismundi-event' ),
				help: __( 'Turn this off for an event with no meaningful finish.', 'axismundi-event' ),
				checked: false !== envelope.displayEndTime,
				onChange: function ( value ) { update( { displayEndTime: !! value } ); }
			} )
		);

		children.push(
			el( C.SelectControl, {
				key: 'eventStatus',
				label: __( 'Status', 'axismundi-event' ),
				value: envelope.eventStatus || 'EventScheduled',
				options: STATUS,
				onChange: function ( value ) { update( { eventStatus: value } ); }
			} )
		);

		children.push(
			el( C.SelectControl, {
				key: 'joinMode',
				label: __( 'Participation', 'axismundi-event' ),
				value: envelope.joinMode || 'free',
				options: JOIN_MODE,
				onChange: function ( value ) { update( { joinMode: value } ); }
			} )
		);

		if ( 'external' === ( envelope.joinMode || 'free' ) ) {
			children.push(
				el( C.TextControl, {
					key: 'externalParticipationUrl',
					type: 'url',
					label: __( 'Where to join', 'axismundi-event' ),
					help: __( 'Required while participation happens elsewhere.', 'axismundi-event' ),
					value: envelope.externalParticipationUrl || '',
					onChange: function ( value ) { update( { externalParticipationUrl: value } ); }
				} )
			);
		}

		children.push(
			el( C.TextControl, {
				key: 'maximumAttendeeCapacity',
				type: 'number',
				min: 1,
				label: __( 'Capacity', 'axismundi-event' ),
				help: __( 'Leave empty for no limit.', 'axismundi-event' ),
				value: null === envelope.maximumAttendeeCapacity || undefined === envelope.maximumAttendeeCapacity ? '' : String( envelope.maximumAttendeeCapacity ),
				onChange: function ( value ) {
					var trimmed = String( value || '' ).trim();
					update( { maximumAttendeeCapacity: '' === trimmed ? null : parseInt( trimmed, 10 ) } );
				}
			} )
		);

		if ( envelope.previousStartsAtGmt ) {
			children.push(
				el(
					C.Notice,
					{ key: 'moved', status: 'info', isDismissible: false },
					__( 'This Event was moved. Peers are told its previous start so they can tell a reschedule from a new Event.', 'axismundi-event' )
				)
			);
		}

		return el(
			Panel,
			{ name: 'axismundi-event-envelope', title: __( 'Event', 'axismundi-event' ), className: 'axismundi-event-envelope' },
			children
		);
	}

	registerPlugin( 'axismundi-event-envelope', { render: EventPanel } );
} )( window.wp );
