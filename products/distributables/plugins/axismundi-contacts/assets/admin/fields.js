/**
 * The field primitives the contacts editor is built from.
 *
 * Three of them, and only three: this screen needs a text field, a multi-line one, and a button with
 * a picture on it. A `Select` is deliberately absent -- the path picker that will want one has to
 * decide first whether it is a menu or something you can type into, and a fake select bolted onto a
 * text field would answer that question by accident.
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
				el( 'label', { className: 'text-field__label', htmlFor: id }, props.label ),
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
			error: props.error,
			supporting: props.supporting,
			className: props.className,
			trailing: props.trailing,
			control: function ( describedBy ) {
				return el( 'input', {
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
					onChange: function ( event ) {
						props.onChange( event.target.value );
					}
				} );
			}
		} );
	}

	/** The same field, taller, for things that are paragraphs or documents. */
	function Textarea( props ) {
		var id = useFieldId( props.id );
		return el( Shell, {
			id: id,
			label: props.label,
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
			className: 'ax-icon-button' + ( props.variant ? ' ax-icon-button--' + props.variant : '' ),
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
		IconButton: IconButton
	};
}( window.wp ) );
