/**
 * Material Symbols facade for the theme-owned icon rendering foundation.
 *
 * The theme owns the font face, ligature shaping, glyph box, and axis defaults.
 * This component only offers the React and accessibility surface for Social UI.
 *
 * @param {Object} props Component props.
 * @param {string} props.name Material Symbols ligature name.
 * @param {string} [props.label] Accessible name for a meaningful standalone icon.
 * @param {number|string} [props.size] CSS size for the glyph box.
 * @param {number} [props.fill] Material Symbols FILL axis value.
 * @param {number} [props.weight] CSS font weight for the Material Symbol.
 * @param {number} [props.grade] Material Symbols GRAD axis value.
 * @param {string} [props.className] Additional component class name.
 * @return {import('@wordpress/element').ReactNode} Material Symbol glyph.
 */
export function Icon( {
	name,
	label,
	size,
	fill,
	weight,
	grade,
	className,
	style: customStyle,
	...props
} ) {
	const style = {};

	if ( undefined !== size ) {
		style[ '--md-icon-size' ] = 'number' === typeof size ? `${ size }px` : size;
	}
	if ( undefined !== fill ) {
		style[ '--md-icon-fill' ] = fill;
	}
	if ( undefined !== weight ) {
		style.fontWeight = weight;
	}
	if ( undefined !== grade ) {
		style[ '--md-icon-grad' ] = grade;
	}
	return (
		<span
			{ ...props }
			aria-hidden={ label ? undefined : true }
			aria-label={ label }
			className={ [ 'ax-icon', 'material-symbols-outlined', className ].filter( Boolean ).join( ' ' ) }
			role={ label ? 'img' : undefined }
			style={ { ...customStyle, ...style } }
		>
			{ name }
		</span>
	);
}

/**
 * Reserves the same inline glyph box as Icon without representing an icon.
 * Use this for a known loading or deferred slot, never as a missing-icon fallback.
 *
 * @param {Object} props Component props.
 * @param {number|string} [props.size] CSS size for the reserved glyph box.
 * @param {string} [props.className] Additional component class name.
 * @return {import('@wordpress/element').ReactNode} Reserved icon box.
 */
export function IconPlaceholder( { size, className, style: customStyle, ...props } ) {
	const style = {};

	if ( undefined !== size ) {
		style[ '--md-icon-size' ] = 'number' === typeof size ? `${ size }px` : size;
	}

	return (
		<span
			{ ...props }
			aria-hidden="true"
			className={ [ 'ax-icon-placeholder', 'material-symbols-outlined', className ].filter( Boolean ).join( ' ' ) }
			style={ { ...customStyle, ...style } }
		/>
	);
}
