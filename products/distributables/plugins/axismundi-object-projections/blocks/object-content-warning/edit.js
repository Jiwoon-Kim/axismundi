/**
 * axismundi/object-content-warning editor registration (no build step).
 *
 * The canvas always shows the blocks unfolded — an author has to see and edit what the
 * warning covers. The disclosure itself is a reader-time decision made from the Object's
 * own authored warning, so it belongs to the front end, not the editor.
 */
( function ( blocks, blockEditor, element, i18n ) {
	var el = element.createElement;
	var __ = i18n.__;
	var useBlockProps = blockEditor.useBlockProps;
	var useInnerBlocksProps = blockEditor.useInnerBlocksProps;

	var TEMPLATE = [
		[ 'axismundi/object-content', {} ],
		[ 'axismundi/quote-context', {} ],
		[ 'axismundi/question', {} ],
		[ 'axismundi/object-attachments', {} ]
	];

	blocks.registerBlockType( 'axismundi/object-content-warning', {
		edit: function () {
			var blockProps = useBlockProps( { className: 'axismundi-object__content-warning is-editor-preview' } );
			var innerProps = useInnerBlocksProps( blockProps, { template: TEMPLATE } );

			return el(
				'div',
				{ className: 'axismundi-object__content-warning-editor' },
				el(
					'p',
					{ className: 'axismundi-object__content-warning-note' },
					__(
						'Everything inside folds behind one disclosure when the Object carries an authored content warning. Marking media sensitive without warning text does not fold it.',
						'axismundi-object-projections'
					)
				),
				el( 'div', innerProps )
			);
		},
		save: function () {
			return el( blockEditor.InnerBlocks.Content );
		}
	} );
}( window.wp.blocks, window.wp.blockEditor, window.wp.element, window.wp.i18n ) );
