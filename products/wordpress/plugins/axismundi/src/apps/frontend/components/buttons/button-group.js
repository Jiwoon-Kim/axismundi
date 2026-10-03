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
 * Which is why a child may be a different size from the group. M3 allows it --
 * "only use multiple sizes in a group for hero moments" -- and the gap stays the
 * group's, because a hero button is an exception inside a group rather than a new
 * group size. The case worth checking is the opposite one: XS and S carry the
 * wider gaps so a 32dp control still clears its neighbour by 48dp, so dropping a
 * small button into a large group gives it the large group's narrower gap.
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
 * @param {'xsmall'|'small'|'medium'|'large'|'xlarge'} [props.size='small'] The group's size, which decides the gap between buttons.
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

/*
 * A narrow icon button at XS or S is not supported in a group, and the reason is
 * arithmetic rather than taste.
 *
 * M3's gaps are the 48dp target mechanism for a group -- the specs say so, and
 * the figures show it: a container plus its gap clears 48 at every size (32+18,
 * 40+12, 56+8). Two neighbouring targets stay apart when the distance between
 * their centres reaches 48, which is `gap >= 48 - (left width + right width) / 2`.
 *
 * Enumerated against the measured widths, eight pairs fall short by 2 to 6px,
 * and every one of them contains a narrow control. Without narrow the published
 * gaps are always sufficient; at Medium and up even narrow is 48 wide and
 * nothing falls short.
 *
 * Compensating per pair is possible -- `gap` is uniform, but a margin is not --
 * and it is not worth it: the gaps inside one group would then differ from each
 * other, 12px beside 18px in the Small case, which trades an overlap nobody can
 * see for spacing everybody can, and abandons the published between-space while
 * doing it.
 */
const NARROW_IN_GROUP = [ 'xsmall', 'small' ];

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

		if (
			child.type === IconButton &&
			'narrow' === child.props?.width &&
			NARROW_IN_GROUP.includes( child.props?.size ?? 'small' )
		) {
			warning(
				`ButtonGroup: a narrow ${ child.props.size ?? 'small' } IconButton leaves the group's published gap short of the 48dp target, so two of them overlap where they are meant to stay apart. Use the default width in a group.`
			);
		}

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
