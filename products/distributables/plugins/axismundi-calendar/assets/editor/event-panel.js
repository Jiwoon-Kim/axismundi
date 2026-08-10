/**
 * Event envelope document panel.
 *
 * A PluginDocumentSettingPanel over the single structured REST field
 * `axismundi_cal_envelope`. Editing surface only: every rule about what a
 * well-formed Event is lives in axismundi_cal_event_save(), and its refusals
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
		{ label: __( 'Scheduled', 'axismundi-calendar' ), value: 'EventScheduled' },
		{ label: __( 'Cancelled', 'axismundi-calendar' ), value: 'EventCancelled' },
		{ label: __( 'Postponed', 'axismundi-calendar' ), value: 'EventPostponed' },
		{ label: __( 'Rescheduled', 'axismundi-calendar' ), value: 'EventRescheduled' },
		{ label: __( 'Tentative', 'axismundi-calendar' ), value: 'EventTentative' },
		{ label: __( 'Moved online', 'axismundi-calendar' ), value: 'EventMovedOnline' }
	];

	var JOIN_MODE = [
		{ label: __( 'Anyone can join', 'axismundi-calendar' ), value: 'free' },
		{ label: __( 'Approval required', 'axismundi-calendar' ), value: 'restricted' },
		{ label: __( 'Join elsewhere', 'axismundi-calendar' ), value: 'external' },
		{ label: __( 'Invitation only', 'axismundi-calendar' ), value: 'invite' },
		{ label: __( 'No participation', 'axismundi-calendar' ), value: 'none' }
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
		var config = window.axismundiCalendarEditor || {};
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

	var FREQ = [
		{ label: __( 'Does not repeat', 'axismundi-calendar' ), value: '' },
		{ label: __( 'Daily', 'axismundi-calendar' ), value: 'DAILY' },
		{ label: __( 'Weekly', 'axismundi-calendar' ), value: 'WEEKLY' },
		{ label: __( 'Monthly', 'axismundi-calendar' ), value: 'MONTHLY' },
		{ label: __( 'Yearly', 'axismundi-calendar' ), value: 'YEARLY' }
	];

	var INTERVAL_HELP = {
		DAILY: __( 'days between repeats', 'axismundi-calendar' ),
		WEEKLY: __( 'weeks between repeats', 'axismundi-calendar' ),
		MONTHLY: __( 'months between repeats', 'axismundi-calendar' ),
		YEARLY: __( 'years between repeats', 'axismundi-calendar' )
	};

	var WEEKDAYS = [
		{ label: __( 'Mon', 'axismundi-calendar' ), value: 'MO' },
		{ label: __( 'Tue', 'axismundi-calendar' ), value: 'TU' },
		{ label: __( 'Wed', 'axismundi-calendar' ), value: 'WE' },
		{ label: __( 'Thu', 'axismundi-calendar' ), value: 'TH' },
		{ label: __( 'Fri', 'axismundi-calendar' ), value: 'FR' },
		{ label: __( 'Sat', 'axismundi-calendar' ), value: 'SA' },
		{ label: __( 'Sun', 'axismundi-calendar' ), value: 'SU' }
	];

	var ORDINALS = [
		{ label: __( 'First', 'axismundi-calendar' ), value: '1' },
		{ label: __( 'Second', 'axismundi-calendar' ), value: '2' },
		{ label: __( 'Third', 'axismundi-calendar' ), value: '3' },
		{ label: __( 'Fourth', 'axismundi-calendar' ), value: '4' },
		{ label: __( 'Last', 'axismundi-calendar' ), value: '-1' }
	];

	/**
	 * Read a stored rule back into the controls.
	 *
	 * Only the parts these controls can express are read. Anything else leaves the fields as they
	 * are rather than being rewritten, because the server stores a normalized rule and the panel
	 * must not be the thing that quietly changes it.
	 */
	function parseRule( rrule ) {
		var out = { freq: '', interval: 1, byday: [], ordinal: '', bymonthday: '', endMode: '', count: '', until: '' };
		if ( ! rrule ) {
			return out;
		}
		String( rrule ).split( ';' ).forEach( function ( part ) {
			var pair = part.split( '=' );
			var key = String( pair[0] || '' ).toUpperCase();
			var value = String( pair[1] || '' ).toUpperCase();
			if ( 'FREQ' === key ) {
				out.freq = value;
			} else if ( 'INTERVAL' === key ) {
				out.interval = parseInt( value, 10 ) || 1;
			} else if ( 'BYDAY' === key ) {
				value.split( ',' ).forEach( function ( token ) {
					var match = /^([+-]?\d{1,2})?(MO|TU|WE|TH|FR|SA|SU)$/.exec( token );
					if ( match ) {
						if ( match[1] ) {
							out.ordinal = match[1];
						}
						out.byday.push( match[2] );
					}
				} );
			} else if ( 'BYMONTHDAY' === key ) {
				out.bymonthday = value;
			} else if ( 'COUNT' === key ) {
				out.endMode = 'count';
				out.count = value;
			} else if ( 'UNTIL' === key ) {
				out.endMode = 'until';
				out.until = value.length >= 8 ? value.slice( 0, 4 ) + '-' + value.slice( 4, 6 ) + '-' + value.slice( 6, 8 ) : '';
			}
		} );
		return out;
	}

	/** Assemble the controls back into a rule for the server to validate and normalize. */
	function buildRule( rule ) {
		if ( ! rule.freq ) {
			return '';
		}
		var parts = [ 'FREQ=' + rule.freq ];
		if ( rule.interval > 1 ) {
			parts.push( 'INTERVAL=' + rule.interval );
		}
		if ( 'MONTHLY' === rule.freq || 'YEARLY' === rule.freq ) {
			if ( rule.ordinal && rule.byday.length ) {
				parts.push( 'BYDAY=' + rule.ordinal + rule.byday[0] );
			} else if ( String( rule.bymonthday ).trim() ) {
				parts.push( 'BYMONTHDAY=' + String( rule.bymonthday ).trim() );
			}
		} else if ( 'WEEKLY' === rule.freq && rule.byday.length ) {
			parts.push( 'BYDAY=' + rule.byday.join( ',' ) );
		}
		if ( 'count' === rule.endMode && String( rule.count ).trim() ) {
			parts.push( 'COUNT=' + String( rule.count ).trim() );
		} else if ( 'until' === rule.endMode && rule.until ) {
			parts.push( 'UNTIL=' + String( rule.until ).replace( /-/g, '' ) );
		}
		return parts.join( ';' );
	}

	function EventPanel() {
		var Panel = documentPanel();

		var state = useSelect( function ( select ) {
			var editor = select( 'core/editor' );
			return {
				postType: editor.getCurrentPostType(),
				envelope: editor.getEditedPostAttribute( 'axismundi_cal_envelope' ) || {}
			};
		}, [] );

		var editPost = useDispatch( 'core/editor' ).editPost;

		if ( ! Panel || POST_TYPE !== state.postType ) {
			return null;
		}

		var envelope = state.envelope;
		var zones = timezoneOptions();

		function update( changes ) {
			editPost( { axismundi_cal_envelope: Object.assign( {}, envelope, changes ) } );
		}

		var missing = [];
		if ( ! String( envelope.startsAt || '' ).trim() ) {
			missing.push( __( 'a start', 'axismundi-calendar' ) );
		}
		if ( ! String( envelope.endsAt || '' ).trim() ) {
			missing.push( __( 'an end', 'axismundi-calendar' ) );
		}
		if ( ! String( envelope.timezone || '' ).trim() ) {
			missing.push( __( 'a timezone', 'axismundi-calendar' ) );
		}

		var children = [];

		if ( missing.length ) {
			children.push(
				el(
					C.Notice,
					{ key: 'incomplete', status: 'warning', isDismissible: false },
					// Said before publishing is attempted, because an Event without these
					// projects to nothing and the page would otherwise look finished.
					__( 'This Event still needs ', 'axismundi-calendar' ) + missing.join( ', ' ) + __ ( '. It cannot be published until then.', 'axismundi-calendar' )
				)
			);
		}

		children.push(
			el( C.TextControl, {
				key: 'startsAt',
				type: 'datetime-local',
				label: __( 'Starts', 'axismundi-calendar' ),
				help: __( 'The local time where the event happens.', 'axismundi-calendar' ),
				value: toInput( envelope.startsAt ),
				onChange: function ( value ) { update( { startsAt: toStored( value ) } ); }
			} )
		);

		children.push(
			el( C.TextControl, {
				key: 'endsAt',
				type: 'datetime-local',
				label: __( 'Ends', 'axismundi-calendar' ),
				value: toInput( envelope.endsAt ),
				onChange: function ( value ) { update( { endsAt: toStored( value ) } ); }
			} )
		);

		children.push(
			el(
				C.BaseControl,
				{ key: 'timezone', id: 'ax-event-timezone', label: __( 'Timezone', 'axismundi-calendar' ), help: __( 'Where the event happens, not where you are. This travels with the start time.', 'axismundi-calendar' ) },
				el(
					'select',
					{
						id: 'ax-event-timezone',
						className: 'components-select-control__input',
						style: { width: '100%' },
						value: envelope.timezone || '',
						onChange: function ( event ) { update( { timezone: event.target.value } ); }
					},
					[ el( 'option', { key: '', value: '' }, __( 'Select a timezone', 'axismundi-calendar' ) ) ].concat(
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

		// -- Recurrence ------------------------------------------------------------------
		//
		// Assembled controls rather than a rule field. The supported set is narrow and specific, and
		// a free-text RRULE invites exactly the rules the writer refuses -- turning a rule the author
		// cannot express into a save error they cannot act on.

		var rule = parseRule( envelope.rrule || '' );

		function writeRule( changes ) {
			update( { rrule: buildRule( Object.assign( {}, rule, changes ) ) } );
		}

		children.push(
			el( C.SelectControl, {
				key: 'freq',
				label: __( 'Repeats', 'axismundi-calendar' ),
				value: rule.freq,
				options: FREQ,
				onChange: function ( value ) {
					// Switching frequency drops the parts that do not apply to it, so a monthly
					// ordinal cannot survive into a weekly rule the writer would then reject.
					writeRule( { freq: value, byday: [], ordinal: '', bymonthday: '' } );
				}
			} )
		);

		if ( rule.freq ) {
			children.push(
				el( C.TextControl, {
					key: 'interval',
					type: 'number',
					min: 1,
					label: __( 'Every', 'axismundi-calendar' ),
					help: INTERVAL_HELP[ rule.freq ],
					value: String( rule.interval || 1 ),
					onChange: function ( value ) { writeRule( { interval: Math.max( 1, parseInt( value, 10 ) || 1 ) } ); }
				} )
			);
		}

		if ( 'WEEKLY' === rule.freq ) {
			children.push(
				el(
					C.BaseControl,
					{ key: 'byday', id: 'ax-event-byday', label: __( 'On these days', 'axismundi-calendar' ) },
					el(
						'div',
						{ style: { display: 'flex', flexWrap: 'wrap', gap: '8px' } },
						WEEKDAYS.map( function ( day ) {
							return el( C.CheckboxControl, {
								key: day.value,
								label: day.label,
								checked: -1 !== rule.byday.indexOf( day.value ),
								onChange: function ( checked ) {
									var next = checked
										? rule.byday.concat( [ day.value ] )
										: rule.byday.filter( function ( d ) { return d !== day.value; } );
									writeRule( { byday: next } );
								}
							} );
						} )
					)
				)
			);
		}

		if ( 'MONTHLY' === rule.freq || 'YEARLY' === rule.freq ) {
			children.push(
				el( C.SelectControl, {
					key: 'monthlyMode',
					label: __( 'Each time, on', 'axismundi-calendar' ),
					value: rule.ordinal ? 'weekday' : 'day',
					options: [
						{ label: __( 'A day of the month', 'axismundi-calendar' ), value: 'day' },
						{ label: __( 'A weekday of the month', 'axismundi-calendar' ), value: 'weekday' }
					],
					onChange: function ( value ) {
						// The two are alternatives, so choosing one clears the other rather than
						// leaving a rule that tries to say both.
						writeRule( 'weekday' === value
							? { ordinal: '1', byday: [ 'MO' ], bymonthday: '' }
							: { ordinal: '', byday: [], bymonthday: '' } );
					}
				} )
			);
			if ( rule.ordinal ) {
				children.push(
					el( C.SelectControl, {
						key: 'ordinal',
						label: __( 'Which one', 'axismundi-calendar' ),
						value: rule.ordinal,
						options: ORDINALS,
						onChange: function ( value ) { writeRule( { ordinal: value } ); }
					} )
				);
				children.push(
					el( C.SelectControl, {
						key: 'ordinalDay',
						label: __( 'Weekday', 'axismundi-calendar' ),
						value: rule.byday[0] || 'MO',
						options: WEEKDAYS,
						onChange: function ( value ) { writeRule( { byday: [ value ] } ); }
					} )
				);
			} else {
				children.push(
					el( C.TextControl, {
						key: 'bymonthday',
						type: 'number',
						label: __( 'Day of the month', 'axismundi-calendar' ),
						help: __( 'Use -1 for the last day. Leave empty to follow the start date.', 'axismundi-calendar' ),
						value: String( rule.bymonthday || '' ),
						onChange: function ( value ) { writeRule( { bymonthday: value } ); }
					} )
				);
			}
		}

		if ( rule.freq ) {
			children.push(
				el( C.SelectControl, {
					key: 'endMode',
					label: __( 'Ends', 'axismundi-calendar' ),
					value: rule.endMode,
					options: [
						{ label: __( 'Never', 'axismundi-calendar' ), value: '' },
						{ label: __( 'After a number of times', 'axismundi-calendar' ), value: 'count' },
						{ label: __( 'On a date', 'axismundi-calendar' ), value: 'until' }
					],
					// Mutually exclusive here because they are mutually exclusive in the rule: one
					// carrying both is refused, and that refusal would be puzzling from this panel.
					onChange: function ( value ) { writeRule( { endMode: value, count: '', until: '' } ); }
				} )
			);
			if ( 'count' === rule.endMode ) {
				children.push(
					el( C.TextControl, {
						key: 'count',
						type: 'number',
						min: 1,
						label: __( 'Number of times', 'axismundi-calendar' ),
						value: String( rule.count || '' ),
						onChange: function ( value ) { writeRule( { count: value } ); }
					} )
				);
			}
			if ( 'until' === rule.endMode ) {
				children.push(
					el( C.TextControl, {
						key: 'until',
						type: 'date',
						label: __( 'Last date', 'axismundi-calendar' ),
						// Sent as a plain date. Turning it into the UTC instant the rule requires is
						// the writer's job, since the event's zone is authoritative on the server.
						value: rule.until || '',
						onChange: function ( value ) { writeRule( { until: value } ); }
					} )
				);
			}
		}

		if ( envelope.recurring ) {
			children.push(
				el(
					C.Notice,
					{ key: 'recurring-local', status: 'warning', isDismissible: false },
					__( 'Repeating events appear on this site only. They are not sent to other servers yet: the federation format describes a single occurrence, so sending one would tell other servers this happens once.', 'axismundi-calendar' )
				)
			);
		}

		children.push(
			el( C.ToggleControl, {
				key: 'displayEndTime',
				label: __( 'Show the end time', 'axismundi-calendar' ),
				help: __( 'Turn this off for an event with no meaningful finish.', 'axismundi-calendar' ),
				checked: false !== envelope.displayEndTime,
				onChange: function ( value ) { update( { displayEndTime: !! value } ); }
			} )
		);

		children.push(
			el( C.SelectControl, {
				key: 'eventStatus',
				label: __( 'Status', 'axismundi-calendar' ),
				value: envelope.eventStatus || 'EventScheduled',
				options: STATUS,
				onChange: function ( value ) { update( { eventStatus: value } ); }
			} )
		);

		children.push(
			el( C.SelectControl, {
				key: 'joinMode',
				label: __( 'Participation', 'axismundi-calendar' ),
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
					label: __( 'Where to join', 'axismundi-calendar' ),
					help: __( 'Required while participation happens elsewhere.', 'axismundi-calendar' ),
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
				label: __( 'Capacity', 'axismundi-calendar' ),
				help: __( 'Leave empty for no limit.', 'axismundi-calendar' ),
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
					__( 'This Event was moved. Peers are told its previous start so they can tell a reschedule from a new Event.', 'axismundi-calendar' )
				)
			);
		}

		return el(
			Panel,
			{ name: 'axismundi-calendar-envelope', title: __( 'Event', 'axismundi-calendar' ), className: 'axismundi-calendar-envelope' },
			children
		);
	}

	registerPlugin( 'axismundi-calendar-envelope', { render: EventPanel } );
} )( window.wp );
