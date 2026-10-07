/**
 * Material 3 Carousel item.
 *
 * The outer group is the APG slide boundary and is never focusable. The inner
 * native link or button is the one action M3 assigns to the item. Keeping those
 * as separate nodes preserves both contracts without putting a widget role on
 * a link or button. The component does not accept an arbitrary host: `href`
 * means navigation, otherwise the action is a button.
 *
 * `position` and `setSize` are supplied by Carousel, and `formatPosition` turns
 * them into the name M3 requires on every item. An earlier version wrote them
 * as `1/5` to avoid hard-coding an English "of"; that trades a known English
 * word for unpredictable output, because a solidus is spoken as "slash", as
 * "of", or not at all depending on the screen reader and the language. The
 * default therefore follows APG's own carousel example and says "1 of 5", and
 * the formatter is a prop for the same reason `roleDescription` is one -- those
 * two already default to English words and are injectable, and this was the
 * only string that was not. This app has no i18n wiring yet; when it does, the
 * caller passes a formatter built with `sprintf` and `__`.
 *
 * The visible content is the caller's node and may adapt to the eventual item
 * geometry.
 *
 * @param {Object}                                 props                           Component props.
 * @param {string}                                 props.label                     Accessible item name.
 * @param {import('@wordpress/element').ReactNode} props.children                  Item contents.
 * @param {string}                                 [props.href]                    Renders a link instead of a button.
 * @param {Function}                               [props.onClick]                 Activates a button item.
 * @param {boolean}                                [props.disabled=false]          Disables the item action.
 * @param {boolean}                                [props.outlined=false]          Uses the published optional outline.
 * @param {number}                                 [props.position]                One-based position supplied by Carousel.
 * @param {number}                                 [props.setSize]                 Total item count supplied by Carousel.
 * @param {Function}                               [props.onNavigate]              Keyboard handler supplied by Carousel.
 * @param {string}                                 [props.roleDescription='slide'] Localized role description.
 * @param {Function}                               [props.formatPosition]          Builds the position name from (position, setSize).
 * @param {string}                                 [props.className]               Additional wrapper class name.
 * @return {import('@wordpress/element').ReactNode} Carousel slide and action.
 */

import { forwardRef } from '@wordpress/element';

/* APG's carousel example names each slide "1 of 6"; this is that wording. */
export function defaultPositionLabel( position, setSize ) {
	return `${ position } of ${ setSize }`;
}
import warning from '@wordpress/warning';

import { Elevation } from '../material/elevation';

export const CarouselItem = forwardRef( function CarouselItemComponent(
	{
		label,
		children,
		href,
		onClick,
		disabled = false,
		outlined = false,
		position,
		setSize,
		onNavigate,
		roleDescription = 'slide',
		formatPosition = defaultPositionLabel,
		className,
		type = 'button',
		...props
	},
	ref
) {
	if ( ! label ) {
		warning(
			'CarouselItem: `label` is required so the item can be identified.'
		);
	}
	if ( ! href && ! onClick ) {
		warning(
			'CarouselItem: provide `href` or `onClick`; M3 defines every carousel item as directly actionable.'
		);
	}
	if ( ! position || ! setSize ) {
		warning(
			'CarouselItem: render items as direct children of Carousel so current and total can be announced.'
		);
	}

	const isLink = undefined !== href;
	const Host = isLink ? 'a' : 'button';
	const actionProps = isLink
		? {
				href: disabled ? undefined : href,
				'aria-disabled': disabled || undefined,
			}
		: { disabled, type };
	const positionLabel =
		position && setSize ? formatPosition( position, setSize ) : undefined;

	return (
		<div
			aria-label={ positionLabel }
			aria-roledescription={ roleDescription }
			className={ [ 'ax-carousel-item', className ]
				.filter( Boolean )
				.join( ' ' ) }
			data-outlined={ outlined ? '' : undefined }
			role="group"
		>
			<Host
				{ ...props }
				{ ...actionProps }
				ref={ ref }
				aria-label={ label || undefined }
				className="ax-carousel-item__action"
				data-carousel-action=""
				onClick={ disabled ? undefined : onClick }
				onKeyDown={ onNavigate }
			>
				<Elevation level={ 0 } />
				<span className="ax-carousel-item__content">{ children }</span>
			</Host>
		</div>
	);
} );
