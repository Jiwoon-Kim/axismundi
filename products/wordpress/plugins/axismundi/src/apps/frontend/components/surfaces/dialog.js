/**
 * Material 3 Dialog -- the basic and full-screen rows.
 *
 * Two rows in one component, and the reason is published rather than
 * convenient: a full-screen dialog *becomes* a basic dialog from the medium
 * breakpoint (`surface.yml` `adaptive`, "becomes: dialog-basic, from: medium").
 * One instance therefore has to be both rows at once, which is the single
 * place the block's one-host argument actually applies here. The bottom and
 * side sheets are separate components because their swap is published as
 * "can be swapped ... not must" -- the caller's composition, not the
 * component's obligation. Section 8 of
 * `docs/DECISION-FRONTEND-OVERLAY-ESCAPE-AND-PUSH.md`.
 *
 * ACTIONS ARE A SLOT, AND WHERE THEY GO IS THE ROW'S BUSINESS. M3 pins a basic
 * dialog's actions to a footer and puts a full-screen dialog's into the 56dp
 * header beside the headline. The caller passes the same node either way.
 *
 * THE CLOSE AFFORDANCE IS THE ROW'S TOO. A full-screen dialog's close icon
 * button is "the only navigation in the app bar", so this renders it. A basic
 * dialog has none in its anatomy, so it does not.
 *
 * MOTION. The choreography is material-web's and the numbers are
 * `surface.yml` `motion.dialog`, which this file spends rather than invents:
 * 50px of travel from above, a container clipped to 35% of its height at the
 * start, and the headline, body and actions fading in after a hold. Which
 * easing and duration each row takes is CSS (`styles/components/dialog.css`),
 * so a theme retimes a dialog without touching this file.
 *
 * The container is a `clip-path` rather than an animated height, as in the
 * block's runtime: the inner blocks are the surface's own children and there
 * is no inner box to resize. The inset carries the row's corner radius so the
 * fold keeps its rounded corners, which an unrounded clip would square off for
 * the length of the animation.
 */

import { useId, useRef } from '@wordpress/element';
import warning from '@wordpress/warning';
import { IconButton } from '../buttons/icon-button';
import { Divider } from '../dividers/divider';
import { Elevation } from '../material/elevation';
import { Icon } from '../material/icon';
import { Surface, useScrollAffordance } from './surface';

const PRESENTATIONS = [ 'basic', 'full-screen' ];
const ROLES = [ 'dialog', 'alertdialog' ];
const CONTENT_KINDS = [ 'default', 'list' ];

/*
 * `surface.yml` `motion.dialog`. Shares of a slot's own fade spent at zero
 * opacity, and the fade's share of the surface's enter duration.
 */
const CONTAINER_TRAVEL = 50;
const FOLDED_HEIGHT = 0.35;
const SLOTS = [
	{ selector: '.ax-dialog__headline', hold: 0.2, share: 0.5 },
	{ selector: '.ax-dialog__body', hold: 0.2, share: 0.5 },
	{ selector: '.ax-dialog__actions', hold: 0.5, share: 0.6 },
];
const EXIT_SLOT_FADE = 2 / 3;

function oneOf( value, allowed, fallback, name ) {
	if ( allowed.includes( value ) ) {
		return value;
	}

	warning( `Dialog: unsupported ${ name } "${ value }". Using "${ fallback }".` );

	return fallback;
}

/**
 * The two ends of the container fold, as `inset()` strings.
 *
 * All four components are percentages so they interpolate component by
 * component; a bare `0` would be a length beside three percentages. The radius
 * comes from CSS so the row keeps its own corner through the fold.
 *
 * @param {HTMLDialogElement} dialog Surface host.
 * @return {{folded: string, open: string}} Clip paths.
 */
function foldPaths( dialog ) {
	const radius = getComputedStyle( dialog ).getPropertyValue( '--ax-surface-fold-radius' ).trim();
	const round = radius ? ` round ${ radius }` : '';
	const inset = ( ( 1 - FOLDED_HEIGHT ) / 2 ) * 100;

	return {
		folded: `inset( ${ inset }% 0% ${ inset }% 0%${ round } )`,
		open: `inset( 0% 0% 0% 0%${ round } )`,
	};
}

/**
 * Fades the scrim.
 *
 * `::backdrop` is reachable through the `pseudoElement` option, and whether
 * there is a scrim to see is CSS's answer, not this function's. That used to be
 * a `data-scrim` attribute set from the presentation, which was wrong in a way
 * worth naming: a full-screen dialog has no scrim only on the window where it
 * is full-screen, and from medium it resolves to a basic dialog and takes that
 * row's scrim. A dataset cannot answer a media query, so the policy moved to
 * the stylesheet and this fades the backdrop unconditionally -- fading a
 * transparent backdrop paints nothing.
 *
 * @param {HTMLDialogElement} dialog  Surface host.
 * @param {Array}             opacity Opacity keyframes.
 * @param {Object}            timing  Easing and duration.
 * @return {Array} One animation.
 */
function animateScrim( dialog, opacity, timing ) {
	return [ dialog.animate( { opacity }, { ...timing, pseudoElement: '::backdrop' } ) ];
}

function animateSlots( dialog, build, duration ) {
	return SLOTS.map( ( slot ) => {
		const element = dialog.querySelector( slot.selector );

		return element ? element.animate( ...build( slot, duration ) ) : null;
	} ).filter( Boolean );
}

function enterDialog( dialog, timing ) {
	const { folded, open } = foldPaths( dialog );

	return [
		dialog.animate(
			{ transform: [ `translateY( -${ CONTAINER_TRAVEL }px )`, 'translateY( 0 )' ] },
			timing
		),
		dialog.animate( { clipPath: [ folded, open ] }, timing ),
		...animateScrim( dialog, [ 0, 1 ], timing ),
		...animateSlots( dialog, ( slot, duration ) => [
			[
				{ opacity: 0, offset: 0 },
				{ opacity: 0, offset: slot.hold },
				{ opacity: 1, offset: 1 },
			],
			{ duration: duration * slot.share, easing: 'linear' },
		], timing.duration ),
	];
}

function exitDialog( dialog, timing ) {
	const { folded, open } = foldPaths( dialog );

	return [
		dialog.animate(
			{ transform: [ 'translateY( 0 )', `translateY( -${ CONTAINER_TRAVEL }px )` ] },
			timing
		),
		dialog.animate( { clipPath: [ open, folded ] }, timing ),
		...animateScrim( dialog, [ 1, 0 ], timing ),
		...animateSlots( dialog, ( slot, duration ) => [
			[ { opacity: 1 }, { opacity: 0 } ],
			{ duration: duration * EXIT_SLOT_FADE, easing: 'linear' },
		], timing.duration ),
	];
}

/**
 * @param {Object} props Component props.
 * @param {import('@wordpress/element').ReactNode} props.children Dialog body.
 * @param {boolean} props.open Whether the dialog is open. Controlled.
 * @param {Function} props.onClose Called when the dialog asks to be closed.
 * @param {'basic'|'full-screen'} [props.presentation='basic'] M3 row.
 * @param {'dialog'|'alertdialog'} [props.role='dialog'] Per-instance, not per-row.
 * @param {'any'|'closerequest'|'none'} [props.closedBy='any'] Dismissal policy.
 * @param {import('@wordpress/element').ReactNode} [props.icon] Optional hero icon.
 * @param {import('@wordpress/element').ReactNode} [props.headline] Headline text.
 * @param {import('@wordpress/element').ReactNode} [props.supportingText] Text above list content.
 * @param {import('@wordpress/element').ReactNode} [props.actions] Up to two actions.
 * @param {boolean} [props.divider=false] Full-width rule before basic-dialog actions.
 * @param {'default'|'list'} [props.contentKind='default'] Basic or list-dialog anatomy.
 * @param {string} [props.closeLabel='Close'] Name for the full-screen close button.
 * @param {string} [props.className] Additional class name.
 * @return {import('@wordpress/element').ReactNode} Dialog surface.
 */
export function Dialog( {
	children,
	open,
	onClose,
	presentation = 'basic',
	role = 'dialog',
	closedBy = 'any',
	icon,
	headline,
	supportingText,
	actions,
	divider = false,
	contentKind = 'default',
	closeLabel = 'Close',
	className,
	...props
} ) {
	const row = oneOf( presentation, PRESENTATIONS, 'basic', 'presentation' );
	const resolvedRole = oneOf( role, ROLES, 'dialog', 'role' );
	const resolvedContentKind = oneOf( contentKind, CONTENT_KINDS, 'default', 'contentKind' );
	const bodyRef = useRef( null );
	const headlineId = `${ useId() }-headline`;
	const bodyReachable = useScrollAffordance( bodyRef, open );
	const fullScreen = 'full-screen' === row;
	const listContent = 'list' === resolvedContentKind;

	/*
	 * A full-screen dialog's close icon is its only navigation, so without a
	 * handler it has no way out at all. A basic dialog can legitimately be
	 * dismissed by Escape or the scrim, so this is stated for the row that
	 * cannot.
	 */
	if ( fullScreen && ! onClose ) {
		warning(
			'Dialog: a full-screen dialog needs `onClose`. Its close icon button is the only navigation in its app bar.'
		);
	}

	const closeButton = fullScreen ? (
		<IconButton
			className="ax-dialog__close"
			label={ closeLabel }
			icon={ <Icon name="close" /> }
			variant="standard"
			onClick={ onClose }
		/>
	) : null;

	const headlineNode = headline ? (
		<h2 className="ax-dialog__headline" id={ headlineId }>
			{ headline }
		</h2>
	) : null;
	const bodyNode = (
		<div
			className={ [ 'ax-dialog__body', listContent ? 'ax-dialog__body--list' : null ]
				.filter( Boolean )
				.join( ' ' ) }
			ref={ bodyRef }
			// Marks this as the surface's scrolling region, so the initial
			// focus rule skips it. Chrome focuses a scrolling region on
			// open even when it is not in the sequential focus order, and
			// M3 wants the first interactive element instead.
			data-surface-scroll=""
			// Section 7 of the escape record: a tab stop only when the body
			// overflows and nothing inside it can take focus.
			tabIndex={ bodyReachable ? 0 : undefined }
			role={ bodyReachable ? 'group' : undefined }
			aria-labelledby={ bodyReachable && headline ? headlineId : undefined }
		>
			{ children }
		</div>
	);

	return (
		<Surface
			{ ...props }
			open={ open }
			onClose={ onClose }
			modality="modal"
			display="floating"
			closedBy={ closedBy }
			role={ 'alertdialog' === resolvedRole ? 'alertdialog' : undefined }
			labelledBy={ headline ? headlineId : undefined }
			enter={ enterDialog }
			exit={ exitDialog }
			className={ [ 'ax-dialog', className ].filter( Boolean ).join( ' ' ) }
			data-presentation={ row }
			data-content-kind={ resolvedContentKind }
			data-has-icon={ icon ? '' : undefined }
		>
			{ /*
			 * Level 3 for a basic dialog, 0 for a full-screen one. The level is
			 * one number here and the full-screen row hides the layer in CSS,
			 * because that row becomes a basic dialog from medium and a media
			 * query cannot outbid the primitive's inline custom property.
			 */ }
			<Elevation level={ 3 } />

			{ fullScreen ? (
				<header className="ax-dialog__header">
					{ closeButton }
					{ headlineNode }
					{ actions ? <div className="ax-dialog__actions">{ actions }</div> : null }
				</header>
			) : listContent ? (
				<>
					<div className="ax-dialog__content ax-dialog__content--list">
						<div className="ax-dialog__heading">
							{ icon ? <div className="ax-dialog__icon">{ icon }</div> : null }
							{ headlineNode }
						</div>
						{ supportingText ? (
							<div className="ax-dialog__supporting-text">{ supportingText }</div>
						) : null }
					</div>
					{ bodyNode }
				</>
			) : (
				<div className="ax-dialog__content">
					<div className="ax-dialog__heading">
						{ icon ? <div className="ax-dialog__icon">{ icon }</div> : null }
						{ headlineNode }
					</div>
					{ bodyNode }
				</div>
			) }

			{ fullScreen ? bodyNode : null }

			{ ! fullScreen && divider && actions ? <Divider className="ax-dialog__divider" /> : null }
			{ ! fullScreen && actions ? (
				<div className="ax-dialog__actions">{ actions }</div>
			) : null }
		</Surface>
	);
}
