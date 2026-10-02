/**
 * Visual Material elevation layer for a positioned surface.
 *
 * Elevation is a closed semantic relationship scale, not a z-index or a shadow
 * preset. The parent surface must establish its own positioning and shape.
 *
 * @param {Object} props Component props.
 * @param {number} [props.level=0] Material elevation level, clamped to `0..5`.
 * @param {string} [props.className] Additional component class name.
 * @return {import('@wordpress/element').ReactNode} Non-semantic visual layer.
 */
export function Elevation( { level = 0, className, style: customStyle, ...props } ) {
	const numericLevel = Number( level );
	const restingLevel = Number.isFinite( numericLevel ) ? Math.min( 5, Math.max( 0, numericLevel ) ) : 0;

	return (
		<span
			{ ...props }
			aria-hidden="true"
			className={ [ 'ax-elevation', className ].filter( Boolean ).join( ' ' ) }
			style={ { ...customStyle, '--ax-elevation-resting-level': restingLevel } }
		/>
	);
}
