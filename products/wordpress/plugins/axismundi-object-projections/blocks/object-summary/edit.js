/**
 * axismundi/object-summary editor registration (no build step).
 *
 * The Object sibling of Core's Post Excerpt. The controls intentionally retain
 * Core's names and defaults: they control presentation of an authored AS2
 * summary, not generation of a new excerpt from Object content.
 */
( function ( blocks, blockEditor, components, element, i18n ) {
	var el = element.createElement;
	var __ = i18n.__;
	var InspectorControls = blockEditor.InspectorControls;
	var RichText = blockEditor.RichText;
	var PanelBody = components.PanelBody;
	var RangeControl = components.RangeControl;
	var ToggleControl = components.ToggleControl;

	blocks.registerBlockType( 'axismundi/object-summary', {
		edit: function ( props ) {
			var attributes = props.attributes;
			var moreText = attributes.moreText || '';
			var showMoreOnNewLine = attributes.showMoreOnNewLine !== false;
			var excerptLength = Math.max( 1, attributes.excerptLength || 55 );
			var preview = __( 'This is the Object Summary block. For a local Article it displays the content before the More block, or the authored WordPress excerpt when no More block exists.', 'axismundi-object-projections' );
			var more = el( RichText, {
				identifier: 'moreText',
				className: 'wp-block-post-excerpt__more-link',
				tagName: 'a',
				'aria-label': __( '“Read more” link text', 'axismundi-object-projections' ),
				placeholder: __( 'Add "read more" link text', 'axismundi-object-projections' ),
				value: moreText,
				onChange: function ( value ) { props.setAttributes( { moreText: value } ); },
				withoutInteractiveFormatting: true
			} );
			var previewChildren = [
				el(
					'p',
					{ className: 'wp-block-post-excerpt__excerpt' + ( ! showMoreOnNewLine ? ' is-inline' : '' ) },
					preview
				)
			];
			if ( showMoreOnNewLine ) {
				previewChildren.push( el( 'p', { className: 'wp-block-post-excerpt__more-text' }, more ) );
			} else {
				previewChildren.push( ' ', more );
			}

			return [
				el(
					InspectorControls,
					{ key: 'inspector' },
					el(
						PanelBody,
						{ title: __( 'Excerpt settings', 'axismundi-object-projections' ), initialOpen: true },
						el( RangeControl, {
							label: __( 'Max number of words', 'axismundi-object-projections' ),
							value: excerptLength,
							onChange: function ( value ) { props.setAttributes( { excerptLength: value || 1 } ); },
							min: 1,
							max: 200
						} ),
						el( ToggleControl, {
							label: __( 'Show link on new line', 'axismundi-object-projections' ),
							checked: showMoreOnNewLine,
							onChange: function ( value ) { props.setAttributes( { showMoreOnNewLine: value } ); }
						} )
					)
				),
				el(
					'div',
					blockEditor.useBlockProps( { className: 'wp-block-post-excerpt axismundi-object__summary' } ),
					previewChildren
				)
			];
		},
		save: function () { return null; }
	} );
} )( window.wp.blocks, window.wp.blockEditor, window.wp.components, window.wp.element, window.wp.i18n );
