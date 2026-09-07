/**
 * Calendar workspace.
 *
 * A month grid over `calendarView`, with the sidebar being `calendarList`. Ticking a calendar is a
 * `calendarList` write and nothing else -- `selected` is one person's view state, so hiding a
 * calendar here changes what they see and nothing about who may read it.
 *
 * Everything displayed comes from the API, so there is no second permission model here to disagree
 * with the first: a calendar the caller may not read is simply absent from both endpoints.
 *
 * The grid is drawn in the viewer's timezone, which is the one question a reader is asking. All-day
 * entries are the exception and are placed by their civil date, never converted -- a holiday on the
 * 15th is on the 15th everywhere, and converting it puts it on the 14th for anyone west of it.
 *
 * No JSX, no build -- plain wp.element.createElement, as the Event panel does.
 */
( function ( wp, config ) {
	'use strict';

	if ( ! wp || ! wp.element || ! document.getElementById( 'ax-cal-workspace' ) ) {
		return;
	}

	var el = wp.element.createElement;
	var Fragment = wp.element.Fragment;
	var useState = wp.element.useState;
	var useEffect = wp.element.useEffect;
	var __ = wp.i18n.__;
	var _x = wp.i18n._x;
	var sprintf = wp.i18n.sprintf;
	var C = wp.components;
	var apiFetch = wp.apiFetch;

	var DAY_MS = 86400000;
	// wp_localize_script() serializes scalars as strings. Normalize once so the weekday
	// header cannot concatenate ("1" + 0) while date arithmetic coerces it to a number.
	var startOfWeek = Number( config.startOfWeek );
	if ( ! Number.isInteger( startOfWeek ) || startOfWeek < 0 || startOfWeek > 6 ) {
		startOfWeek = 0;
	}
	var locale = config.locale || undefined;

	/* -- Dates ------------------------------------------------------------------------------- */

	/**
	 * A `Y-m-d H:i:s` UTC string from the API as a Date.
	 *
	 * The API sends stored UTC without a zone marker, which every browser is entitled to read as
	 * local time. Stating the offset is what keeps a 19:00 Seoul event out of the previous evening.
	 */
	function parseUtc( value ) {
		return new Date( String( value ).replace( ' ', 'T' ) + 'Z' );
	}

	/** The civil date part of a `Y-m-d …` string, untouched by any zone. */
	function civilDate( value ) {
		return String( value ).slice( 0, 10 );
	}

	/** A Date as `Y-m-d` in the browser's own zone. */
	function localKey( date ) {
		var month = String( date.getMonth() + 1 );
		var day = String( date.getDate() );
		return date.getFullYear() + '-' + ( month.length < 2 ? '0' + month : month ) + '-' + ( day.length < 2 ? '0' + day : day );
	}

	/** A day-precision cursor in the local wall-time form core's DatePicker expects. */
	function datePickerValue( date ) {
		return localKey( date ) + 'T00:00:00';
	}

	/** First day of the grid: the start of the week containing the 1st. */
	function gridStart( year, month, startOfWeek ) {
		var first = new Date( year, month, 1 );
		var shift = ( first.getDay() - startOfWeek + 7 ) % 7;
		return new Date( year, month, 1 - shift );
	}

	/** Six weeks of dates, so the grid does not change height from month to month. */
	function gridDays( year, month, startOfWeek ) {
		var start = gridStart( year, month, startOfWeek );
		var days = [];
		for ( var i = 0; i < 42; i++ ) {
			days.push( new Date( start.getFullYear(), start.getMonth(), start.getDate() + i ) );
		}
		return days;
	}

	/** Weekday names in the site's week order, formatted in the current admin locale. */
	function weekdayNames( startOfWeek ) {
		var names = [];
		for ( var i = 0; i < 7; i++ ) {
			var day = new Date( 2024, 0, 7 + ( ( startOfWeek + i ) % 7 ) );
			names.push( day.toLocaleDateString( locale, { weekday: 'short' } ) );
		}
		return names;
	}

	/** Local midnight of a date, which is what a day-precision cursor is. */
	function startOfDay( date ) {
		return new Date( date.getFullYear(), date.getMonth(), date.getDate() );
	}

	/**
	 * The month a cursor falls in, as a key.
	 *
	 * What the range fetch depends on, rather than the cursor itself: moving between days of the same
	 * month redraws nothing new, and refetching on every click in the mini calendar would make it feel
	 * slower the more it is used.
	 */
	function monthKey( date ) {
		return date.getFullYear() + '-' + date.getMonth();
	}

	/**
	 * A day-precision Date from DatePicker's local wall-clock string.
	 *
	 * Do not pass this through `new Date( string )`: an ISO date is liable to be interpreted as UTC,
	 * making 12 August appear as 11 August in a browser east of Greenwich when the site runs in UTC.
	 */
	function pickedDate( value ) {
		var match = String( value ).match( /^(\d{4})-(\d{2})-(\d{2})(?:T\d{2}:\d{2}:\d{2})?$/ );
		if ( ! match ) {
			return null;
		}

		var year = Number( match[ 1 ] );
		var month = Number( match[ 2 ] ) - 1;
		var day = Number( match[ 3 ] );
		var parsed = new Date( year, month, day );
		return parsed.getFullYear() === year && parsed.getMonth() === month && parsed.getDate() === day ? parsed : null;
	}

	function monthTitle( year, month ) {
		return new Date( year, month, 1 ).toLocaleDateString( locale, { year: 'numeric', month: 'long' } );
	}

	function timeLabel( item ) {
		if ( item.allDay ) {
			return '';
		}
		return parseUtc( item.startUtc ).toLocaleTimeString( locale, { hour: 'numeric', minute: '2-digit' } );
	}

	/**
	 * Say the Gregorian day with one of ICU's other calendars.
	 *
	 * Always month and day, because this is only asked for on the days that anchor a run -- a bare
	 * day number on a week's first cell would be a count with nothing to count from.
	 *
	 * The API says whether a system can name the day and returns its structured answer. It does not
	 * ship an English string for the browser to translate: `Intl` already knows that dangi is
	 * `2026년(병오년) 7월 1일` in Korean and a different, equally valid expression elsewhere.
	 */
	// A leap month is the same number twice, so the mark is the only thing telling them apart.
	var LEAP_MARK = _x( 'L', 'leap month marker', 'axismundi-calendar' );

	function secondaryLabel( system, day ) {
		var date = system.dates[ localKey( day ) ];
		if ( ! date || ! system.icuCalendar || ! window.Intl || ! Intl.DateTimeFormat ) {
			return '';
		}
		/*
		 * Digits, from the numbers the server sent, for the calendars whose months are numbers. Not
		 * `Intl` with numeric options: for Hebrew that would print ICU's internal month index, which
		 * counts a leap month most years do not have and calls Elul the thirteenth.
		 *
		 * Which calendar gets which is registered rather than configured. 7월 and 七月 are how those
		 * dates are written; `Seventh Month` is a translation nobody asked for, and there is no site
		 * for which it is the better answer.
		 */
		if ( 'named' !== system.notation ) {
			return ( date.leapMonth ? LEAP_MARK + ' ' : '' ) + date.month + '.' + date.day;
		}

		var options = { calendar: system.icuCalendar, month: 'long', day: 'numeric', timeZone: 'UTC' };
		try {
			// The server resolves secondary calendars at UTC noon. Recreate that civil day rather than
			// passing a local midnight through the viewer's zone and risking yesterday in the formatter.
			return new Intl.DateTimeFormat( locale, options ).format( new Date( Date.UTC(
				day.getFullYear(), day.getMonth(), day.getDate(), 12
			) ) );
		} catch ( error ) {
			// An older browser without a provider is allowed to omit the annotation; a guessed Gregorian
			// label wearing another calendar's name would be worse than no second date.
			return '';
		}
	}

	/**
	 * The whole date, for the tooltip and nowhere else.
	 *
	 * This is where the year lives -- including whatever the viewer's locale adds to it, so Korean
	 * gets `2026년(병오년) 7월 1일`. It is deliberately not an option for the cell: a year under every
	 * number is what broke the grid, and the one place a full date is actually wanted is the one
	 * place there is room for it.
	 */
	function fullSecondaryLabel( system, day ) {
		var date = system.dates[ localKey( day ) ];
		if ( ! date || ! system.icuCalendar || ! window.Intl || ! Intl.DateTimeFormat ) {
			return '';
		}
		try {
			return new Intl.DateTimeFormat( locale, {
				calendar: system.icuCalendar,
				year: 'numeric',
				month: 'long',
				day: 'numeric',
				timeZone: 'UTC'
			} ).format( new Date( Date.UTC( day.getFullYear(), day.getMonth(), day.getDate(), 12 ) ) );
		} catch ( error ) {
			return '';
		}
	}

	/* -- Placing occurrences on days ----------------------------------------------------------- */

	/**
	 * Group occurrences by the day they belong on, in the viewer's zone.
	 *
	 * An occurrence that runs past midnight appears on every day it covers, because somebody looking
	 * at the second day wants to see what is still going on. All-day entries are placed by their
	 * civil date and never converted.
	 */
	function byDay( items ) {
		var map = {};
		function push( key, item ) {
			if ( ! map[ key ] ) {
				map[ key ] = [];
			}
			map[ key ].push( item );
		}
		items.forEach( function ( item ) {
			if ( item.allDay ) {
				var from = civilDate( item.startLocal || item.startUtc );
				var to = civilDate( item.endLocal || item.endUtc );
				var cursor = new Date( from + 'T00:00:00' );
				var last = new Date( to + 'T00:00:00' );
				// An all-day entry ends at midnight of the following day, so the last day it covers is
				// the day before its end. A single-day entry still shows once.
				if ( last > cursor ) {
					last = new Date( last.getTime() - DAY_MS );
				}
				while ( cursor <= last ) {
					push( localKey( cursor ), item );
					cursor = new Date( cursor.getTime() + DAY_MS );
				}
				return;
			}
			/*
			 * Projected here, from the instant, in the reader's own timezone. Not from `startLocal`,
			 * which is the site's reading: a full moon at 00:30Z is the 28th in Seoul and the 27th in
			 * Los Angeles, and the cell it belongs in is the one the person looking at it is in.
			 */
			var start = parseUtc( item.startUtc );
			var end = parseUtc( item.endUtc );
			var day = new Date( start.getFullYear(), start.getMonth(), start.getDate() );
			// A moment has no duration, so the span loop below would place it nowhere. It happens on
			// the day it happens on.
			if ( end <= start ) {
				push( localKey( day ), item );
				return;
			}
			while ( day < end ) {
				push( localKey( day ), item );
				day = new Date( day.getFullYear(), day.getMonth(), day.getDate() + 1 );
			}
		} );
		return map;
	}

	/* -- The sidebar ---------------------------------------------------------------------------- */

	/**
	 * The sidebar mini calendar.
	 *
	 * Core's `DatePicker` rather than a second grid of our own: it already answers month navigation,
	 * roving tabindex, keyboard movement and the week start, and a hand-written copy would answer
	 * some of them worse. It is a date picker and nothing more -- the main grid is not one of these,
	 * because it lays occurrences from several calendars over the same days.
	 *
	 * Absent on a WordPress too old to export it, in which case the sidebar simply has no mini
	 * calendar: the toolbar already moves the cursor, so this is a convenience rather than the only
	 * way to navigate.
	 */
	function MiniCalendar( props ) {
		if ( ! C.DatePicker ) {
			return null;
		}
		return el(
			'div',
			{ className: 'ax-cal-workspace__mini' },
			el( C.DatePicker, {
				currentDate: datePickerValue( props.cursor ),
				startOfWeek: startOfWeek,
				onChange: function ( value ) {
					var picked = pickedDate( value );
					if ( picked ) {
						props.onPick( picked );
					}
				}
			} )
		);
	}

	/**
	 * Calendars that are one dataset in several languages, gathered into one thing to tick.
	 *
	 * 대한민국의 휴일 and Holidays in South Korea are the same holidays written twice. Offering both
	 * asks the reader a question with no good answer: tick both and the grid would be choosing for
	 * them, tick one and they have made a language decision in a control that means content. A
	 * calendar with no catalog is its own group, which is nearly all of them.
	 */
	function groupByCatalog( calendars ) {
		var groups = [];
		var byKey = {};
		calendars.forEach( function ( calendar ) {
			var key = calendar.catalog || ( 'calendar:' + calendar.id );
			if ( ! byKey[ key ] ) {
				byKey[ key ] = { key: key, members: [], label: calendar.summaryOverride || calendar.summary, locales: [] };
				groups.push( byKey[ key ] );
			}
			byKey[ key ].members.push( calendar );
			if ( calendar.locale ) {
				byKey[ key ].locales.push( calendar.locale );
			}
		} );
		return groups;
	}

	/**
	 * The calendars a person could have and does not, which is where a list starts.
	 *
	 * Grouped the same way the sidebar is, and added the same way: one dataset is one thing to add,
	 * and adding it takes every language it has, because the grid draws the day once and chooses the
	 * label. Adding half a dataset would be choosing a language in a control that means content.
	 */
	function BrowseCalendars( props ) {
		return el(
			C.Modal,
			{ title: __( 'Browse calendars', 'axismundi-calendar' ), onRequestClose: props.onClose, className: 'ax-cal-browse' },
			props.loading ? el( C.Spinner, null ) : null,
			! props.loading && 0 === props.calendars.length
				? el( 'p', null, __( 'There is nothing else published here yet.', 'axismundi-calendar' ) )
				: null,
			el(
				'ul',
				{ className: 'ax-cal-browse__list' },
				groupByCatalog( props.calendars ).map( function ( group ) {
					return el(
						'li',
						{ key: group.key, className: 'ax-cal-browse__item' },
						el(
							'div',
							null,
							el( 'strong', null, group.label ),
							group.locales.length > 1
								? el( 'span', { className: 'ax-cal-workspace__role' }, group.locales.join( ' · ' ) )
								: null
						),
						el( C.Button, {
							variant: 'secondary',
							disabled: props.busy,
							onClick: function () {
								props.onAdd( group.members );
							}
						}, __( 'Add', 'axismundi-calendar' ) )
					);
				} )
			)
		);
	}

	/**
	 * How days are written, which is not which calendars you are a member of.
	 *
	 * Deliberately its own section rather than another group in the list above. Ticking a dataset
	 * says what you subscribe to and travels with permissions; ticking 음력 says how you would like
	 * dates spelled, and nobody can grant or revoke it.
	 */
	function SecondaryCalendars( props ) {
		if ( ! props.available.length ) {
			return null;
		}
		/*
		 * One at a time. Two second dates under a number is three numbers in a cell, and by the third
		 * the reader is decoding rather than reading -- the annotation stops being an aid and becomes
		 * something else to parse. Nothing about the model forbids more; this is a judgement about how
		 * much a day cell can carry, made where it is visible rather than enforced in storage.
		 *
		 * `None` is an option rather than an unchecked box, so turning it off is a choice on the same
		 * list as turning it on instead of the absence of one.
		 */
		return el(
			'div',
			{ className: 'ax-cal-workspace__section' },
			el( C.RadioControl, {
				label: __( 'Second date', 'axismundi-calendar' ),
				selected: props.selected.length ? props.selected[ 0 ] : '',
				options: [ { label: __( 'None', 'axismundi-calendar' ), value: '' } ].concat(
					props.available.map( function ( system ) {
						return { label: system.label, value: system.id };
					} )
				),
				onChange: props.onChoose
			} )
		);
	}

	function CalendarList( props ) {
		var owned = props.calendars.filter( function ( calendar ) {
			return 'remote' !== calendar.kind;
		} );
		var subscribed = props.calendars.filter( function ( calendar ) {
			return 'remote' === calendar.kind;
		} );

		function section( title, list, emptyText ) {
			var groups = groupByCatalog( list );
			return el(
				'div',
				{ className: 'ax-cal-workspace__section' },
				el( 'h2', null, title ),
				groups.length
					? el(
						'ul',
						{ className: 'ax-cal-workspace__list' },
						groups.map( function ( group ) {
							// Any language of a dataset being on means the dataset is on. The state cannot
							// diverge through this control, so it cannot accumulate a difference either.
							var on = group.members.some( function ( member ) {
								return !! member.selected;
							} );
							return el(
								'li',
								{ key: group.key },
								el( C.CheckboxControl, {
									label: group.label,
									checked: on,
									disabled: props.busy,
									__nextHasNoMarginBottom: true,
									onChange: function ( next ) {
										group.members.forEach( function ( member ) {
											props.onToggle( member, next );
										} );
									}
								} ),
								group.members.length > 1
									// What it exists in, which is not a choice: the grid shows each day once
									// and picks the label, so this says why two calendars became one row.
									? el( 'span', { className: 'ax-cal-workspace__role' }, group.locales.join( ' · ' ) )
									: null,
								1 === group.members.length && group.members[ 0 ].accessRole && 'owner' !== group.members[ 0 ].accessRole
									? el( 'span', { className: 'ax-cal-workspace__role' }, group.members[ 0 ].accessRole )
									: null
							);
						} )
					)
					: el( 'p', { className: 'ax-cal-workspace__empty' }, emptyText )
			);
		}

		return el(
			'div',
			{ className: 'ax-cal-workspace__sidebar' },
			section( __( 'My calendars', 'axismundi-calendar' ), owned, __( 'No calendars yet.', 'axismundi-calendar' ) ),
			section( __( 'Subscribed', 'axismundi-calendar' ), subscribed, __( 'Nothing subscribed.', 'axismundi-calendar' ) ),
			el(
				'p',
				null,
				el( C.Button, { variant: 'link', href: config.settings }, __( 'Manage calendars', 'axismundi-calendar' ) )
			)
		);
	}

	/* -- The grid --------------------------------------------------------------------------------- */

	function MonthGrid( props ) {
		var placed = byDay( props.items );
		var days = gridDays( props.year, props.month, props.startOfWeek );
		var today = localKey( new Date() );
		var cursorKey = localKey( props.cursor );

		return el(
			'div',
			{ className: 'ax-cal-workspace__grid', role: 'grid', 'aria-label': monthTitle( props.year, props.month ) },
			el(
				'div',
				{ className: 'ax-cal-workspace__weekdays', role: 'row' },
				weekdayNames( props.startOfWeek ).map( function ( name ) {
					return el( 'div', { key: name, role: 'columnheader' }, name );
				} )
			),
			el(
				'div',
				{ className: 'ax-cal-workspace__weeks' },
				days.map( function ( day, dayIndex ) {
					var key = localKey( day );
					var entries = placed[ key ] || [];
					var outside = day.getMonth() !== props.month;
					var pick = function ( offset ) {
						return new Date( day.getFullYear(), day.getMonth(), day.getDate() + offset );
					};
					return el(
						'div',
						{
							key: key,
							role: 'gridcell',
							/*
							 * Reachable by keyboard, because a cell that only answers a mouse is a cell
							 * half the people using this screen cannot select. Roving tabindex: the
							 * selected day is the one tab stop, and arrow keys move within the grid the
							 * way the mini calendar does.
							 */
							tabIndex: key === cursorKey ? 0 : -1,
							'aria-selected': key === cursorKey,
							className: 'ax-cal-workspace__day'
								+ ( outside ? ' is-outside' : '' )
								+ ( key === today ? ' is-today' : '' )
								+ ( key === cursorKey ? ' is-selected' : '' ),
							// Selecting the day, which is all a click does for now. What goes here next
							// is the quick-create draft, and putting the selection in first is what
							// proves the mini calendar and the grid share one cursor.
							onClick: function () {
								props.onPickDay( pick( 0 ) );
							},
							onKeyDown: function ( event ) {
								var moves = { ArrowLeft: -1, ArrowRight: 1, ArrowUp: -7, ArrowDown: 7 };
								if ( 'Enter' === event.key || ' ' === event.key ) {
									event.preventDefault();
									props.onPickDay( pick( 0 ) );
									return;
								}
								if ( Object.prototype.hasOwnProperty.call( moves, event.key ) ) {
									event.preventDefault();
									props.onPickDay( pick( moves[ event.key ] ) );
								}
							}
						},
						el(
						'div',
						{ className: 'ax-cal-workspace__daynum' },
						/*
						 * The number is its own element. Today's marker is a circle sized to a digit, and
						 * while the number and the second date shared one box the circle was drawn around
						 * both and the second date ended up inside it.
						 */
						el( 'span', { className: 'ax-cal-workspace__daynum-value' }, day.getDate() ),
						/*
						 * The same day said another way, not another day. It sits with the number rather
						 * than with the events because it is part of what this cell *is*, and an entry in
						 * the list would read as something happening.
						 *
						 * A system with nothing to say about this day contributes nothing. An empty slot
						 * under every number would be the provider promising an answer it does not have.
						 */
						( props.secondary || [] ).map( function ( system ) {
							/*
							 * Not every day. A number under all 42 cells says the same thing 42 times and
							 * leaves the grid looking half-filled before a single event is on it; what a
							 * reader actually needs is a fix often enough to count from.
							 *
							 * So: the start of each week, and the first of the second calendar's month --
							 * which is the day worth noticing anyway, and the one that carries the month.
							 * This is the convention printed Korean calendars and Samsung's use.
							 */
							var date = system.dates[ localKey( day ) ];
							var anchor = 0 === dayIndex % 7 || ( date && 1 === Number( date.day ) );
							var label = anchor ? secondaryLabel( system, day ) : '';
							return label
								? el( 'span', {
									key: system.id,
									className: 'ax-cal-workspace__secondary',
									title: fullSecondaryLabel( system, day ),
									// Not read out with every date. A screen reader moving through a month
									// would hear two numbers per cell with nothing saying why.
									'aria-hidden': true
								}, label )
								: null;
						} )
					),
						entries.map( function ( item, index ) {
							return el(
								'button',
								{
									type: 'button',
									key: key + '-' + index,
									className: 'ax-cal-workspace__event' + ( item.readOnly ? ' is-readonly' : '' ),
									onClick: function ( event ) {
										// Otherwise the cell beneath also handles it and the day moves
										// while a dialog about one of its events opens.
										event.stopPropagation();
										props.onSelect( item );
									}
								},
								timeLabel( item ) ? el( 'span', { className: 'ax-cal-workspace__time' }, timeLabel( item ) ) : null,
								el( 'span', { className: 'ax-cal-workspace__summary' }, item.summary )
							);
						} )
					);
				} )
			)
		);
	}

	/* -- The detail panel ---------------------------------------------------------------------------- */

	function EventPanel( props ) {
		var item = props.item;
		if ( ! item ) {
			return null;
		}
		var start = parseUtc( item.startUtc );
		var end = parseUtc( item.endUtc );
		var when = item.allDay
			? civilDate( item.startLocal || item.startUtc )
			: start.toLocaleString( locale ) + ' – ' + end.toLocaleTimeString( locale, { hour: 'numeric', minute: '2-digit' } );

		return el(
			C.Modal,
			{ title: item.summary, onRequestClose: props.onClose },
			el( 'p', null, when ),
			item.recurring ? el( 'p', null, __( 'Part of a repeating event.', 'axismundi-calendar' ) ) : null,
			item.readOnly
				? el( 'p', null, __( 'From a subscribed calendar. It is read-only here and changes at its source.', 'axismundi-calendar' ) )
				: null,
			el(
				'p',
				null,
				item.url ? el( C.Button, { variant: 'secondary', href: item.url }, __( 'View', 'axismundi-calendar' ) ) : null,
				' ',
				! item.readOnly && item.eventId
					? el(
						C.Button,
						{ variant: 'primary', href: window.wp.url ? window.wp.url.addQueryArgs( 'post.php', { post: item.eventId, action: 'edit' } ) : 'post.php?action=edit&post=' + item.eventId },
						__( 'Edit', 'axismundi-calendar' )
					)
					: null
			)
		);
	}

	/* -- The screen ------------------------------------------------------------------------------------ */

	function Workspace() {
		var now = new Date();
		/*
		 * One date, not a month. Every view this screen will grow -- day, four-day, week, year --
		 * computes its own period from the same cursor, and a month-shaped state would have to be
		 * widened or duplicated for each of them.
		 */
		var cursorState = useState( startOfDay( now ) );
		var cursor = cursorState[ 0 ];
		var setCursor = cursorState[ 1 ];
		var year = cursor.getFullYear();
		var month = cursor.getMonth();

		var calendarState = useState( null );
		var calendars = calendarState[ 0 ];
		var setCalendars = calendarState[ 1 ];

		var itemState = useState( [] );
		var items = itemState[ 0 ];
		var setItems = itemState[ 1 ];

		var busyState = useState( false );
		var busy = busyState[ 0 ];
		var setBusy = busyState[ 1 ];

		var errorState = useState( '' );
		var error = errorState[ 0 ];
		var setError = errorState[ 1 ];

		var selectedState = useState( null );
		var selected = selectedState[ 0 ];
		var setSelected = selectedState[ 1 ];

		var truncatedState = useState( false );
		var truncated = truncatedState[ 0 ];
		var setTruncated = truncatedState[ 1 ];

		// Whether the one bootstrap request has answered. Without it the range effect fires on the
		// first render as well, and the screen makes the two requests the bootstrap exists to avoid.
		var loadedState = useState( false );
		var loaded = loadedState[ 0 ];
		var setLoaded = loadedState[ 1 ];

		var secondaryState = useState( { available: [], selected: [], dates: {} } );
		var secondary = secondaryState[ 0 ];
		var setSecondary = secondaryState[ 1 ];

		var browsingState = useState( false );
		var browsing = browsingState[ 0 ];
		var setBrowsing = browsingState[ 1 ];

		// null until asked. The discovery list is not part of the first paint: it answers a question
		// nobody has asked yet, and fetching it with the month would slow down the screen that has.
		var discoveryState = useState( null );
		var discovery = discoveryState[ 0 ];
		var setDiscovery = discoveryState[ 1 ];

		function report( failure ) {
			setError( ( failure && failure.message ) || __( 'The calendar could not be loaded.', 'axismundi-calendar' ) );
		}

		// The first paint is one request. Asking for the list and then the month is a waterfall: the
		// grid cannot start until the sidebar has come back and said which calendars are ticked, so it
		// arrives visibly later even when both are quick.
		useEffect( function () {
			var days = gridDays( year, month, startOfWeek );
			setBusy( true );
			apiFetch( {
				path: wp.url.addQueryArgs( '/' + config.namespace + '/actors/me/calendarWorkspace', {
					start: days[ 0 ].toISOString(),
					end: new Date( days[ 41 ].getTime() + DAY_MS ).toISOString()
				} )
			} )
				.then( function ( response ) {
					setCalendars( response.calendars || [] );
					setItems( ( response.view && response.view.items ) || [] );
					setTruncated( !! ( response.view && response.view.truncated ) );
					setLoaded( true );
				} )
				.catch( function ( failure ) {
					report( failure );
					setLoaded( true );
				} )
				.finally( function () {
					setBusy( false );
				} );
		}, [] );

		var ticked = ( calendars || [] )
			.filter( function ( calendar ) {
				return calendar.selected && ! calendar.hidden;
			} )
			.map( function ( calendar ) {
				return calendar.id;
			} );
		var tickedKey = ticked.join( ',' );

		// Afterwards only the range changes, so only the range is fetched. Skipped on the first pass,
		// which the request above has already answered.
		useEffect( function () {
			if ( null === calendars || ! loaded ) {
				return;
			}
			if ( ! ticked.length ) {
				// Unticking everything is a thing people do, and an empty month is the right answer to
				// it rather than an error or the last month left on screen.
				setItems( [] );
				setTruncated( false );
				return;
			}
			// A whole grid, not a whole month: the six weeks drawn include days either side, and asking
			// only for the month would leave those cells empty while looking complete.
			var days = gridDays( year, month, startOfWeek );
			setBusy( true );
			apiFetch( {
				path: wp.url.addQueryArgs( '/' + config.namespace + '/actors/me/calendarView', {
					calendars: tickedKey,
					// A hint, not a decision. The server picks the label so this grid, the feed and the
					// calendar page cannot disagree about the same day.
					languages: ( navigator.languages || [] ).slice( 0, 10 ).join( ',' ),
					start: days[ 0 ].toISOString(),
					end: new Date( days[ 41 ].getTime() + DAY_MS ).toISOString()
				} )
			} )
				.then( function ( response ) {
					setItems( response.items || [] );
					setTruncated( !! response.truncated );
					setError( '' );
				} )
				.catch( report )
				.finally( function () {
					setBusy( false );
				} );
		}, [ tickedKey, monthKey( cursor ), loaded ] );

		/*
		 * Its own request, not part of the bootstrap. Most people have no second date turned on, and
		 * the month grid should not wait on an answer that is usually "none" -- when it does arrive
		 * it adds a line to cells that are already drawn rather than holding them back.
		 */
		useEffect( function () {
			var days = gridDays( year, month, startOfWeek );
			apiFetch( {
				path: wp.url.addQueryArgs( '/' + config.namespace + '/actors/me/secondaryCalendars', {
					start: localKey( days[ 0 ] ),
					end: localKey( days[ 41 ] )
				} )
			} )
				.then( setSecondary )
				.catch( function () {
					// Silent. A second date failing to arrive is not a reason to put an error over a
					// calendar that is otherwise complete and correct.
				} );
		}, [ monthKey( cursor ) ] );

		// In the order they were chosen, so two systems do not swap places between renders.
		var secondaryAvailable = {};
		( secondary.available || [] ).forEach( function ( system ) {
			secondaryAvailable[ system.id ] = system;
		} );
		var secondaryShown = ( secondary.selected || [] )
			.filter( function ( id ) {
				return secondary.dates && secondary.dates[ id ] && secondaryAvailable[ id ];
			} )
			.map( function ( id ) {
				return {
					id: id,
					dates: secondary.dates[ id ],
					icuCalendar: secondaryAvailable[ id ].icuCalendar,
					notation: secondaryAvailable[ id ].notation
				};
			} );

		function chooseSecondary( id ) {
			// '' is None. The preference stays a list because the server and the grid both handle one,
			// and narrowing the stored shape to match a control would be letting the sidebar decide
			// what the model is allowed to say.
			var chosen = id ? [ id ] : [];
			var days = gridDays( year, month, startOfWeek );
			// The server answers with the dates for the current month in the same round trip, so the
			// grid fills in at the moment the box is ticked rather than a request later.
			apiFetch( {
				path: wp.url.addQueryArgs( '/' + config.namespace + '/actors/me/secondaryCalendars', {
					start: localKey( days[ 0 ] ),
					end: localKey( days[ 41 ] )
				} ),
				method: 'PUT',
				data: { systems: chosen }
			} )
				.then( setSecondary )
				.catch( report );
		}

		function openBrowse() {
			setBrowsing( true );
			setDiscovery( null );
			apiFetch( { path: '/' + config.namespace + '/actors/me/calendarDiscovery' } )
				.then( function ( response ) {
					setDiscovery( response.items || [] );
				} )
				.catch( function ( failure ) {
					setDiscovery( [] );
					report( failure );
				} );
		}

		/*
		 * Adding is one request per language of the dataset, and the screen only changes once they
		 * have all answered. A half-added dataset drawn as if it were whole would be a lie the next
		 * reload corrects, and this is not the optimistic case a checkbox is: nobody is waiting on a
		 * control they just clicked, they are waiting to see a calendar appear.
		 */
		function add( members ) {
			setBusy( true );
			Promise.all( members.map( function ( member ) {
				return apiFetch( {
					path: '/' + config.namespace + '/actors/me/calendarList/' + member.id,
					method: 'PUT',
					data: { selected: true }
				} );
			} ) )
				.then( function ( added ) {
					setCalendars( ( calendars || [] ).concat( added ) );
					setDiscovery( ( discovery || [] ).filter( function ( entry ) {
						return ! members.some( function ( member ) {
							return member.id === entry.id;
						} );
					} ) );
				} )
				.catch( report )
				.finally( function () {
					setBusy( false );
				} );
		}

		function toggle( calendar, next ) {
			// Optimistic, because the answer is already known and a checkbox that waits for a round
			// trip feels broken. A failure puts it back and says so.
			setCalendars( ( calendars || [] ).map( function ( entry ) {
				return entry.id === calendar.id ? Object.assign( {}, entry, { selected: next } ) : entry;
			} ) );
			apiFetch( {
				path: '/' + config.namespace + '/actors/me/calendarList/' + calendar.id,
				method: 'PUT',
				data: { selected: next }
			} ).catch( function ( failure ) {
				setCalendars( ( calendars || [] ).map( function ( entry ) {
					return entry.id === calendar.id ? Object.assign( {}, entry, { selected: ! next } ) : entry;
				} ) );
				report( failure );
			} );
		}

		/*
		 * Moving a month keeps the day of the month where the target has one, so the mini calendar's
		 * selection follows the toolbar instead of jumping to the 1st. Clamped for the months that are
		 * shorter: the 31st of a 30-day month is the 30th, not the 1st of the month after.
		 */
		function move( delta ) {
			var target = new Date( year, month + delta, 1 );
			var lastDay = new Date( target.getFullYear(), target.getMonth() + 1, 0 ).getDate();
			setCursor( new Date( target.getFullYear(), target.getMonth(), Math.min( cursor.getDate(), lastDay ) ) );
		}

		if ( null === calendars ) {
			return el( C.Spinner, null );
		}

		return el(
			Fragment,
			null,
			el(
				'div',
				{ className: 'ax-cal-workspace__bar' },
				el( C.Button, { variant: 'secondary', onClick: function () {
					setCursor( startOfDay( new Date() ) );
				} }, __( 'Today', 'axismundi-calendar' ) ),
				el( C.Button, { icon: 'arrow-left-alt2', label: __( 'Previous month', 'axismundi-calendar' ), onClick: function () {
					move( -1 );
				} } ),
				el( C.Button, { icon: 'arrow-right-alt2', label: __( 'Next month', 'axismundi-calendar' ), onClick: function () {
					move( 1 );
				} } ),
				el( 'h1', { className: 'ax-cal-workspace__title' }, monthTitle( year, month ) ),
				busy ? el( C.Spinner, null ) : null,
				el( C.Button, { variant: 'primary', href: config.newEvent }, __( 'Add event', 'axismundi-calendar' ) )
			),
			error
				? el( C.Notice, { status: 'error', isDismissible: false }, error )
				: null,
			truncated
				? el(
					C.Notice,
					{ status: 'warning', isDismissible: false },
					__( 'This month has more events than one request returns. Some are not shown.', 'axismundi-calendar' )
				)
				: null,
			el(
				'div',
				{ className: 'ax-cal-workspace__body' },
				el(
					'div',
					{ className: 'ax-cal-workspace__aside' },
					el( MiniCalendar, { cursor: cursor, onPick: setCursor } ),
					el( CalendarList, { calendars: calendars, busy: busy, onToggle: toggle } ),
					el( C.Button, { variant: 'link', onClick: openBrowse }, __( 'Browse calendars', 'axismundi-calendar' ) ),
					el( SecondaryCalendars, {
						available: secondary.available || [],
						selected: secondary.selected || [],
						onChoose: chooseSecondary
					} )
				),
				el( MonthGrid, {
					items: items,
					secondary: secondaryShown,
					year: year,
					month: month,
					cursor: cursor,
					startOfWeek: startOfWeek,
					onPickDay: setCursor,
					onSelect: setSelected
				} )
			),
			el( EventPanel, { item: selected, onClose: function () {
				setSelected( null );
			} } ),
			browsing
				? el( BrowseCalendars, {
					calendars: discovery,
					loading: null === discovery,
					busy: busy,
					onAdd: add,
					onClose: function () {
						setBrowsing( false );
					}
				} )
				: null
		);
	}

	wp.element.createRoot
		? wp.element.createRoot( document.getElementById( 'ax-cal-workspace' ) ).render( el( Workspace ) )
		: wp.element.render( el( Workspace ), document.getElementById( 'ax-cal-workspace' ) );
}( window.wp, window.axismundiCalendarWorkspace || {} ) );
