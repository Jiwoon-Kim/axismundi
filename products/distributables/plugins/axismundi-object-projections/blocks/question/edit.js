/**
 * Editor preview for axismundi/question.
 *
 * The real poll is supplied by the current Object at render time. The preview
 * therefore demonstrates the three semantic surfaces without pretending that
 * an editor setting changes the federated Question itself.
 */
( function ( blocks, blockEditor, components, element, i18n ) {
	'use strict';
	var el = element.createElement;
	var __ = i18n.__;
	var InspectorControls = blockEditor.InspectorControls;
	var PanelBody = components.PanelBody;
	var SelectControl = components.SelectControl;

	function CheckboxChoice( label, checked ) {
		return el( 'li', { key: label },
			el( 'label', { className: 'ax-checkbox' },
				el( 'input', { className: 'ax-checkbox__input', type: 'checkbox', checked: checked, readOnly: true } ),
				el( 'span', { className: 'ax-checkbox__visual', 'aria-hidden': true },
					el( 'svg', { className: 'ax-checkbox__check', viewBox: '0 0 18 18' },
						el( 'path', { d: 'M4 9.5 7.5 13 14 5.5' } )
					)
				),
				el( 'span', { className: 'ax-checkbox__label' }, label )
			)
		);
	}

	function RadioChoice( label, checked ) {
		return el( 'li', { key: label },
			el( 'label', { className: 'ax-radio' },
				el( 'input', { className: 'ax-radio__input', type: 'radio', checked: checked, readOnly: true } ),
				el( 'span', { className: 'ax-radio__visual', 'aria-hidden': true } ),
				el( 'span', { className: 'ax-radio__label' }, label )
			)
		);
	}

	function Results( selected ) {
		var values = [ [ __( 'Walk', 'axismundi-object-projections' ), 72 ], [ __( 'Ride', 'axismundi-object-projections' ), 28 ] ];
		return el( element.Fragment, {},
			el( 'ul', { className: 'wp-block-list is-style-list-segmented axismundi-question__options' },
				values.map( function ( item ) {
					var isSelected = selected && 'Ride' === item[0];
					return el( 'li', { key: item[0], className: 'axismundi-question__option axismundi-question__result' + ( isSelected ? ' is-selected' : '' ) },
						el( 'div', { className: 'axismundi-question__option-row' },
							isSelected ? el( 'span', { className: 'material-symbols-outlined axismundi-question__selected-icon', 'aria-hidden': true }, 'check' ) : null,
							el( 'span', { className: 'axismundi-question__option-name' }, item[0] ),
							el( 'span', { className: 'axismundi-question__option-percent' }, item[1] )
						),
						el( 'div', { className: 'axismundi-question__result-meter', role: 'progressbar', 'aria-valuemin': 0, 'aria-valuemax': 100, 'aria-valuenow': item[1] },
							el( 'span', { className: 'axismundi-question__result-meter-value', style: { '--_value': item[1] + '%' } } )
						)
					);
				} )
			),
			el( 'p', { className: 'axismundi-question__meta' }, selected ? __( 'You voted | 48 votes', 'axismundi-object-projections' ) : __( '48 votes | Final results', 'axismundi-object-projections' ) )
		);
	}

	function Choices( type ) {
		return el( 'form', { className: 'axismundi-question__vote' },
			el( 'fieldset', {},
				el( 'legend', {}, __( 'Cast your vote', 'axismundi-object-projections' ) ),
				el( 'ul', { className: 'wp-block-list is-style-list-segmented axismundi-question__choices' },
					'anyOf' === type
						? CheckboxChoice( __( 'Walk', 'axismundi-object-projections' ), false )
						: RadioChoice( __( 'Walk', 'axismundi-object-projections' ), false ),
					'anyOf' === type
						? CheckboxChoice( __( 'Ride', 'axismundi-object-projections' ), true )
						: RadioChoice( __( 'Ride', 'axismundi-object-projections' ), true )
				)
			),
			el( 'button', { className: 'wp-element-button', type: 'button', disabled: true }, __( 'Vote', 'axismundi-object-projections' ) )
		);
	}

	blocks.registerBlockType( 'axismundi/question', {
		edit: function ( props ) {
			var variant = props.attributes.previewVariant || 'oneOf';
			var preview = 'results' === variant ? Results( false ) : ( 'voted' === variant ? Results( true ) : Choices( 'anyOf' === variant ? 'anyOf' : 'oneOf' ) );
			return el( element.Fragment, {},
				el( InspectorControls, {},
					el( PanelBody, { title: __( 'Preview', 'axismundi-object-projections' ) },
						el( SelectControl, {
							label: __( 'Question preview', 'axismundi-object-projections' ),
							value: variant,
							options: [
								{ label: __( 'Choose one', 'axismundi-object-projections' ), value: 'oneOf' },
								{ label: __( 'Choose any', 'axismundi-object-projections' ), value: 'anyOf' },
								{ label: __( 'Voted', 'axismundi-object-projections' ), value: 'voted' },
								{ label: __( 'Results', 'axismundi-object-projections' ), value: 'results' }
							],
							onChange: function ( value ) { props.setAttributes( { previewVariant: value } ); }
						} )
					)
				),
				el( 'div', blockEditor.useBlockProps( { className: 'axismundi-question axismundi-question--' + ( 'results' === variant ? 'closed' : 'open' ) + ' is-editor-preview' } ), preview )
			);
		},
		save: function () { return null; }
	} );
} )( window.wp.blocks, window.wp.blockEditor, window.wp.components, window.wp.element, window.wp.i18n );
