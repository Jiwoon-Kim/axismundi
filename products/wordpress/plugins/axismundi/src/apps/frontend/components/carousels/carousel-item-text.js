/**
 * Optional label and supporting text building block for a CarouselItem.
 *
 * Visibility is composition-owned: callers include or omit this node instead
 * of duplicating Figma's "Show carousel text" specimen control as an API prop.
 *
 * @param {Object}                                 props                  Component props.
 * @param {import('@wordpress/element').ReactNode} props.label            Brief item label.
 * @param {import('@wordpress/element').ReactNode} [props.supportingText] Optional supporting text.
 * @param {'stacked'|'overlay'}                     [props.appearance]     Text placement.
 * @param {string}                                 [props.className]      Additional class name.
 * @return {import('@wordpress/element').ReactNode} Carousel item text.
 */

import warning from '@wordpress/warning';

export function CarouselItemText( {
	label,
	supportingText,
	appearance = 'stacked',
	className,
	...props
} ) {
	if ( ! label ) {
		warning( 'CarouselItemText: `label` is required.' );
	}
	const textAppearance = [ 'stacked', 'overlay' ].includes( appearance )
		? appearance
		: 'stacked';
	if ( textAppearance !== appearance ) {
		warning(
			`CarouselItemText: unsupported appearance "${ appearance }"; using stacked.`
		);
	}

	return (
		<span
			{ ...props }
			className={ [ 'ax-carousel-item-text', className ]
				.filter( Boolean )
				.join( ' ' ) }
			data-appearance={ textAppearance }
		>
			<strong className="ax-carousel-item-text__label">{ label }</strong>
			{ supportingText ? (
				<span className="ax-carousel-item-text__supporting">
					{ supportingText }
				</span>
			) : null }
		</span>
	);
}
