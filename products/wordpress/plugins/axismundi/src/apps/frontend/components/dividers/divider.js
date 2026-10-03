/**
 * Material 3 Divider.
 *
 * "A divider is a simple line", and the published token surface is two rows:
 * `md.comp.divider.color` references `md.sys.color.outline-variant` and
 * `md.comp.divider.thickness` is 1dp. No states, because a divider is not
 * interactive. Published values live in `products/styleguide/_data/divider.yml`,
 * checked by `tools/validators/validate_styleguide_divider.py`.
 *
 * Three variants, although the prose says two. M3's text offers "Full width" and
 * "Inset" while its measurements table publishes inset and middle-inset with
 * different figures, 16dp start / 0dp end against 16dp on both sides. The theme
 * had already shipped both as separate `core/separator` style variations.
 *
 * IT OWNS NO SURROUNDING SPACE. The divider measurements table also carries a
 * 4dp gap to supporting text and 8dp right and bottom margins, and those are the
 * layout's, not the line's. A divider that shipped its own bottom margin would
 * put 8dp into every card and list using one, with no way for the caller to take
 * it back. The consumer spaces its own dividers.
 *
 * DECORATIVE BY DEFAULT, which is ours rather than published. M3 calls dividers
 * decorative -- a statement about colour contrast, since they "have no contrast
 * minimums" -- and a decorative line should not be announced. But ARIA has a real
 * `separator` role that an `<hr>` carries natively, and a line between two
 * unrelated sections genuinely is one. So the caller decides: `decorative` (the
 * default) renders an `aria-hidden` span, and `decorative={ false }` renders an
 * `<hr>` and lets the platform announce it.
 *
 * It lives with the UI components, not in `components/material/`, even though it
 * behaves like a primitive and Card, List and Menu will all consume it. The
 * conventions document reserves that directory for primitives corresponding to
 * an M3 Styles axis -- Icon and Elevation -- and M3 publishes Divider under
 * Components. Being consumed by other components is not the test.
 *
 * @param {Object} props Component props.
 * @param {'full'|'inset'|'middle-inset'} [props.variant='full'] M3 inset geometry.
 * @param {'horizontal'|'vertical'} [props.orientation='horizontal'] Line direction.
 * @param {boolean} [props.decorative=true] False renders an announced `<hr>`.
 * @param {string} [props.className] Additional component class name.
 * @return {import('@wordpress/element').ReactNode} A one-pixel line.
 */

const VARIANTS = [ 'full', 'inset', 'middle-inset' ];
const ORIENTATIONS = [ 'horizontal', 'vertical' ];

export function Divider( {
	variant = 'full',
	orientation = 'horizontal',
	decorative = true,
	className,
	...props
} ) {
	const inset = VARIANTS.includes( variant ) ? variant : 'full';
	const direction = ORIENTATIONS.includes( orientation ) ? orientation : 'horizontal';

	/*
	 * An `<hr>` is a thematic break and brings `role="separator"` with it, so the
	 * announced form uses the element the platform already defines rather than
	 * putting the role on a span.
	 */
	const Host = decorative ? 'span' : 'hr';

	return (
		<Host
			{ ...props }
			aria-hidden={ decorative ? 'true' : undefined }
			className={ [ 'ax-divider', className ].filter( Boolean ).join( ' ' ) }
			data-orientation={ direction }
			data-variant={ inset }
		/>
	);
}
