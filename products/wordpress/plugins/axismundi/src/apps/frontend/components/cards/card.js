/**
 * Material 3 Card.
 *
 * A container, and in M3 that is the whole of it: "The card container is the
 * only required element of a card. All other elements are optional." So there is
 * no size axis, no width axis and no label type here -- a card's dimensions are
 * what its contents occupy. Published values live in
 * `products/styleguide/_data/card.yml`, checked by
 * `tools/validators/validate_styleguide_card.py`.
 *
 * NON-ACTIONABLE. M3's accessibility page states the rule as a prohibition: "A
 * card can be a non-actionable container that holds actions like buttons and
 * links, or it can be directly actionable without any buttons or links. This is
 * to avoid stacking actionable elements. An action shouldn't be placed on an
 * actionable surface."
 *
 * This is the non-actionable kind, and therefore has no role, no tab stop, no
 * hover state and no ripple. Its interactive children keep their own. Social's
 * object card will hold reply, favourite and overflow actions, so it is this
 * kind; a card that is itself one target is a separate component with the
 * exclusion above to enforce, and it is not built on speculation.
 *
 * `variant` has no default in M3 -- "Each provides the same legibility and
 * functionality, so the variant you use depends on style alone." The default
 * here is ours. Elevated, because the guidelines place it between the other two
 * ("more separation from the background than filled, but less than outlined"),
 * which makes it the least opinionated thing to land on.
 *
 * `as` is allowed here, unlike on Button, and the reason the prohibition does
 * not transfer is that it was about interaction. A general `as` on a control
 * lets a caller put interactive styling on an element with no interaction
 * semantics; on a container the risk runs the other way, since forcing a `div`
 * would make a semantic feed or list impossible to build. The allowlist is
 * therefore non-interactive containers only -- an interactive element would
 * contradict the paragraph above, not just this component.
 *
 * @param {Object} props Component props.
 * @param {import('@wordpress/element').ReactNode} props.children Card contents.
 * @param {'elevated'|'filled'|'outlined'} [props.variant='elevated'] M3 colour style.
 * @param {'div'|'section'|'article'|'li'|'aside'} [props.as='div'] Host element.
 * @param {string} [props.className] Additional component class name.
 * @return {import('@wordpress/element').ReactNode} Card container.
 */

import { Elevation } from '../material/elevation';
import warning from '@wordpress/warning';

const VARIANTS = [ 'elevated', 'filled', 'outlined' ];

/* Non-interactive containers. Anything focusable belongs to a different contract. */
const HOSTS = [ 'div', 'section', 'article', 'li', 'aside' ];

/* Only Elevated rests with a shadow; the other two rest flat. */
const RESTING_ELEVATION = { elevated: 1, filled: 0, outlined: 0 };

export function Card( { children, variant = 'elevated', as = 'div', className, ...props } ) {
	const colorStyle = VARIANTS.includes( variant ) ? variant : 'elevated';
	const Host = HOSTS.includes( as ) ? as : 'div';

	/*
	 * A click handler on a container with no role and no tab stop is a control
	 * nobody can reach by keyboard or hear announced. The warning names the
	 * alternative rather than only refusing, because the usual intent -- making
	 * the card open something -- is served by linking its headline or media.
	 */
	if ( props.onClick || undefined !== props.href ) {
		warning(
			'Card: this is the non-actionable card, which has no role and no tab stop, so a click handler on it is unreachable. Link the headline or the media instead, or wait for the actionable card, which carries M3\'s rule that it may then contain no buttons or links of its own.'
		);
	}

	const restingLevel = RESTING_ELEVATION[ colorStyle ];

	return (
		<Host
			{ ...props }
			className={ [ 'ax-card', className ].filter( Boolean ).join( ' ' ) }
			data-variant={ colorStyle }
		>
			{ 0 < restingLevel && <Elevation level={ restingLevel } /> }
			{ children }
		</Host>
	);
}
