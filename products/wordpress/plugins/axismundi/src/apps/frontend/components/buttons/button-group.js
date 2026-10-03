/**
 * Material 3 Standard button group.
 *
 * An invisible container. M3: "Button groups are invisible containers that add
 * padding between buttons and modify button shape." It paints nothing, owns no
 * colour -- `products/styleguide/_data/button_group.yml` records why there is no
 * colour axis at all -- and publishes no shape tokens of its own, so shape stays
 * the buttons'.
 *
 * What it does own is the space between buttons, which is per size because the
 * gap exists to keep a 48dp target reachable rather than to look even.
 *
 * `size` therefore picks the gap; it is not handed to the children. The children
 * are real Button and IconButton instances that own their own size, colour,
 * width and state, which is also what separates this from a connected group,
 * whose children are parts rather than components.
 *
 * `distribution` carries the adjacent interaction, and it is a setting rather
 * than the default because two published rules collide. M3 says a standard group
 * hugs the width of the buttons inside, and it also says a selected button widens
 * by 15% while its neighbours compress to pay for it. A neighbour can only
 * compress if there is room to take from it, so a group sized to its content has
 * nothing to redistribute -- measured in the block adapter at 250px, where the
 * 15% changed nothing at all, against 420px where 135/135/135 became 144/130/130.
 *
 * So `hug` is the container rule and `fill` is the interaction, and an author
 * choosing `fill` is saying this group spans its surface rather than sitting in
 * a row of actions.
 *
 * @param {'hug'|'fill'} [props.distribution='hug'] Whether the group sizes to its
 * buttons or spans its surface and lets them respond to each other.
 *
 * @param {Object} props Component props.
 * @param {import('@wordpress/element').ReactNode} props.children Button and IconButton instances.
 * @param {'xsmall'|'small'|'medium'|'large'|'xlarge'} [props.size='small'] Size of the buttons inside, which decides the gap.
 * @param {string} [props.label] Accessible name, when the group is itself a landmark worth naming.
 * @param {string} [props.className] Additional component class name.
 * @return {import('@wordpress/element').ReactNode} Button group container.
 */

import { Button } from './button';
import { Children, isValidElement } from '@wordpress/element';
import { IconButton } from './icon-button';
import warning from '@wordpress/warning';

const SIZES = [ 'xsmall', 'small', 'medium', 'large', 'xlarge' ];

/*
 * "Avoid using standard icon buttons or text buttons, as they have no container
 * treatment." Both are invisible at rest, and a group needs each member to read
 * as one segment of a single control, which needs a box to read from.
 *
 * Keyed on the component itself rather than on its name: a minified build
 * renames the function, so `child.type.name` would stop matching and the warning
 * would simply never fire again. Verified by probe: the match is true for a text
 * Button and false for every other child on the page.
 *
 * The warning itself does not survive `npm run build`. `@wordpress/warning`
 * ships a babel plugin that strips its calls from production builds, and
 * `wp-scripts build` is one, so this message is absent from the bundle -- it
 * reaches a console only in a development build. The check above still runs;
 * only the reporting is compiled out.
 */
const CONTAINERLESS = [
	{ component: Button, variant: 'text', label: 'Button' },
	{ component: IconButton, variant: 'standard', label: 'IconButton' },
];

export function ButtonGroup( {
	children,
	size = 'small',
	distribution = 'hug',
	label,
	className,
	...props
} ) {
	const groupSize = SIZES.includes( size ) ? size : 'small';
	const groupDistribution = 'fill' === distribution ? 'fill' : 'hug';

	Children.toArray( children ).forEach( ( child ) => {
		if ( ! isValidElement( child ) ) {
			return;
		}

		const match = CONTAINERLESS.find(
			( entry ) => child.type === entry.component && child.props?.variant === entry.variant
		);

		if ( match ) {
			warning(
				`ButtonGroup: a ${ match.variant } ${ match.label } has no container at rest, and M3 says to avoid it in a group -- a segment with no box has nowhere to show its edge or its selection.`
			);
		}
	} );

	return (
		<div
			{ ...props }
			aria-label={ label }
			className={ [ 'ax-button-group', className ].filter( Boolean ).join( ' ' ) }
			data-distribution={ groupDistribution }
			data-size={ groupSize }
			role={ label ? 'group' : undefined }
		>
			{ children }
		</div>
	);
}
