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
		return el( Shell, {
			id: id,
			label: props.label,
			hideLabel: props.hideLabel,
			error: props.error,
			supporting: props.supporting,
			className: props.className,
			trailing: props.trailing,
			control: function ( describedBy ) {
				return el( 'input', Object.assign( {
					id: id,
					className: 'text-field__input',
					type: props.type || 'text',
					// The floating label is CSS, and this is what it reads.
					placeholder: ' ',
					value: undefined === props.value || null === props.value ? '' : props.value,
					disabled: props.disabled,
					readOnly: props.readOnly,
					'aria-describedby': describedBy,
					'aria-invalid': props.error ? 'true' : undefined,
					onFocus: props.onFocus,
					onChange: function ( event ) {
						props.onChange( event.target.value );
					}
				}, props.inputProps || {} ) );
			}
		} );
	}

	/** The same field, taller, for things that are paragraphs or documents. */
	function Textarea( props ) {
		var id = useFieldId( props.id );
		return el( Shell, {
			id: id,
			label: props.label,
			hideLabel: props.hideLabel,
			error: props.error,
			supporting: props.supporting,
			className: props.className,
			control: function ( describedBy ) {
				return el( 'textarea', {
					id: id,
					className: 'text-field__input',
					rows: props.rows || 4,
					placeholder: ' ',
					spellCheck: false === props.spellCheck ? false : undefined,
					value: undefined === props.value || null === props.value ? '' : props.value,
					disabled: props.disabled,
					readOnly: props.readOnly,
					'aria-describedby': describedBy,
					'aria-invalid': props.error ? 'true' : undefined,
					onChange: function ( event ) {
						props.onChange( event.target.value );
					}
				} );
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
		var [ query, setQuery ] = wp.element.useState( '' );
		var [ open, setOpen ] = wp.element.useState( false );
		var [ active, setActive ] = wp.element.useState( 0 );
		var options = ( props.options || [] ).filter( function ( option ) {
			return ! query || -1 !== option.toLowerCase().indexOf( query.toLowerCase() );
		} );
		var listId = id + '-list';

		function choose( option ) {
			if ( ! option ) {
				return;
			}
			props.onChange( option );
			setQuery( '' );
			setOpen( false );
		}

		return el(
			'div',
			{ className: 'ax-combobox' + ( props.className ? ' ' + props.className : '' ) },
			el( TextField, {
				hideLabel: props.hideLabel,
				id: id,
				label: props.label,
				value: open ? query : ( props.value || '' ),
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
					setOpen( true );
				},
				inputProps: {
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
						// Late enough for a click on an option to land first.
						window.setTimeout( function () {
							setOpen( false );
							setQuery( '' );
						}, 150 );
					},
					disabled: props.disabled
				}
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
									key: option,
									role: 'option',
									'aria-selected': index === active ? 'true' : 'false',
									className: 'ax-combobox__option' + ( index === active ? ' is-active' : '' ),
									onMouseDown: function ( event ) {
										// Before blur, so the choice survives the field losing focus.
										event.preventDefault();
										choose( option );
									}
								},
								option
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
