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
	var useState = wp.element.useState;
	var useCallback = wp.element.useCallback;
	var apiFetch = wp.apiFetch;
	var __ = wp.i18n.__;
	var sprintf = wp.i18n.sprintf;

	/** One icon's markup, from the registry this plugin fills. */
	function icon( name ) {
		return ( window.axismundiContactsIcons || {} )[ name ] || '';
	}

	/** Properties this draws rows for, and what each entry's value is called. */
	var ENTRY_FIELDS = [
		{ key: 'emails', label: __( 'Email', 'axismundi-contacts' ), value: 'address', type: 'email', icon: 'mail' },
		{ key: 'phones', label: __( 'Phone', 'axismundi-contacts' ), value: 'number', icon: 'call' },
		{ key: 'onlineServices', label: __( 'Online accounts', 'axismundi-contacts' ), value: 'uri', type: 'url', icon: 'account-circle' },
		{ key: 'links', label: __( 'Links', 'axismundi-contacts' ), value: 'uri', type: 'url', icon: 'link' },
		{ key: 'media', label: __( 'Media', 'axismundi-contacts' ), value: 'uri', type: 'url', icon: 'image' },
		{ key: 'notes', label: __( 'Notes', 'axismundi-contacts' ), value: 'note', icon: 'file-json' }
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
	 * One part of a name.
	 *
	 * A separator is a component like any other and carries a value, which may be empty -- that is how
	 * a name written with nothing between its parts is said.
	 */
	function Component( props ) {
		var part = props.part;
		return el(
			'li',
			{
				className: 'ax-ce-part',
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
			el(
				'span',
				{ className: 'ax-ce-part__grip', 'aria-hidden': 'true', dangerouslySetInnerHTML: { __html: icon( 'drag-indicator' ) } }
			),
			/*
			 * A plain select, for now. Which of the six kinds a part is comes from a closed list, and
			 * the field adapter deliberately has no Select yet -- the path picker that will want one
			 * has to decide first whether it is a menu or something you type into.
			 */
			el(
				'select',
				{
					className: 'ax-ce-part__kind',
					value: part.kind || 'given',
					'aria-label': __( 'Kind of name part', 'axismundi-contacts' ),
					onChange: function ( event ) {
						props.onChange( props.index, withKey( part, 'kind', event.target.value ) );
					}
				},
				COMPONENT_KINDS.map( function ( kind ) {
					return el( 'option', { key: kind, value: kind }, kind );
				} )
			),
			el( TextField, {
				label: 'separator' === part.kind ? __( 'Between the parts', 'axismundi-contacts' ) : __( 'Value', 'axismundi-contacts' ),
				className: 'ax-ce-part__value',
				value: undefined === part.value ? '' : part.value,
				supporting: 'separator' === part.kind ? __( 'Empty for names written without spaces.', 'axismundi-contacts' ) : undefined,
				onChange: function ( value ) {
					// A separator keeps an empty value, because an empty separator is a real answer.
					var next = Object.assign( {}, part );
					next.value = value;
					if ( '' === next.value && 'separator' !== next.kind ) {
						delete next.value;
					}
					props.onChange( props.index, next );
				}
			} ),
			el( IconButton, {
				icon: 'delete',
				variant: 'danger',
				label: __( 'Remove this part of the name', 'axismundi-contacts' ),
				onClick: function () {
					props.onRemove( props.index );
				}
			} )
		);
	}

	/**
	 * The name.
	 *
	 * Either half is a whole name. Somebody may write one out and never take it apart, or give the
	 * parts and never write it out -- which is what an import brings, and what the drag order and the
	 * separator are for. Neither is filled in on the other's behalf.
	 */
	function NameEditor( props ) {
		var name = props.name || {};
		var components = name.components || [];
		var dragging = props.dragging;

		function setName( next ) {
			// A name with nothing in it is not a name, so the property goes rather than sitting empty.
			var keys = Object.keys( next ).filter( function ( key ) {
				return '@type' !== key;
			} );
			props.onChange( keys.length ? next : undefined );
		}

		function setComponents( list ) {
			var next = Object.assign( {}, name );
			if ( list.length ) {
				next.components = list;
			} else {
				delete next.components;
				delete next.isOrdered;
				delete next.defaultSeparator;
			}
			setName( next );
		}

		return el(
			'section',
			{ className: 'ax-ce-section' },
			el( 'h2', null, __( 'Name', 'axismundi-contacts' ) ),
			el( TextField, {
				label: __( 'Written out', 'axismundi-contacts' ),
				value: name.full || '',
				supporting: sprintf(
					/* translators: %s: what the name reads as. */
					__( 'Reads as: %s', 'axismundi-contacts' ),
					nameText( name ) || __( '(nothing yet)', 'axismundi-contacts' )
				),
				onChange: function ( value ) {
					setName( withKey( name, 'full', value ) );
				}
			} ),
			el(
				'ul',
				{ className: 'ax-ce-parts' + ( null === dragging ? '' : ' is-dragging' ) },
				components.map( function ( part, index ) {
					return el( Component, {
						key: index,
						index: index,
						part: part || {},
						onChange: function ( at, next ) {
							var list = components.slice();
							list[ at ] = next;
							setComponents( list );
						},
						onRemove: function ( at ) {
							setComponents( components.filter( function ( ignored, i ) {
								return i !== at;
							} ) );
						},
						onDragStart: props.onDragStart,
						onDrop: function ( at ) {
							if ( null === dragging || dragging === at ) {
								return;
							}
							var list = components.slice();
							var moved = list.splice( dragging, 1 )[ 0 ];
							list.splice( at, 0, moved );
							setComponents( list );
							props.onDragStart( null );
						}
					} );
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
							setComponents( components.concat( [ { '@type': 'NameComponent', kind: 'given', value: '' } ] ) );
						}
					},
					__( 'Add a part', 'axismundi-contacts' )
				)
			),
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
								checked: true === name.isOrdered,
								onChange: function ( event ) {
									setName( withKey( name, 'isOrdered', event.target.checked ? true : '' ) );
								}
							} ),
							' ',
							__( 'These parts are already in the order they are read', 'axismundi-contacts' )
						)
					),
					el( TextField, {
						label: __( 'Between the parts', 'axismundi-contacts' ),
						className: 'ax-ce-separator',
						value: undefined === name.defaultSeparator ? ' ' : name.defaultSeparator,
						supporting: __( 'Empty for names written without spaces.', 'axismundi-contacts' ),
						onChange: function ( value ) {
							var next = Object.assign( {}, name );
							next.defaultSeparator = value;
							setName( next );
						}
					} )
				)
				: null
		);
	}

	/** One repeating property, as rows keyed by the id the rest of the system addresses them by. */
	function EntryField( props ) {
		var entries = props.entries || {};
		var ids = Object.keys( entries );

		function setEntries( next ) {
			props.onChange( Object.keys( next ).length ? next : undefined );
		}

		return el(
			'section',
			{ className: 'ax-ce-section' },
			el( 'h2', null, props.field.label ),
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
	 * The ledger itself.
	 *
	 * Everything the fields above do not show is here, and stays here: this reads and writes the same
	 * draft they do rather than a copy of it, so a property with no field survives being edited beside
	 * one that has.
	 */
	function AdvancedJson( props ) {
		var text = props.text;
		return el(
			'section',
			{ className: 'ax-ce-section' },
			el( 'h2', null, __( 'The card itself', 'axismundi-contacts' ) ),
			el(
				'p',
				{ className: 'description' },
				__( 'Everything above, and everything this editor has no field for. Edits here and edits above are the same document.', 'axismundi-contacts' )
			),
			el( Textarea, {
				label: __( 'JSContact', 'axismundi-contacts' ),
				className: 'ax-ce-json',
				rows: 18,
				spellCheck: false,
				value: text,
				error: !! props.error,
				supporting: props.error || undefined,
				onChange: props.onChange
			} )
		);
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

		function row( pointer, label ) {
			return el(
				'p',
				{ key: pointer },
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
					label
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
				return Object.keys( entries ).map( function ( id ) {
					var entry = entries[ id ] || {};
					var text = entry.address || entry.number || entry.uri || entry.note || entry.name || id;
					return row( property + '/' + id, property + ': ' + text );
				} );
			} )
		);
	}

	/** The whole screen. */
	function Editor() {
		var [ card, setCard ] = useState( config.card );
		var [ revision, setRevision ] = useState( config.revision );
		var [ published, setPublished ] = useState( config.published );
		var [ json, setJson ] = useState( JSON.stringify( config.card, null, 2 ) );
		var [ jsonError, setJsonError ] = useState( '' );
		var [ dragging, setDragging ] = useState( null );
		var [ status, setStatus ] = useState( '' );
		var [ saving, setSaving ] = useState( false );

		// One draft. Every view writes through here, so the JSON box and the fields never diverge.
		var update = useCallback( function ( next ) {
			setCard( next );
			setJson( JSON.stringify( next, null, 2 ) );
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
				setStatus( __( 'The card itself has an error, so nothing was saved.', 'axismundi-contacts' ) );
				return;
			}
			setSaving( true );
			setStatus( '' );
			var body = { revision: revision, card: card };
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
				'div',
				{ className: 'ax-ce' },
				el(
					'div',
					{ className: 'ax-ce__main' },
					el( NameEditor, {
						name: card.name,
						dragging: dragging,
						onDragStart: setDragging,
						onChange: function ( value ) {
							setProperty( 'name', value );
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
					config.isProfile
						? el( PublishedFields, { card: card, published: published, onChange: setPublished } )
						: null,
					el( AdvancedJson, { text: json, error: jsonError, onChange: onJson } )
				)
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
