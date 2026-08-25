/**
 * The Card editor.
 *
 * One draft, three ways of looking at it. The fields, the parts of a name and the JSON box are views
 * of the same object in memory, so a value this editor has no field for is still there when the JSON
 * box is opened and still there when it is saved. That is the whole reason the draft is one object
 * rather than a form: a Card carries `contexts`, `personalInfo`, a vendor's own property, and an
 * editor that rebuilt the document from its inputs would drop every one of them.
 *
 * It saves through the draft route and nowhere else. The revision it was read at goes back with it,
 * so a save written against a version somebody else has replaced is refused rather than merged, and
 * what comes back is what is stored -- read from the database rather than echoed, in the order it is
 * stored in.
 *
 * No JSX, no build -- plain wp.element.createElement, as the calendar workspace does.
 */
( function ( wp, config ) {
	'use strict';

	var root = document.getElementById( 'ax-contacts-card-editor' );
	if ( ! wp || ! wp.element || ! root || ! config ) {
		return;
	}

	var el = wp.element.createElement;
	var Fragment = wp.element.Fragment;
	// The field primitives, which own the markup and the states so this file can own the Card.
	var fields = window.axismundiContactsFields || {};
	var TextField = fields.TextField;
	var Textarea = fields.Textarea;
	var IconButton = fields.IconButton;
	var Combobox = fields.Combobox;
	var useState = wp.element.useState;
	var useCallback = wp.element.useCallback;
	var apiFetch = wp.apiFetch;
	var __ = wp.i18n.__;
	var sprintf = wp.i18n.sprintf;

	/** How many lines this screen has opened, so each of them can be told from the others. */
	var rows = 0;

	/** One icon's markup, from the registry this plugin fills. */
	function icon( name ) {
		return ( window.axismundiContactsIcons || {} )[ name ] || '';
	}

	/**
	 * What each kind of card looks like at a glance.
	 *
	 * Beside the name rather than beside the word "Name", because what sits there is what this card is
	 * about: a person, a company, a building, a machine. An icon of a person on a card describing an
	 * office would be the screen saying something the card does not, and the radio at the top is
	 * exactly where somebody changes their mind about that -- so this follows it.
	 *
	 * A kind nothing here recognises gets the address-book mark rather than a guess. Somebody else's
	 * vendor value is a real answer and drawing a person for it would be inventing one.
	 */
	var KIND_ICONS = {
		individual: 'person',
		org: 'domain',
		group: 'group',
		location: 'location-on',
		application: 'apps',
		device: 'devices'
	};

	function kindIcon( kind ) {
		return KIND_ICONS[ kind || 'individual' ] || 'contacts';
	}

	/**
	 * A section, with what it is about drawn once beside it.
	 *
	 * Once, and not on every row. Six rows of email addresses do not each need telling that they are
	 * email addresses, and an icon repeated down a column is a column of noise -- so it sits at the
	 * top of the stack, on the first line, where a heading would be.
	 */
	function Section( props ) {
		return el(
			'section',
			{ className: 'ax-ce-section is-marked' + ( props.className ? ' ' + props.className : '' ) },
			el(
				'div',
				{ className: 'ax-ce-section__mark', 'aria-hidden': 'true', dangerouslySetInnerHTML: { __html: icon( props.icon ) } }
			),
			el(
				'div',
				{ className: 'ax-ce-section__body' },
				props.title ? el( 'h2', null, props.title ) : el( 'h2', { className: 'screen-reader-text' }, props.label ),
				props.children
			)
		);
	}

	/** Properties this draws rows for, and what each entry's value is called. */
	var ENTRY_FIELDS = [
		{ key: 'emails', label: __( 'Email', 'axismundi-contacts' ), value: 'address', type: 'email', icon: 'mail' },
		{ key: 'links', label: __( 'Links', 'axismundi-contacts' ), value: 'uri', type: 'url', icon: 'link' },
		{ key: 'media', label: __( 'Media', 'axismundi-contacts' ), value: 'uri', type: 'url', icon: 'image' },
		{ key: 'notes', label: __( 'Notes', 'axismundi-contacts' ), value: 'note', icon: 'notes' }
	];

	/** The component kinds a name is made of, plus the separator that goes between them. */
	var COMPONENT_KINDS = [ 'title', 'given', 'given2', 'surname', 'surname2', 'credential', 'separator' ];

	/**
	 * Read what a name says, the way RFC 9553 leaves to whoever shows it.
	 *
	 * The written-out form when there is one, and the parts joined when there is not. Worked out here
	 * for display only: nothing this returns is written into the draft unless somebody asks for it.
	 */
	function nameText( name ) {
		if ( ! name ) {
			return '';
		}
		if ( name.full && name.full.trim() ) {
			return name.full;
		}
		var parts = name.components || [];
		var separator = typeof name.defaultSeparator === 'string' ? name.defaultSeparator : ' ';
		var out = '';
		var pending = '';
		var written = false;
		parts.forEach( function ( part ) {
			if ( ! part ) {
				return;
			}
			if ( 'separator' === part.kind ) {
				pending = part.value || '';
				return;
			}
			var value = ( part.value || '' ).trim();
			if ( ! value ) {
				return;
			}
			if ( written ) {
				out += pending;
			}
			out += value;
			pending = separator;
			written = true;
		} );
		return out;
	}

	/** A copy of an object with one key set, or removed when the value is empty. */
	function withKey( object, key, value ) {
		var next = Object.assign( {}, object || {} );
		if ( '' === value || undefined === value || null === value ) {
			delete next[ key ];
		} else {
			next[ key ] = value;
		}
		return next;
	}

	/**
	 * The languages this site offers, wherever somebody names one.
	 *
	 * Not a list of this plugin's own. A Note's language, an Actor's `nameMap` and a Card all name
	 * the same languages, and a second list invented here would be a second answer to a question the
	 * site has already answered -- the kind nobody notices until the two disagree.
	 *
	 * Anything may still be typed. BCP 47 has far more tags than belong in a menu -- `ko-Latn` is
	 * Korean written in Latin letters and `ja-Kana` is Japanese in kana, both ordinary answers and
	 * neither in any short list -- and what is typed is written down the way the Actor registry
	 * writes tags, by the store rather than by the screen.
	 */
	var LANGUAGES = config.languages || [];

	/**
	 * What kind of thing this Card describes.
	 *
	 * First, because it decides what the rest of the card is for: a person has a given name, an
	 * organisation has units, a group has members. Asking it after somebody has filled the card in is
	 * asking them to reconsider what they have already written.
	 *
	 * On the Card an Actor publishes about itself it is not a question at all. That Actor is a Person,
	 * a Group or an Organization in a registry that federates, and a Card claiming otherwise would say
	 * one thing to a reader and the Actor document another. Shown, and shown as decided.
	 */
	function KindField( props ) {
		var locked = props.locked;
		return el(
			'section',
			{ className: 'ax-ce-section' },
			el( 'h2', null, __( 'What this is', 'axismundi-contacts' ) ),
			el(
				'div',
				{ className: 'ax-ce-kinds', role: 'radiogroup', 'aria-label': __( 'What this card describes', 'axismundi-contacts' ) },
				config.kinds.map( function ( kind ) {
					return el(
						'label',
						{ key: kind.value, className: 'ax-ce-kind' + ( locked ? ' is-locked' : '' ) },
						el( 'input', {
							type: 'radio',
							name: 'ax-ce-kind',
							value: kind.value,
							checked: ( props.value || 'individual' ) === kind.value,
							disabled: !! locked,
							onChange: function () {
								props.onChange( kind.value );
							}
						} ),
						' ',
						kind.label
					);
				} )
			),
			locked
				? el(
					'p',
					{ className: 'description' },
					__( 'This is the card an Actor publishes about itself, so what it describes is what that Actor is.', 'axismundi-contacts' )
				)
				: null
		);
	}

	/**
	 * The language this card is written in.
	 *
	 * Not the languages this person prefers to be written to in -- that is `preferredLanguages`, a
	 * ranked list further down, and the two are asked with the same control because they are the same
	 * kind of answer, never from the same value because they are different questions. This one says
	 * what the card above says it in, and what every localization is a translation *of*.
	 */
	function CardLanguage( props ) {
		return el( Combobox, {
			label: __( 'Language', 'axismundi-contacts' ),
			hideLabel: true,
			value: props.value || '',
			options: LANGUAGES,
			allowFree: true,
			supporting: __( 'What the card above says it in. Anything BCP 47 allows, whether it is on the list or not.', 'axismundi-contacts' ),
			onChange: props.onChange
		} );
	}

	/** What each part of a name is called where somebody reads it. */
	function componentLabel( kind ) {
		var labels = {
			title: __( 'Title', 'axismundi-contacts' ),
			given: __( 'Given name', 'axismundi-contacts' ),
			given2: __( 'Given name 2', 'axismundi-contacts' ),
			surname: __( 'Surname', 'axismundi-contacts' ),
			surname2: __( 'Surname 2', 'axismundi-contacts' ),
			credential: __( 'Credential', 'axismundi-contacts' ),
			separator: __( 'Separator', 'axismundi-contacts' )
		};
		return labels[ kind ] || kind;
	}

	/** Whether a name already has a part of some kind. */
	function hasKind( components, kind ) {
		return ( components || [] ).some( function ( part ) {
			return part && kind === part.kind;
		} );
	}

	/**
	 * The parts a name is filled in as, in the order they are read down the screen.
	 *
	 * Not the JSContact model with a form around it. Somebody writing down a person's name is filling
	 * in a name, and the shape of the document that results -- that each of these is an object in an
	 * ordered list, that the list may hold two of some of them, that a separator is one of them -- is
	 * true and is not what they came to do. So the ordinary way in is a stack of fields, and the
	 * document underneath it is reached by asking for it.
	 */
	var NAME_SLOTS = [ 'title', 'given', 'given2', 'surname', 'surname2', 'credential' ];

	/** The two a name usually is, which is what is open before anybody asks for more. */
	var BASIC_SLOTS = [ 'given', 'surname' ];

	/**
	 * The lines that stay where they are.
	 *
	 * A title opens a name and letters after it close one. Neither is somewhere in the middle of how
	 * a name is read, so neither is something to drag: the two are drawn at the ends and the parts
	 * between them are what has an order worth arranging.
	 */
	var FIXED_FIRST = 'title';
	var FIXED_LAST = 'credential';
	var MIDDLE_SLOTS = [ 'given', 'given2', 'surname', 'surname2' ];

	/**
	 * The parts somebody adds themselves, which are the ones there is a point in removing.
	 *
	 * A separator among them. It is a line like the others -- added at the end and dragged where it
	 * belongs, the same as a second middle name -- rather than a control hidden in the space between
	 * two rows, which put a button between every pair of lines to serve the one card in fifty that
	 * wants one.
	 */
	var ADDABLE = [ 'given2', 'surname2', 'separator' ];

	/**
	 * The lines to draw for a set of kinds: what the name has, in the order it has it, then a line
	 * for each one it does not.
	 *
	 * An empty line is a place to type and nothing else. It is not a part of the name, it is not in
	 * the document, and opening the screen it appears on does not put it there -- which is the whole
	 * difference between a field and a record.
	 */
	function slotRows( components, kinds, pending, everything ) {
		var seen = {};
		var rows = [];
		( components || [] ).forEach( function ( part, index ) {
			if ( ! part ) {
				return;
			}
			/*
			 * Everything the middle of a name holds, not only the kinds that have a line of their own.
			 * A separator, a second middle name, a kind out of some other system's export: each is a
			 * part of somebody's name and each gets a row. A screen that could only draw six kinds had
			 * to turn into a different screen when it met a seventh, and turning into a different
			 * screen is not something an editor should do to somebody mid-name.
			 */
			var mine = everything
				? -1 === NAME_SLOTS.indexOf( part.kind ) || -1 !== kinds.indexOf( part.kind )
				: -1 !== kinds.indexOf( part.kind );
			if ( ! mine ) {
				return;
			}
			/*
			 * Which one of its kind this is, counted down the document. That is the row's name for as
			 * long as it is on screen -- not the kind alone, because a name may have two middle names
			 * and two rows called `given2` are one row as far as React is concerned: editing the
			 * second would change the first.
			 */
			seen[ part.kind ] = ( seen[ part.kind ] || 0 ) + 1;
			rows.push( {
				key: part.kind + '#' + ( seen[ part.kind ] - 1 ),
				kind: part.kind,
				part: part,
				index: index
			} );
		} );
		kinds.forEach( function ( kind ) {
			if ( ! seen[ kind ] ) {
				rows.push( { key: kind + '#0', kind: kind, part: null } );
			}
		} );
		rows.forEach( function ( row ) {
			// Which one of its kind it is, so a line that is one of several knows whether it is the
			// first -- the first `given` is the section, and a second is something somebody added.
			row.occurrence = Number( row.key.split( '#' )[ 1 ] );
		} );
		/*
		 * And the rows somebody asked for that the document has nothing for yet. A row is a place to
		 * type: it becomes a part of the name when somebody types in it, and it keeps its name when it
		 * does -- the second `given2` a person adds is `given2#1` before and after they fill it in, so
		 * the field they are typing into is not replaced under them.
		 */
		( pending || [] ).forEach( function ( row ) {
			// The same rule the parts follow: a kind with no line of its own still belongs somewhere.
			var mine = everything
				? -1 === NAME_SLOTS.indexOf( row.kind ) || -1 !== kinds.indexOf( row.kind )
				: -1 !== kinds.indexOf( row.kind );
			if ( ! mine ) {
				return;
			}
			seen[ row.kind ] = ( seen[ row.kind ] || 0 ) + 1;
			rows.push( {
				key: row.kind + '#' + ( seen[ row.kind ] - 1 ),
				kind: row.kind,
				part: null,
				pendingId: row.id
			} );
		} );
		return rows;
	}

	/**
	 * The parts, with the two that belong at the ends put back at the ends.
	 *
	 * Applied after anything that moves a part. A name whose letters after it ended up in the middle
	 * is a name somebody has to tidy up after every drag, and the standard's reading order is exactly
	 * what the list is for.
	 */
	function endsFirstAndLast( components ) {
		var first = [];
		var middle = [];
		var last = [];
		( components || [] ).forEach( function ( part ) {
			if ( part && FIXED_FIRST === part.kind ) {
				first.push( part );
			} else if ( part && FIXED_LAST === part.kind ) {
				last.push( part );
			} else {
				middle.push( part );
			}
		} );
		return first.concat( middle, last );
	}

	/**
	 * The parts a pronunciation belongs to.
	 *
	 * A separator is `-` or `・`; a title is `Dr`. Neither is somebody's name being said, so neither
	 * gets a column for how it sounds.
	 */
	var PHONETIC_SLOTS = [ 'given', 'given2', 'surname', 'surname2' ];

	/** Where the first part of some kind sits, or -1. */
	function slotIndex( components, kind ) {
		return ( components || [] ).findIndex( function ( part ) {
			return part && kind === part.kind;
		} );
	}

	/** Whether any part of this name says how it is said. */
	function hasPhonetic( components ) {
		return ( components || [] ).some( function ( part ) {
			return part && part.phonetic && String( part.phonetic ).trim();
		} );
	}

	/** Where a part of some kind goes, so the parts stay in the order the fields are read in. */
	function slotInsertion( components, kind ) {
		var list = components || [];
		/*
		 * Next to the part it extends, where there is one. A second middle name belongs after the
		 * first given name and a second surname after the first surname -- and which of those comes
		 * first is the name's own business: `김 지운` is stored surname first, and a rule that only
		 * knew the order the fields are drawn in would file a middle name in front of the surname.
		 */
		var anchor = { given2: 'given', surname2: 'surname' }[ kind ] || kind;
		var after = -1;
		list.forEach( function ( part, index ) {
			if ( part && ( part.kind === anchor || part.kind === kind ) ) {
				after = index;
			}
		} );
		if ( -1 !== after ) {
			return after + 1;
		}
		// Nothing to sit beside, so the order the lines are drawn in is the best answer there is.
		var rank = NAME_SLOTS.indexOf( kind );
		if ( -1 === rank ) {
			// And a kind with no line of its own joins the end, rather than sorting before them all.
			return list.length;
		}
		var at = list.findIndex( function ( part ) {
			var other = NAME_SLOTS.indexOf( part && part.kind );
			return -1 !== other && other > rank;
		} );
		return -1 === at ? list.length : at;
	}

	/**
	 * One line of the name.
	 *
	 * What it says, and -- when somebody has asked for it, and only for the parts that are somebody's
	 * name rather than punctuation around it -- how it is said. The two sit beside each other because
	 * that is what they are: one part of a name, written and spoken.
	 */
	function NameSlot( props ) {
		var part = props.part || {};
		// Only a line holding something, of a kind that sits between the ends, is somewhere to move.
		// Anything but the two that belong at the ends, which is where they always are.
		var movable = !! props.part && props.ordered
			&& FIXED_FIRST !== props.kind && FIXED_LAST !== props.kind;
		var at = props.row.index;
		return el(
			'div',
			{
				className: 'ax-ce-slot' + ( movable ? ' is-movable' : '' ),
				draggable: movable,
				onDragStart: function () {
					if ( movable ) {
						props.onDragStart( at );
					}
				},
				onDragOver: function ( event ) {
					if ( movable ) {
						event.preventDefault();
					}
				},
				onDrop: function () {
					if ( movable ) {
						props.onDrop( at );
					}
				}
			},
			el(
				'span',
				{
					className: 'ax-ce-slot__grip',
					'aria-hidden': 'true',
					dangerouslySetInnerHTML: { __html: movable ? icon( 'drag-indicator' ) : '' }
				}
			),
			el( TextField, {
				label: componentLabel( props.kind ),
				className: 'ax-ce-slot__value',
				value: part.value || '',
				onChange: function ( value ) {
					props.onChange( props.row, 'value', value );
				}
			} ),
			props.phonetic && -1 !== PHONETIC_SLOTS.indexOf( props.kind )
				? el( TextField, {
					label: __( 'Pronunciation', 'axismundi-contacts' ),
					className: 'ax-ce-slot__phonetic',
					value: part.phonetic || '',
					onChange: function ( value ) {
						props.onChange( props.row, 'phonetic', value );
					}
				} )
				: null,
			/*
			 * Removed only where removing means something. A given name and a surname are the two
			 * lines the section is, and a title and letters after it are where they always are: those
			 * four are emptied rather than taken away, and the line stays to be typed into again. A
			 * second middle name is something somebody added, so it is something they can take back.
			 */
			( props.part || props.row.pendingId )
				&& (
					-1 !== ADDABLE.indexOf( props.kind )
					// A kind with no line of its own, and any part past the first of its kind, is
					// something somebody put there rather than a line the section is made of.
					|| -1 === NAME_SLOTS.indexOf( props.kind )
					|| props.row.occurrence > 0
				)
				? el( IconButton, {
					icon: 'delete',
					variant: 'danger',
					label: sprintf(
						/* translators: %s: which part of the name, such as Surname 2. */
						__( 'Remove %s', 'axismundi-contacts' ),
						componentLabel( props.kind )
					),
					onClick: function () {
						props.onRemove( props.row );
					}
				} )
				: el( 'span', { className: 'ax-ce-slot__spacer', 'aria-hidden': 'true' } )
		);
	}

	/**
	 * The name.
	 *
	 * Two fields and a way to open the rest. A person has a given name and a surname far more often
	 * than they have a credential or a second middle name, and a screen that shows every part a name
	 * could have is a screen that asks everybody to read past what they do not need on the way to the
	 * two things they came to type.
	 *
	 * Everything else the standard allows is still reachable and nothing is decided on anybody's
	 * behalf: the written-out form, the reading order and the separators inside it, and how the name
	 * files are each asked for rather than offered. A name that already carries one of them opens
	 * with it in view, because what is stored is what a screen has to be able to show.
	 *
	 * A card about an organisation, a place or a machine has no given name. That name is the name --
	 * `full` and nothing else -- and the parts are there for the rare card that wants them.
	 */
	function NameEditor( props ) {
		var name = props.name || {};
		var components = name.components || [];
		var dragging = props.dragging;
		var blocked = props.blocked;
		var ordered = true === name.isOrdered;
		var personal = 'individual' === ( props.kind || 'individual' );

		/*
		 * What is open. Screen state, all of it: a name that arrived with a middle name opens showing
		 * it, and closing the fields never takes anything away.
		 */
		var [ expanded, setExpanded ] = useState(
			components.some( function ( part ) {
				return part && -1 === BASIC_SLOTS.indexOf( part.kind );
			} )
		);
		var [ phonetic, setPhonetic ] = useState( hasPhonetic( components ) );
		/*
		 * The full name, which a person's card does not lead with. It is asked for, or shown because
		 * the card arrived with one and nothing else -- a name somebody wrote down and never took
		 * apart, which there is nowhere else to put.
		 */
		var [ written, setWritten ] = useState( ! components.length && undefined !== name.full );
		var [ asking, setAsking ] = useState( '' );
		var [ adding, setAdding ] = useState( false );
		/*
		 * Lines somebody asked for that the document has nothing for yet. Screen state, like every
		 * other line that is empty -- the difference is only that these were asked for rather than
		 * always drawn.
		 */
		var [ pending, setPending ] = useState( [] );

		function setName( next ) {
			// A name with nothing in it is not a name, so the property goes rather than sitting empty.
			var keys = Object.keys( next ).filter( function ( key ) {
				return '@type' !== key;
			} );
			props.onChange( keys.length ? next : undefined );
		}

		function setComponents( list, extra ) {
			var next = Object.assign( {}, name, extra || {} );
			if ( list.length ) {
				next.components = list;
			} else {
				delete next.components;
				delete next.isOrdered;
				delete next.defaultSeparator;
				delete next.sortAs;
				delete next.phoneticSystem;
				delete next.phoneticScript;
			}
			setName( next );
		}

		/*
		 * Adding opens a line, and nothing more. The document gets a part when somebody types in that
		 * line -- the same rule as every other line on the screen, and the reason a card does not
		 * collect empty parts from people who clicked a button and thought better of it.
		 */
		function addPart( kind ) {
			rows += 1;
			setPending( pending.concat( [ { id: 'row-' + rows, kind: kind } ] ) );
		}

		/**
		 * One field of the stack, writing through to the part it stands for.
		 *
		 * The part is made when there is something to put in it and never before, which is what keeps
		 * an untouched field out of the document. It is not taken away again when somebody empties it:
		 * a name half-retyped is not a name being deleted, and what is left empty is dropped on the way
		 * out rather than mid-keystroke.
		 */
		function writeSlot( row, key, value ) {
			var at = row.index;
			if ( undefined === at ) {
				if ( ! value ) {
					return;
				}
				var made = { kind: row.kind, value: '' };
				made[ key ] = value;
				var list = components.slice();
				list.splice( slotInsertion( components, row.kind ), 0, made );
				if ( row.pendingId ) {
					// The line is the document's now, so it stops being one this screen is holding.
					setPending( pending.filter( function ( each ) {
						return each.id !== row.pendingId;
					} ) );
				}
				/*
				 * A name this editor builds is a name whose order it knows: the lines somebody is
				 * filling in are read down the screen in the order they are read aloud. So the first
				 * part written here says so.
				 *
				 * A name that arrived saying otherwise is left saying it. An import that did not know
				 * the reading order is not made to claim one because somebody opened it.
				 */
				setComponents( endsFirstAndLast( list ), components.length ? {} : { isOrdered: true } );
				return;
			}
			var part = Object.assign( {}, components[ at ] );
			if ( '' === value && 'phonetic' === key ) {
				delete part.phonetic;
			} else {
				part[ key ] = value;
			}
			/*
			 * A line emptied of everything is a part of the name that is no longer there. The line
			 * itself stays -- it is a place to type, not a record -- but the document stops carrying a
			 * part with nothing in it, which is the difference the screen has to keep straight.
			 *
			 * Unless another language says something about it. Taking it away would leave those
			 * patches pointing at nothing, which the server refuses, so the ones affected are named
			 * and somebody decides.
			 */
			var emptied = ! String( part.value || '' ).trim() && ! String( part.phonetic || '' ).trim();
			if ( emptied ) {
				var affected = patchesUnder( props.localizations, 'name/components/' + at );
				if ( affected.length ) {
					props.onBlocked( at, affected );
					return;
				}
				setComponents( components.filter( function ( ignored, i ) {
					return i !== at;
				} ) );
				return;
			}
			var next = components.slice();
			next[ at ] = part;
			setComponents( next );
		}

		/** Take a line away outright, which is only offered for the lines somebody added. */
		function removePart( row ) {
			if ( undefined === row.index ) {
				setPending( pending.filter( function ( each ) {
					return each.id !== row.pendingId;
				} ) );
				return;
			}
			var at = row.index;
			var affected = patchesUnder( props.localizations, 'name/components/' + at );
			if ( affected.length ) {
				props.onBlocked( at, affected );
				return;
			}
			setComponents( components.filter( function ( ignored, i ) {
				return i !== at;
			} ) );
		}

		/*
		 * The lines. Collapsed, the two a name usually is; open, a title above them and letters after
		 * the name below, both where they always are. In between, what the name has in the order it
		 * has it, and then a line for each part it does not -- which is a place to type and not a part
		 * of anything until somebody types in it.
		 */
		function line( row ) {
			return el( NameSlot, {
				key: row.key,
				row: row,
				kind: row.kind,
				part: row.part,
				ordered: ordered,
				phonetic: phonetic,
				onChange: writeSlot,
				onRemove: removePart,
				onDragStart: props.onDragStart,
				onDrop: function ( at ) {
					if ( null === dragging || dragging === at ) {
						return;
					}
					var list = components.slice();
					var moved = list.splice( dragging, 1 )[ 0 ];
					list.splice( at, 0, moved );
					// And whatever the drag did, a title opens the name and a credential closes it.
					setComponents( endsFirstAndLast( list ) );
					props.onDragStart( null );
				}
			} );
		}

		var slots = expanded
			? slotRows( components, [ FIXED_FIRST ] ).map( line )
				.concat( slotRows( components, MIDDLE_SLOTS, pending, true ).map( line ) )
				.concat( slotRows( components, [ FIXED_LAST ] ).map( line ) )
			: slotRows( components, BASIC_SLOTS ).map( line );

		return el(
			Section,
			{ icon: kindIcon( props.kind ), label: __( 'Name', 'axismundi-contacts' ) },
			/*
			 * The written-out name. Not offered to somebody filling in a person's name, and never
			 * built from the parts -- how a name reads is a question the standard leaves to whoever
			 * shows it. It is here when the card already carries one, which is what an import of a
			 * name nobody took apart brings, and when a card is about something that has one name
			 * rather than parts.
			 */
			written || ! personal
				? el( TextField, {
					/*
					 * `Full name` rather than `Written out`. The property is `full` and the standard
					 * calls it the full name; "written out" reads in English as the opposite of an
					 * abbreviation -- `J. Kim` written out, `Dr.` written out -- which is a different
					 * question from the one this field asks.
					 */
					label: __( 'Full name', 'axismundi-contacts' ),
					value: name.full || '',
					supporting: personal
						? sprintf(
							/* translators: %s: what the name reads as. */
							__( 'Reads as: %s', 'axismundi-contacts' ),
							nameText( name ) || __( '(nothing yet)', 'axismundi-contacts' )
						)
						: __( 'The name this is known by.', 'axismundi-contacts' ),
					onChange: function ( value ) {
						setName( withKey( name, 'full', value ) );
					}
				} )
				: null,
			// The parts, as a stack of fields, whenever a stack of fields can say what they say.
			personal || expanded
				? el(
					'div',
					{ className: 'ax-ce-name' },
					el( 'div', { className: 'ax-ce-name__slots' }, slots ),
					el( IconButton, {
						icon: 'keyboard-arrow-down',
						className: 'ax-ce-name__more' + ( expanded ? ' is-open' : '' ),
						label: expanded
							? __( 'Fewer parts of the name', 'axismundi-contacts' )
							: __( 'More parts of the name', 'axismundi-contacts' ),
						onClick: function () {
							setExpanded( ! expanded );
						}
					} )
				)
				: null,
			/*
			 * A second middle name, a second surname, something written between two parts. Each of
			 * these is a real part of somebody's name and none of them is a line worth keeping open on
			 * every card, so they are added rather than offered -- and a name that ends up with two of
			 * a kind is one the lines cannot hold, so it opens as the list below.
			 */
			expanded
				? el(
					'div',
					{ className: 'ax-ce-name__add' },
					el( IconButton, {
						icon: 'add',
						label: __( 'Add a part of the name', 'axismundi-contacts' ),
						onClick: function () {
							setAdding( ! adding );
						}
					} ),
					adding
						? el(
							'span',
							{ className: 'ax-ce-name__add-list' },
							ADDABLE.map( function ( kind ) {
								return el(
									'button',
									{
										key: kind,
										type: 'button',
										className: 'button',
										/*
										 * Offered only when every line of that kind already holds
										 * something. A card with an empty `Given name 2` on screen
										 * does not need a second empty one, and offering it is how
										 * somebody ends up with two lines and one name.
										 */
										disabled: (
											// A line of that kind is already open and empty.
											( -1 !== NAME_SLOTS.indexOf( kind ) && -1 === slotIndex( components, kind ) )
											|| pending.some( function ( each ) {
												return each.kind === kind;
											} )
										),
										onClick: function () {
											setAdding( false );
											addPart( kind );
										}
									},
									componentLabel( kind )
								);
							} )
						)
						: null
				)
				: null,
			blocked
				? el(
					'div',
					{ className: 'ax-ce-blocked', role: 'alert' },
					el(
						'p',
						null,
						__( 'Other languages say something about this part of the name. Removing it would leave them pointing at nothing.', 'axismundi-contacts' )
					),
					el(
						'ul',
						null,
						blocked.affected.map( function ( each ) {
							return el( 'li', { key: each.tag + each.path }, each.tag + ' — ' + each.path );
						} )
					),
					el(
						'p',
						null,
						el(
							'button',
							{
								type: 'button',
								className: 'button',
								onClick: function () {
									props.onResolve( blocked );
								}
							},
							__( 'Remove them and the part', 'axismundi-contacts' )
						),
						' ',
						el(
							'button',
							{ type: 'button', className: 'button', onClick: props.onCancel },
							__( 'Keep everything', 'axismundi-contacts' )
						)
					)
				)
				: null,
			/*
			 * How it is said, asked for rather than shown. Turning it off folds the column away and
			 * leaves every pronunciation exactly where it was: a screen being tidied is not somebody
			 * deleting how their name sounds.
			 */
			expanded
				? el(
					'p',
					null,
					el(
						'label',
						null,
						el( 'input', {
							type: 'checkbox',
							checked: phonetic,
							onChange: function ( event ) {
								setPhonetic( event.target.checked );
							}
						} ),
						' ',
						__( 'Add pronunciation', 'axismundi-contacts' )
					)
				)
				: null,
			/*
			 * And what those pronunciations are written in, which belongs to the name rather than to
			 * any one part of it. Shown as soon as there is a pronunciation to read, because until
			 * then there is nothing to say it about -- and required from that moment, because sounds
			 * in an unstated alphabet are sounds nobody can read: `Jīn` is Pinyin, `キム` is kana, and
			 * the standard will not store one without the other.
			 */
			/*
			 * Asked as soon as somebody says they are writing pronunciations down, rather than after
			 * they have written one. The standard requires one of these two the moment any part
			 * carries a sound, so a screen that waited for the value would be letting somebody fill in
			 * a name and then refusing to save it.
			 */
			phonetic && expanded
				? el(
					'div',
					{ className: 'ax-ce-phonetic' },
					el( Combobox, {
						label: __( 'Pronunciation system', 'axismundi-contacts' ),
						value: name.phoneticSystem || '',
						options: PHONETIC_SYSTEMS,
						allowFree: true,
						supporting: __( 'How the sounds are spelled: IPA, Jyutping or Pinyin.', 'axismundi-contacts' ),
						onChange: function ( value ) {
							setName( withKey( name, 'phoneticSystem', value ) );
						}
					} ),
					/*
					 * The other way of answering the same requirement, and the one that fits a
					 * pronunciation written in an alphabet rather than a notation: `キム` is not a
					 * system, it is kana. Either will do, which is why neither is asked for twice.
					 */
					el( Combobox, {
						label: __( 'Pronunciation script', 'axismundi-contacts' ),
						value: name.phoneticScript || '',
						options: PHONETIC_SCRIPTS,
						allowFree: true,
						supporting: __( 'Or the script they are written in, like Kana. One of the two is required.', 'axismundi-contacts' ),
						onChange: function ( value ) {
							setName( withKey( name, 'phoneticScript', value ) );
						}
					} )
				)
				: null,
			/*
			 * Everything a name can say that filling one in does not ask about. Each of these is a
			 * real answer somebody may need and none of them is a question to put in front of
			 * somebody typing a surname.
			 */
			el(
				'details',
				{ className: 'ax-ce-name__advanced' },
				el( 'summary', null, __( 'More about this name', 'axismundi-contacts' ) ),
				personal
					? el(
						'p',
						null,
						el(
							'label',
							null,
							el( 'input', {
								type: 'checkbox',
								checked: written,
								onChange: function ( event ) {
									if ( event.target.checked ) {
										setWritten( true );
										return;
									}
									if ( name.full ) {
										setAsking( 'written' );
										return;
									}
									setWritten( false );
								}
							} ),
							' ',
							__( 'Full name', 'axismundi-contacts' )
						)
					)
					: null,
				/*
				 * A name that arrived without saying its parts are in the order they are read. Nothing
				 * here decides that on its behalf -- the parts may have come from a vCard that recorded
				 * what they were and not how they are said -- so the order is offered as something to
				 * state rather than assumed by opening the screen.
				 */
				components.length && ! ordered
					? el(
						'div',
						{ className: 'ax-ce-unordered' },
						el(
							'p',
							null,
							__( 'These parts are stored without a reading order, so they cannot be rearranged and nothing can go between them.', 'axismundi-contacts' )
						),
						el(
							'button',
							{
								type: 'button',
								className: 'button',
								onClick: function () {
									setComponents( components, { isOrdered: true } );
								}
							},
							__( 'Say they are in the order they are read', 'axismundi-contacts' )
						)
					)
					: null,
				/*
				 * The separator between the parts, which exists only for a name whose parts are in
				 * order -- the standard says as much, and a separator between parts nobody has put in
				 * order would be joining them in an order that means nothing.
				 *
				 * The checkbox is whether the card answers at all. Unticked there is no property and a
				 * reader uses a space; ticked and empty says `""`, which is how a name written without
				 * spaces is said. Turning it off throws an answer away, so when there is one to lose it
				 * asks first.
				 */
				ordered && components.length
					? el(
						Fragment,
						null,
						el(
							'p',
							null,
							el(
								'label',
								null,
								el( 'input', {
									type: 'checkbox',
									checked: undefined !== name.defaultSeparator,
									onChange: function ( event ) {
										if ( event.target.checked ) {
											setName( Object.assign( {}, name, { defaultSeparator: '' } ) );
											return;
										}
										if ( name.defaultSeparator ) {
											setAsking( 'separator' );
											return;
										}
										var next = Object.assign( {}, name );
										delete next.defaultSeparator;
										setName( next );
									}
								} ),
								' ',
								__( 'Default separator', 'axismundi-contacts' )
							)
						),
						undefined !== name.defaultSeparator
							? el( TextField, {
								label: __( 'Default separator', 'axismundi-contacts' ),
								className: 'ax-ce-separator',
								value: name.defaultSeparator,
								supporting: __( 'What goes between the parts. Empty for names written without spaces.', 'axismundi-contacts' ),
								onChange: function ( value ) {
									// Empty is a value here, so it is written rather than treated as nothing.
									setName( Object.assign( {}, name, { defaultSeparator: value } ) );
								}
							} )
							: null
					)
					: null,
				/*
				 * How it files, which is a third answer rather than a consequence of the other two.
				 * Left alone, a directory reads the parts themselves -- so this is here for the name
				 * whose filing does not follow from them: RFC 9553 files `Pau Shou Chang` under a
				 * surname whose value is `Shou Chang`, because the `given2` belongs with the surname
				 * when sorting and nowhere else. Two keys, because a directory has two columns.
				 */
				components.length
					? el(
						Fragment,
						null,
						el(
							'p',
							null,
							el(
								'label',
								null,
								el( 'input', {
									type: 'checkbox',
									checked: undefined !== name.sortAs,
									onChange: function ( event ) {
										if ( event.target.checked ) {
											setName( Object.assign( {}, name, { sortAs: {} } ) );
											return;
										}
										if ( Object.keys( name.sortAs || {} ).length ) {
											setAsking( 'sorting' );
											return;
										}
										var next = Object.assign( {}, name );
										delete next.sortAs;
										setName( next );
									}
								} ),
								' ',
								__( 'Custom sorting', 'axismundi-contacts' )
							)
						),
						undefined !== name.sortAs
							? el(
								'div',
								{ className: 'ax-ce-sortas' },
								SORT_KEYS.filter( function ( kind ) {
									return hasKind( components, kind );
								} ).map( function ( kind ) {
									return el( TextField, {
										key: kind,
										label: sprintf(
											/* translators: %s: which part of the name, such as Surname. */
											__( 'File %s under', 'axismundi-contacts' ),
											componentLabel( kind )
										),
										value: ( name.sortAs || {} )[ kind ] || '',
										onChange: function ( value ) {
											var sortAs = withKey( name.sortAs || {}, kind, value );
											setName( Object.assign( {}, name, { sortAs: sortAs } ) );
										}
									} );
								} )
							)
							: null
					)
					: null
			),
			asking
				? el(
					'div',
					{ className: 'ax-ce-blocked', role: 'alert' },
					el(
						'p',
						null,
						'separator' === asking
							? __( 'Turning this off removes the separator this name is written with.', 'axismundi-contacts' )
							: 'sorting' === asking
								? __( 'Turning this off removes the way this name is filed.', 'axismundi-contacts' )
								: __( 'Turning this off removes the full name.', 'axismundi-contacts' )
					),
					el(
						'p',
						null,
						el(
							'button',
							{
								type: 'button',
								className: 'button',
								onClick: function () {
									if ( 'written' === asking ) {
										var without = Object.assign( {}, name );
										delete without.full;
										setWritten( false );
										setAsking( '' );
										setName( without );
										return;
									}
									var next = Object.assign( {}, name );
									delete next[ 'separator' === asking ? 'defaultSeparator' : 'sortAs' ];
									setAsking( '' );
									setName( next );
								}
							},
							__( 'Remove it', 'axismundi-contacts' )
						),
						' ',
						el(
							'button',
							{
								type: 'button',
								className: 'button',
								onClick: function () {
									setAsking( '' );
								}
							},
							__( 'Keep it', 'axismundi-contacts' )
						)
					)
				)
				: null
		);
	}

	/**
	 * The phonetic systems the standard registers, and the scripts a pronunciation is usually in.
	 *
	 * The systems are a closed list in RFC 9553 and the scripts are not, so both are typed into: a
	 * pronunciation written in a script nobody listed is still a pronunciation, and refusing it would
	 * be this editor deciding which alphabets exist.
	 */
	var PHONETIC_SYSTEMS = [ 'ipa', 'jyut', 'piny' ];
	var PHONETIC_SCRIPTS = [ 'Latn', 'Kana', 'Hira', 'Hang', 'Hani', 'Cyrl', 'Arab', 'Grek' ];

	/**
	 * The columns a directory files a name in.
	 *
	 * Two, because that is what a directory has. A name with three given names still files under one
	 * given sort key, and RFC 9553's own example writes a `given2` into the surname key rather than
	 * inventing a column for it.
	 */
	var SORT_KEYS = [ 'given', 'surname' ];

	/** One repeating property, as rows keyed by the id the rest of the system addresses them by. */
	function EntryField( props ) {
		var entries = props.entries || {};
		var ids = Object.keys( entries );

		function setEntries( next ) {
			props.onChange( Object.keys( next ).length ? next : undefined );
		}

		return el(
			Section,
			{ icon: props.field.icon, label: props.field.label },
			ids.map( function ( id ) {
				var entry = entries[ id ] || {};
				return el(
					'div',
					{ key: id, className: 'ax-ce-entry' },
					el( TextField, {
						label: props.field.label,
						type: props.field.type,
						className: 'ax-ce-entry__value',
						value: entry[ props.field.value ] || '',
						// The id is the address a published pointer and a provenance row name, so it is
						// shown rather than hidden: somebody choosing what to publish is choosing by it.
						supporting: id,
						onChange: function ( value ) {
							var next = Object.assign( {}, entries );
							next[ id ] = withKey( entry, props.field.value, value );
							setEntries( next );
						}
					} ),
					el( TextField, {
						label: __( 'Label', 'axismundi-contacts' ),
						className: 'ax-ce-entry__label',
						value: entry.label || '',
						onChange: function ( value ) {
							var next = Object.assign( {}, entries );
							next[ id ] = withKey( entry, 'label', value );
							setEntries( next );
						}
					} ),
					el( IconButton, {
						icon: 'delete',
						variant: 'danger',
						label: __( 'Remove this entry', 'axismundi-contacts' ),
						onClick: function () {
							var next = Object.assign( {}, entries );
							delete next[ id ];
							setEntries( next );
						}
					} )
				);
			} ),
			el(
				'p',
				null,
				el(
					'button',
					{
						type: 'button',
						className: 'button',
						onClick: function () {
							/*
							 * A new id, never a reused one. An entry id is the address a published
							 * pointer and a provenance row name, so handing a fresh value the id of one
							 * that was removed would hand it that value's publishing consent too.
							 */
							var id = props.field.key.slice( 0, 3 ) + '-' + Math.random().toString( 36 ).slice( 2, 8 );
							var next = Object.assign( {}, entries );
							next[ id ] = {};
							next[ id ][ props.field.value ] = '';
							setEntries( next );
						}
					},
					sprintf(
						/* translators: %s: what kind of thing is being added. */
						__( 'Add %s', 'axismundi-contacts' ),
						props.field.label.toLowerCase()
					)
				)
			)
		);
	}

	/**
	 * An id for a new entry, which is never one that has been used before.
	 *
	 * An entry id is an address. `phones/p1` is what a provenance row is written against and what a
	 * published pointer names, so counting up from `p1` hands a deleted number's address to the next
	 * one somebody types -- along with where that old value came from and whoever had agreed it could
	 * be published. Nothing counts: an id is drawn, and drawn again if the card already has it.
	 *
	 * @param {string} prefix  A short word saying what kind of entry it is, for reading a diff.
	 * @param {Object} entries The entries this card already holds.
	 */
	function newEntryId( prefix, entries ) {
		var id = '';
		do {
			id = prefix + '-' + Math.random().toString( 36 ).slice( 2, 8 );
		} while ( Object.prototype.hasOwnProperty.call( entries || {}, id ) );
		return id;
	}

	/**
	 * Google's phone number rules, when they loaded.
	 *
	 * Everything below works without them: the country control disappears, what somebody types is
	 * stored as they typed it, and a number that already says its country still reads back. A phone
	 * field that stopped working because a script did not arrive would be worse than one that stops
	 * helping.
	 */
	var phoneRules = window.libphonenumber || null;

	/** The countries a number can be read as, named the way this browser names them. */
	function phoneRegions() {
		if ( ! phoneRules ) {
			return [];
		}
		var names = null;
		try {
			names = new Intl.DisplayNames( undefined, { type: 'region' } );
		} catch ( error ) {
			names = null;
		}
		return phoneRules.getCountries().map( function ( region ) {
			var name = region;
			try {
				name = names ? names.of( region ) : region;
			} catch ( error ) {
				name = region;
			}
			return {
				value: region,
				label: name + ' (+' + phoneRules.getCountryCallingCode( region ) + ')'
			};
		} ).sort( function ( a, b ) {
			return a.label.localeCompare( b.label );
		} );
	}

	/** What a stored number says, when it says enough to be read. */
	function readNumber( value ) {
		if ( ! phoneRules || ! value ) {
			return null;
		}
		try {
			return phoneRules.parsePhoneNumberFromString( String( value ) ) || null;
		} catch ( error ) {
			return null;
		}
	}

	/**
	 * What to show in the box.
	 *
	 * A number that says its own country is shown the way that country writes it, which is how
	 * somebody recognises their own number. Anything else -- an extension, a short code, a note
	 * somebody imported from a phone that let them type anything -- is shown exactly as it is stored.
	 */
	function showNumber( value, region ) {
		var read = readNumber( value );
		if ( ! read ) {
			return String( value || '' );
		}
		return read.country === region ? read.formatNational() : read.formatInternational();
	}

	/**
	 * What to store for what somebody typed.
	 *
	 * `tel:+82…` when it can be read, and what they typed when it cannot. A number this cannot parse
	 * is not a number this gets to refuse: extensions, short codes, an internal four-digit line, and
	 * whatever an import brought are all real entries in somebody's address book, and a card that
	 * dropped them would be a worse record than the phone it came from.
	 */
	function storeNumber( text, region ) {
		var typed = String( text || '' ).trim();
		if ( ! phoneRules || ! typed ) {
			return typed;
		}
		try {
			var read = phoneRules.parsePhoneNumberFromString( typed, region || undefined );
			/*
			 * Valid, not merely possible. `isPossible` asks whether the digits are the right length,
			 * which a Korean number typed into a row that says United States passes -- and settling it
			 * into `tel:+102…` would be this screen inventing a number nobody has. When it cannot be
			 * sure, what somebody typed stays exactly as they typed it.
			 */
			return read && read.isValid() ? read.getURI() : typed;
		} catch ( error ) {
			return typed;
		}
	}

	/**
	 * Which of the offered labels an entry reads as, or `custom` when it reads as none.
	 *
	 * Derived every time rather than stored beside the values it set: an entry from another client has
	 * no preset to store, and a second record of the same fact is one that eventually disagrees. The
	 * table is the server's -- it decides the same question when the row is drawn anywhere else.
	 */
	function presetOf( entry, presets ) {
		var contexts = Object.keys( ( entry || {} ).contexts || {} ).filter( function ( key ) {
			return entry.contexts[ key ];
		} ).sort().join( ',' );
		var features = Object.keys( ( entry || {} ).features || {} ).filter( function ( key ) {
			return entry.features[ key ];
		} ).sort().join( ',' );
		// A label somebody typed is the fact, and outranks whatever the axes happen to say.
		if ( String( ( entry || {} ).label || '' ).trim() ) {
			return 'custom';
		}
		var found = ( presets || [] ).filter( function ( preset ) {
			return 'custom' !== preset.value
				&& contexts === ( preset.contexts || [] ).slice().sort().join( ',' )
				&& features === ( preset.features || [] ).slice().sort().join( ',' );
		} );
		return found.length ? found[ 0 ].value : 'custom';
	}

	/**
	 * One entry with a label applied, or with somebody's own word for it.
	 *
	 * The two are exclusive. An entry saying both `work` and `Google Voice` is claiming two different
	 * answers to one question, and whoever reads it has to pick.
	 */
	function withPreset( entry, key, presets, label ) {
		var next = Object.assign( {}, entry );
		delete next.contexts;
		delete next.features;
		delete next.label;
		if ( 'custom' === key ) {
			return String( label || '' ).trim() ? Object.assign( next, { label: String( label ).trim() } ) : next;
		}
		var preset = ( presets || [] ).filter( function ( each ) {
			return each.value === key;
		} )[ 0 ];
		if ( ! preset ) {
			return next;
		}
		[ 'contexts', 'features' ].forEach( function ( axis ) {
			if ( ( preset[ axis ] || [] ).length ) {
				// JSContact says these as sets: a map of value => true, not a list.
				next[ axis ] = {};
				preset[ axis ].forEach( function ( value ) {
					next[ axis ][ value ] = true;
				} );
			}
		} );
		return next;
	}

	/**
	 * The phone numbers.
	 *
	 * A country, a number, and what to call it. The country is a hint for reading what somebody
	 * types and is never written down: the card keeps `tel:+821027421672`, which says Korea itself,
	 * and a second field saying `KR` beside it would be the same answer twice with nothing keeping
	 * the two in step.
	 *
	 * What is typed stays as typed until the box is left. Somebody writing `010-2742-1672` is writing
	 * the number the way they know it, and a field that rewrote it into `+82 10…` under their hands
	 * mid-keystroke would be arguing with them about their own phone number.
	 */
	function Phones( props ) {
		var entries = props.value || {};
		var presets = ( config.presets || {} ).phones || [];
		var regions = phoneRegions();
		var [ hints, setHints ] = useState( {} );
		var [ pending, setPending ] = useState( [] );
		var [ asking, setAsking ] = useState( null );

		function regionFor( id, entry ) {
			if ( hints[ id ] ) {
				return hints[ id ];
			}
			var read = readNumber( ( entry || {} ).number );
			return read && read.country ? read.country : ( config.region || '' );
		}

		function setEntries( next ) {
			props.onChange( Object.keys( next ).length ? next : undefined );
		}

		function write( row, change ) {
			if ( row.pending ) {
				var made = change( row.entry );
				if ( ! String( made.number || '' ).trim() ) {
					/*
					 * A phone entry is a number, so until there is one this is a line. What has been
					 * answered about it waits on the line: somebody who picks `Mobile` and then types
					 * the number meant both, and dropping the first because the second came second
					 * would be the screen forgetting what it was told.
					 */
					setPending( pending.map( function ( each ) {
						return each.id === row.pending ? { id: each.id, entry: made } : each;
					} ) );
					return;
				}
				var next = Object.assign( {}, entries );
				next[ newEntryId( 'pho', entries ) ] = made;
				setPending( pending.filter( function ( each ) {
					return each.id !== row.pending;
				} ) );
				setEntries( next );
				return;
			}
			var written = change( entries[ row.id ] || {} );
			if ( ! String( written.number || '' ).trim() ) {
				/*
				 * A number rubbed out is a number removed, and an entry that is only a label is one
				 * the store refuses. The row stays with everything else it said, waiting for another
				 * number -- unless a language says something about this entry, in which case taking it
				 * away is a question rather than a keystroke.
				 */
				var affected = patchesUnder( ( props.card || {} ).localizations, 'phones/' + row.id );
				if ( affected.length ) {
					setAsking( { id: row.id, depends: affected.map( function ( each ) {
						return { kind: 'patch', tag: each.tag, path: each.path, label: each.tag + ' — ' + each.path };
					} ) } );
					return;
				}
				var without = Object.assign( {}, entries );
				delete without[ row.id ];
				delete written.number;
				setPending( pending.concat( [ { id: newEntryId( 'row', {} ), entry: written } ] ) );
				setEntries( without );
				return;
			}
			var updated = Object.assign( {}, entries );
			updated[ row.id ] = written;
			setEntries( updated );
		}

		var rows = Object.keys( entries ).map( function ( id ) {
			return { key: id, id: id, entry: entries[ id ] || {} };
		} ).concat( pending.map( function ( each ) {
			return { key: each.id, pending: each.id, entry: each.entry };
		} ) );

		return el(
			Section,
			{ icon: 'call', label: __( 'Phone', 'axismundi-contacts' ) },
			rows.map( function ( row ) {
				var entry = row.entry;
				var region = regionFor( row.key, entry );
				var preset = presetOf( entry, presets );
				return el(
					'div',
					{ key: row.key, className: 'ax-ce-phone' },
					regions.length
						? el( Combobox, {
							label: __( 'Country', 'axismundi-contacts' ),
							className: 'ax-ce-phone__region',
							value: region,
							options: regions,
							onChange: function ( value ) {
								var next = Object.assign( {}, hints );
								next[ row.key ] = value;
								setHints( next );
								/*
								 * The country somebody picked is how the number they already typed
								 * should be read, so it is read again -- but only for a number that
								 * has not been settled into one already.
								 */
								if ( entry.number && ! readNumber( entry.number ) ) {
									write( row, function ( each ) {
										return withKey( each, 'number', storeNumber( each.number, value ) );
									} );
								}
							}
						} )
						: null,
					el( TextField, {
						label: __( 'Phone', 'axismundi-contacts' ),
						className: 'ax-ce-phone__number',
						type: 'tel',
						value: showNumber( entry.number, region ),
						onChange: function ( value ) {
							write( row, function ( each ) {
								return withKey( each, 'number', value );
							} );
						},
						inputProps: {
							onBlur: function ( event ) {
								// Settled when the box is left, and not one keystroke sooner.
								var settled = storeNumber( event.target.value, region );
								if ( settled !== entry.number ) {
									write( row, function ( each ) {
										return withKey( each, 'number', settled );
									} );
								}
							}
						}
					} ),
					el( Combobox, {
						label: __( 'What it is', 'axismundi-contacts' ),
						className: 'ax-ce-phone__preset',
						value: preset,
						options: presets,
						onChange: function ( value ) {
							write( row, function ( each ) {
								return withPreset( each, value, presets, each.label );
							} );
						}
					} ),
					'custom' === preset
						? el( TextField, {
							label: __( 'Called', 'axismundi-contacts' ),
							className: 'ax-ce-phone__label',
							value: entry.label || '',
							onChange: function ( value ) {
								write( row, function ( each ) {
									return withKey( each, 'label', value );
								} );
							}
						} )
						: null,
					el( IconButton, {
						icon: 'delete',
						variant: 'danger',
						label: __( 'Remove this number', 'axismundi-contacts' ),
						onClick: function () {
							if ( row.pending ) {
								setPending( pending.filter( function ( each ) {
									return each.id !== row.pending;
								} ) );
								return;
							}
							var affected = patchesUnder( ( props.card || {} ).localizations, 'phones/' + row.id );
							if ( affected.length ) {
								setAsking( { id: row.id, depends: affected.map( function ( each ) {
									return { kind: 'patch', tag: each.tag, path: each.path, label: each.tag + ' — ' + each.path };
								} ) } );
								return;
							}
							var next = Object.assign( {}, entries );
							delete next[ row.id ];
							setEntries( next );
						}
					} )
				);
			} ),
			asking
				? el(
					'div',
					{ className: 'ax-ce-blocked', role: 'alert' },
					el(
						'p',
						null,
						__( 'Other languages say something about this number. Removing it would leave them saying it about nothing.', 'axismundi-contacts' )
					),
					el(
						'ul',
						null,
						asking.depends.map( function ( each ) {
							return el( 'li', { key: each.tag + each.path }, each.label );
						} )
					),
					el(
						'p',
						null,
						el(
							'button',
							{
								type: 'button',
								className: 'button',
								onClick: function () {
									props.onResolve( asking );
									setAsking( null );
								}
							},
							__( 'Remove them and the number', 'axismundi-contacts' )
						),
						' ',
						el(
							'button',
							{
								type: 'button',
								className: 'button',
								onClick: function () {
									setAsking( null );
								}
							},
							__( 'Keep everything', 'axismundi-contacts' )
						)
					)
				)
				: null,
			el(
				'p',
				null,
				el(
					'button',
					{
						type: 'button',
						className: 'button',
						onClick: function () {
							setPending( pending.concat( [ { id: newEntryId( 'row', {} ), entry: {} } ] ) );
						}
					},
					__( 'Add a phone number', 'axismundi-contacts' )
				)
			)
		);
	}

	/**
	 * What points at one organization, and would be pointing at nothing without it.
	 *
	 * A title names its employer by entry id, and a language may translate that organization's name.
	 * Removing the entry leaves both saying something about a thing the card no longer holds -- which
	 * the server refuses on the way in, so the screen has to ask before it gets there.
	 */
	function dependsOnOrganization( card, id ) {
		var found = [];
		Object.keys( ( card || {} ).titles || {} ).forEach( function ( key ) {
			if ( id === ( card.titles[ key ] || {} ).organizationId ) {
				found.push( { kind: 'title', id: key, label: ( card.titles[ key ] || {} ).name || key } );
			}
		} );
		patchesUnder( ( card || {} ).localizations, 'organizations/' + id ).forEach( function ( each ) {
			found.push( { kind: 'patch', tag: each.tag, path: each.path, label: each.tag + ' — ' + each.path } );
		} );
		/*
		 * And a language that says where a title is held. It says it in one of two shapes -- the
		 * whole title, or just the line naming its employer -- and both name this organization by the
		 * id being taken away. Looking only under `organizations/` found neither: they are written
		 * under `titles/`, about a different property, and they would have gone on pointing here.
		 */
		var localizations = ( card || {} ).localizations || {};
		Object.keys( localizations ).forEach( function ( tag ) {
			Object.keys( localizations[ tag ] || {} ).forEach( function ( path ) {
				var value = localizations[ tag ][ path ];
				var names = /^titles\/[^/]+\/organizationId$/.test( path ) && id === value;
				var holds = /^titles\/[^/]+$/.test( path ) && value && 'object' === typeof value && id === value.organizationId;
				if ( names || holds ) {
					found.push( {
						kind: holds ? 'inside' : 'patch',
						tag: tag,
						path: path,
						label: tag + ' — ' + path
					} );
				}
			} );
		} );
		return found;
	}

	/**
	 * A localization with some of it taken away.
	 *
	 * A patch removed outright when it was only about the thing that is going, and emptied of one key
	 * when it was about more than that: a language giving a whole title still gives the title after
	 * the organization it named is gone, so what goes is the line naming it and nothing else.
	 */
	function withoutPatches( localizations, going ) {
		var out = Object.assign( {}, localizations || {} );
		going.forEach( function ( each ) {
			if ( ! each.tag || ! out[ each.tag ] ) {
				return;
			}
			var patch = Object.assign( {}, out[ each.tag ] );
			if ( 'inside' === each.kind ) {
				var value = Object.assign( {}, patch[ each.path ] );
				delete value.organizationId;
				patch[ each.path ] = value;
			} else {
				delete patch[ each.path ];
			}
			if ( Object.keys( patch ).length ) {
				out[ each.tag ] = patch;
			} else {
				delete out[ each.tag ];
			}
		} );
		return out;
	}

	/**
	 * The places somebody belongs, and what they are there.
	 *
	 * Two properties rather than one, because the standard keeps them apart and so does life: an
	 * organization is a place with a name, and a title is what somebody is called inside one. A person
	 * has two titles at one company far more often than they have two companies, and a card that
	 * folded the two together would have to invent a rule for which title belonged to which employer.
	 * `organizationId` is that rule, and it is the standard's.
	 *
	 * A row is a place to type, the same as every line of the name: the document gets an entry when
	 * somebody types in one, not when they open one.
	 */
	function Organizations( props ) {
		var entries = props.value || {};
		var [ pending, setPending ] = useState( [] );
		var [ asking, setAsking ] = useState( null );
		// Parts somebody has opened a line for, by the row they were opened on.
		var [ openUnits, setOpenUnits ] = useState( {} );

		function openUnit( key ) {
			var next = Object.assign( {}, openUnits );
			next[ key ] = ( next[ key ] || [] ).concat( [ 'unit-' + Math.random().toString( 36 ).slice( 2, 8 ) ] );
			setOpenUnits( next );
		}

		function closeUnit( key, unit ) {
			var next = Object.assign( {}, openUnits );
			next[ key ] = ( next[ key ] || [] ).filter( function ( each ) {
				return each !== unit;
			} );
			setOpenUnits( next );
		}

		function setEntries( next ) {
			props.onChange( Object.keys( next ).length ? next : undefined );
		}

		function freeId() {
			return newEntryId( 'org', entries );
		}

		/** Write through to an entry, making it when there is something to put in it. */
		function write( row, change ) {
			if ( row.pending ) {
				var made = change( {} );
				if ( ! String( made.name || '' ).trim() ) {
					// An organization is a place with a name. Until there is one, this is only a row.
					return;
				}
				var next = Object.assign( {}, entries );
				next[ freeId() ] = made;
				setPending( pending.filter( function ( each ) {
					return each !== row.pending;
				} ) );
				setEntries( next );
				return;
			}
			var updated = Object.assign( {}, entries );
			updated[ row.id ] = change( entries[ row.id ] || {} );
			setEntries( updated );
		}

		function remove( row ) {
			if ( row.pending ) {
				setPending( pending.filter( function ( each ) {
					return each !== row.pending;
				} ) );
				return;
			}
			var depends = dependsOnOrganization( props.card, row.id );
			if ( depends.length ) {
				setAsking( { id: row.id, depends: depends } );
				return;
			}
			var next = Object.assign( {}, entries );
			delete next[ row.id ];
			setEntries( next );
		}

		var rows = Object.keys( entries ).map( function ( id ) {
			return { key: id, id: id, entry: entries[ id ] || {} };
		} ).concat( pending.map( function ( id ) {
			return { key: id, pending: id, entry: {} };
		} ) );

		return el(
			Section,
			{ icon: 'domain', label: __( 'Organizations', 'axismundi-contacts' ) },
			rows.map( function ( row ) {
				var entry = row.entry;
				var units = entry.units || [];
				return el(
					'div',
					{ key: row.key, className: 'ax-ce-org' },
					el(
						'div',
						{ className: 'ax-ce-org__fields' },
						el( TextField, {
							label: __( 'Organization', 'axismundi-contacts' ),
							value: entry.name || '',
							onChange: function ( value ) {
								write( row, function ( each ) {
									return withKey( each, 'name', value );
								} );
							}
						} ),
						/*
						 * The parts of it somebody belongs to, from the outside in: a faculty inside a
						 * university, a team inside a department. A list, because the order is what
						 * says which contains which -- and a list that exists only once something is
						 * in it, because `units: []` is a property saying nothing.
						 */
						units.map( function ( unit, at ) {
							return el(
								'div',
								{ key: at, className: 'ax-ce-org__unit' },
								el( TextField, {
									label: __( 'Part of it', 'axismundi-contacts' ),
									value: ( unit || {} ).name || '',
									onChange: function ( value ) {
										write( row, function ( each ) {
											var list = ( each.units || [] ).slice();
											list[ at ] = { name: value };
											return withKey( each, 'units', list );
										} );
									}
								} ),
								el( IconButton, {
									icon: 'delete',
									variant: 'danger',
									label: __( 'Remove this part', 'axismundi-contacts' ),
									onClick: function () {
										write( row, function ( each ) {
											var list = ( each.units || [] ).filter( function ( ignored, i ) {
												return i !== at;
											} );
											return withKey( each, 'units', list.length ? list : '' );
										} );
									}
								} )
							);
						} ),
						/*
						 * And a part somebody has opened but not named. A row, like everything else on
						 * this screen: `units: [{ "name": "" }]` is a list saying that an organization
						 * contains something nobody can name, which is not a thing anybody meant.
						 */
						( openUnits[ row.key ] || [] ).map( function ( unit ) {
							return el(
								'div',
								{ key: unit, className: 'ax-ce-org__unit' },
								el( TextField, {
									label: __( 'Part of it', 'axismundi-contacts' ),
									value: '',
									onChange: function ( value ) {
										if ( ! String( value ).trim() ) {
											return;
										}
										closeUnit( row.key, unit );
										write( row, function ( each ) {
											return withKey( each, 'units', ( each.units || [] ).concat( [ { name: value } ] ) );
										} );
									}
								} ),
								el( IconButton, {
									icon: 'delete',
									variant: 'danger',
									label: __( 'Remove this part', 'axismundi-contacts' ),
									onClick: function () {
										closeUnit( row.key, unit );
									}
								} )
							);
						} ),
						row.id
							? el(
								'p',
								null,
								el(
									'button',
									{
										type: 'button',
										className: 'button',
										onClick: function () {
											openUnit( row.key );
										}
									},
									__( 'Add a part of it', 'axismundi-contacts' )
								)
							)
							: null
					),
					el( IconButton, {
						icon: 'delete',
						variant: 'danger',
						label: __( 'Remove this organization', 'axismundi-contacts' ),
						onClick: function () {
							remove( row );
						}
					} )
				);
			} ),
			asking
				? el(
					'div',
					{ className: 'ax-ce-blocked', role: 'alert' },
					el(
						'p',
						null,
						__( 'Other parts of this card point at this organization. Removing it would leave them pointing at nothing.', 'axismundi-contacts' )
					),
					el(
						'ul',
						null,
						asking.depends.map( function ( each ) {
							return el( 'li', { key: each.kind + each.id }, each.label );
						} )
					),
					el(
						'p',
						null,
						el(
							'button',
							{
								type: 'button',
								className: 'button',
								onClick: function () {
									props.onResolve( asking );
									setAsking( null );
								}
							},
							__( 'Remove them and the organization', 'axismundi-contacts' )
						),
						' ',
						el(
							'button',
							{
								type: 'button',
								className: 'button',
								onClick: function () {
									setAsking( null );
								}
							},
							__( 'Keep everything', 'axismundi-contacts' )
						)
					)
				)
				: null,
			el(
				'p',
				null,
				el(
					'button',
					{
						type: 'button',
						className: 'button',
						onClick: function () {
							setPending( pending.concat( [ 'org-' + Math.random().toString( 36 ).slice( 2, 8 ) ] ) );
						}
					},
					__( 'Add an organization', 'axismundi-contacts' )
				)
			)
		);
	}

	/** What somebody is called, and whether that is a post or a part they play. */
	var TITLE_KINDS = [
		{ value: 'title', label: __( 'A post they hold', 'axismundi-contacts' ) },
		{ value: 'role', label: __( 'A part they play', 'axismundi-contacts' ) }
	];

	/**
	 * What somebody is called where they work.
	 *
	 * Which employer a title belongs to is `organizationId`, which names an entry in the section
	 * above rather than repeating its name -- so renaming the company renames it once. A title with
	 * no organization is a title somebody holds on their own, which is an ordinary thing to be.
	 */
	function Titles( props ) {
		var entries = props.value || {};
		/*
		 * Rows somebody has opened, each holding whatever they have answered so far. A title is a
		 * title because it has a name -- the standard requires one -- so a row where somebody has
		 * picked the kind and the employer but not yet typed the name is a row and not a title. It
		 * keeps those answers itself until the name arrives, rather than writing a document the
		 * server would refuse.
		 */
		var [ pending, setPending ] = useState( [] );
		var [ asking, setAsking ] = useState( null );
		var organizations = props.organizations || {};
		var employers = Object.keys( organizations ).map( function ( id ) {
			return { value: id, label: ( organizations[ id ] || {} ).name || id };
		} );

		function setEntries( next ) {
			props.onChange( Object.keys( next ).length ? next : undefined );
		}

		function write( row, key, value ) {
			if ( row.pending ) {
				var held = withKey( row.entry, key, value );
				if ( ! String( held.name || '' ).trim() ) {
					// Still only a row: what has been answered waits here for the name.
					setPending( pending.map( function ( each ) {
						return each.id === row.pending ? { id: each.id, entry: held } : each;
					} ) );
					return;
				}
				var next = Object.assign( {}, entries );
				next[ newEntryId( 'ttl', entries ) ] = held;
				setPending( pending.filter( function ( each ) {
					return each.id !== row.pending;
				} ) );
				setEntries( next );
				return;
			}
			var updated = Object.assign( {}, entries );
			updated[ row.id ] = withKey( entries[ row.id ] || {}, key, value );
			setEntries( updated );
		}

		function remove( row ) {
			if ( row.pending ) {
				setPending( pending.filter( function ( each ) {
					return each.id !== row.pending;
				} ) );
				return;
			}
			/*
			 * A language may say this title in its own words. Taking the title away would leave those
			 * patches naming a property the card no longer has, which the server refuses -- so they
			 * are named and somebody decides, the same as removing a part of a name.
			 */
			var affected = patchesUnder( ( props.card || {} ).localizations, 'titles/' + row.id );
			if ( affected.length ) {
				setAsking( { id: row.id, depends: affected.map( function ( each ) {
					return { kind: 'patch', tag: each.tag, path: each.path, label: each.tag + ' — ' + each.path };
				} ) } );
				return;
			}
			var next = Object.assign( {}, entries );
			delete next[ row.id ];
			setEntries( next );
		}

		var rows = Object.keys( entries ).map( function ( id ) {
			return { key: id, id: id, entry: entries[ id ] || {} };
		} ).concat( pending.map( function ( each ) {
			return { key: each.id, pending: each.id, entry: each.entry };
		} ) );

		return el(
			Section,
			{ icon: 'person-text', label: __( 'Titles', 'axismundi-contacts' ) },
			rows.map( function ( row ) {
				var entry = row.entry;
				return el(
					'div',
					{ key: row.key, className: 'ax-ce-title' },
					el( TextField, {
						label: __( 'Title', 'axismundi-contacts' ),
						className: 'ax-ce-title__name',
						value: entry.name || '',
						onChange: function ( value ) {
							write( row, 'name', value );
						}
					} ),
					el( Combobox, {
						label: __( 'What kind', 'axismundi-contacts' ),
						className: 'ax-ce-title__kind',
						value: entry.kind || '',
						options: TITLE_KINDS,
						onChange: function ( value ) {
							write( row, 'kind', value );
						}
					} ),
					employers.length
						? el( Combobox, {
							label: __( 'Where', 'axismundi-contacts' ),
							className: 'ax-ce-title__where',
							value: entry.organizationId || '',
							options: employers,
							supporting: __( 'One of the organizations above.', 'axismundi-contacts' ),
							onChange: function ( value ) {
								write( row, 'organizationId', value );
							}
						} )
						: null,
					el( IconButton, {
						icon: 'delete',
						variant: 'danger',
						label: __( 'Remove this title', 'axismundi-contacts' ),
						onClick: function () {
							remove( row );
						}
					} )
				);
			} ),
			asking
				? el(
					'div',
					{ className: 'ax-ce-blocked', role: 'alert' },
					el(
						'p',
						null,
						__( 'Other languages say this title in their own words. Removing it would leave them saying it about nothing.', 'axismundi-contacts' )
					),
					el(
						'ul',
						null,
						asking.depends.map( function ( each ) {
							return el( 'li', { key: each.tag + each.path }, each.label );
						} )
					),
					el(
						'p',
						null,
						el(
							'button',
							{
								type: 'button',
								className: 'button',
								onClick: function () {
									props.onResolve( asking );
									setAsking( null );
								}
							},
							__( 'Remove them and the title', 'axismundi-contacts' )
						),
						' ',
						el(
							'button',
							{
								type: 'button',
								className: 'button',
								onClick: function () {
									setAsking( null );
								}
							},
							__( 'Keep everything', 'axismundi-contacts' )
						)
					)
				)
				: null,
			el(
				'p',
				null,
				el(
					'button',
					{
						type: 'button',
						className: 'button',
						onClick: function () {
							setPending( pending.concat( [ { id: 'title-' + Math.random().toString( 36 ).slice( 2, 8 ), entry: {} } ] ) );
						}
					},
					__( 'Add a title', 'axismundi-contacts' )
				)
			)
		);
	}

	/**
	 * One account on some service.
	 *
	 * Four answers rather than a URI: what the service is called, what somebody is called there, the
	 * address that identifies them, and where the account sits among the others. A row showing only
	 * its URI is a row nobody can read -- `Mastodon · @pfefferle@mastodon.social` says in two words
	 * what a link says in forty characters, and it is what the Published list has to show for a
	 * checkbox beside it to mean anything.
	 *
	 * The key each row is stored under is not on screen. It is an address -- what a published pointer
	 * names and what a provenance row is written against -- and showing it invites somebody to want
	 * it to say `mastodon`, which would tie their consent to publish to a word they can rename.
	 */
	function OnlineService( props ) {
		var entry = props.entry;
		return el(
			'div',
			{
				className: 'ax-ce-service',
				draggable: true,
				onDragStart: function () {
					props.onDragStart( props.index );
				},
				onDragOver: function ( event ) {
					event.preventDefault();
				},
				onDrop: function () {
					props.onDrop( props.index );
				}
			},
			el( 'span', { className: 'ax-ce-part__grip', 'aria-hidden': 'true', dangerouslySetInnerHTML: { __html: icon( 'drag-indicator' ) } } ),
			el(
				'div',
				{ className: 'ax-ce-service__fields' },
				el( TextField, {
					label: __( 'Service', 'axismundi-contacts' ),
					value: entry.service || '',
					supporting: __( 'What the service is called, such as Mastodon.', 'axismundi-contacts' ),
					onChange: function ( value ) {
						props.onChange( withKey( entry, 'service', value ) );
					}
				} ),
				el( TextField, {
					label: __( 'Username', 'axismundi-contacts' ),
					value: entry.user || '',
					supporting: __( 'What they are called there, such as @name@host.', 'axismundi-contacts' ),
					onChange: function ( value ) {
						props.onChange( withKey( entry, 'user', value ) );
					}
				} ),
				el( TextField, {
					label: __( 'Address', 'axismundi-contacts' ),
					type: 'url',
					value: entry.uri || '',
					supporting: __( 'The profile or Actor this account is.', 'axismundi-contacts' ),
					onChange: function ( value ) {
						props.onChange( withKey( entry, 'uri', value ) );
					}
				} ),
				el( TextField, {
					label: __( 'Label', 'axismundi-contacts' ),
					value: entry.label || '',
					onChange: function ( value ) {
						props.onChange( withKey( entry, 'label', value ) );
					}
				} )
			),
			el( IconButton, {
				icon: 'delete',
				variant: 'danger',
				label: __( 'Remove this account', 'axismundi-contacts' ),
				onClick: props.onRemove
			} )
		);
	}

	/**
	 * The accounts, in the order they are preferred.
	 *
	 * `pref` is that order and the only thing recording it: 1 is the account this person leads with,
	 * which is the one a reader shows and the one a face is taken from. Dragging a row rewrites the
	 * numbers rather than storing a second order beside them, so what the list shows, what a reader
	 * shows, and what the document says are one answer.
	 */
	function OnlineServices( props ) {
		var entries = props.value || {};
		var [ dragging, setDragging ] = useState( null );
		var ordered = orderedServices( entries );

		function setEntries( next ) {
			props.onChange( Object.keys( next ).length ? next : undefined );
		}

		// Whatever the rows read as now, numbered from the top.
		function renumber( ids ) {
			var next = {};
			Object.keys( entries ).forEach( function ( id ) {
				next[ id ] = entries[ id ];
			} );
			ids.forEach( function ( id, index ) {
				next[ id ] = Object.assign( {}, next[ id ], { pref: index + 1 } );
			} );
			setEntries( next );
		}

		return el(
			Section,
			{ icon: 'alternate-email', label: __( 'Online accounts', 'axismundi-contacts' ) },
			el(
				'p',
				{ className: 'description' },
				__( 'Most preferred first. The one at the top is the account this contact leads with.', 'axismundi-contacts' )
			),
			ordered.map( function ( id, index ) {
				return el( OnlineService, {
					key: id,
					index: index,
					entry: entries[ id ] || {},
					onDragStart: setDragging,
					onDrop: function ( at ) {
						if ( null === dragging || dragging === at ) {
							return;
						}
						var ids = ordered.slice();
						var moved = ids.splice( dragging, 1 )[ 0 ];
						ids.splice( at, 0, moved );
						setDragging( null );
						renumber( ids );
					},
					onChange: function ( next ) {
						var updated = Object.assign( {}, entries );
						updated[ id ] = next;
						setEntries( updated );
					},
					onRemove: function () {
						var updated = Object.assign( {}, entries );
						delete updated[ id ];
						setEntries( updated );
					}
				} );
			} ),
			el(
				'p',
				null,
				el(
					'button',
					{
						type: 'button',
						className: 'button',
						onClick: function () {
							var updated = Object.assign( {}, entries );
							// New accounts go to the end; the order somebody chose is theirs to change.
							updated[ newEntryId( 'x', entries ) ] = { service: '', user: '', uri: '', pref: ordered.length + 1 };
							setEntries( updated );
						}
					},
					__( 'Add an account', 'axismundi-contacts' )
				)
			)
		);
	}

	/**
	 * Entry ids in the order they are preferred.
	 *
	 * By `pref`, with the order they sit in the document breaking ties, which is what the server does
	 * when it decides which account leads. An account with no preference is not unranked and last: it
	 * is unranked, which is behind everything that said where it goes.
	 */
	function orderedServices( entries ) {
		var ids = Object.keys( entries || {} );
		return ids.slice().sort( function ( a, b ) {
			var pa = entries[ a ] && 'number' === typeof entries[ a ].pref ? entries[ a ].pref : Infinity;
			var pb = entries[ b ] && 'number' === typeof entries[ b ].pref ? entries[ b ].pref : Infinity;
			return pa === pb ? ids.indexOf( a ) - ids.indexOf( b ) : pa - pb;
		} );
	}

	/**
	 * What a Card is when read in one language.
	 *
	 * The same walk the server does, for showing rather than for storing: a patch names a path and a
	 * value to put there, and `null` takes one away. Nothing here is written back -- this answers
	 * "what would somebody reading in Japanese see", which is a question the editor has to be able to
	 * show before somebody saves.
	 */
	function applyPatch( card, patch ) {
		var out = JSON.parse( JSON.stringify( card || {} ) );
		Object.keys( patch || {} ).forEach( function ( path ) {
			var segments = path.split( '/' );
			var last = segments.pop();
			var at = out;
			for ( var i = 0; i < segments.length; i += 1 ) {
				var step = segments[ i ];
				if ( ! at || 'object' !== typeof at[ step ] ) {
					return;
				}
				at = at[ step ];
			}
			if ( ! at || 'object' !== typeof at ) {
				return;
			}
			if ( null === patch[ path ] ) {
				if ( Array.isArray( at ) ) {
					at.splice( Number( last ), 1 );
				} else {
					delete at[ last ];
				}
				return;
			}
			at[ last ] = patch[ path ];
		} );
		return out;
	}

	/**
	 * The paths a localization may be offered, which is narrower than what is valid.
	 *
	 * Only values the Card already has, and nothing that is about the document rather than about the
	 * person. Setting a property the base Card does not carry is something the standard advises a
	 * writer against, so the picker does not offer it -- while a document that arrived with one is
	 * still stored, because that is a rule for writers and not a reason to refuse.
	 */
	var NOT_TRANSLATED = [ '@type', 'version', 'uid', 'created', 'updated', 'prodId', 'localizations' ];

	function patchablePaths( value, prefix ) {
		var paths = [];
		Object.keys( value || {} ).forEach( function ( key ) {
			var path = prefix ? prefix + '/' + key : key;
			if ( ! prefix && -1 !== NOT_TRANSLATED.indexOf( key ) ) {
				return;
			}
			paths.push( path );
			if ( value[ key ] && 'object' === typeof value[ key ] ) {
				paths = paths.concat( patchablePaths( value[ key ], path ) );
			}
		} );
		return paths;
	}

	/** What a card says at one path, for a screen that wants to start from it. */
	function valueAt( card, path ) {
		var at = card;
		var segments = String( path ).split( '/' );
		for ( var i = 0; i < segments.length; i += 1 ) {
			if ( ! at || 'object' !== typeof at ) {
				return undefined;
			}
			at = at[ segments[ i ] ];
		}
		return at;
	}

	/** Every localization path that goes through one place in the Card. */
	function patchesUnder( localizations, path ) {
		var found = [];
		Object.keys( localizations || {} ).forEach( function ( tag ) {
			Object.keys( localizations[ tag ] || {} ).forEach( function ( patch ) {
				if ( patch === path || 0 === patch.indexOf( path + '/' ) ) {
					found.push( { tag: tag, path: patch } );
				}
			} );
		} );
		return found;
	}

	/**
	 * One language's patch, and what the Card reads as with it applied.
	 *
	 * The paths are offered from the base Card rather than typed, because a patch that names something
	 * the Card does not have is one the server refuses -- so a picker that allowed it would be handing
	 * somebody a save that fails. Anything the list cannot express is written in the JSON below, which
	 * is why that box is here rather than only at the bottom of the screen.
	 */
	function Localization( props ) {
		var patch = props.patch || {};
		var paths = props.paths;
		var [ adding, setAdding ] = useState( '' );
		var [ dragging, setDragging ] = useState( null );
		var [ blocked, setBlocked ] = useState( null );
		var applied = applyPatch( props.card, patch );

		function setPatch( next ) {
			props.onChange( next );
		}

		function setPath( path, value ) {
			var updated = Object.assign( {}, patch );
			if ( undefined === value ) {
				delete updated[ path ];
			} else {
				updated[ path ] = value;
			}
			setPatch( updated );
		}

		return el(
			'div',
			{ className: 'ax-ce-localization' },
			el(
				'div',
				{ className: 'ax-ce-localization__head' },
				el( 'h3', null, props.tag ),
				el( IconButton, {
					icon: 'delete',
					variant: 'danger',
					label: sprintf(
						/* translators: %s: a language tag. */
						__( 'Remove everything written for %s', 'axismundi-contacts' ),
						props.tag
					),
					onClick: props.onRemove
				} )
			),
			Object.keys( patch ).map( function ( path ) {
				var value = patch[ path ];
				var isText = 'string' === typeof value;
				/*
				 * A language that gives the whole name gets the whole name editor. `Kim Jiwoon` in
				 * English is a name, with parts and an order and a way of being said, and asking
				 * somebody to type `{"components":[{"kind":"given",...}]}` into a box is asking them
				 * to be a serializer. It is the same editor as above because it is the same question.
				 */
				if ( 'name' === path && value && 'object' === typeof value && ! Array.isArray( value ) ) {
					return el(
						'div',
						{ key: path, className: 'ax-ce-localization__name' },
						el( NameEditor, {
							name: value,
							kind: props.kind,
							dragging: dragging,
							onDragStart: setDragging,
							// A localization holds no localizations of its own, so nothing patches into this.
							localizations: {},
							blocked: blocked,
							onBlocked: function ( at, affected ) {
								setBlocked( { at: at, affected: affected } );
							},
							onCancel: function () {
								setBlocked( null );
							},
							onResolve: function () {
								setBlocked( null );
							},
							onChange: function ( next ) {
								setPath( path, next );
							}
						} ),
						el(
							'p',
							null,
							el(
								'button',
								{
									type: 'button',
									className: 'button',
									onClick: function () {
										setPath( path, undefined );
									}
								},
								sprintf(
									/* translators: %s: a language tag. */
									__( 'Stop giving the name in %s', 'axismundi-contacts' ),
									props.tag
								)
							)
						)
					);
				}
				return el(
					'div',
					{ key: path, className: 'ax-ce-entry' },
					isText
						? el( TextField, {
							label: path,
							className: 'ax-ce-entry__value',
							value: value,
							onChange: function ( next ) {
								var updated = Object.assign( {}, patch );
								updated[ path ] = next;
								setPatch( updated );
							}
						} )
						: el( TextField, {
							label: path,
							className: 'ax-ce-entry__value',
							value: JSON.stringify( value ),
							readOnly: true,
							// A patch may set a whole object; this screen edits the strings and says so.
							supporting: __( 'Written below, in the JSON, because it is not a single value.', 'axismundi-contacts' ),
							onChange: function () {}
						} ),
					el( IconButton, {
						icon: 'delete',
						variant: 'danger',
						label: sprintf(
							/* translators: %s: a path inside the card. */
							__( 'Stop translating %s', 'axismundi-contacts' ),
							path
						),
						onClick: function () {
							var updated = Object.assign( {}, patch );
							delete updated[ path ];
							setPatch( updated );
						}
					} )
				);
			} ),
			el(
				'div',
				{ className: 'ax-ce-localization__add' },
				el( Combobox, {
					label: __( 'Translate something else', 'axismundi-contacts' ),
					value: adding,
					options: paths.filter( function ( path ) {
						return ! Object.prototype.hasOwnProperty.call( patch, path );
					} ),
					supporting: __( 'Only what the card already says. Anything else goes in the JSON below.', 'axismundi-contacts' ),
					onChange: function ( path ) {
						/*
						 * Started from what the card already says there. Translating a name means
						 * changing one, and handing somebody an empty box for a structure they are
						 * about to rebuild by hand is handing them the serializer's job.
						 */
						var updated = Object.assign( {}, patch );
						var from = valueAt( props.card, path );
						updated[ path ] = null !== from && 'object' === typeof from
							? JSON.parse( JSON.stringify( from ) )
							: '';
						setAdding( '' );
						setPatch( updated );
					}
				} )
			),
			el(
				'details',
				{ className: 'ax-ce-localization__raw' },
				el( 'summary', null, __( 'What this language reads as', 'axismundi-contacts' ) ),
				el(
					'pre',
					{ className: 'ax-ce-preview' },
					JSON.stringify( applied, null, 2 )
				)
			)
		);
	}

	/**
	 * Every language this Card is written in.
	 *
	 * At the bottom of the screen because that is what it is: a set of changes applied over everything
	 * above it. It is not part of the name, however much of it is usually about one -- a localization
	 * can patch a title, an address component or a note just as well.
	 */
	function Localizations( props ) {
		var localizations = props.card.localizations || {};
		var [ tag, setTag ] = useState( '' );
		var paths = patchablePaths( props.card, '' );

		function setLocalizations( next ) {
			props.onChange( Object.keys( next ).length ? next : undefined );
		}

		return el(
			Section,
			{ icon: 'language-international', title: __( 'Other languages', 'axismundi-contacts' ) },
			el(
				'p',
				{ className: 'description' },
				__( 'Each one says what to put where when the card is read in that language. Everything above is what it says otherwise.', 'axismundi-contacts' )
			),
			Object.keys( localizations ).map( function ( each ) {
				return el( Localization, {
					key: each,
					tag: each,
					patch: localizations[ each ],
					card: props.card,
					kind: props.card.kind,
					paths: paths,
					onChange: function ( next ) {
						var updated = Object.assign( {}, localizations );
						if ( next && Object.keys( next ).length ) {
							updated[ each ] = next;
						} else {
							// A language with nothing written for it is a language nobody chose.
							delete updated[ each ];
						}
						setLocalizations( updated );
					},
					onRemove: function () {
						var updated = Object.assign( {}, localizations );
						delete updated[ each ];
						setLocalizations( updated );
					}
				} );
			} ),
			el(
				'div',
				{ className: 'ax-ce-localization__add' },
				el( Combobox, {
					label: __( 'Add a language', 'axismundi-contacts' ),
					value: tag,
					options: LANGUAGES,
					allowFree: true,
					supporting: __( 'Often the same language in another script, like ko-Latn or ja-Kana, which are typed rather than listed.', 'axismundi-contacts' ),
					onChange: setTag
				} ),
				el(
					'button',
					{
						type: 'button',
						className: 'button',
						disabled: ! tag.trim() || Object.prototype.hasOwnProperty.call( localizations, tag.trim() ),
						onClick: function () {
							var updated = Object.assign( {}, localizations );
							updated[ tag.trim() ] = {};
							setTag( '' );
							setLocalizations( updated );
						}
					},
					__( 'Add', 'axismundi-contacts' )
				)
			)
		);
	}

	/**
	 * The languages this contact would rather be written to in, in order.
	 *
	 * The same control as the card's own language and never the same value. One says what the card
	 * above is written in; this says what somebody would like to receive -- a person whose card is in
	 * Korean may ask to be written to in English, and a card that conflated the two would have no way
	 * to say so.
	 *
	 * Order is `pref`, which is what the standard reads: 1 is the one they would rather have.
	 */
	function PreferredLanguages( props ) {
		var entries = props.value || {};
		var ids = Object.keys( entries );

		function setEntries( next ) {
			props.onChange( Object.keys( next ).length ? next : undefined );
		}

		return el(
			Section,
			{ icon: 'language', title: __( 'Preferred languages', 'axismundi-contacts' ) },
			el(
				'p',
				{ className: 'description' },
				__( 'What this contact would rather be written to in, most preferred first. Not the language the card is written in.', 'axismundi-contacts' )
			),
			ids.map( function ( id, index ) {
				var entry = entries[ id ] || {};
				return el(
					'div',
					{ key: id, className: 'ax-ce-entry' },
					el( Combobox, {
						label: __( 'Language', 'axismundi-contacts' ),
						className: 'ax-ce-entry__value',
						value: entry.language || '',
						options: LANGUAGES,
						allowFree: true,
						hideLabel: true,
						supporting: sprintf(
							/* translators: %d: where this sits in the order of preference. */
							__( 'Preferred %d', 'axismundi-contacts' ),
							index + 1
						),
						onChange: function ( value ) {
							var next = Object.assign( {}, entries );
							next[ id ] = Object.assign( {}, entry, { language: value, pref: index + 1 } );
							setEntries( next );
						}
					} ),
					el( IconButton, {
						icon: 'delete',
						variant: 'danger',
						label: __( 'Remove this language', 'axismundi-contacts' ),
						onClick: function () {
							var next = Object.assign( {}, entries );
							delete next[ id ];
							setEntries( next );
						}
					} )
				);
			} ),
			el(
				'p',
				null,
				el(
					'button',
					{
						type: 'button',
						className: 'button',
						onClick: function () {
							var id = 'lang-' + Math.random().toString( 36 ).slice( 2, 8 );
							var next = Object.assign( {}, entries );
							next[ id ] = { language: '', pref: ids.length + 1 };
							setEntries( next );
						}
					},
					__( 'Add a preferred language', 'axismundi-contacts' )
				)
			)
		);
	}

	/**
	 * Which record this is.
	 *
	 * A uid is not a name and not a URL: it is what somebody holding a copy of this Card finds it by,
	 * so that a second copy arriving later is recognised as the same contact rather than kept as a
	 * second person. Once a Card has been published under one it stops being editable -- changing it
	 * would leave everybody who saved the first holding a record they can no longer match -- so it is
	 * shown as what it is rather than hidden in the JSON.
	 *
	 * Cards about other people usually have none, and that is normal. A uid somebody can quote should
	 * come from the person it describes; minting one here for every contact in a private address book
	 * would put this site's invented identifiers into other people's exports.
	 */
	function Identity( props ) {
		var minted = !! props.value;
		return el(
			'section',
			{ className: 'ax-ce-section' },
			el( 'h2', null, __( 'Identity', 'axismundi-contacts' ) ),
			el( TextField, {
				label: __( 'Unique identifier', 'axismundi-contacts' ),
				value: props.value || '',
				disabled: minted,
				supporting: minted
					? __( 'What anybody holding a copy of this card finds it by. It does not change.', 'axismundi-contacts' )
					: __( 'Optional. If this contact published one, it belongs here, so a copy arriving later is recognised as the same person.', 'axismundi-contacts' ),
				onChange: props.onChange
			} )
		);
	}

	/**
	 * The ledger itself, folded away.
	 *
	 * Everything the fields above do not show is here, and stays here: this reads and writes the same
	 * draft they do rather than a copy of it, so a property with no field survives being edited beside
	 * one that has. Closed by default, because open it reads as a second screen competing with the
	 * first -- and the fields are where a Card is meant to be written. What it is for is the property
	 * this editor has no field for yet, which is why it is one fold away rather than gone.
	 */
	function AdvancedJson( props ) {
		var box = el( Textarea, {
			label: __( 'JSContact', 'axismundi-contacts' ),
			className: 'ax-ce-json',
			rows: props.beside ? 30 : 18,
			spellCheck: false,
			value: props.text,
			error: !! props.error,
			supporting: props.error || undefined,
			onChange: props.onChange
		} );
		var explains = __( 'Everything beside this, and everything this editor has no field for. Edits here and edits there are the same document, so this is a way to reach what has no field yet rather than a second way to fill in the fields that have one.', 'axismundi-contacts' );
		/*
		 * Beside the fields it is not a section of the form: it is the same draft, seen the other way,
		 * and it scrolls with itself so that reading the document does not mean losing the field being
		 * typed into.
		 */
		if ( props.beside ) {
			return el(
				'div',
				{ className: 'ax-ce-json-pane' },
				el( 'h2', null, __( 'JSContact', 'axismundi-contacts' ) ),
				el( 'p', { className: 'description' }, explains ),
				box
			);
		}
		return el(
			'details',
			{ className: 'ax-ce-section ax-ce-json-section' },
			el( 'summary', null, __( 'Advanced JSON', 'axismundi-contacts' ) ),
			el( 'p', { className: 'description' }, explains ),
			box
		);
	}

	/** Where the split sits, as a share of the screen given to the fields. */
	var SPLIT_KEY = 'axismundiContactsSplit';
	var SPLIT_DEFAULT = 40;

	/**
	 * The handle between the two.
	 *
	 * Dragged with the mouse and moved with the arrow keys, because a divider that can only be
	 * dragged is a divider some people cannot move at all.
	 */
	function SplitHandle( props ) {
		function fromPointer( event ) {
			var host = event.currentTarget.parentNode;
			var box = host.getBoundingClientRect();
			if ( ! box.width ) {
				return;
			}
			props.onChange( ( ( event.clientX - box.left ) / box.width ) * 100 );
		}
		return el( 'div', {
			className: 'ax-ce-split__handle',
			role: 'separator',
			tabIndex: 0,
			'aria-orientation': 'vertical',
			'aria-label': __( 'How much of the screen the fields take', 'axismundi-contacts' ),
			'aria-valuenow': Math.round( props.value ),
			'aria-valuemin': 20,
			'aria-valuemax': 80,
			onKeyDown: function ( event ) {
				if ( 'ArrowLeft' === event.key ) {
					props.onChange( props.value - 2 );
				} else if ( 'ArrowRight' === event.key ) {
					props.onChange( props.value + 2 );
				} else {
					return;
				}
				event.preventDefault();
			},
			onPointerDown: function ( event ) {
				event.currentTarget.setPointerCapture( event.pointerId );
				props.onDragging( true );
			},
			onPointerMove: function ( event ) {
				if ( event.currentTarget.hasPointerCapture( event.pointerId ) ) {
					fromPointer( event );
				}
			},
			onPointerUp: function ( event ) {
				event.currentTarget.releasePointerCapture( event.pointerId );
				props.onDragging( false );
			}
		} );
	}

	/**
	 * What one entry reads as, for somebody deciding whether to publish it.
	 *
	 * A checkbox beside a URI asks a question nobody can answer. An account is `Mastodon` and
	 * `@pfefferle@mastodon.social`; the address is how a machine finds it and goes underneath, where
	 * it can be checked without being what the row says. When an entry has neither, the property and
	 * its position are still something to point at -- `Online account 2` is at least a row on the
	 * screen above, which its key is not.
	 */
	function entryLabel( property, entry, index ) {
		if ( 'onlineServices' === property ) {
			var named = [ entry.service, entry.user ].filter( function ( part ) {
				return part && String( part ).trim();
			} );
			return {
				label: named.length
					? named.join( ' · ' )
					: sprintf(
						/* translators: %d: which account on the card, counting from the top. */
						__( 'Online account %d', 'axismundi-contacts' ),
						index + 1
					),
				detail: entry.uri || ''
			};
		}
		var text = entry.address || entry.number || entry.uri || entry.note || entry.name || '';
		return {
			label: property + ( text ? ': ' + text : '' ),
			detail: ''
		};
	}

	/** Which parts of this Card a stranger may have. Only the Card an Actor publishes has any. */
	function PublishedFields( props ) {
		var card = props.card || {};
		var chosen = props.published || [];

		function toggle( pointer, on ) {
			var next = chosen.filter( function ( value ) {
				return value !== pointer;
			} );
			if ( on ) {
				next.push( pointer );
			}
			props.onChange( next );
		}

		function row( pointer, label, detail ) {
			return el(
				'p',
				{ key: pointer, className: 'ax-ce-published__row' },
				el(
					'label',
					null,
					el( 'input', {
						type: 'checkbox',
						checked: -1 !== chosen.indexOf( pointer ),
						onChange: function ( event ) {
							toggle( pointer, event.target.checked );
						}
					} ),
					' ',
					el( 'span', { className: 'ax-ce-published__label' }, label ),
					detail ? el( 'span', { className: 'ax-ce-published__detail' }, detail ) : null
				)
			);
		}

		return el(
			'section',
			{ className: 'ax-ce-section' },
			el( 'h2', null, __( 'Published', 'axismundi-contacts' ) ),
			el(
				'p',
				{ className: 'description' },
				__( 'Sharing decides whether this card is published at all. This decides what of it.', 'axismundi-contacts' )
			),
			config.publishableSingular.map( function ( property ) {
				return row( property, property );
			} ),
			config.publishableEntries.map( function ( property ) {
				var entries = card[ property ] || {};
				var ids = 'onlineServices' === property ? orderedServices( entries ) : Object.keys( entries );
				return ids.map( function ( id, index ) {
					var entry = entries[ id ] || {};
					var read = entryLabel( property, entry, index );
					return row( property + '/' + id, read.label, read.detail );
				} );
			} )
		);
	}

	/**
	 * The draft, as it is worth storing.
	 *
	 * A part of a name that was added and never filled in is somebody who clicked a button and
	 * changed their mind, not a part of their name that is blank. It is dropped on the way out rather
	 * than written down, which is why the buttons above can add a row without asking first.
	 *
	 * A separator keeps its empty value: that one is an answer -- it is how a name written with
	 * nothing between its parts is said.
	 *
	 * Left alone entirely when a language patches into the parts. Dropping one there would move every
	 * part after it up a place while the patches still name the old positions, and a patch pointing
	 * at the wrong part is worse than a row nobody filled in.
	 */
	function prepare( card ) {
		var parts = ( card.name || {} ).components;
		if ( ! parts || patchesUnder( card.localizations, 'name/components' ).length ) {
			return card;
		}
		var kept = parts.filter( function ( part ) {
			return part && (
				'separator' === part.kind
					|| ( part.value && String( part.value ).trim() )
					|| ( part.phonetic && String( part.phonetic ).trim() )
			);
		} );
		if ( kept.length === parts.length ) {
			return card;
		}
		var next = Object.assign( {}, card );
		var name = Object.assign( {}, next.name );
		if ( kept.length ) {
			name.components = kept;
		} else {
			delete name.components;
			delete name.isOrdered;
			delete name.defaultSeparator;
			delete name.sortAs;
		}
		if ( Object.keys( name ).length ) {
			next.name = name;
		} else {
			delete next.name;
		}
		return next;
	}

	/**
	 * The draft, in the order a stored Card is written in.
	 *
	 * A property assigned to a JavaScript object lands at the end of it, so a card that gained a
	 * `language` showed it below `localizations` until a save moved it -- and the JSON somebody is
	 * reading while they type is exactly the one that looked wrong. The order is the server's own,
	 * handed over rather than written here again, so the two cannot drift.
	 */
	function ordered( card ) {
		var out = {};
		( config.order || [] ).forEach( function ( key ) {
			if ( Object.prototype.hasOwnProperty.call( card, key ) ) {
				out[ key ] = card[ key ];
			}
		} );
		Object.keys( card ).sort().forEach( function ( key ) {
			// Anything the list does not name -- a vendor's own property, a newer revision's -- after it.
			if ( ! Object.prototype.hasOwnProperty.call( out, key ) ) {
				out[ key ] = card[ key ];
			}
		} );
		return out;
	}

	/** The whole screen. */
	function Editor() {
		var [ card, setCard ] = useState( config.card );
		var [ revision, setRevision ] = useState( config.revision );
		var [ published, setPublished ] = useState( config.published );
		var [ json, setJson ] = useState( JSON.stringify( config.card, null, 2 ) );
		var [ jsonError, setJsonError ] = useState( '' );
		var [ dragging, setDragging ] = useState( null );
		var [ blocked, setBlocked ] = useState( null );

		// A locked kind is the Actor's answer, so the card says it whatever it said before.
		if ( config.lockedKind && card.kind !== config.lockedKind ) {
			window.setTimeout( function () {
				setProperty( 'kind', config.lockedKind );
			}, 0 );
		}
		var [ status, setStatus ] = useState( '' );
		var [ saving, setSaving ] = useState( false );
		/*
		 * Whether the document stands beside the fields. Useful while the fields are being built --
		 * type on the left, watch what it writes on the right -- and noise for somebody writing down
		 * a phone number, so it is remembered per person rather than decided for everybody.
		 */
		var [ beside, setBeside ] = useState( 'true' === window.localStorage.getItem( SPLIT_KEY + 'Open' ) );
		var [ split, setSplitAt ] = useState( Number( window.localStorage.getItem( SPLIT_KEY ) ) || SPLIT_DEFAULT );
		var [ sliding, setSliding ] = useState( false );

		function setSplit( value ) {
			var next = Math.min( 80, Math.max( 20, value ) );
			window.localStorage.setItem( SPLIT_KEY, String( Math.round( next ) ) );
			setSplitAt( next );
		}

		// One draft. Every view writes through here, so the JSON box and the fields never diverge.
		var update = useCallback( function ( next ) {
			var written = ordered( next );
			setCard( written );
			setJson( JSON.stringify( written, null, 2 ) );
			setJsonError( '' );
		}, [] );

		function setProperty( key, value ) {
			var next = Object.assign( {}, card );
			if ( undefined === value ) {
				delete next[ key ];
			} else {
				next[ key ] = value;
			}
			update( next );
		}

		function onJson( text ) {
			setJson( text );
			try {
				var parsed = JSON.parse( text );
				if ( ! parsed || 'object' !== typeof parsed || Array.isArray( parsed ) ) {
					throw new Error( __( 'A card is an object.', 'axismundi-contacts' ) );
				}
				// Not reordered: this is somebody typing, and tidying it would move the cursor out
				// from under them. What is stored is ordered on save, and read back that way.
				setCard( parsed );
				setJsonError( '' );
			} catch ( error ) {
				// Kept as typed. Reformatting somebody's half-finished JSON while they are in the middle
				// of it is how an editor moves the cursor out from under them.
				setJsonError( error.message );
			}
		}

		function save() {
			if ( jsonError ) {
				setStatus( __( 'The JSON has an error, so nothing was saved.', 'axismundi-contacts' ) );
				return;
			}
			setSaving( true );
			setStatus( '' );
			var body = { revision: revision, card: prepare( card ) };
			if ( config.isProfile ) {
				body.publishedPointers = published;
			}
			apiFetch( { path: config.draftPath, method: 'PUT', data: body } )
				.then( function ( response ) {
					// What comes back is what is stored, in the order it is stored in.
					setRevision( response.revision );
					update( response.card );
					if ( response.publishedPointers ) {
						setPublished( response.publishedPointers );
					}
					setStatus( __( 'Saved.', 'axismundi-contacts' ) );
				} )
				.catch( function ( error ) {
					setStatus( error && error.message ? error.message : __( 'That could not be saved.', 'axismundi-contacts' ) );
				} )
				.finally( function () {
					setSaving( false );
				} );
		}

		return el(
			Fragment,
			null,
			el(
				'p',
				{ className: 'ax-ce__view' },
				el(
					'label',
					null,
					el( 'input', {
						type: 'checkbox',
						checked: beside,
						onChange: function ( event ) {
							window.localStorage.setItem( SPLIT_KEY + 'Open', event.target.checked ? 'true' : 'false' );
							setBeside( event.target.checked );
						}
					} ),
					' ',
					__( 'Show the JSContact beside the fields', 'axismundi-contacts' )
				)
			),
			el(
				'div',
				{
					className: 'ax-ce' + ( beside ? ' is-split' : '' ) + ( sliding ? ' is-sliding' : '' ),
					style: beside ? { gridTemplateColumns: split + '% 8px 1fr' } : undefined
				},
				el(
					'div',
					{ className: 'ax-ce__main' },
					el( KindField, {
						value: card.kind,
						locked: config.lockedKind,
						onChange: function ( value ) {
							setProperty( 'kind', value );
						}
					} ),
					el(
						Section,
						{ icon: 'language', title: __( 'Language', 'axismundi-contacts' ) },
						el( CardLanguage, {
							value: card.language,
							onChange: function ( value ) {
								setProperty( 'language', value );
							}
						} )
					),
					el( NameEditor, {
						name: card.name,
						// What the card describes decides what a name is: an organisation has no surname.
						kind: card.kind,
						dragging: dragging,
						onDragStart: setDragging,
						localizations: card.localizations,
						blocked: blocked,
						onBlocked: function ( at, affected ) {
							setBlocked( { at: at, affected: affected } );
						},
						onCancel: function () {
							setBlocked( null );
						},
						onResolve: function ( question ) {
							/*
							 * Both at once, because either alone is a Card the server refuses: the part
							 * without its translations is what somebody asked for, and the translations
							 * without their part is a patch pointing at nothing.
							 */
							var next = Object.assign( {}, card );
							var parts = ( ( next.name || {} ).components || [] ).filter( function ( ignored, i ) {
								return i !== question.at;
							} );
							next.name = Object.assign( {}, next.name );
							if ( parts.length ) {
								next.name.components = parts;
							} else {
								delete next.name.components;
								delete next.name.isOrdered;
								delete next.name.defaultSeparator;
							}
							var localizations = Object.assign( {}, next.localizations );
							question.affected.forEach( function ( each ) {
								var patch = Object.assign( {}, localizations[ each.tag ] );
								delete patch[ each.path ];
								if ( Object.keys( patch ).length ) {
									localizations[ each.tag ] = patch;
								} else {
									delete localizations[ each.tag ];
								}
							} );
							if ( Object.keys( localizations ).length ) {
								next.localizations = localizations;
							} else {
								delete next.localizations;
							}
							setBlocked( null );
							update( next );
						},
						onChange: function ( value ) {
							setProperty( 'name', value );
						}
					} ),
					el( Organizations, {
						value: card.organizations,
						card: card,
						onChange: function ( value ) {
							setProperty( 'organizations', value );
						},
						/*
						 * Both at once, because either alone is a Card the server refuses: an
						 * organization removed on its own leaves a title whose employer nothing can
						 * resolve, and a language still translating a name that is no longer there.
						 */
						onResolve: function ( question ) {
							var next = Object.assign( {}, card );
							var organizations = Object.assign( {}, next.organizations );
							delete organizations[ question.id ];
							if ( Object.keys( organizations ).length ) {
								next.organizations = organizations;
							} else {
								delete next.organizations;
							}
							var titles = Object.assign( {}, next.titles );
							Object.keys( titles ).forEach( function ( key ) {
								if ( question.id === ( titles[ key ] || {} ).organizationId ) {
									// The title stays; what it said about where it was held does not.
									titles[ key ] = withKey( titles[ key ], 'organizationId', '' );
								}
							} );
							if ( Object.keys( titles ).length ) {
								next.titles = titles;
							}
							var localizations = withoutPatches( next.localizations, question.depends.filter( function ( each ) {
								return each.tag;
							} ) );
							if ( Object.keys( localizations ).length ) {
								next.localizations = localizations;
							} else {
								delete next.localizations;
							}
							update( next );
						}
					} ),
					el( Titles, {
						value: card.titles,
						organizations: card.organizations,
						card: card,
						onChange: function ( value ) {
							setProperty( 'titles', value );
						},
						/*
						 * Both at once, because either alone is a Card the server refuses: the title
						 * gone without its translations leaves them saying something about a property
						 * the card no longer has.
						 */
						onResolve: function ( question ) {
							var next = Object.assign( {}, card );
							var titles = Object.assign( {}, next.titles );
							delete titles[ question.id ];
							if ( Object.keys( titles ).length ) {
								next.titles = titles;
							} else {
								delete next.titles;
							}
							var localizations = withoutPatches( next.localizations, question.depends );
							if ( Object.keys( localizations ).length ) {
								next.localizations = localizations;
							} else {
								delete next.localizations;
							}
							update( next );
						}
					} ),
					el( Phones, {
						value: card.phones,
						card: card,
						onChange: function ( value ) {
							setProperty( 'phones', value );
						},
						onResolve: function ( question ) {
							var next = Object.assign( {}, card );
							var phones = Object.assign( {}, next.phones );
							delete phones[ question.id ];
							if ( Object.keys( phones ).length ) {
								next.phones = phones;
							} else {
								delete next.phones;
							}
							var localizations = withoutPatches( next.localizations, question.depends );
							if ( Object.keys( localizations ).length ) {
								next.localizations = localizations;
							} else {
								delete next.localizations;
							}
							update( next );
						}
					} ),
					el( OnlineServices, {
						value: card.onlineServices,
						onChange: function ( value ) {
							setProperty( 'onlineServices', value );
						}
					} ),
					el( PreferredLanguages, {
						value: card.preferredLanguages,
						onChange: function ( value ) {
							setProperty( 'preferredLanguages', value );
						}
					} ),
					ENTRY_FIELDS.map( function ( field ) {
						return el( EntryField, {
							key: field.key,
							field: field,
							entries: card[ field.key ],
							onChange: function ( value ) {
								setProperty( field.key, value );
							}
						} );
					} ),
					el( Localizations, {
						card: card,
						onChange: function ( value ) {
							setProperty( 'localizations', value );
						}
					} ),
					config.isProfile
						? el( PublishedFields, { card: card, published: published, onChange: setPublished } )
						: null,
					el( Identity, {
						value: card.uid,
						onChange: function ( value ) {
							setProperty( 'uid', value );
						}
					} ),
					beside ? null : el( AdvancedJson, { text: json, error: jsonError, onChange: onJson } )
				),
				beside ? el( SplitHandle, { value: split, onChange: setSplit, onDragging: setSliding } ) : null,
				beside ? el( AdvancedJson, { text: json, error: jsonError, onChange: onJson, beside: true } ) : null
			),
			el(
				'p',
				{ className: 'ax-ce__actions' },
				el(
					'button',
					{ type: 'button', className: 'button button-primary', disabled: saving, onClick: save },
					saving ? __( 'Saving…', 'axismundi-contacts' ) : __( 'Save', 'axismundi-contacts' )
				),
				' ',
				el( 'a', { className: 'button', href: config.backUrl }, __( 'Done', 'axismundi-contacts' ) ),
				status ? el( 'span', { className: 'ax-ce__status' }, status ) : null
			)
		);
	}

	wp.element.createRoot
		? wp.element.createRoot( root ).render( el( Editor ) )
		: wp.element.render( el( Editor ), root );
}( window.wp, window.axismundiContactsCardEditor ) );
