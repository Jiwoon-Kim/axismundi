/**
 * Toggle and selection rules for a Dialog Button Group (M3 Standard button
 * group). Three separate ideas, each owned where it belongs:
 *
 *   Togglable  whether a button is a toggle - takes part in selection at all.
 *              The group supplies the default, a button may say otherwise, so
 *              a group of actions can hold one toggle (Save, Share, ★) and a
 *              selecting group can hold one plain action.
 *   Selection  how the group's toggles relate: single or multiple. Multiple is
 *              the fallback, since toggles that do not constrain each other
 *              are what a group of independent toggles already is.
 *   Selected   a toggle's initial state. Meaningless on a button that is not a
 *              toggle; kept there anyway, so turning it back on restores it.
 *
 * Every toggle uses the same markup - a <button aria-pressed> - so single and
 * multiple differ only in how many toggles may be selected at once:
 *
 *   single               0..1        single + required    exactly 1
 *   multiple             0..n        multiple + required  1..n
 *
 * `selectionRequired` is one constraint for both modes: the selection may not
 * become empty. The M3 sources in this repository list "selection-required"
 * beside single- and multi-select without saying which modes it applies to,
 * so it is allowed for both; narrow it here if the M3 original restricts it.
 *
 * What a visitor selects afterwards is runtime state and never written back to
 * the block. Mirrored in PHP (axismundi_dialogs_button_group_selection).
 */
import { actionOwnsClick } from './action-controls';

/**
 * Whether a button is a toggle: its own choice, else the group's default -
 * unless its Action runs the click, which a toggle's click would replace.
 *
 * @param {Object}  attributes     Button attributes.
 * @param {boolean} groupTogglable The group's default.
 * @return {boolean} Whether the button is a toggle.
 */
export function isTogglable( attributes, groupTogglable ) {
	if ( actionOwnsClick( attributes.action ) ) {
		return false;
	}
	return attributes.togglable ?? !! groupTogglable;
}

/**
 * The attribute changes that bring a group's toggles back inside its rule.
 *
 * Keeps the first selected toggle in single mode and selects the first toggle
 * when a selection is required and none is. A link cannot be a toggle: one the
 * group's default reached stops being a toggle, one the author made a toggle
 * becomes a `<button>`. Buttons that are not toggles are left alone, `selected`
 * included.
 *
 * @param {Array}  buttons Inner blocks, in order.
 * @param {Object} group   Group attributes.
 * @return {Object} Attribute changes keyed by client ID; empty when valid.
 */
export function selectionChanges( buttons, group ) {
	const changes = {};
	const change = ( clientId, attributes ) => {
		changes[ clientId ] = { ...changes[ clientId ], ...attributes };
	};
	/*
	 * A link cannot be a toggle - it goes somewhere, it does not hold a state -
	 * and the server agrees: the runtime's directives are only ever written on
	 * a <button> (axismundi_dialogs_button_group_selection). Which of the two gives
	 * way depends on who said the button was a toggle:
	 *
	 *   inherited  the group's default reached a link that was already there.
	 *              The link stays a link and opts out, which is what
	 *              `togglable` on a button is for - a group of toggles holding
	 *              one plain action. Turning it into a <button> instead would
	 *              silently stop an existing link from going anywhere, with
	 *              its URL still stored and no longer used.
	 *   explicit   the author turned this button's own Togglable on, knowing
	 *              what it is. That is a request, so the link becomes a button.
	 */
	const optedOut = new Set();
	buttons.forEach( ( { clientId, attributes } ) => {
		if ( 'a' !== attributes.tagName || ! isTogglable( attributes, group.togglable ) ) {
			return;
		}
		if ( attributes.togglable ) {
			change( clientId, { tagName: 'button' } );
		} else {
			change( clientId, { togglable: false } );
			optedOut.add( clientId );
		}
	} );

	const toggles = buttons.filter(
		( { clientId, attributes } ) =>
			! optedOut.has( clientId ) && isTogglable( attributes, group.togglable )
	);

	const selected = toggles.filter( ( { attributes } ) => attributes.selected );
	if ( group.selection === 'single' ) {
		selected.slice( 1 ).forEach( ( { clientId } ) => change( clientId, { selected: undefined } ) );
	}
	if ( group.selectionRequired && ! selected.length && toggles.length ) {
		change( toggles[ 0 ].clientId, { selected: true } );
	}
	return changes;
}
