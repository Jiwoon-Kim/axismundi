import warning from '@wordpress/warning';

const MAXIMUM_LABEL_LENGTH = 4;

const formatLabel = ( label ) => {
	const value = String( label ).trim();

	if ( MAXIMUM_LABEL_LENGTH >= value.length ) {
		return value;
	}

	return `${ value.slice( 0, MAXIMUM_LABEL_LENGTH - 1 ) }+`;
};

/**
 * Presentational notification marker. Its host owns the accessible description.
 *
 * @param {Object} props Component props.
 * @param {string|number} [props.label] Count or status label; omit for small.
 * @param {string} [props.className] Additional component class name.
 * @return {import('@wordpress/element').ReactNode} Badge markup.
 */
export function Badge( { label, className = '', ...props } ) {
	const hasLabel = undefined !== label && null !== label && '' !== String( label ).trim();
	const content = hasLabel ? formatLabel( label ) : null;

	if ( hasLabel && MAXIMUM_LABEL_LENGTH < String( label ).trim().length ) {
		warning( `Badge: labels are limited to ${ MAXIMUM_LABEL_LENGTH } characters; rendering a truncated label.` );
	}

	return (
		<span
			{ ...props }
			aria-hidden="true"
			data-maximum-label={ MAXIMUM_LABEL_LENGTH === content?.length ? 'true' : undefined }
			className={ [ 'ax-badge', hasLabel ? 'ax-badge--large' : 'ax-badge--small', className ]
				.filter( Boolean )
				.join( ' ' ) }
		>
			{ content }
		</span>
	);
}
