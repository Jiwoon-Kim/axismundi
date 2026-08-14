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
	var sprintf = wp.i18n.sprintf;
	var C = wp.components;
	var registerPlugin = wp.plugins.registerPlugin;
	var useSelect = wp.data.useSelect;
	var useDispatch = wp.data.useDispatch;
	var useEffect = wp.element.useEffect;
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
		{ label: __( 'Every weekday', 'axismundi-calendar' ), value: 'WEEKDAYS' },
		{ label: __( 'Monthly', 'axismundi-calendar' ), value: 'MONTHLY' },
		{ label: __( 'Yearly', 'axismundi-calendar' ), value: 'YEARLY' },
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

	/**
	 * A sensible pair of times for an Event nobody has given any.
	 *
	 * The next whole hour, running an hour. Chosen over "now" because a start of 14:37 is a time
	 * somebody has to correct rather than accept, and over a fixed 09:00 because an Event being written
	 * this afternoon is rarely tomorrow morning's.
	 *
	 * Produced in the browser's own clock, which is the one the author is reading. It is written as a
	 * wall time and stored against the Event's zone, so the two agree for the ordinary case of somebody
	 * scheduling something where they are.
	 */
	function suggestedTimes() {
		var start = new Date();
		start.setMinutes( 0, 0, 0 );
		start.setHours( start.getHours() + 1 );
		var end = new Date( start.getTime() + 3600000 );
		var stamp = function ( date ) {
			var pad = function ( n ) { return String( n ).padStart( 2, '0' ); };
			return date.getFullYear() + '-' + pad( date.getMonth() + 1 ) + '-' + pad( date.getDate() )
				+ ' ' + pad( date.getHours() ) + ':' + pad( date.getMinutes() ) + ':00';
		};
		return { startsAt: stamp( start ), endsAt: stamp( end ) };
	}

	/** The five weekdays, which is the one BYDAY set common enough to be worth a preset. */
	var WEEKDAY_SET = [ 'MO', 'TU', 'WE', 'TH', 'FR' ];

	/** What a joining link offers, in RFC 7986's own vocabulary. */
	var FEATURES = [
		{ label: __( 'Video', 'axismundi-calendar' ), value: 'VIDEO' },
		{ label: __( 'Audio', 'axismundi-calendar' ), value: 'AUDIO' },
		{ label: __( 'Phone', 'axismundi-calendar' ), value: 'PHONE' },
		{ label: __( 'Chat', 'axismundi-calendar' ), value: 'CHAT' },
		{ label: __( 'Screen', 'axismundi-calendar' ), value: 'SCREEN' },
		{ label: __( 'Broadcast', 'axismundi-calendar' ), value: 'FEED' },
		{ label: __( 'Moderator', 'axismundi-calendar' ), value: 'MODERATOR' }
	];

	/**
	 * Whether a rule is exactly "every weekday".
	 *
	 * `WEEKDAYS` is not a frequency -- iCalendar has no such FREQ -- so it is offered as a preset and
	 * recognised on the way back in. Reading it as a distinct kind would mean storing something the
	 * writer would reject.
	 */
	function isWeekdayRule( rule ) {
		return 'WEEKLY' === rule.freq
			&& 1 === ( rule.interval || 1 )
			&& rule.byday.length === WEEKDAY_SET.length
			&& WEEKDAY_SET.every( function ( day ) { return -1 !== rule.byday.indexOf( day ); } );
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
		var config = window.axismundiCalendarEditor || {};
		var calendars = Array.isArray( config.calendars ) ? config.calendars : [];

		function update( changes ) {
			editPost( { axismundi_cal_envelope: Object.assign( {}, envelope, changes ) } );
		}

		/*
		 * An Event with no times at all is one nobody has started filling in, so it is given a plausible
		 * pair rather than two empty fields and a warning about them. Only ever when both are empty:
		 * an Event halfway through being written must not have the other half decided for it.
		 *
		 * In an effect rather than during render, because writing to the store while rendering is how a
		 * panel ends up re-entering its own update. The post becomes dirty, which is the honest state --
		 * the times are real and will be saved.
		 */
		var hasTimes = !! String( envelope.startsAt || '' ).trim() || !! String( envelope.endsAt || '' ).trim();
		useEffect( function () {
			if ( ! hasTimes ) {
				update( suggestedTimes() );
			}
		}, [ hasTimes ] );

		var missing = [];
		if ( ! String( envelope.startsAt || '' ).trim() ) {
			missing.push( __( 'a start', 'axismundi-calendar' ) );
		}
		if ( ! String( envelope.endsAt || '' ).trim() ) {
			missing.push( __( 'an end', 'axismundi-calendar' ) );
		}
		if ( ! Number( envelope.calendarId || 0 ) ) {
			missing.push( __( 'a calendar', 'axismundi-calendar' ) );
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
			el( C.SelectControl, {
				key: 'calendar',
				label: __( 'Calendar', 'axismundi-calendar' ),
				help: __( 'Its timezone is used for the times below.', 'axismundi-calendar' ),
				value: String( envelope.calendarId || '' ),
				options: [ { label: __( 'Select a calendar', 'axismundi-calendar' ), value: '' } ].concat(
					calendars.map( function ( calendar ) {
						return { label: calendar.name + ' (' + calendar.timezone + ')', value: String( calendar.id ) };
					} )
				),
				onChange: function ( value ) {
					var selected = calendars.filter( function ( calendar ) { return String( calendar.id ) === String( value ); } )[0];
					update( selected ? { calendarId: selected.id, timezone: selected.timezone } : { calendarId: 0, timezone: '' } );
				}
			} )
		);

		/*
		 * The zone the Event happens in, which is the Event's and not the Calendar's. Choosing a
		 * Calendar suggests its zone for a new Event; it does not own the answer afterwards, so a Seoul
		 * calendar can hold a New York meeting and changing what the Calendar suggests never moves one
		 * that is already written.
		 *
		 * Hidden for a whole day, because a civil date has no zone to be in. Offering one there would
		 * invite the conversion that all-day exists to prevent.
		 */
		if ( ! envelope.allDay ) {
			var calendarZone = ( calendars.filter( function ( c ) {
				return String( c.id ) === String( envelope.calendarId );
			} )[0] || {} ).timezone || '';
			children.push(
				el( C.SelectControl, {
					key: 'timezone',
					label: __( 'Time zone', 'axismundi-calendar' ),
					help: calendarZone
						? sprintf( __( 'The calendar suggests %s. The event keeps whatever is chosen here.', 'axismundi-calendar' ), calendarZone )
						: null,
					value: envelope.timezone || calendarZone,
					options: ( config.timezones || [] ).map( function ( zone ) {
						return { label: zone, value: zone };
					} ),
					onChange: function ( value ) { update( { timezone: String( value ) } ); }
				} )
			);
		}

		// A whole day is a different kind of fact, not a time of 00:00. It is a civil date -- the 15th is
		// the 15th wherever it is read -- so it is offered before the fields it changes the meaning of.
		children.push(
			el( C.ToggleControl, {
				key: 'allDay',
				label: __( 'All day', 'axismundi-calendar' ),
				help: __( 'A date rather than a time. It stays on the same day for every reader, wherever they are.', 'axismundi-calendar' ),
				checked: !! envelope.allDay,
				onChange: function ( value ) { update( { allDay: !! value } ); }
			} )
		);

		/*
		 * Two datetimes, always, even when the end is on the same day. The pair is the model -- iCalendar
		 * has `DTSTART` and `DTEND` and nothing that means "and it runs until this time" -- so an event
		 * crossing midnight needs no special case and cannot be entered wrongly.
		 *
		 * Whole days keep both fields and lose only their times. The dates are what the author chose and
		 * are not re-derived from anything.
		 */
		children.push(
			el( C.TextControl, {
				key: 'startsAt',
				type: envelope.allDay ? 'date' : 'datetime-local',
				label: __( 'Starts', 'axismundi-calendar' ),
				help: envelope.allDay
					? __( 'The first day it covers.', 'axismundi-calendar' )
					: __( 'The local time where the event happens.', 'axismundi-calendar' ),
				value: envelope.allDay ? String( envelope.startsAt || '' ).slice( 0, 10 ) : toInput( envelope.startsAt ),
				onChange: function ( value ) {
					update( { startsAt: envelope.allDay ? String( value ) + ' 00:00:00' : toStored( value ) } );
				}
			} )
		);

		children.push(
			el( C.TextControl, {
				key: 'endsAt',
				type: envelope.allDay ? 'date' : 'datetime-local',
				label: __( 'Ends', 'axismundi-calendar' ),
				/*
				 * Shown as the day after the last one covered, which is what is stored and what iCalendar
				 * means by `DTEND`. Presenting an inclusive date here would need a conversion in both
				 * directions, and the two would be where the off-by-one lives.
				 */
				help: envelope.allDay
					? __( 'The day after the last one it covers, as calendars count whole days.', 'axismundi-calendar' )
					: null,
				value: envelope.allDay ? String( envelope.endsAt || '' ).slice( 0, 10 ) : toInput( envelope.endsAt ),
				onChange: function ( value ) {
					update( { endsAt: envelope.allDay ? String( value ) + ' 00:00:00' : toStored( value ) } );
				}
			} )
		);

		/*
		 * A list, because an Event can be in more than one place at once: a room, a meeting link, and
		 * often a stream of the same thing. Whether it is hybrid follows from what is in here rather
		 * than from a separate "online" checkbox, which would be a second answer to the same question.
		 */
		var locations = Array.isArray( envelope.locations ) ? envelope.locations : [];
		function writeLocations( next ) {
			update( { locations: next } );
		}

		children.push(
			el(
				C.BaseControl,
				{ key: 'locations', id: 'ax-event-locations', label: __( 'Where it happens', 'axismundi-calendar' ) },
				el(
					'div',
					{ style: { display: 'flex', flexDirection: 'column', gap: '12px' } },
					locations.map( function ( location, index ) {
						var isVirtual = 'virtual' === location.kind;
						return el(
							'div',
							{ key: 'loc-' + index, style: { border: '1px solid #ddd', borderRadius: '2px', padding: '8px' } },
							el( C.SelectControl, {
								label: __( 'Kind', 'axismundi-calendar' ),
								value: location.kind || 'physical',
								options: [
									{ label: __( 'A place to go', 'axismundi-calendar' ), value: 'physical' },
									{ label: __( 'A link to join', 'axismundi-calendar' ), value: 'virtual' }
								],
								onChange: function ( value ) {
									var next = locations.slice();
									next[ index ] = Object.assign( {}, location, { kind: String( value ) } );
									writeLocations( next );
								}
							} ),
							el( C.TextControl, {
								label: __( 'Name', 'axismundi-calendar' ),
								value: String( location.label || '' ),
								onChange: function ( value ) {
									var next = locations.slice();
									next[ index ] = Object.assign( {}, location, { label: String( value ) } );
									writeLocations( next );
								}
							} ),
							isVirtual ? el( C.TextControl, {
								label: __( 'Link', 'axismundi-calendar' ),
								type: 'url',
								value: String( location.url || '' ),
								onChange: function ( value ) {
									var next = locations.slice();
									next[ index ] = Object.assign( {}, location, { url: String( value ) } );
									writeLocations( next );
								}
							} ) : el( C.TextareaControl, {
								label: __( 'Address', 'axismundi-calendar' ),
								value: String( location.address_text || '' ),
								onChange: function ( value ) {
									var next = locations.slice();
									next[ index ] = Object.assign( {}, location, { address_text: String( value ) } );
									writeLocations( next );
								}
							} ),
							isVirtual ? el(
								C.BaseControl,
								{ id: 'ax-loc-features-' + index, label: __( 'What it offers', 'axismundi-calendar' ) },
								el(
									'div',
									{ style: { display: 'flex', flexWrap: 'wrap', gap: '8px' } },
									// RFC 7986's own vocabulary rather than kinds of our own. A stream is a
									// conference offering FEED, which is why there is no separate livestream type.
									FEATURES.map( function ( feature ) {
										var have = Array.isArray( location.features ) ? location.features : [];
										return el( C.CheckboxControl, {
											key: feature.value,
											label: feature.label,
											checked: -1 !== have.indexOf( feature.value ),
											onChange: function ( checked ) {
												var next = locations.slice();
												next[ index ] = Object.assign( {}, location, {
													features: checked
														? have.concat( [ feature.value ] )
														: have.filter( function ( f ) { return f !== feature.value; } )
												} );
												writeLocations( next );
											}
										} );
									} )
								)
							) : null,
							el( C.SelectControl, {
								label: __( 'Who is told', 'axismundi-calendar' ),
								// A public event with a private joining link is ordinary, not an edge case.
								help: __( 'A joining link kept for attendees stays out of the public page and the subscription feed.', 'axismundi-calendar' ),
								value: location.access || 'public',
								options: [
									{ label: __( 'Anyone', 'axismundi-calendar' ), value: 'public' },
									{ label: __( 'People attending', 'axismundi-calendar' ), value: 'attendees' }
								],
								onChange: function ( value ) {
									var next = locations.slice();
									next[ index ] = Object.assign( {}, location, { access: String( value ) } );
									writeLocations( next );
								}
							} ),
							el(
								C.Button,
								{
									isDestructive: true,
									variant: 'link',
									onClick: function () {
										writeLocations( locations.filter( function ( _, at ) { return at !== index; } ) );
									}
								},
								__( 'Remove', 'axismundi-calendar' )
							)
						);
					} ).concat( [
						el(
							C.Button,
							{
								key: 'add-location',
								variant: 'secondary',
								onClick: function () {
									writeLocations( locations.concat( [ { kind: 'physical', label: '', address_text: '', url: '', features: [], access: 'public' } ] ) );
								}
							},
							__( 'Add location', 'axismundi-calendar' )
						)
					] )
				)
			)
		);

		/*
		 * Two axes, and the words are worth keeping apart. This one is how much of the Event somebody
		 * who may already see its Calendar is shown; whether they may see the Calendar at all is the
		 * Calendar's own setting, and the stricter of the two wins.
		 *
		 * There is no "public" here on purpose: an Event cannot open a Calendar that is closed, so a
		 * choice saying it could would do nothing in exactly the case somebody would rely on it.
		 */
		children.push(
			el( C.SelectControl, {
				key: 'visibility',
				label: __( 'Who can see it', 'axismundi-calendar' ),
				value: envelope.visibility || 'default',
				options: [
					{ label: __( 'As the calendar allows', 'axismundi-calendar' ), value: 'default' },
					// Described by what it does rather than by who it names. What is enforced is that the
					// public surfaces withhold it; people who already have a role on the calendar keep
					// seeing it, and a label promising otherwise would be claiming a rule that is not there.
					{ label: __( 'Hidden from the public page and feed', 'axismundi-calendar' ), value: 'private' }
				],
				help: __( 'A private event stays off the public page, out of the subscription feed and off its own web address, whatever the calendar allows. People who can already see this calendar still see it.', 'axismundi-calendar' ),
				onChange: function ( value ) { update( { visibility: String( value ) } ); }
			} )
		);

		// A different question: not who may look, but whether looking should make them appear occupied.
		children.push(
			el( C.SelectControl, {
				key: 'transparency',
				label: __( 'Show me as', 'axismundi-calendar' ),
				value: envelope.transparency || 'OPAQUE',
				options: [
					{ label: __( 'Busy', 'axismundi-calendar' ), value: 'OPAQUE' },
					{ label: __( 'Free', 'axismundi-calendar' ), value: 'TRANSPARENT' }
				],
				onChange: function ( value ) { update( { transparency: String( value ) } ); }
			} )
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
				value: isWeekdayRule( rule ) ? 'WEEKDAYS' : rule.freq,
				options: FREQ,
				onChange: function ( value ) {
					if ( 'WEEKDAYS' === value ) {
						// A preset, expanded here into the rule it stands for. Nothing downstream ever
						// sees `WEEKDAYS`, so the writer has one vocabulary rather than two.
						writeRule( { freq: 'WEEKLY', interval: 1, byday: WEEKDAY_SET.slice(), ordinal: '', bymonthday: '' } );
						return;
					}
					// Switching frequency drops the parts that do not apply to it, so a monthly
					// ordinal cannot survive into a weekly rule the writer would then reject.
					writeRule( { freq: value, byday: [], ordinal: '', bymonthday: '' } );
				}
			} )
		);

		if ( rule.freq && ! isWeekdayRule( rule ) ) {
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

		if ( 'WEEKLY' === rule.freq && ! isWeekdayRule( rule ) ) {
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
