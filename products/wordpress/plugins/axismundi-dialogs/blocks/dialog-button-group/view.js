/**
 * axismundi/dialog-button-group - front-end Interactivity store.
 *
 * Runs the group's selection rule when a visitor presses a toggle. The shape
 * follows core/accordion, which solves the same problem (one open item, or
 * many): the group's context holds every member's state, and each member knows
 * only its own position in it.
 *
 *   group context   { selection, required, pressed: [ bool, ... ] }
 *   toggle context  { index }   - merged over the group's
 *
 * One context per group, so two groups on a page never share a selection. The
 * initial `pressed` is the author's `selected`, already put inside the rule by
 * the server (axismundi_dialogs_button_group_selection); from then on the store owns
 * the current state and nothing is written back to the block. Keeping it is the
 * integrator's business.
 *
 * The rule is the one in src/shared/selection.js: single allows one selected
 * toggle, multiple any number, and a required selection may not become empty.
 *
 * Nothing lives at module scope, so a page reached by client-side navigation
 * starts from its own server-rendered context like any other.
 */
import { getContext, store } from '@wordpress/interactivity';

store( 'axismundi/dialog-button-group', {
	state: {
		get isPressed() {
			const { index, pressed } = getContext();
			return !! pressed[ index ];
		},
	},
	actions: {
		toggle() {
			const context = getContext();
			const { index, pressed } = context;
			const next = ! pressed[ index ];

			// The last selected toggle stays on while a selection is required.
			if ( ! next && context.required && pressed.filter( Boolean ).length === 1 ) {
				return;
			}
			if ( next && context.selection === 'single' ) {
				pressed.forEach( ( _, i ) => {
					pressed[ i ] = false;
				} );
			}
			pressed[ index ] = next;
		},
	},
} );
