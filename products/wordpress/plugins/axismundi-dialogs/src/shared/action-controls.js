/**
 * What a button does.
 *
 * ONE AXIS, AND THE MARKUP FOLLOWS FROM IT. The other way round - letting an
 * author pick `role`, `aria-haspopup`, `aria-expanded` and hoping a behaviour
 * falls out - cannot work, because ARIA describes behaviour that already
 * exists rather than creating it. `role="tab"` without a tablist, arrow keys
 * and a panel is a broken tab; `aria-haspopup="menu"` with no menu is a
 * statement to a screen reader that is not true. So the author says what the
 * button is for, and this file decides the attributes:
 *
 *   Command (default)   <button type="button">
 *   Submit / Reset      type="submit" / "reset" - stored in `type`, as
 *                       core/button stores it
 *   Open Overlay        aria-haspopup="dialog", aria-controls, aria-expanded
 *   template
 *   Navigation overlay  a plain command that closes the surface it is inside,
 *   close               so it needs no ARIA of its own
 *
 * The platform is going the same way: `command` / `commandfor` declares the
 * action and leaves the semantics to the browser.
 *
 * An escape hatch belongs in Advanced, beside the HTML anchor, for an author
 * who knows exactly which ARIA they want. This file is the safe path, not the
 * only one.
 */
import { __ } from '@wordpress/i18n';

export const ACTION_OPTIONS = [
	{ label: __( 'Command', 'axismundi-dialogs' ), value: '' },
	{ label: __( 'Submit form', 'axismundi-dialogs' ), value: 'submit' },
	{ label: __( 'Reset form', 'axismundi-dialogs' ), value: 'reset' },
	// Core's own noun for the thing being opened: "The Navigation Overlay
	// template defines an overlay area that typically contains navigation
	// links and can be toggled open and closed."
	{ label: __( 'Open Overlay template', 'axismundi-dialogs' ), value: 'overlay' },
	{ label: __( 'Navigation overlay close', 'axismundi-dialogs' ), value: 'overlay-close' },
];

// Actions that open something, and the template-part area their targets live
// in. An area is exactly "the parts that belong in this kind of surface", which
// is why the action can name one.
export const ACTION_AREAS = {
	overlay: 'navigation-overlay',
};

/**
 * Whether an action runs its own click.
 *
 * Opening and closing are the button's click, so the button cannot also be a
 * toggle: the group would write its own click directive over this one. Such a
 * button still wears the selected look while its surface is open, through
 * `aria-expanded` (assets/button.css) - semantics and appearance stay separate.
 * Mirrors axismundi_dialogs_action_owns_click() in includes/action.php.
 *
 * @param {string} action The stored action.
 * @return {boolean} Whether the action owns the click.
 */
export function actionOwnsClick( action ) {
	return action === 'overlay' || action === 'overlay-close';
}

/**
 * The attribute changes an action choice makes.
 *
 * `type` carries submit and reset because that is where core/button keeps them
 * and where the transforms already look; nothing is stored twice.
 *
 * @param {string} value The chosen action.
 * @return {Object} Attributes to set.
 */
export function actionAttributes( value ) {
	return {
		action: value || undefined,
		type: value === 'submit' || value === 'reset' ? value : undefined,
		// A new action draws its targets from a different area, so the old
		// target cannot travel with it.
		actionTarget: undefined,
		// Every action but Command is a <button>'s: a link cannot submit, reset,
		// open or close anything. Choosing Command leaves the element alone.
		...( value ? { tagName: undefined } : {} ),
	};
}

/**
 * The markup an action produces, as a line an author can read.
 *
 * Shown in Advanced and never editable: it is the consequence of the choice
 * above, so making it editable would give two sources for one answer.
 *
 * @param {Object} attributes Block attributes.
 * @param {string} tag        The element being rendered.
 * @return {string} The attributes, in source order.
 */
export function actionMarkup( attributes, tag = 'button' ) {
	const { action, actionTarget, disabled, togglable, selected } = attributes;
	const parts = [];

	if ( 'a' === tag ) {
		parts.push( 'href' );
	} else {
		parts.push( `type="${ action === 'submit' || action === 'reset' ? action : 'button' }"` );
	}

	if ( action === 'overlay' ) {
		// Without a template the server renders a plain button rather than
		// announce a popup that is not there, so this says the same.
		if ( actionTarget ) {
			parts.push( 'aria-haspopup="dialog"', 'aria-controls="…"', 'aria-expanded="false"' );
		}
	} else if ( action === 'overlay-close' ) {
		// Nothing to add: closing is a command, and the surface it closes is
		// the one it sits in.
	} else if ( togglable ) {
		parts.push( `aria-pressed="${ selected ? 'true' : 'false' }"` );
	}

	if ( disabled ) {
		parts.push( 'a' === tag ? 'aria-disabled="true"' : 'disabled' );
	}

	return `<${ tag } ${ parts.join( ' ' ) }>`;
}
