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

	function monthTitle( year, month ) {
		return new Date( year, month, 1 ).toLocaleDateString( locale, { year: 'numeric', month: 'long' } );
	}

	function timeLabel( item ) {
		if ( item.allDay ) {
			return '';
		}
		return parseUtc( item.startUtc ).toLocaleTimeString( locale, { hour: 'numeric', minute: '2-digit' } );
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
			var start = parseUtc( item.startUtc );
			var end = parseUtc( item.endUtc );
			var day = new Date( start.getFullYear(), start.getMonth(), start.getDate() );
			while ( day < end ) {
				push( localKey( day ), item );
				day = new Date( day.getFullYear(), day.getMonth(), day.getDate() + 1 );
			}
		} );
		return map;
	}

	/* -- The sidebar ---------------------------------------------------------------------------- */

	function CalendarList( props ) {
		var owned = props.calendars.filter( function ( calendar ) {
			return 'remote' !== calendar.kind;
		} );
		var subscribed = props.calendars.filter( function ( calendar ) {
			return 'remote' === calendar.kind;
		} );

		function section( title, list, emptyText ) {
			return el(
				'div',
				{ className: 'ax-cal-workspace__section' },
				el( 'h2', null, title ),
				list.length
					? el(
						'ul',
						{ className: 'ax-cal-workspace__list' },
						list.map( function ( calendar ) {
							return el(
								'li',
								{ key: calendar.id },
								el( C.CheckboxControl, {
									label: calendar.summaryOverride || calendar.summary,
									checked: !! calendar.selected,
									disabled: props.busy,
									__nextHasNoMarginBottom: true,
									onChange: function ( next ) {
										props.onToggle( calendar, next );
									}
								} ),
								calendar.accessRole && 'owner' !== calendar.accessRole
									? el( 'span', { className: 'ax-cal-workspace__role' }, calendar.accessRole )
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
				days.map( function ( day ) {
					var key = localKey( day );
					var entries = placed[ key ] || [];
					var outside = day.getMonth() !== props.month;
					return el(
						'div',
						{
							key: key,
							role: 'gridcell',
							className: 'ax-cal-workspace__day'
								+ ( outside ? ' is-outside' : '' )
								+ ( key === today ? ' is-today' : '' )
						},
						el( 'div', { className: 'ax-cal-workspace__daynum' }, day.getDate() ),
						entries.map( function ( item, index ) {
							return el(
								'button',
								{
									type: 'button',
									key: key + '-' + index,
									className: 'ax-cal-workspace__event' + ( item.readOnly ? ' is-readonly' : '' ),
									onClick: function () {
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
		var monthState = useState( { year: now.getFullYear(), month: now.getMonth() } );
		var cursor = monthState[ 0 ];
		var setCursor = monthState[ 1 ];

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

		function report( failure ) {
			setError( ( failure && failure.message ) || __( 'The calendar could not be loaded.', 'axismundi-calendar' ) );
		}

		useEffect( function () {
			apiFetch( { path: '/' + config.namespace + '/actors/me/calendarList' } )
				.then( function ( response ) {
					setCalendars( response.items || [] );
				} )
				.catch( report );
		}, [] );

		var ticked = ( calendars || [] )
			.filter( function ( calendar ) {
				return calendar.selected && ! calendar.hidden;
			} )
			.map( function ( calendar ) {
				return calendar.id;
			} );
		var tickedKey = ticked.join( ',' );

		useEffect( function () {
			if ( null === calendars ) {
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
			var days = gridDays( cursor.year, cursor.month, startOfWeek );
			setBusy( true );
			apiFetch( {
				path: wp.url.addQueryArgs( '/' + config.namespace + '/actors/me/calendarView', {
					calendars: tickedKey,
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
		}, [ tickedKey, cursor.year, cursor.month, null === calendars ] );

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

		function move( delta ) {
			var next = new Date( cursor.year, cursor.month + delta, 1 );
			setCursor( { year: next.getFullYear(), month: next.getMonth() } );
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
					var today = new Date();
					setCursor( { year: today.getFullYear(), month: today.getMonth() } );
				} }, __( 'Today', 'axismundi-calendar' ) ),
				el( C.Button, { icon: 'arrow-left-alt2', label: __( 'Previous month', 'axismundi-calendar' ), onClick: function () {
					move( -1 );
				} } ),
				el( C.Button, { icon: 'arrow-right-alt2', label: __( 'Next month', 'axismundi-calendar' ), onClick: function () {
					move( 1 );
				} } ),
				el( 'h1', { className: 'ax-cal-workspace__title' }, monthTitle( cursor.year, cursor.month ) ),
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
				el( CalendarList, { calendars: calendars, busy: busy, onToggle: toggle } ),
				el( MonthGrid, {
					items: items,
					year: cursor.year,
					month: cursor.month,
					startOfWeek: startOfWeek,
					onSelect: setSelected
				} )
			),
			el( EventPanel, { item: selected, onClose: function () {
				setSelected( null );
			} } )
		);
	}

	wp.element.createRoot
		? wp.element.createRoot( document.getElementById( 'ax-cal-workspace' ) ).render( el( Workspace ) )
		: wp.element.render( el( Workspace ), document.getElementById( 'ax-cal-workspace' ) );
}( window.wp, window.axismundiCalendarWorkspace || {} ) );
