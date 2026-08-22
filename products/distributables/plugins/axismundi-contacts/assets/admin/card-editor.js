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
		var blocked = props.blocked;

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
						localizations: props.localizations,
						onBlocked: props.onBlocked,
						onChange: function ( at, next ) {
							var list = components.slice();
							list[ at ] = next;
							setComponents( list );
						},
						onRemove: function ( at ) {
							/*
							 * A translation may patch into this part. Removing it would leave those patches
							 * pointing at nothing, which the server refuses -- so the ones affected are
							 * named and somebody decides, rather than being deleted quietly along with a
							 * part they were not looking at.
							 */
							var affected = patchesUnder( props.localizations, 'name/components/' + at );
							if ( affected.length ) {
								props.onBlocked( at, affected );
								return;
							}
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
		var applied = applyPatch( props.card, patch );

		function setPatch( next ) {
			props.onChange( next );
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
						var updated = Object.assign( {}, patch );
						updated[ path ] = '';
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
			'section',
			{ className: 'ax-ce-section' },
			el( 'h2', null, __( 'Other languages', 'axismundi-contacts' ) ),
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
				el( TextField, {
					label: __( 'Add a language', 'axismundi-contacts' ),
					value: tag,
					supporting: __( 'A language tag, like ko-KR or ja-Kana.', 'axismundi-contacts' ),
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
		var [ blocked, setBlocked ] = useState( null );
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
