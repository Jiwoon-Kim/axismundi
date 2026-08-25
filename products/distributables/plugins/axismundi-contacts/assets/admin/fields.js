/**
 * The field primitives the contacts editor is built from.
 *
 * Four of them: a text field, a multi-line one, a button with a picture on it, and a field you type
 * into that offers what is already there. A `Select` is deliberately absent -- the paths a
 * localization may patch run deep and there are dozens on a full Card, so the picker that needed one
 * turned out to want typing rather than scrolling, and a general dropdown nobody uses would be a
 * component maintained for nothing.
 *
 * The markup is the lab pattern's, not an approximation of it: `text-field > __container > input +
 * label`, with the label floated by `:placeholder-shown` rather than by anything here. That is why
 * every input carries `placeholder=" "` -- a field already knows whether it is empty, and asking
 * JavaScript to tell it would mean the label sat wrong for as long as the script took to load.
 *
 * An id is generated when the caller does not give one, because a label that is not `for` its input
 * is a label a screen reader will not read out and a click will not focus.
 */
( function ( wp ) {
	'use strict';

	if ( ! wp || ! wp.element ) {
		return;
	}

	var el = wp.element.createElement;
	var Fragment = wp.element.Fragment;
	var useRef = wp.element.useRef;
	var useEffect = wp.element.useEffect;
	/*
	 * Translation, guarded. This file draws one string of its own -- what a list says when nothing in
	 * it matches -- and it is drawn on the one render where somebody has typed something the list
	 * does not have, which is exactly when a missing function takes the whole editor down with it.
	 */
	var __ = wp.i18n && wp.i18n.__ ? wp.i18n.__ : function ( text ) {
		return text;
	};
	var counter = 0;

	/** An id of this field's own, stable for as long as it is on screen. */
	function useFieldId( given ) {
		var ref = useRef( null );
		if ( ! ref.current ) {
			counter += 1;
			ref.current = given || 'ax-field-' + counter;
		}
		return given || ref.current;
	}

	/**
	 * Every field on screen, so that one filled in behind this code's back can be noticed.
	 *
	 * A browser filling in an address writes several boxes at once and fires nothing a script
	 * listens for. Worse, it writes into boxes nobody is looking at: the person clicked one field and
	 * accepted a suggestion, and the city, the region and the postcode were filled in beside it. So
	 * there is no one field to watch -- the answer is to look at all of them.
	 *
	 * Each entry holds the node and a box carrying whatever this render's value and writer are, since
	 * both change on every render and the registration does not.
	 */
	var onScreen = [];

	function watchField( node, current ) {
		var entry = { node: node, current: current };
		onScreen.push( entry );
		return function () {
			onScreen = onScreen.filter( function ( each ) {
				return each !== entry;
			} );
		};
	}

	/**
	 * Anything a field is showing that the document does not say.
	 *
	 * What is compared is what this code last drew, so a field showing `010-2742-1672` for a card
	 * saying `tel:+82…` is not mistaken for a change -- the caller passes what it wants shown, and
	 * that is what is checked against. When they differ, something wrote into the field, and it goes
	 * through the same writer a keystroke would.
	 */
	function sweep() {
		onScreen.forEach( function ( each ) {
			var node = each.node.current;
			var held = each.current.current;
			if ( ! node || ! held || 'function' !== typeof held.onChange ) {
				return;
			}
			var shown = undefined === held.value || null === held.value ? '' : String( held.value );
			if ( node.value === shown ) {
				return;
			}
			/*
			 * Said once. Between writing a value and the screen being drawn again there is a gap, and
			 * a check running every tenth of a second inside that gap would keep finding the same
			 * difference and keep reporting it -- which for a field that makes something when it is
			 * first filled in means making it over and over.
			 */
			if ( node.value === each.pushed ) {
				return;
			}
			each.pushed = node.value;
			held.onChange( node.value );
		} );
	}

	/*
	 * When to look. A browser fills in on a gesture -- somebody clicks a field and accepts what it
	 * offers -- so a focus is the signal that one may be about to happen, and the next second or two
	 * is when it does. Blur is the last chance, and the CSS animation below is a third way of hearing
	 * about it where the browser offers one.
	 *
	 * Nothing runs while nobody is in a field.
	 */
	var looking = null;

	function lookForAWhile() {
		if ( looking ) {
			window.clearTimeout( looking.until );
		} else {
			looking = { every: window.setInterval( sweep, 120 ) };
		}
		looking.until = window.setTimeout( function () {
			window.clearInterval( looking.every );
			looking = null;
			sweep();
		}, 2000 );
	}

	/** The animation a browser starts when it fills a field in, where it starts one. */
	function autofilled() {
		return function ( event ) {
			if ( 'ax-contacts-autofill' !== event.animationName ) {
				return;
			}
			sweep();
		};
	}

	/**
	 * The shell both text fields share.
	 *
	 * `supporting` is tied to the input with `aria-describedby` rather than sitting near it: an error
	 * nobody's screen reader mentions is an error only sighted users are told about.
	 */
	function Shell( props ) {
		var id = props.id;
		var describedBy = props.supporting ? id + '-supporting' : undefined;
		return el(
			'div',
			{
				className: 'text-field text-field--outlined'
					+ ( props.error ? ' is-error' : '' )
					+ ( props.className ? ' ' + props.className : '' )
			},
			el(
				'div',
				{ className: 'text-field__container' },
				props.control( describedBy ),
				/*
				 * The label is still written down when it is not shown. A section headed `Language`
				 * holding one field does not need the word twice, but the field is still the thing
				 * being labelled -- for a screen reader, and for whoever is tabbing through it.
				 */
				el(
					'label',
					{ className: 'text-field__label' + ( props.hideLabel ? ' screen-reader-text' : '' ), htmlFor: id },
					props.label
				),
				props.trailing || null
			),
			props.supporting
				? el(
					'div',
					{ className: 'text-field__bottom' },
					el( 'span', { className: 'text-field__supporting', id: describedBy }, props.supporting )
				)
				: null
		);
	}

	/**
	 * One line of text.
	 *
	 * `type` is passed through, so an email field is an email field to the browser and to a phone
	 * keyboard. What is not passed through is validation: this draws what it is told, and whether a
	 * value is acceptable is a question the draft route answers for everybody at once.
	 */
	function TextField( props ) {
		var id = useFieldId( props.id );
		var node = useRef( null );
		var current = useRef( null );
		current.current = { value: props.value, onChange: props.onChange };
		useEffect( function () {
			return watchField( node, current );
		}, [] );
		return el( Shell, {
			id: id,
			label: props.label,
			hideLabel: props.hideLabel,
			error: props.error,
			supporting: props.supporting,
			className: props.className,
			trailing: props.trailing,
			control: function ( describedBy ) {
				var extra = props.inputProps || {};
				/*
				 * Merged rather than overwritten. A caller adds its own `onBlur` -- a phone number
				 * settles into `tel:` when the box is left -- and a merge that let one win would be
				 * the field quietly losing either that or the watch for what a browser filled in.
				 */
				return el( 'input', Object.assign( {}, extra, {
					id: id,
					ref: node,
					className: 'text-field__input',
					type: props.type || 'text',
					// The floating label is CSS, and this is what it reads.
					placeholder: ' ',
					value: undefined === props.value || null === props.value ? '' : props.value,
					disabled: props.disabled,
					readOnly: props.readOnly,
					'aria-describedby': describedBy,
					'aria-invalid': props.error ? 'true' : undefined,
					onFocus: function ( event ) {
						lookForAWhile();
						if ( props.onFocus ) {
							props.onFocus( event );
						}
						if ( extra.onFocus ) {
							extra.onFocus( event );
						}
					},
					onBlur: function ( event ) {
						sweep();
						if ( extra.onBlur ) {
							extra.onBlur( event );
						}
					},
					onAnimationStart: autofilled(),
					onChange: function ( event ) {
						props.onChange( event.target.value );
					}
				} ) );
			}
		} );
	}

	/** The same field, taller, for things that are paragraphs or documents. */
	function Textarea( props ) {
		var id = useFieldId( props.id );
		var node = useRef( null );
		var current = useRef( null );
		current.current = { value: props.value, onChange: props.onChange };
		useEffect( function () {
			return watchField( node, current );
		}, [] );
		return el( Shell, {
			id: id,
			label: props.label,
			hideLabel: props.hideLabel,
			error: props.error,
			supporting: props.supporting,
			className: props.className,
			control: function ( describedBy ) {
				return el( 'textarea', Object.assign( {
					id: id,
					ref: node,
					className: 'text-field__input',
					rows: props.rows || 4,
					placeholder: ' ',
					spellCheck: false === props.spellCheck ? false : undefined,
					value: undefined === props.value || null === props.value ? '' : props.value,
					disabled: props.disabled,
					readOnly: props.readOnly,
					'aria-describedby': describedBy,
					'aria-invalid': props.error ? 'true' : undefined,
					onFocus: lookForAWhile,
					onBlur: sweep,
					onAnimationStart: autofilled(),
					onChange: function ( event ) {
						props.onChange( event.target.value );
					}
				}, props.inputProps || {} ) );
			}
		} );
	}

	/**
	 * A field you type into that offers what is already there.
	 *
	 * Not a `Select`, and deliberately not built out of one. The paths a localization may patch run to
	 * `addresses/home/components/2/value` and there are dozens of them on a full Card, so a fixed
	 * dropdown is a list somebody scrolls rather than a question they answer. Typing narrows it.
	 *
	 * Whether it accepts something that is not on the list depends on what the list is. The paths a
	 * localization may patch are a closed set -- inventing one would be offering somebody a patch the
	 * server is about to refuse -- so that picker takes only what it offers. A language tag is not:
	 * BCP 47 has more of them than anyone would put in a menu, and refusing an unusual one would be
	 * this editor deciding which languages exist. `allowFree` is which of the two a caller wants.
	 */
	function Combobox( props ) {
		var id = useFieldId( props.id );
		/*
		 * What has been typed, or `null` for nothing yet. The distinction is the whole of this: a
		 * field somebody has clicked into still holds the answer they chose last time, and a list
		 * filtered by that answer offers them the one thing they already have. Typing is what narrows
		 * it; opening it is not.
		 */
		var [ query, setQuery ] = wp.element.useState( null );
		var [ open, setOpen ] = wp.element.useState( false );
		var [ active, setActive ] = wp.element.useState( 0 );
		// Closing waits, in case the click that blurred the field was a click on one of the options.
		var closing = useRef( null );
		var typing = null !== query;

		function stopClosing() {
			if ( closing.current ) {
				window.clearTimeout( closing.current );
				closing.current = null;
			}
		}
		/*
		 * An option is a value, and sometimes a name for it. `ko-KR` is the answer; `Korean (Korea)`
		 * is how somebody finds it -- and they will type either, so both are searched and the name is
		 * what the list shows.
		 */
		var options = ( props.options || [] ).map( function ( option ) {
			return 'string' === typeof option ? { value: option, label: option } : option;
		} ).filter( function ( option ) {
			var against = ( option.value + ' ' + ( option.label || '' ) ).toLowerCase();
			return ! typing || ! query || -1 !== against.indexOf( query.toLowerCase() );
		} );
		var listId = id + '-list';

		function choose( option ) {
			if ( ! option ) {
				return;
			}
			props.onChange( option.value );
			setQuery( null );
			setOpen( false );
		}

		/*
		 * What the field shows when nobody is typing in it: what the chosen option is called, rather
		 * than the value stored behind it. `KR` is the answer a card wants; `대한민국 (+82)` is the
		 * answer a person recognises, and a picker showing the first was showing its own workings.
		 * A value nothing on the list matches -- which `allowFree` exists for -- is shown as it is.
		 */
		var chosen = ( props.options || [] ).map( function ( option ) {
			return 'string' === typeof option ? { value: option, label: option } : option;
		} ).filter( function ( option ) {
			return option.value === props.value;
		} )[ 0 ];

		return el(
			'div',
			{ className: 'ax-combobox' + ( props.className ? ' ' + props.className : '' ) },
			el( TextField, {
				hideLabel: props.hideLabel,
				id: id,
				label: props.label,
				// What is in the field is what the card says, until somebody types something else.
				value: typing ? query : ( ( chosen && chosen.label ) || props.value || '' ),
				supporting: props.supporting,
				error: props.error,
				onChange: function ( value ) {
					setQuery( value );
					setActive( 0 );
					setOpen( true );
					if ( props.allowFree ) {
						// What was typed is already an answer; the list is only a shortcut to a common one.
						props.onChange( value );
					}
				},
				onFocus: function () {
					stopClosing();
					// Opened, not narrowed: everything is on offer until a key is pressed.
					setActive( 0 );
					setOpen( true );
				},
				inputProps: Object.assign( {
					role: 'combobox',
					'aria-expanded': open ? 'true' : 'false',
					'aria-controls': listId,
					'aria-autocomplete': 'list',
					onKeyDown: function ( event ) {
						if ( 'ArrowDown' === event.key || 'ArrowUp' === event.key ) {
							event.preventDefault();
							setOpen( true );
							setActive( function ( at ) {
								var next = 'ArrowDown' === event.key ? at + 1 : at - 1;
								return Math.max( 0, Math.min( options.length - 1, next ) );
							} );
							return;
						}
						if ( 'Enter' === event.key && open ) {
							event.preventDefault();
							choose( options[ active ] );
							return;
						}
						if ( 'Escape' === event.key ) {
							setOpen( false );
						}
					},
					onBlur: function () {
						// Late enough for a click on an option to land first, and cancelled if the
						// field is focused again before then -- otherwise coming back closes the list
						// that coming back just opened.
						stopClosing();
						closing.current = window.setTimeout( function () {
							closing.current = null;
							setOpen( false );
							setQuery( null );
						}, 150 );
					},
					disabled: props.disabled
				}, props.inputProps || {} )
			} ),
			open
				? el(
					'ul',
					{ className: 'ax-combobox__list', id: listId, role: 'listbox' },
					options.length
						? options.slice( 0, 40 ).map( function ( option, index ) {
							return el(
								'li',
								{
									key: option.value,
									role: 'option',
									'aria-selected': index === active ? 'true' : 'false',
									className: 'ax-combobox__option' + ( index === active ? ' is-active' : '' ),
									onMouseDown: function ( event ) {
										// Before blur, so the choice survives the field losing focus.
										event.preventDefault();
										choose( option );
									}
								},
								option.label || option.value
							);
						} )
						: el( 'li', { className: 'ax-combobox__empty' }, props.emptyLabel || __( 'Nothing matches', 'axismundi-contacts' ) )
				)
				: null
		);
	}

	/**
	 * A button with a picture on it.
	 *
	 * The picture comes from the icon registry, handed over as markup rather than drawn here, so that
	 * two screens asking for `delete` cannot end up with two different bins. The label is required and
	 * is never shown: a control whose only content is a picture has no name at all without one.
	 */
	function IconButton( props ) {
		var icon = ( window.axismundiContactsIcons || {} )[ props.icon ] || '';
		return el( 'button', {
			type: 'button',
			className: 'ax-icon-button'
				+ ( props.variant ? ' ax-icon-button--' + props.variant : '' )
				+ ( props.className ? ' ' + props.className : '' ),
			'aria-label': props.label,
			title: props.label,
			disabled: props.disabled,
			onClick: props.onClick,
			// The registry's own markup, which is this plugin's asset rather than anything a person typed.
			dangerouslySetInnerHTML: { __html: icon }
		} );
	}

	window.axismundiContactsFields = {
		TextField: TextField,
		Textarea: Textarea,
		Combobox: Combobox,
		IconButton: IconButton
	};
}( window.wp ) );
